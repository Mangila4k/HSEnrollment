<?php
session_start();
include("../config/database.php");

// Check if user is admin
if(!isset($_SESSION['user']) || $_SESSION['user']['role'] != 'Admin'){
    header("Location: ../auth/login.php");
    exit();
}

$admin_name = $_SESSION['user']['fullname'];
$admin_id = $_SESSION['user']['id'];
$success_message = '';
$error_message = '';

// Get admin profile picture
$admin_stmt = $conn->prepare("SELECT profile_picture FROM users WHERE id = ?");
$admin_stmt->execute([$admin_id]);
$admin_data = $admin_stmt->fetch(PDO::FETCH_ASSOC);
$profile_picture = $admin_data['profile_picture'] ?? null;

// Check for session messages
if(isset($_SESSION['success_message'])) {
    $success_message = $_SESSION['success_message'];
    unset($_SESSION['success_message']);
}

if(isset($_SESSION['error_message'])) {
    $error_message = $_SESSION['error_message'];
    unset($_SESSION['error_message']);
}

// Handle delete action
if(isset($_GET['delete'])) {
    $delete_id = $_GET['delete'];
    
    try {
        // Check if student has enrollments
        $check_enrollments = $conn->prepare("SELECT id FROM enrollments WHERE student_id = ?");
        $check_enrollments->execute([$delete_id]);
        if($check_enrollments->rowCount() > 0) {
            // Delete enrollments first
            $delete_enrollments = $conn->prepare("DELETE FROM enrollments WHERE student_id = ?");
            $delete_enrollments->execute([$delete_id]);
        }
        
        // Check if student has attendance records
        $check_attendance = $conn->prepare("SELECT id FROM attendance WHERE student_id = ?");
        $check_attendance->execute([$delete_id]);
        if($check_attendance->rowCount() > 0) {
            // Delete attendance records first
            $delete_attendance = $conn->prepare("DELETE FROM attendance WHERE student_id = ?");
            $delete_attendance->execute([$delete_id]);
        }
        
        // Delete the student
        $delete = $conn->prepare("DELETE FROM users WHERE id = ? AND role = 'Student'");
        $delete->execute([$delete_id]);
        
        if($delete->rowCount() > 0) {
            $success_message = "Student deleted successfully!";
        } else {
            $error_message = "Error deleting student.";
        }
    } catch(PDOException $e) {
        $error_message = "Error: " . $e->getMessage();
    }
}

// Get filter parameters
$grade_filter = isset($_GET['grade']) ? $_GET['grade'] : '';
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';
$search_query = isset($_GET['search']) ? $_GET['search'] : '';
$enrollee_type = isset($_GET['type']) ? $_GET['type'] : 'all'; // all, new, old, not_enrolled

// Get current school year
$current_year = date('Y');
$current_sy = $current_year . '-' . ($current_year + 1);
$previous_sy = ($current_year - 1) . '-' . $current_year;

// Build the query to identify new vs old students vs not enrolled
$query = "
    SELECT u.*, 
           e.id as enrollment_id,
           e.grade_id,
           e.status as enrollment_status,
           e.strand,
           e.school_year,
           e.created_at as enrolled_date,
           g.grade_name,
           (SELECT COUNT(*) FROM enrollments WHERE student_id = u.id) as total_enrollments,
           (SELECT COUNT(*) FROM enrollments WHERE student_id = u.id AND school_year < ?) as previous_enrollments,
           (SELECT COUNT(*) FROM enrollments WHERE student_id = u.id AND school_year = ?) as current_enrollments
    FROM users u
    LEFT JOIN enrollments e ON u.id = e.student_id AND e.school_year = ?
    LEFT JOIN grade_levels g ON e.grade_id = g.id
    WHERE u.role = 'Student'
";

$params = [$current_sy, $current_sy, $current_sy];

// Add enrollee type filter
if($enrollee_type == 'new') {
    // Students who have enrollment but only current school year (first time)
    $query .= " AND (SELECT COUNT(*) FROM enrollments WHERE student_id = u.id) = 1";
    $query .= " AND (SELECT COUNT(*) FROM enrollments WHERE student_id = u.id AND school_year = ?) = 1";
    $params[] = $current_sy;
} elseif($enrollee_type == 'old') {
    // Students who have previous enrollments (returning students with at least one enrollment in current year)
    $query .= " AND (SELECT COUNT(*) FROM enrollments WHERE student_id = u.id AND school_year < ?) > 0";
    $query .= " AND (SELECT COUNT(*) FROM enrollments WHERE student_id = u.id AND school_year = ?) = 1";
    $params[] = $current_sy;
    $params[] = $current_sy;
} elseif($enrollee_type == 'not_enrolled') {
    // Students with no enrollment record at all
    $query .= " AND (SELECT COUNT(*) FROM enrollments WHERE student_id = u.id) = 0";
}

if(!empty($grade_filter)) {
    $query .= " AND e.grade_id = ?";
    $params[] = $grade_filter;
}

if(!empty($status_filter)) {
    $query .= " AND e.status = ?";
    $params[] = $status_filter;
}

if(!empty($search_query)) {
    $query .= " AND (u.fullname LIKE ? OR u.email LIKE ? OR u.id_number LIKE ?)";
    $search_term = "%$search_query%";
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
}

$query .= " ORDER BY u.created_at DESC";

$stmt = $conn->prepare($query);
$stmt->execute($params);
$students = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get statistics
$total_students_stmt = $conn->prepare("SELECT COUNT(*) as count FROM users WHERE role = 'Student'");
$total_students_stmt->execute();
$total_students = $total_students_stmt->fetch(PDO::FETCH_ASSOC)['count'];

// Get new students (first-time enrollees with current enrollment)
$new_students_stmt = $conn->prepare("
    SELECT COUNT(DISTINCT u.id) as count 
    FROM users u 
    INNER JOIN enrollments e ON u.id = e.student_id 
    WHERE u.role = 'Student' 
    AND e.school_year = ?
    AND (SELECT COUNT(*) FROM enrollments WHERE student_id = u.id) = 1
");
$new_students_stmt->execute([$current_sy]);
$new_students = $new_students_stmt->fetch(PDO::FETCH_ASSOC)['count'];

// Get old students (have previous enrollments and current enrollment)
$old_students_stmt = $conn->prepare("
    SELECT COUNT(DISTINCT u.id) as count 
    FROM users u 
    INNER JOIN enrollments e ON u.id = e.student_id 
    WHERE u.role = 'Student' 
    AND e.school_year = ?
    AND (SELECT COUNT(*) FROM enrollments WHERE student_id = u.id AND school_year < ?) > 0
");
$old_students_stmt->execute([$current_sy, $current_sy]);
$old_students = $old_students_stmt->fetch(PDO::FETCH_ASSOC)['count'];

// Get not enrolled students (no enrollment records)
$not_enrolled_stmt = $conn->prepare("
    SELECT COUNT(*) as count 
    FROM users u 
    WHERE u.role = 'Student' 
    AND NOT EXISTS (SELECT 1 FROM enrollments WHERE student_id = u.id)
");
$not_enrolled_stmt->execute();
$not_enrolled = $not_enrolled_stmt->fetch(PDO::FETCH_ASSOC)['count'];

$enrolled_students_stmt = $conn->prepare("
    SELECT COUNT(DISTINCT u.id) as count 
    FROM users u 
    JOIN enrollments e ON u.id = e.student_id 
    WHERE u.role = 'Student' AND e.status = 'Enrolled' AND e.school_year = ?
");
$enrolled_students_stmt->execute([$current_sy]);
$enrolled_students = $enrolled_students_stmt->fetch(PDO::FETCH_ASSOC)['count'];

$pending_students_stmt = $conn->prepare("
    SELECT COUNT(DISTINCT u.id) as count 
    FROM users u 
    JOIN enrollments e ON u.id = e.student_id 
    WHERE u.role = 'Student' AND e.status = 'Pending' AND e.school_year = ?
");
$pending_students_stmt->execute([$current_sy]);
$pending_students = $pending_students_stmt->fetch(PDO::FETCH_ASSOC)['count'];

$rejected_students_stmt = $conn->prepare("
    SELECT COUNT(DISTINCT u.id) as count 
    FROM users u 
    JOIN enrollments e ON u.id = e.student_id 
    WHERE u.role = 'Student' AND e.status = 'Rejected' AND e.school_year = ?
");
$rejected_students_stmt->execute([$current_sy]);
$rejected_students = $rejected_students_stmt->fetch(PDO::FETCH_ASSOC)['count'];

// Get grade levels for filter
$grade_levels_stmt = $conn->prepare("SELECT * FROM grade_levels ORDER BY id");
$grade_levels_stmt->execute();
$grade_levels = $grade_levels_stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Students Management - Admin Dashboard</title>
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        /* Copy all styles from previous students.php but update sidebar avatar styles */
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
            --new-student: #28a745;
            --old-student: #17a2b8;
            --not-enrolled: #6c757d;
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

        .admin-info {
            text-align: center;
            padding: 20px 0;
            border-bottom: 1px solid rgba(255, 255, 255, 0.2);
            margin-bottom: 20px;
        }

        .admin-avatar {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 15px;
            border: 3px solid white;
            overflow: hidden;
            background: #FFD700;
        }

        .admin-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .admin-avatar .avatar-initial {
            width: 100%;
            height: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 32px;
            font-weight: bold;
            color: #0B4F2E;
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
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            border-radius: 15px;
            padding: 20px;
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
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, rgba(11, 79, 46, 0.1) 0%, rgba(26, 122, 66, 0.1) 100%);
            border-radius: 50%;
            transform: translate(30px, -30px);
        }

        .stat-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
        }

        .stat-header h3 {
            color: var(--text-secondary);
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .stat-icon {
            width: 35px;
            height: 35px;
            background: linear-gradient(135deg, #0B4F2E, #1a7a42);
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 16px;
        }

        .stat-number {
            font-size: 24px;
            font-weight: 700;
            color: var(--text-primary);
            margin-bottom: 5px;
        }

        .stat-label {
            color: var(--text-secondary);
            font-size: 12px;
        }

        /* Tab Navigation */
        .tab-navigation {
            background: white;
            border-radius: 15px;
            padding: 5px;
            margin-bottom: 25px;
            display: flex;
            gap: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
        }

        .tab-btn {
            flex: 1;
            padding: 15px;
            border: none;
            background: transparent;
            font-size: 16px;
            font-weight: 600;
            color: var(--text-secondary);
            cursor: pointer;
            border-radius: 12px;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            text-decoration: none;
        }

        .tab-btn i {
            font-size: 18px;
        }

        .tab-btn:hover {
            background: var(--hover-color);
            color: #0B4F2E;
        }

        .tab-btn.active {
            background: linear-gradient(135deg, #0B4F2E, #1a7a42);
            color: white;
        }

        .tab-btn.active i {
            color: #FFD700;
        }

        /* Actions Bar */
        .actions-bar {
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

        .filter-group {
            display: flex;
            align-items: center;
            gap: 15px;
            flex-wrap: wrap;
        }

        .filter-select {
            padding: 10px 15px;
            border: 2px solid var(--border-color);
            border-radius: 10px;
            font-size: 14px;
            min-width: 150px;
            background: white;
        }

        .filter-select:focus {
            border-color: #0B4F2E;
            outline: none;
        }

        .btn-add {
            background: #0B4F2E;
            color: white;
            padding: 12px 25px;
            border-radius: 12px;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 10px;
            transition: all 0.3s ease;
            font-weight: 600;
            border: none;
            cursor: pointer;
        }

        .btn-add:hover {
            background: #1a7a42;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(11, 79, 46, 0.3);
        }

        .btn-add i {
            font-size: 16px;
        }

        .btn-reset {
            background: #f8f9fa;
            color: var(--text-secondary);
            padding: 10px 20px;
            border-radius: 10px;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s ease;
            font-weight: 500;
            border: 1px solid var(--border-color);
        }

        .btn-reset:hover {
            border-color: #0B4F2E;
            color: #0B4F2E;
        }

        .search-box {
            display: flex;
            align-items: center;
            gap: 10px;
            background: white;
            border: 2px solid var(--border-color);
            border-radius: 12px;
            padding: 0 15px;
            flex: 1;
            max-width: 300px;
        }

        .search-box i {
            color: var(--text-secondary);
        }

        .search-box input {
            border: none;
            padding: 12px 0;
            width: 100%;
            font-size: 14px;
        }

        .search-box input:focus {
            outline: none;
        }

        /* Table Card */
        .table-card {
            background: white;
            border-radius: 20px;
            padding: 25px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
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

        .table-container {
            overflow-x: auto;
        }

        .students-table {
            width: 100%;
            border-collapse: collapse;
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
        }

        .students-table td {
            padding: 15px;
            border-bottom: 1px solid var(--border-color);
            color: var(--text-primary);
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
            width: 45px;
            height: 45px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 18px;
            font-weight: 600;
        }

        .student-avatar.new {
            background: linear-gradient(135deg, #28a745, #20c997);
        }

        .student-avatar.old {
            background: linear-gradient(135deg, #17a2b8, #0d6efd);
        }

        .student-avatar.not-enrolled {
            background: linear-gradient(135deg, #6c757d, #495057);
        }

        .student-details h4 {
            font-size: 16px;
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

        .student-details span i {
            font-size: 11px;
        }

        .badge {
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
            display: inline-block;
        }

        .badge-enrolled {
            background: rgba(40, 167, 69, 0.1);
            color: #28a745;
        }

        .badge-pending {
            background: rgba(255, 193, 7, 0.1);
            color: #ffc107;
        }

        .badge-rejected {
            background: rgba(220, 53, 69, 0.1);
            color: #dc3545;
        }

        .badge-none {
            background: rgba(108, 117, 125, 0.1);
            color: #6c757d;
        }

        .badge-new {
            background: rgba(40, 167, 69, 0.1);
            color: #28a745;
            border: 1px solid #28a745;
        }

        .badge-old {
            background: rgba(23, 162, 184, 0.1);
            color: #17a2b8;
            border: 1px solid #17a2b8;
        }

        .badge-not-enrolled {
            background: rgba(108, 117, 125, 0.1);
            color: #6c757d;
            border: 1px solid #6c757d;
        }

        .grade-tag {
            background: rgba(11, 79, 46, 0.1);
            color: #0B4F2E;
            padding: 3px 8px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 500;
            display: inline-block;
        }

        .strand-tag {
            background: rgba(255, 215, 0, 0.1);
            color: #b8860b;
            padding: 3px 8px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 500;
            display: inline-block;
            margin-left: 5px;
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
            color: #0B4F2E;
        }

        .btn-edit:hover {
            color: #007bff;
        }

        .btn-delete:hover {
            color: #dc3545;
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
            
            .admin-info h3,
            .admin-info p,
            .menu-section h3,
            .menu-items a span {
                display: none;
            }
            
            .admin-avatar {
                width: 50px;
                height: 50px;
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
            
            .tab-navigation {
                flex-direction: column;
            }
            
            .actions-bar {
                flex-direction: column;
                align-items: stretch;
            }
            
            .filter-group {
                flex-direction: column;
                width: 100%;
            }
            
            .filter-select {
                width: 100%;
            }
            
            .search-box {
                max-width: 100%;
            }
            
            .btn-add {
                width: 100%;
                justify-content: center;
            }
            
            .student-info {
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
            <div class="admin-info">
                <div class="admin-avatar">
                    <?php if($profile_picture && file_exists("../" . $profile_picture)): ?>
                        <img src="../<?php echo $profile_picture; ?>" alt="Profile Picture">
                    <?php else: ?>
                        <div class="avatar-initial">
                            <?php echo strtoupper(substr($admin_name, 0, 1)); ?>
                        </div>
                    <?php endif; ?>
                </div>
                <h3><?php echo htmlspecialchars(explode(' ', $admin_name)[0]); ?></h3>
                <p><i class="fas fa-user-shield"></i> Administrator</p>
            </div>
            
            <div class="menu-section">
                <h3>MAIN MENU</h3>
                <ul class="menu-items">
                    <li><a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> <span>Dashboard</span></a></li>
                    <li><a href="students.php" class="active"><i class="fas fa-user-graduate"></i> <span>Students</span></a></li>
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
                <h1>Students Management</h1>
                <p>Manage student accounts and enrollment records</p>
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

            <!-- Statistics -->
            <div class="stats-container">
                <div class="stat-card">
                    <div class="stat-header">
                        <h3>Total Students</h3>
                        <div class="stat-icon">
                            <i class="fas fa-user-graduate"></i>
                        </div>
                    </div>
                    <div class="stat-number"><?php echo $total_students; ?></div>
                    <div class="stat-label">All students</div>
                </div>

                <div class="stat-card">
                    <div class="stat-header">
                        <h3>New Enrollees</h3>
                        <div class="stat-icon">
                            <i class="fas fa-star-of-life"></i>
                        </div>
                    </div>
                    <div class="stat-number"><?php echo $new_students; ?></div>
                    <div class="stat-label">First-time enrollees</div>
                </div>

                <div class="stat-card">
                    <div class="stat-header">
                        <h3>Old Enrollees</h3>
                        <div class="stat-icon">
                            <i class="fas fa-history"></i>
                        </div>
                    </div>
                    <div class="stat-number"><?php echo $old_students; ?></div>
                    <div class="stat-label">Returning students</div>
                </div>

                <div class="stat-card">
                    <div class="stat-header">
                        <h3>Not Enrolled</h3>
                        <div class="stat-icon">
                            <i class="fas fa-user-slash"></i>
                        </div>
                    </div>
                    <div class="stat-number"><?php echo $not_enrolled; ?></div>
                    <div class="stat-label">No enrollment record</div>
                </div>
            </div>

            <!-- Tab Navigation -->
            <div class="tab-navigation">
                <a href="?type=all" class="tab-btn <?php echo $enrollee_type == 'all' ? 'active' : ''; ?>">
                    <i class="fas fa-users"></i> All Students
                </a>
                <a href="?type=new" class="tab-btn <?php echo $enrollee_type == 'new' ? 'active' : ''; ?>">
                    <i class="fas fa-star-of-life"></i> New Enrollees
                </a>
                <a href="?type=old" class="tab-btn <?php echo $enrollee_type == 'old' ? 'active' : ''; ?>">
                    <i class="fas fa-history"></i> Old Enrollees
                </a>
                <a href="?type=not_enrolled" class="tab-btn <?php echo $enrollee_type == 'not_enrolled' ? 'active' : ''; ?>">
                    <i class="fas fa-user-slash"></i> Not Enrolled
                </a>
            </div>

            <!-- Actions Bar -->
            <div class="actions-bar">
                <form method="GET" action="" style="display: flex; gap: 15px; flex-wrap: wrap; width: 100%;">
                    <input type="hidden" name="type" value="<?php echo $enrollee_type; ?>">
                    <div class="filter-group">
                        <select name="grade" class="filter-select">
                            <option value="">All Grades</option>
                            <?php foreach($grade_levels as $grade): ?>
                                <option value="<?php echo $grade['id']; ?>" <?php echo $grade_filter == $grade['id'] ? 'selected' : ''; ?>>
                                    <?php echo $grade['grade_name']; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>

                        <select name="status" class="filter-select">
                            <option value="">All Status</option>
                            <option value="Enrolled" <?php echo $status_filter == 'Enrolled' ? 'selected' : ''; ?>>Enrolled</option>
                            <option value="Pending" <?php echo $status_filter == 'Pending' ? 'selected' : ''; ?>>Pending</option>
                            <option value="Rejected" <?php echo $status_filter == 'Rejected' ? 'selected' : ''; ?>>Rejected</option>
                        </select>

                        <button type="submit" class="btn-add" style="padding: 10px 20px;">
                            <i class="fas fa-filter"></i> Apply Filters
                        </button>

                        <a href="students.php?type=<?php echo $enrollee_type; ?>" class="btn-reset">
                            <i class="fas fa-redo-alt"></i> Reset
                        </a>
                    </div>

                    <div class="search-box">
                        <i class="fas fa-search"></i>
                        <input type="text" name="search" placeholder="Search students..." value="<?php echo htmlspecialchars($search_query); ?>">
                    </div>
                </form>

                <a href="add_student.php" class="btn-add">
                    <i class="fas fa-plus-circle"></i> Add New Student
                </a>
            </div>

            <!-- Students Table -->
            <div class="table-card">
                <div class="table-header">
                    <h3>
                        <i class="fas fa-user-graduate"></i> 
                        <?php 
                        if($enrollee_type == 'new') echo 'New Enrollees List';
                        elseif($enrollee_type == 'old') echo 'Old Enrollees List';
                        elseif($enrollee_type == 'not_enrolled') echo 'Not Enrolled Students List';
                        else echo 'Student List';
                        ?>
                    </h3>
                    <span class="badge badge-enrolled">Total: <?php echo count($students); ?> students</span>
                </div>

                <div class="table-container">
                    <table class="students-table">
                        <thead>
                            <tr>
                                <th>Student</th>
                                <th>ID Number</th>
                                <th>Type</th>
                                <th>Grade & Strand</th>
                                <th>Status</th>
                                <th>Enrolled Date</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if(count($students) > 0): ?>
                                <?php foreach($students as $student): 
                                    // Determine student type
                                    if($student['total_enrollments'] == 0) {
                                        $student_type = 'not_enrolled';
                                        $student_type_label = 'Not Enrolled';
                                        $student_type_icon = 'user-slash';
                                    } elseif($student['total_enrollments'] == 1 && $student['current_enrollments'] == 1) {
                                        $student_type = 'new';
                                        $student_type_label = 'New Enrollee';
                                        $student_type_icon = 'star-of-life';
                                    } elseif($student['previous_enrollments'] > 0 && $student['current_enrollments'] == 1) {
                                        $student_type = 'old';
                                        $student_type_label = 'Old Enrollee';
                                        $student_type_icon = 'history';
                                    } else {
                                        $student_type = 'not_enrolled';
                                        $student_type_label = 'Not Enrolled';
                                        $student_type_icon = 'user-slash';
                                    }
                                ?>
                                    <tr>
                                        <td>
                                            <div class="student-info">
                                                <div class="student-avatar <?php echo $student_type; ?>">
                                                    <?php echo strtoupper(substr($student['fullname'], 0, 1)); ?>
                                                </div>
                                                <div class="student-details">
                                                    <h4><?php echo htmlspecialchars($student['fullname']); ?></h4>
                                                    <span><i class="fas fa-envelope"></i> <?php echo htmlspecialchars($student['email']); ?></span>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="grade-tag"><?php echo $student['id_number'] ?? 'N/A'; ?></span>
                                        </td>
                                        <td>
                                            <span class="badge badge-<?php echo $student_type; ?>">
                                                <i class="fas fa-<?php echo $student_type_icon; ?>"></i>
                                                <?php echo $student_type_label; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if(!empty($student['grade_name'])): ?>
                                                <span class="grade-tag"><?php echo htmlspecialchars($student['grade_name']); ?></span>
                                                <?php if(!empty($student['strand'])): ?>
                                                    <span class="strand-tag"><?php echo htmlspecialchars($student['strand']); ?></span>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <span class="badge-none">Not enrolled</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if(!empty($student['enrollment_status'])): ?>
                                                <span class="badge badge-<?php echo strtolower($student['enrollment_status']); ?>">
                                                    <?php echo $student['enrollment_status']; ?>
                                                </span>
                                            <?php else: ?>
                                                <span class="badge badge-none">No record</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if(!empty($student['enrolled_date'])): ?>
                                                <span class="activity-time">
                                                    <i class="far fa-calendar"></i>
                                                    <?php echo date('M d, Y', strtotime($student['enrolled_date'])); ?>
                                                </span>
                                            <?php else: ?>
                                                <span class="activity-time">—</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="action-btns">
                                                <a href="view_student.php?id=<?php echo $student['id']; ?>" class="btn-icon btn-view" title="View Details">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                                <a href="edit_student.php?id=<?php echo $student['id']; ?>" class="btn-icon btn-edit" title="Edit">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                                <a href="?delete=<?php echo $student['id']; ?>&type=<?php echo $enrollee_type; ?>" class="btn-icon btn-delete" title="Delete" 
                                                   onclick="return confirm('Are you sure you want to delete this student? This will also delete all associated records.')">
                                                    <i class="fas fa-trash"></i>
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="7">
                                        <div class="no-data">
                                            <i class="fas fa-user-graduate"></i>
                                            <h3>No Students Found</h3>
                                            <p>
                                                <?php 
                                                if($enrollee_type == 'new') {
                                                    echo 'No new enrollees found for this school year.';
                                                } elseif($enrollee_type == 'old') {
                                                    echo 'No returning students found.';
                                                } elseif($enrollee_type == 'not_enrolled') {
                                                    echo 'No students without enrollment records found.';
                                                } else {
                                                    echo 'Click the "Add New Student" button to add your first student.';
                                                }
                                                ?>
                                            </p>
                                        </div>
                                    </td>
                                </tr>
                            <?php endif; ?>
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

        // Auto-submit form when filters change
        document.querySelectorAll('.filter-select').forEach(select => {
            select.addEventListener('change', function() {
                this.form.submit();
            });
        });

        // Search on Enter key
        const searchInput = document.querySelector('.search-box input');
        if(searchInput) {
            searchInput.addEventListener('keypress', function(e) {
                if (e.key === 'Enter') {
                    this.form.submit();
                }
            });
        }
    </script>
</body>
</html>