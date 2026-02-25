<?php
session_start();
include("../config/database.php");

if(isset($_POST['question'])){
    $question = $_POST['question'];
    $answer = "I’m sorry, I don’t have an answer for that yet.";
    
    if(stripos($question, "enroll") !== false) $answer = "To enroll, go to the enrollment page and submit your request.";
    if(stripos($question, "schedule") !== false) $answer = "You can view your schedule under the schedule page.";
    if(stripos($question, "attendance") !== false) $answer = "Your attendance is recorded by your teacher. Check attendance page for details.";
}
?>

<h2>Chatbot</h2>
<form method="POST">
    <input type="text" name="question" placeholder="Ask something..." required>
    <button type="submit">Ask</button>
</form>

<?php if(isset($answer)) echo "<p><b>Answer:</b> $answer</p>"; ?>