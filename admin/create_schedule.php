<?php
session_start();
include("../config/database.php");

// Check if user is admin
if(!isset($_SESSION['user']) || $_SESSION['user']['role'] != 'Admin'){
    header("Location: ../auth/login.php");
    exit();
}

$admin_name = $_SESSION['user']['fullname'];
$section_id = $_GET['section_id'] ?? 0;
$success_message = '';
$error_message = '';

// Get section details
$section = $conn->query("
    SELECT s.*, g.grade_name, u.fullname as adviser_name 
    FROM sections s 
    LEFT JOIN grade_levels g ON s.grade_id = g.id 
    LEFT JOIN users u ON s.adviser_id = u.id
    WHERE s.id = '$section_id'
")->fetch_assoc();

if(!$section) {
    header("Location: sections.php");
    exit();
}

// Handle delete schedule
if(isset($_GET['delete_schedule'])) {
    $schedule_id = $_GET['delete_schedule'];
    $delete = $conn->query("DELETE FROM class_schedules WHERE id = '$schedule_id'");
    if($delete) {
        $success_message = "Schedule deleted successfully!";
    } else {
        $error_message = "Error deleting schedule.";
    }
}

// Handle form submission for adding schedule
if(isset($_POST['add_schedule'])) {
    $subject_id = $_POST['subject_id'];
    $teacher_id = $_POST['teacher_id'];
    $day_id = $_POST['day_id'];
    $time_slot_id = $_POST['time_slot_id'];
    $room = $_POST['room'];
    $school_year = $_POST['school_year'];
    $semester = $_POST['semester'];

    // Check for teacher conflict
    $teacher_conflict = $conn->query("
        SELECT cs.*, d.day_name, ts.start_time, ts.end_time, sub.subject_name, s.section_name
        FROM class_schedules cs
        JOIN days_of_week d ON cs.day_id = d.id
        JOIN time_slots ts ON cs.time_slot_id = ts.id
        JOIN subjects sub ON cs.subject_id = sub.id
        JOIN sections s ON cs.section_id = s.id
        WHERE cs.teacher_id = '$teacher_id' 
        AND cs.day_id = '$day_id' 
        AND cs.time_slot_id = '$time_slot_id'
        AND cs.school_year = '$school_year'
    ");

    if($teacher_conflict->num_rows > 0) {
        $conflict = $teacher_conflict->fetch_assoc();
        $error_message = "Teacher conflict! This teacher is already teaching {$conflict['subject_name']} for {$conflict['section_name']} on {$conflict['day_name']} at " . date('h:i A', strtotime($conflict['start_time']));
    } else {
        // Check for room conflict
        $room_conflict = $conn->query("
            SELECT cs.*, d.day_name, ts.start_time, ts.end_time, sub.subject_name, s.section_name
            FROM class_schedules cs
            JOIN days_of_week d ON cs.day_id = d.id
            JOIN time_slots ts ON cs.time_slot_id = ts.id
            JOIN subjects sub ON cs.subject_id = sub.id
            JOIN sections s ON cs.section_id = s.id
            WHERE cs.room = '$room' 
            AND cs.day_id = '$day_id' 
            AND cs.time_slot_id = '$time_slot_id'
            AND cs.school_year = '$school_year'
            AND cs.room IS NOT NULL 
            AND cs.room != ''
        ");

        if($room_conflict->num_rows > 0) {
            $conflict = $room_conflict->fetch_assoc();
            $error_message = "Room conflict! Room $room is already used for {$conflict['subject_name']} on {$conflict['day_name']} at " . date('h:i A', strtotime($conflict['start_time']));
        } else {
            // No conflicts, insert schedule
            $insert = $conn->query("
                INSERT INTO class_schedules (section_id, subject_id, teacher_id, day_id, time_slot_id, room, school_year, semester)
                VALUES ('$section_id', '$subject_id', '$teacher_id', '$day_id', '$time_slot_id', '$room', '$school_year', '$semester')
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
    SELECT id, subject_name 
    FROM subjects 
    WHERE grade_id = '{$section['grade_id']}' 
    ORDER BY subject_name
");

// Get teachers
$teachers = $conn->query("
    SELECT id, fullname 
    FROM users 
    WHERE role = 'Teacher' 
    ORDER BY fullname
");

// Get days of week
$days = $conn->query("SELECT * FROM days_of_week ORDER BY day_order");

// Get time slots
$time_slots = $conn->query("SELECT * FROM time_slots ORDER BY start_time");

// Get current schedules for this section with all details - FIXED: Removed subject_code
$schedules = $conn->query("
    SELECT cs.*, sub.subject_name, u.fullname as teacher_name,
           d.day_name, d.day_order, ts.start_time, ts.end_time, ts.slot_name
    FROM class_schedules cs
    JOIN subjects sub ON cs.subject_id = sub.id
    JOIN users u ON cs.teacher_id = u.id
    JOIN days_of_week d ON cs.day_id = d.id
    JOIN time_slots ts ON cs.time_slot_id = ts.id
    WHERE cs.section_id = '$section_id'
    ORDER BY d.day_order, ts.start_time
");

// Organize schedules by day for weekly view
$weekly_schedule = [];
if($schedules && $schedules->num_rows > 0) {
    $schedules->data_seek(0);
    while($sch = $schedules->fetch_assoc()) {
        $weekly_schedule[$sch['day_name']][] = $sch;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Schedule - <?php echo htmlspecialchars($section['section_name']); ?></title>
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
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .header-left h1 {
            font-size: 28px;
            color: var(--text-primary);
            margin-bottom: 10px;
            font-weight: 700;
        }

        .header-left p {
            color: var(--text-secondary);
            font-size: 16px;
        }

        .back-btn {
            background: white;
            color: var(--text-primary);
            padding: 12px 25px;
            border-radius: 12px;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 10px;
            transition: all 0.3s ease;
            font-weight: 500;
            border: 1px solid var(--border-color);
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
        }

        .back-btn:hover {
            border-color: #0B4F2E;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(11, 79, 46, 0.1);
        }

        .back-btn i {
            color: #0B4F2E;
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

        /* Section Info Card */
        .section-info-card {
            background: white;
            border-radius: 20px;
            padding: 25px;
            margin-bottom: 30px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            display: flex;
            align-items: center;
            gap: 20px;
            flex-wrap: wrap;
        }

        .section-icon-large {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, #0B4F2E, #1a7a42);
            border-radius: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 40px;
        }

        .section-details h2 {
            font-size: 24px;
            color: var(--text-primary);
            margin-bottom: 10px;
        }

        .section-details p {
            color: var(--text-secondary);
            display: flex;
            align-items: center;
            gap: 20px;
            flex-wrap: wrap;
        }

        .section-details p span {
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .section-details p i {
            color: #0B4F2E;
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

        /* Grid Layout */
        .grid-2 {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
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
            border-radius: 20px;
            padding: 25px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
        }

        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 1px solid var(--border-color);
        }

        .card-header h3 {
            color: var(--text-primary);
            font-size: 18px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .card-header h3 i {
            color: #0B4F2E;
        }

        /* Form Styles */
        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: var(--text-secondary);
            font-weight: 500;
            font-size: 14px;
        }

        .form-group input,
        .form-group select {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid var(--border-color);
            border-radius: 10px;
            font-size: 14px;
            transition: all 0.3s;
            font-family: 'Inter', sans-serif;
        }

        .form-group input:focus,
        .form-group select:focus {
            border-color: #0B4F2E;
            outline: none;
            box-shadow: 0 0 0 3px rgba(11, 79, 46, 0.1);
        }

        .btn {
            padding: 12px 25px;
            border: none;
            border-radius: 10px;
            font-weight: 600;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s;
            font-size: 14px;
        }

        .btn-primary {
            background: #0B4F2E;
            color: white;
        }

        .btn-primary:hover {
            background: #1a7a42;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(11, 79, 46, 0.3);
        }

        .btn-danger {
            background: #dc3545;
            color: white;
        }

        .btn-danger:hover {
            background: #c82333;
        }

        .btn-sm {
            padding: 8px 15px;
            font-size: 13px;
        }

        .conflict-warning {
            background: #fff3cd;
            color: #856404;
            padding: 15px;
            border-radius: 10px;
            font-size: 13px;
            margin-top: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
            border-left: 4px solid #ffc107;
        }

        .conflict-warning i {
            font-size: 18px;
        }

        /* Schedule List */
        .schedule-list {
            max-height: 500px;
            overflow-y: auto;
        }

        .schedule-item {
            background: #f8f9fa;
            border-radius: 12px;
            padding: 15px;
            margin-bottom: 10px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-left: 4px solid #0B4F2E;
        }

        .schedule-item:hover {
            background: #f0f0f0;
        }

        .schedule-info h4 {
            color: var(--text-primary);
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
        }

        .schedule-info p span {
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .schedule-info p i {
            color: #0B4F2E;
            width: 16px;
        }

        .delete-btn {
            color: #dc3545;
            background: none;
            border: none;
            font-size: 18px;
            cursor: pointer;
            padding: 8px;
            border-radius: 8px;
            transition: all 0.3s;
        }

        .delete-btn:hover {
            background: #fee;
        }

        /* Weekly Schedule Table */
        .weekly-schedule {
            overflow-x: auto;
            margin-top: 30px;
        }

        .schedule-table {
            width: 100%;
            border-collapse: collapse;
            background: white;
            border-radius: 15px;
            overflow: hidden;
        }

        .schedule-table th {
            background: #0B4F2E;
            color: white;
            padding: 15px;
            text-align: left;
            font-weight: 600;
        }

        .schedule-table td {
            border: 1px solid var(--border-color);
            padding: 15px;
            vertical-align: top;
            min-width: 150px;
        }

        .schedule-cell {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 10px;
            margin-bottom: 8px;
            border-left: 3px solid #0B4F2E;
        }

        .schedule-cell:last-child {
            margin-bottom: 0;
        }

        .schedule-cell .subject {
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 3px;
        }

        .schedule-cell .teacher {
            font-size: 12px;
            color: var(--text-secondary);
        }

        .schedule-cell .room {
            font-size: 11px;
            background: #0B4F2E;
            color: white;
            padding: 2px 6px;
            border-radius: 4px;
            display: inline-block;
            margin-top: 5px;
        }

        .empty-cell {
            color: var(--text-secondary);
            font-style: italic;
            text-align: center;
            padding: 20px;
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
            
            .section-info-card {
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
                    <li><a href="subjects.php"><i class="fas fa-book"></i> <span>Subjects</span></a></li>
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
                <div class="header-left">
                    <h1>Class Schedule Management</h1>
                    <p>Create and manage class schedules for sections</p>
                </div>
                <a href="sections.php" class="back-btn">
                    <i class="fas fa-arrow-left"></i> Back to Sections
                </a>
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

            <!-- Section Info Card -->
            <div class="section-info-card">
                <div class="section-icon-large">
                    <i class="fas fa-users"></i>
                </div>
                <div class="section-details">
                    <h2><?php echo htmlspecialchars($section['section_name']); ?></h2>
                    <p>
                        <span><i class="fas fa-layer-group"></i> <?php echo htmlspecialchars($section['grade_name']); ?></span>
                        <span><i class="fas fa-user-tie"></i> Adviser: <?php echo htmlspecialchars($section['adviser_name'] ?? 'Not Assigned'); ?></span>
                        <span><i class="fas fa-calendar"></i> School Year: 2026-2027</span>
                    </p>
                </div>
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

            <!-- Main Grid -->
            <div class="grid-2">
                <!-- Add Schedule Form -->
                <div class="card">
                    <div class="card-header">
                        <h3><i class="fas fa-plus-circle"></i> Add New Schedule</h3>
                    </div>
                    <form method="POST">
                        <div class="form-group">
                            <label>Subject</label>
                            <select name="subject_id" required>
                                <option value="">Select Subject</option>
                                <?php if($subjects && $subjects->num_rows > 0): ?>
                                    <?php while($subject = $subjects->fetch_assoc()): ?>
                                        <option value="<?php echo $subject['id']; ?>">
                                            <?php echo htmlspecialchars($subject['subject_name']); ?>
                                        </option>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <option value="">No subjects available for this grade level</option>
                                <?php endif; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label>Teacher</label>
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
                            <label>Day</label>
                            <select name="day_id" required>
                                <option value="">Select Day</option>
                                <?php while($day = $days->fetch_assoc()): ?>
                                    <option value="<?php echo $day['id']; ?>"><?php echo $day['day_name']; ?></option>
                                <?php endwhile; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label>Time Slot</label>
                            <select name="time_slot_id" required>
                                <option value="">Select Time</option>
                                <?php while($slot = $time_slots->fetch_assoc()): ?>
                                    <option value="<?php echo $slot['id']; ?>">
                                        <?php echo date('h:i A', strtotime($slot['start_time'])) . ' - ' . date('h:i A', strtotime($slot['end_time'])); ?>
                                        <?php if($slot['slot_name']): ?>(<?php echo $slot['slot_name']; ?>)<?php endif; ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label>Room</label>
                            <input type="text" name="room" placeholder="e.g., Room 101, Science Lab">
                        </div>

                        <div class="form-group">
                            <label>School Year</label>
                            <select name="school_year" required>
                                <option value="2026-2027">2026-2027</option>
                                <option value="2027-2028">2027-2028</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label>Semester</label>
                            <select name="semester">
                                <option value="">Full Year</option>
                                <option value="1st Semester">1st Semester</option>
                                <option value="2nd Semester">2nd Semester</option>
                            </select>
                        </div>

                        <button type="submit" name="add_schedule" class="btn btn-primary" style="width: 100%;">
                            <i class="fas fa-save"></i> Add to Schedule
                        </button>
                    </form>

                    <div class="conflict-warning">
                        <i class="fas fa-info-circle"></i>
                        <div>
                            <strong>Schedule Conflict Prevention:</strong>
                            <p style="margin-top: 5px;">The system automatically checks for teacher and room conflicts to prevent double-booking.</p>
                        </div>
                    </div>
                </div>

                <!-- Current Schedule List -->
                <div class="card">
                    <div class="card-header">
                        <h3><i class="fas fa-list"></i> Current Schedule</h3>
                        <span class="grade-badge"><?php echo $schedules->num_rows; ?> subjects</span>
                    </div>
                    
                    <div class="schedule-list">
                        <?php if($schedules && $schedules->num_rows > 0): ?>
                            <?php 
                            $schedules->data_seek(0);
                            while($sch = $schedules->fetch_assoc()): 
                            ?>
                                <div class="schedule-item">
                                    <div class="schedule-info">
                                        <h4><?php echo htmlspecialchars($sch['subject_name']); ?></h4>
                                        <p>
                                            <span><i class="fas fa-user"></i> <?php echo htmlspecialchars($sch['teacher_name']); ?></span>
                                            <span><i class="fas fa-calendar"></i> <?php echo $sch['day_name']; ?></span>
                                            <span><i class="fas fa-clock"></i> <?php echo date('h:i A', strtotime($sch['start_time'])); ?></span>
                                            <?php if($sch['room']): ?>
                                                <span><i class="fas fa-door-open"></i> <?php echo htmlspecialchars($sch['room']); ?></span>
                                            <?php endif; ?>
                                        </p>
                                    </div>
                                    <a href="?section_id=<?php echo $section_id; ?>&delete_schedule=<?php echo $sch['id']; ?>" 
                                       class="delete-btn" 
                                       onclick="return confirm('Are you sure you want to delete this schedule?')">
                                        <i class="fas fa-trash"></i>
                                    </a>
                                </div>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <div style="text-align: center; padding: 40px; color: var(--text-secondary);">
                                <i class="fas fa-calendar-times" style="font-size: 48px; margin-bottom: 15px; opacity: 0.3;"></i>
                                <h3>No Schedule Yet</h3>
                                <p>Add subjects to create the class schedule.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Weekly Schedule View -->
            <div class="card" style="margin-top: 20px;">
                <div class="card-header">
                    <h3><i class="fas fa-table"></i> Weekly Schedule View</h3>
                </div>
                
                <div class="weekly-schedule">
                    <table class="schedule-table">
                        <thead>
                            <tr>
                                <th>Time</th>
                                <th>Monday</th>
                                <th>Tuesday</th>
                                <th>Wednesday</th>
                                <th>Thursday</th>
                                <th>Friday</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            // Get all time slots for the rows
                            $time_slots->data_seek(0);
                            while($slot = $time_slots->fetch_assoc()): 
                                $start = date('h:i A', strtotime($slot['start_time']));
                                $end = date('h:i A', strtotime($slot['end_time']));
                            ?>
                                <tr>
                                    <td style="font-weight: 600; background: #f8f9fa;">
                                        <?php echo $start; ?> - <?php echo $end; ?>
                                    </td>
                                    <?php
                                    $days_of_week = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'];
                                    foreach($days_of_week as $day):
                                        $found = false;
                                        if(isset($weekly_schedule[$day])) {
                                            foreach($weekly_schedule[$day] as $class) {
                                                if($class['time_slot_id'] == $slot['id']) {
                                                    $found = true;
                                                    ?>
                                                    <td>
                                                        <div class="schedule-cell">
                                                            <div class="subject"><?php echo htmlspecialchars($class['subject_name']); ?></div>
                                                            <div class="teacher"><?php echo htmlspecialchars($class['teacher_name']); ?></div>
                                                            <?php if($class['room']): ?>
                                                                <div class="room"><?php echo htmlspecialchars($class['room']); ?></div>
                                                            <?php endif; ?>
                                                        </div>
                                                    </td>
                                                    <?php
                                                    break;
                                                }
                                            }
                                        }
                                        if(!$found) {
                                            echo '<td class="empty-cell">—</td>';
                                        }
                                    endforeach;
                                    ?>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
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
</body>
</html>