<?php
include('includes/connection.php');

$query = isset($_GET['query']) ? mysqli_real_escape_string($connection, $_GET['query']) : '';

$sql = "SELECT * FROM tbl_electrolytes WHERE deleted = 0";

if (!empty($query)) {
    $sql .= " AND (
        electrolytes_id LIKE '%$query%'
        OR patient_id LIKE '%$query%'
        OR patient_name LIKE '%$query%'
        OR gender LIKE '%$query%'
        OR sodium LIKE '%$query%'
        OR potassium LIKE '%$query%'
        OR chloride LIKE '%$query%'
        OR calcium LIKE '%$query%'
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
        'electrolytes_id' => $row['electrolytes_id'],
        'patient_id' => $row['patient_id'],
        'patient_name' => $row['patient_name'],
        'gender' => $row['gender'],
        'age' => $year,
        'date_time' => $date_time,
        'sodium' => $row['sodium'],
        'potassium' => $row['potassium'],
        'chloride' => $row['chloride'],
        'calcium' => $row['calcium']
    );
}

echo json_encode($data);
?>
