<?php
session_start();
require_once '../includes/config.php';

if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    header('Location: login.php');
    exit();
}

$db = getDB();

// Filters
$type = $_GET['type'] ?? '';
$agent = $_GET['agent'] ?? '';
$dateFrom = $_GET['date_from'] ?? '';
$dateTo = $_GET['date_to'] ?? '';
$page = (int)($_GET['page'] ?? 1);
$limit = 50;
$offset = ($page - 1) * $limit;

// Build query
$where = [];
$params = [];

if ($type) {
    $where[] = "activity_type = ?";
    $params[] = $type;
}
if ($agent) {
    $where[] = "agent_id = ?";
    $params[] = $agent;
}
if ($dateFrom) {
    $where[] = "DATE(created_at) >= ?";
    $params[] = $dateFrom;
}
if ($dateTo) {
    $where[] = "DATE(created_at) <= ?";
    $params[] = $dateTo;
}

$whereClause = $where ? 'WHERE ' . implode(' AND ', $where) : '';

// Get activities from multiple sources
$activities = [];

// Lead assignments
$sql = "SELECT 
    'lead_assigned' as activity_type,
    CONCAT('Lead assigned: ', l.full_name, ' to ', u.name) as description,
    la.assigned_at as created_at,
    la.agent_id,
    la.lead_id,
    u.name as agent_name,
    l.full_name as lead_name
    FROM lead_assignments la
    JOIN leads l ON la.lead_id = l.id
    JOIN users u ON la.agent_id = u.id
    $whereClause
    ORDER BY la.assigned_at DESC
    LIMIT $limit OFFSET $offset";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$assignments = $stmt->fetchAll();

foreach ($assignments as $a) {
    $activities[] = [
        'type' => $a['activity_type'],
        'description' => $a['description'],
        'timestamp' => $a['created_at'],
        'agent_name' => $a['agent_name'],
        'lead_name' => $a['lead_name']
    ];
}

// Agent responses
$sql = "SELECT 
    'response_submitted' as activity_type,
    CONCAT('Response: ', ar.response_status, ' for lead ', l.full_name) as description,
    ar.created_at,
    ar.agent_id,
    ar.lead_id,
    u.name as agent_name,
    l.full_name as lead_name
    FROM agent_responses ar
    JOIN leads l ON ar.lead_id = l.id
    JOIN users u ON ar.agent_id = u.id
    $whereClause
    ORDER BY ar.created_at DESC
    LIMIT $limit OFFSET $offset";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$responses = $stmt->fetchAll();

foreach ($responses as $r) {
    $activities[] = [
        'type' => $r['activity_type'],
        'description' => $r['description'],
        'timestamp' => $r['created_at'],
        'agent_name' => $r['agent_name'],
        'lead_name' => $r['lead_name']
    ];
}

// Sort by timestamp
usort($activities, function($a, $b) {
    return strtotime($b['timestamp']) - strtotime($a['timestamp']);
});

// Get agents for filter
$stmt = $db->query("SELECT id, name FROM users WHERE role = 'agent' ORDER BY name");
$agents = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Activity Logs - CRM Admin</title>
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
                    <h1 class="h2">Activity Logs</h1>
                    <button class="btn btn-primary" onclick="exportLogs()">
                        <i class="fas fa-download"></i> Export
                    </button>
                </div>

                <!-- Filters -->
                <div class="card mb-4">
                    <div class="card-body">
                        <form method="GET" class="row g-3">
                            <div class="col-md-3">
                                <label class="form-label">Activity Type</label>
                                <select name="type" class="form-select">
                                    <option value="">All Types</option>
                                    <option value="lead_assigned" <?= $type === 'lead_assigned' ? 'selected' : '' ?>>Lead Assigned</option>
                                    <option value="response_submitted" <?= $type === 'response_submitted' ? 'selected' : '' ?>>Response Submitted</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Agent</label>
                                <select name="agent" class="form-select">
                                    <option value="">All Agents</option>
                                    <?php foreach ($agents as $ag): ?>
                                        <option value="<?= $ag['id'] ?>" <?= $agent == $ag['id'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($ag['name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Date From</label>
                                <input type="date" name="date_from" class="form-control" value="<?= htmlspecialchars($dateFrom) ?>">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Date To</label>
                                <input type="date" name="date_to" class="form-control" value="<?= htmlspecialchars($dateTo) ?>">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">&nbsp;</label>
                                <button type="submit" class="btn btn-primary d-block">Filter</button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Activity Logs Table -->
                <div class="card">
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Timestamp</th>
                                        <th>Type</th>
                                        <th>Description</th>
                                        <th>Agent</th>
                                        <th>Lead</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach (array_slice($activities, 0, $limit) as $activity): ?>
                                        <tr>
                                            <td><?= date('M j, Y H:i:s', strtotime($activity['timestamp'])) ?></td>
                                            <td>
                                                <?php
                                                $badgeClass = $activity['type'] === 'lead_assigned' ? 'bg-primary' : 'bg-success';
                                                $typeLabel = $activity['type'] === 'lead_assigned' ? 'Assignment' : 'Response';
                                                ?>
                                                <span class="badge <?= $badgeClass ?>"><?= $typeLabel ?></span>
                                            </td>
                                            <td><?= htmlspecialchars($activity['description']) ?></td>
                                            <td><?= htmlspecialchars($activity['agent_name'] ?? '-') ?></td>
                                            <td><?= htmlspecialchars($activity['lead_name'] ?? '-') ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                    <?php if (empty($activities)): ?>
                                        <tr>
                                            <td colspan="5" class="text-center text-muted">No activity logs found</td>
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
        function exportLogs() {
            const params = new URLSearchParams(window.location.search);
            window.location.href = 'export_activity_logs.php?' + params.toString();
        }
    </script>
</body>
</html>

