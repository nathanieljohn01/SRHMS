<?php
include('includes/connection.php');

if (isset($_GET['search'])) {
    $search = $_GET['search'];
    
    $query = "SELECT DISTINCT b.patient_name, b.patient_id 
              FROM tbl_billing_inpatient b 
              WHERE b.patient_name LIKE ? 
              AND b.deleted = 0 
              ORDER BY b.patient_name 
              LIMIT 10";
              
    $stmt = $connection->prepare($query);
    $searchTerm = "%$search%";
    $stmt->bind_param("s", $searchTerm);
    $stmt->execute();
    
    $result = $stmt->get_result();
    $patients = array();
    
    while ($row = $result->fetch_assoc()) {
        $patients[] = array(
            'patient_id' => $row['patient_id'],
            'patient_name' => $row['patient_name']
        );
    }
    
    header('Content-Type: application/json');
    echo json_encode($patients);
}
?>
