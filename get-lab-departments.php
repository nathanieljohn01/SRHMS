<?php
session_start();
if (empty($_SESSION['name'])) {
    header('location:index.php');
}
include('header.php');
include('includes/connection.php');

$sql = "SELECT DISTINCT lab_department FROM tbl_labtest";
$result = $connection->query($sql);

if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        echo "<option value='{$row['lab_department']}'>{$row['lab_department']}</option>";
    }
} else {
    echo "<option value=''>No lab departments found</option>";
}

mysqli_close($connection);
?>
