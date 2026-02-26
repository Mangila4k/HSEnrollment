<?php
session_start();
include("../config/database.php");

// Check if already logged in
if(isset($_SESSION['user'])) {
    header("Location: ../index.php");
    exit();
}

$error = '';
$success = '';

if(isset($_POST['register'])){
    // Get form data
    $fullname = trim($_POST['fullname']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $id_number = !empty($_POST['id_number']) ? trim($_POST['id_number']) : null;
    
    // Validation
    $errors = [];
    
    if(empty($fullname)) {
        $errors[] = "Full name is required";
    }
    
    if(empty($email)) {
        $errors[] = "Email address is required";
    } elseif(!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid email format";
    }
    
    if(empty($password)) {
        $errors[] = "Password is required";
    } elseif(strlen($password) < 6) {
        $errors[] = "Password must be at least 6 characters";
    }
    
    if($password !== $confirm_password) {
        $errors[] = "Passwords do not match";
    }

    // Check if email already exists
    if(empty($errors)) {
        $check = $conn->prepare("SELECT id FROM users WHERE email = ?");
        $check->bind_param("s", $email);
        $check->execute();
        $check->store_result();
        
        if($check->num_rows > 0) {
            $errors[] = "Email already registered!";
        }
        $check->close();
    }
    
    // Check if ID number already exists (if provided)
    if(empty($errors) && $id_number) {
        $check_id = $conn->prepare("SELECT id FROM users WHERE id_number = ?");
        $check_id->bind_param("s", $id_number);
        $check_id->execute();
        $check_id->store_result();
        
        if($check_id->num_rows > 0) {
            $errors[] = "ID number already exists!";
        }
        $check_id->close();
    }

    // If no errors, insert the user
    if(empty($errors)) {
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        $role = "Student"; // Default role for registration
        
        // Insert based on whether ID number is provided
        if($id_number) {
            $stmt = $conn->prepare("INSERT INTO users (id_number, fullname, email, password, role) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("sssss", $id_number, $fullname, $email, $hashed_password, $role);
        } else {
            $stmt = $conn->prepare("INSERT INTO users (fullname, email, password, role) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("ssss", $fullname, $email, $hashed_password, $role);
        }
        
        if($stmt->execute()) {
            $success = "Registration successful! You can now login.";
            // Clear form data
            $_POST = array();
        } else {
            $errors[] = "Registration failed: " . $conn->error;
        }
        $stmt->close();
    }
    
    // If there are errors, combine them
    if(!empty($errors)) {
        $error = implode("<br>", $errors);
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Registration - Placido L. Señor Senior High School</title>
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #0B4F2E, #1a7a42);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
            position: relative;
            overflow-x: hidden;
        }

        /* Floating background shapes */
        .floating-shape {
            position: absolute;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 50%;
            z-index: 0;
        }

        .shape-1 {
            width: 300px;
            height: 300px;
            top: -100px;
            right: -100px;
            background: radial-gradient(circle, rgba(255,255,255,0.2) 0%, rgba(255,255,255,0.05) 70%);
        }

        .shape-2 {
            width: 400px;
            height: 400px;
            bottom: -150px;
            left: -150px;
            background: radial-gradient(circle, rgba(255,215,0,0.15) 0%, rgba(255,215,0,0.03) 70%);
        }

        .register-container {
            background: white;
            width: 100%;
            max-width: 500px;
            border-radius: 30px;
            padding: 50px 40px;
            box-shadow: 0 25px 50px rgba(0, 0, 0, 0.3);
            position: relative;
            z-index: 1;
            animation: slideUp 0.6s ease;
        }

        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .logo-section {
            text-align: center;
            margin-bottom: 30px;
        }

        .school-logo {
            width: 100px;
            height: 100px;
            background: linear-gradient(135deg, #0B4F2E, #1a7a42);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            font-size: 42px;
            font-weight: bold;
            color: white;
            border: 4px solid #FFD700;
            box-shadow: 0 10px 25px rgba(11, 79, 46, 0.3);
        }

        .logo-section h1 {
            font-size: 24px;
            color: #0B4F2E;
            margin-bottom: 5px;
            font-weight: 700;
        }

        .logo-section p {
            font-size: 14px;
            color: #666;
            font-style: italic;
        }

        h2 {
            font-size: 28px;
            color: #333;
            margin-bottom: 10px;
            text-align: center;
        }

        .alert {
            padding: 15px 20px;
            border-radius: 12px;
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            gap: 10px;
            animation: slideIn 0.3s ease;
        }

        .alert-error {
            background: #fee2e2;
            color: #dc2626;
            border-left: 4px solid #dc2626;
        }

        .alert-success {
            background: #d4edda;
            color: #155724;
            border-left: 4px solid #28a745;
        }

        .alert i {
            font-size: 18px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #555;
            font-weight: 600;
            font-size: 14px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .form-group label span {
            color: #dc3545;
            margin-left: 3px;
        }

        .input-wrapper {
            position: relative;
        }

        .input-wrapper input {
            width: 100%;
            padding: 14px 45px 14px 16px;
            border: 2px solid #e0e0e0;
            border-radius: 12px;
            font-size: 15px;
            transition: all 0.3s;
            background-color: #f8f9fa;
        }

        .input-wrapper input:focus {
            border-color: #0B4F2E;
            background-color: #ffffff;
            outline: none;
            box-shadow: 0 0 0 4px rgba(11, 79, 46, 0.1);
        }

        .input-wrapper input.error {
            border-color: #dc3545;
            background-color: #fff8f8;
        }

        .input-icon {
            position: absolute;
            right: 16px;
            top: 50%;
            transform: translateY(-50%);
            color: #666;
            opacity: 0.5;
        }

        .toggle-password {
            position: absolute;
            right: 16px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            cursor: pointer;
            color: #666;
            transition: color 0.3s;
            z-index: 2;
            font-size: 16px;
        }

        .toggle-password:hover {
            color: #0B4F2E;
        }

        .password-strength {
            margin-top: 8px;
            height: 4px;
            background: #e0e0e0;
            border-radius: 2px;
            overflow: hidden;
        }

        .strength-bar {
            height: 100%;
            width: 0;
            transition: all 0.3s;
        }

        .strength-bar.weak {
            width: 33.33%;
            background-color: #ef4444;
        }

        .strength-bar.medium {
            width: 66.66%;
            background-color: #f59e0b;
        }

        .strength-bar.strong {
            width: 100%;
            background-color: #10b981;
        }

        .strength-text {
            font-size: 12px;
            margin-top: 5px;
            color: #666;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .form-hint {
            font-size: 12px;
            color: #666;
            margin-top: 5px;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .form-hint i {
            color: #0B4F2E;
        }

        .terms {
            margin: 20px 0;
            color: #666;
            font-size: 14px;
            text-align: center;
        }

        .terms a {
            color: #0B4F2E;
            text-decoration: none;
            font-weight: 600;
        }

        .terms a:hover {
            text-decoration: underline;
        }

        .btn-register {
            width: 100%;
            background: linear-gradient(135deg, #0B4F2E, #1a7a42);
            color: white;
            padding: 14px;
            border: none;
            border-radius: 12px;
            font-size: 16px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1px;
            cursor: pointer;
            transition: all 0.3s;
            margin: 20px 0 15px;
            position: relative;
        }

        .btn-register:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(11, 79, 46, 0.3);
        }

        .btn-register.loading {
            opacity: 0.7;
            cursor: not-allowed;
        }

        .btn-register.loading::after {
            content: '';
            position: absolute;
            width: 20px;
            height: 20px;
            border: 2px solid white;
            border-top-color: transparent;
            border-radius: 50%;
            animation: spin 1s linear infinite;
            right: 20px;
            top: 50%;
            transform: translateY(-50%);
        }

        @keyframes spin {
            to { transform: translateY(-50%) rotate(360deg); }
        }

        .login-link {
            text-align: center;
            margin: 15px 0;
            color: #666;
            font-size: 15px;
        }

        .login-link a {
            color: #0B4F2E;
            text-decoration: none;
            font-weight: 600;
        }

        .login-link a:hover {
            text-decoration: underline;
        }

        .form-footer {
            text-align: center;
            margin-top: 15px;
        }

        .back-home {
            color: #666;
            text-decoration: none;
            font-size: 14px;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: color 0.3s;
        }

        .back-home:hover {
            color: #0B4F2E;
        }

        /* Responsive */
        @media (max-width: 480px) {
            .register-container {
                padding: 30px 20px;
            }

            .logo-section h1 {
                font-size: 20px;
            }

            h2 {
                font-size: 24px;
            }
        }
    </style>
</head>
<body>
    <!-- Floating background shapes -->
    <div class="floating-shape shape-1"></div>
    <div class="floating-shape shape-2"></div>

    <div class="register-container">
        <!-- Logo Section -->
        <div class="logo-section">
            <div class="school-logo">
                <span>PLS</span>
            </div>
            <h1>Placido L. Señor</h1>
            <p>Senior High School</p>
        </div>

        <h2>Student Registration</h2>
        
        <?php if($error): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i>
                <?php echo $error; ?>
            </div>
        <?php endif; ?>

        <?php if($success): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                <?php echo $success; ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="" id="registerForm">
            <div class="form-group">
                <label for="fullname">Full Name <span>*</span></label>
                <div class="input-wrapper">
                    <input type="text" id="fullname" name="fullname" placeholder="Enter your full name" 
                           value="<?php echo isset($_POST['fullname']) ? htmlspecialchars($_POST['fullname']) : ''; ?>" required>
                    <i class="fas fa-user input-icon"></i>
                </div>
            </div>

            <div class="form-group">
                <label for="email">Email Address <span>*</span></label>
                <div class="input-wrapper">
                    <input type="email" id="email" name="email" placeholder="Enter your email" 
                           value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>" required>
                    <i class="fas fa-envelope input-icon"></i>
                </div>
            </div>

            <div class="form-group">
                <label for="id_number">Student ID <span style="color:#666; font-size:11px;">(Optional)</span></label>
                <div class="input-wrapper">
                    <input type="text" id="id_number" name="id_number" placeholder="Enter your student ID (optional)" 
                           value="<?php echo isset($_POST['id_number']) ? htmlspecialchars($_POST['id_number']) : ''; ?>">
                    <i class="fas fa-id-card input-icon"></i>
                </div>
                <div class="form-hint">
                    <i class="fas fa-info-circle"></i>
                    Leave blank if you don't have an ID yet
                </div>
            </div>

            <div class="form-group">
                <label for="password">Password <span>*</span></label>
                <div class="input-wrapper">
                    <input type="password" id="password" name="password" placeholder="Create a password" required>
                    <button type="button" class="toggle-password" onclick="togglePassword()">
                        <i class="fas fa-eye"></i>
                    </button>
                </div>
                <div class="password-strength">
                    <div class="strength-bar" id="strengthBar"></div>
                </div>
                <div class="strength-text" id="strengthText">
                    <i class="fas fa-info-circle"></i>
                    <span>Enter password (min. 6 characters)</span>
                </div>
            </div>

            <div class="form-group">
                <label for="confirm_password">Confirm Password <span>*</span></label>
                <div class="input-wrapper">
                    <input type="password" id="confirm_password" name="confirm_password" placeholder="Confirm your password" required>
                    <i class="fas fa-lock input-icon"></i>
                </div>
                <div class="strength-text" id="passwordMatch">
                    <i class="fas fa-info-circle"></i>
                    <span>Re-enter your password</span>
                </div>
            </div>

            <div class="terms">
                By registering, you agree to our <a href="#">Terms of Service</a> and <a href="#">Privacy Policy</a>
            </div>

            <button type="submit" name="register" class="btn-register" id="registerBtn">
                Create Account
            </button>
        </form>

        <div class="login-link">
            Already have an account? <a href="login.php">Sign In</a>
        </div>

        <div class="form-footer">
            <a href="../index.php" class="back-home">
                <i class="fas fa-arrow-left"></i> Back to Home
            </a>
        </div>
    </div>

    <script>
        // Toggle password visibility
        function togglePassword() {
            const passwordInput = document.getElementById('password');
            const toggleBtn = document.querySelector('.toggle-password i');
            
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                toggleBtn.className = 'fas fa-eye-slash';
            } else {
                passwordInput.type = 'password';
                toggleBtn.className = 'fas fa-eye';
            }
        }

        // Password strength checker
        document.getElementById('password').addEventListener('input', function() {
            const password = this.value;
            const strengthBar = document.getElementById('strengthBar');
            const strengthText = document.getElementById('strengthText');
            
            // Remove existing classes
            strengthBar.className = 'strength-bar';
            
            if (password.length === 0) {
                strengthBar.style.width = '0';
                strengthText.innerHTML = '<i class="fas fa-info-circle"></i> <span>Enter password (min. 6 characters)</span>';
                return;
            }
            
            // Check strength
            let strength = 0;
            
            // Length check
            if (password.length >= 6) strength += 1;
            if (password.length >= 8) strength += 1;
            
            // Character variety
            if (/[a-z]/.test(password)) strength += 1;
            if (/[A-Z]/.test(password)) strength += 1;
            if (/[0-9]/.test(password)) strength += 1;
            if (/[^A-Za-z0-9]/.test(password)) strength += 1;
            
            // Update UI based on strength
            if (password.length < 6) {
                strengthBar.classList.add('weak');
                strengthBar.style.width = '33.33%';
                strengthText.innerHTML = '<i class="fas fa-exclamation-circle"></i> <span style="color: #ef4444;">Too short (min. 6 characters)</span>';
            } else if (strength <= 3) {
                strengthBar.classList.add('weak');
                strengthBar.style.width = '33.33%';
                strengthText.innerHTML = '<i class="fas fa-shield-alt"></i> <span style="color: #ef4444;">Weak password</span>';
            } else if (strength <= 5) {
                strengthBar.classList.add('medium');
                strengthBar.style.width = '66.66%';
                strengthText.innerHTML = '<i class="fas fa-shield-alt"></i> <span style="color: #f59e0b;">Medium password</span>';
            } else {
                strengthBar.classList.add('strong');
                strengthBar.style.width = '100%';
                strengthText.innerHTML = '<i class="fas fa-shield-alt"></i> <span style="color: #10b981;">Strong password</span>';
            }
        });

        // Password match checker
        document.getElementById('confirm_password').addEventListener('input', function() {
            const password = document.getElementById('password').value;
            const confirm = this.value;
            const matchText = document.getElementById('passwordMatch');
            
            if (confirm.length === 0) {
                matchText.innerHTML = '<i class="fas fa-info-circle"></i> <span>Re-enter your password</span>';
            } else if (password === confirm) {
                matchText.innerHTML = '<i class="fas fa-check-circle" style="color: #28a745;"></i> <span style="color: #28a745;">Passwords match</span>';
            } else {
                matchText.innerHTML = '<i class="fas fa-exclamation-circle" style="color: #dc3545;"></i> <span style="color: #dc3545;">Passwords do not match</span>';
            }
        });

        // Form validation
        document.getElementById('registerForm').addEventListener('submit', function(e) {
            const password = document.getElementById('password').value;
            const confirm = document.getElementById('confirm_password').value;
            
            if (password.length < 6) {
                e.preventDefault();
                alert('Password must be at least 6 characters long');
                return;
            }
            
            if (password !== confirm) {
                e.preventDefault();
                alert('Passwords do not match!');
                return;
            }
        });

        // Real-time email validation
        document.getElementById('email').addEventListener('blur', function() {
            const email = this.value;
            const emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            
            if (email && !emailPattern.test(email)) {
                this.style.borderColor = '#ef4444';
                this.classList.add('error');
            } else {
                this.style.borderColor = '#e2e8f0';
                this.classList.remove('error');
            }
        });

        document.getElementById('email').addEventListener('focus', function() {
            this.style.borderColor = '#e2e8f0';
            this.classList.remove('error');
        });
    </script>
</body>
</html>