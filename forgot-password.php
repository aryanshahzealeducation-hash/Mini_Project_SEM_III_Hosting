<?php
require_once 'config.php';
require_once 'database.php';
require_once 'functions.php';

$db = new Database();
$error = '';
$success = '';
$step = 1;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = sanitize($_POST['email']);
    
    if (isset($_POST['step']) && $_POST['step'] == '2') {
        // Reset password step
        $token = sanitize($_POST['token']);
        $password = $_POST['password'];
        $confirm_password = $_POST['confirm_password'];
        
        // Validate token
        $user = $db->query("SELECT id FROM users WHERE reset_token = ? AND reset_expires > NOW()", [$token])->fetch();
        
        if (!$user) {
            $error = 'Invalid or expired reset token';
            $step = 1;
        } elseif (empty($password) || empty($confirm_password)) {
            $error = 'All fields are required';
        } elseif (strlen($password) < 6) {
            $error = 'Password must be at least 6 characters long';
        } elseif ($password !== $confirm_password) {
            $error = 'Passwords do not match';
        } else {
            // Update password
            $hashedPassword = hashPassword($password);
            $db->query(
                "UPDATE users SET password = ?, reset_token = NULL, reset_expires = NULL WHERE id = ?",
                [$hashedPassword, $user['id']]
            );
            
            $success = 'Password reset successfully! You can now login.';
            header('refresh:2;url=login.php');
        }
    } else {
        // Request reset step
        if (empty($email)) {
            $error = 'Email address is required';
        } elseif (!validateEmail($email)) {
            $error = 'Invalid email address';
        } else {
            // Check if user exists
            $user = $db->query("SELECT id, name FROM users WHERE email = ? AND status = 'active'", [$email])->fetch();
            
            if ($user) {
                // Generate reset token
                $token = generateToken();
                $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));
                
                // Update user with reset token
                $db->query(
                    "UPDATE users SET reset_token = ?, reset_expires = ? WHERE id = ?",
                    [$token, $expires, $user['id']]
                );
                
                // Send reset email (in production)
                $resetLink = SITE_URL . "forgot-password.php?token=" . $token;
                $subject = "Password Reset Request";
                $message = "
                    <h2>Password Reset</h2>
                    <p>Hello {$user['name']},</p>
                    <p>You requested a password reset for your account. Click the link below to reset your password:</p>
                    <p><a href='{$resetLink}'>Reset Password</a></p>
                    <p>This link will expire in 1 hour.</p>
                    <p>If you didn't request this, please ignore this email.</p>
                ";
                
                // sendEmail($email, $subject, $message);
                
                $success = 'Password reset link sent to your email. Please check your inbox.';
                $step = 2;
            } else {
                // Don't reveal if email exists or not
                $success = 'If an account with this email exists, a reset link has been sent.';
                $step = 2;
            }
        }
    }
}

// Check for token in URL
if (isset($_GET['token']) && !isset($_POST['step'])) {
    $token = sanitize($_GET['token']);
    $user = $db->query("SELECT id FROM users WHERE reset_token = ? AND reset_expires > NOW()", [$token])->fetch();
    
    if ($user) {
        $step = 2;
    } else {
        $error = 'Invalid or expired reset token';
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password - <?php echo SITE_NAME; ?></title>
    
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
        
        .forgot-container {
            background: white;
            border-radius: 15px;
            box-shadow: 0 15px 35px rgba(0,0,0,0.1);
            max-width: 500px;
            width: 100%;
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
        
        .icon-circle {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, var(--primary-color) 0%, #667eea 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 30px;
            color: white;
            font-size: 2rem;
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
    </style>
</head>
<body>
    <div class="forgot-container">
        <div class="text-center">
            <div class="icon-circle">
                <i class="fas fa-key"></i>
            </div>
            
            <?php if ($step == 1): ?>
                <h3 class="fw-bold mb-3">Forgot Password?</h3>
                <p class="text-muted mb-4">Enter your email address and we'll send you a link to reset your password</p>
            <?php else: ?>
                <h3 class="fw-bold mb-3">Reset Password</h3>
                <p class="text-muted mb-4">Enter your new password below</p>
            <?php endif; ?>
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
        
        <?php if ($step == 1): ?>
            <form method="POST">
                <div class="mb-4">
                    <label for="email" class="form-label">Email Address</label>
                    <div class="input-group">
                        <span class="input-group-text">
                            <i class="fas fa-envelope"></i>
                        </span>
                        <input type="email" class="form-control" id="email" name="email" 
                               value="<?php echo isset($_POST['email']) ? sanitize($_POST['email']) : ''; ?>"
                               placeholder="Enter your email address" required>
                    </div>
                </div>
                
                <button type="submit" class="btn btn-primary btn-lg w-100 mb-3">
                    <i class="fas fa-paper-plane me-2"></i> Send Reset Link
                </button>
                
                <div class="text-center">
                    <a href="login.php" class="text-decoration-none">
                        <i class="fas fa-arrow-left me-1"></i> Back to Login
                    </a>
                </div>
            </form>
        <?php else: ?>
            <form method="POST">
                <input type="hidden" name="step" value="2">
                <input type="hidden" name="token" value="<?php echo isset($_GET['token']) ? sanitize($_GET['token']) : ''; ?>">
                
                <div class="mb-3">
                    <label for="password" class="form-label">New Password</label>
                    <div class="input-group">
                        <span class="input-group-text">
                            <i class="fas fa-lock"></i>
                        </span>
                        <input type="password" class="form-control" id="password" name="password" 
                               placeholder="Enter new password" required>
                        <button class="btn btn-outline-secondary" type="button" id="togglePassword">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                    <div class="password-strength" id="passwordStrength"></div>
                    <small class="text-muted">Password must be at least 6 characters long</small>
                </div>
                
                <div class="mb-4">
                    <label for="confirm_password" class="form-label">Confirm New Password</label>
                    <div class="input-group">
                        <span class="input-group-text">
                            <i class="fas fa-lock"></i>
                        </span>
                        <input type="password" class="form-control" id="confirm_password" name="confirm_password" 
                               placeholder="Confirm new password" required>
                    </div>
                </div>
                
                <button type="submit" class="btn btn-primary btn-lg w-100 mb-3">
                    <i class="fas fa-check me-2"></i> Reset Password
                </button>
                
                <div class="text-center">
                    <a href="login.php" class="text-decoration-none">
                        <i class="fas fa-arrow-left me-1"></i> Back to Login
                    </a>
                </div>
            </form>
        <?php endif; ?>
    </div>

    <!-- MDB5 JS -->
    <script src="MDB5-STANDARD-UI-KIT-Free-9.2.0/js/mdb.umd.min.js"></script>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Password visibility toggle
            const togglePassword = document.getElementById('togglePassword');
            const passwordInput = document.getElementById('password');
            
            if (togglePassword && passwordInput) {
                togglePassword.addEventListener('click', function() {
                    const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
                    passwordInput.setAttribute('type', type);
                    this.innerHTML = type === 'password' ? '<i class="fas fa-eye"></i>' : '<i class="fas fa-eye-slash"></i>';
                });
                
                // Password strength indicator
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
            }
            
            // Form validation
            const forms = document.querySelectorAll('form');
            forms.forEach(form => {
                form.addEventListener('submit', function(e) {
                    const password = document.getElementById('password');
                    const confirmPassword = document.getElementById('confirm_password');
                    
                    if (password && confirmPassword) {
                        if (password.value !== confirmPassword.value) {
                            e.preventDefault();
                            alert('Passwords do not match!');
                            return false;
                        }
                    }
                });
            });
        });
    </script>
</body>
</html>
