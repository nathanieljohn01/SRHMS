<?php
include('includes/connection.php');

$query = isset($_GET['query']) ? mysqli_real_escape_string($connection, $_GET['query']) : '';

$sql = "SELECT p.*, TIMESTAMPDIFF(YEAR, p.dob, CURDATE()) AS age 
        FROM tbl_ptptt p 
        WHERE p.deleted = 0";

if (!empty($query)) {
    $sql .= " AND (
        p.ptptt_id LIKE '%$query%' OR
        p.patient_id LIKE '%$query%' OR
        p.patient_name LIKE '%$query%' OR
        p.prothrombin_control LIKE '%$query%' OR
        p.prothrombin_test LIKE '%$query%' OR
        p.prothrombin_inr LIKE '%$query%' OR
        p.prothrombin_activity LIKE '%$query%' OR
        p.prothrombin_result LIKE '%$query%' OR
        p.prothrombin_normal_values LIKE '%$query%' OR
        p.ptt_control LIKE '%$query%' OR
        p.ptt_patient_result LIKE '%$query%' OR
        p.ptt_normal_values LIKE '%$query%' OR
        p.remarks LIKE '%$query%' OR
        DATE_FORMAT(p.date_time, '%M %d, %Y %h:%i %p') LIKE '%$query%'
    )";
}

$result = mysqli_query($connection, $sql);
$data = [];

while ($row = mysqli_fetch_assoc($result)) {
    $data[] = [
        'ptptt_id' => $row['ptptt_id'],
        'patient_id' => $row['patient_id'],
        'patient_name' => $row['patient_name'],
        'gender' => $row['gender'],
        'age' => $row['age'],
        'date_time' => date('M d, Y h:i A', strtotime($row['date_time'])),
        'prothrombin_control' => $row['prothrombin_control'],
        'prothrombin_test' => $row['prothrombin_test'],
        'prothrombin_inr' => $row['prothrombin_inr'],
        'prothrombin_activity' => $row['prothrombin_activity'],
        'prothrombin_result' => $row['prothrombin_result'],
        'prothrombin_normal_values' => $row['prothrombin_normal_values'],
        'ptt_control' => $row['ptt_control'],
        'ptt_patient_result' => $row['ptt_patient_result'],
        'ptt_normal_values' => $row['ptt_normal_values'],
        'remarks' => $row['remarks']
    ];
}

header('Content-Type: application/json');
echo json_encode($data);
?>
