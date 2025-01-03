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

// Fetch outpatient data using prepared statements
$fetch_query = mysqli_prepare($connection, "SELECT * FROM tbl_outpatient WHERE id = ?");
mysqli_stmt_bind_param($fetch_query, "s", $id);
mysqli_stmt_execute($fetch_query);
$result = mysqli_stmt_get_result($fetch_query);
$row = mysqli_fetch_array($result);
mysqli_stmt_close($fetch_query);

// Handle form submission for updating outpatient record
if (isset($_REQUEST['save-outpatient'])) {
    // Sanitize user inputs
    $patient_name = sanitize($connection, $_POST['patient_name']);
    $dob = sanitize($connection, $_POST['dob']);
    $gender = sanitize($connection, $_POST['gender']);
    $diagnosis = sanitize($connection, $_POST['diagnosis']);

    // Fetch patient details (gender and dob) using sanitized patient name
    $fetch_query = mysqli_prepare($connection, "SELECT gender, dob FROM tbl_patient WHERE CONCAT(first_name, ' ', last_name) = ? AND deleted = 0");
    mysqli_stmt_bind_param($fetch_query, "s", $patient_name);
    mysqli_stmt_execute($fetch_query);
    $result = mysqli_stmt_get_result($fetch_query);
    $patient_row = mysqli_fetch_array($result);
    $gender = $patient_row['gender'];
    $dob = $patient_row['dob'];
    mysqli_stmt_close($fetch_query);

    // Update outpatient record using prepared statements
    $update_query = mysqli_prepare($connection, "UPDATE tbl_outpatient SET patient_name = ?, dob = ?, gender = ?, diagnosis = ? WHERE id = ?");
    mysqli_stmt_bind_param($update_query, 'ssssi', $patient_name, $dob, $gender, $diagnosis, $id);

    // Execute the update query
    if (mysqli_stmt_execute($update_query)) {
        $msg = "Outpatient updated successfully";
        // Re-fetch the updated outpatient record
        $fetch_query = mysqli_prepare($connection, "SELECT * FROM tbl_outpatient WHERE id = ?");
        mysqli_stmt_bind_param($fetch_query, "s", $id);
        mysqli_stmt_execute($fetch_query);
        $result = mysqli_stmt_get_result($fetch_query);
        $row = mysqli_fetch_array($result);
        mysqli_stmt_close($fetch_query);
    } else {
        $msg = "Error updating outpatient record";
    }

    // Close the update query
    mysqli_stmt_close($update_query);
}
?>

<div class="page-wrapper">
    <div class="content">
        <div class="row">
            <div class="col-sm-4">
                <h4 class="page-title">Edit Outpatient</h4>
            </div>
            <div class="col-sm-8 text-right m-b-20">
                <a href="outpatients.php" class="btn btn-primary btn-rounded float-right">Back</a>
            </div>
        </div>
        <div class="row">
            <div class="col-lg-8 offset-lg-2">
                <form method="post">
                    <div class="form-group">
                        <label>Outpatient ID <span class="text-danger">*</span></label>
                        <input class="form-control" type="text" name="outpatient_id" value="<?php echo $row['outpatient_id']; ?>" disabled>
                    </div>
                    <div class="form-group">
                        <label>Patient Name <span class="text-danger">*</span></label>
                        <input class="form-control" type="text" name="patient_name" value="<?php echo $row['patient_name']; ?>" disabled>
                    </div>
                        <div class="form-group">
                                <label>Diagnosis</label>
                                <textarea cols="30" rows="4" class="form-control" name="diagnosis"> <?php  echo $row['diagnosis'];  ?></textarea>
                        </div>        
                    <div class="m-t-20 text-center">
                        <button name="save-outpatient" class="btn btn-primary submit-btn">Save</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php
include('footer.php');
?>

<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
<script src="https://maxcdn.bootstrapcdn.com/bootstrap/4.0.0/js/bootstrap.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/moment.js/2.29.1/moment.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/tempusdominus-bootstrap-4/5.39.0/js/tempusdominus-bootstrap-4.min.js"></script>

<script type="text/javascript">
    <?php
    if (isset($msg)) {

        echo 'swal("' . $msg . '");';
    }
    ?>
</script>

<script type="text/javascript">
    $(function () {
        // Initialize DateTimePicker with date format
        $('#discharge_date').datetimepicker({
            format: 'YYYY-MM-DD',
        });
    });
</script>

<script type="text/javascript">
    $(function () {
        // Initialize DateTimePicker with date format
        $('#admission_date').datetimepicker({
            format: 'YYYY-MM-DD',
        });
    });
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