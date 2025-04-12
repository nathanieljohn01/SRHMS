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

// Fetch existing inpatient record data
$fetch_query = mysqli_query($connection, "SELECT * FROM tbl_inpatient_record WHERE id='$id'");
$row = mysqli_fetch_array($fetch_query);

$msg = ''; // Initialize message variable

if (isset($_POST['update-inpatient'])) {
    // Sanitize user inputs
    $patient_name = sanitize($connection, $_POST['patient_name']);
    $doctor_incharge = sanitize($connection, $_POST['doctor_incharge']);
    $treatment = sanitize($connection, $_POST['treatment']);

    // Prepare the update query using a prepared statement
    $stmt = mysqli_prepare($connection, "UPDATE tbl_inpatient_record SET patient_name = ?, doctor_incharge = ?, treatment = ? WHERE id = ?");

    // Bind parameters
    mysqli_stmt_bind_param($stmt, 'sssi', $patient_name, $doctor_incharge, $treatment, $id);

    // Execute the query and check if it was successful
    if (mysqli_stmt_execute($stmt)) {
        $msg = "Inpatient record updated successfully";

        // SweetAlert success message
        echo "
        <script src='https://cdn.jsdelivr.net/npm/sweetalert2@10'></script>
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                Swal.fire({
                    icon: 'success',
                    title: 'Success',
                    text: 'Inpatient record updated successfully.',
                    confirmButtonColor: '#12369e'
                }).then(() => {
                    // Optional: Redirect after success, adjust the URL as necessary
                    window.location.href = 'inpatient-record-list.php'; // Change to your relevant page
                });
            });
        </script>";
    } else {
        $msg = "Error updating inpatient record: " . mysqli_error($connection);

        // SweetAlert error message
        echo "
        <script src='https://cdn.jsdelivr.net/npm/sweetalert2@10'></script>
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'Error updating inpatient record: " . mysqli_error($connection) . "',
                });
            });
        </script>";
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
                <a href="inpatient-record.php" class="btn btn-primary float-right"><i class="fa fa-arrow-left"></i> Back</a>
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
                                <select class="form-control" name="doctor_incharge" disabled>
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
                        <button name="update-inpatient" class="btn btn-primary submit-btn"><i class="fas fa-save mr-2"></i>Update</button>
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