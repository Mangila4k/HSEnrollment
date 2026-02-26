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
    header("Location: teachers.php");
    exit();
}

$teacher_id = $_GET['id'];

// Get teacher details
$query = "SELECT * FROM users WHERE id = ? AND role = 'Teacher'";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $teacher_id);
$stmt->execute();
$result = $stmt->get_result();

if($result->num_rows === 0) {
    header("Location: teachers.php");
    exit();
}

$teacher = $result->fetch_assoc();
$stmt->close();

// Get teacher's advisory sections
$sections_query = "
    SELECT s.*, g.grade_name,
           (SELECT COUNT(*) FROM enrollments e WHERE e.grade_id = s.grade_id AND e.status = 'Enrolled') as student_count
    FROM sections s
    LEFT JOIN grade_levels g ON s.grade_id = g.id
    WHERE s.adviser_id = ?
    ORDER BY g.id, s.section_name
";
$stmt = $conn->prepare($sections_query);
$stmt->bind_param("i", $teacher_id);
$stmt->execute();
$sections = $stmt->get_result();
$stmt->close();

// Get subjects taught by teacher (if you have a teacher_subjects table)
// For now, we'll show all subjects as placeholder
$subjects_query = "
    SELECT sub.*, g.grade_name
    FROM subjects sub
    LEFT JOIN grade_levels g ON sub.grade_id = g.id
    ORDER BY g.id, sub.subject_name
    LIMIT 10
";
$subjects = $conn->query($subjects_query);

// Get recent activities (attendance records from teacher's sections)
$activities_query = "
    SELECT a.*, u.fullname as student_name, sub.subject_name
    FROM attendance a
    LEFT JOIN users u ON a.student_id = u.id
    LEFT JOIN subjects sub ON a.subject_id = sub.id
    WHERE sub.id IN (SELECT id FROM subjects)
    ORDER BY a.date DESC
    LIMIT 10
";
$activities = $conn->query($activities_query);

// Calculate statistics
$total_sections = $sections->num_rows;
$total_students = 0;
$sections->data_seek(0);
while($section = $sections->fetch_assoc()) {
    $total_students += $section['student_count'];
}
$sections->data_seek(0);

$account_created = $teacher['created_at'];
$days_active = floor((time() - strtotime($account_created)) / (60 * 60 * 24));
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Teacher - Admin Dashboard</title>
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

        /* Teacher Profile Card */
        .profile-card {
            background: white;
            border-radius: 20px;
            padding: 30px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            margin-bottom: 25px;
            display: flex;
            gap: 30px;
            align-items: center;
            flex-wrap: wrap;
        }

        .profile-avatar-large {
            width: 120px;
            height: 120px;
            background: linear-gradient(135deg, #0B4F2E, #1a7a42);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 56px;
            font-weight: bold;
            color: white;
            border: 4px solid #FFD700;
            box-shadow: 0 10px 25px rgba(11, 79, 46, 0.3);
        }

        .profile-info {
            flex: 1;
        }

        .profile-info h2 {
            font-size: 28px;
            color: var(--text-primary);
            margin-bottom: 10px;
        }

        .profile-meta {
            display: flex;
            gap: 25px;
            flex-wrap: wrap;
            margin-bottom: 15px;
        }

        .profile-meta-item {
            display: flex;
            align-items: center;
            gap: 8px;
            color: var(--text-secondary);
            font-size: 14px;
        }

        .profile-meta-item i {
            color: #0B4F2E;
            width: 18px;
        }

        .profile-badge {
            display: inline-block;
            padding: 6px 16px;
            background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);
            color: white;
            border-radius: 30px;
            font-size: 13px;
            font-weight: 600;
            margin-right: 10px;
        }

        .action-buttons {
            display: flex;
            gap: 10px;
            margin-top: 15px;
            flex-wrap: wrap;
        }

        .btn-edit {
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

        .btn-edit:hover {
            background: #1a7a42;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(11, 79, 46, 0.3);
        }

        .btn-delete {
            background: #dc3545;
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

        .btn-delete:hover {
            background: #c82333;
            transform: translateY(-2px);
        }

        /* Stats Cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
            margin-bottom: 25px;
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

        /* Info Grid */
        .info-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
        }

        .info-item {
            margin-bottom: 15px;
        }

        .info-label {
            font-size: 12px;
            color: var(--text-secondary);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 5px;
        }

        .info-value {
            font-size: 16px;
            font-weight: 500;
            color: var(--text-primary);
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .info-value i {
            color: #0B4F2E;
            width: 20px;
        }

        /* Section Cards */
        .sections-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
        }

        .section-card {
            background: #f8f9fa;
            border-radius: 15px;
            padding: 20px;
            border-left: 4px solid #0B4F2E;
        }

        .section-card h4 {
            color: var(--text-primary);
            font-size: 18px;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .section-card h4 i {
            color: #FFD700;
        }

        .section-details {
            display: flex;
            gap: 15px;
            color: var(--text-secondary);
            font-size: 13px;
            margin-bottom: 15px;
        }

        .section-details i {
            color: #0B4F2E;
            width: 16px;
        }

        .section-stats {
            display: flex;
            gap: 15px;
            padding-top: 10px;
            border-top: 1px solid var(--border-color);
        }

        .section-stat {
            flex: 1;
            text-align: center;
        }

        .section-stat .value {
            font-size: 18px;
            font-weight: 600;
            color: #0B4F2E;
        }

        .section-stat .label {
            font-size: 11px;
            color: var(--text-secondary);
        }

        .view-link {
            color: #0B4F2E;
            text-decoration: none;
            font-size: 13px;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            margin-top: 10px;
        }

        .view-link:hover {
            text-decoration: underline;
        }

        /* Subjects List */
        .subjects-list {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
        }

        .subject-tag {
            background: rgba(11, 79, 46, 0.1);
            color: #0B4F2E;
            padding: 8px 16px;
            border-radius: 30px;
            font-size: 13px;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .subject-tag i {
            color: #FFD700;
        }

        /* Activities Table */
        .table-container {
            overflow-x: auto;
        }

        .activities-table {
            width: 100%;
            border-collapse: collapse;
        }

        .activities-table th {
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

        .activities-table td {
            padding: 12px;
            border-bottom: 1px solid var(--border-color);
            color: var(--text-primary);
            font-size: 14px;
        }

        .activities-table tbody tr:hover {
            background: var(--hover-color);
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
            
            .profile-card {
                flex-direction: column;
                text-align: center;
            }
            
            .profile-meta {
                justify-content: center;
            }
            
            .action-buttons {
                justify-content: center;
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .sections-grid {
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
                    <li><a href="teachers.php" class="active"><i class="fas fa-chalkboard-teacher"></i> <span>Teachers</span></a></li>
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
                <div class="header-left">
                    <h1>Teacher Profile</h1>
                    <p>View complete teacher information and assignments</p>
                </div>
                <a href="teachers.php" class="back-btn">
                    <i class="fas fa-arrow-left"></i> Back to Teachers
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

            <!-- Teacher Profile Card -->
            <div class="profile-card">
                <div class="profile-avatar-large">
                    <?php echo strtoupper(substr($teacher['fullname'], 0, 1)); ?>
                </div>
                <div class="profile-info">
                    <h2><?php echo htmlspecialchars($teacher['fullname']); ?></h2>
                    
                    <div class="profile-meta">
                        <span class="profile-meta-item">
                            <i class="fas fa-envelope"></i> <?php echo htmlspecialchars($teacher['email']); ?>
                        </span>
                        <span class="profile-meta-item">
                            <i class="fas fa-id-card"></i> ID: <?php echo $teacher['id_number'] ?? 'Not assigned'; ?>
                        </span>
                        <span class="profile-meta-item">
                            <i class="fas fa-calendar-alt"></i> Registered: <?php echo date('F d, Y', strtotime($teacher['created_at'])); ?>
                        </span>
                        <span class="profile-meta-item">
                            <i class="fas fa-clock"></i> Active: <?php echo $days_active; ?> days
                        </span>
                    </div>

                    <div>
                        <span class="profile-badge">
                            <i class="fas fa-chalkboard-teacher"></i> Teacher
                        </span>
                    </div>

                    <div class="action-buttons">
                        <a href="edit_teacher.php?id=<?php echo $teacher_id; ?>" class="btn-edit">
                            <i class="fas fa-edit"></i> Edit Teacher
                        </a>
                        <a href="?delete=<?php echo $teacher_id; ?>" class="btn-delete" onclick="return confirm('Are you sure you want to delete this teacher? This will also remove them as adviser from sections.')">
                            <i class="fas fa-trash"></i> Delete
                        </a>
                    </div>
                </div>
            </div>

            <!-- Statistics Cards -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-layer-group"></i>
                    </div>
                    <div class="stat-content">
                        <div class="stat-number"><?php echo $total_sections; ?></div>
                        <div class="stat-label">Advisory Sections</div>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-user-graduate"></i>
                    </div>
                    <div class="stat-content">
                        <div class="stat-number"><?php echo $total_students; ?></div>
                        <div class="stat-label">Students Under Advisory</div>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-book"></i>
                    </div>
                    <div class="stat-content">
                        <div class="stat-number"><?php echo $subjects->num_rows; ?></div>
                        <div class="stat-label">Subjects Available</div>
                    </div>
                </div>
            </div>

            <!-- Advisory Sections -->
            <div class="detail-card">
                <div class="card-header">
                    <h3><i class="fas fa-layer-group"></i> Advisory Sections</h3>
                    <a href="sections.php?adviser=<?php echo $teacher_id; ?>" class="view-link">
                        View All <i class="fas fa-arrow-right"></i>
                    </a>
                </div>

                <?php if($sections && $sections->num_rows > 0): ?>
                    <div class="sections-grid">
                        <?php while($section = $sections->fetch_assoc()): ?>
                            <div class="section-card">
                                <h4>
                                    <i class="fas fa-users"></i>
                                    <?php echo htmlspecialchars($section['section_name']); ?>
                                </h4>
                                <div class="section-details">
                                    <span><i class="fas fa-layer-group"></i> <?php echo htmlspecialchars($section['grade_name']); ?></span>
                                </div>
                                <div class="section-stats">
                                    <div class="section-stat">
                                        <div class="value"><?php echo $section['student_count']; ?></div>
                                        <div class="label">Students</div>
                                    </div>
                                </div>
                                <a href="view_section.php?id=<?php echo $section['id']; ?>" class="view-link">
                                    View Section <i class="fas fa-arrow-right"></i>
                                </a>
                            </div>
                        <?php endwhile; ?>
                    </div>
                <?php else: ?>
                    <div class="no-data">
                        <i class="fas fa-layer-group"></i>
                        <h3>No Advisory Sections</h3>
                        <p>This teacher is not assigned as adviser to any section.</p>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Subjects -->
            <div class="detail-card">
                <div class="card-header">
                    <h3><i class="fas fa-book"></i> Subjects</h3>
                </div>

                <?php if($subjects && $subjects->num_rows > 0): ?>
                    <div class="subjects-list">
                        <?php while($subject = $subjects->fetch_assoc()): ?>
                            <span class="subject-tag">
                                <i class="fas fa-book-open"></i>
                                <?php echo htmlspecialchars($subject['subject_name']); ?>
                                <span style="color: var(--text-secondary); font-size: 11px;">(<?php echo $subject['grade_name']; ?>)</span>
                            </span>
                        <?php endwhile; ?>
                    </div>
                <?php else: ?>
                    <div class="no-data" style="padding: 20px;">
                        <i class="fas fa-book"></i>
                        <p>No subjects available.</p>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Recent Activities -->
            <div class="detail-card">
                <div class="card-header">
                    <h3><i class="fas fa-history"></i> Recent Attendance Activities</h3>
                </div>

                <?php if($activities && $activities->num_rows > 0): ?>
                    <div class="table-container">
                        <table class="activities-table">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Student</th>
                                    <th>Subject</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $count = 0;
                                while($row = $activities->fetch_assoc()): 
                                    if($count++ >= 5) break;
                                ?>
                                    <tr>
                                        <td><?php echo date('M d, Y', strtotime($row['date'])); ?></td>
                                        <td><?php echo htmlspecialchars($row['student_name']); ?></td>
                                        <td><?php echo htmlspecialchars($row['subject_name']); ?></td>
                                        <td>
                                            <span class="badge badge-<?php echo strtolower($row['status']); ?>">
                                                <?php echo $row['status']; ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="no-data" style="padding: 20px;">
                        <i class="fas fa-calendar-times"></i>
                        <p>No recent activities found.</p>
                    </div>
                <?php endif; ?>
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