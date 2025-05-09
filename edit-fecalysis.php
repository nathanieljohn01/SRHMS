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

// Get fecalysis ID for fetching existing data
if (isset($_GET['id'])) {
    $fecalysis_id = sanitize($connection, $_GET['id']);

    // Fetch existing fecalysis data using prepared statement
    $fetch_query = mysqli_prepare($connection, "SELECT * FROM tbl_fecalysis WHERE fecalysis_id = ?");
    mysqli_stmt_bind_param($fetch_query, "s", $fecalysis_id);
    mysqli_stmt_execute($fetch_query);
    $result = mysqli_stmt_get_result($fetch_query);
    $fecalysis_data = mysqli_fetch_array($result);
    mysqli_stmt_close($fetch_query);

    if (!$fecalysis_data) {
        echo "Fecalysis data not found.";
        exit;
    }
}

// Handle form submission for editing fecalysis data
if (isset($_POST['edit-fecalysis'])) {
    // Sanitize inputs
    $fecalysis_id = sanitize($connection, $_POST['fecalysis_id']);
    $patient_name = sanitize($connection, $_POST['patient_name']);
    $date_time = sanitize($connection, $_POST['date_time']);
    $color = sanitize($connection, $_POST['color'] ?? NULL);
    $consistency = sanitize($connection, $_POST['consistency'] ?? NULL);
    $occult_blood = sanitize($connection, $_POST['occult_blood'] ?? NULL);
    $ova_or_parasite = sanitize($connection, $_POST['ova_or_parasite'] ?? NULL);
    $yeast_cells = sanitize($connection, $_POST['yeast_cells'] ?? NULL);
    $fat_globules = sanitize($connection, $_POST['fat_globules'] ?? NULL);
    $pus_cells = sanitize($connection, $_POST['pus_cells'] ?? NULL);
    $rbc = sanitize($connection, $_POST['rbc'] ?? NULL);
    $bacteria = sanitize($connection, $_POST['bacteria'] ?? NULL);

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

    // Update fecalysis data using prepared statement
    $update_query = mysqli_prepare($connection, "UPDATE tbl_fecalysis SET patient_name = ?, dob = ?, gender = ?, date_time = ?, color = ?, consistency = ?, occult_blood = ?, ova_or_parasite = ?, yeast_cells = ?, fat_globules = ?, pus_cells = ?, rbc = ?, bacteria = ? WHERE fecalysis_id = ?");

    // Bind all parameters as strings
    mysqli_stmt_bind_param($update_query, "ssssssssssssss", $patient_name, $dob, $gender, $date_time, $color, $consistency, $occult_blood, $ova_or_parasite, $yeast_cells, $fat_globules, $pus_cells, $rbc, $bacteria, $fecalysis_id);

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
                    text: 'Fecalysis result updated successfully!',
                    confirmButtonColor: '#12369e'
                }).then(() => {
                    // Redirect to the relevant page or refresh
                    window.location.href = 'fecalysis.php'; // Adjust the URL as needed
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
                    text: 'Error updating the fecalysis result!',
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
                <h4 class="page-title">Edit Fecalysis Result</h4>
            </div>
            <div class="col-sm-8 text-right mb-3">
                <a href="fecalysis.php" class="btn btn-primary"><i class="fa fa-arrow-left"></i> Back</a>
            </div>
        </div>
        <div class="row">
            <div class="col-lg-10 offset-lg-1">
                <form method="post">
                    <div class="form-group row">
                        <div class="col-sm-6">
                            <label for="fecalysis_id">Fecalysis ID</label>
                            <input class="form-control" type="text" name="fecalysis_id" value="<?php echo htmlspecialchars($fecalysis_data['fecalysis_id']); ?>" readonly>
                        </div>
                        <div class="col-sm-6">
                            <label for="patient_name">Patient Name</label>
                            <input class="form-control" type="text" name="patient_name" value="<?php echo htmlspecialchars($fecalysis_data['patient_name']); ?>" readonly>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="date_time">Date and Time</label>
                        <input type="datetime-local" class="form-control" name="date_time" value="<?php echo date('Y-m-d\TH:i', strtotime($fecalysis_data['date_time'])); ?>">
                    </div>
                    <div class="form-group">
                        <label for="color">Color</label>
                        <input class="form-control" type="text" name="color" value="<?php echo htmlspecialchars($fecalysis_data['color']); ?>">
                    </div>
                    <div class="form-group">
                        <label for="consistency">Consistency</label>
                        <input class="form-control" type="text" name="consistency" value="<?php echo htmlspecialchars($fecalysis_data['consistency']); ?>">
                    </div>
                    <div class="form-group">
                        <label for="occult_blood">Occult Blood</label>
                        <select class="form-control" name="occult_blood">
                            <option value="Select" <?php echo ($fecalysis_data['occult_blood'] == 'Select') ? 'selected' : ''; ?>readonly>Select</option>
                            <option value="Negative" <?php echo ($fecalysis_data['occult_blood'] == 'Negative') ? 'selected' : ''; ?>>Negative</option>
                            <option value="Positive" <?php echo ($fecalysis_data['occult_blood'] == 'Positive') ? 'selected' : ''; ?>>Positive</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="ova_or_parasite">Ova/Parasite</label>
                        <input class="form-control" type="text" name="ova_or_parasite" value="<?php echo htmlspecialchars($fecalysis_data['ova_or_parasite']); ?>">
                    </div>
                    <div class="form-group">
                        <label for="yeast_cells">Yeast Cells</label>
                        <input class="form-control" type="number" name="yeast_cells" min="0" step="1" value="<?php echo htmlspecialchars($fecalysis_data['yeast_cells']); ?>">
                    </div>
                    <div class="form-group">
                        <label for="fat_globules">Fat Globules</label>
                        <input class="form-control" type="number" name="fat_globules" min="0" step="1" value="<?php echo htmlspecialchars($fecalysis_data['fat_globules']); ?>">
                    </div>
                    <div class="form-group">
                        <label for="pus_cells">Pus Cells (HPF)</label>
                        <input class="form-control" type="text" name="pus_cells" value="<?php echo htmlspecialchars($fecalysis_data['pus_cells']); ?>">
                    </div>
                    <div class="form-group">
                        <label for="rbc">RBC (HPF)</label>
                        <input class="form-control" type="text" name="rbc" value="<?php echo htmlspecialchars($fecalysis_data['rbc']); ?>">
                    </div>
                    <div class="form-group">
                        <label for="bacteria">Bacteria</label>
                        <select class="form-control" name="bacteria">
                            <option value="Select" <?php echo ($fecalysis_data['bacteria'] == 'Select') ? 'selected' : ''; ?>readonly>Select</option> 
                            <option value="None" <?php echo ($fecalysis_data['bacteria'] == 'None') ? 'selected' : ''; ?>>None</option>
                            <option value="Few" <?php echo ($fecalysis_data['bacteria'] == 'Few') ? 'selected' : ''; ?>>Few</option>
                            <option value="Moderate" <?php echo ($fecalysis_data['bacteria'] == 'Moderate') ? 'selected' : ''; ?>>Moderate</option>
                            <option value="Many" <?php echo ($fecalysis_data['bacteria'] == 'Many') ? 'selected' : ''; ?>>Many</option>
                        </select>
                    </div>
                    <div class="text-center mt-4">
                        <button class="btn btn-primary submit-btn" name="edit-fecalysis"><i class="fas fa-save mr-2"></i>Update Result</button>
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
