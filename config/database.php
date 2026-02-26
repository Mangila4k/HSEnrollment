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

// Additional connection check
if (!$conn) {
    die("Database connection failed: " . mysqli_connect_error());
}

// Note: The prepare statement check cannot be here because $query is not defined
// This check should be used in each individual file when preparing queries
// Example:
// $stmt = $conn->prepare($query);
// if (!$stmt) {
//     die("Error preparing query: " . $conn->error);
// }

// echo "Database Connected Successfully!";
?>