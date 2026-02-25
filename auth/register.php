<?php
session_start();
include("../config/database.php");

if(isset($_POST['register'])){
    $fullname = $_POST['fullname'];
    $email = $_POST['email'];
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $role = "Student"; // Fixed role: Student only

    // Check if email already exists
    $check = $conn->query("SELECT * FROM users WHERE email='$email'");
    if($check->num_rows > 0){
        $error = "Email already registered!";
    } else {
        $sql = "INSERT INTO users (fullname,email,password,role) 
                VALUES ('$fullname','$email','$password','$role')";
        if($conn->query($sql)){
            $_SESSION['success'] = "Registration successful! Please login.";
            header("Location: login.php");
            exit();
        } else {
            $error = "Registration failed: ".$conn->error;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Registration - EnrollSys</title>
    <link rel="stylesheet" href="../assets/css/register.css">
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
        
        <?php if(isset($error)): ?>
            <div class="error-message">
                ⚠️ <?php echo $error; ?>
            </div>
        <?php endif; ?>

        <form method="POST" id="registerForm">
            <div class="form-group">
                <label for="fullname">Full Name</label>
                <div class="password-wrapper">
                    <input type="text" id="fullname" name="fullname" placeholder="Enter your full name" required>
                    <span class="input-icon">👤</span>
                </div>
            </div>

            <div class="form-group">
                <label for="email">Email Address</label>
                <div class="password-wrapper">
                    <input type="email" id="email" name="email" placeholder="Enter your email" required>
                    <span class="input-icon">✉️</span>
                </div>
            </div>

            <div class="form-group">
                <label for="password">Password</label>
                <div class="password-wrapper">
                    <input type="password" id="password" name="password" placeholder="Create a password" required>
                    <button type="button" class="toggle-password" onclick="togglePassword()">👁️</button>
                </div>
                <!-- Password strength indicator -->
                <div class="password-strength">
                    <div class="strength-bar" id="strengthBar"></div>
                </div>
                <div class="strength-text" id="strengthText">Enter password</div>
            </div>

            <div class="terms">
                By registering, you agree to our <a href="#">Terms of Service</a> and <a href="#">Privacy Policy</a>
            </div>

            <button type="submit" name="register" class="btn-register" id="registerBtn">
                Create Account
            </button>
        </form>

        <div class="login-link">
            <p>Already have an account? <a href="login.php">Sign In</a></p>
        </div>

        <div class="form-footer">
            <a href="../index.php" class="back-home">← Back to Home</a>
        </div>
    </div>

    <script>
        // Toggle password visibility
        function togglePassword() {
            const passwordInput = document.getElementById('password');
            const toggleBtn = document.querySelector('.toggle-password');
            
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                toggleBtn.textContent = '🔒';
            } else {
                passwordInput.type = 'password';
                toggleBtn.textContent = '👁️';
            }
        }

        // Password strength checker
        document.getElementById('password').addEventListener('input', function() {
            const password = this.value;
            const strengthBar = document.getElementById('strengthBar');
            const strengthText = document.getElementById('strengthText');
            
            // Remove existing classes
            strengthBar.classList.remove('weak', 'medium', 'strong');
            
            if (password.length === 0) {
                strengthBar.style.width = '0';
                strengthText.textContent = 'Enter password';
                return;
            }
            
            // Check strength
            let strength = 0;
            
            // Length check
            if (password.length >= 8) strength += 1;
            if (password.length >= 12) strength += 1;
            
            // Character variety
            if (/[A-Z]/.test(password)) strength += 1;
            if (/[0-9]/.test(password)) strength += 1;
            if (/[^A-Za-z0-9]/.test(password)) strength += 1;
            
            // Update UI based on strength
            if (strength <= 2) {
                strengthBar.classList.add('weak');
                strengthText.textContent = 'Weak password';
                strengthText.style.color = '#ef4444';
            } else if (strength <= 4) {
                strengthBar.classList.add('medium');
                strengthText.textContent = 'Medium password';
                strengthText.style.color = '#f59e0b';
            } else {
                strengthBar.classList.add('strong');
                strengthText.textContent = 'Strong password';
                strengthText.style.color = '#10b981';
            }
        });

        // Form submission loading state
        document.getElementById('registerForm').addEventListener('submit', function(e) {
            const password = document.getElementById('password').value;
            const registerBtn = document.getElementById('registerBtn');
            
            if (password.length < 6) {
                e.preventDefault();
                alert('Password must be at least 6 characters long');
                return;
            }
            
            // Add loading state
            registerBtn.innerHTML = 'Creating Account...';
            registerBtn.classList.add('loading');
            registerBtn.disabled = true;
        });

        // Real-time validation
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