<?php
session_start();
include('includes/connection.php');

$query = isset($_GET['query']) ? mysqli_real_escape_string($connection, $_GET['query']) : '';

$sql = "SELECT h.*, b.status AS bed_status 
        FROM tbl_housekeeping_schedule h
        LEFT JOIN tbl_bedallocation b ON h.room_number = b.room_number AND h.bed_number = b.bed_number
        WHERE h.deleted = 0";

if (!empty($query)) {
    $sql .= " AND (
        h.room_type LIKE '%$query%'
        OR h.room_number LIKE '%$query%'
        OR h.bed_number LIKE '%$query%'
        OR h.task_description LIKE '%$query%'
        OR DATE_FORMAT(h.schedule_date_time, '%M %d %Y %h:%i %p') LIKE '%$query%'
    )";
}

$result = mysqli_query($connection, $sql);
$data = array();

while ($row = mysqli_fetch_assoc($result)) {
    $schedule_date_time = date('F d Y g:i A', strtotime($row['schedule_date_time']));
    
    $data[] = array(
        'id' => $row['id'],
        'room_type' => $row['room_type'],
        'room_number' => $row['room_number'],
        'bed_number' => $row['bed_number'],
        'schedule_date_time' => $schedule_date_time,
        'task_description' => $row['task_description'],
        'bed_status' => $row['bed_status'] ?? 'Occupied'
    );
}

echo json_encode($data);
?>
