<?php
session_start();
include("../config/database.php");
if(!isset($_SESSION['user']) || $_SESSION['user']['role'] != 'Student'){
    header("Location: ../auth/login.php");
    exit();
}

// Fetch student enrollment
$student_id = $_SESSION['user']['id'];
$enroll = $conn->query("SELECT * FROM enrollments WHERE student_id='$student_id' AND status='Enrolled'")->fetch_assoc();

?>

<h2>My Schedule</h2>
<?php if(!$enroll): ?>
<p>You are not enrolled yet!</p>
<?php else: ?>
<p>Grade: <?php echo $enroll['grade_id']; ?> | School Year: <?php echo $enroll['school_year']; ?></p>
<!-- Future: display subjects and schedule -->
<?php endif; ?>