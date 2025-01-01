<?php
session_start();
if (empty($_SESSION['name'])) {
    header('location:index.php');
}
include('includes/connection.php');

if(isset($_GET['id'])) {
    $id = $_GET['id'];

    // Retrieve the corresponding bed allocation
    $bed_query = mysqli_query($connection, "SELECT * FROM tbl_bedallocation WHERE id='$id'");
    $bed_row = mysqli_fetch_assoc($bed_query);
    
    // Update the status in tbl_bedallocation to 'Available'
    $update_query = mysqli_query($connection, "UPDATE tbl_bedallocation SET status = 'Available'");

    if($update_query) {
        // Redirect to housekeeping schedule page after updating status
        header('Location: housekeeping-schedule.php');
        exit();
    } else {
        // Handle error if update fails
        echo "Error updating status";
    }
}
?>



    
