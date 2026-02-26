<?php
session_start();
include("../config/database.php");

// Check if user is teacher
if(!isset($_SESSION['user']) || $_SESSION['user']['role'] != 'Teacher'){
    header("Location: ../auth/login.php");
    exit();
}

$teacher_id = $_SESSION['user']['id'];
$teacher_name = $_SESSION['user']['fullname'];
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

// Get teacher's sections (where they are adviser)
$sections_query = "
    SELECT s.*, g.grade_name
    FROM sections s
    JOIN grade_levels g ON s.grade_id = g.id
    WHERE s.adviser_id = ?
    ORDER BY g.id, s.section_name
";
$stmt = $conn->prepare($sections_query);
$stmt->bind_param("i", $teacher_id);
$stmt->execute();
$sections = $stmt->get_result();
$stmt->close();

// Get teacher's subjects
$subjects_query = "
    SELECT sub.*, g.grade_name
    FROM subjects sub
    JOIN grade_levels g ON sub.grade_id = g.id
    ORDER BY g.id, sub.subject_name
";
$subjects = $conn->query($subjects_query);

// Get selected section and subject from URL
$selected_section = isset($_GET['section_id']) ? $_GET['section_id'] : '';
$selected_subject = isset($_GET['subject_id']) ? $_GET['subject_id'] : '';
$selected_quarter = isset($_GET['quarter']) ? $_GET['quarter'] : '1st Quarter';

// Check if grades table exists
$grades_table_exists = false;
$table_check = $conn->query("SHOW TABLES LIKE 'grades'");
if($table_check && $table_check->num_rows > 0) {
    $grades_table_exists = true;
}

// Get students for selected section
$students_list = [];
if($selected_section) {
    // Get grade_id from section
    $section_info = $conn->query("SELECT grade_id FROM sections WHERE id = '$selected_section'")->fetch_assoc();
    if($section_info) {
        $grade_id = $section_info['grade_id'];
        
        // Get enrolled students in this grade level
        $students_query = $conn->query("
            SELECT u.*, e.id as enrollment_id
            FROM users u
            JOIN enrollments e ON u.id = e.student_id
            WHERE u.role = 'Student' 
            AND e.grade_id = '$grade_id'
            AND e.status = 'Enrolled'
            ORDER BY u.fullname ASC
        ");
        
        if($students_query) {
            while($student = $students_query->fetch_assoc()) {
                // Check if grade already exists for this student, subject, and quarter
                $student['grade_recorded'] = false;
                $student['grade'] = null;
                
                if($grades_table_exists && $selected_subject) {
                    $grade_check = $conn->query("
                        SELECT * FROM grades 
                        WHERE student_id = '{$student['id']}' 
                        AND subject_id = '$selected_subject'
                        AND quarter = '$selected_quarter'
                    ");
                    
                    if($grade_check && $grade_check->num_rows > 0) {
                        $student['grade_recorded'] = true;
                        $student['grade'] = $grade_check->fetch_assoc();
                    }
                }
                
                $students_list[] = $student;
            }
        }
    }
}

// Handle grade submission
if(isset($_POST['save_grades']) && $grades_table_exists) {
    $subject_id = $_POST['subject_id'];
    $section_id = $_POST['section_id'];
    $quarter = $_POST['quarter'];
    $student_ids = $_POST['student_ids'] ?? [];
    $grades = $_POST['grades'] ?? [];
    $remarks = $_POST['remarks'] ?? [];
    
    $conn->begin_transaction();
    
    try {
        foreach($student_ids as $index => $student_id) {
            $grade_value = $grades[$index] ?? null;
            $remark = $remarks[$index] ?? '';
            
            // Skip if grade is empty
            if($grade_value === '' || $grade_value === null) {
                continue;
            }
            
            // Validate grade range (0-100)
            if($grade_value < 0 || $grade_value > 100) {
                throw new Exception("Grade must be between 0 and 100");
            }
            
            // Check if grade already exists
            $check = $conn->query("
                SELECT id FROM grades 
                WHERE student_id = '$student_id' 
                AND subject_id = '$subject_id'
                AND quarter = '$quarter'
            ");
            
            if($check && $check->num_rows > 0) {
                // Update existing grade
                $grade_id = $check->fetch_assoc()['id'];
                $update = $conn->query("
                    UPDATE grades 
                    SET grade = '$grade_value', remarks = '$remark'
                    WHERE id = '$grade_id'
                ");
            } else {
                // Insert new grade
                $insert = $conn->query("
                    INSERT INTO grades (student_id, subject_id, quarter, grade, remarks, recorded_by)
                    VALUES ('$student_id', '$subject_id', '$quarter', '$grade_value', '$remark', '$teacher_id')
                ");
            }
        }
        
        $conn->commit();
        $success_message = "Grades saved successfully!";
        
        // Refresh the page
        header("Location: grades.php?section_id=$section_id&subject_id=$subject_id&quarter=$quarter&saved=1");
        exit();
        
    } catch (Exception $e) {
        $conn->rollback();
        $error_message = "Error saving grades: " . $e->getMessage();
    }
}

// Get grade statistics if subject and section are selected
$statistics = [];
if($selected_subject && $selected_section && !empty($students_list) && $grades_table_exists) {
    $grade_values = [];
    foreach($students_list as $student) {
        if(isset($student['grade']['grade'])) {
            $grade_values[] = $student['grade']['grade'];
        }
    }
    
    if(!empty($grade_values)) {
        $statistics['average'] = array_sum($grade_values) / count($grade_values);
        $statistics['highest'] = max($grade_values);
        $statistics['lowest'] = min($grade_values);
        $statistics['passed'] = count(array_filter($grade_values, function($g) { return $g >= 75; }));
        $statistics['failed'] = count(array_filter($grade_values, function($g) { return $g < 75; }));
        $statistics['total'] = count($grade_values);
    }
}

// Quarters
$quarters = ['1st Quarter', '2nd Quarter', '3rd Quarter', '4th Quarter'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Placido L. Señor Senior High School</title>
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

        .teacher-info {
            text-align: center;
            padding: 20px 0;
            border-bottom: 1px solid rgba(255, 255, 255, 0.2);
            margin-bottom: 20px;
        }

        .teacher-avatar {
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

        .teacher-info h3 {
            font-size: 18px;
            margin-bottom: 5px;
            color: var(--accent);
        }

        .teacher-info p {
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

        /* Stats Cards */
        .stats-container {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 25px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
        }

        .stat-card::after {
            content: '';
            position: absolute;
            top: 0;
            right: 0;
            width: 100px;
            height: 100px;
            background: linear-gradient(135deg, rgba(11, 79, 46, 0.1) 0%, rgba(26, 122, 66, 0.1) 100%);
            border-radius: 50%;
            transform: translate(30px, -30px);
        }

        .stat-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }

        .stat-header h3 {
            color: var(--text-secondary);
            font-size: 14px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .stat-icon {
            width: 45px;
            height: 45px;
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 20px;
        }

        .stat-number {
            font-size: 32px;
            font-weight: 700;
            color: var(--text-primary);
            margin-bottom: 5px;
        }

        .stat-label {
            color: var(--text-secondary);
            font-size: 14px;
        }

        /* Filter Card */
        .filter-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 30px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
        }

        .filter-card h3 {
            color: var(--text-primary);
            font-size: 18px;
            font-weight: 600;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .filter-card h3 i {
            color: var(--primary);
        }

        .filter-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            align-items: flex-end;
        }

        .filter-group {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .filter-group label {
            color: var(--text-secondary);
            font-size: 13px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .filter-group select,
        .filter-group input {
            padding: 12px 15px;
            border: 2px solid var(--border-color);
            border-radius: 10px;
            font-size: 14px;
            transition: all 0.3s;
            background: white;
        }

        .filter-group select:focus,
        .filter-group input:focus {
            border-color: var(--primary);
            outline: none;
            box-shadow: 0 0 0 3px var(--primary-light);
        }

        .btn-filter {
            background: var(--primary);
            color: white;
            border: none;
            padding: 12px 25px;
            border-radius: 10px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            height: 45px;
        }

        .btn-filter:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(11, 79, 46, 0.3);
        }

        /* Statistics Panel */
        .stats-panel {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 30px;
            color: white;
            display: grid;
            grid-template-columns: repeat(5, 1fr);
            gap: 20px;
        }

        .stat-item {
            text-align: center;
            padding: 10px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 10px;
        }

        .stat-item .stat-value {
            font-size: 24px;
            font-weight: 700;
            margin-bottom: 5px;
            color: var(--accent);
        }

        .stat-item .stat-label {
            font-size: 12px;
            opacity: 0.9;
        }

        /* Grade Table Card */
        .grade-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
        }

        .grade-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            flex-wrap: wrap;
            gap: 15px;
        }

        .grade-header h3 {
            color: var(--text-primary);
            font-size: 18px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .grade-header h3 i {
            color: var(--primary);
        }

        .batch-actions {
            display: flex;
            gap: 10px;
        }

        .btn-batch {
            padding: 8px 16px;
            border: none;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .btn-pass {
            background: rgba(40, 167, 69, 0.1);
            color: var(--success);
        }

        .btn-pass:hover {
            background: var(--success);
            color: white;
        }

        .btn-fail {
            background: rgba(220, 53, 69, 0.1);
            color: var(--danger);
        }

        .btn-fail:hover {
            background: var(--danger);
            color: white;
        }

        .table-container {
            overflow-x: auto;
        }

        .grades-table {
            width: 100%;
            border-collapse: collapse;
        }

        .grades-table th {
            text-align: left;
            padding: 15px;
            background: #f8f9fa;
            color: var(--text-secondary);
            font-weight: 600;
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .grades-table td {
            padding: 15px;
            border-bottom: 1px solid var(--border-color);
            color: var(--text-primary);
        }

        .grades-table tbody tr:hover {
            background: var(--hover-color);
        }

        .student-info {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .student-avatar {
            width: 40px;
            height: 40px;
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
            font-size: 16px;
        }

        .student-details h4 {
            font-size: 15px;
            margin-bottom: 3px;
            color: var(--text-primary);
        }

        .student-details span {
            font-size: 12px;
            color: var(--text-secondary);
        }

        .grade-input {
            width: 80px;
            padding: 8px 12px;
            border: 2px solid var(--border-color);
            border-radius: 8px;
            font-size: 14px;
            text-align: center;
            transition: all 0.3s;
        }

        .grade-input:focus {
            border-color: var(--primary);
            outline: none;
            box-shadow: 0 0 0 3px var(--primary-light);
        }

        .grade-input.passing {
            border-color: var(--success);
            background: rgba(40, 167, 69, 0.05);
        }

        .grade-input.failing {
            border-color: var(--danger);
            background: rgba(220, 53, 69, 0.05);
        }

        .remarks-input {
            width: 100%;
            padding: 8px 12px;
            border: 2px solid var(--border-color);
            border-radius: 8px;
            font-size: 13px;
            transition: all 0.3s;
        }

        .remarks-input:focus {
            border-color: var(--primary);
            outline: none;
        }

        .grade-badge {
            padding: 3px 8px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 600;
            display: inline-block;
        }

        .grade-badge.passing {
            background: rgba(40, 167, 69, 0.1);
            color: var(--success);
        }

        .grade-badge.failing {
            background: rgba(220, 53, 69, 0.1);
            color: var(--danger);
        }

        .btn-save {
            background: var(--primary);
            color: white;
            border: none;
            padding: 14px 30px;
            border-radius: 12px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            gap: 10px;
            margin-top: 25px;
            width: 100%;
            justify-content: center;
        }

        .btn-save:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(11, 79, 46, 0.3);
        }

        .btn-save:disabled {
            background: #ccc;
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }

        .no-data {
            text-align: center;
            padding: 60px;
            color: var(--text-secondary);
        }

        .no-data i {
            font-size: 60px;
            margin-bottom: 20px;
            opacity: 0.3;
        }

        .no-data h3 {
            color: var(--text-primary);
            margin-bottom: 10px;
        }

        .warning-message {
            background: #fff3cd;
            color: #856404;
            padding: 15px 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            border-left: 4px solid #ffc107;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        /* Responsive */
        @media (max-width: 1200px) {
            .stats-container,
            .stats-panel {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 768px) {
            .sidebar {
                width: 80px;
                padding: 20px 10px;
            }
            
            .sidebar h2 span,
            .teacher-info h3,
            .teacher-info p,
            .menu-section h3,
            .menu-items a span {
                display: none;
            }
            
            .teacher-avatar {
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
            
            .stats-container,
            .stats-panel {
                grid-template-columns: 1fr;
            }
            
            .filter-grid {
                grid-template-columns: 1fr;
            }
            
            .grade-header {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .student-info {
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
            
            <div class="teacher-info">
                <div class="teacher-avatar">
                    <?php echo strtoupper(substr($teacher_name, 0, 1)); ?>
                </div>
                <h3><?php echo htmlspecialchars(explode(' ', $teacher_name)[0]); ?></h3>
                <p><i class="fas fa-chalkboard-teacher"></i> Teacher</p>
            </div>
            
            <div class="menu-section">
                <h3>MAIN MENU</h3>
                <ul class="menu-items">
                    <li><a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> <span>Dashboard</span></a></li>
                    <li><a href="attendance.php"><i class="fas fa-calendar-check"></i> <span>Attendance</span></a></li>
                    <li><a href="classes.php"><i class="fas fa-users"></i> <span>My Classes</span></a></li>
                    <li><a href="schedule.php"><i class="fas fa-clock"></i> <span>Schedule</span></a></li>
                    <li><a href="grades.php" class="active"><i class="fas fa-star"></i> <span>Grades</span></a></li>
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
                <h1>Grade Management</h1>
                <p>Record and manage student grades</p>
            </div>

            <!-- Welcome Card -->
            <div class="welcome-card">
                <div class="welcome-text">
                    <h2>Welcome back, <?php echo htmlspecialchars(explode(' ', $teacher_name)[0]); ?>! 👋</h2>
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

            <?php if(isset($_GET['saved'])): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    Grades saved successfully!
                </div>
            <?php endif; ?>

            <?php if(!$grades_table_exists && $selected_section && $selected_subject): ?>
                <div class="warning-message">
                    <i class="fas fa-exclamation-triangle"></i>
                    <strong>Note:</strong> The grades table doesn't exist yet. Please run the SQL script to create the grades table. You can still enter grades, but they won't be saved until the table is created.
                </div>
            <?php endif; ?>

            <!-- Quick Stats -->
            <div class="stats-container">
                <div class="stat-card">
                    <div class="stat-header">
                        <h3>My Sections</h3>
                        <div class="stat-icon">
                            <i class="fas fa-layer-group"></i>
                        </div>
                    </div>
                    <div class="stat-number"><?php echo $sections ? $sections->num_rows : 0; ?></div>
                    <div class="stat-label">Classes handling</div>
                </div>

                <div class="stat-card">
                    <div class="stat-header">
                        <h3>Subjects</h3>
                        <div class="stat-icon">
                            <i class="fas fa-book"></i>
                        </div>
                    </div>
                    <div class="stat-number"><?php echo $subjects ? $subjects->num_rows : 0; ?></div>
                    <div class="stat-label">Subjects taught</div>
                </div>

                <div class="stat-card">
                    <div class="stat-header">
                        <h3>Students</h3>
                        <div class="stat-icon">
                            <i class="fas fa-user-graduate"></i>
                        </div>
                    </div>
                    <div class="stat-number"><?php echo count($students_list); ?></div>
                    <div class="stat-label">In selected section</div>
                </div>

                <div class="stat-card">
                    <div class="stat-header">
                        <h3>With Grades</h3>
                        <div class="stat-icon">
                            <i class="fas fa-star"></i>
                        </div>
                    </div>
                    <div class="stat-number">
                        <?php 
                        $graded = 0;
                        foreach($students_list as $s) {
                            if(isset($s['grade_recorded']) && $s['grade_recorded']) $graded++;
                        }
                        echo $graded;
                        ?>
                    </div>
                    <div class="stat-label">Records entered</div>
                </div>
            </div>

            <!-- Filter Card -->
            <div class="filter-card">
                <h3><i class="fas fa-filter"></i> Select Class and Subject</h3>
                <form method="GET" action="">
                    <div class="filter-grid">
                        <div class="filter-group">
                            <label><i class="fas fa-layer-group"></i> Section</label>
                            <select name="section_id" required>
                                <option value="">Select Section</option>
                                <?php if($sections && $sections->num_rows > 0): ?>
                                    <?php while($section = $sections->fetch_assoc()): ?>
                                        <option value="<?php echo $section['id']; ?>" 
                                            <?php echo ($selected_section == $section['id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($section['section_name'] . ' - ' . $section['grade_name']); ?>
                                        </option>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <option value="">No sections assigned</option>
                                <?php endif; ?>
                            </select>
                        </div>

                        <div class="filter-group">
                            <label><i class="fas fa-book"></i> Subject</label>
                            <select name="subject_id" required>
                                <option value="">Select Subject</option>
                                <?php if($subjects && $subjects->num_rows > 0): ?>
                                    <?php 
                                    $subjects->data_seek(0);
                                    while($subject = $subjects->fetch_assoc()): 
                                    ?>
                                        <option value="<?php echo $subject['id']; ?>" 
                                            <?php echo ($selected_subject == $subject['id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($subject['subject_name'] . ' - ' . $subject['grade_name']); ?>
                                        </option>
                                    <?php endwhile; ?>
                                <?php endif; ?>
                            </select>
                        </div>

                        <div class="filter-group">
                            <label><i class="fas fa-chart-line"></i> Quarter</label>
                            <select name="quarter" required>
                                <?php foreach($quarters as $quarter): ?>
                                    <option value="<?php echo $quarter; ?>" 
                                        <?php echo ($selected_quarter == $quarter) ? 'selected' : ''; ?>>
                                        <?php echo $quarter; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="filter-group">
                            <button type="submit" class="btn-filter">
                                <i class="fas fa-search"></i> Load Students
                            </button>
                        </div>
                    </div>
                </form>
            </div>

            <!-- Statistics Panel -->
            <?php if(!empty($statistics)): ?>
                <div class="stats-panel">
                    <div class="stat-item">
                        <div class="stat-value"><?php echo number_format($statistics['average'], 2); ?></div>
                        <div class="stat-label">Class Average</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-value"><?php echo $statistics['highest']; ?></div>
                        <div class="stat-label">Highest Grade</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-value"><?php echo $statistics['lowest']; ?></div>
                        <div class="stat-label">Lowest Grade</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-value"><?php echo $statistics['passed']; ?></div>
                        <div class="stat-label">Passed</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-value"><?php echo $statistics['failed']; ?></div>
                        <div class="stat-label">Failed</div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Grade Entry Form -->
            <?php if(!empty($students_list)): ?>
                <div class="grade-card">
                    <div class="grade-header">
                        <h3>
                            <i class="fas fa-star"></i>
                            Grade Entry - <?php echo $selected_quarter; ?>
                        </h3>
                        <div class="batch-actions">
                            <button type="button" class="btn-batch btn-pass" onclick="setAllGrades(75)">
                                <i class="fas fa-check-circle"></i> All Passing (75)
                            </button>
                            <button type="button" class="btn-batch btn-fail" onclick="setAllGrades(65)">
                                <i class="fas fa-times-circle"></i> All Failing (65)
                            </button>
                        </div>
                    </div>

                    <form method="POST" action="" id="gradesForm">
                        <input type="hidden" name="section_id" value="<?php echo $selected_section; ?>">
                        <input type="hidden" name="subject_id" value="<?php echo $selected_subject; ?>">
                        <input type="hidden" name="quarter" value="<?php echo $selected_quarter; ?>">
                        
                        <div class="table-container">
                            <table class="grades-table">
                                <thead>
                                    <tr>
                                        <th>Student</th>
                                        <th>Grade</th>
                                        <th>Remarks</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach($students_list as $index => $student): ?>
                                        <tr>
                                            <td>
                                                <div class="student-info">
                                                    <div class="student-avatar">
                                                        <?php echo strtoupper(substr($student['fullname'], 0, 1)); ?>
                                                    </div>
                                                    <div class="student-details">
                                                        <h4><?php echo htmlspecialchars($student['fullname']); ?></h4>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <input type="hidden" name="student_ids[]" value="<?php echo $student['id']; ?>">
                                                <input type="number" 
                                                       name="grades[]" 
                                                       class="grade-input <?php 
                                                           if(isset($student['grade']['grade'])) {
                                                               echo $student['grade']['grade'] >= 75 ? 'passing' : 'failing';
                                                           }
                                                       ?>" 
                                                       value="<?php echo isset($student['grade']['grade']) ? $student['grade']['grade'] : ''; ?>"
                                                       min="0" 
                                                       max="100" 
                                                       step="0.01"
                                                       placeholder="0-100"
                                                       oninput="validateGrade(this)">
                                            </td>
                                            <td>
                                                <input type="text" 
                                                       name="remarks[]" 
                                                       class="remarks-input" 
                                                       value="<?php echo isset($student['grade']['remarks']) ? htmlspecialchars($student['grade']['remarks']) : ''; ?>"
                                                       placeholder="Optional remarks">
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <?php if($grades_table_exists): ?>
                            <button type="submit" name="save_grades" class="btn-save">
                                <i class="fas fa-save"></i> Save Grades
                            </button>
                        <?php else: ?>
                            <button type="button" class="btn-save" style="background: #ccc;" disabled>
                                <i class="fas fa-exclamation-triangle"></i> Grades Table Not Created Yet
                            </button>
                        <?php endif; ?>
                    </form>
                </div>
            <?php elseif($selected_section && $selected_subject): ?>
                <div class="grade-card">
                    <div class="no-data">
                        <i class="fas fa-user-graduate"></i>
                        <h3>No Students Found</h3>
                        <p>There are no enrolled students in this section.</p>
                    </div>
                </div>
            <?php else: ?>
                <div class="grade-card">
                    <div class="no-data">
                        <i class="fas fa-hand-pointer"></i>
                        <h3>Select a Class and Subject</h3>
                        <p>Please select a section, subject, and quarter to enter grades.</p>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        // Function to validate grade input
        function validateGrade(input) {
            let value = parseFloat(input.value);
            if (input.value === '') {
                input.classList.remove('passing', 'failing');
                return;
            }
            if (value >= 75) {
                input.classList.add('passing');
                input.classList.remove('failing');
            } else {
                input.classList.add('failing');
                input.classList.remove('passing');
            }
        }

        // Function to set all grades to a specific value
        function setAllGrades(grade) {
            const gradeInputs = document.querySelectorAll('.grade-input');
            gradeInputs.forEach(input => {
                input.value = grade;
                validateGrade(input);
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

        // Form validation
        document.getElementById('gradesForm')?.addEventListener('submit', function(e) {
            const gradeInputs = document.querySelectorAll('.grade-input');
            let hasInvalid = false;
            
            gradeInputs.forEach(input => {
                if (input.value !== '') {
                    const value = parseFloat(input.value);
                    if (value < 0 || value > 100) {
                        hasInvalid = true;
                        input.style.borderColor = '#dc3545';
                    }
                }
            });
            
            if (hasInvalid) {
                e.preventDefault();
                alert('Please ensure all grades are between 0 and 100');
            }
        });
    </script>
    <?php include('../includes/chatbot_widget_teacher.php'); ?>
</body>
</html>