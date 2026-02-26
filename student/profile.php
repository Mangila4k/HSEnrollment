<?php
session_start();
include("../config/database.php");

// Check if user is student
if(!isset($_SESSION['user']) || $_SESSION['user']['role'] != 'Student'){
    header("Location: ../auth/login.php");
    exit();
}

$student_id = $_SESSION['user']['id'];
$student_name = $_SESSION['user']['fullname'];
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

// Get student details
$query = "SELECT * FROM users WHERE id = ? AND role = 'Student'";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $student_id);
$stmt->execute();
$result = $stmt->get_result();
$student = $result->fetch_assoc();
$stmt->close();

// Get student's enrollment information
$enrollment_query = "
    SELECT e.*, g.grade_name
    FROM enrollments e
    JOIN grade_levels g ON e.grade_id = g.id
    WHERE e.student_id = ? AND e.status = 'Enrolled'
    ORDER BY e.created_at DESC LIMIT 1
";
$stmt = $conn->prepare($enrollment_query);
$stmt->bind_param("i", $student_id);
$stmt->execute();
$enrollment = $stmt->get_result()->fetch_assoc();
$stmt->close();

$grade_name = $enrollment ? $enrollment['grade_name'] : 'Not Enrolled';
$strand = $enrollment ? ($enrollment['strand'] ?? 'N/A') : 'N/A';
$school_year = $enrollment ? $enrollment['school_year'] : 'N/A';
$enrollment_status = $enrollment ? $enrollment['status'] : 'Not Enrolled';
$enrollment_date = $enrollment ? $enrollment['created_at'] : null;

// Get attendance statistics
$attendance_stats = [
    'present' => 0,
    'absent' => 0,
    'late' => 0,
    'total' => 0
];

$attendance_query = "
    SELECT status, COUNT(*) as count
    FROM attendance
    WHERE student_id = ?
    GROUP BY status
";
$stmt = $conn->prepare($attendance_query);
$stmt->bind_param("i", $student_id);
$stmt->execute();
$attendance_result = $stmt->get_result();
while($row = $attendance_result->fetch_assoc()) {
    $attendance_stats[strtolower($row['status'])] = $row['count'];
    $attendance_stats['total'] += $row['count'];
}
$stmt->close();

$attendance_rate = $attendance_stats['total'] > 0 
    ? round(($attendance_stats['present'] / $attendance_stats['total']) * 100, 2) 
    : 0;

// Get enrolled subjects count
$subjects_count = 0;
if($enrollment && isset($enrollment['grade_id'])) {
    $subjects_query = "SELECT COUNT(*) as count FROM subjects WHERE grade_id = ?";
    $stmt = $conn->prepare($subjects_query);
    $stmt->bind_param("i", $enrollment['grade_id']);
    $stmt->execute();
    $subjects_result = $stmt->get_result();
    $subjects_count = $subjects_result->fetch_assoc()['count'];
    $stmt->close();
}

$account_created = $student['created_at'];
$days_active = floor((time() - strtotime($account_created)) / (60 * 60 * 24));
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile - Student Dashboard</title>
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

        .student-info {
            text-align: center;
            padding: 20px 0;
            border-bottom: 1px solid rgba(255, 255, 255, 0.2);
            margin-bottom: 20px;
        }

        .student-avatar {
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

        .student-info h3 {
            font-size: 18px;
            margin-bottom: 5px;
            color: #FFD700;
        }

        .student-info p {
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

        /* Profile Grid */
        .profile-grid {
            display: grid;
            grid-template-columns: 1fr 2fr;
            gap: 25px;
        }

        /* Profile Card */
        .profile-card {
            background: white;
            border-radius: 20px;
            padding: 30px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            text-align: center;
        }

        .profile-avatar-large {
            width: 120px;
            height: 120px;
            background: linear-gradient(135deg, #0B4F2E, #1a7a42);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            font-size: 48px;
            font-weight: bold;
            color: white;
            border: 4px solid #FFD700;
        }

        .profile-name {
            font-size: 22px;
            font-weight: 700;
            color: var(--text-primary);
            margin-bottom: 5px;
        }

        .profile-role {
            display: inline-block;
            padding: 5px 15px;
            background: linear-gradient(135deg, #fa709a 0%, #fee140 100%);
            color: white;
            border-radius: 20px;
            font-size: 14px;
            font-weight: 600;
            margin-bottom: 20px;
        }

        .profile-stats {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
            margin: 25px 0;
            padding: 20px 0;
            border-top: 1px solid var(--border-color);
            border-bottom: 1px solid var(--border-color);
        }

        .stat-item {
            text-align: center;
        }

        .stat-value {
            font-size: 24px;
            font-weight: 700;
            color: #0B4F2E;
        }

        .stat-label {
            font-size: 12px;
            color: var(--text-secondary);
            margin-top: 5px;
        }

        .profile-info-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 0;
            border-bottom: 1px solid var(--border-color);
            text-align: left;
        }

        .profile-info-item:last-child {
            border-bottom: none;
        }

        .info-icon {
            width: 40px;
            height: 40px;
            background: rgba(11, 79, 46, 0.1);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #0B4F2E;
            font-size: 18px;
        }

        .info-content {
            flex: 1;
        }

        .info-label {
            font-size: 12px;
            color: var(--text-secondary);
            margin-bottom: 3px;
        }

        .info-value {
            font-size: 15px;
            font-weight: 500;
            color: var(--text-primary);
        }

        /* Academic Card */
        .academic-card {
            background: white;
            border-radius: 20px;
            padding: 30px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            margin-bottom: 25px;
        }

        .academic-card h3 {
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

        .academic-card h3 i {
            color: #0B4F2E;
        }

        .academic-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
            margin-bottom: 20px;
        }

        .academic-item {
            text-align: center;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 12px;
        }

        .academic-value {
            font-size: 20px;
            font-weight: 700;
            color: #0B4F2E;
            margin-bottom: 5px;
        }

        .academic-label {
            font-size: 12px;
            color: var(--text-secondary);
        }

        .enrollment-badge {
            display: inline-block;
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: 600;
            margin-top: 10px;
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

        .badge-not-enrolled {
            background: rgba(108, 117, 125, 0.1);
            color: #6c757d;
        }

        /* Attendance Stats */
        .attendance-stats {
            display: flex;
            justify-content: space-around;
            margin: 20px 0;
        }

        .attendance-stat {
            text-align: center;
        }

        .attendance-stat .value {
            font-size: 28px;
            font-weight: 700;
        }

        .attendance-stat .label {
            font-size: 12px;
            color: var(--text-secondary);
            margin-top: 5px;
        }

        .attendance-stat.present .value {
            color: #28a745;
        }

        .attendance-stat.absent .value {
            color: #dc3545;
        }

        .attendance-stat.late .value {
            color: #ffc107;
        }

        .attendance-rate {
            margin-top: 15px;
            padding-top: 15px;
            border-top: 1px solid var(--border-color);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .rate-label {
            font-size: 14px;
            color: var(--text-secondary);
        }

        .rate-value {
            font-size: 20px;
            font-weight: 700;
            color: #0B4F2E;
        }

        /* Action Buttons */
        .action-buttons {
            display: flex;
            gap: 15px;
            margin-top: 25px;
        }

        .btn-edit {
            flex: 1;
            background: #0B4F2E;
            color: white;
            padding: 14px;
            border: none;
            border-radius: 12px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
            text-align: center;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }

        .btn-edit:hover {
            background: #1a7a42;
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(11, 79, 46, 0.3);
        }

        .btn-settings {
            flex: 1;
            background: white;
            color: var(--text-primary);
            padding: 14px;
            border: 2px solid var(--border-color);
            border-radius: 12px;
            font-size: 16px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
            text-align: center;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }

        .btn-settings:hover {
            border-color: #0B4F2E;
            color: #0B4F2E;
        }

        /* Info Box */
        .info-box {
            background: #e8f4fd;
            border-left: 4px solid #0B4F2E;
            padding: 15px 20px;
            border-radius: 10px;
            margin: 20px 0;
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .info-box i {
            font-size: 24px;
            color: #0B4F2E;
        }

        .info-box p {
            color: var(--text-primary);
            font-size: 14px;
            margin: 0;
        }

        .no-data {
            text-align: center;
            padding: 40px;
            color: var(--text-secondary);
        }

        .no-data i {
            font-size: 50px;
            margin-bottom: 15px;
            opacity: 0.3;
        }

        .no-data h3 {
            color: var(--text-primary);
            margin-bottom: 10px;
        }

        /* Responsive */
        @media (max-width: 1200px) {
            .profile-grid {
                grid-template-columns: 1fr;
            }
            
            .academic-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 768px) {
            .sidebar {
                width: 80px;
                padding: 20px 10px;
            }
            
            .sidebar h2 span,
            .student-info h3,
            .student-info p,
            .menu-section h3,
            .menu-items a span {
                display: none;
            }
            
            .student-avatar {
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
            
            .academic-grid {
                grid-template-columns: 1fr;
            }
            
            .attendance-stats {
                flex-direction: column;
                gap: 15px;
            }
            
            .action-buttons {
                flex-direction: column;
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
            
            <div class="student-info">
                <div class="student-avatar">
                    <?php echo strtoupper(substr($student_name, 0, 1)); ?>
                </div>
                <h3><?php echo htmlspecialchars(explode(' ', $student_name)[0]); ?></h3>
                <p><i class="fas fa-user-graduate"></i> Student</p>
            </div>
            
            <div class="menu-section">
                <h3>MAIN MENU</h3>
                <ul class="menu-items">
                    <li><a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> <span>Dashboard</span></a></li>
                    <li><a href="profile.php" class="active"><i class="fas fa-user"></i> <span>My Profile</span></a></li>
                    <li><a href="schedule.php"><i class="fas fa-calendar-alt"></i> <span>Class Schedule</span></a></li>
                    <li><a href="grades.php"><i class="fas fa-star"></i> <span>My Grades</span></a></li>
                    <li><a href="attendance.php"><i class="fas fa-calendar-check"></i> <span>Attendance</span></a></li>
                </ul>
            </div>

            <div class="menu-section">
                <h3>ACCOUNT</h3>
                <ul class="menu-items">
                    <li><a href="settings.php"><i class="fas fa-cog"></i> <span>Settings</span></a></li>
                    <li><a href="../auth/logout.php"><i class="fas fa-sign-out-alt"></i> <span>Logout</span></a></li>
                </ul>
            </div>
        </div>

        <!-- Main Content -->
        <div class="main-content">
            <!-- Header -->
            <div class="dashboard-header">
                <h1>My Profile</h1>
                <p>View your personal information and academic details</p>
            </div>

            <!-- Welcome Card -->
            <div class="welcome-card">
                <div class="welcome-text">
                    <h2>Welcome back, <?php echo htmlspecialchars(explode(' ', $student_name)[0]); ?>! 👋</h2>
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

            <!-- Profile Grid -->
            <div class="profile-grid">
                <!-- Left Column - Profile Info -->
                <div>
                    <div class="profile-card">
                        <div class="profile-avatar-large">
                            <?php echo strtoupper(substr($student['fullname'], 0, 1)); ?>
                        </div>
                        <h2 class="profile-name"><?php echo htmlspecialchars($student['fullname']); ?></h2>
                        <span class="profile-role">Student</span>

                        <div class="profile-stats">
                            <div class="stat-item">
                                <div class="stat-value"><?php echo $days_active; ?></div>
                                <div class="stat-label">Days Active</div>
                            </div>
                            <div class="stat-item">
                                <div class="stat-value"><?php echo $attendance_stats['total']; ?></div>
                                <div class="stat-label">Total Attendance</div>
                            </div>
                        </div>

                        <div class="profile-info-item">
                            <div class="info-icon">
                                <i class="fas fa-envelope"></i>
                            </div>
                            <div class="info-content">
                                <div class="info-label">Email Address</div>
                                <div class="info-value"><?php echo htmlspecialchars($student['email']); ?></div>
                            </div>
                        </div>

                        <div class="profile-info-item">
                            <div class="info-icon">
                                <i class="fas fa-id-card"></i>
                            </div>
                            <div class="info-content">
                                <div class="info-label">Student ID</div>
                                <div class="info-value"><?php echo $student['id_number'] ?? 'Not assigned'; ?></div>
                            </div>
                        </div>

                        <div class="profile-info-item">
                            <div class="info-icon">
                                <i class="fas fa-calendar-alt"></i>
                            </div>
                            <div class="info-content">
                                <div class="info-label">Member Since</div>
                                <div class="info-value"><?php echo date('F d, Y', strtotime($account_created)); ?></div>
                            </div>
                        </div>

                        <div class="action-buttons">
                            <a href="settings.php#profile" class="btn-edit">
                                <i class="fas fa-edit"></i> Edit Profile
                            </a>
                            <a href="settings.php" class="btn-settings">
                                <i class="fas fa-cog"></i> Settings
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Right Column - Academic Info -->
                <div>
                    <!-- Academic Information -->
                    <div class="academic-card">
                        <h3><i class="fas fa-graduation-cap"></i> Academic Information</h3>
                        
                        <?php if($enrollment): ?>
                            <div class="academic-grid">
                                <div class="academic-item">
                                    <div class="academic-value"><?php echo htmlspecialchars($grade_name); ?></div>
                                    <div class="academic-label">Grade Level</div>
                                </div>
                                <div class="academic-item">
                                    <div class="academic-value"><?php echo htmlspecialchars($strand); ?></div>
                                    <div class="academic-label">Strand</div>
                                </div>
                                <div class="academic-item">
                                    <div class="academic-value"><?php echo htmlspecialchars($school_year); ?></div>
                                    <div class="academic-label">School Year</div>
                                </div>
                            </div>

                            <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 10px;">
                                <span class="enrollment-badge badge-<?php echo strtolower($enrollment_status); ?>">
                                    <i class="fas fa-<?php echo $enrollment_status == 'Enrolled' ? 'check-circle' : 'clock'; ?>"></i>
                                    Status: <?php echo $enrollment_status; ?>
                                </span>
                                <?php if($enrollment_date): ?>
                                    <span style="color: var(--text-secondary); font-size: 13px;">
                                        <i class="far fa-calendar"></i> Enrolled: <?php echo date('M d, Y', strtotime($enrollment_date)); ?>
                                    </span>
                                <?php endif; ?>
                            </div>

                            <div class="info-box" style="margin-top: 20px;">
                                <i class="fas fa-book-open"></i>
                                <p>You are currently enrolled in <?php echo $subjects_count; ?> subjects for this grade level.</p>
                            </div>
                        <?php else: ?>
                            <div class="no-data">
                                <i class="fas fa-graduation-cap"></i>
                                <h3>Not Enrolled</h3>
                                <p>You are not currently enrolled in any grade level.</p>
                                <p style="font-size: 13px; margin-top: 10px;">Please contact the registrar's office for assistance.</p>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Attendance Summary -->
                    <div class="academic-card">
                        <h3><i class="fas fa-calendar-check"></i> Attendance Summary</h3>
                        
                        <?php if($attendance_stats['total'] > 0): ?>
                            <div class="attendance-stats">
                                <div class="attendance-stat present">
                                    <div class="value"><?php echo $attendance_stats['present']; ?></div>
                                    <div class="label">Present</div>
                                </div>
                                <div class="attendance-stat absent">
                                    <div class="value"><?php echo $attendance_stats['absent']; ?></div>
                                    <div class="label">Absent</div>
                                </div>
                                <div class="attendance-stat late">
                                    <div class="value"><?php echo $attendance_stats['late']; ?></div>
                                    <div class="label">Late</div>
                                </div>
                            </div>

                            <div class="attendance-rate">
                                <span class="rate-label">Attendance Rate</span>
                                <span class="rate-value"><?php echo $attendance_rate; ?>%</span>
                            </div>

                            <div style="margin-top: 15px;">
                                <a href="attendance.php" style="color: #0B4F2E; text-decoration: none; font-size: 14px;">
                                    View detailed attendance <i class="fas fa-arrow-right"></i>
                                </a>
                            </div>
                        <?php else: ?>
                            <div class="no-data" style="padding: 20px;">
                                <i class="fas fa-calendar-times"></i>
                                <p>No attendance records found.</p>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Quick Links -->
                    <div class="academic-card">
                        <h3><i class="fas fa-link"></i> Quick Links</h3>
                        
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                            <a href="schedule.php" style="text-decoration: none; color: var(--text-primary); background: #f8f9fa; padding: 15px; border-radius: 12px; text-align: center; transition: all 0.3s;">
                                <i class="fas fa-calendar-alt" style="font-size: 24px; color: #0B4F2E; margin-bottom: 8px; display: block;"></i>
                                <span style="font-size: 14px; font-weight: 500;">Class Schedule</span>
                            </a>
                            <a href="grades.php" style="text-decoration: none; color: var(--text-primary); background: #f8f9fa; padding: 15px; border-radius: 12px; text-align: center; transition: all 0.3s;">
                                <i class="fas fa-star" style="font-size: 24px; color: #0B4F2E; margin-bottom: 8px; display: block;"></i>
                                <span style="font-size: 14px; font-weight: 500;">My Grades</span>
                            </a>
                        </div>
                    </div>
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
    <?php include('../includes/chatbot_widget.php'); ?>
</body>
</html>