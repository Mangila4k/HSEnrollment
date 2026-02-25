<?php
session_start();
include("../config/database.php");

// Check if user is student
if(!isset($_SESSION['user']) || $_SESSION['user']['role'] != 'Student'){
    header("Location: ../auth/login.php");
    exit();
}

$student_id = $_SESSION['user']['id'];
$student_name = $_SESSION['user']['fullname'];
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

// Get student's enrollment information
$enrollment_query = "
    SELECT e.*, g.grade_name, s.section_name
    FROM enrollments e
    JOIN grade_levels g ON e.grade_id = g.id
    LEFT JOIN sections s ON e.grade_id = s.grade_id
    WHERE e.student_id = ? AND e.status = 'Enrolled'
    ORDER BY e.created_at DESC LIMIT 1
";
$stmt = $conn->prepare($enrollment_query);
$stmt->bind_param("i", $student_id);
$stmt->execute();
$enrollment = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Get student's section (if assigned)
$section_id = $enrollment ? ($enrollment['section_id'] ?? null) : null;
$grade_id = $enrollment ? $enrollment['grade_id'] : null;
$grade_name = $enrollment ? $enrollment['grade_name'] : 'Not Enrolled';
$section_name = $enrollment ? ($enrollment['section_name'] ?? 'Not Assigned') : 'Not Assigned';
$strand = $enrollment ? ($enrollment['strand'] ?? 'N/A') : 'N/A';

// Get subjects for student's grade level
$subjects_query = "
    SELECT * FROM subjects 
    WHERE grade_id = ? 
    ORDER BY subject_name
";
$stmt = $conn->prepare($subjects_query);
$stmt->bind_param("i", $grade_id);
$stmt->execute();
$subjects = $stmt->get_result();
$subjects_list = [];
while($subject = $subjects->fetch_assoc()) {
    $subjects_list[] = $subject;
}
$stmt->close();

// Days of the week
$days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'];

// Time slots
$time_slots = [
    '1' => '7:30 AM - 8:30 AM',
    '2' => '8:30 AM - 9:30 AM',
    '3' => '9:45 AM - 10:45 AM',
    '4' => '10:45 AM - 11:45 AM',
    '5' => '12:45 PM - 1:45 PM',
    '6' => '1:45 PM - 2:45 PM',
    '7' => '3:00 PM - 4:00 PM'
];

// Generate sample schedule based on grade level and subjects
$schedule = [];
if($grade_id && !empty($subjects_list)) {
    $subject_count = count($subjects_list);
    foreach($days as $day) {
        $schedule[$day] = [];
        foreach(array_keys($time_slots) as $slot) {
            // Distribute subjects across the week
            $subject_index = (array_search($day, $days) * 7 + $slot) % max(1, $subject_count);
            if($subject_index < $subject_count) {
                $schedule[$day][$slot] = [
                    'subject' => $subjects_list[$subject_index]['subject_name'],
                    'teacher' => 'Teacher ' . chr(65 + $subject_index), // Placeholder teacher names
                    'room' => 'Room ' . (101 + $slot)
                ];
            } else {
                $schedule[$day][$slot] = null;
            }
        }
    }
}

// Get current week dates
$today = new DateTime();
$start_of_week = clone $today;
$start_of_week->modify('monday this week');
$week_dates = [];
for($i = 0; $i < 5; $i++) {
    $date = clone $start_of_week;
    $date->modify("+$i days");
    $week_dates[$days[$i]] = $date->format('M d, Y');
}

// Calculate class statistics
$total_classes = 0;
$classes_per_day = [];
foreach($days as $day) {
    $count = 0;
    if(isset($schedule[$day])) {
        foreach($schedule[$day] as $slot) {
            if($slot) $count++;
        }
    }
    $classes_per_day[$day] = $count;
    $total_classes += $count;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Schedule - Student Dashboard</title>
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

        .student-info {
            text-align: center;
            padding: 20px 0;
            border-bottom: 1px solid rgba(255, 255, 255, 0.2);
            margin-bottom: 20px;
        }

        .student-avatar {
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

        .student-info h3 {
            font-size: 18px;
            margin-bottom: 5px;
            color: #FFD700;
        }

        .student-info p {
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

        /* Class Info Card */
        .class-info-card {
            background: linear-gradient(135deg, #0B4F2E, #1a7a42);
            border-radius: 20px;
            padding: 25px;
            color: white;
            margin-bottom: 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 20px;
        }

        .class-info-details {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .class-info-details h3 {
            font-size: 20px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .class-info-details h3 i {
            color: #FFD700;
        }

        .class-info-badges {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
        }

        .info-badge {
            background: rgba(255, 255, 255, 0.2);
            padding: 8px 16px;
            border-radius: 30px;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .info-badge i {
            color: #FFD700;
        }

        .class-stats {
            display: flex;
            gap: 30px;
        }

        .stat {
            text-align: center;
        }

        .stat .number {
            font-size: 32px;
            font-weight: 700;
            color: #FFD700;
        }

        .stat .label {
            font-size: 12px;
            opacity: 0.9;
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

        /* Today's Classes Card */
        .today-card {
            background: white;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 30px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            display: flex;
            align-items: center;
            gap: 20px;
            flex-wrap: wrap;
        }

        .today-icon {
            width: 60px;
            height: 60px;
            background: rgba(11, 79, 46, 0.1);
            border-radius: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #0B4F2E;
            font-size: 30px;
        }

        .today-info {
            flex: 1;
        }

        .today-info h4 {
            color: var(--text-primary);
            font-size: 16px;
            margin-bottom: 5px;
        }

        .today-classes {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
        }

        .today-class-item {
            background: #f8f9fa;
            padding: 8px 15px;
            border-radius: 30px;
            font-size: 13px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .today-class-item i {
            color: #0B4F2E;
        }

        .today-class-item .time {
            color: var(--text-secondary);
            font-size: 11px;
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

        .view-options {
            display: flex;
            gap: 10px;
        }

        .view-btn {
            padding: 8px 16px;
            border-radius: 8px;
            text-decoration: none;
            font-size: 13px;
            font-weight: 500;
            transition: all 0.3s;
            border: 2px solid var(--border-color);
            background: white;
            color: var(--text-secondary);
        }

        .view-btn.active {
            background: #0B4F2E;
            color: white;
            border-color: #0B4F2E;
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
            height: 120px;
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
            border-left: 4px solid #0B4F2E;
            padding: 10px;
            border-radius: 8px;
            font-size: 12px;
            transition: all 0.3s;
            height: 100%;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        .class-item:hover {
            background: rgba(11, 79, 46, 0.15);
            transform: translateX(2px);
        }

        .class-item .subject-name {
            font-weight: 700;
            color: #0B4F2E;
            margin-bottom: 5px;
            font-size: 13px;
        }

        .class-item .teacher-name {
            color: var(--text-secondary);
            font-size: 11px;
            display: flex;
            align-items: center;
            gap: 3px;
            margin-bottom: 3px;
        }

        .class-item .room {
            color: var(--text-secondary);
            font-size: 11px;
            display: flex;
            align-items: center;
            gap: 3px;
        }

        .class-item i {
            color: #FFD700;
            font-size: 10px;
            width: 14px;
        }

        .empty-cell {
            color: var(--text-secondary);
            font-size: 12px;
            text-align: center;
            padding: 15px;
            font-style: italic;
            background: #f8f9fa;
            border-radius: 6px;
            height: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
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
            background: rgba(11, 79, 46, 0.1);
            border-left: 4px solid #0B4F2E;
            border-radius: 4px;
        }

        /* Subjects List */
        .subjects-card {
            background: white;
            border-radius: 15px;
            padding: 20px;
            margin-top: 30px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
        }

        .subjects-card h4 {
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

        .subjects-card h4 i {
            color: #0B4F2E;
        }

        .subject-tags {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
        }

        .subject-tag {
            background: #f8f9fa;
            padding: 8px 16px;
            border-radius: 30px;
            font-size: 13px;
            display: flex;
            align-items: center;
            gap: 8px;
            border: 1px solid var(--border-color);
        }

        .subject-tag i {
            color: #0B4F2E;
        }

        .no-enrollment {
            text-align: center;
            padding: 60px;
            background: white;
            border-radius: 20px;
            color: var(--text-secondary);
        }

        .no-enrollment i {
            font-size: 60px;
            margin-bottom: 20px;
            opacity: 0.3;
        }

        .no-enrollment h3 {
            color: var(--text-primary);
            margin-bottom: 10px;
        }

        /* Responsive */
        @media (max-width: 1200px) {
            .class-info-card {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .class-stats {
                width: 100%;
                justify-content: space-around;
            }
        }

        @media (max-width: 768px) {
            .sidebar {
                width: 80px;
                padding: 20px 10px;
            }
            
            .sidebar h2 span,
            .student-info h3,
            .student-info p,
            .menu-section h3,
            .menu-items a span {
                display: none;
            }
            
            .student-avatar {
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
            
            .class-info-badges {
                flex-direction: column;
            }
            
            .class-stats {
                flex-direction: column;
                gap: 15px;
            }
            
            .week-nav {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .today-card {
                flex-direction: column;
                text-align: center;
            }
            
            .today-classes {
                justify-content: center;
            }
            
            .schedule-table td {
                height: 100px;
            }
            
            .class-item {
                font-size: 10px;
                padding: 6px;
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
            
            <div class="student-info">
                <div class="student-avatar">
                    <?php echo strtoupper(substr($student_name, 0, 1)); ?>
                </div>
                <h3><?php echo htmlspecialchars(explode(' ', $student_name)[0]); ?></h3>
                <p><i class="fas fa-user-graduate"></i> Student</p>
            </div>
            
            <div class="menu-section">
                <h3>MAIN MENU</h3>
                <ul class="menu-items">
                    <li><a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> <span>Dashboard</span></a></li>
                    <li><a href="profile.php"><i class="fas fa-user"></i> <span>My Profile</span></a></li>
                    <li><a href="schedule.php" class="active"><i class="fas fa-calendar-alt"></i> <span>Class Schedule</span></a></li>
                    <li><a href="grades.php"><i class="fas fa-star"></i> <span>My Grades</span></a></li>
                    <li><a href="attendance.php"><i class="fas fa-calendar-check"></i> <span>Attendance</span></a></li>
                </ul>
            </div>

            <div class="menu-section">
                <h3>ACCOUNT</h3>
                <ul class="menu-items">
                    <li><a href="settings.php"><i class="fas fa-cog"></i> <span>Settings</span></a></li>
                    <li><a href="../auth/logout.php"><i class="fas fa-sign-out-alt"></i> <span>Logout</span></a></li>
                </ul>
            </div>
        </div>

        <!-- Main Content -->
        <div class="main-content">
            <!-- Header -->
            <div class="dashboard-header">
                <h1>My Class Schedule</h1>
                <p>View your weekly class schedule and subjects</p>
            </div>

            <!-- Welcome Card -->
            <div class="welcome-card">
                <div class="welcome-text">
                    <h2>Welcome back, <?php echo htmlspecialchars(explode(' ', $student_name)[0]); ?>! 👋</h2>
                    <p><i class="fas fa-calendar"></i> <?php echo date('l, F j, Y'); ?></p>
                </div>
                <a href="../auth/logout.php" class="logout-btn">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a>
            </div>

            <?php if($enrollment): ?>
                <!-- Class Info Card -->
                <div class="class-info-card">
                    <div class="class-info-details">
                        <h3>
                            <i class="fas fa-graduation-cap"></i>
                            <?php echo htmlspecialchars($grade_name); ?>
                        </h3>
                        <div class="class-info-badges">
                            <span class="info-badge">
                                <i class="fas fa-layer-group"></i> Section: <?php echo htmlspecialchars($section_name); ?>
                            </span>
                            <?php if($strand != 'N/A'): ?>
                                <span class="info-badge">
                                    <i class="fas fa-tag"></i> Strand: <?php echo htmlspecialchars($strand); ?>
                                </span>
                            <?php endif; ?>
                            <span class="info-badge">
                                <i class="fas fa-book"></i> Subjects: <?php echo count($subjects_list); ?>
                            </span>
                        </div>
                    </div>
                    <div class="class-stats">
                        <div class="stat">
                            <div class="number"><?php echo $total_classes; ?></div>
                            <div class="label">Weekly Classes</div>
                        </div>
                        <div class="stat">
                            <div class="number"><?php echo count($subjects_list); ?></div>
                            <div class="label">Subjects</div>
                        </div>
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

                <!-- Today's Classes -->
                <div class="today-card">
                    <div class="today-icon">
                        <i class="fas fa-calendar-day"></i>
                    </div>
                    <div class="today-info">
                        <h4>Today's Classes (<?php echo date('l'); ?>)</h4>
                        <div class="today-classes">
                            <?php 
                            $today = date('l');
                            $today_classes = 0;
                            if(isset($schedule[$today])) {
                                foreach($schedule[$today] as $slot_id => $class) {
                                    if($class) {
                                        $today_classes++;
                                        echo '<span class="today-class-item">';
                                        echo '<i class="fas fa-book-open"></i> ' . htmlspecialchars($class['subject']);
                                        echo '<span class="time">' . $time_slots[$slot_id] . '</span>';
                                        echo '</span>';
                                    }
                                }
                            }
                            if($today_classes == 0) {
                                echo '<span class="today-class-item">No classes today</span>';
                            }
                            ?>
                        </div>
                    </div>
                </div>

                <!-- Schedule Container -->
                <div class="schedule-container">
                    <div class="schedule-header">
                        <h3><i class="fas fa-table"></i> Weekly Schedule</h3>
                        <div class="view-options">
                            <a href="#" class="view-btn active">Week</a>
                            <a href="#" class="view-btn">List</a>
                        </div>
                    </div>

                    <!-- Schedule Table -->
                    <table class="schedule-table">
                        <thead>
                            <tr>
                                <th>Time</th>
                                <?php foreach($days as $day): ?>
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
                            <?php foreach($time_slots as $slot_id => $time): ?>
                                <tr>
                                    <td class="time-column"><?php echo $time; ?></td>
                                    <?php foreach($days as $day): ?>
                                        <td>
                                            <div class="schedule-cell">
                                                <?php if(isset($schedule[$day][$slot_id]) && $schedule[$day][$slot_id]): ?>
                                                    <?php $class = $schedule[$day][$slot_id]; ?>
                                                    <div class="class-item">
                                                        <div class="subject-name">
                                                            <i class="fas fa-book"></i>
                                                            <?php echo htmlspecialchars($class['subject']); ?>
                                                        </div>
                                                        <div class="teacher-name">
                                                            <i class="fas fa-chalkboard-teacher"></i>
                                                            <?php echo htmlspecialchars($class['teacher']); ?>
                                                        </div>
                                                        <div class="room">
                                                            <i class="fas fa-door-open"></i>
                                                            <?php echo htmlspecialchars($class['room']); ?>
                                                        </div>
                                                    </div>
                                                <?php else: ?>
                                                    <div class="empty-cell">
                                                        <i class="fas fa-minus-circle"></i> No class
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    <?php endforeach; ?>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>

                    <!-- Legend -->
                    <div class="legend">
                        <div class="legend-item">
                            <div class="legend-color"></div>
                            <span>Regular Class</span>
                        </div>
                    </div>
                </div>

                <!-- My Subjects -->
                <div class="subjects-card">
                    <h4><i class="fas fa-book-open"></i> My Subjects (<?php echo count($subjects_list); ?>)</h4>
                    <div class="subject-tags">
                        <?php foreach($subjects_list as $subject): ?>
                            <span class="subject-tag">
                                <i class="fas fa-book"></i>
                                <?php echo htmlspecialchars($subject['subject_name']); ?>
                            </span>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php else: ?>
                <!-- No Enrollment Message -->
                <div class="no-enrollment">
                    <i class="fas fa-calendar-times"></i>
                    <h3>No Enrollment Found</h3>
                    <p>You are not currently enrolled in any grade level. Please contact the registrar's office.</p>
                </div>
            <?php endif; ?>
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

        // View options
        const viewBtns = document.querySelectorAll('.view-btn');
        viewBtns.forEach(btn => {
            btn.addEventListener('click', function(e) {
                e.preventDefault();
                viewBtns.forEach(b => b.classList.remove('active'));
                this.classList.add('active');
                
                // Here you would change the view (week/list)
                // For now, just UI feedback
            });
        });
    </script>
</body>
</html>