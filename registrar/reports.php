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

// Check for session messages
if(isset($_SESSION['success_message'])) {
    $success_message = $_SESSION['success_message'];
    unset($_SESSION['success_message']);
}

if(isset($_SESSION['error_message'])) {
    $error_message = $_SESSION['error_message'];
    unset($_SESSION['error_message']);
}

// Get filter parameters for reports
$report_type = isset($_GET['report_type']) ? $_GET['report_type'] : 'enrollment_summary';
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : date('Y-m-d', strtotime('-30 days'));
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : date('Y-m-d');
$grade_filter = isset($_GET['grade']) ? $_GET['grade'] : '';
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';

// Get grade levels for filter
$grade_levels = $conn->query("SELECT * FROM grade_levels ORDER BY id");

// Initialize report data
$report_data = null;
$report_title = '';
$report_headers = [];
$report_rows = [];

// Generate report based on type
if($report_type == 'enrollment_summary') {
    $report_title = 'Enrollment Summary Report';
    $report_headers = ['Grade Level', 'Total Enrollments', 'Pending', 'Enrolled', 'Rejected', 'Enrollment Rate'];
    
    $query = "
        SELECT 
            g.grade_name,
            COUNT(e.id) as total,
            SUM(CASE WHEN e.status = 'Pending' THEN 1 ELSE 0 END) as pending,
            SUM(CASE WHEN e.status = 'Enrolled' THEN 1 ELSE 0 END) as enrolled,
            SUM(CASE WHEN e.status = 'Rejected' THEN 1 ELSE 0 END) as rejected
        FROM grade_levels g
        LEFT JOIN enrollments e ON g.id = e.grade_id
            AND e.created_at BETWEEN '$date_from 00:00:00' AND '$date_to 23:59:59'
        GROUP BY g.id
        ORDER BY g.id
    ";
    
    $result = $conn->query($query);
    while($row = $result->fetch_assoc()) {
        $enrollment_rate = $row['total'] > 0 ? round(($row['enrolled'] / $row['total']) * 100, 2) : 0;
        $report_rows[] = [
            $row['grade_name'],
            $row['total'],
            $row['pending'],
            $row['enrolled'],
            $row['rejected'],
            $enrollment_rate . '%'
        ];
    }
}
elseif($report_type == 'student_list') {
    $report_title = 'Student List Report';
    $report_headers = ['Student Name', 'ID Number', 'Email', 'Grade Level', 'Strand', 'Status', 'School Year'];
    
    $query = "
        SELECT 
            u.fullname,
            u.id_number,
            u.email,
            g.grade_name,
            e.strand,
            e.status,
            e.school_year
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
    
    $query .= " ORDER BY u.fullname";
    
    $result = $conn->query($query);
    while($row = $result->fetch_assoc()) {
        $report_rows[] = [
            $row['fullname'],
            $row['id_number'] ?? 'N/A',
            $row['email'],
            $row['grade_name'] ?? 'Not Enrolled',
            $row['strand'] ?? '—',
            $row['status'] ?? 'No Record',
            $row['school_year'] ?? '—'
        ];
    }
}
elseif($report_type == 'enrollment_trends') {
    $report_title = 'Enrollment Trends Report';
    $report_headers = ['Month', 'Total Enrollments', 'Pending', 'Enrolled', 'Rejected', 'Monthly Change'];
    
    $query = "
        SELECT 
            DATE_FORMAT(created_at, '%Y-%m') as month,
            COUNT(*) as total,
            SUM(CASE WHEN status = 'Pending' THEN 1 ELSE 0 END) as pending,
            SUM(CASE WHEN status = 'Enrolled' THEN 1 ELSE 0 END) as enrolled,
            SUM(CASE WHEN status = 'Rejected' THEN 1 ELSE 0 END) as rejected
        FROM enrollments
        WHERE created_at BETWEEN '$date_from 00:00:00' AND '$date_to 23:59:59'
        GROUP BY DATE_FORMAT(created_at, '%Y-%m')
        ORDER BY month DESC
    ";
    
    $result = $conn->query($query);
    $prev_total = 0;
    while($row = $result->fetch_assoc()) {
        $change = $prev_total > 0 ? round((($row['total'] - $prev_total) / $prev_total) * 100, 2) : 0;
        $change_text = $change > 0 ? "+$change%" : ($change < 0 ? "$change%" : "0%");
        $report_rows[] = [
            date('F Y', strtotime($row['month'] . '-01')),
            $row['total'],
            $row['pending'],
            $row['enrolled'],
            $row['rejected'],
            $change_text
        ];
        $prev_total = $row['total'];
    }
}
elseif($report_type == 'strand_distribution') {
    $report_title = 'Strand Distribution Report (Senior High)';
    $report_headers = ['Strand', 'Number of Students', 'Percentage'];
    
    $query = "
        SELECT 
            COALESCE(e.strand, 'No Strand') as strand,
            COUNT(DISTINCT e.student_id) as student_count
        FROM enrollments e
        WHERE e.grade_id IN (5, 6) AND e.status = 'Enrolled'
            AND e.created_at BETWEEN '$date_from 00:00:00' AND '$date_to 23:59:59'
        GROUP BY e.strand
        ORDER BY student_count DESC
    ";
    
    $result = $conn->query($query);
    $total = 0;
    $rows = [];
    while($row = $result->fetch_assoc()) {
        $total += $row['student_count'];
        $rows[] = $row;
    }
    
    foreach($rows as $row) {
        $percentage = $total > 0 ? round(($row['student_count'] / $total) * 100, 2) : 0;
        $report_rows[] = [
            $row['strand'],
            $row['student_count'],
            $percentage . '%'
        ];
    }
}

// Get summary statistics for dashboard
$total_students = $conn->query("SELECT COUNT(*) as count FROM users WHERE role='Student'")->fetch_assoc()['count'];
$total_enrollments = $conn->query("SELECT COUNT(*) as count FROM enrollments")->fetch_assoc()['count'];
$enrolled_count = $conn->query("SELECT COUNT(*) as count FROM enrollments WHERE status='Enrolled'")->fetch_assoc()['count'];
$this_month_enrollments = $conn->query("SELECT COUNT(*) as count FROM enrollments WHERE MONTH(created_at) = MONTH(CURRENT_DATE()) AND YEAR(created_at) = YEAR(CURRENT_DATE())")->fetch_assoc()['count'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports - Registrar Dashboard</title>
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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

        /* Report Controls */
        .report-controls {
            background: white;
            border-radius: 20px;
            padding: 25px;
            margin-bottom: 30px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
        }

        .report-controls h3 {
            color: var(--text-primary);
            font-size: 18px;
            font-weight: 600;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .report-controls h3 i {
            color: #0B4F2E;
        }

        .controls-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            align-items: flex-end;
        }

        .control-group {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .control-group label {
            color: var(--text-secondary);
            font-size: 13px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .control-group select,
        .control-group input {
            padding: 12px 15px;
            border: 2px solid var(--border-color);
            border-radius: 10px;
            font-size: 14px;
            transition: all 0.3s;
            background: #f8f9fa;
        }

        .control-group select:focus,
        .control-group input:focus {
            border-color: #0B4F2E;
            outline: none;
            background: white;
        }

        .btn-generate {
            background: #0B4F2E;
            color: white;
            border: none;
            padding: 12px 25px;
            border-radius: 10px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            height: 45px;
        }

        .btn-generate:hover {
            background: #1a7a42;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(11, 79, 46, 0.3);
        }

        .btn-reset {
            background: #f8f9fa;
            color: var(--text-secondary);
            border: 2px solid var(--border-color);
            padding: 12px 25px;
            border-radius: 10px;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            height: 45px;
        }

        .btn-reset:hover {
            border-color: #0B4F2E;
            color: #0B4F2E;
        }

        /* Report Card */
        .report-card {
            background: white;
            border-radius: 20px;
            padding: 25px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            overflow-x: auto;
        }

        .report-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 1px solid var(--border-color);
            flex-wrap: wrap;
            gap: 15px;
        }

        .report-header h2 {
            color: var(--text-primary);
            font-size: 20px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .report-header h2 i {
            color: #0B4F2E;
        }

        .report-actions {
            display: flex;
            gap: 10px;
        }

        .btn-export {
            background: white;
            color: var(--text-primary);
            border: 2px solid var(--border-color);
            padding: 10px 20px;
            border-radius: 10px;
            font-size: 13px;
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

        .btn-print {
            background: white;
            color: var(--text-primary);
            border: 2px solid var(--border-color);
            padding: 10px 20px;
            border-radius: 10px;
            font-size: 13px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-print:hover {
            border-color: #0B4F2E;
            color: #0B4F2E;
        }

        .report-table {
            width: 100%;
            border-collapse: collapse;
        }

        .report-table th {
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

        .report-table td {
            padding: 12px 15px;
            border-bottom: 1px solid var(--border-color);
            color: var(--text-primary);
        }

        .report-table tbody tr:hover {
            background: var(--hover-color);
        }

        .report-table tfoot {
            background: #f8f9fa;
            font-weight: 600;
        }

        .report-table tfoot td {
            padding: 15px;
            border-top: 2px solid var(--border-color);
        }

        .date-range {
            color: var(--text-secondary);
            font-size: 14px;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .date-range i {
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
            
            .controls-grid {
                grid-template-columns: 1fr;
            }
            
            .report-header {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .report-actions {
                width: 100%;
                justify-content: flex-start;
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
                    <li><a href="enrollments.php"><i class="fas fa-file-signature"></i> <span>Enrollments</span></a></li>
                    <li><a href="students.php"><i class="fas fa-user-graduate"></i> <span>Students</span></a></li>
                    <li><a href="reports.php" class="active"><i class="fas fa-chart-bar"></i> <span>Reports</span></a></li>
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
                <h1>Reports</h1>
                <p>Generate and export enrollment reports</p>
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

            <!-- Statistics Cards -->
            <div class="stats-container">
                <div class="stat-card">
                    <div class="stat-header">
                        <h3>Total Students</h3>
                        <div class="stat-icon">
                            <i class="fas fa-user-graduate"></i>
                        </div>
                    </div>
                    <div class="stat-number"><?php echo $total_students; ?></div>
                    <div class="stat-label">Registered</div>
                </div>

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
                        <h3>Currently Enrolled</h3>
                        <div class="stat-icon">
                            <i class="fas fa-check-circle"></i>
                        </div>
                    </div>
                    <div class="stat-number"><?php echo $enrolled_count; ?></div>
                    <div class="stat-label">Active students</div>
                </div>

                <div class="stat-card">
                    <div class="stat-header">
                        <h3>This Month</h3>
                        <div class="stat-icon">
                            <i class="fas fa-calendar"></i>
                        </div>
                    </div>
                    <div class="stat-number"><?php echo $this_month_enrollments; ?></div>
                    <div class="stat-label">New enrollments</div>
                </div>
            </div>

            <!-- Report Controls -->
            <div class="report-controls">
                <h3><i class="fas fa-sliders-h"></i> Report Controls</h3>
                <form method="GET" class="controls-grid">
                    <div class="control-group">
                        <label>Report Type</label>
                        <select name="report_type">
                            <option value="enrollment_summary" <?php echo $report_type == 'enrollment_summary' ? 'selected' : ''; ?>>Enrollment Summary</option>
                            <option value="student_list" <?php echo $report_type == 'student_list' ? 'selected' : ''; ?>>Student List</option>
                            <option value="enrollment_trends" <?php echo $report_type == 'enrollment_trends' ? 'selected' : ''; ?>>Enrollment Trends</option>
                            <option value="strand_distribution" <?php echo $report_type == 'strand_distribution' ? 'selected' : ''; ?>>Strand Distribution</option>
                        </select>
                    </div>

                    <div class="control-group">
                        <label>Date From</label>
                        <input type="date" name="date_from" value="<?php echo $date_from; ?>">
                    </div>

                    <div class="control-group">
                        <label>Date To</label>
                        <input type="date" name="date_to" value="<?php echo $date_to; ?>">
                    </div>

                    <div class="control-group">
                        <label>Grade Level</label>
                        <select name="grade">
                            <option value="">All Grades</option>
                            <?php 
                            if($grade_levels) {
                                $grade_levels->data_seek(0);
                                while($grade = $grade_levels->fetch_assoc()): 
                            ?>
                                <option value="<?php echo $grade['id']; ?>" <?php echo $grade_filter == $grade['id'] ? 'selected' : ''; ?>>
                                    <?php echo $grade['grade_name']; ?>
                                </option>
                            <?php 
                                endwhile;
                            } 
                            ?>
                        </select>
                    </div>

                    <div class="control-group">
                        <label>Status</label>
                        <select name="status">
                            <option value="">All Status</option>
                            <option value="Pending" <?php echo $status_filter == 'Pending' ? 'selected' : ''; ?>>Pending</option>
                            <option value="Enrolled" <?php echo $status_filter == 'Enrolled' ? 'selected' : ''; ?>>Enrolled</option>
                            <option value="Rejected" <?php echo $status_filter == 'Rejected' ? 'selected' : ''; ?>>Rejected</option>
                        </select>
                    </div>

                    <div class="control-group" style="display: flex; gap: 10px;">
                        <button type="submit" class="btn-generate">
                            <i class="fas fa-sync-alt"></i> Generate
                        </button>
                        <a href="reports.php" class="btn-reset">
                            <i class="fas fa-redo-alt"></i> Reset
                        </a>
                    </div>
                </form>
            </div>

            <!-- Report Display -->
            <?php if(!empty($report_rows)): ?>
                <div class="report-card">
                    <div class="report-header">
                        <h2><i class="fas fa-chart-bar"></i> <?php echo $report_title; ?></h2>
                        <div class="report-actions">
                            <button class="btn-export" onclick="exportToExcel()">
                                <i class="fas fa-file-excel"></i> Export
                            </button>
                            <button class="btn-print" onclick="printReport()">
                                <i class="fas fa-print"></i> Print
                            </button>
                        </div>
                    </div>

                    <div class="date-range">
                        <i class="fas fa-calendar-alt"></i>
                        Report Period: <?php echo date('F d, Y', strtotime($date_from)); ?> - <?php echo date('F d, Y', strtotime($date_to)); ?>
                    </div>

                    <table class="report-table" id="reportTable">
                        <thead>
                            <tr>
                                <?php foreach($report_headers as $header): ?>
                                    <th><?php echo $header; ?></th>
                                <?php endforeach; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($report_rows as $row): ?>
                                <tr>
                                    <?php foreach($row as $cell): ?>
                                        <td><?php echo $cell; ?></td>
                                    <?php endforeach; ?>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <?php if(count($report_rows) > 0): ?>
                            <tfoot>
                                <tr>
                                    <td colspan="<?php echo count($report_headers); ?>" style="text-align: right;">
                                        Total Records: <?php echo count($report_rows); ?>
                                    </td>
                                </tr>
                            </tfoot>
                        <?php endif; ?>
                    </table>
                </div>
            <?php else: ?>
                <div class="report-card">
                    <div class="no-data">
                        <i class="fas fa-chart-bar"></i>
                        <h3>No Data Available</h3>
                        <p>No records found for the selected criteria. Try adjusting your filters.</p>
                    </div>
                </div>
            <?php endif; ?>
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

        // Export to Excel function
        function exportToExcel() {
            const table = document.getElementById('reportTable');
            const rows = Array.from(table.querySelectorAll('tr'));
            
            let csv = [];
            rows.forEach(row => {
                const cols = Array.from(row.querySelectorAll('th, td'));
                const rowData = cols.map(col => {
                    // Get text content, remove extra spaces and commas
                    return '"' + col.innerText.replace(/"/g, '""').replace(/\s+/g, ' ').trim() + '"';
                }).join(',');
                csv.push(rowData);
            });
            
            const blob = new Blob([csv.join('\n')], { type: 'text/csv;charset=utf-8;' });
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = 'report_<?php echo $report_type; ?>_' + new Date().toISOString().slice(0,10) + '.csv';
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            window.URL.revokeObjectURL(url);
        }
        
        // Print function
        function printReport() {
            const table = document.getElementById('reportTable').cloneNode(true);
            const reportTitle = '<?php echo $report_title; ?>';
            const dateRange = '<?php echo date('F d, Y', strtotime($date_from)); ?> - <?php echo date('F d, Y', strtotime($date_to)); ?>';
            
            const newWindow = window.open('', '_blank');
            newWindow.document.write(`
                <html>
                    <head>
                        <title>${reportTitle}</title>
                        <style>
                            body { font-family: 'Inter', Arial, sans-serif; padding: 30px; }
                            h2 { color: #0B4F2E; margin-bottom: 5px; }
                            h3 { color: #666; font-weight: 400; margin-bottom: 5px; }
                            .date { color: #999; font-size: 12px; margin-bottom: 20px; }
                            table { border-collapse: collapse; width: 100%; margin-top: 20px; }
                            th { background: #f0f0f0; padding: 12px; text-align: left; font-size: 13px; }
                            td { padding: 10px; border-bottom: 1px solid #ddd; font-size: 13px; }
                            .header { text-align: center; margin-bottom: 30px; }
                            .footer { margin-top: 30px; text-align: center; color: #666; font-size: 12px; }
                        </style>
                    </head>
                    <body>
                        <div class="header">
                            <h2>Placido L. Señor Senior High School</h2>
                            <h3>${reportTitle}</h3>
                            <div class="date">Period: ${dateRange}</div>
                            <div class="date">Generated on: ${new Date().toLocaleString()}</div>
                        </div>
                        ${table.outerHTML}
                        <div class="footer">
                            <p>This is a system-generated report.</p>
                        </div>
                    </body>
                </html>
            `);
            newWindow.document.close();
            newWindow.print();
        }
    </script>
</body>
</html>