<?php
session_start();
require_once '../includes/config.php';
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') { header('Location: login.php'); exit(); }
$db = getDB();
$rows = $db->query('SELECT wc.id, wc.phone_number, wc.assigned_agent_id, u.name AS agent_name, wc.last_message_at FROM whatsapp_conversations wc LEFT JOIN users u ON wc.assigned_agent_id = u.id ORDER BY wc.last_message_at DESC')->fetchAll();
?>
<!DOCTYPE html>
<html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Contacts</title><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="css/global.css?v=<?= APP_ASSET_VERSION ?>" rel="stylesheet">
    <link href="css/admin.css?v=<?= APP_ASSET_VERSION ?>" rel="stylesheet">
</head>
<body>
<?php include 'navbar.php'; ?>
<div class="container-fluid"><div class="row"><?php include 'sidebar.php'; ?>
<main class="col-md-10 ms-sm-auto px-md-4">
<div class="pt-3 pb-2 mb-3 border-bottom d-flex justify-content-between"><h1 class="h2">WhatsApp Contacts</h1><input id="search" class="form-control" placeholder="Search"></div>
<div class="card"><div class="table-responsive"><table class="table table-striped"><thead><tr><th>Phone</th><th>Agent</th><th>Last Activity</th><th></th></tr></thead><tbody id="tbody">
<?php foreach ($rows as $r): ?>
<tr><td><?= htmlspecialchars($r['phone_number']) ?></td><td><?= htmlspecialchars($r['agent_name'] ?? 'Unassigned') ?></td><td><?= htmlspecialchars($r['last_message_at'] ?? '') ?></td><td><a class="btn btn-sm btn-primary" href="whatsapp_conversations.php?id=<?= (int)$r['id'] ?>">Open Chat</a></td></tr>
<?php endforeach; ?>
</tbody></table></div></div>
</main></div></div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body></html>
