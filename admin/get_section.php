<?php
session_start();
include("../config/database.php");

if(!isset($_SESSION['user']) || $_SESSION['user']['role'] != 'Admin'){
    http_response_code(403);
    exit();
}

$id = $_GET['id'] ?? 0;

$section = $conn->query("
    SELECT s.*, g.grade_name 
    FROM sections s 
    LEFT JOIN grade_levels g ON s.grade_id = g.id 
    WHERE s.id = '$id'
")->fetch_assoc();

header('Content-Type: application/json');
echo json_encode($section);
?>