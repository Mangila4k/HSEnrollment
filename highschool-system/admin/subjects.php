<?php
session_start();
include("../config/database.php");
if(!isset($_SESSION['user']) || $_SESSION['user']['role'] != 'Admin'){
    header("Location: ../auth/login.php");
    exit();
}

$subjects = $conn->query("SELECT s.id, s.subject_name, g.grade_name 
FROM subjects s 
LEFT JOIN grade_levels g ON s.grade_id=g.id");
?>

<h2>Subjects</h2>
<a href="dashboard.php">Back to Dashboard</a>
<table border="1" cellpadding="5">
<tr>
    <th>ID</th>
    <th>Subject Name</th>
    <th>Grade</th>
</tr>
<?php while($sub = $subjects->fetch_assoc()): ?>
<tr>
    <td><?php echo $sub['id']; ?></td>
    <td><?php echo $sub['subject_name']; ?></td>
    <td><?php echo $sub['grade_name']; ?></td>
</tr>
<?php endwhile; ?>
</table>