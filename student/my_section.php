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

// Get class schedule for student's section
$schedule = null;
$weekly_schedule = [];
if($has_section) {
    $schedule_query = "
        SELECT 
            cs.*,
            sub.subject_name,
            sub.subject_code,
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
            'subject_code' => $class['subject_code'],
            'teacher' => $class['teacher_name'],
            'teacher_id' => $class['teacher_id'],
            'room' => $class['room'] ?? 'TBA',
            'start_time' => $class['start_time'],
            'end_time' => $class['end_time']
        ];
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
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Section & Schedule - Student Dashboard</title>
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

        /* Schedule Table */
        .schedule-container {
            background: white;
            border-radius: 20px;
            padding: 25px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            overflow-x: auto;
        }

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
            border-bottom: 2px solid var(--border-color);
        }

        .schedule-table td {
            padding: 12px;
            border: 1px solid var(--border-color);
            vertical-align: top;
            height: 120px;
        }

        .time-column {
            background: #f8f9fa;
            font-weight: 600;
            color: var(--primary);
            text-align: center;
            vertical-align: middle;
            width: 120px;
        }

        .class-item {
            background: var(--primary-light);
            border-left: 4px solid var(--primary);
            padding: 10px;
            border-radius: 8px;
            font-size: 12px;
            height: 100%;
        }

        .class-item .subject-code {
            font-weight: 700;
            color: var(--primary);
            margin-bottom: 4px;
        }

        .class-item .subject-name {
            font-weight: 600;
            margin-bottom: 4px;
        }

        .class-item .teacher {
            color: var(--text-secondary);
            font-size: 11px;
            display: flex;
            align-items: center;
            gap: 4px;
            margin-bottom: 4px;
        }

        .class-item .room {
            display: inline-block;
            background: var(--primary);
            color: white;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 10px;
        }

        .empty-cell {
            color: var(--text-secondary);
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
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 10px;
            font-weight: 600;
            margin-left: 8px;
        }

        .no-data {
            text-align: center;
            padding: 60px;
            background: white;
            border-radius: 20px;
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

        @media (max-width: 768px) {
            .sidebar {
                width: 80px;
            }
            
            .sidebar h2 span,
            .student-info h3,
            .student-info p,
            .menu-section h3,
            .menu-items a span {
                display: none;
            }
            
            .main-content {
                margin-left: 80px;
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="app-container">
        <!-- Sidebar -->
        <div class="sidebar">
            <h2><i class="fas fa-check-circle"></i><span>PNHS</span></h2>
            <div class="student-info">
                <div class="student-avatar"><?php echo strtoupper(substr($student_name, 0, 1)); ?></div>
                <h3><?php echo htmlspecialchars(explode(' ', $student_name)[0]); ?></h3>
                <p><i class="fas fa-user-graduate"></i> Student</p>
            </div>
            <div class="menu-section">
                <h3>MAIN MENU</h3>
                <ul class="menu-items">
                    <li><a href="dashboard.php"><i class="fas fa-tachometer-alt"></i><span>Dashboard</span></a></li>
                    <li><a href="profile.php"><i class="fas fa-user"></i><span>My Profile</span></a></li>
                    <li><a href="my_section.php" class="active"><i class="fas fa-layer-group"></i><span>My Section</span></a></li>
                    <li><a href="grades.php"><i class="fas fa-star"></i><span>My Grades</span></a></li>
                    <li><a href="attendance.php"><i class="fas fa-calendar-check"></i><span>Attendance</span></a></li>
                </ul>
            </div>
            <div class="menu-section">
                <h3>ACCOUNT</h3>
                <ul class="menu-items">
                    <li><a href="settings.php"><i class="fas fa-cog"></i><span>Settings</span></a></li>
                    <li><a href="../auth/logout.php"><i class="fas fa-sign-out-alt"></i><span>Logout</span></a></li>
                </ul>
            </div>
        </div>

        <!-- Main Content -->
        <div class="main-content">
            <div class="dashboard-header">
                <h1>My Section & Schedule</h1>
                <p>View your assigned section and class schedule</p>
            </div>

            <div class="welcome-card">
                <div class="welcome-text">
                    <h2>Welcome back, <?php echo htmlspecialchars(explode(' ', $student_name)[0]); ?>! 👋</h2>
                    <p><i class="fas fa-calendar"></i> <?php echo date('l, F j, Y'); ?></p>
                </div>
                <a href="../auth/logout.php" class="logout-btn"><i class="fas fa-sign-out-alt"></i> Logout</a>
            </div>

            <?php if(!$enrollment): ?>
                <div class="no-data">
                    <i class="fas fa-graduation-cap"></i>
                    <h3>No Enrollment Found</h3>
                    <p>You are not currently enrolled. Please contact the registrar's office.</p>
                </div>
            <?php elseif(!$has_section): ?>
                <div class="no-data">
                    <i class="fas fa-layer-group"></i>
                    <h3>No Section Assigned</h3>
                    <p>You are enrolled but not yet assigned to a section. Please contact the registrar's office.</p>
                    <div style="margin-top: 20px; background: var(--primary-light); padding: 15px; border-radius: 10px; display: inline-block;">
                        <strong>Grade Level:</strong> <?php echo htmlspecialchars($enrollment['grade_name']); ?>
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
                            <h2><?php echo htmlspecialchars($enrollment['section_name']); ?></h2>
                            <p><?php echo htmlspecialchars($enrollment['grade_name']); ?></p>
                        </div>
                    </div>
                    <div class="section-details">
                        <span class="detail-item">
                            <i class="fas fa-user-tie"></i> Adviser: <?php echo htmlspecialchars($enrollment['adviser_name'] ?? 'Not Assigned'); ?>
                        </span>
                        <span class="detail-item">
                            <i class="fas fa-calendar"></i> School Year: <?php echo htmlspecialchars($enrollment['school_year']); ?>
                        </span>
                        <?php if($enrollment['strand'] != 'N/A'): ?>
                        <span class="detail-item">
                            <i class="fas fa-tag"></i> Strand: <?php echo htmlspecialchars($enrollment['strand']); ?>
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
                            <div class="stat-number"><?php echo count($weekly_schedule); ?></div>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon"><i class="fas fa-clock"></i></div>
                        <div class="stat-content">
                            <h3>Class Hours/Week</h3>
                            <div class="stat-number"><?php 
                                $total = 0;
                                foreach($weekly_schedule as $day => $classes) {
                                    $total += count($classes);
                                }
                                echo $total;
                            ?></div>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon"><i class="fas fa-chalkboard-teacher"></i></div>
                        <div class="stat-content">
                            <h3>Teachers</h3>
                            <div class="stat-number"><?php 
                                $teachers = [];
                                foreach($weekly_schedule as $day => $classes) {
                                    foreach($classes as $class) {
                                        $teachers[$class['teacher_id']] = true;
                                    }
                                }
                                echo count($teachers);
                            ?></div>
                        </div>
                    </div>
                </div>

                <!-- Week Navigation -->
                <div class="week-nav">
                    <div class="week-display">
                        <h3><i class="fas fa-calendar-week"></i> This Week's Schedule</h3>
                        <span class="week-range"><?php echo $week_dates['Monday']; ?> - <?php echo $week_dates['Friday']; ?></span>
                    </div>
                </div>

                <!-- Schedule Table -->
                <div class="schedule-container">
                    <h3 style="margin-bottom: 20px; display: flex; align-items: center; gap: 10px;">
                        <i class="fas fa-table" style="color: var(--primary);"></i> 
                        Class Schedule - <?php echo htmlspecialchars($enrollment['section_name']); ?>
                        <?php if($today_name): ?>
                            <span class="today-badge">Today is <?php echo $today_name; ?></span>
                        <?php endif; ?>
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
                                        <th>
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
                                                        <div class="subject-code"><?php echo htmlspecialchars($class['subject_code']); ?></div>
                                                        <div class="subject-name"><?php echo htmlspecialchars($class['subject']); ?></div>
                                                        <div class="teacher">
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
            <?php endif; ?>
        </div>
    </div>

    <?php include('../includes/chatbot_widget.php'); ?>
</body>
</html>