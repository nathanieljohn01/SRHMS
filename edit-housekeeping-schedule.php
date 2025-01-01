<?php
session_start();
if(empty($_SESSION['name'])) {
    header('location:index.php');
}
include('header.php');
include('includes/connection.php');

$id = $_GET['id'];
$fetch_query = mysqli_query($connection, "SELECT * FROM tbl_housekeeping_schedule WHERE id='$id'");
$row = mysqli_fetch_array($fetch_query);
$schedDateTime = $row['schedule_date_time'];

if(isset($_POST['update-housekeeping-schedule'])) {
    $room_type = $_POST['room_type'];
    $room_number = $_POST['room_number'];
    $bed_number = $_POST['bed_number'];
    $attendant = $_POST['attendant'];
    $schedule_date_time = date('Y-m-d H:i:s', strtotime($_POST['schedule_date_time'])); // Updated to include time
    $task_description = $_POST['task_description'];
    
    $update_query = mysqli_query($connection, "UPDATE tbl_housekeeping_schedule SET room_type='$room_type', room_number='$room_number', bed_number='$bed_number', attendant='$attendant', schedule_date_time='$schedule_date_time', task_description='$task_description' WHERE id='$id'");
    
    if($update_query) {
        $msg = "Housekeeping schedule updated successfully";
    } else {
        $msg = "Error updating schedule!";
    }
}

$id = $_GET['id']; 
$fetch_query = mysqli_query($connection, "SELECT * FROM tbl_housekeeping_schedule WHERE id='$id'");
$row = mysqli_fetch_array($fetch_query);
?>

<div class="page-wrapper">
    <div class="content">
        <div class="row">
            <div class="col-sm-4">
                <h4 class="page-title">Edit Housekeeping Schedule</h4>
            </div>
            <div class="col-sm-8 text-right m-b-20">
                <a href="housekeeping-schedule.php" class="btn btn-primary btn-rounded float-right">Back</a>
            </div>
        </div>
        <div class="row">
            <div class="col-lg-8 offset-lg-2">
                <form method="post">
                    <input type="hidden" name="id" value="<?php echo $row['id']; ?>">
                    <div class="form-group">
                        <label>Room Type</label>
                        <select class="form-control" name="room_type">
                            <option value="">Select Room Type</option>
                            <?php
                            // Query to retrieve room types with status 'For cleaning'
                            $room_query = mysqli_query($connection, "SELECT DISTINCT room_type FROM tbl_bedallocation WHERE status='For cleaning'");
                            
                            // Display room types with status 'For cleaning' in the dropdown list
                            while($room_row = mysqli_fetch_assoc($room_query)) {
                                $selected = ($room_row['room_type'] == $row['room_type']) ? 'selected' : '';
                                echo '<option value="' . $room_row['room_type'] . '" ' . $selected . '>' . $room_row['room_type'] . '</option>';
                            }
                            ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Room Number</label>
                        <select class="form-control" name="room_number">
                            <option value="">Select Room Number</option>
                            <?php
                            // Query to retrieve room numbers with status 'For cleaning'
                            $room_query = mysqli_query($connection, "SELECT DISTINCT room_number FROM tbl_bedallocation WHERE status='For cleaning'");
                            
                            // Display room numbers with status 'For cleaning' in the dropdown list
                            while($room_row = mysqli_fetch_assoc($room_query)) {
                                $selected = ($room_row['room_number'] == $row['room_number']) ? 'selected' : '';
                                echo '<option value="' . $room_row['room_number'] . '" ' . $selected . '>' . $room_row['room_number'] . '</option>';
                            }
                            ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Bed Number</label>
                        <select class="form-control" name="bed_number">
                            <option value="">Select Bed Number</option>
                            <?php
                            // Query to retrieve bed numbers with status 'For cleaning'
                            $bed_query = mysqli_query($connection, "SELECT DISTINCT bed_number FROM tbl_bedallocation WHERE status='For cleaning'");
                            
                            // Display bed numbers with status 'For cleaning' in the dropdown list
                            while($bed_row = mysqli_fetch_assoc($bed_query)) {
                                $selected = ($bed_row['bed_number'] == $row['bed_number']) ? 'selected' : '';
                                echo '<option value="' . $bed_row['bed_number'] . '" ' . $selected . '>' . $bed_row['bed_number'] . '</option>';
                            }
                            ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Attendant</label>
                        <select class="form-control" name="attendant" required>
                            <?php
                        
                            $query = "SELECT * FROM tbl_employee WHERE role = 9";
                            $result = mysqli_query($connection, $query);


                            while ($row_employee = mysqli_fetch_assoc($result)) {
                                $selected = ($row['attendant'] == $row_employee['employee_id']) ? 'selected' : '';

                                echo '<option value="' . $row_employee['employee_id'] . '" ' . $selected . '>' . $row_employee['employee_name'] . '</option>';
                            }
                            ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Schedule Date and Time</label>
                        <div class="cal-icon">
                            <input type="text" class="form-control datetimepicker" name="schedule_date_time" value="<?php echo $schedDateTime; ?>" required>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Task Description</label>
                        <textarea class="form-control" name="task_description" rows="4" required><?php echo $row['task_description']; ?></textarea>
                    </div>
                    <div class="m-t-20 text-center">
                        <button class="btn btn-primary submit-btn" name="update-housekeeping-schedule">Update Housekeeping Schedule</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php
include('footer.php');
?>

<!-- Include jQuery -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

<!-- Include datetimepicker CSS -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/jquery-datetimepicker/2.5.20/jquery.datetimepicker.min.css">

<!-- Include datetimepicker JS -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery-datetimepicker/2.5.20/jquery.datetimepicker.full.min.js"></script>

<script type="text/javascript">
<?php
if(isset($msg)) {
    echo 'swal("' . $msg . '");';
}
?>
</script>

<script>
    $(document).ready(function(){
        $('.datetimepicker').datetimepicker({
            format: 'Y-m-d h:i A', // Date and time format with AM/PM
            step: 30, // Time step (in minutes)
            // You can add more options here as needed
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

