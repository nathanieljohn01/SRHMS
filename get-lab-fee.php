<?php
include('includes/connection.php');

$patient_name = isset($_GET['patient_name']) ? $_GET['patient_name'] : '';

if (!empty($patient_name)) {
    $sql_lab_tests = "SELECT lab_test, COALESCE(NULLIF(price, ''), 0) as price 
                      FROM tbl_laborder 
                      WHERE patient_name = ? 
                      AND is_billed = 0 
                      AND status = 'Completed'";

    if ($stmt_lab_tests = mysqli_prepare($connection, $sql_lab_tests)) {
        mysqli_stmt_bind_param($stmt_lab_tests, 's', $patient_name);
        mysqli_stmt_execute($stmt_lab_tests);
        $result_lab_tests = mysqli_stmt_get_result($stmt_lab_tests);

        if (mysqli_num_rows($result_lab_tests) > 0) {
            $lab_tests = [];
            while ($row = mysqli_fetch_assoc($result_lab_tests)) {
                $lab_tests[] = $row;
            }
            echo json_encode($lab_tests);
        } else {
            echo json_encode([]);
        }
        mysqli_stmt_close($stmt_lab_tests);
    } else {
        echo json_encode([]);
    }
} else {
    echo json_encode([]);
}
?>
