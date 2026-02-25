<?php
// Database connection
$servername = "localhost"; // MySQL server
$username = "root";        // default XAMPP username
$password = "";            // default XAMPP password is empty
$database = "hs_enrollment"; // your database name

// Create connection
$conn = new mysqli($servername, $username, $password, $database);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
// echo "Database Connected Successfully!";
?>