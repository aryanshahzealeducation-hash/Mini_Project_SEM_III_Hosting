<?php
require_once 'config.php';
require_once 'database.php';
require_once 'functions.php';

$db = new Database();

// Get FAQ items
$faqItems = $db->query("SELECT * FROM faq WHERE status = 'active' ORDER BY sort_order ASC")->fetchAll();

// Get categories for filtering
$categories = $db->query("SELECT DISTINCT category FROM faq WHERE category IS NOT NULL AND category != '' ORDER BY category")->fetchAll();

// Handle search
$search = sanitize($_GET['search'] ?? '');
$category = sanitize($_GET['category'] ?? '');

$where = ["status = 'active'"];
$params = [];

if ($search) {
    $where[] = "(question LIKE ? OR answer LIKE ?)";
    $searchParam = "%$search%";
    $params[] = $searchParam;
    $params[] = $searchParam;
}

if ($category) {
    $where[] = "category = ?";
    $params[] = $category;
}

$whereClause = "WHERE " . implode(" AND ", $where);

$filteredFAQ = $db->query("SELECT * FROM faq $whereClause ORDER BY sort_order ASC", $params)->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FAQ - <?php echo SITE_NAME; ?></title>
    
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
            text-align: center;
        }
        
        .faq-card {
            background: white;
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            margin-bottom: 20px;
        }
        
        .faq-item {
            background: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 15px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
            transition: all 0.3s ease;
        }
        
        .faq-item:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        
        .faq-question {
            cursor: pointer;
            font-weight: 600;
            color: var(--primary-color);
            margin-bottom: 10px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .faq-answer {
            color: #6c757d;
            line-height: 1.6;
        }
        
        .faq-icon {
            transition: transform 0.3s ease;
        }
        
        .faq-item.active .faq-icon {
            transform: rotate(180deg);
        }
        
        .category-badge {
            background: rgba(59, 113, 202, 0.1);
            color: var(--primary-color);
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 500;
        }
        
        .search-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            margin-bottom: 20px;
        }
        
        .help-card {
            background: linear-gradient(135deg, var(--primary-color) 0%, #667eea 100%);
            color: white;
            border-radius: 15px;
            padding: 30px;
            text-align: center;
        }
        
        .help-icon {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background: rgba(255,255,255,0.2);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            margin: 0 auto 20px;
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
            <h1 class="fw-bold mb-3">Frequently Asked Questions</h1>
            <p class="lead mb-0">Find answers to common questions about our products and services</p>
        </div>
    </section>

    <!-- FAQ Content -->
    <section class="py-5">
        <div class="container">
            <!-- Search and Filter -->
            <div class="search-card">
                <form method="GET" class="row g-3">
                    <div class="col-md-6">
                        <input type="text" class="form-control" name="search" placeholder="Search FAQs..." value="<?php echo $search; ?>">
                    </div>
                    <div class="col-md-4">
                        <select class="form-select" name="category">
                            <option value="">All Categories</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?php echo $cat['category']; ?>" <?php echo $category === $cat['category'] ? 'selected' : ''; ?>>
                                    <?php echo sanitize($cat['category']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <button type="submit" class="btn btn-primary w-100">Search</button>
                    </div>
                </form>
            </div>

            <div class="row">
                <!-- FAQ Items -->
                <div class="col-lg-8">
                    <div class="faq-card">
                        <?php if (empty($filteredFAQ)): ?>
                            <div class="text-center py-5">
                                <i class="fas fa-question-circle fa-3x text-muted mb-3"></i>
                                <h5>No FAQs found</h5>
                                <p class="text-muted">Try adjusting your search or filter criteria.</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($filteredFAQ as $faq): ?>
                                <div class="faq-item" onclick="toggleFAQ(this)">
                                    <div class="faq-question">
                                        <span><?php echo sanitize($faq['question']); ?></span>
                                        <i class="fas fa-chevron-down faq-icon"></i>
                                    </div>
                                    <div class="faq-answer">
                                        <?php echo sanitize($faq['answer']); ?>
                                    </div>
                                    <?php if ($faq['category']): ?>
                                        <div class="mt-3">
                                            <span class="category-badge"><?php echo sanitize($faq['category']); ?></span>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Help Section -->
                <div class="col-lg-4">
                    <div class="help-card">
                        <div class="help-icon">
                            <i class="fas fa-headset"></i>
                        </div>
                        <h5 class="fw-bold mb-3">Still Need Help?</h5>
                        <p class="mb-4">Can't find what you're looking for? Our support team is here to help!</p>
                        <a href="contact.php" class="btn btn-light btn-lg">
                            <i class="fas fa-envelope me-2"></i> Contact Support
                        </a>
                    </div>
                    
                    <div class="faq-card mt-4">
                        <h6 class="fw-bold mb-3">Popular Topics</h6>
                        <ul class="list-unstyled">
                            <li class="mb-2">
                                <a href="?search=payment" class="text-decoration-none text-primary">
                                    <i class="fas fa-credit-card me-2"></i> Payment & Billing
                                </a>
                            </li>
                            <li class="mb-2">
                                <a href="?search=download" class="text-decoration-none text-primary">
                                    <i class="fas fa-download me-2"></i> Downloads
                                </a>
                            </li>
                            <li class="mb-2">
                                <a href="?search=account" class="text-decoration-none text-primary">
                                    <i class="fas fa-user me-2"></i> Account Management
                                </a>
                            </li>
                            <li class="mb-2">
                                <a href="?search=refund" class="text-decoration-none text-primary">
                                    <i class="fas fa-undo me-2"></i> Refunds & Returns
                                </a>
                            </li>
                        </ul>
                    </div>
                    
                    <div class="faq-card mt-4">
                        <h6 class="fw-bold mb-3">Quick Links</h6>
                        <ul class="list-unstyled">
                            <li class="mb-2">
                                <a href="products.php" class="text-decoration-none text-primary">
                                    <i class="fas fa-shopping-bag me-2"></i> Browse Products
                                </a>
                            </li>
                            <li class="mb-2">
                                <a href="orders.php" class="text-decoration-none text-primary">
                                    <i class="fas fa-list me-2"></i> My Orders
                                </a>
                            </li>
                            <li class="mb-2">
                                <a href="profile.php" class="text-decoration-none text-primary">
                                    <i class="fas fa-user me-2"></i> My Profile
                                </a>
                            </li>
                        </ul>
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
        function toggleFAQ(element) {
            element.classList.toggle('active');
            const answer = element.querySelector('.faq-answer');
            
            if (element.classList.contains('active')) {
                answer.style.display = 'block';
            } else {
                answer.style.display = 'none';
            }
        }
        
        // Initially hide all FAQ answers
        document.addEventListener('DOMContentLoaded', function() {
            document.querySelectorAll('.faq-answer').forEach(answer => {
                answer.style.display = 'none';
            });
        });
    </script>
</body>
</html>
