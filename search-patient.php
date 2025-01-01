<?php
include('includes/connection.php');

if (isset($_GET['query'])) {
    $query = $_GET['query'];
    $stmt = mysqli_prepare($connection, "SELECT patient_id, CONCAT(first_name, ' ', last_name) as name FROM tbl_patient WHERE CONCAT(first_name, ' ', last_name) LIKE ? AND deleted = 0");
    $searchQuery = "%" . $query . "%";
    mysqli_stmt_bind_param($stmt, 's', $searchQuery);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    if (mysqli_num_rows($result) > 0) {
        while ($row = mysqli_fetch_assoc($result)) {
            echo '<div class="patient-result" data-id="' . $row['patient_id'] . '">' . $row['name'] . '</div>';
        }
    } else {
        echo '<div>No patients found</div>';
    }
}
?>
