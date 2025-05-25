<?php
session_start();
include('includes/connection.php');

if (isset($_GET['patient_id'])) {
    $patient_id = mysqli_real_escape_string($connection, $_GET['patient_id']);
    
    $query = "SELECT id, exam_type, test_type, radiographic_image 
              FROM tbl_radiology 
              WHERE patient_id = '$patient_id' 
              AND radiographic_image IS NOT NULL 
              AND radiographic_image != '' 
              AND deleted = 0";
    
    $result = mysqli_query($connection, $query);
    $images = array();
    
    while ($row = mysqli_fetch_assoc($result)) {
        $images[] = array(
            'id' => $row['id'],
            'exam_type' => htmlspecialchars($row['exam_type']),
            'test_type' => htmlspecialchars($row['test_type'])
        );
    }
    
    echo json_encode(array('images' => $images));
}

mysqli_close($connection);
?>