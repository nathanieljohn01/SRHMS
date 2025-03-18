<?php
include('includes/connection.php');

$query = isset($_GET['query']) ? $_GET['query'] : '';

$sql = "SELECT * FROM tbl_dengueduo WHERE deleted = 0";

if (!empty($query)) {
    $sql .= " AND (
        dd_id LIKE '%$query%'
        OR patient_id LIKE '%$query%'
        OR patient_name LIKE '%$query%'
        OR gender LIKE '%$query%'
        OR ns1ag LIKE '%$query%'
        OR igg LIKE '%$query%'
        OR igm LIKE '%$query%'
        OR DATE_FORMAT(date_time, '%M %d, %Y %l:%i %p') LIKE '%$query%'
    )";
}

$result = mysqli_query($connection, $sql);
$data = array();

while ($row = mysqli_fetch_assoc($result)) {
    // Calculate age
    $dob = $row['dob'];
    $date = str_replace('/', '-', $dob);
    $dob = date('Y-m-d', strtotime($date));
    $year = (date('Y') - date('Y', strtotime($dob)));

    // Format date
    $date_time = date('F d, Y g:i A', strtotime($row['date_time']));

    $data[] = array(
        'dd_id' => $row['dd_id'],
        'patient_id' => $row['patient_id'],
        'patient_name' => $row['patient_name'],
        'gender' => $row['gender'],
        'age' => $year,
        'date_time' => $date_time,
        'ns1ag' => $row['ns1ag'],
        'igg' => $row['igg'],
        'igm' => $row['igm']
    );
}

echo json_encode($data);
?>
