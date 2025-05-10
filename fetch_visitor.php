<?php
include('includes/connection.php');

$query = isset($_GET['query']) ? mysqli_real_escape_string($connection, $_GET['query']) : '';


$sql = "SELECT * FROM tbl_visitorpass WHERE deleted = 0";

if(!empty($query)) {
    $sql .= " AND (
        visitor_id LIKE '%$query%'
        OR visitor_name LIKE '%$query%'
        OR contact_number LIKE '%$query%'
        OR purpose LIKE '%$query%'
        OR DATE_FORMAT(check_in_time, '%M %d, %Y %l:%i %p') LIKE '%$query%'
        OR DATE_FORMAT(check_out_time, '%M %d, %Y %l:%i %p') LIKE '%$query%'
    )";
}

$result = mysqli_query($connection, $sql);
$data = array();

while($row = mysqli_fetch_assoc($result)) {
    $check_in_time = date('F d, Y g:i A', strtotime($row['check_in_time']));
    $check_out_time = ($row['check_out_time'] != NULL) ? date('F d, Y g:i A', strtotime($row['check_out_time'])) : '';
    
    $data[] = array(
        'id' => $row['id'],
        'visitor_id' => $row['visitor_id'],
        'visitor_name' => $row['visitor_name'],
        'contact_number' => $row['contact_number'],
        'purpose' => $row['purpose'],
        'check_in_time' => $check_in_time,
        'check_out_time' => $check_out_time
    );
}

echo json_encode($data);
?>
