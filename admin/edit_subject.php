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
    header("Location: subjects.php");
    exit();
}

$subject_id = $_GET['id'];

// Get subject details
$query = "SELECT s.*, g.grade_name FROM subjects s LEFT JOIN grade_levels g ON s.grade_id = g.id WHERE s.id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $subject_id);
$stmt->execute();
$result = $stmt->get_result();

if($result->num_rows === 0) {
    header("Location: subjects.php");
    exit();
}

$subject = $result->fetch_assoc();
$stmt->close();

// Get grade levels for dropdown
$grade_levels = $conn->query("SELECT * FROM grade_levels ORDER BY id");

// Handle form submission
if($_SERVER['REQUEST_METHOD'] == 'POST') {
    if(isset($_POST['update_subject'])) {
        $subject_name = trim($_POST['subject_name']);
        $grade_id = $_POST['grade_id'];
        $description = trim($_POST['description']);
        
        // Validation
        $errors = [];
        
        if(empty($subject_name)) {
            $errors[] = "Subject name is required";
        }
        
        if(empty($grade_id)) {
            $errors[] = "Grade level is required";
        }
        
        // Check if subject already exists for this grade level (excluding current subject)
        if(empty($errors)) {
            $check_query = "SELECT id FROM subjects WHERE subject_name = ? AND grade_id = ? AND id != ?";
            $check_stmt = $conn->prepare($check_query);
            $check_stmt->bind_param("sii", $subject_name, $grade_id, $subject_id);
            $check_stmt->execute();
            $check_result = $check_stmt->get_result();
            
            if($check_result->num_rows > 0) {
                $errors[] = "Subject already exists for this grade level";
            }
            $check_stmt->close();
        }
        
        // If no errors, update the subject
        if(empty($errors)) {
            $update_query = "UPDATE subjects SET subject_name = ?, grade_id = ? WHERE id = ?";
            $update_stmt = $conn->prepare($update_query);
            $update_stmt->bind_param("sii", $subject_name, $grade_id, $subject_id);
            
            if($update_stmt->execute()) {
                $_SESSION['success_message'] = "Subject updated successfully!";
                header("Location: view_subject.php?id=" . $subject_id);
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
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Subject - Admin Dashboard</title>
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

        /* Subject Info Card */
        .subject-info-card {
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

        .subject-icon-large {
            width: 100px;
            height: 100px;
            background: linear-gradient(135deg, #0B4F2E, #1a7a42);
            border-radius: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 48px;
            font-weight: bold;
            color: white;
            border: 4px solid #FFD700;
        }

        .subject-details {
            flex: 1;
        }

        .subject-details h2 {
            font-size: 28px;
            color: var(--text-primary);
            margin-bottom: 10px;
        }

        .subject-meta {
            display: flex;
            gap: 20px;
            flex-wrap: wrap;
            color: var(--text-secondary);
            font-size: 14px;
        }

        .subject-meta i {
            color: #0B4F2E;
            margin-right: 5px;
        }

        .info-badge {
            display: inline-block;
            padding: 4px 12px;
            background: rgba(11, 79, 46, 0.1);
            color: #0B4F2E;
            border-radius: 20px;
            font-size: 13px;
            margin-right: 10px;
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

        .form-group {
            margin-bottom: 25px;
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
            padding: 14px 18px;
            border: 2px solid var(--border-color);
            border-radius: 12px;
            font-size: 15px;
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

        .form-group input.error {
            border-color: #dc3545;
            background: #fff8f8;
        }

        .form-group textarea {
            resize: vertical;
            min-height: 100px;
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

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 20px;
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
        }

        .preview-details {
            flex: 1;
        }

        .preview-details h5 {
            color: var(--text-primary);
            font-size: 20px;
            margin-bottom: 5px;
        }

        .preview-details .preview-grade {
            display: inline-block;
            padding: 4px 12px;
            background: rgba(11, 79, 46, 0.1);
            color: #0B4F2E;
            border-radius: 20px;
            font-size: 13px;
            font-weight: 500;
            margin-top: 5px;
        }

        .preview-details .preview-description {
            color: var(--text-secondary);
            font-size: 14px;
            margin-top: 10px;
            font-style: italic;
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
            padding: 16px;
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
            padding: 16px;
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

        /* Quick Actions */
        .quick-actions {
            display: flex;
            gap: 15px;
            margin-top: 20px;
        }

        .btn-view {
            flex: 1;
            background: #4a90e2;
            color: white;
            padding: 12px;
            border: none;
            border-radius: 10px;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
            text-align: center;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .btn-view:hover {
            background: #3a7bc8;
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
            
            .subject-info-card {
                flex-direction: column;
                text-align: center;
            }
            
            .subject-meta {
                justify-content: center;
            }
            
            .form-row {
                grid-template-columns: 1fr;
            }
            
            .form-actions {
                flex-direction: column;
            }
            
            .quick-actions {
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
                    <li><a href="subjects.php" class="active"><i class="fas fa-book"></i> <span>Subjects</span></a></li>
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
                    <h1>Edit Subject</h1>
                    <p>Update subject information</p>
                </div>
                <a href="subjects.php" class="back-btn">
                    <i class="fas fa-arrow-left"></i> Back to Subjects
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

            <!-- Subject Info Card -->
            <div class="subject-info-card">
                <div class="subject-icon-large">
                    <i class="fas fa-book-open"></i>
                </div>
                <div class="subject-details">
                    <h2><?php echo htmlspecialchars($subject['subject_name']); ?></h2>
                    <div class="subject-meta">
                        <span><i class="fas fa-layer-group"></i> Current Grade: <?php echo htmlspecialchars($subject['grade_name']); ?></span>
                        <span><i class="fas fa-hashtag"></i> Subject ID: <?php echo $subject['id']; ?></span>
                    </div>
                    <div style="margin-top: 10px;">
                        <span class="info-badge">
                            <i class="fas fa-calendar-check"></i> Attendance records available
                        </span>
                    </div>
                </div>
            </div>

            <!-- Edit Subject Form -->
            <div class="form-card">
                <h3><i class="fas fa-book-medical"></i> Edit Subject Information</h3>
                
                <form method="POST" action="" id="editSubjectForm">
                    <div class="form-group">
                        <label>Subject Name <span>*</span></label>
                        <input type="text" 
                               name="subject_name" 
                               id="subject_name"
                               value="<?php echo htmlspecialchars($subject['subject_name']); ?>" 
                               placeholder="e.g., Mathematics, Science, English"
                               required>
                        <div class="form-hint">
                            <i class="fas fa-info-circle"></i>
                            Enter the full official name of the subject
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Grade Level <span>*</span></label>
                        <select name="grade_id" id="grade_id" required>
                            <option value="">Select Grade Level</option>
                            <?php 
                            if($grade_levels) {
                                $grade_levels->data_seek(0);
                                while($grade = $grade_levels->fetch_assoc()): 
                            ?>
                                <option value="<?php echo $grade['id']; ?>" 
                                    <?php echo $grade['id'] == $subject['grade_id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($grade['grade_name']); ?>
                                </option>
                            <?php 
                                endwhile;
                            } 
                            ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Description (Optional)</label>
                        <textarea name="description" 
                                  id="description" 
                                  rows="4"
                                  placeholder="Enter a brief description of the subject, including topics covered and learning objectives"><?php echo isset($_POST['description']) ? htmlspecialchars($_POST['description']) : ''; ?></textarea>
                        <div class="form-hint">
                            <i class="fas fa-info-circle"></i>
                            Provide additional details about the subject curriculum
                        </div>
                    </div>

                    <!-- Live Preview -->
                    <div class="preview-card">
                        <h4><i class="fas fa-eye"></i> Live Preview</h4>
                        <div class="preview-item">
                            <div class="preview-icon" id="previewIcon">
                                <i class="fas fa-book"></i>
                            </div>
                            <div class="preview-details">
                                <h5 id="previewName"><?php echo htmlspecialchars($subject['subject_name']); ?></h5>
                                <div>
                                    <span class="preview-grade" id="previewGrade">
                                        <?php echo htmlspecialchars($subject['grade_name']); ?>
                                    </span>
                                </div>
                                <div class="preview-description" id="previewDescription">
                                    <?php echo isset($_POST['description']) && !empty($_POST['description']) ? htmlspecialchars($_POST['description']) : 'No description provided'; ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="form-actions">
                        <button type="submit" name="update_subject" class="btn-primary">
                            <i class="fas fa-save"></i> Update Subject
                        </button>
                        <a href="view_subject.php?id=<?php echo $subject_id; ?>" class="btn-secondary">
                            <i class="fas fa-times"></i> Cancel
                        </a>
                    </div>
                </form>
            </div>

            <!-- Quick Actions -->
            <div class="quick-actions">
                <a href="view_subject.php?id=<?php echo $subject_id; ?>" class="btn-view">
                    <i class="fas fa-eye"></i> View Subject Details
                </a>
                <a href="attendance.php?subject=<?php echo $subject_id; ?>" class="btn-view" style="background: #ffc107; color: #2b2d42;">
                    <i class="fas fa-calendar-check"></i> View Attendance
                </a>
            </div>
        </div>
    </div>

    <script>
        // Live preview update
        const subjectNameInput = document.getElementById('subject_name');
        const gradeSelect = document.getElementById('grade_id');
        const descriptionInput = document.getElementById('description');
        const previewName = document.getElementById('previewName');
        const previewGrade = document.getElementById('previewGrade');
        const previewDescription = document.getElementById('previewDescription');

        // Store grade options for preview
        const gradeOptions = {};
        <?php 
        if($grade_levels) {
            $grade_levels->data_seek(0);
            while($grade = $grade_levels->fetch_assoc()): 
        ?>
            gradeOptions[<?php echo $grade['id']; ?>] = "<?php echo $grade['grade_name']; ?>";
        <?php 
            endwhile;
        } 
        ?>

        function updatePreview() {
            // Update subject name
            const subjectName = subjectNameInput.value.trim() || 'Subject Name';
            previewName.textContent = subjectName;

            // Update grade
            const gradeId = gradeSelect.value;
            if (gradeId && gradeOptions[gradeId]) {
                previewGrade.textContent = gradeOptions[gradeId];
            } else {
                previewGrade.textContent = '<?php echo $subject['grade_name']; ?>';
            }

            // Update description
            const description = descriptionInput.value.trim() || 'No description provided';
            previewDescription.textContent = description;
        }

        subjectNameInput.addEventListener('input', updatePreview);
        gradeSelect.addEventListener('change', updatePreview);
        descriptionInput.addEventListener('input', updatePreview);

        // Form validation
        document.getElementById('editSubjectForm').addEventListener('submit', function(e) {
            const subjectName = subjectNameInput.value.trim();
            const gradeId = gradeSelect.value;

            if (!subjectName) {
                e.preventDefault();
                alert('Please enter a subject name');
            } else if (!gradeId) {
                e.preventDefault();
                alert('Please select a grade level');
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