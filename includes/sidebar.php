<?php
// file: includes/sidebar.php (or add this to your existing sidebar)
// Make sure to include this at the top of your sidebar file
if (!isset($conn) && file_exists(__DIR__ . '/../config/database.php')) {
    require_once __DIR__ . '/../config/database.php';
    $database = new Database();
    $conn = $database->getConnection();
}

// Initialize StudentClassifier for stats
$sidebar_classifier = null;
$sidebar_stats = ['old' => 0, 'new' => 0];
if (file_exists(__DIR__ . '/StudentClassifier.php')) {
    require_once __DIR__ . '/StudentClassifier.php';
    $sidebar_classifier = new StudentClassifier($conn ?? null);
    $stats = $sidebar_classifier->getStudentStats();
    $sidebar_stats = [
        'old' => $stats['old_students'] ?? 0,
        'new' => $stats['new_students'] ?? 0
    ];
}
?>

<!-- Sidebar Navigation -->
<div class="sidebar">
    <div class="sidebar-header">
        <h2>
            <i class="fas fa-check-circle"></i>
            <span>PNHS</span>
        </h2>
    </div>

    <!-- User Info (dynamic based on role) -->
    <div class="user-info">
        <?php if(isset($_SESSION['user'])): ?>
            <div class="user-avatar">
                <?php echo strtoupper(substr($_SESSION['user']['fullname'] ?? 'U', 0, 1)); ?>
            </div>
            <div class="user-details">
                <h4><?php echo htmlspecialchars(explode(' ', ($_SESSION['user']['fullname'] ?? 'User'))[0]); ?></h4>
                <p><i class="fas fa-<?php 
                    echo $_SESSION['user']['role'] == 'Admin' ? 'crown' : 
                        ($_SESSION['user']['role'] == 'Registrar' ? 'user-tie' : 
                        ($_SESSION['user']['role'] == 'Teacher' ? 'chalkboard-teacher' : 'user-graduate')); 
                ?>"></i> <?php echo $_SESSION['user']['role'] ?? 'User'; ?></p>
            </div>
        <?php endif; ?>
    </div>

    <!-- Main Menu -->
    <div class="menu-section">
        <h3>MAIN MENU</h3>
        <ul class="menu-items">
            <!-- Dashboard -->
            <li>
                <a href="<?php 
                    echo $_SESSION['user']['role'] == 'Admin' ? '../admin/dashboard.php' : 
                        ($_SESSION['user']['role'] == 'Registrar' ? '../registrar/dashboard.php' : 
                        ($_SESSION['user']['role'] == 'Teacher' ? '../teacher/dashboard.php' : 
                        '../student/dashboard.php')); 
                ?>" class="<?php echo basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'active' : ''; ?>">
                    <i class="fas fa-tachometer-alt"></i>
                    <span>Dashboard</span>
                </a>
            </li>

            <!-- Student Management (for Admin and Registrar) -->
            <?php if(in_array($_SESSION['user']['role'] ?? '', ['Admin', 'Registrar'])): ?>
            <li>
                <a href="<?php echo $_SESSION['user']['role'] == 'Admin' ? '../admin/students.php' : '../registrar/students.php'; ?>" 
                   class="<?php echo basename($_SERVER['PHP_SELF']) == 'students.php' ? 'active' : ''; ?>">
                    <i class="fas fa-user-graduate"></i>
                    <span>Student Management</span>
                    <?php if($sidebar_stats['old'] > 0 || $sidebar_stats['new'] > 0): ?>
                        <div class="menu-badges">
                            <span class="badge old-badge" title="Old Students">
                                <i class="fas fa-undo-alt"></i> <?php echo $sidebar_stats['old']; ?>
                            </span>
                            <span class="badge new-badge" title="New Students">
                                <i class="fas fa-star"></i> <?php echo $sidebar_stats['new']; ?>
                            </span>
                        </div>
                    <?php endif; ?>
                </a>
            </li>
            <?php endif; ?>

            <!-- Enrollments (for Admin and Registrar) -->
            <?php if(in_array($_SESSION['user']['role'] ?? '', ['Admin', 'Registrar'])): ?>
            <li>
                <a href="<?php echo $_SESSION['user']['role'] == 'Admin' ? '../admin/enrollments.php' : '../registrar/enrollments.php'; ?>" 
                   class="<?php echo basename($_SERVER['PHP_SELF']) == 'enrollments.php' ? 'active' : ''; ?>">
                    <i class="fas fa-file-signature"></i>
                    <span>Enrollments</span>
                </a>
            </li>
            <?php endif; ?>

            <!-- Sections (for Admin and Registrar) -->
            <?php if(in_array($_SESSION['user']['role'] ?? '', ['Admin', 'Registrar'])): ?>
            <li>
                <a href="<?php echo $_SESSION['user']['role'] == 'Admin' ? '../admin/sections.php' : '../registrar/sections.php'; ?>" 
                   class="<?php echo basename($_SERVER['PHP_SELF']) == 'sections.php' ? 'active' : ''; ?>">
                    <i class="fas fa-layer-group"></i>
                    <span>Sections</span>
                </a>
            </li>
            <?php endif; ?>

            <!-- Subjects (for Admin and Registrar) -->
            <?php if(in_array($_SESSION['user']['role'] ?? '', ['Admin', 'Registrar'])): ?>
            <li>
                <a href="<?php echo $_SESSION['user']['role'] == 'Admin' ? '../admin/subjects.php' : '../registrar/subjects.php'; ?>" 
                   class="<?php echo basename($_SERVER['PHP_SELF']) == 'subjects.php' ? 'active' : ''; ?>">
                    <i class="fas fa-book"></i>
                    <span>Subjects</span>
                </a>
            </li>
            <?php endif; ?>

            <!-- Teachers (for Admin and Registrar) -->
            <?php if(in_array($_SESSION['user']['role'] ?? '', ['Admin', 'Registrar'])): ?>
            <li>
                <a href="<?php echo $_SESSION['user']['role'] == 'Admin' ? '../admin/teachers.php' : '../registrar/teachers.php'; ?>" 
                   class="<?php echo basename($_SERVER['PHP_SELF']) == 'teachers.php' ? 'active' : ''; ?>">
                    <i class="fas fa-chalkboard-teacher"></i>
                    <span>Teachers</span>
                </a>
            </li>
            <?php endif; ?>

            <!-- Student Menu Items -->
            <?php if(($_SESSION['user']['role'] ?? '') == 'Student'): ?>
            <li>
                <a href="../student/profile.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'profile.php' ? 'active' : ''; ?>">
                    <i class="fas fa-user"></i>
                    <span>My Profile</span>
                </a>
            </li>
            <li>
                <a href="../student/schedule.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'schedule.php' ? 'active' : ''; ?>">
                    <i class="fas fa-calendar-alt"></i>
                    <span>Class Schedule</span>
                </a>
            </li>
            <li>
                <a href="../student/grades.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'grades.php' ? 'active' : ''; ?>">
                    <i class="fas fa-star"></i>
                    <span>My Grades</span>
                </a>
            </li>
            <li>
                <a href="../student/attendance.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'attendance.php' ? 'active' : ''; ?>">
                    <i class="fas fa-calendar-check"></i>
                    <span>Attendance</span>
                </a>
            </li>
            <li>
                <a href="../student/enrollment_history.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'enrollment_history.php' ? 'active' : ''; ?>">
                    <i class="fas fa-history"></i>
                    <span>Enrollment History</span>
                    <?php 
                    if(isset($_SESSION['user']['id']) && $sidebar_classifier) {
                        $student_type = $sidebar_classifier->getStudentType($_SESSION['user']['id']);
                        $badge_color = $student_type == 'Old Student' ? '#28a745' : '#007bff';
                        $badge_icon = $student_type == 'Old Student' ? 'fa-undo-alt' : 'fa-star';
                        ?>
                        <span class="badge" style="background: <?php echo $badge_color; ?>; color: white; margin-left: 5px; font-size: 10px;">
                            <i class="fas <?php echo $badge_icon; ?>"></i>
                        </span>
                        <?php
                    }
                    ?>
                </a>
            </li>
            <?php endif; ?>

            <!-- Teacher Menu Items -->
            <?php if(($_SESSION['user']['role'] ?? '') == 'Teacher'): ?>
            <li>
                <a href="../teacher/classes.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'classes.php' ? 'active' : ''; ?>">
                    <i class="fas fa-chalkboard"></i>
                    <span>My Classes</span>
                </a>
            </li>
            <li>
                <a href="../teacher/attendance.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'attendance.php' ? 'active' : ''; ?>">
                    <i class="fas fa-calendar-check"></i>
                    <span>Take Attendance</span>
                </a>
            </li>
            <li>
                <a href="../teacher/grades.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'grades.php' ? 'active' : ''; ?>">
                    <i class="fas fa-chart-line"></i>
                    <span>Grade Management</span>
                </a>
            </li>
            <li>
                <a href="../teacher/schedule.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'schedule.php' ? 'active' : ''; ?>">
                    <i class="fas fa-clock"></i>
                    <span>My Schedule</span>
                </a>
            </li>
            <?php endif; ?>
        </ul>
    </div>

    <!-- Reports Menu (for Admin and Registrar) -->
    <?php if(in_array($_SESSION['user']['role'] ?? '', ['Admin', 'Registrar'])): ?>
    <div class="menu-section">
        <h3>REPORTS</h3>
        <ul class="menu-items">
            <li>
                <a href="<?php echo $_SESSION['user']['role'] == 'Admin' ? '../admin/reports.php' : '../registrar/reports.php'; ?>" 
                   class="<?php echo basename($_SERVER['PHP_SELF']) == 'reports.php' ? 'active' : ''; ?>">
                    <i class="fas fa-chart-bar"></i>
                    <span>Reports</span>
                </a>
            </li>
            <li>
                <a href="<?php echo $_SESSION['user']['role'] == 'Admin' ? '../admin/statistics.php' : '../registrar/statistics.php'; ?>" 
                   class="<?php echo basename($_SERVER['PHP_SELF']) == 'statistics.php' ? 'active' : ''; ?>">
                    <i class="fas fa-chart-pie"></i>
                    <span>Statistics</span>
                </a>
            </li>
        </ul>
    </div>
    <?php endif; ?>

    <!-- Account Menu -->
    <div class="menu-section">
        <h3>ACCOUNT</h3>
        <ul class="menu-items">
            <li>
                <a href="<?php 
                    echo $_SESSION['user']['role'] == 'Admin' ? '../admin/profile.php' : 
                        ($_SESSION['user']['role'] == 'Registrar' ? '../registrar/profile.php' : 
                        ($_SESSION['user']['role'] == 'Teacher' ? '../teacher/profile.php' : 
                        '../student/profile.php')); 
                ?>" class="<?php echo basename($_SERVER['PHP_SELF']) == 'profile.php' ? 'active' : ''; ?>">
                    <i class="fas fa-user-cog"></i>
                    <span>Profile</span>
                </a>
            </li>
            <li>
                <a href="../auth/logout.php">
                    <i class="fas fa-sign-out-alt"></i>
                    <span>Logout</span>
                </a>
            </li>
        </ul>
    </div>
</div>

<style>
/* Sidebar Styles */
.sidebar {
    width: 280px;
    background: linear-gradient(135deg, #0B4F2E, #1a7a42);
    color: white;
    padding: 20px;
    position: fixed;
    height: 100vh;
    overflow-y: auto;
    box-shadow: 4px 0 10px rgba(0, 0, 0, 0.1);
    z-index: 1000;
}

.sidebar-header h2 {
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

.sidebar-header h2 i {
    color: #FFD700;
}

.user-info {
    text-align: center;
    padding: 20px 0;
    border-bottom: 1px solid rgba(255, 255, 255, 0.2);
    margin-bottom: 20px;
}

.user-avatar {
    width: 70px;
    height: 70px;
    background: #FFD700;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 10px;
    font-size: 28px;
    font-weight: bold;
    color: #0B4F2E;
    border: 3px solid white;
}

.user-details h4 {
    font-size: 16px;
    margin-bottom: 5px;
    color: #FFD700;
}

.user-details p {
    font-size: 13px;
    opacity: 0.9;
}

.user-details p i {
    color: #FFD700;
    margin-right: 5px;
}

.menu-section {
    margin-bottom: 25px;
}

.menu-section h3 {
    font-size: 12px;
    text-transform: uppercase;
    letter-spacing: 1px;
    color: rgba(255, 255, 255, 0.5);
    margin-bottom: 10px;
    padding-left: 10px;
}

.menu-items {
    list-style: none;
    padding: 0;
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
    position: relative;
}

.menu-items a:hover {
    background: rgba(255, 255, 255, 0.1);
    color: white;
    transform: translateX(5px);
}

.menu-items a.active {
    background: rgba(255, 255, 255, 0.15);
    color: white;
    border-left: 3px solid #FFD700;
}

.menu-items a i {
    width: 20px;
    font-size: 1.1em;
    color: #FFD700;
}

.menu-badges {
    margin-left: auto;
    display: flex;
    gap: 5px;
}

.menu-badges .badge {
    padding: 3px 8px;
    border-radius: 12px;
    font-size: 10px;
    font-weight: 600;
    display: inline-flex;
    align-items: center;
    gap: 3px;
}

.menu-badges .old-badge {
    background: #28a745;
    color: white;
}

.menu-badges .new-badge {
    background: #007bff;
    color: white;
}

/* Scrollbar styling */
.sidebar::-webkit-scrollbar {
    width: 5px;
}

.sidebar::-webkit-scrollbar-track {
    background: rgba(255, 255, 255, 0.1);
}

.sidebar::-webkit-scrollbar-thumb {
    background: rgba(255, 255, 255, 0.3);
    border-radius: 5px;
}

.sidebar::-webkit-scrollbar-thumb:hover {
    background: rgba(255, 255, 255, 0.5);
}

/* Responsive */
@media (max-width: 768px) {
    .sidebar {
        width: 80px;
        padding: 20px 10px;
    }
    
    .sidebar-header h2 span,
    .user-details,
    .menu-section h3,
    .menu-items a span,
    .menu-badges {
        display: none;
    }
    
    .user-avatar {
        width: 50px;
        height: 50px;
        font-size: 20px;
    }
    
    .menu-items a {
        justify-content: center;
        padding: 15px;
    }
    
    .menu-items a i {
        margin: 0;
    }
}
</style>