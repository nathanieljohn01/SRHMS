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

// Get crossmatching ID for fetching existing data
if (isset($_GET['id'])) {
    $crossmatching_id = sanitize($connection, $_GET['id']);

    // Fetch existing crossmatching data using prepared statement
    $fetch_query = mysqli_prepare($connection, "SELECT * FROM tbl_crossmatching WHERE crossmatching_id = ?");
    mysqli_stmt_bind_param($fetch_query, "s", $crossmatching_id);
    mysqli_stmt_execute($fetch_query);
    $result = mysqli_stmt_get_result($fetch_query);
    $crossmatching_data = mysqli_fetch_array($result);
    mysqli_stmt_close($fetch_query);

    if (!$crossmatching_data) {
        echo "Crossmatching data not found.";
        exit;
    }
}

// Handle form submission for editing crossmatching data
if (isset($_POST['edit-crossmatching'])) {
    // Sanitize inputs
    $crossmatching_id = sanitize($connection, $_POST['crossmatching_id']);
    $patient_name = sanitize($connection, $_POST['patient_name']);
    $date_time = sanitize($connection, $_POST['date_time']);
    $patient_blood_type = sanitize($connection, $_POST['patient_blood_type']);
    $blood_component = sanitize($connection, $_POST['blood_component']);
    $serial_number = sanitize($connection, $_POST['serial_number']);
    $extraction_date = sanitize($connection, $_POST['extraction_date']);
    $expiration_date = sanitize($connection, $_POST['expiration_date']);
    $major_crossmatching = sanitize($connection, $_POST['major_crossmatching']);
    $donors_blood_type = sanitize($connection, $_POST['donors_blood_type']);
    $packed_red_blood_cell = sanitize($connection, $_POST['packed_red_blood_cell']);
    $time_packed = sanitize($connection, $_POST['time_packed']);
    $dated = sanitize($connection, $_POST['dated']);
    $open_system = sanitize($connection, $_POST['open_system']);
    $closed_system = sanitize($connection, $_POST['closed_system']);
    $to_be_consumed_before = sanitize($connection, $_POST['to_be_consumed_before']);
    $hours = sanitize($connection, $_POST['hours']);
    $minor_crossmatching = sanitize($connection, $_POST['minor_crossmatching']);

    // Fetch patient information using prepared statement
    $patient_query = mysqli_prepare($connection, "SELECT patient_id, gender, dob, patient_type FROM tbl_patient WHERE CONCAT(first_name, ' ', last_name) = ?");
    mysqli_stmt_bind_param($patient_query, "s", $patient_name);
    mysqli_stmt_execute($patient_query);
    $patient_result = mysqli_stmt_get_result($patient_query);
    $row = mysqli_fetch_array($patient_result);
    mysqli_stmt_close($patient_query);

    $patient_id = $row['patient_id'];
    $gender = $row['gender'];
    $dob = $row['dob'];

    // Update crossmatching data using prepared statement
    $update_query = mysqli_prepare($connection, "UPDATE tbl_crossmatching SET 
        patient_name = ?,
        dob = ?,
        gender = ?,
        date_time = ?,
        patient_blood_type = ?, 
        blood_component = ?, 
        serial_number = ?, 
        extraction_date = ?, 
        expiration_date = ?, 
        major_crossmatching = ?, 
        donors_blood_type = ?, 
        packed_red_blood_cell = ?, 
        time_packed = ?, 
        dated = ?, 
        open_system = ?, 
        closed_system = ?, 
        to_be_consumed_before = ?, 
        hours = ?, 
        minor_crossmatching = ? 
        WHERE crossmatching_id = ?");

    // Bind all parameters
    mysqli_stmt_bind_param($update_query, "ssssssssssssssssssss", $patient_name, $dob, $gender, $date_time, $patient_blood_type, $blood_component, $serial_number, $extraction_date, $expiration_date, $major_crossmatching, $donors_blood_type, $packed_red_blood_cell, $time_packed, $dated, $open_system, $closed_system, $to_be_consumed_before, $hours, $minor_crossmatching, $crossmatching_id);

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
                    text: 'Crossmatching record updated successfully!',
                    confirmButtonColor: '#12369e'
                }).then(() => {
                    window.location.href = 'crossmatching.php';
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
                    text: 'Error updating the crossmatching record!',
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
                <h4 class="page-title">Edit Crossmatching Result</h4>
            </div>
            <div class="col-sm-8 text-right mb-3">
                <a href="crossmatching.php" class="btn btn-primary"><i class="fa fa-arrow-left"></i> Back</a>
            </div>
        </div>
        <div class="row">
            <div class="col-lg-10 offset-lg-1">
                <form method="post">
                    <div class="form-group row">
                        <div class="col-sm-6">
                            <label for="crossmatching_id">Crossmatching ID</label>
                            <input class="form-control" type="text" name="crossmatching_id" id="crossmatching_id" value="<?php echo htmlspecialchars($crossmatching_data['crossmatching_id']); ?>" readonly>
                        </div>
                        <div class="col-sm-6">
                            <label for="patient_name">Patient Name</label>
                            <input class="form-control" type="text" name="patient_name" id="patient_name" value="<?php echo htmlspecialchars($crossmatching_data['patient_name']); ?>" readonly>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="date_time">Date and Time</label>
                        <input type="datetime-local" class="form-control" name="date_time" id="date_time" value="<?php echo date('Y-m-d\TH:i', strtotime($crossmatching_data['date_time'])); ?>">
                    </div>
                    <div class="form-group row">
                        <div class="col-sm-6">
                            <label for="patient_blood_type">Patient Blood Type</label>
                            <input class="form-control" type="text" name="patient_blood_type" id="patient_blood_type" value="<?php echo htmlspecialchars($crossmatching_data['patient_blood_type']); ?>" required>
                        </div>
                        <div class="col-sm-6">
                            <label for="blood_component">Blood Component</label>
                            <input class="form-control" type="text" name="blood_component" id="blood_component" value="<?php echo htmlspecialchars($crossmatching_data['blood_component']); ?>">
                        </div>
                    </div>
                    <div class="form-group row">
                        <div class="col-sm-6">
                            <label for="serial_number">Serial Number</label>
                            <input class="form-control" type="text" name="serial_number" id="serial_number" value="<?php echo htmlspecialchars($crossmatching_data['serial_number']); ?>">
                        </div>
                        <div class="col-sm-6">
                            <label for="extraction_date">Extraction Date</label>
                            <input type="date" class="form-control" name="extraction_date" id="extraction_date" value="<?php echo htmlspecialchars($crossmatching_data['extraction_date']); ?>">
                        </div>
                    </div>
                    <div class="form-group row">
                        <div class="col-sm-6">
                            <label for="expiration_date">Expiration Date</label>
                            <input type="date" class="form-control" name="expiration_date" id="expiration_date" value="<?php echo htmlspecialchars($crossmatching_data['expiration_date']); ?>">
                        </div>
                        <div class="col-sm-6">
                            <label for="major_crossmatching">Major Crossmatching</label>
                            <input class="form-control" type="text" name="major_crossmatching" id="major_crossmatching" value="<?php echo htmlspecialchars($crossmatching_data['major_crossmatching']); ?>">
                        </div>
                    </div>
                    <div class="form-group row">
                        <div class="col-sm-6">
                            <label for="donors_blood_type">Donor's Blood Type</label>
                            <input class="form-control" type="text" name="donors_blood_type" id="donors_blood_type" value="<?php echo htmlspecialchars($crossmatching_data['donors_blood_type']); ?>">
                        </div>
                        <div class="col-sm-6">
                            <label for="packed_red_blood_cell">For Packed Red Blood Cell</label>
                            <input class="form-control" type="text" name="packed_red_blood_cell" id="packed_red_blood_cell" value="<?php echo htmlspecialchars($crossmatching_data['packed_red_blood_cell']); ?>">
                        </div>
                    </div>
                    <div class="form-group row">
                        <div class="col-sm-6">
                            <label for="time_packed">Time Packed</label>
                            <div class="input-group">
                                <input type="time" class="form-control" name="time_packed" id="time_packed">
                                <button type="button" class="btn btn-primary px-3" onclick="setCurrentTime('time_packed')">Now</button>
                            </div>
                        </div>
                        <div class="col-sm-6">
                            <label for="dated">Dated</label>
                            <input type="date" class="form-control" name="dated" id="dated" value="<?php echo htmlspecialchars($crossmatching_data['dated']); ?>">
                        </div>
                    </div>
                    <div class="form-group row">
                        <div class="col-sm-6">
                            <label for="open_system">Open System</label>
                            <select class="form-control" name="open_system" id="open_system">
                                <option value="Select" <?php if ($crossmatching_data['open_system'] == 'Select') echo 'selected'; ?>readonly>Select</option>
                                <option value="Yes" <?php echo ($crossmatching_data['open_system'] == 'Yes') ? 'selected' : ''; ?>>Yes</option>
                                <option value="No" <?php echo ($crossmatching_data['open_system'] == 'No') ? 'selected' : ''; ?>>No</option>
                            </select>
                        </div>
                        <div class="col-sm-6">
                            <label for="closed_system">Closed System</label>
                            <select class="form-control" name="closed_system" id="closed_system">
                                <option value="Select" <?php if ($crossmatching_data['closed_system'] == 'Select') echo 'selected'; ?>readonly>Select</option>
                                <option value="Yes" <?php echo ($crossmatching_data['closed_system'] == 'Yes') ? 'selected' : ''; ?>>Yes</option>
                                <option value="No" <?php echo ($crossmatching_data['closed_system'] == 'No') ? 'selected' : ''; ?>>No</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-group row">
                        <div class="col-sm-6">
                            <label for="to_be_consumed_before">To Be Consumed Before</label>
                            <div class="input-group">
                                <input type="time" class="form-control" name="to_be_consumed_before" id="to_be_consumed_before">
                                <button type="button" class="btn btn-primary px-3" onclick="setCurrentTime('to_be_consumed_before')">Now</button>
                            </div>
                        </div>
                        <div class="col-sm-6">
                            <label for="hours">Hours</label>
                            <input type="number" class="form-control" name="hours" id="hours" value="<?php echo htmlspecialchars($crossmatching_data['hours']); ?>">
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="minor_crossmatching">Minor Crossmatching</label>
                        <input class="form-control" type="text" name="minor_crossmatching" id="minor_crossmatching" value="<?php echo htmlspecialchars($crossmatching_data['minor_crossmatching']); ?>">
                    </div>
                    <div class="text-center mt-4">
                        <button class="btn btn-primary submit-btn" name="edit-crossmatching">Update Crossmatching Result</button>
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
function setCurrentTime(inputId) {
    let now = new Date();
    let hours = String(now.getHours()).padStart(2, '0');
    let minutes = String(now.getMinutes()).padStart(2, '0');
    document.getElementById(inputId).value = `${hours}:${minutes}`;
}
</script>

<style>
.input-group .form-control {
    border-right: 0; /* Para seamless ang transition ng input field papunta sa button */
}

.input-group .btn {
    border-left: 0;
    border-radius: 0 8px 8px 0; /* Rounded corners sa right side */
}

.input-group .form-control:focus {
    box-shadow: none; /* Tatanggalin ang default focus shadow para mas clean */
} 
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
