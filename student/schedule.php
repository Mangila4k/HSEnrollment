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

// Get student's enrollment information with section
$enrollment_query = "
    SELECT e.*, g.grade_name, s.section_name, s.id as section_id, 
           u.fullname as adviser_name
    FROM enrollments e
    JOIN grade_levels g ON e.grade_id = g.id
    LEFT JOIN sections s ON e.section_id = s.id
    LEFT JOIN users u ON s.adviser_id = u.id
    WHERE e.student_id = ? AND e.status = 'Enrolled'
    ORDER BY e.created_at DESC LIMIT 1
";
$stmt = $conn->prepare($enrollment_query);
$stmt->bind_param("i", $student_id);
$stmt->execute();
$enrollment = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Check if student has a section
$has_section = ($enrollment && isset($enrollment['section_id']) && $enrollment['section_id']);
$grade_name = $enrollment ? $enrollment['grade_name'] : 'Not Enrolled';
$section_name = $enrollment ? ($enrollment['section_name'] ?? 'Not Assigned') : 'Not Assigned';
$strand = $enrollment ? ($enrollment['strand'] ?? 'N/A') : 'N/A';
$adviser_name = $enrollment ? ($enrollment['adviser_name'] ?? 'Not Assigned') : 'Not Assigned';
$school_year = $enrollment ? $enrollment['school_year'] : date('Y') . '-' . (date('Y') + 1);

// Get class schedule for student's section - FIXED: Removed subject_code
$schedule = null;
$weekly_schedule = [];
if($has_section) {
    $schedule_query = "
        SELECT 
            cs.*,
            sub.subject_name,
            u.fullname as teacher_name,
            d.day_name,
            d.day_order,
            ts.start_time,
            ts.end_time,
            ts.id as time_slot_id
        FROM class_schedules cs
        JOIN subjects sub ON cs.subject_id = sub.id
        JOIN users u ON cs.teacher_id = u.id
        JOIN days_of_week d ON cs.day_id = d.id
        JOIN time_slots ts ON cs.time_slot_id = ts.id
        WHERE cs.section_id = ? AND cs.status = 'active'
        ORDER BY d.day_order, ts.start_time
    ";
    
    $stmt = $conn->prepare($schedule_query);
    $stmt->bind_param("i", $enrollment['section_id']);
    $stmt->execute();
    $schedule_result = $stmt->get_result();
    
    // Organize schedule by day
    while($class = $schedule_result->fetch_assoc()) {
        $day = $class['day_name'];
        $time_slot_id = $class['time_slot_id'];
        
        if(!isset($weekly_schedule[$day])) {
            $weekly_schedule[$day] = [];
        }
        
        $weekly_schedule[$day][$time_slot_id] = [
            'id' => $class['id'],
            'subject' => $class['subject_name'],
            'teacher' => $class['teacher_name'],
            'teacher_id' => $class['teacher_id'],
            'room' => $class['room'] ?? 'TBA',
            'start_time' => $class['start_time'],
            'end_time' => $class['end_time']
        ];
    }
    $stmt->close();
}

// Get subjects for student's grade level (fallback if no schedule)
$subjects_list = [];
if($grade_id = ($enrollment ? $enrollment['grade_id'] : null)) {
    $subjects_query = "
        SELECT * FROM subjects 
        WHERE grade_id = ? 
        ORDER BY subject_name
    ";
    $stmt = $conn->prepare($subjects_query);
    $stmt->bind_param("i", $grade_id);
    $stmt->execute();
    $subjects = $stmt->get_result();
    while($subject = $subjects->fetch_assoc()) {
        $subjects_list[] = $subject;
    }
    $stmt->close();
}

// Get all time slots
$time_slots = [];
$time_slots_query = "SELECT * FROM time_slots ORDER BY start_time";
$time_slots_result = $conn->query($time_slots_query);
if($time_slots_result && $time_slots_result->num_rows > 0) {
    while($slot = $time_slots_result->fetch_assoc()) {
        $time_slots[$slot['id']] = [
            'time' => date('h:i A', strtotime($slot['start_time'])) . ' - ' . date('h:i A', strtotime($slot['end_time'])),
            'start' => $slot['start_time'],
            'end' => $slot['end_time'],
            'name' => $slot['slot_name']
        ];
    }
}

// Days of the week in order
$days_order = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'];

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

// Get today's day name
$today_name = date('l');

// Calculate statistics
$total_classes = 0;
$unique_subjects = [];
$unique_teachers = [];
foreach($weekly_schedule as $day => $classes) {
    $total_classes += count($classes);
    foreach($classes as $class) {
        $unique_subjects[$class['subject']] = true;
        $unique_teachers[$class['teacher_id']] = true;
    }
}
$total_subjects = count($unique_subjects);
$total_teachers = count($unique_teachers);

// Get today's classes
$today_classes = isset($weekly_schedule[$today_name]) ? $weekly_schedule[$today_name] : [];
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

        .student-info {
            text-align: center;
            padding: 20px 0;
            border-bottom: 1px solid rgba(255, 255, 255, 0.2);
            margin-bottom: 20px;
        }

        .student-avatar {
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

        .student-info h3 {
            font-size: 18px;
            margin-bottom: 5px;
            color: var(--accent);
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

        /* Section Info Card */
        .section-card {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            border-radius: 20px;
            padding: 30px;
            color: white;
            margin-bottom: 30px;
            box-shadow: 0 10px 30px rgba(11, 79, 46, 0.3);
        }

        .section-header {
            display: flex;
            align-items: center;
            gap: 20px;
            flex-wrap: wrap;
        }

        .section-icon {
            width: 80px;
            height: 80px;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 40px;
        }

        .section-title h2 {
            font-size: 28px;
            margin-bottom: 10px;
        }

        .section-details {
            display: flex;
            gap: 20px;
            flex-wrap: wrap;
            margin-top: 15px;
        }

        .detail-item {
            background: rgba(255, 255, 255, 0.1);
            padding: 10px 20px;
            border-radius: 30px;
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 14px;
        }

        .detail-item i {
            color: var(--accent);
        }

        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 25px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            border-radius: 15px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
        }

        .stat-icon {
            width: 50px;
            height: 50px;
            background: var(--primary-light);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--primary);
            font-size: 24px;
        }

        .stat-content h3 {
            font-size: 14px;
            color: var(--text-secondary);
            margin-bottom: 5px;
        }

        .stat-number {
            font-size: 24px;
            font-weight: 700;
            color: var(--text-primary);
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

        .week-range {
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
            cursor: pointer;
        }

        .nav-btn:hover {
            border-color: var(--primary);
            color: var(--primary);
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
            background: var(--primary-light);
            border-radius: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--primary);
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
            color: var(--primary);
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
            color: var(--primary);
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
            cursor: pointer;
        }

        .view-btn.active {
            background: var(--primary);
            color: white;
            border-color: var(--primary);
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
            color: var(--primary);
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
            background: var(--primary-light);
            border-left: 4px solid var(--primary);
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
            color: var(--primary);
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
            color: var(--accent);
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

        .today-highlight {
            background: rgba(255, 215, 0, 0.05);
        }

        .today-badge {
            background: var(--accent);
            color: var(--primary);
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            margin-left: 15px;
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
            background: var(--primary-light);
            border-left: 4px solid var(--primary);
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
            color: var(--primary);
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
            color: var(--primary);
        }

        .no-data {
            text-align: center;
            padding: 60px;
            background: white;
            border-radius: 20px;
            color: var(--text-secondary);
            margin-bottom: 30px;
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
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
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
            
            .section-header {
                flex-direction: column;
                text-align: center;
            }
            
            .section-details {
                justify-content: center;
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
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

            <?php if(!$enrollment): ?>
                <!-- No Enrollment Message -->
                <div class="no-data">
                    <i class="fas fa-graduation-cap"></i>
                    <h3>No Enrollment Found</h3>
                    <p>You are not currently enrolled. Please contact the registrar's office.</p>
                </div>
            <?php elseif(!$has_section): ?>
                <!-- No Section Assigned -->
                <div class="no-data">
                    <i class="fas fa-layer-group"></i>
                    <h3>No Section Assigned</h3>
                    <p>You are enrolled but not yet assigned to a section. Please contact the registrar's office.</p>
                    <div style="margin-top: 20px; background: var(--primary-light); padding: 15px; border-radius: 10px; display: inline-block;">
                        <strong>Grade Level:</strong> <?php echo htmlspecialchars($grade_name); ?>
                    </div>
                </div>
            <?php else: ?>
                <!-- Section Information Card -->
                <div class="section-card">
                    <div class="section-header">
                        <div class="section-icon">
                            <i class="fas fa-users"></i>
                        </div>
                        <div class="section-title">
                            <h2><?php echo htmlspecialchars($section_name); ?></h2>
                            <p><?php echo htmlspecialchars($grade_name); ?></p>
                        </div>
                    </div>
                    <div class="section-details">
                        <span class="detail-item">
                            <i class="fas fa-user-tie"></i> Adviser: <?php echo htmlspecialchars($adviser_name); ?>
                        </span>
                        <span class="detail-item">
                            <i class="fas fa-calendar"></i> School Year: <?php echo htmlspecialchars($school_year); ?>
                        </span>
                        <?php if($strand != 'N/A'): ?>
                        <span class="detail-item">
                            <i class="fas fa-tag"></i> Strand: <?php echo htmlspecialchars($strand); ?>
                        </span>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Statistics -->
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-icon"><i class="fas fa-book"></i></div>
                        <div class="stat-content">
                            <h3>Total Subjects</h3>
                            <div class="stat-number"><?php echo $total_subjects; ?></div>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon"><i class="fas fa-clock"></i></div>
                        <div class="stat-content">
                            <h3>Class Hours/Week</h3>
                            <div class="stat-number"><?php echo $total_classes; ?></div>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon"><i class="fas fa-chalkboard-teacher"></i></div>
                        <div class="stat-content">
                            <h3>Teachers</h3>
                            <div class="stat-number"><?php echo $total_teachers; ?></div>
                        </div>
                    </div>
                </div>

                <!-- Week Navigation -->
                <div class="week-nav">
                    <div class="week-display">
                        <h3><i class="fas fa-calendar-week"></i> This Week's Schedule</h3>
                        <span class="week-range">
                            <?php echo $week_dates['Monday']; ?> - <?php echo $week_dates['Friday']; ?>
                        </span>
                    </div>
                    <span class="today-badge">
                        <i class="fas fa-sun"></i> Today is <?php echo $today_name; ?>
                    </span>
                </div>

                <!-- Today's Classes -->
                <div class="today-card">
                    <div class="today-icon">
                        <i class="fas fa-calendar-day"></i>
                    </div>
                    <div class="today-info">
                        <h4>Today's Classes (<?php echo $today_name; ?>)</h4>
                        <div class="today-classes">
                            <?php 
                            if(!empty($today_classes)) {
                                foreach($today_classes as $slot_id => $class) {
                                    echo '<span class="today-class-item">';
                                    echo '<i class="fas fa-book-open"></i> ' . htmlspecialchars($class['subject']);
                                    echo '<span class="time">' . (isset($time_slots[$slot_id]) ? $time_slots[$slot_id]['time'] : '') . '</span>';
                                    echo '</span>';
                                }
                            } else {
                                echo '<span class="today-class-item">No classes today</span>';
                            }
                            ?>
                        </div>
                    </div>
                </div>

                <!-- Schedule Table -->
                <div class="schedule-container">
                    <h3 style="margin-bottom: 20px; display: flex; align-items: center; gap: 10px;">
                        <i class="fas fa-table" style="color: var(--primary);"></i> 
                        Class Schedule - <?php echo htmlspecialchars($section_name); ?>
                    </h3>

                    <?php if(empty($weekly_schedule)): ?>
                        <div class="no-data">
                            <i class="fas fa-calendar-times"></i>
                            <h3>No Schedule Yet</h3>
                            <p>No classes have been scheduled for your section.</p>
                        </div>
                    <?php else: ?>
                        <table class="schedule-table">
                            <thead>
                                <tr>
                                    <th>Time</th>
                                    <?php foreach($days_order as $day): ?>
                                        <th class="<?php echo ($day == $today_name) ? 'today-highlight' : ''; ?>">
                                            <?php echo $day; ?><br>
                                            <small><?php echo $week_dates[$day]; ?></small>
                                        </th>
                                    <?php endforeach; ?>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($time_slots as $slot_id => $slot): ?>
                                    <tr>
                                        <td class="time-column">
                                            <?php echo $slot['time']; ?>
                                            <?php if($slot['name']): ?>
                                                <br><small><?php echo $slot['name']; ?></small>
                                            <?php endif; ?>
                                        </td>
                                        <?php foreach($days_order as $day): ?>
                                            <td class="<?php echo ($day == $today_name) ? 'today-highlight' : ''; ?>">
                                                <?php if(isset($weekly_schedule[$day][$slot_id])): ?>
                                                    <?php $class = $weekly_schedule[$day][$slot_id]; ?>
                                                    <div class="class-item">
                                                        <div class="subject-name"><?php echo htmlspecialchars($class['subject']); ?></div>
                                                        <div class="teacher-name">
                                                            <i class="fas fa-user"></i> <?php echo htmlspecialchars($class['teacher']); ?>
                                                        </div>
                                                        <span class="room">
                                                            <i class="fas fa-door-open"></i> <?php echo htmlspecialchars($class['room']); ?>
                                                        </span>
                                                    </div>
                                                <?php else: ?>
                                                    <div class="empty-cell">—</div>
                                                <?php endif; ?>
                                            </td>
                                        <?php endforeach; ?>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
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
            });
        });
    </script>
    <?php include('../includes/chatbot_widget.php'); ?>
</body>
</html>