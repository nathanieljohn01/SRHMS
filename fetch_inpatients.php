<?php
include('includes/connection.php');

$query = isset($_GET['query']) ? $_GET['query'] : '';

$sql = "SELECT * FROM tbl_inpatient WHERE deleted = 0";

if(!empty($query)) {
    $sql .= " AND (patient_id LIKE '%$query%' 
              OR inpatient_id LIKE '%$query%' 
              OR patient_name LIKE '%$query%'
              OR gender LIKE '%$query%'
              OR room_type LIKE '%$query%'
              OR room_number LIKE '%$query%'
              OR bed_number LIKE '%$query%')";
}

$result = mysqli_query($connection, $sql);
$data = array();

while($row = mysqli_fetch_assoc($result)) {
    // Calculate age
    $dob = $row['dob'];
    $date = str_replace('/', '-', $dob);
    $dob = date('Y-m-d', strtotime($date));
    $year = (date('Y') - date('Y', strtotime($dob)));
    
    // Format dates
    $admission_date_time = date('F d, Y g:i A', strtotime($row['admission_date']));
    $discharge_date_time = ($row['discharge_date']) ? date('F d, Y g:i A', strtotime($row['discharge_date'])) : 'N/A';
    
    $data[] = array(
        'id' => $row['id'],
        'patient_id' => $row['patient_id'],
        'inpatient_id' => $row['inpatient_id'],
        'patient_name' => $row['patient_name'],
        'age' => $year,
        'gender' => $row['gender'],
        'room_type' => $row['room_type'],
        'room_number' => $row['room_number'],
        'bed_number' => $row['bed_number'],
        'admission_date' => $admission_date_time,
        'discharge_date' => $discharge_date_time,
        'has_discharge' => !empty($row['discharge_date'])
    );
}

echo json_encode($data);
?>
