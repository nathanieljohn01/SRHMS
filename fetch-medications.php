<?php
include('includes/connection.php');

if(isset($_POST['patientId'])) {
    $patientId = $_POST['patientId'];
    
    // Fetch medications
    $query = "SELECT * FROM tbl_treatment WHERE patient_id = ?";
    $stmt = $connection->prepare($query);
    $stmt->bind_param("s", $patientId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if($result->num_rows > 0) {
        echo '<table class="table table-bordered">
                <thead>
                    <tr>
                        <th>Medicine Name</th>
                        <th>Brand</th>
                        <th>Quantity</th>
                        <th>Date Prescribed</th>
                    </tr>
                </thead>
                <tbody>';
        
        while($row = $result->fetch_assoc()) {
            echo '<tr>
                    <td>'.$row['medicine_name'].'</td>
                    <td>'.$row['medicine_brand'].'</td>
                    <td>'.$row['total_quantity'].'</td>
                    <td>'.date('F d, Y g:i A', strtotime($row['treatment_date'])).'</td>
                </tr>';
        }
        
        echo '</tbody></table>';
    } else {
        echo '<div class="alert alert-info text-center p-4 my-3" style="border-left: 5px solid #12369e; background-color: #f8f9fa; box-shadow: 0 2px 10px rgba(0,0,0,0.1); border-radius: 8px;">
        <i class="fa fa-info-circle mr-2" style="color: #12369e; font-size: 24px;"></i>
        <div style="color: #12369e; font-weight: bold; font-size: 18px;">No Medication Records</div>
        <div class="mt-2">No medication records found for this patient.</div>
        </div>';

    }
}
?>