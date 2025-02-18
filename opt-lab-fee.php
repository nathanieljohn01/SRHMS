<?php
include('includes/connection.php');

// Check if the patient name is provided via AJAX
if (isset($_GET['patient_name'])) {
    $patient_name = trim($_GET['patient_name']);
    
    if (empty($patient_name)) {
        echo json_encode(['error' => 'Patient name is required']);
        exit;
    }

    // Query to fetch lab prices, requested dates, and lab tests based on the patient name
    $sql = "
        SELECT 
            requested_date AS lab_requested_date, 
            price AS lab_price,
            lab_test AS lab_test
        FROM 
            tbl_laborder 
        WHERE 
            patient_name LIKE ? AND 
            deleted = 0 AND is_billed = 0
    ";

    if ($stmt = mysqli_prepare($connection, $sql)) {
        // Bind the patient name for the query (with wildcards for partial matching)
        $search_term = "%{$patient_name}%";
        mysqli_stmt_bind_param($stmt, 's', $search_term);
        
        // Execute the query
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);

        // Prepare an array to hold the results
        $data = [];

        // Fetch the data from the result set
        while ($row = mysqli_fetch_assoc($result)) {
            $data[] = [
                'lab_test' => $row['lab_test'],
                'lab_price' => $row['lab_price'],
                'lab_requested_date' => date('F d, Y g:i A', strtotime($row['lab_requested_date']))
            ];
        }
        
        // Check if data was found
        if (empty($data)) {
            echo json_encode(['message' => 'No results found']);
        } else {
            // Output results as JSON
            echo json_encode($data);
        }

        // Close the prepared statement
        mysqli_stmt_close($stmt);
    } else {
        echo json_encode(['error' => 'Failed to prepare the query']);
    }
}
?>
