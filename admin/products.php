<?php
require_once '../config.php';
require_once '../database.php';
require_once '../functions.php';

// Check if user is admin
if (!isAdmin()) {
    redirect('../login.php');
}

$db = new Database();

// Handle CRUD operations
$action = sanitize($_POST['action'] ?? '');
$message = sanitize($_GET['message'] ?? '');
$messageType = sanitize($_GET['type'] ?? '');

switch ($action) {
    case 'add':
        $name = sanitize($_POST['name']);
        $slug = slugify($name);
        $description = sanitize($_POST['description']);
        $shortDescription = sanitize($_POST['short_description']);
        $price = floatval($_POST['price']);
        $categoryId = intval($_POST['category_id']);
        $featured = isset($_POST['featured']) ? 1 : 0;
        $status = sanitize($_POST['status']);
        
        // Handle screenshot upload
        $screenshot = null;
        if (isset($_FILES['screenshot']) && $_FILES['screenshot']['error'] === UPLOAD_ERR_OK) {
            try {
                $screenshot = uploadFile($_FILES['screenshot'], '../uploads/screenshots/');
                // Remove ../ prefix for database storage
                $screenshot = str_replace('../', '', $screenshot);
            } catch (Exception $e) {
                $message = 'Screenshot upload failed: ' . $e->getMessage();
                $messageType = 'danger';
            }
        }
        
        if (!$message) {
            $db->query("
                INSERT INTO products (name, slug, description, short_description, price, category_id, screenshot, featured, status) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ", [$name, $slug, $description, $shortDescription, $price, $categoryId, $screenshot, $featured, $status]);
            
            $message = 'Menu item added successfully!';
            $messageType = 'success';
            
            // Redirect to clear form and show updated list
            header('Location: products.php?message=' . urlencode($message) . '&type=' . $messageType);
            exit();
        }
        break;
        
    case 'edit':
        $productId = intval($_POST['product_id']);
        $name = sanitize($_POST['name']);
        $slug = slugify($name);
        $description = sanitize($_POST['description']);
        $shortDescription = sanitize($_POST['short_description']);
        $price = floatval($_POST['price']);
        $categoryId = intval($_POST['category_id']);
        $featured = isset($_POST['featured']) ? 1 : 0;
        $status = sanitize($_POST['status']);
        
        // Handle screenshot upload
        if (isset($_FILES['screenshot']) && $_FILES['screenshot']['error'] === UPLOAD_ERR_OK) {
            try {
                $screenshot = uploadFile($_FILES['screenshot'], '../uploads/screenshots/');
                // Remove ../ prefix for database storage
                $screenshot = str_replace('../', '', $screenshot);
                $db->query("UPDATE products SET screenshot = ? WHERE id = ?", [$screenshot, $productId]);
            } catch (Exception $e) {
                $message = 'Screenshot upload failed: ' . $e->getMessage();
                $messageType = 'danger';
            }
        }
        
        if (!$message) {
            $db->query("
                UPDATE products 
                SET name = ?, slug = ?, description = ?, short_description = ?, price = ?, category_id = ?, featured = ?, status = ?
                WHERE id = ?
            ", [$name, $slug, $description, $shortDescription, $price, $categoryId, $featured, $status, $productId]);
            
            $message = 'Menu item updated successfully!';
            $messageType = 'success';
            
            // Redirect to clear the edit parameter and show updated list
            header('Location: products.php?message=' . urlencode($message) . '&type=' . $messageType);
            exit();
        }
        break;
        
    case 'delete':
        $productId = intval($_POST['product_id']);
        
        // Get product info to delete files
        $product = $db->query("SELECT screenshot FROM products WHERE id = ?", [$productId])->fetch();
        
        // Delete from database
        $db->query("DELETE FROM products WHERE id = ?", [$productId]);
        
        // Delete files
        if ($product['screenshot'] && file_exists('../' . $product['screenshot'])) {
            unlink('../' . $product['screenshot']);
        }
        
        $message = 'Menu item deleted successfully!';
        $messageType = 'success';
        
        // Redirect to show updated list
        header('Location: products.php?message=' . urlencode($message) . '&type=' . $messageType);
        exit();
        break;
}

// Get products with pagination
$page = max(1, intval($_GET['page'] ?? 1));
$search = sanitize($_GET['search'] ?? '');
$category = intval($_GET['category'] ?? 0);
$status = sanitize($_GET['status'] ?? '');

$where = ["1=1"];
$params = [];

if ($search) {
    $where[] = "(p.name LIKE ? OR p.description LIKE ?)";
    $searchParam = "%$search%";
    $params[] = $searchParam;
    $params[] = $searchParam;
}

if ($category > 0) {
    $where[] = "p.category_id = ?";
    $params[] = $category;
}

if ($status) {
    $where[] = "p.status = ?";
    $params[] = $status;
}

$whereClause = "WHERE " . implode(" AND ", $where);

$totalQuery = "SELECT COUNT(*) as total FROM products p $whereClause";
$totalResult = $db->query($totalQuery, $params)->fetch();
$totalItems = $totalResult['total'];

$pagination = paginate($totalItems, 10, $page);

$products = $db->query("
    SELECT p.*, c.name as category_name 
    FROM products p 
    LEFT JOIN categories c ON p.category_id = c.id 
    $whereClause 
    ORDER BY p.created_at DESC 
    LIMIT {$pagination['offset']}, {$pagination['items_per_page']}
", $params)->fetchAll();

// Get categories for dropdown
$categories = $db->query("SELECT * FROM categories WHERE status = 'active' ORDER BY name ASC")->fetchAll();

// Get product for editing
$editingProduct = null;
if (isset($_GET['edit'])) {
    $editingProduct = $db->query("SELECT * FROM products WHERE id = ?", [intval($_GET['edit'])])->fetch();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Café Menu Management - <?php echo SITE_NAME; ?></title>
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <!-- Bootstrap CSS (for reliable modals and dropdowns) -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- MDB5 CSS -->
    <link rel="stylesheet" href="../MDB5-STANDARD-UI-KIT-Free-9.2.0/css/mdb.min.css">
    
    <style>
        :root {
            --primary-color: #3b71ca;
            --secondary-color: #9fa6b2;
        }
        
        body {
            background: #f8f9fa;
        }
        
        .sidebar {
            background: linear-gradient(135deg, var(--primary-color) 0%, #667eea 100%);
            min-height: 100vh;
            color: white;
            position: fixed;
            left: 0;
            top: 0;
            width: 250px;
            z-index: 1000;
        }
        
        .sidebar .nav-link {
            color: rgba(255,255,255,0.8);
            padding: 12px 20px;
            transition: all 0.3s ease;
        }
        
        .sidebar .nav-link:hover,
        .sidebar .nav-link.active {
            color: white;
            background: rgba(255,255,255,0.1);
            border-radius: 8px;
        }
        
        .sidebar .nav-link i {
            width: 20px;
            margin-right: 10px;
        }
        
        .main-content {
            margin-left: 250px;
            padding: 20px;
        }
        
        .table-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
        }
        
        .form-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            margin-bottom: 20px;
        }
        
        .mobile-menu-toggle {
            display: none;
            position: fixed;
            top: 20px;
            left: 20px;
            z-index: 1001;
            background: var(--primary-color);
            color: white;
            border: none;
            border-radius: 8px;
            padding: 10px 15px;
        }
        
        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
                transition: transform 0.3s ease;
            }
            
            .sidebar.show {
                transform: translateX(0);
            }
            
            .main-content {
                margin-left: 0;
                padding: 60px 10px 10px;
            }
            
            .mobile-menu-toggle {
                display: block;
            }
        }
        
        .badge-status {
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 500;
        }
        
        .badge-active { background: rgba(20, 164, 77, 0.1); color: #14a44d; }
        .badge-inactive { background: rgba(220, 76, 100, 0.1); color: #dc4c64; }
        
        .product-thumbnail {
            width: 60px;
            height: 60px;
            object-fit: cover;
            border-radius: 8px;
        }
        
        .action-buttons .btn {
            padding: 4px 8px;
            font-size: 0.8rem;
        }
    </style>
</head>
<body>
    <!-- Mobile Menu Toggle -->
    <button class="mobile-menu-toggle" id="mobileMenuToggle">
        <i class="fas fa-bars"></i>
    </button>

    <!-- Sidebar -->
    <nav class="sidebar" id="sidebar">
        <div class="p-4">
            <h4 class="fw-bold mb-4">
                <i class="fas fa-coffee me-2"></i> CafeNIX Admin
            </h4>
            
            <ul class="nav flex-column">
                <li class="nav-item">
                    <a class="nav-link" href="index.php">
                        <i class="fas fa-tachometer-alt"></i> Dashboard
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link active" href="products.php">
                        <i class="fas fa-coffee"></i> Menu Items
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="orders.php">
                        <i class="fas fa-shopping-cart"></i> Orders
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="users.php">
                        <i class="fas fa-users"></i> Users
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="categories.php">
                        <i class="fas fa-tags"></i> Categories
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="coupons.php">
                        <i class="fas fa-ticket-alt"></i> Coupons
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="support.php">
                        <i class="fas fa-headset"></i> Support
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="settings.php">
                        <i class="fas fa-cog"></i> Settings
                    </a>
                </li>
                <li class="nav-item mt-4">
                    <a class="nav-link" href="../index.php" target="_blank">
                        <i class="fas fa-external-link-alt"></i> View Site
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="../logout.php">
                        <i class="fas fa-sign-out-alt"></i> Logout
                    </a>
                </li>
            </ul>
        </div>
    </nav>

    <!-- Main Content -->
    <main class="main-content">
        <!-- Alert Message -->
        <?php if ($message): ?>
            <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show" role="alert">
                <?php echo $message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Filters -->
        <div class="form-card">
            <form method="GET" class="row g-3">
                <div class="col-md-4">
                    <input type="text" class="form-control" name="search" placeholder="Search menu items..." value="<?php echo $search; ?>">
                </div>
                <div class="col-md-3">
                    <select class="form-select" name="category">
                        <option value="">All Categories</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?php echo $cat['id']; ?>" <?php echo $category == $cat['id'] ? 'selected' : ''; ?>>
                                <?php echo sanitize($cat['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <select class="form-select" name="status">
                        <option value="">All Status</option>
                        <option value="active" <?php echo $status === 'active' ? 'selected' : ''; ?>>Active</option>
                        <option value="inactive" <?php echo $status === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-outline-primary w-100">Filter</button>
                </div>
            </form>
        </div>

        <!-- Add Product Button -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h5 class="fw-bold mb-0">Menu Items (<?php echo count($products); ?>)</h5>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#productModal">
                <i class="fas fa-plus me-2"></i> Add Menu Item
            </button>
        </div>

        <!-- Products Table -->
        <div class="table-card">
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Image</th>
                            <th>Product</th>
                            <th>Category</th>
                            <th>Price</th>
                            <th>Status</th>
                            <th>Featured</th>
                            <th>Downloads</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($products)): ?>
                            <tr>
                                <td colspan="8" class="text-center text-muted">No menu items found</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($products as $product): ?>
                                <tr>
                                    <td>
                                        <?php if ($product['screenshot']): ?>
                                            <img src="../<?php echo $product['screenshot']; ?>" class="product-thumbnail" alt="<?php echo sanitize($product['name']); ?>">
                                        <?php else: ?>
                                            <div class="product-thumbnail d-flex align-items-center justify-content-center bg-light">
                                                <i class="fas fa-image text-muted"></i>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div>
                                            <div class="fw-bold"><?php echo sanitize($product['name']); ?></div>
                                            <small class="text-muted"><?php echo sanitize(substr($product['description'], 0, 50)) . '...'; ?></small>
                                        </div>
                                    </td>
                                    <td><?php echo sanitize($product['category_name'] ?? 'Uncategorized'); ?></td>
                                    <td class="fw-bold"><?php echo formatPrice($product['price']); ?></td>
                                    <td>
                                        <span class="badge-status badge-<?php echo $product['status']; ?>">
                                            <?php echo ucfirst($product['status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($product['featured']): ?>
                                            <i class="fas fa-star text-warning"></i>
                                        <?php else: ?>
                                            <i class="far fa-star text-muted"></i>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo number_format($product['download_count']); ?></td>
                                    <td>
                                        <div class="action-buttons">
                                            <button class="btn btn-sm btn-outline-primary" onclick="editProduct(<?php echo $product['id']; ?>)">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button class="btn btn-sm btn-outline-danger" onclick="deleteProduct(<?php echo $product['id']; ?>)">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Pagination -->
            <?php if ($pagination['total_pages'] > 1): ?>
                <nav aria-label="Menu Items pagination" class="mt-4">
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
        </div>
    </main>

    <!-- Product Modal -->
    <div class="modal fade" id="productModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalTitle"><?php echo $editingProduct ? 'Edit Menu Item' : 'Add New Menu Item'; ?></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" enctype="multipart/form-data">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="<?php echo $editingProduct ? 'edit' : 'add'; ?>">
                        <?php if ($editingProduct): ?>
                            <input type="hidden" name="product_id" value="<?php echo $editingProduct['id']; ?>">
                        <?php endif; ?>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="name" class="form-label">Product Name</label>
                                <input type="text" class="form-control" id="name" name="name" 
                                       value="<?php echo $editingProduct['name'] ?? ''; ?>" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="price" class="form-label">Price (<?php echo CURRENCY_SYMBOL; ?>)</label>
                                <input type="number" class="form-control" id="price" name="price" 
                                       step="0.01" min="0" value="<?php echo $editingProduct['price'] ?? ''; ?>" required>
                                <small class="text-muted">Enter price in Indian Rupees (INR)</small>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="category_id" class="form-label">Category</label>
                                <select class="form-select" id="category_id" name="category_id">
                                    <option value="">Select Category</option>
                                    <?php foreach ($categories as $cat): ?>
                                        <option value="<?php echo $cat['id']; ?>" 
                                                <?php echo ($editingProduct && $editingProduct['category_id'] == $cat['id']) ? 'selected' : ''; ?>>
                                            <?php echo sanitize($cat['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="status" class="form-label">Status</label>
                                <select class="form-select" id="status" name="status">
                                    <option value="active" <?php echo ($editingProduct && $editingProduct['status'] === 'active') ? 'selected' : ''; ?>>Active</option>
                                    <option value="inactive" <?php echo ($editingProduct && $editingProduct['status'] === 'inactive') ? 'selected' : ''; ?>>Inactive</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="short_description" class="form-label">Short Description</label>
                            <textarea class="form-control" id="short_description" name="short_description" rows="2"><?php echo $editingProduct['short_description'] ?? ''; ?></textarea>
                        </div>
                        
                        <div class="mb-3">
                            <label for="description" class="form-label">Full Description</label>
                            <textarea class="form-control" id="description" name="description" rows="4" required><?php echo $editingProduct['description'] ?? ''; ?></textarea>
                        </div>
                        
                        <div class="mb-3">
                            <label for="screenshot" class="form-label">Product Image</label>
                            <input type="file" class="form-control" id="screenshot" name="screenshot" accept="image/*">
                            <?php if ($editingProduct && $editingProduct['screenshot']): ?>
                                <div class="mt-2">
                                    <small class="text-muted d-block mb-1">Current Image:</small>
                                    <img src="../<?php echo $editingProduct['screenshot']; ?>" 
                                         class="img-thumbnail" width="100" height="100" 
                                         style="object-fit: cover;" 
                                         alt="<?php echo sanitize($editingProduct['name']); ?>">
                                </div>
                            <?php endif; ?>
                            <small class="text-muted">Upload a high-quality image for your menu item</small>
                        </div>
                        
                        <div class="mb-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="featured" name="featured" 
                                       <?php echo ($editingProduct && $editingProduct['featured']) ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="featured">
                                    Featured Menu Item
                                </label>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">
                            <?php echo $editingProduct ? 'Update Menu Item' : 'Add Menu Item'; ?>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS (for reliable modals and dropdowns) -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- MDB5 JS (for styling only) -->
    <script src="../MDB5-STANDARD-UI-KIT-Free-9.2.0/js/mdb.umd.min.js"></script>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            console.log('DOM loaded, initializing components...');
            
            // Initialize Bootstrap components
            if (typeof bootstrap !== 'undefined') {
                console.log('Bootstrap loaded successfully');
                
                // Initialize modals
                const modalElements = document.querySelectorAll('.modal');
                modalElements.forEach(function(modalEl) {
                    new bootstrap.Modal(modalEl);
                });
                console.log('Bootstrap modals initialized');
                
                // Initialize dropdowns
                const dropdownTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="dropdown"]'));
                const dropdownList = dropdownTriggerList.map(function (dropdownTriggerEl) {
                    return new bootstrap.Dropdown(dropdownTriggerEl);
                });
                console.log('Bootstrap dropdowns initialized:', dropdownList.length);
                
                // Auto-open modal for editing
                <?php if ($editingProduct): ?>
                    console.log('Opening edit modal for product ID: <?php echo $editingProduct['id']; ?>');
                    const productModal = new bootstrap.Modal(document.getElementById('productModal'));
                    productModal.show();
                <?php endif; ?>
                
            } else {
                console.error('Bootstrap not loaded');
            }
            
            // MDB5 for styling only
            if (typeof window.mdb !== 'undefined') {
                console.log('MDB5 loaded for styling');
            }
            
            // Mobile menu toggle
            const mobileMenuToggle = document.getElementById('mobileMenuToggle');
            const sidebar = document.getElementById('sidebar');
            
            if (mobileMenuToggle && sidebar) {
                mobileMenuToggle.addEventListener('click', function() {
                    sidebar.classList.toggle('show');
                });
            }
            
            // Product management functions
            window.editProduct = function(productId) {
                window.location.href = 'products.php?edit=' + productId;
            };
            
            window.deleteProduct = function(productId) {
                if (confirm('Are you sure you want to delete this product?')) {
                    const form = document.createElement('form');
                    form.method = 'POST';
                    form.innerHTML = '<input type="hidden" name="action" value="delete"><input type="hidden" name="product_id" value="' + productId + '">';
                    document.body.appendChild(form);
                    form.submit();
                }
            };
        });
    </script>
</body>
</html>
