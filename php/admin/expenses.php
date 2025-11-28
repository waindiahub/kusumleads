<?php
session_start();
require_once '../includes/config.php';

if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    header('Location: login.php');
    exit();
}

$db = getDB();

// Handle expense addition
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'add_expense') {
        $amount = $_POST['amount'] ?? '';
        $date = $_POST['date'] ?? '';
        $category = $_POST['category'] ?? '';
        $note = $_POST['note'] ?? '';
        
        if ($amount && $date) {
            try {
                $stmt = $db->prepare("INSERT INTO expenses (amount, date, category, note) VALUES (?, ?, ?, ?)");
                $stmt->execute([$amount, $date, $category, $note]);
                $success = "Expense added successfully";
            } catch (Exception $e) {
                $error = "Failed to add expense";
            }
        } else {
            $error = "Amount and date are required";
        }
    } elseif ($_POST['action'] === 'add_budget') {
        $campaign_id = $_POST['campaign_id'] ?? '';
        $date = $_POST['date'] ?? '';
        $budget = $_POST['budget_amount'] ?? '';
        $spend = $_POST['spend_amount'] ?? '';
        
        if ($campaign_id && $date && $budget) {
            try {
                $stmt = $db->prepare("INSERT INTO ad_budgets (campaign_id, date, budget_amount, spend_amount) 
                    VALUES (?, ?, ?, ?) 
                    ON DUPLICATE KEY UPDATE budget_amount = VALUES(budget_amount), spend_amount = VALUES(spend_amount)");
                $stmt->execute([$campaign_id, $date, $budget, $spend]);
                $success = "Ad budget updated successfully";
            } catch (Exception $e) {
                $error = "Failed to update ad budget";
            }
        } else {
            $error = "Campaign ID, date and budget are required";
        }
    }
}

// Get expenses
$stmt = $db->query("SELECT * FROM expenses ORDER BY date DESC LIMIT 50");
$expenses = $stmt->fetchAll();

// Get ad budgets
$stmt = $db->query("SELECT * FROM ad_budgets ORDER BY date DESC LIMIT 50");
$budgets = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Expenses & Budgets - CRM Admin</title>
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
                    <h1 class="h2">Expenses & Ad Budgets</h1>
                    <div>
                        <button class="btn btn-primary me-2" data-bs-toggle="modal" data-bs-target="#expenseModal">
                            <i class="fas fa-plus"></i> Add Expense
                        </button>
                        <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#budgetModal">
                            <i class="fas fa-plus"></i> Add Budget
                        </button>
                    </div>
                </div>

                <?php if (isset($success)): ?>
                    <div class="alert alert-success"><?= $success ?></div>
                <?php endif; ?>
                <?php if (isset($error)): ?>
                    <div class="alert alert-danger"><?= $error ?></div>
                <?php endif; ?>

                <div class="row">
                    <!-- Expenses -->
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <h5>Recent Expenses</h5>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-sm">
                                        <thead>
                                            <tr>
                                                <th>Date</th>
                                                <th>Amount</th>
                                                <th>Category</th>
                                                <th>Note</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($expenses as $expense): ?>
                                                <tr>
                                                    <td><?= date('M j, Y', strtotime($expense['date'])) ?></td>
                                                    <td>₹<?= number_format($expense['amount']) ?></td>
                                                    <td><?= htmlspecialchars($expense['category'] ?? '-') ?></td>
                                                    <td><?= htmlspecialchars($expense['note'] ?? '-') ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Ad Budgets -->
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <h5>Ad Budgets</h5>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-sm">
                                        <thead>
                                            <tr>
                                                <th>Date</th>
                                                <th>Campaign</th>
                                                <th>Budget</th>
                                                <th>Spend</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($budgets as $budget): ?>
                                                <tr>
                                                    <td><?= date('M j, Y', strtotime($budget['date'])) ?></td>
                                                    <td><?= htmlspecialchars($budget['campaign_id']) ?></td>
                                                    <td>₹<?= number_format($budget['budget_amount']) ?></td>
                                                    <td>₹<?= number_format($budget['spend_amount']) ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Add Expense Modal -->
    <div class="modal fade" id="expenseModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST">
                    <div class="modal-header">
                        <h5 class="modal-title">Add Expense</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="action" value="add_expense">
                        <div class="mb-3">
                            <label class="form-label">Amount (₹)</label>
                            <input type="number" name="amount" class="form-control" step="0.01" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Date</label>
                            <input type="date" name="date" class="form-control" value="<?= date('Y-m-d') ?>" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Category</label>
                            <select name="category" class="form-select">
                                <option value="">Select Category</option>
                                <option value="Marketing">Marketing</option>
                                <option value="Operations">Operations</option>
                                <option value="Staff">Staff</option>
                                <option value="Technology">Technology</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Note</label>
                            <textarea name="note" class="form-control" rows="3"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Add Expense</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Add Budget Modal -->
    <div class="modal fade" id="budgetModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST">
                    <div class="modal-header">
                        <h5 class="modal-title">Add/Update Ad Budget</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="action" value="add_budget">
                        <div class="mb-3">
                            <label class="form-label">Campaign ID</label>
                            <input type="text" name="campaign_id" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Date</label>
                            <input type="date" name="date" class="form-control" value="<?= date('Y-m-d') ?>" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Budget Amount (₹)</label>
                            <input type="number" name="budget_amount" class="form-control" step="0.01" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Spend Amount (₹)</label>
                            <input type="number" name="spend_amount" class="form-control" step="0.01" value="0">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-success">Save Budget</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>