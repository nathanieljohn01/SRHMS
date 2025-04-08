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

// Get Chemistry ID for fetching existing data
if (isset($_GET['id'])) {
    $chem_id = sanitize($connection, $_GET['id']);

    // Fetch existing Chemistry data using prepared statement
    $fetch_query = mysqli_prepare($connection, "SELECT * FROM tbl_chemistry WHERE chem_id = ?");
    mysqli_stmt_bind_param($fetch_query, "s", $chem_id);
    mysqli_stmt_execute($fetch_query);
    $result = mysqli_stmt_get_result($fetch_query);
    $chem_data = mysqli_fetch_array($result);
    mysqli_stmt_close($fetch_query);

    if (!$chem_data) {
        echo "Chemistry data not found.";
        exit;
    }
}

// Handle form submission for editing Chemistry data
if (isset($_POST['edit-chemistry'])) {
    // Sanitize inputs
    $chem_id = sanitize($connection, $_POST['chem_id']);
    $patient_name = sanitize($connection, $_POST['patient_name']);
    $date_time = sanitize($connection, $_POST['date_time']);
    $fbs = sanitize($connection, $_POST['fbs'] ?? NULL);
    $ppbs = sanitize($connection, $_POST['ppbs'] ?? NULL);
    $bun = sanitize($connection, $_POST['bun'] ?? NULL);
    $crea = sanitize($connection, $_POST['crea'] ?? NULL);
    $bua = sanitize($connection, $_POST['bua'] ?? NULL);
    $tc = sanitize($connection, $_POST['tc'] ?? NULL);
    $tg = sanitize($connection, $_POST['tg'] ?? NULL);
    $hdl = sanitize($connection, $_POST['hdl'] ?? NULL);
    $ldl = sanitize($connection, $_POST['ldl'] ?? NULL);
    $vldl = sanitize($connection, $_POST['vldl'] ?? NULL);
    $ast = sanitize($connection, $_POST['ast'] ?? NULL);
    $alt = sanitize($connection, $_POST['alt'] ?? NULL);
    $alp = sanitize($connection, $_POST['alp'] ?? NULL);
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

    // Update Chemistry data using prepared statement
    $update_query = mysqli_prepare($connection, "UPDATE tbl_chemistry SET 
        patient_name = ?, 
        dob = ?, 
        gender = ?, 
        date_time = ?,
        fbs = ?,
        ppbs = ?,
        bun = ?,
        crea = ?,
        bua = ?,
        tc = ?,
        tg = ?,
        hdl = ?,
        ldl = ?,
        vldl = ?,
        ast = ?,
        alt = ?,
        alp = ?,
        remarks = ?
        WHERE chem_id = ?");

    mysqli_stmt_bind_param($update_query, "sssssssssssssssssss", 
        $patient_name, 
        $dob, 
        $gender, 
        $date_time,
        $fbs,
        $ppbs,
        $bun,
        $crea,
        $bua,
        $tc,
        $tg,
        $hdl,
        $ldl,
        $vldl,
        $ast,
        $alt,
        $alp,
        $remarks,
        $chem_id);

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
                    text: 'Chemistry Panel result updated successfully!',
                    confirmButtonColor: '#12369e'
                }).then(() => {
                    window.location.href = 'chemistry-panel.php';
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
                    text: 'Error updating the Chemistry Panel result!',
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
                <h4 class="page-title">Edit Chemistry Panel Result</h4>
            </div>
            <div class="col-sm-8 text-right mb-3">
                <a href="chemistry-panel.php" class="btn btn-primary"><i class="fa fa-arrow-left"></i> Back</a>
            </div>
        </div>
        <div class="row">
            <div class="col-lg-10 offset-lg-1">
                <form method="post">
                    <div class="form-group row">
                        <div class="col-sm-6">
                            <label for="chem_id">Chemistry ID</label>
                            <input class="form-control" type="text" name="chem_id" value="<?php echo htmlspecialchars($chem_data['chem_id']); ?>" readonly>
                        </div>
                        <div class="col-sm-6">
                            <label for="patient_name">Patient Name</label>
                            <input class="form-control" type="text" name="patient_name" value="<?php echo htmlspecialchars($chem_data['patient_name']); ?>" readonly>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="date_time">Date and Time</label>
                        <input type="datetime-local" class="form-control" name="date_time" value="<?php echo date('Y-m-d\TH:i', strtotime($chem_data['date_time'])); ?>">
                    </div>
                    
                    <h4 class="mb-3">Blood Sugar</h4>
                    <div class="form-group row">
                        <div class="col-sm-6">
                            <label for="fbs">Fasting Blood Sugar (mg/dL)</label>
                            <input class="form-control" type="number" name="fbs" min="0" step="0.1" value="<?php echo htmlspecialchars($chem_data['fbs']); ?>">
                        </div>
                        <div class="col-sm-6">
                            <label for="ppbs">Post-Prandial Blood Sugar (mg/dL)</label>
                            <input class="form-control" type="number" name="ppbs" min="0" step="0.1" value="<?php echo htmlspecialchars($chem_data['ppbs']); ?>">
                        </div>
                    </div>
                    
                    <h4 class="mb-3">Renal Function Tests</h4>
                    <div class="form-group row">
                        <div class="col-sm-4">
                            <label for="bun">Blood Urea Nitrogen (mg/dL)</label>
                            <input class="form-control" type="number" name="bun" min="0" step="0.1" value="<?php echo htmlspecialchars($chem_data['bun']); ?>">
                        </div>
                        <div class="col-sm-4">
                            <label for="crea">Creatinine (mg/dL)</label>
                            <input class="form-control" type="number" name="crea" min="0" step="0.01" value="<?php echo htmlspecialchars($chem_data['crea']); ?>">
                        </div>
                        <div class="col-sm-4">
                            <label for="bua">Blood Uric Acid (mg/dL)</label>
                            <input class="form-control" type="number" name="bua" min="0" step="0.1" value="<?php echo htmlspecialchars($chem_data['bua']); ?>">
                        </div>
                    </div>
                    
                    <h4 class="mb-3">Lipid Profile</h4>
                    <div class="form-group row">
                        <div class="col-sm-4">
                            <label for="tc">Total Cholesterol (mg/dL)</label>
                            <input class="form-control" type="number" name="tc" min="0" step="0.1" value="<?php echo htmlspecialchars($chem_data['tc']); ?>">
                        </div>
                        <div class="col-sm-4">
                            <label for="tg">Triglycerides (mg/dL)</label>
                            <input class="form-control" type="number" name="tg" min="0" step="0.1" value="<?php echo htmlspecialchars($chem_data['tg']); ?>">
                        </div>
                        <div class="col-sm-4">
                            <label for="hdl">HDL Cholesterol (mg/dL)</label>
                            <input class="form-control" type="number" name="hdl" min="0" step="0.1" value="<?php echo htmlspecialchars($chem_data['hdl']); ?>">
                        </div>
                    </div>
                    <div class="form-group row">
                        <div class="col-sm-6">
                            <label for="ldl">LDL Cholesterol (mg/dL)</label>
                            <input class="form-control" type="number" name="ldl" min="0" step="0.1" value="<?php echo htmlspecialchars($chem_data['ldl']); ?>">
                        </div>
                        <div class="col-sm-6">
                            <label for="vldl">VLDL Cholesterol (mg/dL)</label>
                            <input class="form-control" type="number" name="vldl" min="0" step="0.1" value="<?php echo htmlspecialchars($chem_data['vldl']); ?>">
                        </div>
                    </div>
                    
                    <h4 class="mb-3">Liver Function Tests</h4>
                    <div class="form-group row">
                        <div class="col-sm-4">
                            <label for="ast">AST/SGOT (U/L)</label>
                            <input class="form-control" type="number" name="ast" min="0" step="0.1" value="<?php echo htmlspecialchars($chem_data['ast']); ?>">
                        </div>
                        <div class="col-sm-4">
                            <label for="alt">ALT/SGPT (U/L)</label>
                            <input class="form-control" type="number" name="alt" min="0" step="0.1" value="<?php echo htmlspecialchars($chem_data['alt']); ?>">
                        </div>
                        <div class="col-sm-4">
                            <label for="alp">Alkaline Phosphatase (U/L)</label>
                            <input class="form-control" type="number" name="alp" min="0" step="0.1" value="<?php echo htmlspecialchars($chem_data['alp']); ?>">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="remarks">Remarks</label>
                        <textarea class="form-control" name="remarks" rows="3"><?php echo htmlspecialchars($chem_data['remarks']); ?></textarea>
                    </div>
                    
                    <div class="text-center mt-4">
                        <button class="btn btn-primary submit-btn" name="edit-chemistry"><i class="fas fa-save mr-2"></i>Update Result</button>
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