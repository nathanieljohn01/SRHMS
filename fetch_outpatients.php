<?php
session_start();
include('includes/connection.php');

$query = isset($_GET['query']) ? $_GET['query'] : '';
$currentUser = $_SESSION['name'] ?? '';
$role = $_SESSION['role'] ?? 0;

// Base query
$sql = "SELECT patient_id, outpatient_id, patient_name, dob, gender, doctor_incharge, diagnosis, date_time 
        FROM tbl_outpatient 
        WHERE deleted = 0";

// Add doctor filter if role is doctor (2)
if ($role == 2) {
    $sql .= " AND doctor_incharge = '$currentUser'";
}

// Add search filter if query exists
if (!empty($query)) {
    $sql .= " AND (patient_id LIKE '%$query%' 
              OR outpatient_id LIKE '%$query%' 
              OR patient_name LIKE '%$query%'
              OR dob LIKE '%$query%'
              OR gender LIKE '%$query%'
              OR doctor_incharge LIKE '%$query%'
              OR diagnosis LIKE '%$query%')";
}

$result = mysqli_query($connection, $sql);

if (!$result) {
    die("Query failed: " . mysqli_error($connection));
}

$data = array();

while ($row = mysqli_fetch_assoc($result)) {
    // Calculate age
    $dob = $row['dob'];
    $date = str_replace('/', '-', $dob);
    $dob = date('Y-m-d', strtotime($date));
    $year = (date('Y') - date('Y', strtotime($dob)));
    
    $data[] = array(
        'patient_id' => $row['patient_id'],
        'outpatient_id' => $row['outpatient_id'],
        'patient_name' => $row['patient_name'],
        'age' => $year,
        'dob' => $row['dob'],
        'gender' => $row['gender'],
        'doctor_incharge' => $row['doctor_incharge'],
        'diagnosis' => $row['diagnosis'],
        'date_time' => date('F d, Y g:i A', strtotime($row['date_time']))
    );
}

echo json_encode($data);
?>