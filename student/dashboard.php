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

// Get enrollment status
$enrollment = $conn->query("SELECT e.*, g.grade_name 
                           FROM enrollments e 
                           LEFT JOIN grade_levels g ON e.grade_id = g.id 
                           WHERE e.student_id = '$student_id' 
                           ORDER BY e.id DESC LIMIT 1")->fetch_assoc();

// Get recent activities
$recent_activities = $conn->query("
    SELECT 'enrollment' as type, status, id as reference_id, school_year as reference, created_at 
    FROM enrollments 
    WHERE student_id = '$student_id' 
    ORDER BY id DESC 
    LIMIT 5
");

// Get attendance statistics
$attendance_stats = [
    'present' => 0,
    'absent' => 0,
    'late' => 0,
    'total' => 0
];

$attendance_query = "
    SELECT status, COUNT(*) as count
    FROM attendance
    WHERE student_id = '$student_id'
    GROUP BY status
";
$attendance_result = $conn->query($attendance_query);
if($attendance_result) {
    while($row = $attendance_result->fetch_assoc()) {
        $attendance_stats[strtolower($row['status'])] = $row['count'];
        $attendance_stats['total'] += $row['count'];
    }
}

$attendance_rate = $attendance_stats['total'] > 0 
    ? round(($attendance_stats['present'] / $attendance_stats['total']) * 100, 2) 
    : 0;

// Get subjects count
$subjects_count = 0;
if($enrollment && isset($enrollment['grade_id'])) {
    $subjects_result = $conn->query("SELECT COUNT(*) as count FROM subjects WHERE grade_id = '{$enrollment['grade_id']}'");
    if($subjects_result) {
        $subjects_count = $subjects_result->fetch_assoc()['count'];
    }
}

// FIXED: Removed the grades table query since it doesn't exist
$average_grade = '--'; // Placeholder value
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Dashboard - Placido L. Señor Senior High School</title>
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
            cursor: pointer;
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

        /* Section Title */
        .section-title {
            margin: 30px 0 20px;
        }

        .section-title h2 {
            color: var(--text-primary);
            font-size: 20px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .section-title h2 i {
            color: #0B4F2E;
        }

        /* Quick Actions Grid */
        .actions-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 25px;
            margin-bottom: 30px;
        }

        .action-card {
            background: white;
            border-radius: 20px;
            padding: 25px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            transition: all 0.3s;
            text-align: center;
        }

        .action-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 30px rgba(0, 0, 0, 0.1);
        }

        .action-icon {
            width: 70px;
            height: 70px;
            background: linear-gradient(135deg, #0B4F2E, #1a7a42);
            border-radius: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 30px;
            margin: 0 auto 20px;
        }

        .action-card h3 {
            color: var(--text-primary);
            font-size: 18px;
            margin-bottom: 10px;
        }

        .action-card p {
            color: var(--text-secondary);
            font-size: 14px;
            margin-bottom: 20px;
            line-height: 1.5;
        }

        .action-btn {
            display: inline-block;
            padding: 10px 25px;
            background: transparent;
            color: #0B4F2E;
            text-decoration: none;
            border-radius: 30px;
            font-size: 14px;
            font-weight: 600;
            transition: all 0.3s;
            border: 2px solid #0B4F2E;
        }

        .action-btn:hover {
            background: #0B4F2E;
            color: white;
        }

        /* Activity Card */
        .activity-card {
            background: white;
            border-radius: 20px;
            padding: 25px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            margin-bottom: 30px;
        }

        .activity-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 1px solid var(--border-color);
        }

        .activity-header h3 {
            color: var(--text-primary);
            font-size: 18px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .activity-header h3 i {
            color: #0B4F2E;
        }

        .activity-list {
            list-style: none;
        }

        .activity-item {
            display: flex;
            align-items: center;
            gap: 15px;
            padding: 15px 0;
            border-bottom: 1px solid var(--border-color);
        }

        .activity-item:last-child {
            border-bottom: none;
        }

        .activity-dot {
            width: 10px;
            height: 10px;
            border-radius: 50%;
        }

        .dot-pending {
            background: #ffc107;
            box-shadow: 0 0 0 3px rgba(255, 193, 7, 0.2);
        }

        .dot-approved {
            background: #28a745;
            box-shadow: 0 0 0 3px rgba(40, 167, 69, 0.2);
        }

        .dot-completed {
            background: #6c757d;
            box-shadow: 0 0 0 3px rgba(108, 117, 125, 0.2);
        }

        .activity-content {
            flex: 1;
        }

        .activity-title {
            color: var(--text-primary);
            font-weight: 500;
            margin-bottom: 5px;
        }

        .activity-time {
            color: var(--text-secondary);
            font-size: 12px;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .activity-status {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
        }

        .status-pending {
            background: rgba(255, 193, 7, 0.1);
            color: #ffc107;
        }

        .status-approved {
            background: rgba(40, 167, 69, 0.1);
            color: #28a745;
        }

        .status-rejected {
            background: rgba(220, 53, 69, 0.1);
            color: #dc3545;
        }

        /* School Info Footer */
        .school-info {
            text-align: center;
            margin-top: 40px;
            padding: 25px;
            background: white;
            border-radius: 15px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
        }

        .school-info p {
            color: var(--text-secondary);
            font-size: 13px;
            line-height: 1.8;
        }

        .school-info i {
            color: #0B4F2E;
            margin: 0 5px;
        }

        /* Chatbot Widget Styles */
        .chatbot-widget {
            position: fixed;
            bottom: 20px;
            right: 20px;
            z-index: 9999;
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        }
        
        /* Chat Button */
        .chat-button {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: linear-gradient(135deg, #0B4F2E, #1a7a42);
            color: white;
            border: none;
            cursor: pointer;
            box-shadow: 0 4px 15px rgba(11, 79, 46, 0.3);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            transition: all 0.3s ease;
            position: relative;
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0% {
                box-shadow: 0 0 0 0 rgba(11, 79, 46, 0.7);
            }
            70% {
                box-shadow: 0 0 0 10px rgba(11, 79, 46, 0);
            }
            100% {
                box-shadow: 0 0 0 0 rgba(11, 79, 46, 0);
            }
        }
        
        .chat-button:hover {
            transform: scale(1.1);
            box-shadow: 0 6px 20px rgba(11, 79, 46, 0.4);
            animation: none;
        }
        
        .chat-button .notification-badge {
            position: absolute;
            top: -5px;
            right: -5px;
            background: #FFD700;
            color: #0B4F2E;
            border-radius: 50%;
            width: 22px;
            height: 22px;
            font-size: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            border: 2px solid white;
            font-weight: bold;
        }
        
        /* Chat Window */
        .chat-window {
            position: absolute;
            bottom: 80px;
            right: 0;
            width: 350px;
            height: 500px;
            background: white;
            border-radius: 15px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
            display: none;
            flex-direction: column;
            overflow: hidden;
            animation: slideIn 0.3s ease;
            border: 1px solid rgba(11, 79, 46, 0.1);
        }
        
        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .chat-window.open {
            display: flex;
        }
        
        /* Chat Header */
        .chat-header {
            background: linear-gradient(135deg, #0B4F2E, #1a7a42);
            color: white;
            padding: 15px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .chat-header h3 {
            margin: 0;
            font-size: 16px;
            display: flex;
            align-items: center;
            gap: 8px;
            font-weight: 600;
        }
        
        .chat-header h3 i {
            color: #FFD700;
        }
        
        .chat-header .close-btn {
            background: none;
            border: none;
            color: white;
            font-size: 20px;
            cursor: pointer;
            padding: 0;
            width: 30px;
            height: 30px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            transition: background 0.3s;
        }
        
        .chat-header .close-btn:hover {
            background: rgba(255,255,255,0.2);
        }
        
        /* Chat Messages */
        .chat-messages {
            flex: 1;
            padding: 15px;
            overflow-y: auto;
            background: #f8f9fa;
            display: flex;
            flex-direction: column;
            gap: 10px;
        }
        
        .message {
            display: flex;
            margin-bottom: 10px;
            animation: fadeIn 0.3s ease;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .message.bot {
            justify-content: flex-start;
        }
        
        .message.user {
            justify-content: flex-end;
        }
        
        .message-content {
            max-width: 80%;
            padding: 12px 15px;
            border-radius: 15px;
            font-size: 14px;
            line-height: 1.4;
        }
        
        .bot .message-content {
            background: white;
            color: #333;
            border-bottom-left-radius: 5px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        
        .user .message-content {
            background: linear-gradient(135deg, #0B4F2E, #1a7a42);
            color: white;
            border-bottom-right-radius: 5px;
        }
        
        .message-time {
            font-size: 10px;
            margin-top: 5px;
            opacity: 0.7;
            display: block;
        }
        
        /* Quick Replies */
        .quick-replies {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            padding: 10px 15px;
            background: white;
            border-top: 1px solid #e0e0e0;
        }
        
        .quick-reply-btn {
            padding: 8px 12px;
            background: #f0f0f0;
            border: none;
            border-radius: 20px;
            font-size: 12px;
            color: #0B4F2E;
            cursor: pointer;
            transition: all 0.3s;
            white-space: nowrap;
            font-weight: 500;
        }
        
        .quick-reply-btn:hover {
            background: #0B4F2E;
            color: white;
        }
        
        /* Chat Input */
        .chat-input-container {
            padding: 15px;
            background: white;
            border-top: 1px solid #e0e0e0;
            display: flex;
            gap: 10px;
        }
        
        .chat-input-container input {
            flex: 1;
            padding: 12px;
            border: 2px solid #e0e0e0;
            border-radius: 25px;
            outline: none;
            font-size: 14px;
            transition: border-color 0.3s;
            font-family: 'Inter', sans-serif;
        }
        
        .chat-input-container input:focus {
            border-color: #0B4F2E;
        }
        
        .chat-input-container button {
            width: 45px;
            height: 45px;
            border-radius: 50%;
            background: linear-gradient(135deg, #0B4F2E, #1a7a42);
            color: white;
            border: none;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
            transition: all 0.3s;
        }
        
        .chat-input-container button:hover {
            transform: scale(1.1);
        }
        
        .chat-input-container button:disabled {
            background: #cbd5e0;
            cursor: not-allowed;
        }
        
        /* Typing Indicator */
        .typing-indicator {
            display: flex;
            gap: 5px;
            padding: 12px 15px;
            background: white;
            border-radius: 15px;
            width: fit-content;
            margin-bottom: 10px;
        }
        
        .typing-indicator span {
            width: 8px;
            height: 8px;
            background: #0B4F2E;
            border-radius: 50%;
            animation: typing 1s infinite ease-in-out;
        }
        
        .typing-indicator span:nth-child(2) {
            animation-delay: 0.2s;
        }
        
        .typing-indicator span:nth-child(3) {
            animation-delay: 0.4s;
        }
        
        @keyframes typing {
            0%, 60%, 100% { transform: translateY(0); }
            30% { transform: translateY(-10px); }
        }

        /* Responsive */
        @media (max-width: 1200px) {
            .stats-container {
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
            
            .stats-container {
                grid-template-columns: 1fr;
            }
            
            .actions-grid {
                grid-template-columns: 1fr;
            }
            
            .activity-item {
                flex-direction: column;
                text-align: center;
            }
            
            .activity-status {
                align-self: center;
            }

            .chat-window {
                width: 300px;
                height: 450px;
                right: 0;
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
                    <li><a href="dashboard.php" class="active"><i class="fas fa-tachometer-alt"></i> <span>Dashboard</span></a></li>
                    <li><a href="profile.php"><i class="fas fa-user"></i> <span>My Profile</span></a></li>
                    <li><a href="schedule.php"><i class="fas fa-calendar-alt"></i> <span>Class Schedule</span></a></li>
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
                <h1>Dashboard</h1>
                <p>Welcome to your student dashboard. Manage your enrollment and track your progress.</p>
            </div>

            <!-- Welcome Card -->
            <div class="welcome-card">
                <div class="welcome-text">
                    <h2>Hi, <?php echo htmlspecialchars(explode(' ', $student_name)[0]); ?>!</h2>
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

            <!-- Statistics Cards -->
            <div class="stats-container">
                <div class="stat-card" onclick="window.location.href='enrollment.php'">
                    <div class="stat-header">
                        <h3>Enrollment Status</h3>
                        <div class="stat-icon">
                            <i class="fas fa-graduation-cap"></i>
                        </div>
                    </div>
                    <div class="stat-number"><?php echo $enrollment ? htmlspecialchars($enrollment['grade_name']) : 'Not Enrolled'; ?></div>
                    <div class="stat-label" style="color: <?php 
                        if(!$enrollment) echo '#6c757d';
                        else if($enrollment['status'] == 'Pending') echo '#ffc107';
                        else if($enrollment['status'] == 'Enrolled') echo '#28a745';
                        else echo '#dc3545';
                    ?>;">
                        <i class="fas fa-circle" style="font-size: 8px; margin-right: 5px;"></i>
                        <?php echo $enrollment ? $enrollment['status'] : 'No Record'; ?>
                    </div>
                </div>
                
                <div class="stat-card" onclick="window.location.href='subjects.php'">
                    <div class="stat-header">
                        <h3>Subjects</h3>
                        <div class="stat-icon">
                            <i class="fas fa-book"></i>
                        </div>
                    </div>
                    <div class="stat-number"><?php echo $subjects_count; ?></div>
                    <div class="stat-label">Current Subjects</div>
                </div>
                
                <div class="stat-card" onclick="window.location.href='attendance.php'">
                    <div class="stat-header">
                        <h3>Attendance</h3>
                        <div class="stat-icon">
                            <i class="fas fa-calendar-check"></i>
                        </div>
                    </div>
                    <div class="stat-number"><?php echo $attendance_rate; ?>%</div>
                    <div class="stat-label">Overall Rate</div>
                </div>
                
                <div class="stat-card" onclick="window.location.href='grades.php'">
                    <div class="stat-header">
                        <h3>Average Grade</h3>
                        <div class="stat-icon">
                            <i class="fas fa-star"></i>
                        </div>
                    </div>
                    <div class="stat-number"><?php echo $average_grade; ?></div>
                    <div class="stat-label">Overall Average</div>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="section-title">
                <h2><i class="fas fa-bolt"></i> Quick Actions</h2>
            </div>

            <div class="actions-grid">
                <div class="action-card">
                    <div class="action-icon">
                        <i class="fas fa-graduation-cap"></i>
                    </div>
                    <h3>Enrollment</h3>
                    <p><?php echo $enrollment ? 'Update your enrollment information' : 'Enroll now for the current school year'; ?></p>
                    <a href="enrollment.php" class="action-btn">
                        <?php echo $enrollment ? 'Update' : 'Enroll Now'; ?>
                    </a>
                </div>
                
                <div class="action-card">
                    <div class="action-icon">
                        <i class="fas fa-calendar-alt"></i>
                    </div>
                    <h3>Class Schedule</h3>
                    <p>View your weekly class schedule and subjects</p>
                    <a href="schedule.php" class="action-btn">View Schedule</a>
                </div>
                
                <div class="action-card">
                    <div class="action-icon">
                        <i class="fas fa-star"></i>
                    </div>
                    <h3>My Grades</h3>
                    <p>Check your grades and academic performance</p>
                    <a href="grades.php" class="action-btn">View Grades</a>
                </div>
                
                <div class="action-card">
                    <div class="action-icon">
                        <i class="fas fa-calendar-check"></i>
                    </div>
                    <h3>Attendance</h3>
                    <p>View your attendance records and statistics</p>
                    <a href="attendance.php" class="action-btn">View Attendance</a>
                </div>
            </div>

            <!-- Recent Activity -->
            <div class="section-title">
                <h2><i class="fas fa-history"></i> Recent Enrollment Activity</h2>
            </div>

            <div class="activity-card">
                <div class="activity-header">
                    <h3><i class="fas fa-file-signature"></i> Enrollment History</h3>
                    <?php if($recent_activities && $recent_activities->num_rows > 0): ?>
                        <span class="stat-label">Last 5 records</span>
                    <?php endif; ?>
                </div>
                <div class="activity-list">
                    <?php if($recent_activities && $recent_activities->num_rows > 0): ?>
                        <?php while($activity = $recent_activities->fetch_assoc()): ?>
                            <div class="activity-item">
                                <div class="activity-dot 
                                    <?php 
                                        if($activity['status'] == 'Pending') echo 'dot-pending';
                                        else if($activity['status'] == 'Enrolled') echo 'dot-approved';
                                        else echo 'dot-completed';
                                    ?>">
                                </div>
                                <div class="activity-content">
                                    <div class="activity-title">
                                        Enrollment Request - <?php echo htmlspecialchars($activity['reference']); ?>
                                    </div>
                                    <div class="activity-time">
                                        <i class="far fa-clock"></i>
                                        School Year: <?php echo htmlspecialchars($activity['reference']); ?>
                                    </div>
                                </div>
                                <div class="activity-status 
                                    <?php 
                                        if($activity['status'] == 'Pending') echo 'status-pending';
                                        else if($activity['status'] == 'Enrolled') echo 'status-approved';
                                        else echo 'status-rejected';
                                    ?>">
                                    <?php echo $activity['status']; ?>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <div class="activity-item">
                            <div class="activity-content" style="text-align: center; padding: 30px;">
                                <i class="fas fa-file-signature" style="font-size: 40px; color: var(--text-secondary); opacity: 0.3; margin-bottom: 10px;"></i>
                                <p style="color: var(--text-secondary);">No enrollment history found.</p>
                                <a href="enrollment.php" style="color: #0B4F2E; text-decoration: none; font-weight: 500; display: inline-block; margin-top: 10px;">
                                    Enroll Now <i class="fas fa-arrow-right"></i>
                                </a>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- School Information -->
            <div class="school-info">
                <p>
                    <i class="fas fa-map-marker-alt"></i> PLACIDO L. SEÑOR SENIOR HIGH SCHOOL<br>
                    Langtad, City of Naga, Cebu 6037<br>
                    <i class="fas fa-phone"></i> (032) 123-4567 · <i class="fas fa-envelope"></i> info@plsshs.edu.ph
                </p>
            </div>
        </div>
    </div>

    <!-- Floating Chatbot Widget -->
    <div class="chatbot-widget" id="chatbotWidget">
        <!-- Chat Button -->
        <button class="chat-button" id="chatButton" onclick="toggleChat()">
            💬
            <span class="notification-badge" id="notificationBadge" style="display: none;">1</span>
        </button>
        
        <!-- Chat Window -->
        <div class="chat-window" id="chatWindow">
            <div class="chat-header">
                <h3>
                    <i class="fas fa-robot"></i>
                    Student Assistant
                </h3>
                <button class="close-btn" onclick="toggleChat()">×</button>
            </div>
            
            <div class="chat-messages" id="chatMessages">
                <!-- Welcome Message -->
                <div class="message bot">
                    <div class="message-content">
                        👋 Hi <?php echo htmlspecialchars(explode(' ', $student_name)[0]); ?>! I'm your student assistant. How can I help you today?
                        <span class="message-time"><?php echo date('h:i A'); ?></span>
                    </div>
                </div>
            </div>
            
            <!-- Quick Replies -->
            <div class="quick-replies" id="quickReplies">
                <button class="quick-reply-btn" onclick="setQuestion('How to enroll?')">📝 Enrollment</button>
                <button class="quick-reply-btn" onclick="setQuestion('My schedule')">📅 Schedule</button>
                <button class="quick-reply-btn" onclick="setQuestion('My attendance')">📊 Attendance</button>
                <button class="quick-reply-btn" onclick="setQuestion('My grades')">📚 Grades</button>
                <button class="quick-reply-btn" onclick="setQuestion('Office hours')">⏰ Office Hours</button>
            </div>
            
            <!-- Chat Input -->
            <div class="chat-input-container">
                <input type="text" id="chatInput" placeholder="Type your question..." onkeypress="handleKeyPress(event)">
                <button onclick="sendMessage()" id="sendButton">➤</button>
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

        // Chatbot functionality
        let chatHistory = [];
        let isTyping = false;
        const studentName = "<?php echo htmlspecialchars(explode(' ', $student_name)[0]); ?>";

        // Toggle chat window
        function toggleChat() {
            const chatWindow = document.getElementById('chatWindow');
            const chatButton = document.getElementById('chatButton');
            
            chatWindow.classList.toggle('open');
            
            // Hide notification when opened
            if(chatWindow.classList.contains('open')) {
                document.getElementById('notificationBadge').style.display = 'none';
            }
        }

        // Set question from quick reply
        function setQuestion(question) {
            document.getElementById('chatInput').value = question;
            sendMessage();
        }

        // Send message
        function sendMessage() {
            const input = document.getElementById('chatInput');
            const question = input.value.trim();
            
            if(question === '') return;
            
            // Disable input and button
            input.disabled = true;
            document.getElementById('sendButton').disabled = true;
            
            // Add user message
            addMessage(question, 'user');
            input.value = '';
            
            // Show typing indicator
            showTypingIndicator();
            
            // Simulate bot response
            setTimeout(() => {
                // Remove typing indicator
                hideTypingIndicator();
                
                // Generate response based on question
                let response = getBotResponse(question);
                
                // Add bot response
                addMessage(response, 'bot');
                
                // Re-enable input and button
                input.disabled = false;
                document.getElementById('sendButton').disabled = false;
                input.focus();
            }, 1500);
        }

        // Handle enter key
        function handleKeyPress(event) {
            if(event.key === 'Enter') {
                sendMessage();
            }
        }

        // Add message to chat
        function addMessage(text, sender) {
            const messagesDiv = document.getElementById('chatMessages');
            const messageDiv = document.createElement('div');
            messageDiv.className = `message ${sender}`;
            
            const time = new Date().toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' });
            
            messageDiv.innerHTML = `
                <div class="message-content">
                    ${text}
                    <span class="message-time">${time}</span>
                </div>
            `;
            
            messagesDiv.appendChild(messageDiv);
            messagesDiv.scrollTop = messagesDiv.scrollHeight;
            
            // Add to history
            chatHistory.push({ text, sender, time });
        }

        // Show typing indicator
        function showTypingIndicator() {
            const messagesDiv = document.getElementById('chatMessages');
            const typingDiv = document.createElement('div');
            typingDiv.className = 'message bot';
            typingDiv.id = 'typingIndicator';
            typingDiv.innerHTML = `
                <div class="typing-indicator">
                    <span></span>
                    <span></span>
                    <span></span>
                </div>
            `;
            messagesDiv.appendChild(typingDiv);
            messagesDiv.scrollTop = messagesDiv.scrollHeight;
        }

        // Hide typing indicator
        function hideTypingIndicator() {
            const typingIndicator = document.getElementById('typingIndicator');
            if(typingIndicator) {
                typingIndicator.remove();
            }
        }

        // Get bot response based on keywords
        function getBotResponse(question) {
            question = question.toLowerCase();
            
            // Enrollment related
            if(question.includes('enroll') || question.includes('enrollment')) {
                return "You can enroll by going to the <strong>Enrollment</strong> page from your dashboard. Click on the 'Enroll Now' button in the Quick Actions section!";
            }
            
            // Schedule related
            else if(question.includes('schedule') || question.includes('class') || question.includes('time')) {
                return "Your class schedule is available in the <strong>Class Schedule</strong> page. You can access it from the sidebar menu under 'Class Schedule'.";
            }
            
            // Attendance related
            else if(question.includes('attendance')) {
                let attendanceRate = <?php echo $attendance_rate; ?>;
                return `Your current attendance rate is <strong>${attendanceRate}%</strong>. You can view detailed attendance records in the <strong>Attendance</strong> page.`;
            }
            
            // Grades related
            else if(question.includes('grade') || question.includes('grades') || question.includes('score')) {
                return "You can check your grades in the <strong>My Grades</strong> page. It shows your performance in all subjects.";
            }
            
            // Office hours related
            else if(question.includes('office') || question.includes('hour') || question.includes('teacher')) {
                return "Office hours are typically Monday to Friday, 8:00 AM to 5:00 PM. You can check specific teacher schedules in the faculty directory.";
            }
            
            // Profile related
            else if(question.includes('profile') || question.includes('account')) {
                return "You can view and edit your profile information in the <strong>My Profile</strong> page.";
            }
            
            // Default response
            else {
                const responses = [
                    "I'm here to help! Could you please provide more details about what you need?",
                    "For specific questions about your account, please check the My Profile page.",
                    "You can find most information in the dashboard sections. Which area would you like to know more about?",
                    "I can help you with enrollment, schedules, attendance, and grades. What would you like to know?",
                    "Feel free to ask about enrollment, class schedules, attendance tracking, or grades!"
                ];
                return responses[Math.floor(Math.random() * responses.length)];
            }
        }

        // Show notification after 30 seconds if chat not opened
        setTimeout(() => {
            if(!document.getElementById('chatWindow').classList.contains('open')) {
                document.getElementById('notificationBadge').style.display = 'flex';
            }
        }, 30000);
    </script>
</body>
</html>