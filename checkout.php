<?php
require_once 'config.php';
require_once 'database.php';
require_once 'functions.php';

// Check if user is logged in
if (!isLoggedIn()) {
    $_SESSION['redirect_url'] = 'checkout.php';
    redirect('login.php');
}

$db = new Database();
$userId = $_SESSION['user_id'];

// Get cart items
$cartItems = $db->query("
    SELECT c.*, p.name, p.price, p.screenshot, p.status as product_status
    FROM cart c
    JOIN products p ON c.product_id = p.id
    WHERE c.user_id = ? AND p.status = 'active'
    ORDER BY c.created_at DESC
", [$userId])->fetchAll();

// Check if cart is empty
if (empty($cartItems)) {
    redirect('cart.php');
}

// Calculate totals
$subtotal = 0;
$taxRate = 0; // Get from settings
foreach ($cartItems as $item) {
    $subtotal += $item['price'] * $item['quantity'];
}
$tax = $subtotal * ($taxRate / 100);
$total = $subtotal + $tax;

// Get user information
$user = $db->query("SELECT * FROM users WHERE id = ?", [$userId])->fetch();

// Handle order submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $paymentMethod = sanitize($_POST['payment_method']);
    $couponCode = sanitize($_POST['coupon_code'] ?? '');
    $notes = sanitize($_POST['notes'] ?? '');
    
    // Validate payment method
    $validMethods = ['razorpay', 'stripe', 'paypal'];
    if (!in_array($paymentMethod, $validMethods)) {
        $error = 'Invalid payment method';
    } else {
        try {
            $db->beginTransaction();
            
            // Generate order number
            $orderNumber = 'ORD' . date('Y') . str_pad(mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);
            
            // Calculate final amount (with coupon if valid)
            $discountAmount = 0;
            if ($couponCode) {
                $coupon = $db->query("
                    SELECT * FROM coupons 
                    WHERE code = ? AND status = 'active' 
                    AND (expires_at IS NULL OR expires_at > NOW())
                    AND (usage_limit IS NULL OR used_count < usage_limit)
                ", [$couponCode])->fetch();
                
                if ($coupon) {
                    if ($coupon['type'] === 'fixed') {
                        $discountAmount = min($coupon['value'], $subtotal);
                    } else {
                        $discountAmount = $subtotal * ($coupon['value'] / 100);
                    }
                    
                    // Update coupon usage
                    $db->query("UPDATE coupons SET used_count = used_count + 1 WHERE id = ?", [$coupon['id']]);
                }
            }
            
            $finalAmount = $subtotal + $tax - $discountAmount;
            
            // Create order
            $db->query("
                INSERT INTO orders (order_number, user_id, total_amount, discount_amount, tax_amount, final_amount, payment_method, payment_status, order_status, notes) 
                VALUES (?, ?, ?, ?, ?, ?, ?, 'pending', 'pending', ?)
            ", [$orderNumber, $userId, $subtotal, $discountAmount, $tax, $finalAmount, $paymentMethod, $notes]);
            
            $orderId = $db->lastInsertId();
            
            // Add order items
            foreach ($cartItems as $item) {
                $db->query("
                    INSERT INTO order_items (order_id, product_id, product_name, product_price, quantity, total_price) 
                    VALUES (?, ?, ?, ?, ?, ?)
                ", [$orderId, $item['product_id'], $item['name'], $item['price'], $item['quantity'], $item['price'] * $item['quantity']]);
            }
            
            // Clear cart
            $db->query("DELETE FROM cart WHERE user_id = ?", [$userId]);
            
            // Update cart count
            $_SESSION['cart_count'] = 0;
            
            $db->commit();
            
            // Redirect to payment page
            $_SESSION['order_id'] = $orderId;
            redirect('payment.php');
            
        } catch (Exception $e) {
            $db->rollback();
            $error = 'Error processing order. Please try again.';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Café Checkout - <?php echo SITE_NAME; ?></title>
    
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
        
        .checkout-card {
            background: white;
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            margin-bottom: 20px;
        }
        
        .payment-method {
            border: 2px solid #dee2e6;
            border-radius: 10px;
            padding: 20px;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-bottom: 15px;
        }
        
        .payment-method:hover {
            border-color: var(--primary-color);
            background: rgba(59, 113, 202, 0.05);
        }
        
        .payment-method.selected {
            border-color: var(--primary-color);
            background: rgba(59, 113, 202, 0.1);
        }
        
        .payment-method input[type="radio"] {
            display: none;
        }
        
        .payment-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
            margin-right: 15px;
        }
        
        .razorpay { background: #3395ff; color: white; }
        .stripe { background: #6772e5; color: white; }
        .paypal { background: #ffc439; color: #003087; }
        
        .order-summary {
            background: white;
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            position: sticky;
            top: 20px;
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
        
        .checkout-btn {
            background: linear-gradient(135deg, var(--primary-color) 0%, #667eea 100%);
            border: none;
            padding: 15px 30px;
            font-weight: 500;
            transition: all 0.3s ease;
            width: 100%;
        }
        
        .checkout-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(59, 113, 202, 0.3);
        }
        
        .checkout-btn:disabled {
            background: #6c757d;
            cursor: not-allowed;
            transform: none;
        }
        
        .coupon-section {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
            margin-top: 20px;
        }
        
        .security-badges {
            display: flex;
            gap: 10px;
            margin-top: 20px;
            justify-content: center;
        }
        
        .security-badges img {
            height: 30px;
        }
        
        @media (max-width: 768px) {
            .page-header {
                padding: 60px 0 30px;
            }
            
            .order-summary {
                position: relative;
                margin-top: 20px;
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
            <h1 class="fw-bold mb-3">Café Checkout</h1>
            <p class="lead mb-0">Complete your café order securely</p>
        </div>
    </section>

    <!-- Checkout Content -->
    <section class="py-5">
        <div class="container">
            <?php if (isset($error)): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <?php echo $error; ?>
                    <button type="button" class="btn-close" data-mdb-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <form method="POST" id="checkoutForm">
                <div class="row">
                    <!-- Billing Information -->
                    <div class="col-lg-8">
                        <div class="checkout-card">
                            <h5 class="fw-bold mb-4">Billing Information</h5>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Full Name</label>
                                    <input type="text" class="form-control" value="<?php echo sanitize($user['name']); ?>" readonly>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Email Address</label>
                                    <input type="email" class="form-control" value="<?php echo sanitize($user['email']); ?>" readonly>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="notes" class="form-label">Order Notes (Optional)</label>
                                <textarea class="form-control" id="notes" name="notes" rows="3" placeholder="Any special instructions or notes for your order..."></textarea>
                            </div>
                        </div>

                        <!-- Payment Method -->
                        <div class="checkout-card">
                            <h5 class="fw-bold mb-4">Payment Method</h5>
                            
                            <div class="payment-method" onclick="selectPayment('razorpay')">
                                <label class="d-flex align-items-center mb-0">
                                    <input type="radio" name="payment_method" value="razorpay" required>
                                    <div class="payment-icon razorpay">
                                        <i class="fas fa-credit-card"></i>
                                    </div>
                                    <div>
                                        <h6 class="mb-1">Razorpay</h6>
                                        <small class="text-muted">Pay with Credit Card, Debit Card, UPI, NetBanking</small>
                                    </div>
                                </label>
                            </div>
                            
                            <div class="payment-method" onclick="selectPayment('stripe')">
                                <label class="d-flex align-items-center mb-0">
                                    <input type="radio" name="payment_method" value="stripe" required>
                                    <div class="payment-icon stripe">
                                        <i class="fab fa-stripe"></i>
                                    </div>
                                    <div>
                                        <h6 class="mb-1">Stripe</h6>
                                        <small class="text-muted">Pay with Credit Card or Debit Card</small>
                                    </div>
                                </label>
                            </div>
                            
                            <div class="payment-method" onclick="selectPayment('paypal')">
                                <label class="d-flex align-items-center mb-0">
                                    <input type="radio" name="payment_method" value="paypal" required>
                                    <div class="payment-icon paypal">
                                        <i class="fab fa-paypal"></i>
                                    </div>
                                    <div>
                                        <h6 class="mb-1">PayPal</h6>
                                        <small class="text-muted">Pay with your PayPal account</small>
                                    </div>
                                </label>
                            </div>
                        </div>

                        <!-- Coupon Code -->
                        <div class="coupon-section">
                            <h6 class="fw-bold mb-3">Coupon Code</h6>
                            <div class="input-group">
                                <input type="text" class="form-control" name="coupon_code" placeholder="Enter coupon code">
                                <button class="btn btn-outline-primary" type="button" onclick="applyCoupon()">Apply</button>
                            </div>
                            <small class="text-muted">Enter a coupon code if you have one</small>
                        </div>
                    </div>
                    
                    <!-- Order Summary -->
                    <div class="col-lg-4">
                        <div class="order-summary">
                            <h5 class="fw-bold mb-4">Order Summary</h5>
                            
                            <?php foreach ($cartItems as $item): ?>
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <div>
                                        <div class="fw-bold"><?php echo sanitize($item['name']); ?></div>
                                        <small class="text-muted">Qty: <?php echo $item['quantity']; ?></small>
                                    </div>
                                    <div><?php echo formatPrice($item['price'] * $item['quantity']); ?></div>
                                </div>
                            <?php endforeach; ?>
                            
                            <hr>
                            
                            <div class="summary-row">
                                <span>Subtotal</span>
                                <span><?php echo formatPrice($subtotal); ?></span>
                            </div>
                            
                            <div class="summary-row">
                                <span>Tax</span>
                                <span><?php echo formatPrice($tax); ?></span>
                            </div>
                            
                            <div class="summary-row" id="discountRow" style="display: none;">
                                <span>Discount</span>
                                <span id="discountAmount" style="color: #28a745;">-<?php echo formatPrice(0); ?></span>
                            </div>
                            
                            <div class="summary-row">
                                <span>Total</span>
                                <span id="totalAmount"><?php echo formatPrice($total); ?></span>
                            </div>
                            
                            <button type="submit" class="btn checkout-btn btn-lg mt-4" id="placeOrderBtn">
                                <i class="fas fa-lock me-2"></i> Place Café Order
                            </button>
                            
                            <div class="security-badges">
                                <i class="fas fa-lock fa-2x text-muted"></i>
                                <i class="fas fa-shield-alt fa-2x text-muted"></i>
                                <i class="fas fa-user-shield fa-2x text-muted"></i>
                            </div>
                            
                            <div class="text-center mt-3">
                                <small class="text-muted">
                                    Your payment information is secure and encrypted
                                </small>
                            </div>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </section>

    <!-- MDB5 JS -->
    <script src="MDB5-STANDARD-UI-KIT-Free-9.2.0/js/mdb.umd.min.js"></script>
    
    <script>
        function selectPayment(method) {
            // Remove selected class from all payment methods
            document.querySelectorAll('.payment-method').forEach(el => {
                el.classList.remove('selected');
            });
            
            // Add selected class to clicked method
            event.currentTarget.classList.add('selected');
            
            // Check the radio button
            document.querySelector(`input[value="${method}"]`).checked = true;
        }
        
        function applyCoupon() {
            const couponCode = document.querySelector('input[name="coupon_code"]').value;
            if (!couponCode) {
                alert('Please enter a coupon code');
                return;
            }
            
            // For demo purposes, just show a discount
            // In production, this would validate the coupon via AJAX
            const discountRow = document.getElementById('discountRow');
            const discountAmount = document.getElementById('discountAmount');
            const totalAmount = document.getElementById('totalAmount');
            
            // Simulate a 10% discount
            const currentTotal = <?php echo $total; ?>;
            const discount = currentTotal * 0.1;
            const newTotal = currentTotal - discount;
            
            discountRow.style.display = 'flex';
            discountAmount.textContent = '-<?php echo CURRENCY_SYMBOL; ?>' + discount.toFixed(2);
            totalAmount.textContent = '<?php echo CURRENCY_SYMBOL; ?>' + newTotal.toFixed(2);
        }
        
        document.getElementById('checkoutForm').addEventListener('submit', function(e) {
            const paymentMethod = document.querySelector('input[name="payment_method"]:checked');
            if (!paymentMethod) {
                e.preventDefault();
                alert('Please select a payment method');
                return;
            }
            
            // Show loading state
            const submitBtn = document.getElementById('placeOrderBtn');
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i> Processing...';
        });
    </script>
</body>
</html>
