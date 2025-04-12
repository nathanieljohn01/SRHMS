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

// Fetch the patient details
if (isset($_GET['id'])) {
    $id = sanitize($connection, $_GET['id']); // Sanitize ID
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
    $first_name = sanitize($connection, $_POST['first_name']);
    $last_name = sanitize($connection, $_POST['last_name']);
    $email = sanitize($connection, $_POST['email']);
    $dob = sanitize($connection, $_POST['dob']);
    $gender = sanitize($connection, $_POST['gender']);
    $civil_status = sanitize($connection, $_POST['civil_status']);
    $patient_type = sanitize($connection, $_POST['patient_type']);
    $contact_number = sanitize($connection, $_POST['contact_number']);
    $address = sanitize($connection, $_POST['address']);
    $message = sanitize($connection, $_POST['message']);
    $status = sanitize($connection, $_POST['status']);
    $weight = sanitize($connection, $_POST['weight']);
    $height = sanitize($connection, $_POST['height']);
    $temperature = sanitize($connection, $_POST['temperature']);
    $blood_pressure = sanitize($connection, $_POST['blood_pressure']);
    $menstruation = sanitize($connection, $_POST['menstruation']);
    $last_menstrual_period = sanitize($connection, $_POST['last_menstrual_period']);

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
        
        // Success message for SweetAlert
        echo "
        <script src='https://cdn.jsdelivr.net/npm/sweetalert2@11'></script>
        <script>
            Swal.fire({
                title: 'Success!',
                text: '$msg',
                icon: 'success',
                confirmButtonColor: '#12369e',
                confirmButtonText: 'OK'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = 'patients.php?id=$id'; // Redirect to updated patient details page
                }
            });
        </script>";
    } else {
        $msg = "Error updating patient details!";
        
        // Error message for SweetAlert
        echo "
        <script src='https://cdn.jsdelivr.net/npm/sweetalert2@11'></script>
        <script>
            Swal.fire({
                title: 'Error!',
                text: '$msg',
                icon: 'error',
                confirmButtonColor: '#12369e',
                confirmButtonText: 'OK'
            });
        </script>";
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
                <a href="patients.php" class="btn btn-primary btn-rounded"><i class="fa fa-arrow-left"></i> Back</a>
            </div>
        </div>
        <div class="row">
            <div class="col-lg-8 offset-lg-2">
                <form id="editPatientForm" method="post">
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
                                <input class="form-control" type="number" name="weight" value="<?php echo $row['weight']; ?>">
                                <span class="input-group-text">kg</span>
                            </div>
                        </div>
                        <div class="col-sm-6">
                            <div class="form-group">
                                <label>Height</label>
                                <input class="form-control" type="number" name="height" value="<?php echo $row['height']; ?>">
                                <span class="input-group-text">ft</span>
                            </div>
                        </div>
                        <div class="col-sm-6">
                            <div class="form-group">
                                <label>Temperature</label>
                                <input class="form-control" type="number" name="temperature" value="<?php echo $row['temperature']; ?>">
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
                            <button class="btn btn-primary submit-btn" name="save-patient"><i class="fas fa-save mr-2"></i>Update</button>
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

// Handle form submission
$('#editPatientForm').on('submit', function(e) {
    e.preventDefault();
    
    // Basic validation
    const required = ['first_name', 'last_name', 'dob', 'gender', 'contact_number', 'address'];
    let isValid = true;
    let emptyFields = [];
    
    required.forEach(field => {
        if (!$(`#${field}`).val()) {
            isValid = false;
            emptyFields.push(field.replace('_', ' '));
        }
    });
    
    if (!isValid) {
        Swal.fire({
            icon: 'error',
            title: 'Error',
            text: `Please fill in the following fields: ${emptyFields.join(', ')}`,
            showConfirmButton: false,
            timer: 2000
        });
        return;
    }
    
    // Validate contact number
    const contact = $('#contact_number').val();
    if (!/^[0-9]{11}$/.test(contact)) {
        Swal.fire({
            icon: 'error',
            title: 'Error',
            text: 'Contact number must be 11 digits',
            showConfirmButton: false,
            timer: 2000
        });
        return;
    }
    
    // Validate date of birth
    const dob = new Date($('#dob').val());
    const today = new Date();
    if (dob > today) {
        Swal.fire({
            icon: 'error',
            title: 'Error',
            text: 'Date of birth cannot be in the future',
            showConfirmButton: false,
            timer: 2000
        });
        return;
    }
    
    // Show loading state
    Swal.fire({
        title: 'Updating patient information...',
        text: 'Please wait...',
        allowOutsideClick: false,
        showConfirmButton: false,
        willOpen: () => {
            Swal.showLoading();
        }
    });
    
    // Submit the form
    this.submit();
});

// Initialize datepicker with better UX
$('#dob').datetimepicker({
    format: 'YYYY-MM-DD',
    maxDate: new Date(),
    icons: {
        up: "fa fa-chevron-up",
        down: "fa fa-chevron-down",
        next: 'fa fa-chevron-right',
        previous: 'fa fa-chevron-left'
    }
});

// Auto-format contact number
$('#contact_number').on('input', function() {
    let value = $(this).val().replace(/\D/g, '');
    if (value.length > 11) {
        value = value.substr(0, 11);
    }
    $(this).val(value);
});

// Handle changes in patient type
$('#patient_type').on('change', function() {
    const type = $(this).val();
    if (type === 'Inpatient') {
        // Show room selection modal
        Swal.fire({
            title: 'Select Room',
            html: `
                <div class="form-group">
                    <label>Room Number</label>
                    <select class="form-control" id="room_no">
                        <option value="">Select Room</option>
                        <?php
                        // Get available rooms
                        $room_query = mysqli_query($connection, "SELECT * FROM tbl_rooms WHERE status='Available'");
                        while ($room = mysqli_fetch_array($room_query)) {
                            echo "<option value='" . $room['room_no'] . "'>" . $room['room_no'] . "</option>";
                        }
                        ?>
                    </select>
                </div>
            `,
            showCancelButton: true,
            confirmButtonText: 'Confirm',
            cancelButtonText: 'Cancel',
            preConfirm: () => {
                const room = $('#room_no').val();
                if (!room) {
                    Swal.showValidationMessage('Please select a room');
                }
                return room;
            }
        }).then((result) => {
            if (result.value) {
                $('#room_no_hidden').val(result.value);
            } else {
                $(this).val('Outpatient');
            }
        });
    }
});

// Confirm before leaving page with unsaved changes
window.onbeforeunload = function() {
    if ($('#editPatientForm').data('changed')) {
        return "You have unsaved changes. Are you sure you want to leave?";
    }
};

// Track form changes
$('#editPatientForm :input').on('change', function() {
    $('#editPatientForm').data('changed', true);
});

// Clear form change tracking on submit
$('#editPatientForm').on('submit', function() {
    $(this).data('changed', false);
});

// SweetAlert2 helper functions
function showSuccess(message, redirect = false) {
    Swal.fire({
        icon: 'success',
        title: 'Success',
        text: message,
        showConfirmButton: false,
        timer: 2000
    }).then(() => {
        if (redirect) {
            window.location.href = 'patients.php';
        }
    });
}

function showError(message) {
    Swal.fire({
        icon: 'error',
        title: 'Error',
        text: message,
        showConfirmButton: false,
        timer: 2000
    });
}

function showLoading(message) {
    Swal.fire({
        title: message,
        text: 'Please wait...',
        allowOutsideClick: false,
        showConfirmButton: false,
        willOpen: () => {
            Swal.showLoading();
        }
    });
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
