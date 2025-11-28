<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
ini_set('log_errors', 1);
ini_set('error_log', '/tmp/php_errors.log');

// Custom error handler to display errors on page
set_error_handler(function($severity, $message, $file, $line) {
    echo "<div style='background:red;color:white;padding:5px;margin:5px;'>ERROR: $message in $file on line $line</div>";
});

session_start();
require_once '../includes/config.php';

if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    header('Location: login.php');
    exit();
}

$db = getDB();

// Check if assigned_forms column exists
$columnsStmt = $db->query("SHOW COLUMNS FROM agents LIKE 'assigned_forms'");
$hasAssignedForms = $columnsStmt->rowCount() > 0;

// Handle agent creation and editing
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'create') {
        $name = $_POST['name'] ?? '';
        $email = $_POST['email'] ?? '';
        $password = $_POST['password'] ?? '';
        $phone = $_POST['phone'] ?? '';
        
        if ($name && $email && $password) {
            try {
                $db->beginTransaction();
                
                $passwordHash = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $db->prepare("INSERT INTO users (name, email, phone, password_hash, role) VALUES (?, ?, ?, ?, 'agent')");
                $stmt->execute([$name, $email, $phone, $passwordHash]);
                $userId = $db->lastInsertId();
                
                // Get available forms from leads table
                $assignedForms = $_POST['assigned_forms'] ?? [];
                $assignedFormsJson = json_encode($assignedForms);
                
                // Use appropriate column name
                $columnName = $hasAssignedForms ? 'assigned_forms' : 'assigned_sheets';
                $stmt = $db->prepare("INSERT INTO agents (id, {$columnName}) VALUES (?, ?)");
                $stmt->execute([$userId, $assignedFormsJson]);
                
                $db->commit();
                $success = "Agent created successfully";
            } catch (Exception $e) {
                $db->rollBack();
                $error = "Failed to create agent: " . $e->getMessage();
            }
        } else {
            $error = "All fields are required";
        }
} elseif ($_POST['action'] === 'edit') {
        $agentId = $_POST['agent_id'] ?? '';
        $name = $_POST['name'] ?? '';
        $email = $_POST['email'] ?? '';
        $phone = $_POST['phone'] ?? '';
        
        if ($agentId && $name && $email) {
            try {
                $db->beginTransaction();
                
                $stmt = $db->prepare("UPDATE users SET name = ?, email = ?, phone = ? WHERE id = ?");
                $stmt->execute([$name, $email, $phone, $agentId]);
                
                $assignedForms = $_POST['assigned_forms'] ?? [];
                $assignedFormsJson = json_encode($assignedForms);
                
                // Use appropriate column name
                $columnName = $hasAssignedForms ? 'assigned_forms' : 'assigned_sheets';
                $stmt = $db->prepare("UPDATE agents SET {$columnName} = ? WHERE id = ?");
                $stmt->execute([$assignedFormsJson, $agentId]);
                
                $db->commit();
                $success = "Agent updated successfully";
            } catch (Exception $e) {
                $db->rollBack();
                $error = "Failed to update agent: " . $e->getMessage();
            }
        } else {
            $error = "All fields are required";
        }
    } elseif ($_POST['action'] === 'toggle_online') {
        $agentId = (int)($_POST['agent_id'] ?? 0);
        if ($agentId) {
            $st = $db->prepare('SELECT is_online FROM users WHERE id = ?');
            $st->execute([$agentId]);
            $row = $st->fetch();
            $next = ((int)($row['is_online'] ?? 1)) ? 0 : 1;
            $db->prepare('UPDATE users SET is_online = ? WHERE id = ?')->execute([$next, $agentId]);
            $success = 'Online status updated';
        }
    }
}

// Get available form names
$formStmt = $db->query("SELECT DISTINCT form_name FROM leads WHERE form_name IS NOT NULL ORDER BY form_name");
$availableForms = $formStmt->fetchAll(PDO::FETCH_COLUMN);

// Ensure all agent users have agent records
$db->exec("INSERT IGNORE INTO agents (id, assigned_forms) 
           SELECT u.id, '[]' FROM users u 
           LEFT JOIN agents a ON u.id = a.id 
           WHERE u.role = 'agent' AND a.id IS NULL");

// Get agents from users table where role = 'agent'
$assignedColumn = $hasAssignedForms ? 'a.assigned_forms' : 'a.assigned_sheets';
try {
    $stmt = $db->prepare("SELECT DISTINCT u.id, u.name, u.email, u.phone, u.active, u.is_online,
        COALESCE(a.pusher_device_id, '') as pusher_device_id,
        COALESCE(a.device_token, '') as device_token,
        COALESCE({$assignedColumn}, '[]') as assigned_forms,
        a.last_assignment,
        a.last_login,
        (
            SELECT COUNT(*) 
            FROM lead_assignments la 
            WHERE la.agent_id = u.id
        ) as total_leads,
        (
            SELECT COUNT(*) 
            FROM lead_assignments la 
            WHERE la.agent_id = u.id AND la.status = 'qualified'
        ) as qualified_leads
        FROM users u
        LEFT JOIN agents a ON u.id = a.id
        WHERE u.role = 'agent'
        ORDER BY u.name");
    $stmt->execute();
    $agents = $stmt->fetchAll();
    
    // Check for actual duplicates in query result
    $ids = array_column($agents, 'id');
    $duplicates = array_diff_assoc($ids, array_unique($ids));
    if (!empty($duplicates)) {
        echo "<div style='background:red;color:white;padding:10px;'>DUPLICATE IDs FOUND: " . implode(', ', $duplicates) . "</div>";
    }
    error_log("Agents query executed. Found " . count($agents) . " agents.");
} catch (Exception $e) {
    error_log("Error fetching agents: " . $e->getMessage());
    $agents = [];
}

// Parse assigned forms for display
foreach ($agents as &$agent) {
    $agent['assigned_forms_array'] = json_decode($agent['assigned_forms'] ?? '[]', true);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Agents Management - CRM Admin</title>
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
                    <h1 class="h2">Agents Management</h1>
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createAgentModal">
                        <i class="fas fa-plus"></i> Add Agent
                    </button>
                </div>

                <?php if (isset($success)): ?>
                    <div class="alert alert-success"><?= $success ?></div>
                <?php endif; ?>
                <?php if (isset($error)): ?>
                    <div class="alert alert-danger"><?= $error ?></div>
                <?php endif; ?>

                <div class="card">
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Name</th>
                                        <th>Email</th>
                                        <th>Phone</th>
                                        <th>Status</th>
                                        <th>Online</th>
                                        <th>Device</th>
                                        <th>Total Leads</th>
                                        <th>Qualified</th>
                                        <th>Assigned Forms</th>
                                        <th>Last Assignment</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($agents as $agent): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($agent['name']) ?></td>
                                            <td><?= htmlspecialchars($agent['email']) ?></td>
                                            <td><?= htmlspecialchars($agent['phone'] ?? '-') ?></td>
                                            <td>
                                                <span class="badge bg-<?= $agent['active'] ? 'success' : 'danger' ?>">
                                                    <?= $agent['active'] ? 'Active' : 'Inactive' ?>
                                                </span>
                                            </td>
                                            <td>
                                                <form method="POST" class="d-inline">
                                                    <input type="hidden" name="action" value="toggle_online">
                                                    <input type="hidden" name="agent_id" value="<?= (int)$agent['id'] ?>">
                                                    <button class="btn btn-sm <?= ((int)$agent['is_online']===1)?'btn-success':'btn-secondary' ?>" type="submit">
                                                        <?= ((int)$agent['is_online']===1)?'Online':'Offline' ?>
                                                    </button>
                                                </form>
                                            </td>
                                            <td>
                                                <?php 
                                                // Check if agent is currently logged in
                                                $loginStatus = 'Offline';
                                                $badgeClass = 'bg-danger';
                                                
                                                if ($agent['pusher_device_id'] && $agent['last_login']) {
                                                    $lastLogin = strtotime($agent['last_login']);
                                                    $now = time();
                                                    $timeDiff = $now - $lastLogin;
                                                    
                                                    // Consider online if logged in within last 30 minutes
                                                    if ($timeDiff < 1800) {
                                                        $loginStatus = 'Online';
                                                        $badgeClass = 'bg-success';
                                                    } else {
                                                        $loginStatus = 'Last seen ' . date('M j, H:i', $lastLogin);
                                                        $badgeClass = 'bg-warning';
                                                    }
                                                }
                                                ?>
                                                <span class="badge <?= $badgeClass ?>">
                                                    <?= $loginStatus ?>
                                                </span>
                                            </td>
                                            <td><?= $agent['total_leads'] ?></td>
                                            <td><?= $agent['qualified_leads'] ?></td>
                                            <td>
                                                <?php if (!empty($agent['assigned_forms_array'])): ?>
                                                    <?php foreach ($agent['assigned_forms_array'] as $form): ?>
                                                        <span class="badge bg-info me-1"><?= htmlspecialchars($form) ?></span>
                                                    <?php endforeach; ?>
                                                <?php else: ?>
                                                    <span class="text-muted">All forms</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?= $agent['last_assignment'] ? date('M j, Y', strtotime($agent['last_assignment'])) : 'Never' ?></td>
                                            <td>
                                                <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#editAgentModal" onclick="editAgent(<?= $agent['id'] ?>, '<?= htmlspecialchars($agent['name']) ?>', '<?= htmlspecialchars($agent['email']) ?>', '<?= htmlspecialchars($agent['phone'] ?? '') ?>', <?= json_encode($agent['assigned_forms_array']) ?>)">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Create Agent Modal -->
    <div class="modal fade" id="createAgentModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST">
                    <div class="modal-header">
                        <h5 class="modal-title">Create New Agent</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="action" value="create">
                        <div class="mb-3">
                            <label class="form-label">Name</label>
                            <input type="text" name="name" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Email</label>
                            <input type="email" name="email" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Phone</label>
                            <input type="text" name="phone" class="form-control">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Password</label>
                            <input type="password" name="password" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Assigned Forms (leave empty for all forms)</label>
                            <div class="form-check-container">
                                <?php foreach ($availableForms as $form): ?>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="assigned_forms[]" value="<?= htmlspecialchars($form) ?>" id="form_<?= md5($form) ?>">
                                        <label class="form-check-label" for="form_<?= md5($form) ?>">
                                            <?= htmlspecialchars($form) ?>
                                        </label>
                                    </div>
                                <?php endforeach; ?>
                                <?php if (empty($availableForms)): ?>
                                    <p class="text-muted">Available forms: Kusum, Neha, Manju, Mayara</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Create Agent</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Agent Modal -->
    <div class="modal fade" id="editAgentModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST">
                    <div class="modal-header">
                        <h5 class="modal-title">Edit Agent</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="action" value="edit">
                        <input type="hidden" name="agent_id" id="edit_agent_id">
                        <div class="mb-3">
                            <label class="form-label">Name</label>
                            <input type="text" name="name" id="edit_name" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Email</label>
                            <input type="email" name="email" id="edit_email" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Phone</label>
                            <input type="text" name="phone" id="edit_phone" class="form-control">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Assigned Forms</label>
                            <div class="form-check-container" id="edit_forms_container">
                                <?php foreach ($availableForms as $form): ?>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="assigned_forms[]" value="<?= htmlspecialchars($form) ?>" id="edit_form_<?= md5($form) ?>">
                                        <label class="form-check-label" for="edit_form_<?= md5($form) ?>">
                                            <?= htmlspecialchars($form) ?>
                                        </label>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Update Agent</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    function editAgent(id, name, email, phone, assignedForms) {
        document.getElementById('edit_agent_id').value = id;
        document.getElementById('edit_name').value = name;
        document.getElementById('edit_email').value = email;
        document.getElementById('edit_phone').value = phone;
        
        // Clear all checkboxes
        document.querySelectorAll('#edit_forms_container input[type="checkbox"]').forEach(cb => cb.checked = false);
        
        // Check assigned forms
        assignedForms.forEach(form => {
            const checkbox = document.querySelector(`#edit_forms_container input[value="${form}"]`);
            if (checkbox) checkbox.checked = true;
        });
    }
    </script>
</body>
</html>
