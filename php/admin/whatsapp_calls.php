<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/jwt_helper.php';

if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    header('Location: login.php');
    exit();
}

$db = getDB();
$pageTitle = 'WhatsApp Calls Management';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?> - Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="css/global.css?v=<?= APP_ASSET_VERSION ?>" rel="stylesheet">
    <link href="css/admin.css?v=<?= APP_ASSET_VERSION ?>" rel="stylesheet">
</head>
<body class="admin-shell">
    <?php include __DIR__ . '/partials/layout_start.php'; ?>
    
    <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
        <h1 class="h2"><i class="fas fa-phone"></i> WhatsApp Calls</h1>
        <div class="btn-toolbar mb-2 mb-md-0">
            <button class="btn btn-sm btn-outline-secondary" onclick="refreshCalls()">
                <i class="fas fa-sync-alt"></i> Refresh
            </button>
        </div>
    </div>

    <!-- Call Settings Card -->
    <div class="card mb-4">
        <div class="card-header">
            <h5 class="mb-0"><i class="fas fa-cog"></i> Call Settings</h5>
        </div>
        <div class="card-body">
            <form id="callSettingsForm">
                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label class="form-label">Calling Status</label>
                            <select class="form-select" name="calling_status" id="calling_status">
                                <option value="ENABLED">Enabled</option>
                                <option value="DISABLED">Disabled</option>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label class="form-label">Call Icon Visibility</label>
                            <select class="form-select" name="call_icon_visibility" id="call_icon_visibility">
                                <option value="DEFAULT">Default</option>
                                <option value="HIDDEN">Hidden</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label class="form-label">Callback Permission</label>
                            <select class="form-select" name="callback_permission_status" id="callback_permission_status">
                                <option value="ENABLED">Enabled</option>
                                <option value="DISABLED">Disabled</option>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label class="form-label">Call Hours Status</label>
                            <select class="form-select" name="call_hours_status" id="call_hours_status">
                                <option value="ENABLED">Enabled</option>
                                <option value="DISABLED">Disabled</option>
                            </select>
                        </div>
                    </div>
                </div>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> Save Settings
                </button>
            </form>
        </div>
    </div>

    <!-- Calls List -->
    <div class="card">
        <div class="card-header">
            <h5 class="mb-0"><i class="fas fa-list"></i> Recent Calls</h5>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover" id="callsTable">
                    <thead>
                        <tr>
                            <th>Call ID</th>
                            <th>Phone Number</th>
                            <th>Direction</th>
                            <th>Status</th>
                            <th>Duration</th>
                            <th>Agent</th>
                            <th>Time</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="callsTableBody">
                        <tr>
                            <td colspan="8" class="text-center">Loading...</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <?php include __DIR__ . '/partials/layout_end.php'; ?>
    
    <script>
        const API_BASE = '../api';
        
        async function loadCallSettings() {
            try {
                const res = await fetch(`${API_BASE}/whatsapp/settings/calls`, {
                    headers: { 'Authorization': 'Bearer ' + getToken() }
                });
                const data = await res.json();
                if (data.success) {
                    const settings = data.data;
                    document.getElementById('calling_status').value = settings.calling_status || 'DISABLED';
                    document.getElementById('call_icon_visibility').value = settings.call_icon_visibility || 'DEFAULT';
                    document.getElementById('callback_permission_status').value = settings.callback_permission_status || 'DISABLED';
                    document.getElementById('call_hours_status').value = settings.call_hours_status || 'DISABLED';
                }
            } catch (error) {
                console.error('Error loading call settings:', error);
            }
        }
        
        async function loadCalls() {
            try {
                const res = await fetch(`${API_BASE}/whatsapp/calls`, {
                    headers: { 'Authorization': 'Bearer ' + getToken() }
                });
                const data = await res.json();
                const tbody = document.getElementById('callsTableBody');
                if (data.success && data.data && data.data.length > 0) {
                    tbody.innerHTML = data.data.map(call => `
                        <tr>
                            <td><code>${call.call_id}</code></td>
                            <td>${call.phone_number}</td>
                            <td><span class="badge bg-${call.direction === 'USER_INITIATED' ? 'info' : 'primary'}">${call.direction}</span></td>
                            <td><span class="badge bg-${getStatusColor(call.status)}">${call.status}</span></td>
                            <td>${call.duration ? formatDuration(call.duration) : '-'}</td>
                            <td>${call.assigned_agent_id || '-'}</td>
                            <td>${new Date(call.created_at).toLocaleString()}</td>
                            <td>
                                <button class="btn btn-sm btn-info" onclick="viewCall('${call.call_id}')">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </td>
                        </tr>
                    `).join('');
                } else {
                    tbody.innerHTML = '<tr><td colspan="8" class="text-center">No calls found</td></tr>';
                }
            } catch (error) {
                console.error('Error loading calls:', error);
                document.getElementById('callsTableBody').innerHTML = '<tr><td colspan="8" class="text-center text-danger">Error loading calls</td></tr>';
            }
        }
        
        function getStatusColor(status) {
            const colors = {
                'RINGING': 'warning',
                'ACCEPTED': 'success',
                'REJECTED': 'danger',
                'TERMINATED': 'secondary',
                'FAILED': 'danger',
                'COMPLETED': 'success'
            };
            return colors[status] || 'secondary';
        }
        
        function formatDuration(seconds) {
            const mins = Math.floor(seconds / 60);
            const secs = seconds % 60;
            return `${mins}:${secs.toString().padStart(2, '0')}`;
        }
        
        function getToken() {
            return sessionStorage.getItem('jwt') || localStorage.getItem('admin_token') || '';
        }
        
        function refreshCalls() {
            loadCalls();
        }
        
        function viewCall(callId) {
            window.location.href = `whatsapp_call_detail.php?id=${callId}`;
        }
        
        document.getElementById('callSettingsForm').addEventListener('submit', async (e) => {
            e.preventDefault();
            const formData = new FormData(e.target);
            const settings = Object.fromEntries(formData);
            
            try {
                const res = await fetch(`${API_BASE}/whatsapp/settings/calls`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Authorization': 'Bearer ' + getToken()
                    },
                    body: JSON.stringify(settings)
                });
                const data = await res.json();
                if (data.success) {
                    alert('Settings saved successfully');
                } else {
                    alert('Error: ' + (data.message || 'Failed to save settings'));
                }
            } catch (error) {
                alert('Error saving settings: ' + error.message);
            }
        });
        
        // Load data on page load
        loadCallSettings();
        loadCalls();
        setInterval(loadCalls, 30000); // Refresh every 30 seconds
    </script>
</body>
</html>

