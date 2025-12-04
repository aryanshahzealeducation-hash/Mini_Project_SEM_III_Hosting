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

// Handle profile update
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = sanitize($_POST['action'] ?? '');
    
    switch ($action) {
        case 'update_profile':
            $name = sanitize($_POST['name']);
            $email = sanitize($_POST['email']);
            
            // Validate email
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $message = 'Invalid email address';
                $messageType = 'danger';
            } else {
                // Check if email is already taken by another user
                $existingUser = $db->query("SELECT id FROM users WHERE email = ? AND id != ?", [$email, $userId])->fetch();
                if ($existingUser) {
                    $message = 'Email address is already in use';
                    $messageType = 'danger';
                } else {
                    $db->query("UPDATE users SET name = ?, email = ? WHERE id = ?", [$name, $email, $userId]);
                    
                    // Update session
                    $_SESSION['user_name'] = $name;
                    
                    $message = 'Profile updated successfully!';
                    $messageType = 'success';
                }
            }
            break;
            
        case 'change_password':
            $currentPassword = $_POST['current_password'];
            $newPassword = $_POST['new_password'];
            $confirmPassword = $_POST['confirm_password'];
            
            // Get current user
            $user = $db->query("SELECT password FROM users WHERE id = ?", [$userId])->fetch();
            
            if (!password_verify($currentPassword, $user['password'])) {
                $message = 'Current password is incorrect';
                $messageType = 'danger';
            } elseif ($newPassword !== $confirmPassword) {
                $message = 'New passwords do not match';
                $messageType = 'danger';
            } elseif (strlen($newPassword) < 8) {
                $message = 'Password must be at least 8 characters long';
                $messageType = 'danger';
            } else {
                $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT, ['cost' => HASH_COST]);
                $db->query("UPDATE users SET password = ? WHERE id = ?", [$hashedPassword, $userId]);
                
                $message = 'Password changed successfully!';
                $messageType = 'success';
            }
            break;
    }
}

// Get user information
$user = $db->query("SELECT * FROM users WHERE id = ?", [$userId])->fetch();

// Get user's orders
$orders = $db->query("
    SELECT o.*, COUNT(oi.id) as item_count
    FROM orders o
    LEFT JOIN order_items oi ON o.id = oi.order_id
    WHERE o.user_id = ?
    GROUP BY o.id
    ORDER BY o.created_at DESC
    LIMIT 5
", [$userId])->fetchAll();

// Get user's download links
$downloads = $db->query("
    SELECT d.*, p.name as product_name, p.file_path
    FROM downloads d
    JOIN products p ON d.product_id = p.id
    WHERE d.user_id = ? AND d.expires_at > NOW()
    ORDER BY d.created_at DESC
    LIMIT 5
", [$userId])->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Café Profile - <?php echo SITE_NAME; ?></title>
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <!-- Bootstrap CSS (for reliable dropdowns) -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- MDB5 CSS -->
    <link rel="stylesheet" href="MDB5-STANDARD-UI-KIT-Free-9.2.0/css/mdb.min.css">
    
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
        
        .profile-card {
            background: white;
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            margin-bottom: 20px;
        }
        
        .avatar {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary-color) 0%, #667eea 100%);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 3rem;
            font-weight: bold;
            margin: 0 auto 20px;
        }
        
        .nav-tabs .nav-link {
            color: var(--secondary-color);
            border: none;
            padding: 12px 20px;
            transition: all 0.3s ease;
        }
        
        .nav-tabs .nav-link.active {
            color: var(--primary-color);
            background: rgba(59, 113, 202, 0.1);
            border-radius: 8px 8px 0 0;
        }
        
        .nav-tabs .nav-link:hover {
            color: var(--primary-color);
        }
        
        .order-item {
            background: white;
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 15px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
            transition: all 0.3s ease;
        }
        
        .order-item:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        
        .download-item {
            background: white;
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 15px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
        }
        
        .btn-download {
            background: var(--primary-color);
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 6px;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            font-size: 0.9rem;
            transition: all 0.3s ease;
        }
        
        .btn-download:hover {
            background: #2f5aa2;
            color: white;
            transform: translateY(-2px);
        }
        
        .badge-status {
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 500;
        }
        
        .badge-completed { background: rgba(20, 164, 77, 0.1); color: #14a44d; }
        .badge-pending { background: rgba(228, 161, 27, 0.1); color: #e4a11b; }
        .badge-processing { background: rgba(84, 180, 211, 0.1); color: #54b4d3; }
        
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
                            <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown" role="button" aria-expanded="false">
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
            <h1 class="fw-bold mb-3">My Café Profile</h1>
            <p class="lead mb-0">Manage your café membership and view your orders</p>
        </div>
    </section>

    <!-- Profile Content -->
    <section class="py-5">
        <div class="container">
            <?php if ($message): ?>
                <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show" role="alert">
                    <?php echo $message; ?>
                    <button type="button" class="btn-close" data-mdb-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <!-- Profile Tabs -->
            <ul class="nav nav-tabs mb-4" id="profileTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" id="profile-tab" data-mdb-toggle="tab" data-mdb-target="#profile" type="button" role="tab">
                        <i class="fas fa-user me-2"></i> Profile
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="orders-tab" data-mdb-toggle="tab" data-mdb-target="#orders" type="button" role="tab">
                        <i class="fas fa-shopping-cart me-2"></i> Recent Orders
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="downloads-tab" data-mdb-toggle="tab" data-mdb-target="#downloads" type="button" role="tab">
                        <i class="fas fa-download me-2"></i> Downloads
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="security-tab" data-mdb-toggle="tab" data-mdb-target="#security" type="button" role="tab">
                        <i class="fas fa-lock me-2"></i> Security
                    </button>
                </li>
            </ul>

            <div class="tab-content" id="profileTabContent">
                <!-- Profile Tab -->
                <div class="tab-pane fade show active" id="profile" role="tabpanel">
                    <div class="profile-card">
                        <div class="text-center mb-4">
                            <div class="avatar">
                                <?php echo strtoupper(substr($user['name'], 0, 2)); ?>
                            </div>
                            <h4 class="fw-bold"><?php echo sanitize($user['name']); ?></h4>
                            <p class="text-muted">Member since <?php echo date('F Y', strtotime($user['created_at'])); ?></p>
                        </div>
                        
                        <form method="POST">
                            <input type="hidden" name="action" value="update_profile">
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="name" class="form-label">Full Name</label>
                                    <input type="text" class="form-control" id="name" name="name" value="<?php echo sanitize($user['name']); ?>" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="email" class="form-label">Email Address</label>
                                    <input type="email" class="form-control" id="email" name="email" value="<?php echo sanitize($user['email']); ?>" required>
                                </div>
                            </div>
                            
                            <div class="text-end">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save me-2"></i> Update Profile
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Orders Tab -->
                <div class="tab-pane fade" id="orders" role="tabpanel">
                    <div class="profile-card">
                        <h5 class="fw-bold mb-4">Recent Orders</h5>
                        
                        <?php if (empty($orders)): ?>
                            <div class="text-center py-5">
                                <i class="fas fa-shopping-cart fa-3x text-muted mb-3"></i>
                                <h6>No orders yet</h6>
                                <p class="text-muted">You haven't placed any orders yet.</p>
                                <a href="products.php" class="btn btn-primary">
                                    <i class="fas fa-shopping-bag me-2"></i> Start Shopping
                                </a>
                            </div>
                        <?php else: ?>
                            <?php foreach ($orders as $order): ?>
                                <div class="order-item">
                                    <div class="row align-items-center">
                                        <div class="col-md-4">
                                            <h6 class="mb-1">#<?php echo $order['order_number']; ?></h6>
                                            <small class="text-muted"><?php echo date('M j, Y', strtotime($order['created_at'])); ?></small>
                                        </div>
                                        <div class="col-md-4">
                                            <span class="badge-status badge-<?php echo $order['payment_status']; ?>">
                                                <?php echo ucfirst($order['payment_status']); ?>
                                            </span>
                                            <small class="text-muted ms-2"><?php echo $order['item_count']; ?> items</small>
                                        </div>
                                        <div class="col-md-2">
                                            <strong><?php echo formatPrice($order['final_amount']); ?></strong>
                                        </div>
                                        <div class="col-md-2 text-end">
                                            <a href="order-details.php?id=<?php echo $order['id']; ?>" class="btn btn-sm btn-outline-primary">
                                                View Details
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                            
                            <div class="text-center mt-4">
                                <a href="orders.php" class="btn btn-outline-primary">
                                    View All Orders <i class="fas fa-arrow-right ms-2"></i>
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Downloads Tab -->
                <div class="tab-pane fade" id="downloads" role="tabpanel">
                    <div class="profile-card">
                        <h5 class="fw-bold mb-4">Available Downloads</h5>
                        
                        <?php if (empty($downloads)): ?>
                            <div class="text-center py-5">
                                <i class="fas fa-download fa-3x text-muted mb-3"></i>
                                <h6>No downloads available</h6>
                                <p class="text-muted">You don't have any active downloads.</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($downloads as $download): ?>
                                <div class="download-item">
                                    <div class="row align-items-center">
                                        <div class="col-md-8">
                                            <h6 class="mb-1"><?php echo sanitize($download['product_name']); ?></h6>
                                            <small class="text-muted">
                                                Expires: <?php echo date('F j, Y', strtotime($download['expires_at'])); ?>
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
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Security Tab -->
                <div class="tab-pane fade" id="security" role="tabpanel">
                    <div class="profile-card">
                        <h5 class="fw-bold mb-4">Change Password</h5>
                        
                        <form method="POST">
                            <input type="hidden" name="action" value="change_password">
                            
                            <div class="mb-3">
                                <label for="current_password" class="form-label">Current Password</label>
                                <input type="password" class="form-control" id="current_password" name="current_password" required>
                            </div>
                            
                            <div class="mb-3">
                                <label for="new_password" class="form-label">New Password</label>
                                <input type="password" class="form-control" id="new_password" name="new_password" required minlength="8">
                                <small class="text-muted">Password must be at least 8 characters long</small>
                            </div>
                            
                            <div class="mb-3">
                                <label for="confirm_password" class="form-label">Confirm New Password</label>
                                <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                            </div>
                            
                            <div class="text-end">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-lock me-2"></i> Change Password
                                </button>
                            </div>
                        </form>
                        
                        <hr class="my-5">
                        
                        <h5 class="fw-bold mb-4">Account Information</h5>
                        <div class="row">
                            <div class="col-md-6">
                                <p><strong>Account Type:</strong> <?php echo ucfirst($user['role']); ?></p>
                                <p><strong>Status:</strong> <span class="badge bg-success">Active</span></p>
                            </div>
                            <div class="col-md-6">
                                <p><strong>Member Since:</strong> <?php echo date('F j, Y', strtotime($user['created_at'])); ?></p>
                                <p><strong>Email Verified:</strong> 
                                    <?php echo $user['email_verified'] ? '<span class="badge bg-success">Yes</span>' : '<span class="badge bg-warning">No</span>'; ?>
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
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

    <!-- Bootstrap JS (for reliable dropdowns) -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- MDB5 JS (for styling) -->
    <script src="MDB5-STANDARD-UI-KIT-Free-9.2.0/js/mdb.umd.min.js"></script>
    
    <script>
        // Initialize components
        document.addEventListener('DOMContentLoaded', function() {
            console.log('DOM loaded, initializing dropdowns...');
            
            // Bootstrap dropdowns should work automatically
            // Test if Bootstrap is loaded
            if (typeof bootstrap !== 'undefined') {
                console.log('Bootstrap loaded successfully');
                
                // Initialize any Bootstrap components if needed
                const dropdownTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="dropdown"]'));
                const dropdownList = dropdownTriggerList.map(function (dropdownTriggerEl) {
                    return new bootstrap.Dropdown(dropdownTriggerEl);
                });
                console.log('Bootstrap dropdowns initialized:', dropdownList.length);
            } else {
                console.error('Bootstrap not loaded');
            }
            
            // MDB5 for styling only (no dropdown initialization)
            if (typeof window.mdb !== 'undefined') {
                console.log('MDB5 loaded for styling');
            }
            
            // Password confirmation validation
            const confirmPwd = document.getElementById('confirm_password');
            if (confirmPwd) {
                confirmPwd.addEventListener('input', function() {
                    const newPassword = document.getElementById('new_password').value;
                    const confirmPassword = this.value;
                    
                    if (newPassword !== confirmPassword) {
                        this.setCustomValidity('Passwords do not match');
                    } else {
                        this.setCustomValidity('');
                    }
                });
            }
        });
    </script>
</body>
</html>
