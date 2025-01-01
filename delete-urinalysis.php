<?php
session_start();
if (empty($_SESSION['name'])) {
    header('location:index.php');
}
include('includes/connection.php');

if ($_SESSION['role'] != 1) {
    header('location:index.php');
}

if (isset($_GET['id'])) {
    $id = $_GET['id'];
    $query = "DELETE FROM tbl_urinalysis WHERE urinalysis_id = '$id'";
    $result = mysqli_query($connection, $query);

    if ($result) {
        $_SESSION['msg'] = "Record deleted successfully.";
    } else {
        $_SESSION['msg'] = "Failed to delete record.";
    }
}

header('location:urinalysis.php');
?>
