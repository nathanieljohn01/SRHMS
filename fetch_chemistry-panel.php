<?php
include('includes/connection.php');

$query = isset($_GET['query']) ? $_GET['query'] : '';

$sql = "SELECT * FROM tbl_chemistry WHERE deleted = 0";

if (!empty($query)) {
    $sql .= " AND (
        chem_id LIKE '%$query%'
        OR patient_id LIKE '%$query%'
        OR patient_name LIKE '%$query%'
        OR gender LIKE '%$query%'
        OR fbs LIKE '%$query%'
        OR ppbs LIKE '%$query%'
        OR bun LIKE '%$query%'
        OR crea LIKE '%$query%'
        OR bua LIKE '%$query%'
        OR tc LIKE '%$query%'
        OR tg LIKE '%$query%'
        OR hdl LIKE '%$query%'
        OR ldl LIKE '%$query%'
        OR vldl LIKE '%$query%'
        OR ast LIKE '%$query%'
        OR alt LIKE '%$query%'
        OR alp LIKE '%$query%'
        OR remarks LIKE '%$query%'
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
        'chem_id' => $row['chem_id'],
        'patient_id' => $row['patient_id'],
        'patient_name' => $row['patient_name'],
        'gender' => $row['gender'],
        'age' => $year,
        'date_time' => $date_time,
        'fbs' => $row['fbs'],
        'ppbs' => $row['ppbs'],
        'bun' => $row['bun'],
        'crea' => $row['crea'],
        'bua' => $row['bua'],
        'tc' => $row['tc'],
        'tg' => $row['tg'],
        'hdl' => $row['hdl'],
        'ldl' => $row['ldl'],
        'vldl' => $row['vldl'],
        'ast' => $row['ast'],
        'alt' => $row['alt'],
        'alp' => $row['alp'],
        'remarks' => $row['remarks']
    );
}

echo json_encode($data);
?>