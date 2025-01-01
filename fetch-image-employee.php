<?php
include('includes/connection.php');

if (isset($_GET['id'])) {
    $id = $_GET['id'];

    // Prepare the query to fetch image data
    $query = "SELECT profile_picture FROM tbl_employee WHERE id = ? AND deleted = 0";
    $stmt = mysqli_prepare($connection, $query);
    mysqli_stmt_bind_param($stmt, "i", $id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_bind_result($stmt, $profile_picture);
    mysqli_stmt_fetch($stmt);
    mysqli_stmt_close($stmt);

    if ($profile_picture) {
        // Clean the output buffer and set headers
        ob_clean();
        header("Content-Type: image/jpeg");        
        echo $profile_picture;
    } else {
        header("HTTP/1.0 404 Not Found");
        echo "Image not found.";
    }
} else {
    header("HTTP/1.0 400 Bad Request");
    echo "Invalid image ID.";
}
?>
