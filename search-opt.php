<?php
include('includes/connection.php');

// Get the search term from the URL
if (isset($_GET['search'])) {
    $search = trim($_GET['search']);
    
    // Refined query to get outpatients with unbilled lab or rad orders
    $sql = "
        SELECT DISTINCT 
            p.patient_id,
            CONCAT(p.first_name, ' ', p.last_name) as patient_name
        FROM tbl_patient p
        LEFT JOIN tbl_laborder l ON p.patient_id = l.patient_id AND l.is_billed = 0 AND l.deleted = 0
        LEFT JOIN tbl_radiology r ON p.patient_id = r.patient_id AND r.is_billed = 0 AND r.deleted = 0
        WHERE 
            (CONCAT(p.first_name, ' ', p.last_name) LIKE ?)
            AND (l.patient_id IS NOT NULL OR r.patient_id IS NOT NULL)
            AND (l.patient_type = 'Outpatient' OR r.patient_type = 'Outpatient')
        GROUP BY p.patient_id
        HAVING COUNT(l.id) > 0 OR COUNT(r.id) > 0
    ";

    if ($stmt = $connection->prepare($sql)) {
        $search_term = "%{$search}%";
        $stmt->bind_param("s", $search_term);
        $stmt->execute();
        $result = $stmt->get_result();
    
        $patients = [];
        while ($row = $result->fetch_assoc()) {
            $patients[] = [
                'patient_id' => $row['patient_id'],
                'patient_name' => $row['patient_name']
            ];
        }
        
        // Return results as JSON
        header('Content-Type: application/json');
        echo json_encode($patients);
        
        // Close the statement
        $stmt->close();
    }
}
?>