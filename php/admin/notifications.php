<?php
session_start();
require_once '../includes/config.php';

if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    header('Location: login.php');
    exit();
}

$db = getDB();

// Get notification queue
$status = $_GET['status'] ?? '';
$where = [];
$params = [];

if ($status) {
    $where[] = "nq.status = ?";
    $params[] = $status;
}

$whereClause = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$stmt = $db->prepare("SELECT 
    nq.*,
    u.name as agent_name,
    l.full_name as lead_name,
    l.phone_number as lead_phone
    FROM notification_queue nq
    JOIN users u ON nq.agent_id = u.id
    JOIN leads l ON nq.lead_id = l.id
    $whereClause
    ORDER BY nq.created_at DESC
    LIMIT 100");

$stmt->execute($params);
$notifications = $stmt->fetchAll();

// Get stats
$stmt = $db->query("SELECT 
    COUNT(*) as total,
    COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending,
    COUNT(CASE WHEN status = 'sent' THEN 1 END) as sent,
    COUNT(CASE WHEN status = 'failed' THEN 1 END) as failed
    FROM notification_queue");
$stats = $stmt->fetch();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notifications Management - CRM Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="css/global.css?v=<?= APP_ASSET_VERSION ?>" rel="stylesheet">
    <link href="css/admin.css?v=<?= APP_ASSET_VERSION ?>" rel="stylesheet">
</head>
<body>
    <?php include 'navbar.php'; ?>
    
    <div class="container-fluid">
        <div class="row">
            <?php include 'sidebar.php'; ?>
            
            <main class="col-md-10 ms-sm-auto px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">Notifications Management</h1>
                    <button class="btn btn-primary" onclick="retryFailed()">
                        <i class="fas fa-redo"></i> Retry Failed
                    </button>
                </div>

                <!-- Stats Cards -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="card bg-primary text-white">
                            <div class="card-body">
                                <h4><?= $stats['total'] ?></h4>
                                <p>Total Notifications</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-warning text-white">
                            <div class="card-body">
                                <h4><?= $stats['pending'] ?></h4>
                                <p>Pending</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-success text-white">
                            <div class="card-body">
                                <h4><?= $stats['sent'] ?></h4>
                                <p>Sent</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-danger text-white">
                            <div class="card-body">
                                <h4><?= $stats['failed'] ?></h4>
                                <p>Failed</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Filters -->
                <div class="card mb-4">
                    <div class="card-body">
                        <form method="GET" class="row g-3">
                            <div class="col-md-3">
                                <label class="form-label">Status</label>
                                <select name="status" class="form-select">
                                    <option value="">All Status</option>
                                    <option value="pending" <?= $status === 'pending' ? 'selected' : '' ?>>Pending</option>
                                    <option value="sent" <?= $status === 'sent' ? 'selected' : '' ?>>Sent</option>
                                    <option value="failed" <?= $status === 'failed' ? 'selected' : '' ?>>Failed</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">&nbsp;</label>
                                <button type="submit" class="btn btn-primary d-block">Filter</button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Notifications Table -->
                <div class="card">
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Created At</th>
                                        <th>Agent</th>
                                        <th>Lead</th>
                                        <th>Type</th>
                                        <th>Status</th>
                                        <th>Sent At</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($notifications as $notif): ?>
                                        <tr>
                                            <td><?= date('M j, Y H:i:s', strtotime($notif['created_at'])) ?></td>
                                            <td><?= htmlspecialchars($notif['agent_name']) ?></td>
                                            <td>
                                                <strong><?= htmlspecialchars($notif['lead_name']) ?></strong><br>
                                                <small class="text-muted"><?= htmlspecialchars($notif['lead_phone']) ?></small>
                                            </td>
                                            <td>
                                                <span class="badge bg-info"><?= ucfirst(str_replace('_', ' ', $notif['notification_type'])) ?></span>
                                            </td>
                                            <td>
                                                <?php
                                                $statusClass = [
                                                    'pending' => 'warning',
                                                    'sent' => 'success',
                                                    'failed' => 'danger'
                                                ];
                                                $class = $statusClass[$notif['status']] ?? 'secondary';
                                                ?>
                                                <span class="badge bg-<?= $class ?>"><?= ucfirst($notif['status']) ?></span>
                                            </td>
                                            <td>
                                                <?= $notif['sent_at'] ? date('M j, Y H:i:s', strtotime($notif['sent_at'])) : '-' ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                    <?php if (empty($notifications)): ?>
                                        <tr>
                                            <td colspan="6" class="text-center text-muted">No notifications found</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function retryFailed() {
            if (confirm('Retry all failed notifications?')) {
                fetch('retry_notifications.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    }
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('Failed notifications queued for retry');
                        location.reload();
                    } else {
                        alert('Error: ' + data.message);
                    }
                })
                .catch(error => {
                    alert('Error: ' + error.message);
                });
            }
        }
    </script>
</body>
</html>

