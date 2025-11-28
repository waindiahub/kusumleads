<?php
session_start();
require_once '../includes/config.php';
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') { header('Location: login.php'); exit(); }
$db = getDB();
$scores = $db->query('SELECT lead_score, priority_level, city, campaign_name, platform FROM leads')->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Lead Scoring Analytics</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
        <h1 class="h2">Lead Scoring Analytics</h1>
        <div>
          <button class="btn btn-outline-primary" id="rescoreBtn"><i class="fas fa-sync"></i> Rescore Recent</button>
        </div>
      </div>
      <div class="row mb-4">
        <div class="col-md-6"><div class="card"><div class="card-header">Score Distribution</div><div class="card-body"><canvas id="scoreChart"></canvas></div></div></div>
        <div class="col-md-6"><div class="card"><div class="card-header">Priorities</div><div class="card-body"><canvas id="priorityChart"></canvas></div></div></div>
      </div>
      <div class="card">
        <div class="card-header">Rules Editor</div>
        <div class="card-body">
          <form method="POST" action="settings.php">
            <input type="hidden" name="action" value="update_settings">
            <div class="row g-2">
              <div class="col-md-3"><label class="form-label">Very Fresh +</label><input type="number" class="form-control" name="settings[score_very_fresh_bonus]"></div>
              <div class="col-md-3"><label class="form-label">Fresh +</label><input type="number" class="form-control" name="settings[score_fresh_bonus]"></div>
              <div class="col-md-3"><label class="form-label">Old -</label><input type="number" class="form-control" name="settings[score_old_penalty]"></div>
              <div class="col-md-3"><label class="form-label">Metro +</label><input type="number" class="form-control" name="settings[score_city_bonus]"></div>
            </div>
            <div class="mt-3"><button class="btn btn-primary" type="submit">Save Weights</button></div>
          </form>
        </div>
      </div>
    </main>
  </div>
</div>
<script>
const data = <?= json_encode($scores) ?>
const scores = data.map(d=>parseInt(d.lead_score||0)).filter(x=>!isNaN(x))
const buckets = [0,10,20,30,40,50,60,70,80,90,100]
const hist = buckets.map((b,i)=> scores.filter(s=> s>=b && s < (buckets[i+1]||101)).length )
const prio = {}; data.forEach(d=>{ const p=d.priority_level||'medium'; prio[p]=(prio[p]||0)+1 })
new Chart(document.getElementById('scoreChart'),{ type:'bar', data:{ labels:buckets.map((b,i)=> `${b}-${buckets[i+1]||100}`), datasets:[{ label:'Leads', data:hist, backgroundColor:'#4caf50'}] } })
new Chart(document.getElementById('priorityChart'),{ type:'pie', data:{ labels:Object.keys(prio), datasets:[{ data:Object.values(prio), backgroundColor:['#f44336','#ff9800','#2196f3','#9c27b0']}] } })
document.getElementById('rescoreBtn').onclick = async function(){ const r=await fetch('../includes/leads.php?route=/api/leads/scoring_rescore',{ method:'POST', headers:{'Authorization':'Bearer '+(sessionStorage.getItem('jwt')||'')}}); const j=await r.json(); alert('Rescored '+(j.data?.updated||0)+' leads'); location.reload() }
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
