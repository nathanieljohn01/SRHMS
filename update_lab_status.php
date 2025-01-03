<?php
// update_lab_status.php

// Include the database connection file
include('includes/connection.php');

// Check if lab_test and status parameters are set
if(isset($_POST['lab_test'], $_POST['status'])) {
    // Retrieve lab_test and status values
    $lab_test = $_POST['lab_test'];
    $status = $_POST['status'];

    // Prepare the SQL statement
    $stmt = $connection->prepare("UPDATE tbl_labtest SET status = ? WHERE lab_test = ?");
    $stmt->bind_param("ss", $status, $lab_test);

    // Execute the statement
    if($stmt->execute()) {
        // Return a success message
        echo "Status updated successfully";
    } else {
        // Return an error message
        echo "Error updating status";
    }

    // Close the statement
    $stmt->close();
} else {
    // Return an error message if parameters are not set
    echo "Parameters not set";
}

// Close the database connection
$connection->close();
?>
