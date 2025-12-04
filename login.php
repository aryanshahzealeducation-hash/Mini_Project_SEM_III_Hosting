<?php
require_once 'config.php';
require_once 'database.php';
require_once 'functions.php';

$db = new Database();
$error = '';

// Redirect if already logged in
if (isLoggedIn()) {
    redirect('index.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = sanitize($_POST['email']);
    $password = $_POST['password'];
    $remember = isset($_POST['remember']);
    
    // Validation
    if (empty($email) || empty($password)) {
        $error = 'Email and password are required';
    } elseif (!validateEmail($email)) {
        $error = 'Invalid email address';
    } else {
        // Check user credentials
        $user = $db->query("SELECT * FROM users WHERE email = ? AND status = 'active'", [$email])->fetch();
        
        if ($user && verifyPassword($password, $user['password'])) {
            // Set session
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_name'] = $user['name'];
            $_SESSION['user_email'] = $user['email'];
            $_SESSION['user_role'] = $user['role'];
            
            // Update cart count
            $cartCount = $db->query("SELECT COUNT(*) as count FROM cart WHERE user_id = ?", [$user['id']])->fetch()['count'];
            $_SESSION['cart_count'] = $cartCount;
            
            // Remember me functionality
            if ($remember) {
                $token = generateToken();
                $expires = date('Y-m-d H:i:s', strtotime('+30 days'));
                
                // Store remember token in database (you'd need a remember_tokens table)
                setcookie('remember_token', $token, strtotime('+30 days'), '/', '', false, true);
            }
            
            // Redirect to intended page or dashboard
            $redirect = isset($_SESSION['redirect_url']) ? $_SESSION['redirect_url'] : 'index.php';
            unset($_SESSION['redirect_url']);
            redirect($redirect);
        } else {
            $error = 'Invalid email or password';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - <?php echo SITE_NAME; ?></title>
    
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
            background: linear-gradient(135deg, var(--primary-color) 0%, #667eea 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .login-container {
            background: white;
            border-radius: 15px;
            box-shadow: 0 15px 35px rgba(0,0,0,0.1);
            overflow: hidden;
            max-width: 900px;
            width: 100%;
        }
        
        .login-image {
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 800 600"><rect fill="%233b71ca" width="800" height="600"/><path fill="%23ffffff" fill-opacity="0.1" d="M0,100 L200,300 L0,500 Z"/><circle cx="600" cy="200" r="100" fill="%23ffffff" fill-opacity="0.1"/><circle cx="200" cy="400" r="50" fill="%23ffffff" fill-opacity="0.1"/></svg>') center/cover;
            min-height: 400px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            text-align: center;
            padding: 40px;
        }
        
        .login-form {
            padding: 40px;
        }
        
        .form-control:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.2rem rgba(59, 113, 202, 0.25);
        }
        
        .btn-primary {
            background: var(--primary-color);
            border: none;
            padding: 12px 30px;
            font-weight: 500;
            transition: all 0.3s ease;
        }
        
        .btn-primary:hover {
            background: #2f5aa2;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(59, 113, 202, 0.3);
        }
        
        .social-login {
            border-top: 1px solid #dee2e6;
            padding-top: 20px;
            margin-top: 20px;
        }
        
        .social-btn {
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 10px;
            border: 1px solid #dee2e6;
            border-radius: 8px;
            text-decoration: none;
            color: #495057;
            transition: all 0.3s ease;
            margin-bottom: 10px;
        }
        
        .social-btn:hover {
            background: #f8f9fa;
            color: #212529;
            transform: translateY(-2px);
        }
        
        @media (max-width: 768px) {
            .login-image {
                display: none;
            }
            
            .login-form {
                padding: 30px 20px;
            }
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="row g-0">
            <!-- Left side - Image/Branding -->
            <div class="col-lg-6">
                <div class="login-image">
                    <div>
                        <i class="fas fa-coffee fa-4x mb-4"></i>
                        <h2 class="fw-bold mb-3">Welcome Back!</h2>
                        <p class="lead">Login to access your cafe menu items and continue your experience</p>
                    </div>
                </div>
            </div>
            
            <!-- Right side - Login Form -->
            <div class="col-lg-6">
                <div class="login-form">
                    <div class="text-center mb-4">
                        <h3 class="fw-bold">Login to Your Account</h3>
                        <p class="text-muted">Enter your credentials to continue</p>
                    </div>
                    
                    <?php if ($error): ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            <?php echo $error; ?>
                            <button type="button" class="btn-close" data-mdb-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>
                    
                    <form method="POST" id="loginForm">
                        <div class="mb-3">
                            <label for="email" class="form-label">Email Address</label>
                            <div class="input-group">
                                <span class="input-group-text">
                                    <i class="fas fa-envelope"></i>
                                </span>
                                <input type="email" class="form-control" id="email" name="email" 
                                       value="<?php echo isset($_POST['email']) ? sanitize($_POST['email']) : ''; ?>"
                                       placeholder="Enter your email" required>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="password" class="form-label">Password</label>
                            <div class="input-group">
                                <span class="input-group-text">
                                    <i class="fas fa-lock"></i>
                                </span>
                                <input type="password" class="form-control" id="password" name="password" 
                                       placeholder="Enter your password" required>
                                <button class="btn btn-outline-secondary" type="button" id="togglePassword">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                        </div>
                        
                        <div class="d-flex justify-content-between align-items-center mb-4">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="remember" name="remember">
                                <label class="form-check-label" for="remember">
                                    Remember me
                                </label>
                            </div>
                            <a href="forgot-password.php" class="text-decoration-none">Forgot Password?</a>
                        </div>
                        
                        <button type="submit" class="btn btn-primary btn-lg w-100 mb-3">
                            <i class="fas fa-sign-in-alt me-2"></i> Login
                        </button>
                        
                        <div class="text-center">
                            <p class="mb-0">Don't have an account? <a href="signup.php" class="text-decoration-none">Sign up here</a></p>
                        </div>
                    </form>
                    
                    <!-- Social Login (Optional) -->
                    <div class="social-login">
                        <p class="text-center text-muted mb-3">Or continue with</p>
                        <div class="d-grid gap-2">
                            <a href="#" class="social-btn">
                                <i class="fab fa-google me-2"></i> Continue with Google
                            </a>
                            <a href="#" class="social-btn">
                                <i class="fab fa-github me-2"></i> Continue with GitHub
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- MDB5 JS -->
    <script src="MDB5-STANDARD-UI-KIT-Free-9.2.0/js/mdb.umd.min.js"></script>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Password visibility toggle
            const togglePassword = document.getElementById('togglePassword');
            const passwordInput = document.getElementById('password');
            
            togglePassword.addEventListener('click', function() {
                const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
                passwordInput.setAttribute('type', type);
                this.innerHTML = type === 'password' ? '<i class="fas fa-eye"></i>' : '<i class="fas fa-eye-slash"></i>';
            });
            
            // Form validation
            const form = document.getElementById('loginForm');
            form.addEventListener('submit', function(e) {
                const email = document.getElementById('email').value;
                const password = document.getElementById('password').value;
                
                if (!email || !password) {
                    e.preventDefault();
                    alert('Please fill in all fields');
                    return false;
                }
            });
        });
    </script>
</body>
</html>
