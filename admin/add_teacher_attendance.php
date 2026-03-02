<?php
session_start();
include("../config/database.php");

// Check if user is admin
if(!isset($_SESSION['user']) || $_SESSION['user']['role'] != 'Admin'){
    header("Location: ../auth/login.php");
    exit();
}

if(isset($_POST['add_teacher_attendance'])) {
    $teacher_id = $_POST['teacher_id'];
    $date = $_POST['date'];
    $time_in = !empty($_POST['time_in']) ? $_POST['time_in'] : null;
    $time_out = !empty($_POST['time_out']) ? $_POST['time_out'] : null;
    $status = $_POST['status'];
    $remarks = $_POST['remarks'] ?? '';
    
    // Check if table exists
    $table_check = $conn->query("SHOW TABLES LIKE 'teacher_attendance'");
    if($table_check->num_rows == 0) {
        $_SESSION['error_message'] = "Teacher attendance table does not exist. Please create it first.";
        header("Location: attendance.php?tab=teachers");
        exit();
    }
    
    // Check if record already exists for this teacher on this date
    $check = $conn->query("SELECT id FROM teacher_attendance WHERE teacher_id = '$teacher_id' AND date = '$date'");
    
    if($check && $check->num_rows > 0) {
        $_SESSION['error_message'] = "Attendance record already exists for this teacher on this date.";
    } else {
        $query = "INSERT INTO teacher_attendance (teacher_id, date, time_in, time_out, status, remarks) 
                  VALUES ('$teacher_id', '$date', " . ($time_in ? "'$time_in'" : "NULL") . ", " . ($time_out ? "'$time_out'" : "NULL") . ", '$status', '$remarks')";
        
        if($conn->query($query)) {
            $_SESSION['success_message'] = "Teacher attendance record added successfully!";
        } else {
            $_SESSION['error_message'] = "Error adding record: " . $conn->error;
        }
    }
}

header("Location: attendance.php?tab=teachers");
exit();
?>