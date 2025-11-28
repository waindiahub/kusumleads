const API_URL = 'https://sandybrown-gull-863456.hostingersite.com/api/leads/ingest_google_sheet';
const MAX_RETRIES = 3;

/* =========================================================
   1️⃣  Handle manual edits (only when user types)
   ========================================================= */
function onEdit(e) {
  try {
    if (!e || !e.range || !e.source) return;

    const sheet = e.range.getSheet();
    const row = e.range.getRow();
    if (row === 1 || sheet.getName() === "API_Logs") return;

    const lastCol = sheet.getLastColumn();
    const headers = sheet.getRange(1, 1, 1, lastCol).getValues()[0];
    const rowData = sheet.getRange(row, 1, 1, lastCol).getValues()[0];

    const syncIndex = headers.indexOf("synced");
    if (syncIndex === -1) return;

    // Skip already synced
    if (rowData[syncIndex] === "YES") return;

    // Skip empty row
    if (!rowData[0]) return;

    const leadData = buildLeadData(headers, rowData, sheet);

    sendToAPI(leadData);

    // Mark as synced
    sheet.getRange(row, syncIndex + 1).setValue("YES");

  } catch (error) {
    logError("onEdit Error", error.toString(), {});
  }
}

/* =========================================================
   2️⃣  Backup scanner runs every minute (catches Meta rows)
   ========================================================= */
function backupScan() {
  const ss = SpreadsheetApp.getActiveSpreadsheet();
  const sheet = ss.getActiveSheet();

  if (sheet.getName() === "API_Logs") return;

  const lastRow = sheet.getLastRow();
  const lastCol = sheet.getLastColumn();
  const headers = sheet.getRange(1, 1, 1, lastCol).getValues()[0];

  const syncIndex = headers.indexOf("synced");
  if (syncIndex === -1) return;

  for (let row = 2; row <= lastRow; row++) {
    const rowData = sheet.getRange(row, 1, 1, lastCol).getValues()[0];

    if (rowData[syncIndex] === "YES") continue;     // skip already synced
    if (!rowData[0]) continue;                     // skip empty rows

    const leadData = buildLeadData(headers, rowData, sheet);

    sendToAPI(leadData);

    sheet.getRange(row, syncIndex + 1).setValue("YES");
  }
}

/* =========================================================
   3️⃣  Helper: Build Lead JSON
   ========================================================= */
function buildLeadData(headers, rowData, sheet) {
  const leadData = {};

  headers.forEach((h, i) => {
    leadData[h.trim()] = rowData[i];
  });

  // External ID - prefer existing external_id or id field, otherwise use first column
  if (!leadData.external_id && !leadData.id) {
    leadData.external_id = rowData[0];
  } else if (leadData.id && !leadData.external_id) {
    leadData.external_id = leadData.id;
  }
  
  leadData.sheet_source = sheet.getName();

  // Fix Hindi question column
  const hindiKey = Object.keys(leadData).find(k =>
    k.includes("क्या") || k.includes("मेडिकल")
  );
  if (hindiKey) {
    leadData["hindi_question"] = leadData[hindiKey];
  }

  // Phone cleanup
  if (leadData.phone_number && typeof leadData.phone_number === "string") {
    leadData.phone_number = leadData.phone_number.replace(/^p:/, "").trim();
  }

  // Date cleanup
  if (leadData.created_time) {
    const parsed = new Date(leadData.created_time);
    if (!isNaN(parsed.getTime())) {
      leadData.created_time = parsed.toISOString();
    }
  }

  return leadData;
}

/* =========================================================
   4️⃣  Send lead to PHP API with retry
   ========================================================= */
function sendToAPI(leadData, retryCount = 0) {
  try {
    // Validate required fields before sending
    const required = ['external_id', 'created_time', 'full_name', 'phone_number'];
    const missing = required.filter(field => !leadData[field] || leadData[field] === '');
    
    if (missing.length > 0) {
      throw new Error(`Missing required fields: ${missing.join(', ')}. Data: ${JSON.stringify(leadData)}`);
    }
    
    const response = UrlFetchApp.fetch(API_URL, {
      method: "POST",
      contentType: "application/json",
      payload: JSON.stringify(leadData)
    });

    const code = response.getResponseCode();
    const text = response.getContentText();

    // Parse JSON response to check success field
    let responseData;
    try {
      responseData = JSON.parse(text);
    } catch (e) {
      throw new Error(`Invalid JSON response: ${text}`);
    }

    // Check both HTTP status and JSON success field
    if (code === 200 && responseData.success === true) {
      logSuccess("Lead Ingested", leadData.external_id, responseData.message || text);
    } else {
      // Even if HTTP 200, if success is false, it's an error
      const errorMsg = responseData.message || responseData.error || text;
      throw new Error(`HTTP ${code} - ${errorMsg}`);
    }

  } catch (error) {
    if (retryCount < MAX_RETRIES) {
      Utilities.sleep(Math.pow(2, retryCount) * 1000);
      return sendToAPI(leadData, retryCount + 1);
    }
    logError("API Error", error.toString(), leadData);
  }
}

/* =========================================================
   5️⃣ Logging helpers
   ========================================================= */
function getLogSheet() {
  const ss = SpreadsheetApp.getActiveSpreadsheet();
  let logSheet = ss.getSheetByName("API_Logs");

  if (!logSheet) {
    logSheet = ss.insertSheet("API_Logs");
    logSheet.appendRow(["Timestamp", "Status", "Action", "Lead ID", "Message", "Data"]);
  }
  return logSheet;
}

function logSuccess(action, leadId, message) {
  getLogSheet().appendRow([new Date(), "SUCCESS", action, leadId, message, ""]);
}

function logError(action, error, leadData) {
  getLogSheet().appendRow([
    new Date(),
    "ERROR",
    action,
    leadData?.external_id || "Unknown",
    error,
    JSON.stringify(leadData || {})
  ]);
}

/* =========================================================
   6️⃣ Install triggers automatically
   ========================================================= */
function setupTriggers() {
  ScriptApp.getProjectTriggers().forEach(t => ScriptApp.deleteTrigger(t));

  // Trigger 1: Manual edits
  ScriptApp.newTrigger("onEdit")
    .forSpreadsheet(SpreadsheetApp.getActive())
    .onEdit()
    .create();

  // Trigger 2: Metadata (auto-insert) leads
  ScriptApp.newTrigger("backupScan")
    .timeBased()
    .everyMinutes(1)
    .create();
}
