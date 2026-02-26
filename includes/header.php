<?php
// includes/header.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>High School System</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <!-- Your existing styles -->
</head>
<body>
    <div class="navbar">
        <a href="../index.php">Home</a>
        <?php if(isset($_SESSION['user_type'])): ?>
            <a href="../<?php echo $_SESSION['user_type']; ?>/dashboard.php">Dashboard</a>
            <a href="../auth/logout.php">Logout (<?php echo $_SESSION['username'] ?? ''; ?>)</a>
        <?php else: ?>
            <a href="../auth/login.php">Login</a>
            <a href="../auth/register.php">Register</a>
        <?php endif; ?>
    </div>
    
    <!-- Include Chatbot Widget -->
    <?php include(dirname(__DIR__) . '/includes/chatbot_widget.php'); ?>