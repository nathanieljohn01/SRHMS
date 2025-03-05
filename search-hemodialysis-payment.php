<?php
include('includes/connection.php');

if (isset($_GET['search'])) {
    $search = trim($_GET['search']);
    
    // Get hemodialysis patients with unpaizd bills
    $query = "
        SELECT DISTINCT 
            p.patient_id,
            CONCAT(p.first_name, ' ', p.last_name) as patient_name
        FROM tbl_patient p
        INNER JOIN tbl_billing_hemodialysis b ON CONCAT(p.first_name, ' ', p.last_name) = b.patient_name
        WHERE CONCAT(p.first_name, ' ', p.last_name) LIKE ?
            AND b.deleted = 0
            AND b.remaining_balance > 0
            AND b.status != 'Paid'
        GROUP BY p.patient_id, p.first_name, p.last_name
        ORDER BY p.first_name, p.last_name
        LIMIT 10";
              
    if ($stmt = $connection->prepare($query)) {
        $search_term = "%{$search}%";
        $stmt->bind_param("s", $search_term);
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
        
        $stmt->close();
    }
}
?>
