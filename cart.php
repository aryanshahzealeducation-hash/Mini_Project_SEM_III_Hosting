<?php
require_once 'config.php';
require_once 'database.php';
require_once 'functions.php';

// Check if user is logged in
if (!isLoggedIn()) {
    $_SESSION['redirect_url'] = 'cart.php';
    redirect('login.php');
}

$db = new Database();
$userId = $_SESSION['user_id'];

// Get cart items
$cartItems = $db->query("
    SELECT c.*, p.name, p.price, p.screenshot, p.status as product_status
    FROM cart c
    JOIN products p ON c.product_id = p.id
    WHERE c.user_id = ?
    ORDER BY c.created_at DESC
", [$userId])->fetchAll();

// Calculate totals
$subtotal = 0;
$taxRate = 0; // Default tax rate, you can get this from settings
foreach ($cartItems as $item) {
    $subtotal += $item['price'] * $item['quantity'];
}
$tax = $subtotal * ($taxRate / 100);
$total = $subtotal + $tax;

// Handle cart actions
$action = sanitize($_POST['action'] ?? '');
$message = '';
$messageType = '';

switch ($action) {
    case 'update':
        $cartId = intval($_POST['cart_id']);
        $quantity = intval($_POST['quantity']);
        
        if ($quantity > 0) {
            $db->query("UPDATE cart SET quantity = ? WHERE id = ? AND user_id = ?", [$quantity, $cartId, $userId]);
            $message = 'Cart updated!';
            $messageType = 'success';
            
            // Refresh cart items
            $cartItems = $db->query("
                SELECT c.*, p.name, p.price, p.screenshot, p.status as product_status
                FROM cart c
                JOIN products p ON c.product_id = p.id
                WHERE c.user_id = ?
                ORDER BY c.created_at DESC
            ", [$userId])->fetchAll();
            
            // Recalculate totals
            $subtotal = 0;
            foreach ($cartItems as $item) {
                $subtotal += $item['price'] * $item['quantity'];
            }
            $tax = $subtotal * ($taxRate / 100);
            $total = $subtotal + $tax;
        }
        break;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Café Order - <?php echo SITE_NAME; ?></title>
    
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
        
        .cart-item {
            background: white;
            border-radius: 15px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            margin-bottom: 20px;
            transition: all 0.3s ease;
        }
        
        .cart-item:hover {
            box-shadow: 0 5px 20px rgba(0,0,0,0.15);
        }
        
        .product-image {
            width: 100px;
            height: 100px;
            object-fit: cover;
            border-radius: 10px;
        }
        
        .quantity-input {
            width: 80px;
            text-align: center;
            border: 1px solid #dee2e6;
            border-radius: 5px;
            padding: 5px;
        }
        
        .summary-card {
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
        
        .btn-checkout {
            background: linear-gradient(135deg, var(--primary-color) 0%, #667eea 100%);
            border: none;
            padding: 15px 30px;
            font-weight: 500;
            transition: all 0.3s ease;
            width: 100%;
        }
        
        .btn-checkout:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(59, 113, 202, 0.3);
        }
        
        .empty-cart {
            text-align: center;
            padding: 60px 20px;
        }
        
        .empty-cart i {
            font-size: 5rem;
            color: #dee2e6;
            margin-bottom: 20px;
        }
        
        @media (max-width: 768px) {
            .page-header {
                padding: 60px 0 30px;
            }
            
            .summary-card {
                position: relative;
                margin-top: 20px;
            }
            
            .product-image {
                width: 80px;
                height: 80px;
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
            <h1 class="fw-bold mb-3">Café Order</h1>
            <p class="lead mb-0">Review your menu items before checkout</p>
        </div>
    </section>

    <!-- Cart Content -->
    <section class="py-5">
        <div class="container">
            <?php if ($message): ?>
                <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show" role="alert">
                    <?php echo $message; ?>
                    <button type="button" class="btn-close" data-mdb-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <?php if (empty($cartItems)): ?>
                <div class="empty-cart">
                    <i class="fas fa-coffee"></i>
                    <h3>Your order is empty</h3>
                    <p class="text-muted mb-4">Looks like you haven't added any menu items to your order yet.</p>
                    <a href="products.php" class="btn btn-primary btn-lg">
                        <i class="fas fa-coffee me-2"></i> Browse Menu
                    </a>
                </div>
            <?php else: ?>
                <div class="row">
                    <!-- Cart Items -->
                    <div class="col-lg-8">
                        <?php foreach ($cartItems as $item): ?>
                            <div class="cart-item">
                                <div class="row align-items-center">
                                    <div class="col-md-2">
                                        <?php if ($item['screenshot']): ?>
                                            <img src="<?php echo $item['screenshot']; ?>" class="product-image" alt="<?php echo sanitize($item['name']); ?>">
                                        <?php else: ?>
                                            <div class="product-image d-flex align-items-center justify-content-center bg-light">
                                                <i class="fas fa-image fa-2x text-muted"></i>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="col-md-4">
                                        <h5 class="mb-1"><?php echo sanitize($item['name']); ?></h5>
                                        <p class="text-muted mb-0">
                                            <?php if ($item['product_status'] === 'active'): ?>
                                                <span class="badge bg-success">In Stock</span>
                                            <?php else: ?>
                                                <span class="badge bg-danger">Out of Stock</span>
                                            <?php endif; ?>
                                        </p>
                                    </div>
                                    <div class="col-md-2">
                                        <div class="fw-bold"><?php echo formatPrice($item['price']); ?></div>
                                    </div>
                                    <div class="col-md-2">
                                        <div class="d-flex align-items-center">
                                            <button class="btn btn-sm btn-outline-secondary" onclick="updateQuantity(<?php echo $item['id']; ?>, <?php echo $item['quantity'] - 1; ?>)">
                                                <i class="fas fa-minus"></i>
                                            </button>
                                            <input type="number" class="quantity-input mx-2" id="qty-<?php echo $item['id']; ?>" 
                                                   value="<?php echo $item['quantity']; ?>" min="1" max="10" readonly>
                                            <button class="btn btn-sm btn-outline-secondary" onclick="updateQuantity(<?php echo $item['id']; ?>, <?php echo $item['quantity'] + 1; ?>)">
                                                <i class="fas fa-plus"></i>
                                            </button>
                                        </div>
                                    </div>
                                    <div class="col-md-2 text-end">
                                        <div class="fw-bold mb-2"><?php echo formatPrice($item['price'] * $item['quantity']); ?></div>
                                        <button class="btn btn-sm btn-outline-danger" onclick="removeFromCart(<?php echo $item['id']; ?>)">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                        
                        <div class="text-center mt-4">
                            <a href="products.php" class="btn btn-outline-primary">
                                <i class="fas fa-arrow-left me-2"></i> Continue Shopping
                            </a>
                        </div>
                    </div>
                    
                    <!-- Order Summary -->
                    <div class="col-lg-4">
                        <div class="summary-card">
                            <h5 class="fw-bold mb-4">Order Summary</h5>
                            
                            <div class="summary-row">
                                <span>Subtotal (<?php echo count($cartItems); ?> items)</span>
                                <span><?php echo formatPrice($subtotal); ?></span>
                            </div>
                            
                            <div class="summary-row">
                                <span>Tax</span>
                                <span><?php echo formatPrice($tax); ?></span>
                            </div>
                            
                            <div class="summary-row">
                                <span>Total</span>
                                <span><?php echo formatPrice($total); ?></span>
                            </div>
                            
                            <a href="checkout.php" class="btn btn-checkout btn-lg mt-4">
                                <i class="fas fa-lock me-2"></i> Proceed to Checkout
                            </a>
                            
                            <div class="text-center mt-3">
                                <small class="text-muted">
                                    <i class="fas fa-shield-alt me-1"></i> Secure checkout powered by SSL
                                </small>
                            </div>
                            
                            <div class="mt-4">
                                <h6 class="fw-bold mb-3">Accepted Payment Methods</h6>
                                <div class="d-flex gap-2">
                                    <i class="fab fa-cc-visa fa-2x text-muted"></i>
                                    <i class="fab fa-cc-mastercard fa-2x text-muted"></i>
                                    <i class="fab fa-cc-amex fa-2x text-muted"></i>
                                    <i class="fab fa-paypal fa-2x text-muted"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
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
                <a href="cart.php" class="text-decoration-none text-primary p-2 position-relative">
                    <i class="fas fa-shopping-cart fa-lg"></i>
                    <div class="small">Order</div>
                    <span class="position-absolute top-0 start-50 translate-middle badge rounded-pill bg-danger" style="font-size: 0.6rem;">
                        <?php echo isset($_SESSION['cart_count']) ? $_SESSION['cart_count'] : 0; ?>
                    </span>
                </a>
                <?php if (isLoggedIn()): ?>
                    <a href="profile.php" class="text-decoration-none text-muted p-2">
                        <i class="fas fa-user fa-lg"></i>
                        <div class="small">Profile</div>
                    </a>
                <?php else: ?>
                    <a href="login.php" class="text-decoration-none text-muted p-2">
                        <i class="fas fa-sign-in-alt fa-lg"></i>
                        <div class="small">Login</div>
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </nav>

    <!-- MDB5 JS -->
    <script src="MDB5-STANDARD-UI-KIT-Free-9.2.0/js/mdb.umd.min.js"></script>
    
    <script>
        function updateQuantity(cartId, newQuantity) {
            if (newQuantity < 1 || newQuantity > 10) return;
            
            const form = document.createElement('form');
            form.method = 'POST';
            form.innerHTML = `
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="cart_id" value="${cartId}">
                <input type="hidden" name="quantity" value="${newQuantity}">
            `;
            document.body.appendChild(form);
            form.submit();
        }
        
        function removeFromCart(cartId) {
            if (confirm('Are you sure you want to remove this item from your cart?')) {
                fetch('cart-handler.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `action=remove&cart_id=${cartId}`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        location.reload();
                    } else {
                        alert('Error removing item from cart');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error removing item from cart');
                });
            }
        }
        
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
