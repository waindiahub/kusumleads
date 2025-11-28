<?php
session_start();
require_once '../includes/config.php';

if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    header('Location: login.php');
    exit();
}

$db = getDB();

// Handle lead assignment and bulk operations
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'assign' && isset($_POST['lead_id']) && isset($_POST['agent_id'])) {
        try {
            $db->beginTransaction();
            
            // Remove existing assignment
            $stmt = $db->prepare("DELETE FROM lead_assignments WHERE lead_id = ?");
            $stmt->execute([$_POST['lead_id']]);
            
            // Create new assignment
            $stmt = $db->prepare("INSERT INTO lead_assignments (lead_id, agent_id, assigned_by) VALUES (?, ?, ?)");
            $stmt->execute([$_POST['lead_id'], $_POST['agent_id'], $_SESSION['user']['id']]);
            
            $db->commit();
            $success = "Lead assigned successfully";
        } catch (Exception $e) {
            $db->rollBack();
            $error = "Failed to assign lead";
        }
    } elseif ($_POST['action'] === 'bulk_assign' && isset($_POST['lead_ids']) && isset($_POST['agent_id'])) {
        try {
            $db->beginTransaction();
            $leadIds = json_decode($_POST['lead_ids'], true);
            $assigned = 0;
            
            foreach ($leadIds as $leadId) {
                // Remove existing assignment
                $stmt = $db->prepare("DELETE FROM lead_assignments WHERE lead_id = ?");
                $stmt->execute([$leadId]);
                
                // Create new assignment
                $stmt = $db->prepare("INSERT INTO lead_assignments (lead_id, agent_id, assigned_by) VALUES (?, ?, ?)");
                $stmt->execute([$leadId, $_POST['agent_id'], $_SESSION['user']['id']]);
                $assigned++;
            }
            
            $db->commit();
            $success = "$assigned leads assigned successfully";
        } catch (Exception $e) {
            $db->rollBack();
            $error = "Failed to assign leads: " . $e->getMessage();
        }
    } elseif ($_POST['action'] === 'export' && isset($_POST['lead_ids'])) {
        $leadIds = json_decode($_POST['lead_ids'], true);
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="leads_export_' . date('Y-m-d') . '.csv"');
        
        $output = fopen('php://output', 'w');
        fputcsv($output, ['ID', 'Name', 'Phone', 'City', 'Campaign', 'Status', 'Agent', 'Price', 'Created']);
        
        $stmt = $db->prepare("SELECT l.*, la.status as assignment_status, u.name as agent_name, ar.price_offered
            FROM leads l
            LEFT JOIN lead_assignments la ON l.id = la.lead_id
            LEFT JOIN users u ON la.agent_id = u.id
            LEFT JOIN agent_responses ar ON l.id = ar.lead_id
            WHERE l.id IN (" . implode(',', array_fill(0, count($leadIds), '?')) . ")");
        $stmt->execute($leadIds);
        
        while ($row = $stmt->fetch()) {
            fputcsv($output, [
                $row['id'],
                $row['full_name'],
                $row['phone_number'],
                $row['city'] ?? '',
                $row['campaign_name'] ?? '',
                $row['assignment_status'] ?? 'Unassigned',
                $row['agent_name'] ?? '',
                $row['price_offered'] ?? '',
                $row['created_at']
            ]);
        }
        
        fclose($output);
        exit();
    }
}

// Get filters
$status = $_GET['status'] ?? '';
$agent = $_GET['agent'] ?? '';
$search = $_GET['search'] ?? '';
$page = (int)($_GET['page'] ?? 1);
$limit = 20;
$offset = ($page - 1) * $limit;

// Build query
$where = [];
$params = [];

if ($status) {
    $where[] = "la.status = ?";
    $params[] = $status;
}
if ($agent) {
    $where[] = "la.agent_id = ?";
    $params[] = $agent;
}
if ($search) {
    $where[] = "(l.full_name LIKE ? OR l.phone_number LIKE ? OR l.city LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$whereClause = $where ? 'WHERE ' . implode(' AND ', $where) : '';

// Get leads
$stmt = $db->prepare("SELECT l.*, la.status as assignment_status, la.assigned_at, 
    u.name as agent_name, ar.response_status, ar.price_offered
    FROM leads l 
    LEFT JOIN lead_assignments la ON l.id = la.lead_id
    LEFT JOIN users u ON la.agent_id = u.id
    LEFT JOIN agent_responses ar ON l.id = ar.lead_id
    $whereClause
    ORDER BY l.created_at DESC
    LIMIT $limit OFFSET $offset");
$stmt->execute($params);
$leads = $stmt->fetchAll();

// Get total count
$stmt = $db->prepare("SELECT COUNT(*) FROM leads l 
    LEFT JOIN lead_assignments la ON l.id = la.lead_id
    LEFT JOIN users u ON la.agent_id = u.id
    $whereClause");
$stmt->execute($params);
$totalLeads = $stmt->fetchColumn();
$totalPages = ceil($totalLeads / $limit);

// Get agents for assignment
$stmt = $db->query("SELECT u.id, u.name FROM users u WHERE u.role = 'agent' AND u.active = 1");
$agents = $stmt->fetchAll();

// Pipeline snapshots
$pipelineStats = $db->query("SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN DATE(created_at) = CURDATE() THEN 1 ELSE 0 END) as today,
        SUM(CASE WHEN priority_level = 'hot' THEN 1 ELSE 0 END) as hot,
        COALESCE(AVG(lead_score), 0) as avg_score
    FROM leads")->fetch();

$assignedCount = (int)$db->query("SELECT COUNT(DISTINCT lead_id) FROM lead_assignments")->fetchColumn();
$unassignedCount = max(($pipelineStats['total'] ?? 0) - $assignedCount, 0);

$statusLabels = [
    'assigned' => 'Assigned',
    'contacted' => 'Contacted',
    'qualified' => 'Qualified',
    'not_qualified' => 'Not Qualified',
    'call_not_picked' => 'Call Not Picked',
    'payment_completed' => 'Payment Done'
];
$statusCounts = array_fill_keys(array_keys($statusLabels), 0);
$statusStmt = $db->query("SELECT status, COUNT(*) as total FROM lead_assignments GROUP BY status");
while ($row = $statusStmt->fetch()) {
    if (isset($statusCounts[$row['status']])) {
        $statusCounts[$row['status']] = (int)$row['total'];
    }
}

$activeFilters = [];
if ($status && isset($statusLabels[$status])) {
    $activeFilters[] = "Status: " . $statusLabels[$status];
}
if ($agent) {
    $agentName = null;
    foreach ($agents as $ag) {
        if ((string)$ag['id'] === (string)$agent) {
            $agentName = $ag['name'];
            break;
        }
    }
    if ($agentName) {
        $activeFilters[] = "Agent: " . $agentName;
    }
}
if ($search) {
    $activeFilters[] = "Search: \"" . htmlspecialchars($search) . "\"";
}
$filtersCopy = $activeFilters ? implode(' · ', $activeFilters) : 'No filters applied';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Leads Management - CRM Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="css/global.css?v=<?= APP_ASSET_VERSION ?>" rel="stylesheet">
    <link href="css/admin.css?v=<?= APP_ASSET_VERSION ?>" rel="stylesheet">
</head>
<body class="admin-shell">
    <?php include __DIR__ . '/partials/layout_start.php'; ?>
                <div class="page-heading">
                    <div>
                        <p class="eyebrow">Pipeline Control</p>
                        <h1>Leads Command Center</h1>
                        <p class="text-muted mb-0"><?= $filtersCopy ?></p>
                    </div>
                    <div class="page-heading__actions">
                        <button class="ghost-button d-none d-md-inline-flex" onclick="location.reload()">
                            <i class="fas fa-rotate"></i> Refresh
                        </button>
                        <button class="btn btn-primary" onclick="bulkAssign()" id="bulkAssignBtnTop" disabled>
                            <i class="fas fa-user-plus me-1"></i> Assign Selected
                        </button>
                        <button class="btn btn-outline-primary" onclick="exportSelected()" id="exportBtnTop" disabled>
                            <i class="fas fa-file-export me-1"></i> Export
                        </button>
                    </div>
                </div>

                <?php if (isset($success)): ?>
                    <div class="alert alert-success alert-dismissible fade show">
                        <?= $success ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                <?php if (isset($error)): ?>
                    <div class="alert alert-danger alert-dismissible fade show">
                        <?= $error ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <section class="lead-stats-grid mb-4">
                    <article class="lead-stat-card">
                        <p class="label">Total Leads</p>
                        <h3><?= number_format($pipelineStats['total'] ?? 0) ?></h3>
                        <small><?= $unassignedCount ?> unassigned</small>
                    </article>
                    <article class="lead-stat-card">
                        <p class="label">Today’s Intake</p>
                        <h3><?= number_format($pipelineStats['today'] ?? 0) ?></h3>
                        <small>New today</small>
                    </article>
                    <article class="lead-stat-card">
                        <p class="label">Hot Leads</p>
                        <h3><?= number_format($pipelineStats['hot'] ?? 0) ?></h3>
                        <small>Priority: Hot</small>
                    </article>
                    <article class="lead-stat-card">
                        <p class="label">Avg Score</p>
                        <h3><?= number_format($pipelineStats['avg_score'] ?? 0, 1) ?>/100</h3>
                        <small>Engagement quality</small>
                    </article>
                </section>

                <section class="status-chip-group mb-4">
                    <span class="text-muted me-2">Status quick filters:</span>
                    <a href="leads.php" class="status-chip <?= !$status ? 'active' : '' ?>">
                        <span>All</span>
                        <strong><?= number_format($pipelineStats['total'] ?? 0) ?></strong>
                    </a>
                    <?php foreach ($statusLabels as $key => $label): ?>
                        <a href="?status=<?= $key ?>&agent=<?= $agent ?>&search=<?= urlencode($search) ?>"
                           class="status-chip <?= $status === $key ? 'active' : '' ?>">
                            <span><?= $label ?></span>
                            <strong><?= number_format($statusCounts[$key] ?? 0) ?></strong>
                        </a>
                    <?php endforeach; ?>
                </section>

                <div class="card lead-filter-card mb-4">
                    <div class="card-body">
                        <form method="GET" class="row g-3 align-items-end">
                            <div class="col-lg-4">
                                <label class="form-label">Search</label>
                                <div class="input-icon">
                                    <i class="fas fa-search"></i>
                                    <input type="text" name="search" class="form-control" placeholder="Name, phone, city..."
                                        value="<?= htmlspecialchars($search) ?>">
                                </div>
                            </div>
                            <div class="col-lg-3 col-md-6">
                                <label class="form-label">Status</label>
                                <select name="status" class="form-select">
                                    <option value="">All Status</option>
                                    <?php foreach ($statusLabels as $key => $label): ?>
                                        <option value="<?= $key ?>" <?= $status === $key ? 'selected' : '' ?>>
                                            <?= $label ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-lg-3 col-md-6">
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
                            <div class="col-lg-2">
                                <label class="form-label d-block">&nbsp;</label>
                                <button type="submit" class="btn btn-primary w-100">Apply</button>
                            </div>
                        </form>
                    </div>
                </div>

                <div class="card table-card lead-table-card">
                    <div class="card-body p-0">
                        <?php if (empty($leads)): ?>
                            <div class="empty-state">
                                <i class="fas fa-user-slash"></i>
                                <h3>No leads found</h3>
                                <p>Try widening your filters or syncing new leads.</p>
                                <a href="leads.php" class="btn btn-outline-primary">
                                    Clear Filters
                                </a>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table align-middle mb-0">
                                    <thead>
                                        <tr>
                                            <th style="width: 40px;">
                                                <input type="checkbox" id="selectAll" onchange="toggleSelectAll()">
                                            </th>
                                            <th>Lead</th>
                                            <th>Campaign</th>
                                            <th>Status</th>
                                            <th>Agent</th>
                                            <th>Price</th>
                                            <th>Created</th>
                                            <th class="text-end">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($leads as $lead): ?>
                                            <tr>
                                                <td>
                                                    <input type="checkbox" class="lead-checkbox" value="<?= $lead['id'] ?>" onchange="updateBulkButtons()">
                                                </td>
                                                <td>
                                                    <div class="lead-cell">
                                                        <div>
                                                            <a href="#" onclick="viewLeadDetail(<?= $lead['id'] ?>); return false;" class="lead-name">
                                                                <?= htmlspecialchars($lead['full_name']) ?>
                                                            </a>
                                                            <div class="lead-meta">
                                                                <span><i class="fas fa-phone"></i> <?= htmlspecialchars($lead['phone_number']) ?></span>
                                                                <span><i class="fas fa-location-dot"></i> <?= htmlspecialchars($lead['city'] ?? '-') ?></span>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td>
                                                    <span class="badge badge-soft">
                                                        <?= htmlspecialchars($lead['campaign_name'] ?? 'N/A') ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <?php
                                                        $statusClass = [
                                                            'assigned' => 'warning',
                                                            'contacted' => 'info',
                                                            'qualified' => 'success',
                                                            'not_qualified' => 'danger',
                                                            'call_not_picked' => 'secondary',
                                                            'payment_completed' => 'primary'
                                                        ];
                                                        $class = $statusClass[$lead['assignment_status']] ?? 'secondary';
                                                    ?>
                                                    <span class="badge bg-<?= $class ?>">
                                                        <?= ucfirst($lead['assignment_status'] ?? 'Unassigned') ?>
                                                    </span>
                                                </td>
                                                <td><?= htmlspecialchars($lead['agent_name'] ?? 'Unassigned') ?></td>
                                                <td><?= $lead['price_offered'] ? '₹' . number_format($lead['price_offered']) : '-' ?></td>
                                                <td><?= date('M j, Y', strtotime($lead['created_at'])) ?></td>
                                                <td class="text-end">
                                                    <div class="table-actions">
                                                        <button class="btn-icon" onclick="assignLead(<?= $lead['id'] ?>, '<?= htmlspecialchars($lead['full_name']) ?>')" title="Assign Lead">
                                                            <i class="fas fa-user-plus"></i>
                                                        </button>
                                                        <a class="btn-icon" href="lead_profile.php?id=<?= (int)$lead['id'] ?>" title="Lead Profile">
                                                            <i class="fas fa-user-circle"></i>
                                                        </a>
                                                        <?php if ($lead['agent_name']): ?>
                                                            <button class="btn-icon" onclick="sendNotification(<?= $lead['id'] ?>, '<?= htmlspecialchars($lead['agent_name']) ?>', '<?= htmlspecialchars($lead['full_name']) ?>')" title="Send Notification">
                                                                <i class="fas fa-bell"></i>
                                                            </button>
                                                        <?php endif; ?>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>

                            <?php if ($totalPages > 1): ?>
                                <div class="pagination-wrapper">
                                    <nav>
                                        <ul class="pagination">
                                            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                                                <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                                                    <a class="page-link" href="?page=<?= $i ?>&status=<?= $status ?>&agent=<?= $agent ?>&search=<?= urlencode($search) ?>"><?= $i ?></a>
                                                </li>
                                            <?php endfor; ?>
                                        </ul>
                                    </nav>
                                </div>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="bulk-action-bar" id="bulkActionBar">
                    <div>
                        <strong id="selectedCount">0</strong> lead(s) selected
                    </div>
                    <div class="d-flex gap-2">
                        <button class="btn btn-outline-light" onclick="exportSelected()" id="exportBtn" disabled>
                            <i class="fas fa-file-export me-1"></i> Export
                        </button>
                        <button class="btn btn-primary" onclick="bulkAssign()" id="bulkAssignBtn" disabled>
                            <i class="fas fa-user-plus me-1"></i> Assign
                        </button>
                    </div>
                </div>
    <?php include __DIR__ . '/partials/layout_end.php'; ?>

    <!-- Assignment Modal -->
    <div class="modal fade" id="assignModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST">
                    <div class="modal-header">
                        <h5 class="modal-title">Assign Lead</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="action" value="assign">
                        <input type="hidden" name="lead_id" id="assignLeadId">
                        <p>Assign lead <strong id="assignLeadName"></strong> to:</p>
                        <select name="agent_id" class="form-select" required>
                            <option value="">Select Agent</option>
                            <?php foreach ($agents as $ag): ?>
                                <option value="<?= $ag['id'] ?>"><?= htmlspecialchars($ag['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Assign</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Lead Detail Modal -->
    <div class="modal fade" id="leadDetailModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Lead Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="leadDetailContent">
                    <div class="text-center">
                        <div class="spinner-border" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Bulk Assign Modal -->
    <div class="modal fade" id="bulkAssignModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST">
                    <div class="modal-header">
                        <h5 class="modal-title">Bulk Assign Leads</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="action" value="bulk_assign">
                        <input type="hidden" name="lead_ids" id="bulkLeadIds">
                        <p>Assign <span id="bulkLeadCount">0</span> selected leads to:</p>
                        <select name="agent_id" class="form-select" required>
                            <option value="">Select Agent</option>
                            <?php foreach ($agents as $ag): ?>
                                <option value="<?= $ag['id'] ?>"><?= htmlspecialchars($ag['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Assign</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function assignLead(leadId, leadName) {
            document.getElementById('assignLeadId').value = leadId;
            document.getElementById('assignLeadName').textContent = leadName;
            new bootstrap.Modal(document.getElementById('assignModal')).show();
        }
        
        function sendNotification(leadId, agentName, leadName) {
            if (confirm(`Send notification to ${agentName} for lead ${leadName}?`)) {
                fetch('../send_onesignal_notification.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        lead_id: leadId,
                        title: 'Manual Notification',
                        message: `Please contact lead: ${leadName}`
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('Notification sent successfully!');
                    } else {
                        alert('Failed to send notification: ' + data.message);
                    }
                })
                .catch(error => {
                    alert('Error sending notification: ' + error.message);
                });
            }
        }

        function toggleSelectAll() {
            const selectAll = document.getElementById('selectAll');
            const checkboxes = document.querySelectorAll('.lead-checkbox');
            checkboxes.forEach(cb => cb.checked = selectAll.checked);
            updateBulkButtons();
        }

        function updateBulkButtons() {
            const checked = document.querySelectorAll('.lead-checkbox:checked');
            const count = checked.length;
            const exportButtons = [document.getElementById('exportBtn'), document.getElementById('exportBtnTop')];
            const assignButtons = [document.getElementById('bulkAssignBtn'), document.getElementById('bulkAssignBtnTop')];
            exportButtons.forEach(btn => { if (btn) btn.disabled = count === 0; });
            assignButtons.forEach(btn => { if (btn) btn.disabled = count === 0; });
            document.getElementById('selectedCount').textContent = count;
            document.getElementById('bulkActionBar').classList.toggle('show', count > 0);
        }

        function bulkAssign() {
            const checked = Array.from(document.querySelectorAll('.lead-checkbox:checked')).map(cb => cb.value);
            if (checked.length === 0) {
                alert('Please select at least one lead');
                return;
            }
            document.getElementById('bulkLeadIds').value = JSON.stringify(checked);
            document.getElementById('bulkLeadCount').textContent = checked.length;
            new bootstrap.Modal(document.getElementById('bulkAssignModal')).show();
        }

        function exportSelected() {
            const checked = Array.from(document.querySelectorAll('.lead-checkbox:checked')).map(cb => cb.value);
            if (checked.length === 0) {
                alert('Please select at least one lead');
                return;
            }
            
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = 'leads.php';
            
            const actionInput = document.createElement('input');
            actionInput.type = 'hidden';
            actionInput.name = 'action';
            actionInput.value = 'export';
            form.appendChild(actionInput);
            
            const idsInput = document.createElement('input');
            idsInput.type = 'hidden';
            idsInput.name = 'lead_ids';
            idsInput.value = JSON.stringify(checked);
            form.appendChild(idsInput);
            
            document.body.appendChild(form);
            form.submit();
            document.body.removeChild(form);
        }

        function viewLeadDetail(leadId) {
            const modal = new bootstrap.Modal(document.getElementById('leadDetailModal'));
            const content = document.getElementById('leadDetailContent');
            
            content.innerHTML = '<div class="text-center"><div class="spinner-border" role="status"><span class="visually-hidden">Loading...</span></div></div>';
            modal.show();
            
            fetch(`get_lead_detail.php?id=${leadId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const lead = data.data;
                        const timeline = data.timeline || [];
                        
                        content.innerHTML = `
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <h5>${lead.full_name || 'N/A'}</h5>
                                    <p class="text-muted mb-1"><i class="fas fa-phone"></i> ${lead.phone_number || 'N/A'}</p>
                                    <p class="text-muted mb-1"><i class="fas fa-map-marker-alt"></i> ${lead.city || 'N/A'}</p>
                                    <p class="text-muted"><i class="fas fa-bullhorn"></i> ${lead.campaign_name || 'N/A'}</p>
                                </div>
                                <div class="col-md-6">
                                    <p><strong>Status:</strong> <span class="badge bg-primary">${lead.assignment_status || 'Unassigned'}</span></p>
                                    <p><strong>Agent:</strong> ${lead.agent_name || 'Unassigned'}</p>
                                    <p><strong>Price Offered:</strong> ₹${lead.price_offered || '0'}</p>
                                    <p><strong>Lead Score:</strong> ${lead.lead_score || 0}/100</p>
                                    <p><strong>Priority:</strong> <span class="badge bg-${lead.priority_level === 'hot' ? 'danger' : (lead.priority_level === 'high' ? 'warning' : 'info')}">${lead.priority_level || 'medium'}</span></p>
                                </div>
                            </div>
                            <hr>
                            <h6>Timeline</h6>
                            <div class="timeline">
                                ${timeline.map(item => `
                                    <div class="d-flex mb-2">
                                        <div class="flex-shrink-0">
                                            <i class="fas fa-circle text-primary"></i>
                                        </div>
                                        <div class="flex-grow-1 ms-3">
                                            <strong>${item.action}</strong>
                                            <p class="text-muted small mb-0">${item.description}</p>
                                            <small class="text-muted">${item.timestamp}</small>
                                        </div>
                                    </div>
                                `).join('')}
                            </div>
                        `;
                    } else {
                        content.innerHTML = '<div class="alert alert-danger">Failed to load lead details</div>';
                    }
                })
                .catch(error => {
                    content.innerHTML = '<div class="alert alert-danger">Error loading lead details</div>';
                });
        }

        document.addEventListener('DOMContentLoaded', updateBulkButtons);
    </script>
</body>
</html>
