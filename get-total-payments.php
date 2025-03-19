<?php
include('includes/connection.php');

if (isset($_GET['patient_name'])) {
    $patient_name = $_GET['patient_name'];

    // Get total amount paid for this patient for Inpatient, Hemodialysis, and Newborn
    $query = "SELECT 
                COALESCE(SUM(CASE WHEN patient_type = 'Inpatient' THEN amount_paid ELSE 0 END), 0) AS inpatient_total_paid,
                COALESCE(SUM(CASE WHEN patient_type = 'Hemodialysis' THEN amount_paid ELSE 0 END), 0) AS hemodialysis_total_paid,
                COALESCE(SUM(CASE WHEN patient_type = 'Newborn' THEN amount_paid ELSE 0 END), 0) AS newborn_total_paid
              FROM tbl_payment 
              WHERE patient_name = ?";
              
    $stmt = $connection->prepare($query);
    $stmt->bind_param("s", $patient_name);
    $stmt->execute();
    
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    
    header('Content-Type: application/json');
    echo json_encode($row);
    
    $stmt->close();
} else {
    echo json_encode([
        'inpatient_total_paid' => 0,
        'hemodialysis_total_paid' => 0,
        'newborn_total_paid' => 0
    ]);
}

$connection->close();
?>
