<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/jwt_helper.php';

if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    header('Location: login.php');
    exit();
}

$pageTitle = 'Welcome Message Sequences';
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
        <h1 class="h2"><i class="fas fa-hand-wave"></i> Welcome Message Sequences</h1>
        <div class="btn-toolbar mb-2 mb-md-0">
            <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#createSequenceModal">
                <i class="fas fa-plus"></i> Create Sequence
            </button>
        </div>
    </div>

    <!-- Create/Edit Sequence Modal -->
    <div class="modal fade" id="createSequenceModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Create Welcome Sequence</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="sequenceForm">
                        <div class="mb-3">
                            <label class="form-label">Sequence Name</label>
                            <input type="text" class="form-control" name="name" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Welcome Text</label>
                            <textarea class="form-control" name="text" rows="3" required></textarea>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Autofill Message (Optional)</label>
                            <input type="text" class="form-control" name="autofill_message" placeholder="Hello! Can I get more info on this!">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Ice Breakers (one per line, max 3)</label>
                            <textarea class="form-control" name="ice_breakers" rows="3" placeholder="Quick reply 1&#10;Quick reply 2&#10;Quick reply 3"></textarea>
                            <small class="form-text text-muted">Enter up to 3 ice breakers, one per line</small>
                        </div>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> Save Sequence
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Sequences List -->
    <div class="card">
        <div class="card-header">
            <h5 class="mb-0">Welcome Sequences</h5>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Text</th>
                            <th>Ice Breakers</th>
                            <th>Created</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="sequencesTableBody">
                        <tr>
                            <td colspan="5" class="text-center">Loading...</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <?php include __DIR__ . '/partials/layout_end.php'; ?>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const API_BASE = '../api';
        
        document.getElementById('sequenceForm').addEventListener('submit', async (e) => {
            e.preventDefault();
            const formData = new FormData(e.target);
            const iceBreakersText = formData.get('ice_breakers');
            const iceBreakers = iceBreakersText ? iceBreakersText.split('\n').filter(b => b.trim()).slice(0, 3) : [];
            
            const sequenceData = {
                name: formData.get('name'),
                text: formData.get('text'),
                autofill_message: formData.get('autofill_message') || null,
                ice_breakers: iceBreakers
            };
            
            try {
                const res = await fetch(`${API_BASE}/whatsapp/welcome_sequences`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Authorization': 'Bearer ' + getToken()
                    },
                    body: JSON.stringify(sequenceData)
                });
                const data = await res.json();
                
                if (data.success) {
                    alert('Sequence created successfully!');
                    e.target.reset();
                    bootstrap.Modal.getInstance(document.getElementById('createSequenceModal')).hide();
                    loadSequences();
                } else {
                    alert('Error: ' + (data.message || 'Failed to create sequence'));
                }
            } catch (error) {
                alert('Error creating sequence: ' + error.message);
            }
        });
        
        async function loadSequences() {
            try {
                const res = await fetch(`${API_BASE}/whatsapp/welcome_sequences`, {
                    headers: { 'Authorization': 'Bearer ' + getToken() }
                });
                const data = await res.json();
                
                const tbody = document.getElementById('sequencesTableBody');
                if (data.success && data.data && data.data.length > 0) {
                    tbody.innerHTML = data.data.map(seq => `
                        <tr>
                            <td>${seq.name || '-'}</td>
                            <td>${seq.text || '-'}</td>
                            <td>${seq.ice_breakers ? seq.ice_breakers.length : 0} breakers</td>
                            <td>${seq.created_at ? new Date(seq.created_at).toLocaleDateString() : '-'}</td>
                            <td>
                                <button class="btn btn-sm btn-danger" onclick="deleteSequence('${seq.sequence_id}')">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </td>
                        </tr>
                    `).join('');
                } else {
                    tbody.innerHTML = '<tr><td colspan="5" class="text-center">No sequences found</td></tr>';
                }
            } catch (error) {
                console.error('Error loading sequences:', error);
            }
        }
        
        async function deleteSequence(sequenceId) {
            if (!confirm('Are you sure you want to delete this sequence?')) return;
            
            try {
                const res = await fetch(`${API_BASE}/whatsapp/welcome_sequences/${sequenceId}`, {
                    method: 'DELETE',
                    headers: { 'Authorization': 'Bearer ' + getToken() }
                });
                const data = await res.json();
                
                if (data.success) {
                    alert('Sequence deleted successfully');
                    loadSequences();
                } else {
                    alert('Error: ' + (data.message || 'Failed to delete sequence'));
                }
            } catch (error) {
                alert('Error deleting sequence: ' + error.message);
            }
        }
        
        function getToken() {
            return sessionStorage.getItem('jwt') || localStorage.getItem('admin_token') || '';
        }
        
        loadSequences();
    </script>
</body>
</html>

