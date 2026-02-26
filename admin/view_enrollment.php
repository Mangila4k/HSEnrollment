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
    header("Location: enrollments.php");
    exit();
}

$enrollment_id = $_GET['id'];

// Get enrollment details with all related information
$query = "
    SELECT 
        e.*,
        u.id as student_id,
        u.fullname,
        u.email,
        u.id_number,
        u.created_at as student_created_at,
        g.grade_name,
        g.id as grade_id
    FROM enrollments e
    LEFT JOIN users u ON e.student_id = u.id
    LEFT JOIN grade_levels g ON e.grade_id = g.id
    WHERE e.id = ?
";

$stmt = $conn->prepare($query);
$stmt->bind_param("i", $enrollment_id);
$stmt->execute();
$result = $stmt->get_result();

if($result->num_rows === 0) {
    header("Location: enrollments.php");
    exit();
}

$enrollment = $result->fetch_assoc();
$stmt->close();

// Get enrollment history (previous enrollments of the same student)
$history_query = "
    SELECT e.*, g.grade_name
    FROM enrollments e
    LEFT JOIN grade_levels g ON e.grade_id = g.id
    WHERE e.student_id = ? AND e.id != ?
    ORDER BY e.created_at DESC
    LIMIT 5
";
$stmt = $conn->prepare($history_query);
$stmt->bind_param("ii", $enrollment['student_id'], $enrollment_id);
$stmt->execute();
$history = $stmt->get_result();
$stmt->close();

// Get student's attendance records
$attendance_query = "
    SELECT a.*, sub.subject_name
    FROM attendance a
    LEFT JOIN subjects sub ON a.subject_id = sub.id
    WHERE a.student_id = ?
    ORDER BY a.date DESC
    LIMIT 5
";
$stmt = $conn->prepare($attendance_query);
$stmt->bind_param("i", $enrollment['student_id']);
$stmt->execute();
$attendance = $stmt->get_result();
$stmt->close();

// Get available sections for this grade level (for potential assignment)
$sections_query = "
    SELECT s.*, g.grade_name
    FROM sections s
    LEFT JOIN grade_levels g ON s.grade_id = g.id
    WHERE s.grade_id = ?
    ORDER BY s.section_name
";
$stmt = $conn->prepare($sections_query);
$stmt->bind_param("i", $enrollment['grade_id']);
$stmt->execute();
$sections = $stmt->get_result();
$stmt->close();

// Handle status update
if(isset($_POST['update_status'])) {
    $new_status = $_POST['status'];
    $remarks = $_POST['remarks'];
    
    $update_query = "UPDATE enrollments SET status = ? WHERE id = ?";
    $stmt = $conn->prepare($update_query);
    $stmt->bind_param("si", $new_status, $enrollment_id);
    
    if($stmt->execute()) {
        $_SESSION['success_message'] = "Enrollment status updated successfully!";
        header("Location: view_enrollment.php?id=" . $enrollment_id);
        exit();
    } else {
        $error_message = "Error updating status: " . $conn->error;
    }
    $stmt->close();
}

// Handle section assignment
if(isset($_POST['assign_section'])) {
    $section_id = $_POST['section_id'];
    
    // You would need a section_id column in enrollments table for this to work
    // For now, we'll just show a message
    $_SESSION['success_message'] = "Section assignment feature coming soon!";
    header("Location: view_enrollment.php?id=" . $enrollment_id);
    exit();
}

// Calculate student statistics
$student_id = $enrollment['student_id'];
$total_attendance = $conn->query("SELECT COUNT(*) as count FROM attendance WHERE student_id = '$student_id'")->fetch_assoc()['count'];
$total_enrollments = $conn->query("SELECT COUNT(*) as count FROM enrollments WHERE student_id = '$student_id'")->fetch_assoc()['count'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Enrollment - Admin Dashboard</title>
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

        /* Status Badge */
        .status-badge {
            display: inline-block;
            padding: 8px 20px;
            border-radius: 30px;
            font-size: 14px;
            font-weight: 600;
        }

        .status-pending {
            background: rgba(255, 193, 7, 0.15);
            color: #ffc107;
            border: 1px solid #ffc107;
        }

        .status-enrolled {
            background: rgba(40, 167, 69, 0.15);
            color: #28a745;
            border: 1px solid #28a745;
        }

        .status-rejected {
            background: rgba(220, 53, 69, 0.15);
            color: #dc3545;
            border: 1px solid #dc3545;
        }

        /* Enrollment Header Card */
        .enrollment-header {
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

        .header-title h2 {
            font-size: 28px;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .header-title h2 i {
            color: #FFD700;
        }

        .header-meta {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
        }

        .header-meta-item {
            display: flex;
            align-items: center;
            gap: 8px;
            background: rgba(255, 255, 255, 0.1);
            padding: 8px 16px;
            border-radius: 30px;
            font-size: 14px;
        }

        .header-meta-item i {
            color: #FFD700;
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

        /* Student Info */
        .student-info {
            display: flex;
            align-items: center;
            gap: 20px;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 15px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }

        .student-avatar-large {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, #0B4F2E, #1a7a42);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 36px;
            font-weight: bold;
            color: white;
            border: 3px solid #FFD700;
        }

        .student-details {
            flex: 1;
        }

        .student-details h3 {
            font-size: 22px;
            color: var(--text-primary);
            margin-bottom: 5px;
        }

        .student-meta {
            display: flex;
            gap: 20px;
            flex-wrap: wrap;
            color: var(--text-secondary);
            font-size: 14px;
        }

        .student-meta i {
            color: #0B4F2E;
            margin-right: 5px;
        }

        /* Info Grid */
        .info-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
            margin-top: 20px;
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

        .document-link {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 16px;
            background: rgba(11, 79, 46, 0.1);
            color: #0B4F2E;
            text-decoration: none;
            border-radius: 8px;
            font-size: 14px;
            transition: all 0.3s;
        }

        .document-link:hover {
            background: #0B4F2E;
            color: white;
        }

        /* Stats Cards */
        .stats-mini-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 15px;
            margin-top: 20px;
        }

        .stat-mini-card {
            background: #f8f9fa;
            border-radius: 12px;
            padding: 15px;
            text-align: center;
        }

        .stat-mini-number {
            font-size: 24px;
            font-weight: 700;
            color: #0B4F2E;
            margin-bottom: 5px;
        }

        .stat-mini-label {
            font-size: 12px;
            color: var(--text-secondary);
        }

        /* Status Update Form */
        .status-update {
            background: #f8f9fa;
            border-radius: 15px;
            padding: 20px;
            margin-top: 20px;
        }

        .status-update h4 {
            color: var(--text-primary);
            font-size: 16px;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .status-update h4 i {
            color: #0B4F2E;
        }

        .status-form {
            display: flex;
            gap: 15px;
            align-items: flex-end;
            flex-wrap: wrap;
        }

        .status-form .form-group {
            flex: 1;
            min-width: 200px;
        }

        .status-form label {
            display: block;
            margin-bottom: 5px;
            font-size: 13px;
            color: var(--text-secondary);
            font-weight: 500;
        }

        .status-form select,
        .status-form input {
            width: 100%;
            padding: 10px 12px;
            border: 2px solid var(--border-color);
            border-radius: 8px;
            font-size: 14px;
            transition: all 0.3s;
        }

        .status-form select:focus,
        .status-form input:focus {
            border-color: #0B4F2E;
            outline: none;
        }

        .btn-update {
            background: #0B4F2E;
            color: white;
            padding: 10px 25px;
            border: none;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            height: 42px;
        }

        .btn-update:hover {
            background: #1a7a42;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(11, 79, 46, 0.3);
        }

        /* Tables */
        .table-container {
            overflow-x: auto;
        }

        .history-table,
        .attendance-table,
        .sections-table {
            width: 100%;
            border-collapse: collapse;
        }

        .history-table th,
        .attendance-table th,
        .sections-table th {
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

        .history-table td,
        .attendance-table td,
        .sections-table td {
            padding: 12px;
            border-bottom: 1px solid var(--border-color);
            color: var(--text-primary);
            font-size: 14px;
        }

        .history-table tbody tr:hover,
        .attendance-table tbody tr:hover,
        .sections-table tbody tr:hover {
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

        .btn-assign {
            background: #ffc107;
            color: #2b2d42;
            border: none;
            padding: 6px 12px;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s;
        }

        .btn-assign:hover {
            background: #e0a800;
            transform: translateY(-2px);
        }

        .no-data {
            text-align: center;
            padding: 30px;
            color: var(--text-secondary);
        }

        .no-data i {
            font-size: 40px;
            margin-bottom: 10px;
            opacity: 0.3;
        }

        /* Action Buttons */
        .action-buttons {
            display: flex;
            gap: 10px;
            margin-top: 20px;
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

        .btn-print {
            background: white;
            color: var(--text-primary);
            border: 2px solid var(--border-color);
            padding: 10px 20px;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
        }

        .btn-print:hover {
            border-color: #0B4F2E;
            color: #0B4F2E;
        }

        /* Responsive */
        @media (max-width: 1200px) {
            .info-grid {
                grid-template-columns: 1fr;
            }
            
            .stats-mini-grid {
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
            
            .enrollment-header {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .student-info {
                flex-direction: column;
                text-align: center;
            }
            
            .student-meta {
                justify-content: center;
            }
            
            .status-form {
                flex-direction: column;
            }
            
            .status-form .form-group {
                width: 100%;
            }
            
            .btn-update {
                width: 100%;
                justify-content: center;
            }
            
            .action-buttons {
                flex-direction: column;
            }
            
            .stats-mini-grid {
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
                    <li><a href="enrollments.php" class="active"><i class="fas fa-file-signature"></i> <span>Enrollments</span></a></li>
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
                    <h1>Enrollment Details</h1>
                    <p>View and manage enrollment information</p>
                </div>
                <a href="enrollments.php" class="back-btn">
                    <i class="fas fa-arrow-left"></i> Back to Enrollments
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

            <?php if(isset($error_message)): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i>
                    <?php echo $error_message; ?>
                </div>
            <?php endif; ?>

            <!-- Enrollment Header -->
            <div class="enrollment-header">
                <div class="header-title">
                    <h2>
                        <i class="fas fa-file-signature"></i>
                        Enrollment #<?php echo $enrollment['id']; ?>
                    </h2>
                    <div class="header-meta">
                        <span class="header-meta-item">
                            <i class="fas fa-calendar"></i> Applied: <?php echo date('M d, Y', strtotime($enrollment['created_at'])); ?>
                        </span>
                        <span class="header-meta-item">
                            <i class="fas fa-clock"></i> <?php echo date('h:i A', strtotime($enrollment['created_at'])); ?>
                        </span>
                    </div>
                </div>
                <div class="status-badge status-<?php echo strtolower($enrollment['status']); ?>">
                    <?php echo $enrollment['status']; ?>
                </div>
            </div>

            <!-- Student Information -->
            <div class="detail-card">
                <div class="card-header">
                    <h3><i class="fas fa-user-graduate"></i> Student Information</h3>
                    <a href="view_student.php?id=<?php echo $enrollment['student_id']; ?>" class="view-link">
                        View Full Profile <i class="fas fa-arrow-right"></i>
                    </a>
                </div>

                <div class="student-info">
                    <div class="student-avatar-large">
                        <?php echo strtoupper(substr($enrollment['fullname'], 0, 1)); ?>
                    </div>
                    <div class="student-details">
                        <h3><?php echo htmlspecialchars($enrollment['fullname']); ?></h3>
                        <div class="student-meta">
                            <span><i class="fas fa-envelope"></i> <?php echo htmlspecialchars($enrollment['email']); ?></span>
                            <span><i class="fas fa-id-card"></i> ID: <?php echo $enrollment['id_number'] ?? 'Not assigned'; ?></span>
                        </div>
                    </div>
                </div>

                <div class="stats-mini-grid">
                    <div class="stat-mini-card">
                        <div class="stat-mini-number"><?php echo $total_enrollments; ?></div>
                        <div class="stat-mini-label">Total Enrollments</div>
                    </div>
                    <div class="stat-mini-card">
                        <div class="stat-mini-number"><?php echo $total_attendance; ?></div>
                        <div class="stat-mini-label">Attendance Records</div>
                    </div>
                    <div class="stat-mini-card">
                        <div class="stat-mini-number"><?php echo date('Y', strtotime($enrollment['student_created_at'])); ?></div>
                        <div class="stat-mini-label">Registered</div>
                    </div>
                </div>
            </div>

            <!-- Enrollment Details -->
            <div class="detail-card">
                <div class="card-header">
                    <h3><i class="fas fa-info-circle"></i> Enrollment Information</h3>
                </div>

                <div class="info-grid">
                    <div class="info-item">
                        <div class="info-label">Grade Level</div>
                        <div class="info-value">
                            <i class="fas fa-layer-group"></i>
                            <?php echo htmlspecialchars($enrollment['grade_name']); ?>
                        </div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Strand</div>
                        <div class="info-value">
                            <i class="fas fa-tag"></i>
                            <?php echo $enrollment['strand'] ?: 'Not Applicable'; ?>
                        </div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">School Year</div>
                        <div class="info-value">
                            <i class="fas fa-calendar-alt"></i>
                            <?php echo htmlspecialchars($enrollment['school_year']); ?>
                        </div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Application Date</div>
                        <div class="info-value">
                            <i class="fas fa-clock"></i>
                            <?php echo date('F d, Y h:i A', strtotime($enrollment['created_at'])); ?>
                        </div>
                    </div>
                </div>

                <div class="info-item">
                    <div class="info-label">Form 138 (Report Card)</div>
                    <div class="info-value">
                        <?php if($enrollment['form_138']): ?>
                            <a href="../<?php echo $enrollment['form_138']; ?>" target="_blank" class="document-link">
                                <i class="fas fa-file-pdf"></i> View Document
                            </a>
                        <?php else: ?>
                            <span style="color: var(--text-secondary);">No document uploaded</span>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Status Update Form -->
                <div class="status-update">
                    <h4><i class="fas fa-edit"></i> Update Enrollment Status</h4>
                    <form method="POST" class="status-form">
                        <div class="form-group">
                            <label>Change Status</label>
                            <select name="status">
                                <option value="Pending" <?php echo $enrollment['status'] == 'Pending' ? 'selected' : ''; ?>>Pending</option>
                                <option value="Enrolled" <?php echo $enrollment['status'] == 'Enrolled' ? 'selected' : ''; ?>>Enrolled</option>
                                <option value="Rejected" <?php echo $enrollment['status'] == 'Rejected' ? 'selected' : ''; ?>>Rejected</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Remarks (Optional)</label>
                            <input type="text" name="remarks" placeholder="Add remarks">
                        </div>
                        <button type="submit" name="update_status" class="btn-update">
                            <i class="fas fa-save"></i> Update Status
                        </button>
                    </form>
                </div>

                <!-- Action Buttons -->
                <div class="action-buttons">
                    <a href="edit_enrollment.php?id=<?php echo $enrollment['id']; ?>" class="btn-edit">
                        <i class="fas fa-edit"></i> Edit Enrollment
                    </a>
                    <button onclick="window.print()" class="btn-print">
                        <i class="fas fa-print"></i> Print Details
                    </button>
                </div>
            </div>

            <!-- Available Sections -->
            <?php if($enrollment['status'] == 'Enrolled' && $sections && $sections->num_rows > 0): ?>
            <div class="detail-card">
                <div class="card-header">
                    <h3><i class="fas fa-layer-group"></i> Available Sections</h3>
                </div>

                <div class="table-container">
                    <table class="sections-table">
                        <thead>
                            <tr>
                                <th>Section Name</th>
                                <th>Grade Level</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while($section = $sections->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($section['section_name']); ?></td>
                                    <td><?php echo htmlspecialchars($section['grade_name']); ?></td>
                                    <td>
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="section_id" value="<?php echo $section['id']; ?>">
                                            <button type="submit" name="assign_section" class="btn-assign">
                                                Assign to Section
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php endif; ?>

            <!-- Enrollment History -->
            <div class="detail-card">
                <div class="card-header">
                    <h3><i class="fas fa-history"></i> Enrollment History</h3>
                </div>

                <?php if($history && $history->num_rows > 0): ?>
                    <div class="table-container">
                        <table class="history-table">
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
                                <?php while($row = $history->fetch_assoc()): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($row['school_year']); ?></td>
                                        <td><?php echo htmlspecialchars($row['grade_name']); ?></td>
                                        <td><?php echo $row['strand'] ?: '—'; ?></td>
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
                        <i class="fas fa-history"></i>
                        <p>No previous enrollment records found.</p>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Recent Attendance -->
            <div class="detail-card">
                <div class="card-header">
                    <h3><i class="fas fa-calendar-check"></i> Recent Attendance</h3>
                    <a href="attendance.php?student=<?php echo $enrollment['student_id']; ?>" class="view-link">
                        View All <i class="fas fa-arrow-right"></i>
                    </a>
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
                                <?php while($row = $attendance->fetch_assoc()): ?>
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
                    <div class="no-data">
                        <i class="fas fa-calendar-times"></i>
                        <p>No attendance records found.</p>
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