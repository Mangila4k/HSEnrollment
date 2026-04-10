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

// Get grade levels for dropdown using PDO
$grade_levels_stmt = $conn->prepare("SELECT * FROM grade_levels ORDER BY id");
$grade_levels_stmt->execute();
$grade_levels = $grade_levels_stmt->fetchAll(PDO::FETCH_ASSOC);

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
        $check_stmt = $conn->prepare("SELECT id FROM subjects WHERE subject_name = ? AND grade_id = ?");
        $check_stmt->execute([$subject_name, $grade_id]);
        
        if($check_stmt->rowCount() > 0) {
            $errors[] = "Subject already exists for this grade level";
        }
    }
    
    // If no errors, insert the subject
    if(empty($errors)) {
        try {
            $insert_stmt = $conn->prepare("INSERT INTO subjects (subject_name, grade_id) VALUES (?, ?)");
            $insert_stmt->execute([$subject_name, $grade_id]);
            
            $_SESSION['success_message'] = "Subject added successfully!";
            header("Location: subjects.php");
            exit();
        } catch(PDOException $e) {
            $errors[] = "Database error: " . $e->getMessage();
        }
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
            flex-wrap: wrap;
            gap: 20px;
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

        .input-wrapper {
            position: relative;
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

        .form-group input:read-only,
        .form-group textarea:read-only {
            background: #e9ecef;
            cursor: not-allowed;
            border-color: #dee2e6;
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

        /* Category Tags */
        .category-tags {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-bottom: 20px;
        }

        .category-tag {
            padding: 8px 16px;
            border-radius: 25px;
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
            background: rgba(23, 162, 184, 0.1);
            color: #17a2b8;
        }

        .category-tag.elective:hover {
            background: #17a2b8;
            color: white;
        }

        /* Quick Add Buttons */
        .quick-add-section {
            margin: 20px 0;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 12px;
        }

        .quick-add-section h4 {
            color: var(--text-primary);
            font-size: 14px;
            margin-bottom: 12px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .quick-add-section h4 i {
            color: #0B4F2E;
        }

        .quick-buttons {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
        }

        .quick-btn {
            background: white;
            color: var(--text-secondary);
            padding: 8px 14px;
            border: 1px solid var(--border-color);
            border-radius: 20px;
            font-size: 12px;
            cursor: pointer;
            transition: all 0.3s;
        }

        .quick-btn:hover {
            border-color: #0B4F2E;
            color: #0B4F2E;
            background: white;
            transform: translateY(-2px);
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
            margin-bottom: 8px;
        }

        .preview-grade {
            display: inline-block;
            padding: 4px 12px;
            background: rgba(11, 79, 46, 0.1);
            color: #0B4F2E;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }

        .preview-description {
            color: var(--text-secondary);
            font-size: 13px;
            margin-top: 8px;
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

        .btn-submit {
            flex: 1;
            background: linear-gradient(135deg, #0B4F2E, #1a7a42);
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

        /* Active Category Indicator */
        .active-category {
            background: #0B4F2E;
            color: white;
            position: relative;
        }

        .active-category::after {
            content: '✓';
            margin-left: 5px;
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
            
            .category-tags,
            .quick-buttons {
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

            <!-- Form Card -->
            <div class="form-card">
                <h3>
                    <i class="fas fa-book-medical"></i>
                    Subject Information
                </h3>

                <form method="POST" action="" id="subjectForm">
                    <!-- Subject Category Quick Select -->
                    <div class="category-tags" id="categoryTags">
                        <span class="category-tag core" onclick="selectCategory('Core')">📚 Core Subject</span>
                        <span class="category-tag elective" onclick="selectCategory('Elective')">🎯 Elective</span>
                    </div>

                    <div class="form-group">
                        <label>Subject Name <span>*</span></label>
                        <div class="input-wrapper">
                            <input type="text" 
                                   id="subject_name" 
                                   name="subject_name" 
                                   placeholder="Enter Subject Name" 
                                   value="<?php echo isset($_POST['subject_name']) ? htmlspecialchars($_POST['subject_name']) : 'Enter Subject Name'; ?>" 
                                   required>
                        </div>
                        <div class="form-hint">
                            <i class="fas fa-info-circle"></i>
                            Click on category tags above to add a prefix. The prefix cannot be erased once added.
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Grade Level <span>*</span></label>
                        <select id="grade_id" name="grade_id" required>
                            <option value="">Select Grade Level</option>
                            <?php foreach($grade_levels as $grade): ?>
                                <option value="<?php echo $grade['id']; ?>" 
                                    <?php echo (isset($_POST['grade_id']) && $_POST['grade_id'] == $grade['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($grade['grade_name']); ?>
                                </option>
                            <?php endforeach; ?>
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
                    <div class="quick-add-section">
                        <h4><i class="fas fa-bolt"></i> Quick Add Common Subjects</h4>
                        <div class="quick-buttons" id="quickButtons">
                            <!-- Buttons will be populated dynamically based on grade level -->
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
                                <h5 id="previewName"><?php echo isset($_POST['subject_name']) ? htmlspecialchars($_POST['subject_name']) : 'Enter Subject Name'; ?></h5>
                                <div>
                                    <span class="preview-grade" id="previewGrade">
                                        <?php 
                                        if(isset($_POST['grade_id'])) {
                                            foreach($grade_levels as $g) {
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
        // Create grade options object for preview
        const gradeOptions = {};
        <?php foreach($grade_levels as $grade): ?>
            gradeOptions[<?php echo $grade['id']; ?>] = "<?php echo $grade['grade_name']; ?>";
        <?php endforeach; ?>

        // Subject lists by grade level
        const juniorHighSubjects = [
            'Mathematics', 'Science', 'English', 'Filipino', 'Araling Panlipunan', 
            'MAPEH', 'Edukasyon sa Pagpapakatao', 'Technology and Livelihood Education'
        ];
        
        const seniorHighSubjects = [
            'General Mathematics', 'Statistics and Probability', 'Earth Science', 'Physical Science',
            '21st Century Literature', 'Oral Communication', 'Reading and Writing Skills',
            'Personal Development', 'Understanding Culture, Society and Politics',
            'Introduction to Philosophy', 'Physical Education and Health'
        ];

        // Get DOM elements
        const subjectNameInput = document.getElementById('subject_name');
        const gradeSelect = document.getElementById('grade_id');
        const descriptionInput = document.getElementById('description');
        const previewName = document.getElementById('previewName');
        const previewGrade = document.getElementById('previewGrade');
        const previewDescription = document.getElementById('previewDescription');
        const categoryTags = document.getElementById('categoryTags');
        const quickButtons = document.getElementById('quickButtons');

        let currentCategory = null;
        let isPrefixProtected = false;
        let currentPrefix = '';

        // Function to select a category and add protected prefix
        function selectCategory(category) {
            const gradeId = parseInt(gradeSelect.value);
            
            // Check if grade level is selected
            if (!gradeId) {
                alert('Please select a grade level first');
                return;
            }
            
            // For Senior High, only allow Major category (handled separately)
            const isSeniorHigh = gradeId === 5 || gradeId === 6;
            if (isSeniorHigh) {
                alert('For Senior High, only "Major" category is available.');
                return;
            }
            
            currentCategory = category;
            const prefix = category + ':';
            
            // Remove any existing prefix
            const prefixes = ['Core:', 'Major:', 'Elective:'];
            let currentValue = subjectNameInput.value;
            for (let p of prefixes) {
                if (currentValue.startsWith(p)) {
                    currentValue = currentValue.substring(p.length).trim();
                    break;
                }
            }
            
            // Set new value with prefix
            if (currentValue === 'Enter Subject Name') {
                subjectNameInput.value = prefix + ' Enter Subject Name';
            } else {
                subjectNameInput.value = prefix + ' ' + currentValue;
            }
            
            isPrefixProtected = true;
            currentPrefix = prefix;
            
            // Update active state on category tags
            document.querySelectorAll('.category-tag').forEach(tag => {
                tag.classList.remove('active-category');
            });
            event.target.classList.add('active-category');
            
            updatePreview();
        }

        // Function to enforce prefix protection
        function enforcePrefixProtection() {
            const currentValue = subjectNameInput.value;
            
            if (isPrefixProtected && currentPrefix) {
                if (!currentValue.startsWith(currentPrefix)) {
                    subjectNameInput.value = currentPrefix + ' ' + currentValue;
                }
            }
        }

        // Function to prevent erasing the protected prefix
        function protectPrefix(e) {
            if (!isPrefixProtected || !currentPrefix) return;
            
            const start = this.selectionStart;
            const end = this.selectionEnd;
            const prefixLength = currentPrefix.length;
            
            // Check if user is trying to delete the prefix
            if (start < prefixLength && end > 0) {
                e.preventDefault();
                alert(`The "${currentPrefix}" prefix is protected and cannot be erased.`);
                return false;
            }
        }

        // Function to handle input while protecting prefix
        function handleInput(e) {
            if (!isPrefixProtected || !currentPrefix) return;
            
            let newValue = this.value;
            
            // Ensure prefix is always present
            if (!newValue.startsWith(currentPrefix)) {
                if (newValue === '' || newValue === 'Enter Subject Name') {
                    this.value = currentPrefix + ' Enter Subject Name';
                } else {
                    this.value = currentPrefix + ' ' + newValue;
                }
            }
            
            // Prevent deleting the prefix entirely
            if (newValue === currentPrefix) {
                this.value = currentPrefix + ' Enter Subject Name';
            }
            
            updatePreview();
        }

        // Function to update category tags based on grade level
        function updateCategoryTags() {
            const gradeId = parseInt(gradeSelect.value);
            const isSeniorHigh = gradeId === 5 || gradeId === 6;
            
            if (isSeniorHigh) {
                // For Senior High, show only Major category
                categoryTags.innerHTML = `
                    <span class="category-tag major" onclick="selectMajorCategory()">⭐ Major Subject (Required)</span>
                `;
                // Auto-select Major category
                if (!currentCategory) {
                    selectMajorCategory();
                }
            } else if (gradeId) {
                // For Junior High, show Core and Elective
                categoryTags.innerHTML = `
                    <span class="category-tag core" onclick="selectCategory('Core')">📚 Core Subject</span>
                    <span class="category-tag elective" onclick="selectCategory('Elective')">🎯 Elective</span>
                `;
                // Reset prefix protection if no category selected
                if (!currentCategory) {
                    isPrefixProtected = false;
                    currentPrefix = '';
                }
            } else {
                // No grade selected, show default but disable
                categoryTags.innerHTML = `
                    <span class="category-tag core" onclick="selectCategory('Core')">📚 Core Subject</span>
                    <span class="category-tag elective" onclick="selectCategory('Elective')">🎯 Elective</span>
                `;
            }
        }

        // Function to select Major category (for Senior High)
        function selectMajorCategory() {
            const gradeId = parseInt(gradeSelect.value);
            const isSeniorHigh = gradeId === 5 || gradeId === 6;
            
            if (!isSeniorHigh) {
                alert('Major category is only available for Senior High (Grades 11-12)');
                return;
            }
            
            currentCategory = 'Major';
            currentPrefix = 'Major:';
            
            // Set the value with prefix
            let currentValue = subjectNameInput.value;
            if (currentValue === 'Enter Subject Name' || currentValue === '' || currentValue === 'Core:' || currentValue === 'Elective:') {
                subjectNameInput.value = currentPrefix + ' Enter Subject Name';
            } else {
                // Remove any existing prefix
                const prefixes = ['Core:', 'Major:', 'Elective:'];
                for (let p of prefixes) {
                    if (currentValue.startsWith(p)) {
                        currentValue = currentValue.substring(p.length).trim();
                        break;
                    }
                }
                subjectNameInput.value = currentPrefix + ' ' + currentValue;
            }
            
            isPrefixProtected = true;
            
            // Update active state
            document.querySelectorAll('.category-tag').forEach(tag => {
                tag.classList.remove('active-category');
            });
            const activeTag = document.querySelector('.category-tag.major');
            if (activeTag) activeTag.classList.add('active-category');
            
            updatePreview();
        }

        // Function to update quick buttons based on grade level
        function updateQuickButtons() {
            const gradeId = parseInt(gradeSelect.value);
            const isSeniorHigh = gradeId === 5 || gradeId === 6;
            
            if (!gradeId) {
                quickButtons.innerHTML = '<p style="color: var(--text-secondary); font-size: 12px;">Select a grade level to see quick add options</p>';
                return;
            }
            
            let subjects = [];
            if (isSeniorHigh) {
                subjects = seniorHighSubjects;
            } else {
                subjects = juniorHighSubjects;
            }
            
            // Generate buttons
            quickButtons.innerHTML = subjects.map(subject => 
                `<button type="button" class="quick-btn" onclick="setSubjectName('${subject}')">${subject}</button>`
            ).join('');
        }

        // Set subject name from quick add
        function setSubjectName(name) {
            let currentValue = subjectNameInput.value;
            
            if (isPrefixProtected && currentPrefix) {
                // For protected fields, add after the prefix
                if (currentValue === currentPrefix + ' Enter Subject Name' || currentValue === currentPrefix) {
                    subjectNameInput.value = currentPrefix + ' ' + name;
                } else {
                    // Remove prefix for checking duplicates
                    let cleanValue = currentValue.substring(currentPrefix.length).trim();
                    if (!cleanValue.includes(name)) {
                        subjectNameInput.value = currentValue + ', ' + name;
                    } else {
                        alert('This subject is already in the list');
                    }
                }
            } else {
                // For unprotected fields
                if (currentValue === 'Enter Subject Name') {
                    subjectNameInput.value = name;
                } else {
                    if (!currentValue.includes(name)) {
                        subjectNameInput.value = currentValue + ', ' + name;
                    } else {
                        alert('This subject is already in the list');
                    }
                }
            }
            
            updatePreview();
            subjectNameInput.focus();
        }

        // Update preview function
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

        // Reset category when grade changes
        function resetCategory() {
            const gradeId = parseInt(gradeSelect.value);
            const isSeniorHigh = gradeId === 5 || gradeId === 6;
            
            if (isSeniorHigh) {
                // For Senior High, automatically set Major category
                selectMajorCategory();
            } else {
                // For Junior High, reset protection
                currentCategory = null;
                isPrefixProtected = false;
                currentPrefix = '';
                
                // Remove any existing prefix from the value
                let currentValue = subjectNameInput.value;
                const prefixes = ['Core:', 'Major:', 'Elective:'];
                for (let p of prefixes) {
                    if (currentValue.startsWith(p)) {
                        currentValue = currentValue.substring(p.length).trim();
                        subjectNameInput.value = currentValue;
                        break;
                    }
                }
                
                // Remove active class from category tags
                document.querySelectorAll('.category-tag').forEach(tag => {
                    tag.classList.remove('active-category');
                });
            }
            updatePreview();
        }

        // Add event listeners
        if (subjectNameInput) {
            subjectNameInput.addEventListener('keydown', protectPrefix);
            subjectNameInput.addEventListener('input', handleInput);
            subjectNameInput.addEventListener('blur', enforcePrefixProtection);
        }
        
        if (gradeSelect) {
            gradeSelect.addEventListener('change', function() {
                resetCategory();
                updateCategoryTags();
                updateQuickButtons();
                updatePreview();
            });
        }
        
        if (descriptionInput) descriptionInput.addEventListener('input', updatePreview);

        // Initialize on page load
        updateCategoryTags();
        updateQuickButtons();
        updatePreview();

        // Form validation
        document.getElementById('subjectForm').addEventListener('submit', function(e) {
            const subjectName = subjectNameInput.value.trim();
            const gradeId = gradeSelect.value;
            
            if (gradeId === '5' || gradeId === '6') {
                if (!subjectName.startsWith('Major:') || subjectName === 'Major:' || subjectName === 'Major: Enter Subject Name') {
                    e.preventDefault();
                    alert('For Senior High subjects, you must enter a subject name with the "Major:" prefix.');
                    return false;
                }
            } else {
                // For Junior High, check if a category is selected
                if (!isPrefixProtected && subjectName !== 'Enter Subject Name' && subjectName !== '') {
                    // Allow if user manually entered without category
                    if (!subjectName.startsWith('Core:') && !subjectName.startsWith('Elective:')) {
                        // No category selected, but user can proceed if they entered a custom name
                        if (subjectName === 'Enter Subject Name' || subjectName === '') {
                            e.preventDefault();
                            alert('Please select a category (Core or Elective) or enter a valid subject name.');
                            return false;
                        }
                    }
                } else if (subjectName === 'Enter Subject Name' || subjectName === 'Core: Enter Subject Name' || subjectName === 'Elective: Enter Subject Name') {
                    e.preventDefault();
                    alert('Please enter a valid subject name');
                    return false;
                }
            }
            
            if (!gradeId) {
                e.preventDefault();
                alert('Please select a grade level');
                return false;
            }
            
            return true;
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