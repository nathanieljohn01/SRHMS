<?php
session_start();
if(empty($_SESSION['name'])) {
    header('location:index.php');
}
include('header.php');
include('includes/connection.php');

// Fetch the maximum ID from tbl_hemodialysis to increment for new entry
$fetch_query = mysqli_query($connection, "SELECT MAX(id) as id FROM tbl_hemodialysis");
$row = mysqli_fetch_row($fetch_query);
if ($row[0] == 0) {
    $hpt_id = 1;
} else {
    $hpt_id = $row[0] + 1;
}

if (isset($_REQUEST['save-hemopatient'])) {
    $hemopatient_id = 'HPT-' . $hpt_id;
    $patient_name = $_REQUEST['patient_name'];
    $dialysis_report = $_REQUEST['dialysis_report'];
    $date_time = $_REQUEST['date_time'];


    // Query to get patient details based on name
    $fetch_query = mysqli_query($connection, "SELECT patient_id gender, dob FROM tbl_patient WHERE concat(first_name,' ',last_name) = '$patient_name'");
    $row = mysqli_fetch_array($fetch_query);
    $patient_id = $row['patient_id'];
    $gender = $row['gender'];
    $dob = $row['dob'];

    // Prepare the insert query
    $insert_query = mysqli_prepare($connection, "INSERT INTO tbl_hemodialysis (hemopatient_id, patient_id, patient_name, dob, gender, dialysis_report, date_time) VALUES (?, ?, ?, ?, ?, ?, ?)");
    
    // Bind the parameters for the prepared statement
    mysqli_stmt_bind_param($insert_query, "sssssss", $hemopatient_id, $patient_id, $patient_name, $dob, $gender, $dialysis_report, $date_time);
    
    // Execute the statement
    $result = mysqli_stmt_execute($insert_query);

    if ($result) {
        $msg = "Patient added successfully";
    } else {
        $msg = "Error: " . mysqli_error($connection);
    }

    mysqli_stmt_close($insert_query); // Close the prepared statement
}
?>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/datepicker/1.0.10/datepicker.min.css">
<link rel="stylesheet" type="text/css" href="assets/css/bootstrap-datetimepicker.css">
<link rel="stylesheet" type="text/css" href="assets/css/bootstrap-datetimepicker.min.css">
<div class="page-wrapper">
    <div class="content">
        <div class="row">
            <div class="col-sm-4">
                <h4 class="page-title">Add Patient</h4>
            </div>
            <div class="col-sm-8 text-right m-b-20">
                <a href="hemodialysis.php" class="btn btn-primary btn-rounded float-right"><i class="fa fa-arrow-left"></i> Back</a>
            </div>
        </div>
        <div class="row">
            <div class="col-lg-8 offset-lg-2">
                <form method="post">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Hemo-patient ID <span class="text-danger">*</span></label>
                                <input class="form-control" type="text" name="hemopatient_id" value="<?php
                                    if (!empty($hpt_id)) {
                                        echo 'HPT-' . $hpt_id;
                                    } else {
                                        echo "HPT-1";
                                    } ?>" disabled>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Patient Name</label>
                                <select class="select" name="patient_name" required>
                                    <option value="">Select</option>
                                    <?php
                                    $fetch_query = mysqli_query($connection, "SELECT concat(first_name,' ',last_name) as name, patient_type FROM tbl_patient WHERE patient_type = 'Hemodialysis' AND deleted = 0");
                                    while ($row = mysqli_fetch_array($fetch_query)) {
                                        ?>
                                        <option><?php echo $row['name']; ?></option>
                                    <?php } ?>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Date and Time</label>
                                <input type="datetime-local" class="form-control" name="date_time" id="date_time">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Dialysis Report</label>
                                <input type="text" class="form-control" name="dialysis_report">
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-12 text-center">
                            <button name="save-hemopatient" class="btn btn-primary submit-btn">Save</button>
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

<script src="assets/js/moment.min.js"></script>
<script src="assets/js/bootstrap-datetimepicker.js"></script>
<script src="assets/js/bootstrap-datetimepicker.min.js"></script>


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

    </style>