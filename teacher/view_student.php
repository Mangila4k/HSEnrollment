<?php
session_start();

// Check if user is teacher first before including database
if(!isset($_SESSION['user']) || $_SESSION['user']['role'] != 'Teacher'){
    header("Location: ../auth/login.php");
    exit();
}

// Include database after session check
require_once("../config/database.php");

// Check if connection is successful
if (!$conn) {
    die("Database connection failed: " . mysqli_connect_error());
}

$teacher_id = $_SESSION['user']['id'];
$teacher_name = $_SESSION['user']['fullname'];
$student_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$section_id = isset($_GET['section_id']) ? (int)$_GET['section_id'] : 0;
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

// First, check what columns exist in the users table
$columns_check = $conn->query("SHOW COLUMNS FROM users");
$user_columns = [];
if($columns_check) {
    while($col = $columns_check->fetch_assoc()) {
        $user_columns[] = $col['Field'];
    }
}

// Check what columns exist in the enrollments table
$enrollment_columns_check = $conn->query("SHOW COLUMNS FROM enrollments");
$enrollment_columns = [];
if($enrollment_columns_check) {
    while($col = $enrollment_columns_check->fetch_assoc()) {
        $enrollment_columns[] = $col['Field'];
    }
}

// Build the student query dynamically based on existing columns
$student_query = "
    SELECT 
        u.id,
        u.fullname,
        u.email,
        u.role,
        u.created_at";

// Add optional fields if they exist in users table
if(in_array('contact_number', $user_columns)) {
    $student_query .= ", u.contact_number";
}
if(in_array('phone', $user_columns)) {
    $student_query .= ", u.phone as contact_number";
}
if(in_array('contact', $user_columns)) {
    $student_query .= ", u.contact as contact_number";
}
if(in_array('address', $user_columns)) {
    $student_query .= ", u.address";
}
if(in_array('gender', $user_columns)) {
    $student_query .= ", u.gender";
}
if(in_array('birthdate', $user_columns)) {
    $student_query .= ", u.birthdate";
}
if(in_array('birth_date', $user_columns)) {
    $student_query .= ", u.birth_date as birthdate";
}
if(in_array('profile_picture', $user_columns)) {
    $student_query .= ", u.profile_picture";
}
if(in_array('profile_photo', $user_columns)) {
    $student_query .= ", u.profile_photo as profile_picture";
}

$student_query .= "
    FROM users u
    WHERE u.id = ? AND u.role = 'Student'
";

$stmt = $conn->prepare($student_query);
if (!$stmt) {
    die("Error preparing student query: " . $conn->error);
}
$stmt->bind_param("i", $student_id);
$stmt->execute();
$result = $stmt->get_result();
$student = $result->fetch_assoc();
$stmt->close();

if(!$student) {
    $_SESSION['error_message'] = "Student not found.";
    header("Location: classes.php");
    exit();
}

// Build enrollment query dynamically based on existing columns
$enrollment_query = "SELECT 
    e.id as enrollment_id";

// Add optional fields if they exist in enrollments table
if(in_array('enrollment_date', $enrollment_columns)) {
    $enrollment_query .= ", e.enrollment_date";
}
if(in_array('enrolled_date', $enrollment_columns)) {
    $enrollment_query .= ", e.enrolled_date as enrollment_date";
}
if(in_array('date_enrolled', $enrollment_columns)) {
    $enrollment_query .= ", e.date_enrolled as enrollment_date";
}
if(in_array('created_at', $enrollment_columns)) {
    $enrollment_query .= ", e.created_at as enrollment_date";
}
if(in_array('status', $enrollment_columns)) {
    $enrollment_query .= ", e.status as enrollment_status";
}
if(in_array('enrollment_status', $enrollment_columns)) {
    $enrollment_query .= ", e.enrollment_status";
}
if(in_array('school_year', $enrollment_columns)) {
    $enrollment_query .= ", e.school_year";
}
if(in_array('academic_year', $enrollment_columns)) {
    $enrollment_query .= ", e.academic_year as school_year";
}
if(in_array('section_id', $enrollment_columns)) {
    $enrollment_query .= ", e.section_id";
}
if(in_array('grade_id', $enrollment_columns)) {
    $enrollment_query .= ", e.grade_id";
}
if(in_array('strand', $enrollment_columns)) {
    $enrollment_query .= ", e.strand";
}

$enrollment_query .= "
    FROM enrollments e
    WHERE e.student_id = ?
    ORDER BY e.id DESC
    LIMIT 1
";

$stmt = $conn->prepare($enrollment_query);
if ($stmt) {
    $stmt->bind_param("i", $student_id);
    $stmt->execute();
    $enrollment_result = $stmt->get_result();
    $enrollment = $enrollment_result->fetch_assoc();
    $stmt->close();
    
    // Merge enrollment data with student data
    if($enrollment) {
        foreach($enrollment as $key => $value) {
            $student[$key] = $value;
        }
    }
}

// Get section and grade information if section_id exists - FIXED: Removed grade_level
if(isset($student['section_id']) && $student['section_id'] > 0) {
    $section_info_query = "
        SELECT 
            s.section_name,
            g.grade_name
        FROM sections s
        LEFT JOIN grade_levels g ON s.grade_id = g.id
        WHERE s.id = ?
    ";
    
    $stmt = $conn->prepare($section_info_query);
    if($stmt) {
        $stmt->bind_param("i", $student['section_id']);
        $stmt->execute();
        $section_info = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        if($section_info) {
            $student['section_name'] = $section_info['section_name'];
            $student['grade_name'] = $section_info['grade_name'];
        }
    }
} elseif(isset($student['grade_id']) && $student['grade_id'] > 0) {
    // Get grade information from grade_id - FIXED: Removed grade_level
    $grade_info_query = "
        SELECT grade_name
        FROM grade_levels
        WHERE id = ?
    ";
    
    $stmt = $conn->prepare($grade_info_query);
    if($stmt) {
        $stmt->bind_param("i", $student['grade_id']);
        $stmt->execute();
        $grade_info = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        if($grade_info) {
            $student['grade_name'] = $grade_info['grade_name'];
        }
    }
}

// Verify teacher has access to this student (through sections they teach)
if(isset($student['section_id']) && $student['section_id'] > 0) {
    $access_query = "
        SELECT COUNT(*) as has_access
        FROM class_schedules cs
        WHERE cs.teacher_id = ? AND cs.section_id = ?
    ";

    $stmt = $conn->prepare($access_query);
    if($stmt) {
        $stmt->bind_param("ii", $teacher_id, $student['section_id']);
        $stmt->execute();
        $access_result = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        $is_adviser_query = "
            SELECT COUNT(*) as is_adviser
            FROM sections s
            WHERE s.adviser_id = ? AND s.id = ?
        ";

        $stmt = $conn->prepare($is_adviser_query);
        if($stmt) {
            $stmt->bind_param("ii", $teacher_id, $student['section_id']);
            $stmt->execute();
            $adviser_result = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            
            $has_access = ($access_result['has_access'] > 0 || $adviser_result['is_adviser'] > 0);
            
            if(!$has_access) {
                $_SESSION['error_message'] = "You don't have access to view this student.";
                header("Location: classes.php");
                exit();
            }
        }
    }
}

// Get student's attendance records - FIXED: Removed time_in and time_out since they don't exist
$attendance_query = "
    SELECT 
        a.*,
        DATE_FORMAT(a.date, '%M %d, %Y') as formatted_date,
        sub.subject_name
    FROM attendance a
    LEFT JOIN subjects sub ON a.subject_id = sub.id
    WHERE a.student_id = ?
    ORDER BY a.date DESC
    LIMIT 30
";

$stmt = $conn->prepare($attendance_query);
if ($stmt) {
    $stmt->bind_param("i", $student_id);
    $stmt->execute();
    $attendance_records = $stmt->get_result();
    $stmt->close();
} else {
    $attendance_records = null;
}

// Get attendance statistics
$attendance_stats_query = "
    SELECT 
        COUNT(*) as total_days,
        SUM(CASE WHEN status = 'Present' THEN 1 ELSE 0 END) as present_count,
        SUM(CASE WHEN status = 'Absent' THEN 1 ELSE 0 END) as absent_count,
        SUM(CASE WHEN status = 'Late' THEN 1 ELSE 0 END) as late_count
    FROM attendance
    WHERE student_id = ?
";

$stmt = $conn->prepare($attendance_stats_query);
if ($stmt) {
    $stmt->bind_param("i", $student_id);
    $stmt->execute();
    $stats = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    $total_days = $stats['total_days'] ?? 0;
    $present_count = $stats['present_count'] ?? 0;
    $absent_count = $stats['absent_count'] ?? 0;
    $late_count = $stats['late_count'] ?? 0;
    
    $attendance_rate = $total_days > 0 ? round(($present_count / $total_days) * 100, 1) : 0;
} else {
    $total_days = 0;
    $present_count = 0;
    $absent_count = 0;
    $late_count = 0;
    $attendance_rate = 0;
}

// Check if grades table exists
$grades_table_exists = false;
$table_check = $conn->query("SHOW TABLES LIKE 'grades'");
if($table_check && $table_check->num_rows > 0) {
    $grades_table_exists = true;
}

// Get student's grades - FIXED: Removed subject_code and teacher_name
$grades = null;
$grade_stats = [
    'q1_avg' => 0,
    'q2_avg' => 0,
    'q3_avg' => 0,
    'q4_avg' => 0,
    'final_avg' => 0,
    'total_subjects' => 0
];

if($grades_table_exists) {
    $grades_query = "
        SELECT 
            g.*,
            sub.subject_name
        FROM grades g
        LEFT JOIN subjects sub ON g.subject_id = sub.id
        WHERE g.student_id = ?
        ORDER BY g.quarter, sub.subject_name
    ";

    $stmt = $conn->prepare($grades_query);
    if ($stmt) {
        $stmt->bind_param("i", $student_id);
        $stmt->execute();
        $grades = $stmt->get_result();
        $stmt->close();
        
        // Calculate grade statistics
        if ($grades && $grades->num_rows > 0) {
            $grades->data_seek(0);
            $q1_sum = 0; $q1_count = 0;
            $q2_sum = 0; $q2_count = 0;
            $q3_sum = 0; $q3_count = 0;
            $q4_sum = 0; $q4_count = 0;
            $subjects = [];
            
            while($grade = $grades->fetch_assoc()) {
                if (!in_array($grade['subject_id'], $subjects)) {
                    $subjects[] = $grade['subject_id'];
                }
                
                if ($grade['quarter'] == 1 && isset($grade['grade']) && $grade['grade'] > 0) {
                    $q1_sum += $grade['grade'];
                    $q1_count++;
                } elseif ($grade['quarter'] == 2 && isset($grade['grade']) && $grade['grade'] > 0) {
                    $q2_sum += $grade['grade'];
                    $q2_count++;
                } elseif ($grade['quarter'] == 3 && isset($grade['grade']) && $grade['grade'] > 0) {
                    $q3_sum += $grade['grade'];
                    $q3_count++;
                } elseif ($grade['quarter'] == 4 && isset($grade['grade']) && $grade['grade'] > 0) {
                    $q4_sum += $grade['grade'];
                    $q4_count++;
                }
            }
            
            $grade_stats = [
                'q1_avg' => $q1_count > 0 ? round($q1_sum / $q1_count, 2) : 0,
                'q2_avg' => $q2_count > 0 ? round($q2_sum / $q2_count, 2) : 0,
                'q3_avg' => $q3_count > 0 ? round($q3_sum / $q3_count, 2) : 0,
                'q4_avg' => $q4_count > 0 ? round($q4_sum / $q4_count, 2) : 0,
                'final_avg' => 0, // No final_grade column
                'total_subjects' => count($subjects)
            ];
            
            $grades->data_seek(0); // Reset pointer
        }
    }
}

// Calculate age from birthdate
$age = '';
if (isset($student['birthdate']) && $student['birthdate']) {
    $birthdate = new DateTime($student['birthdate']);
    $today = new DateTime('today');
    $age = $birthdate->diff($today)->y;
}

// Set default enrollment status if not set
if(!isset($student['enrollment_status'])) {
    $student['enrollment_status'] = 'Enrolled';
}

// Get current school year
$current_sy = date('Y') . '-' . (date('Y') + 1);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($student['fullname']); ?> - Student Profile</title>
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
            padding-bottom: 20px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.2);
        }

        .sidebar h2 i {
            color: var(--accent);
        }

        .teacher-info {
            text-align: center;
            padding: 20px 0;
            border-bottom: 1px solid rgba(255, 255, 255, 0.2);
            margin-bottom: 20px;
        }

        .teacher-avatar {
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

        .teacher-info h3 {
            font-size: 18px;
            margin-bottom: 5px;
            color: var(--accent);
        }

        .teacher-info p {
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
            border-color: var(--primary);
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(11, 79, 46, 0.1);
        }

        .back-btn i {
            color: var(--primary);
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

        /* Profile Header */
        .profile-header {
            background: white;
            border-radius: 20px;
            padding: 30px;
            margin-bottom: 30px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            display: flex;
            align-items: center;
            gap: 30px;
            flex-wrap: wrap;
        }

        .profile-avatar-large {
            width: 120px;
            height: 120px;
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            border-radius: 30px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 48px;
            font-weight: 600;
            box-shadow: 0 5px 15px rgba(11, 79, 46, 0.3);
        }

        .profile-info h1 {
            font-size: 32px;
            color: var(--text-primary);
            margin-bottom: 10px;
        }

        .profile-meta {
            display: flex;
            gap: 20px;
            flex-wrap: wrap;
        }

        .meta-item {
            display: flex;
            align-items: center;
            gap: 8px;
            color: var(--text-secondary);
            font-size: 14px;
        }

        .meta-item i {
            color: var(--primary);
        }

        .status-badge {
            padding: 8px 20px;
            border-radius: 30px;
            font-size: 14px;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .status-enrolled {
            background: rgba(40, 167, 69, 0.1);
            color: #28a745;
        }

        .status-pending {
            background: rgba(255, 193, 7, 0.1);
            color: #ffc107;
        }

        .status-rejected {
            background: rgba(220, 53, 69, 0.1);
            color: #dc3545;
        }

        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 25px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            border-radius: 15px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .stat-icon {
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

        .stat-content h3 {
            font-size: 14px;
            color: var(--text-secondary);
            margin-bottom: 5px;
        }

        .stat-content .stat-number {
            font-size: 24px;
            font-weight: 700;
            color: var(--text-primary);
        }

        .stat-content .stat-label {
            font-size: 13px;
            color: var(--text-secondary);
        }

        /* Info Cards */
        .info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
            margin-bottom: 30px;
        }

        @media (max-width: 1024px) {
            .info-grid {
                grid-template-columns: 1fr;
            }
        }

        .card {
            background: white;
            border-radius: 20px;
            padding: 25px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
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
            color: var(--primary);
        }

        .card-header .badge {
            background: #f8f9fa;
            color: var(--text-secondary);
            font-size: 12px;
            padding: 5px 10px;
            border-radius: 20px;
        }

        /* Info List */
        .info-list {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }

        .info-row {
            display: flex;
            padding: 10px 0;
            border-bottom: 1px dashed var(--border-color);
        }

        .info-label {
            width: 140px;
            color: var(--text-secondary);
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .info-label i {
            color: var(--primary);
            width: 20px;
        }

        .info-value {
            flex: 1;
            color: var(--text-primary);
            font-weight: 500;
            font-size: 14px;
        }

        /* Tables */
        .table-container {
            overflow-x: auto;
            max-height: 400px;
            overflow-y: auto;
            border-radius: 10px;
            border: 1px solid var(--border-color);
        }

        .data-table {
            width: 100%;
            border-collapse: collapse;
            min-width: 600px;
        }

        .data-table th {
            text-align: left;
            padding: 15px;
            background: #f8f9fa;
            color: var(--text-secondary);
            font-weight: 600;
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            position: sticky;
            top: 0;
            background: #f8f9fa;
            z-index: 10;
            border-bottom: 2px solid var(--border-color);
        }

        .data-table td {
            padding: 15px;
            border-bottom: 1px solid var(--border-color);
        }

        .data-table tbody tr:hover {
            background: var(--hover-color);
        }

        /* Grade Indicators */
        .grade-high {
            color: var(--success);
            font-weight: 600;
        }

        .grade-medium {
            color: var(--warning);
            font-weight: 600;
        }

        .grade-low {
            color: var(--danger);
            font-weight: 600;
        }

        /* Attendance Status */
        .attendance-present {
            color: var(--success);
            font-weight: 600;
        }

        .attendance-absent {
            color: var(--danger);
            font-weight: 600;
        }

        .attendance-late {
            color: var(--warning);
            font-weight: 600;
        }

        /* Grade Summary */
        .grade-summary {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 15px;
            margin-top: 20px;
            text-align: center;
        }

        .summary-item {
            background: #f8f9fa;
            border-radius: 12px;
            padding: 15px;
        }

        .summary-item .label {
            font-size: 12px;
            color: var(--text-secondary);
            margin-bottom: 5px;
        }

        .summary-item .value {
            font-size: 24px;
            font-weight: 700;
            color: var(--primary);
        }

        /* Quick Actions */
        .quick-actions {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
            margin-top: 20px;
        }

        .action-btn {
            padding: 12px 25px;
            border-radius: 10px;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-weight: 500;
            transition: all 0.3s;
            cursor: pointer;
        }

        .action-btn.primary {
            background: var(--primary);
            color: white;
            border: none;
        }

        .action-btn.primary:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(11, 79, 46, 0.3);
        }

        .action-btn.warning {
            background: var(--accent);
            color: var(--primary);
            border: none;
        }

        .action-btn.warning:hover {
            background: #ffed4a;
            transform: translateY(-2px);
        }

        .action-btn.secondary {
            background: white;
            color: var(--text-primary);
            border: 2px solid var(--border-color);
        }

        .action-btn.secondary:hover {
            border-color: var(--primary);
            color: var(--primary);
        }

        .action-btn.info {
            background: var(--info);
            color: white;
            border: none;
        }

        .action-btn.info:hover {
            background: #138496;
            transform: translateY(-2px);
        }

        /* Responsive */
        @media (max-width: 768px) {
            .sidebar {
                width: 80px;
                padding: 20px 10px;
            }
            
            .sidebar h2 span,
            .teacher-info h3,
            .teacher-info p,
            .menu-section h3,
            .menu-items a span {
                display: none;
            }
            
            .teacher-avatar {
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
            
            .profile-header {
                flex-direction: column;
                text-align: center;
            }
            
            .profile-meta {
                justify-content: center;
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
            }

            .info-row {
                flex-direction: column;
                gap: 5px;
            }

            .info-label {
                width: 100%;
            }

            .grade-summary {
                grid-template-columns: repeat(2, 1fr);
            }

            .quick-actions {
                flex-direction: column;
            }

            .action-btn {
                width: 100%;
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
            
            <div class="teacher-info">
                <div class="teacher-avatar">
                    <?php echo strtoupper(substr($teacher_name, 0, 1)); ?>
                </div>
                <h3><?php echo htmlspecialchars(explode(' ', $teacher_name)[0]); ?></h3>
                <p><i class="fas fa-chalkboard-teacher"></i> Teacher</p>
            </div>
            
            <div class="menu-section">
                <h3>MAIN MENU</h3>
                <ul class="menu-items">
                    <li><a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> <span>Dashboard</span></a></li>
                    <li><a href="attendance.php"><i class="fas fa-calendar-check"></i> <span>Attendance</span></a></li>
                    <li><a href="classes.php" class="active"><i class="fas fa-users"></i> <span>My Classes</span></a></li>
                    <li><a href="schedule.php"><i class="fas fa-clock"></i> <span>Schedule</span></a></li>
                    <li><a href="grades.php"><i class="fas fa-star"></i> <span>Grades</span></a></li>
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
                    <p>View detailed information about <?php echo htmlspecialchars($student['fullname']); ?></p>
                </div>
                <a href="javascript:history.back()" class="back-btn">
                    <i class="fas fa-arrow-left"></i> Go Back
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

            <!-- Profile Header -->
            <div class="profile-header">
                <div class="profile-avatar-large">
                    <?php echo strtoupper(substr($student['fullname'], 0, 1)); ?>
                </div>
                <div class="profile-info">
                    <h1><?php echo htmlspecialchars($student['fullname']); ?></h1>
                    <div class="profile-meta">
                        <span class="meta-item">
                            <i class="fas fa-envelope"></i> <?php echo htmlspecialchars($student['email']); ?>
                        </span>
                        <span class="meta-item">
                            <i class="fas fa-calendar"></i> Student ID: <?php echo str_pad($student['id'], 6, '0', STR_PAD_LEFT); ?>
                        </span>
                        <?php if(isset($student['grade_name']) && $student['grade_name']): ?>
                        <span class="meta-item">
                            <i class="fas fa-layer-group"></i> <?php echo htmlspecialchars($student['grade_name']); ?>
                        </span>
                        <?php endif; ?>
                        <?php if(isset($student['section_name']) && $student['section_name']): ?>
                        <span class="meta-item">
                            <i class="fas fa-users"></i> <?php echo htmlspecialchars($student['section_name']); ?>
                        </span>
                        <?php endif; ?>
                    </div>
                    <?php if(isset($student['enrollment_status']) && $student['enrollment_status']): ?>
                    <div style="margin-top: 15px;">
                        <span class="status-badge status-<?php echo strtolower($student['enrollment_status']); ?>">
                            <i class="fas fa-circle"></i> Enrollment: <?php echo $student['enrollment_status']; ?>
                        </span>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Statistics -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-calendar-check"></i>
                    </div>
                    <div class="stat-content">
                        <h3>Attendance Rate</h3>
                        <div class="stat-number"><?php echo $attendance_rate; ?>%</div>
                        <div class="stat-label">Overall attendance</div>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <div class="stat-content">
                        <h3>Present</h3>
                        <div class="stat-number"><?php echo $present_count; ?></div>
                        <div class="stat-label">Days present</div>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-clock"></i>
                    </div>
                    <div class="stat-content">
                        <h3>Late</h3>
                        <div class="stat-number"><?php echo $late_count; ?></div>
                        <div class="stat-label">Days late</div>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-times-circle"></i>
                    </div>
                    <div class="stat-content">
                        <h3>Absent</h3>
                        <div class="stat-number"><?php echo $absent_count; ?></div>
                        <div class="stat-label">Days absent</div>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-star"></i>
                    </div>
                    <div class="stat-content">
                        <h3>Average Grade</h3>
                        <div class="stat-number"><?php echo $grade_stats['final_avg'] > 0 ? $grade_stats['final_avg'] : 'N/A'; ?></div>
                        <div class="stat-label">Overall average</div>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-book"></i>
                    </div>
                    <div class="stat-content">
                        <h3>Subjects</h3>
                        <div class="stat-number"><?php echo $grade_stats['total_subjects']; ?></div>
                        <div class="stat-label">Enrolled subjects</div>
                    </div>
                </div>
            </div>

            <!-- Personal Information and Grade Summary -->
            <div class="info-grid">
                <!-- Personal Information -->
                <div class="card">
                    <div class="card-header">
                        <h3><i class="fas fa-user-circle"></i> Personal Information</h3>
                    </div>
                    <div class="info-list">
                        <div class="info-row">
                            <span class="info-label"><i class="fas fa-user"></i> Full Name</span>
                            <span class="info-value"><?php echo htmlspecialchars($student['fullname']); ?></span>
                        </div>
                        <div class="info-row">
                            <span class="info-label"><i class="fas fa-envelope"></i> Email</span>
                            <span class="info-value"><?php echo htmlspecialchars($student['email']); ?></span>
                        </div>
                        <?php if(isset($student['contact_number']) && $student['contact_number']): ?>
                        <div class="info-row">
                            <span class="info-label"><i class="fas fa-phone"></i> Contact</span>
                            <span class="info-value"><?php echo htmlspecialchars($student['contact_number']); ?></span>
                        </div>
                        <?php endif; ?>
                        <?php if(isset($student['gender']) && $student['gender']): ?>
                        <div class="info-row">
                            <span class="info-label"><i class="fas fa-venus-mars"></i> Gender</span>
                            <span class="info-value"><?php echo htmlspecialchars($student['gender']); ?></span>
                        </div>
                        <?php endif; ?>
                        <?php if(isset($student['birthdate']) && $student['birthdate']): ?>
                        <div class="info-row">
                            <span class="info-label"><i class="fas fa-birthday-cake"></i> Birthdate</span>
                            <span class="info-value">
                                <?php echo date('F j, Y', strtotime($student['birthdate'])); ?>
                                <?php if($age): ?> (<?php echo $age; ?> years old)<?php endif; ?>
                            </span>
                        </div>
                        <?php endif; ?>
                        <?php if(isset($student['address']) && $student['address']): ?>
                        <div class="info-row">
                            <span class="info-label"><i class="fas fa-map-marker-alt"></i> Address</span>
                            <span class="info-value"><?php echo htmlspecialchars($student['address']); ?></span>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Academic Information -->
                <div class="card">
                    <div class="card-header">
                        <h3><i class="fas fa-graduation-cap"></i> Academic Information</h3>
                    </div>
                    <div class="info-list">
                        <?php if(isset($student['grade_name']) && $student['grade_name']): ?>
                        <div class="info-row">
                            <span class="info-label"><i class="fas fa-layer-group"></i> Grade Level</span>
                            <span class="info-value"><?php echo htmlspecialchars($student['grade_name']); ?></span>
                        </div>
                        <?php endif; ?>
                        <?php if(isset($student['section_name']) && $student['section_name']): ?>
                        <div class="info-row">
                            <span class="info-label"><i class="fas fa-users"></i> Section</span>
                            <span class="info-value"><?php echo htmlspecialchars($student['section_name']); ?></span>
                        </div>
                        <?php endif; ?>
                        <?php if(isset($student['school_year']) && $student['school_year']): ?>
                        <div class="info-row">
                            <span class="info-label"><i class="fas fa-calendar-alt"></i> School Year</span>
                            <span class="info-value"><?php echo htmlspecialchars($student['school_year']); ?></span>
                        </div>
                        <?php endif; ?>
                        <?php if(isset($student['enrollment_date']) && $student['enrollment_date']): ?>
                        <div class="info-row">
                            <span class="info-label"><i class="fas fa-flag-checkered"></i> Enrollment Date</span>
                            <span class="info-value"><?php echo date('F j, Y', strtotime($student['enrollment_date'])); ?></span>
                        </div>
                        <?php endif; ?>
                        <div class="info-row">
                            <span class="info-label"><i class="fas fa-id-card"></i> Student ID</span>
                            <span class="info-value"><?php echo str_pad($student['id'], 6, '0', STR_PAD_LEFT); ?></span>
                        </div>
                        <?php if(isset($student['created_at']) && $student['created_at']): ?>
                        <div class="info-row">
                            <span class="info-label"><i class="fas fa-clock"></i> Account Created</span>
                            <span class="info-value"><?php echo date('F j, Y', strtotime($student['created_at'])); ?></span>
                        </div>
                        <?php endif; ?>
                    </div>

                    <!-- Grade Summary -->
                    <?php if($grade_stats['total_subjects'] > 0): ?>
                    <div style="margin-top: 20px;">
                        <h4 style="margin-bottom: 15px; color: var(--text-primary);">Quarterly Averages</h4>
                        <div class="grade-summary">
                            <div class="summary-item">
                                <div class="label">Q1</div>
                                <div class="value"><?php echo $grade_stats['q1_avg'] > 0 ? $grade_stats['q1_avg'] : '—'; ?></div>
                            </div>
                            <div class="summary-item">
                                <div class="label">Q2</div>
                                <div class="value"><?php echo $grade_stats['q2_avg'] > 0 ? $grade_stats['q2_avg'] : '—'; ?></div>
                            </div>
                            <div class="summary-item">
                                <div class="label">Q3</div>
                                <div class="value"><?php echo $grade_stats['q3_avg'] > 0 ? $grade_stats['q3_avg'] : '—'; ?></div>
                            </div>
                            <div class="summary-item">
                                <div class="label">Q4</div>
                                <div class="value"><?php echo $grade_stats['q4_avg'] > 0 ? $grade_stats['q4_avg'] : '—'; ?></div>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Grades Table -->
            <div class="card" style="margin-bottom: 30px;">
                <div class="card-header">
                    <h3><i class="fas fa-star"></i> Subject Grades</h3>
                    <span class="badge"><?php echo $grades ? $grades->num_rows : 0; ?> records</span>
                </div>

                <div class="table-container">
                    <?php if($grades_table_exists && $grades && $grades->num_rows > 0): ?>
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Subject</th>
                                    <th>Quarter</th>
                                    <th>Grade</th>
                                    <th>Remarks</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                while($grade = $grades->fetch_assoc()): 
                                ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo htmlspecialchars($grade['subject_name']); ?></strong>
                                        </td>
                                        <td>Quarter <?php echo $grade['quarter']; ?></td>
                                        <td>
                                            <?php if(isset($grade['grade']) && $grade['grade'] > 0): ?>
                                                <span class="<?php 
                                                    echo $grade['grade'] >= 90 ? 'grade-high' : 
                                                        ($grade['grade'] >= 75 ? 'grade-medium' : 'grade-low'); 
                                                ?>">
                                                    <?php echo $grade['grade']; ?>
                                                </span>
                                            <?php else: ?>—<?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if(isset($grade['remarks']) && $grade['remarks']): ?>
                                                <?php echo htmlspecialchars($grade['remarks']); ?>
                                            <?php else: ?>—<?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    <?php elseif($grades_table_exists): ?>
                        <div class="no-data" style="padding: 40px; text-align: center; color: var(--text-secondary);">
                            <i class="fas fa-star" style="font-size: 48px; margin-bottom: 15px; opacity: 0.3;"></i>
                            <h3 style="color: var(--text-primary); margin-bottom: 10px;">No Grades Available</h3>
                            <p>This student doesn't have any grades recorded yet.</p>
                        </div>
                    <?php else: ?>
                        <div class="no-data" style="padding: 40px; text-align: center; color: var(--text-secondary);">
                            <i class="fas fa-database" style="font-size: 48px; margin-bottom: 15px; opacity: 0.3;"></i>
                            <h3 style="color: var(--text-primary); margin-bottom: 10px;">Grades Table Not Found</h3>
                            <p>The grades table hasn't been created yet. Please run the SQL to create the grades table.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Recent Attendance -->
            <div class="card" style="margin-bottom: 30px;">
                <div class="card-header">
                    <h3><i class="fas fa-calendar-check"></i> Recent Attendance</h3>
                    <span class="badge">Last 30 records</span>
                </div>

                <div class="table-container">
                    <?php if($attendance_records && $attendance_records->num_rows > 0): ?>
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Subject</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while($attendance = $attendance_records->fetch_assoc()): ?>
                                    <tr>
                                        <td><?php echo $attendance['formatted_date']; ?></td>
                                        <td><?php echo htmlspecialchars($attendance['subject_name'] ?? 'N/A'); ?></td>
                                        <td>
                                            <span class="attendance-<?php echo strtolower($attendance['status']); ?>">
                                                <i class="fas fa-<?php 
                                                    echo $attendance['status'] == 'Present' ? 'check-circle' : 
                                                        ($attendance['status'] == 'Late' ? 'clock' : 'times-circle'); 
                                                ?>"></i>
                                                <?php echo $attendance['status']; ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <div class="no-data" style="padding: 40px; text-align: center; color: var(--text-secondary);">
                            <i class="fas fa-calendar-times" style="font-size: 48px; margin-bottom: 15px; opacity: 0.3;"></i>
                            <h3 style="color: var(--text-primary); margin-bottom: 10px;">No Attendance Records</h3>
                            <p>This student doesn't have any attendance records yet.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="card">
                <div class="card-header">
                    <h3><i class="fas fa-bolt"></i> Quick Actions</h3>
                </div>
                <div class="quick-actions">
                    <a href="attendance.php?student_id=<?php echo $student_id; ?><?php echo isset($student['section_id']) ? '&section_id=' . $student['section_id'] : ''; ?>" class="action-btn primary">
                        <i class="fas fa-calendar-check"></i> Take Attendance
                    </a>
                    <a href="grades.php?student_id=<?php echo $student_id; ?>" class="action-btn warning">
                        <i class="fas fa-star"></i> Manage Grades
                    </a>
                    <?php if(isset($student['section_id']) && $student['section_id']): ?>
                    <a href="view_section.php?id=<?php echo $student['section_id']; ?>" class="action-btn secondary">
                        <i class="fas fa-users"></i> View Class
                    </a>
                    <?php endif; ?>
                    <a href="#" class="action-btn info" onclick="alert('Message feature coming soon!')">
                        <i class="fas fa-envelope"></i> Send Message
                    </a>
                </div>
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
    <?php include('../includes/chatbot_widget_teacher.php'); ?>
</body>
</html>