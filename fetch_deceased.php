<?php
include('includes/connection.php');

$query = isset($_GET['query']) ? $_GET['query'] : '';

$sql = "SELECT * FROM tbl_deceased WHERE deleted = 0";

if(!empty($query)) {
    $sql .= " AND (
        deceased_id LIKE '%$query%'
        OR patient_id LIKE '%$query%'
        OR patient_name LIKE '%$query%'
        OR dod LIKE '%$query%'
        OR tod LIKE '%$query%'
        OR cod LIKE '%$query%'
        OR physician LIKE '%$query%'
        OR next_of_kin_contact LIKE '%$query%'
        OR discharge_status LIKE '%$query%'
    )";
}

$result = mysqli_query($connection, $sql);
$data = array();

while($row = mysqli_fetch_assoc($result)) {
    $data[] = array(
        'deceased_id' => $row['deceased_id'],
        'patient_id' => $row['patient_id'],
        'patient_name' => $row['patient_name'],
        'dod' => $row['dod'],
        'tod' => $row['tod'],
        'cod' => $row['cod'],
        'physician' => $row['physician'],
        'next_of_kin_contact' => $row['next_of_kin_contact'],
        'discharge_status' => $row['discharge_status']
    );
}

echo json_encode($data);
?>
