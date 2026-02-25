<?php
session_start();
include("../config/database.php");

if(isset($_POST['login'])){
    $email = $_POST['email'];
    $password = $_POST['password'];

    $sql = "SELECT * FROM users WHERE email='$email'";
    $result = $conn->query($sql);

    if($result->num_rows > 0){
        $user = $result->fetch_assoc();
        if(password_verify($password, $user['password'])){
            $_SESSION['user'] = $user;

            // Redirect based on role
            switch($user['role']){
                case 'Admin':
                    header("Location: ../admin/dashboard.php");
                    break;
                case 'Registrar':
                    header("Location: ../registrar/enrollments.php");
                    break;
                case 'Teacher':
                    header("Location: ../teacher/dashboard.php");
                    break;
                case 'Student':
                    header("Location: ../student/dashboard.php");
                    break;
            }
            exit();
        } else {
            $error = "Incorrect password!";
        }
    } else {
        $error = "Email not registered!";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Placido L. Señor Senior High School</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Arial, sans-serif;
            background: linear-gradient(135deg, #0B4F2E 0%, #1a7a42 100%);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            margin: 0;
            padding: 20px;
        }

        .login-container {
            background: #ffffff;
            width: 100%;
            max-width: 1000px;
            display: flex;
            border-radius: 20px;
            overflow: hidden;
            box-shadow: 0 25px 50px rgba(0, 0, 0, 0.2);
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

        /* Left Panel - Login Form */
        .login-panel {
            flex: 1;
            padding: 50px 40px;
            background: white;
        }

        .logo-section {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 40px;
        }

        .school-logo {
            width: 70px;
            height: 70px;
            background: linear-gradient(135deg, #0B4F2E, #1a7a42);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 32px;
            font-weight: bold;
            box-shadow: 0 5px 15px rgba(11, 79, 46, 0.3);
        }

        .school-name h1 {
            font-size: 22px;
            color: #0B4F2E;
            line-height: 1.3;
            font-weight: 600;
        }

        .school-name p {
            font-size: 13px;
            color: #666;
            margin-top: 5px;
        }

        h2 {
            font-size: 28px;
            color: #333;
            margin-bottom: 10px;
            font-weight: 500;
        }

        .subtitle {
            color: #666;
            margin-bottom: 30px;
            font-size: 15px;
        }

        .input-group {
            margin-bottom: 25px;
        }

        .input-group label {
            display: block;
            margin-bottom: 8px;
            color: #555;
            font-weight: 500;
            font-size: 14px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .input-group input {
            width: 100%;
            padding: 15px 18px;
            border: 2px solid #e0e0e0;
            border-radius: 12px;
            font-size: 15px;
            transition: all 0.3s;
            background-color: #f8f9fa;
        }

        .input-group input:focus {
            border-color: #0B4F2E;
            background-color: #ffffff;
            outline: none;
            box-shadow: 0 0 0 4px rgba(11, 79, 46, 0.1);
        }

        .remember-me {
            display: flex;
            align-items: center;
            gap: 10px;
            margin: 20px 0;
        }

        .remember-me input[type="checkbox"] {
            width: 18px;
            height: 18px;
            accent-color: #0B4F2E;
            cursor: pointer;
        }

        .remember-me label {
            color: #555;
            font-size: 14px;
            cursor: pointer;
        }

        .btn-login {
            width: 100%;
            background-color: #0B4F2E;
            color: white;
            padding: 16px;
            border: none;
            border-radius: 12px;
            font-size: 16px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1px;
            cursor: pointer;
            transition: all 0.3s;
            margin: 20px 0 15px;
        }

        .btn-login:hover {
            background-color: #1a7a42;
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(11, 79, 46, 0.3);
        }

        .signup-link {
            text-align: center;
            margin: 20px 0;
            color: #666;
            font-size: 15px;
        }

        .signup-link a {
            color: #0B4F2E;
            text-decoration: none;
            font-weight: 600;
            margin-left: 5px;
        }

        .signup-link a:hover {
            text-decoration: underline;
        }

        /* Right Panel - High School Programs */
        .info-panel {
            flex: 1;
            background: linear-gradient(135deg, #0B4F2E, #1a7a42);
            padding: 50px 40px;
            color: white;
        }

        .motto {
            text-align: center;
            margin-bottom: 30px;
        }

        .motto h3 {
            font-size: 32px;
            font-weight: 300;
            letter-spacing: 6px;
            text-transform: uppercase;
            line-height: 1.4;
        }

        .motto h3 span {
            display: block;
            font-size: 26px;
            letter-spacing: 3px;
        }

        .school-level {
            text-align: center;
            margin: 25px 0 20px;
            padding: 15px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 12px;
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        .school-level h4 {
            font-size: 22px;
            margin-bottom: 5px;
            color: #FFD700;
            font-weight: 600;
        }

        .school-level p {
            font-size: 13px;
            opacity: 0.9;
        }

        .programs-list {
            list-style: none;
            margin: 25px 0;
        }

        .programs-list li {
            padding: 12px 0;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .programs-list li::before {
            content: "→";
            font-size: 18px;
            opacity: 0.8;
            color: #FFD700;
        }

        .junior-high {
            margin: 25px 0;
            padding: 12px 15px;
            background: rgba(255, 255, 255, 0.08);
            border-radius: 10px;
            text-align: center;
            border: 1px solid rgba(255, 255, 255, 0.15);
        }

        .junior-high h4 {
            font-size: 18px;
            margin-bottom: 5px;
            color: #FFD700;
        }

        .junior-high p {
            font-size: 12px;
            opacity: 0.8;
        }

        .address {
            margin-top: 30px;
            text-align: center;
            font-size: 12px;
            opacity: 0.8;
            line-height: 1.8;
            padding: 15px;
            border-top: 1px solid rgba(255, 255, 255, 0.2);
            font-style: italic;
        }

        .error-message {
            background-color: #fee2e2;
            color: #dc2626;
            padding: 12px 16px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 14px;
            border-left: 4px solid #dc2626;
        }

        .success-message {
            background-color: #dcfce7;
            color: #16a34a;
            padding: 12px 16px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 14px;
            border-left: 4px solid #22c55e;
        }

        @media (max-width: 768px) {
            .login-container {
                flex-direction: column;
                max-width: 450px;
            }
            
            .info-panel {
                padding: 30px 20px;
            }
            
            .motto h3 {
                font-size: 28px;
            }
        }

        @media (max-width: 480px) {
            .login-panel {
                padding: 30px 20px;
            }
            
            .logo-section {
                flex-direction: column;
                text-align: center;
            }
            
            h2 {
                font-size: 24px;
            }
        }
    </style>
</head>
<body>
    <div class="login-container">
        <!-- Left Panel - Login Form -->
        <div class="login-panel">
            <div class="logo-section">
                <div class="school-logo">
                    <span>PNHS</span>
                </div>
                <div class="school-name">
                    <h1>Placido L. Señor<br>Senior High School</h1>
                    <p>Excellence • Service • Virtue</p>
                </div>
            </div>

            <h2>Login to your Account</h2>
            <p class="subtitle">Enter your credentials to access the system</p>
            
            <?php if(isset($error)): ?>
                <div class="error-message">
                    ⚠️ <?php echo $error; ?>
                </div>
            <?php endif; ?>
            
            <?php if(isset($_GET['registered'])): ?>
                <div class="success-message">
                    ✅ Registration successful! Please login.
                </div>
            <?php endif; ?>

            <form method="POST" action="">
                <div class="input-group">
                    <label for="email">Email Address</label>
                    <input type="email" id="email" name="email" placeholder="Enter your email address" required>
                </div>
                
                <div class="input-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" placeholder="Enter your password" required>
                </div>
                
                <div class="remember-me">
                    <input type="checkbox" id="remember" name="remember">
                    <label for="remember">Remember me</label>
                </div>
                
                <button type="submit" name="login" class="btn-login">
                    LOG IN
                </button>
            </form>
            
            <div class="signup-link">
                Don't have an account?
                <a href="register.php">Sign Up</a>
            </div>
        </div>
        
        <!-- Right Panel - High School Programs -->
        <div class="info-panel">
            <div class="motto">
                <h3>VIRTUS<br><span>EXCELLENTIA</span><br>SERVITIUM</h3>
            </div>
            
            <div class="school-level">
                <h4>SENIOR HIGH SCHOOL</h4>
                <p>Grades 11-12 · Academic Tracks</p>
            </div>
            
            <ul class="programs-list">
                <li><strong>ACADEMIC TRACK - STEM</strong> - Science, Technology, Engineering, and Mathematics</li>
                <li><strong>ACADEMIC TRACK - ABM</strong> - Accountancy, Business, and Management</li>
                <li><strong>ACADEMIC TRACK - HUMSS</strong> - Humanities and Social Sciences</li>
                <li><strong>ACADEMIC TRACK - GAS</strong> - General Academic Strand</li>
                <li><strong>TECHNICAL-VOCATIONAL - ICT</strong> - Information and Communications Technology</li>
                <li><strong>TECHNICAL-VOCATIONAL - HE</strong> - Home Economics</li>
                <li><strong>TECHNICAL-VOCATIONAL - IA</strong> - Industrial Arts</li>
            </ul>
            
            <div class="junior-high">
                <h4>JUNIOR HIGH SCHOOL</h4>
                <p>Grades 7-10 · Basic Education Curriculum</p>
            </div>
            
            <div class="address">
                PLACIDO L. SEÑOR SENIOR HIGH SCHOOL<br>
                Langtad, City of Naga, Cebu<br>
                📞 (032) 123-4567 · 📧 info@plsshs.edu.ph
            </div>
        </div>
    </div>
</body>
</html>