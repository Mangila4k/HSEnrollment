<?php
session_start();

// Check if user is teacher first before including database
if(!isset($_SESSION['user']) || $_SESSION['user']['role'] != 'Teacher'){
    header("Location: ../auth/login.php");
    exit();
}

// Include database after session check
require_once("../config/database.php");

// Check if connection is successful
if (!$conn) {
    die("Database connection failed: " . mysqli_connect_error());
}

$teacher_id = $_SESSION['user']['id'];
$teacher_name = $_SESSION['user']['fullname'];
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

// Get section details and verify teacher has access
$section_query = "
    SELECT s.*, g.grade_name, u.fullname as adviser_name,
           CASE WHEN s.adviser_id = ? THEN 1 ELSE 0 END as is_adviser
    FROM sections s
    LEFT JOIN grade_levels g ON s.grade_id = g.id
    LEFT JOIN users u ON s.adviser_id = u.id
    WHERE s.id = ? AND (
        s.adviser_id = ? OR 
        EXISTS (
            SELECT 1 FROM class_schedules cs 
            WHERE cs.section_id = s.id AND cs.teacher_id = ?
        )
    )
";

$stmt = $conn->prepare($section_query);
if (!$stmt) {
    die("Error preparing section query: " . $conn->error);
}
$stmt->bind_param("iiii", $teacher_id, $section_id, $teacher_id, $teacher_id);
$stmt->execute();
$result = $stmt->get_result();
$section = $result->fetch_assoc();
$stmt->close();

if(!$section) {
    $_SESSION['error_message'] = "Section not found or you don't have access to this section.";
    header("Location: classes.php");
    exit();
}

// Check what columns exist in enrollments table
$columns_check = $conn->query("SHOW COLUMNS FROM enrollments");
$enrollment_columns = [];
if($columns_check) {
    while($col = $columns_check->fetch_assoc()) {
        $enrollment_columns[] = $col['Field'];
    }
}

// Get students - check if section_id exists in enrollments
if(in_array('section_id', $enrollment_columns)) {
    // If section_id exists, use it
    $students_query = "
        SELECT 
            u.id,
            u.fullname,
            u.email,
            e.status as enrollment_status
        FROM users u
        JOIN enrollments e ON u.id = e.student_id
        WHERE e.section_id = ? AND u.role = 'Student'
        ORDER BY u.fullname
    ";
    $stmt = $conn->prepare($students_query);
    if (!$stmt) {
        die("Error preparing students query: " . $conn->error);
    }
    $stmt->bind_param("i", $section_id);
} else {
    // If no section_id, get students by grade_id
    $students_query = "
        SELECT 
            u.id,
            u.fullname,
            u.email,
            e.status as enrollment_status
        FROM users u
        JOIN enrollments e ON u.id = e.student_id
        WHERE e.grade_id = ? AND u.role = 'Student'
        ORDER BY u.fullname
    ";
    $stmt = $conn->prepare($students_query);
    if (!$stmt) {
        die("Error preparing students query: " . $conn->error);
    }
    $stmt->bind_param("i", $section['grade_id']);
}

$stmt->execute();
$students = $stmt->get_result();
$stmt->close();

// Get class schedule for this section
$schedule_query = "
    SELECT 
        cs.*,
        sub.subject_name,
        u.fullname as teacher_name,
        d.day_name,
        d.day_order,
        ts.start_time,
        ts.end_time,
        ts.slot_name
    FROM class_schedules cs
    LEFT JOIN subjects sub ON cs.subject_id = sub.id
    LEFT JOIN users u ON cs.teacher_id = u.id
    LEFT JOIN days_of_week d ON cs.day_id = d.id
    LEFT JOIN time_slots ts ON cs.time_slot_id = ts.id
    WHERE cs.section_id = ? AND (cs.status = 'active' OR cs.status IS NULL)
    ORDER BY d.day_order, ts.start_time
";

$stmt = $conn->prepare($schedule_query);
if (!$stmt) {
    // If table doesn't exist, just set to empty
    $schedule = null;
} else {
    $stmt->bind_param("i", $section_id);
    $stmt->execute();
    $schedule = $stmt->get_result();
    $stmt->close();
}

// Organize schedule by day for display
$subjects_taught = [];
if($schedule && $schedule->num_rows > 0) {
    $schedule->data_seek(0);
    while($class = $schedule->fetch_assoc()) {
        if($class['teacher_id'] == $teacher_id) {
            $subjects_taught[] = $class['subject_name'];
        }
    }
    $schedule->data_seek(0); // Reset pointer for later use
}

// Get attendance statistics (check if attendance table exists)
$attendance_stats = [
    'total' => 0,
    'present' => 0,
    'absent' => 0,
    'late' => 0
];

$table_check = $conn->query("SHOW TABLES LIKE 'attendance'");
if($table_check && $table_check->num_rows > 0) {
    if(in_array('section_id', $enrollment_columns)) {
        $attendance_query = "
            SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN status = 'Present' THEN 1 ELSE 0 END) as present,
                SUM(CASE WHEN status = 'Absent' THEN 1 ELSE 0 END) as absent,
                SUM(CASE WHEN status = 'Late' THEN 1 ELSE 0 END) as late
            FROM attendance a
            JOIN users u ON a.student_id = u.id
            JOIN enrollments e ON u.id = e.student_id
            WHERE e.section_id = ?
        ";
        $stmt = $conn->prepare($attendance_query);
        if($stmt) {
            $stmt->bind_param("i", $section_id);
            $stmt->execute();
            $stats = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            
            $attendance_stats = [
                'total' => $stats['total'] ?? 0,
                'present' => $stats['present'] ?? 0,
                'absent' => $stats['absent'] ?? 0,
                'late' => $stats['late'] ?? 0
            ];
        }
    }
}

$attendance_rate = $attendance_stats['total'] > 0 
    ? round(($attendance_stats['present'] / $attendance_stats['total']) * 100, 1) 
    : 0;

// Get current school year
$current_sy = date('Y') . '-' . (date('Y') + 1);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($section['section_name']); ?> - Section Details</title>
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
            border-color: var(--primary);
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(11, 79, 46, 0.1);
        }

        .back-btn i {
            color: var(--primary);
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

        /* Section Header */
        .section-header {
            background: white;
            border-radius: 20px;
            padding: 25px;
            margin-bottom: 30px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            display: flex;
            align-items: center;
            gap: 25px;
            flex-wrap: wrap;
        }

        .section-icon-large {
            width: 100px;
            height: 100px;
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            border-radius: 25px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 50px;
        }

        .section-title-info h1 {
            font-size: 32px;
            color: var(--text-primary);
            margin-bottom: 10px;
        }

        .badge-container {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
        }

        .badge {
            padding: 8px 16px;
            border-radius: 30px;
            font-size: 14px;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .badge-grade {
            background: var(--primary-light);
            color: var(--primary);
        }

        .badge-adviser {
            background: rgba(255, 215, 0, 0.1);
            color: #b8860b;
        }

        .badge i {
            font-size: 14px;
        }

        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
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

        .stat-content .stat-number {
            font-size: 24px;
            font-weight: 700;
            color: var(--text-primary);
        }

        .stat-content .stat-label {
            font-size: 13px;
            color: var(--text-secondary);
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
            color: var(--primary);
        }

        .card-header .badge {
            background: #f8f9fa;
            color: var(--text-secondary);
            font-size: 12px;
        }

        /* Students Table */
        .table-container {
            overflow-x: auto;
            max-height: 500px;
            overflow-y: auto;
            border-radius: 10px;
            border: 1px solid var(--border-color);
        }

        .students-table {
            width: 100%;
            border-collapse: collapse;
            min-width: 600px;
        }

        .students-table th {
            text-align: left;
            padding: 15px;
            background: #f8f9fa;
            color: var(--text-secondary);
            font-weight: 600;
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            position: sticky;
            top: 0;
            background: #f8f9fa;
            z-index: 10;
            border-bottom: 2px solid var(--border-color);
        }

        .students-table td {
            padding: 15px;
            border-bottom: 1px solid var(--border-color);
            vertical-align: middle;
        }

        .students-table tbody tr:hover {
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
            background: var(--primary);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
            font-size: 16px;
            flex-shrink: 0;
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

        .status-badge {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            display: inline-block;
            text-align: center;
            min-width: 80px;
        }

        .status-enrolled {
            background: rgba(40, 167, 69, 0.1);
            color: #28a745;
        }

        .status-pending {
            background: rgba(255, 193, 7, 0.1);
            color: #ffc107;
        }

        .status-rejected {
            background: rgba(220, 53, 69, 0.1);
            color: #dc3545;
        }

        .action-btns {
            display: flex;
            gap: 8px;
            justify-content: flex-start;
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
            text-decoration: none;
        }

        .btn-icon:hover {
            background: var(--hover-color);
        }

        .btn-view:hover {
            color: var(--primary);
        }

        /* Schedule List */
        .schedule-list {
            max-height: 500px;
            overflow-y: auto;
            padding-right: 5px;
        }

        .schedule-item {
            background: #f8f9fa;
            border-radius: 12px;
            padding: 15px;
            margin-bottom: 10px;
            border-left: 4px solid var(--primary);
            transition: all 0.3s;
        }

        .schedule-item:hover {
            transform: translateX(5px);
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .schedule-item.taught-by-me {
            border-left-color: var(--accent);
            background: rgba(255, 215, 0, 0.05);
        }

        .schedule-item .day-time {
            display: flex;
            justify-content: space-between;
            margin-bottom: 8px;
        }

        .schedule-item .day {
            font-weight: 600;
            color: var(--primary);
        }

        .schedule-item .time {
            color: var(--text-secondary);
            font-size: 13px;
        }

        .schedule-item .subject {
            font-size: 16px;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 5px;
        }

        .schedule-item .teacher {
            color: var(--text-secondary);
            font-size: 13px;
            display: flex;
            align-items: center;
            gap: 5px;
            margin-bottom: 5px;
        }

        .schedule-item .room {
            display: inline-block;
            background: var(--primary);
            color: white;
            padding: 3px 10px;
            border-radius: 15px;
            font-size: 11px;
        }

        .taught-badge {
            display: inline-block;
            background: var(--accent);
            color: var(--primary);
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 10px;
            font-weight: 600;
            margin-left: 8px;
        }

        /* No Data */
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

        .no-data h3 {
            color: var(--text-primary);
            margin-bottom: 10px;
        }

        /* Quick Actions */
        .quick-actions {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
            margin-top: 20px;
        }

        .action-btn {
            padding: 12px 25px;
            border-radius: 10px;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-weight: 500;
            transition: all 0.3s;
            cursor: pointer;
        }

        .action-btn.primary {
            background: var(--primary);
            color: white;
            border: none;
        }

        .action-btn.primary:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(11, 79, 46, 0.3);
        }

        .action-btn.warning {
            background: var(--accent);
            color: var(--primary);
            border: none;
        }

        .action-btn.warning:hover {
            background: #ffed4a;
            transform: translateY(-2px);
        }

        .action-btn.secondary {
            background: white;
            color: var(--text-primary);
            border: 2px solid var(--border-color);
        }

        .action-btn.secondary:hover {
            border-color: var(--primary);
            color: var(--primary);
        }

        /* Responsive */
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
            
            .section-header {
                flex-direction: column;
                text-align: center;
            }
            
            .badge-container {
                justify-content: center;
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .student-info {
                flex-direction: column;
                text-align: center;
            }
            
            .action-btns {
                justify-content: center;
            }

            .quick-actions {
                flex-direction: column;
            }

            .action-btn {
                width: 100%;
                justify-content: center;
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
                <div class="header-left">
                    <h1>Section Details</h1>
                    <p>View information about <?php echo htmlspecialchars($section['section_name']); ?></p>
                </div>
                <a href="classes.php" class="back-btn">
                    <i class="fas fa-arrow-left"></i> Back to Classes
                </a>
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

            <!-- Section Header -->
            <div class="section-header">
                <div class="section-icon-large">
                    <i class="fas fa-users"></i>
                </div>
                <div class="section-title-info">
                    <h1><?php echo htmlspecialchars($section['section_name']); ?></h1>
                    <div class="badge-container">
                        <span class="badge badge-grade">
                            <i class="fas fa-layer-group"></i> <?php echo htmlspecialchars($section['grade_name']); ?>
                        </span>
                        <?php if($section['is_adviser']): ?>
                            <span class="badge badge-adviser">
                                <i class="fas fa-star"></i> You are the Adviser
                            </span>
                        <?php endif; ?>
                        <span class="badge" style="background: #f8f9fa;">
                            <i class="fas fa-user-tie"></i> Adviser: <?php echo htmlspecialchars($section['adviser_name'] ?? 'Not Assigned'); ?>
                        </span>
                        <span class="badge" style="background: #f8f9fa;">
                            <i class="fas fa-calendar"></i> SY: <?php echo $current_sy; ?>
                        </span>
                    </div>
                </div>
            </div>

            <!-- Statistics -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="stat-content">
                        <h3>Total Students</h3>
                        <div class="stat-number"><?php echo $students->num_rows; ?></div>
                        <div class="stat-label">Enrolled in this section</div>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-book"></i>
                    </div>
                    <div class="stat-content">
                        <h3>Subjects</h3>
                        <div class="stat-number"><?php echo $schedule ? $schedule->num_rows : 0; ?></div>
                        <div class="stat-label">Classes scheduled</div>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-calendar-check"></i>
                    </div>
                    <div class="stat-content">
                        <h3>Attendance Rate</h3>
                        <div class="stat-number"><?php echo $attendance_rate; ?>%</div>
                        <div class="stat-label">Overall attendance</div>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-chart-line"></i>
                    </div>
                    <div class="stat-content">
                        <h3>Your Subjects</h3>
                        <div class="stat-number"><?php echo count($subjects_taught); ?></div>
                        <div class="stat-label">You teach in this section</div>
                    </div>
                </div>
            </div>

            <!-- Main Grid -->
            <div class="grid-2">
                <!-- Students List - TABLE FORMAT -->
                <div class="card">
                    <div class="card-header">
                        <h3><i class="fas fa-user-graduate"></i> Enrolled Students</h3>
                        <span class="badge"><?php echo $students->num_rows; ?> students</span>
                    </div>

                    <div class="table-container">
                        <?php if($students && $students->num_rows > 0): ?>
                            <table class="students-table">
                                <thead>
                                    <tr>
                                        <th>Student</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while($student = $students->fetch_assoc()): ?>
                                        <tr>
                                            <td>
                                                <div class="student-info">
                                                    <div class="student-avatar">
                                                        <?php echo strtoupper(substr($student['fullname'], 0, 1)); ?>
                                                    </div>
                                                    <div class="student-details">
                                                        <h4><?php echo htmlspecialchars($student['fullname']); ?></h4>
                                                        <span><?php echo htmlspecialchars($student['email']); ?></span>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <span class="status-badge status-<?php echo strtolower($student['enrollment_status']); ?>">
                                                    <?php echo $student['enrollment_status']; ?>
                                                </span>
                                            </td>
                                            <td>
                                                <div class="action-btns">
                                                    <a href="view_student.php?id=<?php echo $student['id']; ?>" class="btn-icon btn-view" title="View Student">
                                                        <i class="fas fa-eye"></i>
                                                    </a>
                                                    <a href="attendance.php?student_id=<?php echo $student['id']; ?>&section_id=<?php echo $section_id; ?>" class="btn-icon btn-view" title="View Attendance">
                                                        <i class="fas fa-calendar-check"></i>
                                                    </a>
                                                    <a href="grades.php?student_id=<?php echo $student['id']; ?>" class="btn-icon btn-view" title="View Grades">
                                                        <i class="fas fa-star"></i>
                                                    </a>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        <?php else: ?>
                            <div class="no-data">
                                <i class="fas fa-user-graduate"></i>
                                <h3>No Students Enrolled</h3>
                                <p>This section currently has no enrolled students.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Class Schedule -->
                <div class="card">
                    <div class="card-header">
                        <h3><i class="fas fa-calendar-alt"></i> Class Schedule</h3>
                        <span class="badge"><?php echo $schedule ? $schedule->num_rows : 0; ?> classes</span>
                    </div>

                    <div class="schedule-list">
                        <?php if($schedule && $schedule->num_rows > 0): ?>
                            <?php while($class = $schedule->fetch_assoc()): 
                                $is_my_class = ($class['teacher_id'] == $teacher_id);
                            ?>
                                <div class="schedule-item <?php echo $is_my_class ? 'taught-by-me' : ''; ?>">
                                    <div class="day-time">
                                        <span class="day"><?php echo $class['day_name']; ?></span>
                                        <span class="time">
                                            <?php echo date('h:i A', strtotime($class['start_time'])); ?> - 
                                            <?php echo date('h:i A', strtotime($class['end_time'])); ?>
                                        </span>
                                    </div>
                                    <div class="subject">
                                        <?php echo htmlspecialchars($class['subject_name']); ?>
                                        <?php if($is_my_class): ?>
                                            <span class="taught-badge">You teach this</span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="teacher">
                                        <i class="fas fa-user"></i> <?php echo htmlspecialchars($class['teacher_name']); ?>
                                    </div>
                                    <?php if($class['room']): ?>
                                        <div class="room">
                                            <i class="fas fa-door-open"></i> <?php echo htmlspecialchars($class['room']); ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <div class="no-data">
                                <i class="fas fa-calendar-times"></i>
                                <h3>No Schedule Yet</h3>
                                <p>No classes have been scheduled for this section.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="card" style="margin-top: 20px;">
                <div class="card-header">
                    <h3><i class="fas fa-bolt"></i> Quick Actions</h3>
                </div>
                <div class="quick-actions">
                    <a href="attendance.php?section_id=<?php echo $section_id; ?>" class="action-btn primary">
                        <i class="fas fa-calendar-check"></i> Take Attendance
                    </a>
                    <a href="grades.php?section_id=<?php echo $section_id; ?>" class="action-btn warning">
                        <i class="fas fa-star"></i> Manage Grades
                    </a>
                    <a href="schedule.php?section_id=<?php echo $section_id; ?>" class="action-btn secondary">
                        <i class="fas fa-clock"></i> Full Schedule
                    </a>
                    <a href="#" class="action-btn secondary" onclick="alert('Communication feature coming soon!')">
                        <i class="fas fa-envelope"></i> Send Message
                    </a>
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