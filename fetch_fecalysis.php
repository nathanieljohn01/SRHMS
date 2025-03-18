<?php
include('includes/connection.php');

$query = isset($_GET['query']) ? $_GET['query'] : '';

$sql = "SELECT * FROM tbl_fecalysis WHERE deleted = 0";

if (!empty($query)) {
    $sql .= " AND (
        fecalysis_id LIKE '%$query%'
        OR patient_id LIKE '%$query%'
        OR patient_name LIKE '%$query%'
        OR gender LIKE '%$query%'
        OR color LIKE '%$query%'
        OR consistency LIKE '%$query%'
        OR occult_blood LIKE '%$query%'
        OR ova_or_parasite LIKE '%$query%'
        OR yeast_cells LIKE '%$query%'
        OR fat_globules LIKE '%$query%'
        OR pus_cells LIKE '%$query%'
        OR rbc LIKE '%$query%'
        OR bacteria LIKE '%$query%'
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
        'fecalysis_id' => $row['fecalysis_id'],
        'patient_id' => $row['patient_id'],
        'patient_name' => $row['patient_name'],
        'gender' => $row['gender'],
        'age' => $year,
        'date_time' => $date_time,
        'color' => $row['color'],
        'consistency' => $row['consistency'],
        'occult_blood' => $row['occult_blood'],
        'ova_or_parasite' => $row['ova_or_parasite'],
        'yeast_cells' => $row['yeast_cells'],
        'fat_globules' => $row['fat_globules'],
        'pus_cells' => $row['pus_cells'],
        'rbc' => $row['rbc'],
        'bacteria' => $row['bacteria']
    );
}

echo json_encode($data);
?>
