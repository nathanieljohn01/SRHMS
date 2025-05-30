<?php
include('includes/connection.php');

if(isset($_POST['patientId'])) {
    $patientId = $_POST['patientId'];
    
    // Fetch radiology results
    $query = "SELECT * FROM tbl_radiology WHERE patient_id = ? ORDER BY update_date DESC";
    $stmt = $connection->prepare($query);
    $stmt->bind_param("s", $patientId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if($result && $result->num_rows > 0) {
        echo '<div class="row">';
        
        while($row = $result->fetch_assoc()) {
            // Convert BLOB to base64 image
            $imageData = base64_encode($row['radiographic_image']);
            $src = 'data:image/jpeg;base64,'.$imageData;
            
            echo '<div class="col-md-6 mb-3">
                    <div class="card">
                        <img src="'.$src.'" class="card-img-top" alt="Radiology Image" style="max-height: 300px; object-fit: contain;">
                        <div class="card-body">
                            <h5 class="card-title">'.$row['exam_type'].'</h5>
                            <p class="card-text">'.$row['test_type'].'</p>
                            <p class="card-text"><small class="text-muted">'.date('F d, Y g:i A', strtotime($row['update_date'])).'</small></p>
                        </div>
                    </div>
                </div>';
        }
        
        echo '</div>';
    } else {
        echo '<div class="alert alert-info text-center p-4 my-3" style="border-left: 5px solid #12369e; background-color: #f8f9fa; box-shadow: 0 2px 10px rgba(0,0,0,0.1); border-radius: 8px;">
        <i class="fa fa-info-circle mr-2" style="color: #12369e; font-size: 24px;"></i>
        <div style="color: #12369e; font-weight: bold; font-size: 18px;">No Radiology Results</div>
        <div class="mt-2">No radiology results found for this patient.</div>
        </div>';

    }
    
    $stmt->close();
}
?>