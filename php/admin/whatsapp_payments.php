<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/jwt_helper.php';

if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    header('Location: login.php');
    exit();
}

$db = getDB();
$pageTitle = 'WhatsApp Payments (Cashfree)';
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
        <h1 class="h2"><i class="fas fa-credit-card"></i> Payment Orders</h1>
        <div class="btn-toolbar mb-2 mb-md-0">
            <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#createOrderModal">
                <i class="fas fa-plus"></i> Create Order
            </button>
        </div>
    </div>

    <!-- Create Order Modal -->
    <div class="modal fade" id="createOrderModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Create Payment Order</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="createOrderForm">
                        <div class="mb-3">
                            <label class="form-label">Order Amount</label>
                            <input type="number" step="0.01" class="form-control" name="order_amount" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Customer Phone</label>
                            <input type="text" class="form-control" name="customer_phone" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Customer Email</label>
                            <input type="email" class="form-control" name="customer_email">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Order Note</label>
                            <textarea class="form-control" name="order_note"></textarea>
                        </div>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-check"></i> Create Order
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Payment Orders Table -->
    <div class="card">
        <div class="card-header">
            <h5 class="mb-0">Payment Orders</h5>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Order ID</th>
                            <th>Amount</th>
                            <th>Customer</th>
                            <th>Status</th>
                            <th>Created</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="ordersTableBody">
                        <tr>
                            <td colspan="6" class="text-center">Loading...</td>
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
        
        document.getElementById('createOrderForm').addEventListener('submit', async (e) => {
            e.preventDefault();
            const formData = new FormData(e.target);
            const orderData = {
                order_amount: parseFloat(formData.get('order_amount')),
                order_currency: 'INR',
                customer_phone: formData.get('customer_phone'),
                customer_email: formData.get('customer_email'),
                order_note: formData.get('order_note'),
                payment_methods: 'upi'
            };
            
            try {
                const res = await fetch(`${API_BASE}/whatsapp/payments/orders`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Authorization': 'Bearer ' + getToken()
                    },
                    body: JSON.stringify(orderData)
                });
                const data = await res.json();
                
                if (data.success) {
                    alert('Order created successfully! Order ID: ' + data.data.order_id);
                    e.target.reset();
                    bootstrap.Modal.getInstance(document.getElementById('createOrderModal')).hide();
                    loadOrders();
                } else {
                    alert('Error: ' + (data.message || 'Failed to create order'));
                }
            } catch (error) {
                alert('Error creating order: ' + error.message);
            }
        });
        
        async function loadOrders() {
            // Load orders from database
            document.getElementById('ordersTableBody').innerHTML = '<tr><td colspan="6" class="text-center">Payment orders will be displayed here</td></tr>';
        }
        
        function getToken() {
            return sessionStorage.getItem('jwt') || localStorage.getItem('admin_token') || '';
        }
        
        loadOrders();
    </script>
</body>
</html>

