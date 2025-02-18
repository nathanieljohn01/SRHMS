<?php
include('includes/connection.php');

$query = isset($_GET['query']) ? $_GET['query'] : '';

$sql = "SELECT * FROM tbl_patient WHERE deleted = 0";

if(!empty($query)) {
    $sql .= " AND (
        patient_id LIKE '%$query%'
        OR patient_type LIKE '%$query%'
        OR CONCAT(first_name, ' ', last_name) LIKE '%$query%'
        OR dob LIKE '%$query%'
        OR gender LIKE '%$query%'
        OR civil_status LIKE '%$query%'
        OR address LIKE '%$query%'
        OR DATE_FORMAT(date_time, '%M %d, %Y %l:%i %p') LIKE '%$query%'
        OR email LIKE '%$query%'
        OR contact_number LIKE '%$query%'
        OR weight LIKE '%$query%'
        OR height LIKE '%$query%'
        OR temperature LIKE '%$query%'
        OR blood_pressure LIKE '%$query%'
        OR menstruation LIKE '%$query%'
        OR last_menstrual_period LIKE '%$query%'
        OR message LIKE '%$query%'
        OR CAST((YEAR(CURRENT_DATE()) - YEAR(STR_TO_DATE(REPLACE(dob, '/', '-'), '%Y-%m-%d'))) AS CHAR) LIKE '%$query%'
    )";
}

$result = mysqli_query($connection, $sql);
$data = array();

while($row = mysqli_fetch_assoc($result)) {
    // Calculate age
    $dob = $row['dob'];
    $date = str_replace('/', '-', $dob);
    $dob = date('Y-m-d', strtotime($date));
    $year = (date('Y') - date('Y', strtotime($dob)));

    // Format date and time
    $date_time = date('F d, Y g:i A', strtotime($row['date_time']));

    $data[] = array(
        'id' => $row['id'],
        'patient_id' => $row['patient_id'],
        'patient_type' => $row['patient_type'],
        'name' => $row['first_name'] . " " . $row['last_name'],
        'age' => $year,
        'dob' => $row['dob'],
        'gender' => $row['gender'],
        'civil_status' => $row['civil_status'],
        'address' => $row['address'],
        'date_time' => $date_time,
        'email' => $row['email'],
        'contact_number' => $row['contact_number'],
        'weight' => $row['weight'] . ' kg',
        'height' => $row['height'] . ' ft',
        'temperature' => $row['temperature'] . ' °C',
        'blood_pressure' => $row['blood_pressure'],
        'menstruation' => $row['menstruation'],
        'last_menstrual_period' => $row['last_menstrual_period'],
        'message' => $row['message']
    );
}

echo json_encode($data);
?>
    