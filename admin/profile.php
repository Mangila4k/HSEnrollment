<?php
session_start();
include("../config/database.php");
include("../includes/2fa_functions.php");

// Check if user is admin
if(!isset($_SESSION['user']) || $_SESSION['user']['role'] != 'Admin'){
    header("Location: ../auth/login.php");
    exit();
}

$admin_id = $_SESSION['user']['id'];
$admin_name = $_SESSION['user']['fullname'];
$success_message = '';
$error_message = '';

// Check for session messages
if(isset($_SESSION['success_message'])) {
    $success_message = $_SESSION['success_message'];
    unset($_SESSION['success_message']);
}

if(isset($_SESSION['error_message'])) {
    $error_message = $_SESSION['error_message'];
    unset($_SESSION['error_message']);
}

// Get admin details
$query = "SELECT * FROM users WHERE id = ? AND role = 'Admin'";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $admin_id);
$stmt->execute();
$result = $stmt->get_result();
$admin = $result->fetch_assoc();
$stmt->close();

// Handle 2FA toggle
if(isset($_POST['toggle_2fa'])) {
    $action = $_POST['action'];
    
    if($action == 'enable') {
        // Start 2FA verification for enabling
        $error = start2FAVerification(
            $admin_id,
            $admin['email'],
            $admin['fullname'],
            'enable_2fa',
            'profile.php'
        );
    } elseif($action == 'disable') {
        // Start 2FA verification for disabling
        $error = start2FAVerification(
            $admin_id,
            $admin['email'],
            $admin['fullname'],
            'disable_2fa',
            'profile.php'
        );
    }
}

// Handle 2FA verification completion
if(isset($_SESSION['2fa_verified']) && $_SESSION['2fa_verified'] === true) {
    if(time() - $_SESSION['2fa_verified_time'] < 600) {
        // Verification is still valid
        // The actual enable/disable was handled in verify_2fa.php
        unset($_SESSION['2fa_verified']);
        unset($_SESSION['2fa_verified_time']);
        
        // Refresh admin data
        $result = $conn->query("SELECT * FROM users WHERE id = $admin_id");
        $admin = $result->fetch_assoc();
    } else {
        unset($_SESSION['2fa_verified']);
        unset($_SESSION['2fa_verified_time']);
    }
}

// Handle profile update
if($_SERVER['REQUEST_METHOD'] == 'POST') {
    if(isset($_POST['update_profile'])) {
        $fullname = trim($_POST['fullname']);
        $email = trim($_POST['email']);
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
        
        // Check if email already exists (excluding current admin)
        if(empty($errors)) {
            $check_email = $conn->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
            $check_email->bind_param("si", $email, $admin_id);
            $check_email->execute();
            $check_email->store_result();
            
            if($check_email->num_rows > 0) {
                $errors[] = "Email address already registered to another user";
            }
            $check_email->close();
        }
        
        // Check if ID number already exists (if provided and excluding current admin)
        if(empty($errors) && $id_number) {
            $check_id = $conn->prepare("SELECT id FROM users WHERE id_number = ? AND id != ?");
            $check_id->bind_param("si", $id_number, $admin_id);
            $check_id->execute();
            $check_id->store_result();
            
            if($check_id->num_rows > 0) {
                $errors[] = "ID number already exists for another user";
            }
            $check_id->close();
        }
        
        // If no errors, update the profile
        if(empty($errors)) {
            $update_query = "UPDATE users SET fullname = ?, email = ?, id_number = ? WHERE id = ?";
            $update_stmt = $conn->prepare($update_query);
            $update_stmt->bind_param("sssi", $fullname, $email, $id_number, $admin_id);
            
            if($update_stmt->execute()) {
                // Update session
                $_SESSION['user']['fullname'] = $fullname;
                $_SESSION['user']['email'] = $email;
                $_SESSION['user']['id_number'] = $id_number;
                
                $_SESSION['success_message'] = "Profile updated successfully!";
                header("Location: profile.php");
                exit();
            } else {
                $errors[] = "Database error: " . $conn->error;
            }
            $update_stmt->close();
        }
        
        // If there are errors, store them
        if(!empty($errors)) {
            $error_message = implode("<br>", $errors);
        }
    }
    
    // Handle password change
    if(isset($_POST['change_password'])) {
        $current_password = $_POST['current_password'];
        $new_password = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];
        
        $errors = [];
        
        // Verify current password
        if(empty($current_password)) {
            $errors[] = "Current password is required";
        } else {
            if(!password_verify($current_password, $admin['password'])) {
                $errors[] = "Current password is incorrect";
            }
        }
        
        if(empty($new_password)) {
            $errors[] = "New password is required";
        } elseif(strlen($new_password) < 6) {
            $errors[] = "New password must be at least 6 characters";
        }
        
        if($new_password !== $confirm_password) {
            $errors[] = "New passwords do not match";
        }
        
        // If no errors, check if 2FA is enabled
        if(empty($errors)) {
            if(is2FAEnabled($conn, $admin_id)) {
                // Start 2FA verification for password change
                $error = start2FAVerification(
                    $admin_id,
                    $admin['email'],
                    $admin['fullname'],
                    'password_change',
                    'profile.php'
                );
            } else {
                // Proceed with password change without 2FA
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                $update_query = "UPDATE users SET password = ? WHERE id = ?";
                $update_stmt = $conn->prepare($update_query);
                $update_stmt->bind_param("si", $hashed_password, $admin_id);
                
                if($update_stmt->execute()) {
                    $_SESSION['success_message'] = "Password changed successfully!";
                    header("Location: profile.php");
                    exit();
                } else {
                    $errors[] = "Database error: " . $conn->error;
                }
                $update_stmt->close();
            }
        }
        
        // If there are errors, store them
        if(!empty($errors)) {
            $error_message = implode("<br>", $errors);
        }
    }
}

$account_created = $admin['created_at'];
$two_factor_enabled = $admin['two_factor_enabled'] ?? 0;
$two_factor_last_used = $admin['two_factor_last_used'] ?? null;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile - Admin Dashboard</title>
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

        :root {
            --primary: #0B4F2E;
            --primary-dark: #1a7a42;
            --primary-light: rgba(11, 79, 46, 0.1);
            --accent: #FFD700;
            --text-primary: #2b2d42;
            --text-secondary: #8d99ae;
            --border-color: #e9ecef;
            --hover-color: #f8f9fa;
            --success: #28a745;
            --warning: #ffc107;
            --danger: #dc3545;
            --info: #17a2b8;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #f4f7fd;
            min-height: 100vh;
        }

        /* Main Layout */
        .app-container {
            display: flex;
            min-height: 100vh;
        }

        /* Sidebar */
        .sidebar {
            width: 280px;
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: white;
            padding: 30px 20px;
            position: fixed;
            height: 100vh;
            overflow-y: auto;
            box-shadow: 4px 0 10px rgba(0, 0, 0, 0.1);
        }

        .sidebar h2 {
            font-size: 24px;
            margin-bottom: 30px;
            display: flex;
            align-items: center;
            gap: 10px;
            color: #fff;
            font-weight: 700;
            letter-spacing: 1px;
            padding-bottom: 20px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.2);
        }

        .sidebar h2 i {
            color: var(--accent);
        }

        .admin-info {
            text-align: center;
            padding: 20px 0;
            border-bottom: 1px solid rgba(255, 255, 255, 0.2);
            margin-bottom: 20px;
        }

        .admin-avatar {
            width: 80px;
            height: 80px;
            background: var(--accent);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 15px;
            font-size: 32px;
            font-weight: bold;
            color: var(--primary);
            border: 3px solid white;
        }

        .admin-info h3 {
            font-size: 18px;
            margin-bottom: 5px;
            color: var(--accent);
        }

        .admin-info p {
            font-size: 14px;
            opacity: 0.9;
        }

        .menu-section {
            margin-bottom: 30px;
        }

        .menu-section h3 {
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: rgba(255, 255, 255, 0.5);
            margin-bottom: 15px;
            padding-left: 10px;
        }

        .menu-items {
            list-style: none;
        }

        .menu-items li {
            margin-bottom: 5px;
        }

        .menu-items a {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 15px;
            color: rgba(255, 255, 255, 0.8);
            text-decoration: none;
            border-radius: 10px;
            transition: all 0.3s ease;
            font-weight: 500;
        }

        .menu-items a:hover,
        .menu-items a.active {
            background: rgba(255, 255, 255, 0.1);
            color: white;
            transform: translateX(5px);
        }

        .menu-items a i {
            width: 20px;
            font-size: 1.1em;
            color: var(--accent);
        }

        .menu-items a.active {
            background: rgba(255, 255, 255, 0.15);
            border-left: 3px solid var(--accent);
        }

        /* Main Content */
        .main-content {
            flex: 1;
            margin-left: 280px;
            padding: 30px;
        }

        /* Header */
        .dashboard-header {
            margin-bottom: 30px;
        }

        .dashboard-header h1 {
            font-size: 28px;
            color: var(--text-primary);
            margin-bottom: 10px;
            font-weight: 700;
        }

        .dashboard-header p {
            color: var(--text-secondary);
            font-size: 16px;
        }

        /* Welcome Card */
        .welcome-card {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            border-radius: 20px;
            padding: 30px;
            color: white;
            margin-bottom: 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 10px 30px rgba(11, 79, 46, 0.3);
        }

        .welcome-text h2 {
            font-size: 24px;
            margin-bottom: 10px;
            font-weight: 600;
        }

        .welcome-text p {
            font-size: 16px;
            opacity: 0.9;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .welcome-text p i {
            color: var(--accent);
        }

        .logout-btn {
            background: rgba(255, 255, 255, 0.2);
            color: white;
            padding: 12px 25px;
            border-radius: 12px;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 10px;
            transition: all 0.3s ease;
            font-weight: 500;
            border: 1px solid rgba(255, 255, 255, 0.3);
        }

        .logout-btn:hover {
            background: rgba(255, 255, 255, 0.3);
            transform: translateY(-2px);
        }

        /* Alert Messages */
        .alert {
            padding: 15px 20px;
            border-radius: 12px;
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            gap: 12px;
            animation: slideIn 0.3s ease;
        }

        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .alert-success {
            background: #d4edda;
            color: #155724;
            border-left: 4px solid var(--success);
        }

        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border-left: 4px solid var(--danger);
        }

        .alert i {
            font-size: 20px;
        }

        /* Profile Grid */
        .profile-grid {
            display: grid;
            grid-template-columns: 1fr 2fr;
            gap: 25px;
        }

        /* Profile Card */
        .profile-card {
            background: white;
            border-radius: 20px;
            padding: 30px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            text-align: center;
        }

        .profile-avatar-large {
            width: 120px;
            height: 120px;
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            font-size: 48px;
            font-weight: bold;
            color: white;
            border: 4px solid var(--accent);
        }

        .profile-name {
            font-size: 22px;
            font-weight: 700;
            color: var(--text-primary);
            margin-bottom: 5px;
        }

        .profile-role {
            display: inline-block;
            padding: 5px 15px;
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            color: white;
            border-radius: 20px;
            font-size: 14px;
            font-weight: 600;
            margin-bottom: 20px;
        }

        .profile-stats {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
            margin: 25px 0;
            padding: 20px 0;
            border-top: 1px solid var(--border-color);
            border-bottom: 1px solid var(--border-color);
        }

        .stat-item {
            text-align: center;
        }

        .stat-value {
            font-size: 24px;
            font-weight: 700;
            color: var(--primary);
        }

        .stat-label {
            font-size: 12px;
            color: var(--text-secondary);
            margin-top: 5px;
        }

        .profile-info-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 0;
            border-bottom: 1px solid var(--border-color);
            text-align: left;
        }

        .profile-info-item:last-child {
            border-bottom: none;
        }

        .info-icon {
            width: 40px;
            height: 40px;
            background: var(--primary-light);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--primary);
            font-size: 18px;
        }

        .info-content {
            flex: 1;
        }

        .info-label {
            font-size: 12px;
            color: var(--text-secondary);
            margin-bottom: 3px;
        }

        .info-value {
            font-size: 15px;
            font-weight: 500;
            color: var(--text-primary);
        }

        /* 2FA Section */
        .twofa-section {
            background: white;
            border-radius: 20px;
            padding: 25px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            margin-bottom: 25px;
        }

        .twofa-section h3 {
            color: var(--text-primary);
            font-size: 18px;
            font-weight: 600;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
            padding-bottom: 15px;
            border-bottom: 1px solid var(--border-color);
        }

        .twofa-section h3 i {
            color: var(--primary);
        }

        .twofa-status {
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 20px;
            margin-bottom: 20px;
        }

        .status-badge {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 16px;
            font-weight: 600;
        }

        .status-badge .enabled {
            color: var(--success);
            background: rgba(40, 167, 69, 0.1);
            padding: 5px 15px;
            border-radius: 30px;
        }

        .status-badge .disabled {
            color: var(--text-secondary);
            background: #f8f9fa;
            padding: 5px 15px;
            border-radius: 30px;
        }

        .last-used {
            font-size: 14px;
            color: var(--text-secondary);
        }

        .btn-toggle {
            padding: 12px 25px;
            border: none;
            border-radius: 10px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-toggle.enable {
            background: rgba(40, 167, 69, 0.1);
            color: var(--success);
            border: 1px solid var(--success);
        }

        .btn-toggle.enable:hover {
            background: var(--success);
            color: white;
        }

        .btn-toggle.disable {
            background: rgba(220, 53, 69, 0.1);
            color: var(--danger);
            border: 1px solid var(--danger);
        }

        .btn-toggle.disable:hover {
            background: var(--danger);
            color: white;
        }

        .info-box {
            background: #e8f4fd;
            border-left: 4px solid var(--info);
            padding: 15px;
            border-radius: 8px;
            margin-top: 15px;
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 14px;
            color: #0c5460;
        }

        .info-box i {
            font-size: 18px;
            color: var(--info);
        }

        /* Form Card */
        .form-card {
            background: white;
            border-radius: 20px;
            padding: 30px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            margin-bottom: 25px;
        }

        .form-card h3 {
            color: var(--text-primary);
            font-size: 18px;
            font-weight: 600;
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            gap: 10px;
            padding-bottom: 15px;
            border-bottom: 1px solid var(--border-color);
        }

        .form-card h3 i {
            color: var(--primary);
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: var(--text-primary);
            font-weight: 500;
            font-size: 14px;
        }

        .form-group label span {
            color: #dc3545;
            margin-left: 3px;
        }

        .form-group input {
            width: 100%;
            padding: 12px 16px;
            border: 2px solid var(--border-color);
            border-radius: 10px;
            font-size: 14px;
            transition: all 0.3s;
            background: #f8f9fa;
        }

        .form-group input:focus {
            border-color: var(--primary);
            background: white;
            outline: none;
            box-shadow: 0 0 0 4px var(--primary-light);
        }

        .form-group input:disabled {
            background: #e9ecef;
            cursor: not-allowed;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }

        .btn-submit {
            background: var(--primary);
            color: white;
            padding: 14px 25px;
            border: none;
            border-radius: 12px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 10px;
        }

        .btn-submit:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(11, 79, 46, 0.3);
        }

        .btn-submit i {
            font-size: 16px;
        }

        /* Password Section */
        .password-section {
            margin-top: 20px;
        }

        .password-header {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 20px;
        }

        .password-header input[type="checkbox"] {
            width: 18px;
            height: 18px;
            accent-color: var(--primary);
            cursor: pointer;
        }

        .password-header label {
            color: var(--text-primary);
            font-weight: 600;
            font-size: 15px;
            cursor: pointer;
        }

        .password-fields {
            display: none;
        }

        .password-fields.show {
            display: block;
        }

        .password-strength {
            margin-top: 8px;
            height: 4px;
            background: #e9ecef;
            border-radius: 2px;
            overflow: hidden;
        }

        .password-strength-bar {
            height: 100%;
            width: 0;
            transition: width 0.3s, background-color 0.3s;
        }

        .password-strength-text {
            font-size: 12px;
            margin-top: 5px;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .strength-weak {
            color: var(--danger);
        }

        .strength-medium {
            color: var(--warning);
        }

        .strength-strong {
            color: var(--success);
        }

        /* Responsive */
        @media (max-width: 1200px) {
            .profile-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 768px) {
            .sidebar {
                width: 80px;
                padding: 20px 10px;
            }
            
            .sidebar h2 span,
            .admin-info h3,
            .admin-info p,
            .menu-section h3,
            .menu-items a span {
                display: none;
            }
            
            .admin-avatar {
                width: 50px;
                height: 50px;
                font-size: 20px;
            }
            
            .menu-items a {
                justify-content: center;
                padding: 15px;
            }
            
            .main-content {
                margin-left: 80px;
                padding: 20px;
            }
            
            .dashboard-header {
                flex-direction: column;
                gap: 15px;
                align-items: flex-start;
            }
            
            .welcome-card {
                flex-direction: column;
                text-align: center;
                gap: 20px;
            }
            
            .form-row {
                grid-template-columns: 1fr;
            }
            
            .profile-stats {
                grid-template-columns: 1fr;
            }
            
            .twofa-status {
                flex-direction: column;
                align-items: flex-start;
            }
        }
    </style>
</head>
<body>
    <div class="app-container">
        <!-- Sidebar -->
        <div class="sidebar">
            <h2>
                <i class="fas fa-check-circle"></i>
                <span>PNHS</span>
            </h2>
            
            <div class="admin-info">
                <div class="admin-avatar">
                    <?php echo strtoupper(substr($admin_name, 0, 1)); ?>
                </div>
                <h3><?php echo htmlspecialchars(explode(' ', $admin_name)[0]); ?></h3>
                <p><i class="fas fa-user-shield"></i> Administrator</p>
            </div>
            
            <div class="menu-section">
                <h3>MAIN MENU</h3>
                <ul class="menu-items">
                    <li><a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> <span>Dashboard</span></a></li>
                    <li><a href="students.php"><i class="fas fa-user-graduate"></i> <span>Students</span></a></li>
                    <li><a href="teachers.php"><i class="fas fa-chalkboard-teacher"></i> <span>Teachers</span></a></li>
                    <li><a href="sections.php"><i class="fas fa-layer-group"></i> <span>Sections</span></a></li>
                    <li><a href="subjects.php"><i class="fas fa-book"></i> <span>Subjects</span></a></li>
                    <li><a href="enrollments.php"><i class="fas fa-file-signature"></i> <span>Enrollments</span></a></li>
                </ul>
            </div>

            <div class="menu-section">
                <h3>MANAGEMENT</h3>
                <ul class="menu-items">
                    <li><a href="manage_accounts.php"><i class="fas fa-users-cog"></i> <span>Accounts</span></a></li>
                    <li><a href="attendance.php"><i class="fas fa-calendar-check"></i> <span>Attendance</span></a></li>
                </ul>
            </div>

            <div class="menu-section">
                <h3>ACCOUNT</h3>
                <ul class="menu-items">
                    <li><a href="profile.php" class="active"><i class="fas fa-user"></i> <span>Profile</span></a></li>
                    <li><a href="../auth/logout.php"><i class="fas fa-sign-out-alt"></i> <span>Logout</span></a></li>
                </ul>
            </div>
        </div>

        <!-- Main Content -->
        <div class="main-content">
            <!-- Header -->
            <div class="dashboard-header">
                <h1>My Profile</h1>
                <p>View and manage your account information</p>
            </div>
        
            <!-- Alert Messages -->
            <?php if($success_message): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    <?php echo $success_message; ?>
                </div>
            <?php endif; ?>

            <?php if($error_message): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i>
                    <?php echo $error_message; ?>
                </div>
            <?php endif; ?>

            <!-- Profile Grid -->
            <div class="profile-grid">
                <!-- Left Column - Profile Info -->
                <div>
                    <div class="profile-card">
                        <div class="profile-avatar-large">
                            <?php echo strtoupper(substr($admin['fullname'], 0, 1)); ?>
                        </div>
                        <h2 class="profile-name"><?php echo htmlspecialchars($admin['fullname']); ?></h2>
                        <span class="profile-role">Administrator</span>

                        <div class="profile-stats">
                            <div class="stat-item">
                                <div class="stat-value">
                                    <?php 
                                    $days = floor((time() - strtotime($account_created)) / (60 * 60 * 24));
                                    echo $days;
                                    ?>
                                </div>
                                <div class="stat-label">Days Active</div>
                            </div>
                            <div class="stat-item">
                                <div class="stat-value">Admin</div>
                                <div class="stat-label">Account Type</div>
                            </div>
                        </div>

                        <div class="profile-info-item">
                            <div class="info-icon">
                                <i class="fas fa-envelope"></i>
                            </div>
                            <div class="info-content">
                                <div class="info-label">Email Address</div>
                                <div class="info-value"><?php echo htmlspecialchars($admin['email']); ?></div>
                            </div>
                        </div>

                        <div class="profile-info-item">
                            <div class="info-icon">
                                <i class="fas fa-id-card"></i>
                            </div>
                            <div class="info-content">
                                <div class="info-label">ID Number</div>
                                <div class="info-value"><?php echo $admin['id_number'] ?? 'Not set'; ?></div>
                            </div>
                        </div>

                        <div class="profile-info-item">
                            <div class="info-icon">
                                <i class="fas fa-calendar-alt"></i>
                            </div>
                            <div class="info-content">
                                <div class="info-label">Member Since</div>
                                <div class="info-value"><?php echo date('F d, Y', strtotime($account_created)); ?></div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Right Column - Edit Forms -->
                <div>
                    <!-- 2FA Section -->
                    <div class="twofa-section">
                        <h3><i class="fas fa-shield-alt"></i> Two-Factor Authentication</h3>
                        
                        <div class="twofa-status">
                            <div class="status-badge">
                                <span class="<?php echo $two_factor_enabled ? 'enabled' : 'disabled'; ?>">
                                    <i class="fas fa-<?php echo $two_factor_enabled ? 'check-circle' : 'times-circle'; ?>"></i>
                                    <?php echo $two_factor_enabled ? 'Enabled' : 'Disabled'; ?>
                                </span>
                            </div>
                            <?php if($two_factor_last_used): ?>
                                <div class="last-used">
                                    <i class="fas fa-clock"></i> Last used: <?php echo date('M d, Y h:i A', strtotime($two_factor_last_used)); ?>
                                </div>
                            <?php endif; ?>
                        </div>

                        <?php if($two_factor_enabled): ?>
                            <form method="POST" style="display: inline;">
                                <input type="hidden" name="action" value="disable">
                                <button type="submit" name="toggle_2fa" class="btn-toggle disable" onclick="return confirm('Are you sure you want to disable Two-Factor Authentication? This will make your account less secure.')">
                                    <i class="fas fa-times-circle"></i> Disable 2FA
                                </button>
                            </form>
                        <?php else: ?>
                            <form method="POST" style="display: inline;">
                                <input type="hidden" name="action" value="enable">
                                <button type="submit" name="toggle_2fa" class="btn-toggle enable">
                                    <i class="fas fa-check-circle"></i> Enable 2FA
                                </button>
                            </form>
                        <?php endif; ?>

                        <div class="info-box">
                            <i class="fas fa-info-circle"></i>
                            <div>
                                <strong>What is Two-Factor Authentication?</strong>
                                <p style="margin-top: 5px;">2FA adds an extra layer of security to your account. When enabled, you'll need to enter a verification code sent to your email after logging in with your password. This helps protect your account even if your password is compromised.</p>
                            </div>
                        </div>
                    </div>

                    <!-- Edit Profile Form -->
                    <div class="form-card">
                        <h3><i class="fas fa-user-edit"></i> Edit Profile Information</h3>
                        <form method="POST" action="">
                            <div class="form-group">
                                <label>Full Name <span>*</span></label>
                                <input type="text" name="fullname" value="<?php echo htmlspecialchars($admin['fullname']); ?>" required>
                            </div>

                            <div class="form-group">
                                <label>Email Address <span>*</span></label>
                                <input type="email" name="email" value="<?php echo htmlspecialchars($admin['email']); ?>" required>
                            </div>

                            <div class="form-group">
                                <label>ID Number</label>
                                <input type="text" name="id_number" value="<?php echo htmlspecialchars($admin['id_number'] ?? ''); ?>" placeholder="Enter your ID number">
                            </div>

                            <div class="form-group">
                                <label>Role</label>
                                <input type="text" value="Administrator" disabled>
                            </div>

                            <button type="submit" name="update_profile" class="btn-submit">
                                <i class="fas fa-save"></i> Update Profile
                            </button>
                        </form>
                    </div>

                    <!-- Change Password Form -->
                    <div class="form-card">
                        <h3><i class="fas fa-lock"></i> Change Password</h3>
                        
                        <div class="password-section">
                            <div class="password-header">
                                <input type="checkbox" id="change_password_checkbox">
                                <label for="change_password_checkbox">I want to change my password</label>
                            </div>

                            <form method="POST" action="" id="passwordForm">
                                <div class="password-fields" id="passwordFields">
                                    <div class="form-group">
                                        <label>Current Password <span>*</span></label>
                                        <input type="password" name="current_password" id="current_password" disabled>
                                    </div>

                                    <div class="form-row">
                                        <div class="form-group">
                                            <label>New Password <span>*</span></label>
                                            <input type="password" name="new_password" id="new_password" disabled>
                                            <div class="password-strength">
                                                <div class="password-strength-bar" id="passwordStrength"></div>
                                            </div>
                                            <div class="password-strength-text" id="passwordStrengthText">
                                                <i class="fas fa-info-circle"></i>
                                                <span>Minimum 6 characters</span>
                                            </div>
                                        </div>

                                        <div class="form-group">
                                            <label>Confirm Password <span>*</span></label>
                                            <input type="password" name="confirm_password" id="confirm_password" disabled>
                                            <div class="password-strength-text" id="passwordMatch">
                                                <i class="fas fa-info-circle"></i>
                                                <span>Re-enter new password</span>
                                            </div>
                                        </div>
                                    </div>

                                    <button type="submit" name="change_password" class="btn-submit" id="changePasswordBtn" disabled>
                                        <i class="fas fa-key"></i> Change Password
                                    </button>
                                </div>
                            </form>
                        </div>
                        
                        <?php if($two_factor_enabled): ?>
                            <div class="info-box" style="margin-top: 15px;">
                                <i class="fas fa-shield-alt"></i>
                                <div>
                                    <strong>2FA Protection:</strong> Since 2FA is enabled, you'll need to verify your identity via email when changing your password.
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Toggle password fields
        const changePasswordCheckbox = document.getElementById('change_password_checkbox');
        const passwordFields = document.getElementById('passwordFields');
        const currentPassword = document.getElementById('current_password');
        const newPassword = document.getElementById('new_password');
        const confirmPassword = document.getElementById('confirm_password');
        const changePasswordBtn = document.getElementById('changePasswordBtn');

        changePasswordCheckbox.addEventListener('change', function() {
            if (this.checked) {
                passwordFields.classList.add('show');
                currentPassword.disabled = false;
                newPassword.disabled = false;
                confirmPassword.disabled = false;
                changePasswordBtn.disabled = false;
                newPassword.focus();
            } else {
                passwordFields.classList.remove('show');
                currentPassword.disabled = true;
                newPassword.disabled = true;
                confirmPassword.disabled = true;
                changePasswordBtn.disabled = true;
                currentPassword.value = '';
                newPassword.value = '';
                confirmPassword.value = '';
                resetPasswordStrength();
            }
        });

        // Password strength checker
        function resetPasswordStrength() {
            document.getElementById('passwordStrength').style.width = '0';
            document.getElementById('passwordStrengthText').innerHTML = 
                '<i class="fas fa-info-circle"></i> <span>Minimum 6 characters</span>';
            document.getElementById('passwordMatch').innerHTML = 
                '<i class="fas fa-info-circle"></i> <span>Re-enter new password</span>';
        }

        function checkPasswordStrength() {
            const password = newPassword.value;
            let strength = 0;
            let strengthLabel = '';
            let strengthColor = '';

            if (password.length >= 6) strength += 1;
            if (password.match(/[a-z]+/)) strength += 1;
            if (password.match(/[A-Z]+/)) strength += 1;
            if (password.match(/[0-9]+/)) strength += 1;
            if (password.match(/[$@#&!]+/)) strength += 1;

            switch(strength) {
                case 0:
                case 1:
                    document.getElementById('passwordStrength').style.width = '20%';
                    document.getElementById('passwordStrength').style.backgroundColor = '#dc3545';
                    strengthLabel = 'Weak';
                    strengthColor = 'strength-weak';
                    break;
                case 2:
                case 3:
                    document.getElementById('passwordStrength').style.width = '60%';
                    document.getElementById('passwordStrength').style.backgroundColor = '#ffc107';
                    strengthLabel = 'Medium';
                    strengthColor = 'strength-medium';
                    break;
                case 4:
                case 5:
                    document.getElementById('passwordStrength').style.width = '100%';
                    document.getElementById('passwordStrength').style.backgroundColor = '#28a745';
                    strengthLabel = 'Strong';
                    strengthColor = 'strength-strong';
                    break;
            }

            if (password.length > 0) {
                document.getElementById('passwordStrengthText').innerHTML = 
                    `<i class="fas fa-shield-alt"></i> <span class="${strengthColor}">Password strength: ${strengthLabel}</span>`;
            } else {
                resetPasswordStrength();
            }
        }

        function checkPasswordMatch() {
            const password = newPassword.value;
            const confirm = confirmPassword.value;

            if (confirm.length > 0) {
                if (password === confirm) {
                    document.getElementById('passwordMatch').innerHTML = 
                        '<i class="fas fa-check-circle" style="color: #28a745;"></i> <span style="color: #28a745;">Passwords match</span>';
                } else {
                    document.getElementById('passwordMatch').innerHTML = 
                        '<i class="fas fa-exclamation-circle" style="color: #dc3545;"></i> <span style="color: #dc3545;">Passwords do not match</span>';
                }
            } else {
                document.getElementById('passwordMatch').innerHTML = 
                    '<i class="fas fa-info-circle"></i> <span>Re-enter new password</span>';
            }
        }

        newPassword.addEventListener('input', checkPasswordStrength);
        newPassword.addEventListener('input', checkPasswordMatch);
        confirmPassword.addEventListener('input', checkPasswordMatch);

        // Form validation for password change
        document.getElementById('passwordForm').addEventListener('submit', function(e) {
            if (changePasswordCheckbox.checked) {
                const current = currentPassword.value;
                const password = newPassword.value;
                const confirm = confirmPassword.value;

                if (!current) {
                    e.preventDefault();
                    alert('Please enter your current password');
                } else if (password.length < 6) {
                    e.preventDefault();
                    alert('New password must be at least 6 characters long');
                } else if (password !== confirm) {
                    e.preventDefault();
                    alert('New passwords do not match');
                }
            } else {
                e.preventDefault();
            }
        });

        // Auto-hide alerts after 5 seconds
        setTimeout(function() {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                alert.style.opacity = '0';
                setTimeout(() => {
                    alert.style.display = 'none';
                }, 300);
            });
        }, 5000);
    </script>
</body>
</html>