<?php
session_start();
if (empty($_SESSION['name'])) {
    header('location:index.php');
    exit;
}
include('includes/connection.php');

// Function to sanitize user inputs
function sanitize($connection, $input) {
    return mysqli_real_escape_string($connection, trim($input));
}

// Sanitize the `id` parameter from the URL
$id = sanitize($connection, $_GET['id']);

// Prepare and bind the query for updating discharge_date in tbl_inpatient_record
$update_query = mysqli_prepare($connection, "UPDATE tbl_inpatient_record SET discharge_date = NOW() WHERE id = ?");
mysqli_stmt_bind_param($update_query, "i", $id); // "i" for integer type
mysqli_stmt_execute($update_query);

// Prepare and bind the query for updating discharge_date in tbl_inpatient
$update_inpatient_query = mysqli_prepare($connection, "UPDATE tbl_inpatient SET discharge_date = NOW() WHERE id = ?");
mysqli_stmt_bind_param($update_inpatient_query, "i", $id); // "i" for integer type
mysqli_stmt_execute($update_inpatient_query);

if ($update_query && $update_inpatient_query) {
    // Prepare and bind the query to fetch room_number and bed_number from tbl_inpatient
    $fetch_bed_query = mysqli_prepare($connection, "SELECT room_number, bed_number FROM tbl_inpatient WHERE id = ?");
    mysqli_stmt_bind_param($fetch_bed_query, "i", $id); // "i" for integer type
    mysqli_stmt_execute($fetch_bed_query);
    $result = mysqli_stmt_get_result($fetch_bed_query);
    $bed_row = mysqli_fetch_assoc($result);

    if ($bed_row) {
        $room_number = sanitize($connection, $bed_row['room_number']); // Sanitize fetched data
        $bed_number = sanitize($connection, $bed_row['bed_number']); // Sanitize fetched data

        // Prepare and bind the query to update the bed status in tbl_bedallocation
        $update_bed_status_query = mysqli_prepare($connection, "UPDATE tbl_bedallocation SET status = 'For cleaning' WHERE room_number = ? AND bed_number = ?");
        mysqli_stmt_bind_param($update_bed_status_query, "si", $room_number, $bed_number); // "s" for string, "i" for integer
        mysqli_stmt_execute($update_bed_status_query);

        if ($update_bed_status_query) {
            $msg = "Patient discharged successfully and bed status updated to For cleaning";
        } else {
            $msg = "Error updating bed status!";
        }
    } else {
        $msg = "Error fetching room and bed details!";
    }
} else {
    $msg = "Error updating discharge date!";
}

header('location:inpatients.php');
exit;
?>
