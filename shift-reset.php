<?php
// Include your database connection file
include('includes/connection.php');

// Get the current hour and minute
$current_hour = (int)date('H');
$current_minute = (int)date('i');

// Check if it's exactly 6:00 AM
if ($current_hour == 6 && $current_minute == 0) {
    // Prepare an SQL statement to reset all counters
    $sql = "
        UPDATE tbl_counts
        SET count_in_progress = 0,
            count_completed = 0,
            count_cancelled = 0,
            count_stat = 0;
    ";

    // Execute the SQL query
    if (mysqli_query($connection, $sql)) {
        echo "Counters reset successfully at 6:00 AM.";
    } else {
        echo "Error resetting counters: " . mysqli_error($connection);
    }
} else {
    echo "No reset needed at this time.";
}

// Close the database connection
mysqli_close($connection);
?>
