<?php
session_start();
include("../config/database.php");

// Check if user is registrar
if(!isset($_SESSION['user']) || $_SESSION['user']['role'] != 'Registrar'){
    header("Location: ../auth/login.php");
    exit();
}

$registrar_name = $_SESSION['user']['fullname'];
$section_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
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

// Get section details
$section_query = "
    SELECT s.*, g.grade_name, g.id as grade_id, u.fullname as adviser_name
    FROM sections s
    LEFT JOIN grade_levels g ON s.grade_id = g.id
    LEFT JOIN users u ON s.adviser_id = u.id
    WHERE s.id = ?
";
$stmt = $conn->prepare($section_query);
$stmt->bind_param("i", $section_id);
$stmt->execute();
$section = $stmt->get_result()->fetch_assoc();
$stmt->close();

if(!$section) {
    $_SESSION['error_message'] = "Section not found.";
    header("Location: sections.php");
    exit();
}

// Handle assign multiple students
if(isset($_POST['assign_selected'])) {
    $student_ids = $_POST['student_ids'] ?? [];
    $success_count = 0;
    $error_count = 0;
    
    foreach($student_ids as $student_id) {
        $update = $conn->query("
            UPDATE enrollments 
            SET section_id = $section_id 
            WHERE student_id = $student_id AND status = 'Enrolled'
        ");
        
        if($update && $conn->affected_rows > 0) {
            $success_count++;
        } else {
            $error_count++;
        }
    }
    
    if($success_count > 0) {
        $success_message = "$success_count student(s) assigned to section successfully!";
    }
    if($error_count > 0) {
        $error_message = "$error_count student(s) could not be assigned.";
    }
}

// Handle assign single student
if(isset($_POST['assign_student'])) {
    $student_id = (int)$_POST['student_id'];
    
    $update = $conn->query("
        UPDATE enrollments 
        SET section_id = $section_id 
        WHERE student_id = $student_id AND status = 'Enrolled'
    ");
    
    if($update && $conn->affected_rows > 0) {
        $success_message = "Student assigned to section successfully!";
    } else {
        $error_message = "Error assigning student. Make sure the student is enrolled.";
    }
}

// Handle remove student
if(isset($_GET['remove'])) {
    $student_id = (int)$_GET['remove'];
    
    $update = $conn->query("
        UPDATE enrollments 
        SET section_id = NULL 
        WHERE student_id = $student_id AND section_id = $section_id
    ");
    
    if($update && $conn->affected_rows > 0) {
        $success_message = "Student removed from section successfully!";
    } else {
        $error_message = "Error removing student.";
    }
}

// Handle bulk remove
if(isset($_POST['remove_selected'])) {
    $student_ids = $_POST['student_ids'] ?? [];
    $success_count = 0;
    $error_count = 0;
    
    foreach($student_ids as $student_id) {
        $update = $conn->query("
            UPDATE enrollments 
            SET section_id = NULL 
            WHERE student_id = $student_id AND section_id = $section_id
        ");
        
        if($update && $conn->affected_rows > 0) {
            $success_count++;
        } else {
            $error_count++;
        }
    }
    
    if($success_count > 0) {
        $success_message = "$success_count student(s) removed from section successfully!";
    }
    if($error_count > 0) {
        $error_message = "$error_count student(s) could not be removed.";
    }
}

// Get students in this section - FIXED: Removed enrollment_date
$section_students = $conn->query("
    SELECT u.id, u.fullname, u.email, u.id_number, e.status, e.school_year
    FROM users u
    JOIN enrollments e ON u.id = e.student_id
    WHERE e.section_id = $section_id AND u.role = 'Student'
    ORDER BY u.fullname
");

// Get available students (enrolled but no section) in the same grade level - FIXED: Removed enrollment_date
$available_students = $conn->query("
    SELECT u.id, u.fullname, u.email, u.id_number, e.school_year
    FROM users u
    JOIN enrollments e ON u.id = e.student_id
    WHERE u.role = 'Student' 
    AND e.status = 'Enrolled'
    AND (e.section_id IS NULL OR e.section_id = 0)
    AND e.grade_id = {$section['grade_id']}
    ORDER BY u.fullname
");

// Get all students in this grade level (for reference)
$all_grade_students = $conn->query("
    SELECT COUNT(*) as total 
    FROM users u
    JOIN enrollments e ON u.id = e.student_id
    WHERE u.role = 'Student' 
    AND e.status = 'Enrolled'
    AND e.grade_id = {$section['grade_id']}
");

$total_grade_students = $all_grade_students->fetch_assoc()['total'];
$current_section_count = $section_students->num_rows;
$available_count = $available_students->num_rows;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Section Students - <?php echo htmlspecialchars($section['section_name']); ?></title>
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
            padding-bottom: 20px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.2);
        }

        .sidebar h2 i {
            color: var(--accent);
        }

        .user-info {
            text-align: center;
            padding: 20px 0;
            border-bottom: 1px solid rgba(255, 255, 255, 0.2);
            margin-bottom: 20px;
        }

        .user-avatar {
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

        .user-info h3 {
            font-size: 18px;
            margin-bottom: 5px;
            color: var(--accent);
        }

        .user-info p {
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
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
        }

        .header-left h1 {
            font-size: 28px;
            color: var(--text-primary);
            margin-bottom: 5px;
        }

        .header-left p {
            color: var(--text-secondary);
        }

        .back-btn {
            background: white;
            color: var(--text-primary);
            padding: 10px 20px;
            border-radius: 8px;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 8px;
            border: 2px solid var(--border-color);
            transition: all 0.3s;
        }

        .back-btn:hover {
            border-color: var(--primary);
            color: var(--primary);
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

        /* Section Info Card */
        .section-info-card {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            border-radius: 15px;
            padding: 25px;
            color: white;
            margin-bottom: 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 20px;
        }

        .section-info h2 {
            font-size: 24px;
            margin-bottom: 10px;
        }

        .section-info p {
            display: flex;
            gap: 20px;
            flex-wrap: wrap;
        }

        .section-info span {
            display: flex;
            align-items: center;
            gap: 8px;
            background: rgba(255,255,255,0.1);
            padding: 8px 15px;
            border-radius: 30px;
            font-size: 14px;
        }

        .section-info i {
            color: var(--accent);
        }

        .stats {
            display: flex;
            gap: 30px;
        }

        .stat-item {
            text-align: center;
        }

        .stat-value {
            font-size: 32px;
            font-weight: 700;
            color: var(--accent);
        }

        .stat-label {
            font-size: 12px;
            opacity: 0.9;
        }

        /* Grid Layout */
        .grid-2 {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
        }

        @media (max-width: 1024px) {
            .grid-2 {
                grid-template-columns: 1fr;
            }
        }

        /* Cards */
        .card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
        }

        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid var(--border-color);
        }

        .card-header h3 {
            display: flex;
            align-items: center;
            gap: 10px;
            color: var(--text-primary);
        }

        .card-header h3 i {
            color: var(--primary);
        }

        .card-header .badge {
            background: var(--primary-light);
            color: var(--primary);
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }

        /* Bulk Actions */
        .bulk-actions {
            display: flex;
            gap: 10px;
            margin-bottom: 15px;
            padding: 10px;
            background: #f8f9fa;
            border-radius: 8px;
        }

        .btn-bulk {
            padding: 8px 15px;
            border: none;
            border-radius: 6px;
            font-weight: 500;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 5px;
            font-size: 13px;
        }

        .btn-bulk.assign {
            background: var(--primary);
            color: white;
        }

        .btn-bulk.assign:hover {
            background: var(--primary-dark);
        }

        .btn-bulk.remove {
            background: var(--danger);
            color: white;
        }

        .btn-bulk.remove:hover {
            background: #c82333;
        }

        .btn-bulk:disabled {
            background: #ccc;
            cursor: not-allowed;
        }

        /* Search Box */
        .search-box {
            display: flex;
            align-items: center;
            gap: 10px;
            background: white;
            border: 2px solid var(--border-color);
            border-radius: 8px;
            padding: 0 12px;
            margin-bottom: 20px;
        }

        .search-box i {
            color: var(--text-secondary);
        }

        .search-box input {
            border: none;
            padding: 10px 0;
            width: 100%;
            font-size: 14px;
            background: transparent;
        }

        .search-box input:focus {
            outline: none;
        }

        /* Student List */
        .student-list {
            list-style: none;
            max-height: 500px;
            overflow-y: auto;
            border: 1px solid var(--border-color);
            border-radius: 8px;
        }

        .student-item {
            display: flex;
            align-items: center;
            padding: 12px 15px;
            border-bottom: 1px solid var(--border-color);
            transition: all 0.3s;
        }

        .student-item:last-child {
            border-bottom: none;
        }

        .student-item:hover {
            background: var(--hover-color);
        }

        .student-checkbox {
            margin-right: 15px;
            width: 18px;
            height: 18px;
            cursor: pointer;
        }

        .student-avatar {
            width: 40px;
            height: 40px;
            background: var(--primary-light);
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--primary);
            font-weight: 600;
            margin-right: 15px;
        }

        .student-info {
            flex: 1;
        }

        .student-info h4 {
            font-size: 15px;
            margin-bottom: 3px;
            color: var(--text-primary);
        }

        .student-info p {
            font-size: 12px;
            color: var(--text-secondary);
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
        }

        .student-info .id-number {
            background: #f0f0f0;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 11px;
        }

        .btn-icon {
            padding: 6px 12px;
            border: none;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 500;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }

        .btn-icon.assign {
            background: var(--primary-light);
            color: var(--primary);
        }

        .btn-icon.assign:hover {
            background: var(--primary);
            color: white;
        }

        .btn-icon.remove {
            background: rgba(220, 53, 69, 0.1);
            color: var(--danger);
        }

        .btn-icon.remove:hover {
            background: var(--danger);
            color: white;
        }

        .no-data {
            text-align: center;
            padding: 40px;
            color: var(--text-secondary);
        }

        .no-data i {
            font-size: 48px;
            margin-bottom: 15px;
            opacity: 0.3;
        }

        /* Select All */
        .select-all {
            padding: 10px 15px;
            background: #f8f9fa;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .select-all label {
            display: flex;
            align-items: center;
            gap: 8px;
            cursor: pointer;
            font-size: 13px;
            color: var(--text-primary);
        }

        @media (max-width: 768px) {
            .sidebar {
                width: 80px;
            }
            
            .sidebar h2 span,
            .user-info h3,
            .user-info p,
            .menu-section h3,
            .menu-items a span {
                display: none;
            }
            
            .main-content {
                margin-left: 80px;
            }
            
            .section-info-card {
                flex-direction: column;
                text-align: center;
            }
            
            .stats {
                justify-content: center;
            }
            
            .student-info p {
                flex-direction: column;
                align-items: flex-start;
                gap: 5px;
            }
        }
    </style>
</head>
<body>
    <div class="app-container">
        <!-- Sidebar -->
        <div class="sidebar">
            <h2><i class="fas fa-check-circle"></i><span>PNHS</span></h2>
            <div class="user-info">
                <div class="user-avatar"><?php echo strtoupper(substr($registrar_name, 0, 1)); ?></div>
                <h3><?php echo htmlspecialchars(explode(' ', $registrar_name)[0]); ?></h3>
                <p><i class="fas fa-user-graduate"></i> Registrar</p>
            </div>
            <div class="menu-section">
                <h3>MAIN MENU</h3>
                <ul class="menu-items">
                    <li><a href="dashboard.php"><i class="fas fa-tachometer-alt"></i><span>Dashboard</span></a></li>
                    <li><a href="enrollments.php"><i class="fas fa-file-signature"></i><span>Enrollments</span></a></li>
                    <li><a href="students.php"><i class="fas fa-user-graduate"></i><span>Students</span></a></li>
                    <li><a href="sections.php" class="active"><i class="fas fa-layer-group"></i><span>Sections</span></a></li>
                    <li><a href="reports.php"><i class="fas fa-chart-bar"></i><span>Reports</span></a></li>
                </ul>
            </div>
            <div class="menu-section">
                <h3>ACCOUNT</h3>
                <ul class="menu-items">
                    <li><a href="profile.php"><i class="fas fa-user"></i><span>Profile</span></a></li>
                    <li><a href="../auth/logout.php"><i class="fas fa-sign-out-alt"></i><span>Logout</span></a></li>
                </ul>
            </div>
        </div>

        <!-- Main Content -->
        <div class="main-content">
            <!-- Header -->
            <div class="dashboard-header">
                <div class="header-left">
                    <h1>Manage Section Students</h1>
                    <p>Assign and remove students from <?php echo htmlspecialchars($section['section_name']); ?></p>
                </div>
                <a href="sections.php" class="back-btn">
                    <i class="fas fa-arrow-left"></i> Back to Sections
                </a>
            </div>

            <!-- Alert Messages -->
            <?php if($success_message): ?>
                <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?php echo $success_message; ?></div>
            <?php endif; ?>
            <?php if($error_message): ?>
                <div class="alert alert-error"><i class="fas fa-exclamation-circle"></i> <?php echo $error_message; ?></div>
            <?php endif; ?>

            <!-- Section Info Card -->
            <div class="section-info-card">
                <div class="section-info">
                    <h2><?php echo htmlspecialchars($section['section_name']); ?></h2>
                    <p>
                        <span><i class="fas fa-layer-group"></i> <?php echo htmlspecialchars($section['grade_name']); ?></span>
                        <span><i class="fas fa-user-tie"></i> Adviser: <?php echo htmlspecialchars($section['adviser_name'] ?? 'Not Assigned'); ?></span>
                    </p>
                </div>
                <div class="stats">
                    <div class="stat-item">
                        <div class="stat-value"><?php echo $current_section_count; ?></div>
                        <div class="stat-label">In This Section</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-value"><?php echo $available_count; ?></div>
                        <div class="stat-label">Available</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-value"><?php echo $total_grade_students; ?></div>
                        <div class="stat-label">Total in Grade</div>
                    </div>
                </div>
            </div>

            <!-- Main Grid -->
            <div class="grid-2">
                <!-- Current Students in Section -->
                <div class="card">
                    <div class="card-header">
                        <h3><i class="fas fa-users"></i> Current Students</h3>
                        <span class="badge"><?php echo $section_students->num_rows; ?> students</span>
                    </div>

                    <?php if($section_students && $section_students->num_rows > 0): ?>
                        <form method="POST" id="removeForm">
                            <div class="bulk-actions">
                                <button type="button" class="btn-bulk remove" onclick="toggleAll('current')">
                                    <i class="fas fa-check-double"></i> Select All
                                </button>
                                <button type="submit" name="remove_selected" class="btn-bulk remove" onclick="return confirm('Remove selected students from this section?')">
                                    <i class="fas fa-user-minus"></i> Remove Selected
                                </button>
                            </div>

                            <div class="search-box">
                                <i class="fas fa-search"></i>
                                <input type="text" id="searchCurrent" placeholder="Search current students...">
                            </div>

                            <div class="student-list" id="currentList">
                                <div class="select-all">
                                    <label>
                                        <input type="checkbox" id="selectAllCurrent"> <strong>Select All</strong> (<?php echo $section_students->num_rows; ?> students)
                                    </label>
                                </div>
                                <?php while($student = $section_students->fetch_assoc()): ?>
                                    <div class="student-item">
                                        <input type="checkbox" name="student_ids[]" value="<?php echo $student['id']; ?>" class="student-checkbox current-checkbox">
                                        <div class="student-avatar"><?php echo strtoupper(substr($student['fullname'], 0, 1)); ?></div>
                                        <div class="student-info">
                                            <h4><?php echo htmlspecialchars($student['fullname']); ?></h4>
                                            <p>
                                                <span><?php echo htmlspecialchars($student['email']); ?></span>
                                                <span class="id-number">ID: <?php echo $student['id_number'] ?? 'N/A'; ?></span>
                                                <span>SY: <?php echo $student['school_year']; ?></span>
                                            </p>
                                        </div>
                                        <a href="?id=<?php echo $section_id; ?>&remove=<?php echo $student['id']; ?>" 
                                           class="btn-icon remove" 
                                           onclick="return confirm('Remove this student from section?')">
                                            <i class="fas fa-times"></i> Remove
                                        </a>
                                    </div>
                                <?php endwhile; ?>
                            </div>
                        </form>
                    <?php else: ?>
                        <div class="no-data">
                            <i class="fas fa-user-graduate"></i>
                            <p>No students in this section yet.</p>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Available Students -->
                <div class="card">
                    <div class="card-header">
                        <h3><i class="fas fa-user-plus"></i> Available Students</h3>
                        <span class="badge"><?php echo $available_students->num_rows; ?> available</span>
                    </div>

                    <?php if($available_students && $available_students->num_rows > 0): ?>
                        <form method="POST" id="assignForm">
                            <div class="bulk-actions">
                                <button type="button" class="btn-bulk assign" onclick="toggleAll('available')">
                                    <i class="fas fa-check-double"></i> Select All
                                </button>
                                <button type="submit" name="assign_selected" class="btn-bulk assign">
                                    <i class="fas fa-user-plus"></i> Assign Selected
                                </button>
                            </div>

                            <div class="search-box">
                                <i class="fas fa-search"></i>
                                <input type="text" id="searchAvailable" placeholder="Search available students...">
                            </div>

                            <div class="student-list" id="availableList">
                                <div class="select-all">
                                    <label>
                                        <input type="checkbox" id="selectAllAvailable"> <strong>Select All</strong> (<?php echo $available_students->num_rows; ?> students)
                                    </label>
                                </div>
                                <?php while($student = $available_students->fetch_assoc()): ?>
                                    <div class="student-item">
                                        <input type="checkbox" name="student_ids[]" value="<?php echo $student['id']; ?>" class="student-checkbox available-checkbox">
                                        <div class="student-avatar"><?php echo strtoupper(substr($student['fullname'], 0, 1)); ?></div>
                                        <div class="student-info">
                                            <h4><?php echo htmlspecialchars($student['fullname']); ?></h4>
                                            <p>
                                                <span><?php echo htmlspecialchars($student['email']); ?></span>
                                                <span class="id-number">ID: <?php echo $student['id_number'] ?? 'N/A'; ?></span>
                                                <span>SY: <?php echo $student['school_year']; ?></span>
                                            </p>
                                        </div>
                                        <form method="POST" style="display:inline;">
                                            <input type="hidden" name="student_id" value="<?php echo $student['id']; ?>">
                                            <button type="submit" name="assign_student" class="btn-icon assign">
                                                <i class="fas fa-plus"></i> Assign
                                            </button>
                                        </form>
                                    </div>
                                <?php endwhile; ?>
                            </div>
                        </form>
                    <?php else: ?>
                        <div class="no-data">
                            <i class="fas fa-user-check"></i>
                            <p>No available students in <?php echo htmlspecialchars($section['grade_name']); ?></p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Quick Actions -->
            <div style="margin-top: 30px; display: flex; gap: 15px; justify-content: flex-end;">
                <a href="sections.php" class="back-btn">
                    <i class="fas fa-arrow-left"></i> Back to Sections
                </a>
                <a href="section_schedule.php?id=<?php echo $section_id; ?>" class="back-btn" style="border-color: var(--primary); color: var(--primary);">
                    <i class="fas fa-calendar-alt"></i> View Schedule
                </a>
            </div>
        </div>
    </div>

    <script>
        // Search functionality for current students
        document.getElementById('searchCurrent')?.addEventListener('keyup', function() {
            const searchText = this.value.toLowerCase();
            const items = document.querySelectorAll('#currentList .student-item');
            
            items.forEach(item => {
                const text = item.textContent.toLowerCase();
                item.style.display = text.includes(searchText) ? 'flex' : 'none';
            });
        });

        // Search functionality for available students
        document.getElementById('searchAvailable')?.addEventListener('keyup', function() {
            const searchText = this.value.toLowerCase();
            const items = document.querySelectorAll('#availableList .student-item');
            
            items.forEach(item => {
                const text = item.textContent.toLowerCase();
                item.style.display = text.includes(searchText) ? 'flex' : 'none';
            });
        });

        // Select all for current students
        document.getElementById('selectAllCurrent')?.addEventListener('change', function() {
            const checkboxes = document.querySelectorAll('.current-checkbox');
            checkboxes.forEach(cb => cb.checked = this.checked);
        });

        // Select all for available students
        document.getElementById('selectAllAvailable')?.addEventListener('change', function() {
            const checkboxes = document.querySelectorAll('.available-checkbox');
            checkboxes.forEach(cb => cb.checked = this.checked);
        });

        // Toggle all function for bulk actions
        function toggleAll(type) {
            const checkboxes = type === 'current' 
                ? document.querySelectorAll('.current-checkbox')
                : document.querySelectorAll('.available-checkbox');
            
            const allChecked = Array.from(checkboxes).every(cb => cb.checked);
            checkboxes.forEach(cb => cb.checked = !allChecked);
            
            // Update select all checkbox
            if(type === 'current') {
                document.getElementById('selectAllCurrent').checked = !allChecked;
            } else {
                document.getElementById('selectAllAvailable').checked = !allChecked;
            }
        }

        // Auto-hide alerts
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