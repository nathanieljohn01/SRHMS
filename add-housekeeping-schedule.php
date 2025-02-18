<?php
session_start();
if (empty($_SESSION['name'])) {
    header('location:index.php');
    exit;
}
include('header.php');
include('includes/connection.php');

// Function to sanitize user inputs
function sanitize($connection, $input) {
    return mysqli_real_escape_string($connection, trim($input));
}

if (isset($_REQUEST['add-housekeeping-schedule'])) {
    // Sanitize inputs
    $room_type = sanitize($connection, $_REQUEST['room_type']);
    $room_number = sanitize($connection, $_REQUEST['room_number']);
    $bed_number = sanitize($connection, $_REQUEST['bed_number']);
    $schedule_date_time = sanitize($connection, $_REQUEST['schedule_date_time']);
    $task_description = sanitize($connection, $_REQUEST['task_description']);

    // Prepare the insert query
    $insert_query = $connection->prepare("INSERT INTO tbl_housekeeping_schedule (room_type, room_number, bed_number, schedule_date_time, task_description) VALUES (?, ?, ?, ?, ?)");

    // Bind the parameters for the prepared statement
    $insert_query->bind_param("sssss", $room_type, $room_number, $bed_number, $schedule_date_time, $task_description);

    // Execute the statement
    if ($insert_query->execute()) {
        $msg = "Housekeeping schedule added successfully";
        // SweetAlert success message
        echo "
        <script src='https://cdn.jsdelivr.net/npm/sweetalert2@10'></script>
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                var style = document.createElement('style');
                style.innerHTML = '.swal2-confirm { background-color: #12369e !important; color: white !important; border: none !important; } .swal2-confirm:hover { background-color: #05007E !important; } .swal2-confirm:focus { box-shadow: 0 0 0 0.2rem rgba(18, 54, 158, 0.5) !important; }';
                document.head.appendChild(style);

                Swal.fire({
                    icon: 'success',
                    title: 'Success!',
                    text: '$msg',
                    confirmButtonColor: '#12369e'
                }).then(() => {
                    window.location.href = 'housekeeping-schedule.php'; // Adjust the redirection URL as needed
                });
            });
        </script>";
    } else {
        $msg = "Error: " . $connection->error;
        // SweetAlert error message
        echo "
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: '$msg'
                });
            });
        </script>";
    }

    // Close the prepared statement
    $insert_query->close();
}
?>

<div class="page-wrapper">
    <div class="content">
        <div class="row">
            <div class="col-sm-4">
                <h4 class="page-title">Add Housekeeping Schedule</h4>
            </div>
            <div class="col-sm-8 text-right m-b-20">
                <a href="housekeeping-schedule.php" class="btn btn-primary float-right">Back</a>
            </div>
        </div>
        <div class="row">
            <div class="col-lg-8 offset-lg-2">
                <form method="post">
                <div class="form-group">
                        <label>Room Type</label>
                        <select class="form-control" name="room_type">
                            <option value="">Select Room Type</option>
                            <?php
                            $room_query = mysqli_query($connection, "SELECT DISTINCT room_type FROM tbl_bedallocation WHERE status='For cleaning'");
                            while($row = mysqli_fetch_assoc($room_query)) {
                                echo '<option value="' . $row['room_type'] . '">' . $row['room_type'] . '</option>';
                            }
                            ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Room Number</label>
                        <select class="form-control" name="room_number">
                            <option value="">Select Room Number</option>
                            <?php
                            $room_query = mysqli_query($connection, "SELECT DISTINCT room_number FROM tbl_bedallocation WHERE status='For cleaning'");
                            while($row = mysqli_fetch_assoc($room_query)) {
                                echo '<option value="' . $row['room_number'] . '">' . $row['room_number'] . '</option>';
                            }
                            ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Bed Number</label>
                        <select class="form-control" name="bed_number">
                            <option value="">Select Bed Number</option>
                            <?php
                            $bed_query = mysqli_query($connection, "SELECT DISTINCT bed_number FROM tbl_bedallocation WHERE status='For cleaning'");
                            while($row = mysqli_fetch_assoc($bed_query)) {
                                echo '<option value="' . $row['bed_number'] . '">' . $row['bed_number'] . '</option>';
                            }
                            ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Schedule Date and Time</label>
                        <input type="datetime-local" class="form-control" name="schedule_date_time" id="schedule_date_time">
                    </div>
                    <div class="form-group">
                        <label>Task Description</label>
                        <textarea class="form-control" name="task_description" rows="4"></textarea>
                    </div>
                    <div class="m-t-20 text-center">
                        <button class="btn btn-primary submit-btn" name="add-housekeeping-schedule">Add Housekeeping Schedule</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>


<?php
include('footer.php');
?>

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

    </style>