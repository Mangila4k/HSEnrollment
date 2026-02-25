<?php
session_start();
include("../config/database.php");
if(!isset($_SESSION['user']) || $_SESSION['user']['role'] != 'Teacher'){
    header("Location: ../auth/login.php");
    exit();
}
?>

<h2>Teacher Dashboard</h2>
<p>Welcome, <?php echo $_SESSION['user']['fullname']; ?> | <a href="../auth/logout.php">Logout</a></p>
<a href="attendance.php">Take Attendance</a>