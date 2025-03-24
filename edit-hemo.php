<?php
session_start();
if (empty($_SESSION['name'])) {
    header('location:index.php');
    exit();
}

include('header.php');
include('includes/connection.php');

// Function to sanitize user inputs
function sanitize($connection, $input) {
    return mysqli_real_escape_string($connection, htmlspecialchars(strip_tags(trim($input)), ENT_QUOTES, 'UTF-8'));
}

$id = sanitize($connection, $_GET['id']); // Sanitize the incoming GET parameter

// Fetch the record based on ID
$fetch_query = $connection->prepare("SELECT * FROM tbl_hemodialysis WHERE id = ?");
if (!$fetch_query) {
    die("Error in prepared statement: " . $connection->error);
}
$fetch_query->bind_param("i", $id); // Bind as an integer
$fetch_query->execute();
$result = $fetch_query->get_result();
$row = $result->fetch_assoc();

// Check if the form is submitted
if (isset($_POST['save-hemopatient'])) {
    // Sanitize form inputs
    $date_time = sanitize($connection, $_POST['date_time']);
    $dialysis_report = sanitize($connection, $_POST['dialysis_report']);
    $follow_up_date = sanitize($connection, $_POST['follow_up_date']); // Follow-up Date

    // Prepare the UPDATE query using a prepared statement
    $update_query = $connection->prepare("UPDATE tbl_hemodialysis SET date_time = ?, dialysis_report = ?, follow_up_date = ? WHERE id = ?");
    if (!$update_query) {
        die("Error in prepared statement: " . $connection->error);
    }
    $update_query->bind_param("sssi", $date_time, $dialysis_report, $follow_up_date, $id); // Bind the parameters

    // Execute the statement
    if ($update_query->execute()) {
        $msg = "Hemo-patient updated successfully";
    
        // SweetAlert success message
        echo "
        <script src='https://cdn.jsdelivr.net/npm/sweetalert2@10'></script>
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                Swal.fire({
                    icon: 'success',
                    title: 'Success',
                    text: 'Hemo-patient updated successfully',
                    confirmButtonColor: '#12369e'
                }).then(() => {
                    // Optional: Redirect after success, adjust the URL as necessary
                    window.location.href = 'hemodialysis.php'; // Change to your relevant page
                });
            });
        </script>";
    
        // Fetch the updated record
        $fetch_query = $connection->prepare("SELECT * FROM tbl_hemodialysis WHERE id = ?");
        if (!$fetch_query) {
            die("Error in prepared statement: " . $connection->error);
        }
        $fetch_query->bind_param("i", $id);
        $fetch_query->execute();
        $result = $fetch_query->get_result();
        $row = $result->fetch_assoc();
    } else {
        $msg = "Error: " . $connection->error;
    
        // SweetAlert error message
        echo "
        <script src='https://cdn.jsdelivr.net/npm/sweetalert2@10'></script>
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'Error updating hemo-patient: " . $connection->error . "',
                });
            });
        </script>";
    }
    
    // Close the update query
    $update_query->close();
    
}
?>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/datepicker/1.0.10/datepicker.min.css">
<link rel="stylesheet" type="text/css" href="assets/css/bootstrap-datetimepicker.css">
<link rel="stylesheet" type="text/css" href="assets/css/bootstrap-datetimepicker.min.css">
<div class="page-wrapper">
    <div class="content">
        <div class="row">
            <div class="col-sm-4">
                <h4 class="page-title">Edit Patient</h4>
            </div>
            <div class="col-sm-8 text-right m-b-20">
                <a href="hemodialysis.php" class="btn btn-primary float-right"><i class="fa fa-arrow-left"></i> Back</a>
            </div>
        </div>
        <div class="row">
            <div class="col-lg-8 offset-lg-2">
                <form method="post">
                    <div class="form-group">
                        <label>Hemo-patient ID <span class="text-danger">*</span></label>
                        <input class="form-control" type="text" name="inpatient_id" value="<?php echo $row['hemopatient_id']; ?>" disabled>
                    </div>
                    <div class="form-group">
                        <label>Patient Name <span class="text-danger">*</span></label>
                        <input class="form-control" type="text" name="patient_name" value="<?php echo $row['patient_name']; ?>" disabled>
                    </div>
                    <div class="form-group">
                        <label for="date_time">Date and Time <span class="text-danger">*</span></label>
                        <input class="form-control" type="datetime-local" name="date_time" value="<?php echo isset($row['date_time']) ? date('Y-m-d\TH:i', strtotime($row['date_time'])) : ''; ?>" required>
                    </div>   
                    <div class="form-group">
                        <label for="dialysis_report">Dialysis Report <span class="text-danger">*</span></label>
                        <textarea class="form-control" name="dialysis_report" cols="30" rows="4" required><?php echo htmlspecialchars($row['dialysis_report']); ?></textarea>
                    </div>
                    <div class="form-group">
                        <label for="follow_up_date">Follow-up Date</label>
                        <div class="cal-icon">
                            <input class="form-control datetimepicker" type="text" name="follow_up_date" value="<?php echo isset($row['follow_up_date']) ? htmlspecialchars($row['follow_up_date']) : ''; ?>" required>
                        </div>
                    </div>
                    <div class="col-md-12 text-center">
                        <button name="save-hemopatient" class="btn btn-primary submit-btn"><i class="fas fa-save mr-2"></i>Save</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php
include('footer.php');
?>

<script src="assets/js/moment.min.js"></script>
<script src="assets/js/bootstrap-datetimepicker.js"></script>
<script src="assets/js/bootstrap-datetimepicker.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@10"></script>
<style>
    .btn-primary.submit-btn {
        border-radius: 4px; 
        padding: 10px 20px;
        font-size: 16px;
    }
    .btn-primary {
        background: #12369e;
        border: none;
    }
    .btn-primary:hover {
        background: #05007E;
    }
    .form-control {
        border-radius: .375rem; /* Rounded corners */
        border-color: #ced4da; /* Border color */
        background-color: #f8f9fa; /* Background color */
    }
</style>
