<?php
session_start();
include("../config/database.php");

if(!isset($_SESSION['user']) || $_SESSION['user']['role'] != 'Registrar'){
    header("Location: ../auth/login.php");
    exit();
}

if(isset($_POST['edit_section'])) {
    $section_id = $_POST['section_id'];
    $section_name = $_POST['section_name'];
    $grade_id = $_POST['grade_id'];
    $adviser_id = $_POST['adviser_id'] ?: 'NULL';
    
    $query = "UPDATE sections SET 
              section_name = '$section_name',
              grade_id = '$grade_id',
              adviser_id = $adviser_id
              WHERE id = $section_id";
    
    if($conn->query($query)) {
        $_SESSION['success_message'] = "Section updated successfully!";
    } else {
        $_SESSION['error_message'] = "Error updating section: " . $conn->error;
    }
}

header("Location: sections.php");
exit();
?>