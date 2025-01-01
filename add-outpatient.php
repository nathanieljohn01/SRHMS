<?php
session_start();
if(empty($_SESSION['name'])) {
    header('location:index.php');
    exit();
}
include('header.php');
include('includes/connection.php');

// Get the next outpatient ID
$fetch_query = mysqli_query($connection, "SELECT MAX(id) as id FROM tbl_outpatient");
$row = mysqli_fetch_row($fetch_query);
$opt_id = $row[0] == 0 ? 1 : $row[0] + 1;

// Handle form submission
if(isset($_REQUEST['save-outpatient'])) {
    $outpatient_id = 'OPT-' . $opt_id;
    $patient_name = $_REQUEST['patient_name'];
    $doctor_incharge = $_REQUEST['doctor_incharge'];

    // Fetch patient details securely using prepared statements
    $stmt = mysqli_prepare($connection, "SELECT gender, dob FROM tbl_patient WHERE concat(first_name, ' ', last_name) = ? AND deleted = 0");
    mysqli_stmt_bind_param($stmt, 's', $patient_name);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_array($result);
    $gender = $row['gender'];
    $dob = $row['dob'];
    
    // Insert outpatient record securely
    $stmt_insert = mysqli_prepare($connection, "INSERT INTO tbl_outpatient (outpatient_id, patient_name, doctor_incharge, dob, gender) VALUES (?, ?, ?, ?, ?)");
    mysqli_stmt_bind_param($stmt_insert, 'sssss', $outpatient_id, $patient_name, $doctor_incharge, $dob, $gender);
    
    if(mysqli_stmt_execute($stmt_insert)) {
        $msg = "Outpatient added successfully";
    } else {
        $msg = "Error!";
    }

    // Close statements
    mysqli_stmt_close($stmt);
    mysqli_stmt_close($stmt_insert);
}
?>

<div class="page-wrapper">
    <div class="content">
        <div class="row">
            <div class="col-sm-4">
                <h4 class="page-title">Add Outpatient</h4>
            </div>
            <div class="col-sm-8 text-right m-b-20">
                <a href="outpatients.php" class="btn btn-primary btn-rounded float-right">Back</a>
            </div>
        </div>
        <div class="row">
            <div class="col-lg-8 offset-lg-2">
                <form method="post">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Outpatient ID <span class="text-danger">*</span></label>
                                <input class="form-control" type="text" name="outpatient_id" value="<?php echo 'OPT-' . $opt_id; ?>" disabled>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Patient Name</label>
                                <select class="form-control" name="patient_name" required>
                                    <option value="">Select</option>
                                    <?php
                                    $fetch_query = mysqli_query($connection, "SELECT concat(first_name, ' ', last_name) as name FROM tbl_patient WHERE patient_type = 'Outpatient' AND deleted = 0");
                                    while ($row = mysqli_fetch_array($fetch_query)) {
                                        ?>
                                        <option><?php echo $row['name']; ?></option>
                                    <?php } ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Doctor In Charge</label>
                                <select class="form-control" name="doctor_incharge" required>
                                    <option value="">Select</option>
                                    <?php
                                    $fetch_query = mysqli_query($connection, "SELECT CONCAT(first_name, ' ', last_name) as name FROM tbl_employee WHERE role = 2");
                                    while ($row = mysqli_fetch_array($fetch_query)) {
                                        ?>
                                        <option><?php echo $row['name']; ?></option>
                                    <?php } ?>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="m-t-20 text-center">
                        <button class="btn btn-primary submit-btn" name="save-outpatient">Save</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php 
include('footer.php');
?>

<script type="text/javascript">
    <?php
    if(isset($msg)) {
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
