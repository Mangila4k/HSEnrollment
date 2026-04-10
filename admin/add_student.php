<?php
session_start();
include("../config/database.php");

// Check if user is admin
if(!isset($_SESSION['user']) || $_SESSION['user']['role'] != 'Admin'){
    header("Location: ../auth/login.php");
    exit();
}

$admin_name = $_SESSION['user']['fullname'];
$error = '';
$success = '';

if($_SERVER['REQUEST_METHOD'] == 'POST') {
    $firstname = trim($_POST['firstname']);
    $middlename = !empty($_POST['middlename']) ? trim($_POST['middlename']) : null;
    $lastname = trim($_POST['lastname']);
    $birthdate = $_POST['birthdate'];
    $gender = $_POST['gender'];
    $email = trim($_POST['email']);
    $id_number = !empty($_POST['id_number']) ? trim($_POST['id_number']) : null;
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    
    // Combine names for fullname
    $fullname = trim($firstname . ' ' . ($middlename ? $middlename . ' ' : '') . $lastname);
    
    // Validation
    $errors = [];
    
    if(empty($firstname)) {
        $errors[] = "First name is required";
    }
    
    if(empty($lastname)) {
        $errors[] = "Last name is required";
    }
    
    if(empty($birthdate)) {
        $errors[] = "Birthdate is required";
    } else {
        // Validate age (at least 15 years old for senior high school)
        $birthdate_obj = DateTime::createFromFormat('Y-m-d', $birthdate);
        if(!$birthdate_obj) {
            $errors[] = "Invalid birthdate format";
        } else {
            $today = new DateTime();
            $age = $today->diff($birthdate_obj)->y;
            
            if($age < 15) {
                $errors[] = "You must be at least 15 years old to register";
            } elseif($age > 30) {
                $errors[] = "Age exceeds maximum allowed (30 years)";
            }
        }
    }
    
    if(empty($gender)) {
        $errors[] = "Gender is required";
    } elseif(!in_array($gender, ['Male', 'Female', 'Other'])) {
        $errors[] = "Invalid gender selection";
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
    
    if(empty($errors)) {
        try {
            // Check if email already exists
            $check = $conn->prepare("SELECT id FROM users WHERE email = ?");
            $check->execute([$email]);
            
            if($check->rowCount() > 0) {
                $error = "Email already exists";
            } else {
                // Check if ID number exists (if provided)
                if($id_number) {
                    $check_id = $conn->prepare("SELECT id FROM users WHERE id_number = ?");
                    $check_id->execute([$id_number]);
                    
                    if($check_id->rowCount() > 0) {
                        $error = "ID number already exists";
                    }
                }
                
                if(empty($error)) {
                    // Start transaction
                    $conn->beginTransaction();
                    
                    try {
                        // Hash password
                        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                        $role = 'Student';
                        
                        // Check if is_approved column exists
                        $check_column = $conn->query("SHOW COLUMNS FROM users LIKE 'is_approved'");
                        $has_is_approved = $check_column->rowCount() > 0;
                        
                        // Insert user based on available columns
                        if($has_is_approved) {
                            $stmt = $conn->prepare("INSERT INTO users (id_number, fullname, email, password, role, is_approved) VALUES (?, ?, ?, ?, ?, ?)");
                            $stmt->execute([$id_number, $fullname, $email, $hashed_password, $role, 1]);
                        } else {
                            $stmt = $conn->prepare("INSERT INTO users (id_number, fullname, email, password, role) VALUES (?, ?, ?, ?, ?)");
                            $stmt->execute([$id_number, $fullname, $email, $hashed_password, $role]);
                        }
                        
                        $conn->commit();
                        $success = "Student account created successfully!";
                        
                        // Clear form
                        $_POST = array();
                    } catch (Exception $e) {
                        $conn->rollBack();
                        $error = "Error creating student: " . $e->getMessage();
                    }
                }
            }
        } catch(PDOException $e) {
            $error = "Database error: " . $e->getMessage();
        }
    } else {
        $error = implode("<br>", $errors);
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add New Student - Admin Dashboard</title>
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <!-- Flatpickr for date picker -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        :root {
            --primary-color: #0B4F2E;
            --primary-dark: #1a7a42;
            --primary-light: rgba(11, 79, 46, 0.1);
            --accent: #FFD700;
            --text-primary: #2b2d42;
            --text-secondary: #8d99ae;
            --border-color: #e9ecef;
            --hover-color: #f8f9fa;
            --success: #28a745;
            --danger: #dc3545;
            --warning: #ffc107;
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
        }

        .dashboard-header h1 {
            font-size: 28px;
            color: var(--text-primary);
            margin-bottom: 10px;
            font-weight: 700;
        }

        .dashboard-header h1 i {
            color: var(--primary-color);
            margin-right: 10px;
        }

        .dashboard-header p {
            color: var(--text-secondary);
            font-size: 16px;
        }

        /* Welcome Section */
        .welcome-section {
            background: linear-gradient(135deg, #0B4F2E, #1a7a42);
            border-radius: 20px;
            padding: 25px 30px;
            color: white;
            margin-bottom: 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 10px 30px rgba(11, 79, 46, 0.3);
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .user-info i {
            font-size: 48px;
            color: #FFD700;
        }

        .user-details h4 {
            font-size: 18px;
            font-weight: 600;
            margin-bottom: 5px;
        }

        .user-details span {
            font-size: 14px;
            opacity: 0.9;
        }

        .logout-btn {
            background: rgba(255, 255, 255, 0.2);
            color: white;
            padding: 10px 20px;
            border-radius: 10px;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s ease;
            font-weight: 500;
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

        /* Form Container */
        .form-container {
            max-width: 900px;
            margin: 0 auto;
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
            color: var(--primary-color);
        }

        /* Form Row */
        .form-row {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
            margin-bottom: 20px;
        }

        .form-row-2 {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
            margin-bottom: 20px;
        }

        /* Form Groups */
        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: var(--text-secondary);
            font-weight: 600;
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .form-group label span {
            color: #dc3545;
            margin-left: 3px;
        }

        .form-group input,
        .form-group select {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid var(--border-color);
            border-radius: 10px;
            font-size: 14px;
            transition: all 0.3s;
            background: white;
        }

        .form-group input:focus,
        .form-group select:focus {
            border-color: var(--primary-color);
            outline: none;
            box-shadow: 0 0 0 4px rgba(11, 79, 46, 0.1);
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
            color: var(--primary-color);
            font-size: 12px;
        }

        .password-strength {
            margin-top: 8px;
            height: 4px;
            background: #e0e0e0;
            border-radius: 2px;
            overflow: hidden;
        }

        .strength-bar {
            height: 100%;
            width: 0;
            transition: all 0.3s;
        }

        .strength-bar.weak {
            width: 33.33%;
            background-color: #ef4444;
        }

        .strength-bar.medium {
            width: 66.66%;
            background-color: #f59e0b;
        }

        .strength-bar.strong {
            width: 100%;
            background-color: #10b981;
        }

        .strength-text {
            font-size: 12px;
            margin-top: 5px;
            color: #666;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .toggle-password {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            cursor: pointer;
            color: #666;
            transition: color 0.3s;
        }

        .toggle-password:hover {
            color: var(--primary-color);
        }

        .input-wrapper {
            position: relative;
        }

        /* Form Actions */
        .form-actions {
            display: flex;
            gap: 15px;
            margin-top: 30px;
        }

        .btn-submit {
            background: linear-gradient(135deg, #0B4F2E, #1a7a42);
            color: white;
            padding: 14px 30px;
            border: none;
            border-radius: 12px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(11, 79, 46, 0.3);
        }

        .btn-cancel {
            background: #f8f9fa;
            color: var(--text-secondary);
            padding: 14px 30px;
            border: 2px solid var(--border-color);
            border-radius: 12px;
            text-decoration: none;
            font-size: 16px;
            font-weight: 600;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .btn-cancel:hover {
            border-color: var(--danger);
            color: var(--danger);
        }

        /* Responsive */
        @media (max-width: 1200px) {
            .form-row {
                grid-template-columns: repeat(2, 1fr);
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
            
            .welcome-section {
                flex-direction: column;
                text-align: center;
                gap: 15px;
            }
            
            .user-info {
                flex-direction: column;
            }
            
            .form-row,
            .form-row-2 {
                grid-template-columns: 1fr;
            }
            
            .form-actions {
                flex-direction: column;
            }
            
            .btn-submit,
            .btn-cancel {
                justify-content: center;
            }
        }
    </style>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
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
                    <li><a href="students.php" class="active"><i class="fas fa-user-graduate"></i> <span>Students</span></a></li>
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
                    <li><a href="profile.php"><i class="fas fa-user"></i> <span>Profile</span></a></li>
                    <li><a href="../auth/logout.php"><i class="fas fa-sign-out-alt"></i> <span>Logout</span></a></li>
                </ul>
            </div>
        </div>

        <!-- Main Content -->
        <div class="main-content">
            <!-- Header -->
            <div class="dashboard-header">
                <h1>
                    <i class="fas fa-user-plus"></i>
                    Add New Student
                </h1>
                <p>Create a new student account</p>
            </div>

            <!-- Welcome Section -->
            <div class="welcome-section">
                <div class="user-info">
                    <i class="fas fa-user-circle"></i>
                    <div class="user-details">
                        <h4>Welcome, <?php echo htmlspecialchars($admin_name); ?></h4>
                        <span>Administrator</span>
                    </div>
                </div>
                <a href="../auth/logout.php" class="logout-btn">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a>
            </div>

            <!-- Alert Messages -->
            <?php if($error): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i>
                    <?php echo $error; ?>
                </div>
            <?php endif; ?>
            
            <?php if($success): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    <?php echo $success; ?>
                </div>
            <?php endif; ?>

            <!-- Form -->
            <div class="form-container">
                <form method="POST" action="" id="registerForm">
                    <!-- Personal Information -->
                    <div class="form-card">
                        <h3>
                            <i class="fas fa-user"></i>
                            Personal Information
                        </h3>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="firstname">First Name <span>*</span></label>
                                <input type="text" id="firstname" name="firstname" placeholder="First name" 
                                       value="<?php echo isset($_POST['firstname']) ? htmlspecialchars($_POST['firstname']) : ''; ?>" required>
                            </div>

                            <div class="form-group">
                                <label for="middlename">Middle Initial</label>
                                <input type="text" id="middlename" name="middlename" placeholder="Middle initial" maxlength="2"
                                       value="<?php echo isset($_POST['middlename']) ? htmlspecialchars($_POST['middlename']) : ''; ?>">
                            </div>

                            <div class="form-group">
                                <label for="lastname">Last Name <span>*</span></label>
                                <input type="text" id="lastname" name="lastname" placeholder="Last name" 
                                       value="<?php echo isset($_POST['lastname']) ? htmlspecialchars($_POST['lastname']) : ''; ?>" required>
                            </div>
                        </div>

                        <div class="form-row-2">
                            <div class="form-group">
                                <label for="birthdate">Birthdate <span>*</span></label>
                                <input type="text" id="birthdate" name="birthdate" placeholder="Select birthdate" 
                                       value="<?php echo isset($_POST['birthdate']) ? htmlspecialchars($_POST['birthdate']) : ''; ?>" required>
                                <div class="form-hint" id="ageHint">
                                    <i class="fas fa-info-circle"></i>
                                    Must be 15-30 years old
                                </div>
                            </div>

                            <div class="form-group">
                                <label for="gender">Gender <span>*</span></label>
                                <select id="gender" name="gender" required>
                                    <option value="">Select gender</option>
                                    <option value="Male" <?php echo (isset($_POST['gender']) && $_POST['gender'] == 'Male') ? 'selected' : ''; ?>>Male</option>
                                    <option value="Female" <?php echo (isset($_POST['gender']) && $_POST['gender'] == 'Female') ? 'selected' : ''; ?>>Female</option>
                                    <option value="Other" <?php echo (isset($_POST['gender']) && $_POST['gender'] == 'Other') ? 'selected' : ''; ?>>Other</option>
                                </select>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="email">Email Address <span>*</span></label>
                            <input type="email" id="email" name="email" placeholder="Enter your email" 
                                   value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>" required>
                        </div>

                        <div class="form-group">
                            <label for="id_number">Student ID (Optional)</label>
                            <input type="text" id="id_number" name="id_number" placeholder="Enter your student ID (optional)" 
                                   value="<?php echo isset($_POST['id_number']) ? htmlspecialchars($_POST['id_number']) : ''; ?>">
                            <div class="form-hint">
                                <i class="fas fa-info-circle"></i>
                                Leave blank if you don't have an ID yet
                            </div>
                        </div>
                    </div>

                    <!-- Account Security -->
                    <div class="form-card">
                        <h3>
                            <i class="fas fa-lock"></i>
                            Account Security
                        </h3>

                        <div class="form-group">
                            <label for="password">Password <span>*</span></label>
                            <div class="input-wrapper">
                                <input type="password" id="password" name="password" placeholder="Create a password" required>
                                <button type="button" class="toggle-password" onclick="togglePassword()">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                            <div class="password-strength">
                                <div class="strength-bar" id="strengthBar"></div>
                            </div>
                            <div class="strength-text" id="strengthText">
                                <i class="fas fa-info-circle"></i>
                                <span>Enter password (min. 6 characters)</span>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="confirm_password">Confirm Password <span>*</span></label>
                            <input type="password" id="confirm_password" name="confirm_password" placeholder="Confirm your password" required>
                            <div class="strength-text" id="passwordMatch">
                                <i class="fas fa-info-circle"></i>
                                <span>Re-enter your password</span>
                            </div>
                        </div>
                    </div>

                    <div class="form-actions">
                        <button type="submit" class="btn-submit">
                            <i class="fas fa-save"></i> Create Student
                        </button>
                        <a href="students.php" class="btn-cancel">
                            <i class="fas fa-times"></i> Cancel
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        // Wait for DOM to be fully loaded
        document.addEventListener('DOMContentLoaded', function() {
            // Initialize date picker
            flatpickr("#birthdate", {
                maxDate: "today",
                minDate: "1993-01-01",
                dateFormat: "Y-m-d",
                altInput: true,
                altFormat: "F j, Y",
                placeholder: "Select birthdate",
                onChange: function(selectedDates, dateStr, instance) {
                    if (dateStr) {
                        calculateAge(dateStr);
                    }
                }
            });

            // Auto-generate password based on lastname
            const lastnameInput = document.getElementById('lastname');
            const passwordInput = document.getElementById('password');
            
            if(lastnameInput && passwordInput) {
                lastnameInput.addEventListener('blur', function() {
                    if(this.value && !passwordInput.value) {
                        const lastname = this.value.toLowerCase().trim();
                        const currentYear = new Date().getFullYear();
                        const generatedPassword = lastname + currentYear;
                        passwordInput.value = generatedPassword;
                        
                        // Trigger password strength check
                        passwordInput.dispatchEvent(new Event('input'));
                    }
                });
            }
        });

        // Calculate age and validate
        function calculateAge(birthdate) {
            const birthDate = new Date(birthdate);
            const today = new Date();
            let age = today.getFullYear() - birthDate.getFullYear();
            const monthDiff = today.getMonth() - birthDate.getMonth();
            const dayDiff = today.getDate() - birthDate.getDate();
            
            if (monthDiff < 0 || (monthDiff === 0 && dayDiff < 0)) {
                age--;
            }
            
            const ageHint = document.getElementById('ageHint');
            
            if (age < 15) {
                ageHint.innerHTML = '<i class="fas fa-exclamation-circle"></i> Age: ' + age + ' years - You must be at least 15 years old!';
                ageHint.style.color = '#dc3545';
                document.getElementById('birthdate').style.borderColor = '#dc3545';
                return false;
            } else if (age > 30) {
                ageHint.innerHTML = '<i class="fas fa-exclamation-circle"></i> Age: ' + age + ' years - Age exceeds maximum allowed (30 years)!';
                ageHint.style.color = '#dc3545';
                document.getElementById('birthdate').style.borderColor = '#dc3545';
                return false;
            } else {
                ageHint.innerHTML = '<i class="fas fa-check-circle"></i> Age: ' + age + ' years - Valid age!';
                ageHint.style.color = '#28a745';
                document.getElementById('birthdate').style.borderColor = '#28a745';
                return true;
            }
        }

        // Toggle password visibility
        function togglePassword() {
            const passwordInput = document.getElementById('password');
            const toggleBtn = document.querySelector('.toggle-password i');
            
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                toggleBtn.className = 'fas fa-eye-slash';
            } else {
                passwordInput.type = 'password';
                toggleBtn.className = 'fas fa-eye';
            }
        }

        // Password strength checker
        document.getElementById('password').addEventListener('input', function() {
            const password = this.value;
            const strengthBar = document.getElementById('strengthBar');
            const strengthText = document.getElementById('strengthText');
            
            strengthBar.className = 'strength-bar';
            
            if (password.length === 0) {
                strengthBar.style.width = '0';
                strengthText.innerHTML = '<i class="fas fa-info-circle"></i> <span>Enter password (min. 6 characters)</span>';
                return;
            }
            
            let strength = 0;
            
            if (password.length >= 6) strength += 1;
            if (password.length >= 8) strength += 1;
            if (/[a-z]/.test(password)) strength += 1;
            if (/[A-Z]/.test(password)) strength += 1;
            if (/[0-9]/.test(password)) strength += 1;
            if (/[^A-Za-z0-9]/.test(password)) strength += 1;
            
            if (password.length < 6) {
                strengthBar.classList.add('weak');
                strengthBar.style.width = '33.33%';
                strengthText.innerHTML = '<i class="fas fa-exclamation-circle"></i> <span style="color: #ef4444;">Too short (min. 6 characters)</span>';
            } else if (strength <= 3) {
                strengthBar.classList.add('weak');
                strengthBar.style.width = '33.33%';
                strengthText.innerHTML = '<i class="fas fa-shield-alt"></i> <span style="color: #ef4444;">Weak password</span>';
            } else if (strength <= 5) {
                strengthBar.classList.add('medium');
                strengthBar.style.width = '66.66%';
                strengthText.innerHTML = '<i class="fas fa-shield-alt"></i> <span style="color: #f59e0b;">Medium password</span>';
            } else {
                strengthBar.classList.add('strong');
                strengthBar.style.width = '100%';
                strengthText.innerHTML = '<i class="fas fa-shield-alt"></i> <span style="color: #10b981;">Strong password</span>';
            }
        });

        // Password match checker
        document.getElementById('confirm_password').addEventListener('input', function() {
            const password = document.getElementById('password').value;
            const confirm = this.value;
            const matchText = document.getElementById('passwordMatch');
            
            if (confirm.length === 0) {
                matchText.innerHTML = '<i class="fas fa-info-circle"></i> <span>Re-enter your password</span>';
            } else if (password === confirm) {
                matchText.innerHTML = '<i class="fas fa-check-circle" style="color: #28a745;"></i> <span style="color: #28a745;">Passwords match</span>';
            } else {
                matchText.innerHTML = '<i class="fas fa-exclamation-circle" style="color: #dc3545;"></i> <span style="color: #dc3545;">Passwords do not match</span>';
            }
        });

        // Form validation
        document.getElementById('registerForm').addEventListener('submit', function(e) {
            const password = document.getElementById('password').value;
            const confirm = document.getElementById('confirm_password').value;
            const birthdate = document.getElementById('birthdate').value;
            const firstname = document.getElementById('firstname').value;
            const lastname = document.getElementById('lastname').value;
            const gender = document.getElementById('gender').value;
            const email = document.getElementById('email').value;
            
            if (!firstname || !lastname) {
                e.preventDefault();
                alert('First name and last name are required!');
                return false;
            }
            
            if (!gender) {
                e.preventDefault();
                alert('Please select gender!');
                return false;
            }
            
            if (!email) {
                e.preventDefault();
                alert('Email address is required!');
                return false;
            }
            
            if (!birthdate) {
                e.preventDefault();
                alert('Birthdate is required!');
                return false;
            }
            
            // Validate age
            const birthDate = new Date(birthdate);
            const today = new Date();
            let age = today.getFullYear() - birthDate.getFullYear();
            const monthDiff = today.getMonth() - birthDate.getMonth();
            const dayDiff = today.getDate() - birthDate.getDate();
            
            if (monthDiff < 0 || (monthDiff === 0 && dayDiff < 0)) {
                age--;
            }
            
            if (age < 15) {
                e.preventDefault();
                alert('Student must be at least 15 years old!');
                return false;
            } else if (age > 30) {
                e.preventDefault();
                alert('Age exceeds maximum allowed (30 years)!');
                return false;
            }
            
            if (password.length < 6) {
                e.preventDefault();
                alert('Password must be at least 6 characters long');
                return false;
            }
            
            if (password !== confirm) {
                e.preventDefault();
                alert('Passwords do not match!');
                return false;
            }
            
            return true;
        });

        // Auto-uppercase middle initial
        document.getElementById('middlename').addEventListener('input', function() {
            this.value = this.value.toUpperCase();
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