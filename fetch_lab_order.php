<?php
include('includes/connection.php');

$query = isset($_GET['query']) ? trim($_GET['query']) : '';
$search_term = "%{$query}%";

$sql = "SELECT lab_department, lab_test, code, lab_test_price 
        FROM tbl_labtest 
        WHERE status = 'Available'
        AND (
            lab_test LIKE ? OR
            code LIKE ? OR
            lab_department LIKE ? OR
            lab_test_price LIKE ?
        )";

$stmt = mysqli_prepare($connection, $sql);
mysqli_stmt_bind_param($stmt, 'ssss', 
    $search_term, $search_term, $search_term, $search_term
);

mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$tests = array();

while ($row = mysqli_fetch_assoc($result)) {
    $tests[] = array(
        'lab_test' => htmlspecialchars($row['lab_test']),
        'code' => htmlspecialchars($row['code']),
        'lab_department' => htmlspecialchars($row['lab_department']),
        'lab_test_price' => htmlspecialchars($row['lab_test_price'])
    );
}

echo json_encode($tests);
