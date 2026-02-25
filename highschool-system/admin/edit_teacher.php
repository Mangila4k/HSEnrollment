<?php
session_start();
include("../config/database.php");

// Check if user is admin
if(!isset($_SESSION['user']) || $_SESSION['user']['role'] != 'Admin'){
    header("Location: ../auth/login.php");
    exit();
}

$admin_name = $_SESSION['user']['fullname'];
$success_message = '';
$error_message = '';

// Check if teacher ID is provided
if(!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: teachers.php");
    exit();
}

$teacher_id = $_GET['id'];

// Get teacher information
$teacher_query = $conn->prepare("
    SELECT u.*, 
           COUNT(DISTINCT s.id) as section_count,
           GROUP_CONCAT(DISTINCT s.section_name SEPARATOR ', ') as sections
    FROM users u
    LEFT JOIN sections s ON u.id = s.adviser_id
    WHERE u.id = ? AND u.role = 'Teacher'
    GROUP BY u.id
");

$teacher_query->bind_param("i", $teacher_id);
$teacher_query->execute();
$teacher_result = $teacher_query->get_result();

if($teacher_result->num_rows === 0) {
    header("Location: teachers.php");
    exit();
}

$teacher = $teacher_result->fetch_assoc();

// Handle form submission
if($_SERVER['REQUEST_METHOD'] == 'POST') {
    $fullname = trim($_POST['fullname']);
    $email = trim($_POST['email']);
    $id_number = !empty($_POST['id_number']) ? trim($_POST['id_number']) : null;
    $specialization = trim($_POST['specialization']);
    $phone = trim($_POST['phone']);
    $address = trim($_POST['address']);
    $change_password = isset($_POST['change_password']) ? true : false;
    
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
    
    // Check if email already exists (excluding current teacher)
    $check_email = $conn->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
    $check_email->bind_param("si", $email, $teacher_id);
    $check_email->execute();
    $check_email->store_result();
    
    if($check_email->num_rows > 0) {
        $errors[] = "Email address already registered to another user";
    }
    $check_email->close();
    
    // Check if ID number already exists (if provided and excluding current teacher)
    if($id_number) {
        $check_id = $conn->prepare("SELECT id FROM users WHERE id_number = ? AND id != ?");
        $check_id->bind_param("si", $id_number, $teacher_id);
        $check_id->execute();
        $check_id->store_result();
        
        if($check_id->num_rows > 0) {
            $errors[] = "ID number already exists for another user";
        }
        $check_id->close();
    }
    
    // Password validation if changing
    $new_password = '';
    if($change_password) {
        $new_password = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];
        
        if(empty($new_password)) {
            $errors[] = "New password is required";
        } elseif(strlen($new_password) < 6) {
            $errors[] = "Password must be at least 6 characters";
        }
        
        if($new_password !== $confirm_password) {
            $errors[] = "Passwords do not match";
        }
    }
    
    // If no errors, update the teacher
    if(empty($errors)) {
        if($change_password) {
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            $update_query = "UPDATE users SET id_number = ?, fullname = ?, email = ?, password = ? WHERE id = ?";
            $update_stmt = $conn->prepare($update_query);
            $update_stmt->bind_param("ssssi", $id_number, $fullname, $email, $hashed_password, $teacher_id);
        } else {
            $update_query = "UPDATE users SET id_number = ?, fullname = ?, email = ? WHERE id = ?";
            $update_stmt = $conn->prepare($update_query);
            $update_stmt->bind_param("sssi", $id_number, $fullname, $email, $teacher_id);
        }
        
        if($update_stmt->execute()) {
            // Update teacher details in a separate table if you have one
            // For now, we'll just show success message
            
            $_SESSION['success_message'] = "Teacher information updated successfully!";
            header("Location: teachers.php");
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
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Teacher - Admin Dashboard</title>
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

        /* Teacher Info Card */
        .info-card {
            background: linear-gradient(135deg, #f8f9fa, #e9ecef);
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .info-avatar {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, #0B4F2E, #1a7a42);
            border-radius: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 36px;
            font-weight: 600;
        }

        .info-details h3 {
            color: var(--text-primary);
            font-size: 20px;
            margin-bottom: 5px;
        }

        .info-details p {
            color: var(--text-secondary);
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .info-details p i {
            color: #0B4F2E;
            width: 20px;
        }

        .badge {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }

        .badge-success {
            background: rgba(40, 167, 69, 0.1);
            color: #28a745;
        }

        .badge-warning {
            background: rgba(255, 193, 7, 0.1);
            color: #ffc107;
        }

        /* Form Card */
        .form-card {
            background: white;
            border-radius: 20px;
            padding: 40px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            max-width: 800px;
            margin: 0 auto;
        }

        .form-card h3 {
            color: var(--text-primary);
            font-size: 20px;
            font-weight: 600;
            margin-bottom: 30px;
            display: flex;
            align-items: center;
            gap: 10px;
            padding-bottom: 20px;
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

        .form-group input.error {
            border-color: #dc3545;
            background: #fff8f8;
        }

        .form-group input::placeholder,
        .form-group textarea::placeholder {
            color: var(--text-secondary);
            opacity: 0.7;
        }

        .form-hint {
            margin-top: 6px;
            font-size: 12px;
            color: var(--text-secondary);
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .form-hint i {
            color: #0B4F2E;
            font-size: 13px;
        }

        /* Password Change Section */
        .password-section {
            background: #f8f9fa;
            border-radius: 15px;
            padding: 20px;
            margin: 25px 0;
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

        .section-tags {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-top: 10px;
        }

        .section-tag {
            background: rgba(11, 79, 46, 0.1);
            color: #0B4F2E;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }

        .section-tag i {
            font-size: 12px;
        }

        .form-actions {
            display: flex;
            gap: 15px;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid var(--border-color);
        }

        .btn-submit {
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

        .btn-submit:hover {
            background: #1a7a42;
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(11, 79, 46, 0.3);
        }

        .btn-cancel {
            flex: 1;
            background: white;
            color: var(--text-secondary);
            padding: 14px;
            border: 2px solid var(--border-color);
            border-radius: 12px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
            text-align: center;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }

        .btn-cancel:hover {
            border-color: #dc3545;
            color: #dc3545;
            background: #fff8f8;
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
            
            .info-card {
                flex-direction: column;
                text-align: center;
            }
            
            .info-details p {
                flex-direction: column;
                gap: 5px;
            }
            
            .form-row {
                grid-template-columns: 1fr;
            }
            
            .form-actions {
                flex-direction: column;
            }
            
            .form-card {
                padding: 25px;
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
                    <li><a href="teachers.php" class="active"><i class="fas fa-chalkboard-teacher"></i> <span>Teachers</span></a></li>
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
                    <h1>Edit Teacher</h1>
                    <p>Update teacher information and credentials</p>
                </div>
                <a href="teachers.php" class="back-btn">
                    <i class="fas fa-arrow-left"></i> Back to Teachers
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
            <?php if($error_message): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i>
                    <?php echo $error_message; ?>
                </div>
            <?php endif; ?>

            <!-- Teacher Info Card -->
            <div class="info-card">
                <div class="info-avatar">
                    <?php echo strtoupper(substr($teacher['fullname'], 0, 1)); ?>
                </div>
                <div class="info-details">
                    <h3><?php echo htmlspecialchars($teacher['fullname']); ?></h3>
                    <p>
                        <span><i class="fas fa-envelope"></i> <?php echo htmlspecialchars($teacher['email']); ?></span>
                        <span><i class="fas fa-id-card"></i> ID: <?php echo $teacher['id_number'] ?? 'Not assigned'; ?></span>
                    </p>
                    <p>
                        <span><i class="fas fa-calendar-alt"></i> Joined: <?php echo date('F d, Y', strtotime($teacher['created_at'])); ?></span>
                        <span>
                            <i class="fas fa-layer-group"></i> 
                            Sections: 
                            <?php if($teacher['section_count'] > 0): ?>
                                <span class="badge badge-success"><?php echo $teacher['section_count']; ?> section(s)</span>
                            <?php else: ?>
                                <span class="badge badge-warning">No sections</span>
                            <?php endif; ?>
                        </span>
                    </p>
                    <?php if($teacher['section_count'] > 0): ?>
                        <div class="section-tags">
                            <?php 
                            $sections = explode(', ', $teacher['sections']);
                            foreach($sections as $section): 
                            ?>
                                <span class="section-tag"><i class="fas fa-users"></i> <?php echo htmlspecialchars($section); ?></span>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Form Card -->
            <div class="form-card">
                <h3>
                    <i class="fas fa-edit"></i>
                    Edit Teacher Information
                </h3>

                <form method="POST" action="" id="teacherForm">
                    <div class="form-row">
                        <div class="form-group">
                            <label>Full Name <span>*</span></label>
                            <input type="text" 
                                   name="fullname" 
                                   id="fullname"
                                   placeholder="e.g., Juan Dela Cruz" 
                                   value="<?php echo isset($_POST['fullname']) ? htmlspecialchars($_POST['fullname']) : htmlspecialchars($teacher['fullname']); ?>"
                                   required>
                        </div>

                        <div class="form-group">
                            <label>Email Address <span>*</span></label>
                            <input type="email" 
                                   name="email" 
                                   id="email"
                                   placeholder="teacher@plshs.edu.ph" 
                                   value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : htmlspecialchars($teacher['email']); ?>"
                                   required>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label>ID Number</label>
                            <input type="text" 
                                   name="id_number" 
                                   id="id_number"
                                   placeholder="e.g., TCH-2024-001" 
                                   value="<?php echo isset($_POST['id_number']) ? htmlspecialchars($_POST['id_number']) : htmlspecialchars($teacher['id_number'] ?? ''); ?>">
                            <div class="form-hint">
                                <i class="fas fa-info-circle"></i>
                                Leave blank if not applicable
                            </div>
                        </div>

                        <div class="form-group">
                            <label>Phone Number</label>
                            <input type="text" 
                                   name="phone" 
                                   id="phone"
                                   placeholder="e.g., 09123456789" 
                                   value="<?php echo isset($_POST['phone']) ? htmlspecialchars($_POST['phone']) : ''; ?>">
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Specialization</label>
                        <input type="text" 
                               name="specialization" 
                               id="specialization"
                               placeholder="e.g., Mathematics, Science, English" 
                               value="<?php echo isset($_POST['specialization']) ? htmlspecialchars($_POST['specialization']) : ''; ?>">
                    </div>

                    <div class="form-group">
                        <label>Address</label>
                        <textarea name="address" 
                                  id="address" 
                                  rows="3"
                                  placeholder="Complete address"><?php echo isset($_POST['address']) ? htmlspecialchars($_POST['address']) : ''; ?></textarea>
                    </div>

                    <!-- Password Change Section -->
                    <div class="password-section">
                        <div class="password-header">
                            <input type="checkbox" id="change_password" name="change_password">
                            <label for="change_password">Change Password</label>
                        </div>
                        
                        <div class="password-fields" id="passwordFields">
                            <div class="form-row">
                                <div class="form-group">
                                    <label>New Password</label>
                                    <input type="password" 
                                           name="new_password" 
                                           id="new_password"
                                           placeholder="Enter new password"
                                           <?php echo isset($_POST['change_password']) ? '' : 'disabled'; ?>>
                                    <div class="password-strength">
                                        <div class="password-strength-bar" id="passwordStrength"></div>
                                    </div>
                                    <div class="password-strength-text" id="passwordStrengthText">
                                        <i class="fas fa-info-circle"></i>
                                        <span>Minimum 6 characters</span>
                                    </div>
                                </div>

                                <div class="form-group">
                                    <label>Confirm Password</label>
                                    <input type="password" 
                                           name="confirm_password" 
                                           id="confirm_password"
                                           placeholder="Confirm new password"
                                           <?php echo isset($_POST['change_password']) ? '' : 'disabled'; ?>>
                                    <div class="form-hint" id="passwordMatch">
                                        <i class="fas fa-info-circle"></i>
                                        <span>Re-enter new password</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="form-actions">
                        <button type="submit" name="submit" class="btn-submit">
                            <i class="fas fa-save"></i> Update Teacher
                        </button>
                        <a href="teachers.php" class="btn-cancel">
                            <i class="fas fa-times"></i> Cancel
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        // Toggle password fields
        const changePasswordCheckbox = document.getElementById('change_password');
        const passwordFields = document.getElementById('passwordFields');
        const newPasswordInput = document.getElementById('new_password');
        const confirmPasswordInput = document.getElementById('confirm_password');

        changePasswordCheckbox.addEventListener('change', function() {
            if (this.checked) {
                passwordFields.classList.add('show');
                newPasswordInput.disabled = false;
                confirmPasswordInput.disabled = false;
                newPasswordInput.focus();
            } else {
                passwordFields.classList.remove('show');
                newPasswordInput.disabled = true;
                confirmPasswordInput.disabled = true;
                newPasswordInput.value = '';
                confirmPasswordInput.value = '';
                resetPasswordStrength();
            }
        });

        // Password strength checker
        function resetPasswordStrength() {
            document.getElementById('passwordStrength').style.width = '0';
            document.getElementById('passwordStrengthText').innerHTML = 
                '<i class="fas fa-info-circle"></i> <span>Minimum 6 characters</span>';
        }

        function checkPasswordStrength() {
            const password = newPasswordInput.value;
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
            const password = newPasswordInput.value;
            const confirm = confirmPasswordInput.value;

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

        newPasswordInput.addEventListener('input', checkPasswordStrength);
        newPasswordInput.addEventListener('input', checkPasswordMatch);
        confirmPasswordInput.addEventListener('input', checkPasswordMatch);

        // Form validation
        document.getElementById('teacherForm').addEventListener('submit', function(e) {
            if (changePasswordCheckbox.checked) {
                const password = newPasswordInput.value;
                const confirm = confirmPasswordInput.value;

                if (password.length < 6) {
                    e.preventDefault();
                    alert('Password must be at least 6 characters long!');
                } else if (password !== confirm) {
                    e.preventDefault();
                    alert('Passwords do not match!');
                }
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

        // Check if change password was checked due to form error
        <?php if(isset($_POST['change_password'])): ?>
            changePasswordCheckbox.checked = true;
            passwordFields.classList.add('show');
            newPasswordInput.disabled = false;
            confirmPasswordInput.disabled = false;
        <?php endif; ?>
    </script>
</body>
</html>