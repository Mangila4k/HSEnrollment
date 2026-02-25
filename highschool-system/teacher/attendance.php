<?php
session_start();
include("../config/database.php");
if(!isset($_SESSION['user']) || $_SESSION['user']['role'] != 'Teacher'){
    header("Location: ../auth/login.php");
    exit();
}

// Fetch students
$students = $conn->query("SELECT * FROM users WHERE role='Student'");

if(isset($_POST['submit'])){
    $date = $_POST['date'];
    foreach($_POST['status'] as $student_id => $status){
        $conn->query("INSERT INTO attendance (student_id, subject_id, date, status) 
                     VALUES ('$student_id', 1, '$date', '$status')");
    }
    $success = "Attendance recorded!";
}
?>

<h2>Take Attendance</h2>
<?php if(isset($success)) echo "<p style='color:green;'>$success</p>"; ?>
<form method="POST">
    <input type="date" name="date" required>
    <table border="1" cellpadding="5">
        <tr><th>Student</th><th>Status</th></tr>
        <?php while($s=$students->fetch_assoc()): ?>
        <tr>
            <td><?php echo $s['fullname']; ?></td>
            <td>
                <select name="status[<?php echo $s['id']; ?>]">
                    <option value="Present">Present</option>
                    <option value="Absent">Absent</option>
                    <option value="Late">Late</option>
                </select>
            </td>
        </tr>
        <?php endwhile; ?>
    </table>
    <button type="submit" name="submit">Submit Attendance</button>
</form>