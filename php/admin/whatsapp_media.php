<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/jwt_helper.php';

if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    header('Location: login.php');
    exit();
}

$pageTitle = 'WhatsApp Media Management';
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
        <h1 class="h2"><i class="fas fa-images"></i> Media Management</h1>
        <div class="btn-toolbar mb-2 mb-md-0">
            <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#uploadModal">
                <i class="fas fa-upload"></i> Upload Media
            </button>
        </div>
    </div>

    <!-- Upload Modal -->
    <div class="modal fade" id="uploadModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Upload Media</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="uploadForm">
                        <div class="mb-3">
                            <label class="form-label">Select File</label>
                            <input type="file" class="form-control" id="mediaFile" accept="image/*,video/*,audio/*,.pdf,.doc,.docx" required>
                            <small class="form-text text-muted">Max size: 15MB</small>
                        </div>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-upload"></i> Upload
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Media Grid -->
    <div class="row" id="mediaGrid">
        <div class="col-12 text-center">
            <p>Loading media...</p>
        </div>
    </div>

    <?php include __DIR__ . '/partials/layout_end.php'; ?>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const API_BASE = '../api';
        
        document.getElementById('uploadForm').addEventListener('submit', async (e) => {
            e.preventDefault();
            const fileInput = document.getElementById('mediaFile');
            const file = fileInput.files[0];
            
            if (!file) {
                alert('Please select a file');
                return;
            }
            
            const formData = new FormData();
            formData.append('file', file);
            formData.append('mime_type', file.type);
            
            try {
                const res = await fetch(`${API_BASE}/whatsapp/media/upload`, {
                    method: 'POST',
                    headers: { 'Authorization': 'Bearer ' + getToken() },
                    body: formData
                });
                const data = await res.json();
                
                if (data.success) {
                    alert('Media uploaded successfully! Media ID: ' + data.media_id);
                    fileInput.value = '';
                    bootstrap.Modal.getInstance(document.getElementById('uploadModal')).hide();
                    loadMedia();
                } else {
                    alert('Error: ' + (data.message || 'Upload failed'));
                }
            } catch (error) {
                alert('Error uploading media: ' + error.message);
            }
        });
        
        function getToken() {
            return sessionStorage.getItem('jwt') || localStorage.getItem('admin_token') || '';
        }
        
        function loadMedia() {
            // This would load media from your storage/R2
            document.getElementById('mediaGrid').innerHTML = '<div class="col-12 text-center"><p>Media management interface - integrate with your media storage</p></div>';
        }
        
        loadMedia();
    </script>
</body>
</html>

