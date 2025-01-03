<?php
include('includes/connection.php');

$roomNumber = $_GET['room_number'];
$bedNumber = $_GET['bed_number'];

$query = "SELECT status FROM tbl_bedallocation WHERE room_number=? AND bed_number=?";
$stmt = mysqli_prepare($connection, $query);
mysqli_stmt_bind_param($stmt, 'ss', $roomNumber, $bedNumber);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

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

mysqli_stmt_close($stmt);
mysqli_close($connection);
?>
