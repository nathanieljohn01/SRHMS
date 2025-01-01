<?php
include('includes/connection.php');

if (isset($_POST['outpatient_id']) && isset($_POST['doctor_incharge'])) {
  $outpatientId = sanitize($connection, $_POST['outpatient_id']);
  $doctorName = sanitize($connection, $_POST['doctor_incharge']);

  $update_query = mysqli_query($connection, "
    UPDATE tbl_outpatient 
    SET doctor_incharge='$doctorName' 
    WHERE outpatient_id='$outpatientId'");

  if ($update_query) {
    echo 'success';
  } else {
    echo 'error'; // Include more specific error details if possible
    // Log the error for troubleshooting (optional)
    error_log('Failed to update doctor for outpatient ID ' . $outpatientId . ': ' . mysqli_error($connection));
  }
} else {
  echo 'error';  // Handle missing data in the request
}