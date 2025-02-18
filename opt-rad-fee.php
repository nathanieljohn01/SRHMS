<?php
include('includes/connection.php');

if (isset($_GET['patient_name'])) {
    $patient_name = trim($_GET['patient_name']);
    
    if (empty($patient_name)) {
        echo json_encode(['error' => 'Patient name is required']);
        exit;
    }

    $sql = "
        SELECT
            requested_date,
            price,
            test_type
        FROM
            tbl_radiology
        WHERE
            patient_name LIKE ? AND
            deleted = 0 AND is_billed = 0
    ";

    if ($stmt = mysqli_prepare($connection, $sql)) {
        $search_term = "%{$patient_name}%";
        mysqli_stmt_bind_param($stmt, 's', $search_term);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);

        $data = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $data[] = [
                'test_type' => $row['test_type'],
                'price' => $row['price'],
                'requested_date' => date('F d, Y g:i A', strtotime($row['requested_date']))
            ];
        }

        echo json_encode($data);
        mysqli_stmt_close($stmt);
    }
}
?>
