<?php
include('includes/connection.php');

if (isset($_GET['patient_name'])) {
    $patient_name = $_GET['patient_name'];
    
    // Get total amount paid for this patient
    $query = "SELECT COALESCE(SUM(amount_paid), 0) as total_paid
              FROM tbl_payment 
              WHERE patient_name = ? 
              AND patient_type = 'Inpatient'";
              
    $stmt = $connection->prepare($query);
    $stmt->bind_param("s", $patient_name);
    $stmt->execute();
    
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    
    header('Content-Type: application/json');
    echo json_encode($row);
    
    $stmt->close();
} else {
    echo json_encode(['total_paid' => 0]);
}

$connection->close();
?>
