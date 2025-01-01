<?php
include('includes/connection.php');

if (isset($_GET['query'])) {
    $query = sanitize($connection, $_GET['query']); // Sanitize input

    // Prepare SQL query with a placeholder for the search term
    $sql = "
        SELECT * 
        FROM tbl_patient 
        WHERE CONCAT(first_name, ' ', last_name) LIKE ? 
        AND patient_type IS NOT NULL
    ";

    // Prepare the statement
    if ($stmt = mysqli_prepare($connection, $sql)) {
        // Bind the sanitized query string to the statement
        $searchTerm = "%$query%"; // Add percent signs for LIKE clause
        mysqli_stmt_bind_param($stmt, 's', $searchTerm);

        // Execute the prepared statement
        mysqli_stmt_execute($stmt);

        // Get the result
        $result = mysqli_stmt_get_result($stmt);

        // Output the results
        if (mysqli_num_rows($result) > 0) {
            while ($row = mysqli_fetch_assoc($result)) {
                echo '<li class="search-result" data-id="' . $row['id'] . '">' . $row['first_name'] . ' ' . $row['last_name'] . '</li>';
            }
        } else {
            echo '<li class="search-result text-muted">No matching patients found</li>';
        }

        // Close the statement
        mysqli_stmt_close($stmt);
    } else {
        echo "Error: Could not prepare query.";
    }
}

function sanitize($connection, $input) {
    return mysqli_real_escape_string($connection, $input);
}
?>

