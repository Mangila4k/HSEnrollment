<?php
session_start();
include("../config/database.php");

// Check if user is admin
if(!isset($_SESSION['user']) || $_SESSION['user']['role'] != 'Admin'){
    header("Location: ../auth/login.php");
    exit();
}

$admin_name = $_SESSION['user']['fullname'];

// Count total students
$student_count = $conn->query("SELECT * FROM users WHERE role='Student'")->num_rows;
$teacher_count = $conn->query("SELECT * FROM users WHERE role='Teacher'")->num_rows;
$section_count = $conn->query("SELECT * FROM sections")->num_rows;
$subject_count = $conn->query("SELECT * FROM subjects")->num_rows;

// Get additional stats
$enrollment_count = $conn->query("SELECT COUNT(*) as count FROM enrollments")->fetch_assoc()['count'];
$pending_count = $conn->query("SELECT COUNT(*) as count FROM enrollments WHERE status='Pending'")->fetch_assoc()['count'];
$enrolled_count = $conn->query("SELECT COUNT(*) as count FROM enrollments WHERE status='Enrolled'")->fetch_assoc()['count'];
$rejected_count = $conn->query("SELECT COUNT(*) as count FROM enrollments WHERE status='Rejected'")->fetch_assoc()['count'];

// Get recent enrollments
$recent_enrollments = $conn->query("
    SELECT e.*, u.fullname, g.grade_name 
    FROM enrollments e 
    JOIN users u ON e.student_id = u.id 
    JOIN grade_levels g ON e.grade_id = g.id 
    ORDER BY e.created_at DESC 
    LIMIT 5
");

// Get recent activities (combined) - FIXED: Added table aliases for created_at
$recent_activities = $conn->query("
    (SELECT 'enrollment' as type, e.created_at, CONCAT(u.fullname, ' enrolled in ', g.grade_name) as description
     FROM enrollments e
     JOIN users u ON e.student_id = u.id
     JOIN grade_levels g ON e.grade_id = g.id
     ORDER BY e.created_at DESC LIMIT 3)
    UNION ALL
    (SELECT 'user' as type, u.created_at, CONCAT('New ', u.role, ' account created: ', u.fullname) as description
     FROM users u
     WHERE u.created_at > DATE_SUB(NOW(), INTERVAL 7 DAY)
     ORDER BY u.created_at DESC LIMIT 3)
    ORDER BY created_at DESC LIMIT 5
");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - High School Enrollment System</title>
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

        /* Stats Row 2 */
        .stats-row-2 {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 25px;
            margin-bottom: 30px;
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
            padding: 20px;
            text-decoration: none;
            color: var(--text-primary);
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .action-btn:hover {
            border-color: #0B4F2E;
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(11, 79, 46, 0.1);
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
            font-size: 20px;
        }

        .action-content h4 {
            font-size: 16px;
            margin-bottom: 5px;
            font-weight: 600;
        }

        .action-content p {
            color: var(--text-secondary);
            font-size: 13px;
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

        /* Activity List */
        .activity-list {
            list-style: none;
        }

        .activity-item {
            display: flex;
            align-items: flex-start;
            gap: 15px;
            padding: 15px 0;
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
            font-weight: 500;
            margin-bottom: 5px;
            font-size: 14px;
        }

        .activity-time {
            color: var(--text-secondary);
            font-size: 12px;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        /* Status Badges */
        .status-badge {
            padding: 3px 10px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
            display: inline-block;
        }

        .status-enrolled {
            background: rgba(40, 167, 69, 0.1);
            color: #28a745;
        }

        .status-pending {
            background: rgba(255, 193, 7, 0.1);
            color: #ffc107;
        }

        .status-rejected {
            background: rgba(220, 53, 69, 0.1);
            color: #dc3545;
        }

        /* Enrollment Item */
        .enrollment-item {
            display: flex;
            align-items: center;
            gap: 15px;
            padding: 12px 0;
            border-bottom: 1px solid var(--border-color);
        }

        .enrollment-item:last-child {
            border-bottom: none;
        }

        .enrollment-avatar {
            width: 40px;
            height: 40px;
            background: linear-gradient(135deg, #0B4F2E, #1a7a42);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
        }

        .enrollment-info {
            flex: 1;
        }

        .enrollment-info h4 {
            font-size: 15px;
            margin-bottom: 3px;
            color: var(--text-primary);
        }

        .enrollment-info p {
            font-size: 13px;
            color: var(--text-secondary);
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
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

        /* System Info */
        .system-info {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
        }

        .info-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
        }

        .info-item {
            text-align: center;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 12px;
        }

        .info-item strong {
            display: block;
            color: var(--text-secondary);
            font-size: 13px;
            font-weight: 500;
            margin-bottom: 8px;
        }

        .info-item .info-value {
            font-size: 20px;
            font-weight: 700;
            color: #0B4F2E;
        }

        /* Responsive */
        @media (max-width: 1200px) {
            .stats-container,
            .stats-row-2,
            .dashboard-grid {
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
            
            .welcome-card {
                flex-direction: column;
                text-align: center;
                gap: 20px;
            }
            
            .stats-container,
            .stats-row-2,
            .dashboard-grid,
            .info-grid {
                grid-template-columns: 1fr;
            }
            
            .action-buttons {
                grid-template-columns: 1fr;
            }
            
            .enrollment-info p {
                flex-direction: column;
                align-items: flex-start;
                gap: 5px;
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
                    <li><a href="dashboard.php" class="active"><i class="fas fa-tachometer-alt"></i> <span>Dashboard</span></a></li>
                    <li><a href="students.php"><i class="fas fa-user-graduate"></i> <span>Students</span></a></li>
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
                <h1>Dashboard</h1>
                <p>Welcome to your admin dashboard. Manage your school system efficiently.</p>
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

            <!-- Statistics Cards -->
            <div class="stats-container">
                <div class="stat-card">
                    <div class="stat-header">
                        <h3>Total Students</h3>
                        <div class="stat-icon">
                            <i class="fas fa-user-graduate"></i>
                        </div>
                    </div>
                    <div class="stat-number"><?php echo $student_count; ?></div>
                    <div class="stat-label">Enrolled students</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-header">
                        <h3>Total Teachers</h3>
                        <div class="stat-icon">
                            <i class="fas fa-chalkboard-teacher"></i>
                        </div>
                    </div>
                    <div class="stat-number"><?php echo $teacher_count; ?></div>
                    <div class="stat-label">Faculty members</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-header">
                        <h3>Total Sections</h3>
                        <div class="stat-icon">
                            <i class="fas fa-layer-group"></i>
                        </div>
                    </div>
                    <div class="stat-number"><?php echo $section_count; ?></div>
                    <div class="stat-label">Active sections</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-header">
                        <h3>Total Subjects</h3>
                        <div class="stat-icon">
                            <i class="fas fa-book"></i>
                        </div>
                    </div>
                    <div class="stat-number"><?php echo $subject_count; ?></div>
                    <div class="stat-label">Offered subjects</div>
                </div>
            </div>

            <!-- Second Row Stats -->
            <div class="stats-row-2">
                <div class="stat-card">
                    <div class="stat-header">
                        <h3>Total Enrollments</h3>
                        <div class="stat-icon">
                            <i class="fas fa-file-signature"></i>
                        </div>
                    </div>
                    <div class="stat-number"><?php echo $enrollment_count; ?></div>
                    <div class="stat-label">All time</div>
                </div>

                <div class="stat-card">
                    <div class="stat-header">
                        <h3>Enrolled</h3>
                        <div class="stat-icon">
                            <i class="fas fa-check-circle"></i>
                        </div>
                    </div>
                    <div class="stat-number"><?php echo $enrolled_count; ?></div>
                    <div class="stat-label">Approved enrollments</div>
                </div>

                <div class="stat-card">
                    <div class="stat-header">
                        <h3>Pending</h3>
                        <div class="stat-icon">
                            <i class="fas fa-clock"></i>
                        </div>
                    </div>
                    <div class="stat-number"><?php echo $pending_count; ?></div>
                    <div class="stat-label">Awaiting approval</div>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="quick-actions">
                <h3><i class="fas fa-bolt"></i> Quick Actions</h3>
                <div class="action-buttons">
                    <a href="add_account.php" class="action-btn">
                        <div class="action-icon">
                            <i class="fas fa-user-plus"></i>
                        </div>
                        <div class="action-content">
                            <h4>Add New Account</h4>
                            <p>Create admin, teacher, or student account</p>
                        </div>
                    </a>
                    
                    <a href="add_student.php" class="action-btn">
                        <div class="action-icon">
                            <i class="fas fa-user-graduate"></i>
                        </div>
                        <div class="action-content">
                            <h4>Add New Student</h4>
                            <p>Enroll a new student</p>
                        </div>
                    </a>
                    
                    <a href="create_section.php" class="action-btn">
                        <div class="action-icon">
                            <i class="fas fa-plus-circle"></i>
                        </div>
                        <div class="action-content">
                            <h4>Create Section</h4>
                            <p>Add a new class section</p>
                        </div>
                    </a>
                    
                    <a href="add_subject.php" class="action-btn">
                        <div class="action-icon">
                            <i class="fas fa-book-medical"></i>
                        </div>
                        <div class="action-content">
                            <h4>Add Subject</h4>
                            <p>Create a new subject</p>
                        </div>
                    </a>
                </div>
            </div>

            <!-- Dashboard Grid -->
            <div class="dashboard-grid">
                <!-- Recent Enrollments -->
                <div class="info-card">
                    <h3><i class="fas fa-history"></i> Recent Enrollments</h3>
                    <?php if($recent_enrollments && $recent_enrollments->num_rows > 0): ?>
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
                                        <span class="status-badge status-<?php echo strtolower($enrollment['status']); ?>">
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
                        <a href="enrollments.php" class="view-all-link">
                            View All Enrollments <i class="fas fa-arrow-right"></i>
                        </a>
                    <?php else: ?>
                        <div style="text-align: center; padding: 30px; color: var(--text-secondary);">
                            <i class="fas fa-file-signature" style="font-size: 40px; opacity: 0.3; margin-bottom: 10px;"></i>
                            <p>No recent enrollments</p>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Recent Activities -->
                <div class="info-card">
                    <h3><i class="fas fa-bell"></i> Recent Activities</h3>
                    <?php if($recent_activities && $recent_activities->num_rows > 0): ?>
                        <ul class="activity-list">
                            <?php while($activity = $recent_activities->fetch_assoc()): ?>
                                <li class="activity-item">
                                    <div class="activity-icon">
                                        <i class="fas fa-<?php echo $activity['type'] == 'enrollment' ? 'user-graduate' : 'user-plus'; ?>"></i>
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
                                </li>
                            <?php endwhile; ?>
                        </ul>
                    <?php else: ?>
                        <div style="text-align: center; padding: 30px; color: var(--text-secondary);">
                            <i class="fas fa-bell-slash" style="font-size: 40px; opacity: 0.3; margin-bottom: 10px;"></i>
                            <p>No recent activities</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- System Information -->
            <div class="system-info">
                <h3 style="color: var(--text-primary); font-size: 18px; font-weight: 600; margin-bottom: 20px; display: flex; align-items: center; gap: 10px;">
                    <i class="fas fa-info-circle" style="color: #0B4F2E;"></i>
                    System Information
                </h3>
                <div class="info-grid">
                    <div class="info-item">
                        <strong>School Year</strong>
                        <div class="info-value">2026-2027</div>
                    </div>
                    <div class="info-item">
                        <strong>Total Enrollments</strong>
                        <div class="info-value"><?php echo $enrollment_count; ?></div>
                    </div>
                    <div class="info-item">
                        <strong>Pending Approvals</strong>
                        <div class="info-value"><?php echo $pending_count; ?></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Add active class to current nav link
        document.addEventListener('DOMContentLoaded', function() {
            const currentLocation = window.location.pathname;
            const navLinks = document.querySelectorAll('.menu-items a');
            
            navLinks.forEach(link => {
                if(link.getAttribute('href') === 'dashboard.php') {
                    link.classList.add('active');
                }
            });
        });

        // Auto-hide alerts after 5 seconds (if any alerts are added in the future)
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