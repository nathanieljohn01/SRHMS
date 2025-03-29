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

// Get PBS ID for fetching existing data
if (isset($_GET['id'])) {
    $pbs_id = sanitize($connection, $_GET['id']);

    // Fetch existing PBS data using prepared statement
    $fetch_query = mysqli_prepare($connection, "SELECT * FROM tbl_pbs WHERE pbs_id = ?");
    mysqli_stmt_bind_param($fetch_query, "s", $pbs_id);
    mysqli_stmt_execute($fetch_query);
    $result = mysqli_stmt_get_result($fetch_query);
    $pbs_data = mysqli_fetch_array($result);
    mysqli_stmt_close($fetch_query);

    if (!$pbs_data) {
        echo "PBS data not found.";
        exit;
    }
}

// Handle form submission for editing PBS data
if (isset($_POST['edit-pbs'])) {
    // Sanitize inputs
    $pbs_id = sanitize($connection, $_POST['pbs_id']);
    $patient_name = sanitize($connection, $_POST['patient_name']);
    $date_time = sanitize($connection, $_POST['date_time']);
    $rbc_morphology = sanitize($connection, $_POST['rbc_morphology'] ?? NULL);
    $platelet_count = sanitize($connection, $_POST['platelet_count'] ?? NULL);
    $toxic_granules = sanitize($connection, $_POST['toxic_granules'] ?? NULL);
    $abnormal_cells = sanitize($connection, $_POST['abnormal_cells'] ?? NULL);
    $segments = sanitize($connection, $_POST['segments'] ?? NULL);
    $lymphocytes = sanitize($connection, $_POST['lymphocytes'] ?? NULL);
    $monocytes = sanitize($connection, $_POST['monocytes'] ?? NULL);
    $eosinophils = sanitize($connection, $_POST['eosinophils'] ?? NULL);
    $bands = sanitize($connection, $_POST['bands'] ?? NULL);
    $reticulocyte_count = sanitize($connection, $_POST['reticulocyte_count'] ?? NULL);
    $remarks = sanitize($connection, $_POST['remarks'] ?? NULL);

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

    // Update PBS data using prepared statement
    $update_query = mysqli_prepare($connection, "UPDATE tbl_pbs SET 
        patient_name = ?, 
        dob = ?, 
        gender = ?, 
        date_time = ?, 
        rbc_morphology = ?, 
        platelet_count = ?, 
        toxic_granules = ?, 
        abnormal_cells = ?,
        segments = ?, 
        lymphocytes = ?, 
        monocytes = ?, 
        eosinophils = ?, 
        bands = ?, 
        reticulocyte_count = ?, 
        remarks = ?
        WHERE pbs_id = ?");

    mysqli_stmt_bind_param($update_query, "ssssssssssssssss", 
        $patient_name, 
        $dob, 
        $gender, 
        $date_time,
        $rbc_morphology,
        $platelet_count,
        $toxic_granules,
        $abnormal_cells,
        $segments,
        $lymphocytes,
        $monocytes,
        $eosinophils,
        $bands,
        $reticulocyte_count,
        $remarks,
        $pbs_id);

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
                    text: 'PBS result updated successfully!',
                    confirmButtonColor: '#12369e'
                }).then(() => {
                    window.location.href = 'pbs.php';
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
                    text: 'Error updating the PBS result!',
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
                <h4 class="page-title">Edit PBS Result</h4>
            </div>
            <div class="col-sm-8 text-right mb-3">
                <a href="pbs.php" class="btn btn-primary"><i class="fa fa-arrow-left"></i> Back</a>
            </div>
        </div>
        <div class="row">
            <div class="col-lg-10 offset-lg-1">
                <form method="post">
                    <div class="form-group row">
                        <div class="col-sm-6">
                            <label for="pbs_id">PBS ID</label>
                            <input class="form-control" type="text" name="pbs_id" value="<?php echo htmlspecialchars($pbs_data['pbs_id']); ?>" readonly>
                        </div>
                        <div class="col-sm-6">
                            <label for="patient_name">Patient Name</label>
                            <input class="form-control" type="text" name="patient_name" value="<?php echo htmlspecialchars($pbs_data['patient_name']); ?>" readonly>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="date_time">Date and Time</label>
                        <input type="datetime-local" class="form-control" name="date_time" value="<?php echo date('Y-m-d\TH:i', strtotime($pbs_data['date_time'])); ?>">
                    </div>
                    <div class="form-group">
                        <label for="rbc_morphology">RBC Morphology</label>
                        <input class="form-control" type="text" name="rbc_morphology" value="<?php echo htmlspecialchars($pbs_data['rbc_morphology']); ?>">
                    </div>
                    <div class="form-group">
                        <label for="platelet_count">Platelet Count</label>
                        <input class="form-control" type="text" name="platelet_count" value="<?php echo htmlspecialchars($pbs_data['platelet_count']); ?>">
                    </div>
                    <div class="form-group">
                        <label for="toxic_granules">Toxic Granules</label>
                        <select class="form-control" name="toxic_granules">
                            <option value="Select" <?php echo ($pbs_data['toxic_granules'] == 'Select') ? 'selected' : ''; ?>>Select</option>
                            <option value="None" <?php echo ($pbs_data['toxic_granules'] == 'None') ? 'selected' : ''; ?>>None</option>
                            <option value="Present" <?php echo ($pbs_data['toxic_granules'] == 'Present') ? 'selected' : ''; ?>>Present</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="abnormal_cells">Abnormal Cells</label>
                        <input class="form-control" type="text" name="abnormal_cells" value="<?php echo htmlspecialchars($pbs_data['abnormal_cells']); ?>">
                    </div>
                    <div class="form-group">
                        <label for="segments">Segments (%)</label>
                        <input class="form-control" type="number" name="segments" min="0" max="100" value="<?php echo htmlspecialchars($pbs_data['segments']); ?>">
                    </div>
                    <div class="form-group">
                        <label for="lymphocytes">Lymphocytes (%)</label>
                        <input class="form-control" type="number" name="lymphocytes" min="0" max="100" value="<?php echo htmlspecialchars($pbs_data['lymphocytes']); ?>">
                    </div>
                    <div class="form-group">
                        <label for="monocytes">Monocytes (%)</label>
                        <input class="form-control" type="number" name="monocytes" min="0" max="100" value="<?php echo htmlspecialchars($pbs_data['monocytes']); ?>">
                    </div>
                    <div class="form-group">
                        <label for="eosinophils">Eosinophils (%)</label>
                        <input class="form-control" type="number" name="eosinophils" min="0" max="100" value="<?php echo htmlspecialchars($pbs_data['eosinophils']); ?>">
                    </div>
                    <div class="form-group">
                        <label for="bands">Bands (%)</label>
                        <input class="form-control" type="number" name="bands" min="0" max="100" value="<?php echo htmlspecialchars($pbs_data['bands']); ?>">
                    </div>
                    <div class="form-group">
                        <label for="reticulocyte_count">Reticulocyte Count (%)</label>
                        <input class="form-control" type="number" name="reticulocyte_count" min="0" max="100" step="0.1" value="<?php echo htmlspecialchars($pbs_data['reticulocyte_count']); ?>">
                    </div>
                    <div class="form-group">
                        <label for="remarks">Remarks</label>
                        <textarea class="form-control" name="remarks"><?php echo htmlspecialchars($pbs_data['remarks']); ?></textarea>
                    </div>
                    <div class="text-center mt-4">
                        <button class="btn btn-primary submit-btn" name="edit-pbs"><i class="fas fa-save mr-2"></i>Update Result</button>
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