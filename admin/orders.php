<?php
require_once '../config.php';
require_once '../database.php';
require_once '../functions.php';

// Check if user is admin
if (!isAdmin()) {
    redirect('../login.php');
}

$db = new Database();

// Handle order status updates
if (isset($_POST['action']) && $_POST['action'] === 'update_status') {
    $orderId = intval($_POST['order_id']);
    $paymentStatus = sanitize($_POST['payment_status']);
    $orderStatus = sanitize($_POST['order_status']);
    
    $db->query("
        UPDATE orders 
        SET payment_status = ?, order_status = ? 
        WHERE id = ?
    ", [$paymentStatus, $orderStatus, $orderId]);
    
    $message = 'Order status updated successfully!';
    $messageType = 'success';
}

// Get filters
$status = sanitize($_GET['status'] ?? '');
$dateFrom = sanitize($_GET['date_from'] ?? '');
$dateTo = sanitize($_GET['date_to'] ?? '');
$search = sanitize($_GET['search'] ?? '');

// Build where clause
$where = ["1=1"];
$params = [];

if ($status) {
    $where[] = "o.payment_status = ?";
    $params[] = $status;
}

if ($dateFrom) {
    $where[] = "DATE(o.created_at) >= ?";
    $params[] = $dateFrom;
}

if ($dateTo) {
    $where[] = "DATE(o.created_at) <= ?";
    $params[] = $dateTo;
}

if ($search) {
    $where[] = "(o.order_number LIKE ? OR u.name LIKE ? OR u.email LIKE ?)";
    $searchParam = "%$search%";
    $params[] = $searchParam;
    $params[] = $searchParam;
    $params[] = $searchParam;
}

$whereClause = "WHERE " . implode(" AND ", $where);

// Get orders with pagination
$page = max(1, intval($_GET['page'] ?? 1));
$pagination = paginate(100, 20, $page); // Assuming max 100 orders for now

$orders = $db->query("
    SELECT o.*, u.name as customer_name, u.email as customer_email, u.id as user_id,
           COUNT(oi.id) as item_count
    FROM orders o
    LEFT JOIN users u ON o.user_id = u.id
    LEFT JOIN order_items oi ON o.id = oi.order_id
    $whereClause
    GROUP BY o.id
    ORDER BY o.created_at DESC
    LIMIT {$pagination['offset']}, {$pagination['items_per_page']}
", $params)->fetchAll();

// Get order statistics
$stats = [
    'total' => $db->query("SELECT COUNT(*) as count FROM orders")->fetch()['count'],
    'completed' => $db->query("SELECT COUNT(*) as count FROM orders WHERE payment_status = 'completed'")->fetch()['count'],
    'pending' => $db->query("SELECT COUNT(*) as count FROM orders WHERE payment_status = 'pending'")->fetch()['count'],
    'revenue' => $db->query("SELECT SUM(final_amount) as total FROM orders WHERE payment_status = 'completed'")->fetch()['total'] ?? 0
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Café Orders Management - <?php echo SITE_NAME; ?></title>
    
    <!-- MDB5 CSS -->
    <link rel="stylesheet" href="../MDB5-STANDARD-UI-KIT-Free-9.2.0/css/mdb.min.css">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <style>
        :root {
            --primary-color: #3b71ca;
            --secondary-color: #9fa6b2;
            --success-color: #14a44d;
            --danger-color: #dc4c64;
            --warning-color: #e4a11b;
            --info-color: #54b4d3;
        }
        
        body {
            background: #f8f9fa;
        }
        
        .sidebar {
            background: linear-gradient(135deg, var(--primary-color) 0%, #667eea 100%);
            min-height: 100vh;
            color: white;
            position: fixed;
            left: 0;
            top: 0;
            width: 250px;
            z-index: 1000;
        }
        
        .sidebar .nav-link {
            color: rgba(255,255,255,0.8);
            padding: 12px 20px;
            transition: all 0.3s ease;
        }
        
        .sidebar .nav-link:hover,
        .sidebar .nav-link.active {
            color: white;
            background: rgba(255,255,255,0.1);
            border-radius: 8px;
        }
        
        .sidebar .nav-link i {
            width: 20px;
            margin-right: 10px;
        }
        
        .main-content {
            margin-left: 250px;
            padding: 20px;
        }
        
        .stat-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            transition: all 0.3s ease;
            border-left: 4px solid transparent;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 20px rgba(0,0,0,0.15);
        }
        
        .stat-card.total { border-left-color: var(--primary-color); }
        .stat-card.completed { border-left-color: var(--success-color); }
        .stat-card.pending { border-left-color: var(--warning-color); }
        .stat-card.revenue { border-left-color: var(--info-color); }
        
        .stat-icon {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            margin-bottom: 15px;
        }
        
        .stat-card.total .stat-icon { background: rgba(59, 113, 202, 0.1); color: var(--primary-color); }
        .stat-card.completed .stat-icon { background: rgba(20, 164, 77, 0.1); color: var(--success-color); }
        .stat-card.pending .stat-icon { background: rgba(228, 161, 27, 0.1); color: var(--warning-color); }
        .stat-card.revenue .stat-icon { background: rgba(84, 180, 211, 0.1); color: var(--info-color); }
        
        .stat-value {
            font-size: 2rem;
            font-weight: bold;
            margin-bottom: 5px;
        }
        
        .stat-label {
            color: #6c757d;
            font-size: 0.9rem;
        }
        
        .table-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
        }
        
        .form-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            margin-bottom: 20px;
        }
        
        .mobile-menu-toggle {
            display: none;
            position: fixed;
            top: 20px;
            left: 20px;
            z-index: 1001;
            background: var(--primary-color);
            color: white;
            border: none;
            border-radius: 8px;
            padding: 10px 15px;
        }
        
        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
                transition: transform 0.3s ease;
            }
            
            .sidebar.show {
                transform: translateX(0);
            }
            
            .main-content {
                margin-left: 0;
                padding: 60px 10px 10px;
            }
            
            .mobile-menu-toggle {
                display: block;
            }
        }
        
        .badge-status {
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 500;
        }
        
        .badge-completed { background: rgba(20, 164, 77, 0.1); color: var(--success-color); }
        .badge-pending { background: rgba(228, 161, 27, 0.1); color: var(--warning-color); }
        .badge-failed { background: rgba(220, 76, 100, 0.1); color: var(--danger-color); }
        .badge-processing { background: rgba(84, 180, 211, 0.1); color: var(--info-color); }
        .badge-cancelled { background: rgba(159, 166, 178, 0.1); color: var(--secondary-color); }
        
        .order-actions .btn {
            padding: 4px 8px;
            font-size: 0.8rem;
        }
        
        .status-select {
            min-width: 120px;
        }
        
        .hover-underline {
            text-decoration: none !important;
            transition: all 0.3s ease;
        }
        
        .hover-underline:hover {
            text-decoration: underline !important;
        }
    </style>
</head>
<body>
    <!-- Mobile Menu Toggle -->
    <button class="mobile-menu-toggle" id="mobileMenuToggle">
        <i class="fas fa-bars"></i>
    </button>

    <!-- Sidebar -->
    <nav class="sidebar" id="sidebar">
        <div class="p-4">
            <h4 class="fw-bold mb-4">
                <i class="fas fa-coffee me-2"></i> CafeNIX Admin
            </h4>
            
            <ul class="nav flex-column">
                <li class="nav-item">
                    <a class="nav-link" href="index.php">
                        <i class="fas fa-tachometer-alt"></i> Dashboard
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="products.php">
                        <i class="fas fa-box"></i> Products
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link active" href="orders.php">
                        <i class="fas fa-shopping-cart"></i> Café Orders
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="users.php">
                        <i class="fas fa-users"></i> Users
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="categories.php">
                        <i class="fas fa-tags"></i> Categories
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="coupons.php">
                        <i class="fas fa-ticket-alt"></i> Coupons
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="support.php">
                        <i class="fas fa-headset"></i> Support
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="settings.php">
                        <i class="fas fa-cog"></i> Settings
                    </a>
                </li>
                <li class="nav-item mt-4">
                    <a class="nav-link" href="../index.php" target="_blank">
                        <i class="fas fa-external-link-alt"></i> View Site
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="../logout.php">
                        <i class="fas fa-sign-out-alt"></i> Logout
                    </a>
                </li>
            </ul>
        </div>
    </nav>

    <!-- Main Content -->
    <main class="main-content">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2 class="fw-bold">Café Orders Management</h2>
        </div>

        <!-- Alert Message -->
        <?php if (isset($message)): ?>
            <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show" role="alert">
                <?php echo $message; ?>
                <button type="button" class="btn-close" data-mdb-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Statistics Cards -->
        <div class="row mb-4">
            <div class="col-md-3 mb-3">
                <div class="stat-card total">
                    <div class="stat-icon">
                        <i class="fas fa-shopping-cart"></i>
                    </div>
                    <div class="stat-value"><?php echo number_format($stats['total']); ?></div>
                    <div class="stat-label">Total Café Orders</div>
                </div>
            </div>
            
            <div class="col-md-3 mb-3">
                <div class="stat-card completed">
                    <div class="stat-icon">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <div class="stat-value"><?php echo number_format($stats['completed']); ?></div>
                    <div class="stat-label">Completed</div>
                </div>
            </div>
            
            <div class="col-md-3 mb-3">
                <div class="stat-card pending">
                    <div class="stat-icon">
                        <i class="fas fa-clock"></i>
                    </div>
                    <div class="stat-value"><?php echo number_format($stats['pending']); ?></div>
                    <div class="stat-label">Pending</div>
                </div>
            </div>
            
            <div class="col-md-3 mb-3">
                <div class="stat-card revenue">
                    <div class="stat-icon">
                        <i class="fas fa-dollar-sign"></i>
                    </div>
                    <div class="stat-value"><?php echo formatPrice($stats['revenue']); ?></div>
                    <div class="stat-label">Total Revenue</div>
                </div>
            </div>
        </div>

        <!-- Filters -->
        <div class="form-card">
            <form method="GET" class="row g-3">
                <div class="col-md-3">
                    <input type="text" class="form-control" name="search" placeholder="Search café orders..." value="<?php echo $search; ?>">
                </div>
                <div class="col-md-2">
                    <select class="form-select" name="status">
                        <option value="">All Status</option>
                        <option value="pending" <?php echo $status === 'pending' ? 'selected' : ''; ?>>Pending</option>
                        <option value="completed" <?php echo $status === 'completed' ? 'selected' : ''; ?>>Completed</option>
                        <option value="failed" <?php echo $status === 'failed' ? 'selected' : ''; ?>>Failed</option>
                        <option value="processing" <?php echo $status === 'processing' ? 'selected' : ''; ?>>Processing</option>
                        <option value="cancelled" <?php echo $status === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <input type="date" class="form-control" name="date_from" value="<?php echo $dateFrom; ?>">
                </div>
                <div class="col-md-2">
                    <input type="date" class="form-control" name="date_to" value="<?php echo $dateTo; ?>">
                </div>
                <div class="col-md-3">
                    <button type="submit" class="btn btn-outline-primary">Filter</button>
                    <a href="orders.php" class="btn btn-outline-secondary">Clear</a>
                </div>
            </form>
        </div>

        <!-- Orders Table -->
        <div class="table-card">
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Order #</th>
                            <th>Customer</th>
                            <th>Items</th>
                            <th>Total</th>
                            <th>Payment Status</th>
                            <th>Order Status</th>
                            <th>Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($orders)): ?>
                            <tr>
                                <td colspan="8" class="text-center text-muted">No café orders found</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($orders as $order): ?>
                                <tr>
                                    <td>
                                        <a href="order-details.php?id=<?php echo $order['id']; ?>" class="text-decoration-none fw-bold">
                                            #<?php echo $order['order_number']; ?>
                                        </a>
                                    </td>
                                    <td>
                                        <div>
                                            <div class="fw-bold">
                                                <?php if ($order['user_id']): ?>
                                                    <a href="user-profile.php?id=<?php echo $order['user_id']; ?>" class="text-decoration-none text-primary hover-underline">
                                                        <?php echo sanitize($order['customer_name']); ?>
                                                    </a>
                                                <?php else: ?>
                                                    <?php echo sanitize($order['customer_name']); ?>
                                                <?php endif; ?>
                                            </div>
                                            <small class="text-muted"><?php echo sanitize($order['customer_email']); ?></small>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="badge bg-secondary"><?php echo $order['item_count']; ?> items</span>
                                    </td>
                                    <td class="fw-bold"><?php echo formatPrice($order['final_amount']); ?></td>
                                    <td>
                                        <span class="badge-status badge-<?php echo $order['payment_status']; ?>">
                                            <?php echo ucfirst($order['payment_status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge-status badge-<?php echo $order['order_status']; ?>">
                                            <?php echo ucfirst($order['order_status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div>
                                            <div><?php echo date('M j, Y', strtotime($order['created_at'])); ?></div>
                                            <small class="text-muted"><?php echo date('h:i A', strtotime($order['created_at'])); ?></small>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="order-actions">
                                            <button class="btn btn-sm btn-outline-primary" onclick="viewOrder(<?php echo $order['id']; ?>)">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            <button class="btn btn-sm btn-outline-secondary" onclick="updateStatus(<?php echo $order['id']; ?>)">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Pagination -->
            <?php if ($pagination['total_pages'] > 1): ?>
                <nav aria-label="Café Orders pagination" class="mt-4">
                    <ul class="pagination justify-content-center">
                        <?php if ($pagination['has_prev']): ?>
                            <li class="page-item">
                                <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $pagination['current_page'] - 1])); ?>">
                                    <i class="fas fa-chevron-left"></i>
                                </a>
                            </li>
                        <?php endif; ?>
                        
                        <?php for ($i = 1; $i <= $pagination['total_pages']; $i++): ?>
                            <?php if ($i == $pagination['current_page']): ?>
                                <li class="page-item active">
                                    <span class="page-link"><?php echo $i; ?></span>
                                </li>
                            <?php elseif (abs($i - $pagination['current_page']) <= 2 || $i == 1 || $i == $pagination['total_pages']): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>">
                                        <?php echo $i; ?>
                                    </a>
                                </li>
                            <?php elseif (abs($i - $pagination['current_page']) == 3): ?>
                                <li class="page-item disabled">
                                    <span class="page-link">...</span>
                                </li>
                            <?php endif; ?>
                        <?php endfor; ?>
                        
                        <?php if ($pagination['has_next']): ?>
                            <li class="page-item">
                                <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $pagination['current_page'] + 1])); ?>">
                                    <i class="fas fa-chevron-right"></i>
                                </a>
                            </li>
                        <?php endif; ?>
                    </ul>
                </nav>
            <?php endif; ?>
        </div>
    </main>

    <!-- Status Update Modal -->
    <div class="modal fade" id="statusModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Update Café Order Status</h5>
                    <button type="button" class="btn-close" data-mdb-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="update_status">
                        <input type="hidden" id="updateOrderId" name="order_id">
                        
                        <div class="mb-3">
                            <label for="payment_status" class="form-label">Payment Status</label>
                            <select class="form-select" id="payment_status" name="payment_status">
                                <option value="pending">Pending</option>
                                <option value="completed">Completed</option>
                                <option value="failed">Failed</option>
                                <option value="processing">Processing</option>
                                <option value="cancelled">Cancelled</option>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label for="order_status" class="form-label">Order Status</label>
                            <select class="form-select" id="order_status" name="order_status">
                                <option value="pending">Pending</option>
                                <option value="processing">Processing</option>
                                <option value="completed">Completed</option>
                                <option value="cancelled">Cancelled</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-mdb-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Update Status</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- MDB5 JS -->
    <script src="../MDB5-STANDARD-UI-KIT-Free-9.2.0/js/mdb.umd.min.js"></script>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Mobile menu toggle
            const mobileMenuToggle = document.getElementById('mobileMenuToggle');
            const sidebar = document.getElementById('sidebar');
            
            if (mobileMenuToggle && sidebar) {
                mobileMenuToggle.addEventListener('click', function() {
                    sidebar.classList.toggle('show');
                });
            }
            
            // View order
            function viewOrder(id) {
                window.location.href = 'order-details.php?id=' + id;
            }
            
            // Update status
            function updateStatus(id) {
                document.getElementById('updateOrderId').value = id;
                
                // Get current order data (you might want to fetch this via AJAX)
                const modal = new mdb.Modal(document.getElementById('statusModal'));
                modal.show();
            }
            
            // Make functions global
            window.viewOrder = viewOrder;
            window.updateStatus = updateStatus;
        });
    </script>
</body>
</html>
