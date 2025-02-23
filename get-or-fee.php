<?php
include('includes/connection.php');

// Get the patient name from the AJAX request
$patient_name = isset($_GET['patient_name']) ? $_GET['patient_name'] : '';

// Ensure we have a valid patient name
if (!empty($patient_name)) {
    // Query to get operating room fee (operation_type and price) directly using patient_name
    $sql_or_fee = "SELECT operation_type, COALESCE(NULLIF(price, ''), 0) as price 
                   FROM tbl_operating_room 
                   WHERE patient_name = ? 
                   AND is_billed = 0";

    if ($stmt_or_fee = mysqli_prepare($connection, $sql_or_fee)) {
        mysqli_stmt_bind_param($stmt_or_fee, 's', $patient_name);
        mysqli_stmt_execute($stmt_or_fee);
        $result_or_fee = mysqli_stmt_get_result($stmt_or_fee);

        // Check if results are found
        if (mysqli_num_rows($result_or_fee) > 0) {
            $or_fee = [];
            while ($row = mysqli_fetch_assoc($result_or_fee)) {
                $or_fee[] = $row;
            }
            echo json_encode($or_fee);
        } else {
            echo json_encode([]); // No operating room fee found
        }

        mysqli_stmt_close($stmt_or_fee);
    } else {
        echo json_encode([]); // Error with the query
    }
} else {
    echo json_encode([]); // No patient name provided
}
?>
