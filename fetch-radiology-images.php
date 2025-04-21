<?php
session_start();
include('includes/connection.php');

if(isset($_GET['patient_id'])) {
    $patient_id = $_GET['patient_id'];
    $query = "SELECT * FROM tbl_radiology WHERE patient_id = ? AND radiographic_image IS NOT NULL AND radiographic_image != '' AND deleted = 0";
    $stmt = $connection->prepare($query);
    $stmt->bind_param("s", $patient_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $images = array();
    while($row = $result->fetch_assoc()) {
        $images[] = array(
            'id' => $row['id'],
            'exam_type' => $row['exam_type'],
            'test_type' => $row['test_type']
        );
    }
    
    header('Content-Type: application/json');
    echo json_encode(array('images' => $images));
    exit;
}
?>