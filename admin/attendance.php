<?php
session_start();
include("../config/database.php");

// Check if user is admin
if(!isset($_SESSION['user']) || $_SESSION['user']['role'] != 'Admin'){
    header("Location: ../auth/login.php");
    exit();
}

$admin_name = $_SESSION['user']['fullname'];
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

// Handle delete action for student attendance
if(isset($_GET['delete'])) {
    $delete_id = $_GET['delete'];
    $delete = $conn->query("DELETE FROM attendance WHERE id = '$delete_id'");
    if($delete) {
        $success_message = "Attendance record deleted successfully!";
    } else {
        $error_message = "Error deleting attendance record.";
    }
}

// Handle delete action for teacher attendance
if(isset($_GET['delete_teacher'])) {
    $delete_id = $_GET['delete_teacher'];
    $delete = $conn->query("DELETE FROM teacher_attendance WHERE id = '$delete_id'");
    if($delete) {
        $success_message = "Teacher attendance record deleted successfully!";
    } else {
        $error_message = "Error deleting teacher attendance record.";
    }
}

// Get filter parameters for student attendance
$date_filter = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');
$grade_filter = isset($_GET['grade']) ? $_GET['grade'] : '';
$section_filter = isset($_GET['section']) ? $_GET['section'] : '';
$subject_filter = isset($_GET['subject']) ? $_GET['subject'] : '';
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';

// Get filter parameters for teacher attendance
$teacher_date_filter = isset($_GET['teacher_date']) ? $_GET['teacher_date'] : date('Y-m-d');
$teacher_status_filter = isset($_GET['teacher_status']) ? $_GET['teacher_status'] : '';
$teacher_department_filter = isset($_GET['department']) ? $_GET['department'] : '';

// Build the student attendance query
$query = "
    SELECT a.*, 
           u.fullname as student_name,
           u.id_number as student_id_number,
           sub.subject_name,
           g.grade_name,
           s.section_name
    FROM attendance a
    JOIN users u ON a.student_id = u.id
    JOIN subjects sub ON a.subject_id = sub.id
    JOIN grade_levels g ON sub.grade_id = g.id
    LEFT JOIN sections s ON u.id = s.adviser_id
    WHERE 1=1
";

if(!empty($date_filter)) {
    $query .= " AND a.date = '$date_filter'";
}

if(!empty($grade_filter)) {
    $query .= " AND sub.grade_id = '$grade_filter'";
}

if(!empty($subject_filter)) {
    $query .= " AND a.subject_id = '$subject_filter'";
}

if(!empty($status_filter)) {
    $query .= " AND a.status = '$status_filter'";
}

$query .= " ORDER BY a.date DESC, a.created_at DESC";

$attendance_records = $conn->query($query);

// Build the teacher attendance query
$teacher_query = "
    SELECT ta.*, 
           u.fullname as teacher_name,
           u.id_number as teacher_id_number,
           u.email as teacher_email
    FROM teacher_attendance ta
    JOIN users u ON ta.teacher_id = u.id
    WHERE u.role = 'Teacher'
";

if(!empty($teacher_date_filter)) {
    $teacher_query .= " AND ta.date = '$teacher_date_filter'";
}

if(!empty($teacher_status_filter)) {
    $teacher_query .= " AND ta.status = '$teacher_status_filter'";
}

$teacher_query .= " ORDER BY ta.date DESC, ta.created_at DESC";

$teacher_attendance_records = $conn->query($teacher_query);

// Get student attendance statistics
$today = date('Y-m-d');
$total_today = $conn->query("SELECT COUNT(*) as count FROM attendance WHERE date = '$today'")->fetch_assoc()['count'];
$present_today = $conn->query("SELECT COUNT(*) as count FROM attendance WHERE date = '$today' AND status = 'Present'")->fetch_assoc()['count'];
$absent_today = $conn->query("SELECT COUNT(*) as count FROM attendance WHERE date = '$today' AND status = 'Absent'")->fetch_assoc()['count'];
$late_today = $conn->query("SELECT COUNT(*) as count FROM attendance WHERE date = '$today' AND status = 'Late'")->fetch_assoc()['count'];

// Get teacher attendance statistics
$teacher_today = $conn->query("SELECT COUNT(*) as count FROM teacher_attendance WHERE date = '$today'")->fetch_assoc()['count'] ?? 0;
$teacher_present_today = $conn->query("SELECT COUNT(*) as count FROM teacher_attendance WHERE date = '$today' AND status = 'Present'")->fetch_assoc()['count'] ?? 0;
$teacher_absent_today = $conn->query("SELECT COUNT(*) as count FROM teacher_attendance WHERE date = '$today' AND status = 'Absent'")->fetch_assoc()['count'] ?? 0;
$teacher_late_today = $conn->query("SELECT COUNT(*) as count FROM teacher_attendance WHERE date = '$today' AND status = 'Late'")->fetch_assoc()['count'] ?? 0;

// Get grade levels for filter
$grade_levels = $conn->query("SELECT * FROM grade_levels ORDER BY id");

// Get subjects for filter
$subjects = $conn->query("SELECT * FROM subjects ORDER BY subject_name");

// Get sections for filter
$sections = $conn->query("SELECT s.*, g.grade_name FROM sections s JOIN grade_levels g ON s.grade_id = g.id ORDER BY g.id, s.section_name");

// Get teachers for filter
$teachers = $conn->query("SELECT id, fullname FROM users WHERE role = 'Teacher' ORDER BY fullname");

// Check if teacher_attendance table exists, if not create it
$table_check = $conn->query("SHOW TABLES LIKE 'teacher_attendance'");
if($table_check->num_rows == 0) {
    $create_table = "
        CREATE TABLE IF NOT EXISTS `teacher_attendance` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `teacher_id` int(11) NOT NULL,
            `date` date NOT NULL,
            `time_in` time DEFAULT NULL,
            `time_out` time DEFAULT NULL,
            `status` enum('Present','Absent','Late') NOT NULL,
            `remarks` text DEFAULT NULL,
            `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
            PRIMARY KEY (`id`),
            KEY `teacher_id` (`teacher_id`),
            CONSTRAINT `teacher_attendance_ibfk_1` FOREIGN KEY (`teacher_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
    ";
    $conn->query($create_table);
}

// Get active tab
$active_tab = isset($_GET['tab']) ? $_GET['tab'] : 'students';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Attendance Management - Admin Dashboard</title>
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
            letter-spacing: 1px;
            padding-bottom: 20px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.2);
        }

        .sidebar h2 i {
            color: var(--accent);
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

        .admin-info h3 {
            font-size: 18px;
            margin-bottom: 5px;
            color: var(--accent);
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

        /* Tab Navigation */
        .tab-nav {
            background: white;
            border-radius: 15px;
            padding: 5px;
            margin-bottom: 30px;
            display: flex;
            gap: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
        }

        .tab-btn {
            flex: 1;
            padding: 15px 25px;
            border: none;
            border-radius: 10px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            background: transparent;
            color: var(--text-secondary);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }

        .tab-btn i {
            font-size: 18px;
        }

        .tab-btn.active {
            background: var(--primary);
            color: white;
        }

        .tab-btn:hover:not(.active) {
            background: var(--hover-color);
            color: var(--primary);
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
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
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

        /* Date Navigation */
        .date-nav {
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

        .date-display {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .date-display h3 {
            color: var(--text-primary);
            font-size: 18px;
            font-weight: 600;
        }

        .date-picker {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .date-input {
            padding: 10px 15px;
            border: 2px solid var(--border-color);
            border-radius: 10px;
            font-size: 14px;
            background: #f8f9fa;
        }

        .date-input:focus {
            border-color: var(--primary);
            outline: none;
        }

        .btn-date {
            background: var(--primary);
            color: white;
            padding: 10px 20px;
            border-radius: 10px;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-size: 14px;
            font-weight: 500;
            border: none;
            cursor: pointer;
        }

        .btn-date:hover {
            background: var(--primary-dark);
        }

        /* Filters Bar */
        .filters-bar {
            background: white;
            border-radius: 15px;
            padding: 20px 25px;
            margin-bottom: 30px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
        }

        .filters-form {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
            align-items: flex-end;
        }

        .filter-group {
            flex: 1;
            min-width: 150px;
        }

        .filter-group label {
            display: block;
            margin-bottom: 8px;
            color: var(--text-secondary);
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .filter-select, .filter-input {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid var(--border-color);
            border-radius: 10px;
            font-size: 14px;
            background: #f8f9fa;
            cursor: pointer;
        }

        .filter-select:focus, .filter-input:focus {
            border-color: var(--primary);
            outline: none;
            background: white;
        }

        .btn-filter {
            background: var(--primary);
            color: white;
            padding: 12px 25px;
            border-radius: 10px;
            border: none;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            height: 45px;
        }

        .btn-filter:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(11, 79, 46, 0.3);
        }

        .btn-reset {
            background: #f8f9fa;
            color: var(--text-secondary);
            padding: 12px 25px;
            border-radius: 10px;
            border: 2px solid var(--border-color);
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            height: 45px;
        }

        .btn-reset:hover {
            border-color: var(--primary);
            color: var(--primary);
        }

        .btn-add {
            background: var(--primary);
            color: white;
            padding: 12px 25px;
            border-radius: 10px;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-weight: 600;
            border: none;
            cursor: pointer;
            height: 45px;
        }

        .btn-add:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(11, 79, 46, 0.3);
        }

        /* Table Card */
        .table-card {
            background: white;
            border-radius: 20px;
            padding: 25px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            margin-bottom: 30px;
        }

        .table-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            flex-wrap: wrap;
            gap: 15px;
        }

        .table-header h3 {
            color: var(--text-primary);
            font-size: 18px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .table-header h3 i {
            color: var(--primary);
        }

        .table-container {
            overflow-x: auto;
        }

        .attendance-table {
            width: 100%;
            border-collapse: collapse;
        }

        .attendance-table th {
            text-align: left;
            padding: 15px;
            background: #f8f9fa;
            color: var(--text-secondary);
            font-weight: 600;
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-bottom: 2px solid var(--border-color);
        }

        .attendance-table td {
            padding: 15px;
            border-bottom: 1px solid var(--border-color);
            color: var(--text-primary);
        }

        .attendance-table tbody tr:hover {
            background: var(--hover-color);
        }

        .person-info {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .person-avatar {
            width: 40px;
            height: 40px;
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
            font-size: 16px;
        }

        .person-details h4 {
            font-size: 15px;
            margin-bottom: 3px;
            color: var(--text-primary);
        }

        .person-details span {
            font-size: 12px;
            color: var(--text-secondary);
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .badge {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            display: inline-block;
        }

        .badge-present {
            background: rgba(40, 167, 69, 0.1);
            color: var(--success);
        }

        .badge-absent {
            background: rgba(220, 53, 69, 0.1);
            color: var(--danger);
        }

        .badge-late {
            background: rgba(255, 193, 7, 0.1);
            color: var(--warning);
        }

        .grade-tag {
            background: rgba(11, 79, 46, 0.1);
            color: var(--primary);
            padding: 3px 8px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 500;
            display: inline-block;
        }

        .subject-tag {
            background: rgba(76, 201, 240, 0.1);
            color: #4cc9f0;
            padding: 3px 8px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 500;
            display: inline-block;
            margin-left: 5px;
        }

        .time-tag {
            background: rgba(108, 117, 125, 0.1);
            color: #6c757d;
            padding: 3px 8px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 500;
            display: inline-block;
        }

        .action-btns {
            display: flex;
            gap: 8px;
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

        .btn-delete:hover {
            color: var(--danger);
        }

        .btn-edit:hover {
            color: var(--warning);
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

        .no-data h3 {
            color: var(--text-primary);
            margin-bottom: 10px;
        }

        /* Modal */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 1000;
            justify-content: center;
            align-items: center;
        }

        .modal.active {
            display: flex;
        }

        .modal-content {
            background: white;
            border-radius: 15px;
            width: 90%;
            max-width: 500px;
            max-height: 90vh;
            overflow-y: auto;
        }

        .modal-header {
            padding: 20px;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .modal-header h3 {
            display: flex;
            align-items: center;
            gap: 10px;
            color: var(--text-primary);
        }

        .close-modal {
            background: none;
            border: none;
            font-size: 24px;
            cursor: pointer;
            color: var(--text-secondary);
        }

        .modal-body {
            padding: 20px;
        }

        .form-group {
            margin-bottom: 15px;
        }

        .form-group label {
            display: block;
            margin-bottom: 5px;
            color: var(--text-secondary);
            font-weight: 500;
        }

        .form-group input, .form-group select, .form-group textarea {
            width: 100%;
            padding: 10px;
            border: 2px solid var(--border-color);
            border-radius: 8px;
            font-size: 14px;
        }

        .form-group input:focus, .form-group select:focus, .form-group textarea:focus {
            border-color: var(--primary);
            outline: none;
        }

        .modal-footer {
            padding: 20px;
            border-top: 1px solid var(--border-color);
            display: flex;
            justify-content: flex-end;
            gap: 10px;
        }

        .btn-save {
            background: var(--primary);
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
        }

        .btn-cancel {
            background: #e9ecef;
            color: var(--text-primary);
            border: none;
            padding: 10px 20px;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
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
            
            .stats-container {
                grid-template-columns: 1fr;
            }
            
            .tab-nav {
                flex-direction: column;
            }
            
            .date-nav {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .filters-form {
                flex-direction: column;
            }
            
            .filter-group {
                width: 100%;
            }
            
            .btn-filter, .btn-reset, .btn-add {
                width: 100%;
                justify-content: center;
            }
            
            .person-info {
                flex-direction: column;
                text-align: center;
            }
            
            .action-btns {
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
                    <li><a href="attendance.php" class="active"><i class="fas fa-calendar-check"></i> <span>Attendance</span></a></li>
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
                <h1>Attendance Management</h1>
                <p>View and manage student and teacher attendance records</p>
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

            <!-- Tab Navigation -->
            <div class="tab-nav">
                <a href="?tab=students" class="tab-btn <?php echo $active_tab == 'students' ? 'active' : ''; ?>">
                    <i class="fas fa-user-graduate"></i> Student Attendance
                </a>
                <a href="?tab=teachers" class="tab-btn <?php echo $active_tab == 'teachers' ? 'active' : ''; ?>">
                    <i class="fas fa-chalkboard-teacher"></i> Teacher Attendance
                </a>
            </div>

            <?php if($active_tab == 'students'): ?>
                <!-- STUDENT ATTENDANCE SECTION -->
                
                <!-- Student Attendance Statistics -->
                <div class="stats-container">
                    <div class="stat-card">
                        <div class="stat-header">
                            <h3>Today's Total</h3>
                            <div class="stat-icon">
                                <i class="fas fa-calendar-check"></i>
                            </div>
                        </div>
                        <div class="stat-number"><?php echo $total_today; ?></div>
                        <div class="stat-label">Student records</div>
                    </div>

                    <div class="stat-card">
                        <div class="stat-header">
                            <h3>Present</h3>
                            <div class="stat-icon">
                                <i class="fas fa-user-check"></i>
                            </div>
                        </div>
                        <div class="stat-number"><?php echo $present_today; ?></div>
                        <div class="stat-label">Students present</div>
                    </div>

                    <div class="stat-card">
                        <div class="stat-header">
                            <h3>Absent</h3>
                            <div class="stat-icon">
                                <i class="fas fa-user-times"></i>
                            </div>
                        </div>
                        <div class="stat-number"><?php echo $absent_today; ?></div>
                        <div class="stat-label">Students absent</div>
                    </div>

                    <div class="stat-card">
                        <div class="stat-header">
                            <h3>Late</h3>
                            <div class="stat-icon">
                                <i class="fas fa-clock"></i>
                            </div>
                        </div>
                        <div class="stat-number"><?php echo $late_today; ?></div>
                        <div class="stat-label">Students late</div>
                    </div>
                </div>

                <!-- Date Navigation -->
                <div class="date-nav">
                    <div class="date-display">
                        <h3><i class="fas fa-calendar-alt"></i> Student Attendance Records</h3>
                    </div>
                    <div class="date-picker">
                        <form method="GET" action="" style="display: flex; gap: 10px;">
                            <input type="hidden" name="tab" value="students">
                            <input type="date" name="date" class="date-input" value="<?php echo $date_filter; ?>">
                            <button type="submit" class="btn-date">
                                <i class="fas fa-search"></i> View Date
                            </button>
                        </form>
                    </div>
                </div>

                <!-- Filters Bar -->
                <div class="filters-bar">
                    <form method="GET" action="" class="filters-form">
                        <input type="hidden" name="tab" value="students">
                        <input type="hidden" name="date" value="<?php echo $date_filter; ?>">
                        
                        <div class="filter-group">
                            <label>Grade Level</label>
                            <select name="grade" class="filter-select">
                                <option value="">All Grades</option>
                                <?php 
                                $grade_levels->data_seek(0);
                                while($grade = $grade_levels->fetch_assoc()): 
                                ?>
                                    <option value="<?php echo $grade['id']; ?>" <?php echo $grade_filter == $grade['id'] ? 'selected' : ''; ?>>
                                        <?php echo $grade['grade_name']; ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>

                        <div class="filter-group">
                            <label>Subject</label>
                            <select name="subject" class="filter-select">
                                <option value="">All Subjects</option>
                                <?php 
                                $subjects->data_seek(0);
                                while($subject = $subjects->fetch_assoc()): 
                                ?>
                                    <option value="<?php echo $subject['id']; ?>" <?php echo $subject_filter == $subject['id'] ? 'selected' : ''; ?>>
                                        <?php echo $subject['subject_name']; ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>

                        <div class="filter-group">
                            <label>Status</label>
                            <select name="status" class="filter-select">
                                <option value="">All Status</option>
                                <option value="Present" <?php echo $status_filter == 'Present' ? 'selected' : ''; ?>>Present</option>
                                <option value="Absent" <?php echo $status_filter == 'Absent' ? 'selected' : ''; ?>>Absent</option>
                                <option value="Late" <?php echo $status_filter == 'Late' ? 'selected' : ''; ?>>Late</option>
                            </select>
                        </div>

                        <div class="filter-group" style="flex: 0 0 auto;">
                            <button type="submit" class="btn-filter">
                                <i class="fas fa-filter"></i> Apply Filters
                            </button>
                        </div>

                        <div class="filter-group" style="flex: 0 0 auto;">
                            <a href="attendance.php?tab=students" class="btn-reset">
                                <i class="fas fa-redo-alt"></i> Reset
                            </a>
                        </div>
                    </form>
                </div>

                <!-- Student Attendance Table -->
                <div class="table-card">
                    <div class="table-header">
                        <h3><i class="fas fa-calendar-check"></i> Student Attendance Records</h3>
                        <span class="grade-tag">Total: <?php echo $attendance_records ? $attendance_records->num_rows : 0; ?> records</span>
                    </div>

                    <div class="table-container">
                        <table class="attendance-table">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Student</th>
                                    <th>ID Number</th>
                                    <th>Subject</th>
                                    <th>Grade</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if($attendance_records && $attendance_records->num_rows > 0): ?>
                                    <?php while($record = $attendance_records->fetch_assoc()): ?>
                                        <tr>
                                            <td>
                                                <span class="time-tag">
                                                    <i class="far fa-calendar"></i>
                                                    <?php echo date('M d, Y', strtotime($record['date'])); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <div class="person-info">
                                                    <div class="person-avatar">
                                                        <?php echo strtoupper(substr($record['student_name'], 0, 1)); ?>
                                                    </div>
                                                    <div class="person-details">
                                                        <h4><?php echo htmlspecialchars($record['student_name']); ?></h4>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <span class="grade-tag"><?php echo $record['student_id_number'] ?? 'N/A'; ?></span>
                                            </td>
                                            <td>
                                                <span class="subject-tag"><?php echo htmlspecialchars($record['subject_name']); ?></span>
                                            </td>
                                            <td>
                                                <span class="grade-tag"><?php echo htmlspecialchars($record['grade_name']); ?></span>
                                            </td>
                                            <td>
                                                <span class="badge badge-<?php echo strtolower($record['status']); ?>">
                                                    <?php echo $record['status']; ?>
                                                </span>
                                            </td>
                                            <td>
                                                <div class="action-btns">
                                                    <a href="view_attendance.php?id=<?php echo $record['id']; ?>" class="btn-icon btn-view" title="View Details">
                                                        <i class="fas fa-eye"></i>
                                                    </a>
                                                    <a href="?tab=students&delete=<?php echo $record['id']; ?>" class="btn-icon btn-delete" title="Delete" 
                                                       onclick="return confirm('Are you sure you want to delete this attendance record?')">
                                                        <i class="fas fa-trash"></i>
                                                    </a>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="7">
                                            <div class="no-data">
                                                <i class="fas fa-calendar-times"></i>
                                                <h3>No Student Attendance Records Found</h3>
                                                <p>Try adjusting your filters or select a different date.</p>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

            <?php else: ?>
                <!-- TEACHER ATTENDANCE SECTION -->
                
                <!-- Teacher Attendance Statistics -->
                <div class="stats-container">
                    <div class="stat-card">
                        <div class="stat-header">
                            <h3>Today's Total</h3>
                            <div class="stat-icon">
                                <i class="fas fa-calendar-check"></i>
                            </div>
                        </div>
                        <div class="stat-number"><?php echo $teacher_today; ?></div>
                        <div class="stat-label">Teacher records</div>
                    </div>

                    <div class="stat-card">
                        <div class="stat-header">
                            <h3>Present</h3>
                            <div class="stat-icon">
                                <i class="fas fa-user-check"></i>
                            </div>
                        </div>
                        <div class="stat-number"><?php echo $teacher_present_today; ?></div>
                        <div class="stat-label">Teachers present</div>
                    </div>

                    <div class="stat-card">
                        <div class="stat-header">
                            <h3>Absent</h3>
                            <div class="stat-icon">
                                <i class="fas fa-user-times"></i>
                            </div>
                        </div>
                        <div class="stat-number"><?php echo $teacher_absent_today; ?></div>
                        <div class="stat-label">Teachers absent</div>
                    </div>

                    <div class="stat-card">
                        <div class="stat-header">
                            <h3>Late</h3>
                            <div class="stat-icon">
                                <i class="fas fa-clock"></i>
                            </div>
                        </div>
                        <div class="stat-number"><?php echo $teacher_late_today; ?></div>
                        <div class="stat-label">Teachers late</div>
                    </div>
                </div>

                <!-- Date Navigation -->
                <div class="date-nav">
                    <div class="date-display">
                        <h3><i class="fas fa-calendar-alt"></i> Teacher Attendance Records</h3>
                    </div>
                    <div class="date-picker">
                        <form method="GET" action="" style="display: flex; gap: 10px;">
                            <input type="hidden" name="tab" value="teachers">
                            <input type="date" name="teacher_date" class="date-input" value="<?php echo $teacher_date_filter; ?>">
                            <button type="submit" class="btn-date">
                                <i class="fas fa-search"></i> View Date
                            </button>
                        </form>
                    </div>
                </div>

                <!-- Add Teacher Attendance Button -->
                <div style="margin-bottom: 20px; text-align: right;">
                    <button class="btn-add" onclick="openAddTeacherModal()">
                        <i class="fas fa-plus"></i> Add Teacher Attendance
                    </button>
                </div>

                <!-- Filters Bar -->
                <div class="filters-bar">
                    <form method="GET" action="" class="filters-form">
                        <input type="hidden" name="tab" value="teachers">
                        <input type="hidden" name="teacher_date" value="<?php echo $teacher_date_filter; ?>">
                        
                        <div class="filter-group">
                            <label>Teacher</label>
                            <select name="teacher" class="filter-select">
                                <option value="">All Teachers</option>
                                <?php 
                                $teachers->data_seek(0);
                                while($teacher = $teachers->fetch_assoc()): 
                                ?>
                                    <option value="<?php echo $teacher['id']; ?>" <?php echo isset($_GET['teacher']) && $_GET['teacher'] == $teacher['id'] ? 'selected' : ''; ?>>
                                        <?php echo $teacher['fullname']; ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>

                        <div class="filter-group">
                            <label>Status</label>
                            <select name="teacher_status" class="filter-select">
                                <option value="">All Status</option>
                                <option value="Present" <?php echo $teacher_status_filter == 'Present' ? 'selected' : ''; ?>>Present</option>
                                <option value="Absent" <?php echo $teacher_status_filter == 'Absent' ? 'selected' : ''; ?>>Absent</option>
                                <option value="Late" <?php echo $teacher_status_filter == 'Late' ? 'selected' : ''; ?>>Late</option>
                            </select>
                        </div>

                        <div class="filter-group" style="flex: 0 0 auto;">
                            <button type="submit" class="btn-filter">
                                <i class="fas fa-filter"></i> Apply Filters
                            </button>
                        </div>

                        <div class="filter-group" style="flex: 0 0 auto;">
                            <a href="attendance.php?tab=teachers" class="btn-reset">
                                <i class="fas fa-redo-alt"></i> Reset
                            </a>
                        </div>
                    </form>
                </div>

                <!-- Teacher Attendance Table -->
                <div class="table-card">
                    <div class="table-header">
                        <h3><i class="fas fa-calendar-check"></i> Teacher Attendance Records</h3>
                        <span class="grade-tag">Total: <?php echo $teacher_attendance_records ? $teacher_attendance_records->num_rows : 0; ?> records</span>
                    </div>

                    <div class="table-container">
                        <table class="attendance-table">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Teacher</th>
                                    <th>ID Number</th>
                                    <th>Time In</th>
                                    <th>Time Out</th>
                                    <th>Status</th>
                                    <th>Remarks</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if($teacher_attendance_records && $teacher_attendance_records->num_rows > 0): ?>
                                    <?php while($record = $teacher_attendance_records->fetch_assoc()): ?>
                                        <tr>
                                            <td>
                                                <span class="time-tag">
                                                    <i class="far fa-calendar"></i>
                                                    <?php echo date('M d, Y', strtotime($record['date'])); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <div class="person-info">
                                                    <div class="person-avatar">
                                                        <?php echo strtoupper(substr($record['teacher_name'], 0, 1)); ?>
                                                    </div>
                                                    <div class="person-details">
                                                        <h4><?php echo htmlspecialchars($record['teacher_name']); ?></h4>
                                                        <span><?php echo htmlspecialchars($record['teacher_email']); ?></span>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <span class="grade-tag"><?php echo $record['teacher_id_number'] ?? 'N/A'; ?></span>
                                            </td>
                                            <td>
                                                <?php if($record['time_in']): ?>
                                                    <span class="time-tag">
                                                        <i class="fas fa-clock"></i> <?php echo date('h:i A', strtotime($record['time_in'])); ?>
                                                    </span>
                                                <?php else: ?>
                                                    <span class="grade-tag">—</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if($record['time_out']): ?>
                                                    <span class="time-tag">
                                                        <i class="fas fa-clock"></i> <?php echo date('h:i A', strtotime($record['time_out'])); ?>
                                                    </span>
                                                <?php else: ?>
                                                    <span class="grade-tag">—</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <span class="badge badge-<?php echo strtolower($record['status']); ?>">
                                                    <?php echo $record['status']; ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php echo htmlspecialchars($record['remarks'] ?? '—'); ?>
                                            </td>
                                            <td>
                                                <div class="action-btns">
                                                    <a href="#" class="btn-icon btn-edit" title="Edit" onclick="openEditTeacherModal(<?php echo htmlspecialchars(json_encode($record)); ?>)">
                                                        <i class="fas fa-edit"></i>
                                                    </a>
                                                    <a href="?tab=teachers&delete_teacher=<?php echo $record['id']; ?>" class="btn-icon btn-delete" title="Delete" 
                                                       onclick="return confirm('Are you sure you want to delete this teacher attendance record?')">
                                                        <i class="fas fa-trash"></i>
                                                    </a>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="8">
                                            <div class="no-data">
                                                <i class="fas fa-calendar-times"></i>
                                                <h3>No Teacher Attendance Records Found</h3>
                                                <p>Try adjusting your filters or select a different date.</p>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Add Teacher Attendance Modal -->
    <div class="modal" id="addTeacherModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-plus-circle"></i> Add Teacher Attendance</h3>
                <button class="close-modal" onclick="closeAddTeacherModal()">&times;</button>
            </div>
            <form method="POST" action="add_teacher_attendance.php">
                <div class="modal-body">
                    <div class="form-group">
                        <label>Teacher</label>
                        <select name="teacher_id" required>
                            <option value="">Select Teacher</option>
                            <?php 
                            $teachers->data_seek(0);
                            while($teacher = $teachers->fetch_assoc()): 
                            ?>
                                <option value="<?php echo $teacher['id']; ?>"><?php echo $teacher['fullname']; ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Date</label>
                        <input type="date" name="date" value="<?php echo date('Y-m-d'); ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Time In</label>
                        <input type="time" name="time_in">
                    </div>
                    <div class="form-group">
                        <label>Time Out</label>
                        <input type="time" name="time_out">
                    </div>
                    <div class="form-group">
                        <label>Status</label>
                        <select name="status" required>
                            <option value="Present">Present</option>
                            <option value="Absent">Absent</option>
                            <option value="Late">Late</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Remarks</label>
                        <textarea name="remarks" rows="3" placeholder="Optional remarks"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-cancel" onclick="closeAddTeacherModal()">Cancel</button>
                    <button type="submit" name="add_teacher_attendance" class="btn-save">Save Record</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Edit Teacher Attendance Modal -->
    <div class="modal" id="editTeacherModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-edit"></i> Edit Teacher Attendance</h3>
                <button class="close-modal" onclick="closeEditTeacherModal()">&times;</button>
            </div>
            <form method="POST" action="edit_teacher_attendance.php">
                <input type="hidden" name="id" id="edit_id">
                <div class="modal-body">
                    <div class="form-group">
                        <label>Teacher</label>
                        <select name="teacher_id" id="edit_teacher_id" required>
                            <option value="">Select Teacher</option>
                            <?php 
                            $teachers->data_seek(0);
                            while($teacher = $teachers->fetch_assoc()): 
                            ?>
                                <option value="<?php echo $teacher['id']; ?>"><?php echo $teacher['fullname']; ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Date</label>
                        <input type="date" name="date" id="edit_date" required>
                    </div>
                    <div class="form-group">
                        <label>Time In</label>
                        <input type="time" name="time_in" id="edit_time_in">
                    </div>
                    <div class="form-group">
                        <label>Time Out</label>
                        <input type="time" name="time_out" id="edit_time_out">
                    </div>
                    <div class="form-group">
                        <label>Status</label>
                        <select name="status" id="edit_status" required>
                            <option value="Present">Present</option>
                            <option value="Absent">Absent</option>
                            <option value="Late">Late</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Remarks</label>
                        <textarea name="remarks" id="edit_remarks" rows="3" placeholder="Optional remarks"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-cancel" onclick="closeEditTeacherModal()">Cancel</button>
                    <button type="submit" name="edit_teacher_attendance" class="btn-save">Update Record</button>
                </div>
            </form>
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

        // Add Teacher Modal Functions
        function openAddTeacherModal() {
            document.getElementById('addTeacherModal').classList.add('active');
            document.body.style.overflow = 'hidden';
        }

        function closeAddTeacherModal() {
            document.getElementById('addTeacherModal').classList.remove('active');
            document.body.style.overflow = 'auto';
        }

        // Edit Teacher Modal Functions
        function openEditTeacherModal(record) {
            document.getElementById('edit_id').value = record.id;
            document.getElementById('edit_teacher_id').value = record.teacher_id;
            document.getElementById('edit_date').value = record.date;
            document.getElementById('edit_time_in').value = record.time_in ? record.time_in.slice(0,5) : '';
            document.getElementById('edit_time_out').value = record.time_out ? record.time_out.slice(0,5) : '';
            document.getElementById('edit_status').value = record.status;
            document.getElementById('edit_remarks').value = record.remarks || '';
            
            document.getElementById('editTeacherModal').classList.add('active');
            document.body.style.overflow = 'hidden';
        }

        function closeEditTeacherModal() {
            document.getElementById('editTeacherModal').classList.remove('active');
            document.body.style.overflow = 'auto';
        }

        // Close modals when clicking outside
        window.onclick = function(event) {
            if (event.target.classList.contains('modal')) {
                event.target.classList.remove('active');
                document.body.style.overflow = 'auto';
            }
        }
    </script>
</body>
</html>