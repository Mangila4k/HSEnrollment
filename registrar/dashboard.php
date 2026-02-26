<?php
session_start();
include("../config/database.php");

// Check if user is registrar
if(!isset($_SESSION['user']) || $_SESSION['user']['role'] != 'Registrar'){
    header("Location: ../auth/login.php");
    exit();
}

$registrar_id = $_SESSION['user']['id'];
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

// Get enrollment statistics
$total_enrollments = $conn->query("SELECT COUNT(*) as count FROM enrollments")->fetch_assoc()['count'];
$pending_count = $conn->query("SELECT COUNT(*) as count FROM enrollments WHERE status='Pending'")->fetch_assoc()['count'];
$enrolled_count = $conn->query("SELECT COUNT(*) as count FROM enrollments WHERE status='Enrolled'")->fetch_assoc()['count'];
$rejected_count = $conn->query("SELECT COUNT(*) as count FROM enrollments WHERE status='Rejected'")->fetch_assoc()['count'];

// Get student statistics
$total_students = $conn->query("SELECT COUNT(*) as count FROM users WHERE role='Student'")->fetch_assoc()['count'];

// Get recent enrollments
$recent_enrollments = $conn->query("
    SELECT e.*, u.fullname, u.email, u.id_number, g.grade_name 
    FROM enrollments e 
    LEFT JOIN users u ON e.student_id = u.id 
    LEFT JOIN grade_levels g ON e.grade_id = g.id 
    ORDER BY e.id DESC 
    LIMIT 5
");

// Get enrollment trends by month (last 6 months)
$trends_query = "
    SELECT 
        DATE_FORMAT(created_at, '%Y-%m') as month,
        COUNT(*) as total,
        SUM(CASE WHEN status = 'Pending' THEN 1 ELSE 0 END) as pending,
        SUM(CASE WHEN status = 'Enrolled' THEN 1 ELSE 0 END) as enrolled,
        SUM(CASE WHEN status = 'Rejected' THEN 1 ELSE 0 END) as rejected
    FROM enrollments 
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
    GROUP BY DATE_FORMAT(created_at, '%Y-%m')
    ORDER BY month DESC
";
$trends = $conn->query($trends_query);

// Get grade level distribution
$grade_distribution = $conn->query("
    SELECT g.grade_name, COUNT(e.id) as count
    FROM grade_levels g
    LEFT JOIN enrollments e ON g.id = e.grade_id AND e.status = 'Enrolled'
    GROUP BY g.id
    ORDER BY g.id
");

// Get recent activities - FIXED: Added table aliases for created_at
$recent_activities = $conn->query("
    (SELECT 'enrollment' as type, e.created_at, CONCAT('New enrollment from ', u.fullname) as description
     FROM enrollments e
     JOIN users u ON e.student_id = u.id
     ORDER BY e.created_at DESC LIMIT 3)
    UNION ALL
    (SELECT 'status_change' as type, e.created_at, CONCAT('Enrollment ', e.status, ' for ', u.fullname) as description
     FROM enrollments e
     JOIN users u ON e.student_id = u.id
     WHERE e.status IN ('Enrolled', 'Rejected')
     ORDER BY e.created_at DESC LIMIT 3)
    ORDER BY created_at DESC LIMIT 5
");

// Calculate approval rate
$approval_rate = $total_enrollments > 0 ? round(($enrolled_count / $total_enrollments) * 100, 2) : 0;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registrar Dashboard - Placido L. Señor Senior High School</title>
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

        .stat-trend {
            display: flex;
            align-items: center;
            gap: 5px;
            font-size: 12px;
            margin-top: 10px;
        }

        .trend-up {
            color: #28a745;
        }

        .trend-down {
            color: #dc3545;
        }

        /* Stats Row 2 */
        .stats-row-2 {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 25px;
            margin-bottom: 30px;
        }

        /* Chart Cards */
        .chart-card {
            background: white;
            border-radius: 20px;
            padding: 25px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
        }

        .chart-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 1px solid var(--border-color);
        }

        .chart-header h3 {
            color: var(--text-primary);
            font-size: 18px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .chart-header h3 i {
            color: #0B4F2E;
        }

        .chart-container {
            height: 300px;
            position: relative;
        }

        /* Dashboard Grid */
        .dashboard-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 25px;
            margin-bottom: 30px;
        }

        /* Info Card */
        .info-card {
            background: white;
            border-radius: 20px;
            padding: 25px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
        }

        .info-card h3 {
            color: var(--text-primary);
            font-size: 18px;
            font-weight: 600;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
            padding-bottom: 15px;
            border-bottom: 1px solid var(--border-color);
        }

        .info-card h3 i {
            color: #0B4F2E;
        }

        /* Enrollment List */
        .enrollment-list {
            list-style: none;
        }

        .enrollment-item {
            display: flex;
            align-items: center;
            gap: 15px;
            padding: 15px 0;
            border-bottom: 1px solid var(--border-color);
        }

        .enrollment-item:last-child {
            border-bottom: none;
        }

        .enrollment-avatar {
            width: 45px;
            height: 45px;
            background: linear-gradient(135deg, #0B4F2E, #1a7a42);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
            font-size: 18px;
        }

        .enrollment-info {
            flex: 1;
        }

        .enrollment-info h4 {
            font-size: 16px;
            margin-bottom: 3px;
            color: var(--text-primary);
        }

        .enrollment-info p {
            font-size: 13px;
            color: var(--text-secondary);
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .badge {
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
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

        .view-all-link {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            color: #0B4F2E;
            text-decoration: none;
            font-size: 14px;
            font-weight: 500;
            margin-top: 15px;
        }

        .view-all-link:hover {
            text-decoration: underline;
        }

        /* Activity List */
        .activity-list {
            list-style: none;
        }

        .activity-item {
            display: flex;
            align-items: flex-start;
            gap: 15px;
            padding: 12px 0;
            border-bottom: 1px solid var(--border-color);
        }

        .activity-item:last-child {
            border-bottom: none;
        }

        .activity-icon {
            width: 35px;
            height: 35px;
            background: rgba(11, 79, 46, 0.1);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #0B4F2E;
        }

        .activity-content {
            flex: 1;
        }

        .activity-title {
            color: var(--text-primary);
            font-size: 14px;
            margin-bottom: 5px;
        }

        .activity-time {
            color: var(--text-secondary);
            font-size: 12px;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        /* Grade Distribution */
        .grade-item {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 10px 0;
            border-bottom: 1px solid var(--border-color);
        }

        .grade-item:last-child {
            border-bottom: none;
        }

        .grade-name {
            flex: 1;
            font-weight: 500;
        }

        .grade-count {
            font-weight: 600;
            color: #0B4F2E;
        }

        .grade-bar {
            width: 100px;
            height: 8px;
            background: #e9ecef;
            border-radius: 4px;
            overflow: hidden;
        }

        .grade-bar-fill {
            height: 100%;
            background: linear-gradient(135deg, #0B4F2E, #1a7a42);
            border-radius: 4px;
        }

        /* Quick Actions */
        .quick-actions {
            margin-top: 30px;
        }

        .action-buttons {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
        }

        .action-btn {
            background: white;
            border: 2px solid var(--border-color);
            border-radius: 12px;
            padding: 20px;
            text-decoration: none;
            color: var(--text-primary);
            transition: all 0.3s;
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .action-btn:hover {
            border-color: #0B4F2E;
            transform: translateY(-3px);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
        }

        .action-icon {
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

        .action-content h4 {
            font-size: 16px;
            margin-bottom: 5px;
        }

        .action-content p {
            color: var(--text-secondary);
            font-size: 13px;
        }

        /* No Data */
        .no-data {
            text-align: center;
            padding: 30px;
            color: var(--text-secondary);
        }

        .no-data i {
            font-size: 40px;
            opacity: 0.3;
            margin-bottom: 10px;
        }

        /* Responsive */
        @media (max-width: 1200px) {
            .stats-container {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .stats-row-2 {
                grid-template-columns: 1fr;
            }
            
            .dashboard-grid {
                grid-template-columns: 1fr;
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
            
            .action-buttons {
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
                <span>PNSH</span>
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
                    <li><a href="dashboard.php" class="active"><i class="fas fa-tachometer-alt"></i> <span>Dashboard</span></a></li>
                    <li><a href="enrollments.php"><i class="fas fa-file-signature"></i> <span>Enrollments</span></a></li>
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
                <h1>Registrar Dashboard</h1>
                <p>Manage enrollments and student records</p>
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
                <div class="stat-card" onclick="window.location.href='enrollments.php'">
                    <div class="stat-header">
                        <h3>Total Enrollments</h3>
                        <div class="stat-icon">
                            <i class="fas fa-file-signature"></i>
                        </div>
                    </div>
                    <div class="stat-number"><?php echo $total_enrollments; ?></div>
                    <div class="stat-label">All time</div>
                    <div class="stat-trend trend-up">
                        <i class="fas fa-arrow-up"></i> +12% from last month
                    </div>
                </div>

                <div class="stat-card" onclick="window.location.href='enrollments.php?status=Pending'">
                    <div class="stat-header">
                        <h3>Pending</h3>
                        <div class="stat-icon">
                            <i class="fas fa-clock"></i>
                        </div>
                    </div>
                    <div class="stat-number"><?php echo $pending_count; ?></div>
                    <div class="stat-label">Awaiting review</div>
                    <?php if($pending_count > 0): ?>
                        <div class="stat-trend trend-up">
                            <i class="fas fa-exclamation-circle"></i> Needs attention
                        </div>
                    <?php endif; ?>
                </div>

                <div class="stat-card" onclick="window.location.href='enrollments.php?status=Enrolled'">
                    <div class="stat-header">
                        <h3>Enrolled</h3>
                        <div class="stat-icon">
                            <i class="fas fa-check-circle"></i>
                        </div>
                    </div>
                    <div class="stat-number"><?php echo $enrolled_count; ?></div>
                    <div class="stat-label">Approved</div>
                    <div class="stat-trend trend-up">
                        <i class="fas fa-arrow-up"></i> Approval rate: <?php echo $approval_rate; ?>%
                    </div>
                </div>

                <div class="stat-card" onclick="window.location.href='enrollments.php?status=Rejected'">
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

            <!-- Second Row Stats -->
            <div class="stats-row-2">
                <!-- Enrollment Trends Chart -->
                <div class="chart-card">
                    <div class="chart-header">
                        <h3><i class="fas fa-chart-line"></i> Enrollment Trends</h3>
                        <span class="badge badge-pending">Last 6 months</span>
                    </div>
                    <div class="chart-container">
                        <canvas id="trendsChart"></canvas>
                    </div>
                </div>

                <!-- Grade Distribution -->
                <div class="chart-card">
                    <div class="chart-header">
                        <h3><i class="fas fa-pie-chart"></i> Enrollment by Grade Level</h3>
                        <span class="badge badge-enrolled">Current enrolled</span>
                    </div>
                    <div class="chart-container">
                        <canvas id="gradeChart"></canvas>
                    </div>
                </div>
            </div>

            <!-- Dashboard Grid -->
            <div class="dashboard-grid">
                <!-- Recent Enrollments -->
                <div class="info-card">
                    <h3><i class="fas fa-history"></i> Recent Enrollments</h3>
                    <?php if($recent_enrollments && $recent_enrollments->num_rows > 0): ?>
                        <div class="enrollment-list">
                            <?php while($enrollment = $recent_enrollments->fetch_assoc()): ?>
                                <div class="enrollment-item">
                                    <div class="enrollment-avatar">
                                        <?php echo strtoupper(substr($enrollment['fullname'], 0, 1)); ?>
                                    </div>
                                    <div class="enrollment-info">
                                        <h4><?php echo htmlspecialchars($enrollment['fullname']); ?></h4>
                                        <p>
                                            <span><?php echo htmlspecialchars($enrollment['grade_name']); ?></span>
                                            <?php if($enrollment['strand']): ?>
                                                <span>• <?php echo htmlspecialchars($enrollment['strand']); ?></span>
                                            <?php endif; ?>
                                            <span class="badge badge-<?php echo strtolower($enrollment['status']); ?>">
                                                <?php echo $enrollment['status']; ?>
                                            </span>
                                        </p>
                                        <span class="activity-time">
                                            <i class="far fa-clock"></i>
                                            <?php echo date('M d, Y', strtotime($enrollment['created_at'])); ?>
                                        </span>
                                    </div>
                                </div>
                            <?php endwhile; ?>
                        </div>
                        <a href="enrollments.php" class="view-all-link">
                            View All Enrollments <i class="fas fa-arrow-right"></i>
                        </a>
                    <?php else: ?>
                        <div class="no-data">
                            <i class="fas fa-file-signature"></i>
                            <p>No recent enrollments</p>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Recent Activities -->
                <div class="info-card">
                    <h3><i class="fas fa-bell"></i> Recent Activities</h3>
                    <?php if($recent_activities && $recent_activities->num_rows > 0): ?>
                        <div class="activity-list">
                            <?php while($activity = $recent_activities->fetch_assoc()): ?>
                                <div class="activity-item">
                                    <div class="activity-icon">
                                        <i class="fas fa-<?php echo $activity['type'] == 'enrollment' ? 'user-plus' : 'sync-alt'; ?>"></i>
                                    </div>
                                    <div class="activity-content">
                                        <div class="activity-title">
                                            <?php echo htmlspecialchars($activity['description']); ?>
                                        </div>
                                        <div class="activity-time">
                                            <i class="far fa-clock"></i>
                                            <?php echo date('M d, Y h:i A', strtotime($activity['created_at'])); ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endwhile; ?>
                        </div>
                    <?php else: ?>
                        <div class="no-data">
                            <i class="fas fa-bell-slash"></i>
                            <p>No recent activities</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="quick-actions">
                <h3 style="color: var(--text-primary); font-size: 18px; font-weight: 600; margin-bottom: 20px; display: flex; align-items: center; gap: 10px;">
                    <i class="fas fa-bolt" style="color: #0B4F2E;"></i> Quick Actions
                </h3>
                <div class="action-buttons">
                    <a href="enrollments.php" class="action-btn">
                        <div class="action-icon">
                            <i class="fas fa-file-signature"></i>
                        </div>
                        <div class="action-content">
                            <h4>Manage Enrollments</h4>
                            <p>View and process enrollment applications</p>
                        </div>
                    </a>
                    
                    <a href="students.php" class="action-btn">
                        <div class="action-icon">
                            <i class="fas fa-user-graduate"></i>
                        </div>
                        <div class="action-content">
                            <h4>Student Records</h4>
                            <p>Manage student information</p>
                        </div>
                    </a>
                    
                    <a href="reports.php" class="action-btn">
                        <div class="action-icon">
                            <i class="fas fa-chart-bar"></i>
                        </div>
                        <div class="action-content">
                            <h4>Generate Reports</h4>
                            <p>Create enrollment reports</p>
                        </div>
                    </a>
                    
                    <a href="profile.php" class="action-btn">
                        <div class="action-icon">
                            <i class="fas fa-user-cog"></i>
                        </div>
                        <div class="action-content">
                            <h4>Account Settings</h4>
                            <p>Update your profile</p>
                        </div>
                    </a>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Enrollment Trends Chart
        const trendsCtx = document.getElementById('trendsChart').getContext('2d');
        
        <?php
        $months = [];
        $pending_data = [];
        $enrolled_data = [];
        $rejected_data = [];
        
        if($trends && $trends->num_rows > 0) {
            $trends->data_seek(0);
            while($row = $trends->fetch_assoc()) {
                $months[] = date('M Y', strtotime($row['month'] . '-01'));
                $pending_data[] = $row['pending'];
                $enrolled_data[] = $row['enrolled'];
                $rejected_data[] = $row['rejected'];
            }
        } else {
            // Default data if no trends
            $months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun'];
            $pending_data = [0,0,0,0,0,0];
            $enrolled_data = [0,0,0,0,0,0];
            $rejected_data = [0,0,0,0,0,0];
        }
        ?>
        
        new Chart(trendsCtx, {
            type: 'line',
            data: {
                labels: <?php echo json_encode(array_reverse($months)); ?>,
                datasets: [
                    {
                        label: 'Pending',
                        data: <?php echo json_encode(array_reverse($pending_data)); ?>,
                        borderColor: '#ffc107',
                        backgroundColor: 'rgba(255, 193, 7, 0.1)',
                        tension: 0.4,
                        fill: true
                    },
                    {
                        label: 'Enrolled',
                        data: <?php echo json_encode(array_reverse($enrolled_data)); ?>,
                        borderColor: '#28a745',
                        backgroundColor: 'rgba(40, 167, 69, 0.1)',
                        tension: 0.4,
                        fill: true
                    },
                    {
                        label: 'Rejected',
                        data: <?php echo json_encode(array_reverse($rejected_data)); ?>,
                        borderColor: '#dc3545',
                        backgroundColor: 'rgba(220, 53, 69, 0.1)',
                        tension: 0.4,
                        fill: true
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            usePointStyle: true,
                            boxWidth: 8
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: {
                            display: true,
                            color: 'rgba(0,0,0,0.05)'
                        }
                    },
                    x: {
                        grid: {
                            display: false
                        }
                    }
                }
            }
        });

        // Grade Distribution Chart
        const gradeCtx = document.getElementById('gradeChart').getContext('2d');
        
        <?php
        $grade_labels = [];
        $grade_counts = [];
        
        if($grade_distribution && $grade_distribution->num_rows > 0) {
            $grade_distribution->data_seek(0);
            while($row = $grade_distribution->fetch_assoc()) {
                $grade_labels[] = $row['grade_name'];
                $grade_counts[] = (int)$row['count'];
            }
        } else {
            // Default data if no distribution
            $grade_labels = ['Grade 7', 'Grade 8', 'Grade 9', 'Grade 10', 'Grade 11', 'Grade 12'];
            $grade_counts = [0,0,0,0,0,0];
        }
        ?>
        
        new Chart(gradeCtx, {
            type: 'doughnut',
            data: {
                labels: <?php echo json_encode($grade_labels); ?>,
                datasets: [{
                    data: <?php echo json_encode($grade_counts); ?>,
                    backgroundColor: [
                        '#0B4F2E',
                        '#1a7a42',
                        '#2a9d5a',
                        '#3abf6e',
                        '#4ad082',
                        '#5ae196'
                    ],
                    borderWidth: 0
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            usePointStyle: true,
                            boxWidth: 8
                        }
                    }
                },
                cutout: '65%'
            }
        });

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
    <li><a href="sections.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'sections.php' ? 'active' : ''; ?>">
    <i class="fas fa-layer-group"></i><span>Sections</span>
</a></li>
<li><a href="sections.php"><i class="fas fa-layer-group"></i> <span>Sections</span></a></li>
</body>
</html>