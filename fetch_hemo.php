<?php
include('includes/connection.php');

$query = isset($_GET['query']) ? $_GET['query'] : '';

$sql = "
    SELECT h.*,
    GROUP_CONCAT(CONCAT(t.medicine_name, ' (', t.medicine_brand, ') - ', t.total_quantity, ' pcs') SEPARATOR '<br>') AS treatments
    FROM tbl_hemodialysis h
    LEFT JOIN tbl_treatment t ON h.hemopatient_id = t.hemopatient_id
    WHERE h.deleted = 0
";

if(!empty($query)) {
    $sql .= " AND (
        h.hemopatient_id LIKE '%$query%'
        OR h.patient_id LIKE '%$query%'
        OR h.patient_name LIKE '%$query%'
        OR h.gender LIKE '%$query%'
        OR h.dob LIKE '%$query%'
        OR h.dialysis_report LIKE '%$query%'
        OR DATE_FORMAT(h.date_time, '%M %d, %Y %l:%i %p') LIKE '%$query%'
        OR DATE_FORMAT(h.follow_up_date, '%M %d, %Y') LIKE '%$query%'
        OR CAST((YEAR(CURRENT_DATE()) - YEAR(STR_TO_DATE(REPLACE(h.dob, '/', '-'), '%Y-%m-%d'))) AS CHAR) LIKE '%$query%'
        OR t.medicine_name LIKE '%$query%'
        OR t.medicine_brand LIKE '%$query%'
    )";
}

$sql .= " GROUP BY h.hemopatient_id";

$result = mysqli_query($connection, $sql);
$data = array();

while($row = mysqli_fetch_assoc($result)) {
    // Calculate age
    $dob = $row['dob'];
    $date = str_replace('/', '-', $dob);
    $dob = date('Y-m-d', strtotime($date));
    $year = (date('Y') - date('Y', strtotime($dob)));

    // Format dates
    $date_time = date('F d, Y g:i A', strtotime($row['date_time']));
    $follow_up_date = date('F d, Y', strtotime(str_replace('/', '-', $row['follow_up_date'])));
    
    $data[] = array(
        'id' => $row['id'],
        'hemopatient_id' => $row['hemopatient_id'],
        'patient_id' => $row['patient_id'],
        'patient_name' => $row['patient_name'],
        'age' => $year,
        'dob' => $row['dob'],
        'gender' => $row['gender'],
        'date_time' => $date_time,
        'treatments' => $row['treatments'] ?: 'No treatments added',
        'dialysis_report' => nl2br(htmlspecialchars($row['dialysis_report'])),
        'follow_up_date' => $follow_up_date
    );
}

echo json_encode($data);
?>
