<?php
include('includes/connection.php');

$query = isset($_GET['query']) ? mysqli_real_escape_string($connection, $_GET['query']) : '';
$query = mysqli_real_escape_string($connection, $query);

$sql = "SELECT 
            patient_id, 
            patient_type, 
            first_name, 
            last_name, 
            dob, 
            gender, 
            date_time 
        FROM tbl_patient 
        WHERE deleted = 0 
        AND (
            LOWER(patient_id) LIKE LOWER(?) OR
            LOWER(patient_type) LIKE LOWER(?) OR
            LOWER(first_name) LIKE LOWER(?) OR
            LOWER(last_name) LIKE LOWER(?) OR
            LOWER(gender) LIKE LOWER(?) OR
            DATE_FORMAT(date_time, '%M %d, %Y %h:%i %p') LIKE ?
        )
        ORDER BY last_name ASC";

$search_term = "%{$query}%";
$stmt = mysqli_prepare($connection, $sql);
mysqli_stmt_bind_param($stmt, 'ssssss',
    $search_term, $search_term, $search_term, 
    $search_term, $search_term, $search_term
);  

mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$patients = array();

while ($row = mysqli_fetch_assoc($result)) {
    $dob = date('Y-m-d', strtotime(str_replace('/', '-', $row['dob'])));
    $age = date('Y') - date('Y', strtotime($dob));
    
    $patients[] = array(
        'patient_id' => htmlspecialchars($row['patient_id']),
        'patient_type' => htmlspecialchars($row['patient_type']),
        'first_name' => htmlspecialchars($row['first_name']),
        'last_name' => htmlspecialchars($row['last_name']),
        'age' => $age,
        'gender' => htmlspecialchars($row['gender']),
        'date_time' => date('F d, Y g:i A', strtotime($row['date_time']))
    );
}

echo json_encode($patients);
