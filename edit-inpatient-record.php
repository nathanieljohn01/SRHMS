<?php
session_start();
if (empty($_SESSION['name'])) {
    header('location:index.php');
}
include('header.php');
include('includes/connection.php');

$id = $_GET['id'];
$fetch_query = mysqli_query($connection, "SELECT * FROM tbl_inpatient_record WHERE id='$id'");
$row = mysqli_fetch_array($fetch_query);

$msg = ''; // Initialize $msg variable

if (isset($_POST['update-inpatient'])) {
    $patient_name = $_POST['patient_name'];
    $doctor_incharge = $_POST['doctor_incharge'];
    $treatment = $_POST['treatment'];

    // Use prepared statement to prevent SQL injection
    $stmt = mysqli_prepare($connection, "UPDATE tbl_inpatient_record SET patient_name = ?, doctor_incharge = ?, treatment = ? WHERE id = ?");
    
    // Bind the parameters
    mysqli_stmt_bind_param($stmt, 'sssi', $patient_name, $doctor_incharge, $treatment, $id);
    
    // Execute the query
    if (mysqli_stmt_execute($stmt)) {
        $msg = "Inpatient record updated successfully";
    } else {
        $msg = "Error updating inpatient record: " . mysqli_error($connection);
    }

    // Close the statement
    mysqli_stmt_close($stmt);
}
?>


<div class="page-wrapper">
    <div class="content">
        <div class="row">
            <div class="col-sm-4">
                <h4 class="page-title">Edit Inpatient Record</h4>
            </div>
            <div class="col-sm-8 text-right m-b-20">
                <a href="inpatient-record.php" class="btn btn-primary btn-rounded float-right">Back</a>
            </div>
        </div>
        <div class="row">
            <div class="col-lg-8 offset-lg-2">
                <form method="post">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Patient Name</label>
                                <input type="text" class="form-control" name="patient_name" value="<?php echo $row['patient_name']; ?>" readonly>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Doctor In Charge</label>
                                <select class="form-control" name="doctor_incharge" required>
                                    <option value="">Select Doctor</option>
                                    <?php
                                    $doctor_query = mysqli_query($connection, "SELECT CONCAT(first_name, ' ', last_name) as name FROM tbl_employee WHERE role = 2");
                                    while ($doctor_row = mysqli_fetch_array($doctor_query)) {
                                        $selected = ($row['doctor_incharge'] == $doctor_row['name']) ? 'selected' : '';
                                        ?>
                                        <option <?php echo $selected; ?>><?php echo $doctor_row['name']; ?></option>
                                    <?php } ?>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-12">
                            <div class="form-group">
                                <label>Treatment / Medications</label>
                                <textarea class="form-control" name="treatment" rows="3"><?php echo $row['treatment']; ?></textarea>
                            </div>
                        </div>
                    </div>
                    <div class="m-t-20 text-center">
                        <button name="update-inpatient" class="btn btn-primary submit-btn">Update</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>


<?php include('footer.php'); ?>

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