<?php
session_start();
require_once '../includes/config.php';
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') { header('Location: login.php'); exit(); }
$leadId = (int)($_GET['id'] ?? 0);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Lead Profile</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="css/global.css?v=<?= APP_ASSET_VERSION ?>" rel="stylesheet">
  <link href="css/admin.css?v=<?= APP_ASSET_VERSION ?>" rel="stylesheet">
</head>
<body>
<?php include 'navbar.php'; ?>
<div class="container-fluid">
  <div class="row">
    <?php include 'sidebar.php'; ?>
    <main class="col-md-10 ms-sm-auto px-md-4">
      <div class="pt-3 pb-2 mb-3 border-bottom d-flex justify-content-between align-items-center">
        <h1 class="h2">Lead Profile</h1>
        <a href="leads.php" class="btn btn-outline-secondary">Back</a>
      </div>
      <div class="row">
        <div class="col-lg-8">
          <div class="card mb-3">
            <div class="card-header">Timeline</div>
            <div class="card-body" id="timeline"></div>
          </div>
          <div class="card mb-3">
            <div class="card-header">Reassignment History</div>
            <div class="card-body" id="assignHistory"></div>
          </div>
        </div>
        <div class="col-lg-4">
          <div class="card mb-3">
            <div class="card-header">Notes</div>
            <div class="card-body">
              <div id="notes"></div>
            </div>
          </div>
        </div>
      </div>
    </main>
  </div>
  </div>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
  <script>
    const leadId = <?= $leadId ?>;
    async function loadTimeline() {
      const res = await fetch('../includes/leads.php?route=/api/leads/' + leadId + '/timeline', { headers: { 'Authorization': 'Bearer ' + (sessionStorage.getItem('jwt') || '') } });
      const data = await res.json();
      if (!data.success) return;
      const list = data.data.map(ev => {
        const ts = ev.timestamp ? new Date(ev.timestamp).toLocaleString() : '';
        const title = ev.title || ev.type;
        let meta = '';
        if (ev.meta) {
          if (ev.meta.text) meta = ev.meta.text;
          else if (ev.meta.body) meta = ev.meta.body;
          else if (ev.meta.agent_name) meta = ev.meta.agent_name;
          else if (ev.meta.tag) meta = '#' + ev.meta.tag;
        }
        return `<div class="d-flex align-items-start mb-3"><div class="me-2"><span class="badge bg-secondary">${title}</span></div><div><div class="small text-muted">${ts}</div><div>${meta || ''}</div></div></div>`;
      }).join('');
      document.getElementById('timeline').innerHTML = list || '<div class="text-muted">No activity</div>';
    }
    async function loadNotes() {
      const res = await fetch('../includes/leads.php?route=/api/leads/' + leadId + '/notes', { headers: { 'Authorization': 'Bearer ' + (sessionStorage.getItem('jwt') || '') } });
      const data = await res.json();
      if (!data.success) return;
      document.getElementById('notes').innerHTML = data.data.map(n => `<div class="border rounded p-2 mb-2"><div class="small text-muted">${new Date(n.created_at).toLocaleString()}</div><div>${n.note_text}</div></div>`).join('');
    }
    async function loadAssignHistory(){
      const res = await fetch('../includes/leads.php?route=/api/leads/'+leadId)
      const j = await res.json()
      const r = await fetch('../includes/whatsapp.php/whatsapp/conversations?lead_id='+leadId)
      const d = await r.json()
      const conv = (d.data||[])[0]
      if (conv){
        const q = await fetch('../includes/whatsapp_api.php?action=get_messages&conversation_id='+conv.id)
        const k = await fetch('../includes/whatsapp_api.php?action=list_conversations')
        document.getElementById('assignHistory').innerHTML = '<div class="text-muted">Open chat to view assignment changes</div>'
      } else {
        document.getElementById('assignHistory').innerHTML = '<div class="text-muted">No conversation</div>'
      }
    }
    loadTimeline();
    loadNotes();
    loadAssignHistory();
  </script>
</body>
</html>
