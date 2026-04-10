<?php
session_start();
require_once("../config/database.php");

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

// Handle delete action for teacher attendance
if(isset($_GET['delete_teacher'])) {
    $delete_id = $_GET['delete_teacher'];
    $delete = $conn->prepare("DELETE FROM teacher_attendance WHERE id = ?");
    $delete->execute([$delete_id]);
    if($delete->rowCount() > 0) {
        $success_message = "Teacher attendance record deleted successfully!";
    } else {
        $error_message = "Error deleting teacher attendance record.";
    }
}

// Handle edit action
if(isset($_POST['edit_teacher_attendance'])) {
    $id = $_POST['id'];
    $teacher_id = $_POST['teacher_id'];
    $date = $_POST['date'];
    $time_in = $_POST['time_in'] ?: null;
    $time_out = $_POST['time_out'] ?: null;
    $status = $_POST['status'];
    $remarks = $_POST['remarks'];
    
    $update = $conn->prepare("
        UPDATE teacher_attendance 
        SET teacher_id = ?, date = ?, time_in = ?, time_out = ?, status = ?, remarks = ?
        WHERE id = ?
    ");
    if($update->execute([$teacher_id, $date, $time_in, $time_out, $status, $remarks, $id])) {
        $_SESSION['success_message'] = "Teacher attendance record updated successfully!";
    } else {
        $_SESSION['error_message'] = "Error updating teacher attendance record.";
    }
    header("Location: attendance.php");
    exit();
}

// Handle add action
if(isset($_POST['add_teacher_attendance'])) {
    $teacher_id = $_POST['teacher_id'];
    $date = $_POST['date'];
    $time_in = $_POST['time_in'] ?: null;
    $time_out = $_POST['time_out'] ?: null;
    $status = $_POST['status'];
    $remarks = $_POST['remarks'];
    
    // Check if record already exists for this teacher on this date
    $check = $conn->prepare("SELECT id FROM teacher_attendance WHERE teacher_id = ? AND date = ?");
    $check->execute([$teacher_id, $date]);
    if($check->rowCount() > 0) {
        $_SESSION['error_message'] = "Attendance record already exists for this teacher on this date.";
    } else {
        $insert = $conn->prepare("
            INSERT INTO teacher_attendance (teacher_id, date, time_in, time_out, status, remarks, created_at)
            VALUES (?, ?, ?, ?, ?, ?, NOW())
        ");
        if($insert->execute([$teacher_id, $date, $time_in, $time_out, $status, $remarks])) {
            $_SESSION['success_message'] = "Teacher attendance record added successfully!";
        } else {
            $_SESSION['error_message'] = "Error adding teacher attendance record.";
        }
    }
    header("Location: attendance.php");
    exit();
}

// Get filter parameters for teacher attendance
$teacher_date_filter = isset($_GET['teacher_date']) ? $_GET['teacher_date'] : '';
$teacher_status_filter = isset($_GET['teacher_status']) ? $_GET['teacher_status'] : '';
$teacher_filter = isset($_GET['teacher']) ? $_GET['teacher'] : '';

// Build the teacher attendance query - FIXED: Properly handle the date filter
$teacher_query = "
    SELECT ta.*, 
           u.fullname as teacher_name,
           u.id_number as teacher_id_number,
           u.email as teacher_email,
           u.firstname,
           u.lastname
    FROM teacher_attendance ta
    INNER JOIN users u ON ta.teacher_id = u.id
    WHERE u.role = 'Teacher'
";
$teacher_params = [];

// Only add date filter if a specific date is selected and it's not 'all'
if(!empty($teacher_date_filter) && $teacher_date_filter != 'all') {
    $teacher_query .= " AND ta.date = ?";
    $teacher_params[] = $teacher_date_filter;
}

if(!empty($teacher_status_filter)) {
    $teacher_query .= " AND ta.status = ?";
    $teacher_params[] = $teacher_status_filter;
}

if(!empty($teacher_filter)) {
    $teacher_query .= " AND ta.teacher_id = ?";
    $teacher_params[] = $teacher_filter;
}

$teacher_query .= " ORDER BY ta.date DESC, ta.created_at DESC";

$teacher_stmt = $conn->prepare($teacher_query);
$teacher_stmt->execute($teacher_params);
$teacher_attendance_records = $teacher_stmt->fetchAll(PDO::FETCH_ASSOC);

// Debug: Check if records are found
$record_count = count($teacher_attendance_records);

// If no records found with filters, try to get all records for debugging
if($record_count == 0 && empty($teacher_date_filter) && empty($teacher_status_filter) && empty($teacher_filter)) {
    // Try a simpler query to check if there are any records at all
    $check_stmt = $conn->query("SELECT COUNT(*) as total FROM teacher_attendance");
    $total_records = $check_stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    if($total_records > 0) {
        // There are records but the join might be failing
        $simple_stmt = $conn->query("SELECT * FROM teacher_attendance LIMIT 5");
        $sample_records = $simple_stmt->fetchAll(PDO::FETCH_ASSOC);
        // For debugging - you can remove this after fixing
        error_log("Total teacher_attendance records: " . $total_records);
        error_log("Sample record: " . print_r($sample_records[0] ?? [], true));
    }
}

// Get teacher attendance statistics for today
$today = date('Y-m-d');

$teacher_today_stmt = $conn->prepare("SELECT COUNT(*) as count FROM teacher_attendance WHERE date = ?");
$teacher_today_stmt->execute([$today]);
$teacher_today = $teacher_today_stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

$teacher_present_today_stmt = $conn->prepare("SELECT COUNT(*) as count FROM teacher_attendance WHERE date = ? AND status = 'Present'");
$teacher_present_today_stmt->execute([$today]);
$teacher_present_today = $teacher_present_today_stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

$teacher_absent_today_stmt = $conn->prepare("SELECT COUNT(*) as count FROM teacher_attendance WHERE date = ? AND status = 'Absent'");
$teacher_absent_today_stmt->execute([$today]);
$teacher_absent_today = $teacher_absent_today_stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

$teacher_late_today_stmt = $conn->prepare("SELECT COUNT(*) as count FROM teacher_attendance WHERE date = ? AND status = 'Late'");
$teacher_late_today_stmt->execute([$today]);
$teacher_late_today = $teacher_late_today_stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

// Get overall statistics
$overall_stats = $conn->prepare("
    SELECT 
        COUNT(*) as total_records,
        COUNT(DISTINCT teacher_id) as total_teachers,
        MIN(date) as earliest_date,
        MAX(date) as latest_date
    FROM teacher_attendance
");
$overall_stats->execute();
$overall = $overall_stats->fetch(PDO::FETCH_ASSOC);

// Get teachers for filter (only approved teachers)
$teachers_stmt = $conn->prepare("SELECT id, fullname, id_number FROM users WHERE role = 'Teacher' AND status = 'approved' ORDER BY fullname");
$teachers_stmt->execute();
$teachers = $teachers_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get all distinct dates for the date filter dropdown
$dates_stmt = $conn->query("SELECT DISTINCT date FROM teacher_attendance ORDER BY date DESC LIMIT 30");
$available_dates = $dates_stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teacher Attendance Management - Admin Dashboard</title>
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
            flex-wrap: wrap;
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
            flex-wrap: wrap;
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
        
        .badge-pending {
            background: rgba(23, 162, 184, 0.1);
            color: var(--info);
        }
        
        .badge-active {
            background: rgba(40, 167, 69, 0.1);
            color: var(--success);
        }
        
        .badge-used {
            background: rgba(255, 193, 7, 0.1);
            color: var(--warning);
        }
        
        .badge-expired, .badge-completed {
            background: rgba(108, 117, 125, 0.1);
            color: #6c757d;
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
            
            .stats-container {
                grid-template-columns: 1fr;
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
        <div class="sidebar">
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

        <div class="main-content">
            <div class="dashboard-header">
                <h1>Teacher Attendance Management</h1>
                <p>View and manage teacher attendance records</p>
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
                    <div class="stat-label">Teacher records today</div>
                </div>

                <div class="stat-card">
                    <div class="stat-header">
                        <h3>Present Today</h3>
                        <div class="stat-icon">
                            <i class="fas fa-user-check"></i>
                        </div>
                    </div>
                    <div class="stat-number"><?php echo $teacher_present_today; ?></div>
                    <div class="stat-label">Teachers present</div>
                </div>

                <div class="stat-card">
                    <div class="stat-header">
                        <h3>Absent Today</h3>
                        <div class="stat-icon">
                            <i class="fas fa-user-times"></i>
                        </div>
                    </div>
                    <div class="stat-number"><?php echo $teacher_absent_today; ?></div>
                    <div class="stat-label">Teachers absent</div>
                </div>

                <div class="stat-card">
                    <div class="stat-header">
                        <h3>Late Today</h3>
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
                    <span class="grade-tag">Total Records: <?php echo $overall['total_records'] ?? 0; ?></span>
                    <span class="grade-tag">Teachers: <?php echo $overall['total_teachers'] ?? 0; ?></span>
                    <?php if($overall['earliest_date']): ?>
                        <span class="grade-tag">From: <?php echo date('M d, Y', strtotime($overall['earliest_date'])); ?></span>
                    <?php endif; ?>
                </div>
                <div class="date-picker">
                    <form method="GET" action="" style="display: flex; gap: 10px; flex-wrap: wrap;">
                        <select name="teacher_date" class="date-input">
                            <option value="all">All Dates</option>
                            <?php foreach($available_dates as $date): ?>
                                <option value="<?php echo $date['date']; ?>" <?php echo $teacher_date_filter == $date['date'] ? 'selected' : ''; ?>>
                                    <?php echo date('F d, Y', strtotime($date['date'])); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <button type="submit" class="btn-date">
                            <i class="fas fa-search"></i> Filter
                        </button>
                        <button type="button" class="btn-add" onclick="openAddTeacherModal()">
                            <i class="fas fa-plus"></i> Add Record
                        </button>
                    </form>
                </div>
            </div>

            <!-- Filters Bar -->
            <div class="filters-bar">
                <form method="GET" action="" class="filters-form">
                    <input type="hidden" name="teacher_date" value="<?php echo $teacher_date_filter; ?>">
                    
                    <div class="filter-group">
                        <label>Teacher</label>
                        <select name="teacher" class="filter-select">
                            <option value="">All Teachers</option>
                            <?php foreach($teachers as $teacher): ?>
                                <option value="<?php echo $teacher['id']; ?>" <?php echo $teacher_filter == $teacher['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($teacher['fullname']); ?> <?php echo $teacher['id_number'] ?? ''; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="filter-group">
                        <label>Status</label>
                        <select name="teacher_status" class="filter-select">
                            <option value="">All Status</option>
                            <option value="Present" <?php echo $teacher_status_filter == 'Present' ? 'selected' : ''; ?>>Present</option>
                            <option value="Absent" <?php echo $teacher_status_filter == 'Absent' ? 'selected' : ''; ?>>Absent</option>
                            <option value="Late" <?php echo $teacher_status_filter == 'Late' ? 'selected' : ''; ?>>Late</option>
                            <option value="Pending" <?php echo $teacher_status_filter == 'Pending' ? 'selected' : ''; ?>>Pending</option>
                        </select>
                    </div>

                    <div class="filter-group" style="flex: 0 0 auto;">
                        <button type="submit" class="btn-filter">
                            <i class="fas fa-filter"></i> Apply Filters
                        </button>
                    </div>

                    <div class="filter-group" style="flex: 0 0 auto;">
                        <a href="attendance.php" class="btn-reset">
                            <i class="fas fa-redo-alt"></i> Reset
                        </a>
                    </div>
                </form>
            </div>

            <!-- Teacher Attendance Table -->
            <div class="table-card">
                <div class="table-header">
                    <h3><i class="fas fa-calendar-check"></i> Teacher Attendance Records</h3>
                    <span class="grade-tag">Showing: <?php echo $record_count; ?> records</span>
                </div>

                <div class="table-container">
                    <?php if($record_count > 0): ?>
                    <table class="attendance-table">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Teacher</th>
                                <th>ID Number</th>
                                <th>Time In</th>
                                <th>Time Out</th>
                                <th>Status</th>
                                <th>Session Status</th>
                                <th>QR Token</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($teacher_attendance_records as $record): ?>
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
                                                <span><i class="fas fa-envelope"></i> <?php echo htmlspecialchars($record['teacher_email']); ?></span>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="grade-tag"><?php echo $record['teacher_id_number'] ?? 'N/A'; ?></span>
                                    </td>
                                    <td>
                                        <?php if($record['time_in'] && $record['time_in'] != '00:00:00'): ?>
                                            <span class="time-tag">
                                                <i class="fas fa-clock"></i> <?php echo date('h:i A', strtotime($record['time_in'])); ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="grade-tag">—</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if($record['time_out'] && $record['time_out'] != '00:00:00'): ?>
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
                                        <?php if(isset($record['session_status']) && $record['session_status']): ?>
                                            <span class="badge badge-<?php echo strtolower($record['session_status']); ?>">
                                                <?php echo ucfirst($record['session_status']); ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="grade-tag">—</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if(isset($record['qr_token']) && $record['qr_token']): ?>
                                            <span class="time-tag" title="<?php echo $record['qr_token']; ?>">
                                                <i class="fas fa-qrcode"></i> 
                                                <?php echo substr($record['qr_token'], 0, 8); ?>...
                                            </span>
                                        <?php else: ?>
                                            <span class="grade-tag">—</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="action-btns">
                                            <a href="?delete_teacher=<?php echo $record['id']; ?>" class="btn-icon btn-delete" title="Delete" 
                                               onclick="return confirm('Are you sure you want to delete this teacher attendance record?')">
                                                <i class="fas fa-trash"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php else: ?>
                        <div class="no-data">
                            <i class="fas fa-calendar-times"></i>
                            <h3>No Teacher Attendance Records Found</h3>
                            <?php if($overall['total_records'] > 0): ?>
                            <?php else: ?>
                                <p style="margin-top: 10px;">Teachers can generate QR codes and scan to record their attendance, or you can manually add records using the "Add Record" button above.</p>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Add Teacher Attendance Modal -->
    <div class="modal" id="addTeacherModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-plus-circle"></i> Add Teacher Attendance</h3>
                <button class="close-modal" onclick="closeAddTeacherModal()">&times;</button>
            </div>
            <form method="POST" action="">
                <div class="modal-body">
                    <div class="form-group">
                        <label>Teacher *</label>
                        <select name="teacher_id" required>
                            <option value="">Select Teacher</option>
                            <?php foreach($teachers as $teacher): ?>
                                <option value="<?php echo $teacher['id']; ?>"><?php echo htmlspecialchars($teacher['fullname']); ?> (<?php echo $teacher['id_number'] ?? 'No ID'; ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Date *</label>
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
                        <label>Status *</label>
                        <select name="status" required>
                            <option value="Present">Present</option>
                            <option value="Absent">Absent</option>
                            <option value="Late">Late</option>
                            <option value="Pending">Pending</option>
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
            <form method="POST" action="">
                <input type="hidden" name="id" id="edit_id">
                <div class="modal-body">
                    <div class="form-group">
                        <label>Teacher *</label>
                        <select name="teacher_id" id="edit_teacher_id" required>
                            <option value="">Select Teacher</option>
                            <?php foreach($teachers as $teacher): ?>
                                <option value="<?php echo $teacher['id']; ?>"><?php echo htmlspecialchars($teacher['fullname']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Date *</label>
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
                        <label>Status *</label>
                        <select name="status" id="edit_status" required>
                            <option value="Present">Present</option>
                            <option value="Absent">Absent</option>
                            <option value="Late">Late</option>
                            <option value="Pending">Pending</option>
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
                setTimeout(() => {
                    alert.style.opacity = '0';
                    setTimeout(() => {
                        alert.style.display = 'none';
                    }, 300);
                }, 5000);
            });
        });

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