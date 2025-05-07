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
        echo '<div class="alert alert-info">No medications found.</div>';
    }
}
?>