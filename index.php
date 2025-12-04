<?php
require_once 'config.php';
require_once 'database.php';
require_once 'functions.php';

$db = new Database();

// Get featured products for homepage
$featuredProducts = $db->query("
    SELECT p.*, c.name as category_name 
    FROM products p 
    LEFT JOIN categories c ON p.category_id = c.id 
    WHERE p.status = 'active' AND p.featured = 1 
    ORDER BY p.created_at DESC 
    LIMIT 8
")->fetchAll();

// Get categories for navigation
$categories = $db->query("
    SELECT * FROM categories 
    WHERE status = 'active' 
    ORDER BY name ASC
")->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo SITE_NAME; ?> - Digital Café Experience</title>
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <!-- Bootstrap CSS (for reliable dropdowns) -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- MDB5 CSS -->
    <link rel="stylesheet" href="MDB5-STANDARD-UI-KIT-Free-9.2.0/css/mdb.min.css">
    
    <!-- Custom CSS -->
    <style>
        :root {
            --primary-color: #3b71ca;
            --secondary-color: #9fa6b2;
            --accent-color: #ff6b6b;
            --dark-bg: #2c3e50;
            --light-bg: #ecf0f1;
        }
        
        .hero-section {
            background: linear-gradient(135deg, var(--primary-color) 0%, #667eea 100%);
            color: white;
            padding: 100px 0;
            position: relative;
            overflow: hidden;
        }
        
        .hero-section::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1440 320"><path fill="%23ffffff" fill-opacity="0.1" d="M0,96L48,112C96,128,192,160,288,160C384,160,480,128,576,122.7C672,117,768,139,864,133.3C960,128,1056,96,1152,90.7C1248,85,1344,107,1392,117.3L1440,128L1440,320L1392,320C1344,320,1248,320,1152,320C1056,320,960,320,864,320C768,320,672,320,576,320C480,320,384,320,288,320C192,320,96,320,48,320L0,320Z"></path></svg>') no-repeat bottom;
            background-size: cover;
        }
        
        .product-card {
            transition: all 0.3s ease;
            height: 100%;
            border: none;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .product-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 20px rgba(0,0,0,0.15);
        }
        
        .product-image {
            height: 200px;
            object-fit: cover;
            border-radius: 8px 8px 0 0;
        }
        
        .price-tag {
            font-size: 1.5rem;
            font-weight: bold;
            color: var(--accent-color);
        }
        
        .category-badge {
            background: var(--primary-color);
            color: white;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
        }
        
        .mobile-bottom-nav {
            display: none;
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            background: white;
            box-shadow: 0 -2px 10px rgba(0,0,0,0.1);
            z-index: 1000;
        }
        
        @media (max-width: 768px) {
            .hero-section {
                padding: 60px 0;
            }
            
            .mobile-bottom-nav {
                display: block;
            }
            
            .desktop-nav {
                display: none;
            }
            
            .product-grid {
                grid-template-columns: 1fr !important;
            }
        }
        
        .feature-icon {
            width: 60px;
            height: 60px;
            background: var(--primary-color);
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 20px;
        }
        
        .testimonial-card {
            background: white;
            border-radius: 10px;
            padding: 30px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            height: 100%;
        }
        
        .star-rating {
            color: #ffc107;
            margin-bottom: 15px;
        }
    </style>
</head>
<body>
    <!-- Desktop Navigation -->
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
                        <a class="nav-link active" href="index.php">Home</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="products.php">Products</a>
                    </li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown" role="button" aria-expanded="false">
                            Categories
                        </a>
                        <ul class="dropdown-menu">
                            <?php foreach ($categories as $category): ?>
                                <li><a class="dropdown-item" href="products.php?category=<?php echo $category['slug']; ?>">
                                    <?php echo sanitize($category['name']); ?>
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

    <!-- Hero Section -->
    <section class="hero-section">
        <div class="container text-center position-relative">
            <div class="row justify-content-center">
                <div class="col-lg-8">
                    <h1 class="display-4 fw-bold mb-4">Welcome to <?php echo SITE_NAME; ?> Café</h1>
                    <p class="lead mb-4">Serving premium products and foods that you can order online</p>
                    <div class="d-flex gap-3 justify-content-center">
                        <a href="products.php" class="btn btn-light btn-lg px-4">
                            <i class="fas fa-coffee me-2"></i> Browse Menu
                        </a>
                        <a href="#featured" class="btn btn-outline-light btn-lg px-4">
                            <i class="fas fa-star me-2"></i> Special Items
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Features Section -->
    <section class="py-5">
        <div class="container">
            <div class="row text-center">
                <div class="col-md-4 mb-4">
                    <div class="feature-icon mx-auto">
                        <i class="fas fa-mug-hot fa-2x"></i>
                    </div>
                    <h5>Instant Service</h5>
                    <p class="text-muted">Get your orders immediately , and enjoy your fresh cup of coffee</p>
                </div>
                <div class="col-md-4 mb-4">
                    <div class="feature-icon mx-auto">
                        <i class="fas fa-shield-alt fa-2x"></i>
                    </div>
                    <h5>Secure Transactions</h5>
                    <p class="text-muted">Safe payment processing with our trusted cafe payment system</p>
                </div>
                <div class="col-md-4 mb-4">
                    <div class="feature-icon mx-auto">
                        <i class="fas fa-headset fa-2x"></i>
                    </div>
                    <h5>24/7 Support</h5>
                    <p class="text-muted">Our friendly baristas are always here to help with your orders</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Featured Products Section -->
    <section id="featured" class="py-5 bg-light">
        <div class="container">
            <div class="text-center mb-5">
                <h2 class="fw-bold">Today's Specials</h2>
                <p class="text-muted">Handpicked delights from our cafe menu</p>
            </div>
            
            <div class="row product-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 20px;">
                <?php if (empty($featuredProducts)): ?>
                    <div class="col-12 text-center">
                        <p class="text-muted">No featured products available at the moment.</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($featuredProducts as $product): ?>
                        <div class="col">
                            <div class="card product-card">
                                <?php if ($product['screenshot']): ?>
                                    <img src="<?php echo sanitize($product['screenshot']); ?>" class="product-image" alt="<?php echo sanitize($product['name']); ?>">
                                <?php else: ?>
                                    <div class="product-image d-flex align-items-center justify-content-center bg-light">
                                        <i class="fas fa-image fa-3x text-muted"></i>
                                    </div>
                                <?php endif; ?>
                                
                                <div class="card-body">
                                    <?php if ($product['category_name']): ?>
                                        <span class="category-badge"><?php echo sanitize($product['category_name']); ?></span>
                                    <?php endif; ?>
                                    
                                    <h5 class="card-title mt-2"><?php echo sanitize($product['name']); ?></h5>
                                    <p class="card-text text-muted small">
                                        <?php echo substr(sanitize($product['description']), 0, 100) . '...'; ?>
                                    </p>
                                    
                                    <div class="d-flex justify-content-between align-items-center">
                                        <span class="price-tag"><?php echo formatPrice($product['price']); ?></span>
                                        <div>
                                            <a href="product.php?id=<?php echo $product['id']; ?>" class="btn btn-primary btn-sm">
                                                View Details
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            
            <div class="text-center mt-4">
                <a href="products.php" class="btn btn-outline-primary">View All Products</a>
            </div>
        </div>
    </section>

    <!-- Testimonials Section -->
    <section class="py-5">
        <div class="container">
            <div class="text-center mb-5">
                <h2 class="fw-bold">What Our Customers Say</h2>
                <p class="text-muted">Real feedback from our valued customers</p>
            </div>
            
            <div class="row">
                <div class="col-md-4 mb-4">
                    <div class="testimonial-card">
                        <div class="star-rating">
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star"></i>
                        </div>
                        <p class="mb-3">"Amazing collection of of foods and drinks! Always fresh from our chef.."</p>
                        <div class="d-flex align-items-center">
                            <div class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center me-3" style="width: 40px; height: 40px;">
                                <span>JD</span>
                            </div>
                            <div>
                                <h6 class="mb-0">John Doe</h6>
                                <small class="text-muted">Regular</small>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-4 mb-4">
                    <div class="testimonial-card">
                        <div class="star-rating">
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star"></i>
                        </div>
                        <p class="mb-3">"Great platform with easy to navigate and order my drink. Customer support is very responsive!"</p>
                        <div class="d-flex align-items-center">
                            <div class="rounded-circle bg-success text-white d-flex align-items-center justify-content-center me-3" style="width: 40px; height: 40px;">
                                <span>SM</span>
                            </div>
                            <div>
                                <h6 class="mb-0">Sarah Miller</h6>
                                <small class="text-muted">Regular</small>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-4 mb-4">
                    <div class="testimonial-card">
                        <div class="star-rating">
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star"></i>
                            <i class="far fa-star"></i>
                        </div>
                        <p class="mb-3">"Found exactly what I needed to get my day started. The payment process was secure and easy."</p>
                        <div class="d-flex align-items-center">
                            <div class="rounded-circle bg-warning text-white d-flex align-items-center justify-content-center me-3" style="width: 40px; height: 40px;">
                                <span>MJ</span>
                            </div>
                            <div>
                                <h6 class="mb-0">Mike Johnson</h6>
                                <small class="text-muted">Regular</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="bg-dark text-white py-4">
        <div class="container">
            <div class="row">
                <div class="col-md-4 mb-3">
                    <h5><i class="fas fa-coffee text-primary"></i> <?php echo SITE_NAME; ?></h5>
                    <p class="text-muted">Your trusted marketplace for premium foods and drinks.</p>
                </div>
                <div class="col-md-4 mb-3">
                    <h6>Quick Links</h6>
                    <ul class="list-unstyled">
                        <li><a href="products.php" class="text-muted text-decoration-none">Products</a></li>
                        <li><a href="contact.php" class="text-muted text-decoration-none">Contact</a></li>
                        <li><a href="faq.php" class="text-muted text-decoration-none">FAQ</a></li>
                        <li><a href="terms.php" class="text-muted text-decoration-none">Terms & Conditions</a></li>
                    </ul>
                </div>
                <div class="col-md-4 mb-3">
                    <h6>Connect With Us</h6>
                    <div class="d-flex gap-3">
                        <a href="#" class="text-white"><i class="fab fa-facebook fa-lg"></i></a>
                        <a href="#" class="text-white"><i class="fab fa-twitter fa-lg"></i></a>
                        <a href="#" class="text-white"><i class="fab fa-instagram fa-lg"></i></a>
                        <a href="#" class="text-white"><i class="fab fa-linkedin fa-lg"></i></a>
                    </div>
                </div>
            </div>
            <hr class="bg-secondary">
            <div class="text-center">
                <p class="mb-0">&copy; <?php echo date('Y'); ?> <?php echo SITE_NAME; ?>. All rights reserved.</p>
            </div>
        </div>
    </footer>

    <!-- Mobile Bottom Navigation -->
    <nav class="mobile-bottom-nav">
        <div class="container-fluid">
            <div class="d-flex justify-content-around py-2">
                <a href="index.php" class="text-decoration-none text-primary p-2">
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
        });
    </script>
</body>
</html>
