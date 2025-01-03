<?php
include('includes/connection.php');

// Start output buffering
ob_start();

if (isset($_GET['id'])) {
    $id = $_GET['id'];

    // Fetch the image data from the database
    $query = "SELECT radiographic_image FROM tbl_radiology WHERE id = ? AND deleted = 0";
    $stmt = mysqli_prepare($connection, $query);
    mysqli_stmt_bind_param($stmt, "i", $id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_bind_result($stmt, $radiographic_image);
    mysqli_stmt_fetch($stmt);
    mysqli_stmt_close($stmt);

    if ($radiographic_image) {
        // Clean the output buffer to avoid any unwanted content before sending the image
        ob_clean();

        // Set the content type for the image (JPEG in this case)
        header("Content-Type: image/jpeg");

        // Output the image data
        echo $radiographic_image;
    } else {
        echo "Image not found.";
    }
} else {
    echo "Invalid image ID.";
}

// End the output buffering
ob_end_flush();
?>

