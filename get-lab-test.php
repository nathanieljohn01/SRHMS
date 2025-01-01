<?php
session_start();
if (empty($_SESSION['name'])) {
    header('location:index.php');
}
include('header.php');
include('includes/connection.php');

$category = $_POST['category'];

$sql = "SELECT id, lab_test FROM tbl_labtest WHERE lab_department = '$category'";
$result = $connection->query($sql);

if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        echo "<option value='{$row['lab_test']}'>{$row['lab_test']}</option>";
    }
} else {
    echo "<option value=''>No lab tests found</option>";
}

mysqli_close($connection);
?>
