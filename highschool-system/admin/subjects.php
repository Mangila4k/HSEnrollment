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
    
    // Check if subject has attendance records
    $check_attendance = $conn->query("SELECT id FROM attendance WHERE subject_id = '$delete_id'");
    if($check_attendance && $check_attendance->num_rows > 0) {
        $error_message = "Cannot delete subject because it has attendance records.";
    } else {
        $delete = $conn->query("DELETE FROM subjects WHERE id = '$delete_id'");
        if($delete) {
            $success_message = "Subject deleted successfully!";
        } else {
            $error_message = "Error deleting subject.";
        }
    }
}

// Get filter parameters
$grade_filter = isset($_GET['grade']) ? $_GET['grade'] : '';
$search_query = isset($_GET['search']) ? $_GET['search'] : '';

// Build the query
$query = "
    SELECT s.*, g.grade_name,
           (SELECT COUNT(*) FROM attendance WHERE subject_id = s.id) as attendance_count
    FROM subjects s
    JOIN grade_levels g ON s.grade_id = g.id
    WHERE 1=1
";

if(!empty($grade_filter)) {
    $query .= " AND s.grade_id = '$grade_filter'";
}

if(!empty($search_query)) {
    $query .= " AND s.subject_name LIKE '%$search_query%'";
}

$query .= " ORDER BY g.id, s.subject_name";

$subjects = $conn->query($query);

// Get statistics
$total_subjects = $conn->query("SELECT COUNT(*) as count FROM subjects")->fetch_assoc()['count'];

// Get grade levels for filter
$grade_levels = $conn->query("SELECT * FROM grade_levels ORDER BY id");

// Get subject count per grade
$subjects_per_grade = [];
$grade_count_query = "
    SELECT g.id, g.grade_name, COUNT(s.id) as subject_count
    FROM grade_levels g
    LEFT JOIN subjects s ON g.id = s.grade_id
    GROUP BY g.id
    ORDER BY g.id
";
$grade_counts = $conn->query($grade_count_query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Subjects Management - Admin Dashboard</title>
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

        /* Grade Cards */
        .grade-cards {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
            margin-bottom: 30px;
        }

        .grade-card {
            background: white;
            border-radius: 15px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            display: flex;
            align-items: center;
            gap: 15px;
            transition: all 0.3s ease;
        }

        .grade-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
        }

        .grade-icon {
            width: 50px;
            height: 50px;
            background: linear-gradient(135deg, #0B4F2E, #1a7a42);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 24px;
        }

        .grade-info {
            flex: 1;
        }

        .grade-info h4 {
            color: var(--text-primary);
            font-size: 16px;
            margin-bottom: 5px;
        }

        .grade-info p {
            color: var(--text-secondary);
            font-size: 13px;
        }

        .grade-badge {
            background: rgba(11, 79, 46, 0.1);
            color: #0B4F2E;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: 600;
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

        .subjects-table {
            width: 100%;
            border-collapse: collapse;
        }

        .subjects-table th {
            text-align: left;
            padding: 15px;
            background: #f8f9fa;
            color: var(--text-secondary);
            font-weight: 600;
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .subjects-table td {
            padding: 15px;
            border-bottom: 1px solid var(--border-color);
            color: var(--text-primary);
        }

        .subjects-table tbody tr:hover {
            background: var(--hover-color);
        }

        .subject-info {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .subject-icon {
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

        .subject-details h4 {
            font-size: 16px;
            margin-bottom: 3px;
            color: var(--text-primary);
        }

        .subject-details span {
            font-size: 12px;
            color: var(--text-secondary);
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .subject-details span i {
            font-size: 11px;
        }

        .grade-tag {
            background: rgba(11, 79, 46, 0.1);
            color: #0B4F2E;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
            display: inline-block;
        }

        .attendance-badge {
            background: rgba(76, 201, 240, 0.1);
            color: #4cc9f0;
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
                grid-template-columns: repeat(2, 1fr);
            }
            
            .grade-cards {
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
            
            .grade-cards {
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
            
            .subject-info {
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
                    <li><a href="subjects.php" class="active"><i class="fas fa-book"></i> <span>Subjects</span></a></li>
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
                <h1>Subjects Management</h1>
                <p>Manage subjects offered per grade level</p>
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
                        <h3>Total Subjects</h3>
                        <div class="stat-icon">
                            <i class="fas fa-book"></i>
                        </div>
                    </div>
                    <div class="stat-number"><?php echo $total_subjects; ?></div>
                    <div class="stat-label">All subjects</div>
                </div>

                <div class="stat-card">
                    <div class="stat-header">
                        <h3>Junior High</h3>
                        <div class="stat-icon">
                            <i class="fas fa-users"></i>
                        </div>
                    </div>
                    <div class="stat-number">
                        <?php 
                        $jhs_count = 0;
                        $grade_counts->data_seek(0);
                        while($gc = $grade_counts->fetch_assoc()) {
                            if($gc['id'] <= 4) $jhs_count += $gc['subject_count'];
                        }
                        echo $jhs_count;
                        ?>
                    </div>
                    <div class="stat-label">Grades 7-10</div>
                </div>

                <div class="stat-card">
                    <div class="stat-header">
                        <h3>Senior High</h3>
                        <div class="stat-icon">
                            <i class="fas fa-user-graduate"></i>
                        </div>
                    </div>
                    <div class="stat-number">
                        <?php 
                        $shs_count = 0;
                        $grade_counts->data_seek(0);
                        while($gc = $grade_counts->fetch_assoc()) {
                            if($gc['id'] >= 5) $shs_count += $gc['subject_count'];
                        }
                        echo $shs_count;
                        ?>
                    </div>
                    <div class="stat-label">Grades 11-12</div>
                </div>

                <div class="stat-card">
                    <div class="stat-header">
                        <h3>With Attendance</h3>
                        <div class="stat-icon">
                            <i class="fas fa-calendar-check"></i>
                        </div>
                    </div>
                    <div class="stat-number">
                        <?php 
                        $with_attendance = $conn->query("SELECT COUNT(DISTINCT subject_id) as count FROM attendance")->fetch_assoc()['count'];
                        echo $with_attendance;
                        ?>
                    </div>
                    <div class="stat-label">Subjects with records</div>
                </div>
            </div>

            <!-- Grade Cards -->
            <div class="grade-cards">
                <?php 
                $grade_counts->data_seek(0);
                while($grade = $grade_counts->fetch_assoc()): 
                ?>
                    <div class="grade-card">
                        <div class="grade-icon">
                            <i class="fas fa-layer-group"></i>
                        </div>
                        <div class="grade-info">
                            <h4><?php echo $grade['grade_name']; ?></h4>
                            <p><?php echo $grade['subject_count']; ?> subjects</p>
                        </div>
                        <div class="grade-badge">
                            <?php echo $grade['subject_count']; ?>
                        </div>
                    </div>
                <?php endwhile; ?>
            </div>

            <!-- Actions Bar -->
            <div class="actions-bar">
                <form method="GET" action="" style="display: flex; gap: 15px; flex-wrap: wrap; width: 100%;">
                    <div class="filter-group">
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

                        <button type="submit" class="btn-add" style="padding: 10px 20px;">
                            <i class="fas fa-filter"></i> Apply Filter
                        </button>

                        <a href="subjects.php" class="btn-reset">
                            <i class="fas fa-redo-alt"></i> Reset
                        </a>
                    </div>

                    <div class="search-box">
                        <i class="fas fa-search"></i>
                        <input type="text" name="search" placeholder="Search subjects..." value="<?php echo htmlspecialchars($search_query); ?>">
                    </div>
                </form>

                <a href="add_subject.php" class="btn-add">
                    <i class="fas fa-plus-circle"></i> Add New Subject
                </a>
            </div>

            <!-- Subjects Table -->
            <div class="table-card">
                <div class="table-header">
                    <h3><i class="fas fa-book"></i> Subject List</h3>
                    <span class="grade-tag">Total: <?php echo $subjects ? $subjects->num_rows : 0; ?> subjects</span>
                </div>

                <div class="table-container">
                    <table class="subjects-table">
                        <thead>
                            <tr>
                                <th>Subject</th>
                                <th>Grade Level</th>
                                <th>Attendance Records</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if($subjects && $subjects->num_rows > 0): ?>
                                <?php while($subject = $subjects->fetch_assoc()): ?>
                                    <tr>
                                        <td>
                                            <div class="subject-info">
                                                <div class="subject-icon">
                                                    <i class="fas fa-book-open"></i>
                                                </div>
                                                <div class="subject-details">
                                                    <h4><?php echo htmlspecialchars($subject['subject_name']); ?></h4>
                                                    <span><i class="fas fa-hashtag"></i> ID: <?php echo $subject['id']; ?></span>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="grade-tag"><?php echo htmlspecialchars($subject['grade_name']); ?></span>
                                        </td>
                                        <td>
                                            <span class="attendance-badge">
                                                <i class="fas fa-calendar-check"></i>
                                                <?php echo $subject['attendance_count']; ?> records
                                            </span>
                                        </td>
                                        <td>
                                            <div class="action-btns">
                                                <a href="view_subject.php?id=<?php echo $subject['id']; ?>" class="btn-icon btn-view" title="View Details">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                                <a href="edit_subject.php?id=<?php echo $subject['id']; ?>" class="btn-icon btn-edit" title="Edit">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                                <?php if($subject['attendance_count'] == 0): ?>
                                                    <a href="?delete=<?php echo $subject['id']; ?>" class="btn-icon btn-delete" title="Delete" 
                                                       onclick="return confirm('Are you sure you want to delete this subject?')">
                                                        <i class="fas fa-trash"></i>
                                                    </a>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="4">
                                        <div class="no-data">
                                            <i class="fas fa-book"></i>
                                            <h3>No Subjects Found</h3>
                                            <p>Click the "Add New Subject" button to add your first subject.</p>
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

        // Auto-submit form when filter changes
        document.querySelector('.filter-select').addEventListener('change', function() {
            this.form.submit();
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