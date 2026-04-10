<?php
session_start();
include("../config/database.php");

// Check if user is admin
if(!isset($_SESSION['user']) || $_SESSION['user']['role'] != 'Admin'){
    header("Location: ../auth/login.php");
    exit();
}

$admin_name = $_SESSION['user']['fullname'];
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

// Handle delete action
if(isset($_GET['delete'])) {
    $delete_id = $_GET['delete'];
    
    try {
        // Check if subject has attendance records
        $check_attendance = $conn->prepare("SELECT id FROM attendance WHERE subject_id = ?");
        $check_attendance->execute([$delete_id]);
        
        if($check_attendance->rowCount() > 0) {
            $error_message = "Cannot delete subject because it has attendance records.";
        } else {
            $delete = $conn->prepare("DELETE FROM subjects WHERE id = ?");
            $delete->execute([$delete_id]);
            
            if($delete->rowCount() > 0) {
                $success_message = "Subject deleted successfully!";
            } else {
                $error_message = "Error deleting subject.";
            }
        }
    } catch(PDOException $e) {
        $error_message = "Error: " . $e->getMessage();
    }
}

// Get filter parameters
$grade_filter = isset($_GET['grade']) ? $_GET['grade'] : '';
$search_query = isset($_GET['search']) ? $_GET['search'] : '';

// Build the query - get all subjects with their grade levels
$query = "
    SELECT s.*, g.grade_name, g.id as grade_id,
           (SELECT COUNT(*) FROM attendance WHERE subject_id = s.id) as attendance_count
    FROM subjects s
    JOIN grade_levels g ON s.grade_id = g.id
    WHERE 1=1
";

$params = [];

if(!empty($grade_filter)) {
    $query .= " AND s.grade_id = ?";
    $params[] = $grade_filter;
}

if(!empty($search_query)) {
    $query .= " AND s.subject_name LIKE ?";
    $params[] = "%$search_query%";
}

$query .= " ORDER BY g.id, s.subject_name";

$stmt = $conn->prepare($query);
$stmt->execute($params);
$subjects = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Group subjects by grade level
$subjects_by_grade = [];
foreach($subjects as $subject) {
    $grade_name = $subject['grade_name'];
    $grade_id = $subject['grade_id'];
    if(!isset($subjects_by_grade[$grade_id])) {
        $subjects_by_grade[$grade_id] = [
            'grade_name' => $grade_name,
            'subjects' => []
        ];
    }
    $subjects_by_grade[$grade_id]['subjects'][] = $subject;
}

// Get statistics
$total_subjects_stmt = $conn->prepare("SELECT COUNT(*) as count FROM subjects");
$total_subjects_stmt->execute();
$total_subjects = $total_subjects_stmt->fetch(PDO::FETCH_ASSOC)['count'];

// Get grade levels for filter
$grade_levels_stmt = $conn->prepare("SELECT * FROM grade_levels ORDER BY id");
$grade_levels_stmt->execute();
$grade_levels = $grade_levels_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get subject count per grade
$grade_count_query = "
    SELECT g.id, g.grade_name, COUNT(s.id) as subject_count
    FROM grade_levels g
    LEFT JOIN subjects s ON g.id = s.grade_id
    GROUP BY g.id
    ORDER BY g.id
";
$grade_counts_stmt = $conn->prepare($grade_count_query);
$grade_counts_stmt->execute();
$grade_counts = $grade_counts_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get subjects with attendance
$with_attendance_stmt = $conn->prepare("SELECT COUNT(DISTINCT subject_id) as count FROM attendance");
$with_attendance_stmt->execute();
$with_attendance = $with_attendance_stmt->fetch(PDO::FETCH_ASSOC)['count'];

// Calculate JHS and SHS counts
$jhs_count = 0;
$shs_count = 0;
foreach($grade_counts as $gc) {
    if($gc['id'] <= 4) {
        $jhs_count += $gc['subject_count'];
    } else {
        $shs_count += $gc['subject_count'];
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Subjects Management - Admin Dashboard</title>
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

        /* Grade Cards */
        .grade-cards {
            display: grid;
            grid-template-columns: repeat(6, 1fr);
            gap: 15px;
            margin-bottom: 30px;
        }

        .grade-card {
            background: white;
            border-radius: 12px;
            padding: 15px;
            text-align: center;
            transition: all 0.3s ease;
            cursor: pointer;
            border: 2px solid transparent;
            text-decoration: none;
            display: block;
        }

        .grade-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }

        .grade-card.active {
            border-color: #0B4F2E;
            background: linear-gradient(135deg, #0B4F2E, #1a7a42);
            color: white;
        }

        .grade-card.active .grade-number,
        .grade-card.active .grade-subject-count {
            color: white;
        }

        .grade-number {
            font-size: 24px;
            font-weight: 700;
            color: var(--text-primary);
            margin-bottom: 5px;
        }

        .grade-name {
            font-size: 14px;
            color: var(--text-secondary);
            margin-bottom: 8px;
        }

        .grade-subject-count {
            font-size: 12px;
            color: #0B4F2E;
            font-weight: 600;
        }

        /* Actions Bar */
        .actions-bar {
            background: white;
            border-radius: 15px;
            padding: 20px 25px;
            margin-bottom: 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 20px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
        }

        .filter-group {
            display: flex;
            align-items: center;
            gap: 15px;
            flex-wrap: wrap;
        }

        .filter-select {
            padding: 10px 15px;
            border: 2px solid var(--border-color);
            border-radius: 10px;
            font-size: 14px;
            min-width: 150px;
            background: white;
        }

        .filter-select:focus {
            border-color: #0B4F2E;
            outline: none;
        }

        .btn-add {
            background: #0B4F2E;
            color: white;
            padding: 12px 25px;
            border-radius: 12px;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 10px;
            transition: all 0.3s ease;
            font-weight: 600;
            border: none;
            cursor: pointer;
        }

        .btn-add:hover {
            background: #1a7a42;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(11, 79, 46, 0.3);
        }

        .btn-add i {
            font-size: 16px;
        }

        .btn-reset {
            background: #f8f9fa;
            color: var(--text-secondary);
            padding: 10px 20px;
            border-radius: 10px;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s ease;
            font-weight: 500;
            border: 1px solid var(--border-color);
        }

        .btn-reset:hover {
            border-color: #0B4F2E;
            color: #0B4F2E;
        }

        .search-box {
            display: flex;
            align-items: center;
            gap: 10px;
            background: white;
            border: 2px solid var(--border-color);
            border-radius: 12px;
            padding: 0 15px;
            flex: 1;
            max-width: 300px;
        }

        .search-box i {
            color: var(--text-secondary);
        }

        .search-box input {
            border: none;
            padding: 12px 0;
            width: 100%;
            font-size: 14px;
        }

        .search-box input:focus {
            outline: none;
        }

        /* Grade Sections */
        .grade-section {
            background: white;
            border-radius: 20px;
            margin-bottom: 30px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
        }

        .grade-section-header {
            background: linear-gradient(135deg, #0B4F2E, #1a7a42);
            color: white;
            padding: 20px 25px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            cursor: pointer;
        }

        .grade-section-header h2 {
            font-size: 20px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .grade-section-header h2 i {
            color: #FFD700;
        }

        .grade-section-header .badge {
            background: rgba(255, 255, 255, 0.2);
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 14px;
        }

        .grade-section-header .toggle-icon {
            font-size: 20px;
            transition: transform 0.3s;
        }

        .grade-section-header.collapsed .toggle-icon {
            transform: rotate(-90deg);
        }

        .grade-section-content {
            transition: all 0.3s ease;
            overflow: hidden;
        }

        .grade-section-content.collapsed {
            display: none;
        }

        /* Table */
        .subjects-table {
            width: 100%;
            border-collapse: collapse;
        }

        .subjects-table th {
            text-align: left;
            padding: 15px;
            background: #f8f9fa;
            color: var(--text-secondary);
            font-weight: 600;
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .subjects-table td {
            padding: 15px;
            border-bottom: 1px solid var(--border-color);
            color: var(--text-primary);
        }

        .subjects-table tbody tr:hover {
            background: var(--hover-color);
        }

        .subject-info {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .subject-icon {
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

        .subject-details h4 {
            font-size: 15px;
            margin-bottom: 3px;
            color: var(--text-primary);
        }

        .grade-tag {
            background: rgba(11, 79, 46, 0.1);
            color: #0B4F2E;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
            display: inline-block;
        }

        .attendance-badge {
            background: rgba(76, 201, 240, 0.1);
            color: #4cc9f0;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
            display: inline-block;
        }

        .action-btns {
            display: flex;
            gap: 8px;
        }

        .btn-icon {
            width: 32px;
            height: 32px;
            border-radius: 8px;
            border: none;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.3s;
            background: transparent;
            color: var(--text-secondary);
            text-decoration: none;
        }

        .btn-icon:hover {
            background: var(--hover-color);
        }

        .btn-view:hover {
            color: #0B4F2E;
        }

        .btn-edit:hover {
            color: #007bff;
        }

        .btn-delete:hover {
            color: #dc3545;
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
            
            .grade-cards {
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
            
            .stats-container {
                grid-template-columns: 1fr;
            }
            
            .grade-cards {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .actions-bar {
                flex-direction: column;
                align-items: stretch;
            }
            
            .filter-group {
                flex-direction: column;
                width: 100%;
            }
            
            .filter-select {
                width: 100%;
            }
            
            .search-box {
                max-width: 100%;
            }
            
            .btn-add {
                width: 100%;
                justify-content: center;
            }
            
            .grade-section-header {
                flex-direction: column;
                gap: 10px;
                text-align: center;
            }
            
            .subject-info {
                flex-direction: column;
                text-align: center;
            }
            
            .action-btns {
                justify-content: center;
            }
        }
    </style>
</head>
<body>
    <div class="app-container">
        <!-- Sidebar -->
        <div class="sidebar">           
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
                <h1>Subjects Management</h1>
                <p>Manage subjects offered per grade level (Grade 7 - Grade 12)</p>
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

            <!-- Statistics -->
            <div class="stats-container">
                <div class="stat-card">
                    <div class="stat-header">
                        <h3>Total Subjects</h3>
                        <div class="stat-icon">
                            <i class="fas fa-book"></i>
                        </div>
                    </div>
                    <div class="stat-number"><?php echo $total_subjects; ?></div>
                    <div class="stat-label">All subjects</div>
                </div>

                <div class="stat-card">
                    <div class="stat-header">
                        <h3>Junior High School</h3>
                        <div class="stat-icon">
                            <i class="fas fa-users"></i>
                        </div>
                    </div>
                    <div class="stat-number"><?php echo $jhs_count; ?></div>
                    <div class="stat-label">Grades 7-10</div>
                </div>

                <div class="stat-card">
                    <div class="stat-header">
                        <h3>Senior High School</h3>
                        <div class="stat-icon">
                            <i class="fas fa-user-graduate"></i>
                        </div>
                    </div>
                    <div class="stat-number"><?php echo $shs_count; ?></div>
                    <div class="stat-label">Grades 11-12</div>
                </div>

                <div class="stat-card">
                    <div class="stat-header">
                        <h3>With Attendance</h3>
                        <div class="stat-icon">
                            <i class="fas fa-calendar-check"></i>
                        </div>
                    </div>
                    <div class="stat-number"><?php echo $with_attendance; ?></div>
                    <div class="stat-label">Subjects with records</div>
                </div>
            </div>

            <!-- Grade Level Quick Navigation -->
            <!-- Grade Level Quick Navigation -->
<div class="grade-cards">
    <?php foreach($grade_counts as $grade): 
        // Only show grades 1-6 (which correspond to Grade 7-12)
        if($grade['id'] >= 1 && $grade['id'] <= 6):
    ?>
        <a href="?grade=<?php echo $grade['id']; ?>" class="grade-card <?php echo $grade_filter == $grade['id'] ? 'active' : ''; ?>">
            <div class="grade-number">Grade <?php echo $grade['id'] + 6; ?></div>
            <div class="grade-name"><?php echo htmlspecialchars($grade['grade_name']); ?></div>
            <div class="grade-subject-count"><?php echo $grade['subject_count']; ?> Subjects</div>
        </a>
    <?php 
        endif;
    endforeach; 
    ?>
</div>

            <!-- Actions Bar -->
            <div class="actions-bar">
                <form method="GET" action="" style="display: flex; gap: 15px; flex-wrap: wrap; width: 100%;">
                    <div class="filter-group">
                        <select name="grade" class="filter-select">
                            <option value="">All Grades (7-12)</option>
                            <?php foreach($grade_levels as $grade): ?>
                                <option value="<?php echo $grade['id']; ?>" <?php echo $grade_filter == $grade['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($grade['grade_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>

                        <button type="submit" class="btn-add" style="padding: 10px 20px;">
                            <i class="fas fa-filter"></i> Apply Filter
                        </button>

                        <a href="subjects.php" class="btn-reset">
                            <i class="fas fa-redo-alt"></i> Reset
                        </a>
                    </div>

                    <div class="search-box">
                        <i class="fas fa-search"></i>
                        <input type="text" name="search" placeholder="Search subjects..." value="<?php echo htmlspecialchars($search_query); ?>">
                    </div>
                </form>

                <a href="add_subject.php" class="btn-add">
                    <i class="fas fa-plus-circle"></i> Add New Subject
                </a>
            </div>

            <!-- Subjects by Grade Level -->
            <?php if(count($subjects_by_grade) > 0): ?>
                <?php 
                $grade_order = [1 => 'Grade 7', 2 => 'Grade 8', 3 => 'Grade 9', 4 => 'Grade 10', 5 => 'Grade 11', 6 => 'Grade 12'];
                foreach($grade_order as $grade_id => $grade_display):
                    if(isset($subjects_by_grade[$grade_id])):
                        $grade_data = $subjects_by_grade[$grade_id];
                ?>
                    <div class="grade-section">
                        <div class="grade-section-header" onclick="toggleGradeSection(this)">
                            <h2>
                                <i class="fas fa-layer-group"></i>
                                <?php echo $grade_display; ?> - <?php echo $grade_data['grade_name']; ?>
                            </h2>
                            <div style="display: flex; gap: 15px; align-items: center;">
                                <span class="badge"><?php echo count($grade_data['subjects']); ?> Subjects</span>
                                <i class="fas fa-chevron-down toggle-icon"></i>
                            </div>
                        </div>
                        <div class="grade-section-content">
                            <table class="subjects-table">
                                <thead>
                                    <tr>
                                        <th>Subject</th>
                                        <th>ID</th>
                                        <th>Attendance Records</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach($grade_data['subjects'] as $subject): ?>
                                        <tr>
                                            <td>
                                                <div class="subject-info">
                                                    <div class="subject-icon">
                                                        <i class="fas fa-book-open"></i>
                                                    </div>
                                                    <div class="subject-details">
                                                        <h4><?php echo htmlspecialchars($subject['subject_name']); ?></h4>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <span class="grade-tag">#<?php echo $subject['id']; ?></span>
                                            </td>
                                            <td>
                                                <span class="attendance-badge">
                                                    <i class="fas fa-calendar-check"></i>
                                                    <?php echo $subject['attendance_count']; ?> records
                                                </span>
                                            </td>
                                            <td>
                                                <div class="action-btns">
                                                    <a href="view_subject.php?id=<?php echo $subject['id']; ?>" class="btn-icon btn-view" title="View Details">
                                                        <i class="fas fa-eye"></i>
                                                    </a>
                                                    <a href="edit_subject.php?id=<?php echo $subject['id']; ?>" class="btn-icon btn-edit" title="Edit">
                                                        <i class="fas fa-edit"></i>
                                                    </a>
                                                    <?php if($subject['attendance_count'] == 0): ?>
                                                        <a href="?delete=<?php echo $subject['id']; ?>" class="btn-icon btn-delete" title="Delete" 
                                                           onclick="return confirm('Are you sure you want to delete this subject?')">
                                                            <i class="fas fa-trash"></i>
                                                        </a>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                <?php 
                    endif;
                endforeach; 
                ?>
            <?php else: ?>
                <div class="table-card">
                    <div class="no-data">
                        <i class="fas fa-book"></i>
                        <h3>No Subjects Found</h3>
                        <p>Click the "Add New Subject" button to add your first subject.</p>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        // Toggle grade section
        function toggleGradeSection(header) {
            const content = header.nextElementSibling;
            const parent = header.parentElement;
            
            if (content.classList.contains('collapsed')) {
                content.classList.remove('collapsed');
                header.classList.remove('collapsed');
            } else {
                content.classList.add('collapsed');
                header.classList.add('collapsed');
            }
        }

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

        // Auto-submit form when filter changes
        const filterSelect = document.querySelector('.filter-select');
        if(filterSelect) {
            filterSelect.addEventListener('change', function() {
                this.form.submit();
            });
        }

        // Search on Enter key
        const searchInput = document.querySelector('.search-box input');
        if(searchInput) {
            searchInput.addEventListener('keypress', function(e) {
                if (e.key === 'Enter') {
                    this.form.submit();
                }
            });
        }
    </script>
</body>
</html>