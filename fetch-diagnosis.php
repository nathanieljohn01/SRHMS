<?php
include('includes/connection.php');

if(isset($_POST['patientId'])) {
    $patientId = $_POST['patientId'];
    
    // Fetch diagnosis from both outpatient and inpatient records
    $query = "SELECT 'Outpatient' as type, diagnosis, date_time 
             FROM tbl_outpatient 
             WHERE patient_id = ? AND diagnosis IS NOT NULL
             UNION ALL
             SELECT 'Inpatient' as type, diagnosis, admission_date
             FROM tbl_inpatient_record 
             WHERE patient_id = ? AND diagnosis IS NOT NULL";
             
    $stmt = $connection->prepare($query);
    $stmt->bind_param("ss", $patientId, $patientId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if($result->num_rows > 0) {
        echo '<table class="table table-bordered">
                <thead>
                    <tr>
                        <th>Type</th>
                        <th>Diagnosis</th>
                        <th>Date</th>
                    </tr>
                </thead>
                <tbody>';
        
        while($row = $result->fetch_assoc()) {
            echo '<tr>
                    <td>'.$row['type'].'</td>
                    <td>'.$row['diagnosis'].'</td>
                    <td>'.date('F d, Y g:i A', strtotime($row['date_time'])).'</td>
                </tr>';
        }
        
        echo '</tbody></table>';
    } else {
        echo '<div class="alert alert-info text-center p-4 my-3" style="border-left: 5px solid #12369e; background-color: #f8f9fa; box-shadow: 0 2px 10px rgba(0,0,0,0.1); border-radius: 8px;">
        <i class="fa fa-info-circle mr-2" style="color: #12369e; font-size: 24px;"></i>
        <div style="color: #12369e; font-weight: bold; font-size: 18px;">No Diagnosis Records</div>
        <div class="mt-2">No diagnosis records found for this patient.</div>
        </div>';

    }
}
?>