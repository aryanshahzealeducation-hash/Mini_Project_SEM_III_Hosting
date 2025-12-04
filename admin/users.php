<?php
require_once '../config.php';
require_once '../database.php';
require_once '../functions.php';

// Check if user is admin
if (!isAdmin()) {
    redirect('../login.php');
}

$db = new Database();

// Handle user actions
$action = sanitize($_POST['action'] ?? '');
$message = '';
$messageType = '';

switch ($action) {
    case 'toggle_status':
        $userId = intval($_POST['user_id']);
        $newStatus = sanitize($_POST['status']);
        
        $db->query("UPDATE users SET status = ? WHERE id = ?", [$newStatus, $userId]);
        
        $message = 'User status updated successfully!';
        $messageType = 'success';
        break;
        
    case 'delete':
        $userId = intval($_POST['user_id']);
        
        // Don't allow deletion of admin users
        $user = $db->query("SELECT role FROM users WHERE id = ?", [$userId])->fetch();
        if ($user && $user['role'] !== 'admin') {
            // Delete user's cart and orders first
            $db->query("DELETE FROM cart WHERE user_id = ?", [$userId]);
            $db->query("DELETE FROM downloads WHERE user_id = ?", [$userId]);
            $db->query("DELETE FROM support_tickets WHERE user_id = ?", [$userId]);
            
            // Delete the user
            $db->query("DELETE FROM users WHERE id = ?", [$userId]);
            
            $message = 'User deleted successfully!';
            $messageType = 'success';
        } else {
            $message = 'Cannot delete admin users!';
            $messageType = 'danger';
        }
        break;
}

// Get filters
$status = sanitize($_GET['status'] ?? '');
$search = sanitize($_GET['search'] ?? '');
$role = sanitize($_GET['role'] ?? '');

// Build where clause
$where = ["1=1"];
$params = [];

if ($status) {
    $where[] = "u.status = ?";
    $params[] = $status;
}

if ($role) {
    $where[] = "u.role = ?";
    $params[] = $role;
}

if ($search) {
    $where[] = "(u.name LIKE ? OR u.email LIKE ?)";
    $searchParam = "%$search%";
    $params[] = $searchParam;
    $params[] = $searchParam;
}

$whereClause = "WHERE " . implode(" AND ", $where);

// Get users with pagination
$page = max(1, intval($_GET['page'] ?? 1));
$pagination = paginate(100, 20, $page); // Assuming max 100 users for now

$users = $db->query("
    SELECT u.*, 
           COUNT(DISTINCT o.id) as order_count,
           COALESCE(SUM(CASE WHEN o.payment_status = 'completed' THEN o.final_amount ELSE 0 END), 0) as total_spent
    FROM users u
    LEFT JOIN orders o ON u.id = o.user_id
    $whereClause
    GROUP BY u.id
    ORDER BY u.created_at DESC
    LIMIT {$pagination['offset']}, {$pagination['items_per_page']}
", $params)->fetchAll();

// Get user statistics
$stats = [
    'total' => $db->query("SELECT COUNT(*) as count FROM users WHERE role = 'user'")->fetch()['count'],
    'active' => $db->query("SELECT COUNT(*) as count FROM users WHERE role = 'user' AND status = 'active'")->fetch()['count'],
    'blocked' => $db->query("SELECT COUNT(*) as count FROM users WHERE role = 'user' AND status = 'blocked'")->fetch()['count'],
    'new_this_month' => $db->query("SELECT COUNT(*) as count FROM users WHERE role = 'user' AND MONTH(created_at) = MONTH(CURRENT_DATE) AND YEAR(created_at) = YEAR(CURRENT_DATE)")->fetch()['count']
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Café Members Management - <?php echo SITE_NAME; ?></title>
    
    <!-- MDB5 CSS -->
    <link rel="stylesheet" href="../MDB5-STANDARD-UI-KIT-Free-9.2.0/css/mdb.min.css">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <style>
        :root {
            --primary-color: #3b71ca;
            --secondary-color: #9fa6b2;
            --success-color: #14a44d;
            --danger-color: #dc4c64;
            --warning-color: #e4a11b;
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
        
        .stat-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            transition: all 0.3s ease;
            border-left: 4px solid transparent;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 20px rgba(0,0,0,0.15);
        }
        
        .stat-card.total { border-left-color: var(--primary-color); }
        .stat-card.active { border-left-color: var(--success-color); }
        .stat-card.blocked { border-left-color: var(--danger-color); }
        .stat-card.new { border-left-color: var(--warning-color); }
        
        .stat-icon {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            margin-bottom: 15px;
        }
        
        .stat-card.total .stat-icon { background: rgba(59, 113, 202, 0.1); color: var(--primary-color); }
        .stat-card.active .stat-icon { background: rgba(20, 164, 77, 0.1); color: var(--success-color); }
        .stat-card.blocked .stat-icon { background: rgba(220, 76, 100, 0.1); color: var(--danger-color); }
        .stat-card.new .stat-icon { background: rgba(228, 161, 27, 0.1); color: var(--warning-color); }
        
        .stat-value {
            font-size: 2rem;
            font-weight: bold;
            margin-bottom: 5px;
        }
        
        .stat-label {
            color: #6c757d;
            font-size: 0.9rem;
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
        
        .badge-active { background: rgba(20, 164, 77, 0.1); color: var(--success-color); }
        .badge-blocked { background: rgba(220, 76, 100, 0.1); color: var(--danger-color); }
        .badge-admin { background: rgba(59, 113, 202, 0.1); color: var(--primary-color); }
        .badge-user { background: rgba(159, 166, 178, 0.1); color: var(--secondary-color); }
        
        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: var(--primary-color);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
        }
        
        .user-actions .btn {
            padding: 4px 8px;
            font-size: 0.8rem;
        }
        
        .hover-underline {
            text-decoration: none !important;
            transition: all 0.3s ease;
        }
        
        .hover-underline:hover {
            text-decoration: underline !important;
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
                    <a class="nav-link" href="products.php">
                        <i class="fas fa-box"></i> Products
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="orders.php">
                        <i class="fas fa-shopping-cart"></i> Orders
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link active" href="users.php">
                        <i class="fas fa-users"></i> Café Members
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
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2 class="fw-bold">Café Members Management</h2>
        </div>

        <!-- Alert Message -->
        <?php if ($message): ?>
            <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show" role="alert">
                <?php echo $message; ?>
                <button type="button" class="btn-close" data-mdb-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Statistics Cards -->
        <div class="row mb-4">
            <div class="col-md-3 mb-3">
                <div class="stat-card total">
                    <div class="stat-icon">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="stat-value"><?php echo number_format($stats['total']); ?></div>
                    <div class="stat-label">Total Café Members</div>
                </div>
            </div>
            
            <div class="col-md-3 mb-3">
                <div class="stat-card active">
                    <div class="stat-icon">
                        <i class="fas fa-user-check"></i>
                    </div>
                    <div class="stat-value"><?php echo number_format($stats['active']); ?></div>
                    <div class="stat-label">Active Members</div>
                </div>
            </div>
            
            <div class="col-md-3 mb-3">
                <div class="stat-card blocked">
                    <div class="stat-icon">
                        <i class="fas fa-user-slash"></i>
                    </div>
                    <div class="stat-value"><?php echo number_format($stats['blocked']); ?></div>
                    <div class="stat-label">Blocked Members</div>
                </div>
            </div>
            
            <div class="col-md-3 mb-3">
                <div class="stat-card new">
                    <div class="stat-icon">
                        <i class="fas fa-user-plus"></i>
                    </div>
                    <div class="stat-value"><?php echo number_format($stats['new_this_month']); ?></div>
                    <div class="stat-label">New This Month</div>
                </div>
            </div>
        </div>

        <!-- Filters -->
        <div class="form-card">
            <form method="GET" class="row g-3">
                <div class="col-md-4">
                    <input type="text" class="form-control" name="search" placeholder="Search café members..." value="<?php echo $search; ?>">
                </div>
                <div class="col-md-2">
                    <select class="form-select" name="role">
                        <option value="">All Roles</option>
                        <option value="user" <?php echo $role === 'user' ? 'selected' : ''; ?>>Members</option>
                        <option value="admin" <?php echo $role === 'admin' ? 'selected' : ''; ?>>Admins</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <select class="form-select" name="status">
                        <option value="">All Status</option>
                        <option value="active" <?php echo $status === 'active' ? 'selected' : ''; ?>>Active</option>
                        <option value="blocked" <?php echo $status === 'blocked' ? 'selected' : ''; ?>>Blocked</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <button type="submit" class="btn btn-outline-primary">Filter</button>
                    <a href="users.php" class="btn btn-outline-secondary">Clear</a>
                </div>
            </form>
        </div>

        <!-- Users Table -->
        <div class="table-card">
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>User</th>
                            <th>Email</th>
                            <th>Role</th>
                            <th>Status</th>
                            <th>Orders</th>
                            <th>Total Spent</th>
                            <th>Joined</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($users)): ?>
                            <tr>
                                <td colspan="8" class="text-center text-muted">No café members found</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($users as $user): ?>
                                <tr>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <div class="user-avatar me-3">
                                                <?php echo strtoupper(substr($user['name'], 0, 2)); ?>
                                            </div>
                                            <div class="fw-bold">
                                                <a href="user-profile.php?id=<?php echo $user['id']; ?>" class="text-decoration-none text-primary hover-underline">
                                                    <?php echo sanitize($user['name']); ?>
                                                </a>
                                            </div>
                                        </div>
                                    </td>
                                    <td><?php echo sanitize($user['email']); ?></td>
                                    <td>
                                        <span class="badge-status badge-<?php echo $user['role']; ?>">
                                            <?php echo ucfirst($user['role']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge-status badge-<?php echo $user['status']; ?>">
                                            <?php echo ucfirst($user['status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge bg-secondary"><?php echo $user['order_count']; ?></span>
                                    </td>
                                    <td class="fw-bold"><?php echo formatPrice($user['total_spent']); ?></td>
                                    <td>
                                        <div>
                                            <div><?php echo date('M j, Y', strtotime($user['created_at'])); ?></div>
                                            <small class="text-muted"><?php echo date('h:i A', strtotime($user['created_at'])); ?></small>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="user-actions">
                                            <?php if ($user['role'] !== 'admin'): ?>
                                                <button class="btn btn-sm btn-outline-<?php echo $user['status'] === 'active' ? 'warning' : 'success'; ?>" 
                                                        onclick="toggleStatus(<?php echo $user['id']; ?>, '<?php echo $user['status'] === 'active' ? 'blocked' : 'active'; ?>')">
                                                    <i class="fas fa-<?php echo $user['status'] === 'active' ? 'ban' : 'check'; ?>"></i>
                                                </button>
                                                <button class="btn btn-sm btn-outline-danger" onclick="deleteUser(<?php echo $user['id']; ?>)">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            <?php else: ?>
                                                <span class="text-muted small">Admin</span>
                                            <?php endif; ?>
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
                <nav aria-label="Café Members pagination" class="mt-4">
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

    <!-- MDB5 JS -->
    <script src="../MDB5-STANDARD-UI-KIT-Free-9.2.0/js/mdb.umd.min.js"></script>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Mobile menu toggle
            const mobileMenuToggle = document.getElementById('mobileMenuToggle');
            const sidebar = document.getElementById('sidebar');
            
            if (mobileMenuToggle && sidebar) {
                mobileMenuToggle.addEventListener('click', function() {
                    sidebar.classList.toggle('show');
                });
            }
            
            // Toggle user status
            function toggleStatus(userId, newStatus) {
                const actionText = newStatus === 'blocked' ? 'block' : 'unblock';
                
                if (confirm(`Are you sure you want to ${actionText} this user?`)) {
                    const form = document.createElement('form');
                    form.method = 'POST';
                    form.innerHTML = `
                        <input type="hidden" name="action" value="toggle_status">
                        <input type="hidden" name="user_id" value="${userId}">
                        <input type="hidden" name="status" value="${newStatus}">
                    `;
                    document.body.appendChild(form);
                    form.submit();
                }
            }
            
            // Delete user
            function deleteUser(userId) {
                if (confirm('Are you sure you want to delete this user? This action cannot be undone and will delete all associated data.')) {
                    const form = document.createElement('form');
                    form.method = 'POST';
                    form.innerHTML = `
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="user_id" value="${userId}">
                    `;
                    document.body.appendChild(form);
                    form.submit();
                }
            }
            
            // Make functions global
            window.toggleStatus = toggleStatus;
            window.deleteUser = deleteUser;
        });
    </script>
</body>
</html>
