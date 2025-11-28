<?php
// Handle CORS
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once 'config.php';
require_once 'jwt_helper.php';

// Load send_onesignal.php with proper path resolution (optional - notifications handled by triggers)
// Try multiple paths to find the file
$sendOnesignalPaths = [
    dirname(__DIR__) . '/send_onesignal.php',  // phpcode/send_onesignal.php
    __DIR__ . '/../send_onesignal.php',       // Alternative relative path
];

// Only try to load if file exists, don't fail if missing
$sendOnesignalLoaded = false;
foreach ($sendOnesignalPaths as $path) {
    if (file_exists($path)) {
        @require_once $path;
        $sendOnesignalLoaded = true;
        break;
    }
}

// Note: Notifications are sent via MySQL triggers, so this file may not be needed here
// If it's missing, it's not critical for lead ingestion to work

// Only execute routing if not being called directly (i.e., not from test_lead_api.php)
if (!defined('SKIP_LEADS_ROUTING')) {
    $method = $_SERVER['REQUEST_METHOD'];
    $path   = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

/*
|-------------------------------------------------------------------------- 
| ROUTING
|-------------------------------------------------------------------------- 
*/
if ($method === 'POST' && str_contains($path, '/leads/ingest_google_sheet')) {
    ingestGoogleSheet();
}

elseif ($method === 'GET' && preg_match('/\/leads\/(\d+)$/', $path, $matches)) {
    getLeadDetail($matches[1]);
}

elseif ($method === 'POST' && preg_match('/\/leads\/(\d+)\/assign/', $path, $matches)) {
    assignLead($matches[1]);
}

elseif ($method === 'POST' && preg_match('/\/leads\/(\d+)\/response/', $path, $matches)) {
    submitResponse($matches[1]);
}

elseif ($method === 'GET' && str_contains($path, '/leads')) {
    getLeads();
}

elseif ($method === 'GET' && preg_match('/\/leads\/(\d+)\/timeline/', $path, $matches)) {
    getLeadTimeline($matches[1]);
}

elseif ($method === 'GET' && preg_match('/\/leads\/(\d+)\/notes/', $path, $matches)) {
    getLeadNotes($matches[1]);
}

elseif ($method === 'POST' && str_contains($path, '/leads/scoring_rescore')) {
    $token = validateJWT();
    if (!$token || $token['role'] !== 'admin') sendResponse(false, 'Admin required');
    require_once 'lead_scoring.php';
    $updated = LeadScoring::scoreAllLeads();
    sendResponse(true, 'Rescored', ['updated' => $updated]);
}

else {
    sendResponse(false, 'Invalid endpoint');
}
}

/*
|-------------------------------------------------------------------------- 
| INGEST GOOGLE SHEET LEAD (Improved, base = working file)
|-------------------------------------------------------------------------- 
*/
function ingestGoogleSheet() {
    // Read input from php://input
    $rawInput = file_get_contents("php://input");
    $input = json_decode($rawInput, true);
    
    // Call the main function with the parsed data
    ingestGoogleSheetWithData($input, $rawInput);
}

function ingestGoogleSheetWithData($input, $rawInput = null) {
    // If rawInput not provided, try to get it (may be empty if already read)
    if ($rawInput === null) {
        $rawInput = file_get_contents("php://input");
    }

    // Log incoming request
    logError("REQUEST", "Incoming lead ingestion request", [
        'raw_input_length' => strlen($rawInput),
        'json_decode_success' => $input !== null,
        'request_method' => $_SERVER['REQUEST_METHOD'],
        'request_uri' => $_SERVER['REQUEST_URI'] ?? 'unknown'
    ]);

    if (!$input) {
        logError("VALIDATION", "Invalid JSON payload", ['raw_input' => substr($rawInput, 0, 500)]);
        sendResponse(false, "Invalid JSON payload");
    }

    // Accept either external_id or id (Google Sheet may send id)
    $input['external_id'] = $input['external_id'] ?? $input['id'] ?? null;

    // Required fields - check if they exist and are not empty strings
    $required = ['external_id', 'created_time', 'full_name', 'phone_number'];
    foreach ($required as $field) {
        if (!isset($input[$field]) || $input[$field] === '' || $input[$field] === null) {
            logError("VALIDATION", "Missing required field: $field", [
                'received_fields' => array_keys($input),
                'input_data' => $input
            ]);
            sendResponse(false, "Missing required field: $field. Received data: " . json_encode(array_keys($input)));
        }
    }

    /*
    |----------------------------------------------------------------------
    | PHONE CLEANUP (robust)
    | remove leading "p:", trim, keep + and digits
    |----------------------------------------------------------------------
    */
    $rawPhone = trim($input['phone_number']);
    $rawPhone = preg_replace('/^p:/i', '', $rawPhone); // remove leading p:
    // keep + and digits only
    $phone = preg_replace('/[^0-9+]/', '', $rawPhone);
    // if leading +, keep it; else remove leading zeros
    if (substr($phone, 0, 1) !== '+') {
        $phone = ltrim($phone, '0');
    } else {
        // optional: remove leading + to store just digits; comment/uncomment per DB preference
        // $phone = ltrim($phone, '+');
    }
    
    // Validate phone is not empty after cleanup
    if (empty($phone)) {
        logError("VALIDATION", "Phone number is invalid or empty after cleanup", [
            'original_phone' => $input['phone_number'],
            'cleaned_phone' => $phone
        ]);
        sendResponse(false, "Phone number is invalid or empty after cleanup. Original: " . $input['phone_number']);
    }

    /*
    |----------------------------------------------------------------------
    | DATE PARSING FIX (Excel numbers, ISO/GMT strings, fallback now)
    |----------------------------------------------------------------------
    */
    $rawDate = $input['created_time'];

    if (is_numeric($rawDate)) {
        // Excel numeric date to unix
        $unix = ($rawDate - 25569) * 86400;
        $createdTime = date("Y-m-d H:i:s", (int)$unix);
    } elseif (strtotime($rawDate) !== false) {
        // Accept ISO/GMT/timezone-aware strings
        $createdTime = date("Y-m-d H:i:s", strtotime($rawDate));
    } else {
        // fallback to current time
        $createdTime = date("Y-m-d H:i:s");
    }

    /*
    |----------------------------------------------------------------------
    | HINDI QUESTION AUTOMATIC DETECTION
    | Apps Script may set 'hindi_question' OR the original Hindi header may exist
    |----------------------------------------------------------------------
    */
    $questionField = null;

    if (isset($input['hindi_question'])) {
        $questionField = $input['hindi_question'];
    } elseif (isset($input['क्या_आप_मेडिकल_क्षेत्र_में_अपना_भविष्य_बनाना_चाहते_है_?'])) {
        $questionField = $input['क्या_आप_मेडिकल_क्षेत्र_में_अपना_भविष्य_बनाना_चाहते_है_?'];
    } else {
        // generic scan for any key containing Hindi words
        foreach ($input as $k => $v) {
            if (is_string($k) && (strpos($k, "क्या") !== false || strpos($k, "मेडिकल") !== false)) {
                $questionField = $v;
                break;
            }
        }
    }

    /*
    |----------------------------------------------------------------------
    | Normalize is_organic => 0/1 (Sheet might provide "true"/"false")
    |----------------------------------------------------------------------
    */
    if (isset($input['is_organic'])) {
        $isOrganic = ($input['is_organic'] === true || $input['is_organic'] === 'true' || $input['is_organic'] === '1' || $input['is_organic'] == 1) ? 1 : 0;
    } else {
        $isOrganic = 0;
    }

    try {
        $db = getDB();
        
        if (!$db) {
            throw new Exception("Database connection failed");
        }

        // Check duplicate by external_id
        $stmt = $db->prepare("SELECT id FROM leads WHERE external_id = ?");
        $stmt->execute([$input['external_id']]);
        $existing = $stmt->fetch();
        
        logError("DB_CHECK", "Checking for existing lead", [
            'external_id' => $input['external_id'],
            'exists' => $existing !== false,
            'existing_id' => $existing['id'] ?? null
        ]);

        if ($existing) {
            /*
            |------------------------------------------------------------------
            | UPDATE EXISTING LEAD
            |------------------------------------------------------------------
            */
            $stmt = $db->prepare("
                UPDATE leads SET 
                  created_time = ?, ad_id = ?, ad_name = ?, adset_id = ?, adset_name = ?,
                  campaign_id = ?, campaign_name = ?, form_id = ?, form_name = ?,
                  is_organic = ?, platform = ?, question_text = ?, 
                  full_name = ?, phone_number = ?, city = ?, lead_status = ?, 
                  sheet_source = ?, raw_json = ?, updated_at = NOW()
                WHERE external_id = ?
            ");

            $stmt->execute([
                $createdTime,
                $input['ad_id'] ?? null, $input['ad_name'] ?? null,
                $input['adset_id'] ?? null, $input['adset_name'] ?? null,
                $input['campaign_id'] ?? null, $input['campaign_name'] ?? null,
                $input['form_id'] ?? null, $input['form_name'] ?? null,
                $isOrganic, $input['platform'] ?? null,
                $questionField,
                $input['full_name'], $phone, $input['city'] ?? null,
                $input['lead_status'] ?? 'New', $input['sheet_source'] ?? null,
                json_encode($input),
                $input['external_id']
            ]);

            $leadId = $existing['id'];
            logSuccess("UPDATE", "Lead updated successfully", [
                'lead_id' => $leadId,
                'external_id' => $input['external_id']
            ]);
        }

        else {
            /*
            |------------------------------------------------------------------
            | INSERT NEW LEAD
            |------------------------------------------------------------------
            */
            $stmt = $db->prepare("
                INSERT INTO leads (
                    external_id, created_time, ad_id, ad_name, adset_id, adset_name,
                    campaign_id, campaign_name, form_id, form_name, 
                    is_organic, platform, question_text, full_name,
                    phone_number, city, lead_status, sheet_source, raw_json
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");

            $stmt->execute([
                $input['external_id'], $createdTime,
                $input['ad_id'] ?? null, $input['ad_name'] ?? null,
                $input['adset_id'] ?? null, $input['adset_name'] ?? null,
                $input['campaign_id'] ?? null, $input['campaign_name'] ?? null,
                $input['form_id'] ?? null, $input['form_name'] ?? null,
                $isOrganic, $input['platform'] ?? null,
                $questionField, $input['full_name'], $phone,
                $input['city'] ?? null, $input['lead_status'] ?? 'New',
                $input['sheet_source'] ?? null, json_encode($input)
            ]);

            $leadId = $db->lastInsertId();
            
            // Verify insert was successful
            if (!$leadId || $leadId === 0) {
                logError("DB_INSERT", "Failed to insert lead - lastInsertId returned 0", [
                    'external_id' => $input['external_id'],
                    'last_insert_id' => $leadId,
                    'input_data' => $input
                ]);
                throw new Exception("Failed to insert lead - lastInsertId returned 0");
            }
            
            logSuccess("INSERT", "Lead inserted successfully", [
                'lead_id' => $leadId,
                'external_id' => $input['external_id'],
                'phone' => $phone,
                'created_time' => $createdTime
            ]);

            /*
            |------------------------------------------------------------------
            | AUTO SCORE LEAD
            |------------------------------------------------------------------
            */
            require_once 'lead_scoring.php';
            $leadData = array_merge($input, ['id' => $leadId, 'created_time' => $createdTime, 'phone_number' => $phone, 'is_organic' => $isOrganic]);
            LeadScoring::updateLeadScore($leadId, $leadData);

            /*
            |------------------------------------------------------------------
            | AUTO ASSIGN TO AGENT + SEND NOTIFICATION
            |------------------------------------------------------------------
            */
            $agentId = autoAssignLead($leadId, $input['form_name'] ?? null);

            // Notification will be sent automatically via MySQL trigger
        }

        logSuccess("SUCCESS", "Lead processed successfully", [
            'lead_id' => $leadId,
            'external_id' => $input['external_id']
        ]);
        
        sendResponse(true, "Lead processed successfully", ['lead_id' => $leadId]);

    } catch (PDOException $e) {
        http_response_code(500);
        logError("DB_ERROR", "Database error: " . $e->getMessage(), [
            'external_id' => $input['external_id'] ?? 'unknown',
            'error_code' => $e->getCode(),
            'sql_state' => $e->errorInfo[0] ?? null,
            'input_data' => $input
        ]);
        sendResponse(false, "Database error: " . $e->getMessage());
    } catch (Exception $e) {
        http_response_code(500); // Internal Server Error for database/processing errors
        logError("PROCESSING_ERROR", "Lead processing failed: " . $e->getMessage(), [
            'external_id' => $input['external_id'] ?? 'unknown',
            'error_trace' => $e->getTraceAsString(),
            'input_data' => $input
        ]);
        sendResponse(false, "Lead processing failed: " . $e->getMessage());
    }
}

/*
|-------------------------------------------------------------------------- 
| AUTO ASSIGN LEAD (unchanged logic)
|-------------------------------------------------------------------------- 
*/
function autoAssignLead($leadId, $formName = null) {
    $db = getDB();

    /*
    | Form-based assignment (using form_name)
    */
    if ($formName) {
        $stmt = $db->prepare("SELECT id FROM agents WHERE JSON_CONTAINS(assigned_forms, ?)");
        $stmt->execute([json_encode([$formName])]);

        $agent = $stmt->fetch();

        if ($agent) {
            assignLeadToAgent($leadId, $agent['id']);
            return $agent['id'];
        }
    }

    /*
    | Round robin (MySQL safe)
    */
    $stmt = $db->prepare("
        SELECT a.id 
        FROM agents a 
        JOIN users u ON a.id = u.id
        WHERE u.active = 1
        ORDER BY (a.last_assignment IS NULL) DESC, a.last_assignment ASC
        LIMIT 1
    ");
    $stmt->execute();
    $agent = $stmt->fetch();

    if ($agent) {
        assignLeadToAgent($leadId, $agent['id']);

        $stmt2 = $db->prepare("UPDATE agents SET last_assignment = NOW() WHERE id = ?");
        $stmt2->execute([$agent['id']]);

        return $agent['id'];
    }

    return null;
}

function assignLeadToAgent($leadId, $agentId, $assignedBy = null) {
    $db = getDB();
    $stmt = $db->prepare("
        INSERT INTO lead_assignments (lead_id, agent_id, assigned_by, assigned_at)
        VALUES (?, ?, ?, NOW())
    ");
    $stmt->execute([$leadId, $agentId, $assignedBy]);
}

/*
|-------------------------------------------------------------------------- 
| GET LEADS FOR ADMIN OR AGENT
|-------------------------------------------------------------------------- 
*/
function getLeads() {
    $token = validateJWT();
    if (!$token) sendResponse(false, "Authentication required");

    $agentId = $_GET['agent_id'] ?? null;
    $status  = $_GET['status'] ?? null;
    $dateFilter = $_GET['date_filter'] ?? null;
    $campaign = $_GET['campaign'] ?? null;
    $search = $_GET['search'] ?? null;

    $page  = (int)($_GET['page'] ?? 1);
    $limit = (int)($_GET['limit'] ?? 20);
    $offset = ($page - 1) * $limit;

    $db = getDB();

    $where = [];
    $params = [];

    if ($token['role'] === 'agent') {
        $where[] = "la.agent_id = ?";
        $params[] = $token['user_id'];
    } elseif ($agentId) {
        $where[] = "la.agent_id = ?";
        $params[] = $agentId;
    }

    if ($status) {
        $where[] = "la.status = ?";
        $params[] = $status;
    }

    if ($dateFilter) {
        switch ($dateFilter) {
            case 'today':
                $where[] = "DATE(la.assigned_at) = CURDATE()";
                break;
            case 'week':
                $where[] = "la.assigned_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
                break;
            case 'month':
                $where[] = "la.assigned_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
                break;
        }
    }

    if ($campaign) {
        $where[] = "l.campaign_name = ?";
        $params[] = $campaign;
    }

    if ($search) {
        $where[] = "(l.full_name LIKE ? OR l.phone_number LIKE ? OR l.city LIKE ? OR l.campaign_name LIKE ?)";
        $searchTerm = "%$search%";
        $params[] = $searchTerm;
        $params[] = $searchTerm;
        $params[] = $searchTerm;
        $params[] = $searchTerm;
    }

    $whereSQL = $where ? "WHERE ".implode(" AND ", $where) : "";

    $stmt = $db->prepare("
        SELECT l.*, la.status AS assignment_status, la.assigned_at,
               la.agent_id, u.name AS agent_name
        FROM leads l
        JOIN lead_assignments la ON l.id = la.lead_id
        JOIN users u ON la.agent_id = u.id
        $whereSQL
        ORDER BY l.priority_level DESC, l.lead_score DESC, la.assigned_at DESC
        LIMIT $limit OFFSET $offset
    ");

    $stmt->execute($params);
    $rows = $stmt->fetchAll();

    sendResponse(true, "Leads retrieved", $rows);
}

/*
|-------------------------------------------------------------------------- 
| SINGLE LEAD DETAIL
|-------------------------------------------------------------------------- 
*/
function getLeadDetail($leadId) {
    $token = validateJWT();
    if (!$token) sendResponse(false, "Authentication required");

    $db = getDB();

    $stmt = $db->prepare("
        SELECT l.*, la.agent_id, la.status AS assignment_status, la.assigned_at,
               u.name AS agent_name,
               ar.response_text, ar.response_status, ar.price_offered
        FROM leads l
        JOIN lead_assignments la ON l.id = la.lead_id
        JOIN users u ON la.agent_id = u.id
        LEFT JOIN agent_responses ar ON l.id = ar.lead_id
        WHERE l.id = ?
    ");
    $stmt->execute([$leadId]);
    $lead = $stmt->fetch();

    if (!$lead) sendResponse(false, "Lead not found");

    if ($token['role'] === 'agent' && $lead['agent_id'] != $token['user_id']) {
        sendResponse(false, "Access denied");
    }

    sendResponse(true, "Lead details", $lead);
}

function getLeadTimeline($leadId) {
    $token = validateJWT();
    if (!$token) sendResponse(false, "Authentication required");
    $db = getDB();
    $events = [];
    $st = $db->prepare("SELECT created_at FROM leads WHERE id = ?");
    $st->execute([$leadId]);
    $lead = $st->fetch();
    if ($lead) {
        $events[] = ['type' => 'lead_created', 'title' => 'Lead Created', 'timestamp' => $lead['created_at']];
    }
    $st = $db->prepare("SELECT la.assigned_at, la.agent_id, u.name FROM lead_assignments la JOIN users u ON la.agent_id = u.id WHERE la.lead_id = ? ORDER BY la.assigned_at ASC");
    $st->execute([$leadId]);
    foreach ($st->fetchAll() as $row) {
        $events[] = ['type' => 'assigned', 'title' => 'Lead Assigned', 'timestamp' => $row['assigned_at'], 'meta' => ['agent_id' => $row['agent_id'], 'agent_name' => $row['name']]];
    }
    $st = $db->prepare("SELECT ar.created_at, ar.response_text, ar.response_status FROM agent_responses ar WHERE ar.lead_id = ? ORDER BY ar.created_at ASC");
    $st->execute([$leadId]);
    foreach ($st->fetchAll() as $row) {
        $events[] = ['type' => 'agent_response', 'title' => 'Agent Response', 'timestamp' => $row['created_at'], 'meta' => ['status' => $row['response_status'], 'text' => $row['response_text']]];
    }
    $st = $db->prepare("SELECT id FROM whatsapp_conversations WHERE lead_id = ?");
    $st->execute([$leadId]);
    $convIds = array_map(function($r){ return (int)$r['id']; }, $st->fetchAll());
    foreach ($convIds as $cid) {
        $stm = $db->prepare("SELECT timestamp, direction, type, body, status FROM whatsapp_messages WHERE conversation_id = ? ORDER BY timestamp ASC");
        $stm->execute([$cid]);
        foreach ($stm->fetchAll() as $m) {
            $events[] = ['type' => 'whatsapp_message', 'title' => $m['direction'] === 'incoming' ? 'Incoming Message' : 'Sent Message', 'timestamp' => $m['timestamp'], 'meta' => ['message_type' => $m['type'], 'body' => $m['body'], 'status' => $m['status'], 'conversation_id' => $cid]];
        }
        $stn = $db->prepare("SELECT created_at, note_text, is_private FROM whatsapp_notes WHERE conversation_id = ? ORDER BY id ASC");
        $stn->execute([$cid]);
        foreach ($stn->fetchAll() as $n) {
            $events[] = ['type' => 'note', 'title' => $n['is_private'] ? 'Private Note' : 'Note', 'timestamp' => $n['created_at'], 'meta' => ['text' => $n['note_text'], 'conversation_id' => $cid]];
        }
        $stt = $db->prepare("SELECT tag, created_at FROM whatsapp_conversation_tags WHERE conversation_id = ? ORDER BY id ASC");
        $stt->execute([$cid]);
        foreach ($stt->fetchAll() as $t) {
            $events[] = ['type' => 'tag', 'title' => 'Tag Added', 'timestamp' => $t['created_at'], 'meta' => ['tag' => $t['tag'], 'conversation_id' => $cid]];
        }
    }
    $st = $db->prepare("SELECT reminder_time, status FROM followup_reminders WHERE lead_id = ? ORDER BY reminder_time ASC");
    $st->execute([$leadId]);
    foreach ($st->fetchAll() as $r) {
        $events[] = ['type' => 'followup', 'title' => 'Follow-up Reminder', 'timestamp' => $r['reminder_time'], 'meta' => ['status' => $r['status']]];
    }
    usort($events, function($a, $b){ return strcmp($a['timestamp'] ?? '', $b['timestamp'] ?? ''); });
    sendResponse(true, "Timeline", $events);
}

function getLeadNotes($leadId) {
    $token = validateJWT();
    if (!$token) sendResponse(false, "Authentication required");
    $db = getDB();
    $st = $db->prepare("SELECT wn.*, wc.id as conversation_id FROM whatsapp_notes wn JOIN whatsapp_conversations wc ON wn.conversation_id = wc.id WHERE wc.lead_id = ? ORDER BY wn.id DESC");
    $st->execute([$leadId]);
    sendResponse(true, "Notes", $st->fetchAll());
}

/*
|-------------------------------------------------------------------------- 
| ADMIN ASSIGNS LEAD
|-------------------------------------------------------------------------- 
*/
function assignLead($leadId) {
    $token = validateJWT();
    if (!$token || $token['role'] !== 'admin') {
        sendResponse(false, "Admin access required");
    }

    $input = json_decode(file_get_contents("php://input"), true);
    if (!$input['agent_id']) sendResponse(false, "Agent ID is required");

    $db = getDB();

    try {
        $db->beginTransaction();

        $stmt = $db->prepare("DELETE FROM lead_assignments WHERE lead_id = ?");
        $stmt->execute([$leadId]);

        assignLeadToAgent($leadId, $input['agent_id'], $token['user_id']);

        // Get lead details for notification
        $stmt = $db->prepare("SELECT full_name, phone_number FROM leads WHERE id = ?");
        $stmt->execute([$leadId]);
        $lead = $stmt->fetch();
        
        // Notification will be sent automatically via MySQL trigger

        $db->commit();
        sendResponse(true, "Lead assigned");

    } catch (Exception $e) {
        $db->rollBack();
        sendResponse(false, "Assignment failed: ".$e->getMessage());
    }
}

/*
|-------------------------------------------------------------------------- 
| AGENT SUBMITS RESPONSE
|-------------------------------------------------------------------------- 
*/
function submitResponse($leadId) {
    $token = validateJWT();
    if (!$token || $token['role'] !== 'agent') {
        sendResponse(false, "Agent access required");
    }

    $input = json_decode(file_get_contents("php://input"), true);
    if (!$input['response_status']) sendResponse(false, "Response status is required");

    $db = getDB();

    try {
        $db->beginTransaction();

        // Remove previous response
        $db->prepare("DELETE FROM agent_responses WHERE lead_id = ?")
           ->execute([$leadId]);

        $stmt = $db->prepare("
            INSERT INTO agent_responses (lead_id, agent_id, response_text, response_status, price_offered)
            VALUES (?, ?, ?, ?, ?)
        ");

        $stmt->execute([
            $leadId, $token['user_id'],
            $input['response_text'] ?? null,
            $input['response_status'],
            $input['price_offered'] ?? null
        ]);

        // Update assignment status
        $db->prepare("
            UPDATE lead_assignments SET status = ? WHERE lead_id = ? AND agent_id = ?
        ")->execute([$input['response_status'], $leadId, $token['user_id']]);

        $db->commit();
        sendResponse(true, "Response submitted");

    } catch (Exception $e) {
        $db->rollBack();
        sendResponse(false, "Failed: ".$e->getMessage());
    }
}

?>
