<?php
session_start();
require_once '../includes/config.php';

if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    header('Location: login.php');
    exit();
}

$db = getDB();
$success = '';
$error = '';

// Handle reminder actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'create') {
        $leadId = $_POST['lead_id'] ?? '';
        $agentId = $_POST['agent_id'] ?? '';
        $reminderTime = $_POST['reminder_time'] ?? '';
        $note = $_POST['note'] ?? '';
        
        if ($leadId && $agentId && $reminderTime) {
            try {
                $stmt = $db->prepare("INSERT INTO followup_reminders (lead_id, agent_id, reminder_time, reminder_note) VALUES (?, ?, ?, ?)");
                $stmt->execute([$leadId, $agentId, $reminderTime, $note]);
                $success = "Reminder created successfully";
            } catch (Exception $e) {
                $error = "Failed to create reminder: " . $e->getMessage();
            }
        } else {
            $error = "All fields are required";
        }
    } elseif ($_POST['action'] === 'update_status') {
        $reminderId = $_POST['reminder_id'] ?? '';
        $status = $_POST['status'] ?? '';
        
        if ($reminderId && $status) {
            try {
                $stmt = $db->prepare("UPDATE followup_reminders SET status = ?, completed_at = ? WHERE id = ?");
                $completedAt = $status === 'completed' ? date('Y-m-d H:i:s') : null;
                $stmt->execute([$status, $completedAt, $reminderId]);
                $success = "Reminder updated successfully";
            } catch (Exception $e) {
                $error = "Failed to update reminder: " . $e->getMessage();
            }
        }
    }
}

// Get reminders
$status = $_GET['status'] ?? '';
$where = [];
$params = [];

if ($status) {
    $where[] = "fr.status = ?";
    $params[] = $status;
}

$whereClause = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$stmt = $db->prepare("SELECT 
    fr.*,
    l.full_name as lead_name,
    l.phone_number as lead_phone,
    u.name as agent_name
    FROM followup_reminders fr
    JOIN leads l ON fr.lead_id = l.id
    JOIN users u ON fr.agent_id = u.id
    $whereClause
    ORDER BY fr.reminder_time ASC
    LIMIT 100");

$stmt->execute($params);
$reminders = $stmt->fetchAll();

// Get leads for dropdown
$stmt = $db->query("SELECT l.id, l.full_name, la.agent_id 
    FROM leads l
    JOIN lead_assignments la ON l.id = la.lead_id
    ORDER BY l.created_at DESC
    LIMIT 100");
$leads = $stmt->fetchAll();

// Get agents
$stmt = $db->query("SELECT id, name FROM users WHERE role = 'agent' AND active = 1 ORDER BY name");
$agents = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reminders Management - CRM Admin</title>
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
                    <h1 class="h2">Reminders Management</h1>
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createReminderModal">
                        <i class="fas fa-plus"></i> Create Reminder
                    </button>
                </div>

                <?php if ($success): ?>
                    <div class="alert alert-success alert-dismissible fade show">
                        <?= htmlspecialchars($success) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                
                <?php if ($error): ?>
                    <div class="alert alert-danger alert-dismissible fade show">
                        <?= htmlspecialchars($error) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <!-- Filters -->
                <div class="card mb-4">
                    <div class="card-body">
                        <form method="GET" class="row g-3">
                            <div class="col-md-3">
                                <label class="form-label">Status</label>
                                <select name="status" class="form-select">
                                    <option value="">All Status</option>
                                    <option value="pending" <?= $status === 'pending' ? 'selected' : '' ?>>Pending</option>
                                    <option value="completed" <?= $status === 'completed' ? 'selected' : '' ?>>Completed</option>
                                    <option value="cancelled" <?= $status === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">&nbsp;</label>
                                <button type="submit" class="btn btn-primary d-block">Filter</button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Reminders Table -->
                <div class="card">
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Lead</th>
                                        <th>Agent</th>
                                        <th>Reminder Time</th>
                                        <th>Note</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($reminders as $reminder): ?>
                                        <tr>
                                            <td>
                                                <strong><?= htmlspecialchars($reminder['lead_name']) ?></strong><br>
                                                <small class="text-muted"><?= htmlspecialchars($reminder['lead_phone']) ?></small>
                                            </td>
                                            <td><?= htmlspecialchars($reminder['agent_name']) ?></td>
                                            <td>
                                                <?= date('M j, Y H:i', strtotime($reminder['reminder_time'])) ?>
                                                <?php if (strtotime($reminder['reminder_time']) < time() && $reminder['status'] === 'pending'): ?>
                                                    <span class="badge bg-danger ms-2">Overdue</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?= htmlspecialchars($reminder['reminder_note'] ?? '-') ?></td>
                                            <td>
                                                <?php
                                                $statusClass = [
                                                    'pending' => 'warning',
                                                    'completed' => 'success',
                                                    'cancelled' => 'secondary'
                                                ];
                                                $class = $statusClass[$reminder['status']] ?? 'secondary';
                                                ?>
                                                <span class="badge bg-<?= $class ?>"><?= ucfirst($reminder['status']) ?></span>
                                            </td>
                                            <td>
                                                <?php if ($reminder['status'] === 'pending'): ?>
                                                    <form method="POST" class="d-inline">
                                                        <input type="hidden" name="action" value="update_status">
                                                        <input type="hidden" name="reminder_id" value="<?= $reminder['id'] ?>">
                                                        <input type="hidden" name="status" value="completed">
                                                        <button type="submit" class="btn btn-sm btn-success">
                                                            <i class="fas fa-check"></i> Complete
                                                        </button>
                                                    </form>
                                                    <form method="POST" class="d-inline">
                                                        <input type="hidden" name="action" value="update_status">
                                                        <input type="hidden" name="reminder_id" value="<?= $reminder['id'] ?>">
                                                        <input type="hidden" name="status" value="cancelled">
                                                        <button type="submit" class="btn btn-sm btn-secondary" onclick="return confirm('Cancel this reminder?')">
                                                            <i class="fas fa-times"></i> Cancel
                                                        </button>
                                                    </form>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                    <?php if (empty($reminders)): ?>
                                        <tr>
                                            <td colspan="6" class="text-center text-muted">No reminders found</td>
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

    <!-- Create Reminder Modal -->
    <div class="modal fade" id="createReminderModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST">
                    <div class="modal-header">
                        <h5 class="modal-title">Create Reminder</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="action" value="create">
                        <div class="mb-3">
                            <label class="form-label">Lead</label>
                            <select name="lead_id" class="form-select" required>
                                <option value="">Select Lead</option>
                                <?php foreach ($leads as $lead): ?>
                                    <option value="<?= $lead['id'] ?>">
                                        <?= htmlspecialchars($lead['full_name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Agent</label>
                            <select name="agent_id" class="form-select" required>
                                <option value="">Select Agent</option>
                                <?php foreach ($agents as $agent): ?>
                                    <option value="<?= $agent['id'] ?>">
                                        <?= htmlspecialchars($agent['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Reminder Time</label>
                            <input type="datetime-local" name="reminder_time" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Note</label>
                            <textarea name="note" class="form-control" rows="3"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Create Reminder</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

