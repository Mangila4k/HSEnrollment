<?php
session_start();
include("../config/database.php");

// Check if user is registrar
if(!isset($_SESSION['user']) || $_SESSION['user']['role'] != 'Registrar'){
    header("Location: ../auth/login.php");
    exit();
}

$registrar_name = $_SESSION['user']['fullname'];
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
    
    // Check if section has students
    $check_students = $conn->query("SELECT id FROM enrollments WHERE section_id = '$delete_id'");
    if($check_students && $check_students->num_rows > 0) {
        $_SESSION['error_message'] = "Cannot delete section with enrolled students. Remove students first.";
        header("Location: sections.php");
        exit();
    }
    
    // Check if section has schedules
    $check_schedules = $conn->query("SELECT id FROM class_schedules WHERE section_id = '$delete_id'");
    if($check_schedules && $check_schedules->num_rows > 0) {
        // Delete schedules first
        $conn->query("DELETE FROM class_schedules WHERE section_id = '$delete_id'");
    }
    
    // Delete the section
    $delete = $conn->query("DELETE FROM sections WHERE id = '$delete_id'");
    
    if($delete) {
        $success_message = "Section deleted successfully!";
    } else {
        $error_message = "Error deleting section: " . $conn->error;
    }
}

// Handle add section
if(isset($_POST['add_section'])) {
    $section_name = mysqli_real_escape_string($conn, $_POST['section_name']);
    $grade_id = (int)$_POST['grade_id'];
    $adviser_id = !empty($_POST['adviser_id']) ? (int)$_POST['adviser_id'] : 'NULL';
    $school_year = mysqli_real_escape_string($conn, $_POST['school_year']);
    
    $query = "INSERT INTO sections (section_name, grade_id, adviser_id, school_year) 
              VALUES ('$section_name', '$grade_id', $adviser_id, '$school_year')";
    
    if($conn->query($query)) {
        $success_message = "Section added successfully!";
    } else {
        $error_message = "Error adding section: " . $conn->error;
    }
}

// Handle edit section
if(isset($_POST['edit_section'])) {
    $section_id = (int)$_POST['section_id'];
    $section_name = mysqli_real_escape_string($conn, $_POST['section_name']);
    $grade_id = (int)$_POST['grade_id'];
    $adviser_id = !empty($_POST['adviser_id']) ? (int)$_POST['adviser_id'] : 'NULL';
    
    $query = "UPDATE sections SET 
              section_name = '$section_name',
              grade_id = '$grade_id',
              adviser_id = $adviser_id
              WHERE id = $section_id";
    
    if($conn->query($query)) {
        $success_message = "Section updated successfully!";
    } else {
        $error_message = "Error updating section: " . $conn->error;
    }
}

// Get all sections with details - FIXED: Changed ORDER BY clause
$sections_query = "
    SELECT s.*, g.grade_name, g.id as grade_id, u.fullname as adviser_name,
           (SELECT COUNT(*) FROM enrollments WHERE section_id = s.id AND status = 'Enrolled') as student_count
    FROM sections s
    LEFT JOIN grade_levels g ON s.grade_id = g.id
    LEFT JOIN users u ON s.adviser_id = u.id
    ORDER BY g.grade_name, s.section_name
";
$sections = $conn->query($sections_query);

if(!$sections) {
    die("Error in sections query: " . $conn->error);
}

// Get grade levels for filter
$grade_levels = $conn->query("SELECT * FROM grade_levels ORDER BY grade_name");

// Get teachers for adviser selection
$teachers = $conn->query("SELECT id, fullname FROM users WHERE role = 'Teacher' ORDER BY fullname");

// Get section for editing if ID is provided
$edit_section = null;
if(isset($_GET['edit'])) {
    $edit_id = (int)$_GET['edit'];
    $edit_result = $conn->query("SELECT * FROM sections WHERE id = $edit_id");
    if($edit_result && $edit_result->num_rows > 0) {
        $edit_section = $edit_result->fetch_assoc();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Section Management - Registrar Dashboard</title>
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
            padding-bottom: 20px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.2);
        }

        .sidebar h2 i {
            color: var(--accent);
        }

        .user-info {
            text-align: center;
            padding: 20px 0;
            border-bottom: 1px solid rgba(255, 255, 255, 0.2);
            margin-bottom: 20px;
        }

        .user-avatar {
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

        .user-info h3 {
            font-size: 18px;
            margin-bottom: 5px;
            color: var(--accent);
        }

        .user-info p {
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
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
        }

        .header-left h1 {
            font-size: 28px;
            color: var(--text-primary);
            margin-bottom: 5px;
        }

        .header-left p {
            color: var(--text-secondary);
        }

        .btn-primary {
            background: var(--primary);
            color: white;
            padding: 12px 25px;
            border-radius: 10px;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-weight: 600;
            transition: all 0.3s;
            border: none;
            cursor: pointer;
        }

        .btn-primary:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(11, 79, 46, 0.3);
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

        /* Stats Cards */
        .stats-container {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            border-radius: 15px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            transition: all 0.3s;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
        }

        .stat-icon {
            width: 45px;
            height: 45px;
            background: var(--primary-light);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--primary);
            font-size: 20px;
            margin-bottom: 10px;
        }

        .stat-number {
            font-size: 24px;
            font-weight: 700;
            color: var(--text-primary);
        }

        .stat-label {
            color: var(--text-secondary);
            font-size: 13px;
        }

        /* Filters */
        .filters-card {
            background: white;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 30px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
            align-items: flex-end;
        }

        .filter-group {
            flex: 1;
            min-width: 200px;
        }

        .filter-group label {
            display: block;
            margin-bottom: 5px;
            color: var(--text-secondary);
            font-size: 13px;
            font-weight: 600;
        }

        .filter-group select, .filter-group input {
            width: 100%;
            padding: 10px;
            border: 2px solid var(--border-color);
            border-radius: 8px;
            font-size: 14px;
        }

        /* Sections Grid */
        .sections-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 25px;
        }

        .section-card {
            background: white;
            border-radius: 15px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            transition: all 0.3s;
            border: 1px solid var(--border-color);
        }

        .section-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
            border-color: var(--primary);
        }

        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }

        .section-icon {
            width: 50px;
            height: 50px;
            background: var(--primary-light);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--primary);
            font-size: 24px;
        }

        .section-badge {
            background: var(--primary-light);
            color: var(--primary);
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }

        .section-name {
            font-size: 20px;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 5px;
        }

        .grade-level {
            color: var(--primary);
            font-weight: 500;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .adviser-info {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 10px 0;
            border-top: 1px solid var(--border-color);
            border-bottom: 1px solid var(--border-color);
            margin-bottom: 15px;
        }

        .adviser-avatar {
            width: 35px;
            height: 35px;
            background: var(--accent);
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--primary);
            font-weight: 600;
        }

        .stats-row {
            display: flex;
            justify-content: space-around;
            margin-bottom: 20px;
        }

        .stat-item {
            text-align: center;
        }

        .stat-value {
            font-size: 20px;
            font-weight: 700;
            color: var(--text-primary);
        }

        .stat-label {
            font-size: 12px;
            color: var(--text-secondary);
        }

        .action-buttons {
            display: flex;
            gap: 10px;
        }

        .btn-action {
            flex: 1;
            padding: 10px;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 5px;
            text-decoration: none;
            transition: all 0.3s;
            font-size: 13px;
        }

        .btn-view {
            background: var(--primary-light);
            color: var(--primary);
        }

        .btn-view:hover {
            background: var(--primary);
            color: white;
        }

        .btn-edit {
            background: var(--accent);
            color: var(--primary);
        }

        .btn-edit:hover {
            background: #ffed4a;
        }

        .btn-students {
            background: var(--hover-color);
            color: var(--text-primary);
        }

        .btn-students:hover {
            background: var(--border-color);
        }

        .btn-delete {
            background: rgba(220, 53, 69, 0.1);
            color: var(--danger);
        }

        .btn-delete:hover {
            background: var(--danger);
            color: white;
        }

        .no-data {
            text-align: center;
            padding: 60px;
            grid-column: 1 / -1;
            color: var(--text-secondary);
        }

        .no-data i {
            font-size: 60px;
            margin-bottom: 20px;
            opacity: 0.3;
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
            max-height: 90vh;
            overflow-y: auto;
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

        .form-group {
            margin-bottom: 15px;
        }

        .form-group label {
            display: block;
            margin-bottom: 5px;
            color: var(--text-secondary);
            font-weight: 500;
        }

        .form-group input, .form-group select {
            width: 100%;
            padding: 10px;
            border: 2px solid var(--border-color);
            border-radius: 8px;
            font-size: 14px;
        }

        .modal-footer {
            padding: 20px;
            border-top: 1px solid var(--border-color);
            display: flex;
            justify-content: flex-end;
            gap: 10px;
        }

        .btn-save {
            background: var(--primary);
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

        @media (max-width: 768px) {
            .sidebar {
                width: 80px;
            }
            
            .sidebar h2 span,
            .user-info h3,
            .user-info p,
            .menu-section h3,
            .menu-items a span {
                display: none;
            }
            
            .main-content {
                margin-left: 80px;
            }
            
            .stats-container {
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
            <h2><i class="fas fa-check-circle"></i><span>PNHS</span></h2>
            <div class="user-info">
                <div class="user-avatar"><?php echo strtoupper(substr($registrar_name, 0, 1)); ?></div>
                <h3><?php echo htmlspecialchars(explode(' ', $registrar_name)[0]); ?></h3>
                <p><i class="fas fa-user-graduate"></i> Registrar</p>
            </div>
            <div class="menu-section">
                <h3>MAIN MENU</h3>
                <ul class="menu-items">
                    <li><a href="dashboard.php"><i class="fas fa-tachometer-alt"></i><span>Dashboard</span></a></li>
                    <li><a href="enrollments.php"><i class="fas fa-file-signature"></i><span>Enrollments</span></a></li>
                    <li><a href="students.php"><i class="fas fa-user-graduate"></i><span>Students</span></a></li>
                    <li><a href="sections.php" class="active"><i class="fas fa-layer-group"></i><span>Sections</span></a></li>
                    <li><a href="reports.php"><i class="fas fa-chart-bar"></i><span>Reports</span></a></li>
                </ul>
            </div>
            <div class="menu-section">
                <h3>ACCOUNT</h3>
                <ul class="menu-items">
                    <li><a href="profile.php"><i class="fas fa-user"></i><span>Profile</span></a></li>
                    <li><a href="../auth/logout.php"><i class="fas fa-sign-out-alt"></i><span>Logout</span></a></li>
                </ul>
            </div>
        </div>

        <!-- Main Content -->
        <div class="main-content">
            <div class="dashboard-header">
                <div class="header-left">
                    <h1>Section Management</h1>
                    <p>Manage class sections and assign advisers</p>
                </div>
                <button class="btn-primary" onclick="openAddModal()">
                    <i class="fas fa-plus"></i> Add New Section
                </button>
            </div>

            <?php if($success_message): ?>
                <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?php echo $success_message; ?></div>
            <?php endif; ?>
            <?php if($error_message): ?>
                <div class="alert alert-error"><i class="fas fa-exclamation-circle"></i> <?php echo $error_message; ?></div>
            <?php endif; ?>

            <!-- Quick Stats -->
            <div class="stats-container">
                <?php
                $total_sections = $sections->num_rows;
                $total_students_in_sections = 0;
                $sections_with_adviser = 0;
                $sections->data_seek(0);
                while($sec = $sections->fetch_assoc()) {
                    $total_students_in_sections += $sec['student_count'];
                    if($sec['adviser_name']) $sections_with_adviser++;
                }
                $sections->data_seek(0);
                ?>
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-layer-group"></i></div>
                    <div class="stat-number"><?php echo $total_sections; ?></div>
                    <div class="stat-label">Total Sections</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-users"></i></div>
                    <div class="stat-number"><?php echo $total_students_in_sections; ?></div>
                    <div class="stat-label">Enrolled Students</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-chalkboard-teacher"></i></div>
                    <div class="stat-number"><?php echo $sections_with_adviser; ?></div>
                    <div class="stat-label">With Adviser</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-clock"></i></div>
                    <div class="stat-number"><?php echo date('Y'); ?></div>
                    <div class="stat-label">Current Year</div>
                </div>
            </div>

            <!-- Filters -->
            <div class="filters-card">
                <div class="filter-group">
                    <label>Filter by Grade Level</label>
                    <select id="gradeFilter">
                        <option value="">All Grades</option>
                        <?php 
                        $grade_levels->data_seek(0);
                        while($grade = $grade_levels->fetch_assoc()): 
                        ?>
                            <option value="<?php echo $grade['grade_name']; ?>"><?php echo $grade['grade_name']; ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="filter-group">
                    <label>Search Section</label>
                    <input type="text" id="searchInput" placeholder="Section name...">
                </div>
            </div>

            <!-- Sections Grid -->
            <div class="sections-grid" id="sectionsGrid">
                <?php if($sections && $sections->num_rows > 0): ?>
                    <?php while($section = $sections->fetch_assoc()): ?>
                        <div class="section-card" data-grade="<?php echo $section['grade_name']; ?>">
                            <div class="section-header">
                                <div class="section-icon"><i class="fas fa-users"></i></div>
                                <span class="section-badge"><?php echo $section['student_count']; ?> Students</span>
                            </div>
                            <div class="section-name"><?php echo htmlspecialchars($section['section_name']); ?></div>
                            <div class="grade-level"><i class="fas fa-layer-group"></i> <?php echo htmlspecialchars($section['grade_name']); ?></div>
                            
                            <div class="adviser-info">
                                <div class="adviser-avatar"><?php echo $section['adviser_name'] ? strtoupper(substr($section['adviser_name'], 0, 1)) : '?'; ?></div>
                                <div>
                                    <div style="font-size:12px; color:var(--text-secondary);">Class Adviser</div>
                                    <div style="font-weight:500;"><?php echo $section['adviser_name'] ?? 'Not Assigned'; ?></div>
                                </div>
                            </div>

                            <div class="stats-row">
                                <div class="stat-item">
                                    <div class="stat-value"><?php echo $section['student_count']; ?></div>
                                    <div class="stat-label">Students</div>
                                </div>
                                <div class="stat-item">
                                    <div class="stat-value">-</div>
                                    <div class="stat-label">Subjects</div>
                                </div>
                            </div>

                            <div class="action-buttons">
                                <a href="section_students.php?id=<?php echo $section['id']; ?>" class="btn-action btn-students">
                                    <i class="fas fa-users"></i> Students
                                </a>
                                <a href="?edit=<?php echo $section['id']; ?>" class="btn-action btn-edit">
                                    <i class="fas fa-edit"></i> Edit
                                </a>
                                <a href="?delete=<?php echo $section['id']; ?>" class="btn-action btn-delete" 
                                   onclick="return confirm('Delete this section? This will also delete all schedules.')">
                                    <i class="fas fa-trash"></i> Delete
                                </a>
                            </div>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div class="no-data">
                        <i class="fas fa-layer-group"></i>
                        <h3>No Sections Found</h3>
                        <p>Click "Add New Section" to create your first section.</p>
                    </div>
                <?php endif; ?>
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
            <form method="POST">
                <div class="modal-body">
                    <div class="form-group">
                        <label>Section Name</label>
                        <input type="text" name="section_name" placeholder="e.g., Grade 7 - Section A" required>
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
                        <label>Class Adviser</label>
                        <select name="adviser_id">
                            <option value="">Select Teacher (Optional)</option>
                            <?php 
                            $teachers->data_seek(0);
                            while($teacher = $teachers->fetch_assoc()): 
                            ?>
                                <option value="<?php echo $teacher['id']; ?>"><?php echo $teacher['fullname']; ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>School Year</label>
                        <select name="school_year">
                            <option value="<?php echo date('Y') . '-' . (date('Y')+1); ?>"><?php echo date('Y') . '-' . (date('Y')+1); ?></option>
                            <option value="<?php echo (date('Y')-1) . '-' . date('Y'); ?>"><?php echo (date('Y')-1) . '-' . date('Y'); ?></option>
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
    <?php if($edit_section): ?>
    <div class="modal active" id="editModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-edit"></i> Edit Section</h3>
                <a href="sections.php" class="close-modal">&times;</a>
            </div>
            <form method="POST">
                <input type="hidden" name="section_id" value="<?php echo $edit_section['id']; ?>">
                <div class="modal-body">
                    <div class="form-group">
                        <label>Section Name</label>
                        <input type="text" name="section_name" value="<?php echo htmlspecialchars($edit_section['section_name']); ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Grade Level</label>
                        <select name="grade_id" required>
                            <option value="">Select Grade Level</option>
                            <?php 
                            $grade_levels->data_seek(0);
                            while($grade = $grade_levels->fetch_assoc()): 
                                $selected = ($grade['id'] == $edit_section['grade_id']) ? 'selected' : '';
                            ?>
                                <option value="<?php echo $grade['id']; ?>" <?php echo $selected; ?>><?php echo $grade['grade_name']; ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Class Adviser</label>
                        <select name="adviser_id">
                            <option value="">Select Teacher (Optional)</option>
                            <?php 
                            $teachers->data_seek(0);
                            while($teacher = $teachers->fetch_assoc()): 
                                $selected = ($teacher['id'] == $edit_section['adviser_id']) ? 'selected' : '';
                            ?>
                                <option value="<?php echo $teacher['id']; ?>" <?php echo $selected; ?>><?php echo $teacher['fullname']; ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <a href="sections.php" class="btn-cancel">Cancel</a>
                    <button type="submit" name="edit_section" class="btn-save">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
    <?php endif; ?>

    <script>
        function openAddModal() {
            document.getElementById('addModal').classList.add('active');
        }

        function closeAddModal() {
            document.getElementById('addModal').classList.remove('active');
        }

        // Filter functionality
        document.getElementById('gradeFilter').addEventListener('change', filterSections);
        document.getElementById('searchInput').addEventListener('keyup', filterSections);

        function filterSections() {
            const gradeFilter = document.getElementById('gradeFilter').value.toLowerCase();
            const searchText = document.getElementById('searchInput').value.toLowerCase();
            const cards = document.querySelectorAll('.section-card');

            cards.forEach(card => {
                const grade = card.dataset.grade ? card.dataset.grade.toLowerCase() : '';
                const name = card.querySelector('.section-name').textContent.toLowerCase();
                
                const gradeMatch = !gradeFilter || grade.includes(gradeFilter);
                const searchMatch = !searchText || name.includes(searchText);
                
                card.style.display = (gradeMatch && searchMatch) ? 'block' : 'none';
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
    </script>
</body>
</html>