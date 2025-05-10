<?php
include('includes/connection.php');

$query = isset($_GET['query']) ? mysqli_real_escape_string($connection, $_GET['query']) : '';

$sql = "SELECT * FROM tbl_cbc WHERE deleted = 0";

if(!empty($query)) {
    $sql .= " AND (
        cbc_id LIKE '%$query%'
        OR patient_id LIKE '%$query%'
        OR patient_name LIKE '%$query%'
        OR gender LIKE '%$query%'
        OR hemoglobin LIKE '%$query%'
        OR hematocrit LIKE '%$query%'
        OR red_blood_cells LIKE '%$query%'
        OR white_blood_cells LIKE '%$query%'
        OR esr LIKE '%$query%'
        OR segmenters LIKE '%$query%'
        OR lymphocytes LIKE '%$query%'
        OR monocytes LIKE '%$query%'
        OR bands LIKE '%$query%'
        OR platelets LIKE '%$query%'
        OR DATE_FORMAT(date_time, '%M %d, %Y %l:%i %p') LIKE '%$query%'
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
    
    // Format date
    $date_time = date('F d, Y g:i A', strtotime($row['date_time']));
    
    $data[] = array(
        'cbc_id' => $row['cbc_id'],
        'patient_id' => $row['patient_id'], 
        'patient_name' => $row['patient_name'],
        'gender' => $row['gender'],
        'age' => $year,
        'date_time' => $date_time,
        'hemoglobin' => $row['hemoglobin'],
        'hematocrit' => $row['hematocrit'], 
        'red_blood_cells' => $row['red_blood_cells'],
        'white_blood_cells' => $row['white_blood_cells'],
        'esr' => $row['esr'],
        'segmenters' => $row['segmenters'],
        'lymphocytes' => $row['lymphocytes'],
        'monocytes' => $row['monocytes'],
        'bands' => $row['bands'],
        'platelets' => $row['platelets']
    );
}

echo json_encode($data);
?>
