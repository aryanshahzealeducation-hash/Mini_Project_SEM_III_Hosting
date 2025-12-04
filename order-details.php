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

// Get order ID from URL
$orderId = intval($_GET['id'] ?? 0);

if ($orderId <= 0) {
    redirect('orders.php');
}

// Get order details
$order = $db->query("
    SELECT o.*, u.name, u.email 
    FROM orders o 
    JOIN users u ON o.user_id = u.id 
    WHERE o.id = ? AND o.user_id = ?
", [$orderId, $userId])->fetch();

if (!$order) {
    redirect('orders.php');
}

// Get order items
$orderItems = $db->query("
    SELECT oi.*, p.screenshot 
    FROM order_items oi 
    LEFT JOIN products p ON oi.product_id = p.id
    WHERE oi.order_id = ?
", [$orderId])->fetchAll();

// Get download links
$downloadLinks = $db->query("
    SELECT d.*, p.name as product_name 
    FROM downloads d 
    JOIN products p ON d.product_id = p.id 
    WHERE d.order_id = ? AND d.user_id = ? AND d.expires_at > NOW()
", [$orderId, $userId])->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Details - <?php echo SITE_NAME; ?></title>
    
    <!-- MDB5 CSS -->
    <link rel="stylesheet" href="MDB5-STANDARD-UI-KIT-Free-9.2.0/css/mdb.min.css">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <style>
        :root {
            --primary-color: #3b71ca;
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
        
        .order-card {
            background: white;
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            margin-bottom: 20px;
        }
        
        .order-item {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 15px;
        }
        
        .product-image {
            width: 80px;
            height: 80px;
            object-fit: cover;
            border-radius: 8px;
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
        
        .download-card {
            background: white;
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 15px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
            transition: all 0.3s ease;
        }
        
        .download-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        
        .btn-download {
            background: var(--primary-color);
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 8px;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            transition: all 0.3s ease;
        }
        
        .btn-download:hover {
            background: #2f5aa2;
            color: white;
            transform: translateY(-2px);
        }
        
        .summary-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 0;
            border-bottom: 1px solid #f0f0f0;
        }
        
        .summary-row:last-child {
            border-bottom: none;
            font-weight: bold;
            font-size: 1.1rem;
            color: var(--primary-color);
            padding-top: 15px;
        }
        
        @media (max-width: 768px) {
            .page-header {
                padding: 60px 0 30px;
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
                                <li><a class="dropdown-item" href="orders.php">My Orders</a></li>
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
            <h1 class="fw-bold mb-3">Order Details</h1>
            <p class="lead mb-0">View your order information and downloads</p>
        </div>
    </section>

    <!-- Order Details -->
    <section class="py-5">
        <div class="container">
            <!-- Order Information -->
            <div class="order-card">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h5 class="fw-bold mb-0">Order #<?php echo $order['order_number']; ?></h5>
                    <a href="orders.php" class="btn btn-outline-primary">
                        <i class="fas fa-arrow-left me-2"></i> Back to Orders
                    </a>
                </div>
                
                <div class="row">
                    <div class="col-md-6">
                        <h6 class="fw-bold mb-3">Order Information</h6>
                        <p class="mb-2"><strong>Order Date:</strong> <?php echo date('F j, Y \a\t h:i A', strtotime($order['created_at'])); ?></p>
                        <p class="mb-2"><strong>Payment Status:</strong> 
                            <span class="badge-status badge-<?php echo $order['payment_status']; ?>">
                                <?php echo ucfirst($order['payment_status']); ?>
                            </span>
                        </p>
                        <p class="mb-2"><strong>Order Status:</strong> 
                            <span class="badge-status badge-<?php echo $order['order_status']; ?>">
                                <?php echo ucfirst($order['order_status']); ?>
                            </span>
                        </p>
                        <?php if ($order['paid_at']): ?>
                            <p class="mb-2"><strong>Paid On:</strong> <?php echo date('F j, Y \a\t h:i A', strtotime($order['paid_at'])); ?></p>
                        <?php endif; ?>
                    </div>
                    
                    <div class="col-md-6">
                        <h6 class="fw-bold mb-3">Customer Information</h6>
                        <p class="mb-2"><strong>Name:</strong> <?php echo sanitize($order['name']); ?></p>
                        <p class="mb-2"><strong>Email:</strong> <?php echo sanitize($order['email']); ?></p>
                        <p class="mb-2"><strong>Payment Method:</strong> <?php echo ucfirst($order['payment_method']); ?></p>
                    </div>
                </div>
                
                <?php if ($order['notes']): ?>
                    <div class="mt-4 pt-4 border-top">
                        <h6 class="fw-bold mb-2">Order Notes</h6>
                        <p class="text-muted"><?php echo sanitize($order['notes']); ?></p>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Order Items -->
            <div class="order-card">
                <h5 class="fw-bold mb-4">Order Items</h5>
                
                <?php foreach ($orderItems as $item): ?>
                    <div class="order-item">
                        <div class="row align-items-center">
                            <div class="col-md-2">
                                <?php if ($item['screenshot']): ?>
                                    <img src="<?php echo $item['screenshot']; ?>" class="product-image" alt="<?php echo sanitize($item['product_name']); ?>">
                                <?php else: ?>
                                    <div class="product-image d-flex align-items-center justify-content-center bg-light">
                                        <i class="fas fa-image fa-2x text-muted"></i>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div class="col-md-6">
                                <h6 class="mb-1"><?php echo sanitize($item['product_name']); ?></h6>
                                <p class="text-muted mb-0">Unit Price: <?php echo formatPrice($item['product_price']); ?></p>
                            </div>
                            <div class="col-md-2">
                                <p class="mb-0"><strong>Quantity:</strong> <?php echo $item['quantity']; ?></p>
                            </div>
                            <div class="col-md-2 text-end">
                                <h6 class="mb-0"><?php echo formatPrice($item['total_price']); ?></h6>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
                
                <!-- Order Summary -->
                <div class="mt-4 pt-4 border-top">
                    <h6 class="fw-bold mb-3">Order Summary</h6>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="summary-row">
                                <span>Subtotal</span>
                                <span><?php echo formatPrice($order['total_amount']); ?></span>
                            </div>
                            <div class="summary-row">
                                <span>Tax</span>
                                <span><?php echo formatPrice($order['tax_amount']); ?></span>
                            </div>
                            <?php if ($order['discount_amount'] > 0): ?>
                                <div class="summary-row">
                                    <span>Discount</span>
                                    <span style="color: #28a745;">-<?php echo formatPrice($order['discount_amount']); ?></span>
                                </div>
                            <?php endif; ?>
                            <div class="summary-row">
                                <span>Total</span>
                                <span><?php echo formatPrice($order['final_amount']); ?></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Downloads -->
            <?php if (!empty($downloadLinks)): ?>
                <div class="order-card">
                    <h5 class="fw-bold mb-4">Available Downloads</h5>
                    <p class="text-muted mb-4">Your download links are valid for 30 days from the purchase date.</p>
                    
                    <?php foreach ($downloadLinks as $download): ?>
                        <div class="download-card">
                            <div class="row align-items-center">
                                <div class="col-md-8">
                                    <h6 class="mb-1"><?php echo sanitize($download['product_name']); ?></h6>
                                    <small class="text-muted">
                                        Expires: <?php echo date('F j, Y', strtotime($download['expires_at'])); ?>
                                        <?php if ($download['download_count'] > 0): ?>
                                            | Downloaded: <?php echo $download['download_count']; ?> times
                                        <?php endif; ?>
                                    </small>
                                </div>
                                <div class="col-md-4 text-end">
                                    <a href="download.php?token=<?php echo $download['download_token']; ?>" class="btn-download">
                                        <i class="fas fa-download me-2"></i> Download
                                    </a>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <!-- Action Buttons -->
            <div class="text-center">
                <a href="orders.php" class="btn btn-outline-primary me-2">
                    <i class="fas fa-list me-2"></i> View All Orders
                </a>
                <a href="products.php" class="btn btn-primary">
                    <i class="fas fa-shopping-bag me-2"></i> Continue Shopping
                </a>
            </div>
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
</body>
</html>
