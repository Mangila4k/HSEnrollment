<?php
session_start();
include("../config/database.php");

// Check if user is admin
if(!isset($_SESSION['user']) || $_SESSION['user']['role'] != 'Admin'){
    header("Location: ../auth/login.php");
    exit();
}

// Check if form was submitted
if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_section'])) {
    
    // Get form data
    $section_name = trim($_POST['section_name']);
    $grade_id = $_POST['grade_id'];
    $adviser_id = !empty($_POST['adviser_id']) ? $_POST['adviser_id'] : null;
    
    // Validation
    $errors = [];
    
    if(empty($section_name)) {
        $errors[] = "Section name is required";
    }
    
    if(empty($grade_id)) {
        $errors[] = "Grade level is required";
    }
    
    // Check if section already exists
    if(empty($errors)) {
        $check_query = "SELECT id FROM sections WHERE section_name = ? AND grade_id = ?";
        $check_stmt = $conn->prepare($check_query);
        $check_stmt->bind_param("si", $section_name, $grade_id);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if($check_result->num_rows > 0) {
            $errors[] = "A section with this name already exists for the selected grade level";
        }
        $check_stmt->close();
    }
    
    // If no errors, insert the section
    if(empty($errors)) {
        if($adviser_id) {
            $insert_query = "INSERT INTO sections (section_name, grade_id, adviser_id) VALUES (?, ?, ?)";
            $insert_stmt = $conn->prepare($insert_query);
            $insert_stmt->bind_param("sii", $section_name, $grade_id, $adviser_id);
        } else {
            $insert_query = "INSERT INTO sections (section_name, grade_id) VALUES (?, ?)";
            $insert_stmt = $conn->prepare($insert_query);
            $insert_stmt->bind_param("si", $section_name, $grade_id);
        }
        
        if($insert_stmt->execute()) {
            $_SESSION['success_message'] = "Section created successfully!";
            header("Location: sections.php");
            exit();
        } else {
            $errors[] = "Database error: " . $conn->error;
        }
        $insert_stmt->close();
    }
    
    // If there are errors, store them in session and redirect back
    if(!empty($errors)) {
        $_SESSION['error_messages'] = $errors;
        $_SESSION['form_data'] = $_POST;
        header("Location: create_section.php");
        exit();
    }
    
} else {
    // If someone tries to access this file directly without POST
    header("Location: sections.php");
    exit();
}
?>