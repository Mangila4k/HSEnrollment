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

// Handle form submission
if($_SERVER['REQUEST_METHOD'] == 'POST') {
    $fullname = trim($_POST['fullname']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $id_number = !empty($_POST['id_number']) ? trim($_POST['id_number']) : null;
    $specialization = trim($_POST['specialization']);
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
        $check_email = $conn->prepare("SELECT id FROM users WHERE email = ?");
        $check_email->bind_param("s", $email);
        $check_email->execute();
        $check_email->store_result();
        
        if($check_email->num_rows > 0) {
            $errors[] = "Email address already registered";
        }
        $check_email->close();
    }
    
    // Check if ID number already exists (if provided)
    if(empty($errors) && $id_number) {
        $check_id = $conn->prepare("SELECT id FROM users WHERE id_number = ?");
        $check_id->bind_param("s", $id_number);
        $check_id->execute();
        $check_id->store_result();
        
        if($check_id->num_rows > 0) {
            $errors[] = "ID number already exists";
        }
        $check_id->close();
    }
    
    // If no errors, insert the teacher
    if(empty($errors)) {
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        $role = 'Teacher';
        
        $insert_query = "INSERT INTO users (id_number, fullname, email, password, role) VALUES (?, ?, ?, ?, ?)";
        $insert_stmt = $conn->prepare($insert_query);
        $insert_stmt->bind_param("sssss", $id_number, $fullname, $email, $hashed_password, $role);
        
        if($insert_stmt->execute()) {
            $teacher_id = $insert_stmt->insert_id;
            
            // You can add teacher details to a separate table if needed
            // For now, we'll just show success message
            
            $_SESSION['success_message'] = "Teacher added successfully!";
            header("Location: teachers.php");
            exit();
        } else {
            $errors[] = "Database error: " . $conn->error;
        }
        $insert_stmt->close();
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
    <title>Add New Teacher - Admin Dashboard</title>
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

        .error-list {
            list-style: none;
            margin-top: 10px;
        }

        .error-list li {
            color: #dc3545;
            font-size: 14px;
            margin-bottom: 5px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .error-list li i {
            font-size: 14px;
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

        .form-group input.error,
        .form-group select.error {
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

        /* Preview Card */
        .preview-card {
            background: linear-gradient(135deg, #f8f9fa, #e9ecef);
            border-radius: 15px;
            padding: 20px;
            margin-top: 30px;
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
            gap: 15px;
            padding: 15px;
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
        }

        .preview-icon {
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

        .preview-details h5 {
            color: var(--text-primary);
            font-size: 18px;
            margin-bottom: 5px;
        }

        .preview-details p {
            color: var(--text-secondary);
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .preview-details p i {
            color: #0B4F2E;
            font-size: 12px;
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
                    <h1>Add New Teacher</h1>
                    <p>Create a new faculty account</p>
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

            <!-- Form Card -->
            <div class="form-card">
                <h3>
                    <i class="fas fa-user-plus"></i>
                    Teacher Information
                </h3>

                <form method="POST" action="" id="teacherForm">
                    <div class="form-row">
                        <div class="form-group">
                            <label>Full Name <span>*</span></label>
                            <input type="text" 
                                   name="fullname" 
                                   id="fullname"
                                   placeholder="e.g., Juan Dela Cruz" 
                                   value="<?php echo isset($_POST['fullname']) ? htmlspecialchars($_POST['fullname']) : ''; ?>"
                                   required>
                        </div>

                        <div class="form-group">
                            <label>Email Address <span>*</span></label>
                            <input type="email" 
                                   name="email" 
                                   id="email"
                                   placeholder="teacher@plshs.edu.ph" 
                                   value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>"
                                   required>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label>Password <span>*</span></label>
                            <input type="password" 
                                   name="password" 
                                   id="password"
                                   placeholder="Create a password" 
                                   required>
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
                            <input type="password" 
                                   name="confirm_password" 
                                   id="confirm_password"
                                   placeholder="Confirm password" 
                                   required>
                            <div class="form-hint" id="passwordMatch">
                                <i class="fas fa-info-circle"></i>
                                <span>Re-enter your password</span>
                            </div>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label>ID Number</label>
                            <input type="text" 
                                   name="id_number" 
                                   id="id_number"
                                   placeholder="e.g., TCH-2024-001" 
                                   value="<?php echo isset($_POST['id_number']) ? htmlspecialchars($_POST['id_number']) : ''; ?>">
                            <div class="form-hint">
                                <i class="fas fa-info-circle"></i>
                                Leave blank for auto-generated ID
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
                        <div class="form-hint">
                            <i class="fas fa-info-circle"></i>
                            Main subject area or field of expertise
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Address</label>
                        <textarea name="address" 
                                  id="address" 
                                  rows="3"
                                  placeholder="Complete address"><?php echo isset($_POST['address']) ? htmlspecialchars($_POST['address']) : ''; ?></textarea>
                    </div>

                    <!-- Live Preview -->
                    <div class="preview-card">
                        <h4><i class="fas fa-eye"></i> Teacher Preview</h4>
                        <div class="preview-item">
                            <div class="preview-icon" id="previewInitial">
                                <?php 
                                $initial = isset($_POST['fullname']) ? strtoupper(substr($_POST['fullname'], 0, 1)) : 'T';
                                echo $initial;
                                ?>
                            </div>
                            <div class="preview-details">
                                <h5 id="previewName"><?php echo isset($_POST['fullname']) ? htmlspecialchars($_POST['fullname']) : 'New Teacher'; ?></h5>
                                <p id="previewEmail">
                                    <i class="fas fa-envelope"></i>
                                    <?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : 'teacher@plshs.edu.ph'; ?>
                                </p>
                                <p id="previewSpecialization">
                                    <i class="fas fa-book"></i>
                                    <?php echo isset($_POST['specialization']) ? htmlspecialchars($_POST['specialization']) : 'Specialization not set'; ?>
                                </p>
                            </div>
                        </div>
                    </div>

                    <div class="form-actions">
                        <button type="submit" name="submit" class="btn-submit">
                            <i class="fas fa-save"></i> Add Teacher
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
        // Live preview update
        const fullnameInput = document.getElementById('fullname');
        const emailInput = document.getElementById('email');
        const specializationInput = document.getElementById('specialization');
        const previewName = document.getElementById('previewName');
        const previewEmail = document.getElementById('previewEmail');
        const previewSpecialization = document.getElementById('previewSpecialization');
        const previewInitial = document.getElementById('previewInitial');

        function updatePreview() {
            // Update name and initial
            const fullname = fullnameInput.value.trim() || 'New Teacher';
            previewName.textContent = fullname;
            
            const initial = fullname.charAt(0).toUpperCase() || 'T';
            previewInitial.textContent = initial;

            // Update email
            const email = emailInput.value.trim() || 'teacher@plshs.edu.ph';
            previewEmail.innerHTML = `<i class="fas fa-envelope"></i> ${email}`;

            // Update specialization
            const specialization = specializationInput.value.trim() || 'Specialization not set';
            previewSpecialization.innerHTML = `<i class="fas fa-book"></i> ${specialization}`;
        }

        fullnameInput.addEventListener('input', updatePreview);
        emailInput.addEventListener('input', updatePreview);
        specializationInput.addEventListener('input', updatePreview);

        // Password strength checker
        const passwordInput = document.getElementById('password');
        const confirmInput = document.getElementById('confirm_password');
        const strengthBar = document.getElementById('passwordStrength');
        const strengthText = document.getElementById('passwordStrengthText');
        const passwordMatch = document.getElementById('passwordMatch');

        function checkPasswordStrength() {
            const password = passwordInput.value;
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
                    strengthBar.style.width = '20%';
                    strengthBar.style.backgroundColor = '#dc3545';
                    strengthLabel = 'Weak';
                    strengthColor = 'strength-weak';
                    break;
                case 2:
                case 3:
                    strengthBar.style.width = '60%';
                    strengthBar.style.backgroundColor = '#ffc107';
                    strengthLabel = 'Medium';
                    strengthColor = 'strength-medium';
                    break;
                case 4:
                case 5:
                    strengthBar.style.width = '100%';
                    strengthBar.style.backgroundColor = '#28a745';
                    strengthLabel = 'Strong';
                    strengthColor = 'strength-strong';
                    break;
            }

            if (password.length > 0) {
                strengthText.innerHTML = `<i class="fas fa-shield-alt"></i> <span class="${strengthColor}">Password strength: ${strengthLabel}</span>`;
            } else {
                strengthText.innerHTML = `<i class="fas fa-info-circle"></i> <span>Minimum 6 characters</span>`;
            }
        }

        function checkPasswordMatch() {
            const password = passwordInput.value;
            const confirm = confirmInput.value;

            if (confirm.length > 0) {
                if (password === confirm) {
                    passwordMatch.innerHTML = `<i class="fas fa-check-circle" style="color: #28a745;"></i> <span style="color: #28a745;">Passwords match</span>`;
                } else {
                    passwordMatch.innerHTML = `<i class="fas fa-exclamation-circle" style="color: #dc3545;"></i> <span style="color: #dc3545;">Passwords do not match</span>`;
                }
            } else {
                passwordMatch.innerHTML = `<i class="fas fa-info-circle"></i> <span>Re-enter your password</span>`;
            }
        }

        passwordInput.addEventListener('input', checkPasswordStrength);
        passwordInput.addEventListener('input', checkPasswordMatch);
        confirmInput.addEventListener('input', checkPasswordMatch);

        // Form validation
        document.getElementById('teacherForm').addEventListener('submit', function(e) {
            const password = passwordInput.value;
            const confirm = confirmInput.value;

            if (password !== confirm) {
                e.preventDefault();
                alert('Passwords do not match!');
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