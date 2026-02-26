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

// Get sections where teacher is adviser
$sections_query = $conn->query("
    SELECT s.*, g.grade_name 
    FROM sections s
    JOIN grade_levels g ON s.grade_id = g.id
    WHERE s.adviser_id = '$teacher_id'
");

// Get all subjects (or filter by teacher if you have teacher_id in subjects table)
$subjects_query = $conn->query("SELECT * FROM subjects ORDER BY subject_name");

// Get students for selected section
$selected_section = isset($_GET['section_id']) ? $_GET['section_id'] : '';
$selected_subject = isset($_GET['subject_id']) ? $_GET['subject_id'] : '';
$selected_date = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');

$students_list = [];
if($selected_section && $selected_subject) {
    // Get grade_id from section
    $section_info = $conn->query("SELECT grade_id FROM sections WHERE id = '$selected_section'")->fetch_assoc();
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
    
    while($student = $students_query->fetch_assoc()) {
        // Check if attendance already recorded for this student on this date and subject
        $attendance_check = $conn->query("
            SELECT * FROM attendance 
            WHERE student_id = '{$student['id']}' 
            AND subject_id = '$selected_subject'
            AND date = '$selected_date'
        ");
        
        $student['attendance_recorded'] = ($attendance_check->num_rows > 0);
        if($student['attendance_recorded']) {
            $student['attendance'] = $attendance_check->fetch_assoc();
        }
        
        $students_list[] = $student;
    }
}

// Handle attendance submission
if(isset($_POST['save_attendance'])) {
    $subject_id = $_POST['subject_id'];
    $section_id = $_POST['section_id'];
    $attendance_date = $_POST['attendance_date'];
    $student_ids = $_POST['student_ids'] ?? [];
    $statuses = $_POST['status'] ?? [];
    
    $conn->begin_transaction();
    
    try {
        foreach($student_ids as $index => $student_id) {
            $status = $statuses[$index] ?? 'Absent';
            
            // Check if attendance already exists
            $check = $conn->query("
                SELECT id FROM attendance 
                WHERE student_id = '$student_id' 
                AND subject_id = '$subject_id'
                AND date = '$attendance_date'
            ");
            
            if($check->num_rows > 0) {
                // Update existing attendance
                $attendance_id = $check->fetch_assoc()['id'];
                $update = $conn->query("
                    UPDATE attendance 
                    SET status = '$status'
                    WHERE id = '$attendance_id'
                ");
            } else {
                // Insert new attendance
                $insert = $conn->query("
                    INSERT INTO attendance (student_id, subject_id, date, status)
                    VALUES ('$student_id', '$subject_id', '$attendance_date', '$status')
                ");
            }
        }
        
        $conn->commit();
        $success_message = "Attendance saved successfully!";
        
        // Redirect to refresh with the same parameters
        header("Location: attendance.php?section_id=$section_id&subject_id=$subject_id&date=$attendance_date&saved=1");
        exit();
        
    } catch (Exception $e) {
        $conn->rollback();
        $error_message = "Error saving attendance: " . $e->getMessage();
    }
}

// Handle delete attendance record
if(isset($_GET['delete_id'])) {
    $delete_id = $_GET['delete_id'];
    $delete = $conn->query("DELETE FROM attendance WHERE id = '$delete_id'");
    if($delete) {
        $success_message = "Attendance record deleted successfully!";
    } else {
        $error_message = "Error deleting attendance record.";
    }
}
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

        .teacher-info {
            text-align: center;
            padding: 20px 0;
            border-bottom: 1px solid rgba(255, 255, 255, 0.2);
            margin-bottom: 20px;
        }

        .teacher-avatar {
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

        .teacher-info h3 {
            font-size: 18px;
            margin-bottom: 5px;
            color: #FFD700;
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
            color: #0B4F2E;
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
            border-color: #0B4F2E;
            outline: none;
            box-shadow: 0 0 0 3px rgba(11, 79, 46, 0.1);
        }

        .btn-filter {
            background: #0B4F2E;
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
            background: #1a7a42;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(11, 79, 46, 0.3);
        }

        /* Attendance Table Card */
        .attendance-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
        }

        .attendance-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            flex-wrap: wrap;
            gap: 15px;
        }

        .attendance-header h3 {
            color: var(--text-primary);
            font-size: 18px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .attendance-header h3 i {
            color: #0B4F2E;
        }

        .batch-actions {
            display: flex;
            gap: 10px;
        }

        .btn-batch {
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .btn-present-all {
            background: rgba(40, 167, 69, 0.1);
            color: #28a745;
        }

        .btn-present-all:hover {
            background: #28a745;
            color: white;
        }

        .btn-absent-all {
            background: rgba(220, 53, 69, 0.1);
            color: #dc3545;
        }

        .btn-absent-all:hover {
            background: #dc3545;
            color: white;
        }

        .btn-late-all {
            background: rgba(255, 193, 7, 0.1);
            color: #ffc107;
        }

        .btn-late-all:hover {
            background: #ffc107;
            color: #2b2d42;
        }

        .table-container {
            overflow-x: auto;
        }

        .attendance-table {
            width: 100%;
            border-collapse: collapse;
        }

        .attendance-table th {
            text-align: left;
            padding: 15px;
            background: #f8f9fa;
            color: var(--text-secondary);
            font-weight: 600;
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .attendance-table td {
            padding: 15px;
            border-bottom: 1px solid var(--border-color);
            color: var(--text-primary);
        }

        .student-info {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .student-avatar {
            width: 40px;
            height: 40px;
            background: linear-gradient(135deg, #0B4F2E, #1a7a42);
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

        .status-radio-group {
            display: flex;
            gap: 10px;
        }

        .status-radio {
            display: flex;
            align-items: center;
            gap: 5px;
            cursor: pointer;
        }

        .status-radio input[type="radio"] {
            display: none;
        }

        .status-radio .radio-label {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            transition: all 0.3s;
            border: 2px solid transparent;
        }

        .status-radio input[type="radio"]:checked + .radio-label {
            border-color: #0B4F2E;
            transform: scale(1.05);
        }

        .status-present .radio-label {
            background: rgba(40, 167, 69, 0.1);
            color: #28a745;
        }

        .status-absent .radio-label {
            background: rgba(220, 53, 69, 0.1);
            color: #dc3545;
        }

        .status-late .radio-label {
            background: rgba(255, 193, 7, 0.1);
            color: #ffc107;
        }

        .status-badge {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            display: inline-block;
        }

        .status-present {
            background: rgba(40, 167, 69, 0.1);
            color: #28a745;
        }

        .status-absent {
            background: rgba(220, 53, 69, 0.1);
            color: #dc3545;
        }

        .status-late {
            background: rgba(255, 193, 7, 0.1);
            color: #ffc107;
        }

        .action-btns {
            display: flex;
            gap: 8px;
        }

        .btn-icon {
            width: 35px;
            height: 35px;
            border-radius: 8px;
            border: none;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.3s;
            background: transparent;
            color: var(--text-secondary);
        }

        .btn-icon:hover {
            background: var(--hover-color);
            color: #dc3545;
        }

        .btn-save {
            background: #0B4F2E;
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
            background: #1a7a42;
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

        /* Responsive */
        @media (max-width: 1200px) {
            .filter-grid {
                grid-template-columns: 1fr;
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
            
            .welcome-card {
                flex-direction: column;
                text-align: center;
                gap: 20px;
            }
            
            .attendance-header {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .status-radio-group {
                flex-direction: column;
                gap: 5px;
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
                <h3><?php echo htmlspecialchars($teacher_name); ?></h3>
                <p><i class="fas fa-chalkboard-teacher"></i> Teacher</p>
            </div>
            
            <div class="menu-section">
                <h3>MAIN MENU</h3>
                <ul class="menu-items">
                    <li><a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> <span>Dashboard</span></a></li>
                    <li><a href="attendance.php" class="active"><i class="fas fa-calendar-check"></i> <span>Take Attendance</span></a></li>
                    <li><a href="classes.php"><i class="fas fa-users"></i> <span>My Classes</span></a></li>
                    <li><a href="schedule.php"><i class="fas fa-clock"></i> <span>Schedule</span></a></li>
                    <li><a href="grades.php"><i class="fas fa-star"></i> <span>Grades</span></a></li>
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
                <h1>Take Attendance</h1>
                <p>Record and manage student attendance for your classes</p>
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

            <!-- Success/Error Messages -->
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
                    Attendance saved successfully!
                </div>
            <?php endif; ?>

            <!-- Filter Card -->
            <div class="filter-card">
                <h3><i class="fas fa-filter"></i> Select Class and Subject</h3>
                <form method="GET" action="">
                    <div class="filter-grid">
                        <div class="filter-group">
                            <label><i class="fas fa-layer-group"></i> Section</label>
                            <select name="section_id" required>
                                <option value="">Select Section</option>
                                <?php if($sections_query && $sections_query->num_rows > 0): ?>
                                    <?php while($section = $sections_query->fetch_assoc()): ?>
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
                                <?php if($subjects_query && $subjects_query->num_rows > 0): ?>
                                    <?php while($subject = $subjects_query->fetch_assoc()): ?>
                                        <option value="<?php echo $subject['id']; ?>" 
                                            <?php echo ($selected_subject == $subject['id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($subject['subject_name']); ?>
                                        </option>
                                    <?php endwhile; ?>
                                <?php endif; ?>
                            </select>
                        </div>

                        <div class="filter-group">
                            <label><i class="fas fa-calendar"></i> Date</label>
                            <input type="date" name="date" value="<?php echo $selected_date; ?>" required>
                        </div>

                        <div class="filter-group">
                            <button type="submit" class="btn-filter">
                                <i class="fas fa-search"></i> Load Students
                            </button>
                        </div>
                    </div>
                </form>
            </div>

            <!-- Attendance Form -->
            <?php if(!empty($students_list)): ?>
                <div class="attendance-card">
                    <div class="attendance-header">
                        <h3>
                            <i class="fas fa-users"></i>
                            Students List - <?php echo count($students_list); ?> students
                        </h3>
                        <div class="batch-actions">
                            <button type="button" class="btn-batch btn-present-all" onclick="setAllStatus('Present')">
                                <i class="fas fa-check-circle"></i> All Present
                            </button>
                            <button type="button" class="btn-batch btn-absent-all" onclick="setAllStatus('Absent')">
                                <i class="fas fa-times-circle"></i> All Absent
                            </button>
                            <button type="button" class="btn-batch btn-late-all" onclick="setAllStatus('Late')">
                                <i class="fas fa-clock"></i> All Late
                            </button>
                        </div>
                    </div>

                    <form method="POST" action="" id="attendanceForm">
                        <input type="hidden" name="section_id" value="<?php echo $selected_section; ?>">
                        <input type="hidden" name="subject_id" value="<?php echo $selected_subject; ?>">
                        <input type="hidden" name="attendance_date" value="<?php echo $selected_date; ?>">
                        
                        <div class="table-container">
                            <table class="attendance-table">
                                <thead>
                                    <tr>
                                        <th>Student</th>
                                        <th>Status</th>
                                        <th>Actions</th>
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
                                                        <span>ID: <?php echo $student['id_number'] ?? 'N/A'; ?></span>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <input type="hidden" name="student_ids[]" value="<?php echo $student['id']; ?>">
                                                <div class="status-radio-group">
                                                    <label class="status-radio status-present">
                                                        <input type="radio" name="status[<?php echo $index; ?>]" value="Present" 
                                                            <?php echo (isset($student['attendance']) && $student['attendance']['status'] == 'Present') ? 'checked' : ''; ?>
                                                            <?php echo (!isset($student['attendance'])) ? 'checked' : ''; ?>>
                                                        <span class="radio-label">Present</span>
                                                    </label>
                                                    <label class="status-radio status-absent">
                                                        <input type="radio" name="status[<?php echo $index; ?>]" value="Absent"
                                                            <?php echo (isset($student['attendance']) && $student['attendance']['status'] == 'Absent') ? 'checked' : ''; ?>>
                                                        <span class="radio-label">Absent</span>
                                                    </label>
                                                    <label class="status-radio status-late">
                                                        <input type="radio" name="status[<?php echo $index; ?>]" value="Late"
                                                            <?php echo (isset($student['attendance']) && $student['attendance']['status'] == 'Late') ? 'checked' : ''; ?>>
                                                        <span class="radio-label">Late</span>
                                                    </label>
                                                </div>
                                            </td>
                                            <td>
                                                <?php if(isset($student['attendance'])): ?>
                                                    <div class="action-btns">
                                                        <a href="?delete_id=<?php echo $student['attendance']['id']; ?>&section_id=<?php echo $selected_section; ?>&subject_id=<?php echo $selected_subject; ?>&date=<?php echo $selected_date; ?>" 
                                                           class="btn-icon" 
                                                           onclick="return confirm('Delete this attendance record?')">
                                                            <i class="fas fa-trash"></i>
                                                        </a>
                                                    </div>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <button type="submit" name="save_attendance" class="btn-save">
                            <i class="fas fa-save"></i> Save Attendance
                        </button>
                    </form>
                </div>
            <?php elseif($selected_section && $selected_subject): ?>
                <div class="attendance-card">
                    <div class="no-data">
                        <i class="fas fa-user-graduate"></i>
                        <h3>No Students Found</h3>
                        <p>There are no enrolled students in this section.</p>
                    </div>
                </div>
            <?php else: ?>
                <div class="attendance-card">
                    <div class="no-data">
                        <i class="fas fa-hand-pointer"></i>
                        <h3>Select a Class and Subject</h3>
                        <p>Please select a section, subject, and date to take attendance.</p>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        // Function to set all status radios to the same value
        function setAllStatus(status) {
            const radios = document.querySelectorAll('input[type="radio"][value="' + status + '"]');
            radios.forEach(radio => {
                radio.checked = true;
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
    <?php include('../includes/chatbot_widget_teacher.php'); ?>
</body>
</html>