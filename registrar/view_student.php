<?php
session_start();
include("../config/database.php");

// Check if user is registrar
if(!isset($_SESSION['user']) || $_SESSION['user']['role'] != 'Registrar'){
    header("Location: ../auth/login.php");
    exit();
}

$registrar_name = $_SESSION['user']['fullname'];

// Check if ID is provided
if(!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: students.php");
    exit();
}

$student_id = $_GET['id'];

// Get student details
$query = "SELECT * FROM users WHERE id = ? AND role = 'Student'";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $student_id);
$stmt->execute();
$result = $stmt->get_result();

if($result->num_rows === 0) {
    header("Location: students.php");
    exit();
}

$student = $result->fetch_assoc();
$stmt->close();

// Get student's enrollment history
$enrollments_query = "
    SELECT e.*, g.grade_name 
    FROM enrollments e 
    LEFT JOIN grade_levels g ON e.grade_id = g.id 
    WHERE e.student_id = ? 
    ORDER BY e.created_at DESC
";
$stmt = $conn->prepare($enrollments_query);
$stmt->bind_param("i", $student_id);
$stmt->execute();
$enrollments = $stmt->get_result();
$current_enrollment = $enrollments->fetch_assoc(); // First row is current enrollment
$stmt->close();

// Reset pointer for history table
$enrollments->data_seek(0);

// Get student's attendance records
$attendance_query = "
    SELECT a.*, sub.subject_name
    FROM attendance a
    LEFT JOIN subjects sub ON a.subject_id = sub.id
    WHERE a.student_id = ?
    ORDER BY a.date DESC
    LIMIT 10
";
$stmt = $conn->prepare($attendance_query);
$stmt->bind_param("i", $student_id);
$stmt->execute();
$attendance = $stmt->get_result();
$stmt->close();

// Calculate attendance statistics
$attendance_stats = [
    'present' => 0,
    'absent' => 0,
    'late' => 0,
    'total' => 0
];

$stats_query = "
    SELECT status, COUNT(*) as count
    FROM attendance
    WHERE student_id = ?
    GROUP BY status
";
$stmt = $conn->prepare($stats_query);
$stmt->bind_param("i", $student_id);
$stmt->execute();
$stats_result = $stmt->get_result();
while($row = $stats_result->fetch_assoc()) {
    $attendance_stats[strtolower($row['status'])] = $row['count'];
    $attendance_stats['total'] += $row['count'];
}
$stmt->close();

$attendance_rate = $attendance_stats['total'] > 0 
    ? round(($attendance_stats['present'] / $attendance_stats['total']) * 100, 2) 
    : 0;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Student - Registrar Dashboard</title>
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

        /* Student Profile Card */
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
            border-radius: 30px;
            font-size: 13px;
            font-weight: 600;
        }

        .badge-enrolled {
            background: rgba(40, 167, 69, 0.1);
            color: #28a745;
            border: 1px solid #28a745;
        }

        .badge-pending {
            background: rgba(255, 193, 7, 0.1);
            color: #ffc107;
            border: 1px solid #ffc107;
        }

        .badge-rejected {
            background: rgba(220, 53, 69, 0.1);
            color: #dc3545;
            border: 1px solid #dc3545;
        }

        .badge-none {
            background: rgba(108, 117, 125, 0.1);
            color: #6c757d;
            border: 1px solid #6c757d;
        }

        .action-buttons {
            display: flex;
            gap: 10px;
            margin-top: 15px;
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

        .btn-enroll {
            background: #ffc107;
            color: #2b2d42;
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

        .btn-enroll:hover {
            background: #e0a800;
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

        /* Stats Cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 15px;
            margin-bottom: 20px;
        }

        .stat-card {
            background: #f8f9fa;
            border-radius: 12px;
            padding: 15px;
            text-align: center;
        }

        .stat-number {
            font-size: 24px;
            font-weight: 700;
            color: #0B4F2E;
            margin-bottom: 5px;
        }

        .stat-label {
            font-size: 12px;
            color: var(--text-secondary);
        }

        .stat-card.present .stat-number { color: #28a745; }
        .stat-card.absent .stat-number { color: #dc3545; }
        .stat-card.late .stat-number { color: #ffc107; }

        /* Tables */
        .table-container {
            overflow-x: auto;
        }

        .enrollments-table,
        .attendance-table {
            width: 100%;
            border-collapse: collapse;
        }

        .enrollments-table th,
        .attendance-table th {
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

        .enrollments-table td,
        .attendance-table td {
            padding: 12px;
            border-bottom: 1px solid var(--border-color);
            color: var(--text-primary);
            font-size: 14px;
        }

        .enrollments-table tbody tr:hover,
        .attendance-table tbody tr:hover {
            background: var(--hover-color);
        }

        .badge {
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
            display: inline-block;
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

        .strand-tag {
            background: rgba(255, 215, 0, 0.1);
            color: #b8860b;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 11px;
            margin-left: 5px;
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
                    <li><a href="students.php" class="active"><i class="fas fa-user-graduate"></i> <span>Students</span></a></li>
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
                <div class="header-left">
                    <h1>Student Profile</h1>
                    <p>View complete student information and records</p>
                </div>
                <a href="students.php" class="back-btn">
                    <i class="fas fa-arrow-left"></i> Back to Students
                </a>
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
            <?php if(isset($_SESSION['success_message'])): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    <?php echo $_SESSION['success_message']; unset($_SESSION['success_message']); ?>
                </div>
            <?php endif; ?>

            <!-- Student Profile Card -->
            <div class="profile-card">
                <div class="profile-avatar-large">
                    <?php echo strtoupper(substr($student['fullname'], 0, 1)); ?>
                </div>
                <div class="profile-info">
                    <h2><?php echo htmlspecialchars($student['fullname']); ?></h2>
                    
                    <div class="profile-meta">
                        <span class="profile-meta-item">
                            <i class="fas fa-envelope"></i> <?php echo htmlspecialchars($student['email']); ?>
                        </span>
                        <span class="profile-meta-item">
                            <i class="fas fa-id-card"></i> ID: <?php echo $student['id_number'] ?? 'Not assigned'; ?>
                        </span>
                        <span class="profile-meta-item">
                            <i class="fas fa-calendar-alt"></i> Registered: <?php echo date('F d, Y', strtotime($student['created_at'])); ?>
                        </span>
                    </div>

                    <?php if($current_enrollment): ?>
                        <div style="margin-bottom: 15px;">
                            <span class="profile-badge badge-<?php echo strtolower($current_enrollment['status']); ?>">
                                <i class="fas fa-<?php echo $current_enrollment['status'] == 'Enrolled' ? 'check-circle' : 'clock'; ?>"></i>
                                Current Status: <?php echo $current_enrollment['status']; ?>
                            </span>
                        </div>
                    <?php endif; ?>

                    <div class="action-buttons">
                        <a href="edit_student.php?id=<?php echo $student_id; ?>" class="btn-edit">
                            <i class="fas fa-edit"></i> Edit Student
                        </a>
                        <?php if(!$current_enrollment || $current_enrollment['status'] != 'Enrolled'): ?>
                            <a href="enroll_student.php?id=<?php echo $student_id; ?>" class="btn-enroll">
                                <i class="fas fa-user-plus"></i> Enroll Student
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Current Enrollment Details -->
            <?php if($current_enrollment): ?>
            <div class="detail-card">
                <div class="card-header">
                    <h3><i class="fas fa-graduation-cap"></i> Current Enrollment</h3>
                    <a href="view_enrollment.php?id=<?php echo $current_enrollment['id']; ?>" class="view-link">
                        View Details <i class="fas fa-arrow-right"></i>
                    </a>
                </div>
                
                <div class="info-grid">
                    <div class="info-item">
                        <div class="info-label">Grade Level</div>
                        <div class="info-value">
                            <i class="fas fa-layer-group"></i>
                            <?php echo htmlspecialchars($current_enrollment['grade_name']); ?>
                        </div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Strand</div>
                        <div class="info-value">
                            <i class="fas fa-tag"></i>
                            <?php echo $current_enrollment['strand'] ?: 'Not Applicable'; ?>
                        </div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">School Year</div>
                        <div class="info-value">
                            <i class="fas fa-calendar"></i>
                            <?php echo htmlspecialchars($current_enrollment['school_year']); ?>
                        </div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Enrollment Date</div>
                        <div class="info-value">
                            <i class="fas fa-clock"></i>
                            <?php echo date('F d, Y', strtotime($current_enrollment['created_at'])); ?>
                        </div>
                    </div>
                </div>

                <?php if($current_enrollment['form_138']): ?>
                <div class="info-item" style="margin-top: 15px;">
                    <div class="info-label">Form 138</div>
                    <div class="info-value">
                        <i class="fas fa-file-pdf"></i>
                        <a href="../<?php echo $current_enrollment['form_138']; ?>" target="_blank" style="color: #0B4F2E; text-decoration: none;">
                            View Document
                        </a>
                    </div>
                </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <!-- Attendance Statistics -->
            <div class="detail-card">
                <div class="card-header">
                    <h3><i class="fas fa-calendar-check"></i> Attendance Overview</h3>
                    <a href="attendance.php?student=<?php echo $student_id; ?>" class="view-link">
                        View All <i class="fas fa-arrow-right"></i>
                    </a>
                </div>

                <div class="stats-grid">
                    <div class="stat-card present">
                        <div class="stat-number"><?php echo $attendance_stats['present']; ?></div>
                        <div class="stat-label">Present</div>
                    </div>
                    <div class="stat-card absent">
                        <div class="stat-number"><?php echo $attendance_stats['absent']; ?></div>
                        <div class="stat-label">Absent</div>
                    </div>
                    <div class="stat-card late">
                        <div class="stat-number"><?php echo $attendance_stats['late']; ?></div>
                        <div class="stat-label">Late</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number"><?php echo $attendance_rate; ?>%</div>
                        <div class="stat-label">Rate</div>
                    </div>
                </div>

                <?php if($attendance && $attendance->num_rows > 0): ?>
                <div class="table-container">
                    <table class="attendance-table">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Subject</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $count = 0;
                            while($row = $attendance->fetch_assoc()): 
                                if($count++ >= 5) break;
                            ?>
                                <tr>
                                    <td><?php echo date('M d, Y', strtotime($row['date'])); ?></td>
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
                    <p>No attendance records found.</p>
                </div>
                <?php endif; ?>
            </div>

            <!-- Enrollment History -->
            <div class="detail-card">
                <div class="card-header">
                    <h3><i class="fas fa-history"></i> Enrollment History</h3>
                </div>

                <?php if($enrollments && $enrollments->num_rows > 0): ?>
                <div class="table-container">
                    <table class="enrollments-table">
                        <thead>
                            <tr>
                                <th>School Year</th>
                                <th>Grade Level</th>
                                <th>Strand</th>
                                <th>Status</th>
                                <th>Applied Date</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while($row = $enrollments->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($row['school_year']); ?></td>
                                    <td><?php echo htmlspecialchars($row['grade_name']); ?></td>
                                    <td>
                                        <?php echo $row['strand'] ?: '—'; ?>
                                        <?php if($row['strand']): ?>
                                            <span class="strand-tag"><?php echo $row['strand']; ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge badge-<?php echo strtolower($row['status']); ?>">
                                            <?php echo $row['status']; ?>
                                        </span>
                                    </td>
                                    <td><?php echo date('M d, Y', strtotime($row['created_at'])); ?></td>
                                    <td>
                                        <a href="view_enrollment.php?id=<?php echo $row['id']; ?>" class="view-link">
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
                    <i class="fas fa-file-signature"></i>
                    <h3>No Enrollment Records</h3>
                    <p>This student has no enrollment history.</p>
                    <a href="enroll_student.php?id=<?php echo $student_id; ?>" style="color: #0B4F2E; text-decoration: none; font-weight: 500; display: inline-block; margin-top: 10px;">
                        Enroll Student Now <i class="fas fa-arrow-right"></i>
                    </a>
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