<?php
include('includes/connection.php');

$query = isset($_GET['query']) ? trim($_GET['query']) : '';
$query = mysqli_real_escape_string($connection, $query);

$sql = "SELECT * FROM tbl_medicines 
        WHERE deleted = 0 
        AND (
            LOWER(medicine_name) LIKE LOWER(?) OR
            LOWER(medicine_brand) LIKE LOWER(?) OR
            LOWER(category) LIKE LOWER(?) OR
            REPLACE(weight_measure, ' ', '') LIKE ? OR
            REPLACE(unit_measure, ' ', '') LIKE ? OR
            CAST(quantity AS CHAR) LIKE ? OR
            DATE_FORMAT(expiration_date, '%M %d, %Y %h:%i %p') LIKE ? OR
            CAST(price AS CHAR) LIKE ?
        )
        ORDER BY expiration_date ASC";

$search_term = "%{$query}%";
$stmt = mysqli_prepare($connection, $sql);
mysqli_stmt_bind_param($stmt, 'ssssssss', 
    $search_term, 
    $search_term, 
    $search_term,
    $search_term,
    $search_term,
    $search_term,
    $search_term,
    $search_term
);

mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$medicines = array();

while ($row = mysqli_fetch_assoc($result)) {
    $medicines[] = array(
        'id' => $row['id'],
        'medicine_name' => htmlspecialchars($row['medicine_name']),
        'medicine_brand' => htmlspecialchars($row['medicine_brand']),
        'category' => htmlspecialchars($row['category']),
        'weight_measure' => htmlspecialchars($row['weight_measure']),
        'unit_measure' => htmlspecialchars($row['unit_measure']),
        'quantity' => $row['quantity'],
        'expiration_date' => date('F d, Y g:i A', strtotime($row['expiration_date'])),
        'price' => htmlspecialchars($row['price'])
    );
}

echo json_encode($medicines);
?>
