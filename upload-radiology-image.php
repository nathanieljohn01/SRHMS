<?php
include('includes/connection.php');

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_FILES['radiologyImage']) && isset($_POST['radiologyId'])) {
    $radiologyId = $_POST['radiologyId'];
    $image = $_FILES['radiologyImage'];

    // Validate the file upload
    if ($image['error'] !== 0) {
        echo json_encode(['success' => false, 'error' => 'File upload error']);
        exit;
    }

    // Validate file type and size
    $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
    $maxFileSize = 2 * 1024 * 1024; // 2 MB

    if (!in_array($image['type'], $allowedTypes)) {
        echo json_encode(['success' => false, 'error' => 'Invalid file type. Only JPG, PNG, and GIF are allowed.']);
        exit;
    }

    if ($image['size'] > $maxFileSize) {
        echo json_encode(['success' => false, 'error' => 'File size exceeds the 2MB limit.']);
        exit;
    }

    // Get image binary data
    $imageData = file_get_contents($image['tmp_name']);

    // Prepare the SQL query to update the image BLOB
    $query = "UPDATE tbl_radiology SET radiographic_image = ? WHERE id = ?";
    $stmt = $connection->prepare($query);

    if (!$stmt) {
        echo json_encode(['success' => false, 'error' => 'Database preparation error: ' . $connection->error]);
        exit;
    }

    $stmt->bind_param('si', $imageData, $radiologyId);

    if ($stmt->execute()) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Database execution error: ' . $stmt->error]);
    }

    $stmt->close();
} else {
    echo json_encode(['success' => false, 'error' => 'Invalid request or missing parameters']);
}
exit;
?>
