<?php
// Enable error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();

try {
    require_once '../includes/config.php';
    require_once '../includes/jwt_helper.php';
    
    // Check if user is logged in and is admin
    if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
        header('Location: login.php');
        exit();
    }
    
    $user = $_SESSION['user'];
} catch (Exception $e) {
    die('Error: ' . $e->getMessage() . ' in ' . $e->getFile() . ' on line ' . $e->getLine());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CRM Admin Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="css/global.css?v=<?= APP_ASSET_VERSION ?>" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link href="css/admin.css?v=<?= APP_ASSET_VERSION ?>" rel="stylesheet">
</head>
<body class="admin-shell">
    <?php include __DIR__ . '/partials/layout_start.php'; ?>
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">Dashboard</h1>
                </div>

                <!-- Stats Cards -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="card bg-primary text-white">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h4 id="totalLeads">0</h4>
                                        <p>Total Leads</p>
                                        <small id="totalLeadsChange" class="opacity-75"></small>
                                    </div>
                                    <i class="fas fa-users fa-2x"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-success text-white">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h4 id="qualifiedLeads">0</h4>
                                        <p>Qualified</p>
                                        <small id="qualifiedRate" class="opacity-75"></small>
                                    </div>
                                    <i class="fas fa-check-circle fa-2x"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-warning text-white">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h4 id="activeAgents">0</h4>
                                        <p>Active Agents</p>
                                        <small id="onlineAgents" class="opacity-75"></small>
                                    </div>
                                    <i class="fas fa-user-tie fa-2x"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-info text-white">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h4 id="todayLeads">0</h4>
                                        <p>Today's Leads</p>
                                        <small id="todayRevenue" class="opacity-75"></small>
                                    </div>
                                    <i class="fas fa-calendar-day fa-2x"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Additional Stats Row -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="card bg-danger text-white">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h4 id="totalRevenue">₹0</h4>
                                        <p>Total Revenue</p>
                                    </div>
                                    <i class="fas fa-rupee-sign fa-2x"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-secondary text-white">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h4 id="pendingLeads">0</h4>
                                        <p>Pending Leads</p>
                                    </div>
                                    <i class="fas fa-hourglass-half fa-2x"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-dark text-white">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h4 id="conversionRate">0%</h4>
                                        <p>Conversion Rate</p>
                                    </div>
                                    <i class="fas fa-percentage fa-2x"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white;">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h4 id="avgResponseTime">0h</h4>
                                        <p>Avg Response Time</p>
                                    </div>
                                    <i class="fas fa-stopwatch fa-2x"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Charts -->
                <div class="row mb-4">
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <h5>Lead Status Distribution</h5>
                            </div>
                            <div class="card-body">
                                <canvas id="statusChart"></canvas>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <h5>Daily P&L (Last 7 Days)</h5>
                            </div>
                            <div class="card-body">
                                <canvas id="pnlChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Activity Feed and Top Performers -->
                <div class="row mb-4">
                    <div class="col-md-7">
                        <div class="card">
                            <div class="card-header">
                                <h5>Top Performing Agents</h5>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-striped">
                                        <thead>
                                            <tr>
                                                <th>Agent</th>
                                                <th>Total Leads</th>
                                                <th>Qualified</th>
                                                <th>Conversion Rate</th>
                                                <th>Revenue</th>
                                            </tr>
                                        </thead>
                                        <tbody id="topPerformersTable">
                                            <tr><td colspan="5" class="text-center">Loading...</td></tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-5">
                        <div class="card">
                            <div class="card-header">
                                <h5>Recent Activity</h5>
                            </div>
                            <div class="card-body" style="max-height: 400px; overflow-y: auto;">
                                <div id="activityFeed">
                                    <div class="text-center text-muted">Loading...</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Campaign Performance -->
                <div class="row mb-4">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header">
                                <h5>Top Campaigns Performance</h5>
                            </div>
                            <div class="card-body">
                                <canvas id="campaignChart" height="80"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
    <?php include __DIR__ . '/partials/layout_end.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="js/dashboard.js?v=<?= APP_ASSET_VERSION ?>"></script>
</body>
</html>