<?php
require_once '../config.php';
require_once '../database.php';
require_once '../functions.php';

// Check if user is admin
if (!isAdmin()) {
    redirect('../login.php');
}

$db = new Database();

// Get user ID from URL
$userId = intval($_GET['id'] ?? 0);

if ($userId <= 0) {
    redirect('users.php');
}

// Get user details
$user = $db->query("
    SELECT u.*, 
           COUNT(DISTINCT o.id) as total_orders,
           COALESCE(SUM(o.final_amount), 0) as total_spent,
           MAX(o.created_at) as last_order_date
    FROM users u
    LEFT JOIN orders o ON u.id = o.user_id AND o.payment_status = 'completed'
    WHERE u.id = ?
    GROUP BY u.id
", [$userId])->fetch();

if (!$user) {
    redirect('users.php');
}

// Get user's recent orders
$recentOrders = $db->query("
    SELECT o.*, COUNT(oi.id) as item_count
    FROM orders o
    LEFT JOIN order_items oi ON o.id = oi.order_id
    WHERE o.user_id = ?
    GROUP BY o.id
    ORDER BY o.created_at DESC
    LIMIT 10
", [$userId])->fetchAll();

// Get user's download activity
$downloadActivity = $db->query("
    SELECT d.*, p.name as product_name, p.price as product_price
    FROM downloads d
    JOIN products p ON d.product_id = p.id
    WHERE d.user_id = ?
    ORDER BY d.created_at DESC
    LIMIT 10
", [$userId])->fetchAll();

// Get user's order statistics
$orderStats = [
    'completed' => $db->query("SELECT COUNT(*) as count FROM orders WHERE user_id = ? AND payment_status = 'completed'", [$userId])->fetch()['count'],
    'pending' => $db->query("SELECT COUNT(*) as count FROM orders WHERE user_id = ? AND payment_status = 'pending'", [$userId])->fetch()['count'],
    'failed' => $db->query("SELECT COUNT(*) as count FROM orders WHERE user_id = ? AND payment_status = 'failed'", [$userId])->fetch()['count'],
    'total_downloads' => $db->query("SELECT COUNT(*) as count FROM downloads WHERE user_id = ?", [$userId])->fetch()['count']
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Profile - <?php echo sanitize($user['name']); ?> - Admin</title>
    
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
            overflow-x: hidden;
        }
        
        .sidebar {
            background: linear-gradient(135deg, var(--primary-color) 0%, #667eea 100%);
            height: 100vh;
            color: white;
            position: fixed;
            left: 0;
            top: 0;
            width: 250px;
            z-index: 1000;
            overflow-y: auto;
            overflow-x: hidden;
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
        
        .main-content {
            margin-left: 250px;
            padding: 20px;
            max-width: calc(100vw - 250px);
            overflow-x: hidden;
        }
        
        .profile-header {
            background: linear-gradient(135deg, var(--primary-color) 0%, #667eea 100%);
            color: white;
            padding: 40px;
            border-radius: 15px;
            margin-bottom: 30px;
        }
        
        .profile-avatar {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            background: rgba(255,255,255,0.2);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2.5rem;
            font-weight: bold;
            margin: 0 auto 20px;
        }
        
        .stat-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            text-align: center;
            transition: all 0.3s ease;
            border-left: 4px solid transparent;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 20px rgba(0,0,0,0.15);
        }
        
        .stat-card.orders { border-left-color: var(--primary-color); }
        .stat-card.spent { border-left-color: var(--success-color); }
        .stat-card.downloads { border-left-color: var(--info-color); }
        .stat-card.last-order { border-left-color: var(--warning-color); }
        
        .stat-icon {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            margin: 0 auto 15px;
        }
        
        .stat-card.orders .stat-icon { background: rgba(59, 113, 202, 0.1); color: var(--primary-color); }
        .stat-card.spent .stat-icon { background: rgba(20, 164, 77, 0.1); color: var(--success-color); }
        .stat-card.downloads .stat-icon { background: rgba(84, 180, 211, 0.1); color: var(--info-color); }
        .stat-card.last-order .stat-icon { background: rgba(228, 161, 27, 0.1); color: var(--warning-color); }
        
        .info-card {
            background: white;
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            margin-bottom: 20px;
        }
        
        .table-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            overflow-x: auto;
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
                max-width: 100vw;
            }
            
            .mobile-menu-toggle {
                display: block;
            }
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
                    <a class="nav-link" href="orders.php">
                        <i class="fas fa-shopping-cart"></i> Orders
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
            <h2 class="fw-bold">User Profile</h2>
            <a href="users.php" class="btn btn-outline-primary">
                <i class="fas fa-arrow-left me-2"></i> Back to Users
            </a>
        </div>

        <!-- Profile Header -->
        <div class="profile-header">
            <div class="row align-items-center">
                <div class="col-md-3 text-center">
                    <div class="profile-avatar">
                        <?php echo strtoupper(substr($user['name'], 0, 2)); ?>
                    </div>
                </div>
                <div class="col-md-9">
                    <h3 class="fw-bold mb-2"><?php echo sanitize($user['name']); ?></h3>
                    <p class="mb-2"><i class="fas fa-envelope me-2"></i> <?php echo sanitize($user['email']); ?></p>
                    <p class="mb-2">
                        <i class="fas fa-user-tag me-2"></i> 
                        <span class="badge bg-<?php echo $user['role'] === 'admin' ? 'danger' : 'primary'; ?>">
                            <?php echo ucfirst($user['role']); ?>
                        </span>
                        <span class="badge bg-<?php echo $user['status'] === 'active' ? 'success' : 'warning'; ?> ms-2">
                            <?php echo ucfirst($user['status']); ?>
                        </span>
                    </p>
                    <p class="mb-0"><i class="fas fa-calendar me-2"></i> Member since <?php echo date('F j, Y', strtotime($user['created_at'])); ?></p>
                </div>
            </div>
        </div>

        <!-- Statistics Cards -->
        <div class="row mb-4">
            <div class="col-md-3 mb-3">
                <div class="stat-card orders">
                    <div class="stat-icon">
                        <i class="fas fa-shopping-cart"></i>
                    </div>
                    <div class="stat-value"><?php echo number_format($orderStats['completed']); ?></div>
                    <div class="stat-label">Completed Orders</div>
                </div>
            </div>
            
            <div class="col-md-3 mb-3">
                <div class="stat-card spent">
                    <div class="stat-icon">
                        <i class="fas fa-dollar-sign"></i>
                    </div>
                    <div class="stat-value"><?php echo formatPrice($user['total_spent']); ?></div>
                    <div class="stat-label">Total Spent</div>
                </div>
            </div>
            
            <div class="col-md-3 mb-3">
                <div class="stat-card downloads">
                    <div class="stat-icon">
                        <i class="fas fa-download"></i>
                    </div>
                    <div class="stat-value"><?php echo number_format($orderStats['total_downloads']); ?></div>
                    <div class="stat-label">Downloads</div>
                </div>
            </div>
            
            <div class="col-md-3 mb-3">
                <div class="stat-card last-order">
                    <div class="stat-icon">
                        <i class="fas fa-clock"></i>
                    </div>
                    <div class="stat-value small">
                        <?php echo $user['last_order_date'] ? date('M j, Y', strtotime($user['last_order_date'])) : 'Never'; ?>
                    </div>
                    <div class="stat-label">Last Order</div>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- User Information -->
            <div class="col-lg-4">
                <div class="info-card">
                    <h5 class="fw-bold mb-4">Account Information</h5>
                    
                    <div class="mb-3">
                        <strong>User ID:</strong><br>
                        <span class="text-muted">#<?php echo $user['id']; ?></span>
                    </div>
                    
                    <div class="mb-3">
                        <strong>Full Name:</strong><br>
                        <span class="text-muted"><?php echo sanitize($user['name']); ?></span>
                    </div>
                    
                    <div class="mb-3">
                        <strong>Email Address:</strong><br>
                        <span class="text-muted"><?php echo sanitize($user['email']); ?></span>
                    </div>
                    
                    <div class="mb-3">
                        <strong>Account Type:</strong><br>
                        <span class="badge bg-<?php echo $user['role'] === 'admin' ? 'danger' : 'primary'; ?>">
                            <?php echo ucfirst($user['role']); ?>
                        </span>
                    </div>
                    
                    <div class="mb-3">
                        <strong>Account Status:</strong><br>
                        <span class="badge bg-<?php echo $user['status'] === 'active' ? 'success' : 'warning'; ?>">
                            <?php echo ucfirst($user['status']); ?>
                        </span>
                    </div>
                    
                    <div class="mb-3">
                        <strong>Email Verified:</strong><br>
                        <span class="badge bg-<?php echo $user['email_verified'] ? 'success' : 'warning'; ?>">
                            <?php echo $user['email_verified'] ? 'Yes' : 'No'; ?>
                        </span>
                    </div>
                    
                    <div class="mb-3">
                        <strong>Member Since:</strong><br>
                        <span class="text-muted"><?php echo date('F j, Y \a\t h:i A', strtotime($user['created_at'])); ?></span>
                    </div>
                    
                    <div class="mb-3">
                        <strong>Last Updated:</strong><br>
                        <span class="text-muted"><?php echo date('F j, Y \a\t h:i A', strtotime($user['updated_at'])); ?></span>
                    </div>
                </div>
            </div>

            <!-- Recent Orders -->
            <div class="col-lg-8">
                <div class="table-card">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h5 class="fw-bold mb-0">Recent Orders</h5>
                        <span class="text-muted">Last 10 orders</span>
                    </div>
                    
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Order #</th>
                                    <th>Amount</th>
                                    <th>Status</th>
                                    <th>Items</th>
                                    <th>Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($recentOrders)): ?>
                                    <tr>
                                        <td colspan="5" class="text-center text-muted">No orders yet</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($recentOrders as $order): ?>
                                        <tr>
                                            <td>
                                                <a href="order-details.php?id=<?php echo $order['id']; ?>" class="text-decoration-none text-primary">
                                                    #<?php echo $order['order_number']; ?>
                                                </a>
                                            </td>
                                            <td class="fw-bold"><?php echo formatPrice($order['final_amount']); ?></td>
                                            <td>
                                                <span class="badge-status badge-<?php echo $order['payment_status']; ?>">
                                                    <?php echo ucfirst($order['payment_status']); ?>
                                                </span>
                                            </td>
                                            <td><?php echo $order['item_count']; ?></td>
                                            <td>
                                                <small><?php echo date('M j, Y', strtotime($order['created_at'])); ?></small>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Download Activity -->
                <div class="table-card mt-4">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h5 class="fw-bold mb-0">Download Activity</h5>
                        <span class="text-muted">Last 10 downloads</span>
                    </div>
                    
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Product</th>
                                    <th>Price</th>
                                    <th>Downloads</th>
                                    <th>Downloaded</th>
                                    <th>Expires</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($downloadActivity)): ?>
                                    <tr>
                                        <td colspan="5" class="text-center text-muted">No downloads yet</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($downloadActivity as $download): ?>
                                        <tr>
                                            <td>
                                                <a href="../product.php?id=<?php echo $download['product_id']; ?>" class="text-decoration-none text-primary" target="_blank">
                                                    <?php echo sanitize($download['product_name']); ?>
                                                </a>
                                            </td>
                                            <td><?php echo formatPrice($download['product_price']); ?></td>
                                            <td><?php echo $download['download_count']; ?>/<?php echo $download['max_downloads']; ?></td>
                                            <td>
                                                <small><?php echo date('M j, Y', strtotime($download['created_at'])); ?></small>
                                            </td>
                                            <td>
                                                <small><?php echo date('M j, Y', strtotime($download['expires_at'])); ?></small>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <!-- MDB5 JS -->
    <script src="../MDB5-STANDARD-UI-KIT-Free-9.2.0/js/mdb.umd.min.js"></script>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Mobile menu toggle
            const mobileMenuToggle = document.getElementById('mobileMenuToggle');
            const sidebar = document.getElementById('sidebar');
            
            if (mobileMenuToggle && sidebar) {
                mobileMenuToggle.addEventListener('click', function(e) {
                    e.preventDefault();
                    sidebar.classList.toggle('show');
                });
            }
            
            // Prevent excessive scrolling
            document.body.style.overflowX = 'hidden';
        });
    </script>
</body>
</html>
