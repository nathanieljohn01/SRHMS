<?php
include('includes/connection.php');

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

$query = isset($_GET['query']) ? mysqli_real_escape_string($connection, $_GET['query']) : '';

$sql = "SELECT p.*, TIMESTAMPDIFF(YEAR, p.dob, CURDATE()) as age 
        FROM tbl_pbs p 
        WHERE p.deleted = 0";

if (!empty($query)) {
    $sql .= " AND (
        p.pbs_id LIKE '%$query%' OR
        p.patient_id LIKE '%$query%' OR
        p.patient_name LIKE '%$query%' OR
        p.rbc_morphology LIKE '%$query%' OR
        p.abnormal_cells LIKE '%$query%' OR
        DATE_FORMAT(p.date_time, '%M %d, %Y %h:%i %p') LIKE '%$query%'
    )";
}

$sql .= " ORDER BY p.date_time DESC";

$result = mysqli_query($connection, $sql);
if (!$result) {
    header('Content-Type: application/json');
    echo json_encode(['error' => mysqli_error($connection)]);
    exit;
}

$data = [];

while($row = mysqli_fetch_assoc($result)) {
    // Calculate age
    $dob = $row['dob'];
    $date = str_replace('/', '-', $dob);
    $dob = date('Y-m-d', strtotime($date));
    $year = (date('Y') - date('Y', strtotime($dob)));
    
    // Format date
    $date_time = date('F d, Y g:i A', strtotime($row['date_time']));
    
    $data[] = [
        'pbs_id' => $row['pbs_id'],
        'patient_id' => $row['patient_id'],
        'patient_name' => $row['patient_name'],
        'gender' => $row['gender'],
        'age' => $year,
        'date_time' => $date_time,
        'rbc_morphology' => $row['rbc_morphology'],
        'platelet_count' => $row['platelet_count'],
        'toxic_granules' => $row['toxic_granules'],
        'abnormal_cells' => $row['abnormal_cells'],
        'segmenters' => $row['segmenters'],
        'lymphocytes' => $row['lymphocytes'],
        'monocytes' => $row['monocytes'],
        'eosinophils' => $row['eosinophils'],
        'bands' => $row['bands'],
        'reticulocyte_count' => $row['reticulocyte_count'],
        'remarks' => $row['remarks']
    ];
}

header('Content-Type: application/json');
echo json_encode($data);
?>