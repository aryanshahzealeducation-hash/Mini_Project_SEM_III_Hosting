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
$orderId = intval($_GET['order'] ?? 0);

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
    SELECT * FROM order_items 
    WHERE order_id = ?
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
    <title>Order Successful - <?php echo SITE_NAME; ?></title>
    
    <!-- MDB5 CSS -->
    <link rel="stylesheet" href="MDB5-STANDARD-UI-KIT-Free-9.2.0/css/mdb.min.css">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <style>
        :root {
            --primary-color: #3b71ca;
            --success-color: #14a44d;
        }
        
        body {
            background: #f8f9fa;
        }
        
        .success-header {
            background: linear-gradient(135deg, var(--success-color) 0%, #20c997 100%);
            color: white;
            padding: 80px 0 40px;
            text-align: center;
        }
        
        .success-icon {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            background: rgba(255,255,255,0.2);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 3rem;
            margin: 0 auto 20px;
        }
        
        .order-card {
            background: white;
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            margin-bottom: 20px;
        }
        
        .download-card {
            background: white;
            border-radius: 15px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            margin-bottom: 15px;
            transition: all 0.3s ease;
        }
        
        .download-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(0,0,0,0.15);
        }
        
        .download-btn {
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
        
        .download-btn:hover {
            background: #2f5aa2;
            transform: translateY(-2px);
            color: white;
        }
        
        .order-summary {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
        }
        
        .summary-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 8px 0;
            border-bottom: 1px solid #e9ecef;
        }
        
        .summary-row:last-child {
            border-bottom: none;
            font-weight: bold;
            font-size: 1.1rem;
            padding-top: 10px;
        }
        
        .action-buttons {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        
        .btn-action {
            padding: 12px 25px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 500;
            transition: all 0.3s ease;
        }
        
        .btn-primary-custom {
            background: var(--primary-color);
            color: white;
        }
        
        .btn-primary-custom:hover {
            background: #2f5aa2;
            color: white;
        }
        
        .btn-outline-custom {
            border: 2px solid var(--primary-color);
            color: var(--primary-color);
        }
        
        .btn-outline-custom:hover {
            background: var(--primary-color);
            color: white;
        }
        
        @media (max-width: 768px) {
            .success-header {
                padding: 60px 0 30px;
            }
            
            .action-buttons {
                justify-content: center;
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

    <!-- Success Header -->
    <section class="success-header">
        <div class="container">
            <div class="success-icon">
                <i class="fas fa-check"></i>
            </div>
            <h1 class="fw-bold mb-3">Order Successful!</h1>
            <p class="lead mb-0">Thank you for your purchase. Your order has been confirmed.</p>
        </div>
    </section>

    <!-- Order Details -->
    <section class="py-5">
        <div class="container">
            <!-- Order Information -->
            <div class="order-card">
                <div class="row">
                    <div class="col-md-8">
                        <h5 class="fw-bold mb-4">Order Information</h5>
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <strong>Order Number:</strong><br>
                                <span class="text-primary">#<?php echo $order['order_number']; ?></span>
                            </div>
                            <div class="col-md-6">
                                <strong>Order Date:</strong><br>
                                <?php echo date('F j, Y \a\t h:i A', strtotime($order['created_at'])); ?>
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <strong>Payment Status:</strong><br>
                                <span class="badge bg-success">Completed</span>
                            </div>
                            <div class="col-md-6">
                                <strong>Order Status:</strong><br>
                                <span class="badge bg-info">Processing</span>
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <strong>Customer:</strong><br>
                                <?php echo sanitize($order['name']); ?>
                            </div>
                            <div class="col-md-6">
                                <strong>Email:</strong><br>
                                <?php echo sanitize($order['email']); ?>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-4">
                        <div class="order-summary">
                            <h6 class="fw-bold mb-3">Order Summary</h6>
                            
                            <?php foreach ($orderItems as $item): ?>
                                <div class="summary-row">
                                    <span><?php echo sanitize($item['product_name']); ?> (<?php echo $item['quantity']; ?>)</span>
                                    <span><?php echo formatPrice($item['total_price']); ?></span>
                                </div>
                            <?php endforeach; ?>
                            
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

            <!-- Download Links -->
            <?php if (!empty($downloadLinks)): ?>
                <div class="order-card">
                    <h5 class="fw-bold mb-4">Your Downloads</h5>
                    <p class="text-muted mb-4">Your download links are valid for 30 days. Download your products now.</p>
                    
                    <?php foreach ($downloadLinks as $download): ?>
                        <div class="download-card">
                            <div class="row align-items-center">
                                <div class="col-md-8">
                                    <h6 class="mb-1"><?php echo sanitize($download['product_name']); ?></h6>
                                    <small class="text-muted">
                                        Expires: <?php echo date('F j, Y', strtotime($download['expires_at'])); ?>
                                    </small>
                                </div>
                                <div class="col-md-4 text-end">
                                    <a href="download.php?token=<?php echo $download['download_token']; ?>" class="download-btn">
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
                <div class="action-buttons justify-content-center">
                    <a href="orders.php" class="btn-action btn-primary-custom">
                        <i class="fas fa-list me-2"></i> View All Orders
                    </a>
                    <a href="products.php" class="btn-action btn-outline-custom">
                        <i class="fas fa-shopping-bag me-2"></i> Continue Shopping
                    </a>
                    <a href="profile.php" class="btn-action btn-outline-custom">
                        <i class="fas fa-user me-2"></i> My Profile
                    </a>
                </div>
            </div>

            <!-- Important Information -->
            <div class="order-card mt-4">
                <h6 class="fw-bold mb-3">
                    <i class="fas fa-info-circle me-2"></i> Important Information
                </h6>
                <ul class="mb-0">
                    <li>Your order confirmation has been sent to your email address.</li>
                    <li>Download links are valid for 30 days from the purchase date.</li>
                    <li>If you have any issues with your downloads, please contact our support team.</li>
                    <li>Keep your order number safe for future reference.</li>
                </ul>
            </div>
        </div>
    </section>

    <!-- MDB5 JS -->
    <script src="MDB5-STANDARD-UI-KIT-Free-9.2.0/js/mdb.umd.min.js"></script>
    
    <script>
        // Auto-redirect to orders after 10 seconds
        setTimeout(function() {
            // Optional: You can add an auto-redirect feature
            // window.location.href = 'orders.php';
        }, 10000);
    </script>
</body>
</html>
