<?php
// update_lab_status.php

// Include the database connection file
include('includes/connection.php');

// Check if lab_test and status parameters are set
if(isset($_POST['lab_test'], $_POST['status'])) {
    // Retrieve lab_test and status values
    $lab_test = $_POST['lab_test'];
    $status = $_POST['status'];

    // Update the status in the database
    $update_query = "UPDATE tbl_labtest SET status = '$status' WHERE lab_test = '$lab_test'";
    $result = mysqli_query($connection, $update_query);

    // Check if the query was successful
    if($result) {
        // Return a success message
        echo "Status updated successfully";
    } else {
        // Return an error message
        echo "Error updating status";
    }
} else {
    // Return an error message if parameters are not set
    echo "Parameters not set";
}
?>
