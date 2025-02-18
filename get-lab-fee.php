<?php
include('includes/connection.php');

// Get the patient name from the AJAX request
$patient_name = isset($_GET['patient_name']) ? $_GET['patient_name'] : '';

// Ensure we have a valid patient name
if (!empty($patient_name)) {
    // Query to get the patient ID from both inpatient and hemodialysis records
    $sql_patient = "SELECT patient_id FROM (
        SELECT patient_id FROM tbl_inpatient_record WHERE patient_name = ? AND deleted = 0
        UNION
        SELECT patient_id FROM tbl_hemodialysis WHERE patient_name = ? AND deleted = 0
    ) combined_patients";
   
    if ($stmt_patient = mysqli_prepare($connection, $sql_patient)) {
        mysqli_stmt_bind_param($stmt_patient, 'ss', $patient_name, $patient_name);
        mysqli_stmt_execute($stmt_patient);
        $result_patient = mysqli_stmt_get_result($stmt_patient);

        if ($row_patient = mysqli_fetch_assoc($result_patient)) {
            $patient_id = $row_patient['patient_id'];

            // Query to get lab tests and prices for the patient ID
            $sql_lab_tests = "SELECT lab_test, price FROM tbl_laborder WHERE patient_id = ? AND deleted = 0 AND is_billed = 0";
            if ($stmt_lab_tests = mysqli_prepare($connection, $sql_lab_tests)) {
                mysqli_stmt_bind_param($stmt_lab_tests, 's', $patient_id);
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
            }
        } else {
            echo json_encode([]);
        }
        mysqli_stmt_close($stmt_patient);
    } else {
        echo json_encode([]);
    }
} else {
    echo json_encode([]);
}
?>
