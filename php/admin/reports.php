<?php
session_start();
require_once '../includes/config.php';

if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    header('Location: login.php');
    exit();
}

$db = getDB();
$period = $_GET['period'] ?? 'month';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports - CRM Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
                    <h1 class="h2">Reports & Analytics</h1>
                    <div>
                        <div class="btn-group me-2">
                            <button class="btn btn-outline-primary <?= $period === 'week' ? 'active' : '' ?>" onclick="changePeriod('week')">Week</button>
                            <button class="btn btn-outline-primary <?= $period === 'month' ? 'active' : '' ?>" onclick="changePeriod('month')">Month</button>
                        </div>
                        <button class="btn btn-success" onclick="exportReport()">
                            <i class="fas fa-download"></i> Export Report
                        </button>
                    </div>
                </div>

                <!-- P&L Chart -->
                <div class="row mb-4">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header">
                                <h5>Profit & Loss Analysis</h5>
                            </div>
                            <div class="card-body">
                                <canvas id="pnlChart" height="100"></canvas>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Performance Metrics -->
                <div class="row mb-4">
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <h5>Top Performing Agents</h5>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-sm">
                                        <thead>
                                            <tr>
                                                <th>Agent</th>
                                                <th>Leads</th>
                                                <th>Qualified</th>
                                                <th>Revenue</th>
                                            </tr>
                                        </thead>
                                        <tbody id="topAgentsTable">
                                            <tr><td colspan="4" class="text-center">Loading...</td></tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <h5>Campaign Performance</h5>
                            </div>
                            <div class="card-body">
                                <canvas id="campaignChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Summary Cards -->
                <div class="row">
                    <div class="col-md-3">
                        <div class="card bg-primary text-white">
                            <div class="card-body">
                                <h4 id="totalRevenue">₹0</h4>
                                <p>Total Revenue</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-danger text-white">
                            <div class="card-body">
                                <h4 id="totalExpenses">₹0</h4>
                                <p>Total Expenses</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-success text-white">
                            <div class="card-body">
                                <h4 id="totalProfit">₹0</h4>
                                <p>Net Profit</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-info text-white">
                            <div class="card-body">
                                <h4 id="conversionRate">0%</h4>
                                <p>Conversion Rate</p>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        let pnlChart, campaignChart;
        
        document.addEventListener('DOMContentLoaded', function() {
            loadReports();
        });

        function changePeriod(period) {
            window.location.href = `reports.php?period=${period}`;
        }

        async function loadReports() {
            try {
                // Load P&L data
                const pnlResponse = await fetch(`../reports.php?endpoint=pnl&period=<?= $period ?>`);
                const pnlData = await pnlResponse.json();
                
                if (pnlData.success) {
                    createPnLChart(pnlData.data.data);
                    updateSummaryCards(pnlData.data.totals);
                }

                // Load top performers
                const performersResponse = await fetch(`../reports.php?endpoint=top-performers&period=<?= $period ?>`);
                const performersData = await performersResponse.json();
                
                if (performersData.success) {
                    updateTopAgentsTable(performersData.data);
                }
            } catch (error) {
                console.error('Error loading reports:', error);
            }
        }

        function createPnLChart(data) {
            const ctx = document.getElementById('pnlChart').getContext('2d');
            
            if (pnlChart) pnlChart.destroy();
            
            pnlChart = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: data.map(d => new Date(d.date).toLocaleDateString()),
                    datasets: [
                        {
                            label: 'Revenue',
                            data: data.map(d => d.revenue),
                            borderColor: '#28a745',
                            backgroundColor: 'rgba(40, 167, 69, 0.1)',
                            tension: 0.4
                        },
                        {
                            label: 'Expenses',
                            data: data.map(d => d.ad_spend + d.expenses),
                            borderColor: '#dc3545',
                            backgroundColor: 'rgba(220, 53, 69, 0.1)',
                            tension: 0.4
                        },
                        {
                            label: 'Profit',
                            data: data.map(d => d.profit),
                            borderColor: '#007bff',
                            backgroundColor: 'rgba(0, 123, 255, 0.1)',
                            tension: 0.4
                        }
                    ]
                },
                options: {
                    responsive: true,
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                callback: function(value) {
                                    return '₹' + value.toLocaleString();
                                }
                            }
                        }
                    }
                }
            });
        }

        function updateSummaryCards(totals) {
            document.getElementById('totalRevenue').textContent = '₹' + totals.total_revenue.toLocaleString();
            document.getElementById('totalExpenses').textContent = '₹' + (totals.total_ad_spend + totals.total_expenses).toLocaleString();
            document.getElementById('totalProfit').textContent = '₹' + totals.total_profit.toLocaleString();
        }

        function updateTopAgentsTable(agents) {
            const tbody = document.getElementById('topAgentsTable');
            
            if (agents.length === 0) {
                tbody.innerHTML = '<tr><td colspan="4" class="text-center">No data available</td></tr>';
                return;
            }
            
            tbody.innerHTML = agents.slice(0, 5).map(agent => `
                <tr>
                    <td>${agent.name}</td>
                    <td>${agent.total_leads}</td>
                    <td>${agent.qualified_leads}</td>
                    <td>₹${parseFloat(agent.total_revenue).toLocaleString()}</td>
                </tr>
            `).join('');
        }

        function exportReport() {
            const period = '<?= $period ?>';
            window.location.href = `export_report.php?period=${period}`;
        }
    </script>
</body>
</html>