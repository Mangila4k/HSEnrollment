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
    SELECT s.*, 
           g.grade_name,
           (SELECT COUNT(*) FROM users u 
            JOIN enrollments e ON u.id = e.student_id 
            WHERE e.grade_id = s.grade_id AND e.status = 'Enrolled') as student_count
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
    SELECT sub.*, 
           g.grade_name,
           (SELECT COUNT(*) FROM attendance WHERE subject_id = sub.id) as attendance_count
    FROM subjects sub
    JOIN grade_levels g ON sub.grade_id = g.id
    ORDER BY g.id, sub.subject_name
";

$subjects = $conn->query($subjects_query);

// Get teacher's schedule (if you have a schedules table)
// For now, we'll create a sample schedule based on sections
$schedule = [];
$days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'];
$time_slots = [
    '7:30 AM - 8:30 AM',
    '8:30 AM - 9:30 AM',
    '9:45 AM - 10:45 AM',
    '10:45 AM - 11:45 AM',
    '12:45 PM - 1:45 PM',
    '1:45 PM - 2:45 PM',
    '3:00 PM - 4:00 PM'
];

// Get students per section (for quick view)
$section_students = [];
if($sections && $sections->num_rows > 0) {
    $sections->data_seek(0);
    while($section = $sections->fetch_assoc()) {
        $student_query = "
            SELECT u.id, u.fullname, u.id_number, e.status
            FROM users u
            JOIN enrollments e ON u.id = e.student_id
            WHERE e.grade_id = ? AND e.status = 'Enrolled'
            ORDER BY u.fullname
            LIMIT 5
        ";
        $stmt = $conn->prepare($student_query);
        $stmt->bind_param("i", $section['grade_id']);
        $stmt->execute();
        $result = $stmt->get_result();
        $section_students[$section['id']] = $result->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
    }
    $sections->data_seek(0);
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

        /* Stats Cards */
        .stats-container {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
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
            background: linear-gradient(135deg, #0B4F2E, #1a7a42);
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

        /* Section Tabs */
        .section-tabs {
            display: flex;
            gap: 15px;
            margin-bottom: 30px;
            flex-wrap: wrap;
        }

        .section-tab {
            padding: 12px 25px;
            background: white;
            border-radius: 12px;
            text-decoration: none;
            color: var(--text-primary);
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 10px;
            transition: all 0.3s;
            border: 2px solid transparent;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
        }

        .section-tab:hover {
            border-color: #0B4F2E;
            transform: translateY(-2px);
        }

        .section-tab.active {
            background: #0B4F2E;
            color: white;
        }

        .section-tab.active i {
            color: #FFD700;
        }

        .section-tab i {
            color: #0B4F2E;
        }

        /* Classes Grid */
        .classes-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
            gap: 25px;
            margin-bottom: 30px;
        }

        .class-card {
            background: white;
            border-radius: 20px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            transition: all 0.3s;
        }

        .class-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 30px rgba(0, 0, 0, 0.1);
        }

        .class-header {
            background: linear-gradient(135deg, #0B4F2E, #1a7a42);
            padding: 20px;
            color: white;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .class-header h3 {
            font-size: 18px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .class-header h3 i {
            color: #FFD700;
        }

        .class-badge {
            background: rgba(255, 255, 255, 0.2);
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }

        .class-body {
            padding: 20px;
        }

        .class-info {
            display: flex;
            gap: 15px;
            margin-bottom: 15px;
            padding-bottom: 15px;
            border-bottom: 1px solid var(--border-color);
        }

        .class-info-item {
            flex: 1;
            text-align: center;
        }

        .class-info-value {
            font-size: 20px;
            font-weight: 700;
            color: #0B4F2E;
        }

        .class-info-label {
            font-size: 12px;
            color: var(--text-secondary);
            margin-top: 5px;
        }

        .student-list {
            margin: 15px 0;
        }

        .student-item {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 8px 0;
            border-bottom: 1px solid var(--border-color);
        }

        .student-item:last-child {
            border-bottom: none;
        }

        .student-avatar {
            width: 35px;
            height: 35px;
            background: rgba(11, 79, 46, 0.1);
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #0B4F2E;
            font-weight: 600;
        }

        .student-name {
            flex: 1;
            font-size: 14px;
            font-weight: 500;
        }

        .student-id {
            font-size: 12px;
            color: var(--text-secondary);
        }

        .view-all-link {
            display: inline-block;
            margin-top: 10px;
            color: #0B4F2E;
            text-decoration: none;
            font-size: 13px;
            font-weight: 500;
        }

        .view-all-link:hover {
            text-decoration: underline;
        }

        .class-actions {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
            margin-top: 20px;
        }

        .class-action-btn {
            padding: 10px;
            border-radius: 8px;
            text-decoration: none;
            font-size: 13px;
            font-weight: 500;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 5px;
            transition: all 0.3s;
        }

        .btn-attendance {
            background: rgba(11, 79, 46, 0.1);
            color: #0B4F2E;
        }

        .btn-attendance:hover {
            background: #0B4F2E;
            color: white;
        }

        .btn-grades {
            background: rgba(255, 193, 7, 0.1);
            color: #ffc107;
        }

        .btn-grades:hover {
            background: #ffc107;
            color: white;
        }

        .btn-schedule {
            background: rgba(76, 201, 240, 0.1);
            color: #4cc9f0;
        }

        .btn-schedule:hover {
            background: #4cc9f0;
            color: white;
        }

        .btn-students {
            background: rgba(67, 97, 238, 0.1);
            color: #4361ee;
        }

        .btn-students:hover {
            background: #4361ee;
            color: white;
        }

        /* Schedule Card */
        .schedule-card {
            background: white;
            border-radius: 20px;
            padding: 25px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
        }

        .schedule-card h3 {
            color: var(--text-primary);
            font-size: 18px;
            font-weight: 600;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .schedule-card h3 i {
            color: #0B4F2E;
        }

        .schedule-grid {
            display: grid;
            grid-template-columns: repeat(5, 1fr);
            gap: 15px;
        }

        .day-column {
            background: #f8f9fa;
            border-radius: 12px;
            padding: 15px;
        }

        .day-header {
            font-weight: 600;
            color: #0B4F2E;
            text-align: center;
            padding-bottom: 10px;
            margin-bottom: 10px;
            border-bottom: 2px solid var(--border-color);
        }

        .time-slot {
            background: white;
            padding: 10px;
            border-radius: 8px;
            margin-bottom: 8px;
            font-size: 12px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
        }

        .time-slot .time {
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 3px;
        }

        .time-slot .class-name {
            color: #0B4F2E;
            font-size: 11px;
        }

        .empty-slot {
            background: transparent;
            padding: 10px;
            border-radius: 8px;
            margin-bottom: 8px;
            border: 1px dashed var(--border-color);
            color: var(--text-secondary);
            font-size: 11px;
            text-align: center;
        }

        /* Responsive */
        @media (max-width: 1200px) {
            .schedule-grid {
                grid-template-columns: repeat(3, 1fr);
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
            
            .stats-container {
                grid-template-columns: 1fr;
            }
            
            .schedule-grid {
                grid-template-columns: 1fr;
            }
            
            .class-actions {
                grid-template-columns: 1fr;
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
                    <li><a href="classes.php" class="active"><i class="fas fa-users"></i> <span>My Classes</span></a></li>
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
                <h1>My Classes</h1>
                <p>Manage your advisory classes and subjects</p>
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

            <!-- Statistics -->
            <div class="stats-container">
                <div class="stat-card">
                    <div class="stat-header">
                        <h3>Advisory Classes</h3>
                        <div class="stat-icon">
                            <i class="fas fa-layer-group"></i>
                        </div>
                    </div>
                    <div class="stat-number"><?php echo $sections ? $sections->num_rows : 0; ?></div>
                    <div class="stat-label">Sections handled</div>
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
                        <h3>Total Students</h3>
                        <div class="stat-icon">
                            <i class="fas fa-user-graduate"></i>
                        </div>
                    </div>
                    <div class="stat-number">
                        <?php 
                        $total_students = 0;
                        if($sections) {
                            $sections->data_seek(0);
                            while($section = $sections->fetch_assoc()) {
                                $total_students += $section['student_count'];
                            }
                            $sections->data_seek(0);
                        }
                        echo $total_students;
                        ?>
                    </div>
                    <div class="stat-label">Under your advisory</div>
                </div>
            </div>

            <!-- Section Tabs -->
            <div class="section-tabs">
                <a href="#advisory" class="section-tab active">
                    <i class="fas fa-star"></i> Advisory Classes
                </a>
                <a href="#subjects" class="section-tab">
                    <i class="fas fa-book"></i> Subjects
                </a>
                <a href="#schedule" class="section-tab">
                    <i class="fas fa-clock"></i> Schedule
                </a>
            </div>

            <!-- Advisory Classes -->
            <div id="advisory" class="classes-grid">
                <?php if($sections && $sections->num_rows > 0): ?>
                    <?php while($section = $sections->fetch_assoc()): ?>
                        <div class="class-card">
                            <div class="class-header">
                                <h3>
                                    <i class="fas fa-users"></i>
                                    <?php echo htmlspecialchars($section['section_name']); ?>
                                </h3>
                                <span class="class-badge">Adviser</span>
                            </div>
                            <div class="class-body">
                                <div class="class-info">
                                    <div class="class-info-item">
                                        <div class="class-info-value"><?php echo $section['grade_name']; ?></div>
                                        <div class="class-info-label">Grade Level</div>
                                    </div>
                                    <div class="class-info-item">
                                        <div class="class-info-value"><?php echo $section['student_count']; ?></div>
                                        <div class="class-info-label">Students</div>
                                    </div>
                                </div>

                                <div class="student-list">
                                    <h4 style="font-size: 14px; margin-bottom: 10px; color: var(--text-secondary);">Recent Students</h4>
                                    <?php if(!empty($section_students[$section['id']])): ?>
                                        <?php foreach($section_students[$section['id']] as $student): ?>
                                            <div class="student-item">
                                                <div class="student-avatar">
                                                    <?php echo strtoupper(substr($student['fullname'], 0, 1)); ?>
                                                </div>
                                                <div class="student-name"><?php echo htmlspecialchars($student['fullname']); ?></div>
                                                <div class="student-id"><?php echo $student['id_number'] ?? 'N/A'; ?></div>
                                            </div>
                                        <?php endforeach; ?>
                                        <a href="view_section.php?id=<?php echo $section['id']; ?>" class="view-all-link">
                                            View all students <i class="fas fa-arrow-right"></i>
                                        </a>
                                    <?php else: ?>
                                        <p style="color: var(--text-secondary); font-size: 13px; text-align: center; padding: 15px;">
                                            No students enrolled yet
                                        </p>
                                    <?php endif; ?>
                                </div>

                                <div class="class-actions">
                                    <a href="attendance.php?section=<?php echo $section['id']; ?>" class="class-action-btn btn-attendance">
                                        <i class="fas fa-calendar-check"></i> Attendance
                                    </a>
                                    <a href="grades.php?section=<?php echo $section['id']; ?>" class="class-action-btn btn-grades">
                                        <i class="fas fa-star"></i> Grades
                                    </a>
                                    <a href="view_section.php?id=<?php echo $section['id']; ?>" class="class-action-btn btn-students">
                                        <i class="fas fa-users"></i> Students
                                    </a>
                                    <a href="schedule.php?section=<?php echo $section['id']; ?>" class="class-action-btn btn-schedule">
                                        <i class="fas fa-clock"></i> Schedule
                                    </a>
                                </div>
                            </div>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div style="grid-column: 1/-1; text-align: center; padding: 60px; background: white; border-radius: 20px;">
                        <i class="fas fa-users" style="font-size: 60px; color: var(--text-secondary); opacity: 0.3; margin-bottom: 20px;"></i>
                        <h3 style="color: var(--text-primary); margin-bottom: 10px;">No Advisory Classes</h3>
                        <p style="color: var(--text-secondary);">You are not assigned as adviser to any section yet.</p>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Subjects Taught -->
            <div id="subjects" style="margin-top: 30px; display: none;">
                <div class="classes-grid">
                    <?php if($subjects && $subjects->num_rows > 0): ?>
                        <?php while($subject = $subjects->fetch_assoc()): ?>
                            <div class="class-card">
                                <div class="class-header" style="background: linear-gradient(135deg, #4a90e2, #6c5ce7);">
                                    <h3>
                                        <i class="fas fa-book"></i>
                                        <?php echo htmlspecialchars($subject['subject_name']); ?>
                                    </h3>
                                </div>
                                <div class="class-body">
                                    <div class="class-info">
                                        <div class="class-info-item">
                                            <div class="class-info-value"><?php echo $subject['grade_name']; ?></div>
                                            <div class="class-info-label">Grade Level</div>
                                        </div>
                                        <div class="class-info-item">
                                            <div class="class-info-value"><?php echo $subject['attendance_count']; ?></div>
                                            <div class="class-info-label">Attendance Records</div>
                                        </div>
                                    </div>

                                    <div class="class-actions" style="grid-template-columns: 1fr 1fr;">
                                        <a href="attendance.php?subject=<?php echo $subject['id']; ?>" class="class-action-btn btn-attendance">
                                            <i class="fas fa-calendar-check"></i> Attendance
                                        </a>
                                        <a href="grades.php?subject=<?php echo $subject['id']; ?>" class="class-action-btn btn-grades">
                                            <i class="fas fa-star"></i> Grades
                                        </a>
                                    </div>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <div style="grid-column: 1/-1; text-align: center; padding: 60px; background: white; border-radius: 20px;">
                            <i class="fas fa-book" style="font-size: 60px; color: var(--text-secondary); opacity: 0.3; margin-bottom: 20px;"></i>
                            <h3 style="color: var(--text-primary); margin-bottom: 10px;">No Subjects Assigned</h3>
                            <p style="color: var(--text-secondary);">You are not assigned to teach any subjects yet.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Schedule -->
            <div id="schedule" style="margin-top: 30px; display: none;">
                <div class="schedule-card">
                    <h3><i class="fas fa-calendar-alt"></i> Weekly Schedule</h3>
                    <div class="schedule-grid">
                        <?php foreach($days as $day): ?>
                            <div class="day-column">
                                <div class="day-header"><?php echo $day; ?></div>
                                <?php 
                                // Sample schedule - replace with actual data from database
                                $slot_count = 0;
                                foreach($time_slots as $index => $time): 
                                    if($day == 'Monday' && $index < 3):
                                ?>
                                    <div class="time-slot">
                                        <div class="time"><?php echo $time; ?></div>
                                        <div class="class-name">Grade 11 - STEM A</div>
                                    </div>
                                <?php 
                                    elseif($day == 'Wednesday' && $index >= 3 && $index < 6):
                                ?>
                                    <div class="time-slot">
                                        <div class="time"><?php echo $time; ?></div>
                                        <div class="class-name">Grade 10 - Section B</div>
                                    </div>
                                <?php 
                                    elseif($day == 'Friday' && $index == 4):
                                ?>
                                    <div class="time-slot">
                                        <div class="time"><?php echo $time; ?></div>
                                        <div class="class-name">Grade 12 - ICT</div>
                                    </div>
                                <?php 
                                    else:
                                        $slot_count++;
                                    endif; 
                                endforeach; 
                                
                                // Fill remaining slots with empty
                                for($i = $slot_count; $i < 7; $i++):
                                ?>
                                    <div class="empty-slot">No class</div>
                                <?php endfor; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Tab switching
        const tabs = document.querySelectorAll('.section-tab');
        const sections = {
            'advisory': document.getElementById('advisory'),
            'subjects': document.getElementById('subjects'),
            'schedule': document.getElementById('schedule')
        };

        tabs.forEach(tab => {
            tab.addEventListener('click', function(e) {
                e.preventDefault();
                
                // Remove active class from all tabs
                tabs.forEach(t => t.classList.remove('active'));
                
                // Add active class to clicked tab
                this.classList.add('active');
                
                // Hide all sections
                Object.values(sections).forEach(section => {
                    section.style.display = 'none';
                });
                
                // Show selected section
                const target = this.getAttribute('href').substring(1);
                if(sections[target]) {
                    sections[target].style.display = 'block';
                }
            });
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
    <?php include('../includes/chatbot_widget_teacher.php'); ?>
</body>
</html>