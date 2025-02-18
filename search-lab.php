<?php
include('includes/connection.php');

// Check if a search query is set
if (isset($_GET['query'])) {
    // Sanitize the input to prevent SQL injection
    $query = trim($_GET['query']);
    $stmt = mysqli_prepare($connection, "
        SELECT patient_id, CONCAT(first_name, ' ', last_name) AS patient_name 
        FROM tbl_patient 
        WHERE CONCAT(first_name, ' ', last_name) LIKE ? 
        AND deleted = 0
    ");

    if ($stmt) {
        // Prepare the search query with wildcards
        $searchQuery = "%" . $query . "%";
        mysqli_stmt_bind_param($stmt, 's', $searchQuery);

        // Execute the prepared statement
        mysqli_stmt_execute($stmt);

        // Get the result set
        $result = mysqli_stmt_get_result($stmt);

        // Check if any results are found
        if ($result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                // Output each patient's name as a div element
                echo '<div class="patient-option">' . htmlspecialchars($row['patient_name']) . '</div>';
            }
        } else {
            // No matches found
            echo '<li class="search-result-none" style="pointer-events: none; color: gray; padding: 8px 12px;">No matching patients found</li>';
        }

        // Close the statement
        mysqli_stmt_close($stmt);
    } else {
        // Handle statement preparation error
        echo '<div class="patient-option text-muted">Error preparing the query.</div>';
    }
}

// Close the database connection (optional but good practice)
mysqli_close($connection);
?>
