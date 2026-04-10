<?php
// Set Philippine Timezone
date_default_timezone_set('Asia/Manila');

// Start session only if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include database - use require_once to prevent redeclaration
require_once '../config/database.php';

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
$sections_query = $conn->prepare("
    SELECT s.*, g.grade_name 
    FROM sections s
    JOIN grade_levels g ON s.grade_id = g.id
    WHERE s.adviser_id = ?
");
$sections_query->execute([$teacher_id]);
$sections = $sections_query->fetchAll(PDO::FETCH_ASSOC);

// Get subjects taught by this teacher
$subjects_query = $conn->prepare("
    SELECT DISTINCT sub.*, g.grade_name 
    FROM subjects sub
    JOIN grade_levels g ON sub.grade_id = g.id
    ORDER BY sub.subject_name
");
$subjects_query->execute();
$subjects = $subjects_query->fetchAll(PDO::FETCH_ASSOC);

// FIRST: Get today's attendance record (should be only one)
$today_stmt = $conn->prepare("
    SELECT * FROM teacher_attendance 
    WHERE teacher_id = ? AND date = CURDATE()
    ORDER BY id DESC LIMIT 1
");
$today_stmt->execute([$teacher_id]);
$today_attendance = $today_stmt->fetch(PDO::FETCH_ASSOC);

// Determine attendance status
$time_in_recorded = false;
$time_out_recorded = false;
$attendance_completed = false;

if($today_attendance) {
    $time_in_recorded = ($today_attendance['time_in'] !== null && $today_attendance['time_in'] != '00:00:00');
    $time_out_recorded = ($today_attendance['time_out'] !== null && $today_attendance['time_out'] != '00:00:00');
    $attendance_completed = ($time_in_recorded && $time_out_recorded);
}

// Check for existing active session (only if attendance not completed)
$active_session = null;
if(!$attendance_completed && $today_attendance) {
    // Check if there's an active session for today's record
    $active_stmt = $conn->prepare("
        SELECT * FROM teacher_attendance 
        WHERE id = ? AND session_status = 'active' AND expires_at > NOW()
    ");
    $active_stmt->execute([$today_attendance['id']]);
    $active_session = $active_stmt->fetch(PDO::FETCH_ASSOC);
}

// Handle QR generation
if(isset($_GET['generate']) && $_GET['generate'] == '1') {
    // Check if attendance is already completed for today
    if($attendance_completed) {
        $error_message = "You have already completed your attendance for today (both Time In and Time Out recorded). You cannot generate a new QR code.";
    } else {
        // Check if there's an existing attendance record for today
        if(!$today_attendance) {
            // Create new attendance record
            $token = md5($teacher_id . date('Y-m-d H:i:s') . uniqid() . rand(1000, 9999));
            $current_time = new DateTime('now', new DateTimeZone('Asia/Manila'));
            $expires_time = clone $current_time;
            $expires_time->modify('+1 hour');
            
            $insert_stmt = $conn->prepare("
                INSERT INTO teacher_attendance (teacher_id, date, qr_token, session_status, expires_at, status)
                VALUES (?, CURDATE(), ?, 'active', ?, 'Pending')
            ");
            
            if($insert_stmt->execute([$teacher_id, $token, $expires_time->format('Y-m-d H:i:s')])) {
                $success_message = "QR Code generated successfully! Scan to record your attendance.";
                // Refresh data
                $today_stmt->execute([$teacher_id]);
                $today_attendance = $today_stmt->fetch(PDO::FETCH_ASSOC);
                $active_session = $today_attendance;
            } else {
                $error_message = "Failed to generate QR code. Please try again.";
            }
        } else {
            // Update existing record with new QR code (only if time out not recorded)
            if(!$time_out_recorded) {
                // First, expire any existing active session
                $expire_stmt = $conn->prepare("
                    UPDATE teacher_attendance 
                    SET session_status = 'expired'
                    WHERE id = ? AND session_status = 'active'
                ");
                $expire_stmt->execute([$today_attendance['id']]);
                
                // Generate new token
                $token = md5($teacher_id . date('Y-m-d H:i:s') . uniqid() . rand(1000, 9999));
                $current_time = new DateTime('now', new DateTimeZone('Asia/Manila'));
                $expires_time = clone $current_time;
                $expires_time->modify('+1 hour');
                
                $update_stmt = $conn->prepare("
                    UPDATE teacher_attendance 
                    SET qr_token = ?, session_status = 'active', expires_at = ?
                    WHERE id = ?
                ");
                if($update_stmt->execute([$token, $expires_time->format('Y-m-d H:i:s'), $today_attendance['id']])) {
                    $success_message = "New QR Code generated successfully!";
                    // Refresh active session
                    $active_stmt = $conn->prepare("
                        SELECT * FROM teacher_attendance 
                        WHERE id = ? AND session_status = 'active' AND expires_at > NOW()
                    ");
                    $active_stmt->execute([$today_attendance['id']]);
                    $active_session = $active_stmt->fetch(PDO::FETCH_ASSOC);
                } else {
                    $error_message = "Failed to generate QR code. Please try again.";
                }
            } else {
                $error_message = "Cannot generate QR code. Time Out already recorded.";
            }
        }
    }
}

// Get today's attendance again after any changes
$today_stmt->execute([$teacher_id]);
$today_attendance = $today_stmt->fetch(PDO::FETCH_ASSOC);

if($today_attendance) {
    $time_in_recorded = ($today_attendance['time_in'] !== null && $today_attendance['time_in'] != '00:00:00');
    $time_out_recorded = ($today_attendance['time_out'] !== null && $today_attendance['time_out'] != '00:00:00');
    $attendance_completed = ($time_in_recorded && $time_out_recorded);
}

// Get attendance history
$history_stmt = $conn->prepare("
    SELECT * FROM teacher_attendance 
    WHERE teacher_id = ? 
    ORDER BY date DESC, created_at DESC
    LIMIT 10
");
$history_stmt->execute([$teacher_id]);
$attendance_history = $history_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get statistics
$stats_stmt = $conn->prepare("
    SELECT 
        COUNT(*) as total_days,
        SUM(CASE WHEN status = 'Present' THEN 1 ELSE 0 END) as present_days,
        SUM(CASE WHEN status = 'Late' THEN 1 ELSE 0 END) as late_days,
        SUM(CASE WHEN status = 'Absent' THEN 1 ELSE 0 END) as absent_days
    FROM teacher_attendance 
    WHERE teacher_id = ?
");
$stats_stmt->execute([$teacher_id]);
$stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);

// Calculate the QR URL if active session exists
$qr_url = '';
if($active_session && $active_session['qr_token'] && !$attendance_completed && !$time_out_recorded) {
    $base_url = (isset($_SERVER['HTTPS']) ? "https://" : "http://") . $_SERVER['HTTP_HOST'];
    $base_url = str_replace('/teacher', '', $base_url);
    $qr_url = $base_url . "/highschool-system/teacher/process_attendance.php?token=" . $active_session['qr_token'];
}

// Get current Philippine time
$ph_time = new DateTime('now', new DateTimeZone('Asia/Manila'));
$ph_time_display = $ph_time->format('h:i A');
$ph_date_display = $ph_time->format('F d, Y');
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>QR Code Attendance - Teacher Dashboard</title>
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
            --info: #17a2b8;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #f4f7fd;
            min-height: 100vh;
        }

        .app-container {
            display: flex;
            min-height: 100vh;
        }

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
            letter-spacing: 1px;
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

        .main-content {
            flex: 1;
            margin-left: 280px;
            padding: 30px;
        }

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

        .ph-time {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: white;
            padding: 10px 20px;
            border-radius: 12px;
            display: inline-block;
            margin-bottom: 20px;
            font-weight: 600;
        }

        .ph-time i {
            margin-right: 8px;
            color: var(--accent);
        }

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

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            border-radius: 15px;
            padding: 20px;
            text-align: center;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
        }

        .stat-card i {
            font-size: 30px;
            color: var(--primary);
            margin-bottom: 10px;
        }

        .stat-number {
            font-size: 28px;
            font-weight: 700;
            color: var(--text-primary);
        }

        .stat-label {
            color: var(--text-secondary);
            font-size: 14px;
            margin-top: 5px;
        }

        .qr-card {
            background: white;
            border-radius: 20px;
            padding: 30px;
            margin-bottom: 30px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            text-align: center;
        }

        .qr-container {
            display: flex;
            justify-content: center;
            align-items: center;
            flex-direction: column;
            gap: 20px;
        }

        .qr-code {
            background: white;
            padding: 20px;
            border-radius: 20px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            display: inline-block;
        }

        .qr-code img {
            width: 250px;
            height: 250px;
        }

        .qr-info {
            margin-top: 20px;
        }

        .qr-info h4 {
            color: var(--text-primary);
            margin-bottom: 10px;
        }

        .attendance-info {
            background: white;
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 30px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
        }

        .info-row {
            display: flex;
            justify-content: space-between;
            padding: 12px 0;
            border-bottom: 1px solid var(--border-color);
        }

        .info-row:last-child {
            border-bottom: none;
        }

        .info-label {
            font-weight: 600;
            color: var(--text-secondary);
        }

        .info-value {
            color: var(--text-primary);
            font-weight: 500;
        }

        .btn-generate {
            background: var(--primary);
            color: white;
            border: none;
            padding: 12px 30px;
            border-radius: 10px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            text-decoration: none;
        }

        .btn-generate:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
        }
        
        .btn-generate:disabled {
            background: #ccc;
            cursor: not-allowed;
            transform: none;
        }
        
        .btn-regenerate {
            background: var(--warning);
            color: var(--text-primary);
            margin-left: 10px;
        }
        
        .btn-regenerate:hover {
            background: #e0a800;
        }

        .attendance-table {
            width: 100%;
            border-collapse: collapse;
            background: white;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
        }

        .attendance-table th {
            text-align: left;
            padding: 15px;
            background: #f8f9fa;
            color: var(--text-secondary);
            font-weight: 600;
            font-size: 13px;
            text-transform: uppercase;
        }

        .attendance-table td {
            padding: 15px;
            border-bottom: 1px solid var(--border-color);
        }

        .status-badge {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            display: inline-block;
        }

        .status-Present {
            background: rgba(40, 167, 69, 0.1);
            color: var(--success);
        }

        .status-Late {
            background: rgba(255, 193, 7, 0.1);
            color: var(--warning);
        }

        .status-Absent {
            background: rgba(220, 53, 69, 0.1);
            color: var(--danger);
        }
        
        .status-Pending {
            background: rgba(23, 162, 184, 0.1);
            color: var(--info);
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

        .scanner-container {
            background: white;
            border-radius: 20px;
            padding: 25px;
            margin-bottom: 30px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
        }
        
        .scanner-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            flex-wrap: wrap;
            gap: 15px;
        }
        
        .scanner-header h3 {
            color: var(--text-primary);
            font-size: 20px;
        }
        
        .tab-buttons {
            display: flex;
            gap: 10px;
        }
        
        .tab-btn {
            padding: 8px 20px;
            background: #f0f0f0;
            border: none;
            border-radius: 25px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s;
        }
        
        .tab-btn.active {
            background: var(--primary);
            color: white;
        }
        
        .scanner-tab {
            display: none;
        }
        
        .scanner-tab.active-tab {
            display: block;
            animation: fadeIn 0.3s ease;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .video-container {
            position: relative;
            width: 100%;
            max-width: 500px;
            margin: 0 auto;
            border-radius: 15px;
            overflow: hidden;
            background: #000;
        }
        
        #video {
            width: 100%;
            height: auto;
            display: block;
        }
        
        #canvas {
            display: none;
        }
        
        .scan-line {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 3px;
            background: var(--accent);
            animation: scan 2s linear infinite;
            pointer-events: none;
            box-shadow: 0 0 10px var(--accent);
        }
        
        @keyframes scan {
            0% { top: 0; }
            100% { top: 100%; }
        }
        
        .scanner-controls {
            margin-top: 20px;
            text-align: center;
        }
        
        .btn-scanner {
            background: var(--primary);
            color: white;
            border: none;
            padding: 10px 25px;
            border-radius: 8px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            margin: 5px;
            transition: all 0.3s;
        }
        
        .btn-scanner:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
        }
        
        .btn-scanner.danger {
            background: var(--danger);
        }
        
        .btn-scanner.danger:hover {
            background: #c82333;
        }
        
        .upload-area {
            border: 2px dashed var(--border-color);
            border-radius: 15px;
            padding: 40px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s;
            background: #fafafa;
        }
        
        .upload-area:hover {
            border-color: var(--primary);
            background: var(--primary-light);
        }
        
        .upload-area i {
            font-size: 48px;
            color: var(--primary);
            margin-bottom: 15px;
        }
        
        .upload-area p {
            color: var(--text-secondary);
            margin-bottom: 10px;
        }
        
        #fileInput {
            display: none;
        }
        
        .preview-image {
            max-width: 300px;
            margin: 20px auto;
            display: none;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        
        .preview-image img {
            width: 100%;
            border-radius: 10px;
        }
        
        .scan-result {
            margin-top: 20px;
            padding: 15px;
            border-radius: 10px;
            display: none;
            animation: slideIn 0.3s ease;
        }
        
        .scan-result.success {
            background: #d4edda;
            color: #155724;
            border-left: 4px solid var(--success);
            display: block;
        }
        
        .scan-result.error {
            background: #f8d7da;
            color: #721c24;
            border-left: 4px solid var(--danger);
            display: block;
        }
        
        .scan-result.loading {
            background: #d1ecf1;
            color: #0c5460;
            border-left: 4px solid var(--info);
            display: block;
        }
        
        .scan-result i {
            margin-right: 10px;
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
            
            .main-content {
                margin-left: 80px;
                padding: 20px;
            }
            
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
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
                    <li><a href="attendance_qr.php" class="active"><i class="fas fa-qrcode"></i> <span>QR Attendance</span></a></li>
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
            <div class="dashboard-header">
                <h1>Teacher QR Attendance System</h1>
                <p>Generate QR code or scan to record your time in and time out</p>
            </div>

            <!-- Philippine Time Display -->
            <div class="ph-time">
                <i class="fas fa-clock"></i> Philippine Time: <?php echo $ph_date_display . ' - ' . $ph_time_display; ?>
            </div>

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
            <div class="stats-grid">
                <div class="stat-card">
                    <i class="fas fa-calendar-day"></i>
                    <div class="stat-number"><?php echo $stats['total_days'] ?? 0; ?></div>
                    <div class="stat-label">Total Days</div>
                </div>
                <div class="stat-card">
                    <i class="fas fa-check-circle" style="color: var(--success);"></i>
                    <div class="stat-number"><?php echo $stats['present_days'] ?? 0; ?></div>
                    <div class="stat-label">Present</div>
                </div>
                <div class="stat-card">
                    <i class="fas fa-clock" style="color: var(--warning);"></i>
                    <div class="stat-number"><?php echo $stats['late_days'] ?? 0; ?></div>
                    <div class="stat-label">Late</div>
                </div>
                <div class="stat-card">
                    <i class="fas fa-times-circle" style="color: var(--danger);"></i>
                    <div class="stat-number"><?php echo $stats['absent_days'] ?? 0; ?></div>
                    <div class="stat-label">Absent</div>
                </div>
            </div>

            <!-- Today's Attendance Info -->
            <?php if($today_attendance): ?>
            <div class="attendance-info">
                <h3 style="margin-bottom: 20px;"><i class="fas fa-today"></i> Today's Attendance (<?php echo $ph_date_display; ?>)</h3>
                <div class="info-row">
                    <span class="info-label">⏰ Time In:</span>
                    <span class="info-value">
                        <?php 
                        if($time_in_recorded) {
                            echo date('h:i A', strtotime($today_attendance['time_in']));
                        } else {
                            echo '<span style="color: var(--warning);">Not yet recorded</span>';
                        }
                        ?>
                    </span>
                </div>
                <div class="info-row">
                    <span class="info-label">⏰ Time Out:</span>
                    <span class="info-value">
                        <?php 
                        if($time_out_recorded) {
                            echo date('h:i A', strtotime($today_attendance['time_out']));
                        } elseif($time_in_recorded && !$time_out_recorded) {
                            echo '<span style="color: var(--warning);">Waiting for Time Out scan...</span>';
                        } else {
                            echo '<span style="color: var(--warning);">Not yet recorded</span>';
                        }
                        ?>
                    </span>
                </div>
                <div class="info-row">
                    <span class="info-label">📊 Status:</span>
                    <span class="info-value">
                        <span class="status-badge status-<?php echo $today_attendance['status']; ?>">
                            <?php echo $today_attendance['status']; ?>
                        </span>
                    </span>
                </div>
                <?php if($time_in_recorded && !$time_out_recorded): ?>
                <div class="info-row" style="background: var(--primary-light); margin-top: 10px; border-radius: 8px;">
                    <span class="info-label" style="color: var(--primary);">📌 Next Step:</span>
                    <span class="info-value" style="color: var(--primary);">Scan the same QR code again to record Time Out</span>
                </div>
                <?php endif; ?>
                <?php if($attendance_completed): ?>
                <div class="info-row" style="background: #d4edda; margin-top: 10px; border-radius: 8px;">
                    <span class="info-label" style="color: var(--success);">✅ Complete:</span>
                    <span class="info-value" style="color: var(--success);">You have completed your attendance for today!</span>
                </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <!-- QR Code Scanner Section (Hidden if attendance completed) -->
            <?php if(!$attendance_completed): ?>
            <div class="scanner-container">
                <div class="scanner-header">
                    <h3><i class="fas fa-camera"></i> Scan QR Code to Record Attendance</h3>
                    <div class="tab-buttons">
                        <button class="tab-btn active" onclick="switchTab('camera')">📷 Camera</button>
                        <button class="tab-btn" onclick="switchTab('upload')">📁 Upload Photo</button>
                    </div>
                </div>
                
                <!-- Camera Tab -->
                <div id="cameraScannerTab" class="scanner-tab active-tab">
                    <div class="video-container">
                        <video id="video" playsinline autoplay></video>
                        <canvas id="canvas"></canvas>
                        <div class="scan-line" id="scanLine"></div>
                    </div>
                    <div class="scanner-controls">
                        <button class="btn-scanner" id="startCameraBtn" onclick="startCamera()">
                            <i class="fas fa-camera"></i> Start Camera
                        </button>
                        <button class="btn-scanner danger" id="stopCameraBtn" onclick="stopCamera()" style="display: none;">
                            <i class="fas fa-stop"></i> Stop Camera
                        </button>
                    </div>
                </div>
                
                <!-- Upload Tab -->
                <div id="uploadScannerTab" class="scanner-tab">
                    <div class="upload-area" onclick="document.getElementById('fileInput').click()">
                        <i class="fas fa-cloud-upload-alt"></i>
                        <p>Click to upload QR code image</p>
                        <p style="font-size: 12px;">Supports: JPG, PNG, GIF</p>
                    </div>
                    <input type="file" id="fileInput" accept="image/*" onchange="uploadImage(this)">
                    <div class="preview-image" id="previewImage">
                        <img id="previewImg" src="" alt="Preview">
                    </div>
                </div>
                
                <div class="scan-result" id="scanResult">
                    <div id="scanResultText"></div>
                </div>
            </div>
            <?php endif; ?>

            <!-- QR Code Section -->
            <div class="qr-card">
                <?php if($attendance_completed): ?>
                    <div class="qr-container">
                        <i class="fas fa-check-circle" style="font-size: 60px; color: var(--success);"></i>
                        <h3>Attendance Completed for Today</h3>
                        <p>You have already recorded both Time In and Time Out.</p>
                        <p><strong>Time In:</strong> <?php echo date('h:i A', strtotime($today_attendance['time_in'])); ?></p>
                        <p><strong>Time Out:</strong> <?php echo date('h:i A', strtotime($today_attendance['time_out'])); ?></p>
                        <p style="margin-top: 20px; color: var(--text-secondary);">You can generate a new QR code tomorrow for the next attendance day.</p>
                    </div>
                <?php elseif($active_session && $active_session['qr_token'] && $qr_url && !$time_out_recorded): ?>
                    <div class="qr-container">
                        <h3><i class="fas fa-qrcode"></i> Your Active QR Code</h3>
                        <div class="qr-code">
                            <img src="../includes/generate_qr.php?data=<?php echo urlencode($qr_url); ?>" alt="QR Code">
                        </div>
                        <div class="qr-info">
                            <h4>Scan this QR Code to Record Attendance</h4>
                            <p>📱 Scan this QR code using your phone or the camera above</p>
                            <p style="margin-top: 15px; color: var(--warning);">
                                <i class="fas fa-clock"></i> Expires: <?php echo date('h:i A', strtotime($active_session['expires_at'])); ?>
                            </p>
                            <p style="margin-top: 10px; font-size: 12px; color: var(--text-secondary);">
                                <i class="fas fa-info-circle"></i> 
                                <?php if(!$time_in_recorded): ?>
                                    First scan = Record Time In
                                <?php elseif($time_in_recorded && !$time_out_recorded): ?>
                                    Second scan = Record Time Out
                                <?php endif; ?>
                            </p>
                            <div style="margin-top: 15px;">
                                <a href="?generate=1" class="btn-generate btn-regenerate" onclick="return confirm('Generate a new QR code? The current one will expire.');">
                                    <i class="fas fa-sync-alt"></i> Generate New QR Code
                                </a>
                            </div>
                        </div>
                    </div>
                <?php elseif($time_in_recorded && !$time_out_recorded && !$active_session): ?>
                    <div class="qr-container">
                        <i class="fas fa-clock" style="font-size: 60px; color: var(--warning);"></i>
                        <h3>QR Code Expired or Not Generated</h3>
                        <p>You have recorded Time In but need to scan again for Time Out.</p>
                        <p>Please generate a new QR code to record your Time Out.</p>
                        <div style="margin-top: 20px;">
                            <a href="?generate=1" class="btn-generate">
                                <i class="fas fa-qrcode"></i> Generate QR Code for Time Out
                            </a>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="qr-container">
                        <h3><i class="fas fa-qrcode"></i> Generate QR Code</h3>
                        <p>Click the button below to generate a QR code for today's attendance.</p>
                        <p style="font-size: 12px; color: var(--text-secondary); margin: 10px 0;">
                            <i class="fas fa-info-circle"></i> The QR code will expire in 1 hour
                        </p>
                        <a href="?generate=1" class="btn-generate">
                            <i class="fas fa-qrcode"></i> Generate QR Code
                        </a>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Attendance History -->
            <h3 style="margin: 30px 0 15px;"><i class="fas fa-history"></i> Attendance History (Last 10 Records)</h3>
            <?php if(count($attendance_history) > 0): ?>
            <table class="attendance-table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Time In (PH Time)</th>
                        <th>Time Out (PH Time)</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($attendance_history as $record): ?>
                    <tr>
                        <td><?php echo date('M d, Y', strtotime($record['date'])); ?></td>
                        <td>
                            <?php 
                            if($record['time_in'] && $record['time_in'] != '00:00:00') {
                                $time_in = new DateTime($record['time_in'], new DateTimeZone('Asia/Manila'));
                                echo $time_in->format('h:i A');
                            } else {
                                echo '--:--';
                            }
                            ?>
                        </td>
                        <td>
                            <?php 
                            if($record['time_out'] && $record['time_out'] != '00:00:00') {
                                $time_out = new DateTime($record['time_out'], new DateTimeZone('Asia/Manila'));
                                echo $time_out->format('h:i A');
                            } else {
                                echo '--:--';
                            }
                            ?>
                        </td>
                        <td>
                            <span class="status-badge status-<?php echo $record['status']; ?>">
                                <?php echo $record['status']; ?>
                            </span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php else: ?>
            <div class="no-data">
                <i class="fas fa-calendar-alt"></i>
                <p>No attendance records found</p>
                <p style="font-size: 14px; margin-top: 10px;">Generate a QR code to start recording your attendance</p>
            </div>
            <?php endif; ?>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/jsqr@1.4.0/dist/jsQR.min.js"></script>
    <script>
        let video = null;
        let canvas = null;
        let ctx = null;
        let scanning = false;
        let animationId = null;
        let stream = null;
        
        const videoElement = document.getElementById('video');
        const canvasElement = document.getElementById('canvas');
        const scanLine = document.getElementById('scanLine');
        const startCameraBtn = document.getElementById('startCameraBtn');
        const stopCameraBtn = document.getElementById('stopCameraBtn');
        const scanResult = document.getElementById('scanResult');
        const scanResultText = document.getElementById('scanResultText');
        
        // Auto-start camera when page loads
        document.addEventListener('DOMContentLoaded', function() {
            startCamera();
        });
        
        // Tab switching
        function switchTab(tab) {
            document.querySelectorAll('.scanner-tab').forEach(t => t.classList.remove('active-tab'));
            document.querySelectorAll('.tab-btn').forEach(btn => btn.classList.remove('active'));
            
            if(tab === 'camera') {
                document.getElementById('cameraScannerTab').classList.add('active-tab');
                document.querySelector('.tab-btn:first-child').classList.add('active');
                setTimeout(function() {
                    startCamera();
                }, 100);
            } else {
                document.getElementById('uploadScannerTab').classList.add('active-tab');
                document.querySelector('.tab-btn:last-child').classList.add('active');
                stopCamera();
            }
            
            // Hide scan result when switching tabs
            scanResult.style.display = 'none';
        }
        
        // Start Camera
        async function startCamera() {
            try {
                // Check if camera is already running
                if (stream && stream.active) {
                    return;
                }
                
                const constraints = {
                    video: {
                        facingMode: "environment",
                        width: { ideal: 1280 },
                        height: { ideal: 720 }
                    }
                };
                
                stream = await navigator.mediaDevices.getUserMedia(constraints);
                videoElement.srcObject = stream;
                videoElement.setAttribute("playsinline", true);
                await videoElement.play();
                
                startCameraBtn.style.display = 'none';
                stopCameraBtn.style.display = 'inline-block';
                scanLine.style.display = 'block';
                
                // Give video time to initialize dimensions
                setTimeout(function() {
                    if (videoElement.videoWidth > 0) {
                        canvasElement.width = videoElement.videoWidth;
                        canvasElement.height = videoElement.videoHeight;
                        ctx = canvasElement.getContext('2d');
                        scanning = true;
                        scanQRCode();
                    }
                }, 500);
                
            } catch (err) {
                console.error("Camera error:", err);
                let errorMsg = "Cannot access camera. ";
                if (err.name === 'NotAllowedError') {
                    errorMsg += "Please grant camera permission in your browser settings.";
                } else if (err.name === 'NotFoundError') {
                    errorMsg += "No camera found on your device.";
                } else if (err.name === 'NotSupportedError') {
                    errorMsg += "Camera access requires HTTPS. For localhost, please use Chrome or Edge.";
                } else {
                    errorMsg += "Please ensure you have a camera connected and try again.";
                }
                showResult("error", errorMsg);
                startCameraBtn.style.display = 'inline-block';
                stopCameraBtn.style.display = 'none';
            }
        }
        
        // Stop Camera
        function stopCamera() {
            if (stream) {
                stream.getTracks().forEach(track => {
                    track.stop();
                });
                stream = null;
            }
            if (videoElement) {
                videoElement.srcObject = null;
            }
            scanning = false;
            if (animationId) {
                cancelAnimationFrame(animationId);
                animationId = null;
            }
            startCameraBtn.style.display = 'inline-block';
            stopCameraBtn.style.display = 'none';
            scanLine.style.display = 'none';
        }
        
        // Scan QR Code from video
        function scanQRCode() {
            if (!scanning || !videoElement || videoElement.readyState !== videoElement.HAVE_ENOUGH_DATA) {
                if (scanning) {
                    animationId = requestAnimationFrame(scanQRCode);
                }
                return;
            }
            
            try {
                if (videoElement.videoWidth > 0 && videoElement.videoHeight > 0) {
                    canvasElement.width = videoElement.videoWidth;
                    canvasElement.height = videoElement.videoHeight;
                    ctx.drawImage(videoElement, 0, 0, canvasElement.width, canvasElement.height);
                    const imageData = ctx.getImageData(0, 0, canvasElement.width, canvasElement.height);
                    const code = jsQR(imageData.data, imageData.width, imageData.height, {
                        inversionAttempts: "dontInvert",
                    });
                    
                    if (code && code.data) {
                        const qrData = code.data;
                        console.log("QR Code detected:", qrData);
                        
                        if (qrData.includes('process_attendance.php?token=')) {
                            const tokenMatch = qrData.match(/token=([a-f0-9]+)/);
                            if (tokenMatch) {
                                const token = tokenMatch[1];
                                stopCamera();
                                processAttendance(token);
                            } else {
                                showResult("error", "Invalid QR code format. Please scan a valid teacher attendance QR code.");
                            }
                        } else if (qrData.length === 32 && /^[a-f0-9]{32}$/.test(qrData)) {
                            processAttendance(qrData);
                        } else {
                            showResult("error", "Invalid QR code. Please scan a valid teacher attendance QR code.");
                        }
                    }
                }
            } catch (e) {
                console.error("Scan error:", e);
            }
            
            if (scanning) {
                animationId = requestAnimationFrame(scanQRCode);
            }
        }
        
        // Upload and scan image
        function uploadImage(input) {
            const file = input.files[0];
            if (!file) return;
            
            const reader = new FileReader();
            reader.onload = function(e) {
                const img = new Image();
                img.onload = function() {
                    const previewDiv = document.getElementById('previewImage');
                    const previewImg = document.getElementById('previewImg');
                    previewImg.src = e.target.result;
                    previewDiv.style.display = 'block';
                    
                    const tempCanvas = document.createElement('canvas');
                    const tempCtx = tempCanvas.getContext('2d');
                    tempCanvas.width = img.width;
                    tempCanvas.height = img.height;
                    tempCtx.drawImage(img, 0, 0, img.width, img.height);
                    const imageData = tempCtx.getImageData(0, 0, img.width, img.height);
                    const code = jsQR(imageData.data, imageData.width, imageData.height, {
                        inversionAttempts: "dontInvert",
                    });
                    
                    if (code && code.data) {
                        const qrData = code.data;
                        console.log("QR Code from image:", qrData);
                        
                        if (qrData.includes('process_attendance.php?token=')) {
                            const tokenMatch = qrData.match(/token=([a-f0-9]+)/);
                            if (tokenMatch) {
                                processAttendance(tokenMatch[1]);
                            } else {
                                showResult("error", "Invalid QR code format.");
                            }
                        } else if (qrData.length === 32 && /^[a-f0-9]{32}$/.test(qrData)) {
                            processAttendance(qrData);
                        } else {
                            showResult("error", "Invalid QR code. Please scan a valid teacher attendance QR code.");
                        }
                    } else {
                        showResult("error", "No QR code found in the image. Please try another photo.");
                    }
                };
                img.src = e.target.result;
            };
            reader.readAsDataURL(file);
        }
        
        function showResult(type, message) {
            scanResult.className = 'scan-result ' + type;
            scanResultText.innerHTML = '<i class="fas ' + 
                (type === 'success' ? 'fa-check-circle' : (type === 'error' ? 'fa-exclamation-circle' : 'fa-spinner fa-pulse')) + 
                '"></i> ' + message;
            scanResult.style.display = 'block';
            
            if (type === 'success') {
                setTimeout(() => {
                    location.reload();
                }, 2000);
            }
        }
        
        // Process attendance via AJAX
        function processAttendance(token) {
            showResult("loading", "Processing attendance...");
            
            fetch(`process_attendance.php?token=${token}`, {
                method: 'GET',
                headers: {
                    'Content-Type': 'application/json',
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showResult("success", data.message);
                } else {
                    showResult("error", data.message);
                }
            })
            .catch(error => {
                console.error("Error:", error);
                showResult("error", "An error occurred. Please try again.");
            });
        }
        
        window.addEventListener('beforeunload', function() {
            if (stream) {
                stream.getTracks().forEach(track => track.stop());
            }
        });
    </script>
    
    <?php include('../includes/chatbot_widget_teacher.php'); ?>
</body>
</html>