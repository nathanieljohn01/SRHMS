<?php
session_start();
if (empty($_SESSION['name'])) {
    header('location:index.php');
    exit; // Ensure script stops execution after redirection
}

include('header.php');
include('includes/connection.php');

// Prepared statement to prevent SQL injection
$sql = "SELECT DISTINCT lab_department FROM tbl_labtest";
$stmt = $connection->prepare($sql); // Preparing the SQL query
$stmt->execute(); // Executing the query
$result = $stmt->get_result(); // Get result of the query

if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        // Using htmlspecialchars to escape output and prevent XSS
        echo "<option value='" . htmlspecialchars($row['lab_department'], ENT_QUOTES, 'UTF-8') . "'>" . htmlspecialchars($row['lab_department'], ENT_QUOTES, 'UTF-8') . "</option>";
    }
} else {
    echo "<option value=''>No lab departments found</option>";
}

$stmt->close(); // Close the prepared statement
mysqli_close($connection); // Close the database connection
?>
