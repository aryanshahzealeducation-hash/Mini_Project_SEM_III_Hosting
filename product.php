<?php
require_once 'config.php';
require_once 'database.php';
require_once 'functions.php';

$db = new Database();

// Get product ID
$productId = intval($_GET['id'] ?? 0);

if ($productId <= 0) {
    redirect('products.php');
}

// Get product details
$product = $db->query("
    SELECT p.*, c.name as category_name, c.slug as category_slug 
    FROM products p 
    LEFT JOIN categories c ON p.category_id = c.id 
    WHERE p.id = ? AND p.status = 'active'
", [$productId])->fetch();

if (!$product) {
    redirect('products.php');
}

// Get product images
$productImages = $db->query("
    SELECT * FROM product_images 
    WHERE product_id = ? 
    ORDER BY sort_order ASC
", [$productId])->fetchAll();

// Get related products
$relatedProducts = $db->query("
    SELECT p.*, c.name as category_name 
    FROM products p 
    LEFT JOIN categories c ON p.category_id = c.id 
    WHERE p.id != ? AND p.status = 'active' 
    AND (p.category_id = ? OR p.featured = 1)
    ORDER BY p.featured DESC, RAND() 
    LIMIT 6
", [$productId, $product['category_id']])->fetchAll();

// Increment download count (for statistics)
$db->query("UPDATE products SET download_count = download_count + 1 WHERE id = ?", [$productId]);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo sanitize($product['name']); ?> - <?php echo SITE_NAME; ?></title>
    
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
        
        .product-hero {
            background: linear-gradient(135deg, var(--primary-color) 0%, #667eea 100%);
            color: white;
            padding: 60px 0 30px;
        }
        
        .product-image-main {
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
            height: 400px;
            background: #f8f9fa;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .product-image-main img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .product-image-thumbnail {
            border-radius: 10px;
            overflow: hidden;
            cursor: pointer;
            transition: all 0.3s ease;
            border: 2px solid transparent;
            height: 80px;
        }
        
        .product-image-thumbnail:hover,
        .product-image-thumbnail.active {
            border-color: var(--primary-color);
            transform: scale(1.05);
        }
        
        .product-image-thumbnail img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .price-tag {
            font-size: 2.5rem;
            font-weight: bold;
            color: var(--accent-color);
        }
        
        .category-badge {
            background: var(--primary-color);
            color: white;
            padding: 6px 16px;
            border-radius: 25px;
            font-size: 0.9rem;
            display: inline-block;
        }
        
        .feature-list {
            list-style: none;
            padding: 0;
        }
        
        .feature-list li {
            padding: 10px 0;
            border-bottom: 1px solid #f0f0f0;
        }
        
        .feature-list li:last-child {
            border-bottom: none;
        }
        
        .feature-list i {
            color: var(--primary-color);
            margin-right: 10px;
        }
        
        .related-product-card {
            transition: all 0.3s ease;
            height: 100%;
            border: none;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            border-radius: 10px;
            overflow: hidden;
        }
        
        .related-product-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 20px rgba(0,0,0,0.15);
        }
        
        .related-product-image {
            height: 150px;
            object-fit: cover;
        }
        
        .sticky-purchase {
            position: sticky;
            top: 20px;
        }
        
        .quantity-input {
            width: 80px;
            text-align: center;
        }
        
        @media (max-width: 768px) {
            .product-hero {
                padding: 40px 0 20px;
            }
            
            .product-image-main {
                height: 250px;
            }
            
            .price-tag {
                font-size: 2rem;
            }
            
            .sticky-purchase {
                position: relative;
                top: 0;
                margin-top: 20px;
            }
        }
        
        .breadcrumb {
            background: transparent;
            padding: 0;
            margin-bottom: 20px;
        }
        
        .breadcrumb-item + .breadcrumb-item::before {
            content: ">";
            color: rgba(255,255,255,0.7);
        }
        
        .zoom-container {
            position: relative;
            overflow: hidden;
        }
        
        .zoom-container:hover .zoom-lens {
            display: block;
        }
        
        .zoom-lens {
            position: absolute;
            border: 2px solid var(--primary-color);
            border-radius: 50%;
            width: 100px;
            height: 100px;
            display: none;
            pointer-events: none;
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

    <!-- Product Hero Section -->
    <section class="product-hero">
        <div class="container">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="index.php" class="text-white text-decoration-none">Home</a></li>
                    <li class="breadcrumb-item"><a href="products.php" class="text-white text-decoration-none">Products</a></li>
                    <?php if ($product['category_name']): ?>
                        <li class="breadcrumb-item">
                            <a href="products.php?category=<?php echo $product['category_slug']; ?>" class="text-white text-decoration-none">
                                <?php echo sanitize($product['category_name']); ?>
                            </a>
                        </li>
                    <?php endif; ?>
                    <li class="breadcrumb-item text-white active"><?php echo sanitize($product['name']); ?></li>
                </ol>
            </nav>
        </div>
    </section>

    <!-- Product Details Section -->
    <section class="py-5">
        <div class="container">
            <div class="row">
                <!-- Product Images -->
                <div class="col-lg-6 mb-4">
                    <div class="product-image-main zoom-container" id="mainImageContainer">
                        <?php if ($product['screenshot'] || !empty($productImages)): ?>
                            <img src="<?php echo $product['screenshot'] ?: $productImages[0]['image_path']; ?>" 
                                 id="mainImage" alt="<?php echo sanitize($product['name']); ?>">
                        <?php else: ?>
                            <div class="text-center">
                                <i class="fas fa-image fa-5x text-muted"></i>
                            </div>
                        <?php endif; ?>
                        <div class="zoom-lens" id="zoomLens"></div>
                    </div>
                    
                    <!-- Thumbnail Gallery -->
                    <?php if (!empty($productImages) || $product['screenshot']): ?>
                        <div class="row mt-3 g-2" id="thumbnailGallery">
                            <?php if ($product['screenshot']): ?>
                                <div class="col-3">
                                    <div class="product-image-thumbnail active" onclick="changeImage('<?php echo $product['screenshot']; ?>', this)">
                                        <img src="<?php echo $product['screenshot']; ?>" alt="Main image">
                                    </div>
                                </div>
                            <?php endif; ?>
                            
                            <?php foreach ($productImages as $image): ?>
                                <div class="col-3">
                                    <div class="product-image-thumbnail" onclick="changeImage('<?php echo $image['image_path']; ?>', this)">
                                        <img src="<?php echo $image['image_path']; ?>" alt="<?php echo sanitize($image['alt_text'] ?? 'Product image'); ?>">
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
                
                <!-- Product Info -->
                <div class="col-lg-6">
                    <div class="sticky-purchase">
                        <?php if ($product['category_name']): ?>
                            <span class="category-badge mb-3"><?php echo sanitize($product['category_name']); ?></span>
                        <?php endif; ?>
                        
                        <h1 class="fw-bold mb-3"><?php echo sanitize($product['name']); ?></h1>
                        
                        <div class="d-flex align-items-center mb-3">
                            <div class="price-tag me-3"><?php echo formatPrice($product['price']); ?></div>
                            <?php if ($product['featured']): ?>
                                <span class="badge bg-warning text-dark">Featured</span>
                            <?php endif; ?>
                        </div>
                        
                        <?php if ($product['short_description']): ?>
                            <p class="lead text-muted mb-4"><?php echo sanitize($product['short_description']); ?></p>
                        <?php endif; ?>
                        
                        <div class="mb-4">
                            <h5>Description</h5>
                            <div class="text-muted">
                                <?php echo nl2br(sanitize($product['description'])); ?>
                            </div>
                        </div>
                        
                        <!-- Product Features -->
                        <div class="mb-4">
                            <h5>Product Features</h5>
                            <ul class="feature-list">
                                <li><i class="fas fa-check-circle"></i> Instant digital download</li>
                                <li><i class="fas fa-check-circle"></i> Lifetime access</li>
                                <li><i class="fas fa-check-circle"></i> Free updates</li>
                                <li><i class="fas fa-check-circle"></i> Customer support</li>
                                <?php if ($product['file_size']): ?>
                                    <li><i class="fas fa-file"></i> File size: <?php echo number_format($product['file_size'] / 1024 / 1024, 2); ?> MB</li>
                                <?php endif; ?>
                            </ul>
                        </div>
                        
                        <!-- Purchase Options -->
                        <div class="border-top pt-4">
                            <div class="d-flex align-items-center mb-3">
                                <label class="me-3">Quantity:</label>
                                <div class="input-group" style="width: 150px;">
                                    <button class="btn btn-outline-secondary" type="button" onclick="changeQuantity(-1)">
                                        <i class="fas fa-minus"></i>
                                    </button>
                                    <input type="number" class="form-control quantity-input" id="quantity" value="1" min="1" max="10">
                                    <button class="btn btn-outline-secondary" type="button" onclick="changeQuantity(1)">
                                        <i class="fas fa-plus"></i>
                                    </button>
                                </div>
                            </div>
                            
                            <div class="d-grid gap-2">
                                <button class="btn btn-primary btn-lg" id="addToCartBtn" onclick="addToCart()">
                                    <i class="fas fa-cart-plus me-2"></i> Add to Cart
                                </button>
                                
                                <button class="btn btn-success btn-lg" onclick="buyNow()">
                                    <i class="fas fa-bolt me-2"></i> Buy Now
                                </button>
                            </div>
                            
                            <div class="text-center mt-3">
                                <small class="text-muted">
                                    <i class="fas fa-shield-alt me-1"></i> Secure payment • Instant download
                                </small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Related Products Section -->
    <?php if (!empty($relatedProducts)): ?>
        <section class="py-5 bg-light">
            <div class="container">
                <div class="text-center mb-5">
                    <h3 class="fw-bold">Related Products</h3>
                    <p class="text-muted">You might also like these products</p>
                </div>
                
                <div class="row g-4">
                    <?php foreach ($relatedProducts as $related): ?>
                        <div class="col-md-6 col-lg-4">
                            <div class="card related-product-card">
                                <div class="related-product-image">
                                    <?php if ($related['screenshot']): ?>
                                        <img src="<?php echo sanitize($related['screenshot']); ?>" 
                                             class="w-100 h-100" style="object-fit: cover;" 
                                             alt="<?php echo sanitize($related['name']); ?>">
                                    <?php else: ?>
                                        <div class="w-100 h-100 d-flex align-items-center justify-content-center bg-light">
                                            <i class="fas fa-image fa-3x text-muted"></i>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="card-body">
                                    <?php if ($related['category_name']): ?>
                                        <span class="category-badge"><?php echo sanitize($related['category_name']); ?></span>
                                    <?php endif; ?>
                                    
                                    <h5 class="card-title mt-2"><?php echo sanitize($related['name']); ?></h5>
                                    <p class="card-text text-muted small">
                                        <?php echo substr(sanitize($related['description']), 0, 60) . '...'; ?>
                                    </p>
                                    
                                    <div class="d-flex justify-content-between align-items-center">
                                        <span class="price-tag"><?php echo formatPrice($related['price']); ?></span>
                                        <a href="product.php?id=<?php echo $related['id']; ?>" class="btn btn-primary btn-sm">
                                            View Details
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </section>
    <?php endif; ?>

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
        const productId = <?php echo $productId; ?>;
        const productName = '<?php echo addslashes($product['name']); ?>';
        const productPrice = <?php echo $product['price']; ?>;
        
        function changeImage(imageSrc, thumbnail) {
            document.getElementById('mainImage').src = imageSrc;
            
            // Update active thumbnail
            document.querySelectorAll('.product-image-thumbnail').forEach(thumb => {
                thumb.classList.remove('active');
            });
            thumbnail.classList.add('active');
        }
        
        function changeQuantity(delta) {
            const quantityInput = document.getElementById('quantity');
            let newValue = parseInt(quantityInput.value) + delta;
            
            if (newValue >= 1 && newValue <= 10) {
                quantityInput.value = newValue;
            }
        }
        
        function addToCart() {
            <?php if (!isLoggedIn()): ?>
                window.location.href = 'login.php?redirect=' + encodeURIComponent(window.location.href);
                return;
            <?php endif; ?>
            
            const quantity = parseInt(document.getElementById('quantity').value);
            const btn = document.getElementById('addToCartBtn');
            
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i> Adding...';
            
            fetch('cart-handler.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=add&product_id=${productId}&quantity=${quantity}`
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
                    
                    showToast(`${quantity} × ${productName} added to cart!`, 'success');
                } else {
                    showToast(data.message || 'Error adding to cart', 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showToast('Error adding to cart', 'error');
            })
            .finally(() => {
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-cart-plus me-2"></i> Add to Cart';
            });
        }
        
        function buyNow() {
            // Add to cart first, then redirect to checkout
            <?php if (!isLoggedIn()): ?>
                window.location.href = 'login.php?redirect=' + encodeURIComponent(window.location.href);
                return;
            <?php endif; ?>
            
            const quantity = parseInt(document.getElementById('quantity').value);
            
            fetch('cart-handler.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=add&product_id=${productId}&quantity=${quantity}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    window.location.href = 'checkout.php';
                } else {
                    showToast(data.message || 'Error processing purchase', 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showToast('Error processing purchase', 'error');
            });
        }
        
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
        
        // Image zoom functionality
        document.addEventListener('DOMContentLoaded', function() {
            const mainImageContainer = document.getElementById('mainImageContainer');
            const mainImage = document.getElementById('mainImage');
            const zoomLens = document.getElementById('zoomLens');
            
            if (mainImageContainer && mainImage && zoomLens) {
                mainImageContainer.addEventListener('mousemove', function(e) {
                    const rect = mainImageContainer.getBoundingClientRect();
                    const x = e.clientX - rect.left;
                    const y = e.clientY - rect.top;
                    
                    // Position lens
                    zoomLens.style.left = (x - 50) + 'px';
                    zoomLens.style.top = (y - 50) + 'px';
                    zoomLens.style.display = 'block';
                    
                    // You can implement actual zoom functionality here
                    // This would require a larger version of the image
                });
                
                mainImageContainer.addEventListener('mouseleave', function() {
                    zoomLens.style.display = 'none';
                });
            }
        });
    </script>
</body>
</html>
