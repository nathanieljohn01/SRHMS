<?php
include('includes/connection.php');

if (isset($_GET['query'])) {
    $query = sanitize($connection, $_GET['query']); // Sanitize input

    // Prepare and bind the query to fetch data from tbl_inpatient
    $query_sql = "SELECT * FROM tbl_inpatient WHERE patient_name LIKE ?";  // Using parameterized query for security
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
        echo '<li class="search-result text-muted">No matching patients found</li>';
    }

    // Close the statement
    $stmt->close();
}

function sanitize($connection, $input) {
    return mysqli_real_escape_string($connection, $input);
}
?>
