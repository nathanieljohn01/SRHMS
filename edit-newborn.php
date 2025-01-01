<?php
session_start();
if (empty($_SESSION['name'])) {
    header('location:index.php');
}
include('header.php');
include('includes/connection.php');

$id = $_GET['id'];
// Prepare the fetch statement
$fetch_query = mysqli_prepare($connection, "SELECT * FROM tbl_newborn WHERE id = ?");
mysqli_stmt_bind_param($fetch_query, "s", $id);
mysqli_stmt_execute($fetch_query);
$result = mysqli_stmt_get_result($fetch_query);
$row = mysqli_fetch_assoc($result);

if (isset($_REQUEST['update-newborn'])) {
    $first_name = $_REQUEST['first_name'];
    $last_name = $_REQUEST['last_name'];

    // Convert date of birth from DD/MM/YYYY to September 10, 2024
    $dob = DateTime::createFromFormat('d/m/Y', $_REQUEST['dob'])->format('F j, Y');

    // Convert time of birth
    $tob = date("g:i A", strtotime($_REQUEST['tob']));
    $gender = $_REQUEST['gender'];
    $birth_weight = $_REQUEST['birth_weight'];
    $birth_height = $_REQUEST['birth_height'];
    $gestational_age = $_REQUEST['gestational_age'];
    $physician = $_REQUEST['physician'];

    // Prepare the update statement
    $update_query = mysqli_prepare($connection, "UPDATE tbl_newborn SET first_name = ?, last_name = ?, dob = ?, tob = ?, gender = ?, birth_weight = ?, birth_height = ?, gestational_age = ?, physician = ? WHERE id = ?");
    mysqli_stmt_bind_param($update_query, "ssssssssss", $first_name, $last_name, $dob, $tob, $gender, $birth_weight, $birth_height, $gestational_age, $physician, $id);

    if (mysqli_stmt_execute($update_query)) {
        $msg = "Newborn details updated successfully";
    } else {
        $msg = "Error updating details";
    }

    mysqli_stmt_close($update_query);
}

mysqli_stmt_close($fetch_query);
?>


<div class="page-wrapper">
    <div class="content">
        <div class="row">
            <div class="col-sm-4">
                <h4 class="page-title">Edit Newborn</h4>
            </div>
            <div class="col-sm-8 text-right mb-3">
                <a href="newborn.php" class="btn btn-primary btn-rounded float-right">Back</a>
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
