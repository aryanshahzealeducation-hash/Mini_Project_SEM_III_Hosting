<?php
require_once 'config.php';
require_once 'database.php';
require_once 'functions.php';

// Check if user is logged in
if (!isLoggedIn()) {
    redirect('login.php');
}

$db = new Database();
$userId = $_SESSION['user_id'];

// Get filters
$status = sanitize($_GET['status'] ?? '');
$search = sanitize($_GET['search'] ?? '');

// Build where clause
$where = ["o.user_id = ?"];
$params = [$userId];

if ($status) {
    $where[] = "o.payment_status = ?";
    $params[] = $status;
}

if ($search) {
    $where[] = "(o.order_number LIKE ? OR o.notes LIKE ?)";
    $searchParam = "%$search%";
    $params[] = $searchParam;
    $params[] = $searchParam;
}

$whereClause = "WHERE " . implode(" AND ", $where);

// Get orders with pagination
$page = max(1, intval($_GET['page'] ?? 1));
$pagination = paginate(100, 10, $page); // Assuming max 100 orders for now

$orders = $db->query("
    SELECT o.*, COUNT(oi.id) as item_count
    FROM orders o
    LEFT JOIN order_items oi ON o.id = oi.order_id
    $whereClause
    GROUP BY o.id
    ORDER BY o.created_at DESC
    LIMIT {$pagination['offset']}, {$pagination['items_per_page']}
", $params)->fetchAll();

// Get order statistics
$stats = [
    'total' => $db->query("SELECT COUNT(*) as count FROM orders WHERE user_id = ?", [$userId])->fetch()['count'],
    'completed' => $db->query("SELECT COUNT(*) as count FROM orders WHERE user_id = ? AND payment_status = 'completed'", [$userId])->fetch()['count'],
    'pending' => $db->query("SELECT COUNT(*) as count FROM orders WHERE user_id = ? AND payment_status = 'pending'", [$userId])->fetch()['count'],
    'total_spent' => $db->query("SELECT SUM(final_amount) as total FROM orders WHERE user_id = ? AND payment_status = 'completed'", [$userId])->fetch()['total'] ?? 0
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Café Orders - <?php echo SITE_NAME; ?></title>
    
    <!-- MDB5 CSS -->
    <link rel="stylesheet" href="MDB5-STANDARD-UI-KIT-Free-9.2.0/css/mdb.min.css">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <style>
        :root {
            --primary-color: #3b71ca;
            --secondary-color: #9fa6b2;
            --success-color: #14a44d;
            --warning-color: #e4a11b;
            --info-color: #54b4d3;
        }
        
        body {
            background: #f8f9fa;
        }
        
        .page-header {
            background: linear-gradient(135deg, var(--primary-color) 0%, #667eea 100%);
            color: white;
            padding: 80px 0 40px;
        }
        
        .stat-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            transition: all 0.3s ease;
            border-left: 4px solid transparent;
            text-align: center;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 20px rgba(0,0,0,0.15);
        }
        
        .stat-card.total { border-left-color: var(--primary-color); }
        .stat-card.completed { border-left-color: var(--success-color); }
        .stat-card.pending { border-left-color: var(--warning-color); }
        .stat-card.spent { border-left-color: var(--info-color); }
        
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
        
        .stat-card.total .stat-icon { background: rgba(59, 113, 202, 0.1); color: var(--primary-color); }
        .stat-card.completed .stat-icon { background: rgba(20, 164, 77, 0.1); color: var(--success-color); }
        .stat-card.pending .stat-icon { background: rgba(228, 161, 27, 0.1); color: var(--warning-color); }
        .stat-card.spent .stat-icon { background: rgba(84, 180, 211, 0.1); color: var(--info-color); }
        
        .stat-value {
            font-size: 2rem;
            font-weight: bold;
            margin-bottom: 5px;
        }
        
        .stat-label {
            color: #6c757d;
            font-size: 0.9rem;
        }
        
        .order-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            margin-bottom: 20px;
            transition: all 0.3s ease;
        }
        
        .order-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(0,0,0,0.15);
        }
        
        .badge-status {
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 500;
        }
        
        .badge-completed { background: rgba(20, 164, 77, 0.1); color: var(--success-color); }
        .badge-pending { background: rgba(228, 161, 27, 0.1); color: var(--warning-color); }
        .badge-processing { background: rgba(84, 180, 211, 0.1); color: var(--info-color); }
        .badge-failed { background: rgba(220, 76, 100, 0.1); color: #dc4c64; }
        
        .filter-card {
            background: white;
            border-radius: 15px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            margin-bottom: 20px;
        }
        
        @media (max-width: 768px) {
            .page-header {
                padding: 60px 0 30px;
            }
            
            .stat-card {
                margin-bottom: 15px;
            }
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-light bg-light desktop-nav sticky-top">
        <div class="container">
            <a class="navbar-brand fw-bold" href="index.php">
                <i class="fas fa-coffee text-primary"></i> <?php echo SITE_NAME; ?>
            </a>
            
            <button class="navbar-toggler" type="button" data-mdb-toggle="collapse" data-mdb-target="#navbarNav">
                <i class="fas fa-bars"></i>
            </button>
            
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="index.php">Home</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="products.php">Products</a>
                    </li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" data-mdb-toggle="dropdown">
                            Categories
                        </a>
                        <ul class="dropdown-menu">
                            <?php 
                            $categories = $db->query("SELECT * FROM categories WHERE status = 'active' ORDER BY name ASC")->fetchAll();
                            foreach ($categories as $cat): ?>
                                <li><a class="dropdown-item" href="products.php?category=<?php echo $cat['slug']; ?>">
                                    <?php echo sanitize($cat['name']); ?>
                                </a></li>
                            <?php endforeach; ?>
                        </ul>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="contact.php">Contact</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="faq.php">FAQ</a>
                    </li>
                </ul>
                
                <ul class="navbar-nav">
                    <?php if (isLoggedIn()): ?>
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle" href="#" data-mdb-toggle="dropdown">
                                <i class="fas fa-user"></i> <?php echo sanitize($_SESSION['user_name']); ?>
                            </a>
                            <ul class="dropdown-menu">
                                <li><a class="dropdown-item" href="profile.php">Profile</a></li>
                                <li><a class="dropdown-item" href="orders.php">Café Orders</a></li>
                                <?php if (isAdmin()): ?>
                                    <li><hr class="dropdown-divider"></li>
                                    <li><a class="dropdown-item" href="admin/">Admin Panel</a></li>
                                <?php endif; ?>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item" href="logout.php">Logout</a></li>
                            </ul>
                        </li>
                    <?php else: ?>
                        <li class="nav-item">
                            <a class="nav-link" href="login.php">Login</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="signup.php">Sign Up</a>
                        </li>
                    <?php endif; ?>
                    <li class="nav-item">
                        <a class="nav-link position-relative" href="cart.php">
                            <i class="fas fa-shopping-cart"></i>
                            <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger">
                                <?php echo isset($_SESSION['cart_count']) ? $_SESSION['cart_count'] : 0; ?>
                            </span>
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Page Header -->
    <section class="page-header">
        <div class="container">
            <h1 class="fw-bold mb-3">My Café Orders</h1>
            <p class="lead mb-0">Track and manage your café orders</p>
        </div>
    </section>

    <!-- Orders Content -->
    <section class="py-5">
        <div class="container">
            <!-- Statistics Cards -->
            <div class="row mb-4">
                <div class="col-md-3 mb-3">
                    <div class="stat-card total">
                        <div class="stat-icon">
                            <i class="fas fa-shopping-cart"></i>
                        </div>
                        <div class="stat-value"><?php echo number_format($stats['total']); ?></div>
                        <div class="stat-label">Total Orders</div>
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
                    <div class="stat-card spent">
                        <div class="stat-icon">
                            <i class="fas fa-dollar-sign"></i>
                        </div>
                        <div class="stat-value"><?php echo formatPrice($stats['total_spent']); ?></div>
                        <div class="stat-label">Total Spent</div>
                    </div>
                </div>
            </div>

            <!-- Filters -->
            <div class="filter-card">
                <form method="GET" class="row g-3">
                    <div class="col-md-4">
                        <input type="text" class="form-control" name="search" placeholder="Search orders..." value="<?php echo $search; ?>">
                    </div>
                    <div class="col-md-3">
                        <select class="form-select" name="status">
                            <option value="">All Status</option>
                            <option value="pending" <?php echo $status === 'pending' ? 'selected' : ''; ?>>Pending</option>
                            <option value="completed" <?php echo $status === 'completed' ? 'selected' : ''; ?>>Completed</option>
                            <option value="processing" <?php echo $status === 'processing' ? 'selected' : ''; ?>>Processing</option>
                            <option value="failed" <?php echo $status === 'failed' ? 'selected' : ''; ?>>Failed</option>
                        </select>
                    </div>
                    <div class="col-md-5">
                        <button type="submit" class="btn btn-primary">Filter</button>
                        <a href="orders.php" class="btn btn-outline-secondary">Clear</a>
                    </div>
                </form>
            </div>

            <!-- Orders List -->
            <?php if (empty($orders)): ?>
                <div class="order-card text-center">
                    <i class="fas fa-shopping-cart fa-3x text-muted mb-3"></i>
                    <h5>No orders found</h5>
                    <p class="text-muted">You haven't placed any orders yet.</p>
                    <a href="products.php" class="btn btn-primary">
                        <i class="fas fa-shopping-bag me-2"></i> Start Shopping
                    </a>
                </div>
            <?php else: ?>
                <?php foreach ($orders as $order): ?>
                    <div class="order-card">
                        <div class="row align-items-center">
                            <div class="col-md-4">
                                <h5 class="mb-1">#<?php echo $order['order_number']; ?></h5>
                                <p class="text-muted mb-0">
                                    <i class="fas fa-calendar me-1"></i>
                                    <?php echo date('F j, Y \a\t h:i A', strtotime($order['created_at'])); ?>
                                </p>
                            </div>
                            <div class="col-md-2">
                                <span class="badge-status badge-<?php echo $order['payment_status']; ?>">
                                    <?php echo ucfirst($order['payment_status']); ?>
                                </span>
                            </div>
                            <div class="col-md-2">
                                <span class="badge bg-secondary"><?php echo $order['item_count']; ?> items</span>
                            </div>
                            <div class="col-md-2">
                                <h6 class="mb-0 fw-bold"><?php echo formatPrice($order['final_amount']); ?></h6>
                            </div>
                            <div class="col-md-2 text-end">
                                <a href="order-details.php?id=<?php echo $order['id']; ?>" class="btn btn-primary btn-sm">
                                    <i class="fas fa-eye me-1"></i> View Details
                                </a>
                            </div>
                        </div>
                        
                        <?php if ($order['notes']): ?>
                            <div class="mt-3 pt-3 border-top">
                                <small class="text-muted">
                                    <strong>Notes:</strong> <?php echo sanitize($order['notes']); ?>
                                </small>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>

                <!-- Pagination -->
                <?php if ($pagination['total_pages'] > 1): ?>
                    <nav aria-label="Orders pagination" class="mt-4">
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
            <?php endif; ?>
        </div>
    </section>

    <!-- Mobile Bottom Navigation -->
    <nav class="mobile-bottom-nav">
        <div class="container-fluid">
            <div class="d-flex justify-content-around py-2">
                <a href="index.php" class="text-decoration-none text-muted p-2">
                    <i class="fas fa-home fa-lg"></i>
                    <div class="small">Home</div>
                </a>
                <a href="products.php" class="text-decoration-none text-muted p-2">
                    <i class="fas fa-th-large fa-lg"></i>
                    <div class="small">Products</div>
                </a>
                <a href="cart.php" class="text-decoration-none text-muted p-2 position-relative">
                    <i class="fas fa-shopping-cart fa-lg"></i>
                    <div class="small">Cart</div>
                    <span class="position-absolute top-0 start-50 translate-middle badge rounded-pill bg-danger" style="font-size: 0.6rem;">
                        <?php echo isset($_SESSION['cart_count']) ? $_SESSION['cart_count'] : 0; ?>
                    </span>
                </a>
                <a href="profile.php" class="text-decoration-none text-primary p-2">
                    <i class="fas fa-user fa-lg"></i>
                    <div class="small">Profile</div>
                </a>
            </div>
        </div>
    </nav>

    <!-- MDB5 JS -->
    <script src="MDB5-STANDARD-UI-KIT-Free-9.2.0/js/mdb.umd.min.js"></script>
    
    <script>
        // Initialize MDB components
        document.addEventListener('DOMContentLoaded', function() {
            // Initialize all MDB components
            const mdb = window.mdb;
            if (mdb) {
                // Auto-initialize all MDB components
                mdb.AutoInit();
            }
            
            // Manual dropdown initialization as backup
            const dropdownElements = document.querySelectorAll('[data-mdb-toggle="dropdown"]');
            dropdownElements.forEach(function(dropdown) {
                new mdb.Dropdown(dropdown);
            });
        });
    </script>
</body>
</html>
