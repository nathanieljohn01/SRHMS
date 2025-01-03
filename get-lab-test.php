<?php
session_start();
if (empty($_SESSION['name'])) {
    header('location:index.php');
    exit; // Ensure script stops execution after redirection
}

include('header.php');
include('includes/connection.php');

// Check if category is set and sanitize it
if (isset($_POST['category']) && !empty($_POST['category'])) {
    // Sanitize the category input to avoid malicious data
    $category = htmlspecialchars($_POST['category'], ENT_QUOTES, 'UTF-8');
    
    // Prepared statement to prevent SQL injection
    $sql = "SELECT id, lab_test FROM tbl_labtest WHERE lab_department = ?";
    $stmt = $connection->prepare($sql); // Prepare the SQL query
    
    // Bind the parameter (category) to the prepared statement
    $stmt->bind_param("s", $category); // "s" for string type
    
    // Execute the query
    $stmt->execute();
    
    // Get the result
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            // Use htmlspecialchars() to escape output to prevent XSS
            echo "<option value='" . htmlspecialchars($row['lab_test'], ENT_QUOTES, 'UTF-8') . "'>" . htmlspecialchars($row['lab_test'], ENT_QUOTES, 'UTF-8') . "</option>";
        }
    } else {
        echo "<option value=''>No lab tests found</option>";
    }

    // Close the prepared statement
    $stmt->close();
}

mysqli_close($connection);
?>
