<?php
session_start();
if (empty($_SESSION['name'])) {
    header('location:index.php');
    exit();
}

include('header.php');
include('includes/connection.php');

// Function to sanitize inputs
function sanitize($connection, $input) {
    return mysqli_real_escape_string($connection, htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8'));
}

$id = sanitize($connection, $_GET['id']);

// Prepare the fetch statement
$fetch_query = mysqli_prepare($connection, "SELECT * FROM tbl_newborn WHERE id = ?");
mysqli_stmt_bind_param($fetch_query, "s", $id);
mysqli_stmt_execute($fetch_query);
$result = mysqli_stmt_get_result($fetch_query);
$row = mysqli_fetch_assoc($result);

$msg = ''; // Initialize the message variable

if (isset($_REQUEST['update-newborn'])) {
    // Sanitize the user inputs
    $first_name = sanitize($connection, $_REQUEST['first_name']);
    $last_name = sanitize($connection, $_REQUEST['last_name']);

    // Convert date of birth from DD/MM/YYYY to September 10, 2024 format
    $dob = DateTime::createFromFormat('d/m/Y', sanitize($connection, $_REQUEST['dob']))->format('F j, Y');

    // Convert time of birth
    $tob = date("g:i A", strtotime(sanitize($connection, $_REQUEST['tob'])));
    $gender = sanitize($connection, $_REQUEST['gender']);
    $birth_weight = sanitize($connection, $_REQUEST['birth_weight']);
    $birth_height = sanitize($connection, $_REQUEST['birth_height']);
    $gestational_age = sanitize($connection, $_REQUEST['gestational_age']);
    $physician = sanitize($connection, $_REQUEST['physician']);

    // Prepare the update statement
    $update_query = mysqli_prepare($connection, "UPDATE tbl_newborn SET first_name = ?, last_name = ?, dob = ?, tob = ?, gender = ?, birth_weight = ?, birth_height = ?, gestational_age = ?, physician = ? WHERE id = ?");
    mysqli_stmt_bind_param($update_query, "ssssssssss", $first_name, $last_name, $dob, $tob, $gender, $birth_weight, $birth_height, $gestational_age, $physician, $id);

    // Execute the update query and check if it was successful
    if (mysqli_stmt_execute($update_query)) {
        $msg = "Newborn details updated successfully";

        // SweetAlert success message
        echo "
        <script src='https://cdn.jsdelivr.net/npm/sweetalert2@10'></script>
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                Swal.fire({
                    icon: 'success',
                    title: 'Success',
                    text: 'Newborn details updated successfully.',
                    confirmButtonColor: '#12369e'
                }).then(() => {
                    // Optional: Redirect after success
                    window.location.href = 'newborn.php'; // Adjust URL to your relevant page
                });
            });
        </script>";
    } else {
        $msg = "Error updating details: " . mysqli_error($connection);

        // SweetAlert error message
        echo "
        <script src='https://cdn.jsdelivr.net/npm/sweetalert2@10'></script>
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'Error updating details: " . mysqli_error($connection) . "',
                });
            });
        </script>";
    }

    // Close the update statement
    mysqli_stmt_close($update_query);

}

// Close the fetch statement
mysqli_stmt_close($fetch_query);
?>

<div class="page-wrapper">
    <div class="content">
        <div class="row">
            <div class="col-sm-4">
                <h4 class="page-title">Edit Newborn</h4>
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
                                <input class="form-control" type="text" name="newborn_id" value="<?php echo htmlspecialchars($row['id']); ?>" disabled>
                            </div>
                        </div>
                        <div class="col-sm-6">
                            <div class="form-group">
                                <label>First Name</label>
                                <input class="form-control" type="text" name="first_name" value="<?php echo htmlspecialchars($row['first_name']); ?>" required>
                            </div>
                        </div>
                        <div class="col-sm-6">
                            <div class="form-group">
                                <label>Last Name</label>
                                <input class="form-control" type="text" name="last_name" value="<?php echo htmlspecialchars($row['last_name']); ?>" required>
                            </div>
                        </div>
                        <div class="col-sm-6">
                            <div class="form-group">
                                <label>Date of Birth</label>
                                <div class="cal-icon">
                                    <input type="text" class="form-control datetimepicker" name="dob" value="<?php echo htmlspecialchars($row['dob']); ?>" required>
                                </div>
                            </div>
                        </div>
                        <div class="col-sm-6">
                            <div class="form-group position-relative">
                                <label for="tob">Time of Birth</label>
                                <input type="time" id="tob" class="form-control" name="tob" value="<?php echo date('H:i', strtotime($row['tob'])); ?>" required>
                            </div>
                        </div>
                        <div class="col-sm-6">
                            <div class="form-group">
                                <label>Gender</label>
                                <div class="form-check">
                                    <input type="radio" name="gender" class="form-check-input" value="Male" <?php echo ($row['gender'] == 'Male') ? 'checked' : ''; ?>>
                                    <label class="form-check-label">Male</label>
                                </div>
                                <div class="form-check">
                                    <input type="radio" name="gender" class="form-check-input" value="Female" <?php echo ($row['gender'] == 'Female') ? 'checked' : ''; ?>>
                                    <label class="form-check-label">Female</label>
                                </div>
                            </div>
                        </div>
                        <div class="col-sm-6">
                            <div class="form-group">
                                <label>Birth Weight</label>
                                <div class="input-group">
                                    <input class="form-control" type="text" name="birth_weight" value="<?php echo htmlspecialchars($row['birth_weight']); ?>">
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
                                    <input class="form-control" type="text" name="birth_height" value="<?php echo htmlspecialchars($row['birth_height']); ?>">
                                    <div class="input-group-append">
                                        <span class="input-group-text">ft</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-sm-6">
                            <div class="form-group">
                                <label>Gestational Age (weeks)</label>
                                <input class="form-control" type="text" name="gestational_age" value="<?php echo htmlspecialchars($row['gestational_age']); ?>" required>
                            </div>
                        </div>
                        <div class="col-sm-6">
                            <div class="form-group">
                                <label>Physician</label>
                                <input class="form-control" type="text" name="physician" value="<?php echo htmlspecialchars($row['physician']); ?>" required>
                            </div>
                        </div>
                        <div class="col-12 mt-3 text-center">
                            <button class="btn btn-primary submit-btn" name="update-newborn">Update</button>
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
    <?php
    if(isset($msg)) {
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
/* Optional: Style the input field */
.form-control {
    border-radius: .375rem; /* Rounded corners */
    border-color: #ced4da; /* Border color */
    background-color: #f8f9fa; /* Background color */
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
