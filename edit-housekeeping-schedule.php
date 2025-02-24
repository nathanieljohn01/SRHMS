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

if (isset($_GET['id'])) {
    $id = sanitize($connection, $_GET['id']);

    // Fetch existing housekeeping schedule data
    $fetch_query = mysqli_query($connection, "SELECT * FROM tbl_housekeeping_schedule WHERE id='$id'");
    $row = mysqli_fetch_assoc($fetch_query);

    if (!$row) {
        echo "Housekeeping schedule not found.";
        exit();
    }
}

if (isset($_POST['update-housekeeping-schedule'])) {
    // Sanitize user inputs
    $room_type = sanitize($connection, $_POST['room_type']);
    $room_number = sanitize($connection, $_POST['room_number']);
    $bed_number = sanitize($connection, $_POST['bed_number']);
    $schedule_date_time = sanitize($connection, $_POST['schedule_date_time']); // datetime-local already provides formatted value
    $task_description = sanitize($connection, $_POST['task_description']);

    // Prepare the update query using a prepared statement
    $stmt = mysqli_prepare($connection, "UPDATE tbl_housekeeping_schedule SET room_type=?, room_number=?, bed_number=?, schedule_date_time=?, task_description=? WHERE id=?");

    // Bind parameters
    mysqli_stmt_bind_param($stmt, 'sssssi', $room_type, $room_number, $bed_number, $schedule_date_time, $task_description, $id);

    // Execute the query and check if it was successful
    if (mysqli_stmt_execute($stmt)) {
        $msg = "Housekeeping schedule updated successfully.";

        // SweetAlert success message
        echo "
        <script src='https://cdn.jsdelivr.net/npm/sweetalert2@10'></script>
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                Swal.fire({
                    icon: 'success',
                    title: 'Success',
                    text: 'Housekeeping schedule updated successfully.',
                    confirmButtonColor: '#12369e'
                }).then(() => {
                    // Optional: Redirect after success, adjust the URL as necessary
                    window.location.href = 'housekeeping-schedule.php'; // Change to your relevant page
                });
            });
        </script>";
    } else {
        $msg = "Error updating schedule: " . mysqli_error($connection);

        // SweetAlert error message
        echo "
        <script src='https://cdn.jsdelivr.net/npm/sweetalert2@10'></script>
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'Error updating schedule: " . mysqli_error($connection) . "',
                });
            });
        </script>";
    }

    mysqli_stmt_close($stmt);

}
?>

<div class="page-wrapper">
    <div class="content">
        <div class="row">
            <div class="col-sm-4">
                <h4 class="page-title">Edit Housekeeping Schedule</h4>
            </div>
            <div class="col-sm-8 text-right m-b-20">
                <a href="housekeeping-schedule.php" class="btn btn-primary float-right"><i class="fa fa-arrow-left"></i> Back</a>
            </div>
        </div>
        <div class="row">
            <div class="col-lg-8 offset-lg-2">
                <form method="post">
                    <input type="hidden" name="id" value="<?php echo $row['id']; ?>">
                    <div class="form-group">
                        <label>Room Type</label>
                        <select class="form-control" name="room_type" disabled>
                            <option value="">Select Room Type</option>
                            <?php
                            $room_query = mysqli_query($connection, "SELECT DISTINCT room_type FROM tbl_bedallocation WHERE status='For cleaning'");
                            while ($room_row = mysqli_fetch_assoc($room_query)) {
                                $selected = ($room_row['room_type'] == $row['room_type']) ? 'selected' : '';
                                echo "<option value='{$room_row['room_type']}' $selected>{$room_row['room_type']}</option>";
                            }
                            ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Room Number</label>
                        <select class="form-control" name="room_number" disabled>
                            <option value="">Select Room Number</option>
                            <?php
                            $room_query = mysqli_query($connection, "SELECT DISTINCT room_number FROM tbl_bedallocation WHERE status='For cleaning'");
                            while ($room_row = mysqli_fetch_assoc($room_query)) {
                                $selected = ($room_row['room_number'] == $row['room_number']) ? 'selected' : '';
                                echo "<option value='{$room_row['room_number']}' $selected>{$room_row['room_number']}</option>";
                            }
                            ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Bed Number</label>
                        <select class="form-control" name="bed_number" disabled>
                            <option value="">Select Bed Number</option>
                            <?php
                            $bed_query = mysqli_query($connection, "SELECT DISTINCT bed_number FROM tbl_bedallocation WHERE status='For cleaning'");
                            while ($bed_row = mysqli_fetch_assoc($bed_query)) {
                                $selected = ($bed_row['bed_number'] == $row['bed_number']) ? 'selected' : '';
                                echo "<option value='{$bed_row['bed_number']}' $selected>{$bed_row['bed_number']}</option>";
                            }
                            ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Schedule Date and Time</label>
                        <input type="datetime-local" class="form-control" name="schedule_date_time" value="<?php echo date('Y-m-d\TH:i', strtotime($row['schedule_date_time'])); ?>" disabled>
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
