<?php
session_start();
include("../config/database.php");

// Check if user is student
if(!isset($_SESSION['user']) || $_SESSION['user']['role'] != 'Student'){
    header("Location: ../auth/login.php");
    exit();
}

$student_id = $_SESSION['user']['id'];
$student_name = $_SESSION['user']['fullname'];
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

// Get student details
$query = "SELECT * FROM users WHERE id = ? AND role = 'Student'";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $student_id);
$stmt->execute();
$result = $stmt->get_result();
$student = $result->fetch_assoc();
$stmt->close();

// Get student's enrollment information
$enrollment_query = "
    SELECT e.*, g.grade_name
    FROM enrollments e
    JOIN grade_levels g ON e.grade_id = g.id
    WHERE e.student_id = ? AND e.status = 'Enrolled'
    ORDER BY e.created_at DESC LIMIT 1
";
$stmt = $conn->prepare($enrollment_query);
$stmt->bind_param("i", $student_id);
$stmt->execute();
$enrollment = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Handle profile update
if($_SERVER['REQUEST_METHOD'] == 'POST') {
    if(isset($_POST['update_profile'])) {
        $fullname = trim($_POST['fullname']);
        $email = trim($_POST['email']);
        $id_number = !empty($_POST['id_number']) ? trim($_POST['id_number']) : null;
        $phone = trim($_POST['phone']);
        $address = trim($_POST['address']);
        
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
        
        // Check if email already exists (excluding current student)
        if(empty($errors)) {
            $check_email = $conn->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
            $check_email->bind_param("si", $email, $student_id);
            $check_email->execute();
            $check_email->store_result();
            
            if($check_email->num_rows > 0) {
                $errors[] = "Email address already registered to another user";
            }
            $check_email->close();
        }
        
        // Check if ID number already exists (if provided and excluding current student)
        if(empty($errors) && $id_number) {
            $check_id = $conn->prepare("SELECT id FROM users WHERE id_number = ? AND id != ?");
            $check_id->bind_param("si", $id_number, $student_id);
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
            $update_stmt->bind_param("sssi", $fullname, $email, $id_number, $student_id);
            
            if($update_stmt->execute()) {
                // Update session
                $_SESSION['user']['fullname'] = $fullname;
                $_SESSION['user']['email'] = $email;
                $_SESSION['user']['id_number'] = $id_number;
                
                $_SESSION['success_message'] = "Profile updated successfully!";
                header("Location: settings.php");
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
            if(!password_verify($current_password, $student['password'])) {
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
        
        // If no errors, update password
        if(empty($errors)) {
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            $update_query = "UPDATE users SET password = ? WHERE id = ?";
            $update_stmt = $conn->prepare($update_query);
            $update_stmt->bind_param("si", $hashed_password, $student_id);
            
            if($update_stmt->execute()) {
                $_SESSION['success_message'] = "Password changed successfully!";
                header("Location: settings.php");
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
    
    // Handle notification settings
    if(isset($_POST['save_notifications'])) {
        $email_notifications = isset($_POST['email_notifications']) ? 1 : 0;
        $sms_notifications = isset($_POST['sms_notifications']) ? 1 : 0;
        $grade_alerts = isset($_POST['grade_alerts']) ? 1 : 0;
        $attendance_alerts = isset($_POST['attendance_alerts']) ? 1 : 0;
        $announcements = isset($_POST['announcements']) ? 1 : 0;
        
        // In a real application, you would save these to a user_settings table
        // For now, we'll just show a success message
        $_SESSION['success_message'] = "Notification preferences saved successfully!";
        header("Location: settings.php");
        exit();
    }
    
    // Handle privacy settings
    if(isset($_POST['save_privacy'])) {
        $profile_visibility = $_POST['profile_visibility'];
        $show_grades = isset($_POST['show_grades']) ? 1 : 0;
        $show_attendance = isset($_POST['show_attendance']) ? 1 : 0;
        
        // In a real application, you would save these to a user_settings table
        $_SESSION['success_message'] = "Privacy settings saved successfully!";
        header("Location: settings.php");
        exit();
    }
}

// Get current settings (default values)
$email_notifications = true;
$sms_notifications = false;
$grade_alerts = true;
$attendance_alerts = true;
$announcements = true;
$profile_visibility = 'private';
$show_grades = false;
$show_attendance = false;

$account_created = $student['created_at'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings - Student Dashboard</title>
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

        .student-info {
            text-align: center;
            padding: 20px 0;
            border-bottom: 1px solid rgba(255, 255, 255, 0.2);
            margin-bottom: 20px;
        }

        .student-avatar {
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

        .student-info h3 {
            font-size: 18px;
            margin-bottom: 5px;
            color: #FFD700;
        }

        .student-info p {
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

        /* Settings Navigation */
        .settings-nav {
            background: white;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 30px;
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
        }

        .settings-tab {
            padding: 12px 25px;
            border-radius: 10px;
            text-decoration: none;
            font-size: 14px;
            font-weight: 500;
            transition: all 0.3s;
            color: var(--text-secondary);
            border: 2px solid transparent;
        }

        .settings-tab:hover {
            border-color: #0B4F2E;
            color: #0B4F2E;
        }

        .settings-tab.active {
            background: #0B4F2E;
            color: white;
        }

        .settings-tab i {
            margin-right: 8px;
        }

        /* Settings Card */
        .settings-card {
            background: white;
            border-radius: 20px;
            padding: 30px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            margin-bottom: 25px;
        }

        .settings-card h3 {
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

        .settings-card h3 i {
            color: #0B4F2E;
        }

        .settings-card h4 {
            color: var(--text-primary);
            font-size: 16px;
            font-weight: 600;
            margin: 20px 0 15px;
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
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 12px 16px;
            border: 2px solid var(--border-color);
            border-radius: 10px;
            font-size: 14px;
            transition: all 0.3s;
            background: #f8f9fa;
            font-family: 'Inter', sans-serif;
        }

        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            border-color: #0B4F2E;
            background: white;
            outline: none;
            box-shadow: 0 0 0 4px rgba(11, 79, 46, 0.1);
        }

        .form-group input:disabled {
            background: #e9ecef;
            cursor: not-allowed;
        }

        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 15px;
        }

        .checkbox-group input[type="checkbox"] {
            width: 18px;
            height: 18px;
            accent-color: #0B4F2E;
            cursor: pointer;
        }

        .checkbox-group label {
            color: var(--text-primary);
            font-size: 14px;
            cursor: pointer;
        }

        .radio-group {
            display: flex;
            gap: 20px;
            margin-bottom: 15px;
        }

        .radio-option {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .radio-option input[type="radio"] {
            width: 16px;
            height: 16px;
            accent-color: #0B4F2E;
            cursor: pointer;
        }

        .radio-option label {
            color: var(--text-primary);
            font-size: 14px;
            cursor: pointer;
        }

        .info-box {
            background: #e8f4fd;
            border-left: 4px solid #0B4F2E;
            padding: 15px 20px;
            border-radius: 10px;
            margin: 20px 0;
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .info-box i {
            font-size: 24px;
            color: #0B4F2E;
        }

        .info-box p {
            color: var(--text-primary);
            font-size: 14px;
            margin: 0;
        }

        .btn-submit {
            background: #0B4F2E;
            color: white;
            padding: 14px 30px;
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
            background: #1a7a42;
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(11, 79, 46, 0.3);
        }

        .btn-submit i {
            font-size: 16px;
        }

        .btn-cancel {
            background: #f8f9fa;
            color: var(--text-secondary);
            padding: 14px 30px;
            border: 2px solid var(--border-color);
            border-radius: 12px;
            font-size: 16px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            margin-left: 15px;
        }

        .btn-cancel:hover {
            border-color: #dc3545;
            color: #dc3545;
        }

        .form-actions {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid var(--border-color);
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
            accent-color: #0B4F2E;
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
            color: #dc3545;
        }

        .strength-medium {
            color: #ffc107;
        }

        .strength-strong {
            color: #28a745;
        }

        /* Danger Zone */
        .danger-zone {
            border: 2px solid #dc3545;
            border-radius: 15px;
            padding: 20px;
            margin-top: 30px;
        }

        .danger-zone h4 {
            color: #dc3545;
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 15px;
        }

        .danger-zone p {
            color: var(--text-secondary);
            font-size: 14px;
            margin-bottom: 20px;
        }

        .btn-danger {
            background: #dc3545;
            color: white;
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

        .btn-danger:hover {
            background: #c82333;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(220, 53, 69, 0.3);
        }

        /* Responsive */
        @media (max-width: 768px) {
            .sidebar {
                width: 80px;
                padding: 20px 10px;
            }
            
            .sidebar h2 span,
            .student-info h3,
            .student-info p,
            .menu-section h3,
            .menu-items a span {
                display: none;
            }
            
            .student-avatar {
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
            
            .radio-group {
                flex-direction: column;
                gap: 10px;
            }
            
            .btn-submit, .btn-cancel {
                width: 100%;
                margin: 5px 0;
                justify-content: center;
            }
            
            .settings-tab {
                width: 100%;
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
            
            <div class="student-info">
                <div class="student-avatar">
                    <?php echo strtoupper(substr($student_name, 0, 1)); ?>
                </div>
                <h3><?php echo htmlspecialchars(explode(' ', $student_name)[0]); ?></h3>
                <p><i class="fas fa-user-graduate"></i> Student</p>
            </div>
            
            <div class="menu-section">
                <h3>MAIN MENU</h3>
                <ul class="menu-items">
                    <li><a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> <span>Dashboard</span></a></li>
                    <li><a href="profile.php"><i class="fas fa-user"></i> <span>My Profile</span></a></li>
                    <li><a href="schedule.php"><i class="fas fa-calendar-alt"></i> <span>Class Schedule</span></a></li>
                    <li><a href="grades.php"><i class="fas fa-star"></i> <span>My Grades</span></a></li>
                    <li><a href="attendance.php"><i class="fas fa-calendar-check"></i> <span>Attendance</span></a></li>
                </ul>
            </div>

            <div class="menu-section">
                <h3>ACCOUNT</h3>
                <ul class="menu-items">
                    <li><a href="settings.php" class="active"><i class="fas fa-cog"></i> <span>Settings</span></a></li>
                    <li><a href="../auth/logout.php"><i class="fas fa-sign-out-alt"></i> <span>Logout</span></a></li>
                </ul>
            </div>
        </div>

        <!-- Main Content -->
        <div class="main-content">
            <!-- Header -->
            <div class="dashboard-header">
                <h1>Settings</h1>
                <p>Manage your account preferences and security</p>
            </div>

            <!-- Welcome Card -->
            <div class="welcome-card">
                <div class="welcome-text">
                    <h2>Welcome back, <?php echo htmlspecialchars(explode(' ', $student_name)[0]); ?>! 👋</h2>
                    <p><i class="fas fa-calendar"></i> <?php echo date('l, F j, Y'); ?></p>
                </div>
                <a href="../auth/logout.php" class="logout-btn">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a>
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

            <!-- Settings Navigation -->
            <div class="settings-nav">
                <a href="#profile" class="settings-tab active" onclick="showTab('profile')">
                    <i class="fas fa-user"></i> Profile
                </a>
                <a href="#account" class="settings-tab" onclick="showTab('account')">
                    <i class="fas fa-lock"></i> Account Security
                </a>
                <a href="#notifications" class="settings-tab" onclick="showTab('notifications')">
                    <i class="fas fa-bell"></i> Notifications
                </a>
                <a href="#privacy" class="settings-tab" onclick="showTab('privacy')">
                    <i class="fas fa-shield-alt"></i> Privacy
                </a>
                <a href="#danger" class="settings-tab" onclick="showTab('danger')">
                    <i class="fas fa-exclamation-triangle"></i> Danger Zone
                </a>
            </div>

            <!-- Profile Settings Tab -->
            <div id="profile" class="settings-tab-content">
                <div class="settings-card">
                    <h3><i class="fas fa-user-edit"></i> Edit Profile Information</h3>
                    <form method="POST" action="">
                        <div class="form-row">
                            <div class="form-group">
                                <label>Full Name <span>*</span></label>
                                <input type="text" name="fullname" value="<?php echo htmlspecialchars($student['fullname']); ?>" required>
                            </div>

                            <div class="form-group">
                                <label>Email Address <span>*</span></label>
                                <input type="email" name="email" value="<?php echo htmlspecialchars($student['email']); ?>" required>
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label>ID Number</label>
                                <input type="text" name="id_number" value="<?php echo htmlspecialchars($student['id_number'] ?? ''); ?>" placeholder="Enter your ID number">
                            </div>

                            <div class="form-group">
                                <label>Phone Number</label>
                                <input type="text" name="phone" value="<?php echo isset($_POST['phone']) ? htmlspecialchars($_POST['phone']) : ''; ?>" placeholder="Enter your phone number">
                            </div>
                        </div>

                        <div class="form-group">
                            <label>Address</label>
                            <textarea name="address" rows="3" placeholder="Enter your complete address"><?php echo isset($_POST['address']) ? htmlspecialchars($_POST['address']) : ''; ?></textarea>
                        </div>

                        <div class="info-box">
                            <i class="fas fa-info-circle"></i>
                            <p>Your grade level and strand information cannot be changed here. Please contact the registrar's office for updates.</p>
                        </div>

                        <div class="form-actions">
                            <button type="submit" name="update_profile" class="btn-submit">
                                <i class="fas fa-save"></i> Update Profile
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Account Security Tab -->
            <div id="account" class="settings-tab-content" style="display: none;">
                <div class="settings-card">
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

                                <div class="form-actions">
                                    <button type="submit" name="change_password" class="btn-submit" id="changePasswordBtn" disabled>
                                        <i class="fas fa-key"></i> Change Password
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>

                <div class="settings-card">
                    <h3><i class="fas fa-history"></i> Recent Activity</h3>
                    <div style="text-align: center; padding: 20px;">
                        <i class="fas fa-clock" style="font-size: 40px; color: var(--text-secondary); opacity: 0.3; margin-bottom: 10px;"></i>
                        <p style="color: var(--text-secondary);">Last login: <?php echo date('F d, Y h:i A', strtotime($student['created_at'])); ?></p>
                        <p style="color: var(--text-secondary);">Account created: <?php echo date('F d, Y', strtotime($account_created)); ?></p>
                    </div>
                </div>
            </div>

            <!-- Notifications Tab -->
            <div id="notifications" class="settings-tab-content" style="display: none;">
                <div class="settings-card">
                    <h3><i class="fas fa-bell"></i> Notification Preferences</h3>
                    <form method="POST" action="">
                        <h4>Email Notifications</h4>
                        <div class="checkbox-group">
                            <input type="checkbox" id="email_notifications" name="email_notifications" <?php echo $email_notifications ? 'checked' : ''; ?>>
                            <label for="email_notifications">Receive email notifications</label>
                        </div>

                        <h4>Alert Types</h4>
                        <div class="checkbox-group">
                            <input type="checkbox" id="grade_alerts" name="grade_alerts" <?php echo $grade_alerts ? 'checked' : ''; ?>>
                            <label for="grade_alerts">Grade updates and alerts</label>
                        </div>

                        <div class="checkbox-group">
                            <input type="checkbox" id="attendance_alerts" name="attendance_alerts" <?php echo $attendance_alerts ? 'checked' : ''; ?>>
                            <label for="attendance_alerts">Attendance reminders</label>
                        </div>

                        <div class="checkbox-group">
                            <input type="checkbox" id="announcements" name="announcements" <?php echo $announcements ? 'checked' : ''; ?>>
                            <label for="announcements">School announcements</label>
                        </div>

                        <h4>SMS Notifications</h4>
                        <div class="checkbox-group">
                            <input type="checkbox" id="sms_notifications" name="sms_notifications" <?php echo $sms_notifications ? 'checked' : ''; ?>>
                            <label for="sms_notifications">Receive SMS notifications</label>
                        </div>

                        <div class="info-box">
                            <i class="fas fa-mobile-alt"></i>
                            <p>SMS notifications require a valid phone number in your profile.</p>
                        </div>

                        <div class="form-actions">
                            <button type="submit" name="save_notifications" class="btn-submit">
                                <i class="fas fa-save"></i> Save Preferences
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Privacy Tab -->
            <div id="privacy" class="settings-tab-content" style="display: none;">
                <div class="settings-card">
                    <h3><i class="fas fa-shield-alt"></i> Privacy Settings</h3>
                    <form method="POST" action="">
                        <h4>Profile Visibility</h4>
                        <div class="radio-group">
                            <div class="radio-option">
                                <input type="radio" id="public" name="profile_visibility" value="public" <?php echo $profile_visibility == 'public' ? 'checked' : ''; ?>>
                                <label for="public">Public - Anyone can view your profile</label>
                            </div>
                            <div class="radio-option">
                                <input type="radio" id="private" name="profile_visibility" value="private" <?php echo $profile_visibility == 'private' ? 'checked' : ''; ?>>
                                <label for="private">Private - Only you and school staff</label>
                            </div>
                        </div>

                        <h4>Data Sharing</h4>
                        <div class="checkbox-group">
                            <input type="checkbox" id="show_grades" name="show_grades" <?php echo $show_grades ? 'checked' : ''; ?>>
                            <label for="show_grades">Allow parents/guardians to view grades</label>
                        </div>

                        <div class="checkbox-group">
                            <input type="checkbox" id="show_attendance" name="show_attendance" <?php echo $show_attendance ? 'checked' : ''; ?>>
                            <label for="show_attendance">Allow parents/guardians to view attendance</label>
                        </div>

                        <div class="info-box">
                            <i class="fas fa-user-shield"></i>
                            <p>Your data is protected and will only be shared according to school policy.</p>
                        </div>

                        <div class="form-actions">
                            <button type="submit" name="save_privacy" class="btn-submit">
                                <i class="fas fa-save"></i> Save Privacy Settings
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Danger Zone Tab -->
            <div id="danger" class="settings-tab-content" style="display: none;">
                <div class="settings-card">
                    <h3><i class="fas fa-exclamation-triangle" style="color: #dc3545;"></i> Danger Zone</h3>
                    
                    <div class="danger-zone">
                        <h4><i class="fas fa-trash-alt"></i> Delete Account</h4>
                        <p>Once you delete your account, there is no going back. Please be certain.</p>
                        <button class="btn-danger" onclick="confirmDelete()">
                            <i class="fas fa-trash"></i> Delete My Account
                        </button>
                    </div>

                    <div class="danger-zone" style="margin-top: 20px;">
                        <h4><i class="fas fa-sign-out-alt"></i> Deactivate Account</h4>
                        <p>Temporarily disable your account. You can reactivate it by logging in again.</p>
                        <button class="btn-danger" style="background: #ffc107; color: #2b2d42;" onclick="confirmDeactivate()">
                            <i class="fas fa-pause-circle"></i> Deactivate Account
                        </button>
                    </div>

                    <div class="info-box" style="margin-top: 20px;">
                        <i class="fas fa-info-circle"></i>
                        <p>For any account-related concerns, please contact the registrar's office.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Tab switching
        function showTab(tabId) {
            // Hide all tab contents
            document.querySelectorAll('.settings-tab-content').forEach(tab => {
                tab.style.display = 'none';
            });
            
            // Remove active class from all tabs
            document.querySelectorAll('.settings-tab').forEach(tab => {
                tab.classList.remove('active');
            });
            
            // Show selected tab content
            document.getElementById(tabId).style.display = 'block';
            
            // Add active class to clicked tab
            event.target.classList.add('active');
        }

        // Toggle password fields
        const changePasswordCheckbox = document.getElementById('change_password_checkbox');
        const passwordFields = document.getElementById('passwordFields');
        const currentPassword = document.getElementById('current_password');
        const newPassword = document.getElementById('new_password');
        const confirmPassword = document.getElementById('confirm_password');
        const changePasswordBtn = document.getElementById('changePasswordBtn');

        if(changePasswordCheckbox) {
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

        // Form validation for password change
        const passwordForm = document.getElementById('passwordForm');
        if(passwordForm) {
            passwordForm.addEventListener('submit', function(e) {
                if (changePasswordCheckbox && changePasswordCheckbox.checked) {
                    const current = document.getElementById('current_password').value;
                    const password = document.getElementById('new_password').value;
                    const confirm = document.getElementById('confirm_password').value;

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
                } else if(changePasswordCheckbox) {
                    e.preventDefault();
                }
            });
        }

        // Confirm delete account
        function confirmDelete() {
            if(confirm('WARNING: This action cannot be undone. Are you sure you want to delete your account?')) {
                alert('Account deletion request sent. Please visit the registrar\'s office to complete this process.');
            }
        }

        // Confirm deactivate account
        function confirmDeactivate() {
            if(confirm('Are you sure you want to deactivate your account? You can reactivate by logging in again.')) {
                alert('Account deactivation request sent.');
            }
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

        // Show first tab by default
        document.addEventListener('DOMContentLoaded', function() {
            document.getElementById('profile').style.display = 'block';
        });
    </script>
</body>
</html>