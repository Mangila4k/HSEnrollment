<?php
session_start();
include("../config/database.php");

// Check if user is registrar
if(!isset($_SESSION['user']) || $_SESSION['user']['role'] != 'Registrar'){
    header("Location: ../auth/login.php");
    exit();
}

$registrar_name = $_SESSION['user']['fullname'];
$success_message = '';
$error_message = '';

// Handle enrollment approval/rejection
if(isset($_GET['action']) && isset($_GET['id'])) {
    $enrollment_id = $_GET['id'];
    $action = $_GET['action'];
    
    if($action == 'approve') {
        $status = 'Enrolled';
        $success_message = "Enrollment approved successfully!";
    } elseif($action == 'reject') {
        $status = 'Rejected';
        $success_message = "Enrollment rejected.";
    }
    
    $conn->query("UPDATE enrollments SET status='$status' WHERE id='$enrollment_id'");
}

// Handle enrollment deletion
if(isset($_GET['delete'])) {
    $enrollment_id = $_GET['delete'];
    $conn->query("DELETE FROM enrollments WHERE id='$enrollment_id'");
    $success_message = "Enrollment record deleted successfully!";
}

// Handle new enrollment (manual entry by registrar)
if(isset($_POST['add_enrollment'])) {
    $student_id = $_POST['student_id'];
    $grade_id = $_POST['grade_id'];
    $strand = $_POST['strand'];
    $school_year = $_POST['school_year'];
    $status = $_POST['status'];
    
    $insert = $conn->query("INSERT INTO enrollments (student_id, grade_id, strand, school_year, status, created_at) 
                            VALUES ('$student_id', '$grade_id', '$strand', '$school_year', '$status', NOW())");
    if($insert) {
        $success_message = "New enrollment added successfully!";
    } else {
        $error_message = "Error adding enrollment: " . $conn->error;
    }
}

// Handle search
$search = isset($_GET['search']) ? $_GET['search'] : '';
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';

// Build query based on filters
$query = "SELECT e.*, u.fullname, u.email, u.id_number, g.grade_name 
          FROM enrollments e 
          LEFT JOIN users u ON e.student_id = u.id 
          LEFT JOIN grade_levels g ON e.grade_id = g.id 
          WHERE 1=1";

if($search) {
    $query .= " AND (u.fullname LIKE '%$search%' OR u.email LIKE '%$search%' OR u.id_number LIKE '%$search%' OR e.school_year LIKE '%$search%')";
}

if($status_filter) {
    $query .= " AND e.status = '$status_filter'";
}

$query .= " ORDER BY e.id DESC";

$enrollments = $conn->query($query);

// Get counts for dashboard
$pending_count = $conn->query("SELECT COUNT(*) as count FROM enrollments WHERE status='Pending'")->fetch_assoc()['count'];
$enrolled_count = $conn->query("SELECT COUNT(*) as count FROM enrollments WHERE status='Enrolled'")->fetch_assoc()['count'];
$rejected_count = $conn->query("SELECT COUNT(*) as count FROM enrollments WHERE status='Rejected'")->fetch_assoc()['count'];
$total_enrollments = $conn->query("SELECT COUNT(*) as count FROM enrollments")->fetch_assoc()['count'];

// Get students and grades for dropdown
$students = $conn->query("SELECT * FROM users WHERE role='Student' ORDER BY fullname");
$grades = $conn->query("SELECT * FROM grade_levels ORDER BY id");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Enrollment Management - Registrar Dashboard</title>
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

        .registrar-info {
            text-align: center;
            padding: 20px 0;
            border-bottom: 1px solid rgba(255, 255, 255, 0.2);
            margin-bottom: 20px;
        }

        .registrar-avatar {
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

        .registrar-info h3 {
            font-size: 18px;
            margin-bottom: 5px;
            color: #FFD700;
        }

        .registrar-info p {
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

        /* Filter Bar */
        .filter-bar {
            background: white;
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 30px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
        }

        .filter-form {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
            align-items: center;
        }

        .search-box {
            flex: 2;
            display: flex;
            align-items: center;
            gap: 10px;
            background: #f8f9fa;
            border: 2px solid var(--border-color);
            border-radius: 12px;
            padding: 0 15px;
        }

        .search-box i {
            color: var(--text-secondary);
        }

        .search-box input {
            flex: 1;
            padding: 14px 0;
            border: none;
            background: transparent;
            font-size: 14px;
        }

        .search-box input:focus {
            outline: none;
        }

        .filter-select {
            flex: 1;
            min-width: 150px;
            padding: 14px 15px;
            border: 2px solid var(--border-color);
            border-radius: 12px;
            font-size: 14px;
            background: #f8f9fa;
            cursor: pointer;
        }

        .filter-select:focus {
            border-color: #0B4F2E;
            outline: none;
            background: white;
        }

        .btn-filter {
            background: #0B4F2E;
            color: white;
            border: none;
            padding: 14px 25px;
            border-radius: 12px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-filter:hover {
            background: #1a7a42;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(11, 79, 46, 0.3);
        }

        .btn-reset {
            background: #f8f9fa;
            color: var(--text-secondary);
            border: 2px solid var(--border-color);
            padding: 14px 25px;
            border-radius: 12px;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-reset:hover {
            border-color: #0B4F2E;
            color: #0B4F2E;
        }

        .export-buttons {
            display: flex;
            gap: 10px;
            margin-left: auto;
        }

        .btn-export {
            background: white;
            color: var(--text-primary);
            border: 2px solid var(--border-color);
            padding: 14px 20px;
            border-radius: 12px;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-export:hover {
            border-color: #0B4F2E;
            color: #0B4F2E;
        }

        /* Table Card */
        .table-card {
            background: white;
            border-radius: 20px;
            padding: 25px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            overflow-x: auto;
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
            color: #0B4F2E;
        }

        .enrollments-table {
            width: 100%;
            border-collapse: collapse;
            min-width: 1000px;
        }

        .enrollments-table th {
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

        .enrollments-table td {
            padding: 15px;
            border-bottom: 1px solid var(--border-color);
            color: var(--text-primary);
        }

        .enrollments-table tbody tr:hover {
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
            background: linear-gradient(135deg, #0B4F2E, #1a7a42);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
            font-size: 16px;
        }

        .student-details h4 {
            font-size: 15px;
            margin-bottom: 3px;
            color: var(--text-primary);
        }

        .student-details span {
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

        .badge-pending {
            background: rgba(255, 193, 7, 0.1);
            color: #ffc107;
        }

        .badge-enrolled {
            background: rgba(40, 167, 69, 0.1);
            color: #28a745;
        }

        .badge-rejected {
            background: rgba(220, 53, 69, 0.1);
            color: #dc3545;
        }

        .grade-tag {
            background: rgba(11, 79, 46, 0.1);
            color: #0B4F2E;
            padding: 3px 8px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 500;
        }

        .action-buttons {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }

        .action-btn {
            padding: 6px 12px;
            border-radius: 8px;
            font-size: 12px;
            font-weight: 500;
            text-decoration: none;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }

        .action-btn i {
            font-size: 12px;
        }

        .action-btn.view {
            background: rgba(67, 97, 238, 0.1);
            color: #4361ee;
        }

        .action-btn.view:hover {
            background: #4361ee;
            color: white;
        }

        .action-btn.approve {
            background: rgba(40, 167, 69, 0.1);
            color: #28a745;
        }

        .action-btn.approve:hover {
            background: #28a745;
            color: white;
        }

        .action-btn.reject {
            background: rgba(255, 193, 7, 0.1);
            color: #ffc107;
        }

        .action-btn.reject:hover {
            background: #ffc107;
            color: white;
        }

        .action-btn.delete {
            background: rgba(220, 53, 69, 0.1);
            color: #dc3545;
        }

        .action-btn.delete:hover {
            background: #dc3545;
            color: white;
        }

        /* Quick Action Cards */
        .quick-actions {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 25px;
            margin-top: 30px;
        }

        .quick-action-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            transition: all 0.3s;
        }

        .quick-action-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
        }

        .quick-action-card h3 {
            color: var(--text-primary);
            font-size: 18px;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .quick-action-card h3 i {
            color: #0B4F2E;
        }

        .quick-action-card p {
            color: var(--text-secondary);
            margin-bottom: 20px;
            line-height: 1.6;
        }

        .quick-action-card .btn {
            width: 100%;
            padding: 12px;
            border-radius: 10px;
            text-align: center;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s;
            display: inline-block;
        }

        .quick-action-card .btn-warning {
            background: #ffc107;
            color: #2b2d42;
        }

        .quick-action-card .btn-warning:hover {
            background: #e0a800;
            transform: translateY(-2px);
        }

        .quick-action-card .btn-primary {
            background: #0B4F2E;
            color: white;
        }

        .quick-action-card .btn-primary:hover {
            background: #1a7a42;
            transform: translateY(-2px);
        }

        /* Modal */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 1000;
            justify-content: center;
            align-items: center;
        }

        .modal.active {
            display: flex;
        }

        .modal-content {
            background: white;
            border-radius: 20px;
            width: 90%;
            max-width: 500px;
            max-height: 80vh;
            overflow-y: auto;
            animation: modalSlideIn 0.3s ease;
        }

        @keyframes modalSlideIn {
            from {
                transform: translateY(-50px);
                opacity: 0;
            }
            to {
                transform: translateY(0);
                opacity: 1;
            }
        }

        .modal-header {
            padding: 25px 30px;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .modal-header h3 {
            color: var(--text-primary);
            font-size: 20px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .modal-header h3 i {
            color: #0B4F2E;
        }

        .close-modal {
            background: none;
            border: none;
            font-size: 24px;
            cursor: pointer;
            color: var(--text-secondary);
            transition: color 0.3s;
        }

        .close-modal:hover {
            color: #dc3545;
        }

        .modal-body {
            padding: 30px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: var(--text-primary);
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
            background: #f8f9fa;
        }

        .form-group input:focus,
        .form-group select:focus {
            border-color: #0B4F2E;
            outline: none;
            background: white;
            box-shadow: 0 0 0 4px rgba(11, 79, 46, 0.1);
        }

        .modal-footer {
            display: flex;
            gap: 15px;
            margin-top: 30px;
        }

        .modal-footer .btn {
            flex: 1;
            padding: 14px;
            border: none;
            border-radius: 10px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
        }

        .modal-footer .btn-primary {
            background: #0B4F2E;
            color: white;
        }

        .modal-footer .btn-primary:hover {
            background: #1a7a42;
            transform: translateY(-2px);
        }

        .modal-footer .btn-secondary {
            background: #f8f9fa;
            color: var(--text-secondary);
            border: 2px solid var(--border-color);
        }

        .modal-footer .btn-secondary:hover {
            border-color: #0B4F2E;
            color: #0B4F2E;
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
            .registrar-info h3,
            .registrar-info p,
            .menu-section h3,
            .menu-items a span {
                display: none;
            }
            
            .registrar-avatar {
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
            
            .filter-form {
                flex-direction: column;
            }
            
            .search-box {
                width: 100%;
            }
            
            .filter-select {
                width: 100%;
            }
            
            .btn-filter, .btn-reset {
                width: 100%;
                justify-content: center;
            }
            
            .export-buttons {
                width: 100%;
                justify-content: center;
            }
            
            .quick-actions {
                grid-template-columns: 1fr;
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
            
            <div class="registrar-info">
                <div class="registrar-avatar">
                    <?php echo strtoupper(substr($registrar_name, 0, 1)); ?>
                </div>
                <h3><?php echo htmlspecialchars(explode(' ', $registrar_name)[0]); ?></h3>
                <p><i class="fas fa-user-tie"></i> Registrar</p>
            </div>
            
            <div class="menu-section">
                <h3>MAIN MENU</h3>
                <ul class="menu-items">
                    <li><a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> <span>Dashboard</span></a></li>
                    <li><a href="enrollments.php" class="active"><i class="fas fa-file-signature"></i> <span>Enrollments</span></a></li>
                    <li><a href="students.php"><i class="fas fa-user-graduate"></i> <span>Students</span></a></li>
                    <li><a href="reports.php"><i class="fas fa-chart-bar"></i> <span>Reports</span></a></li>
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
                <h1>Enrollment Management</h1>
                <p>Manage student enrollments and applications</p>
            </div>

            <!-- Welcome Card -->
            <div class="welcome-card">
                <div class="welcome-text">
                    <h2>Welcome back, <?php echo htmlspecialchars(explode(' ', $registrar_name)[0]); ?>! 👋</h2>
                    <p><i class="fas fa-calendar"></i> <?php echo date('l, F j, Y'); ?></p>
                </div>
                <a href="../auth/logout.php" class="logout-btn">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a>
            </div>

            <!-- Alert Messages -->
            <?php if(isset($success_message) && $success_message): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    <?php echo $success_message; ?>
                </div>
            <?php endif; ?>

            <?php if(isset($error_message) && $error_message): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i>
                    <?php echo $error_message; ?>
                </div>
            <?php endif; ?>

            <!-- Statistics Cards -->
            <div class="stats-container">
                <div class="stat-card">
                    <div class="stat-header">
                        <h3>Total Enrollments</h3>
                        <div class="stat-icon">
                            <i class="fas fa-file-signature"></i>
                        </div>
                    </div>
                    <div class="stat-number"><?php echo $total_enrollments; ?></div>
                    <div class="stat-label">All time</div>
                </div>

                <div class="stat-card">
                    <div class="stat-header">
                        <h3>Pending</h3>
                        <div class="stat-icon">
                            <i class="fas fa-clock"></i>
                        </div>
                    </div>
                    <div class="stat-number"><?php echo $pending_count; ?></div>
                    <div class="stat-label">Awaiting review</div>
                </div>

                <div class="stat-card">
                    <div class="stat-header">
                        <h3>Enrolled</h3>
                        <div class="stat-icon">
                            <i class="fas fa-check-circle"></i>
                        </div>
                    </div>
                    <div class="stat-number"><?php echo $enrolled_count; ?></div>
                    <div class="stat-label">Approved</div>
                </div>

                <div class="stat-card">
                    <div class="stat-header">
                        <h3>Rejected</h3>
                        <div class="stat-icon">
                            <i class="fas fa-times-circle"></i>
                        </div>
                    </div>
                    <div class="stat-number"><?php echo $rejected_count; ?></div>
                    <div class="stat-label">Not approved</div>
                </div>
            </div>

            <!-- Filter Bar -->
            <div class="filter-bar">
                <form method="GET" class="filter-form">
                    <div class="search-box">
                        <i class="fas fa-search"></i>
                        <input type="text" name="search" placeholder="Search by name, email, ID, or school year..." value="<?php echo htmlspecialchars($search); ?>">
                    </div>

                    <select name="status" class="filter-select">
                        <option value="">All Status</option>
                        <option value="Pending" <?php echo $status_filter == 'Pending' ? 'selected' : ''; ?>>Pending</option>
                        <option value="Enrolled" <?php echo $status_filter == 'Enrolled' ? 'selected' : ''; ?>>Enrolled</option>
                        <option value="Rejected" <?php echo $status_filter == 'Rejected' ? 'selected' : ''; ?>>Rejected</option>
                    </select>

                    <button type="submit" class="btn-filter">
                        <i class="fas fa-filter"></i> Apply Filters
                    </button>

                    <a href="enrollments.php" class="btn-reset">
                        <i class="fas fa-redo-alt"></i> Reset
                    </a>

                    <div class="export-buttons">
                        <button type="button" class="btn-export" onclick="showAddModal()">
                            <i class="fas fa-plus-circle"></i> Add
                        </button>
                        <button type="button" class="btn-export" onclick="exportToExcel()">
                            <i class="fas fa-file-excel"></i> Export
                        </button>
                        <button type="button" class="btn-export" onclick="printTable()">
                            <i class="fas fa-print"></i> Print
                        </button>
                    </div>
                </form>
            </div>

            <!-- Enrollments Table -->
            <div class="table-card">
                <div class="table-header">
                    <h3><i class="fas fa-file-signature"></i> Enrollment Records</h3>
                    <span class="grade-tag">Total: <?php echo $enrollments ? $enrollments->num_rows : 0; ?> records</span>
                </div>

                <table class="enrollments-table">
                    <thead>
                        <tr>
                            <th>Student</th>
                            <th>ID Number</th>
                            <th>Grade & Strand</th>
                            <th>School Year</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if($enrollments && $enrollments->num_rows > 0): ?>
                            <?php while($row = $enrollments->fetch_assoc()): ?>
                                <tr>
                                    <td>
                                        <div class="student-info">
                                            <div class="student-avatar">
                                                <?php echo strtoupper(substr($row['fullname'], 0, 1)); ?>
                                            </div>
                                            <div class="student-details">
                                                <h4><?php echo htmlspecialchars($row['fullname']); ?></h4>
                                                <span><i class="fas fa-envelope"></i> <?php echo htmlspecialchars($row['email']); ?></span>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="grade-tag"><?php echo $row['id_number'] ?? 'N/A'; ?></span>
                                    </td>
                                    <td>
                                        <span class="grade-tag"><?php echo htmlspecialchars($row['grade_name']); ?></span>
                                        <?php if($row['strand']): ?>
                                            <span class="grade-tag" style="background: rgba(255, 215, 0, 0.1); color: #b8860b;"><?php echo htmlspecialchars($row['strand']); ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="grade-tag"><?php echo htmlspecialchars($row['school_year']); ?></span>
                                    </td>
                                    <td>
                                        <span class="badge badge-<?php echo strtolower($row['status']); ?>">
                                            <?php echo $row['status']; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="action-buttons">
                                            <a href="view_enrollment.php?id=<?php echo $row['id']; ?>" class="action-btn view">
                                                <i class="fas fa-eye"></i> View
                                            </a>
                                            
                                            <?php if($row['status'] == 'Pending'): ?>
                                                <a href="?action=approve&id=<?php echo $row['id']; ?>" class="action-btn approve" onclick="return confirm('Approve this enrollment?')">
                                                    <i class="fas fa-check-circle"></i> Approve
                                                </a>
                                                <a href="?action=reject&id=<?php echo $row['id']; ?>" class="action-btn reject" onclick="return confirm('Reject this enrollment?')">
                                                    <i class="fas fa-times-circle"></i> Reject
                                                </a>
                                            <?php endif; ?>
                                            
                                            <a href="?delete=<?php echo $row['id']; ?>" class="action-btn delete" onclick="return confirm('Delete this enrollment record?')">
                                                <i class="fas fa-trash"></i> Delete
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6">
                                    <div class="no-data">
                                        <i class="fas fa-file-signature"></i>
                                        <h3>No Enrollment Records Found</h3>
                                        <p>Try adjusting your filters or add a new enrollment.</p>
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Quick Actions -->
            <div class="quick-actions">
                <div class="quick-action-card">
                    <h3><i class="fas fa-clock"></i> Pending Actions</h3>
                    <p>You have <strong><?php echo $pending_count; ?></strong> pending enrollments waiting for your review.</p>
                    <a href="?status=Pending" class="btn btn-warning">Review Pending</a>
                </div>
                
                <div class="quick-action-card">
                    <h3><i class="fas fa-chart-bar"></i> Reports</h3>
                    <p>Generate enrollment reports and statistics for analysis.</p>
                    <a href="reports.php" class="btn btn-primary">Generate Reports</a>
                </div>
                
                <div class="quick-action-card">
                    <h3><i class="fas fa-user-graduate"></i> Students</h3>
                    <p>Manage student records and information.</p>
                    <a href="students.php" class="btn btn-primary">Manage Students</a>
                </div>
            </div>
        </div>
    </div>

    <!-- Add Enrollment Modal -->
    <div id="addModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-plus-circle"></i> Add New Enrollment</h3>
                <button class="close-modal" onclick="hideAddModal()">&times;</button>
            </div>
            <div class="modal-body">
                <form method="POST">
                    <div class="form-group">
                        <label>Select Student</label>
                        <select name="student_id" required>
                            <option value="">-- Choose Student --</option>
                            <?php 
                            if($students) {
                                $students->data_seek(0);
                                while($s = $students->fetch_assoc()): 
                            ?>
                                <option value="<?php echo $s['id']; ?>"><?php echo htmlspecialchars($s['fullname']); ?> (<?php echo $s['email']; ?>)</option>
                            <?php 
                                endwhile;
                            } 
                            ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>Grade Level</label>
                        <select name="grade_id" required>
                            <option value="">-- Select Grade --</option>
                            <?php 
                            if($grades) {
                                $grades->data_seek(0);
                                while($g = $grades->fetch_assoc()): 
                            ?>
                                <option value="<?php echo $g['id']; ?>"><?php echo $g['grade_name']; ?></option>
                            <?php 
                                endwhile;
                            } 
                            ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>Strand (for Grade 11-12)</label>
                        <select name="strand">
                            <option value="">-- Optional --</option>
                            <option value="STEM">STEM</option>
                            <option value="ABM">ABM</option>
                            <option value="HUMSS">HUMSS</option>
                            <option value="GAS">GAS</option>
                            <option value="ICT">ICT</option>
                            <option value="HE">HE</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>School Year</label>
                        <input type="text" name="school_year" placeholder="e.g., 2024-2025" value="<?php echo date('Y') . '-' . (date('Y')+1); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Status</label>
                        <select name="status" required>
                            <option value="Pending">Pending</option>
                            <option value="Enrolled">Enrolled</option>
                            <option value="Rejected">Rejected</option>
                        </select>
                    </div>
                    
                    <div class="modal-footer">
                        <button type="submit" name="add_enrollment" class="btn btn-primary">Add Enrollment</button>
                        <button type="button" class="btn btn-secondary" onclick="hideAddModal()">Cancel</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        // Modal functions
        function showAddModal() {
            document.getElementById('addModal').classList.add('active');
        }
        
        function hideAddModal() {
            document.getElementById('addModal').classList.remove('active');
        }
        
        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('addModal');
            if (event.target == modal) {
                modal.classList.remove('active');
            }
        }

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
        
        // Export to Excel function
        function exportToExcel() {
            const table = document.querySelector('.enrollments-table');
            const rows = Array.from(table.querySelectorAll('tr'));
            
            let csv = [];
            rows.forEach(row => {
                const cols = Array.from(row.querySelectorAll('th, td'));
                // Exclude action buttons column (last column)
                const rowData = cols.slice(0, -1).map(col => {
                    // Get text content, remove extra spaces and commas
                    return '"' + col.innerText.replace(/"/g, '""').replace(/\s+/g, ' ').trim() + '"';
                }).join(',');
                csv.push(rowData);
            });
            
            const blob = new Blob([csv.join('\n')], { type: 'text/csv;charset=utf-8;' });
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = 'enrollments_export_' + new Date().toISOString().slice(0,10) + '.csv';
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            window.URL.revokeObjectURL(url);
        }
        
        // Print function
        function printTable() {
            const table = document.querySelector('.enrollments-table').cloneNode(true);
            // Remove action buttons column
            table.querySelectorAll('tr').forEach(row => {
                if(row.lastElementChild) {
                    row.removeChild(row.lastElementChild);
                }
            });
            
            const newWindow = window.open('', '_blank');
            newWindow.document.write(`
                <html>
                    <head>
                        <title>Enrollment Records</title>
                        <style>
                            body { font-family: 'Inter', Arial, sans-serif; padding: 30px; }
                            h2 { color: #0B4F2E; margin-bottom: 5px; }
                            h3 { color: #666; font-weight: 400; margin-bottom: 20px; }
                            table { border-collapse: collapse; width: 100%; margin-top: 20px; }
                            th { background: #f0f0f0; padding: 12px; text-align: left; font-size: 13px; }
                            td { padding: 10px; border-bottom: 1px solid #ddd; font-size: 13px; }
                            .header { text-align: center; margin-bottom: 30px; }
                            .date { color: #999; font-size: 12px; margin-top: 10px; }
                            .badge { padding: 4px 8px; border-radius: 12px; font-size: 11px; }
                            .badge-pending { background: #fff3cd; color: #856404; }
                            .badge-enrolled { background: #d4edda; color: #155724; }
                            .badge-rejected { background: #f8d7da; color: #721c24; }
                        </style>
                    </head>
                    <body>
                        <div class="header">
                            <h2>Placido L. Señor Senior High School</h2>
                            <h3>Enrollment Records</h3>
                            <div class="date">Generated on: ${new Date().toLocaleString()}</div>
                        </div>
                        ${table.outerHTML}
                    </body>
                </html>
            `);
            newWindow.document.close();
            newWindow.print();
        }
    </script>
    <li><a href="sections.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'sections.php' ? 'active' : ''; ?>">
    <i class="fas fa-layer-group"></i><span>Sections</span>
</a></li>
</body>
</html>