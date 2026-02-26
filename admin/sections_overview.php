<?php
session_start();
include("../config/database.php");

// Check if user is admin
if(!isset($_SESSION['user']) || $_SESSION['user']['role'] != 'Admin'){
    header("Location: ../auth/login.php");
    exit();
}

$admin_name = $_SESSION['user']['fullname'];

// Get all sections with details
$sections = $conn->query("
    SELECT s.*, g.grade_name, u.fullname as adviser_name,
           (SELECT COUNT(*) FROM class_schedules WHERE section_id = s.id) as schedule_count
    FROM sections s 
    LEFT JOIN grade_levels g ON s.grade_id = g.id
    LEFT JOIN users u ON s.adviser_id = u.id
    ORDER BY g.id, s.section_name
");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sections Overview - Admin Dashboard</title>
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

        .app-container {
            display: flex;
            min-height: 100vh;
        }

        .sidebar {
            width: 280px;
            background: linear-gradient(135deg, #0B4F2E, #1a7a42);
            color: white;
            padding: 30px 20px;
            position: fixed;
            height: 100vh;
            overflow-y: auto;
        }

        .sidebar h2 {
            font-size: 24px;
            margin-bottom: 30px;
            display: flex;
            align-items: center;
            gap: 10px;
            color: #fff;
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
            color: #0B4F2E;
            border: 3px solid white;
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
        }

        .menu-items a:hover,
        .menu-items a.active {
            background: rgba(255, 255, 255, 0.1);
            color: white;
        }

        .menu-items a i {
            color: #FFD700;
        }

        .main-content {
            flex: 1;
            margin-left: 280px;
            padding: 30px;
        }

        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
        }

        .header h1 {
            font-size: 28px;
            color: var(--text-primary);
        }

        .header p {
            color: var(--text-secondary);
        }

        .sections-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 25px;
            margin-top: 30px;
        }

        .section-card {
            background: white;
            border-radius: 20px;
            padding: 25px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.05);
            transition: all 0.3s ease;
            border: 1px solid var(--border-color);
        }

        .section-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(11, 79, 46, 0.1);
            border-color: #0B4F2E;
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
            background: linear-gradient(135deg, #0B4F2E, #1a7a42);
            border-radius: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 24px;
        }

        .section-badge {
            background: #f0f0f0;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 12px;
            color: var(--text-secondary);
        }

        .section-name {
            font-size: 20px;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 5px;
        }

        .grade-level {
            color: #0B4F2E;
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
            background: #FFD700;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #0B4F2E;
            font-weight: 600;
        }

        .stats-row {
            display: flex;
            justify-content: space-between;
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

        .btn {
            flex: 1;
            padding: 12px;
            border: none;
            border-radius: 10px;
            font-weight: 600;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            text-decoration: none;
            transition: all 0.3s;
        }

        .btn-primary {
            background: #0B4F2E;
            color: white;
        }

        .btn-primary:hover {
            background: #1a7a42;
            transform: translateY(-2px);
        }

        .btn-secondary {
            background: #f0f0f0;
            color: var(--text-primary);
        }

        .btn-secondary:hover {
            background: #e0e0e0;
        }

        .no-data {
            text-align: center;
            padding: 60px;
            background: white;
            border-radius: 20px;
            color: var(--text-secondary);
        }

        .no-data i {
            font-size: 60px;
            margin-bottom: 20px;
            opacity: 0.3;
        }

        @media (max-width: 768px) {
            .sidebar {
                width: 80px;
            }
            
            .sidebar h2 span,
            .admin-info h3,
            .admin-info p,
            .menu-items a span {
                display: none;
            }
            
            .main-content {
                margin-left: 80px;
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
            <div class="admin-info">
                <div class="admin-avatar"><?php echo strtoupper(substr($admin_name, 0, 1)); ?></div>
                <h3><?php echo htmlspecialchars(explode(' ', $admin_name)[0]); ?></h3>
                <p>Administrator</p>
            </div>
            <ul class="menu-items">
                <li><a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> <span>Dashboard</span></a></li>
                <li><a href="sections_overview.php" class="active"><i class="fas fa-layer-group"></i> <span>Sections</span></a></li>
                <li><a href="subjects.php"><i class="fas fa-book"></i> <span>Subjects</span></a></li>
                <li><a href="teachers.php"><i class="fas fa-chalkboard-teacher"></i> <span>Teachers</span></a></li>
                <li><a href="students.php"><i class="fas fa-user-graduate"></i> <span>Students</span></a></li>
            </ul>
        </div>

        <!-- Main Content -->
        <div class="main-content">
            <div class="header">
                <div>
                    <h1>Class Sections</h1>
                    <p>Manage sections and create class schedules</p>
                </div>
                <a href="sections.php" class="btn btn-primary">
                    <i class="fas fa-plus"></i> Manage Sections
                </a>
            </div>

            <div class="sections-grid">
                <?php if($sections && $sections->num_rows > 0): ?>
                    <?php while($section = $sections->fetch_assoc()): ?>
                        <div class="section-card">
                            <div class="section-header">
                                <div class="section-icon">
                                    <i class="fas fa-users"></i>
                                </div>
                                <span class="section-badge">
                                    <?php echo $section['schedule_count'] ?? 0; ?> Subjects
                                </span>
                            </div>
                            
                            <div class="section-name">
                                <?php echo htmlspecialchars($section['section_name']); ?>
                            </div>
                            
                            <div class="grade-level">
                                <i class="fas fa-layer-group"></i>
                                <?php echo htmlspecialchars($section['grade_name']); ?>
                            </div>
                            
                            <div class="adviser-info">
                                <div class="adviser-avatar">
                                    <?php echo $section['adviser_name'] ? strtoupper(substr($section['adviser_name'], 0, 1)) : '?'; ?>
                                </div>
                                <div>
                                    <div style="font-size: 12px; color: var(--text-secondary);">Class Adviser</div>
                                    <div style="font-weight: 500;">
                                        <?php echo $section['adviser_name'] ?? 'Not Assigned'; ?>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="stats-row">
                                <div class="stat-item">
                                    <div class="stat-value"><?php echo $section['schedule_count'] ?? 0; ?></div>
                                    <div class="stat-label">Subjects</div>
                                </div>
                                <div class="stat-item">
                                    <div class="stat-value">--</div>
                                    <div class="stat-label">Students</div>
                                </div>
                                <div class="stat-item">
                                    <div class="stat-value">--</div>
                                    <div class="stat-label">Teachers</div>
                                </div>
                            </div>
                            
                            <div class="action-buttons">
                                <a href="section_schedule.php?section_id=<?php echo $section['id']; ?>" class="btn btn-primary">
                                    <i class="fas fa-calendar-alt"></i> View Schedule
                                </a>
                                <a href="create_schedule.php?section_id=<?php echo $section['id']; ?>" class="btn btn-secondary">
                                    <i class="fas fa-plus"></i> Add Subject
                                </a>
                            </div>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div class="no-data">
                        <i class="fas fa-layer-group"></i>
                        <h3>No Sections Found</h3>
                        <p>Create your first section to start building schedules.</p>
                        <a href="sections.php" class="btn btn-primary" style="display: inline-block; margin-top: 20px;">
                            <i class="fas fa-plus"></i> Create Section
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>