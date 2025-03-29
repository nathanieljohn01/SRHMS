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
    return mysqli_real_escape_string($connection, htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8'));
}

// Get PT/PTT ID for fetching existing data
if (isset($_GET['id'])) {
    $ptptt_id = sanitize($connection, $_GET['id']);

    // Fetch existing PT/PTT data using prepared statement
    $fetch_query = mysqli_prepare($connection, "SELECT * FROM tbl_ptptt WHERE ptptt_id = ?");
    mysqli_stmt_bind_param($fetch_query, "s", $ptptt_id);
    mysqli_stmt_execute($fetch_query);
    $result = mysqli_stmt_get_result($fetch_query);
    $ptptt_data = mysqli_fetch_array($result);
    mysqli_stmt_close($fetch_query);

    if (!$ptptt_data) {
        echo "PT/PTT data not found.";
        exit;
    }
}

// Handle form submission for editing PT/PTT data
if (isset($_POST['edit-ptptt'])) {
    // Sanitize inputs
    $ptptt_id = sanitize($connection, $_POST['ptptt_id']);
    $patient_name = sanitize($connection, $_POST['patient_name']);
    $date_time = sanitize($connection, $_POST['date_time']);
    $pt_control = sanitize($connection, $_POST['pt_control'] ?? NULL);
    $pt_test = sanitize($connection, $_POST['pt_test'] ?? NULL);
    $pt_inr = sanitize($connection, $_POST['pt_inr'] ?? NULL);
    $pt_activity = sanitize($connection, $_POST['pt_activity'] ?? NULL);
    $ptt_control = sanitize($connection, $_POST['ptt_control'] ?? NULL);
    $ptt_patient_result = sanitize($connection, $_POST['ptt_patient_result'] ?? NULL);
    $ptt_remarks = sanitize($connection, $_POST['ptt_remarks'] ?? NULL);

    // Fetch patient information using prepared statement
    $patient_query = mysqli_prepare($connection, "SELECT patient_id, gender, dob FROM tbl_patient WHERE CONCAT(first_name, ' ', last_name) = ?");
    mysqli_stmt_bind_param($patient_query, "s", $patient_name);
    mysqli_stmt_execute($patient_query);
    $patient_result = mysqli_stmt_get_result($patient_query);
    $row = mysqli_fetch_array($patient_result);
    mysqli_stmt_close($patient_query);

    $patient_id = $row['patient_id'];
    $gender = $row['gender'];
    $dob = $row['dob'];

    // Update PT/PTT data using prepared statement
    $update_query = mysqli_prepare($connection, "UPDATE tbl_ptptt SET 
        patient_name = ?, 
        dob = ?, 
        gender = ?, 
        date_time = ?,
        pt_control = ?,
        pt_test = ?,
        pt_inr = ?,
        pt_activity = ?,
        ptt_control = ?,
        ptt_patient_result = ?,
        ptt_remarks = ?
        WHERE ptptt_id = ?");

    mysqli_stmt_bind_param($update_query, "ssssssssssss", 
        $patient_name, 
        $dob, 
        $gender, 
        $date_time,
        $pt_control,
        $pt_test,
        $pt_inr,
        $pt_activity,
        $ptt_control,
        $ptt_patient_result,
        $ptt_remarks,
        $ptptt_id);

    // Execute the update query
    if (mysqli_stmt_execute($update_query)) {
        // SweetAlert success message
        echo "
        <script src='https://cdn.jsdelivr.net/npm/sweetalert2@10'></script>
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                Swal.fire({
                    icon: 'success',
                    title: 'Success',
                    text: 'PT/PTT result updated successfully!',
                    confirmButtonColor: '#12369e'
                }).then(() => {
                    window.location.href = 'ptptt.php';
                });
            });
        </script>";
    } else {
        // SweetAlert error message
        echo "
        <script src='https://cdn.jsdelivr.net/npm/sweetalert2@10'></script>
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'Error updating the PT/PTT result!',
                    confirmButtonColor: '#12369e'
                });
            });
        </script>";
    }

    // Close the prepared statement
    mysqli_stmt_close($update_query);
}
?>

<div class="page-wrapper">
    <div class="content">
        <div class="row">
            <div class="col-sm-4">
                <h4 class="page-title">Edit PT/PTT Result</h4>
            </div>
            <div class="col-sm-8 text-right mb-3">
                <a href="ptptt.php" class="btn btn-primary"><i class="fa fa-arrow-left"></i> Back</a>
            </div>
        </div>
        <div class="row">
            <div class="col-lg-10 offset-lg-1">
                <form method="post">
                    <div class="form-group row">
                        <div class="col-sm-6">
                            <label for="ptptt_id">PT/PTT ID</label>
                            <input class="form-control" type="text" name="ptptt_id" value="<?php echo htmlspecialchars($ptptt_data['ptptt_id']); ?>" readonly>
                        </div>
                        <div class="col-sm-6">
                            <label for="patient_name">Patient Name</label>
                            <input class="form-control" type="text" name="patient_name" value="<?php echo htmlspecialchars($ptptt_data['patient_name']); ?>" readonly>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="date_time">Date and Time</label>
                        <input type="datetime-local" class="form-control" name="date_time" value="<?php echo date('Y-m-d\TH:i', strtotime($ptptt_data['date_time'])); ?>">
                    </div>
                    
                    <h5 class="mb-3">Prothrombin Time (PT)</h5>
                    <div class="form-group row">
                        <div class="col-sm-3">
                            <label for="pt_control">Control (sec)</label>
                            <input class="form-control" type="number" name="pt_control" min="0" step="0.1" value="<?php echo htmlspecialchars($ptptt_data['pt_control']); ?>">
                        </div>
                        <div class="col-sm-3">
                            <label for="pt_test">Test (sec)</label>
                            <input class="form-control" type="number" name="pt_test" min="0" step="0.1" value="<?php echo htmlspecialchars($ptptt_data['pt_test']); ?>">
                        </div>
                        <div class="col-sm-3">
                            <label for="pt_inr">INR</label>
                            <input class="form-control" type="number" name="pt_inr" min="0" step="0.01" value="<?php echo htmlspecialchars($ptptt_data['pt_inr']); ?>">
                        </div>
                        <div class="col-sm-3">
                            <label for="pt_activity">Activity (%)</label>
                            <input class="form-control" type="number" name="pt_activity" min="0" max="100" step="0.1" value="<?php echo htmlspecialchars($ptptt_data['pt_activity']); ?>">
                        </div>
                    </div>
                    
                    <h5 class="mb-3">Partial Thromboplastin Time (PTT)</h5>
                    <div class="form-group row">
                        <div class="col-sm-6">
                            <label for="ptt_control">Control (sec)</label>
                            <input class="form-control" type="number" name="ptt_control" min="0" step="0.1" value="<?php echo htmlspecialchars($ptptt_data['ptt_control']); ?>">
                        </div>
                        <div class="col-sm-6">
                            <label for="ptt_patient_result">Patient Result (sec)</label>
                            <input class="form-control" type="number" name="ptt_patient_result" min="0" step="0.1" value="<?php echo htmlspecialchars($ptptt_data['ptt_patient_result']); ?>">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="ptt_remarks">Remarks</label>
                        <textarea class="form-control" name="ptt_remarks" rows="3"><?php echo htmlspecialchars($ptptt_data['ptt_remarks']); ?></textarea>
                    </div>
                    
                    <div class="text-center mt-4">
                        <button class="btn btn-primary submit-btn" name="edit-ptptt"><i class="fas fa-save mr-2"></i>Update Result</button>
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

<script type="text/javascript">
    <?php
    if (isset($msg)) {
        echo 'swal("' . $msg . '");';
    }
    ?>
</script>
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
    border-radius: .375rem;
    border-color: #ced4da;
    background-color: #f8f9fa;
}
h5 {
    color: #12369e;
    margin-top: 20px;
    padding-bottom: 5px;
    border-bottom: 1px solid #eee;
}
select.form-control {
    border-radius: .375rem; /* Rounded corners */
    border: 1px solid; /* Border color */
    border-color: #ced4da; /* Border color */
    background-color: #f8f9fa; /* Background color */
    padding: .375rem 2.5rem .375rem .75rem; /* Adjust padding to make space for the larger arrow */
    font-size: 1rem; /* Font size */
    line-height: 1.5; /* Line height */
    height: calc(2.25rem + 2px); /* Adjust height */
    -webkit-appearance: none; /* Remove default styling on WebKit browsers */
    -moz-appearance: none; /* Remove default styling on Mozilla browsers */
    appearance: none; /* Remove default styling on other browsers */
    background: url('data:image/svg+xml;charset=UTF-8,%3Csvg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="20" height="20"%3E%3Cpath d="M7 10l5 5 5-5z" fill="%23aaa"/%3E%3C/svg%3E') no-repeat right 0.75rem center;
    background-size: 20px; /* Size of the custom arrow */
}

select.form-control:focus {
    border-color: #12369e; /* Border color on focus */
    box-shadow: 0 0 0 .2rem rgba(38, 143, 255, .25); /* Shadow on focus */
}
</style>