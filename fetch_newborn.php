<?php
session_start(); // Add this to access session variables
include('includes/connection.php');

$query = isset($_GET['query']) ? mysqli_real_escape_string($connection, $_GET['query']) : '';
$currentUser = isset($_SESSION['name']) ? mysqli_real_escape_string($connection, $_SESSION['name']) : '';
$role = $_SESSION['role'] ?? 0;

// Base query
$sql = "SELECT n.*, 
        GROUP_CONCAT(
            CONCAT(t.medicine_name, ' (', t.medicine_brand, ') - ', t.total_quantity, ' pcs')
            ORDER BY t.treatment_date 
            SEPARATOR '<br>'
        ) as treatments
        FROM tbl_newborn n
        LEFT JOIN tbl_treatment t ON n.newborn_id = t.newborn_id 
        WHERE n.deleted = 0";

// Add doctor filter if role is doctor (2)
if ($role == 2) {
    $sql .= " AND n.physician = '$currentUser'";
}

// Add search filter if query exists
if(!empty($query)) {
    $sql .= " AND (n.newborn_id LIKE '%$query%' 
              OR n.first_name LIKE '%$query%' 
              OR n.last_name LIKE '%$query%'
              OR n.gender LIKE '%$query%'
              OR n.dob LIKE '%$query%'
              OR n.tob LIKE '%$query%'
              OR n.birth_weight LIKE '%$query%'
              OR n.birth_height LIKE '%$query%'
              OR n.room_type LIKE '%$query%'";
    
    // Only include physician in search if not a doctor
    if ($role != 2) {
        $sql .= " OR n.physician LIKE '%$query%'";
    }
    
    $sql .= ")";
}

$sql .= " GROUP BY n.id, n.newborn_id";
$result = mysqli_query($connection, $sql);

if (!$result) {
    die("Query failed: " . mysqli_error($connection));
}

$data = array();

while($row = mysqli_fetch_assoc($result)) {
    // Format dates
    $admission_date = isset($row['admission_date']) ? date('F j, Y', strtotime($row['admission_date'])) : 'N/A';
    $discharge_date = isset($row['discharge_date']) && $row['discharge_date'] ? date('F j, Y', strtotime($row['discharge_date'])) : 'N/A';
    
    $data[] = array(
        'id' => $row['id'],
        'newborn_id' => $row['newborn_id'],
        'first_name' => $row['first_name'],
        'last_name' => $row['last_name'],
        'gender' => $row['gender'],
        'dob' => $row['dob'],
        'tob' => $row['tob'],
        'birth_weight' => $row['birth_weight'],
        'birth_height' => $row['birth_height'],
        'room_type' => $row['room_type'],
        'admission_date' => $admission_date,
        'discharge_date' => $discharge_date,
        'physician' => $row['physician'],
        'treatments' => $row['treatments'] ? $row['treatments'] : ''
    );
}

echo json_encode($data);
?>
