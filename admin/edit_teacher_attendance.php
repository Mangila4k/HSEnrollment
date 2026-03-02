<?php
session_start();
include("../config/database.php");

// Check if user is admin
if(!isset($_SESSION['user']) || $_SESSION['user']['role'] != 'Admin'){
    header("Location: ../auth/login.php");
    exit();
}

if(isset($_POST['edit_teacher_attendance'])) {
    $id = $_POST['id'];
    $teacher_id = $_POST['teacher_id'];
    $date = $_POST['date'];
    $time_in = !empty($_POST['time_in']) ? $_POST['time_in'] : null;
    $time_out = !empty($_POST['time_out']) ? $_POST['time_out'] : null;
    $status = $_POST['status'];
    $remarks = $_POST['remarks'] ?? '';
    
    $query = "UPDATE teacher_attendance SET 
              teacher_id = '$teacher_id',
              date = '$date',
              time_in = " . ($time_in ? "'$time_in'" : "NULL") . ",
              time_out = " . ($time_out ? "'$time_out'" : "NULL") . ",
              status = '$status',
              remarks = '$remarks'
              WHERE id = '$id'";
    
    if($conn->query($query)) {
        $_SESSION['success_message'] = "Teacher attendance record updated successfully!";
    } else {
        $_SESSION['error_message'] = "Error updating record: " . $conn->error;
    }
}

header("Location: attendance.php?tab=teachers");
exit();
?>