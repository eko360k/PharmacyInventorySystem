<?php
session_start();
require_once('./admin/includes/init.php');

$error_message = '';
$success_message = '';

// Check if user is already logged in
if (isset($_SESSION['user_id']) && $_SESSION['user_id'] !== '') {
    header('Location: admin/index.php');
    exit;
}

// Handle login form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = isset($_POST['email']) ? trim($_POST['email']) : '';
    $password = isset($_POST['password']) ? trim($_POST['password']) : '';
    $remember = isset($_POST['remember']) ? true : false;

    // Validate inputs
    if (empty($email)) {
        $error_message = "Email address is required.";
    } elseif (empty($password)) {
        $error_message = "Password is required.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error_message = "Please enter a valid email address.";
    } else {
        // Attempt login
        $login_result = $fn->loginUser($email, $password);

        if ($login_result['success']) {
            // Set session variables
            $_SESSION['user_id'] = $login_result['user_id'];
            $_SESSION['user_name'] = $login_result['user_name'];
            $_SESSION['user_email'] = $login_result['user_email'];
            $_SESSION['user_role'] = $login_result['user_role'];

            // Remember me functionality
            if ($remember) {
                setcookie('user_email', $email, time() + (86400 * 30), "/"); // 30 days
            }

            // Redirect to dashboard
            header('Location: admin/index.php');
            exit;
        } else {
            $error_message = $login_result['error'];
        }
    }
}

// Check if email is remembered
$remembered_email = isset($_COOKIE['user_email']) ? $_COOKIE['user_email'] : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Rosano Pharmacy Inventory Management</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/main.css">
    <link rel="stylesheet" href="assets/css/login.css">
</head>
<body>

<div class="blob-9348 blob-1-9348"></div>
<div class="blob-9348 blob-2-9348"></div>

<div class="auth-container-9348">
    <!-- Brand Side -->
    <div class="brand-side-9348">
        <div class="brand-logo-large-9348">R</div>
        <h2>Rosano</h2>
        <p>Pharmacy Inventory Management System</p>

        <div class="brand-features-9348">
            <div class="feature-item-9348">
                <i class="fas fa-box-open"></i>
                <div>
                    <strong>Inventory Management</strong>
                    <p>Real-time tracking of medicines and stock levels</p>
                </div>
            </div>
            <div class="feature-item-9348">
                <i class="fas fa-chart-line"></i>
                <div>
                    <strong>Sales Analytics</strong>
                    <p>Detailed reports on sales and revenue trends</p>
                </div>
            </div>
            <div class="feature-item-9348">
                <i class="fas fa-lock"></i>
                <div>
                    <strong>Secure Platform</strong>
                    <p>Enterprise-grade security for your data</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Form Side -->
    <div class="form-side-9348">
        <div class="mobile-brand-9348">
            <div class="logo-box-9348">R</div>
            <div class="brand-name-9348">Rosano</div>
        </div>

        <div class="welcome-text-9348">Welcome Back</div>
        <div class="sub-welcome-text-9348">Sign in to your account to continue</div>

        <?php if (!empty($error_message)): ?>
            <div class="alert-9348 alert-error-9348">
                <i class="fas fa-exclamation-circle"></i>
                <div class="alert-message-9348">
                    <strong>Login Failed</strong>
                    <?php echo htmlspecialchars($error_message); ?>
                </div>
            </div>
        <?php endif; ?>

        <form id="login-form-9348" method="POST">
            <!-- Email Field -->
            <div class="form-group-9348">
                <label class="label-9348">Email Address</label>
                <div class="input-wrapper-9348">
                    <i class="fas fa-envelope input-icon-9348"></i>
                    <input 
                        type="email" 
                        name="email" 
                        id="email" 
                        class="input-9348" 
                        placeholder="you@example.com"
                        value="<?php echo htmlspecialchars($remembered_email); ?>"
                        required
                    >
                </div>
            </div>

            <!-- Password Field -->
            <div class="form-group-9348">
                <label class="label-9348">Password</label>
                <div class="input-wrapper-9348">
                    <i class="fas fa-lock input-icon-9348"></i>
                    <input 
                        type="password" 
                        name="password" 
                        id="password" 
                        class="input-9348" 
                        placeholder="Enter your password"
                        required
                    >
                    <button type="button" class="toggle-password-9348" id="toggle-pw-9348">
                        <i class="fas fa-eye" id="pw-icon-9348"></i>
                    </button>
                </div>
            </div>

            <!-- Remember & Forgot -->
            <div class="options-row-9348">
                <label class="checkbox-label-9348">
                    <input type="checkbox" name="remember" class="checkbox-9348" id="remember-me">
                    Remember me
                </label>
                <a href="forgot-password.html" class="forgot-link-9348">Forgot password?</a>
            </div>

            <!-- Submit Button -->
            <button type="submit" id="submit-btn-9348" class="btn-submit-9348">
                <span class="btn-text-9348">
                    <i class="fas fa-sign-in-alt"></i>
                    Sign In
                </span>
            </button>
        </form>

        <!-- Sign Up Link -->
        <div class="signup-prompt-9348">
            Don't have an account? 
            <a href="register.php" class="signup-link-9348">Create one now</a>
        </div>
    </div>
</div>

<script>
(function() {
    const loginForm = document.getElementById('login-form-9348');
    const submitBtn = document.getElementById('submit-btn-9348');
    const togglePw = document.getElementById('toggle-pw-9348');
    const pwInput = document.getElementById('password');
    const pwIcon = document.getElementById('pw-icon-9348');

    // Password visibility toggle
    togglePw.addEventListener('click', function(e) {
        e.preventDefault();
        const isPassword = pwInput.type === 'password';
        pwInput.type = isPassword ? 'text' : 'password';
        pwIcon.classList.toggle('fa-eye');
        pwIcon.classList.toggle('fa-eye-slash');
    });

    // Form validation before submission
    loginForm.addEventListener('submit', function(e) {
        const email = document.getElementById('email').value.trim();
        const password = document.getElementById('password').value.trim();

        if (!email || !password) {
            e.preventDefault();
            alert('Please fill in all fields');
        }
    });
})();
</script>

</body>
</html>
