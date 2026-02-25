<?php
session_start();
include("../config/database.php");
if(!isset($_SESSION['user']) || $_SESSION['user']['role'] != 'Admin'){
    header("Location: ../auth/login.php");
    exit();
}

$sections = $conn->query("SELECT s.id, s.section_name, g.grade_name, u.fullname as adviser
FROM sections s 
LEFT JOIN grade_levels g ON s.grade_id=g.id
LEFT JOIN users u ON s.adviser_id=u.id");
?>

<h2>Sections</h2>
<a href="dashboard.php">Back to Dashboard</a>
<table border="1" cellpadding="5">
<tr>
    <th>ID</th>
    <th>Section Name</th>
    <th>Grade</th>
    <th>Adviser</th>
</tr>
<?php while($sec = $sections->fetch_assoc()): ?>
<tr>
    <td><?php echo $sec['id']; ?></td>
    <td><?php echo $sec['section_name']; ?></td>
    <td><?php echo $sec['grade_name']; ?></td>
    <td><?php echo $sec['adviser']; ?></td>
</tr>
<?php endwhile; ?>
</table>