<?php
session_start();
if (empty($_SESSION['name'])) {
    header('location:index.php');
    exit();
}
include('header.php');
include('includes/connection.php');

// Fetch the patient details
if (isset($_GET['id'])) {
    $id = mysqli_real_escape_string($connection, $_GET['id']); // Sanitize ID
    $fetch_query_stmt = mysqli_prepare($connection, "SELECT * FROM tbl_patient WHERE id = ?");
    mysqli_stmt_bind_param($fetch_query_stmt, 'i', $id);
    mysqli_stmt_execute($fetch_query_stmt);
    $result = mysqli_stmt_get_result($fetch_query_stmt);
    $row = mysqli_fetch_array($result, MYSQLI_ASSOC);
    mysqli_stmt_close($fetch_query_stmt);
}

// Update patient details
if (isset($_POST['save-patient'])) {
    // Sanitize inputs
    $first_name = mysqli_real_escape_string($connection, $_POST['first_name']);
    $last_name = mysqli_real_escape_string($connection, $_POST['last_name']);
    $email = mysqli_real_escape_string($connection, $_POST['email']);
    $dob = mysqli_real_escape_string($connection, $_POST['dob']);
    $gender = mysqli_real_escape_string($connection, $_POST['gender']);
    $civil_status = mysqli_real_escape_string($connection, $_POST['civil_status']);
    $patient_type = mysqli_real_escape_string($connection, $_POST['patient_type']);
    $contact_number = mysqli_real_escape_string($connection, $_POST['contact_number']);
    $address = mysqli_real_escape_string($connection, $_POST['address']);
    $message = mysqli_real_escape_string($connection, $_POST['message']);
    $status = mysqli_real_escape_string($connection, $_POST['status']);
    $weight = mysqli_real_escape_string($connection, $_POST['weight']);
    $height = mysqli_real_escape_string($connection, $_POST['height']);
    $temperature = mysqli_real_escape_string($connection, $_POST['temperature']);
    $blood_pressure = mysqli_real_escape_string($connection, $_POST['blood_pressure']);
    $menstruation = mysqli_real_escape_string($connection, $_POST['menstruation']);
    $last_menstrual_period = mysqli_real_escape_string($connection, $_POST['last_menstrual_period']);

    // Handle fields for male patients
    if ($gender == 'Male') {
        $menstruation = '';
        $last_menstrual_period = '';
    }

    // If "None" or "Menopause" is selected for menstruation, clear the last menstrual period
    if ($menstruation == 'None' || $menstruation == 'Menopause') {
        $last_menstrual_period = ''; // Clear the last menstrual period field
    }

    // Prepare the update statement
    $update_query_stmt = mysqli_prepare($connection, "UPDATE tbl_patient SET first_name = ?, last_name = ?, email = ?, dob = ?, gender = ?, civil_status = ?, patient_type = ?, address = ?, contact_number = ?, status = ?, message = ?, weight = ?, height = ?, temperature = ?, blood_pressure = ?, menstruation = ?, last_menstrual_period = ? WHERE id = ?");
    mysqli_stmt_bind_param($update_query_stmt, 'sssssssssssssssssi', $first_name, $last_name, $email, $dob, $gender, $civil_status, $patient_type, $address, $contact_number, $status, $message, $weight, $height, $temperature, $blood_pressure, $menstruation, $last_menstrual_period, $id);
    $update_result = mysqli_stmt_execute($update_query_stmt);

    if ($update_result) {
        $msg = "Patient updated successfully";
        // Re-fetch updated data
        $fetch_query_stmt = mysqli_prepare($connection, "SELECT * FROM tbl_patient WHERE id = ?");
        mysqli_stmt_bind_param($fetch_query_stmt, 'i', $id);
        mysqli_stmt_execute($fetch_query_stmt);
        $result = mysqli_stmt_get_result($fetch_query_stmt);
        $row = mysqli_fetch_array($result, MYSQLI_ASSOC);
        mysqli_stmt_close($fetch_query_stmt);
    } else {
        $msg = "Error!";
    }

    mysqli_stmt_close($update_query_stmt);
}
?>

<div class="page-wrapper">
    <div class="content">
        <div class="row">
            <div class="col-sm-4">
                <h4 class="page-title">Edit Patient</h4>
            </div>
            <div class="col-sm-8 text-right mb-3">
                <a href="patients.php" class="btn btn-primary btn-rounded">Back</a>
            </div>
        </div>
        <div class="row">
            <div class="col-lg-8 offset-lg-2">
                <form method="post">
                    <div class="row">
                        <div class="col-sm-6">
                            <div class="form-group">
                                <label>Patient ID <span class="text-danger">*</span></label>
                                <input class="form-control" type="text" name="patient_id" value="<?php echo $row['patient_id']; ?>" disabled>
                            </div>
                        </div>
                        <div class="col-sm-6">
                            <div class="form-group">
                                <label>First Name <span class="text-danger">*</span></label>
                                <input class="form-control" type="text" name="first_name" value="<?php echo $row['first_name']; ?>">
                            </div>
                        </div>
                        <div class="col-sm-6">
                            <div class="form-group">
                                <label>Last Name</label>
                                <input class="form-control" type="text" name="last_name" value="<?php echo $row['last_name']; ?>">
                            </div>
                        </div>
                        <div class="col-sm-6">
                            <div class="form-group">
                                <label>Email <span class="text-danger">*</span></label>
                                <input class="form-control" type="email" name="email" value="<?php echo $row['email']; ?>">
                            </div>
                        </div>
                        <div class="col-sm-6">
                            <div class="form-group">
                                <label>Date of Birth</label>
                                <div class="cal-icon">
                                    <input type="text" class="form-control datetimepicker" name="dob" value="<?php echo $row['dob']; ?>">
                                </div>
                            </div>
                        </div>
                        <div class="col-sm-6">
                            <div class="form-group">
                                <label>Contact Number</label>
                                <input class="form-control" type="text" name="contact_number" value="<?php echo $row['contact_number']; ?>">
                            </div>
                        </div>
                        <div class="col-sm-6">
                            <div class="form-group">
                                <label class="gen-label">Gender:</label>
                                <div class="form-check-inline">
                                    <label class="form-check-label">
                                        <input type="radio" name="gender" class="form-check-input" value="Male" <?php if ($row['gender'] == 'Male') { echo 'checked'; } ?>> Male
                                    </label>
                                </div>
                                <div class="form-check-inline">
                                    <label class="form-check-label">
                                        <input type="radio" name="gender" class="form-check-input" value="Female" <?php if ($row['gender'] == 'Female') { echo 'checked'; } ?>> Female
                                    </label>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Civil Status</label>
                                <select class="form-control" name="civil_status">
                                    <option value="">Select</option>
                                    <option value="Single" <?php if ($row['civil_status'] == 'Single') echo 'selected'; ?>>Single</option>
                                    <option value="Married" <?php if ($row['civil_status'] == 'Married') echo 'selected'; ?>>Married</option>
                                    <option value="Widow" <?php if ($row['civil_status'] == 'Widow') echo 'selected'; ?>>Widow</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Patient's Type</label>
                                <select class="form-control" name="patient_type" required>
                                    <option value="">Select</option>
                                    <option value="Inpatient" <?php if ($row['patient_type'] == 'Inpatient') echo 'selected'; ?>>Inpatient</option>
                                    <option value="Outpatient" <?php if ($row['patient_type'] == 'Outpatient') echo 'selected'; ?>>Outpatient</option>
                                    <option value="Hemodialysis" <?php if ($row['patient_type'] == 'Hemodialysis') echo 'selected'; ?>>Hemodialysis</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-sm-6">
                            <div class="form-group">
                                <label>Address</label>
                                <input class="form-control" type="text" name="address" value="<?php echo $row['address']; ?>">
                            </div>
                        </div>
                        <div class="col-sm-6">
                            <div class="form-group">
                                <label>Weight</label>
                                <input class="form-control" type="text" name="weight" value="<?php echo $row['weight']; ?>">
                                <span class="input-group-text">kg</span>
                            </div>
                        </div>
                        <div class="col-sm-6">
                            <div class="form-group">
                                <label>Height</label>
                                <input class="form-control" type="text" name="height" value="<?php echo $row['height']; ?>">
                                <span class="input-group-text">ft</span>
                            </div>
                        </div>
                        <div class="col-sm-6">
                            <div class="form-group">
                                <label>Temperature</label>
                                <input class="form-control" type="text" name="temperature" value="<?php echo $row['temperature']; ?>">
                                <span class="input-group-text">°C</span>
                            </div>
                        </div>
                        <div class="col-sm-6">
                            <div class="form-group">
                                <label>Blood Pressure</label>
                                <input class="form-control" type="text" name="blood_pressure" value="<?php echo $row['blood_pressure']; ?>">
                            </div>
                        </div>
                        <div class="col-md-6 menstruation-fields" style="<?php if ($row['gender'] == 'Male') { echo 'display: none;'; } ?>">
                            <div class="form-group">
                                <label>Menstruation</label>
                                <select class="form-control" name="menstruation" id="menstruationSelect">
                                    <option value="">Select</option>
                                    <option value="Regular" <?php if ($row['menstruation'] == 'Regular') echo 'selected'; ?>>Regular</option>
                                    <option value="Irregular" <?php if ($row['menstruation'] == 'Irregular') echo 'selected'; ?>>Irregular</option>
                                    <option value="Menopause" <?php if ($row['menstruation'] == 'Menopause') echo 'selected'; ?>>Menopause</option>
                                    <option value="None" <?php if ($row['menstruation'] == 'None') echo 'selected'; ?>>None</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6 menstruation-fields" style="<?php if ($row['gender'] == 'Male') { echo 'display: none;'; } ?>">
                            <div class="form-group">
                                <label>Last Menstrual Period</label>
                                <div class="cal-icon">
                                    <input type="text" class="form-control datetimepicker" id="lastMenstrualPeriodInput" name="last_menstrual_period" value="<?php echo $row['last_menstrual_period']; ?>">
                                </div>
                            </div>
                        </div>
                        <div class="col-sm-6">
                            <div class="form-group">
                                <label>Other concerns</label>
                                <textarea cols="30" rows="4" class="form-control" name="message"><?php echo $row['message']; ?></textarea>
                            </div>
                        </div>
                        <div class="col-sm-6">
                            <div class="form-group">
                                <label class="display-block">Status</label>
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="radio" name="status" id="patient_active" value="1" <?php if ($row['status'] == 1) { echo 'checked'; } ?>>
                                    <label class="form-check-label" for="patient_active">Active</label>
                                </div>
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="radio" name="status" id="patient_inactive" value="0" <?php if ($row['status'] == 0) { echo 'checked'; } ?>>
                                    <label class="form-check-label" for="patient_inactive">Inactive</label>
                                </div>
                            </div>
                        </div>
                        <div class="col-12 mt-3 text-center">
                            <button class="btn btn-primary submit-btn" name="save-patient">Save</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>


<?php
include('footer.php');
?>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script type="text/javascript">
  $(document).ready(function() {
    // Hide or show menstruation-related fields based on gender selection
    $('input[name="gender"]').on('change', function() {
        if ($(this).val() == 'Male') {
            $('.menstruation-fields').hide(); // Hide all menstruation-related fields
            $('#menstruationSelect').val(''); // Clear the menstruation select field
            $('#lastMenstrualPeriodInput').val(''); // Clear the last menstrual period field
        } else {
            $('.menstruation-fields').show(); // Show menstruation-related fields
        }
    });

    // Handle changes in the menstruation select field
    $('#menstruationSelect').on('change', function() {
        if ($(this).val() == 'None' || $(this).val() == 'Menopause') {
            $('#lastMenstrualPeriodInput').val(''); // Clear the last menstrual period field
            $('#lastMenstrualPeriodInput').prop('disabled', true); // Disable the input field
        } else {
            $('#lastMenstrualPeriodInput').prop('disabled', false); // Enable the input field if not 'None' or 'Menopause'
        }
    });
});
</script>

<script type="text/javascript">
    <?php
    if (isset($msg)) {
        echo 'swal("' . $msg . '");';
    }
    ?>
</script>

<style>
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






