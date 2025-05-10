<?php
session_start(); // Add this line to access session variables
include('includes/connection.php');

$query = isset($_GET['query']) ? mysqli_real_escape_string($connection, $_GET['query']) : '';
$currentUser = isset($_SESSION['name']) ? mysqli_real_escape_string($connection, $_SESSION['name']) : '';
$role = isset($_SESSION['role']) ? (int)$_SESSION['role'] : 0;

$sql = "
    SELECT h.*,
    GROUP_CONCAT(CONCAT(t.medicine_name, ' (', t.medicine_brand, ') - ', t.total_quantity, ' pcs') SEPARATOR '<br>') AS treatments
    FROM tbl_hemodialysis h
    LEFT JOIN tbl_treatment t ON h.hemopatient_id = t.hemopatient_id
    WHERE h.deleted = 0
";

if ($role == 2) {
    $sql .= " AND h.doctor_incharge = '$currentUser'";
}

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
if (!$result) {
    die("Query failed: " . mysqli_error($connection));
}

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
    
    // Check if patient has radiology images
    $rad_query = $connection->prepare("SELECT COUNT(*) as count FROM tbl_radiology WHERE patient_id = ? AND radiographic_image IS NOT NULL AND radiographic_image != '' AND deleted = 0");
    $rad_query->bind_param("s", $row['patient_id']);
    $rad_query->execute();
    $rad_result = $rad_query->get_result();
    $rad_count = $rad_result->fetch_assoc()['count'];
    
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
        'follow_up_date' => $follow_up_date,
        'doctor_incharge' => $row['doctor_incharge'],
        'has_radiology' => ($rad_count > 0),
        'user_role' => $role  // Add user role to response
    );
}

echo json_encode($data);
?>
