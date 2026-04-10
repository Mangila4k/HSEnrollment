<?php
session_start();
include("../config/database.php");

header('Content-Type: application/json');

if(!isset($_SESSION['user']) || $_SESSION['user']['role'] != 'Teacher'){
    echo json_encode([]);
    exit();
}

$teacher_id = $_SESSION['user']['id'];
$section_id = isset($_GET['section_id']) ? $_GET['section_id'] : '';

if(!$section_id) {
    echo json_encode([]);
    exit();
}

// Get subjects that this teacher teaches to the selected section
$subjects_query = "
    SELECT DISTINCT sub.id, sub.subject_name, g.grade_name
    FROM subjects sub
    JOIN class_schedules cs ON sub.id = cs.subject_id
    JOIN grade_levels g ON sub.grade_id = g.id
    WHERE cs.teacher_id = ? AND cs.section_id = ?
    ORDER BY sub.subject_name
";
$stmt = $conn->prepare($subjects_query);
$stmt->execute([$teacher_id, $section_id]);
$subjects = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode($subjects);
?>