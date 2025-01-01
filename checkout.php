<?php
session_start();
if (empty($_SESSION['name'])) {
    header('location:index.php');
}
include('includes/connection.php');

$id = $_GET['id'];

$update_query = mysqli_query($connection, "UPDATE tbl_visitorpass SET check_out_time = NOW() WHERE id='$id'");

if ($update_query) {
    $msg = "Visitor checked out successfully";
} else {
    $msg = "Error!";
}

header('location: visitor-pass.php');
