<?php
// your_server_script_room_type.php

include('includes/connection.php');

$availableRoomTypesQuery = mysqli_query($connection, "SELECT DISTINCT room_type FROM tbl_bedallocation");

$roomTypes = array();

while ($row = mysqli_fetch_assoc($availableRoomTypesQuery)) {
    $roomTypes[] = $row['room_type'];
}

// Return the room types as JSON
echo json_encode($roomTypes);

mysqli_close($connection);
?>
