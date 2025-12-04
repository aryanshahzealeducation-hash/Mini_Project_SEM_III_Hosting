<?php
require_once 'config.php';
require_once 'database.php';
require_once 'functions.php';

$db = new Database();
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = sanitize($_POST['name']);
    $email = sanitize($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    
    // Validation
    if (empty($name) || empty($email) || empty($password)) {
        $error = 'All fields are required';
    } elseif (!validateEmail($email)) {
        $error = 'Invalid email address';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters long';
    } elseif ($password !== $confirm_password) {
        $error = 'Passwords do not match';
    } else {
        // Check if email already exists
        $existing = $db->query("SELECT id FROM users WHERE email = ?", [$email])->fetch();
        
        if ($existing) {
            $error = 'Email address already registered';
        } else {
            // Create new user
            $hashedPassword = hashPassword($password);
            $verificationToken = generateToken();
            
            $db->query(
                "INSERT INTO users (name, email, password, verification_token) VALUES (?, ?, ?, ?)",
                [$name, $email, $hashedPassword, $verificationToken]
            );
            
            // Send verification email (in production)
            // $verificationLink = SITE_URL . "verify.php?token=" . $verificationToken;
            // sendEmail($email, 'Verify your email', "Click here to verify: $verificationLink");
            
            $success = 'Account created successfully! You can now login.';
            
            // Redirect to login after 2 seconds
            header('refresh:2;url=login.php');
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign Up - <?php echo SITE_NAME; ?></title>
    
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
        }
        
        .signup-container {
            background: white;
            border-radius: 15px;
            box-shadow: 0 15px 35px rgba(0,0,0,0.1);
            overflow: hidden;
            max-width: 1000px;
            width: 100%;
            margin: 20px;
        }
        
        .signup-image {
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 800 600"><rect fill="%233b71ca" width="800" height="600"/><path fill="%23ffffff" fill-opacity="0.1" d="M0,100 L200,300 L0,500 Z"/><circle cx="600" cy="200" r="100" fill="%23ffffff" fill-opacity="0.1"/><circle cx="200" cy="400" r="50" fill="%23ffffff" fill-opacity="0.1"/></svg>') center/cover;
            min-height: 500px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            text-align: center;
            padding: 40px;
        }
        
        .signup-form {
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
        
        .password-strength {
            height: 4px;
            border-radius: 2px;
            margin-top: 5px;
            transition: all 0.3s ease;
        }
        
        .strength-weak { background: #dc4c64; width: 33%; }
        .strength-medium { background: #e4a11b; width: 66%; }
        .strength-strong { background: #14a44d; width: 100%; }
        
        @media (max-width: 768px) {
            .signup-image {
                display: none;
            }
            
            .signup-form {
                padding: 30px 20px;
            }
            
            body {
                padding: 20px;
            }
        }
    </style>
</head>
<body>
    <div class="signup-container">
        <div class="row g-0">
            <!-- Left side - Image/Branding -->
            <div class="col-lg-6">
                <div class="signup-image">
                    <div>
                        <i class="fas fa-coffee fa-4x mb-4"></i>
                        <h2 class="fw-bold mb-3">Join <?php echo SITE_NAME; ?></h2>
                        <p class="lead">Discover amazing cafe menu items and resources for your experience</p>
                        <div class="mt-4">
                            <div class="d-flex align-items-center mb-3">
                                <i class="fas fa-mug-hot me-3"></i>
                                <span>Instant Service</span>
                            </div>
                            <div class="d-flex align-items-center mb-3">
                                <i class="fas fa-shield-alt me-3"></i>
                                <span>Secure Payments</span>
                            </div>
                            <div class="d-flex align-items-center">
                                <i class="fas fa-headset me-3"></i>
                                <span>24/7 Support</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Right side - Signup Form -->
            <div class="col-lg-6">
                <div class="signup-form">
                    <div class="text-center mb-4">
                        <h3 class="fw-bold">Create Account</h3>
                        <p class="text-muted">Fill in the details to get started</p>
                    </div>
                    
                    <?php if ($error): ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            <?php echo $error; ?>
                            <button type="button" class="btn-close" data-mdb-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($success): ?>
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            <i class="fas fa-check-circle me-2"></i>
                            <?php echo $success; ?>
                            <button type="button" class="btn-close" data-mdb-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>
                    
                    <form method="POST" id="signupForm">
                        <div class="mb-3">
                            <label for="name" class="form-label">Full Name</label>
                            <div class="input-group">
                                <span class="input-group-text">
                                    <i class="fas fa-user"></i>
                                </span>
                                <input type="text" class="form-control" id="name" name="name" 
                                       value="<?php echo isset($_POST['name']) ? sanitize($_POST['name']) : ''; ?>"
                                       placeholder="Enter your full name" required>
                            </div>
                        </div>
                        
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
                            <div class="password-strength" id="passwordStrength"></div>
                            <small class="text-muted">Password must be at least 6 characters long</small>
                        </div>
                        
                        <div class="mb-4">
                            <label for="confirm_password" class="form-label">Confirm Password</label>
                            <div class="input-group">
                                <span class="input-group-text">
                                    <i class="fas fa-lock"></i>
                                </span>
                                <input type="password" class="form-control" id="confirm_password" name="confirm_password" 
                                       placeholder="Confirm your password" required>
                            </div>
                        </div>
                        
                        <div class="mb-4">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="terms" required>
                                <label class="form-check-label" for="terms">
                                    I agree to the <a href="terms.php" target="_blank">Terms & Conditions</a>
                                </label>
                            </div>
                        </div>
                        
                        <button type="submit" class="btn btn-primary btn-lg w-100 mb-3">
                            <i class="fas fa-user-plus me-2"></i> Create Account
                        </button>
                        
                        <div class="text-center">
                            <p class="mb-0">Already have an account? <a href="login.php" class="text-decoration-none">Login here</a></p>
                        </div>
                    </form>
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
            
            // Password strength indicator
            const passwordInput = document.getElementById('password');
            const passwordStrength = document.getElementById('passwordStrength');
            
            passwordInput.addEventListener('input', function() {
                const password = this.value;
                let strength = 0;
                
                if (password.length >= 6) strength++;
                if (password.match(/[a-z]/) && password.match(/[A-Z]/)) strength++;
                if (password.match(/[0-9]/)) strength++;
                if (password.match(/[^a-zA-Z0-9]/)) strength++;
                
                passwordStrength.className = 'password-strength';
                
                if (password.length > 0) {
                    if (strength <= 1) {
                        passwordStrength.classList.add('strength-weak');
                    } else if (strength === 2) {
                        passwordStrength.classList.add('strength-medium');
                    } else {
                        passwordStrength.classList.add('strength-strong');
                    }
                }
            });
            
            // Form validation
            const form = document.getElementById('signupForm');
            form.addEventListener('submit', function(e) {
                const password = document.getElementById('password').value;
                const confirmPassword = document.getElementById('confirm_password').value;
                
                if (password !== confirmPassword) {
                    e.preventDefault();
                    alert('Passwords do not match!');
                    return false;
                }
                
                if (!document.getElementById('terms').checked) {
                    e.preventDefault();
                    alert('Please accept the terms and conditions');
                    return false;
                }
            });
        });
    </script>
</body>
</html>
