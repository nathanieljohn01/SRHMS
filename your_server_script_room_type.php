<?php
// your_server_script_room_type.php

include('includes/connection.php');

// Sanitize the query to prevent SQL injection
$availableRoomTypesQuery = mysqli_query($connection, "SELECT DISTINCT room_type FROM tbl_bedallocation");

$roomTypes = array();

while ($row = mysqli_fetch_assoc($availableRoomTypesQuery)) {
    // Sanitize the output to prevent XSS
    $roomTypes[] = htmlspecialchars($row['room_type'], ENT_QUOTES, 'UTF-8');
}

// Return the room types as JSON
echo json_encode($roomTypes);

mysqli_close($connection);
?>
