<?php
include('includes/connection.php');

if (isset($_POST['patient_id'])) {
    $patient_id = $_POST['patient_id'];
    
    $query = "SELECT 
                payment_id,
                total_due,
                amount_to_pay,
                amount_paid,
                remaining_balance,
                DATE_FORMAT(payment_datetime, '%M %d, %Y %h:%i %p') as payment_datetime
            FROM tbl_payment 
            WHERE patient_id = ?
            ORDER BY payment_datetime DESC";
            
    $stmt = $connection->prepare($query);
    $stmt->bind_param("s", $patient_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $payments = array();
    while ($row = $result->fetch_assoc()) {
        $payments[] = $row;
    }
    
    echo json_encode($payments);
} else {
    echo json_encode(array('error' => 'Patient ID not provided'));
}
?>
