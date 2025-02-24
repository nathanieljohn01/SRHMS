<?php
session_start();
if (empty($_SESSION['name'])) {
    header('location:index.php');
    exit();
}
include('header.php');
include('includes/connection.php');

// Sanitize function for input sanitization and XSS protection
function sanitize($connection, $input) {
    return mysqli_real_escape_string($connection, htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8'));
}

// Fetch CBC data
if (isset($_GET['id'])) {
    $cbc_id = sanitize($connection, $_GET['id']);  // Sanitize the ID

    $fetch_query_stmt = mysqli_prepare($connection, "SELECT * FROM tbl_cbc WHERE cbc_id = ?");
    mysqli_stmt_bind_param($fetch_query_stmt, 's', $cbc_id);
    mysqli_stmt_execute($fetch_query_stmt);
    $result = mysqli_stmt_get_result($fetch_query_stmt);
    $cbc_data = mysqli_fetch_assoc($result);
    mysqli_stmt_close($fetch_query_stmt);

    if (!$cbc_data) {
        echo "CBC data not found.";
        exit;
    }
}

// Update CBC data
if (isset($_POST['update-cbc'])) {
    $patient_name = sanitize($connection, $_POST['patient_name']);
    $date_time = sanitize($connection, $_POST['date_time']);
    $hemoglobin = sanitize($connection, $_POST['hemoglobin']);
    $hematocrit = sanitize($connection, $_POST['hematocrit']);
    $red_blood_cells = sanitize($connection, $_POST['red_blood_cells']);
    $white_blood_cells = sanitize($connection, $_POST['white_blood_cells']);
    $esr = sanitize($connection, $_POST['esr']);
    $segmenters = sanitize($connection, $_POST['segmenters']);
    $lymphocytes = sanitize($connection, $_POST['lymphocytes']);
    $eosinophils = sanitize($connection, $_POST['eosinophils']);
    $monocytes = sanitize($connection, $_POST['monocytes']);
    $bands = sanitize($connection, $_POST['bands']);
    $platelets = sanitize($connection, $_POST['platelets']);

    // Fetch Patient ID, Gender, and DOB based on the patient's name
    $fetch_patient_stmt = mysqli_prepare($connection, "SELECT patient_id, gender, dob FROM tbl_patient WHERE CONCAT(first_name, ' ', last_name) = ?");
    mysqli_stmt_bind_param($fetch_patient_stmt, 's', $patient_name);
    mysqli_stmt_execute($fetch_patient_stmt);
    $result = mysqli_stmt_get_result($fetch_patient_stmt);
    $row = mysqli_fetch_assoc($result);
    mysqli_stmt_close($fetch_patient_stmt);

    if (!$row) {
        echo "Patient not found.";
        exit;
    }

    $patient_id = $row['patient_id'];
    $gender = $row['gender'];
    $dob = $row['dob'];

    // Prepare the update statement
    $update_stmt = mysqli_prepare($connection, "UPDATE tbl_cbc SET patient_id = ?, patient_name = ?, dob = ?, gender = ?, date_time = ?, hemoglobin = ?, hematocrit = ?, red_blood_cells = ?, white_blood_cells = ?, esr = ?, segmenters = ?, lymphocytes = ?, eosinophils = ?, monocytes = ?, bands = ?, platelets = ? WHERE cbc_id = ?");
    mysqli_stmt_bind_param($update_stmt, 'sssssssssssssssss', $patient_id, $patient_name, $dob, $gender, $date_time, $hemoglobin, $hematocrit, $red_blood_cells, $white_blood_cells, $esr, $segmenters, $lymphocytes, $eosinophils, $monocytes, $bands, $platelets, $cbc_id);
    // Execute the update statement
    if (mysqli_stmt_execute($update_stmt)) {
        $msg = "CBC result updated successfully";

        // SweetAlert success message
        echo "
        <script src='https://cdn.jsdelivr.net/npm/sweetalert2@10'></script>
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                Swal.fire({
                    icon: 'success',
                    title: 'Success',
                    text: 'CBC result updated successfully',
                    confirmButtonColor: '#12369e'
                }).then(() => {
                    window.location.href = 'cbc.php'; // Adjust to the page you want to redirect to after success
                });
            });
        </script>";
    } else {
        $msg = "Error updating CBC data!";

        // SweetAlert error message
        echo "
        <script src='https://cdn.jsdelivr.net/npm/sweetalert2@10'></script>
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'Error updating CBC data: " . mysqli_error($connection) . "',
                });
            });
        </script>";
    }
    mysqli_stmt_close($update_stmt);
}
?>

<div class="page-wrapper">
    <div class="content">
        <div class="row">
            <div class="col-sm-4">
                <h4 class="page-title">Edit CBC Result</h4>
            </div>
            <div class="col-sm-8 text-right mb-3">
                <a href="cbc.php" class="btn btn-primary"><i class="fa fa-arrow-left"></i> Back</a>
            </div>
        </div>
        <div class="row">
            <div class="col-lg-10 offset-lg-1">
                <form method="post">
                    <div class="form-group row">
                        <div class="col-sm-6">
                            <label for="cbc_id">CBC ID</label>
                            <input class="form-control" type="text" name="cbc_id" id="cbc_id" value="<?php echo htmlspecialchars($cbc_data['cbc_id']); ?>" readonly>
                        </div>
                        <div class="col-sm-6">
                            <label for="patient_name">Patient Name</label>
                            <input class="form-control" type="text" name="patient_name" id="patient_name" value="<?php echo htmlspecialchars($cbc_data['patient_name']); ?>" readonly>
                        </div>
                    </div>
                    <div class="form-group row">
                        <div class="col-sm-6">
                            <label for="date_time">Date and Time</label>
                            <input type="datetime-local" class="form-control" name="date_time" id="date_time" value="<?php echo date('Y-m-d\TH:i', strtotime($cbc_data['date_time'])); ?>" required>
                        </div>
                    </div>
                    <div class="form-group">
                    <div class="form-group">
                        <label for="hemoglobin">Hemoglobin</label>
                        <input class="form-control" type="number" name="hemoglobin" id="hemoglobin" value="<?php echo htmlspecialchars($cbc_data['hemoglobin']); ?>" step="0.01" min="0" oninput="validateDecimal(this)">
                    </div>
                    <div class="form-group">
                        <label for="hematocrit">Hematocrit</label>
                        <input class="form-control" type="number" name="hematocrit" id="hematocrit" value="<?php echo htmlspecialchars($cbc_data['hematocrit']); ?>" step="0.01" min="0" oninput="validateDecimal(this)">
                    </div>
                    <div class="form-group">
                        <label for="red_blood_cells">Red Blood Cells</label>
                        <input class="form-control" type="number" name="red_blood_cells" id="red_blood_cells" value="<?php echo htmlspecialchars($cbc_data['red_blood_cells']); ?>" step="0.01" min="0" oninput="validateDecimal(this)">
                    </div>
                    <div class="form-group">
                        <label for="white_blood_cells">White Blood Cells</label>
                        <input class="form-control" type="number" name="white_blood_cells" id="white_blood_cells" value="<?php echo htmlspecialchars($cbc_data['white_blood_cells']); ?>" step="0.01" min="0" oninput="validateDecimal(this)">
                    </div>
                    <div class="form-group">
                        <label for="esr">ESR</label>
                        <input class="form-control" type="number" name="esr" id="esr" value="<?php echo htmlspecialchars($cbc_data['esr']); ?>" step="0.01" min="0" oninput="validateDecimal(this)">
                    </div>
                    <div class="form-group">
                        <label for="segmenters">Segmenters</label>
                        <input class="form-control" type="number" name="segmenters" id="segmenters" value="<?php echo htmlspecialchars($cbc_data['segmenters']); ?>" step="0.01" min="0" oninput="validateDecimal(this)">
                    </div>
                    <div class="form-group">
                        <label for="lymphocytes">Lymphocytes</label>
                        <input class="form-control" type="number" name="lymphocytes" id="lymphocytes" value="<?php echo htmlspecialchars($cbc_data['lymphocytes']); ?>" step="0.01" min="0" oninput="validateDecimal(this)">
                    </div>
                    <div class="form-group">
                        <label for="eosinophils">Eosinophils</label>
                        <input class="form-control" type="number" name="eosinophils" id="eosinophils" value="<?php echo htmlspecialchars($cbc_data['eosinophils']); ?>" step="0.01" min="0" oninput="validateDecimal(this)">
                    </div>
                    <div class="form-group">
                        <label for="monocytes">Monocytes</label>
                        <input class="form-control" type="number" name="monocytes" id="monocytes" value="<?php echo htmlspecialchars($cbc_data['monocytes']); ?>" step="0.01" min="0" oninput="validateDecimal(this)">
                    </div>
                    <div class="form-group">
                        <label for="bands">Bands</label>
                        <input class="form-control" type="number" name="bands" id="bands" value="<?php echo htmlspecialchars($cbc_data['bands']); ?>" step="0.01" min="0" oninput="validateDecimal(this)">
                    </div>
                    <div class="form-group">
                        <label for="platelets">Platelets</label>
                        <input class="form-control" type="number" name="platelets" id="platelets" value="<?php echo htmlspecialchars($cbc_data['platelets']); ?>" step="0.01" min="0" oninput="validateDecimal(this)">
                    </div>
                    <div class="text-center mt-4">
                        <button class="btn btn-primary submit-btn" name="update-cbc">Update Result</button>
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

<script>
function validateDecimal(input) {
    // Get the value of the input
    let value = input.value;
    
    // Allow only digits and at most two decimal places
    if (value !== '' && !/^\d+(\.\d{0,2})?$/.test(value)) {
        // If the value doesn't match the pattern, show custom validity message
        input.setCustomValidity('Please enter a valid number with up to 2 decimal places.');
    } else {
        // If the value is valid, clear any custom validity message
        input.setCustomValidity('');
    }
    
    // Truncate to 2 decimal places if there are more than 2 decimals
    if (value.includes('.')) {
        let parts = value.split('.');
        if (parts[1].length > 2) {
            input.value = parts[0] + '.' + parts[1].substring(0, 2);
        }
    }
}
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
    border-radius: .375rem; /* Rounded corners */
    border-color: #ced4da; /* Border color */
    background-color: #f8f9fa; /* Background color */
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
