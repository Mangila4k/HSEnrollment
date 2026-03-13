<?php
// Start session only if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// For production, use a proper mail library like PHPMailer
// For development, you can use a test mail server or log emails

function sendEmail($to, $subject, $message, $from = "noreply@pnhighschool.com") {
    // For development/testing - log email instead of sending
    $logFile = __DIR__ . '/../logs/email.log';
    $logDir = dirname($logFile);
    
    // Create logs directory if it doesn't exist
    if (!file_exists($logDir)) {
        mkdir($logDir, 0777, true);
    }
    
    $logMessage = date('Y-m-d H:i:s') . " - To: $to, Subject: $subject, Message: $message\n";
    file_put_contents($logFile, $logMessage, FILE_APPEND);
    
    // For production with PHPMailer (commented out)
    /*
    require_once 'path/to/PHPMailer/src/Exception.php';
    require_once 'path/to/PHPMailer/src/PHPMailer.php';
    require_once 'path/to/PHPMailer/src/SMTP.php';
    
    $mail = new PHPMailer\PHPMailer\PHPMailer(true);
    
    try {
        // Server settings
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';                    // Set your SMTP server
        $mail->SMTPAuth   = true;
        $mail->Username   = 'your-email@gmail.com';              // SMTP username
        $mail->Password   = 'your-app-password';                 // SMTP password
        $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;
        
        // Recipients
        $mail->setFrom($from, 'PNHS Admin');
        $mail->addAddress($to);
        
        // Content
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $message;
        
        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Mail error: " . $mail->ErrorInfo);
        return false;
    }
    */
    
    // For now, just return true for development
    return true;
}

/**
 * Start 2FA verification process
 */
function start2FAVerification($user_id, $user_email, $user_name, $action, $redirect_url) {
    try {
        // Generate a 6-digit verification code
        $verification_code = sprintf("%06d", mt_rand(1, 999999));
        
        // Store verification data in session
        $_SESSION['2fa_verification'] = [
            'user_id' => $user_id,
            'code' => $verification_code,
            'action' => $action,
            'redirect' => $redirect_url,
            'expires' => time() + 600, // 10 minutes
            'attempts' => 0
        ];
        
        // Send verification email
        $subject = "2FA Verification Code - PNHS";
        
        $message = "
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: #0B4F2E; color: white; padding: 20px; text-align: center; }
                .code { font-size: 32px; font-weight: bold; color: #0B4F2E; text-align: center; padding: 30px; }
                .footer { font-size: 12px; color: #666; text-align: center; margin-top: 30px; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h2>PNHS Two-Factor Authentication</h2>
                </div>
                <p>Hello $user_name,</p>
                <p>You requested to " . ($action == 'enable_2fa' ? 'enable' : ($action == 'disable_2fa' ? 'disable' : 'verify')) . " Two-Factor Authentication.</p>
                <p>Your verification code is:</p>
                <div class='code'>$verification_code</div>
                <p>This code will expire in 10 minutes.</p>
                <p>If you didn't request this, please ignore this email and contact your administrator.</p>
                <div class='footer'>
                    <p>This is an automated message, please do not reply.</p>
                    <p>&copy; " . date('Y') . " PNHS. All rights reserved.</p>
                </div>
            </div>
        </html>
        ";
        
        // Send email
        $email_sent = sendEmail($user_email, $subject, $message);
        
        if ($email_sent) {
            // Redirect to verification page
            header("Location: ../auth/verify_2fa.php");
            exit();
        } else {
            return "Failed to send verification email. Please try again.";
        }
    } catch (Exception $e) {
        error_log("2FA Error: " . $e->getMessage());
        return "An error occurred. Please try again.";
    }
}

/**
 * Verify 2FA code
 */
function verify2FACode($submitted_code) {
    if (!isset($_SESSION['2fa_verification'])) {
        return ['success' => false, 'message' => 'No verification in progress'];
    }
    
    $verification = $_SESSION['2fa_verification'];
    
    // Check expiration
    if (time() > $verification['expires']) {
        unset($_SESSION['2fa_verification']);
        return ['success' => false, 'message' => 'Verification code has expired'];
    }
    
    // Check attempts
    if ($verification['attempts'] >= 3) {
        unset($_SESSION['2fa_verification']);
        return ['success' => false, 'message' => 'Too many failed attempts. Please try again.'];
    }
    
    // Increment attempts
    $_SESSION['2fa_verification']['attempts']++;
    
    // Verify code
    if ($submitted_code == $verification['code']) {
        $_SESSION['2fa_verified'] = true;
        $_SESSION['2fa_verified_time'] = time();
        $_SESSION['2fa_action'] = $verification['action'];
        $_SESSION['2fa_user_id'] = $verification['user_id'];
        
        unset($_SESSION['2fa_verification']);
        return ['success' => true, 'redirect' => $verification['redirect']];
    }
    
    return ['success' => false, 'message' => 'Invalid verification code'];
}

/**
 * Check if 2FA is enabled for a user
 */
function is2FAEnabled($conn, $user_id) {
    $query = "SELECT two_factor_enabled FROM users WHERE id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    $stmt->close();
    
    return ($user && $user['two_factor_enabled'] == 1);
}

/**
 * Enable 2FA for a user
 */
function enable2FA($conn, $user_id) {
    $query = "UPDATE users SET two_factor_enabled = 1, two_factor_last_used = NOW() WHERE id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $user_id);
    $result = $stmt->execute();
    $stmt->close();
    
    return $result;
}

/**
 * Disable 2FA for a user
 */
function disable2FA($conn, $user_id) {
    $query = "UPDATE users SET two_factor_enabled = 0, two_factor_last_used = NULL WHERE id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $user_id);
    $result = $stmt->execute();
    $stmt->close();
    
    return $result;
}

/**
 * Update last used time for 2FA
 */
function update2FALastUsed($conn, $user_id) {
    $query = "UPDATE users SET two_factor_last_used = NOW() WHERE id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $stmt->close();
}
?>