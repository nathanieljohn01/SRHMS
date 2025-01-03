<?php
include('includes/connection.php');

// Get the patient name from the AJAX request
$patient_name = isset($_GET['patient_name']) ? $_GET['patient_name'] : '';

// Ensure we have a valid patient name
if (!empty($patient_name)) {
    // Query to get the patient medications
    $sql_medications = "
      SELECT 
        treatment_date,
        medicine_name AS name,
        medicine_brand AS brand,
        SUM(total_quantity) AS total_quantity,
        price,
        SUM(total_price) AS total_price
        FROM tbl_treatment
        WHERE patient_name = ? AND deleted = 0
        GROUP BY treatment_date, medicine_name, medicine_brand
        ORDER BY treatment_date DESC;
        ";

    if ($stmt_medications = mysqli_prepare($connection, $sql_medications)) {
        mysqli_stmt_bind_param($stmt_medications, 's', $patient_name);
        mysqli_stmt_execute($stmt_medications);
        $result_medications = mysqli_stmt_get_result($stmt_medications);

        // Check if any medications are found
        if (mysqli_num_rows($result_medications) > 0) {
            $medications = [];

            // Fetch all medication details
            while ($row = mysqli_fetch_assoc($result_medications)) {
                $medications[] = [
                    'treatment_date' => (new DateTime($row['treatment_date']))->format('F d, Y g:i A'),
                    'name' => $row['name'],
                    'brand' => $row['brand'],
                    'total_quantity' => $row['total_quantity'],
                    'price' => number_format($row['price'], 2),
                    'total_price' => number_format($row['total_price'], 2)
                ];
            }

            // Send back the response as JSON
            echo json_encode([
                'success' => true,
                'medications' => $medications
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => 'No medications found for this patient']);
        }

        mysqli_stmt_close($stmt_medications);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error with the query']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'No patient name provided']);
}
?>
