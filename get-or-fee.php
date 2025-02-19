<?php
include('includes/connection.php');

// Get the patient name from the AJAX request
$patient_name = isset($_GET['patient_name']) ? $_GET['patient_name'] : '';

// Ensure we have a valid patient name
if (!empty($patient_name)) {
    // Query to get the patient ID from both inpatient and hemodialysis records
    $sql_patient = "SELECT patient_id FROM (
        SELECT patient_id FROM tbl_inpatient_record WHERE patient_name = ? AND deleted = 0 AND is_billed = 0
        UNION
        SELECT patient_id FROM tbl_hemodialysis WHERE patient_name = ? AND deleted = 0 AND is_billed = 0
    ) combined_patients";
    
    if ($stmt_patient = mysqli_prepare($connection, $sql_patient)) {
        mysqli_stmt_bind_param($stmt_patient, 'ss', $patient_name, $patient_name);
        mysqli_stmt_execute($stmt_patient);
        $result_patient = mysqli_stmt_get_result($stmt_patient);

        // If the patient exists, get their ID
        if ($row_patient = mysqli_fetch_assoc($result_patient)) {
            $patient_id = $row_patient['patient_id'];

            // Query to get operating room fee (operation_type and price) for the patient ID
            $sql_or_fee = "SELECT operation_type, price FROM tbl_operating_room WHERE patient_id = ? AND deleted = 0 AND is_billed = 0";
            if ($stmt_or_fee = mysqli_prepare($connection, $sql_or_fee)) {
                mysqli_stmt_bind_param($stmt_or_fee, 's', $patient_id);
                mysqli_stmt_execute($stmt_or_fee);
                $result_or_fee = mysqli_stmt_get_result($stmt_or_fee);

                // Check if results are found
                if (mysqli_num_rows($result_or_fee) > 0) {
                    $or_fee = [];
                    while ($row = mysqli_fetch_assoc($result_or_fee)) {
                        $or_fee[] = $row;
                    }

                    // Return the results as JSON
                    echo json_encode($or_fee);
                } else {
                    echo json_encode([]); // No operating room fee found for this patient
                }

                mysqli_stmt_close($stmt_or_fee);
            }
        } else {
            echo json_encode([]); // No patient found with the given name
        }

        mysqli_stmt_close($stmt_patient);
    } else {
        echo json_encode([]); // Error with the query
    }
} else {
    echo json_encode([]); // No patient name provided
}
?>
