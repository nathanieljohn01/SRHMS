<?php
session_start(); // Add this line to access session variables
include('includes/connection.php');

$query = isset($_GET['query']) ? mysqli_real_escape_string($connection, $_GET['query']) : '';
$currentUser = isset($_SESSION['name']) ? mysqli_real_escape_string($connection, $_SESSION['name']) : '';
$role = isset($_SESSION['role']) ? (int)$_SESSION['role'] : 0;

// Select all necessary fields including the ID field
$sql = "SELECT o.id, o.patient_id, o.outpatient_id, o.patient_name, o.dob, o.gender, o.doctor_incharge, o.diagnosis, o.date_time 
        FROM tbl_outpatient o 
        WHERE o.deleted = 0";

if ($role == 2) {
    $sql .= " AND o.doctor_incharge = '$currentUser'";  
}

if(!empty($query)) {
    $sql .= " AND (o.patient_id LIKE '%$query%' 
              OR o.outpatient_id LIKE '%$query%' 
              OR o.patient_name LIKE '%$query%'
              OR o.dob LIKE '%$query%'
              OR o.gender LIKE '%$query%'
              OR o.doctor_incharge LIKE '%$query%'
              OR o.diagnosis LIKE '%$query%')";
}

$result = mysqli_query($connection, $sql);
$data = array();

while($row = mysqli_fetch_assoc($result)) {
    // Calculate age
    $dob = $row['dob'];
    $date = str_replace('/', '-', $dob);
    $dob = date('Y-m-d', strtotime($date));
    $year = (date('Y') - date('Y', strtotime($dob)));
    
    // Check if patient has radiology images
    $rad_query = $connection->prepare("SELECT COUNT(*) as count FROM tbl_radiology WHERE patient_id = ? AND radiographic_image IS NOT NULL AND radiographic_image != '' AND deleted = 0");
    $rad_query->bind_param("s", $row['patient_id']);
    $rad_query->execute();
    $rad_result = $rad_query->get_result();
    $rad_count = $rad_result->fetch_assoc()['count'];
    
    $data[] = array(
        'id' => $row['id'],
        'patient_id' => $row['patient_id'],
        'outpatient_id' => $row['outpatient_id'],
        'patient_name' => $row['patient_name'],
        'age' => $year,
        'dob' => $row['dob'],
        'gender' => $row['gender'],
        'doctor_incharge' => $row['doctor_incharge'],
        'diagnosis' => $row['diagnosis'],
        'date_time' => date('F d, Y g:i A', strtotime($row['date_time'])),
        'has_radiology' => ($rad_count > 0),
        'user_role' => $role  // Add user role to response
    );
}

echo json_encode($data);
?>
