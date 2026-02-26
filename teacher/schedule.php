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

// Get teacher's assigned schedules from class_schedules table
$schedules_query = "
    SELECT 
        cs.*,
        s.section_name,
        g.grade_name,
        sub.subject_name,
        d.day_name,
        d.day_order,
        ts.start_time,
        ts.end_time,
        ts.slot_name
    FROM class_schedules cs
    JOIN sections s ON cs.section_id = s.id
    JOIN grade_levels g ON s.grade_id = g.id
    JOIN subjects sub ON cs.subject_id = sub.id
    JOIN days_of_week d ON cs.day_id = d.id
    JOIN time_slots ts ON cs.time_slot_id = ts.id
    WHERE cs.teacher_id = ? AND cs.status = 'active'
    ORDER BY d.day_order, ts.start_time
";

$stmt = $conn->prepare($schedules_query);
$stmt->bind_param("i", $teacher_id);
$stmt->execute();
$assigned_schedules = $stmt->get_result();

// Organize schedules by day for easy display
$weekly_schedule = [];
$total_hours = 0;
$unique_sections = [];
$unique_subjects = [];

if($assigned_schedules && $assigned_schedules->num_rows > 0) {
    while($class = $assigned_schedules->fetch_assoc()) {
        $day = $class['day_name'];
        $time_slot_id = $class['time_slot_id'];
        
        // Organize by day
        if(!isset($weekly_schedule[$day])) {
            $weekly_schedule[$day] = [];
        }
        $weekly_schedule[$day][$time_slot_id] = $class;
        
        // Calculate total hours
        $start = new DateTime($class['start_time']);
        $end = new DateTime($class['end_time']);
        $interval = $start->diff($end);
        $hours = $interval->h + ($interval->i / 60);
        $total_hours += $hours;
        
        // Track unique sections
        $unique_sections[$class['section_id']] = $class['section_name'] . ' - ' . $class['grade_name'];
        
        // Track unique subjects
        $unique_subjects[$class['subject_id']] = $class['subject_name'];
    }
    $assigned_schedules->data_seek(0); // Reset pointer
}

// Get teacher's advisory sections
$advisory_query = "
    SELECT s.*, g.grade_name
    FROM sections s
    JOIN grade_levels g ON s.grade_id = g.id
    WHERE s.adviser_id = ?
    ORDER BY g.id, s.section_name
";
$stmt = $conn->prepare($advisory_query);
$stmt->bind_param("i", $teacher_id);
$stmt->execute();
$advisory_sections = $stmt->get_result();

// Days of the week in order
$days_order = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'];

// Time slots for the table header
$time_slots_query = "SELECT * FROM time_slots ORDER BY start_time";
$time_slots = $conn->query($time_slots_query);

// Get current week dates
$today = new DateTime();
$start_of_week = clone $today;
$start_of_week->modify('monday this week');
$week_dates = [];
foreach($days_order as $day) {
    $date = clone $start_of_week;
    $date->modify("+" . array_search($day, $days_order) . " days");
    $week_dates[$day] = $date->format('M d, Y');
}

// Calculate statistics
$total_classes = $assigned_schedules ? $assigned_schedules->num_rows : 0;
$total_sections = count($unique_sections);
$total_subjects = count($unique_subjects);
$free_periods = 40 - $total_classes; // Assuming 40 total periods in a week (8 periods x 5 days)
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

        /* Week Navigation */
        .week-nav {
            background: white;
            border-radius: 15px;
            padding: 20px 25px;
            margin-bottom: 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 20px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
        }

        .week-display {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .week-display h3 {
            color: var(--text-primary);
            font-size: 18px;
            font-weight: 600;
        }

        .week-display .week-range {
            color: var(--text-secondary);
            font-size: 14px;
        }

        .nav-buttons {
            display: flex;
            gap: 10px;
        }

        .nav-btn {
            padding: 10px 20px;
            border-radius: 10px;
            text-decoration: none;
            font-size: 14px;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s;
            border: 2px solid var(--border-color);
            background: white;
            color: var(--text-primary);
        }

        .nav-btn:hover {
            border-color: #0B4F2E;
            color: #0B4F2E;
        }

        .nav-btn i {
            font-size: 12px;
        }

        /* Schedule Container */
        .schedule-container {
            background: white;
            border-radius: 20px;
            padding: 25px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            overflow-x: auto;
        }

        .schedule-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            flex-wrap: wrap;
            gap: 15px;
        }

        .schedule-header h3 {
            color: var(--text-primary);
            font-size: 20px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .schedule-header h3 i {
            color: #0B4F2E;
        }

        .schedule-info-badge {
            background: #f8f9fa;
            padding: 8px 15px;
            border-radius: 20px;
            font-size: 13px;
            color: #0B4F2E;
            font-weight: 500;
        }

        .schedule-info-badge i {
            margin-right: 5px;
        }

        /* Schedule Table */
        .schedule-table {
            width: 100%;
            border-collapse: collapse;
            min-width: 800px;
        }

        .schedule-table th {
            text-align: center;
            padding: 15px;
            background: #f8f9fa;
            color: var(--text-secondary);
            font-weight: 600;
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-bottom: 2px solid var(--border-color);
        }

        .schedule-table td {
            padding: 10px;
            border: 1px solid var(--border-color);
            vertical-align: top;
            height: 100px;
            width: 14%;
        }

        .time-column {
            background: #f8f9fa;
            font-weight: 600;
            color: #0B4F2E;
            width: 10%;
            text-align: center;
            vertical-align: middle;
        }

        .schedule-cell {
            height: 100%;
            display: flex;
            flex-direction: column;
            gap: 5px;
        }

        .class-item {
            background: rgba(11, 79, 46, 0.1);
            border-left: 3px solid #0B4F2E;
            padding: 8px;
            border-radius: 6px;
            font-size: 12px;
            transition: all 0.3s;
            cursor: pointer;
        }

        .class-item:hover {
            background: rgba(11, 79, 46, 0.15);
            transform: translateX(2px);
        }

        .class-item.advisory {
            border-left-color: #FFD700;
        }

        .class-item .section-name {
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 3px;
            font-size: 13px;
        }

        .class-item .subject-name {
            color: var(--text-primary);
            margin-bottom: 3px;
        }

        .class-item .class-details {
            color: var(--text-secondary);
            font-size: 11px;
            display: flex;
            flex-direction: column;
            gap: 2px;
        }

        .class-item .class-details i {
            width: 12px;
            color: #0B4F2E;
        }

        .class-item .room-badge {
            display: inline-block;
            background: #0B4F2E;
            color: white;
            padding: 2px 6px;
            border-radius: 4px;
            font-size: 10px;
            margin-top: 4px;
        }

        .empty-cell {
            color: var(--text-secondary);
            font-size: 12px;
            text-align: center;
            padding: 15px;
            font-style: italic;
            background: #f8f9fa;
            border-radius: 6px;
        }

        /* Legend */
        .legend {
            display: flex;
            gap: 20px;
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid var(--border-color);
            flex-wrap: wrap;
        }

        .legend-item {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 13px;
        }

        .legend-color {
            width: 20px;
            height: 20px;
            border-radius: 4px;
        }

        .legend-color.class {
            background: rgba(11, 79, 46, 0.1);
            border-left: 3px solid #0B4F2E;
        }

        .legend-color.advisory {
            background: rgba(255, 215, 0, 0.1);
            border-left: 3px solid #FFD700;
        }

        /* My Classes Sidebar */
        .classes-sidebar {
            margin-top: 30px;
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 25px;
        }

        .class-list-card {
            background: white;
            border-radius: 15px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
        }

        .class-list-card h4 {
            color: var(--text-primary);
            font-size: 16px;
            font-weight: 600;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 8px;
            padding-bottom: 10px;
            border-bottom: 1px solid var(--border-color);
        }

        .class-list-card h4 i {
            color: #0B4F2E;
        }

        .class-list-card .count-badge {
            margin-left: auto;
            background: #0B4F2E;
            color: white;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 11px;
        }

        .class-tag {
            display: inline-block;
            padding: 6px 12px;
            background: #f8f9fa;
            border-radius: 20px;
            font-size: 12px;
            margin: 0 5px 5px 0;
            border: 1px solid var(--border-color);
        }

        .class-tag.advisory {
            background: rgba(255, 215, 0, 0.1);
            border-color: #FFD700;
            color: #b8860b;
        }

        .class-tag i {
            margin-right: 5px;
        }

        .no-data-message {
            color: var(--text-secondary);
            font-size: 13px;
            text-align: center;
            padding: 20px;
        }

        /* Responsive */
        @media (max-width: 1200px) {
            .stats-container {
                grid-template-columns: repeat(2, 1fr);
            }
            .classes-sidebar {
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
            
            .stats-container {
                grid-template-columns: 1fr;
            }
            
            .week-nav {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .classes-sidebar {
                grid-template-columns: 1fr;
            }
            
            .schedule-table td {
                height: 80px;
            }
            
            .class-item {
                font-size: 10px;
                padding: 4px;
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
                    <li><a href="schedule.php" class="active"><i class="fas fa-clock"></i> <span>Schedule</span></a></li>
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
                <h1>My Schedule</h1>
                <p>View your weekly class schedule assigned by the admin</p>
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

            <!-- Statistics -->
            <div class="stats-container">
                <div class="stat-card">
                    <div class="stat-header">
                        <h3>Total Classes</h3>
                        <div class="stat-icon">
                            <i class="fas fa-clock"></i>
                        </div>
                    </div>
                    <div class="stat-number"><?php echo $total_classes; ?></div>
                    <div class="stat-label">Classes per week</div>
                </div>

                <div class="stat-card">
                    <div class="stat-header">
                        <h3>Sections</h3>
                        <div class="stat-icon">
                            <i class="fas fa-users"></i>
                        </div>
                    </div>
                    <div class="stat-number"><?php echo $total_sections; ?></div>
                    <div class="stat-label">Different sections</div>
                </div>

                <div class="stat-card">
                    <div class="stat-header">
                        <h3>Subjects</h3>
                        <div class="stat-icon">
                            <i class="fas fa-book"></i>
                        </div>
                    </div>
                    <div class="stat-number"><?php echo $total_subjects; ?></div>
                    <div class="stat-label">Subjects taught</div>
                </div>

                <div class="stat-card">
                    <div class="stat-header">
                        <h3>Free Periods</h3>
                        <div class="stat-icon">
                            <i class="fas fa-coffee"></i>
                        </div>
                    </div>
                    <div class="stat-number"><?php echo $free_periods; ?></div>
                    <div class="stat-label">Available slots</div>
                </div>
            </div>

            <!-- Week Navigation -->
            <div class="week-nav">
                <div class="week-display">
                    <h3><i class="fas fa-calendar-week"></i> Week Schedule</h3>
                    <span class="week-range">
                        <?php echo $week_dates['Monday']; ?> - <?php echo $week_dates['Friday']; ?>
                    </span>
                </div>
                <div class="nav-buttons">
                    <a href="#" class="nav-btn">
                        <i class="fas fa-chevron-left"></i> Previous Week
                    </a>
                    <a href="#" class="nav-btn">
                        Next Week <i class="fas fa-chevron-right"></i>
                    </a>
                </div>
            </div>

            <!-- Schedule Container -->
            <div class="schedule-container">
                <div class="schedule-header">
                    <h3><i class="fas fa-table"></i> Your Assigned Schedule</h3>
                    <span class="schedule-info-badge">
                        <i class="fas fa-info-circle"></i> 
                        <?php echo $total_classes; ?> classes scheduled
                    </span>
                </div>

                <!-- Schedule Table -->
                <table class="schedule-table">
                    <thead>
                        <tr>
                            <th>Time</th>
                            <?php foreach($days_order as $day): ?>
                                <th>
                                    <?php echo $day; ?><br>
                                    <span style="font-weight: normal; font-size: 11px; color: var(--text-secondary);">
                                        <?php echo $week_dates[$day]; ?>
                                    </span>
                                </th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if($time_slots && $time_slots->num_rows > 0): ?>
                            <?php while($slot = $time_slots->fetch_assoc()): ?>
                                <tr>
                                    <td class="time-column">
                                        <?php echo date('h:i A', strtotime($slot['start_time'])); ?> - <br>
                                        <?php echo date('h:i A', strtotime($slot['end_time'])); ?>
                                    </td>
                                    <?php foreach($days_order as $day): ?>
                                        <td>
                                            <div class="schedule-cell">
                                                <?php 
                                                $class_found = false;
                                                if(isset($weekly_schedule[$day][$slot['id']])): 
                                                    $class = $weekly_schedule[$day][$slot['id']];
                                                    $class_found = true;
                                                ?>
                                                    <div class="class-item">
                                                        <div class="section-name">
                                                            <i class="fas fa-users"></i> 
                                                            <?php echo htmlspecialchars($class['section_name']); ?>
                                                        </div>
                                                        <div class="subject-name">
                                                            <i class="fas fa-book-open"></i>
                                                            <?php echo htmlspecialchars($class['subject_name']); ?>
                                                        </div>
                                                        <?php if($class['room']): ?>
                                                            <div class="room-badge">
                                                                <i class="fas fa-door-open"></i> <?php echo htmlspecialchars($class['room']); ?>
                                                            </div>
                                                        <?php endif; ?>
                                                    </div>
                                                <?php endif; ?>
                                                
                                                <?php if(!$class_found): ?>
                                                    <div class="empty-cell">
                                                        <i class="fas fa-minus-circle"></i> Free
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    <?php endforeach; ?>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" style="text-align: center; padding: 50px;">
                                    <i class="fas fa-clock" style="font-size: 48px; color: var(--text-secondary); opacity: 0.3; margin-bottom: 15px;"></i>
                                    <h3 style="color: var(--text-secondary);">No time slots configured</h3>
                                    <p>Please contact the administrator.</p>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>

                <!-- Legend -->
                <div class="legend">
                    <div class="legend-item">
                        <div class="legend-color class"></div>
                        <span>Regular Class</span>
                    </div>
                    <?php if($advisory_sections && $advisory_sections->num_rows > 0): ?>
                    <div class="legend-item">
                        <div class="legend-color advisory"></div>
                        <span>Advisory Class (You are the adviser)</span>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Summary Cards -->
            <div class="classes-sidebar">
                <!-- Your Sections -->
                <div class="class-list-card">
                    <h4>
                        <i class="fas fa-users"></i> Your Sections
                        <span class="count-badge"><?php echo $total_sections; ?></span>
                    </h4>
                    <?php if(!empty($unique_sections)): ?>
                        <?php foreach($unique_sections as $section): ?>
                            <span class="class-tag">
                                <i class="fas fa-layer-group"></i>
                                <?php echo htmlspecialchars($section); ?>
                            </span>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="no-data-message">
                            <i class="fas fa-info-circle"></i> No sections assigned
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Your Subjects -->
                <div class="class-list-card">
                    <h4>
                        <i class="fas fa-book"></i> Your Subjects
                        <span class="count-badge"><?php echo $total_subjects; ?></span>
                    </h4>
                    <?php if(!empty($unique_subjects)): ?>
                        <?php foreach($unique_subjects as $subject): ?>
                            <span class="class-tag">
                                <i class="fas fa-book-open"></i>
                                <?php echo htmlspecialchars($subject); ?>
                            </span>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="no-data-message">
                            <i class="fas fa-info-circle"></i> No subjects assigned
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Advisory Classes -->
                <div class="class-list-card">
                    <h4>
                        <i class="fas fa-star" style="color: #FFD700;"></i> Advisory Classes
                        <span class="count-badge"><?php echo $advisory_sections ? $advisory_sections->num_rows : 0; ?></span>
                    </h4>
                    <?php if($advisory_sections && $advisory_sections->num_rows > 0): ?>
                        <?php while($section = $advisory_sections->fetch_assoc()): ?>
                            <span class="class-tag advisory">
                                <i class="fas fa-users"></i>
                                <?php echo htmlspecialchars($section['section_name'] . ' - ' . $section['grade_name']); ?>
                            </span>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <div class="no-data-message">
                            <i class="fas fa-info-circle"></i> No advisory classes
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script>
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