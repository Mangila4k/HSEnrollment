<?php
session_start();
include("../config/database.php");

// Check if user is admin
if(!isset($_SESSION['user']) || $_SESSION['user']['role'] != 'Admin'){
    header("Location: ../auth/login.php");
    exit();
}

$admin_name = $_SESSION['user']['fullname'];
$admin_id = $_SESSION['user']['id'];
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

// Handle user approval
if(isset($_GET['approve']) && is_numeric($_GET['approve'])) {
    $user_id = $_GET['approve'];
    
    $stmt = $conn->prepare("UPDATE users SET status = 'approved', approved_by = ?, approved_at = NOW() WHERE id = ?");
    $stmt->bind_param("ii", $admin_id, $user_id);
    if($stmt->execute()) {
        $success_message = "User approved successfully!";
    } else {
        $error_message = "Error approving user: " . $conn->error;
    }
    $stmt->close();
}

// Handle user rejection
if(isset($_POST['reject_user'])) {
    $user_id = $_POST['user_id'];
    $reason = mysqli_real_escape_string($conn, $_POST['rejection_reason']);
    
    $stmt = $conn->prepare("UPDATE users SET status = 'rejected', rejection_reason = ?, approved_by = ?, approved_at = NOW() WHERE id = ?");
    $stmt->bind_param("sii", $reason, $admin_id, $user_id);
    if($stmt->execute()) {
        $success_message = "User rejected successfully!";
    } else {
        $error_message = "Error rejecting user: " . $conn->error;
    }
    $stmt->close();
}

// Handle user deletion
if(isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $delete_id = $_GET['delete'];
    
    // Check if user exists and not deleting self
    if($delete_id != $_SESSION['user']['id']) {
        
        // Check if user has related records
        $check_enrollments = $conn->query("SELECT id FROM enrollments WHERE student_id = '$delete_id'");
        $check_attendance = $conn->query("SELECT id FROM attendance WHERE student_id = '$delete_id'");
        $check_sections = $conn->query("SELECT id FROM sections WHERE adviser_id = '$delete_id'");
        $check_teacher_attendance = $conn->query("SELECT id FROM teacher_attendance WHERE teacher_id = '$delete_id'");
        
        if($check_enrollments->num_rows > 0 || $check_attendance->num_rows > 0 || 
           $check_sections->num_rows > 0 || $check_teacher_attendance->num_rows > 0) {
            $error_message = "Cannot delete user because they have related records (enrollments, attendance, or sections).";
        } else {
            $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
            $stmt->bind_param("i", $delete_id);
            if($stmt->execute()) {
                $success_message = "User deleted successfully!";
            } else {
                $error_message = "Error deleting user: " . $conn->error;
            }
            $stmt->close();
        }
    } else {
        $error_message = "You cannot delete your own account!";
    }
}

// Handle role filter and search
$role_filter = isset($_GET['role']) ? $_GET['role'] : '';
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';
$search = isset($_GET['search']) ? $_GET['search'] : '';

// Build query
$query = "SELECT * FROM users WHERE 1=1";
$params = [];
$types = "";

if(!empty($role_filter)) {
    $query .= " AND role = ?";
    $params[] = $role_filter;
    $types .= "s";
}

if(!empty($status_filter)) {
    $query .= " AND status = ?";
    $params[] = $status_filter;
    $types .= "s";
}

if(!empty($search)) {
    $query .= " AND (fullname LIKE ? OR email LIKE ? OR id_number LIKE ?)";
    $search_term = "%$search%";
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
    $types .= "sss";
}

$query .= " ORDER BY 
    CASE status 
        WHEN 'pending' THEN 1 
        WHEN 'approved' THEN 2 
        WHEN 'rejected' THEN 3 
    END, 
    created_at DESC";

// Prepare and execute
$stmt = $conn->prepare($query);
if(!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$users = $stmt->get_result();

// Get counts by role and status
$counts = [];
$roles = ['Admin', 'Registrar', 'Teacher', 'Student'];
foreach($roles as $role) {
    $result = $conn->query("SELECT COUNT(*) as count FROM users WHERE role = '$role'");
    $counts[$role] = $result->fetch_assoc()['count'];
}

// Get status counts
$pending_count = $conn->query("SELECT COUNT(*) as count FROM users WHERE status = 'pending'")->fetch_assoc()['count'];
$approved_count = $conn->query("SELECT COUNT(*) as count FROM users WHERE status = 'approved'")->fetch_assoc()['count'];
$rejected_count = $conn->query("SELECT COUNT(*) as count FROM users WHERE status = 'rejected'")->fetch_assoc()['count'];

$total_users = array_sum($counts);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Accounts - Admin Dashboard</title>
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
            --primary: #0B4F2E;
            --primary-dark: #1a7a42;
            --primary-light: rgba(11, 79, 46, 0.1);
            --accent: #FFD700;
            --text-primary: #2b2d42;
            --text-secondary: #8d99ae;
            --border-color: #e9ecef;
            --hover-color: #f8f9fa;
            --success: #28a745;
            --warning: #ffc107;
            --danger: #dc3545;
            --info: #17a2b8;
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
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
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
            color: var(--accent);
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
            background: var(--accent);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 15px;
            font-size: 32px;
            font-weight: bold;
            color: var(--primary);
            border: 3px solid white;
        }

        .admin-info h3 {
            font-size: 18px;
            margin-bottom: 5px;
            color: var(--accent);
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
            color: var(--accent);
        }

        .menu-items a.active {
            background: rgba(255, 255, 255, 0.15);
            border-left: 3px solid var(--accent);
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
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
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
            color: var(--accent);
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
            border-left: 4px solid var(--success);
        }

        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border-left: 4px solid var(--danger);
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
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
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

        /* Status Badges */
        .status-badge {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            display: inline-block;
            text-align: center;
        }

        .status-pending {
            background: rgba(255, 193, 7, 0.1);
            color: #ffc107;
            border: 1px solid #ffc107;
        }

        .status-approved {
            background: rgba(40, 167, 69, 0.1);
            color: #28a745;
            border: 1px solid #28a745;
        }

        .status-rejected {
            background: rgba(220, 53, 69, 0.1);
            color: #dc3545;
            border: 1px solid #dc3545;
        }

        /* Role Badges */
        .role-badge {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            display: inline-block;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .role-badge.admin {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            color: white;
        }

        .role-badge.registrar {
            background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
            color: white;
        }

        .role-badge.teacher {
            background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);
            color: white;
        }

        .role-badge.student {
            background: linear-gradient(135deg, #fa709a 0%, #fee140 100%);
            color: white;
        }

        /* Section Title */
        .section-title {
            padding: 20px 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid var(--border-color);
            margin-bottom: 25px;
        }

        .section-title h2 {
            color: var(--text-primary);
            font-size: 20px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .section-title h2 i {
            color: var(--primary);
        }

        .btn-add {
            background: var(--primary);
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
            background: var(--primary-dark);
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(11, 79, 46, 0.3);
        }

        .btn-add i {
            font-size: 16px;
        }

        /* Search and Filter Bar */
        .search-bar {
            background: white;
            border-radius: 15px;
            padding: 20px 25px;
            margin-bottom: 30px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
        }

        .filter-form {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
            align-items: center;
        }

        .search-input {
            flex: 1;
            min-width: 250px;
            padding: 12px 18px;
            border: 2px solid var(--border-color);
            border-radius: 12px;
            font-size: 14px;
            transition: all 0.3s;
            background: #f8f9fa;
        }

        .search-input:focus {
            border-color: var(--primary);
            background: white;
            outline: none;
            box-shadow: 0 0 0 4px var(--primary-light);
        }

        .filter-select {
            padding: 12px 18px;
            border: 2px solid var(--border-color);
            border-radius: 12px;
            font-size: 14px;
            min-width: 150px;
            background: #f8f9fa;
            cursor: pointer;
        }

        .filter-select:focus {
            border-color: var(--primary);
            outline: none;
            background: white;
        }

        .btn-search {
            background: var(--primary);
            color: white;
            border: none;
            padding: 12px 25px;
            border-radius: 12px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-search:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(11, 79, 46, 0.3);
        }

        .btn-reset {
            background: #f8f9fa;
            color: var(--text-secondary);
            border: 2px solid var(--border-color);
            padding: 12px 25px;
            border-radius: 12px;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-reset:hover {
            border-color: var(--primary);
            color: var(--primary);
        }

        /* Table Container */
        .table-container {
            background: white;
            border-radius: 20px;
            padding: 25px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            overflow-x: auto;
        }

        .data-table {
            width: 100%;
            border-collapse: collapse;
        }

        .data-table thead tr {
            background: #f8f9fa;
            border-radius: 12px;
        }

        .data-table th {
            text-align: left;
            padding: 15px;
            color: var(--text-secondary);
            font-weight: 600;
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .data-table td {
            padding: 15px;
            border-bottom: 1px solid var(--border-color);
            color: var(--text-primary);
            font-size: 14px;
        }

        .data-table tbody tr:hover {
            background: var(--hover-color);
        }

        .id-badge {
            background: var(--primary-light);
            color: var(--primary);
            padding: 3px 8px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 500;
        }

        .action-btns {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }

        .btn-view, .btn-edit, .btn-delete, .btn-approve, .btn-reject {
            padding: 8px 12px;
            border-radius: 8px;
            font-size: 12px;
            font-weight: 500;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            transition: all 0.3s;
            border: none;
            cursor: pointer;
        }

        .btn-view {
            background: rgba(11, 79, 46, 0.1);
            color: var(--primary);
        }

        .btn-view:hover {
            background: var(--primary);
            color: white;
            transform: translateY(-2px);
        }

        .btn-edit {
            background: rgba(0, 123, 255, 0.1);
            color: #007bff;
        }

        .btn-edit:hover {
            background: #007bff;
            color: white;
            transform: translateY(-2px);
        }

        .btn-delete {
            background: rgba(220, 53, 69, 0.1);
            color: var(--danger);
        }

        .btn-delete:hover {
            background: var(--danger);
            color: white;
            transform: translateY(-2px);
        }

        .btn-approve {
            background: rgba(40, 167, 69, 0.1);
            color: var(--success);
        }

        .btn-approve:hover {
            background: var(--success);
            color: white;
            transform: translateY(-2px);
        }

        .btn-reject {
            background: rgba(220, 53, 69, 0.1);
            color: var(--danger);
        }

        .btn-reject:hover {
            background: var(--danger);
            color: white;
            transform: translateY(-2px);
        }

        .no-data {
            text-align: center;
            padding: 60px;
            color: var(--text-secondary);
        }

        .no-data i {
            font-size: 60px;
            margin-bottom: 20px;
            opacity: 0.3;
        }

        .no-data h3 {
            color: var(--text-primary);
            margin-bottom: 10px;
        }

        /* Modal */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 1000;
            justify-content: center;
            align-items: center;
        }

        .modal.active {
            display: flex;
        }

        .modal-content {
            background: white;
            border-radius: 15px;
            width: 90%;
            max-width: 500px;
        }

        .modal-header {
            padding: 20px;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .modal-header h3 {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .close-modal {
            background: none;
            border: none;
            font-size: 24px;
            cursor: pointer;
            color: var(--text-secondary);
        }

        .modal-body {
            padding: 20px;
        }

        .modal-body textarea {
            width: 100%;
            padding: 10px;
            border: 2px solid var(--border-color);
            border-radius: 8px;
            min-height: 100px;
            font-family: inherit;
        }

        .modal-footer {
            padding: 20px;
            border-top: 1px solid var(--border-color);
            display: flex;
            justify-content: flex-end;
            gap: 10px;
        }

        .btn-submit {
            background: var(--danger);
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
        }

        .btn-cancel {
            background: #e9ecef;
            color: var(--text-primary);
            border: none;
            padding: 10px 20px;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
        }

        /* Responsive */
        @media (max-width: 1200px) {
            .stats-container {
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
            
            .stats-container {
                grid-template-columns: 1fr;
            }
            
            .filter-form {
                flex-direction: column;
            }
            
            .search-input,
            .filter-select,
            .btn-search,
            .btn-reset {
                width: 100%;
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
                <h1>Account Management</h1>
                <p>Manage user accounts, approvals, and roles in the system</p>
            </div>

            <!-- Quick Stats -->
            <div class="stats-container">
                <div class="stat-card">
                    <div class="stat-header">
                        <h3>Total Users</h3>
                        <div class="stat-icon">
                            <i class="fas fa-users"></i>
                        </div>
                    </div>
                    <div class="stat-number"><?php echo $total_users; ?></div>
                    <div class="stat-label">All accounts</div>
                </div>

                <div class="stat-card">
                    <div class="stat-header">
                        <h3>Pending Approval</h3>
                        <div class="stat-icon">
                            <i class="fas fa-clock"></i>
                        </div>
                    </div>
                    <div class="stat-number"><?php echo $pending_count; ?></div>
                    <div class="stat-label">Awaiting approval</div>
                </div>

                <div class="stat-card">
                    <div class="stat-header">
                        <h3>Approved</h3>
                        <div class="stat-icon">
                            <i class="fas fa-check-circle"></i>
                        </div>
                    </div>
                    <div class="stat-number"><?php echo $approved_count; ?></div>
                    <div class="stat-label">Active accounts</div>
                </div>

                <div class="stat-card">
                    <div class="stat-header">
                        <h3>Rejected</h3>
                        <div class="stat-icon">
                            <i class="fas fa-times-circle"></i>
                        </div>
                    </div>
                    <div class="stat-number"><?php echo $rejected_count; ?></div>
                    <div class="stat-label">Rejected accounts</div>
                </div>
            </div>

            <!-- Alert Messages -->
            <?php if($success_message): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i> <?php echo $success_message; ?>
                </div>
            <?php endif; ?>
            
            <?php if($error_message): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i> <?php echo $error_message; ?>
                </div>
            <?php endif; ?>

            <!-- Section Title and Add Button -->
            <div class="section-title">
                <h2><i class="fas fa-list"></i> All Accounts</h2>
                <a href="add_account.php" class="btn-add">
                    <i class="fas fa-plus-circle"></i> Add New Account
                </a>
            </div>

            <!-- Search and Filter -->
            <div class="search-bar">
                <form method="GET" class="filter-form">
                    <input type="text" 
                           name="search" 
                           class="search-input" 
                           placeholder="Search by name, email, or ID..."
                           value="<?php echo htmlspecialchars($search); ?>">
                    
                    <select name="role" class="filter-select">
                        <option value="">All Roles</option>
                        <option value="Admin" <?php echo $role_filter == 'Admin' ? 'selected' : ''; ?>>Admin</option>
                        <option value="Registrar" <?php echo $role_filter == 'Registrar' ? 'selected' : ''; ?>>Registrar</option>
                        <option value="Teacher" <?php echo $role_filter == 'Teacher' ? 'selected' : ''; ?>>Teacher</option>
                        <option value="Student" <?php echo $role_filter == 'Student' ? 'selected' : ''; ?>>Student</option>
                    </select>

                    <select name="status" class="filter-select">
                        <option value="">All Status</option>
                        <option value="pending" <?php echo $status_filter == 'pending' ? 'selected' : ''; ?>>Pending</option>
                        <option value="approved" <?php echo $status_filter == 'approved' ? 'selected' : ''; ?>>Approved</option>
                        <option value="rejected" <?php echo $status_filter == 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                    </select>
                    
                    <button type="submit" class="btn-search">
                        <i class="fas fa-search"></i> Filter
                    </button>
                    
                    <a href="manage_accounts.php" class="btn-reset">
                        <i class="fas fa-redo-alt"></i> Reset
                    </a>
                </form>
            </div>

            <!-- Users Table -->
            <div class="table-container">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>ID Number</th>
                            <th>Full Name</th>
                            <th>Email</th>
                            <th>Role</th>
                            <th>Status</th>
                            <th>Created At</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if($users && $users->num_rows > 0): ?>
                            <?php while($user = $users->fetch_assoc()): ?>
                                <tr>
                                    <td>
                                        <span class="id-badge"><?php echo $user['id_number'] ?: 'N/A'; ?></span>
                                    </td>
                                    <td><strong><?php echo htmlspecialchars($user['fullname']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($user['email']); ?></td>
                                    <td>
                                        <span class="role-badge <?php echo strtolower($user['role']); ?>">
                                            <?php echo $user['role']; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="status-badge status-<?php echo $user['status']; ?>">
                                            <?php echo ucfirst($user['status']); ?>
                                            <?php if($user['status'] == 'pending'): ?>
                                                <i class="fas fa-clock"></i>
                                            <?php elseif($user['status'] == 'approved'): ?>
                                                <i class="fas fa-check-circle"></i>
                                            <?php elseif($user['status'] == 'rejected'): ?>
                                                <i class="fas fa-times-circle"></i>
                                            <?php endif; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="activity-time">
                                            <i class="far fa-calendar"></i>
                                            <?php echo date('M d, Y', strtotime($user['created_at'])); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="action-btns">
                                            <a href="view_account.php?id=<?php echo $user['id']; ?>" class="btn-view" title="View Details">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            
                                            <?php if($user['status'] == 'pending'): ?>
                                                <a href="?approve=<?php echo $user['id']; ?>" class="btn-approve" title="Approve User" 
                                                   onclick="return confirm('Approve this user?')">
                                                    <i class="fas fa-check"></i> Approve
                                                </a>
                                                <button class="btn-reject" title="Reject User" 
                                                        onclick="openRejectModal(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars($user['fullname']); ?>')">
                                                    <i class="fas fa-times"></i> Reject
                                                </button>
                                            <?php endif; ?>
                                            
                                            <?php if($user['role'] != 'Admin'): ?>
                                                <a href="edit_account.php?id=<?php echo $user['id']; ?>" class="btn-edit" title="Edit">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                            <?php endif; ?>
                                            
                                            <?php if($user['id'] != $_SESSION['user']['id']): ?>
                                                <a href="?delete=<?php echo $user['id']; ?>" 
                                                   class="btn-delete" title="Delete"
                                                   onclick="return confirm('Are you sure you want to delete this user? This action cannot be undone.')">
                                                    <i class="fas fa-trash"></i>
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                                <?php if(isset($user['rejection_reason']) && $user['rejection_reason']): ?>
                                <tr style="background: #fff3cd;">
                                    <td colspan="7" style="padding: 10px 15px; color: #856404;">
                                        <i class="fas fa-exclamation-triangle"></i> 
                                        <strong>Rejection Reason:</strong> <?php echo htmlspecialchars($user['rejection_reason']); ?>
                                    </td>
                                </tr>
                                <?php endif; ?>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7">
                                    <div class="no-data">
                                        <i class="fas fa-users"></i>
                                        <h3>No Users Found</h3>
                                        <p>No user accounts match your search criteria.</p>
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Reject Modal -->
    <div class="modal" id="rejectModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-exclamation-triangle" style="color: var(--danger);"></i> Reject User</h3>
                <button class="close-modal" onclick="closeRejectModal()">&times;</button>
            </div>
            <form method="POST" action="">
                <input type="hidden" name="user_id" id="reject_user_id">
                <div class="modal-body">
                    <p>You are about to reject <strong id="reject_user_name"></strong>. Please provide a reason for rejection (optional):</p>
                    <textarea name="rejection_reason" placeholder="Enter rejection reason..."></textarea>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-cancel" onclick="closeRejectModal()">Cancel</button>
                    <button type="submit" name="reject_user" class="btn-submit">Reject User</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Reject Modal Functions
        function openRejectModal(userId, userName) {
            document.getElementById('reject_user_id').value = userId;
            document.getElementById('reject_user_name').textContent = userName;
            document.getElementById('rejectModal').classList.add('active');
            document.body.style.overflow = 'hidden';
        }

        function closeRejectModal() {
            document.getElementById('rejectModal').classList.remove('active');
            document.body.style.overflow = 'auto';
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            if (event.target.classList.contains('modal')) {
                event.target.classList.remove('active');
                document.body.style.overflow = 'auto';
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
    </script>
</body>
</html>
<?php 
if(isset($stmt)) {
    $stmt->close(); 
}
?>