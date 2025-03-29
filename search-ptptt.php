<?php
include('includes/connection.php');

// Check if a search query is set
if (isset($_GET['query'])) {
    // Sanitize the input query to prevent SQL injection
    $query = sanitize($connection, $_GET['query']);

    // Prepare and bind the query to fetch patient data from tbl_ptptt
    $query_sql = "SELECT DISTINCT ptptt_id, patient_name FROM tbl_ptptt 
                  WHERE (prothrombin_control LIKE ? 
                  OR prothrombin_test LIKE ? 
                  OR prothrombin_inr LIKE ? 
                  OR prothrombin_activity LIKE ? 
                  OR prothrombin_result LIKE ? 
                  OR prothrombin_normal_values LIKE ? 
                  OR ptt_control LIKE ? 
                  OR ptt_patient_result LIKE ? 
                  OR ptt_normal_values LIKE ? 
                  OR remarks LIKE ?) 
                  AND deleted = 0";
    
    $stmt = $connection->prepare($query_sql);
    $search_term = "%" . $query . "%";  // Adding wildcards to match any field containing the query term
    $stmt->bind_param("ssssssssss", 
        $search_term, $search_term, $search_term, $search_term, 
        $search_term, $search_term, $search_term, $search_term, 
        $search_term, $search_term
    );  

    // Execute the query
    $stmt->execute();
    $result = $stmt->get_result();

    // Output the results as list items with search-result class
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            // Output each patient as a list item with patient_name
            echo '<li class="search-result" data-id="' . $row['ptptt_id'] . '">' . $row['patient_name'] . '</li>';
        }
    } else {
        echo '<li class="search-result-none" style="pointer-events: none; color: gray; padding: 8px 12px;">No matching records found</li>';
    }

    // Close the statement
    $stmt->close();
}

// Function to sanitize input
function sanitize($connection, $input) {
    return mysqli_real_escape_string($connection, $input);
}
?>
