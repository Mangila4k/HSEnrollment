<?php
// chatbot/process.php
require_once('../config/database.php');

session_start();

header('Content-Type: application/json');

if(isset($_POST['question'])) {
    $question = strtolower(trim($_POST['question']));
    $user_id = $_SESSION['user_id'] ?? null;
    $user_type = $_SESSION['user_type'] ?? 'guest';
    
    // Search knowledge base
    $stmt = $pdo->prepare("SELECT * FROM chatbot_knowledge WHERE is_active = TRUE");
    $stmt->execute();
    $knowledge_base = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $answer = null;
    
    // Check knowledge base
    foreach($knowledge_base as $knowledge) {
        $keywords = explode(',', $knowledge['keywords']);
        foreach($keywords as $keyword) {
            if(stripos($question, trim($keyword)) !== false) {
                $answer = $knowledge['response'];
                break 2;
            }
        }
    }
    
    // Role-specific personalized responses
    if(!$answer) {
        switch($user_type) {
            case 'student':
                if(stripos($question, "schedule") !== false) {
                    $answer = "You can view your schedule in the <a href='../student/schedule.php' target='_blank'>Student Schedule</a> page.";
                } elseif(stripos($question, "attendance") !== false) {
                    $answer = "Check your attendance in the <a href='../student/attendance.php' target='_blank'>Student Attendance</a> page.";
                } elseif(stripos($question, "grade") !== false) {
                    $answer = "Your grades are available in the <a href='../student/grades.php' target='_blank'>Grades</a> page.";
                }
                break;
                
            case 'teacher':
                if(stripos($question, "class") !== false) {
                    $answer = "View your classes in the <a href='../teacher/classes.php' target='_blank'>Teacher Dashboard</a>.";
                } elseif(stripos($question, "attendance") !== false) {
                    $answer = "You can take attendance in the <a href='../teacher/attendance.php' target='_blank'>Attendance</a> page.";
                }
                break;
                
            case 'registrar':
                if(stripos($question, "enrollment") !== false) {
                    $answer = "Manage enrollments in the <a href='../registrar/enrollment.php' target='_blank'>Enrollment</a> page.";
                }
                break;
        }
    }
    
    // Default response
    if(!$answer) {
        $answers = [
            "I'm here to help! Could you please provide more details?",
            "I'm not sure about that. Would you like to speak with a human?",
            "Let me find that information for you. In the meantime, you can check our FAQ section.",
            "I understand you're asking about " . ucfirst($question) . ". Let me get someone to help you with that.",
            "Great question! I'm still learning. Our support team would be happy to assist you."
        ];
        $answer = $answers[array_rand($answers)];
    }
    
    // Log conversation
    $stmt = $pdo->prepare("INSERT INTO chatbot_logs (user_id, user_type, question, answer) VALUES (?, ?, ?, ?)");
    $stmt->execute([$user_id, $user_type, $_POST['question'], $answer]);
    
    echo json_encode(['answer' => $answer]);
} else {
    echo json_encode(['answer' => 'Please ask a question.']);
}
?>