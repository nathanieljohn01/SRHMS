<?php
include('includes/connection.php');

$patient_name = isset($_GET['patient_name']) ? $_GET['patient_name'] : '';

if (!empty($patient_name)) {
    $sql_rad_tests = "SELECT exam_type, COALESCE(NULLIF(price, ''), 0) as price 
                      FROM tbl_radiology 
                      WHERE patient_name = ? 
                      AND deleted = 0 
                      AND is_billed = 0";

    if ($stmt_rad_tests = mysqli_prepare($connection, $sql_rad_tests)) {
        mysqli_stmt_bind_param($stmt_rad_tests, 's', $patient_name);
        mysqli_stmt_execute($stmt_rad_tests);
        $result_rad_tests = mysqli_stmt_get_result($stmt_rad_tests);

        if (mysqli_num_rows($result_rad_tests) > 0) {
            $rad_tests = [];
            while ($row = mysqli_fetch_assoc($result_rad_tests)) {
                $rad_tests[] = $row;
            }
            echo json_encode($rad_tests);
        } else {
            echo json_encode([]);
        }
        mysqli_stmt_close($stmt_rad_tests);
    } else {
        echo json_encode([]);
    }
} else {
    echo json_encode([]);
}
?>
