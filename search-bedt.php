<?php
include('includes/connection.php');

if (isset($_GET['query'])) {
    $query = sanitize($connection, $_GET['query']); // Sanitize input

    // Prepare and bind the query to fetch data from tbl_inpatient_record, with discharge_date as NULL
    $query_sql = "SELECT * FROM tbl_inpatient_record WHERE patient_name LIKE ? AND (discharge_date IS NULL OR discharge_date = '')";  // Added condition for discharge_date being NULL
    $stmt = $connection->prepare($query_sql);
    $search_term = "%" . $query . "%";  // Adding wildcards to match any patient_name containing the query term
    $stmt->bind_param("s", $search_term);  // Binding the search term

    // Execute the query
    $stmt->execute();
    $result = $stmt->get_result();

    // Output the results
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            echo '<li class="search-result" data-id="' . $row['id'] . '">' . $row['patient_name'] . '</li>';
        }
    } else {
        echo '<li class="search-result-none" style="pointer-events: none; color: gray; padding: 8px 12px;">No matching patients found</li>';
    }

    // Close the statement
    $stmt->close();
}

function sanitize($connection, $input) {
    return mysqli_real_escape_string($connection, $input);
}
?>

