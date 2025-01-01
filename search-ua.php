<?php
include('includes/connection.php');

// Check if a search query is set
if (isset($_GET['query'])) {
    // Get the query parameter
    $query = sanitize($connection, $_GET['query']);

    // Prepare and bind the query to fetch patient data from tbl_laborder
    $query_sql = "SELECT DISTINCT id, patient_name FROM tbl_laborder WHERE lab_test = 'Urinalysis' AND status = 'Completed' AND patient_name LIKE ?"; 
    $stmt = $connection->prepare($query_sql);
    $search_term = "%" . $query . "%";  // Adding wildcards to match any patient_name containing the query term
    $stmt->bind_param("s", $search_term);  // Binding the search term to the SQL statement

    // Execute the query
    $stmt->execute();
    $result = $stmt->get_result();

    // Output the results as divs with patient-option class
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            // Output each patient as a div with patient_name
            echo '<li class="search-result" data-id="' . $row['id'] . '">' . $row['patient_name'] . '</li>';
        }
    } else {
        echo '<li class="search-result text-muted">No matching patients found</li>';
    }

    // Close the statement
    $stmt->close();
}

function sanitize($connection, $input) {
    return mysqli_real_escape_string($connection, $input);
}
?>
