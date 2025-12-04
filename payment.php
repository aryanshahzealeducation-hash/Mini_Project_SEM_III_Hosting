<?php
require_once 'config.php';
require_once 'database.php';
require_once 'functions.php';

// Check if user is logged in
if (!isLoggedIn()) {
    redirect('login.php');
}

// Check if order ID is set
if (!isset($_SESSION['order_id'])) {
    redirect('cart.php');
}

$db = new Database();
$orderId = $_SESSION['order_id'];
$userId = $_SESSION['user_id'];

// Get order details
$order = $db->query("
    SELECT o.*, u.name, u.email 
    FROM orders o 
    JOIN users u ON o.user_id = u.id 
    WHERE o.id = ? AND o.user_id = ?
", [$orderId, $userId])->fetch();

if (!$order) {
    redirect('cart.php');
}

// Get order items
$orderItems = $db->query("
    SELECT * FROM order_items 
    WHERE order_id = ?
", [$orderId])->fetchAll();

// Handle payment processing
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $paymentMethod = sanitize($_POST['payment_method']);
    $paymentStatus = 'completed'; // Simulate successful payment
    
    try {
        // Update order status
        $db->query("
            UPDATE orders 
            SET payment_status = ?, order_status = 'processing', paid_at = NOW()
            WHERE id = ?
        ", [$paymentStatus, $orderId]);
        
        // Create download links for each item
        foreach ($orderItems as $item) {
            $downloadToken = bin2hex(random_bytes(32));
            $expiresAt = date('Y-m-d H:i:s', strtotime('+30 days'));
            
            $db->query("
                INSERT INTO downloads (order_id, product_id, user_id, download_token, expires_at) 
                VALUES (?, ?, ?, ?, ?)
            ", [$orderId, $item['product_id'], $userId, $downloadToken, $expiresAt]);
        }
        
        // Clear session
        unset($_SESSION['order_id']);
        
        // Redirect to success page
        redirect('order-success.php?order=' . $orderId);
        
    } catch (Exception $e) {
        $error = 'Payment processing failed. Please try again.';
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment - <?php echo SITE_NAME; ?></title>
    
    <!-- MDB5 CSS -->
    <link rel="stylesheet" href="MDB5-STANDARD-UI-KIT-Free-9.2.0/css/mdb.min.css">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <style>
        :root {
            --primary-color: #3b71ca;
            --secondary-color: #9fa6b2;
        }
        
        body {
            background: #f8f9fa;
        }
        
        .page-header {
            background: linear-gradient(135deg, var(--primary-color) 0%, #667eea 100%);
            color: white;
            padding: 80px 0 40px;
        }
        
        .payment-card {
            background: white;
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            margin-bottom: 20px;
        }
        
        .order-summary {
            background: white;
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
        }
        
        .payment-method-demo {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
            text-align: center;
        }
        
        .demo-payment-form {
            max-width: 400px;
            margin: 0 auto;
        }
        
        .card-input {
            background: white;
            border: 2px solid #dee2e6;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 15px;
        }
        
        .card-input:focus {
            border-color: var(--primary-color);
            outline: none;
        }
        
        .pay-button {
            background: linear-gradient(135deg, var(--primary-color) 0%, #667eea 100%);
            border: none;
            padding: 15px 30px;
            font-weight: 500;
            transition: all 0.3s ease;
            width: 100%;
        }
        
        .pay-button:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(59, 113, 202, 0.3);
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
            font-size: 1.2rem;
            font-weight: bold;
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
            <h1 class="fw-bold mb-3">Payment</h1>
            <p class="lead mb-0">Complete your secure payment</p>
        </div>
    </section>

    <!-- Payment Content -->
    <section class="py-5">
        <div class="container">
            <?php if (isset($error)): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <?php echo $error; ?>
                    <button type="button" class="btn-close" data-mdb-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <div class="row">
                <!-- Payment Form -->
                <div class="col-lg-8">
                    <div class="payment-card">
                        <h5 class="fw-bold mb-4">Payment Details</h5>
                        
                        <div class="payment-method-demo">
                            <h6 class="mb-3">Demo Payment Form</h6>
                            <p class="text-muted mb-4">This is a demo payment form. In production, this would integrate with actual payment gateways.</p>
                            
                            <form method="POST" class="demo-payment-form">
                                <input type="hidden" name="payment_method" value="razorpay">
                                
                                <div class="card-input">
                                    <div class="d-flex align-items-center mb-2">
                                        <i class="fas fa-credit-card me-2"></i>
                                        <strong>Card Number</strong>
                                    </div>
                                    <input type="text" class="form-control" placeholder="4242 4242 4242 4242" value="4242 4242 4242 4242" readonly>
                                </div>
                                
                                <div class="row">
                                    <div class="col-6">
                                        <div class="card-input">
                                            <div class="d-flex align-items-center mb-2">
                                                <i class="fas fa-calendar me-2"></i>
                                                <strong>Expiry Date</strong>
                                            </div>
                                            <input type="text" class="form-control" placeholder="MM/YY" value="12/25" readonly>
                                        </div>
                                    </div>
                                    <div class="col-6">
                                        <div class="card-input">
                                            <div class="d-flex align-items-center mb-2">
                                                <i class="fas fa-lock me-2"></i>
                                                <strong>CVV</strong>
                                            </div>
                                            <input type="text" class="form-control" placeholder="123" value="123" readonly>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="card-input">
                                    <div class="d-flex align-items-center mb-2">
                                        <i class="fas fa-user me-2"></i>
                                        <strong>Cardholder Name</strong>
                                    </div>
                                    <input type="text" class="form-control" placeholder="John Doe" value="<?php echo sanitize($order['name']); ?>" readonly>
                                </div>
                                
                                <button type="submit" class="btn pay-button btn-lg mt-4">
                                    <i class="fas fa-lock me-2"></i> Pay <?php echo formatPrice($order['final_amount']); ?>
                                </button>
                            </form>
                            
                            <div class="mt-4">
                                <small class="text-muted">
                                    <i class="fas fa-info-circle me-1"></i>
                                    This is a demo payment. Click "Pay" to simulate a successful payment.
                                </small>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Order Summary -->
                <div class="col-lg-4">
                    <div class="order-summary">
                        <h5 class="fw-bold mb-4">Order Summary</h5>
                        
                        <div class="mb-3">
                            <strong>Order Number:</strong> <?php echo $order['order_number']; ?>
                        </div>
                        
                        <?php foreach ($orderItems as $item): ?>
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <div>
                                    <div class="fw-bold"><?php echo sanitize($item['product_name']); ?></div>
                                    <small class="text-muted">Qty: <?php echo $item['quantity']; ?></small>
                                </div>
                                <div><?php echo formatPrice($item['total_price']); ?></div>
                            </div>
                        <?php endforeach; ?>
                        
                        <hr>
                        
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
                        
                        <div class="mt-4">
                            <h6 class="fw-bold mb-2">Billing Information</h6>
                            <p class="mb-1"><strong>Name:</strong> <?php echo sanitize($order['name']); ?></p>
                            <p class="mb-0"><strong>Email:</strong> <?php echo sanitize($order['email']); ?></p>
                        </div>
                        
                        <div class="mt-4">
                            <h6 class="fw-bold mb-2">Payment Method</h6>
                            <div class="d-flex align-items-center">
                                <i class="fas fa-credit-card me-2"></i>
                                <span>Credit/Debit Card</span>
                            </div>
                        </div>
                        
                        <div class="security-badges mt-4">
                            <div class="text-center">
                                <i class="fas fa-lock fa-2x text-muted mb-2"></i>
                                <p class="small text-muted mb-0">Secure SSL Encrypted Payment</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- MDB5 JS -->
    <script src="MDB5-STANDARD-UI-KIT-Free-9.2.0/js/mdb.umd.min.js"></script>
    
    <script>
        // Add loading state to payment form
        document.querySelector('form').addEventListener('submit', function(e) {
            const submitBtn = document.querySelector('.pay-button');
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i> Processing Payment...';
        });
    </script>
</body>
</html>
