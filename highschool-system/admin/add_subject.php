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

// Get grade levels for dropdown
$grade_levels = $conn->query("SELECT * FROM grade_levels ORDER BY id");

// Handle form submission
if($_SERVER['REQUEST_METHOD'] == 'POST') {
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
    
    // Check if subject already exists for this grade level
    if(empty($errors)) {
        $check_query = "SELECT id FROM subjects WHERE subject_name = ? AND grade_id = ?";
        $check_stmt = $conn->prepare($check_query);
        $check_stmt->bind_param("si", $subject_name, $grade_id);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if($check_result->num_rows > 0) {
            $errors[] = "Subject already exists for this grade level";
        }
        $check_stmt->close();
    }
    
    // If no errors, insert the subject
    if(empty($errors)) {
        $insert_query = "INSERT INTO subjects (subject_name, grade_id) VALUES (?, ?)";
        $insert_stmt = $conn->prepare($insert_query);
        $insert_stmt->bind_param("si", $subject_name, $grade_id);
        
        if($insert_stmt->execute()) {
            $_SESSION['success_message'] = "Subject added successfully!";
            header("Location: subjects.php");
            exit();
        } else {
            $errors[] = "Database error: " . $conn->error;
        }
        $insert_stmt->close();
    }
    
    // If there are errors, store them
    if(!empty($errors)) {
        $error = implode("<br>", $errors);
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add New Subject - Admin Dashboard</title>
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
            max-width: 700px;
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
            font-size: 18px;
            margin-bottom: 5px;
        }

        .preview-details .preview-grade {
            display: inline-block;
            padding: 4px 12px;
            background: rgba(11, 79, 46, 0.1);
            color: #0B4F2E;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            margin-top: 5px;
        }

        .preview-details .preview-description {
            color: var(--text-secondary);
            font-size: 13px;
            margin-top: 8px;
            font-style: italic;
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

        /* Subject Categories */
        .category-tags {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-bottom: 20px;
        }

        .category-tag {
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s;
            border: 2px solid transparent;
        }

        .category-tag.core {
            background: rgba(11, 79, 46, 0.1);
            color: #0B4F2E;
        }

        .category-tag.core:hover {
            background: #0B4F2E;
            color: white;
        }

        .category-tag.major {
            background: rgba(255, 193, 7, 0.1);
            color: #ffc107;
        }

        .category-tag.major:hover {
            background: #ffc107;
            color: white;
        }

        .category-tag.elective {
            background: rgba(76, 201, 240, 0.1);
            color: #4cc9f0;
        }

        .category-tag.elective:hover {
            background: #4cc9f0;
            color: white;
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
            
            .form-actions {
                flex-direction: column;
            }
            
            .form-card {
                padding: 25px;
            }
            
            .preview-item {
                flex-direction: column;
                text-align: center;
            }
            
            .category-tags {
                justify-content: center;
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
                <h1>Add New Subject</h1>
                <p>Create a new subject for a grade level</p>
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

            <!-- Form Card -->
            <div class="form-card">
                <h3>
                    <i class="fas fa-book-medical"></i>
                    Subject Information
                </h3>

                <form method="POST" action="" id="subjectForm">
                    <!-- Subject Category Quick Select (Optional - for UX) -->
                    <div class="category-tags">
                        <span class="category-tag core" onclick="setSubjectCategory('Core')">Core Subject</span>
                        <span class="category-tag major" onclick="setSubjectCategory('Major')">Major Subject</span>
                        <span class="category-tag elective" onclick="setSubjectCategory('Elective')">Elective</span>
                    </div>

                    <div class="form-group">
                        <label>Subject Name <span>*</span></label>
                        <input type="text" 
                               id="subject_name" 
                               name="subject_name" 
                               placeholder="e.g., Mathematics, Science, English" 
                               value="<?php echo isset($_POST['subject_name']) ? htmlspecialchars($_POST['subject_name']) : ''; ?>" 
                               required>
                        <div class="form-hint">
                            <i class="fas fa-info-circle"></i>
                            Enter the full subject name
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Grade Level <span>*</span></label>
                        <select id="grade_id" name="grade_id" required>
                            <option value="">Select Grade Level</option>
                            <?php 
                            $grade_levels->data_seek(0);
                            while($grade = $grade_levels->fetch_assoc()): 
                            ?>
                                <option value="<?php echo $grade['id']; ?>" 
                                    <?php echo (isset($_POST['grade_id']) && $_POST['grade_id'] == $grade['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($grade['grade_name']); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Description (Optional)</label>
                        <textarea id="description" 
                                  name="description" 
                                  rows="4"
                                  placeholder="Enter a brief description of the subject"><?php echo isset($_POST['description']) ? htmlspecialchars($_POST['description']) : ''; ?></textarea>
                        <div class="form-hint">
                            <i class="fas fa-info-circle"></i>
                            You can add details about the subject curriculum
                        </div>
                    </div>

                    <!-- Quick Add Common Subjects -->
                    <div style="margin: 20px 0; padding: 15px; background: #f8f9fa; border-radius: 10px;">
                        <h4 style="color: var(--text-primary); font-size: 14px; margin-bottom: 10px; display: flex; align-items: center; gap: 5px;">
                            <i class="fas fa-bolt" style="color: #0B4F2E;"></i> Quick Add Common Subjects
                        </h4>
                        <div style="display: flex; flex-wrap: wrap; gap: 8px;">
                            <button type="button" class="btn-reset" style="padding: 8px 12px; font-size: 12px;" onclick="setSubjectName('Mathematics')">Mathematics</button>
                            <button type="button" class="btn-reset" style="padding: 8px 12px; font-size: 12px;" onclick="setSubjectName('Science')">Science</button>
                            <button type="button" class="btn-reset" style="padding: 8px 12px; font-size: 12px;" onclick="setSubjectName('English')">English</button>
                            <button type="button" class="btn-reset" style="padding: 8px 12px; font-size: 12px;" onclick="setSubjectName('Filipino')">Filipino</button>
                            <button type="button" class="btn-reset" style="padding: 8px 12px; font-size: 12px;" onclick="setSubjectName('Araling Panlipunan')">Araling Panlipunan</button>
                            <button type="button" class="btn-reset" style="padding: 8px 12px; font-size: 12px;" onclick="setSubjectName('MAPEH')">MAPEH</button>
                            <button type="button" class="btn-reset" style="padding: 8px 12px; font-size: 12px;" onclick="setSubjectName('Edukasyon sa Pagpapakatao')">Edukasyon sa Pagpapakatao</button>
                            <button type="button" class="btn-reset" style="padding: 8px 12px; font-size: 12px;" onclick="setSubjectName('Technology and Livelihood Education')">TLE</button>
                        </div>
                    </div>

                    <!-- Live Preview -->
                    <div class="preview-card">
                        <h4><i class="fas fa-eye"></i> Subject Preview</h4>
                        <div class="preview-item">
                            <div class="preview-icon" id="previewIcon">
                                <i class="fas fa-book"></i>
                            </div>
                            <div class="preview-details">
                                <h5 id="previewName"><?php echo isset($_POST['subject_name']) ? htmlspecialchars($_POST['subject_name']) : 'New Subject'; ?></h5>
                                <div>
                                    <span class="preview-grade" id="previewGrade">
                                        <?php 
                                        if(isset($_POST['grade_id'])) {
                                            $grade_levels->data_seek(0);
                                            while($g = $grade_levels->fetch_assoc()) {
                                                if($g['id'] == $_POST['grade_id']) {
                                                    echo $g['grade_name'];
                                                    break;
                                                }
                                            }
                                        } else {
                                            echo "Grade Level";
                                        }
                                        ?>
                                    </span>
                                </div>
                                <div class="preview-description" id="previewDescription">
                                    <?php echo isset($_POST['description']) && !empty($_POST['description']) ? htmlspecialchars($_POST['description']) : 'No description provided'; ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="form-actions">
                        <button type="submit" name="submit" class="btn-submit">
                            <i class="fas fa-save"></i> Add Subject
                        </button>
                        <a href="subjects.php" class="btn-cancel">
                            <i class="fas fa-times"></i> Cancel
                        </a>
                    </div>
                </form>
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
        $grade_levels->data_seek(0);
        while($grade = $grade_levels->fetch_assoc()): 
        ?>
            gradeOptions[<?php echo $grade['id']; ?>] = "<?php echo $grade['grade_name']; ?>";
        <?php endwhile; ?>

        function updatePreview() {
            // Update subject name
            const subjectName = subjectNameInput.value.trim() || 'New Subject';
            previewName.textContent = subjectName;

            // Update grade
            const gradeId = gradeSelect.value;
            if (gradeId && gradeOptions[gradeId]) {
                previewGrade.textContent = gradeOptions[gradeId];
            } else {
                previewGrade.textContent = 'Grade Level';
            }

            // Update description
            const description = descriptionInput.value.trim() || 'No description provided';
            previewDescription.textContent = description;
        }

        subjectNameInput.addEventListener('input', updatePreview);
        gradeSelect.addEventListener('change', updatePreview);
        descriptionInput.addEventListener('input', updatePreview);

        // Set subject name from quick add
        function setSubjectName(name) {
            subjectNameInput.value = name;
            updatePreview();
            subjectNameInput.focus();
        }

        // Set subject category (adds prefix to subject name)
        function setSubjectCategory(category) {
            let currentName = subjectNameInput.value.trim();
            if (currentName) {
                // Check if already has a category prefix
                const prefixes = ['Core:', 'Major:', 'Elective:'];
                let hasPrefix = false;
                for (let prefix of prefixes) {
                    if (currentName.startsWith(prefix)) {
                        hasPrefix = true;
                        currentName = currentName.substring(prefix.length).trim();
                        break;
                    }
                }
                subjectNameInput.value = category + ': ' + currentName;
            } else {
                subjectNameInput.value = category + ': ';
            }
            updatePreview();
            subjectNameInput.focus();
        }

        // Form validation
        document.getElementById('subjectForm').addEventListener('submit', function(e) {
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