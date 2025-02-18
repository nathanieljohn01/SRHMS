<?php
include('includes/connection.php');

$query = isset($_GET['query']) ? trim($_GET['query']) : '';
$query = mysqli_real_escape_string($connection, $query);

$sql = "SELECT * FROM tbl_operating_room 
        WHERE deleted = 0 
        AND (
            LOWER(patient_id) LIKE LOWER(?) OR
            LOWER(patient_name) LIKE LOWER(?) OR
            LOWER(operation_status) LIKE LOWER(?) OR
            LOWER(current_surgery) LIKE LOWER(?) OR
            LOWER(surgeon) LIKE LOWER(?) OR
            LOWER(start_time) LIKE LOWER(?) OR
            LOWER(end_time) LIKE LOWER(?) OR
            LOWER(notes) LIKE LOWER(?)
        )
        ORDER BY start_time DESC";

$search_term = "%{$query}%";
$stmt = mysqli_prepare($connection, $sql);
mysqli_stmt_bind_param($stmt, 'ssssssss',
    $search_term, $search_term, $search_term, $search_term,
    $search_term, $search_term, $search_term, $search_term
);

mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$operating_rooms = array();

while ($row = mysqli_fetch_assoc($result)) {
    $operating_rooms[] = array(
        'id' => $row['id'],
        'patient_id' => htmlspecialchars($row['patient_id']),
        'patient_name' => htmlspecialchars($row['patient_name']),
        'operation_status' => htmlspecialchars($row['operation_status']),
        'current_surgery' => htmlspecialchars($row['current_surgery']),
        'surgeon' => htmlspecialchars($row['surgeon']),
        'start_time' => htmlspecialchars($row['start_time']),
        'end_time' => htmlspecialchars($row['end_time']),
        'notes' => htmlspecialchars($row['notes'])
    );
}

echo json_encode($operating_rooms);
