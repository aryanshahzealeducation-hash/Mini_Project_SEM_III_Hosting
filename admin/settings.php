<?php
require_once '../config.php';
require_once '../database.php';
require_once '../functions.php';

// Check if user is admin
if (!isAdmin()) {
    redirect('../login.php');
}

$db = new Database();

$message = '';
$messageType = '';

// Handle settings update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $settings = [
        'site_name' => sanitize($_POST['site_name'] ?? ''),
        'site_logo' => sanitize($_POST['site_logo'] ?? ''),
        'currency' => sanitize($_POST['currency'] ?? ''),
        'tax_rate' => floatval($_POST['tax_rate'] ?? 0),
        'email_notifications' => isset($_POST['email_notifications']) ? '1' : '0',
        'maintenance_mode' => isset($_POST['maintenance_mode']) ? '1' : '0',
        'razorpay_key' => sanitize($_POST['razorpay_key'] ?? ''),
        'razorpay_secret' => sanitize($_POST['razorpay_secret'] ?? ''),
        'stripe_key' => sanitize($_POST['stripe_key'] ?? ''),
        'stripe_secret' => sanitize($_POST['stripe_secret'] ?? ''),
        'paypal_client_id' => sanitize($_POST['paypal_client_id'] ?? ''),
        'paypal_secret' => sanitize($_POST['paypal_secret'] ?? ''),
        'admin_email' => sanitize($_POST['admin_email'] ?? '')
    ];
    
    foreach ($settings as $key => $value) {
        $db->query("
            INSERT INTO settings (setting_key, setting_value, setting_type) 
            VALUES (?, ?, 'text')
            ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)
        ", [$key, $value]);
    }
    
    $message = 'Settings updated successfully!';
    $messageType = 'success';
}

// Get current settings
$settings = $db->query("SELECT * FROM settings ORDER BY setting_key")->fetchAll();
$currentSettings = [];
foreach ($settings as $setting) {
    $currentSettings[$setting['setting_key']] = $setting['setting_value'];
}

// Set defaults
$defaults = [
    'site_name' => 'CafeNIX',
    'site_logo' => '',
    'currency' => 'USD',
    'tax_rate' => 0,
    'email_notifications' => '1',
    'maintenance_mode' => '0',
    'razorpay_key' => '',
    'razorpay_secret' => '',
    'stripe_key' => '',
    'stripe_secret' => '',
    'paypal_client_id' => '',
    'paypal_secret' => '',
    'admin_email' => 'admin@cafenix.com'
];

foreach ($defaults as $key => $value) {
    if (!isset($currentSettings[$key])) {
        $currentSettings[$key] = $value;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings - <?php echo SITE_NAME; ?></title>
    
    <!-- MDB5 CSS -->
    <link rel="stylesheet" href="../MDB5-STANDARD-UI-KIT-Free-9.2.0/css/mdb.min.css">
    
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
        
        .settings-card {
            background: white;
            border-radius: 15px;
            padding: 30px;
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
        
        .form-label {
            font-weight: 500;
            color: #495057;
        }
        
        .form-control:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.2rem rgba(59, 113, 202, 0.25);
        }
        
        .btn-primary {
            background: var(--primary-color);
            border: none;
            padding: 10px 25px;
            font-weight: 500;
            transition: all 0.3s ease;
        }
        
        .btn-primary:hover {
            background: #2f5aa2;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(59, 113, 202, 0.3);
        }
        
        .section-title {
            font-size: 1.2rem;
            font-weight: 600;
            color: #333;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #f0f0f0;
        }
        
        .logo-preview {
            width: 100px;
            height: 100px;
            border: 2px dashed #dee2e6;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-top: 10px;
        }
        
        .switch {
            position: relative;
            display: inline-block;
            width: 60px;
            height: 34px;
        }
        
        .switch input {
            opacity: 0;
            width: 0;
            height: 0;
        }
        
        .slider {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: #ccc;
            transition: .4s;
            border-radius: 34px;
        }
        
        .slider:before {
            position: absolute;
            content: "";
            height: 26px;
            width: 26px;
            left: 4px;
            bottom: 4px;
            background-color: white;
            transition: .4s;
            border-radius: 50%;
        }
        
        input:checked + .slider {
            background-color: var(--primary-color);
        }
        
        input:checked + .slider:before {
            transform: translateX(26px);
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
                    <a class="nav-link active" href="settings.php">
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
            <h2 class="fw-bold">Settings</h2>
        </div>

        <!-- Alert Message -->
        <?php if ($message): ?>
            <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show" role="alert">
                <?php echo $message; ?>
                <button type="button" class="btn-close" data-mdb-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <form method="POST">
            <!-- Settings Tabs -->
            <ul class="nav nav-tabs mb-4" id="settingsTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" id="general-tab" data-mdb-toggle="tab" data-mdb-target="#general" type="button" role="tab">
                        <i class="fas fa-cog me-2"></i> General
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="payment-tab" data-mdb-toggle="tab" data-mdb-target="#payment" type="button" role="tab">
                        <i class="fas fa-credit-card me-2"></i> Payment
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="email-tab" data-mdb-toggle="tab" data-mdb-target="#email" type="button" role="tab">
                        <i class="fas fa-envelope me-2"></i> Email
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="system-tab" data-mdb-toggle="tab" data-mdb-target="#system" type="button" role="tab">
                        <i class="fas fa-server me-2"></i> System
                    </button>
                </li>
            </ul>

            <div class="tab-content" id="settingsTabContent">
                <!-- General Settings -->
                <div class="tab-pane fade show active" id="general" role="tabpanel">
                    <div class="settings-card">
                        <h5 class="section-title">Site Information</h5>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="site_name" class="form-label">Site Name</label>
                                <input type="text" class="form-control" id="site_name" name="site_name" 
                                       value="<?php echo $currentSettings['site_name']; ?>" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="admin_email" class="form-label">Admin Email</label>
                                <input type="email" class="form-control" id="admin_email" name="admin_email" 
                                       value="<?php echo $currentSettings['admin_email']; ?>" required>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="site_logo" class="form-label">Site Logo URL</label>
                                <input type="url" class="form-control" id="site_logo" name="site_logo" 
                                       value="<?php echo $currentSettings['site_logo']; ?>" 
                                       placeholder="https://example.com/logo.png">
                                <div class="logo-preview mt-2">
                                    <?php if ($currentSettings['site_logo']): ?>
                                        <img src="<?php echo $currentSettings['site_logo']; ?>" alt="Logo" style="max-width: 100%; max-height: 100%;">
                                    <?php else: ?>
                                        <i class="fas fa-image fa-2x text-muted"></i>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="currency" class="form-label">Currency</label>
                                <select class="form-select" id="currency" name="currency">
                                    <option value="USD" <?php echo $currentSettings['currency'] === 'USD' ? 'selected' : ''; ?>>USD ($)</option>
                                    <option value="EUR" <?php echo $currentSettings['currency'] === 'EUR' ? 'selected' : ''; ?>>EUR (€)</option>
                                    <option value="GBP" <?php echo $currentSettings['currency'] === 'GBP' ? 'selected' : ''; ?>>GBP (£)</option>
                                    <option value="INR" <?php echo $currentSettings['currency'] === 'INR' ? 'selected' : ''; ?>>INR (₹)</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="tax_rate" class="form-label">Tax Rate (%)</label>
                                <input type="number" class="form-control" id="tax_rate" name="tax_rate" 
                                       value="<?php echo $currentSettings['tax_rate']; ?>" step="0.01" min="0" max="100">
                                <small class="text-muted">Enter tax percentage (e.g., 10 for 10%)</small>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Payment Settings -->
                <div class="tab-pane fade" id="payment" role="tabpanel">
                    <div class="settings-card">
                        <h5 class="section-title">Razorpay</h5>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="razorpay_key" class="form-label">Razorpay Key</label>
                                <input type="text" class="form-control" id="razorpay_key" name="razorpay_key" 
                                       value="<?php echo $currentSettings['razorpay_key']; ?>">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="razorpay_secret" class="form-label">Razorpay Secret</label>
                                <input type="password" class="form-control" id="razorpay_secret" name="razorpay_secret" 
                                       value="<?php echo $currentSettings['razorpay_secret']; ?>">
                            </div>
                        </div>
                    </div>

                    <div class="settings-card">
                        <h5 class="section-title">Stripe</h5>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="stripe_key" class="form-label">Stripe Publishable Key</label>
                                <input type="text" class="form-control" id="stripe_key" name="stripe_key" 
                                       value="<?php echo $currentSettings['stripe_key']; ?>">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="stripe_secret" class="form-label">Stripe Secret Key</label>
                                <input type="password" class="form-control" id="stripe_secret" name="stripe_secret" 
                                       value="<?php echo $currentSettings['stripe_secret']; ?>">
                            </div>
                        </div>
                    </div>

                    <div class="settings-card">
                        <h5 class="section-title">PayPal</h5>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="paypal_client_id" class="form-label">PayPal Client ID</label>
                                <input type="text" class="form-control" id="paypal_client_id" name="paypal_client_id" 
                                       value="<?php echo $currentSettings['paypal_client_id']; ?>">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="paypal_secret" class="form-label">PayPal Secret</label>
                                <input type="password" class="form-control" id="paypal_secret" name="paypal_secret" 
                                       value="<?php echo $currentSettings['paypal_secret']; ?>">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Email Settings -->
                <div class="tab-pane fade" id="email" role="tabpanel">
                    <div class="settings-card">
                        <h5 class="section-title">Email Configuration</h5>
                        
                        <div class="mb-4">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="email_notifications" name="email_notifications" 
                                       <?php echo $currentSettings['email_notifications'] === '1' ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="email_notifications">
                                    Enable Email Notifications
                                </label>
                            </div>
                            <small class="text-muted">Send order confirmations, password resets, and other email notifications</small>
                        </div>
                        
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i>
                            Email configuration requires SMTP settings to be configured in your server's PHP configuration.
                        </div>
                    </div>
                </div>

                <!-- System Settings -->
                <div class="tab-pane fade" id="system" role="tabpanel">
                    <div class="settings-card">
                        <h5 class="section-title">System Configuration</h5>
                        
                        <div class="mb-4">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="maintenance_mode" name="maintenance_mode" 
                                       <?php echo $currentSettings['maintenance_mode'] === '1' ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="maintenance_mode">
                                    Maintenance Mode
                                </label>
                            </div>
                            <small class="text-muted">Enable to show maintenance page to visitors. Admins can still access the site.</small>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="card">
                                    <div class="card-body">
                                        <h6 class="card-title">PHP Version</h6>
                                        <p class="card-text"><?php echo PHP_VERSION; ?></p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="card">
                                    <div class="card-body">
                                        <h6 class="card-title">Database</h6>
                                        <p class="card-text">MySQL</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mt-4">
                            <h6>System Information</h6>
                            <ul class="list-unstyled">
                                <li><strong>Upload Max File Size:</strong> <?php echo ini_get('upload_max_filesize'); ?></li>
                                <li><strong>Post Max Size:</strong> <?php echo ini_get('post_max_size'); ?></li>
                                <li><strong>Memory Limit:</strong> <?php echo ini_get('memory_limit'); ?></li>
                                <li><strong>Max Execution Time:</strong> <?php echo ini_get('max_execution_time'); ?>s</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>

            <div class="text-end mt-4">
                <button type="submit" class="btn btn-primary btn-lg">
                    <i class="fas fa-save me-2"></i> Save Settings
                </button>
            </div>
        </form>
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
            
            // Logo preview
            const logoInput = document.getElementById('site_logo');
            const logoPreview = document.querySelector('.logo-preview');
            
            if (logoInput && logoPreview) {
                logoInput.addEventListener('input', function() {
                    const url = this.value;
                    if (url) {
                        logoPreview.innerHTML = `<img src="${url}" alt="Logo" style="max-width: 100%; max-height: 100%;" onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                                               <i class="fas fa-image fa-2x text-muted" style="display:none;"></i>`;
                    } else {
                        logoPreview.innerHTML = '<i class="fas fa-image fa-2x text-muted"></i>';
                    }
                });
            }
        });
    </script>
</body>
</html>
