<?php
session_start();
if (empty($_SESSION['name'])) {
    header('location:index.php');
    exit();
}
include('header.php');
include('includes/connection.php');

// Function to sanitize user input
function sanitize_input($connection, $input) {
    return mysqli_real_escape_string($connection, htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8'));
}

// Get the next patient ID
$fetch_query = mysqli_query($connection, "SELECT MAX(id) as id FROM tbl_patient");
$row = mysqli_fetch_row($fetch_query);
$pt_id = $row[0] == 0 ? 1 : $row[0] + 1;

// Handle form submission
if (isset($_POST['submit'])) {
    try {
        // Show loading state first
        echo "<script>showLoading('Saving patient information...');</script>";
        
        // Validate required fields
        $required_fields = ['first_name', 'last_name', 'dob', 'gender', 'contact_number', 'address'];
        foreach ($required_fields as $field) {
            if (empty($_POST[$field])) {
                throw new Exception("Please fill in all required fields");
            }
        }
        
        // Validate contact number format
        if (!preg_match("/^[0-9]{11}$/", $_POST['contact_number'])) {
            throw new Exception("Contact number must be 11 digits");
        }
        
        // Sanitize all inputs
        $patient_id = 'PT-' . $pt_id;
        $first_name = sanitize_input($connection, $_POST['first_name']);
        $last_name = sanitize_input($connection, $_POST['last_name']);
        $email = sanitize_input($connection, $_POST['email']);
        $dob = sanitize_input($connection, $_POST['dob']);
        $gender = sanitize_input($connection, $_POST['gender']);
        $civil_status = sanitize_input($connection, $_POST['civil_status']);
        $patient_type = sanitize_input($connection, $_POST['patient_type']);
        $contact_number = sanitize_input($connection, $_POST['contact_number']);
        $address = sanitize_input($connection, $_POST['address']);
        $date_time = sanitize_input($connection, $_POST['date_time']);
        $status = 1;
        $message = sanitize_input($connection, $_POST['message']);
        $weight = sanitize_input($connection, $_POST['weight']);
        $height = sanitize_input($connection, $_POST['height']);
        $temperature = sanitize_input($connection, $_POST['temperature']);
        $blood_pressure = sanitize_input($connection, $_POST['blood_pressure']);
        $menstruation = sanitize_input($connection, $_POST['menstruation']);
        $last_menstrual_period = sanitize_input($connection, $_POST['last_menstrual_period']);

        // Handle gender-specific fields
        if ($gender == 'Male') {
            $menstruation = 'None';
            $last_menstrual_period = '';
        }

        // If "None" or "Menopause" is selected for menstruation, clear the last menstrual period
        if ($menstruation == 'None' || $menstruation == 'Menopause') {
            $last_menstrual_period = ''; // Clear the last menstrual period field
        }

        // Check if the patient with the same first and last name already exists
        $check_query = mysqli_prepare($connection, "SELECT * FROM tbl_patient WHERE first_name = ? AND last_name = ?");
        mysqli_stmt_bind_param($check_query, 'ss', $first_name, $last_name);
        mysqli_stmt_execute($check_query);
        $result = mysqli_stmt_get_result($check_query);

        if (mysqli_num_rows($result) > 0) {
            throw new Exception("Patient with the same name already exists.");
        } else {
            // Insert patient record using prepared statement
            $insert_query = mysqli_prepare($connection, "INSERT INTO tbl_patient (patient_id, first_name, last_name, email, dob, gender, civil_status, patient_type, contact_number, address, status, message, weight, height, temperature, blood_pressure, menstruation, last_menstrual_period, date_time) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
            mysqli_stmt_bind_param($insert_query, 'ssssssssssssssssss', $patient_id, $first_name, $last_name, $email, $dob, $gender, $civil_status, $patient_type, $contact_number, $address, $status, $message, $weight, $height, $temperature, $blood_pressure, $menstruation, $last_menstrual_period);

            if (mysqli_stmt_execute($insert_query)) {
                echo "
                <script src='https://cdn.jsdelivr.net/npm/sweetalert2@10'></script>
                <script>
                    Swal.fire({
                        icon: 'success',
                        title: 'Success',
                        text: 'Patient added successfully!',
                        showConfirmButton: true,
                        confirmButtonText: 'OK'
                        confirmButtonColor: '#12369e'
                    }).then((result) => {
                        window.location.href = 'patients.php';
                    });
                </script>";
            } else {
                throw new Exception("Error inserting patient data");
            }

            // Close the insert statement
            mysqli_stmt_close($insert_query);
        }

        // Close the check query statement
        mysqli_stmt_close($check_query);
    } catch (Exception $e) {
        echo "<script>
            showError('" . addslashes($e->getMessage()) . "');
        </script>";
    }
}
?>

<div class="page-wrapper">
    <div class="content">
        <div class="row">
            <div class="col-sm-4">
                <h4 class="page-title">Add Patient</h4>
            </div>
            <div class="col-sm-8 text-right mb-3">
                <a href="patients.php" class="btn btn-primary float-right"><i class="fa fa-arrow-left"></i> Back</a>
            </div>
        </div>
        <div class="row">
            <div class="col-lg-8 offset-lg-2">
                <form id="addPatientForm" method="post">
                    <div class="row">
                        <div class="col-sm-6">
                            <div class="form-group">
                                <label>Patient ID</label>
                                <input class="form-control" type="text" name="patient_id" value="<?php if(!empty($pt_id)) { echo 'PT-'.$pt_id; } else { echo 'PT-1'; } ?>" disabled>
                            </div>
                        </div>
                        <div class="col-sm-6">
                            <div class="form-group">
                                <label>First Name</label>
                                <input class="form-control" type="text" name="first_name" required>
                            </div>
                        </div>
                        <div class="col-sm-6">
                            <div class="form-group">
                                <label>Last Name</label>
                                <input class="form-control" type="text" name="last_name" required>
                            </div>
                        </div>
                        <div class="col-sm-6">
                            <div class="form-group">
                                <label>Email</label>
                                <input class="form-control" type="email" name="email">
                            </div>
                        </div>
                        <div class="col-sm-6">
                            <div class="form-group">
                                <label>Date of Birth</label>
                                <div class="cal-icon">
                                    <input type="text" class="form-control datetimepicker" name="dob" required>
                                </div>
                            </div>
                        </div>
                        <div class="col-sm-6">
                            <div class="form-group">
                                <label>Contact Number</label>
                                <input class="form-control" type="number" name="contact_number" required>
                            </div>
                        </div>
                        <div class="col-sm-6">
                            <div class="form-group">
                                <label>Gender</label>
                                <div class="form-check">
                                    <input type="radio" name="gender" class="form-check-input" value="Male" checked>
                                    <label class="form-check-label">Male</label>
                                </div>
                                <div class="form-check">
                                    <input type="radio" name="gender" class="form-check-input" value="Female">
                                    <label class="form-check-label">Female</label>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Civil Status</label>
                                <select class="form-control" name="civil_status">
                                    <option value="">Select</option>
                                    <option value="Single">Single</option>
                                    <option value="Married">Married</option>
                                    <option value="Widow">Widow</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Patient's Type</label>
                                <select class="form-control" name="patient_type" required>
                                    <option value="">Select</option>
                                    <option value="Inpatient">Inpatient</option>
                                    <option value="Outpatient">Outpatient</option>
                                    <option value="Hemodialysis">Hemodialysis</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-sm-6">
                            <div class="form-group">
                                <label>Address</label>
                                <input class="form-control" type="text" name="address" required>
                            </div>
                        </div>
                        <div class="col-sm-6">
                            <div class="form-group">
                                <label>Weight</label>
                                <div class="input-group">
                                    <input class="form-control" type="number" name="weight" step="0.01" min="0" required>
                                    <div class="input-group-append">
                                        <span class="input-group-text">kg</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-sm-6">
                            <div class="form-group">
                                <label>Height</label>
                                <div class="input-group">
                                    <input class="form-control" type="text" name="height" required>
                                    <div class="input-group-append">
                                        <span class="input-group-text">ft</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-sm-6">
                            <div class="form-group">
                                <label>Temperature</label>
                                <div class="input-group">
                                    <input class="form-control" type="number" name="temperature" required>
                                    <div class="input-group-append">
                                        <span class="input-group-text">°C</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-sm-6">
                            <div class="form-group">
                                <label>Blood Pressure</label>
                                <input class="form-control" type="text" name="blood_pressure">
                            </div>
                        </div>
                        <div class="col-md-6 menstruation-fields" style="display: none;">
                            <div class="form-group">
                                <label>Menstruation</label>
                                <select class="form-control" name="menstruation" id="menstruationSelect">
                                    <option value="">Select</option>
                                    <option value="Regular">Regular</option>
                                    <option value="Irregular">Irregular</option>
                                    <option value="Menopause">Menopause</option> 
                                    <option value="None">None</option> 
                                </select>
                            </div>
                        </div>

                        <div class="col-md-6 menstruation-fields" style="display: none;">
                            <div class="form-group">
                                <label>Last Menstrual Period</label>
                                <div class="cal-icon">
                                    <input type="text" class="form-control datetimepicker" name="last_menstrual_period" id="lastMenstrualPeriodInput">
                                </div>
                            </div>
                        </div>
                        <div class="col-12">
                            <div class="form-group">
                                <label>Other Concerns</label>
                                <textarea cols="30" rows="4" class="form-control" name="message"></textarea>
                            </div>
                        </div>
                        <div class="col-12 mt-3 text-center">
                            <button class="btn btn-primary submit-btn" name="submit">Save</button>
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
.form-group {
    position: relative;
}

.cal-icon {
    position: relative;
}

.cal-icon input {
    padding-right: 30px; /* Adjust the padding to make space for the icon */
}

.cal-icon::after {
    content: '\f073'; /* FontAwesome calendar icon */
    font-family: 'FontAwesome';
    position: absolute;
    right: 10px; /* Adjust this value to align the icon properly */
    top: 50%;
    transform: translateY(-50%);
    pointer-events: none;
    color: #aaa; /* Adjust color as needed */
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
