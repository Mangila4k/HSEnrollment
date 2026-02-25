<?php
session_start();
include("../config/database.php");

// Check if user is admin
if(!isset($_SESSION['user']) || $_SESSION['user']['role'] != 'Admin'){
    header("Location: ../auth/login.php");
    exit();
}

// Count total students
$student_count = $conn->query("SELECT * FROM users WHERE role='Student'")->num_rows;
$teacher_count = $conn->query("SELECT * FROM users WHERE role='Teacher'")->num_rows;
$section_count = $conn->query("SELECT * FROM sections")->num_rows;
$subject_count = $conn->query("SELECT * FROM subjects")->num_rows;
?>

<h2>Admin Dashboard</h2>
<p>Welcome, <?php echo $_SESSION['user']['fullname']; ?> | <a href="../auth/logout.php">Logout</a></p>

<ul>
    <li>Total Students: <?php echo $student_count; ?></li>
    <li>Total Teachers: <?php echo $teacher_count; ?></li>
    <li>Total Sections: <?php echo $section_count; ?></li>
    <li>Total Subjects: <?php echo $subject_count; ?></li>
</ul>

<a href="students.php">Manage Students</a> | 
<a href="teachers.php">Manage Teachers</a> | 
<a href="sections.php">Manage Sections</a> | 
<a href="subjects.php">Manage Subjects</a>