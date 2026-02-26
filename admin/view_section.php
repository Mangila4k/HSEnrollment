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
    header("Location: sections.php");
    exit();
}

$section_id = $_GET['id'];

// Get section details
$query = "
    SELECT s.*, g.grade_name, u.fullname as adviser_name, u.email as adviser_email, u.id as adviser_id
    FROM sections s
    LEFT JOIN grade_levels g ON s.grade_id = g.id
    LEFT JOIN users u ON s.adviser_id = u.id
    WHERE s.id = ?
";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $section_id);
$stmt->execute();
$result = $stmt->get_result();

if($result->num_rows === 0) {
    header("Location: sections.php");
    exit();
}

$section = $result->fetch_assoc();
$stmt->close();

// Get students enrolled in this section's grade level
$students_query = "
    SELECT u.*, e.status, e.strand, e.school_year, e.created_at as enrolled_date
    FROM users u
    JOIN enrollments e ON u.id = e.student_id
    WHERE e.grade_id = ? AND e.status = 'Enrolled'
    ORDER BY u.fullname
";
$stmt = $conn->prepare($students_query);
$stmt->bind_param("i", $section['grade_id']);
$stmt->execute();
$students = $stmt->get_result();
$total_students = $students->num_rows;
$stmt->close();

// Get subjects for this grade level
$subjects_query = "
    SELECT * FROM subjects 
    WHERE grade_id = ?
    ORDER BY subject_name
";
$stmt = $conn->prepare($subjects_query);
$stmt->bind_param("i", $section['grade_id']);
$stmt->execute();
$subjects = $stmt->get_result();
$total_subjects = $subjects->num_rows;
$stmt->close();

// Get attendance statistics for this section
$attendance_stats = [
    'present' => 0,
    'absent' => 0,
    'late' => 0,
    'total' => 0
];

if($total_students > 0) {
    $student_ids = [];
    $students->data_seek(0);
    while($student = $students->fetch_assoc()) {
        $student_ids[] = $student['id'];
    }
    $students->data_seek(0);
    
    if(!empty($student_ids)) {
        $ids_string = implode(',', $student_ids);
        $stats_query = "
            SELECT status, COUNT(*) as count
            FROM attendance
            WHERE student_id IN ($ids_string)
            GROUP BY status
        ";
        $stats_result = $conn->query($stats_query);
        while($row = $stats_result->fetch_assoc()) {
            $attendance_stats[strtolower($row['status'])] = $row['count'];
            $attendance_stats['total'] += $row['count'];
        }
    }
}

$attendance_rate = $attendance_stats['total'] > 0 
    ? round(($attendance_stats['present'] / $attendance_stats['total']) * 100, 2) 
    : 0;

// Get schedule (placeholder - you can expand this if you have a schedules table)
$schedule = [
    'Monday' => ['08:00 AM - 09:00 AM' => 'Mathematics', '09:00 AM - 10:00 AM' => 'Science'],
    'Tuesday' => ['08:00 AM - 09:00 AM' => 'English', '09:00 AM - 10:00 AM' => 'Filipino'],
    'Wednesday' => ['08:00 AM - 09:00 AM' => 'Mathematics', '09:00 AM - 10:00 AM' => 'Science'],
    'Thursday' => ['08:00 AM - 09:00 AM' => 'English', '09:00 AM - 10:00 AM' => 'Filipino'],
    'Friday' => ['08:00 AM - 09:00 AM' => 'MAPEH', '09:00 AM - 10:00 AM' => 'Araling Panlipunan']
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Section - Admin Dashboard</title>
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

        /* Section Header Card */
        .section-header {
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

        .section-title h2 {
            font-size: 32px;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .section-title h2 i {
            color: #FFD700;
        }

        .section-meta {
            display: flex;
            gap: 20px;
            flex-wrap: wrap;
        }

        .section-meta-item {
            display: flex;
            align-items: center;
            gap: 8px;
            background: rgba(255, 255, 255, 0.1);
            padding: 8px 16px;
            border-radius: 30px;
            font-size: 14px;
        }

        .section-meta-item i {
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

        /* Adviser Card */
        .adviser-card {
            background: white;
            border-radius: 15px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            margin-bottom: 30px;
            display: flex;
            align-items: center;
            gap: 20px;
            flex-wrap: wrap;
        }

        .adviser-avatar {
            width: 60px;
            height: 60px;
            background: linear-gradient(135deg, #0B4F2E, #1a7a42);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 28px;
            font-weight: bold;
            color: white;
        }

        .adviser-info {
            flex: 1;
        }

        .adviser-info h3 {
            color: var(--text-primary);
            font-size: 18px;
            margin-bottom: 5px;
        }

        .adviser-info p {
            color: var(--text-secondary);
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .adviser-info i {
            color: #0B4F2E;
        }

        .btn-view-adviser {
            background: #0B4F2E;
            color: white;
            padding: 10px 20px;
            border-radius: 8px;
            text-decoration: none;
            font-size: 14px;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s;
        }

        .btn-view-adviser:hover {
            background: #1a7a42;
            transform: translateY(-2px);
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

        /* Students Table */
        .table-container {
            overflow-x: auto;
        }

        .students-table {
            width: 100%;
            border-collapse: collapse;
        }

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

        .students-table td {
            padding: 12px;
            border-bottom: 1px solid var(--border-color);
            color: var(--text-primary);
            font-size: 14px;
        }

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

        /* Subjects Grid */
        .subjects-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 10px;
        }

        .subject-item {
            background: #f8f9fa;
            padding: 12px 15px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .subject-item i {
            color: #0B4F2E;
        }

        .subject-name {
            flex: 1;
            font-size: 14px;
            color: var(--text-primary);
        }

        /* Schedule Grid */
        .schedule-grid {
            display: grid;
            grid-template-columns: repeat(5, 1fr);
            gap: 15px;
        }

        .day-column {
            background: #f8f9fa;
            border-radius: 12px;
            padding: 15px;
        }

        .day-header {
            font-weight: 600;
            color: #0B4F2E;
            text-align: center;
            padding-bottom: 10px;
            margin-bottom: 10px;
            border-bottom: 2px solid var(--border-color);
        }

        .time-slot {
            background: white;
            padding: 10px;
            border-radius: 8px;
            margin-bottom: 8px;
            font-size: 12px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
        }

        .time-slot .time {
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 3px;
        }

        .time-slot .subject {
            color: #0B4F2E;
            font-size: 11px;
        }

        .empty-slot {
            background: transparent;
            padding: 10px;
            border-radius: 8px;
            margin-bottom: 8px;
            border: 1px dashed var(--border-color);
            color: var(--text-secondary);
            font-size: 11px;
            text-align: center;
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
            
            .schedule-grid {
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
            
            .section-header {
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
            
            .adviser-card {
                flex-direction: column;
                text-align: center;
            }
            
            .schedule-grid {
                grid-template-columns: 1fr;
            }
            
            .subjects-grid {
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
                    <li><a href="sections.php" class="active"><i class="fas fa-layer-group"></i> <span>Sections</span></a></li>
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
                <div class="header-left">
                    <h1>Section Details</h1>
                    <p>View complete section information and roster</p>
                </div>
                <a href="sections.php" class="back-btn">
                    <i class="fas fa-arrow-left"></i> Back to Sections
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

            <!-- Section Header -->
            <div class="section-header">
                <div class="section-title">
                    <h2>
                        <i class="fas fa-layer-group"></i>
                        <?php echo htmlspecialchars($section['section_name']); ?>
                    </h2>
                    <div class="section-meta">
                        <span class="section-meta-item">
                            <i class="fas fa-graduation-cap"></i> <?php echo htmlspecialchars($section['grade_name']); ?>
                        </span>
                        <span class="section-meta-item">
                            <i class="fas fa-users"></i> <?php echo $total_students; ?> Students
                        </span>
                        <span class="section-meta-item">
                            <i class="fas fa-book"></i> <?php echo $total_subjects; ?> Subjects
                        </span>
                    </div>
                </div>
                <div class="action-buttons">
                    <a href="edit_section.php?id=<?php echo $section_id; ?>" class="btn-edit">
                        <i class="fas fa-edit"></i> Edit Section
                    </a>
                </div>
            </div>

            <!-- Statistics Cards -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-user-graduate"></i>
                    </div>
                    <div class="stat-content">
                        <div class="stat-number"><?php echo $total_students; ?></div>
                        <div class="stat-label">Enrolled Students</div>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-book"></i>
                    </div>
                    <div class="stat-content">
                        <div class="stat-number"><?php echo $total_subjects; ?></div>
                        <div class="stat-label">Subjects</div>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-calendar-check"></i>
                    </div>
                    <div class="stat-content">
                        <div class="stat-number"><?php echo $attendance_stats['total']; ?></div>
                        <div class="stat-label">Attendance Records</div>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-chart-line"></i>
                    </div>
                    <div class="stat-content">
                        <div class="stat-number"><?php echo $attendance_rate; ?>%</div>
                        <div class="stat-label">Attendance Rate</div>
                    </div>
                </div>
            </div>

            <!-- Class Adviser -->
            <div class="adviser-card">
                <div class="adviser-avatar">
                    <?php echo $section['adviser_name'] ? strtoupper(substr($section['adviser_name'], 0, 1)) : '?'; ?>
                </div>
                <div class="adviser-info">
                    <h3>Class Adviser</h3>
                    <?php if($section['adviser_name']): ?>
                        <p>
                            <i class="fas fa-user"></i> <?php echo htmlspecialchars($section['adviser_name']); ?>
                            <i class="fas fa-envelope" style="margin-left: 15px;"></i> <?php echo htmlspecialchars($section['adviser_email']); ?>
                        </p>
                    <?php else: ?>
                        <p style="color: #ffc107;">
                            <i class="fas fa-exclamation-triangle"></i> No adviser assigned to this section
                        </p>
                    <?php endif; ?>
                </div>
                <?php if($section['adviser_id']): ?>
                    <a href="view_teacher.php?id=<?php echo $section['adviser_id']; ?>" class="btn-view-adviser">
                        <i class="fas fa-eye"></i> View Adviser Profile
                    </a>
                <?php endif; ?>
            </div>

            <!-- Students List -->
            <div class="detail-card">
                <div class="card-header">
                    <h3><i class="fas fa-users"></i> Student Roster</h3>
                    <span class="section-meta-item"><?php echo $total_students; ?> Students</span>
                </div>

                <?php if($students && $students->num_rows > 0): ?>
                    <div class="table-container">
                        <table class="students-table">
                            <thead>
                                <tr>
                                    <th></th>
                                    <th>Student Name</th>
                                    <th>ID Number</th>
                                    <th>Email</th>
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
                                        <td><?php echo htmlspecialchars($student['email']); ?></td>
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
                    <div class="no-data">
                        <i class="fas fa-users"></i>
                        <h3>No Students Enrolled</h3>
                        <p>This section has no enrolled students yet.</p>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Subjects -->
            <div class="detail-card">
                <div class="card-header">
                    <h3><i class="fas fa-book"></i> Subjects Offered</h3>
                    <span class="section-meta-item"><?php echo $total_subjects; ?> Subjects</span>
                </div>

                <?php if($subjects && $subjects->num_rows > 0): ?>
                    <div class="subjects-grid">
                        <?php while($subject = $subjects->fetch_assoc()): ?>
                            <div class="subject-item">
                                <i class="fas fa-book-open"></i>
                                <span class="subject-name"><?php echo htmlspecialchars($subject['subject_name']); ?></span>
                            </div>
                        <?php endwhile; ?>
                    </div>
                <?php else: ?>
                    <div class="no-data" style="padding: 20px;">
                        <i class="fas fa-book"></i>
                        <p>No subjects found for this grade level.</p>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Class Schedule -->
            <div class="detail-card">
                <div class="card-header">
                    <h3><i class="fas fa-calendar-alt"></i> Class Schedule</h3>
                </div>

                <div class="schedule-grid">
                    <?php foreach($schedule as $day => $classes): ?>
                        <div class="day-column">
                            <div class="day-header"><?php echo $day; ?></div>
                            <?php foreach($classes as $time => $subject): ?>
                                <div class="time-slot">
                                    <div class="time"><?php echo $time; ?></div>
                                    <div class="subject"><?php echo $subject; ?></div>
                                </div>
                            <?php endforeach; ?>
                            <?php for($i = count($classes); $i < 4; $i++): ?>
                                <div class="empty-slot">No class</div>
                            <?php endfor; ?>
                        </div>
                    <?php endforeach; ?>
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
    </script>
</body>
</html>