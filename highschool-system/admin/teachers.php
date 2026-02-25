<?php
session_start();
include("../config/database.php");
if(!isset($_SESSION['user']) || $_SESSION['user']['role'] != 'Admin'){
    header("Location: ../auth/login.php");
    exit();
}

$teachers = $conn->query("SELECT * FROM users WHERE role='Teacher'");
?>

<h2>Teachers List</h2>
<a href="dashboard.php">Back to Dashboard</a>
<table border="1" cellpadding="5">
<tr>
    <th>ID</th>
    <th>Full Name</th>
    <th>Email</th>
</tr>
<?php while($t = $teachers->fetch_assoc()): ?>
<tr>
    <td><?php echo $t['id']; ?></td>
    <td><?php echo $t['fullname']; ?></td>
    <td><?php echo $t['email']; ?></td>
</tr>
<?php endwhile; ?>
</table>