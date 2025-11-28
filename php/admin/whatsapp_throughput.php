<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/jwt_helper.php';

if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    header('Location: login.php');
    exit();
}

$pageTitle = 'WhatsApp Throughput Management';
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
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body class="admin-shell">
    <?php include __DIR__ . '/partials/layout_start.php'; ?>
    
    <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
        <h1 class="h2"><i class="fas fa-tachometer-alt"></i> Throughput Management</h1>
        <div class="btn-toolbar mb-2 mb-md-0">
            <button class="btn btn-sm btn-outline-secondary" onclick="refreshThroughput()">
                <i class="fas fa-sync-alt"></i> Refresh
            </button>
            <button class="btn btn-sm btn-primary" onclick="processQueue()">
                <i class="fas fa-play"></i> Process Queue
            </button>
        </div>
    </div>

    <!-- Current Throughput Status -->
    <div class="row mb-4">
        <div class="col-md-4">
            <div class="card text-white bg-primary">
                <div class="card-body">
                    <h5 class="card-title">Current Rate</h5>
                    <h2 id="currentRate">0</h2>
                    <p class="card-text">messages/second</p>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card text-white bg-success">
                <div class="card-body">
                    <h5 class="card-title">Max Throughput</h5>
                    <h2 id="maxThroughput">80</h2>
                    <p class="card-text">messages/second</p>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card text-white bg-info">
                <div class="card-body">
                    <h5 class="card-title">Utilization</h5>
                    <h2 id="utilization">0%</h2>
                    <p class="card-text" id="utilizationStatus">Available</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Throughput Chart -->
    <div class="card mb-4">
        <div class="card-header">
            <h5 class="mb-0">Throughput History (Last 24 Hours)</h5>
        </div>
        <div class="card-body">
            <canvas id="throughputChart" height="100"></canvas>
        </div>
    </div>

    <!-- Message Queue -->
    <div class="card">
        <div class="card-header">
            <h5 class="mb-0">Message Queue</h5>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Phone Number</th>
                            <th>Message Type</th>
                            <th>Priority</th>
                            <th>Status</th>
                            <th>Created</th>
                        </tr>
                    </thead>
                    <tbody id="queueTableBody">
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
        let throughputChart = null;
        
        async function loadThroughput() {
            try {
                const res = await fetch(`${API_BASE}/whatsapp/throughput`, {
                    headers: { 'Authorization': 'Bearer ' + getToken() }
                });
                const data = await res.json();
                
                if (data.success && data.throughput) {
                    document.getElementById('maxThroughput').textContent = data.throughput.level === 'HIGH' ? '1000' : (data.throughput.level === 'COEXISTENCE' ? '20' : '80');
                }
            } catch (error) {
                console.error('Error loading throughput:', error);
            }
        }
        
        async function monitorThroughput() {
            try {
                const res = await fetch(`${API_BASE}/whatsapp/throughput/monitor`, {
                    headers: { 'Authorization': 'Bearer ' + getToken() }
                });
                const data = await res.json();
                
                if (data.success) {
                    document.getElementById('currentRate').textContent = data.current_rate;
                    document.getElementById('utilization').textContent = data.utilization_percent.toFixed(1) + '%';
                    document.getElementById('utilizationStatus').textContent = data.can_send ? 'Available' : 'At Limit';
                }
            } catch (error) {
                console.error('Error monitoring throughput:', error);
            }
        }
        
        async function loadThroughputHistory() {
            try {
                const res = await fetch(`${API_BASE}/whatsapp/throughput/history?hours=24`, {
                    headers: { 'Authorization': 'Bearer ' + getToken() }
                });
                const data = await res.json();
                
                if (data.success && data.length > 0) {
                    const ctx = document.getElementById('throughputChart').getContext('2d');
                    if (throughputChart) {
                        throughputChart.destroy();
                    }
                    
                    throughputChart = new Chart(ctx, {
                        type: 'line',
                        data: {
                            labels: data.map(d => new Date(d.created_at).toLocaleTimeString()),
                            datasets: [{
                                label: 'Current Rate',
                                data: data.map(d => d.current_rate),
                                borderColor: 'rgb(75, 192, 192)',
                                backgroundColor: 'rgba(75, 192, 192, 0.2)'
                            }, {
                                label: 'Max Throughput',
                                data: data.map(d => d.max_throughput),
                                borderColor: 'rgb(255, 99, 132)',
                                backgroundColor: 'rgba(255, 99, 132, 0.2)'
                            }]
                        },
                        options: {
                            responsive: true,
                            scales: {
                                y: { beginAtZero: true }
                            }
                        }
                    });
                }
            } catch (error) {
                console.error('Error loading throughput history:', error);
            }
        }
        
        async function processQueue() {
            try {
                const res = await fetch(`${API_BASE}/whatsapp/throughput/queue/process`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Authorization': 'Bearer ' + getToken()
                    },
                    body: JSON.stringify({ limit: 10 })
                });
                const data = await res.json();
                
                if (data.success) {
                    alert(`Processed ${data.processed} messages. ${data.remaining} remaining.`);
                    loadQueue();
                }
            } catch (error) {
                alert('Error processing queue: ' + error.message);
            }
        }
        
        function loadQueue() {
            document.getElementById('queueTableBody').innerHTML = '<tr><td colspan="5" class="text-center">Queue management - integrate with your queue system</td></tr>';
        }
        
        function refreshThroughput() {
            loadThroughput();
            monitorThroughput();
            loadThroughputHistory();
        }
        
        function getToken() {
            return sessionStorage.getItem('jwt') || localStorage.getItem('admin_token') || '';
        }
        
        // Load data on page load
        refreshThroughput();
        setInterval(() => {
            monitorThroughput();
        }, 5000); // Update every 5 seconds
        setInterval(loadThroughputHistory, 60000); // Update history every minute
    </script>
</body>
</html>

