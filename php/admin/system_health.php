<?php
session_start();
require_once '../includes/config.php';

if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    header('Location: login.php');
    exit();
}

$db = getDB();

// System health checks
$health = [];

// Database connection
try {
    $db->query("SELECT 1");
    $health['database'] = ['status' => 'healthy', 'message' => 'Database connection active'];
} catch (Exception $e) {
    $health['database'] = ['status' => 'error', 'message' => 'Database connection failed: ' . $e->getMessage()];
}

// Check table sizes
try {
    $stmt = $db->query("SELECT 
        (SELECT COUNT(*) FROM leads) as leads_count,
        (SELECT COUNT(*) FROM lead_assignments) as assignments_count,
        (SELECT COUNT(*) FROM agent_responses) as responses_count,
        (SELECT COUNT(*) FROM notification_queue WHERE status = 'pending') as pending_notifications");
    $counts = $stmt->fetch();
    $health['tables'] = [
        'status' => 'healthy',
        'data' => $counts
    ];
} catch (Exception $e) {
    $health['tables'] = ['status' => 'error', 'message' => $e->getMessage()];
}

// Check recent errors
$logDir = dirname(__DIR__) . '/logs';
$errorLogFile = $logDir . '/lead_ingestion_errors.log';
$recentErrors = 0;
if (file_exists($errorLogFile)) {
    $logContent = file_get_contents($errorLogFile);
    $lines = explode("\n", $logContent);
    $recentErrors = count(array_filter($lines, function($line) {
        return strpos($line, date('Y-m-d')) !== false;
    }));
}

$health['errors'] = [
    'status' => $recentErrors > 100 ? 'warning' : 'healthy',
    'recent_errors' => $recentErrors
];

// Check active agents
$stmt = $db->query("SELECT 
    COUNT(*) as total_agents,
    COUNT(CASE WHEN a.last_login >= DATE_SUB(NOW(), INTERVAL 30 MINUTE) THEN 1 END) as online_agents
    FROM users u
    JOIN agents a ON u.id = a.id
    WHERE u.role = 'agent' AND u.active = 1");
$agentStats = $stmt->fetch();

$health['agents'] = [
    'status' => 'healthy',
    'data' => $agentStats
];

// Check notification queue backlog
$stmt = $db->query("SELECT COUNT(*) as backlog FROM notification_queue WHERE status = 'pending' AND created_at < DATE_SUB(NOW(), INTERVAL 1 HOUR)");
$backlog = $stmt->fetch()['backlog'];

$health['notifications'] = [
    'status' => $backlog > 50 ? 'warning' : 'healthy',
    'backlog' => $backlog
];

// System info
$health['system'] = [
    'php_version' => PHP_VERSION,
    'server_time' => date('Y-m-d H:i:s'),
    'timezone' => date_default_timezone_get(),
    'memory_usage' => round(memory_get_usage(true) / 1024 / 1024, 2) . ' MB',
    'memory_limit' => ini_get('memory_limit')
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Health - CRM Admin</title>
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
                    <h1 class="h2">System Health</h1>
                    <button class="btn btn-primary" onclick="location.reload()">
                        <i class="fas fa-sync"></i> Refresh
                    </button>
                </div>

                <!-- Health Status Overview -->
                <div class="row mb-4">
                    <?php
                    $overallStatus = 'healthy';
                    foreach ($health as $component) {
                        if (isset($component['status']) && $component['status'] === 'error') {
                            $overallStatus = 'error';
                            break;
                        } elseif (isset($component['status']) && $component['status'] === 'warning') {
                            $overallStatus = 'warning';
                        }
                    }
                    $statusColor = $overallStatus === 'healthy' ? 'success' : ($overallStatus === 'warning' ? 'warning' : 'danger');
                    ?>
                    <div class="col-12">
                        <div class="card bg-<?= $statusColor ?> text-white">
                            <div class="card-body text-center">
                                <h3><i class="fas fa-heartbeat fa-2x mb-3"></i></h3>
                                <h2>System Status: <?= strtoupper($overallStatus) ?></h2>
                                <p class="mb-0">Last checked: <?= date('Y-m-d H:i:s') ?></p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Component Health -->
                <div class="row mb-4">
                    <!-- Database -->
                    <div class="col-md-6 mb-3">
                        <div class="card">
                            <div class="card-header">
                                <h5><i class="fas fa-database"></i> Database</h5>
                            </div>
                            <div class="card-body">
                                <?php
                                $dbStatus = $health['database']['status'];
                                $dbColor = $dbStatus === 'healthy' ? 'success' : 'danger';
                                ?>
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <span>Connection Status</span>
                                    <span class="badge bg-<?= $dbColor ?>"><?= ucfirst($dbStatus) ?></span>
                                </div>
                                <p class="text-muted small mb-0"><?= htmlspecialchars($health['database']['message']) ?></p>
                            </div>
                        </div>
                    </div>

                    <!-- Tables -->
                    <div class="col-md-6 mb-3">
                        <div class="card">
                            <div class="card-header">
                                <h5><i class="fas fa-table"></i> Database Tables</h5>
                            </div>
                            <div class="card-body">
                                <?php if ($health['tables']['status'] === 'healthy'): ?>
                                    <div class="row">
                                        <div class="col-6 mb-2">
                                            <strong>Leads:</strong> <?= number_format($health['tables']['data']['leads_count']) ?>
                                        </div>
                                        <div class="col-6 mb-2">
                                            <strong>Assignments:</strong> <?= number_format($health['tables']['data']['assignments_count']) ?>
                                        </div>
                                        <div class="col-6 mb-2">
                                            <strong>Responses:</strong> <?= number_format($health['tables']['data']['responses_count']) ?>
                                        </div>
                                        <div class="col-6 mb-2">
                                            <strong>Pending Notifications:</strong> <?= number_format($health['tables']['data']['pending_notifications']) ?>
                                        </div>
                                    </div>
                                <?php else: ?>
                                    <p class="text-danger"><?= htmlspecialchars($health['tables']['message']) ?></p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Agents -->
                    <div class="col-md-6 mb-3">
                        <div class="card">
                            <div class="card-header">
                                <h5><i class="fas fa-user-tie"></i> Agents</h5>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-6 mb-2">
                                        <strong>Total Agents:</strong> <?= $health['agents']['data']['total_agents'] ?>
                                    </div>
                                    <div class="col-6 mb-2">
                                        <strong>Online Now:</strong> 
                                        <span class="badge bg-success"><?= $health['agents']['data']['online_agents'] ?></span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Notifications -->
                    <div class="col-md-6 mb-3">
                        <div class="card">
                            <div class="card-header">
                                <h5><i class="fas fa-bell"></i> Notifications</h5>
                            </div>
                            <div class="card-body">
                                <?php
                                $notifStatus = $health['notifications']['status'];
                                $notifColor = $notifStatus === 'healthy' ? 'success' : 'warning';
                                ?>
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <span>Queue Backlog</span>
                                    <span class="badge bg-<?= $notifColor ?>"><?= $health['notifications']['backlog'] ?> pending</span>
                                </div>
                                <?php if ($notifStatus === 'warning'): ?>
                                    <p class="text-warning small mb-0">High backlog detected. Consider processing notifications.</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Errors -->
                    <div class="col-md-6 mb-3">
                        <div class="card">
                            <div class="card-header">
                                <h5><i class="fas fa-exclamation-triangle"></i> Error Logs</h5>
                            </div>
                            <div class="card-body">
                                <?php
                                $errorStatus = $health['errors']['status'];
                                $errorColor = $errorStatus === 'healthy' ? 'success' : 'warning';
                                ?>
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <span>Today's Errors</span>
                                    <span class="badge bg-<?= $errorColor ?>"><?= $health['errors']['recent_errors'] ?></span>
                                </div>
                                <?php if ($errorStatus === 'warning'): ?>
                                    <p class="text-warning small mb-0">High error rate detected. Check error logs.</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- System Info -->
                    <div class="col-md-6 mb-3">
                        <div class="card">
                            <div class="card-header">
                                <h5><i class="fas fa-server"></i> System Information</h5>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-12 mb-2">
                                        <strong>PHP Version:</strong> <?= $health['system']['php_version'] ?>
                                    </div>
                                    <div class="col-12 mb-2">
                                        <strong>Server Time:</strong> <?= $health['system']['server_time'] ?>
                                    </div>
                                    <div class="col-12 mb-2">
                                        <strong>Timezone:</strong> <?= $health['system']['timezone'] ?>
                                    </div>
                                    <div class="col-12 mb-2">
                                        <strong>Memory Usage:</strong> <?= $health['system']['memory_usage'] ?>
                                    </div>
                                    <div class="col-12 mb-2">
                                        <strong>Memory Limit:</strong> <?= $health['system']['memory_limit'] ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Auto-refresh every 60 seconds
        setInterval(function() {
            location.reload();
        }, 60000);
    </script>
</body>
</html>

