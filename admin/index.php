<?php
require_once '../config.php';
require_once '../database.php';
require_once '../functions.php';

// Check if user is admin
if (!isAdmin()) {
    redirect('../login.php');
}

$db = new Database();

// Get dashboard statistics with optimized queries
$stats = [
    'total_users' => $db->query("SELECT COUNT(*) as count FROM users WHERE role = 'user' AND status = 'active'")->fetch()['count'],
    'total_products' => $db->query("SELECT COUNT(*) as count FROM products WHERE status = 'active'")->fetch()['count'],
    'total_orders' => $db->query("SELECT COUNT(*) as count FROM orders WHERE payment_status = 'completed'")->fetch()['count'],
    'total_revenue' => $db->query("SELECT COALESCE(SUM(final_amount), 0) as total FROM orders WHERE payment_status = 'completed'")->fetch()['total']
];

// Get recent orders with optimized query
$recentOrders = $db->query("
    SELECT o.order_number, o.final_amount, o.payment_status, o.created_at, 
           o.user_id, u.name as customer_name, u.email as customer_email 
    FROM orders o 
    LEFT JOIN users u ON o.user_id = u.id 
    ORDER BY o.created_at DESC 
    LIMIT 5
")->fetchAll();

// Get top selling products with optimized query
$topProducts = $db->query("
    SELECT p.name, p.id, SUM(oi.quantity) as total_sold, SUM(oi.total_price) as revenue
    FROM products p
    INNER JOIN order_items oi ON p.id = oi.product_id
    INNER JOIN orders o ON oi.order_id = o.id AND o.payment_status = 'completed'
    WHERE p.status = 'active'
    GROUP BY p.id, p.name
    HAVING total_sold > 0
    ORDER BY total_sold DESC
    LIMIT 5
")->fetchAll();

// Get recent users with optimized query
$recentUsers = $db->query("
    SELECT id, name, email, created_at 
    FROM users 
    WHERE role = 'user' AND status = 'active'
    ORDER BY created_at DESC 
    LIMIT 5
")->fetchAll();

// Get sales data for the last 7 days with single query
$salesData = [];
$salesQuery = $db->query("
    SELECT 
        DATE(created_at) as date,
        COUNT(*) as orders,
        COALESCE(SUM(final_amount), 0) as revenue
    FROM orders 
    WHERE payment_status = 'completed' 
    AND created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
    GROUP BY DATE(created_at)
    ORDER BY date ASC
")->fetchAll();

// Create array with all dates and fill missing ones with zeros
$salesArray = [];
foreach ($salesQuery as $sale) {
    $salesArray[$sale['date']] = [
        'orders' => $sale['orders'],
        'revenue' => $sale['revenue']
    ];
}

// Build complete 7-day data
for ($i = 6; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $salesData[] = [
        'date' => date('M j', strtotime($date)),
        'orders' => $salesArray[$date]['orders'] ?? 0,
        'revenue' => $salesArray[$date]['revenue'] ?? 0
    ];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Café Admin Dashboard - <?php echo SITE_NAME; ?></title>
    
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
            --info-color: #54b4d3;
        }
        
        body {
            background: #f8f9fa;
            overflow-x: hidden;
        }
        
        html {
            scroll-behavior: smooth;
        }
        
        .sidebar {
            background: linear-gradient(135deg, var(--primary-color) 0%, #667eea 100%);
            height: 100vh;
            color: white;
            position: fixed;
            left: 0;
            top: 0;
            width: 250px;
            z-index: 1000;
            overflow-y: auto;
            overflow-x: hidden;
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
            max-width: calc(100vw - 250px);
            overflow-x: hidden;
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
        
        .stat-card.users { border-left-color: var(--primary-color); }
        .stat-card.products { border-left-color: var(--success-color); }
        .stat-card.orders { border-left-color: var(--warning-color); }
        .stat-card.revenue { border-left-color: var(--info-color); }
        
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
        
        .stat-card.users .stat-icon { background: rgba(59, 113, 202, 0.1); color: var(--primary-color); }
        .stat-card.products .stat-icon { background: rgba(20, 164, 77, 0.1); color: var(--success-color); }
        .stat-card.orders .stat-icon { background: rgba(228, 161, 27, 0.1); color: var(--warning-color); }
        .stat-card.revenue .stat-icon { background: rgba(84, 180, 211, 0.1); color: var(--info-color); }
        
        .stat-value {
            font-size: 2rem;
            font-weight: bold;
            margin-bottom: 5px;
        }
        
        .stat-label {
            color: #6c757d;
            font-size: 0.9rem;
        }
        
        .chart-container {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            margin-bottom: 20px;
            max-width: 100%;
            overflow: hidden;
        }
        
        .chart-container canvas {
            max-width: 100% !important;
            height: 300px !important;
        }
        
        .table-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            overflow-x: auto;
        }
        
        .table-responsive {
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
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
                height: 100vh;
                overflow-y: auto;
            }
            
            .sidebar.show {
                transform: translateX(0);
            }
            
            .main-content {
                margin-left: 0;
                padding: 60px 10px 10px;
                max-width: 100vw;
            }
            
            .mobile-menu-toggle {
                display: block;
            }
            
            .chart-container canvas {
                height: 250px !important;
            }
        }
        
        .badge-status {
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 500;
        }
        
        .hover-underline {
            text-decoration: none !important;
            transition: all 0.3s ease;
        }
        
        .hover-underline:hover {
            text-decoration: underline !important;
        }
        
        .badge-completed { background: rgba(20, 164, 77, 0.1); color: var(--success-color); }
        .badge-pending { background: rgba(228, 161, 27, 0.1); color: var(--warning-color); }
        .badge-failed { background: rgba(220, 76, 100, 0.1); color: var(--danger-color); }
        .badge-processing { background: rgba(84, 180, 211, 0.1); color: var(--info-color); }
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
                    <a class="nav-link active" href="index.php">
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
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2 class="fw-bold">Café Dashboard</h2>
            <div class="text-muted">
                Welcome back, <?php echo sanitize($_SESSION['user_name']); ?>!
            </div>
        </div>

        <!-- Statistics Cards -->
        <div class="row mb-4">
            <div class="col-md-3 mb-3">
                <div class="stat-card users">
                    <div class="stat-icon">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="stat-value"><?php echo number_format($stats['total_users']); ?></div>
                    <div class="stat-label">Total Users</div>
                </div>
            </div>
            
            <div class="col-md-3 mb-3">
                <div class="stat-card products">
                    <div class="stat-icon">
                        <i class="fas fa-box"></i>
                    </div>
                    <div class="stat-value"><?php echo number_format($stats['total_products']); ?></div>
                    <div class="stat-label">Total Products</div>
                </div>
            </div>
            
            <div class="col-md-3 mb-3">
                <div class="stat-card orders">
                    <div class="stat-icon">
                        <i class="fas fa-shopping-cart"></i>
                    </div>
                    <div class="stat-value"><?php echo number_format($stats['total_orders']); ?></div>
                    <div class="stat-label">Total Orders</div>
                </div>
            </div>
            
            <div class="col-md-3 mb-3">
                <div class="stat-card revenue">
                    <div class="stat-icon">
                        <i class="fas fa-dollar-sign"></i>
                    </div>
                    <div class="stat-value"><?php echo formatPrice($stats['total_revenue']); ?></div>
                    <div class="stat-label">Total Revenue</div>
                </div>
            </div>
        </div>

        <!-- Sales Chart -->
        <div class="chart-container">
            <h5 class="fw-bold mb-4">Sales Overview (Last 7 Days)</h5>
            <canvas id="salesChart" height="80"></canvas>
        </div>

        <div class="row">
            <!-- Recent Orders -->
            <div class="col-lg-8 mb-4">
                <div class="table-card">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h5 class="fw-bold mb-0">Recent Orders</h5>
                        <a href="orders.php" class="btn btn-sm btn-outline-primary">View All</a>
                    </div>
                    
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Order #</th>
                                    <th>Customer</th>
                                    <th>Amount</th>
                                    <th>Status</th>
                                    <th>Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($recentOrders)): ?>
                                    <tr>
                                        <td colspan="5" class="text-center text-muted">No orders yet</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($recentOrders as $order): ?>
                                        <tr>
                                            <td>
                                                <span class="text-decoration-none">
                                                    #<?php echo $order['order_number']; ?>
                                                </span>
                                            </td>
                                            <td>
                                                <div>
                                                    <div class="fw-bold">
                                                        <?php if ($order['user_id']): ?>
                                                            <a href="user-profile.php?id=<?php echo $order['user_id']; ?>" class="text-decoration-none text-primary hover-underline">
                                                                <?php echo sanitize($order['customer_name']); ?>
                                                            </a>
                                                        <?php else: ?>
                                                            <?php echo sanitize($order['customer_name']); ?>
                                                        <?php endif; ?>
                                                    </div>
                                                    <small class="text-muted"><?php echo sanitize($order['customer_email']); ?></small>
                                                </div>
                                            </td>
                                            <td class="fw-bold"><?php echo formatPrice($order['final_amount']); ?></td>
                                            <td>
                                                <span class="badge-status badge-<?php echo $order['payment_status']; ?>">
                                                    <?php echo ucfirst($order['payment_status']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <small><?php echo date('M j, Y', strtotime($order['created_at'])); ?></small>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Top Products & Recent Users -->
            <div class="col-lg-4">
                <!-- Top Products -->
                <div class="table-card mb-4">
                    <h5 class="fw-bold mb-4">Top Selling Products</h5>
                    
                    <?php if (empty($topProducts)): ?>
                        <div class="text-center text-muted py-3">No sales yet</div>
                    <?php else: ?>
                        <?php foreach ($topProducts as $product): ?>
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <div>
                                    <div class="fw-bold"><?php echo sanitize($product['name']); ?></div>
                                    <small class="text-muted"><?php echo $product['total_sold']; ?> sold</small>
                                </div>
                                <div class="text-end">
                                    <div class="fw-bold"><?php echo formatPrice($product['revenue']); ?></div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <!-- Recent Users -->
                <div class="table-card">
                    <h5 class="fw-bold mb-4">Recent Users</h5>
                    
                    <?php if (empty($recentUsers)): ?>
                        <div class="text-center text-muted py-3">No users yet</div>
                    <?php else: ?>
                        <?php foreach ($recentUsers as $user): ?>
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <div>
                                    <div class="fw-bold">
                                        <a href="user-profile.php?id=<?php echo $user['id']; ?>" class="text-decoration-none text-primary hover-underline">
                                            <?php echo sanitize($user['name']); ?>
                                        </a>
                                    </div>
                                    <small class="text-muted"><?php echo sanitize($user['email']); ?></small>
                                </div>
                                <div>
                                    <span class="badge bg-success">Active</span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </main>

    <!-- MDB5 JS -->
    <script src="../MDB5-STANDARD-UI-KIT-Free-9.2.0/js/mdb.umd.min.js"></script>
    
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Mobile menu toggle with performance optimization
            const mobileMenuToggle = document.getElementById('mobileMenuToggle');
            const sidebar = document.getElementById('sidebar');
            
            if (mobileMenuToggle && sidebar) {
                mobileMenuToggle.addEventListener('click', function(e) {
                    e.preventDefault();
                    sidebar.classList.toggle('show');
                });
            }
            
            // Performance optimizations
            // Lazy load chart after page loads
            setTimeout(function() {
                const ctx = document.getElementById('salesChart');
                if (!ctx) return;
                
                const salesData = <?php echo json_encode($salesData); ?>;
                
                new Chart(ctx.getContext('2d'), {
                    type: 'line',
                    data: {
                        labels: salesData.map(item => item.date),
                        datasets: [{
                            label: 'Revenue',
                            data: salesData.map(item => item.revenue),
                            borderColor: '#3b71ca',
                            backgroundColor: 'rgba(59, 113, 202, 0.1)',
                            tension: 0.4,
                            fill: true,
                            borderWidth: 2,
                            pointRadius: 4,
                            pointHoverRadius: 6
                        }, {
                            label: 'Orders',
                            data: salesData.map(item => item.orders),
                            borderColor: '#14a44d',
                            backgroundColor: 'rgba(20, 164, 77, 0.1)',
                            tension: 0.4,
                            fill: true,
                            borderWidth: 2,
                            pointRadius: 4,
                            pointHoverRadius: 6,
                            yAxisID: 'y1'
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        interaction: {
                            mode: 'index',
                            intersect: false,
                        },
                        plugins: {
                            legend: {
                                display: true,
                                position: 'top',
                                labels: {
                                    usePointStyle: true,
                                    padding: 15
                                }
                            },
                            tooltip: {
                                backgroundColor: 'rgba(0,0,0,0.8)',
                                padding: 12,
                                cornerRadius: 8,
                                titleFont: {
                                    size: 14
                                },
                                bodyFont: {
                                    size: 13
                                }
                            }
                        },
                        scales: {
                            y: {
                                type: 'linear',
                                display: true,
                                position: 'left',
                                title: {
                                    display: true,
                                    text: 'Revenue ($)'
                                },
                                ticks: {
                                    callback: function(value) {
                                        return '$' + value.toLocaleString();
                                    }
                                }
                            },
                            y1: {
                                type: 'linear',
                                display: true,
                                position: 'right',
                                title: {
                                    display: true,
                                    text: 'Orders'
                                },
                                grid: {
                                    drawOnChartArea: false,
                                },
                                ticks: {
                                    stepSize: 1
                                }
                            },
                            x: {
                                grid: {
                                    display: false
                                }
                            }
                        },
                        animation: {
                            duration: 1000,
                            easing: 'easeInOutQuart'
                        }
                    }
                });
            }, 1000);
        });
    </script>
</body>
</html>
