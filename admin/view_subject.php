<?php
session_start();
include("../config/database.php");

// Check if user is admin
if(!isset($_SESSION['user']) || $_SESSION['user']['role'] != 'Admin'){
    header("Location: ../auth/login.php");
    exit();
}

$admin_name = $_SESSION['user']['fullname'];

// Check if ID is provided
if(!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: subjects.php");
    exit();
}

$subject_id = $_GET['id'];

// Get subject details
$query = "
    SELECT s.*, g.grade_name 
    FROM subjects s
    LEFT JOIN grade_levels g ON s.grade_id = g.id
    WHERE s.id = ?
";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $subject_id);
$stmt->execute();
$result = $stmt->get_result();

if($result->num_rows === 0) {
    header("Location: subjects.php");
    exit();
}

$subject = $result->fetch_assoc();
$stmt->close();

// Get attendance records for this subject
$attendance_query = "
    SELECT a.*, u.fullname as student_name, u.id_number, u.email
    FROM attendance a
    JOIN users u ON a.student_id = u.id
    WHERE a.subject_id = ?
    ORDER BY a.date DESC
    LIMIT 20
";
$stmt = $conn->prepare($attendance_query);
$stmt->bind_param("i", $subject_id);
$stmt->execute();
$attendance = $stmt->get_result();
$total_attendance = $attendance->num_rows;
$stmt->close();

// Get attendance statistics
$stats_query = "
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN status = 'Present' THEN 1 ELSE 0 END) as present,
        SUM(CASE WHEN status = 'Absent' THEN 1 ELSE 0 END) as absent,
        SUM(CASE WHEN status = 'Late' THEN 1 ELSE 0 END) as late
    FROM attendance
    WHERE subject_id = ?
";
$stmt = $conn->prepare($stats_query);
$stmt->bind_param("i", $subject_id);
$stmt->execute();
$stats = $stmt->get_result()->fetch_assoc();
$stmt->close();

$attendance_rate = $stats['total'] > 0 
    ? round(($stats['present'] / $stats['total']) * 100, 2) 
    : 0;

// Get students enrolled in this grade level (potential students for this subject)
$students_query = "
    SELECT u.*, e.status, e.strand
    FROM users u
    JOIN enrollments e ON u.id = e.student_id
    WHERE e.grade_id = ? AND e.status = 'Enrolled'
    ORDER BY u.fullname
    LIMIT 10
";
$stmt = $conn->prepare($students_query);
$stmt->bind_param("i", $subject['grade_id']);
$stmt->execute();
$students = $stmt->get_result();
$total_students = $students->num_rows;
$stmt->close();

// Get monthly attendance trend
$monthly_query = "
    SELECT 
        DATE_FORMAT(date, '%Y-%m') as month,
        COUNT(*) as total,
        SUM(CASE WHEN status = 'Present' THEN 1 ELSE 0 END) as present
    FROM attendance
    WHERE subject_id = ?
    GROUP BY DATE_FORMAT(date, '%Y-%m')
    ORDER BY month DESC
    LIMIT 6
";
$stmt = $conn->prepare($monthly_query);
$stmt->bind_param("i", $subject_id);
$stmt->execute();
$monthly_stats = $stmt->get_result();
$stmt->close();

// Get top performing students (based on attendance)
$top_students_query = "
    SELECT 
        u.fullname,
        u.id_number,
        COUNT(*) as total_attendance,
        SUM(CASE WHEN a.status = 'Present' THEN 1 ELSE 0 END) as present_count,
        ROUND((SUM(CASE WHEN a.status = 'Present' THEN 1 ELSE 0 END) / COUNT(*)) * 100, 2) as attendance_rate
    FROM attendance a
    JOIN users u ON a.student_id = u.id
    WHERE a.subject_id = ?
    GROUP BY a.student_id
    HAVING COUNT(*) >= 3
    ORDER BY attendance_rate DESC, present_count DESC
    LIMIT 5
";
$stmt = $conn->prepare($top_students_query);
$stmt->bind_param("i", $subject_id);
$stmt->execute();
$top_students = $stmt->get_result();
$stmt->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Subject - Admin Dashboard</title>
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
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .header-left h1 {
            font-size: 28px;
            color: var(--text-primary);
            margin-bottom: 10px;
            font-weight: 700;
        }

        .header-left p {
            color: var(--text-secondary);
            font-size: 16px;
        }

        .back-btn {
            background: white;
            color: var(--text-primary);
            padding: 12px 25px;
            border-radius: 12px;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 10px;
            transition: all 0.3s ease;
            font-weight: 500;
            border: 1px solid var(--border-color);
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
        }

        .back-btn:hover {
            border-color: #0B4F2E;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(11, 79, 46, 0.1);
        }

        .back-btn i {
            color: #0B4F2E;
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

        /* Subject Header Card */
        .subject-header {
            background: linear-gradient(135deg, #0B4F2E, #1a7a42);
            border-radius: 20px;
            padding: 30px;
            color: white;
            margin-bottom: 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 20px;
        }

        .subject-title h2 {
            font-size: 32px;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .subject-title h2 i {
            color: #FFD700;
        }

        .subject-meta {
            display: flex;
            gap: 20px;
            flex-wrap: wrap;
        }

        .subject-meta-item {
            display: flex;
            align-items: center;
            gap: 8px;
            background: rgba(255, 255, 255, 0.1);
            padding: 8px 16px;
            border-radius: 30px;
            font-size: 14px;
        }

        .subject-meta-item i {
            color: #FFD700;
        }

        .action-buttons {
            display: flex;
            gap: 10px;
        }

        .btn-edit {
            background: white;
            color: #0B4F2E;
            padding: 12px 25px;
            border-radius: 12px;
            text-decoration: none;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s;
        }

        .btn-edit:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }

        /* Stats Cards */
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
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .stat-icon {
            width: 50px;
            height: 50px;
            background: rgba(11, 79, 46, 0.1);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #0B4F2E;
            font-size: 24px;
        }

        .stat-content {
            flex: 1;
        }

        .stat-number {
            font-size: 28px;
            font-weight: 700;
            color: var(--text-primary);
            line-height: 1.2;
        }

        .stat-label {
            color: var(--text-secondary);
            font-size: 13px;
        }

        /* Chart Card */
        .chart-card {
            background: white;
            border-radius: 20px;
            padding: 25px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            margin-bottom: 25px;
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

        /* Detail Cards */
        .detail-card {
            background: white;
            border-radius: 20px;
            padding: 25px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            margin-bottom: 25px;
        }

        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 1px solid var(--border-color);
        }

        .card-header h3 {
            color: var(--text-primary);
            font-size: 18px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .card-header h3 i {
            color: #0B4F2E;
        }

        /* Tables */
        .table-container {
            overflow-x: auto;
        }

        .attendance-table,
        .students-table {
            width: 100%;
            border-collapse: collapse;
        }

        .attendance-table th,
        .students-table th {
            text-align: left;
            padding: 12px;
            background: #f8f9fa;
            color: var(--text-secondary);
            font-weight: 600;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-bottom: 2px solid var(--border-color);
        }

        .attendance-table td,
        .students-table td {
            padding: 12px;
            border-bottom: 1px solid var(--border-color);
            color: var(--text-primary);
            font-size: 14px;
        }

        .attendance-table tbody tr:hover,
        .students-table tbody tr:hover {
            background: var(--hover-color);
        }

        .student-avatar {
            width: 35px;
            height: 35px;
            background: rgba(11, 79, 46, 0.1);
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #0B4F2E;
            font-weight: 600;
        }

        .badge {
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
            display: inline-block;
        }

        .badge-present {
            background: rgba(40, 167, 69, 0.1);
            color: #28a745;
        }

        .badge-absent {
            background: rgba(220, 53, 69, 0.1);
            color: #dc3545;
        }

        .badge-late {
            background: rgba(255, 193, 7, 0.1);
            color: #ffc107;
        }

        .view-link {
            color: #0B4F2E;
            text-decoration: none;
            font-size: 13px;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }

        .view-link:hover {
            text-decoration: underline;
        }

        /* Top Students List */
        .top-students-list {
            list-style: none;
        }

        .top-student-item {
            display: flex;
            align-items: center;
            gap: 15px;
            padding: 15px 0;
            border-bottom: 1px solid var(--border-color);
        }

        .top-student-item:last-child {
            border-bottom: none;
        }

        .student-rank {
            width: 30px;
            height: 30px;
            background: #FFD700;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            color: #0B4F2E;
        }

        .student-info {
            flex: 1;
        }

        .student-info h4 {
            font-size: 16px;
            margin-bottom: 3px;
            color: var(--text-primary);
        }

        .student-info p {
            font-size: 13px;
            color: var(--text-secondary);
        }

        .student-rate {
            font-size: 18px;
            font-weight: 700;
            color: #28a745;
        }

        .no-data {
            text-align: center;
            padding: 40px;
            color: var(--text-secondary);
        }

        .no-data i {
            font-size: 48px;
            margin-bottom: 15px;
            opacity: 0.3;
        }

        .no-data h3 {
            color: var(--text-primary);
            margin-bottom: 10px;
        }

        /* Responsive */
        @media (max-width: 1200px) {
            .stats-grid {
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
            
            .subject-header {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .action-buttons {
                width: 100%;
            }
            
            .btn-edit {
                width: 100%;
                justify-content: center;
            }
            
            .stats-grid {
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
                <div class="header-left">
                    <h1>Subject Details</h1>
                    <p>View complete subject information and statistics</p>
                </div>
                <a href="subjects.php" class="back-btn">
                    <i class="fas fa-arrow-left"></i> Back to Subjects
                </a>
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
            <?php if(isset($_SESSION['success_message'])): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    <?php echo $_SESSION['success_message']; unset($_SESSION['success_message']); ?>
                </div>
            <?php endif; ?>

            <!-- Subject Header -->
            <div class="subject-header">
                <div class="subject-title">
                    <h2>
                        <i class="fas fa-book"></i>
                        <?php echo htmlspecialchars($subject['subject_name']); ?>
                    </h2>
                    <div class="subject-meta">
                        <span class="subject-meta-item">
                            <i class="fas fa-layer-group"></i> <?php echo htmlspecialchars($subject['grade_name']); ?>
                        </span>
                        <span class="subject-meta-item">
                            <i class="fas fa-hashtag"></i> Subject ID: <?php echo $subject['id']; ?>
                        </span>
                    </div>
                </div>
                <div class="action-buttons">
                    <a href="edit_subject.php?id=<?php echo $subject_id; ?>" class="btn-edit">
                        <i class="fas fa-edit"></i> Edit Subject
                    </a>
                </div>
            </div>

            <!-- Statistics Cards -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-calendar-check"></i>
                    </div>
                    <div class="stat-content">
                        <div class="stat-number"><?php echo $stats['total'] ?? 0; ?></div>
                        <div class="stat-label">Total Attendance</div>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-user-check"></i>
                    </div>
                    <div class="stat-content">
                        <div class="stat-number" style="color: #28a745;"><?php echo $stats['present'] ?? 0; ?></div>
                        <div class="stat-label">Present</div>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-user-times"></i>
                    </div>
                    <div class="stat-content">
                        <div class="stat-number" style="color: #dc3545;"><?php echo $stats['absent'] ?? 0; ?></div>
                        <div class="stat-label">Absent</div>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-clock"></i>
                    </div>
                    <div class="stat-content">
                        <div class="stat-number" style="color: #ffc107;"><?php echo $stats['late'] ?? 0; ?></div>
                        <div class="stat-label">Late</div>
                    </div>
                </div>
            </div>

            <!-- Attendance Trend Chart -->
            <?php if($monthly_stats && $monthly_stats->num_rows > 0): ?>
            <div class="chart-card">
                <div class="chart-header">
                    <h3><i class="fas fa-chart-line"></i> Attendance Trend</h3>
                    <span class="subject-meta-item">Last 6 months</span>
                </div>
                <div class="chart-container">
                    <canvas id="attendanceChart"></canvas>
                </div>
            </div>
            <?php endif; ?>

            <!-- Recent Attendance Records -->
            <div class="detail-card">
                <div class="card-header">
                    <h3><i class="fas fa-history"></i> Recent Attendance Records</h3>
                    <a href="attendance.php?subject=<?php echo $subject_id; ?>" class="view-link">
                        View All <i class="fas fa-arrow-right"></i>
                    </a>
                </div>

                <?php if($attendance && $attendance->num_rows > 0): ?>
                    <div class="table-container">
                        <table class="attendance-table">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Student</th>
                                    <th>ID Number</th>
                                    <th>Status</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while($record = $attendance->fetch_assoc()): ?>
                                    <tr>
                                        <td><?php echo date('M d, Y', strtotime($record['date'])); ?></td>
                                        <td><?php echo htmlspecialchars($record['student_name']); ?></td>
                                        <td><?php echo $record['id_number'] ?? 'N/A'; ?></td>
                                        <td>
                                            <span class="badge badge-<?php echo strtolower($record['status']); ?>">
                                                <?php echo $record['status']; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <a href="view_student.php?id=<?php echo $record['student_id']; ?>" class="view-link">
                                                View Student <i class="fas fa-eye"></i>
                                            </a>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="no-data" style="padding: 20px;">
                        <i class="fas fa-calendar-times"></i>
                        <p>No attendance records found for this subject.</p>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Top Performing Students -->
            <?php if($top_students && $top_students->num_rows > 0): ?>
            <div class="detail-card">
                <div class="card-header">
                    <h3><i class="fas fa-trophy"></i> Top Performing Students</h3>
                    <span class="subject-meta-item">Based on attendance rate</span>
                </div>

                <div class="top-students-list">
                    <?php 
                    $rank = 1;
                    while($student = $top_students->fetch_assoc()): 
                    ?>
                        <div class="top-student-item">
                            <div class="student-rank"><?php echo $rank++; ?></div>
                            <div class="student-info">
                                <h4><?php echo htmlspecialchars($student['fullname']); ?></h4>
                                <p>ID: <?php echo $student['id_number'] ?? 'N/A'; ?> • Present: <?php echo $student['present_count']; ?>/<?php echo $student['total_attendance']; ?></p>
                            </div>
                            <div class="student-rate"><?php echo $student['attendance_rate']; ?>%</div>
                        </div>
                    <?php endwhile; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- Students Enrolled in this Grade Level -->
            <div class="detail-card">
                <div class="card-header">
                    <h3><i class="fas fa-user-graduate"></i> Students in <?php echo htmlspecialchars($subject['grade_name']); ?></h3>
                    <a href="students.php?grade=<?php echo $subject['grade_id']; ?>" class="view-link">
                        View All <i class="fas fa-arrow-right"></i>
                    </a>
                </div>

                <?php if($students && $students->num_rows > 0): ?>
                    <div class="table-container">
                        <table class="students-table">
                            <thead>
                                <tr>
                                    <th></th>
                                    <th>Student Name</th>
                                    <th>ID Number</th>
                                    <th>Strand</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while($student = $students->fetch_assoc()): ?>
                                    <tr>
                                        <td>
                                            <div class="student-avatar">
                                                <?php echo strtoupper(substr($student['fullname'], 0, 1)); ?>
                                            </div>
                                        </td>
                                        <td><?php echo htmlspecialchars($student['fullname']); ?></td>
                                        <td><?php echo $student['id_number'] ?? 'N/A'; ?></td>
                                        <td>
                                            <?php if($student['strand']): ?>
                                                <span class="badge" style="background: rgba(255, 215, 0, 0.1); color: #b8860b;">
                                                    <?php echo $student['strand']; ?>
                                                </span>
                                            <?php else: ?>
                                                —
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <a href="view_student.php?id=<?php echo $student['id']; ?>" class="view-link">
                                                View <i class="fas fa-eye"></i>
                                            </a>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="no-data" style="padding: 20px;">
                        <i class="fas fa-users"></i>
                        <p>No students enrolled in this grade level.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        // Attendance Trend Chart
        <?php if($monthly_stats && $monthly_stats->num_rows > 0): ?>
        const ctx = document.getElementById('attendanceChart').getContext('2d');
        
        <?php
        $months = [];
        $present_data = [];
        $total_data = [];
        
        $monthly_stats->data_seek(0);
        while($row = $monthly_stats->fetch_assoc()) {
            $months[] = date('M Y', strtotime($row['month'] . '-01'));
            $present_data[] = $row['present'];
            $total_data[] = $row['total'];
        }
        ?>
        
        new Chart(ctx, {
            type: 'line',
            data: {
                labels: <?php echo json_encode(array_reverse($months)); ?>,
                datasets: [
                    {
                        label: 'Present',
                        data: <?php echo json_encode(array_reverse($present_data)); ?>,
                        borderColor: '#28a745',
                        backgroundColor: 'rgba(40, 167, 69, 0.1)',
                        tension: 0.4,
                        fill: true
                    },
                    {
                        label: 'Total Attendance',
                        data: <?php echo json_encode(array_reverse($total_data)); ?>,
                        borderColor: '#0B4F2E',
                        backgroundColor: 'rgba(11, 79, 46, 0.1)',
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
        <?php endif; ?>

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
</body>
</html>