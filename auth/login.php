<?php
session_start();
include("../config/database.php");

// Check if connection exists
if (!$conn) {
    die("Database connection failed. Please check your configuration.");
}

$error = '';

if(isset($_POST['login'])){
    $email = $_POST['email'];
    $password = $_POST['password'];

    // Use prepared statement to prevent SQL injection
    $sql = "SELECT * FROM users WHERE email = :email";
    $stmt = $conn->prepare($sql);
    $stmt->bindParam(':email', $email, PDO::PARAM_STR);
    $stmt->execute();
    
    if($stmt->rowCount() > 0){
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if(password_verify($password, $user['password'])){
            
            // Check user status
            if($user['status'] == 'pending') {
                $error = "Your account is pending approval from the administrator. Please wait for approval.";
            } elseif($user['status'] == 'rejected') {
                $reason = isset($user['rejection_reason']) ? " Reason: " . $user['rejection_reason'] : "";
                $error = "Your account has been rejected." . $reason . " Please contact the administrator for more information.";
            } elseif($user['status'] == 'approved') {
                
                // Check if 2FA is enabled for this user (only for Admin and Registrar)
                if(($user['role'] == 'Admin' || $user['role'] == 'Registrar') && $user['two_factor_enabled'] == 1) {
                    // Generate 6-digit code
                    $code = sprintf("%06d", mt_rand(1, 999999));
                    
                    // Store in session with expiration (5 minutes)
                    $_SESSION['2fa_user_id'] = $user['id'];
                    $_SESSION['2fa_code'] = $code;
                    $_SESSION['2fa_expires'] = time() + 300; // 5 minutes
                    $_SESSION['2fa_email'] = $user['email'];
                    $_SESSION['2fa_name'] = $user['fullname'];
                    
                    // Send email with code
                    $to = $user['email'];
                    $subject = "Your 2FA Verification Code - PNHS";
                    
                    $message = "
                    <html>
                    <head>
                        <title>2FA Verification Code</title>
                    </head>
                    <body style='font-family: Arial, sans-serif; margin: 0; padding: 0; background-color: #f4f4f4;'>
                        <div style='max-width: 600px; margin: 20px auto; background: white; border-radius: 10px; overflow: hidden; box-shadow: 0 5px 15px rgba(0,0,0,0.1);'>
                            <div style='background: linear-gradient(135deg, #0B4F2E, #1a7a42); padding: 30px; text-align: center;'>
                                <h1 style='color: white; margin: 0; font-size: 28px;'>PNHS</h1>
                                <p style='color: #FFD700; margin: 5px 0 0; font-size: 16px;'>Two-Factor Authentication</p>
                            </div>
                            
                            <div style='padding: 30px;'>
                                <p style='font-size: 16px; color: #333;'>Hello <strong>" . $user['fullname'] . "</strong>,</p>
                                <p style='font-size: 16px; color: #333;'>You have requested to log in to your account. Please use the following verification code:</p>
                                
                                <div style='background: #f8f9fa; padding: 20px; border-radius: 8px; text-align: center; margin: 25px 0; border: 2px dashed #0B4F2E;'>
                                    <div style='font-size: 42px; font-weight: bold; color: #0B4F2E; letter-spacing: 8px; font-family: monospace;'>
                                        " . $code . "
                                    </div>
                                    <p style='color: #666; margin-top: 10px;'>This code will expire in 5 minutes</p>
                                </div>
                                
                                <div style='background: #fff3cd; border-left: 4px solid #ffc107; padding: 15px; margin: 20px 0;'>
                                    <p style='color: #856404; margin: 0; font-size: 14px;'>
                                        <strong>⚠️ Security Notice:</strong> Never share this code with anyone. Our staff will never ask for your verification code.
                                    </p>
                                </div>
                                
                                <p style='color: #666; font-size: 14px; margin-top: 25px;'>If you didn't attempt to log in, please ignore this email and contact the administrator immediately.</p>
                            </div>
                            
                            <div style='background: #f8f9fa; padding: 20px; text-align: center; border-top: 1px solid #e0e0e0;'>
                                <p style='color: #999; font-size: 12px; margin: 0;'>&copy; " . date('Y') . " Placido L. Señor Senior High School. All rights reserved.</p>
                                <p style='color: #999; font-size: 12px; margin: 5px 0 0;'>Langtad, City of Naga, Cebu</p>
                            </div>
                        </div>
                    </body>
                    </html>
                    ";
                    
                    $headers = "MIME-Version: 1.0" . "\r\n";
                    $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
                    $headers .= "From: PNHS 2FA <noreply@plsshs.edu.ph>" . "\r\n";
                    
                    if(mail($to, $subject, $message, $headers)) {
                        header("Location: verify_2fa.php");
                        exit();
                    } else {
                        $error = "Failed to send verification code. Please try again.";
                    }
                } else {
                    // Normal login without 2FA
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
                }
            } else {
                $error = "Account status unknown. Please contact administrator.";
            }
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
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
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
            display: flex;
            align-items: center;
            gap: 8px;
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
            
            <?php if(!empty($error)): ?>
                <div class="error-message">
                    <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
                </div>
            <?php endif; ?>
            
            <?php if(isset($_GET['registered'])): ?>
                <div class="success-message">
                    <i class="fas fa-check-circle"></i> Registration successful! Please wait for admin approval.
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
    
    <!-- Font Awesome -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/js/all.min.js"></script>
</body>
</html>