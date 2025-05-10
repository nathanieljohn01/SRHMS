<?php
include('includes/connection.php');

$query = isset($_GET['query']) ? mysqli_real_escape_string($connection, $_GET['query']) : '';

$sql = "SELECT * FROM tbl_crossmatching WHERE deleted = 0";

if (!empty($query)) {
    $sql .= " AND (
        crossmatching_id LIKE '%$query%'
        OR patient_id LIKE '%$query%'
        OR patient_name LIKE '%$query%'
        OR gender LIKE '%$query%'
        OR patient_blood_type LIKE '%$query%'
        OR blood_component LIKE '%$query%'
        OR serial_number LIKE '%$query%'
        OR major_crossmatching LIKE '%$query%'
        OR donors_blood_type LIKE '%$query%'
        OR packed_red_blood_cell LIKE '%$query%'
        OR time_packed LIKE '%$query%'
        OR open_system LIKE '%$query%'
        OR closed_system LIKE '%$query%'
        OR to_be_consumed_before LIKE '%$query%'
        OR hours LIKE '%$query%'
        OR minor_crossmatching LIKE '%$query%'
        OR DATE_FORMAT(date_time, '%M %d, %Y %l:%i %p') LIKE '%$query%'
        OR DATE_FORMAT(extraction_date, '%M %d, %Y') LIKE '%$query%'
        OR DATE_FORMAT(expiration_date, '%M %d, %Y') LIKE '%$query%'
        OR DATE_FORMAT(dated, '%M %d, %Y') LIKE '%$query%'
    )";
}

$result = mysqli_query($connection, $sql);
$data = array();

while ($row = mysqli_fetch_assoc($result)) {
    // Calculate Age
    $dob = $row['dob'];
    $date = str_replace('/', '-', $dob);
    $dob = date('Y-m-d', strtotime($date));
    $age = (date('Y') - date('Y', strtotime($dob)));

    // Format Dates
    $date_time = date('F d, Y g:i A', strtotime($row['date_time']));
    $extraction_date = !empty($row['extraction_date']) ? date('F d, Y', strtotime($row['extraction_date'])) : '';
    $expiration_date = !empty($row['expiration_date']) ? date('F d, Y', strtotime($row['expiration_date'])) : '';
    $dated = !empty($row['dated']) ? date('F d, Y', strtotime($row['dated'])) : '';

    $data[] = array(
        'crossmatching_id' => $row['crossmatching_id'],
        'patient_id' => $row['patient_id'],
        'patient_name' => $row['patient_name'],
        'gender' => $row['gender'],
        'age' => $age,
        'date_time' => $date_time,
        'patient_blood_type' => $row['patient_blood_type'],
        'blood_component' => $row['blood_component'],
        'serial_number' => $row['serial_number'],
        'extraction_date' => $extraction_date,
        'expiration_date' => $expiration_date,
        'major_crossmatching' => $row['major_crossmatching'],
        'donors_blood_type' => $row['donors_blood_type'],
        'packed_red_blood_cell' => $row['packed_red_blood_cell'],
        'time_packed' => $row['time_packed'],
        'dated' => $dated,
        'open_system' => $row['open_system'],
        'closed_system' => $row['closed_system'],
        'to_be_consumed_before' => $row['to_be_consumed_before'],
        'hours' => $row['hours'],
        'minor_crossmatching' => $row['minor_crossmatching']
    );
}

echo json_encode($data);
?>