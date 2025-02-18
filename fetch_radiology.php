<?php
include('includes/connection.php');

$query = isset($_GET['query']) ? trim($_GET['query']) : '';
$search_term = "%{$query}%";

$sql = "SELECT exam_type, test_type, price 
        FROM tbl_radtest 
        WHERE exam_type LIKE ? OR
              test_type LIKE ? OR
              price LIKE ?";

$stmt = mysqli_prepare($connection, $sql);
mysqli_stmt_bind_param($stmt, 'sss', 
    $search_term, $search_term, $search_term
);

mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$radtests = array();

while ($row = mysqli_fetch_assoc($result)) {
    $radtests[] = array(
        'exam_type' => htmlspecialchars($row['exam_type']),
        'test_type' => htmlspecialchars($row['test_type']),
        'price' => number_format($row['price'], 2)
    );
}

echo json_encode($radtests);
