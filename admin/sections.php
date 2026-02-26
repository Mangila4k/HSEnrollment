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

// Handle delete action
if(isset($_GET['delete'])) {
    $delete_id = $_GET['delete'];
    $delete = $conn->query("DELETE FROM sections WHERE id = '$delete_id'");
    if($delete) {
        $success_message = "Section deleted successfully!";
    } else {
        $error_message = "Error deleting section.";
    }
}

// Get all sections with details
$sections = $conn->query("
    SELECT s.id, s.section_name, g.grade_name, u.fullname as adviser, u.id as adviser_id
    FROM sections s 
    LEFT JOIN grade_levels g ON s.grade_id = g.id
    LEFT JOIN users u ON s.adviser_id = u.id
    ORDER BY g.id, s.section_name
");

// Get grade levels for filter
$grade_levels = $conn->query("SELECT * FROM grade_levels ORDER BY id");

// Get teachers for adviser selection
$teachers = $conn->query("SELECT id, fullname FROM users WHERE role = 'Teacher' ORDER BY fullname");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sections Management - Admin Dashboard</title>
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
            min-width: 180px;
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

        .search-box {
            display: flex;
            align-items: center;
            gap: 10px;
            background: white;
            border: 2px solid var(--border-color);
            border-radius: 12px;
            padding: 0 15px;
        }

        .search-box i {
            color: var(--text-secondary);
        }

        .search-box input {
            border: none;
            padding: 12px 0;
            width: 250px;
            font-size: 14px;
        }

        .search-box input:focus {
            outline: none;
        }

        /* Table Card */
        .table-card {
            background: white;
            border-radius: 20px;
            padding: 25px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
        }

        .table-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            flex-wrap: wrap;
            gap: 15px;
        }

        .table-header h3 {
            color: var(--text-primary);
            font-size: 18px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .table-header h3 i {
            color: #0B4F2E;
        }

        .table-container {
            overflow-x: auto;
        }

        .sections-table {
            width: 100%;
            border-collapse: collapse;
        }

        .sections-table th {
            text-align: left;
            padding: 15px;
            background: #f8f9fa;
            color: var(--text-secondary);
            font-weight: 600;
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .sections-table td {
            padding: 15px;
            border-bottom: 1px solid var(--border-color);
            color: var(--text-primary);
        }

        .sections-table tbody tr:hover {
            background: var(--hover-color);
        }

        .section-info {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .section-icon {
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

        .section-details h4 {
            font-size: 16px;
            margin-bottom: 3px;
            color: var(--text-primary);
        }

        .section-details span {
            font-size: 13px;
            color: var(--text-secondary);
        }

        .grade-badge {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            background: rgba(11, 79, 46, 0.1);
            color: #0B4F2E;
            display: inline-block;
        }

        .adviser-info {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .adviser-avatar {
            width: 35px;
            height: 35px;
            background: #FFD700;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #0B4F2E;
            font-weight: 600;
            font-size: 14px;
        }

        .adviser-name {
            font-weight: 500;
        }

        .no-adviser {
            color: var(--text-secondary);
            font-style: italic;
        }

        .action-btns {
            display: flex;
            gap: 8px;
        }

        .btn-icon {
            width: 35px;
            height: 35px;
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

        .btn-schedule:hover {
            color: #0B4F2E;
        }

        .btn-edit:hover {
            color: #0B4F2E;
        }

        .btn-delete:hover {
            color: #dc3545;
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
            background: rgba(0, 0, 0, 0.5);
            z-index: 1000;
            justify-content: center;
            align-items: center;
        }

        .modal.active {
            display: flex;
        }

        .modal-content {
            background: white;
            border-radius: 20px;
            width: 90%;
            max-width: 500px;
            animation: modalSlideIn 0.3s ease;
        }

        @keyframes modalSlideIn {
            from {
                transform: translateY(-50px);
                opacity: 0;
            }
            to {
                transform: translateY(0);
                opacity: 1;
            }
        }

        .modal-header {
            padding: 25px 30px;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .modal-header h3 {
            color: var(--text-primary);
            font-size: 20px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .modal-header h3 i {
            color: #0B4F2E;
        }

        .close-modal {
            background: none;
            border: none;
            font-size: 24px;
            cursor: pointer;
            color: var(--text-secondary);
            transition: color 0.3s;
        }

        .close-modal:hover {
            color: #dc3545;
        }

        .modal-body {
            padding: 30px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: var(--text-secondary);
            font-weight: 500;
            font-size: 14px;
        }

        .form-group input,
        .form-group select {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid var(--border-color);
            border-radius: 10px;
            font-size: 14px;
            transition: all 0.3s;
        }

        .form-group input:focus,
        .form-group select:focus {
            border-color: #0B4F2E;
            outline: none;
            box-shadow: 0 0 0 3px rgba(11, 79, 46, 0.1);
        }

        .modal-footer {
            padding: 20px 30px;
            border-top: 1px solid var(--border-color);
            display: flex;
            justify-content: flex-end;
            gap: 15px;
        }

        .btn-save {
            background: #0B4F2E;
            color: white;
            padding: 12px 25px;
            border: none;
            border-radius: 10px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
        }

        .btn-save:hover {
            background: #1a7a42;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(11, 79, 46, 0.3);
        }

        .btn-cancel {
            background: #e9ecef;
            color: var(--text-secondary);
            padding: 12px 25px;
            border: none;
            border-radius: 10px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
        }

        .btn-cancel:hover {
            background: #dee2e6;
        }

        /* Pagination */
        .pagination {
            margin-top: 25px;
            display: flex;
            justify-content: flex-end;
            gap: 8px;
        }

        .page-btn {
            width: 40px;
            height: 40px;
            border-radius: 10px;
            border: 1px solid var(--border-color);
            background: white;
            color: var(--text-secondary);
            cursor: pointer;
            transition: all 0.3s;
        }

        .page-btn:hover,
        .page-btn.active {
            background: #0B4F2E;
            color: white;
            border-color: #0B4F2E;
        }

        /* Responsive */
        @media (max-width: 1200px) {
            .actions-bar {
                flex-direction: column;
                align-items: stretch;
            }
            
            .filter-group {
                justify-content: space-between;
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
            
            .filter-group {
                flex-direction: column;
                width: 100%;
            }
            
            .filter-select {
                width: 100%;
            }
            
            .search-box {
                width: 100%;
            }
            
            .search-box input {
                width: 100%;
            }
            
            .btn-add {
                width: 100%;
                justify-content: center;
            }
            
            .section-info {
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
                    <h1>Sections Management</h1>
                    <p>Manage class sections and advisers</p>
                </div>
                <a href="dashboard.php" class="back-btn">
                    <i class="fas fa-arrow-left"></i> Back to Dashboard
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

            <!-- Actions Bar -->
            <div class="actions-bar">
                <div class="filter-group">
                    <select class="filter-select" id="gradeFilter">
                        <option value="">All Grade Levels</option>
                        <?php while($grade = $grade_levels->fetch_assoc()): ?>
                            <option value="<?php echo $grade['grade_name']; ?>"><?php echo $grade['grade_name']; ?></option>
                        <?php endwhile; ?>
                    </select>
                    
                    <select class="filter-select" id="adviserFilter">
                        <option value="">All Advisers</option>
                        <?php 
                        $teachers->data_seek(0);
                        while($teacher = $teachers->fetch_assoc()): 
                        ?>
                            <option value="<?php echo $teacher['fullname']; ?>"><?php echo $teacher['fullname']; ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>

                <div class="search-box">
                    <i class="fas fa-search"></i>
                    <input type="text" id="searchInput" placeholder="Search sections...">
                </div>

                <button class="btn-add" onclick="openAddModal()">
                    <i class="fas fa-plus-circle"></i> Add New Section
                </button>
            </div>

            <!-- Sections Table -->
            <div class="table-card">
                <div class="table-header">
                    <h3><i class="fas fa-layer-group"></i> Section List</h3>
                    <span class="grade-badge">Total: <?php echo $sections->num_rows; ?> sections</span>
                </div>

                <div class="table-container">
                    <table class="sections-table" id="sectionsTable">
                        <thead>
                            <tr>
                                <th>Section</th>
                                <th>Grade Level</th>
                                <th>Adviser</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if($sections && $sections->num_rows > 0): ?>
                                <?php while($sec = $sections->fetch_assoc()): ?>
                                    <tr>
                                        <td>
                                            <div class="section-info">
                                                <div class="section-icon">
                                                    <i class="fas fa-users"></i>
                                                </div>
                                                <div class="section-details">
                                                    <h4><?php echo htmlspecialchars($sec['section_name']); ?></h4>
                                                    <span>ID: <?php echo $sec['id']; ?></span>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="grade-badge">
                                                <?php echo htmlspecialchars($sec['grade_name'] ?? 'N/A'); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if($sec['adviser']): ?>
                                                <div class="adviser-info">
                                                    <div class="adviser-avatar">
                                                        <?php echo strtoupper(substr($sec['adviser'], 0, 1)); ?>
                                                    </div>
                                                    <span class="adviser-name"><?php echo htmlspecialchars($sec['adviser']); ?></span>
                                                </div>
                                            <?php else: ?>
                                                <span class="no-adviser">Not Assigned</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="action-btns">
                                                <!-- Schedule Button - NEW -->
                                                <a href="create_schedule.php?section_id=<?php echo $sec['id']; ?>" class="btn-icon btn-schedule" title="Manage Schedule">
                                                    <i class="fas fa-calendar-alt"></i>
                                                </a>
                                                <!-- Edit Button -->
                                                <a href="#" class="btn-icon btn-edit" onclick="openEditModal(<?php echo $sec['id']; ?>)" title="Edit Section">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                                <!-- Delete Button -->
                                                <a href="?delete=<?php echo $sec['id']; ?>" class="btn-icon btn-delete" 
                                                   onclick="return confirm('Are you sure you want to delete this section?')" title="Delete Section">
                                                    <i class="fas fa-trash"></i>
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="4">
                                        <div class="no-data">
                                            <i class="fas fa-layer-group"></i>
                                            <h3>No Sections Found</h3>
                                            <p>Click the "Add New Section" button to create your first section.</p>
                                        </div>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <div class="pagination">
                    <button class="page-btn active">1</button>
                    <button class="page-btn">2</button>
                    <button class="page-btn">3</button>
                    <button class="page-btn">4</button>
                    <button class="page-btn">5</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Add Section Modal -->
    <div class="modal" id="addModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-plus-circle"></i> Add New Section</h3>
                <button class="close-modal" onclick="closeAddModal()">&times;</button>
            </div>
            <form method="POST" action="sections_add.php">
                <div class="modal-body">
                    <div class="form-group">
                        <label>Section Name</label>
                        <input type="text" name="section_name" placeholder="e.g., Section A" required>
                    </div>
                    <div class="form-group">
                        <label>Grade Level</label>
                        <select name="grade_id" required>
                            <option value="">Select Grade Level</option>
                            <?php 
                            $grade_levels->data_seek(0);
                            while($grade = $grade_levels->fetch_assoc()): 
                            ?>
                                <option value="<?php echo $grade['id']; ?>"><?php echo $grade['grade_name']; ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Adviser</label>
                        <select name="adviser_id">
                            <option value="">Select Adviser (Optional)</option>
                            <?php 
                            $teachers->data_seek(0);
                            while($teacher = $teachers->fetch_assoc()): 
                            ?>
                                <option value="<?php echo $teacher['id']; ?>"><?php echo $teacher['fullname']; ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-cancel" onclick="closeAddModal()">Cancel</button>
                    <button type="submit" name="add_section" class="btn-save">Add Section</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Edit Section Modal -->
    <div class="modal" id="editModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-edit"></i> Edit Section</h3>
                <button class="close-modal" onclick="closeEditModal()">&times;</button>
            </div>
            <form method="POST" action="sections_edit.php" id="editForm">
                <input type="hidden" name="section_id" id="edit_section_id">
                <div class="modal-body">
                    <div class="form-group">
                        <label>Section Name</label>
                        <input type="text" name="section_name" id="edit_section_name" required>
                    </div>
                    <div class="form-group">
                        <label>Grade Level</label>
                        <select name="grade_id" id="edit_grade_id" required>
                            <option value="">Select Grade Level</option>
                            <?php 
                            $grade_levels->data_seek(0);
                            while($grade = $grade_levels->fetch_assoc()): 
                            ?>
                                <option value="<?php echo $grade['id']; ?>"><?php echo $grade['grade_name']; ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Adviser</label>
                        <select name="adviser_id" id="edit_adviser_id">
                            <option value="">Select Adviser (Optional)</option>
                            <?php 
                            $teachers->data_seek(0);
                            while($teacher = $teachers->fetch_assoc()): 
                            ?>
                                <option value="<?php echo $teacher['id']; ?>"><?php echo $teacher['fullname']; ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-cancel" onclick="closeEditModal()">Cancel</button>
                    <button type="submit" name="edit_section" class="btn-save">Save Changes</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Modal functions
        function openAddModal() {
            document.getElementById('addModal').classList.add('active');
        }

        function closeAddModal() {
            document.getElementById('addModal').classList.remove('active');
        }

        function openEditModal(id) {
            // Fetch section data via AJAX
            fetch(`get_section.php?id=${id}`)
                .then(response => response.json())
                .then(data => {
                    document.getElementById('edit_section_id').value = data.id;
                    document.getElementById('edit_section_name').value = data.section_name;
                    document.getElementById('edit_grade_id').value = data.grade_id;
                    document.getElementById('edit_adviser_id').value = data.adviser_id || '';
                    document.getElementById('editModal').classList.add('active');
                });
        }

        function closeEditModal() {
            document.getElementById('editModal').classList.remove('active');
        }

        // Search functionality
        document.getElementById('searchInput').addEventListener('keyup', function() {
            let searchValue = this.value.toLowerCase();
            let tableRows = document.querySelectorAll('#sectionsTable tbody tr');
            
            tableRows.forEach(row => {
                let text = row.textContent.toLowerCase();
                row.style.display = text.includes(searchValue) ? '' : 'none';
            });
        });

        // Filter by grade
        document.getElementById('gradeFilter').addEventListener('change', function() {
            filterTable();
        });

        document.getElementById('adviserFilter').addEventListener('change', function() {
            filterTable();
        });

        function filterTable() {
            let gradeFilter = document.getElementById('gradeFilter').value.toLowerCase();
            let adviserFilter = document.getElementById('adviserFilter').value.toLowerCase();
            let tableRows = document.querySelectorAll('#sectionsTable tbody tr');
            
            tableRows.forEach(row => {
                let gradeCell = row.cells[1]?.textContent.toLowerCase() || '';
                let adviserCell = row.cells[2]?.textContent.toLowerCase() || '';
                
                let gradeMatch = !gradeFilter || gradeCell.includes(gradeFilter);
                let adviserMatch = !adviserFilter || adviserCell.includes(adviserFilter);
                
                row.style.display = (gradeMatch && adviserMatch) ? '' : 'none';
            });
        }

        // Auto-hide alerts
        setTimeout(function() {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                alert.style.opacity = '0';
                setTimeout(() => {
                    alert.style.display = 'none';
                }, 300);
            });
        }, 5000);

        // Close modal when clicking outside
        window.onclick = function(event) {
            if (event.target.classList.contains('modal')) {
                event.target.classList.remove('active');
            }
        }
    </script>
</body>
</html>