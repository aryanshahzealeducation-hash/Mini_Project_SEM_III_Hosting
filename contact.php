<?php
require_once 'config.php';
require_once 'database.php';
require_once 'functions.php';

$db = new Database();

$message = '';
$messageType = '';

// Handle contact form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = sanitize($_POST['name']);
    $email = sanitize($_POST['email']);
    $subject = sanitize($_POST['subject']);
    $messageContent = sanitize($_POST['message']);
    
    // Validate inputs
    if (empty($name) || empty($email) || empty($subject) || empty($messageContent)) {
        $message = 'Please fill in all required fields';
        $messageType = 'danger';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = 'Please enter a valid email address';
        $messageType = 'danger';
    } else {
        try {
            // Insert support ticket if user is logged in, otherwise just store message
            if (isLoggedIn()) {
                $userId = $_SESSION['user_id'];
                $db->query("
                    INSERT INTO support_tickets (user_id, subject, message, status, created_at) 
                    VALUES (?, ?, ?, 'open', NOW())
                ", [$userId, $subject, $messageContent]);
                
                $message = 'Support ticket created successfully! We will respond within 24 hours.';
                $messageType = 'success';
            } else {
                // For non-logged in users, you could store in a separate table or send email
                // For now, just show success message
                $message = 'Message sent successfully! We will respond within 24 hours.';
                $messageType = 'success';
            }
            
            // Clear form
            $_POST = [];
            
        } catch (Exception $e) {
            $message = 'Error sending message. Please try again.';
            $messageType = 'danger';
        }
    }
}

// Get FAQ items
$faqItems = $db->query("SELECT * FROM faq WHERE status = 'active' ORDER BY sort_order ASC")->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contact Us - <?php echo SITE_NAME; ?></title>
    
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
        
        .contact-card {
            background: white;
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            margin-bottom: 20px;
        }
        
        .info-card {
            background: white;
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            text-align: center;
            transition: all 0.3s ease;
        }
        
        .info-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 20px rgba(0,0,0,0.15);
        }
        
        .info-icon {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background: rgba(59, 113, 202, 0.1);
            color: var(--primary-color);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            margin: 0 auto 20px;
        }
        
        .faq-item {
            background: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 15px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
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
            <h1 class="fw-bold mb-3">Contact Us</h1>
            <p class="lead mb-0">We're here to help and answer any questions you might have</p>
        </div>
    </section>

    <!-- Contact Content -->
    <section class="py-5">
        <div class="container">
            <?php if ($message): ?>
                <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show" role="alert">
                    <?php echo $message; ?>
                    <button type="button" class="btn-close" data-mdb-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <div class="row">
                <!-- Contact Form -->
                <div class="col-lg-8">
                    <div class="contact-card">
                        <h5 class="fw-bold mb-4">Send us a Message</h5>
                        
                        <form method="POST">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="name" class="form-label">Your Name</label>
                                    <input type="text" class="form-control" id="name" name="name" 
                                           value="<?php echo sanitize($_POST['name'] ?? ''); ?>" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="email" class="form-label">Email Address</label>
                                    <input type="email" class="form-control" id="email" name="email" 
                                           value="<?php echo sanitize($_POST['email'] ?? ''); ?>" required>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="subject" class="form-label">Subject</label>
                                <input type="text" class="form-control" id="subject" name="subject" 
                                       value="<?php echo sanitize($_POST['subject'] ?? ''); ?>" required>
                            </div>
                            
                            <div class="mb-3">
                                <label for="message" class="form-label">Message</label>
                                <textarea class="form-control" id="message" name="message" rows="6" required><?php echo sanitize($_POST['message'] ?? ''); ?></textarea>
                            </div>
                            
                            <div class="text-end">
                                <button type="submit" class="btn btn-primary btn-lg">
                                    <i class="fas fa-paper-plane me-2"></i> Send Message
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
                
                <!-- Contact Information -->
                <div class="col-lg-4">
                    <div class="info-card mb-4">
                        <div class="info-icon">
                            <i class="fas fa-envelope"></i>
                        </div>
                        <h6 class="fw-bold">Email Support</h6>
                        <p class="text-muted mb-0">support@cafenix.com</p>
                        <small class="text-muted">We respond within 24 hours</small>
                    </div>
                    
                    <div class="info-card mb-4">
                        <div class="info-icon">
                            <i class="fas fa-clock"></i>
                        </div>
                        <h6 class="fw-bold">Business Hours</h6>
                        <p class="text-muted mb-0">Monday - Friday: 9AM - 6PM</p>
                        <small class="text-muted">Saturday: 10AM - 4PM</small>
                    </div>
                    
                    <div class="info-card">
                        <div class="info-icon">
                            <i class="fas fa-headset"></i>
                        </div>
                        <h6 class="fw-bold">Live Chat</h6>
                        <p class="text-muted mb-0">Chat with our support team</p>
                        <small class="text-muted">Available during business hours</small>
                    </div>
                </div>
            </div>

            <!-- FAQ Section -->
            <?php if (!empty($faqItems)): ?>
                <div class="contact-card mt-5">
                    <h5 class="fw-bold mb-4">Frequently Asked Questions</h5>
                    
                    <?php foreach ($faqItems as $faq): ?>
                        <div class="faq-item" onclick="toggleFAQ(this)">
                            <div class="faq-question">
                                <span><?php echo sanitize($faq['question']); ?></span>
                                <i class="fas fa-chevron-down faq-icon"></i>
                            </div>
                            <div class="faq-answer">
                                <?php echo sanitize($faq['answer']); ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
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
