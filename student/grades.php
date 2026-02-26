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

$grade_id = $enrollment ? $enrollment['grade_id'] : null;
$grade_name = $enrollment ? $enrollment['grade_name'] : 'Not Enrolled';
$strand = $enrollment ? ($enrollment['strand'] ?? 'N/A') : 'N/A';
$school_year = $enrollment ? $enrollment['school_year'] : 'N/A';
$enrollment_status = $enrollment ? $enrollment['status'] : 'Not Enrolled';

// Get all subjects for student's grade level
$subjects_list = [];
if($grade_id) {
    $subjects_query = "
        SELECT * FROM subjects 
        WHERE grade_id = ? 
        ORDER BY subject_name
    ";
    $stmt = $conn->prepare($subjects_query);
    $stmt->bind_param("i", $grade_id);
    $stmt->execute();
    $subjects = $stmt->get_result();
    while($subject = $subjects->fetch_assoc()) {
        $subjects_list[] = $subject;
    }
    $stmt->close();
}

// Since there's no grades table, we'll create sample data for demonstration
// In a real application, you would have a grades table
$has_grades = false; // Set to false since no grades table exists

// For demo purposes, you can uncomment this to show sample grades
// $has_grades = true;
// $sample_grades = [];
// foreach($subjects_list as $index => $subject) {
//     $sample_grades[$subject['id']] = [
//         '1st Quarter' => rand(75, 95),
//         '2nd Quarter' => rand(75, 95),
//         '3rd Quarter' => rand(75, 95),
//         '4th Quarter' => rand(75, 95)
//     ];
// }

// Calculate statistics if grades exist
$total_subjects = count($subjects_list);
$overall_average = 0;
$passing_count = 0;
$failing_count = 0;

// if($has_grades) {
//     $total_grades = 0;
//     $grade_count = 0;
//     foreach($sample_grades as $subject_id => $quarters) {
//         $subject_total = 0;
//         foreach($quarters as $grade) {
//             $subject_total += $grade;
//             $total_grades += $grade;
//             $grade_count++;
//         }
//         $subject_avg = $subject_total / 4;
//         if($subject_avg >= 75) $passing_count++; else $failing_count++;
//     }
//     $overall_average = $grade_count > 0 ? round($total_grades / $grade_count, 2) : 0;
// }

// Define grading scale
function getGradeColor($grade) {
    if($grade >= 90) return '#28a745';
    if($grade >= 80) return '#5cb85c';
    if($grade >= 75) return '#f0ad4e';
    return '#d9534f';
}

function getGradeRemarks($grade) {
    if($grade >= 90) return ['Excellent', '#28a745'];
    if($grade >= 85) return ['Very Good', '#5cb85c'];
    if($grade >= 80) return ['Good', '#5bc0de'];
    if($grade >= 75) return ['Satisfactory', '#f0ad4e'];
    if($grade >= 70) return ['Fair', '#f39c12'];
    return ['Needs Improvement', '#d9534f'];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Grades - Student Dashboard</title>
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

        /* Class Info Card */
        .class-info-card {
            background: linear-gradient(135deg, #0B4F2E, #1a7a42);
            border-radius: 20px;
            padding: 25px;
            color: white;
            margin-bottom: 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 20px;
        }

        .class-info-details h3 {
            font-size: 20px;
            font-weight: 600;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .class-info-details h3 i {
            color: #FFD700;
        }

        .class-info-badges {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
        }

        .info-badge {
            background: rgba(255, 255, 255, 0.2);
            padding: 8px 16px;
            border-radius: 30px;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .info-badge i {
            color: #FFD700;
        }

        .school-year {
            font-size: 18px;
            font-weight: 600;
            padding: 10px 20px;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 30px;
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

        /* No Grades Card */
        .no-grades-card {
            background: white;
            border-radius: 20px;
            padding: 60px;
            text-align: center;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            margin-top: 30px;
        }

        .no-grades-card i {
            font-size: 80px;
            color: #FFD700;
            margin-bottom: 20px;
            opacity: 0.5;
        }

        .no-grades-card h3 {
            color: var(--text-primary);
            font-size: 24px;
            margin-bottom: 10px;
        }

        .no-grades-card p {
            color: var(--text-secondary);
            margin-bottom: 30px;
            max-width: 500px;
            margin-left: auto;
            margin-right: auto;
        }

        .info-message {
            background: #e8f4fd;
            border-left: 4px solid #0B4F2E;
            padding: 20px;
            border-radius: 10px;
            margin: 20px 0;
            display: flex;
            align-items: center;
            gap: 15px;
            text-align: left;
        }

        .info-message i {
            font-size: 30px;
            color: #0B4F2E;
        }

        .info-message p {
            color: var(--text-primary);
            font-size: 15px;
            margin: 0;
        }

        /* Subjects List */
        .subjects-card {
            background: white;
            border-radius: 20px;
            padding: 25px;
            margin-top: 30px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
        }

        .subjects-card h3 {
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

        .subjects-card h3 i {
            color: #0B4F2E;
        }

        .subjects-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 15px;
        }

        .subject-item {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            gap: 12px;
            transition: all 0.3s;
        }

        .subject-item:hover {
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }

        .subject-icon {
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

        .subject-info {
            flex: 1;
        }

        .subject-info h4 {
            color: var(--text-primary);
            font-size: 15px;
            margin-bottom: 3px;
        }

        .subject-info p {
            color: var(--text-secondary);
            font-size: 12px;
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
            
            .stats-container {
                grid-template-columns: 1fr;
            }
            
            .class-info-card {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .info-message {
                flex-direction: column;
                text-align: center;
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
                    <li><a href="grades.php" class="active"><i class="fas fa-star"></i> <span>My Grades</span></a></li>
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
                <h1>My Grades</h1>
                <p>View your academic performance and grades</p>
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

            <?php if($enrollment): ?>
                <!-- Class Info Card -->
                <div class="class-info-card">
                    <div class="class-info-details">
                        <h3>
                            <i class="fas fa-graduation-cap"></i>
                            <?php echo htmlspecialchars($grade_name); ?>
                        </h3>
                        <div class="class-info-badges">
                            <?php if($strand != 'N/A'): ?>
                                <span class="info-badge">
                                    <i class="fas fa-tag"></i> Strand: <?php echo htmlspecialchars($strand); ?>
                                </span>
                            <?php endif; ?>
                            <span class="info-badge">
                                <i class="fas fa-check-circle"></i> Status: <?php echo htmlspecialchars($enrollment_status); ?>
                            </span>
                        </div>
                    </div>
                    <div class="school-year">
                        <i class="fas fa-calendar"></i> S.Y. <?php echo htmlspecialchars($school_year); ?>
                    </div>
                </div>

                <!-- Statistics Cards -->
                <div class="stats-container">
                    <div class="stat-card">
                        <div class="stat-header">
                            <h3>Enrollment Status</h3>
                            <div class="stat-icon">
                                <i class="fas fa-user-check"></i>
                            </div>
                        </div>
                        <div class="stat-number" style="font-size: 20px; color: #28a745;">
                            <?php echo $enrollment_status; ?>
                        </div>
                        <div class="stat-label">Current Status</div>
                    </div>

                    <div class="stat-card">
                        <div class="stat-header">
                            <h3>Grade Level</h3>
                            <div class="stat-icon">
                                <i class="fas fa-layer-group"></i>
                            </div>
                        </div>
                        <div class="stat-number" style="font-size: 24px;">
                            <?php echo htmlspecialchars($grade_name); ?>
                        </div>
                        <div class="stat-label">Current Grade</div>
                    </div>

                    <div class="stat-card">
                        <div class="stat-header">
                            <h3>Subjects</h3>
                            <div class="stat-icon">
                                <i class="fas fa-book"></i>
                            </div>
                        </div>
                        <div class="stat-number"><?php echo $total_subjects; ?></div>
                        <div class="stat-label">Enrolled Subjects</div>
                    </div>

                    <div class="stat-card">
                        <div class="stat-header">
                            <h3>School Year</h3>
                            <div class="stat-icon">
                                <i class="fas fa-calendar"></i>
                            </div>
                        </div>
                        <div class="stat-number" style="font-size: 20px;">
                            <?php echo htmlspecialchars($school_year); ?>
                        </div>
                        <div class="stat-label">Current SY</div>
                    </div>
                </div>

                <!-- No Grades Available Message -->
                <div class="no-grades-card">
                    <i class="fas fa-star"></i>
                    <h3>No Grades Available Yet</h3>
                    <p>Your grades have not been posted for this grading period. Please check back later or contact your subject teachers.</p>
                    
                    <div class="info-message">
                        <i class="fas fa-info-circle"></i>
                        <div>
                            <p><strong>Note:</strong> Grades are typically posted after each quarter. If you believe this is an error, please contact the registrar's office or your class adviser.</p>
                        </div>
                    </div>
                </div>

                <!-- Your Subjects List -->
                <div class="subjects-card">
                    <h3><i class="fas fa-book-open"></i> Your Subjects (<?php echo $total_subjects; ?>)</h3>
                    <div class="subjects-grid">
                        <?php if(!empty($subjects_list)): ?>
                            <?php foreach($subjects_list as $subject): ?>
                                <div class="subject-item">
                                    <div class="subject-icon">
                                        <i class="fas fa-book"></i>
                                    </div>
                                    <div class="subject-info">
                                        <h4><?php echo htmlspecialchars($subject['subject_name']); ?></h4>
                                        <p>Subject ID: <?php echo $subject['id']; ?></p>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="no-data" style="grid-column: 1/-1; padding: 30px;">
                                <i class="fas fa-book"></i>
                                <p>No subjects found for your grade level.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

            <?php else: ?>
                <!-- No Enrollment Message -->
                <div class="no-grades-card">
                    <i class="fas fa-graduation-cap"></i>
                    <h3>Not Enrolled</h3>
                    <p>You are not currently enrolled in any grade level. Please contact the registrar's office for assistance.</p>
                    
                    <div class="info-message">
                        <i class="fas fa-phone-alt"></i>
                        <div>
                            <p><strong>Registrar's Office</strong><br>
                            📞 (032) 123-4567<br>
                            📧 registrar@plshs.edu.ph<br>
                            📍 Langtad, City of Naga, Cebu</p>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
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