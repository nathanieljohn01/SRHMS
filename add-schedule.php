<?php
session_start();
if (empty($_SESSION['name'])) {
    header('location:index.php');
    exit();
}
include('header.php');
include('includes/connection.php');

// Function to sanitize inputs with XSS protection
function sanitize($connection, $input) {
    return mysqli_real_escape_string($connection, htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8'));
}

if (isset($_REQUEST['add-schedule'])) {
    // Sanitize inputs
    $doctor_name = sanitize($connection, $_REQUEST['doctor']);
    $days = sanitize($connection, implode(", ", $_REQUEST['days']));
    $start_time = sanitize($connection, $_REQUEST['start_time']);
    $end_time = sanitize($connection, $_REQUEST['end_time']);
    $message = sanitize($connection, $_REQUEST['message']);
    $status = sanitize($connection, $_REQUEST['status']);

    // Get doctor ID from tbl_employee
    $fetch_query = mysqli_prepare($connection, "SELECT specialization FROM tbl_employee WHERE concat(first_name, ' ', last_name) = ?");
    mysqli_stmt_bind_param($fetch_query, 's', $doctor_name);
    mysqli_stmt_execute($fetch_query);
    mysqli_stmt_bind_result($fetch_query, $specialization);
    mysqli_stmt_fetch($fetch_query);
    mysqli_stmt_close($fetch_query);

    // Insert into tbl_schedule
    $insert_schedule_query = mysqli_prepare($connection, "INSERT INTO tbl_schedule (doctor_name, specialization, available_days, start_time, end_time, message, status) VALUES (?, ?, ?, ?, ?, ?, ?)");
    mysqli_stmt_bind_param($insert_schedule_query, 'sssssss', $doctor_name, $specialization, $days, $start_time, $end_time, $message, $status);
    $insert_schedule_result = mysqli_stmt_execute($insert_schedule_query);
    mysqli_stmt_close($insert_schedule_query);

    // Update tbl_employee with schedule information
    $update_employee_query = mysqli_prepare($connection, "UPDATE tbl_employee SET available_days = ?, start_time = ?, end_time = ? WHERE role = 2");
    mysqli_stmt_bind_param($update_employee_query, 'sss', $days, $start_time, $end_time);
    $update_employee_result = mysqli_stmt_execute($update_employee_query);
    mysqli_stmt_close($update_employee_query);

    if ($insert_schedule_result && $update_employee_result) {
        $msg = "Schedule created successfully";
    } else {
        $msg = "Error!";
    }
}
?>

        <div class="page-wrapper">
            <div class="content">
                <div class="row">
                    <div class="col-sm-4 ">
                        <h4 class="page-title">Add Schedule</h4>
                         
                    </div>
                    <div class="col-sm-8  text-right m-b-20">
                        <a href="schedule.php" class="btn btn-primary btn-rounded float-right">Back</a>
                    </div>
                </div>
                <div class="row">
                    <div class="col-lg-8 offset-lg-2">
                        <form method="post">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label>Doctor Name</label>
                                        <select class="select" name="doctor" required>
                                            <option value="">Select</option>
                                            <?php
                                        $fetch_query = mysqli_query($connection, "select concat(first_name,' ',last_name) as name from tbl_employee where role=2 and status=1");
                                        while($row = mysqli_fetch_array($fetch_query)){
                                        ?>
                                            <option><?php echo $row['name']; ?></option>
                                            <?php } ?>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label>Available Days</label>
                                        <select class="select" multiple name="days[]" required>
                                            <option value="">Select Days</option>
                                            <option>Sunday</option>
                                            <option>Monday</option>
                                            <option>Tuesday</option>
                                            <option>Wednesday</option>
                                            <option>Thursday</option>
                                            <option>Friday</option>
                                            <option>Saturday</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label>Start Time</label>
                                        <div class="time-icon">
                                            <input type="text" class="form-control" id="datetimepicker3" name="start_time" required>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label>End Time</label>
                                        <div class="time-icon">
                                            <input type="text" class="form-control" id="datetimepicker4" name="end_time" required>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="form-group">
                                <label>Message</label>
                                <textarea cols="30" rows="4" class="form-control" name="message"></textarea>
                            </div>
                            <div class="form-group">
                                <label class="display-block">Schedule Status</label>
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="radio" name="status" id="product_active" value="1" checked>
                                    <label class="form-check-label" for="product_active">
                                    Available
                                    </label>
                                </div>
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="radio" name="status" id="product_inactive" value="0">
                                    <label class="form-check-label" for="product_inactive">
                                    Not Available
                                    </label>
                                </div>
                            </div>
                            <div class="m-t-20 text-center">
                                <button class="btn btn-primary submit-btn" name="add-schedule">Create Schedule</button>
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
.time-icon {
    position: relative;
}

.time-icon input {
    padding-right: 30px; /* Adjust the padding to make space for the icon */
}

.time-icon::after {
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
</style>