<?php
include('includes/connection.php');

// Get the search term from the URL
if (isset($_GET['search'])) {
    $search = trim($_GET['search']);
    
    // Refined query
    $sql = "
        (SELECT DISTINCT 
            l.patient_id, 
            l.patient_name, 
            l.patient_type
        FROM 
            tbl_laborder l
        WHERE 
            l.patient_name LIKE ?
            AND l.deleted = 0
            AND l.patient_type = 'Outpatient')
        
        UNION
        
        (SELECT DISTINCT 
            r.patient_id, 
            r.patient_name, 
            r.patient_type
        FROM 
            tbl_radiology r
        WHERE 
            r.patient_name LIKE ?
            AND r.deleted = 0
            AND r.patient_type = 'Outpatient')
    ";


    if ($stmt = mysqli_prepare($connection, $sql)) {
        $search_term = "%{$search}%";  // The search term with wildcards
        mysqli_stmt_bind_param($stmt, 'ss', $search_term, $search_term); // Two 's' because you have two placeholders
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
    
        $patients = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $patients[] = [
                'patient_id' => $row['patient_id'],
                'patient_name' => $row['patient_name']
            ];
        }
        
        // Return results as JSON
        echo json_encode($patients);
        
        // Close the statement
        mysqli_stmt_close($stmt);
    }

}
?>
    