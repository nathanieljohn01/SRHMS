<?php
include('includes/connection.php');

if (isset($_GET['query'])) {
    $query = sanitize($connection, $_GET['query']); // Sanitize input

    // Query to fetch data from tbl_patient
    $result = mysqli_query($connection, "
        SELECT * 
        FROM tbl_patient 
        WHERE 
            CONCAT(first_name, ' ', last_name) LIKE '%$query%' 
            AND patient_type = 'Inpatient'
    ");

    // Output the results
    if (mysqli_num_rows($result) > 0) {
        while ($row = mysqli_fetch_assoc($result)) {
            echo '<li class="search-result" data-id="' . $row['id'] . '">' . $row['first_name'] . ' ' . $row['last_name'] . '</li>';
        }
    } else {
        echo '<li class="search-result-none" style="pointer-events: none; color: gray; padding: 8px 12px;">No matching patients found</li>';
    }
}

function sanitize($connection, $input) {
    return mysqli_real_escape_string($connection, $input);
}
?>
