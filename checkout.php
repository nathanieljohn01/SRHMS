<?php
session_start();
if (empty($_SESSION['name'])) {
    header('location:index.php');
    exit;
}

include('includes/connection.php');

// Sanitize the 'id' parameter to prevent SQL injection
$id = isset($_GET['id']) ? mysqli_real_escape_string($connection, $_GET['id']) : null;

if ($id) {
    // Prepare and bind the query to update the checkout time
    $update_query = $connection->prepare("UPDATE tbl_visitorpass SET check_out_time = NOW() WHERE id = ?");
    $update_query->bind_param("i", $id);  // "s" stands for string
    
    // Execute the query
    if ($update_query->execute()) {
        $msg = "Visitor checked out successfully";
    } else {
        $msg = "Error!";
    }
} else {
    $msg = "Invalid visitor ID.";
}

header('location: visitor-pass.php?msg=' . urlencode($msg));  // Pass the message to the next page
exit;
?>
