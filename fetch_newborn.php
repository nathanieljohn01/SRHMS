<?php
include('includes/connection.php');

$query = isset($_GET['query']) ? trim($_GET['query']) : '';
$query = mysqli_real_escape_string($connection, $query);

$sql = "SELECT n.*, 
    GROUP_CONCAT(
        CONCAT(t.medicine_name, ' (', t.medicine_brand, ') - ', t.total_quantity, ' pcs')
        ORDER BY t.treatment_date 
        SEPARATOR '<br>'
    ) as treatments,
    n.admission_datetime,
    n.discharge_datetime
    FROM tbl_newborn n
    LEFT JOIN tbl_treatment t ON n.newborn_id = t.newborn_id 
    WHERE n.deleted = 0 
    AND (
        LOWER(n.newborn_id) LIKE LOWER(?) OR
        LOWER(n.first_name) LIKE LOWER(?) OR
        LOWER(n.last_name) LIKE LOWER(?) OR
        LOWER(n.gender) LIKE LOWER(?) OR
        DATE_FORMAT(n.dob, '%M %d, %Y') LIKE ? OR
        LOWER(n.tob) LIKE LOWER(?) OR
        LOWER(n.birth_weight) LIKE LOWER(?) OR
        LOWER(n.birth_height) LIKE LOWER(?) OR
        LOWER(n.room_type) LIKE LOWER(?) OR
        LOWER(n.physician) LIKE LOWER(?)
    )
    GROUP BY n.id, n.newborn_id
    ORDER BY n.admission_datetime DESC";

$search_term = "%{$query}%";
$stmt = mysqli_prepare($connection, $sql);
mysqli_stmt_bind_param($stmt, 'ssssssssss',
    $search_term, $search_term, $search_term, $search_term, $search_term,
    $search_term, $search_term, $search_term, $search_term, $search_term
);

mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$newborns = array();

while ($row = mysqli_fetch_assoc($result)) {
    // Format dates using PHP date function
    $admission_datetime = date('F j, Y g:i A', strtotime($row['admission_datetime']));
    $discharge_datetime = $row['discharge_datetime'] ? date('F j, Y g:i A', strtotime($row['discharge_datetime'])) : null;

    $newborns[] = array(
        'id' => $row['id'],
        'newborn_id' => htmlspecialchars($row['newborn_id']),
        'first_name' => htmlspecialchars($row['first_name']),
        'last_name' => htmlspecialchars($row['last_name']),
        'gender' => htmlspecialchars($row['gender']),
        'dob' => htmlspecialchars($row['dob']),
        'tob' => htmlspecialchars($row['tob']),
        'birth_weight' => htmlspecialchars($row['birth_weight']),
        'birth_height' => htmlspecialchars($row['birth_height']),
        'room_type' => htmlspecialchars($row['room_type']),
        'admission_datetime' => $admission_datetime,
        'discharge_datetime' => $discharge_datetime,
        'physician' => htmlspecialchars($row['physician']),
        'treatments' => $row['treatments'] ? $row['treatments'] : ''
    );
}

echo json_encode($newborns);
