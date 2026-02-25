<?php
session_start();
include("../config/database.php");
if(!isset($_SESSION['user']) || $_SESSION['user']['role'] != 'Admin'){
    header("Location: ../auth/login.php");
    exit();
}

$students = $conn->query("SELECT * FROM users WHERE role='Student'");
?>

<h2>Students List</h2>
<a href="dashboard.php">Back to Dashboard</a>
<table border="1" cellpadding="5">
    <tr>
        <th>ID</th>
        <th>Full Name</th>
        <th>Email</th>
    </tr>
    <?php while($s = $students->fetch_assoc()): ?>
    <tr>
        <td><?php echo $s['id']; ?></td>
        <td><?php echo $s['fullname']; ?></td>
        <td><?php echo $s['email']; ?></td>
    </tr>
    <?php endwhile; ?>
</table>