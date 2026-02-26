<?php
session_start();
include("../config/database.php");

if(!isset($_SESSION['user']) || $_SESSION['user']['role'] != 'Registrar'){
    header("Location: ../auth/login.php");
    exit();
}

if(isset($_POST['add_section'])) {
    $section_name = $_POST['section_name'];
    $grade_id = $_POST['grade_id'];
    $adviser_id = $_POST['adviser_id'] ?: 'NULL';
    $school_year = $_POST['school_year'] ?: date('Y') . '-' . (date('Y') + 1);
    
    $query = "INSERT INTO sections (section_name, grade_id, adviser_id, school_year) 
              VALUES ('$section_name', '$grade_id', $adviser_id, '$school_year')";
    
    if($conn->query($query)) {
        $_SESSION['success_message'] = "Section added successfully!";
    } else {
        $_SESSION['error_message'] = "Error adding section: " . $conn->error;
    }
}

header("Location: sections.php");
exit();
?>