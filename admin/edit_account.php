<?php
session_start();
include("../config/database.php");

// Check if user is admin
if(!isset($_SESSION['user']) || $_SESSION['user']['role'] != 'Admin'){
    header("Location: ../auth/login.php");
    exit();
}

$admin_name = $_SESSION['user']['fullname'];

// Check if ID is provided
if(!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: manage_accounts.php");
    exit();
}

$account_id = $_GET['id'];

// Get account details
$query = "SELECT * FROM users WHERE id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $account_id);
$stmt->execute();
$result = $stmt->get_result();

if($result->num_rows === 0) {
    header("Location: manage_accounts.php");
    exit();
}

$account = $result->fetch_assoc();
$stmt->close();

// Prevent editing own account? (Optional - can be removed if you want to allow self-editing)
// if($account_id == $_SESSION['user']['id']) {
//     header("Location: profile.php");
//     exit();
// }

// Handle form submission
if($_SERVER['REQUEST_METHOD'] == 'POST') {
    if(isset($_POST['update_account'])) {
        $fullname = trim($_POST['fullname']);
        $email = trim($_POST['email']);
        $role = $_POST['role'];
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
        
        if(empty($role)) {
            $errors[] = "Role is required";
        }
        
        // Check if email already exists (excluding current account)
        if(empty($errors)) {
            $check_email = $conn->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
            $check_email->bind_param("si", $email, $account_id);
            $check_email->execute();
            $check_email->store_result();
            
            if($check_email->num_rows > 0) {
                $errors[] = "Email address already registered to another user";
            }
            $check_email->close();
        }
        
        // Check if ID number already exists (if provided and excluding current account)
        if(empty($errors) && $id_number) {
            $check_id = $conn->prepare("SELECT id FROM users WHERE id_number = ? AND id != ?");
            $check_id->bind_param("si", $id_number, $account_id);
            $check_id->execute();
            $check_id->store_result();
            
            if($check_id->num_rows > 0) {
                $errors[] = "ID number already exists for another user";
            }
            $check_id->close();
        }
        
        // If no errors, update the account
        if(empty($errors)) {
            if($id_number) {
                $update_query = "UPDATE users SET fullname = ?, email = ?, role = ?, id_number = ? WHERE id = ?";
                $update_stmt = $conn->prepare($update_query);
                $update_stmt->bind_param("ssssi", $fullname, $email, $role, $id_number, $account_id);
            } else {
                $update_query = "UPDATE users SET fullname = ?, email = ?, role = ?, id_number = NULL WHERE id = ?";
                $update_stmt = $conn->prepare($update_query);
                $update_stmt->bind_param("sssi", $fullname, $email, $role, $account_id);
            }
            
            if($update_stmt->execute()) {
                $_SESSION['success_message'] = "Account updated successfully!";
                header("Location: view_account.php?id=" . $account_id);
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
    
    // Handle password reset
    if(isset($_POST['reset_password'])) {
        $new_password = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];
        
        $errors = [];
        
        if(empty($new_password)) {
            $errors[] = "New password is required";
        } elseif(strlen($new_password) < 6) {
            $errors[] = "Password must be at least 6 characters";
        }
        
        if($new_password !== $confirm_password) {
            $errors[] = "Passwords do not match";
        }
        
        if(empty($errors)) {
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            $update_query = "UPDATE users SET password = ? WHERE id = ?";
            $update_stmt = $conn->prepare($update_query);
            $update_stmt->bind_param("si", $hashed_password, $account_id);
            
            if($update_stmt->execute()) {
                $_SESSION['success_message'] = "Password reset successfully!";
                header("Location: view_account.php?id=" . $account_id);
                exit();
            } else {
                $errors[] = "Database error: " . $conn->error;
            }
            $update_stmt->close();
        }
        
        if(!empty($errors)) {
            $error_message = implode("<br>", $errors);
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Account - Admin Dashboard</title>
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
            --primary-color: #4361ee;
            --secondary-color: #3f37c9;
            --success-color: #4cc9f0;
            --warning-color: #f72585;
            --info-color: #4895ef;
            --dark-bg: #1a1a2e;
            --sidebar-bg: #16213e;
            --card-bg: #ffffff;
            --text-primary: #2b2d42;
            --text-secondary: #8d99ae;
            --border-color: #e9ecef;
            --hover-color: #f8f9fa;
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
            background: linear-gradient(135deg, #0B4F2E, #1a7a42);
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
            color: #FFD700;
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
            background: #FFD700;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 15px;
            font-size: 32px;
            font-weight: bold;
            color: #0B4F2E;
            border: 3px solid white;
        }

        .admin-info h3 {
            font-size: 18px;
            margin-bottom: 5px;
            color: #FFD700;
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
            color: #FFD700;
        }

        .menu-items a.active {
            background: rgba(255, 255, 255, 0.15);
            border-left: 3px solid #FFD700;
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
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .header-left h1 {
            font-size: 28px;
            color: var(--text-primary);
            margin-bottom: 10px;
            font-weight: 700;
        }

        .header-left p {
            color: var(--text-secondary);
            font-size: 16px;
        }

        .back-btn {
            background: white;
            color: var(--text-primary);
            padding: 12px 25px;
            border-radius: 12px;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 10px;
            transition: all 0.3s ease;
            font-weight: 500;
            border: 1px solid var(--border-color);
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
        }

        .back-btn:hover {
            border-color: #0B4F2E;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(11, 79, 46, 0.1);
        }

        .back-btn i {
            color: #0B4F2E;
        }

        /* Welcome Card */
        .welcome-card {
            background: linear-gradient(135deg, #0B4F2E, #1a7a42);
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
            color: #FFD700;
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
            border-left: 4px solid #28a745;
        }

        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border-left: 4px solid #dc3545;
        }

        .alert i {
            font-size: 20px;
        }

        /* Account Info Card */
        .account-info-card {
            background: white;
            border-radius: 20px;
            padding: 25px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            gap: 25px;
            flex-wrap: wrap;
        }

        .account-avatar-large {
            width: 100px;
            height: 100px;
            background: linear-gradient(135deg, #0B4F2E, #1a7a42);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 48px;
            font-weight: bold;
            color: white;
            border: 4px solid #FFD700;
        }

        .account-details {
            flex: 1;
        }

        .account-details h2 {
            font-size: 24px;
            color: var(--text-primary);
            margin-bottom: 5px;
        }

        .account-meta {
            display: flex;
            gap: 20px;
            flex-wrap: wrap;
            color: var(--text-secondary);
            font-size: 14px;
            margin-bottom: 10px;
        }

        .account-meta i {
            color: #0B4F2E;
            margin-right: 5px;
        }

        .role-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            color: white;
        }

        .role-admin {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
        }

        .role-registrar {
            background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
        }

        .role-teacher {
            background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);
        }

        .role-student {
            background: linear-gradient(135deg, #fa709a 0%, #fee140 100%);
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
            color: #0B4F2E;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 20px;
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

        .form-group input,
        .form-group select {
            width: 100%;
            padding: 12px 16px;
            border: 2px solid var(--border-color);
            border-radius: 10px;
            font-size: 14px;
            transition: all 0.3s;
            background: #f8f9fa;
        }

        .form-group input:focus,
        .form-group select:focus {
            border-color: #0B4F2E;
            background: white;
            outline: none;
            box-shadow: 0 0 0 4px rgba(11, 79, 46, 0.1);
        }

        .form-group input.error {
            border-color: #dc3545;
            background: #fff8f8;
        }

        .form-group input:disabled {
            background: #e9ecef;
            cursor: not-allowed;
        }

        .form-hint {
            font-size: 12px;
            color: var(--text-secondary);
            margin-top: 5px;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .form-hint i {
            color: #0B4F2E;
        }

        /* Password Reset Section */
        .password-reset-section {
            background: #f8f9fa;
            border-radius: 15px;
            padding: 20px;
            margin: 20px 0;
        }

        .password-reset-section h4 {
            color: var(--text-primary);
            font-size: 16px;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .password-reset-section h4 i {
            color: #0B4F2E;
        }

        .password-fields {
            display: none;
        }

        .password-fields.show {
            display: block;
        }

        .password-header {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 15px;
        }

        .password-header input[type="checkbox"] {
            width: 18px;
            height: 18px;
            accent-color: #0B4F2E;
            cursor: pointer;
        }

        .password-header label {
            color: var(--text-primary);
            font-weight: 500;
            cursor: pointer;
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
            color: #dc3545;
        }

        .strength-medium {
            color: #ffc107;
        }

        .strength-strong {
            color: #28a745;
        }

        /* Preview Card */
        .preview-card {
            background: linear-gradient(135deg, #f8f9fa, #e9ecef);
            border-radius: 15px;
            padding: 20px;
            margin: 25px 0;
        }

        .preview-card h4 {
            color: var(--text-primary);
            font-size: 16px;
            font-weight: 600;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .preview-card h4 i {
            color: #0B4F2E;
        }

        .preview-item {
            display: flex;
            align-items: center;
            gap: 20px;
            padding: 20px;
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
        }

        .preview-avatar {
            width: 60px;
            height: 60px;
            background: linear-gradient(135deg, #0B4F2E, #1a7a42);
            border-radius: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 28px;
            font-weight: 600;
        }

        .preview-details {
            flex: 1;
        }

        .preview-details h5 {
            color: var(--text-primary);
            font-size: 18px;
            margin-bottom: 5px;
        }

        .preview-details .preview-role {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            color: white;
        }

        .preview-details .preview-email {
            color: var(--text-secondary);
            font-size: 13px;
            margin-top: 5px;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        /* Form Actions */
        .form-actions {
            display: flex;
            gap: 15px;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid var(--border-color);
        }

        .btn-primary {
            flex: 1;
            background: #0B4F2E;
            color: white;
            padding: 14px;
            border: none;
            border-radius: 12px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }

        .btn-primary:hover {
            background: #1a7a42;
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(11, 79, 46, 0.3);
        }

        .btn-secondary {
            flex: 1;
            background: white;
            color: var(--text-secondary);
            padding: 14px;
            border: 2px solid var(--border-color);
            border-radius: 12px;
            font-size: 16px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
            text-align: center;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }

        .btn-secondary:hover {
            border-color: #0B4F2E;
            color: #0B4F2E;
        }

        .btn-warning {
            background: #ffc107;
            color: #2b2d42;
            padding: 12px 20px;
            border: none;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-warning:hover {
            background: #e0a800;
            transform: translateY(-2px);
        }

        /* Responsive */
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
            
            .account-info-card {
                flex-direction: column;
                text-align: center;
            }
            
            .account-meta {
                justify-content: center;
            }
            
            .form-row {
                grid-template-columns: 1fr;
            }
            
            .form-actions {
                flex-direction: column;
            }
            
            .preview-item {
                flex-direction: column;
                text-align: center;
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
                    <li><a href="manage_accounts.php" class="active"><i class="fas fa-users-cog"></i> <span>Accounts</span></a></li>
                    <li><a href="attendance.php"><i class="fas fa-calendar-check"></i> <span>Attendance</span></a></li>
                </ul>
            </div>

            <div class="menu-section">
                <h3>ACCOUNT</h3>
                <ul class="menu-items">
                    <li><a href="profile.php"><i class="fas fa-user"></i> <span>Profile</span></a></li>
                    <li><a href="../auth/logout.php"><i class="fas fa-sign-out-alt"></i> <span>Logout</span></a></li>
                </ul>
            </div>
        </div>

        <!-- Main Content -->
        <div class="main-content">
            <!-- Header -->
            <div class="dashboard-header">
                <div class="header-left">
                    <h1>Edit Account</h1>
                    <p>Update user account information</p>
                </div>
                <a href="manage_accounts.php" class="back-btn">
                    <i class="fas fa-arrow-left"></i> Back to Accounts
                </a>
            </div>

            <!-- Welcome Card -->
            <div class="welcome-card">
                <div class="welcome-text">
                    <h2>Welcome back, <?php echo htmlspecialchars(explode(' ', $admin_name)[0]); ?>! 👋</h2>
                    <p><i class="fas fa-calendar"></i> <?php echo date('l, F j, Y'); ?></p>
                </div>
                <a href="../auth/logout.php" class="logout-btn">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a>
            </div>

            <!-- Alert Messages -->
            <?php if(isset($_SESSION['success_message'])): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    <?php echo $_SESSION['success_message']; unset($_SESSION['success_message']); ?>
                </div>
            <?php endif; ?>

            <?php if(isset($error_message)): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i>
                    <?php echo $error_message; ?>
                </div>
            <?php endif; ?>

            <!-- Account Info Card -->
            <div class="account-info-card">
                <div class="account-avatar-large">
                    <?php echo strtoupper(substr($account['fullname'], 0, 1)); ?>
                </div>
                <div class="account-details">
                    <h2><?php echo htmlspecialchars($account['fullname']); ?></h2>
                    <div class="account-meta">
                        <span><i class="fas fa-envelope"></i> <?php echo htmlspecialchars($account['email']); ?></span>
                        <span><i class="fas fa-id-card"></i> ID: <?php echo $account['id_number'] ?? 'Not assigned'; ?></span>
                        <span><i class="fas fa-calendar-alt"></i> Registered: <?php echo date('M d, Y', strtotime($account['created_at'])); ?></span>
                    </div>
                    <div>
                        <span class="role-badge role-<?php echo strtolower($account['role']); ?>">
                            <i class="fas fa-<?php 
                                echo $account['role'] == 'Admin' ? 'user-shield' : 
                                    ($account['role'] == 'Registrar' ? 'user-tie' : 
                                    ($account['role'] == 'Teacher' ? 'chalkboard-teacher' : 'user-graduate')); 
                            ?>"></i>
                            Current Role: <?php echo $account['role']; ?>
                        </span>
                    </div>
                </div>
            </div>

            <!-- Edit Account Form -->
            <div class="form-card">
                <h3><i class="fas fa-user-edit"></i> Edit Account Information</h3>
                
                <form method="POST" action="" id="editAccountForm">
                    <div class="form-row">
                        <div class="form-group">
                            <label>Full Name <span>*</span></label>
                            <input type="text" name="fullname" id="fullname" value="<?php echo htmlspecialchars($account['fullname']); ?>" required>
                        </div>

                        <div class="form-group">
                            <label>Email Address <span>*</span></label>
                            <input type="email" name="email" id="email" value="<?php echo htmlspecialchars($account['email']); ?>" required>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label>Role <span>*</span></label>
                            <select name="role" id="role" required>
                                <option value="Admin" <?php echo $account['role'] == 'Admin' ? 'selected' : ''; ?>>Admin</option>
                                <option value="Registrar" <?php echo $account['role'] == 'Registrar' ? 'selected' : ''; ?>>Registrar</option>
                                <option value="Teacher" <?php echo $account['role'] == 'Teacher' ? 'selected' : ''; ?>>Teacher</option>
                                <option value="Student" <?php echo $account['role'] == 'Student' ? 'selected' : ''; ?>>Student</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label>ID Number</label>
                            <input type="text" name="id_number" id="id_number" value="<?php echo htmlspecialchars($account['id_number'] ?? ''); ?>" placeholder="Enter ID number">
                        </div>
                    </div>

                    <div class="form-hint">
                        <i class="fas fa-info-circle"></i>
                        Leave ID number blank if not applicable
                    </div>

                    <!-- Live Preview -->
                    <div class="preview-card">
                        <h4><i class="fas fa-eye"></i> Live Preview</h4>
                        <div class="preview-item">
                            <div class="preview-avatar" id="previewInitial">
                                <?php echo strtoupper(substr($account['fullname'], 0, 1)); ?>
                            </div>
                            <div class="preview-details">
                                <h5 id="previewName"><?php echo htmlspecialchars($account['fullname']); ?></h5>
                                <div>
                                    <span class="preview-role role-<?php echo strtolower($account['role']); ?>" id="previewRole">
                                        <?php echo $account['role']; ?>
                                    </span>
                                </div>
                                <div class="preview-email" id="previewEmail">
                                    <i class="fas fa-envelope"></i> <?php echo htmlspecialchars($account['email']); ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="form-actions">
                        <button type="submit" name="update_account" class="btn-primary">
                            <i class="fas fa-save"></i> Update Account
                        </button>
                        <a href="view_account.php?id=<?php echo $account_id; ?>" class="btn-secondary">
                            <i class="fas fa-times"></i> Cancel
                        </a>
                    </div>
                </form>
            </div>

            <!-- Password Reset Form -->
            <div class="form-card">
                <h3><i class="fas fa-key"></i> Password Management</h3>
                
                <div class="password-reset-section">
                    <div class="password-header">
                        <input type="checkbox" id="reset_password_checkbox">
                        <label for="reset_password_checkbox">Reset user password</label>
                    </div>

                    <form method="POST" action="" id="passwordForm">
                        <div class="password-fields" id="passwordFields">
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

                            <div class="form-actions" style="margin-top: 0; border-top: none; padding-top: 0;">
                                <button type="submit" name="reset_password" class="btn-warning" id="resetPasswordBtn" disabled>
                                    <i class="fas fa-key"></i> Reset Password
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Live preview update
        const fullnameInput = document.getElementById('fullname');
        const emailInput = document.getElementById('email');
        const roleSelect = document.getElementById('role');
        const previewName = document.getElementById('previewName');
        const previewEmail = document.getElementById('previewEmail');
        const previewRole = document.getElementById('previewRole');
        const previewInitial = document.getElementById('previewInitial');

        function updatePreview() {
            // Update name and initial
            const fullname = fullnameInput.value.trim() || 'User Name';
            previewName.textContent = fullname;
            
            const initial = fullname.charAt(0).toUpperCase() || 'U';
            previewInitial.textContent = initial;

            // Update email
            const email = emailInput.value.trim() || 'email@example.com';
            previewEmail.innerHTML = `<i class="fas fa-envelope"></i> ${email}`;

            // Update role
            const role = roleSelect.value || 'Student';
            previewRole.textContent = role;
            
            // Update role class
            previewRole.className = 'preview-role role-' + role.toLowerCase();
        }

        fullnameInput.addEventListener('input', updatePreview);
        emailInput.addEventListener('input', updatePreview);
        roleSelect.addEventListener('change', updatePreview);

        // Toggle password fields
        const resetPasswordCheckbox = document.getElementById('reset_password_checkbox');
        const passwordFields = document.getElementById('passwordFields');
        const newPassword = document.getElementById('new_password');
        const confirmPassword = document.getElementById('confirm_password');
        const resetPasswordBtn = document.getElementById('resetPasswordBtn');

        if(resetPasswordCheckbox) {
            resetPasswordCheckbox.addEventListener('change', function() {
                if (this.checked) {
                    passwordFields.classList.add('show');
                    newPassword.disabled = false;
                    confirmPassword.disabled = false;
                    resetPasswordBtn.disabled = false;
                    newPassword.focus();
                } else {
                    passwordFields.classList.remove('show');
                    newPassword.disabled = true;
                    confirmPassword.disabled = true;
                    resetPasswordBtn.disabled = true;
                    newPassword.value = '';
                    confirmPassword.value = '';
                    resetPasswordStrength();
                }
            });
        }

        // Password strength checker
        function resetPasswordStrength() {
            const strengthBar = document.getElementById('passwordStrength');
            const strengthText = document.getElementById('passwordStrengthText');
            const passwordMatch = document.getElementById('passwordMatch');
            
            if(strengthBar) strengthBar.style.width = '0';
            if(strengthText) strengthText.innerHTML = '<i class="fas fa-info-circle"></i> <span>Minimum 6 characters</span>';
            if(passwordMatch) passwordMatch.innerHTML = '<i class="fas fa-info-circle"></i> <span>Re-enter new password</span>';
        }

        function checkPasswordStrength() {
            const password = document.getElementById('new_password').value;
            let strength = 0;
            let strengthLabel = '';
            let strengthColor = '';

            if (password.length >= 6) strength += 1;
            if (password.length >= 8) strength += 1;
            if (/[a-z]/.test(password)) strength += 1;
            if (/[A-Z]/.test(password)) strength += 1;
            if (/[0-9]/.test(password)) strength += 1;
            if (/[^A-Za-z0-9]/.test(password)) strength += 1;

            switch(strength) {
                case 0:
                case 1:
                case 2:
                    document.getElementById('passwordStrength').style.width = '33%';
                    document.getElementById('passwordStrength').style.backgroundColor = '#dc3545';
                    strengthLabel = 'Weak';
                    strengthColor = 'strength-weak';
                    break;
                case 3:
                case 4:
                    document.getElementById('passwordStrength').style.width = '66%';
                    document.getElementById('passwordStrength').style.backgroundColor = '#ffc107';
                    strengthLabel = 'Medium';
                    strengthColor = 'strength-medium';
                    break;
                case 5:
                case 6:
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
            const password = document.getElementById('new_password').value;
            const confirm = document.getElementById('confirm_password').value;

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

        const newPwd = document.getElementById('new_password');
        const confirmPwd = document.getElementById('confirm_password');
        
        if(newPwd) {
            newPwd.addEventListener('input', checkPasswordStrength);
            newPwd.addEventListener('input', checkPasswordMatch);
        }
        
        if(confirmPwd) {
            confirmPwd.addEventListener('input', checkPasswordMatch);
        }

        // Form validation for password reset
        const passwordForm = document.getElementById('passwordForm');
        if(passwordForm) {
            passwordForm.addEventListener('submit', function(e) {
                if (resetPasswordCheckbox && resetPasswordCheckbox.checked) {
                    const password = document.getElementById('new_password').value;
                    const confirm = document.getElementById('confirm_password').value;

                    if (password.length < 6) {
                        e.preventDefault();
                        alert('Password must be at least 6 characters long');
                    } else if (password !== confirm) {
                        e.preventDefault();
                        alert('Passwords do not match');
                    }
                } else {
                    e.preventDefault();
                }
            });
        }

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