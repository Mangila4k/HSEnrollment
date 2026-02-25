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

// Handle delete action
if(isset($_GET['delete'])) {
    $delete_id = $_GET['delete'];
    
    // Check if student has enrollments
    $check_enrollments = $conn->query("SELECT id FROM enrollments WHERE student_id = '$delete_id'");
    if($check_enrollments && $check_enrollments->num_rows > 0) {
        // Delete enrollments first
        $conn->query("DELETE FROM enrollments WHERE student_id = '$delete_id'");
    }
    
    // Check if student has attendance records
    $check_attendance = $conn->query("SELECT id FROM attendance WHERE student_id = '$delete_id'");
    if($check_attendance && $check_attendance->num_rows > 0) {
        // Delete attendance records first
        $conn->query("DELETE FROM attendance WHERE student_id = '$delete_id'");
    }
    
    // Delete the student
    $delete = $conn->query("DELETE FROM users WHERE id = '$delete_id' AND role = 'Student'");
    
    if($delete) {
        $success_message = "Student deleted successfully!";
    } else {
        $error_message = "Error deleting student.";
    }
}

// Get filter parameters
$grade_filter = isset($_GET['grade']) ? $_GET['grade'] : '';
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';
$search_query = isset($_GET['search']) ? $_GET['search'] : '';

// Build the query
$query = "
    SELECT u.*, 
           e.id as enrollment_id,
           e.grade_id,
           e.status as enrollment_status,
           e.strand,
           e.school_year,
           e.created_at as enrolled_date,
           g.grade_name
    FROM users u
    LEFT JOIN enrollments e ON u.id = e.student_id
    LEFT JOIN grade_levels g ON e.grade_id = g.id
    WHERE u.role = 'Student'
";

if(!empty($grade_filter)) {
    $query .= " AND e.grade_id = '$grade_filter'";
}

if(!empty($status_filter)) {
    $query .= " AND e.status = '$status_filter'";
}

if(!empty($search_query)) {
    $query .= " AND (u.fullname LIKE '%$search_query%' OR u.email LIKE '%$search_query%' OR u.id_number LIKE '%$search_query%')";
}

$query .= " ORDER BY u.created_at DESC";

$students = $conn->query($query);

// Get statistics
$total_students = $conn->query("SELECT COUNT(*) as count FROM users WHERE role = 'Student'")->fetch_assoc()['count'];

$enrolled_students = $conn->query("
    SELECT COUNT(DISTINCT u.id) as count 
    FROM users u 
    JOIN enrollments e ON u.id = e.student_id 
    WHERE u.role = 'Student' AND e.status = 'Enrolled'
")->fetch_assoc()['count'];

$pending_students = $conn->query("
    SELECT COUNT(DISTINCT u.id) as count 
    FROM users u 
    JOIN enrollments e ON u.id = e.student_id 
    WHERE u.role = 'Student' AND e.status = 'Pending'
")->fetch_assoc()['count'];

$rejected_students = $conn->query("
    SELECT COUNT(DISTINCT u.id) as count 
    FROM users u 
    JOIN enrollments e ON u.id = e.student_id 
    WHERE u.role = 'Student' AND e.status = 'Rejected'
")->fetch_assoc()['count'];

$no_enrollment = $total_students - ($enrolled_students + $pending_students + $rejected_students);

// Get grade levels for filter
$grade_levels = $conn->query("SELECT * FROM grade_levels ORDER BY id");
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
            grid-template-columns: repeat(5, 1fr);
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
            background: linear-gradient(135deg, #0B4F2E, #1a7a42);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 18px;
            font-weight: 600;
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

        .btn-enroll:hover {
            color: #28a745;
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

        /* Pagination */
        .pagination {
            margin-top: 25px;
            display: flex;
            justify-content: flex-end;
            gap: 8px;
        }

        .page-btn {
            width: 40px;
            height: 40px;
            border-radius: 10px;
            border: 1px solid var(--border-color);
            background: white;
            color: var(--text-secondary);
            cursor: pointer;
            transition: all 0.3s;
        }

        .page-btn:hover,
        .page-btn.active {
            background: #0B4F2E;
            color: white;
            border-color: #0B4F2E;
        }

        /* Responsive */
        @media (max-width: 1200px) {
            .stats-container {
                grid-template-columns: repeat(3, 1fr);
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
                        <h3>Enrolled</h3>
                        <div class="stat-icon">
                            <i class="fas fa-check-circle"></i>
                        </div>
                    </div>
                    <div class="stat-number"><?php echo $enrolled_students; ?></div>
                    <div class="stat-label">Active enrollments</div>
                </div>

                <div class="stat-card">
                    <div class="stat-header">
                        <h3>Pending</h3>
                        <div class="stat-icon">
                            <i class="fas fa-clock"></i>
                        </div>
                    </div>
                    <div class="stat-number"><?php echo $pending_students; ?></div>
                    <div class="stat-label">Awaiting approval</div>
                </div>

                <div class="stat-card">
                    <div class="stat-header">
                        <h3>Rejected</h3>
                        <div class="stat-icon">
                            <i class="fas fa-times-circle"></i>
                        </div>
                    </div>
                    <div class="stat-number"><?php echo $rejected_students; ?></div>
                    <div class="stat-label">Not enrolled</div>
                </div>

                <div class="stat-card">
                    <div class="stat-header">
                        <h3>No Enrollment</h3>
                        <div class="stat-icon">
                            <i class="fas fa-user-slash"></i>
                        </div>
                    </div>
                    <div class="stat-number"><?php echo $no_enrollment; ?></div>
                    <div class="stat-label">No records</div>
                </div>
            </div>

            <!-- Actions Bar -->
            <div class="actions-bar">
                <form method="GET" action="" style="display: flex; gap: 15px; flex-wrap: wrap; width: 100%;">
                    <div class="filter-group">
                        <select name="grade" class="filter-select">
                            <option value="">All Grades</option>
                            <?php while($grade = $grade_levels->fetch_assoc()): ?>
                                <option value="<?php echo $grade['id']; ?>" <?php echo $grade_filter == $grade['id'] ? 'selected' : ''; ?>>
                                    <?php echo $grade['grade_name']; ?>
                                </option>
                            <?php endwhile; ?>
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

                        <a href="students.php" class="btn-reset">
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
                    <h3><i class="fas fa-user-graduate"></i> Student List</h3>
                    <span class="badge badge-enrolled">Total: <?php echo $students ? $students->num_rows : 0; ?> students</span>
                </div>

                <div class="table-container">
                    <table class="students-table">
                        <thead>
                            <tr>
                                <th>Student</th>
                                <th>ID Number</th>
                                <th>Grade & Strand</th>
                                <th>Status</th>
                                <th>Enrolled Date</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if($students && $students->num_rows > 0): ?>
                                <?php while($student = $students->fetch_assoc()): ?>
                                    <tr>
                                        <td>
                                            <div class="student-info">
                                                <div class="student-avatar">
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
                                            <?php if($student['grade_name']): ?>
                                                <span class="grade-tag"><?php echo htmlspecialchars($student['grade_name']); ?></span>
                                                <?php if($student['strand']): ?>
                                                    <span class="strand-tag"><?php echo htmlspecialchars($student['strand']); ?></span>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <span class="badge-none">Not enrolled</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if($student['enrollment_status']): ?>
                                                <span class="badge badge-<?php echo strtolower($student['enrollment_status']); ?>">
                                                    <?php echo $student['enrollment_status']; ?>
                                                </span>
                                            <?php else: ?>
                                                <span class="badge badge-none">No record</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if($student['enrolled_date']): ?>
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
                                                <?php if(!$student['enrollment_status']): ?>
                                                    <a href="enroll_student.php?id=<?php echo $student['id']; ?>" class="btn-icon btn-enroll" title="Enroll">
                                                        <i class="fas fa-user-plus"></i>
                                                    </a>
                                                <?php endif; ?>
                                                <a href="?delete=<?php echo $student['id']; ?>" class="btn-icon btn-delete" title="Delete" 
                                                   onclick="return confirm('Are you sure you want to delete this student? This will also delete all associated records.')">
                                                    <i class="fas fa-trash"></i>
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="6">
                                        <div class="no-data">
                                            <i class="fas fa-user-graduate"></i>
                                            <h3>No Students Found</h3>
                                            <p>Click the "Add New Student" button to add your first student.</p>
                                        </div>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <div class="pagination">
                    <button class="page-btn active">1</button>
                    <button class="page-btn">2</button>
                    <button class="page-btn">3</button>
                    <button class="page-btn">4</button>
                    <button class="page-btn">5</button>
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
        document.querySelector('.search-box input').addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                this.form.submit();
            }
        });
    </script>
</body>
</html>