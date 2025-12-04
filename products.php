<?php
require_once 'config.php';
require_once 'database.php';
require_once 'functions.php';

$db = new Database();

// Get filters from URL
$category = sanitize($_GET['category'] ?? '');
$search = sanitize($_GET['search'] ?? '');
$price_min = floatval($_GET['price_min'] ?? 0);
$price_max = floatval($_GET['price_max'] ?? 999999);
$sort = sanitize($_GET['sort'] ?? 'latest');
$page = max(1, intval($_GET['page'] ?? 1));

// Build WHERE clause
$where = ["p.status = 'active'"];
$params = [];

// Category filter
if ($category) {
    $where[] = "c.slug = ?";
    $params[] = $category;
}

// Search filter
if ($search) {
    $where[] = "(p.name LIKE ? OR p.description LIKE ? OR p.short_description LIKE ?)";
    $searchParam = "%$search%";
    $params[] = $searchParam;
    $params[] = $searchParam;
    $params[] = $searchParam;
}

// Price filter
$where[] = "p.price BETWEEN ? AND ?";
$params[] = $price_min;
$params[] = $price_max;

$whereClause = "WHERE " . implode(" AND ", $where);

// Sorting
$orderBy = match($sort) {
    'price_low' => 'p.price ASC',
    'price_high' => 'p.price DESC',
    'name_asc' => 'p.name ASC',
    'name_desc' => 'p.name DESC',
    'popular' => 'p.download_count DESC',
    'featured' => 'p.featured DESC, p.created_at DESC',
    default => 'p.created_at DESC'
};

// Get total count for pagination
$totalQuery = "SELECT COUNT(*) as total FROM products p LEFT JOIN categories c ON p.category_id = c.id $whereClause";
$totalResult = $db->query($totalQuery, $params)->fetch();
$totalItems = $totalResult['total'];

// Pagination
$pagination = paginate($totalItems, ITEMS_PER_PAGE, $page);

// Get products
$productsQuery = "
    SELECT p.*, c.name as category_name, c.slug as category_slug 
    FROM products p 
    LEFT JOIN categories c ON p.category_id = c.id 
    $whereClause 
    ORDER BY $orderBy 
    LIMIT {$pagination['offset']}, {$pagination['items_per_page']}
";
$products = $db->query($productsQuery, $params)->fetchAll();

// Get categories for filter sidebar
$categories = $db->query("
    SELECT c.*, COUNT(p.id) as product_count 
    FROM categories c 
    LEFT JOIN products p ON c.id = p.category_id AND p.status = 'active'
    WHERE c.status = 'active'
    GROUP BY c.id 
    ORDER BY c.name ASC
")->fetchAll();

// Get price range
$priceRange = $db->query("
    SELECT MIN(price) as min_price, MAX(price) as max_price 
    FROM products 
    WHERE status = 'active'
")->fetch();

// Set active category
$activeCategory = null;
if ($category) {
    $activeCategory = $db->query("SELECT * FROM categories WHERE slug = ?", [$category])->fetch();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Café Menu - <?php echo SITE_NAME; ?></title>
    
    <!-- MDB5 CSS -->
    <link rel="stylesheet" href="MDB5-STANDARD-UI-KIT-Free-9.2.0/css/mdb.min.css">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <style>
        :root {
            --primary-color: #3b71ca;
            --secondary-color: #9fa6b2;
            --accent-color: #ff6b6b;
        }
        
        .page-header {
            background: linear-gradient(135deg, var(--primary-color) 0%, #667eea 100%);
            color: white;
            padding: 80px 0 40px;
        }
        
        .filter-sidebar {
            background: white;
            border-radius: 10px;
            padding: 25px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            position: sticky;
            top: 20px;
        }
        
        .product-card {
            transition: all 0.3s ease;
            height: 100%;
            border: none;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            border-radius: 10px;
            overflow: hidden;
        }
        
        .product-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 20px rgba(0,0,0,0.15);
        }
        
        .product-image {
            height: 200px;
            object-fit: cover;
            position: relative;
            overflow: hidden;
        }
        
        .product-badge {
            position: absolute;
            top: 10px;
            right: 10px;
            background: var(--accent-color);
            color: white;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: bold;
        }
        
        .price-tag {
            font-size: 1.5rem;
            font-weight: bold;
            color: var(--primary-color);
        }
        
        .category-badge {
            background: var(--primary-color);
            color: white;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            display: inline-block;
        }
        
        .search-bar {
            background: white;
            border-radius: 50px;
            padding: 15px 25px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
        }
        
        .filter-section {
            margin-bottom: 30px;
        }
        
        .filter-section h6 {
            font-weight: 600;
            margin-bottom: 15px;
            color: #333;
        }
        
        .price-range-slider {
            margin: 20px 0;
        }
        
        .mobile-filter-btn {
            display: none;
            position: fixed;
            bottom: 80px;
            right: 20px;
            z-index: 1000;
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: var(--primary-color);
            color: white;
            border: none;
            box-shadow: 0 5px 20px rgba(0,0,0,0.2);
        }
        
        @media (max-width: 768px) {
            .filter-sidebar {
                display: none;
                position: fixed;
                top: 0;
                left: 0;
                right: 0;
                bottom: 0;
                z-index: 1050;
                border-radius: 0;
                overflow-y: auto;
            }
            
            .filter-sidebar.show {
                display: block;
            }
            
            .mobile-filter-btn {
                display: flex;
                align-items: center;
                justify-content: center;
            }
            
            .product-grid {
                grid-template-columns: 1fr !important;
            }
            
            .page-header {
                padding: 60px 0 30px;
            }
        }
        
        .loading-skeleton {
            background: linear-gradient(90deg, #f0f0f0 25%, #e0e0e0 50%, #f0f0f0 75%);
            background-size: 200% 100%;
            animation: loading 1.5s infinite;
        }
        
        @keyframes loading {
            0% { background-position: 200% 0; }
            100% { background-position: -200% 0; }
        }
        
        .pagination .page-link {
            color: var(--primary-color);
            border: 1px solid #dee2e6;
            padding: 8px 16px;
        }
        
        .pagination .page-item.active .page-link {
            background: var(--primary-color);
            border-color: var(--primary-color);
        }
    </style>
</head>
<body>
    <!-- Navigation (same as index) -->
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
                        <a href="products.php" class="nav-link active" href="products.php">Menu</a>
                    </li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" data-mdb-toggle="dropdown">
                            Categories
                        </a>
                        <ul class="dropdown-menu">
                            <?php foreach ($categories as $cat): ?>
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
            <div class="row align-items-center">
                <div class="col-lg-6">
                    <h1 class="fw-bold mb-3">
                        <?php echo $activeCategory ? sanitize($activeCategory['name']) : 'Café Menu'; ?>
                    </h1>
                    <p class="lead mb-0">
                        <?php echo $totalItems; ?> items available
                        <?php if ($search): ?> for "<?php echo sanitize($search); ?>"<?php endif; ?>
                    </p>
                </div>
                <div class="col-lg-6">
                    <!-- Search Bar -->
                    <form method="GET" class="search-bar">
                        <div class="input-group">
                            <input type="text" class="form-control border-0" name="search" 
                                   value="<?php echo $search; ?>" placeholder="Search menu items...">
                            <button class="btn btn-link" type="submit">
                                <i class="fas fa-search text-primary"></i>
                            </button>
                        </div>
                        <?php if ($category): ?>
                            <input type="hidden" name="category" value="<?php echo $category; ?>">
                        <?php endif; ?>
                    </form>
                </div>
            </div>
        </div>
    </section>

    <!-- Products Section -->
    <section class="py-5">
        <div class="container">
            <div class="row">
                <!-- Filter Sidebar -->
                <div class="col-lg-3">
                    <div class="filter-sidebar" id="filterSidebar">
                        <div class="d-flex justify-content-between align-items-center mb-4">
                            <h5 class="fw-bold mb-0">Filters</h5>
                            <button class="btn btn-sm btn-outline-secondary d-lg-none" id="closeFilters">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                        
                        <form method="GET" id="filterForm">
                            <?php if ($search): ?>
                                <input type="hidden" name="search" value="<?php echo $search; ?>">
                            <?php endif; ?>
                            
                            <!-- Categories -->
                            <div class="filter-section">
                                <h6>Categories</h6>
                                <div class="list-group list-group-flush">
                                    <a href="products.php" class="list-group-item list-group-item-action border-0 <?php echo !$category ? 'active' : ''; ?>">
                                        All Categories
                                        <span class="badge bg-primary float-end"><?php echo array_sum(array_column($categories, 'product_count')); ?></span>
                                    </a>
                                    <?php foreach ($categories as $cat): ?>
                                        <a href="products.php?category=<?php echo $cat['slug']; ?><?php echo $search ? '&search=' . urlencode($search) : ''; ?>" 
                                           class="list-group-item list-group-item-action border-0 <?php echo $category === $cat['slug'] ? 'active' : ''; ?>">
                                            <?php echo sanitize($cat['name']); ?>
                                            <span class="badge bg-secondary float-end"><?php echo $cat['product_count']; ?></span>
                                        </a>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            
                            <!-- Price Range -->
                            <div class="filter-section">
                                <h6>Price Range</h6>
                                <div class="price-range-slider">
                                    <div class="d-flex justify-content-between mb-2">
                                        <span>Min: <?php echo formatPrice($price_min); ?></span>
                                        <span>Max: <?php echo formatPrice($price_max); ?></span>
                                    </div>
                                    <input type="range" class="form-range" id="priceMin" name="price_min" 
                                           min="<?php echo $priceRange['min_price']; ?>" 
                                           max="<?php echo $priceRange['max_price']; ?>" 
                                           value="<?php echo $price_min; ?>" step="1">
                                    <input type="range" class="form-range" id="priceMax" name="price_max" 
                                           min="<?php echo $priceRange['min_price']; ?>" 
                                           max="<?php echo $priceRange['max_price']; ?>" 
                                           value="<?php echo $price_max; ?>" step="1">
                                </div>
                            </div>
                            
                            <!-- Sort By -->
                            <div class="filter-section">
                                <h6>Sort By</h6>
                                <select class="form-select" name="sort" id="sortSelect">
                                    <option value="latest" <?php echo $sort === 'latest' ? 'selected' : ''; ?>>Latest First</option>
                                    <option value="price_low" <?php echo $sort === 'price_low' ? 'selected' : ''; ?>>Price: Low to High</option>
                                    <option value="price_high" <?php echo $sort === 'price_high' ? 'selected' : ''; ?>>Price: High to Low</option>
                                    <option value="name_asc" <?php echo $sort === 'name_asc' ? 'selected' : ''; ?>>Name: A to Z</option>
                                    <option value="name_desc" <?php echo $sort === 'name_desc' ? 'selected' : ''; ?>>Name: Z to A</option>
                                    <option value="popular" <?php echo $sort === 'popular' ? 'selected' : ''; ?>>Most Popular</option>
                                    <option value="featured" <?php echo $sort === 'featured' ? 'selected' : ''; ?>>Featured First</option>
                                </select>
                            </div>
                            
                            <button type="submit" class="btn btn-primary w-100">Apply Filters</button>
                            <a href="products.php" class="btn btn-outline-secondary w-100 mt-2">Clear All</a>
                        </form>
                    </div>
                </div>
                
                <!-- Products Grid -->
                <div class="col-lg-9">
                    <!-- Mobile Filter Button -->
                    <button class="mobile-filter-btn" id="mobileFilterBtn">
                        <i class="fas fa-filter"></i>
                    </button>
                    
                    <?php if (empty($products)): ?>
                        <div class="text-center py-5">
                            <i class="fas fa-search fa-4x text-muted mb-3"></i>
                            <h4>No menu items found</h4>
                            <p class="text-muted">Try adjusting your filters or search terms</p>
                            <a href="products.php" class="btn btn-primary">Browse Full Menu</a>
                        </div>
                    <?php else: ?>
                        <div class="product-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 25px;">
                            <?php foreach ($products as $product): ?>
                                <div class="product-card">
                                    <div class="product-image">
                                        <?php if ($product['screenshot']): ?>
                                            <img src="<?php echo sanitize($product['screenshot']); ?>" 
                                                 class="w-100 h-100" style="object-fit: cover;" 
                                                 alt="<?php echo sanitize($product['name']); ?>"
                                                 onerror="this.src='assets/images/placeholder.jpg'; this.onerror='null';"
                                                 loading="lazy">
                                        <?php else: ?>
                                            <div class="w-100 h-100 d-flex align-items-center justify-content-center bg-light">
                                                <i class="fas fa-image fa-3x text-muted"></i>
                                            </div>
                                        <?php endif; ?>
                                        
                                        <?php if ($product['featured']): ?>
                                            <span class="product-badge">Featured</span>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <div class="card-body">
                                        <?php if ($product['category_name']): ?>
                                            <span class="category-badge"><?php echo sanitize($product['category_name']); ?></span>
                                        <?php endif; ?>
                                        
                                        <h5 class="card-title mt-2"><?php echo sanitize($product['name']); ?></h5>
                                        <p class="card-text text-muted small">
                                            <?php echo substr(sanitize($product['description']), 0, 80) . '...'; ?>
                                        </p>
                                        
                                        <div class="d-flex justify-content-between align-items-center">
                                            <span class="price-tag"><?php echo formatPrice($product['price']); ?></span>
                                            <div>
                                                <button class="btn btn-primary btn-sm add-to-cart" 
                                                        data-product-id="<?php echo $product['id']; ?>"
                                                        data-product-name="<?php echo sanitize($product['name']); ?>"
                                                        data-product-price="<?php echo $product['price']; ?>">
                                                    <i class="fas fa-cart-plus me-1"></i> Add to Cart
                                                </button>
                                            </div>
                                        </div>
                                        
                                        <div class="mt-2">
                                            <a href="product.php?id=<?php echo $product['id']; ?>" class="btn btn-outline-primary btn-sm w-100">
                                                View Details
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        
                        <!-- Pagination -->
                        <?php if ($pagination['total_pages'] > 1): ?>
                            <nav aria-label="Products pagination" class="mt-5">
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
                <a href="products.php" class="text-decoration-none text-primary p-2">
                    <i class="fas fa-th-large fa-lg"></i>
                    <div class="small">Menu</div>
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
        document.addEventListener('DOMContentLoaded', function() {
            // Mobile filter toggle
            const mobileFilterBtn = document.getElementById('mobileFilterBtn');
            const filterSidebar = document.getElementById('filterSidebar');
            const closeFilters = document.getElementById('closeFilters');
            
            if (mobileFilterBtn) {
                mobileFilterBtn.addEventListener('click', function() {
                    filterSidebar.classList.add('show');
                });
            }
            
            if (closeFilters) {
                closeFilters.addEventListener('click', function() {
                    filterSidebar.classList.remove('show');
                });
            }
            
            // Price range sync
            const priceMin = document.getElementById('priceMin');
            const priceMax = document.getElementById('priceMax');
            
            function updatePriceRange() {
                const minVal = parseFloat(priceMin.value);
                const maxVal = parseFloat(priceMax.value);
                
                if (minVal > maxVal) {
                    priceMin.value = maxVal;
                }
                if (maxVal < minVal) {
                    priceMax.value = minVal;
                }
                
                // Update display
                const display = priceMin.parentElement.querySelector('.d-flex span');
                if (display) {
                    display.textContent = `Min: $${minVal.toFixed(2)} - Max: $${maxVal.toFixed(2)}`;
                }
            }
            
            if (priceMin && priceMax) {
                priceMin.addEventListener('input', updatePriceRange);
                priceMax.addEventListener('input', updatePriceRange);
            }
            
            // Sort change
            const sortSelect = document.getElementById('sortSelect');
            if (sortSelect) {
                sortSelect.addEventListener('change', function() {
                    document.getElementById('filterForm').submit();
                });
            }
            
            // Add to cart functionality
            const addToCartButtons = document.querySelectorAll('.add-to-cart');
            addToCartButtons.forEach(button => {
                button.addEventListener('click', function(e) {
                    e.preventDefault();
                    
                    const productId = this.dataset.productId;
                    const productName = this.dataset.productName;
                    const productPrice = this.dataset.productPrice;
                    
                    // Check if user is logged in
                    <?php if (!isLoggedIn()): ?>
                        // Redirect to login
                        window.location.href = 'login.php?redirect=' + encodeURIComponent(window.location.href);
                        return;
                    <?php endif; ?>
                    
                    // Send AJAX request to add to cart
                    fetch('cart-handler.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: 'action=add&product_id=' + productId + '&quantity=1'
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            // Update cart count
                            const cartBadges = document.querySelectorAll('.badge');
                            cartBadges.forEach(badge => {
                                if (badge.textContent.match(/^\d+$/)) {
                                    badge.textContent = data.cart_count;
                                }
                            });
                            
                            // Show success message
                            showToast(productName + ' added to cart!', 'success');
                        } else {
                            showToast(data.message || 'Error adding to cart', 'error');
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        showToast('Error adding to cart', 'error');
                    });
                });
            });
            
            // Toast notification function
            function showToast(message, type = 'info') {
                const toast = document.createElement('div');
                toast.className = `alert alert-${type === 'success' ? 'success' : 'danger'} position-fixed top-0 start-50 translate-middle-x mt-3`;
                toast.style.zIndex = '9999';
                toast.style.minWidth = '300px';
                toast.innerHTML = `
                    <div class="d-flex align-items-center">
                        <i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-triangle'} me-2"></i>
                        ${message}
                    </div>
                `;
                
                document.body.appendChild(toast);
                
                setTimeout(() => {
                    toast.remove();
                }, 3000);
            }
        });
        
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
