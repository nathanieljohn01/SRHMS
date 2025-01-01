<?php
session_start();
if(empty($_SESSION['name'])) {
    header('location:index.php');
}
include('header.php');
include('includes/connection.php');

if (isset($_REQUEST['save-inpatient'])) {
    $patient_name = $_REQUEST['patient_name'];
    $doctor_incharge = $_REQUEST['doctor_incharge'];
    $treatment = $_REQUEST['treatment'];

    // Check if tbl_inpatient has any records
    $check_query = "SELECT COUNT(*) FROM tbl_inpatient WHERE deleted = 0";
    $check_result = mysqli_query($connection, $check_query);
    $check_row = mysqli_fetch_array($check_result);
    
    // If there are no records in tbl_inpatient
    if ($check_row[0] == 0) {
        $msg = "No records available in tbl_inpatient.";
    } else {
        // Prepare the query to fetch inpatient details
        $query = "SELECT inpatient_id, dob, gender, room_type, room_number, bed_number, admission_date FROM tbl_inpatient WHERE patient_name = ? AND deleted = 0";
        $fetch_query = mysqli_prepare($connection, $query);
        mysqli_stmt_bind_param($fetch_query, 's', $patient_name);  // 's' for string
        mysqli_stmt_execute($fetch_query);
        $result = mysqli_stmt_get_result($fetch_query);
        $row = mysqli_fetch_array($result);

        if ($row) {
            $inpatient_id = $row['inpatient_id'];
            $gender = $row['gender'];
            $dob = $row['dob'];
            $room_type = $row['room_type'];
            $room_number = $row['room_number'];
            $bed_number = $row['bed_number'];
            $admission_date = $row['admission_date'];

            // Prepare the insert query
            $insert_query = mysqli_prepare($connection, "INSERT INTO tbl_inpatient_record (inpatient_id, patient_name, dob, gender, room_type, room_number, bed_number, admission_date, doctor_incharge, treatment) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            mysqli_stmt_bind_param($insert_query, 'ssssssssss', $inpatient_id, $patient_name, $dob, $gender, $room_type, $room_number, $bed_number, $admission_date, $doctor_incharge, $treatment);  // 's' for all string parameters

            // Execute the insert query
            if (mysqli_stmt_execute($insert_query)) {
                $msg = "Inpatient saved successfully";
            } else {
                $msg = "Error: " . mysqli_error($connection);
            }

            // Close the prepared statements
            mysqli_stmt_close($insert_query);
        } else {
            $msg = "Patient not found or already deleted.";
        }

        // Close the fetch query statement
        mysqli_stmt_close($fetch_query);
    }
}
?>

<div class="page-wrapper">
    <div class="content">
        <div class="row">
            <div class="col-sm-4">
                <h4 class="page-title">Add Inpatient</h4>
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
                                <select class="form-control" name="patient_name" required>
                                    <option value="">Select</option>
                                    <?php
                                    // Query para sa mga pasyente sa tbl_inpatient
                                    $fetch_query = mysqli_query($connection, "SELECT patient_name FROM tbl_inpatient WHERE discharge_date IS NULL AND deleted = 0");
                                    while ($row = mysqli_fetch_array($fetch_query)) {
                                        ?>
                                        <option><?php echo $row['patient_name']; ?></option>
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
                                    $fetch_query = mysqli_query($connection, "SELECT CONCAT(first_name, ' ', last_name) as name FROM tbl_employee WHERE role = 2 ");
                                    while ($row = mysqli_fetch_array($fetch_query)) {
                                        ?>
                                        <option><?php echo $row['name']; ?></option>
                                    <?php } ?>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-12">
                            <div class="form-group">
                                <label>Treatment / Medications</label>
                                <textarea class="form-control" name="treatment" rows="3" placeholder="Enter treatment or medications"></textarea>
                            </div>
                        </div>
                    </div>
                    <div class="m-t-20 text-center">
                        <button name="save-inpatient" class="btn btn-primary submit-btn">Save</button>
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
</style>