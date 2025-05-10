<?php
session_start();
if (empty($_SESSION['name'])) {
    header('location:index.php');
    exit;
}

include('header.php');
include('includes/connection.php');

// Fetch the maximum ID to generate a new ID for the newborn
$fetch_query = mysqli_query($connection, "SELECT MAX(id) AS id FROM tbl_newborn");
$row = mysqli_fetch_row($fetch_query);
if ($row[0] == 0) {
    $newborn_id = 1;
} else {
    $newborn_id = $row[0] + 1;
}

if (isset($_REQUEST['save-newborn'])) {
    // Sanitize user input
    function sanitize($connection, $input) {
        return mysqli_real_escape_string($connection, htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8'));
    }

    $newborn_id = 'NB-' . $newborn_id;
    $first_name = sanitize($connection, $_REQUEST['first_name']);
    $last_name = sanitize($connection, $_REQUEST['last_name']);
    $dob = DateTime::createFromFormat('d/m/Y', $_REQUEST['dob'])->format('F j, Y');
    $tob = date("g:i A", strtotime($_REQUEST['tob']));
    $gender = sanitize($connection, $_REQUEST['gender']);
    $birth_weight = sanitize($connection, $_REQUEST['birth_weight']);
    $birth_height = sanitize($connection, $_REQUEST['birth_height']);
    $gestational_age = sanitize($connection, $_REQUEST['gestational_age']);
    $physician = sanitize($connection, $_REQUEST['physician']);
    $address = sanitize($connection, $_REQUEST['address']); 
    $diagnosis = "Newborn";
    
    // Set default value for room_type
    $room_type = "NICU"; 

    // Check if the newborn with the same first and last name already exists
    $check_query = mysqli_prepare($connection, "SELECT * FROM tbl_newborn WHERE first_name = ? AND last_name = ?");
    mysqli_stmt_bind_param($check_query, 'ss', $first_name, $last_name);
    mysqli_stmt_execute($check_query);
    $check_result = mysqli_stmt_get_result($check_query);

    if (mysqli_num_rows($check_result) > 0) {
        $msg = "Newborn with the same name already exists.";
        // SweetAlert error message
        echo "
        <script src='https://cdn.jsdelivr.net/npm/sweetalert2@10'></script>
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: '$msg'
                });
            });
        </script>";
    } else {
        // Insert newborn details into the database with room_type, address, and diagnosis
        $insert_query = mysqli_prepare($connection, "INSERT INTO tbl_newborn 
            (newborn_id, first_name, last_name, dob, tob, gender, birth_weight, birth_height, gestational_age, physician, address, diagnosis, room_type, admission_date) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
        mysqli_stmt_bind_param($insert_query, 'sssssssssssss', $newborn_id, $first_name, $last_name, $dob, $tob, $gender, $birth_weight, $birth_height, $gestational_age, $physician, $address, $diagnosis, $room_type);
    
        // Execute the insert query
        if (mysqli_stmt_execute($insert_query)) {
            $msg = "Newborn added successfully.";
            // SweetAlert success message
            echo "
            <script src='https://cdn.jsdelivr.net/npm/sweetalert2@10'></script>
            <script>
                document.addEventListener('DOMContentLoaded', function() {
                    var style = document.createElement('style');
                    style.innerHTML = '.swal2-confirm { background-color: #12369e !important; color: white !important; border: none !important; } .swal2-confirm:hover { background-color: #05007E !important; } .swal2-confirm:focus { box-shadow: 0 0 0 0.2rem rgba(18, 54, 158, 0.5) !important; }';
                    document.head.appendChild(style);
    
                    Swal.fire({
                        icon: 'success',
                        title: 'Success!',
                        text: '$msg',
                        confirmButtonColor: '#12369e'
                    }).then(() => {
                        window.location.href = 'newborn.php';
                    });
                });
            </script>";
        } else {
            $msg = "Error: " . mysqli_error($connection);
            // SweetAlert error message for failure
            echo "
            <script>
                document.addEventListener('DOMContentLoaded', function() {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: '$msg'
                    });
                });
            </script>";
        }
    
        // Close the prepared statements
        mysqli_stmt_close($check_query);
        mysqli_stmt_close($insert_query);
    }    
}
?>

<div class="page-wrapper">
    <div class="content">
        <div class="row">
            <div class="col-sm-4">
                <h4 class="page-title">Add Newborn</h4>
            </div>
            <div class="col-sm-8 text-right mb-3">
                <a href="newborn.php" class="btn btn-primary float-right"><i class="fa fa-arrow-left"></i> Back</a>
            </div>
        </div>
        <div class="row">
            <div class="col-lg-8 offset-lg-2">
                <form method="post">
                    <div class="row">
                        <div class="col-sm-6">
                            <div class="form-group">
                                <label>Newborn ID</label>
                                <input class="form-control" type="text" name="newborn_id" value="<?php if(!empty($newborn_id)) { echo 'NB-' . $newborn_id; } else { echo 'NB-1'; } ?>" disabled>
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
                                <label>Address</label>
                                <input class="form-control" type="text" name="address" required>
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
                            <div class="form-group position-relative">
                                <label for="tob">Time of Birth</label>
                                <input type="time" id="tob" class="form-control" name="tob" required>
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
                        <div class="col-sm-6">
                            <div class="form-group">
                                <label> Birth Weight</label>
                                <div class="input-group">
                                    <input class="form-control" type="number" step="0.01" min="0"  name="birth_weight">
                                    <div class="input-group-append">
                                        <span class="input-group-text">kg</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-sm-6">
                            <div class="form-group">
                                <label>Birth Height</label>
                                <div class="input-group">
                                    <input class="form-control" type="number" step="0.01" min="0" name="birth_height">
                                    <div class="input-group-append">
                                        <span class="input-group-text">cm</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-sm-6">
                            <div class="form-group">
                                <label>Physician</label>
                                <select class="form-control" name="physician" required>
                                    <option value="">Select Physician</option>
                                    <?php
                                    $physician_query = mysqli_query($connection, "SELECT id, first_name, last_name FROM tbl_employee WHERE role = 2 AND specialization = 'Cardiologist'");
                                    while ($physician_row = mysqli_fetch_assoc($physician_query)) {
                                        $physician_name = $physician_row['first_name'] . ' ' . $physician_row['last_name'];
                                        echo "<option value='$physician_name'>$physician_name</option>";
                                    }
                                    ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-sm-12">
                            <div class="form-group">
                                <label>Diagnosis</label>
                                <textarea class="form-control" name="diagnosis" rows="2" required></textarea>
                            </div>
                        </div>
                        <div class="col-12 mt-3 text-center">
                            <button class="btn btn-primary submit-btn" name="save-newborn">Save</button>
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
<style>
/* Optional: Style the input field */
.form-control {
    border-radius: .375rem; /* Rounded corners */
    border-color: #ced4da; /* Border color */
    background-color: #f8f9fa; /* Background color */
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
</style>
