<?php
// Start session only if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include mail configuration
require_once __DIR__ . '/../config/mail_config.php';

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
        $subject = "2FA Verification Code - PNHS Enrollment System";
        
        $message = "
        <!DOCTYPE html>
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: #0B4F2E; color: white; padding: 20px; text-align: center; border-radius: 10px 10px 0 0; }
                .content { background: #f9f9f9; padding: 30px; border-left: 1px solid #ddd; border-right: 1px solid #ddd; }
                .code { 
                    font-size: 32px; 
                    font-weight: bold; 
                    color: #0B4F2E; 
                    text-align: center; 
                    padding: 20px;
                    background: #fff;
                    border: 2px dashed #0B4F2E;
                    border-radius: 10px;
                    margin: 20px 0;
                    letter-spacing: 5px;
                }
                .footer { 
                    background: #f0f0f0; 
                    padding: 15px; 
                    text-align: center; 
                    font-size: 12px; 
                    color: #666;
                    border-radius: 0 0 10px 10px;
                    border: 1px solid #ddd;
                    border-top: none;
                }
                .warning { color: #dc3545; font-size: 13px; margin-top: 15px; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h2>PNHS Enrollment System</h2>
                    <p>Two-Factor Authentication</p>
                </div>
                <div class='content'>
                    <p>Hello <strong>$user_name</strong>,</p>
                    <p>You requested to " . ($action == 'enable_2fa' ? 'enable' : ($action == 'disable_2fa' ? 'disable' : 'verify')) . " Two-Factor Authentication for your account.</p>
                    <p>Your verification code is:</p>
                    <div class='code'>$verification_code</div>
                    <p><strong>This code will expire in 10 minutes.</strong></p>
                    <p>If you didn't request this, please:</p>
                    <ul>
                        <li>Ignore this email</li>
                        <li>Change your password immediately</li>
                        <li>Contact your system administrator</li>
                    </ul>
                    <div class='warning'>
                        <strong>⚠️ Security Notice:</strong> Never share this code with anyone.
                    </div>
                </div>
                <div class='footer'>
                    <p>This is an automated message from PNHS Enrollment System. Please do not reply.</p>
                    <p>&copy; " . date('Y') . " PNHS. All rights reserved.</p>
                </div>
            </div>
        </body>
        </html>
        ";
        
        // Send email using our mail configuration
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