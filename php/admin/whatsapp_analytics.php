<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/jwt_helper.php';

if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    header('Location: login.php');
    exit();
}

$db = getDB();
$pageTitle = 'WhatsApp Analytics';
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
        <h1 class="h2"><i class="fas fa-chart-line"></i> WhatsApp Analytics</h1>
        <div class="btn-toolbar mb-2 mb-md-0">
            <div class="btn-group me-2">
                <button class="btn btn-sm btn-outline-secondary" onclick="loadAnalytics('messaging')">
                    <i class="fas fa-envelope"></i> Messaging
                </button>
                <button class="btn btn-sm btn-outline-secondary" onclick="loadAnalytics('conversation')">
                    <i class="fas fa-comments"></i> Conversations
                </button>
                <button class="btn btn-sm btn-outline-secondary" onclick="loadAnalytics('pricing')">
                    <i class="fas fa-dollar-sign"></i> Pricing
                </button>
                <button class="btn btn-sm btn-outline-secondary" onclick="loadAnalytics('template')">
                    <i class="fas fa-file-alt"></i> Templates
                </button>
            </div>
        </div>
    </div>

    <!-- Date Range Selector -->
    <div class="card mb-4">
        <div class="card-body">
            <form id="analyticsForm" class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">Start Date</label>
                    <input type="date" class="form-control" id="startDate" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label">End Date</label>
                    <input type="date" class="form-control" id="endDate" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Granularity</label>
                    <select class="form-select" id="granularity">
                        <option value="DAY">Day</option>
                        <option value="HALF_HOUR">Half Hour</option>
                        <option value="MONTH">Month</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">&nbsp;</label>
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="fas fa-search"></i> Load Analytics
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Analytics Charts -->
    <div class="row mb-4">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0" id="chartTitle">Messaging Analytics</h5>
                </div>
                <div class="card-body">
                    <canvas id="analyticsChart" height="100"></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- Analytics Data Table -->
    <div class="card">
        <div class="card-header">
            <h5 class="mb-0">Analytics Data</h5>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover" id="analyticsTable">
                    <thead id="analyticsTableHead"></thead>
                    <tbody id="analyticsTableBody"></tbody>
                </table>
            </div>
        </div>
    </div>

    <?php include __DIR__ . '/partials/layout_end.php'; ?>
    
    <script>
        const API_BASE = '../api';
        let analyticsChart = null;
        
        // Set default dates (last 30 days)
        document.getElementById('startDate').valueAsDate = new Date(Date.now() - 30 * 24 * 60 * 60 * 1000);
        document.getElementById('endDate').valueAsDate = new Date();
        
        document.getElementById('analyticsForm').addEventListener('submit', async (e) => {
            e.preventDefault();
            const type = document.querySelector('.btn-group .btn.active')?.getAttribute('onclick')?.match(/'(\w+)'/)?.[1] || 'messaging';
            await loadAnalytics(type);
        });
        
        async function loadAnalytics(type) {
            const startDate = document.getElementById('startDate').value;
            const endDate = document.getElementById('endDate').value;
            const granularity = document.getElementById('granularity').value;
            
            const startTime = Math.floor(new Date(startDate).getTime() / 1000);
            const endTime = Math.floor(new Date(endDate).getTime() / 1000);
            
            try {
                const res = await fetch(`${API_BASE}/whatsapp/analytics/${type}?start=${startTime}&end=${endTime}&granularity=${granularity}`, {
                    headers: { 'Authorization': 'Bearer ' + getToken() }
                });
                const data = await res.json();
                
                if (data.success) {
                    renderAnalytics(data.data, type);
                } else {
                    alert('Error: ' + (data.message || 'Failed to load analytics'));
                }
            } catch (error) {
                console.error('Error loading analytics:', error);
                alert('Error loading analytics: ' + error.message);
            }
        }
        
        function renderAnalytics(analyticsData, type) {
            document.getElementById('chartTitle').textContent = type.charAt(0).toUpperCase() + type.slice(1) + ' Analytics';
            
            // Render chart
            const ctx = document.getElementById('analyticsChart').getContext('2d');
            if (analyticsChart) {
                analyticsChart.destroy();
            }
            
            const chartData = prepareChartData(analyticsData, type);
            analyticsChart = new Chart(ctx, {
                type: 'line',
                data: chartData,
                options: {
                    responsive: true,
                    scales: {
                        y: { beginAtZero: true }
                    }
                }
            });
            
            // Render table
            renderTable(analyticsData, type);
        }
        
        function prepareChartData(data, type) {
            const labels = [];
            const datasets = [];
            
            if (type === 'messaging' && data.data_points) {
                data.data_points.forEach(point => {
                    labels.push(new Date(point.start * 1000).toLocaleDateString());
                });
                datasets.push({
                    label: 'Sent',
                    data: data.data_points.map(p => p.sent || 0),
                    borderColor: 'rgb(75, 192, 192)',
                    backgroundColor: 'rgba(75, 192, 192, 0.2)'
                });
                datasets.push({
                    label: 'Delivered',
                    data: data.data_points.map(p => p.delivered || 0),
                    borderColor: 'rgb(54, 162, 235)',
                    backgroundColor: 'rgba(54, 162, 235, 0.2)'
                });
            }
            
            return { labels, datasets };
        }
        
        function renderTable(data, type) {
            const thead = document.getElementById('analyticsTableHead');
            const tbody = document.getElementById('analyticsTableBody');
            
            if (type === 'messaging' && data.data_points) {
                thead.innerHTML = '<tr><th>Date</th><th>Sent</th><th>Delivered</th></tr>';
                tbody.innerHTML = data.data_points.map(point => `
                    <tr>
                        <td>${new Date(point.start * 1000).toLocaleString()}</td>
                        <td>${point.sent || 0}</td>
                        <td>${point.delivered || 0}</td>
                    </tr>
                `).join('');
            }
        }
        
        function getToken() {
            return sessionStorage.getItem('jwt') || localStorage.getItem('admin_token') || '';
        }
        
        // Set active button
        document.querySelectorAll('.btn-group .btn').forEach(btn => {
            btn.addEventListener('click', function() {
                document.querySelectorAll('.btn-group .btn').forEach(b => b.classList.remove('active'));
                this.classList.add('active');
            });
        });
        
        // Load default analytics
        document.querySelector('.btn-group .btn').classList.add('active');
        document.getElementById('analyticsForm').dispatchEvent(new Event('submit'));
    </script>
</body>
</html>

