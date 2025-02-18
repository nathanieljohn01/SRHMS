<?php
include('includes/connection.php');

// Get the search query from the URL and trim any unwanted spaces
$query = isset($_GET['query']) ? trim($_GET['query']) : '';

// Check if the query is not empty
if (!empty($query)) {
    // Prepare the SQL query to search for patients by name and check for discharge_date being non-null
    $sql = "SELECT patient_id, patient_name FROM tbl_inpatient_record WHERE patient_name LIKE ? AND discharge_date IS NOT NULL";
    
    // Prepare the statement
    if ($stmt = mysqli_prepare($connection, $sql)) {
        // Bind the search query to the statement (using wildcard '%' for partial matching)
        $search_term = "%" . $query . "%";
        mysqli_stmt_bind_param($stmt, 's', $search_term);

        // Execute the query
        mysqli_stmt_execute($stmt);

        // Get the result
        $result = mysqli_stmt_get_result($stmt);

        // Check if any patients were found
        if (mysqli_num_rows($result) > 0) {
            // Loop through the results and output each patient's name as a list item
            while ($row = mysqli_fetch_assoc($result)) {
                // Output each patient, ensuring the name is safely output to the HTML
                echo '<div class="patient-option" data-patient-id="' . htmlspecialchars($row['patient_id'], ENT_QUOTES, 'UTF-8') . '">' . htmlspecialchars($row['patient_name'], ENT_QUOTES, 'UTF-8') . '</div>';
            }
        } else {
            // If no patients were found, display a message
            echo '<li class="search-result-none" style="pointer-events: none; color: gray; padding: 8px 12px;">No matching patients found</li>';
        }

        // Close the prepared statement
        mysqli_stmt_close($stmt);
    } else {
        echo 'Error: Unable to prepare the SQL query.';
    }
}
?>
