<?php
session_start();
include('includes/connection.php');

$query = isset($_GET['query']) ? $_GET['query'] : '';
$currentUser = $_SESSION['name'] ?? '';
$role = $_SESSION['role'] ?? 0;

// Base query
$sql = "SELECT r.*, i.discharge_date, 
        GROUP_CONCAT(CONCAT(t.medicine_name, ' (', t.medicine_brand, ') - ', t.total_quantity, ' pcs') SEPARATOR '<br>') AS treatments
        FROM tbl_inpatient_record r
        LEFT JOIN tbl_inpatient i ON r.inpatient_id = i.inpatient_id
        LEFT JOIN tbl_treatment t ON r.inpatient_id = t.inpatient_id
        WHERE r.deleted = 0";

// Add doctor filter if role is doctor (2)
if ($role == 2) {
    $sql .= " AND r.doctor_incharge = '$currentUser'";
}

// Add search filter if query exists
if (!empty($query)) {
    $sql .= " AND (r.patient_id LIKE '%$query%' 
              OR r.inpatient_id LIKE '%$query%' 
              OR r.patient_name LIKE '%$query%'
              OR r.gender LIKE '%$query%'
              OR r.doctor_incharge LIKE '%$query%'
              OR r.diagnosis LIKE '%$query%'
              OR r.room_type LIKE '%$query%')";
}

$sql .= " GROUP BY r.inpatient_id";
$result = mysqli_query($connection, $sql);

if (!$result) {
    die("Query failed: " . mysqli_error($connection));
}

$data = array();

while ($row = mysqli_fetch_assoc($result)) {
    $dob = $row['dob'];
    $date = str_replace('/', '-', $dob);
    $dob = date('Y-m-d', strtotime($date));
    $year = (date('Y') - date('Y', strtotime($dob)));
    
    $admission_date_time = date('F d, Y g:i A', strtotime($row['admission_date']));
    $discharge_date_time = ($row['discharge_date']) ? date('F d, Y g:i A', strtotime($row['discharge_date'])) : 'N/A';
    $treatmentDetails = $row['treatments'] ?: 'No treatments added';

    $data[] = array(
        'id' => $row['id'], // Added ID for delete functionality
        'patient_id' => $row['patient_id'],
        'inpatient_id' => $row['inpatient_id'], 
        'patient_name' => $row['patient_name'],
        'age' => $year,
        'gender' => $row['gender'],
        'doctor_incharge' => $row['doctor_incharge'],
        'diagnosis' => $row['diagnosis'],
        'treatments' => $treatmentDetails,
        'room_type' => $row['room_type'],
        'room_number' => $row['room_number'],
        'bed_number' => $row['bed_number'],
        'admission_date' => $admission_date_time,
        'discharge_date' => $discharge_date_time
    );
}

echo json_encode($data);
?>