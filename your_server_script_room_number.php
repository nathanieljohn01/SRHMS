<?php
// your_server_script_room_number.php

include('includes/connection.php');

$selectedRoomType = $_GET['room_type'];

$query = "SELECT DISTINCT room_number FROM tbl_bedallocation";

if (!empty($selectedRoomType)) {
    $query .= " WHERE room_type = '$selectedRoomType'";
}

$result = mysqli_query($connection, $query);

$roomNumbers = array();

while ($row = mysqli_fetch_assoc($result)) {
    $roomNumbers[] = $row['room_number'];
}

// Return the room numbers as JSON
echo json_encode($roomNumbers);

mysqli_close($connection);
?>
