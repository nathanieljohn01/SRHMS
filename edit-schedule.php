<?php 
session_start();
if (empty($_SESSION['name'])) {
    header('location:index.php');
    exit();
}
include('header.php');
include('includes/connection.php');

// Function to sanitize user inputs
function sanitize($connection, $input) {
    return mysqli_real_escape_string($connection, htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8'));
}

// Sanitize the `id` parameter from the URL
$id = sanitize($connection, $_GET['id']);
$fetch_query = mysqli_prepare($connection, "SELECT * FROM tbl_schedule WHERE id = ?");
mysqli_stmt_bind_param($fetch_query, 'i', $id);
mysqli_stmt_execute($fetch_query);
$result = mysqli_stmt_get_result($fetch_query);
$row = mysqli_fetch_array($result);
mysqli_stmt_close($fetch_query);

if (isset($_POST['save-schedule'])) {
    // Sanitize form inputs
    $doctor_name = sanitize($connection, $_POST['doctor_name']);
    $days = sanitize($connection, implode(", ", $_POST['days']));
    $start_time = sanitize($connection, $_POST['start_time']);
    $end_time = sanitize($connection, $_POST['end_time']);
    $message = sanitize($connection, $_POST['msg']);
    $status = sanitize($connection, $_POST['status']);

    // Retrieve specialization based on the doctor's name
    $stmt = mysqli_prepare($connection, "SELECT specialization FROM tbl_employee WHERE CONCAT(first_name, ' ', last_name) = ?");
    mysqli_stmt_bind_param($stmt, 's', $doctor_name);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $row_doctor = mysqli_fetch_array($result);
    $specialization = $row_doctor['specialization'];
    mysqli_stmt_close($stmt);

    // Update tbl_schedule with prepared statement
    $update_query = mysqli_prepare($connection, "UPDATE tbl_schedule SET doctor_name = ?, specialization = ?, available_days = ?, start_time = ?, end_time = ?, message = ?, status = ? WHERE id = ?");
    mysqli_stmt_bind_param($update_query, 'sssssssi', $doctor_name, $specialization, $days, $start_time, $end_time, $message, $status, $id);

    // Update tbl_employee with available days, start_time, and end_time for role 2 (doctor)
    $update_employee_query = mysqli_prepare($connection, "UPDATE tbl_employee SET available_days = ?, start_time = ?, end_time = ? WHERE role = 2");
    mysqli_stmt_bind_param($update_employee_query, 'sss', $days, $start_time, $end_time);

    // Execute the queries
    $update_result = mysqli_stmt_execute($update_query);
    $update_employee_result = mysqli_stmt_execute($update_employee_query);

    if ($update_result && $update_employee_result) {
        $msg = "Schedule updated successfully";
        // Refetch the updated schedule data
        $fetch_query = mysqli_prepare($connection, "SELECT * FROM tbl_schedule WHERE id = ?");
        mysqli_stmt_bind_param($fetch_query, 'i', $id);
        mysqli_stmt_execute($fetch_query);
        $result = mysqli_stmt_get_result($fetch_query);
        $row = mysqli_fetch_array($result);
        mysqli_stmt_close($fetch_query);   
    } else {
        $msg = "Error!";
    }

    // Close the statements
    mysqli_stmt_close($update_query);
    mysqli_stmt_close($update_employee_query);
}
?>

<div class="page-wrapper">
    <div class="content">
        <div class="row">
            <div class="col-sm-4 ">
                <h4 class="page-title">Edit Schedule</h4>
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
                                <select class="select" name="doctor_name" disabled>
                                    <?php
                                $fetch_query = mysqli_query($connection, "SELECT * FROM tbl_schedule WHERE id='$id'");
                                $schedule= mysqli_fetch_array($fetch_query);

                                $fetch_query = mysqli_query($connection, "SELECT first_name, last_name FROM tbl_employee WHERE status=1 AND role=2");
                                while($doc = mysqli_fetch_array($fetch_query)){
                                ?>
                                    <option <?php if($doc['first_name'] . ' ' . $doc['last_name'] == $schedule['doctor_name']){ ?> selected="selected"; <?php } ?>><?php echo $doc['first_name'] . ' ' . $doc['last_name']; ?></option>
                                    <?php } ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Available Days</label>
                                <select class="select" multiple name="days[]" required>
                                    <option value="">Select Days</option>
                                    <?php
                                
                                $days = explode(", ", $row["available_days"]);
                                $fetch_query = mysqli_query($connection, "SELECT * FROM tbl_week");
                                while ($rows = mysqli_fetch_array($fetch_query))
                                 {
                                if (in_array($rows["name"], $days))
                                $selected = "selected";
                                else
                                $selected = "";
                                ?>
                                    <option value="<?=$rows["name"];?>" <?php echo $selected; ?>><?=$rows["name"];?>
                                    </option>
                                    <?php 
                                    }
                                    ?>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Start Time</label>
                                <div class="time-icon">
                                    <input type="text" class="form-control" id="datetimepicker3" name="start_time" value="<?php  echo $row['start_time'];  ?>" required>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>End Time</label>
                                <div class="time-icon">
                                    <input type="text" class="form-control" id="datetimepicker4" name="end_time" value="<?php  echo $row['end_time'];  ?>" required>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Message</label>
                        <textarea cols="30" rows="4" class="form-control" name="msg"><?php echo $row['message'];  ?></textarea>
                    </div>
                    <div class="form-group">
                        <label class="display-block">Schedule Status</label>
                        <div class="form-check form-check-inline">
                            <input class="form-check-input" type="radio" name="status" id="product_active" value="1" <?php if($row['status']==1) { echo 'checked' ; } ?>>
                            <label class="form-check-label" for="product_active">
                            Available
                            </label>
                        </div>
                        <div class="form-check form-check-inline">
                            <input class="form-check-input" type="radio" name="status" id="product_inactive" value="0" <?php if($row['status']==0) { echo 'checked' ; } ?>>
                            <label class="form-check-label" for="product_inactive">
                            Not Available
                            </label>
                        </div>
                    </div>
                    <div class="m-t-20 text-center">
                        <button class="btn btn-primary submit-btn" name="save-schedule">Save</button>
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