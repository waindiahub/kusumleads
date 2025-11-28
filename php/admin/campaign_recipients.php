<?php
session_start();
require_once '../includes/config.php';
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') { header('Location: login.php'); exit(); }
$id = (int)($_GET['id'] ?? 0);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Campaign Recipients</title>
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
        <h1 class="h2">Campaign #<?= $id ?> Recipients</h1>
        <div class="d-flex gap-2">
          <select id="statusFilter" class="form-select" style="max-width:200px">
            <option value="">All</option>
            <option>queued</option>
            <option>sent</option>
            <option>delivered</option>
            <option>read</option>
            <option>failed</option>
          </select>
          <button class="btn btn-outline-warning" id="retryBtn">Retry Failed</button>
        </div>
      </div>
      <div class="card">
        <div class="card-body">
          <div class="table-responsive">
            <table class="table table-striped">
              <thead><tr><th>ID</th><th>Phone</th><th>Status</th><th>Attempts</th><th>Last Attempt</th><th>Message ID</th></tr></thead>
              <tbody id="tbody"></tbody>
            </table>
          </div>
        </div>
      </div>
    </main>
  </div>
</div>
<script>
const id = <?= $id ?>
let rows=[]
async function load(){ const r=await fetch(`../includes/whatsapp.php/whatsapp/campaigns/${id}/recipients`,{ headers:{'Authorization':'Bearer '+(sessionStorage.getItem('jwt')||'')}}); const j=await r.json(); rows=j.data||[]; render() }
function render(){ const f=document.getElementById('statusFilter').value; const tbody=document.getElementById('tbody'); tbody.innerHTML=''; rows.filter(x=>!f||x.status===f).forEach(x=>{ const tr=document.createElement('tr'); tr.innerHTML=`<td>${x.id}</td><td>${x.phone_number}</td><td><span class="badge bg-${x.status==='failed'?'danger':(x.status==='queued'?'warning':(x.status==='sent'?'info':'success'))}">${x.status}</span></td><td>${x.attempts||0}</td><td>${x.last_attempt_at||''}</td><td>${x.wa_message_id||''}</td>`; tbody.appendChild(tr) }) }
document.getElementById('statusFilter').onchange=render
document.getElementById('retryBtn').onclick=async function(){ const r=await fetch(`../includes/whatsapp.php/whatsapp/campaigns/${id}/recipients/retry_failed`,{ method:'POST', headers:{'Authorization':'Bearer '+(sessionStorage.getItem('jwt')||'')}}); const j=await r.json(); alert('Queued '+(j.data?.queued||0)+' retries'); load() }
load()
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
