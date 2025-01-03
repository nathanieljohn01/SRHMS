<?php
// your_server_script_bed_number.php

include('includes/connection.php');

$selectedRoomNumber = isset($_GET['room_number']) ? mysqli_real_escape_string($connection, $_GET['room_number']) : '';

$query = "SELECT DISTINCT bed_number FROM tbl_bedallocation";

if (!empty($selectedRoomNumber)) {
    $query .= " WHERE room_number = '$selectedRoomNumber'";
}

$result = mysqli_query($connection, $query);

$bedNumbers = array();

while ($row = mysqli_fetch_assoc($result)) {
    $bedNumbers[] = $row['bed_number'];
}

// Return the bed numbers as JSON
echo json_encode($bedNumbers);

mysqli_close($connection);
?>
