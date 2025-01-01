<?php
include('includes/connection.php');

$roomNumber = $_GET['room_number'];
$bedNumber = $_GET['bed_number'];

$query = "SELECT status FROM tbl_bedallocation WHERE room_number='$roomNumber' AND bed_number='$bedNumber'";
$result = mysqli_query($connection, $query);

$response = array();

if ($result) {
    $row = mysqli_fetch_assoc($result);
    if ($row['status'] == 'Available') {
        $response['available'] = true;
    } else {
        $response['available'] = false;
    }
} else {
    $response['available'] = false;
}

// Return the response as JSON
echo json_encode($response);

mysqli_close($connection);
?>
