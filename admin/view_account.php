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
    header("Location: manage_accounts.php");
    exit();
}

$account_id = $_GET['id'];

// Get account details
$query = "SELECT * FROM users WHERE id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $account_id);
$stmt->execute();
$result = $stmt->get_result();

if($result->num_rows === 0) {
    header("Location: manage_accounts.php");
    exit();
}

$account = $result->fetch_assoc();
$stmt->close();

// Get account statistics based on role
$stats = [];

if($account['role'] == 'Student') {
    // Get enrollment count
    $enrollment_count = $conn->query("SELECT COUNT(*) as count FROM enrollments WHERE student_id = '$account_id'")->fetch_assoc()['count'];
    
    // Get current enrollment
    $current_enrollment = $conn->query("
        SELECT e.*, g.grade_name 
        FROM enrollments e 
        LEFT JOIN grade_levels g ON e.grade_id = g.id 
        WHERE e.student_id = '$account_id' AND e.status = 'Enrolled' 
        ORDER BY e.created_at DESC LIMIT 1
    ")->fetch_assoc();
    
    // Get attendance count
    $attendance_count = $conn->query("SELECT COUNT(*) as count FROM attendance WHERE student_id = '$account_id'")->fetch_assoc()['count'];
    
    $stats = [
        'enrollments' => $enrollment_count,
        'current_enrollment' => $current_enrollment,
        'attendance' => $attendance_count
    ];
}
elseif($account['role'] == 'Teacher') {
    // Get sections advised
    $sections_advised = $conn->query("SELECT COUNT(*) as count FROM sections WHERE adviser_id = '$account_id'")->fetch_assoc()['count'];
    
    // Get sections list
    $sections = $conn->query("
        SELECT s.*, g.grade_name 
        FROM sections s 
        LEFT JOIN grade_levels g ON s.grade_id = g.id 
        WHERE s.adviser_id = '$account_id'
        ORDER BY g.id, s.section_name
    ");
    
    $stats = [
        'sections_count' => $sections_advised,
        'sections' => $sections
    ];
}
elseif($account['role'] == 'Registrar') {
    // Get processed enrollments
    // This is a placeholder - you might want to track which registrar processed which enrollment
    $processed_count = $conn->query("SELECT COUNT(*) as count FROM enrollments")->fetch_assoc()['count'];
    
    $stats = [
        'processed' => $processed_count
    ];
}
elseif($account['role'] == 'Admin') {
    // Get system stats for admin
    $total_users = $conn->query("SELECT COUNT(*) as count FROM users")->fetch_assoc()['count'];
    $total_enrollments = $conn->query("SELECT COUNT(*) as count FROM enrollments")->fetch_assoc()['count'];
    
    $stats = [
        'total_users' => $total_users,
        'total_enrollments' => $total_enrollments
    ];
}

// Get account activity (recent logins - placeholder)
$last_login = $account['created_at']; // Using created_at as placeholder for last login

$account_created = $account['created_at'];
$days_active = floor((time() - strtotime($account_created)) / (60 * 60 * 24));
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Account - Admin Dashboard</title>
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

        /* Account Profile Card */
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
            font-size: 32px;
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

        .role-badge {
            display: inline-block;
            padding: 8px 20px;
            border-radius: 30px;
            font-size: 14px;
            font-weight: 600;
            color: white;
        }

        .role-admin {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
        }

        .role-registrar {
            background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
        }

        .role-teacher {
            background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);
        }

        .role-student {
            background: linear-gradient(135deg, #fa709a 0%, #fee140 100%);
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
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
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

        /* Sections Grid */
        .sections-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 15px;
        }

        .section-card {
            background: #f8f9fa;
            border-radius: 12px;
            padding: 15px;
            border-left: 4px solid #0B4F2E;
        }

        .section-card h4 {
            color: var(--text-primary);
            font-size: 16px;
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .section-card h4 i {
            color: #FFD700;
        }

        .section-card p {
            color: var(--text-secondary);
            font-size: 13px;
            display: flex;
            align-items: center;
            gap: 5px;
            margin-bottom: 5px;
        }

        .section-card p i {
            color: #0B4F2E;
            width: 16px;
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

        /* Activity Timeline */
        .timeline {
            list-style: none;
            padding: 0;
        }

        .timeline-item {
            display: flex;
            gap: 15px;
            padding: 15px 0;
            border-bottom: 1px solid var(--border-color);
        }

        .timeline-item:last-child {
            border-bottom: none;
        }

        .timeline-icon {
            width: 40px;
            height: 40px;
            background: rgba(11, 79, 46, 0.1);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #0B4F2E;
        }

        .timeline-content {
            flex: 1;
        }

        .timeline-title {
            color: var(--text-primary);
            font-weight: 500;
            margin-bottom: 5px;
        }

        .timeline-time {
            color: var(--text-secondary);
            font-size: 12px;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        /* Responsive */
        @media (max-width: 1200px) {
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
                    <li><a href="subjects.php"><i class="fas fa-book"></i> <span>Subjects</span></a></li>
                    <li><a href="enrollments.php"><i class="fas fa-file-signature"></i> <span>Enrollments</span></a></li>
                </ul>
            </div>

            <div class="menu-section">
                <h3>MANAGEMENT</h3>
                <ul class="menu-items">
                    <li><a href="manage_accounts.php" class="active"><i class="fas fa-users-cog"></i> <span>Accounts</span></a></li>
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
                    <h1>Account Details</h1>
                    <p>View complete account information</p>
                </div>
                <a href="manage_accounts.php" class="back-btn">
                    <i class="fas fa-arrow-left"></i> Back to Accounts
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

            <!-- Account Profile Card -->
            <div class="profile-card">
                <div class="profile-avatar-large">
                    <?php echo strtoupper(substr($account['fullname'], 0, 1)); ?>
                </div>
                <div class="profile-info">
                    <h2><?php echo htmlspecialchars($account['fullname']); ?></h2>
                    
                    <div class="profile-meta">
                        <span class="profile-meta-item">
                            <i class="fas fa-envelope"></i> <?php echo htmlspecialchars($account['email']); ?>
                        </span>
                        <span class="profile-meta-item">
                            <i class="fas fa-id-card"></i> ID: <?php echo $account['id_number'] ?? 'Not assigned'; ?>
                        </span>
                        <span class="profile-meta-item">
                            <i class="fas fa-calendar-alt"></i> Registered: <?php echo date('F d, Y', strtotime($account['created_at'])); ?>
                        </span>
                        <span class="profile-meta-item">
                            <i class="fas fa-clock"></i> Active: <?php echo $days_active; ?> days
                        </span>
                    </div>

                    <div>
                        <span class="role-badge role-<?php echo strtolower($account['role']); ?>">
                            <i class="fas fa-<?php 
                                echo $account['role'] == 'Admin' ? 'user-shield' : 
                                    ($account['role'] == 'Registrar' ? 'user-tie' : 
                                    ($account['role'] == 'Teacher' ? 'chalkboard-teacher' : 'user-graduate')); 
                            ?>"></i>
                            <?php echo $account['role']; ?>
                        </span>
                    </div>

                    <div class="action-buttons">
                        <a href="edit_account.php?id=<?php echo $account_id; ?>" class="btn-edit">
                            <i class="fas fa-edit"></i> Edit Account
                        </a>
                        <?php if($account['id'] != $_SESSION['user']['id']): ?>
                            <a href="?delete=<?php echo $account['id']; ?>" class="btn-delete" onclick="return confirm('Are you sure you want to delete this account? This action cannot be undone.')">
                                <i class="fas fa-trash"></i> Delete Account
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Statistics Cards (Role-specific) -->
            <div class="stats-grid">
                <?php if($account['role'] == 'Student'): ?>
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-file-signature"></i>
                        </div>
                        <div class="stat-content">
                            <div class="stat-number"><?php echo $stats['enrollments']; ?></div>
                            <div class="stat-label">Total Enrollments</div>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-calendar-check"></i>
                        </div>
                        <div class="stat-content">
                            <div class="stat-number"><?php echo $stats['attendance']; ?></div>
                            <div class="stat-label">Attendance Records</div>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-layer-group"></i>
                        </div>
                        <div class="stat-content">
                            <div class="stat-number"><?php echo $stats['current_enrollment']['grade_name'] ?? 'N/A'; ?></div>
                            <div class="stat-label">Current Grade</div>
                        </div>
                    </div>
                <?php elseif($account['role'] == 'Teacher'): ?>
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-layer-group"></i>
                        </div>
                        <div class="stat-content">
                            <div class="stat-number"><?php echo $stats['sections_count']; ?></div>
                            <div class="stat-label">Advisory Sections</div>
                        </div>
                    </div>
                <?php elseif($account['role'] == 'Registrar'): ?>
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-file-signature"></i>
                        </div>
                        <div class="stat-content">
                            <div class="stat-number"><?php echo $stats['processed']; ?></div>
                            <div class="stat-label">Enrollments Processed</div>
                        </div>
                    </div>
                <?php elseif($account['role'] == 'Admin'): ?>
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-users"></i>
                        </div>
                        <div class="stat-content">
                            <div class="stat-number"><?php echo $stats['total_users']; ?></div>
                            <div class="stat-label">Total Users</div>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-file-signature"></i>
                        </div>
                        <div class="stat-content">
                            <div class="stat-number"><?php echo $stats['total_enrollments']; ?></div>
                            <div class="stat-label">Total Enrollments</div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Account Information -->
            <div class="detail-card">
                <div class="card-header">
                    <h3><i class="fas fa-info-circle"></i> Account Information</h3>
                </div>

                <div class="info-grid">
                    <div class="info-item">
                        <div class="info-label">Account ID</div>
                        <div class="info-value">
                            <i class="fas fa-hashtag"></i>
                            <?php echo $account['id']; ?>
                        </div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Full Name</div>
                        <div class="info-value">
                            <i class="fas fa-user"></i>
                            <?php echo htmlspecialchars($account['fullname']); ?>
                        </div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Email Address</div>
                        <div class="info-value">
                            <i class="fas fa-envelope"></i>
                            <?php echo htmlspecialchars($account['email']); ?>
                        </div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">ID Number</div>
                        <div class="info-value">
                            <i class="fas fa-id-card"></i>
                            <?php echo $account['id_number'] ?? 'Not assigned'; ?>
                        </div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Role</div>
                        <div class="info-value">
                            <i class="fas fa-user-tag"></i>
                            <?php echo $account['role']; ?>
                        </div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Account Created</div>
                        <div class="info-value">
                            <i class="fas fa-calendar-alt"></i>
                            <?php echo date('F d, Y h:i A', strtotime($account['created_at'])); ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Role-specific Details -->
            <?php if($account['role'] == 'Student' && isset($stats['current_enrollment']) && $stats['current_enrollment']): ?>
            <div class="detail-card">
                <div class="card-header">
                    <h3><i class="fas fa-graduation-cap"></i> Current Enrollment</h3>
                    <a href="view_enrollment.php?id=<?php echo $stats['current_enrollment']['id']; ?>" class="view-link">
                        View Details <i class="fas fa-arrow-right"></i>
                    </a>
                </div>

                <div class="info-grid">
                    <div class="info-item">
                        <div class="info-label">Grade Level</div>
                        <div class="info-value">
                            <i class="fas fa-layer-group"></i>
                            <?php echo htmlspecialchars($stats['current_enrollment']['grade_name']); ?>
                        </div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Strand</div>
                        <div class="info-value">
                            <i class="fas fa-tag"></i>
                            <?php echo $stats['current_enrollment']['strand'] ?: 'Not Applicable'; ?>
                        </div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">School Year</div>
                        <div class="info-value">
                            <i class="fas fa-calendar-alt"></i>
                            <?php echo htmlspecialchars($stats['current_enrollment']['school_year']); ?>
                        </div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Status</div>
                        <div class="info-value">
                            <i class="fas fa-check-circle" style="color: #28a745;"></i>
                            <?php echo $stats['current_enrollment']['status']; ?>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <?php if($account['role'] == 'Teacher' && isset($stats['sections']) && $stats['sections']->num_rows > 0): ?>
            <div class="detail-card">
                <div class="card-header">
                    <h3><i class="fas fa-layer-group"></i> Advisory Sections</h3>
                </div>

                <div class="sections-grid">
                    <?php while($section = $stats['sections']->fetch_assoc()): ?>
                        <div class="section-card">
                            <h4>
                                <i class="fas fa-users"></i>
                                <?php echo htmlspecialchars($section['section_name']); ?>
                            </h4>
                            <p>
                                <i class="fas fa-layer-group"></i>
                                <?php echo htmlspecialchars($section['grade_name']); ?>
                            </p>
                            <a href="view_section.php?id=<?php echo $section['id']; ?>" class="view-link">
                                View Section <i class="fas fa-arrow-right"></i>
                            </a>
                        </div>
                    <?php endwhile; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- Recent Activity (Placeholder) -->
            <div class="detail-card">
                <div class="card-header">
                    <h3><i class="fas fa-history"></i> Recent Activity</h3>
                </div>

                <ul class="timeline">
                    <li class="timeline-item">
                        <div class="timeline-icon">
                            <i class="fas fa-user-plus"></i>
                        </div>
                        <div class="timeline-content">
                            <div class="timeline-title">Account Created</div>
                            <div class="timeline-time">
                                <i class="far fa-clock"></i>
                                <?php echo date('F d, Y h:i A', strtotime($account['created_at'])); ?>
                            </div>
                        </div>
                    </li>
                    <li class="timeline-item">
                        <div class="timeline-icon">
                            <i class="fas fa-clock"></i>
                        </div>
                        <div class="timeline-content">
                            <div class="timeline-title">Last Login (estimated)</div>
                            <div class="timeline-time">
                                <i class="far fa-clock"></i>
                                <?php echo date('F d, Y h:i A', strtotime($last_login)); ?>
                            </div>
                        </div>
                    </li>
                </ul>
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