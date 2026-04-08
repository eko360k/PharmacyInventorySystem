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

// Handle registration form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = isset($_POST['full_name']) ? trim($_POST['full_name']) : '';
    $email = isset($_POST['email']) ? trim($_POST['email']) : '';
    $password = isset($_POST['password']) ? trim($_POST['password']) : '';
    $password_confirm = isset($_POST['password_confirm']) ? trim($_POST['password_confirm']) : '';
    $role = isset($_POST['role']) ? trim($_POST['role']) : 'pharmacist';

    // Validation
    if (empty($full_name)) {
        $error_message = "Full name is required.";
    } elseif (strlen($full_name) < 3) {
        $error_message = "Full name must be at least 3 characters long.";
    } elseif (empty($email)) {
        $error_message = "Email address is required.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error_message = "Please enter a valid email address.";
    } elseif (empty($password)) {
        $error_message = "Password is required.";
    } elseif (strlen($password) < 6) {
        $error_message = "Password must be at least 6 characters long.";
    } elseif ($password !== $password_confirm) {
        $error_message = "Passwords do not match.";
    } elseif (!$fn->validatePasswordStrength($password)) {
        $error_message = "Password must contain at least one uppercase letter, one number, and one special character.";
    } else {
        // Check if email already exists
        if ($fn->emailExists($email)) {
            $error_message = "An account with this email address already exists.";
        } else {
            // Attempt registration
            $register_result = $fn->registerUser($full_name, $email, $password, $role);

            if ($register_result['success']) {
                $success_message = "Account created successfully! Redirecting to login...";
                header('Refresh: 2; url=login.php');
            } else {
                $error_message = $register_result['error'];
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - Rosano Pharmacy Inventory Management</title>
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
                <i class="fas fa-user-shield"></i>
                <div>
                    <strong>Secure Access</strong>
                    <p>Password protected accounts for all users</p>
                </div>
            </div>
            <div class="feature-item-9348">
                <i class="fas fa-users"></i>
                <div>
                    <strong>Role-Based Control</strong>
                    <p>Different access levels for pharmacists & staff</p>
                </div>
            </div>
            <div class="feature-item-9348">
                <i class="fas fa-check-circle"></i>
                <div>
                    <strong>Easy Setup</strong>
                    <p>Quick registration in just a few steps</p>
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

        <div class="welcome-text-9348">Create Account</div>
        <div class="sub-welcome-text-9348">Join our pharmacy management system</div>

        <?php if (!empty($error_message)): ?>
            <div class="alert-9348 alert-error-9348">
                <i class="fas fa-exclamation-circle"></i>
                <div class="alert-message-9348">
                    <strong>Registration Error</strong>
                    <?php echo htmlspecialchars($error_message); ?>
                </div>
            </div>
        <?php endif; ?>

        <?php if (!empty($success_message)): ?>
            <div class="alert-9348 alert-success-9348">
                <i class="fas fa-check-circle"></i>
                <div class="alert-message-9348">
                    <strong>Success!</strong>
                    <?php echo htmlspecialchars($success_message); ?>
                </div>
            </div>
        <?php endif; ?>

        <form id="register-form-9348" method="POST">
            <!-- Full Name Field -->
            <div class="form-group-9348">
                <label class="label-9348">Full Name</label>
                <div class="input-wrapper-9348">
                    <i class="fas fa-user input-icon-9348"></i>
                    <input 
                        type="text" 
                        name="full_name" 
                        id="full_name" 
                        class="input-9348" 
                        placeholder="John Doe"
                        value="<?php echo isset($_POST['full_name']) ? htmlspecialchars($_POST['full_name']) : ''; ?>"
                        required
                    >
                </div>
            </div>

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
                        value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>"
                        required
                    >
                </div>
            </div>

            <!-- Role Selection -->
            <div class="form-group-9348">
                <label class="label-9348">User Role</label>
                <div class="input-wrapper-9348">
                    <i class="fas fa-briefcase input-icon-9348"></i>
                    <select name="role" id="role" class="input-9348" required>
                        <option value="pharmacist" <?php echo (isset($_POST['role']) && $_POST['role'] === 'pharmacist') ? 'selected' : ''; ?>>Pharmacist</option>
                        <option value="cashier" <?php echo (isset($_POST['role']) && $_POST['role'] === 'cashier') ? 'selected' : ''; ?>>Cashier</option>
                    </select>
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
                        placeholder="Enter a strong password"
                        required
                    >
                    <button type="button" class="toggle-password-9348" id="toggle-pw-9348">
                        <i class="fas fa-eye" id="pw-icon-9348"></i>
                    </button>
                </div>
                <div class="password-strength-9348">
                    <div class="password-strength-bar-9348" id="strength-bar-9348"></div>
                </div>
                <div class="password-strength-text-9348" id="strength-text-9348"></div>
            </div>

            <!-- Password Confirmation Field -->
            <div class="form-group-9348">
                <label class="label-9348">Confirm Password</label>
                <div class="input-wrapper-9348">
                    <i class="fas fa-lock input-icon-9348"></i>
                    <input 
                        type="password" 
                        name="password_confirm" 
                        id="password_confirm" 
                        class="input-9348" 
                        placeholder="Re-enter your password"
                        required
                    >
                    <button type="button" class="toggle-password-9348" id="toggle-pw-confirm-9348">
                        <i class="fas fa-eye" id="pw-confirm-icon-9348"></i>
                    </button>
                </div>
            </div>

            <!-- Submit Button -->
            <button type="submit" id="submit-btn-9348" class="btn-submit-9348">
                <span class="btn-text-9348">
                    <i class="fas fa-user-plus"></i>
                    Create Account
                </span>
            </button>
        </form>

        <!-- Login Link -->
        <div class="signup-prompt-9348">
            Already have an account? 
            <a href="login.php" class="signup-link-9348">Sign in here</a>
        </div>
    </div>
</div>

<script>
(function() {
    const registerForm = document.getElementById('register-form-9348');
    const togglePw = document.getElementById('toggle-pw-9348');
    const pwInput = document.getElementById('password');
    const pwIcon = document.getElementById('pw-icon-9348');
    const togglePwConfirm = document.getElementById('toggle-pw-confirm-9348');
    const pwConfirmInput = document.getElementById('password_confirm');
    const pwConfirmIcon = document.getElementById('pw-confirm-icon-9348');
    const strengthBar = document.getElementById('strength-bar-9348');
    const strengthText = document.getElementById('strength-text-9348');

    // Password visibility toggle
    togglePw.addEventListener('click', function(e) {
        e.preventDefault();
        const isPassword = pwInput.type === 'password';
        pwInput.type = isPassword ? 'text' : 'password';
        pwIcon.classList.toggle('fa-eye');
        pwIcon.classList.toggle('fa-eye-slash');
    });

    togglePwConfirm.addEventListener('click', function(e) {
        e.preventDefault();
        const isPassword = pwConfirmInput.type === 'password';
        pwConfirmInput.type = isPassword ? 'text' : 'password';
        pwConfirmIcon.classList.toggle('fa-eye');
        pwConfirmIcon.classList.toggle('fa-eye-slash');
    });

    // Password strength checker
    pwInput.addEventListener('input', function() {
        const password = this.value;
        let strength = 0;

        if (password.length >= 6) strength++;
        if (password.length >= 8) strength++;
        if (/[a-z]/.test(password) && /[A-Z]/.test(password)) strength++;
        if (/\d/.test(password)) strength++;
        if (/[!@#$%^&*()_+\-=\[\]{};':"\\|,.<>\/?]/.test(password)) strength++;

        strengthBar.className = 'password-strength-bar-9348';
        
        if (password.length === 0) {
            strengthText.textContent = '';
        } else if (strength <= 2) {
            strengthBar.classList.add('weak-9348');
            strengthText.textContent = 'Weak password';
            strengthText.style.color = '#991b1b';
        } else if (strength <= 3) {
            strengthBar.classList.add('medium-9348');
            strengthText.textContent = 'Medium password';
            strengthText.style.color = '#854d0e';
        } else {
            strengthBar.classList.add('strong-9348');
            strengthText.textContent = 'Strong password';
            strengthText.style.color = '#166534';
        }
    });

    // Form validation before submission
    registerForm.addEventListener('submit', function(e) {
        const fullName = document.getElementById('full_name').value.trim();
        const email = document.getElementById('email').value.trim();
        const password = document.getElementById('password').value.trim();
        const passwordConfirm = document.getElementById('password_confirm').value.trim();

        if (!fullName || !email || !password || !passwordConfirm) {
            e.preventDefault();
            alert('Please fill in all required fields');
            return;
        }

        if (password !== passwordConfirm) {
            e.preventDefault();
            alert('Passwords do not match');
            return;
        }
    });
})();
</script>

</body>
</html>
