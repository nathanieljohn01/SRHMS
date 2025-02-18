<?php
include('includes/connection.php');

$query = isset($_GET['query']) ? trim($_GET['query']) : '';
$query = mysqli_real_escape_string($connection, $query);

$sql = "SELECT * FROM tbl_newborn 
        WHERE deleted = 0 
        AND (
            LOWER(newborn_id) LIKE LOWER(?) OR
            LOWER(first_name) LIKE LOWER(?) OR
            LOWER(last_name) LIKE LOWER(?) OR
            LOWER(gender) LIKE LOWER(?) OR
            DATE_FORMAT(dob, '%M %d, %Y') LIKE ? OR
            LOWER(tob) LIKE LOWER(?) OR
            LOWER(birth_weight) LIKE LOWER(?) OR
            LOWER(birth_height) LIKE LOWER(?) OR
            LOWER(gestational_age) LIKE LOWER(?) OR
            LOWER(physician) LIKE LOWER(?)
        )
        ORDER BY dob DESC";

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
        'gestational_age' => htmlspecialchars($row['gestational_age']),
        'physician' => htmlspecialchars($row['physician'])
    );
}

echo json_encode($newborns);
