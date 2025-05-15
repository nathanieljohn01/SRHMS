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
    $fetch_query = mysqli_query($connection, "SELECT * FROM tbl_housekeeping_schedule WHERE id='$id' AND deleted = 0");
    $row = mysqli_fetch_assoc($fetch_query);

    if (!$row) {
        echo "Housekeeping schedule not found.";
        exit();
    }
    
    // Check if the bed is still marked for cleaning
    $bed_query = mysqli_query($connection, "SELECT status FROM tbl_bedallocation WHERE room_number='{$row['room_number']}' AND bed_number='{$row['bed_number']}'");
    $bed_row = mysqli_fetch_assoc($bed_query);
    $isEditable = ($bed_row && $bed_row['status'] === 'Available') ? true : false;
}

if (isset($_POST['update-housekeeping-schedule'])) {
    // Get the ID from the form
    $id = sanitize($connection, $_POST['id']);
    
    // Fetch the existing record to preserve the disabled field values
    $fetch_query = mysqli_query($connection, "SELECT * FROM tbl_housekeeping_schedule WHERE id='$id'");
    $existing_data = mysqli_fetch_assoc($fetch_query);
    
    // Use existing values for disabled fields and new value for task description
    $room_type = $existing_data['room_type'];
    $room_number = $existing_data['room_number'];
    $bed_number = $existing_data['bed_number'];
    $schedule_date_time = $existing_data['schedule_date_time'];
    $task_description = sanitize($connection, $_POST['task_description']);

    // Prepare the update query using a prepared statement
    $stmt = mysqli_prepare($connection, "UPDATE tbl_housekeeping_schedule SET task_description=? WHERE id=?");

    // Bind parameters
    mysqli_stmt_bind_param($stmt, 'si', $task_description, $id);

    // Execute the query and check if it was successful
    if (mysqli_stmt_execute($stmt)) {
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
                    window.location.href = 'housekeeping-schedule.php';
                });
            });
        </script>";
    } else {
        // SweetAlert error message
        echo "
        <script src='https://cdn.jsdelivr.net/npm/sweetalert2@10'></script>
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'Error updating schedule: " . mysqli_error($connection) . "',
                    confirmButtonColor: '#d33'
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
                        <input type="text" class="form-control" name="room_type" value="<?php echo $row['room_type']; ?>" readonly>
                    </div>
                    <div class="form-group">
                        <label>Room Number</label>
                        <input type="text" class="form-control" name="room_number" value="<?php echo $row['room_number']; ?>" readonly>
                    </div>
                    <div class="form-group">
                        <label>Bed Number</label>
                        <input type="text" class="form-control" name="bed_number" value="<?php echo $row['bed_number']; ?>" readonly>
                    </div>
                    <div class="form-group">
                        <label>Schedule Date and Time</label>
                        <input type="datetime-local" class="form-control" name="schedule_date_time" value="<?php echo date('Y-m-d\TH:i', strtotime($row['schedule_date_time'])); ?>" readonly>
                    </div>
                    <div class="form-group">
                        <label>Task Description</label>
                        <textarea class="form-control" name="task_description" rows="4" required><?php echo $row['task_description']; ?></textarea>
                    </div>
                    <div class="m-t-20 text-center">
                        <button class="btn btn-primary submit-btn" name="update-housekeeping-schedule"><i class="fas fa-save mr-2"></i>Update</button>             
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php include('footer.php'); ?>

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
.btn-success {
    background: #28a745;
    border: none;
    border-radius: 4px;
    padding: 10px 20px;
    font-size: 16px;
}
.btn-success:hover {
    background: #218838;
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
