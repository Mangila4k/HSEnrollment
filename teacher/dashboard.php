<?php
session_start();
include("../config/database.php");

// Check if user is teacher
if(!isset($_SESSION['user']) || $_SESSION['user']['role'] != 'Teacher'){
    header("Location: ../auth/login.php");
    exit();
}

// Get teacher's information
$teacher_id = $_SESSION['user']['id'];
$teacher_name = $_SESSION['user']['fullname'];

// Get sections where teacher is adviser
$sections_query = $conn->prepare("
    SELECT s.*, g.grade_name 
    FROM sections s
    JOIN grade_levels g ON s.grade_id = g.id
    WHERE s.adviser_id = ?
");
$sections_query->execute([$teacher_id]);
$sections = $sections_query->fetchAll(PDO::FETCH_ASSOC);

// Get all grade levels for the filter dropdown
$grade_levels_query = $conn->query("SELECT * FROM grade_levels ORDER BY id");
$grade_levels = $grade_levels_query->fetchAll(PDO::FETCH_ASSOC);

// Get subjects based on selected grade (default to first grade or all)
$selected_grade_id = isset($_GET['grade_id']) ? intval($_GET['grade_id']) : 0;

if ($selected_grade_id > 0) {
    // Get subjects filtered by grade
    $subjects_query = $conn->prepare("
        SELECT sub.*, g.grade_name 
        FROM subjects sub
        JOIN grade_levels g ON sub.grade_id = g.id
        WHERE sub.grade_id = ?
        ORDER BY sub.subject_name
    ");
    $subjects_query->execute([$selected_grade_id]);
} else {
    // Get all subjects
    $subjects_query = $conn->prepare("
        SELECT sub.*, g.grade_name 
        FROM subjects sub
        JOIN grade_levels g ON sub.grade_id = g.id
        ORDER BY g.id, sub.subject_name
    ");
    $subjects_query->execute();
}
$subjects = $subjects_query->fetchAll(PDO::FETCH_ASSOC);

// Get today's attendance count
$today = date('Y-m-d');
$attendance_today_stmt = $conn->prepare("
    SELECT COUNT(*) as count 
    FROM attendance 
    WHERE date = ?
");
$attendance_today_stmt->execute([$today]);
$attendance_today = $attendance_today_stmt->fetch(PDO::FETCH_ASSOC)['count'];

// Get total students in teacher's sections
$total_students = 0;
if(count($sections) > 0) {
    foreach($sections as $section) {
        $student_count_stmt = $conn->prepare("
            SELECT COUNT(*) as count 
            FROM enrollments e
            JOIN users u ON e.student_id = u.id
            WHERE e.grade_id = ? 
            AND e.status = 'Enrolled'
        ");
        $student_count_stmt->execute([$section['grade_id']]);
        $student_count = $student_count_stmt->fetch(PDO::FETCH_ASSOC)['count'];
        $total_students += $student_count;
    }
}

// Get recent attendance records
$recent_attendance_stmt = $conn->prepare("
    SELECT a.*, u.fullname, sub.subject_name
    FROM attendance a
    JOIN users u ON a.student_id = u.id
    JOIN subjects sub ON a.subject_id = sub.id
    ORDER BY a.date DESC, a.created_at DESC
    LIMIT 10
");
$recent_attendance_stmt->execute();
$recent_attendance = $recent_attendance_stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Placido L. Señor Senior High School - Teacher Dashboard</title>
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

        .teacher-info {
            text-align: center;
            padding: 20px 0;
            border-bottom: 1px solid rgba(255, 255, 255, 0.2);
            margin-bottom: 20px;
        }

        .teacher-avatar {
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

        .teacher-info h3 {
            font-size: 18px;
            margin-bottom: 5px;
            color: #FFD700;
        }

        .teacher-info p {
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

        /* Quick Actions */
        .quick-actions {
            margin-bottom: 30px;
        }

        .quick-actions h3 {
            color: var(--text-primary);
            font-size: 18px;
            font-weight: 600;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .quick-actions h3 i {
            color: #0B4F2E;
        }

        .action-buttons {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
        }

        .action-btn {
            background: white;
            border: 2px solid var(--border-color);
            border-radius: 15px;
            padding: 25px;
            text-decoration: none;
            color: var(--text-primary);
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .action-btn:hover {
            border-color: #0B4F2E;
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(11, 79, 46, 0.1);
        }

        .action-icon {
            width: 60px;
            height: 60px;
            background: linear-gradient(135deg, #0B4F2E, #1a7a42);
            border-radius: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 24px;
        }

        .action-content h4 {
            font-size: 18px;
            margin-bottom: 5px;
            font-weight: 600;
        }

        .action-content p {
            color: var(--text-secondary);
            font-size: 14px;
        }

        /* Sections and Subjects */
        .info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 25px;
            margin-bottom: 30px;
        }

        .info-card {
            background: white;
            border-radius: 15px;
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

        .section-list, .subject-list {
            list-style: none;
        }

        .section-item, .subject-item {
            padding: 15px 0;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .section-item:last-child, .subject-item:last-child {
            border-bottom: none;
        }

        .section-info h4, .subject-info h4 {
            color: var(--text-primary);
            font-size: 16px;
            margin-bottom: 5px;
        }

        .section-info p, .subject-info p {
            color: var(--text-secondary);
            font-size: 13px;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .badge {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            background: rgba(11, 79, 46, 0.1);
            color: #0B4F2E;
        }

        /* Subject Filter */
        .subject-filter {
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 15px;
            flex-wrap: wrap;
        }

        .filter-group {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .filter-group label {
            font-weight: 600;
            color: var(--text-primary);
            font-size: 14px;
        }

        .grade-select {
            padding: 10px 15px;
            border: 1px solid var(--border-color);
            border-radius: 10px;
            font-family: 'Inter', sans-serif;
            font-size: 14px;
            color: var(--text-primary);
            background: white;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .grade-select:hover, .grade-select:focus {
            border-color: #0B4F2E;
            outline: none;
            box-shadow: 0 0 0 3px rgba(11, 79, 46, 0.1);
        }

        .filter-btn {
            background: #0B4F2E;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 10px;
            font-family: 'Inter', sans-serif;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .filter-btn:hover {
            background: #1a7a42;
            transform: translateY(-2px);
        }

        .filter-btn.reset {
            background: #6c757d;
        }

        .filter-btn.reset:hover {
            background: #5a6268;
        }

        /* Recent Attendance */
        .recent-attendance {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
        }

        .recent-attendance h3 {
            color: var(--text-primary);
            font-size: 18px;
            font-weight: 600;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .recent-attendance h3 i {
            color: #0B4F2E;
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
        }

        .attendance-table td {
            padding: 15px;
            border-bottom: 1px solid var(--border-color);
            color: var(--text-primary);
        }

        .status-badge {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            display: inline-block;
        }

        .status-present {
            background: rgba(16, 185, 129, 0.1);
            color: #10b981;
        }

        .status-absent {
            background: rgba(239, 68, 68, 0.1);
            color: #ef4444;
        }

        .status-late {
            background: rgba(245, 158, 11, 0.1);
            color: #f59e0b;
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

        /* Responsive */
        @media (max-width: 1200px) {
            .stats-container {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .info-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 768px) {
            .sidebar {
                width: 80px;
                padding: 20px 10px;
            }
            
            .sidebar h2 span,
            .teacher-info h3,
            .teacher-info p,
            .menu-section h3,
            .menu-items a span {
                display: none;
            }
            
            .teacher-avatar {
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
            
            .stats-container {
                grid-template-columns: 1fr;
            }
            
            .welcome-card {
                flex-direction: column;
                text-align: center;
                gap: 20px;
            }
            
            .action-buttons {
                grid-template-columns: 1fr;
            }
            
            .attendance-table {
                font-size: 14px;
            }
            
            .attendance-table th,
            .attendance-table td {
                padding: 10px;
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
            
            <div class="teacher-info">
                <div class="teacher-avatar">
                    <?php echo strtoupper(substr($teacher_name, 0, 1)); ?>
                </div>
                <h3><?php echo htmlspecialchars($teacher_name); ?></h3>
                <p><i class="fas fa-chalkboard-teacher"></i> Teacher</p>
            </div>
            
            <div class="menu-section">
                <h3>MAIN MENU</h3>
                <ul class="menu-items">
                    <li><a href="dashboard.php" class="active"><i class="fas fa-tachometer-alt"></i> <span>Dashboard</span></a></li>
                    <li><a href="attendance_qr.php"><i class="fas fa-qrcode"></i> <span>QR Attendance</span></a></li>
                    <li><a href="classes.php"><i class="fas fa-users"></i> <span>My Classes</span></a></li>
                    <li><a href="schedule.php"><i class="fas fa-clock"></i> <span>Schedule</span></a></li>
                    <li><a href="grades.php"><i class="fas fa-star"></i> <span>Grades</span></a></li>
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
            <!-- Welcome Card -->
            <div class="welcome-card">
                <div class="welcome-text">
                    <h2>Welcome back, <?php echo htmlspecialchars(explode(' ', $teacher_name)[0]); ?>!</h2>
                    <p><i class="fas fa-calendar-alt"></i> <?php echo date('l, F d, Y'); ?></p>
                </div>
                <a href="../auth/logout.php" class="logout-btn">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a>
            </div>

            <!-- Header -->
            <div class="dashboard-header">
                <h1>Teacher Dashboard</h1>
                <p>Manage your classes, attendance, and student progress</p>
            </div>

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
                    <div class="stat-label">Enrolled students</div>
                </div>

                <div class="stat-card">
                    <div class="stat-header">
                        <h3>My Sections</h3>
                        <div class="stat-icon">
                            <i class="fas fa-layer-group"></i>
                        </div>
                    </div>
                    <div class="stat-number"><?php echo count($sections); ?></div>
                    <div class="stat-label">Classes handling</div>
                </div>

                <div class="stat-card">
                    <div class="stat-header">
                        <h3>Subjects</h3>
                        <div class="stat-icon">
                            <i class="fas fa-book"></i>
                        </div>
                    </div>
                    <div class="stat-number"><?php echo count($subjects); ?></div>
                    <div class="stat-label">Showing subjects</div>
                </div>

                <div class="stat-card">
                    <div class="stat-header">
                        <h3>Today's Attendance</h3>
                        <div class="stat-icon">
                            <i class="fas fa-calendar-check"></i>
                        </div>
                    </div>
                    <div class="stat-number"><?php echo $attendance_today; ?></div>
                    <div class="stat-label">Records today</div>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="quick-actions">
                <h3><i class="fas fa-bolt"></i> Quick Actions</h3>
                <div class="action-buttons">
                    <a href="attendance_qr.php" class="action-btn">
                        <div class="action-icon">
                            <i class="fas fa-qrcode"></i>
                        </div>
                        <div class="action-content">
                            <h4>QR Attendance</h4>
                            <p>Scan QR code to record attendance</p>
                        </div>
                    </a>
                    
                    <a href="classes.php" class="action-btn">
                        <div class="action-icon">
                            <i class="fas fa-users"></i>
                        </div>
                        <div class="action-content">
                            <h4>View Classes</h4>
                            <p>See your assigned sections</p>
                        </div>
                    </a>
                    
                    <a href="schedule.php" class="action-btn">
                        <div class="action-icon">
                            <i class="fas fa-clock"></i>
                        </div>
                        <div class="action-content">
                            <h4>Class Schedule</h4>
                            <p>Check your teaching schedule</p>
                        </div>
                    </a>
                    
                    <a href="grades.php" class="action-btn">
                        <div class="action-icon">
                            <i class="fas fa-star"></i>
                        </div>
                        <div class="action-content">
                            <h4>Enter Grades</h4>
                            <p>Input student grades</p>
                        </div>
                    </a>
                </div>
            </div>

            <!-- My Classes and Subjects -->
            <div class="info-grid">
                <!-- My Sections -->
                <div class="info-card">
                    <h3><i class="fas fa-layer-group"></i> My Sections</h3>
                    <?php if(count($sections) > 0): ?>
                        <ul class="section-list">
                            <?php foreach($sections as $section): ?>
                                <li class="section-item">
                                    <div class="section-info">
                                        <h4><?php echo htmlspecialchars($section['section_name']); ?></h4>
                                        <p><i class="fas fa-tag"></i> <?php echo htmlspecialchars($section['grade_name']); ?></p>
                                    </div>
                                    <span class="badge">Adviser</span>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php else: ?>
                        <div class="no-data">
                            <i class="fas fa-layer-group"></i>
                            <p>No sections assigned yet</p>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Subjects with Grade Filter -->
                <div class="info-card">
                    <h3><i class="fas fa-book"></i> School Subjects</h3>
                    
                    <!-- Filter Form -->
                    <div class="subject-filter">
                        <form method="GET" action="" style="display: flex; gap: 10px; flex-wrap: wrap; align-items: center;">
                            <div class="filter-group">
                                <label for="grade_id">Filter by Grade:</label>
                                <select name="grade_id" id="grade_id" class="grade-select">
                                    <option value="0">All Grades</option>
                                    <?php foreach($grade_levels as $grade): ?>
                                        <option value="<?php echo $grade['id']; ?>" <?php echo ($selected_grade_id == $grade['id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($grade['grade_name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <button type="submit" class="filter-btn">
                                <i class="fas fa-filter"></i> Filter
                            </button>
                            <a href="dashboard.php" class="filter-btn reset">
                                <i class="fas fa-undo-alt"></i> Reset
                            </a>
                        </form>
                    </div>

                    <!-- Subjects List -->
                    <?php if(count($subjects) > 0): ?>
                        <ul class="subject-list">
                            <?php foreach($subjects as $subject): ?>
                                <li class="subject-item">
                                    <div class="subject-info">
                                        <h4><?php echo htmlspecialchars($subject['subject_name']); ?></h4>
                                        <p><i class="fas fa-graduation-cap"></i> <?php echo htmlspecialchars($subject['grade_name']); ?></p>
                                    </div>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php else: ?>
                        <div class="no-data">
                            <i class="fas fa-book"></i>
                            <p>No subjects found for this grade level.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    <?php include('../includes/chatbot_widget_teacher.php'); ?>
</body>
</html>