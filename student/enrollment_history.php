<?php
session_start();
include("../config/database.php");
require_once '../includes/StudentClassifier.php';

if(!isset($_SESSION['user']) || $_SESSION['user']['role'] != 'Student'){
    header("Location: ../auth/login.php");
    exit();
}

$student_id = $_SESSION['user']['id'];
$student_name = $_SESSION['user']['fullname'];
$classifier = new StudentClassifier($conn);
$enrollment_history = $classifier->getEnrollmentHistory($student_id);
$student_type = $classifier->getStudentTypeBadge($student_id);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Enrollment History - Student Dashboard</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
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
            color: #2b2d42;
        }

        .back-btn {
            background: #0B4F2E;
            color: white;
            padding: 10px 20px;
            border-radius: 10px;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s;
        }

        .back-btn:hover {
            background: #1a7a42;
            transform: translateY(-2px);
        }

        .student-type-banner {
            background: <?php echo $student_type['color']; ?>;
            color: white;
            padding: 20px 30px;
            border-radius: 15px;
            margin-bottom: 30px;
            display: flex;
            align-items: center;
            gap: 20px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }

        .student-type-banner i {
            font-size: 40px;
        }

        .banner-content h2 {
            font-size: 24px;
            margin-bottom: 5px;
            font-weight: 600;
        }

        .banner-content p {
            opacity: 0.9;
            font-size: 14px;
        }

        .history-card {
            background: white;
            border-radius: 20px;
            padding: 25px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }

        .history-item {
            display: flex;
            align-items: center;
            gap: 20px;
            padding: 20px;
            border-bottom: 1px solid #e9ecef;
            transition: all 0.3s;
        }

        .history-item:last-child {
            border-bottom: none;
        }

        .history-item:hover {
            background: #f8f9fa;
            transform: translateX(5px);
        }

        .year-badge {
            background: #0B4F2E;
            color: white;
            padding: 10px 20px;
            border-radius: 12px;
            font-weight: 600;
            min-width: 120px;
            text-align: center;
        }

        .history-details {
            flex: 1;
        }

        .history-details h3 {
            color: #2b2d42;
            margin-bottom: 5px;
            font-size: 16px;
        }

        .history-details p {
            color: #8d99ae;
            font-size: 13px;
            display: flex;
            align-items: center;
            gap: 15px;
            flex-wrap: wrap;
        }

        .history-details p i {
            width: 16px;
            color: #0B4F2E;
        }

        .status-badge {
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            min-width: 100px;
            text-align: center;
        }

        .status-Enrolled {
            background: rgba(40, 167, 69, 0.1);
            color: #28a745;
            border: 1px solid rgba(40, 167, 69, 0.2);
        }

        .status-Pending {
            background: rgba(255, 193, 7, 0.1);
            color: #ffc107;
            border: 1px solid rgba(255, 193, 7, 0.2);
        }

        .status-Rejected {
            background: rgba(220, 53, 69, 0.1);
            color: #dc3545;
            border: 1px solid rgba(220, 53, 69, 0.2);
        }

        .current-badge {
            background: <?php echo $student_type['color']; ?>;
            color: white;
            padding: 3px 10px;
            border-radius: 20px;
            font-size: 11px;
            margin-left: 10px;
            display: inline-block;
        }

        .no-data {
            text-align: center;
            padding: 60px;
            color: #8d99ae;
        }

        .no-data i {
            font-size: 60px;
            margin-bottom: 20px;
            opacity: 0.3;
        }

        .no-data h3 {
            color: #2b2d42;
            margin-bottom: 10px;
        }

        .stats-summary {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-summary-card {
            background: white;
            border-radius: 15px;
            padding: 20px;
            text-align: center;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }

        .stat-summary-card .number {
            font-size: 28px;
            font-weight: 700;
            color: #0B4F2E;
        }

        .stat-summary-card .label {
            color: #8d99ae;
            font-size: 13px;
            margin-top: 5px;
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

            .header {
                flex-direction: column;
                gap: 15px;
                align-items: flex-start;
            }

            .student-type-banner {
                flex-direction: column;
                text-align: center;
                padding: 20px;
            }

            .history-item {
                flex-direction: column;
                text-align: center;
            }

            .stats-summary {
                grid-template-columns: 1fr;
            }

            .history-details p {
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
                    <li><a href="profile.php"><i class="fas fa-user"></i> <span>My Profile</span></a></li>
                    <li><a href="schedule.php"><i class="fas fa-calendar-alt"></i> <span>Class Schedule</span></a></li>
                    <li><a href="grades.php"><i class="fas fa-star"></i> <span>My Grades</span></a></li>
                    <li><a href="attendance.php"><i class="fas fa-calendar-check"></i> <span>Attendance</span></a></li>
                    <li><a href="enrollment_history.php" class="active"><i class="fas fa-history"></i> <span>Enrollment History</span></a></li>
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
            <div class="header">
                <h1><i class="fas fa-history" style="color: #0B4F2E;"></i> Enrollment History</h1>
                <a href="dashboard.php" class="back-btn">
                    <i class="fas fa-arrow-left"></i> Back to Dashboard
                </a>
            </div>

            <!-- Student Type Banner -->
            <div class="student-type-banner">
                <i class="fas <?php echo $student_type['type'] == 'Old Student' ? 'fa-undo-alt' : 'fa-star'; ?>"></i>
                <div class="banner-content">
                    <h2><?php echo $student_type['type']; ?></h2>
                    <p>
                        <?php if(count($enrollment_history) > 0): ?>
                            First enrolled in <?php echo end($enrollment_history)['school_year']; ?> • 
                            Total of <?php echo count($enrollment_history); ?> enrollment(s)
                        <?php else: ?>
                            This is your first enrollment
                        <?php endif; ?>
                    </p>
                </div>
            </div>

            <!-- Stats Summary -->
            <?php if(!empty($enrollment_history)): ?>
            <div class="stats-summary">
                <div class="stat-summary-card">
                    <div class="number"><?php echo count($enrollment_history); ?></div>
                    <div class="label">Total Enrollments</div>
                </div>
                <div class="stat-summary-card">
                    <div class="number">
                        <?php 
                        $enrolled_count = 0;
                        foreach($enrollment_history as $e) {
                            if($e['status'] == 'Enrolled') $enrolled_count++;
                        }
                        echo $enrolled_count;
                        ?>
                    </div>
                    <div class="label">Approved</div>
                </div>
                <div class="stat-summary-card">
                    <div class="number">
                        <?php 
                        $years = array_unique(array_column($enrollment_history, 'school_year'));
                        echo count($years);
                        ?>
                    </div>
                    <div class="label">School Years</div>
                </div>
            </div>
            <?php endif; ?>

            <!-- History List -->
            <div class="history-card">
                <?php if(empty($enrollment_history)): ?>
                    <div class="no-data">
                        <i class="fas fa-file-signature"></i>
                        <h3>No Enrollment History</h3>
                        <p>You haven't made any enrollments yet.</p>
                        <a href="enrollment.php" style="display: inline-block; margin-top: 20px; padding: 10px 25px; background: #0B4F2E; color: white; text-decoration: none; border-radius: 10px;">
                            Enroll Now
                        </a>
                    </div>
                <?php else: ?>
                    <?php foreach($enrollment_history as $index => $enrollment): ?>
                        <div class="history-item">
                            <div class="year-badge">
                                <?php echo htmlspecialchars($enrollment['school_year']); ?>
                            </div>
                            <div class="history-details">
                                <h3>
                                    Grade <?php echo htmlspecialchars($enrollment['grade_name'] ?? 'N/A'); ?>
                                    <?php if($enrollment['section_name']): ?>
                                        - Section <?php echo htmlspecialchars($enrollment['section_name']); ?>
                                    <?php endif; ?>
                                    <?php if($index == 0): ?>
                                        <span class="current-badge">Current</span>
                                    <?php endif; ?>
                                </h3>
                                <p>
                                    <span><i class="fas fa-calendar"></i> <?php echo date('F d, Y', strtotime($enrollment['created_at'])); ?></span>
                                    <?php if($enrollment['strand']): ?>
                                        <span><i class="fas fa-tag"></i> Strand: <?php echo htmlspecialchars($enrollment['strand']); ?></span>
                                    <?php endif; ?>
                                </p>
                            </div>
                            <div class="status-badge status-<?php echo $enrollment['status']; ?>">
                                <?php echo $enrollment['status']; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>