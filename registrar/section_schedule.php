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

// Handle delete schedule
if(isset($_GET['delete_schedule'])) {
    $schedule_id = (int)$_GET['delete_schedule'];
    
    $delete = $conn->query("DELETE FROM class_schedules WHERE id = $schedule_id");
    
    if($delete) {
        $success_message = "Schedule deleted successfully!";
    } else {
        $error_message = "Error deleting schedule: " . $conn->error;
    }
}

// Handle add schedule
if(isset($_POST['add_schedule'])) {
    $subject_id = (int)$_POST['subject_id'];
    $teacher_id = (int)$_POST['teacher_id'];
    $day_id = (int)$_POST['day_id'];
    $time_slot_id = (int)$_POST['time_slot_id'];
    $room = mysqli_real_escape_string($conn, $_POST['room']);
    $school_year = mysqli_real_escape_string($conn, $_POST['school_year']);
    $semester = mysqli_real_escape_string($conn, $_POST['semester']);
    
    // Check for conflicts within the same section
    $conflict_check = $conn->query("
        SELECT cs.*, d.day_name, ts.start_time, ts.end_time, sub.subject_name, u.fullname as teacher_name
        FROM class_schedules cs
        JOIN days_of_week d ON cs.day_id = d.id
        JOIN time_slots ts ON cs.time_slot_id = ts.id
        JOIN subjects sub ON cs.subject_id = sub.id
        JOIN users u ON cs.teacher_id = u.id
        WHERE cs.section_id = $section_id 
        AND cs.day_id = $day_id 
        AND cs.time_slot_id = $time_slot_id
    ");
    
    if($conflict_check && $conflict_check->num_rows > 0) {
        $error_message = "Schedule conflict! This time slot is already taken.";
    } else {
        // Check teacher conflict
        $teacher_conflict = $conn->query("
            SELECT cs.*, d.day_name, ts.start_time, ts.end_time, sub.subject_name, s.section_name
            FROM class_schedules cs
            JOIN days_of_week d ON cs.day_id = d.id
            JOIN time_slots ts ON cs.time_slot_id = ts.id
            JOIN subjects sub ON cs.subject_id = sub.id
            JOIN sections s ON cs.section_id = s.id
            WHERE cs.teacher_id = $teacher_id 
            AND cs.day_id = $day_id 
            AND cs.time_slot_id = $time_slot_id
        ");
        
        if($teacher_conflict && $teacher_conflict->num_rows > 0) {
            $conflict = $teacher_conflict->fetch_assoc();
            $error_message = "Teacher conflict! This teacher is already teaching {$conflict['subject_name']} for {$conflict['section_name']} at this time.";
        } else {
            // Insert schedule
            $insert = $conn->query("
                INSERT INTO class_schedules (section_id, subject_id, teacher_id, day_id, time_slot_id, room, school_year, semester, status)
                VALUES ($section_id, $subject_id, $teacher_id, $day_id, $time_slot_id, '$room', '$school_year', '$semester', 'active')
            ");
            
            if($insert) {
                $success_message = "Schedule added successfully!";
            } else {
                $error_message = "Error adding schedule: " . $conn->error;
            }
        }
    }
}

// Get subjects for this grade level - FIXED: Removed subject_code
$subjects = $conn->query("
    SELECT id, subject_name FROM subjects 
    WHERE grade_id = {$section['grade_id']} 
    ORDER BY subject_name
");

// Get teachers
$teachers = $conn->query("
    SELECT id, fullname FROM users 
    WHERE role = 'Teacher' 
    ORDER BY fullname
");

// Get days of week
$days = $conn->query("SELECT * FROM days_of_week ORDER BY day_order");

// Get time slots
$time_slots = $conn->query("SELECT * FROM time_slots ORDER BY start_time");

// Get current schedules for this section - FIXED: Removed subject_code
$schedules = $conn->query("
    SELECT cs.*, sub.subject_name, u.fullname as teacher_name,
           d.day_name, d.day_order, ts.start_time, ts.end_time, ts.slot_name
    FROM class_schedules cs
    JOIN subjects sub ON cs.subject_id = sub.id
    JOIN users u ON cs.teacher_id = u.id
    JOIN days_of_week d ON cs.day_id = d.id
    JOIN time_slots ts ON cs.time_slot_id = ts.id
    WHERE cs.section_id = $section_id AND cs.status = 'active'
    ORDER BY d.day_order, ts.start_time
");

// Organize schedule by day for weekly view
$weekly_schedule = [];
if($schedules && $schedules->num_rows > 0) {
    while($class = $schedules->fetch_assoc()) {
        $weekly_schedule[$class['day_name']][] = $class;
    }
    $schedules->data_seek(0); // Reset pointer
}

// Days order for display
$days_order = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Section Schedule - <?php echo htmlspecialchars($section['section_name']); ?></title>
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
            padding: 20px 25px;
            color: white;
            margin-bottom: 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
        }

        .section-info h2 {
            font-size: 22px;
            margin-bottom: 5px;
        }

        .section-info p {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
        }

        .section-info span {
            display: flex;
            align-items: center;
            gap: 8px;
            background: rgba(255,255,255,0.1);
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 13px;
        }

        .section-info i {
            color: var(--accent);
        }

        .schedule-stats {
            display: flex;
            gap: 20px;
        }

        .stat-item {
            text-align: center;
        }

        .stat-value {
            font-size: 24px;
            font-weight: 700;
            color: var(--accent);
        }

        .stat-label {
            font-size: 11px;
            opacity: 0.9;
        }

        /* Grid Layout */
        .grid-2 {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 25px;
            margin-bottom: 30px;
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
            font-size: 18px;
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

        /* Form Styles */
        .form-group {
            margin-bottom: 15px;
        }

        .form-group label {
            display: block;
            margin-bottom: 5px;
            color: var(--text-secondary);
            font-weight: 500;
            font-size: 13px;
        }

        .form-group input, .form-group select {
            width: 100%;
            padding: 10px 12px;
            border: 2px solid var(--border-color);
            border-radius: 8px;
            font-size: 14px;
            transition: all 0.3s;
        }

        .form-group input:focus, .form-group select:focus {
            border-color: var(--primary);
            outline: none;
        }

        .btn-primary {
            background: var(--primary);
            color: white;
            border: none;
            padding: 12px 20px;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            width: 100%;
            transition: all 0.3s;
            margin-top: 10px;
        }

        .btn-primary:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
        }

        .btn-primary i {
            font-size: 14px;
        }

        .conflict-warning {
            background: #fff3cd;
            color: #856404;
            padding: 12px;
            border-radius: 8px;
            font-size: 13px;
            margin-top: 15px;
            display: flex;
            align-items: center;
            gap: 8px;
            border-left: 4px solid #ffc107;
        }

        /* Schedule List */
        .schedule-list {
            max-height: 500px;
            overflow-y: auto;
        }

        .schedule-item {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 12px;
            border-left: 4px solid var(--primary);
            display: flex;
            justify-content: space-between;
            align-items: center;
            transition: all 0.3s;
        }

        .schedule-item:hover {
            transform: translateX(3px);
            box-shadow: 0 3px 10px rgba(0,0,0,0.1);
        }

        .schedule-info h4 {
            color: var(--primary);
            margin-bottom: 5px;
            font-size: 16px;
        }

        .schedule-info p {
            color: var(--text-secondary);
            font-size: 13px;
            display: flex;
            align-items: center;
            gap: 15px;
            flex-wrap: wrap;
            margin-top: 5px;
        }

        .schedule-info span {
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .schedule-info i {
            color: var(--primary);
            width: 16px;
        }

        .room-badge {
            background: var(--primary);
            color: white;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 500;
        }

        .btn-delete {
            color: var(--danger);
            background: rgba(220,53,69,0.1);
            border: none;
            padding: 8px 12px;
            border-radius: 6px;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            font-size: 13px;
            transition: all 0.3s;
        }

        .btn-delete:hover {
            background: var(--danger);
            color: white;
        }

        /* Weekly Schedule Table */
        .weekly-container {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            overflow-x: auto;
            margin-top: 30px;
        }

        .weekly-title {
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .weekly-title h3 {
            color: var(--text-primary);
        }

        .weekly-title i {
            color: var(--primary);
        }

        .schedule-table {
            width: 100%;
            border-collapse: collapse;
            min-width: 800px;
        }

        .schedule-table th {
            background: var(--primary-light);
            color: var(--primary);
            padding: 15px;
            text-align: left;
            font-weight: 600;
            font-size: 14px;
        }

        .schedule-table td {
            border: 1px solid var(--border-color);
            padding: 12px;
            vertical-align: top;
            height: 100px;
        }

        .time-column {
            background: #f8f9fa;
            font-weight: 600;
            color: var(--primary);
            width: 120px;
            text-align: center;
        }

        .class-cell {
            background: var(--primary-light);
            border-radius: 6px;
            padding: 8px;
            margin-bottom: 5px;
            border-left: 3px solid var(--primary);
        }

        .class-cell .subject {
            font-weight: 600;
            color: var(--text-primary);
            font-size: 13px;
            margin-bottom: 3px;
        }

        .class-cell .teacher {
            font-size: 11px;
            color: var(--text-secondary);
            display: flex;
            align-items: center;
            gap: 3px;
        }

        .class-cell .room {
            font-size: 10px;
            background: var(--primary);
            color: white;
            padding: 2px 6px;
            border-radius: 10px;
            display: inline-block;
            margin-top: 4px;
        }

        .empty-cell {
            color: var(--text-secondary);
            font-style: italic;
            text-align: center;
            padding: 10px;
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
                align-items: flex-start;
            }
            
            .schedule-stats {
                width: 100%;
                justify-content: space-around;
            }
            
            .schedule-item {
                flex-direction: column;
                align-items: flex-start;
                gap: 10px;
            }
            
            .btn-delete {
                align-self: flex-end;
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
                    <h1>Section Schedule</h1>
                    <p>Manage class schedule for <?php echo htmlspecialchars($section['section_name']); ?></p>
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
                <div class="schedule-stats">
                    <div class="stat-item">
                        <div class="stat-value"><?php echo $schedules ? $schedules->num_rows : 0; ?></div>
                        <div class="stat-label">Scheduled</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-value"><?php echo $subjects ? $subjects->num_rows : 0; ?></div>
                        <div class="stat-label">Subjects</div>
                    </div>
                </div>
            </div>

            <!-- Main Grid -->
            <div class="grid-2">
                <!-- Add Schedule Form -->
                <div class="card">
                    <div class="card-header">
                        <h3><i class="fas fa-plus-circle"></i> Add Class Schedule</h3>
                        <span class="badge">New Entry</span>
                    </div>

                    <form method="POST">
                        <div class="form-group">
                            <label><i class="fas fa-book"></i> Subject</label>
                            <select name="subject_id" required>
                                <option value="">Select Subject</option>
                                <?php if($subjects && $subjects->num_rows > 0): ?>
                                    <?php while($subject = $subjects->fetch_assoc()): ?>
                                        <option value="<?php echo $subject['id']; ?>">
                                            <?php echo htmlspecialchars($subject['subject_name']); ?>
                                        </option>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <option value="">No subjects available</option>
                                <?php endif; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label><i class="fas fa-chalkboard-teacher"></i> Teacher</label>
                            <select name="teacher_id" required>
                                <option value="">Select Teacher</option>
                                <?php if($teachers && $teachers->num_rows > 0): ?>
                                    <?php while($teacher = $teachers->fetch_assoc()): ?>
                                        <option value="<?php echo $teacher['id']; ?>">
                                            <?php echo htmlspecialchars($teacher['fullname']); ?>
                                        </option>
                                    <?php endwhile; ?>
                                <?php endif; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label><i class="fas fa-calendar-day"></i> Day</label>
                            <select name="day_id" required>
                                <option value="">Select Day</option>
                                <?php while($day = $days->fetch_assoc()): ?>
                                    <option value="<?php echo $day['id']; ?>"><?php echo $day['day_name']; ?></option>
                                <?php endwhile; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label><i class="fas fa-clock"></i> Time Slot</label>
                            <select name="time_slot_id" required>
                                <option value="">Select Time</option>
                                <?php while($slot = $time_slots->fetch_assoc()): ?>
                                    <option value="<?php echo $slot['id']; ?>">
                                        <?php echo date('h:i A', strtotime($slot['start_time'])) . ' - ' . date('h:i A', strtotime($slot['end_time'])); ?>
                                        <?php echo $slot['slot_name'] ? '(' . $slot['slot_name'] . ')' : ''; ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label><i class="fas fa-door-open"></i> Room</label>
                            <input type="text" name="room" placeholder="e.g., Room 101, Science Lab">
                        </div>

                        <div class="form-group">
                            <label><i class="fas fa-calendar"></i> School Year</label>
                            <select name="school_year">
                                <option value="<?php echo date('Y') . '-' . (date('Y')+1); ?>"><?php echo date('Y') . '-' . (date('Y')+1); ?></option>
                                <option value="<?php echo (date('Y')-1) . '-' . date('Y'); ?>"><?php echo (date('Y')-1) . '-' . date('Y'); ?></option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label><i class="fas fa-layer-group"></i> Semester</label>
                            <select name="semester">
                                <option value="">Full Year</option>
                                <option value="1st Semester">1st Semester</option>
                                <option value="2nd Semester">2nd Semester</option>
                            </select>
                        </div>

                        <button type="submit" name="add_schedule" class="btn-primary">
                            <i class="fas fa-save"></i> Add to Schedule
                        </button>
                    </form>

                    <div class="conflict-warning">
                        <i class="fas fa-info-circle"></i>
                        <span>The system will automatically check for scheduling conflicts.</span>
                    </div>
                </div>

                <!-- Current Schedule List -->
                <div class="card">
                    <div class="card-header">
                        <h3><i class="fas fa-list"></i> Current Classes</h3>
                        <span class="badge"><?php echo $schedules ? $schedules->num_rows : 0; ?> classes</span>
                    </div>

                    <div class="schedule-list">
                        <?php if($schedules && $schedules->num_rows > 0): ?>
                            <?php while($class = $schedules->fetch_assoc()): ?>
                                <div class="schedule-item">
                                    <div class="schedule-info">
                                        <h4><?php echo htmlspecialchars($class['subject_name']); ?></h4>
                                        <p>
                                            <span><i class="fas fa-user"></i> <?php echo htmlspecialchars($class['teacher_name']); ?></span>
                                            <span><i class="fas fa-calendar-day"></i> <?php echo $class['day_name']; ?></span>
                                            <span><i class="fas fa-clock"></i> <?php echo date('h:i A', strtotime($class['start_time'])); ?> - <?php echo date('h:i A', strtotime($class['end_time'])); ?></span>
                                            <?php if($class['room']): ?>
                                                <span class="room-badge"><i class="fas fa-door-open"></i> <?php echo htmlspecialchars($class['room']); ?></span>
                                            <?php endif; ?>
                                        </p>
                                    </div>
                                    <a href="?id=<?php echo $section_id; ?>&delete_schedule=<?php echo $class['id']; ?>" 
                                       class="btn-delete" 
                                       onclick="return confirm('Delete this schedule?')">
                                        <i class="fas fa-trash"></i> Delete
                                    </a>
                                </div>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <div class="no-data">
                                <i class="fas fa-calendar-times"></i>
                                <p>No classes scheduled yet.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Weekly Schedule View -->
            <div class="weekly-container">
                <div class="weekly-title">
                    <i class="fas fa-table fa-lg"></i>
                    <h3>Weekly Schedule - <?php echo htmlspecialchars($section['section_name']); ?></h3>
                </div>

                <?php if($schedules && $schedules->num_rows > 0): ?>
                    <table class="schedule-table">
                        <thead>
                            <tr>
                                <th>Time</th>
                                <?php foreach($days_order as $day): ?>
                                    <th><?php echo $day; ?></th>
                                <?php endforeach; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $time_slots->data_seek(0);
                            while($slot = $time_slots->fetch_assoc()): 
                            ?>
                                <tr>
                                    <td class="time-column">
                                        <?php echo date('h:i A', strtotime($slot['start_time'])); ?><br>
                                        <small>to</small><br>
                                        <?php echo date('h:i A', strtotime($slot['end_time'])); ?>
                                    </td>
                                    <?php foreach($days_order as $day): ?>
                                        <td>
                                            <?php 
                                            $found = false;
                                            if(isset($weekly_schedule[$day])) {
                                                foreach($weekly_schedule[$day] as $class) {
                                                    if($class['time_slot_id'] == $slot['id']) {
                                                        $found = true;
                                                        ?>
                                                        <div class="class-cell">
                                                            <div class="subject"><?php echo htmlspecialchars($class['subject_name']); ?></div>
                                                            <div class="teacher"><i class="fas fa-user"></i> <?php echo htmlspecialchars($class['teacher_name']); ?></div>
                                                            <?php if($class['room']): ?>
                                                                <div class="room"><i class="fas fa-door-open"></i> <?php echo htmlspecialchars($class['room']); ?></div>
                                                            <?php endif; ?>
                                                        </div>
                                                        <?php
                                                        break;
                                                    }
                                                }
                                            }
                                            if(!$found) {
                                                echo '<div class="empty-cell">—</div>';
                                            }
                                            ?>
                                        </td>
                                    <?php endforeach; ?>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <div class="no-data">
                        <i class="fas fa-calendar-alt" style="font-size: 48px;"></i>
                        <p>No schedule to display. Add classes using the form.</p>
                    </div>
                <?php endif; ?>
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
</body>
</html>