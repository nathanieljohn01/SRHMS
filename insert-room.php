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

// Fetch the inpatient record based on ID
$id = sanitize($connection, $_GET['id']);
$fetch_query = mysqli_prepare($connection, "SELECT * FROM tbl_inpatient WHERE id = ?");
mysqli_stmt_bind_param($fetch_query, 'i', $id);  // 'i' for integer parameter
mysqli_stmt_execute($fetch_query);
$result = mysqli_stmt_get_result($fetch_query);
$row = mysqli_fetch_array($result);

// Fetch available rooms, room numbers, and bed numbers
$available_rooms_query = mysqli_query($connection, "SELECT DISTINCT room_type FROM tbl_bedallocation");
$available_room_numbers_query = mysqli_query($connection, "SELECT DISTINCT room_number FROM tbl_bedallocation");
$available_bed_numbers_query = mysqli_query($connection, "SELECT DISTINCT bed_number FROM tbl_bedallocation");

$room_types = [];
$room_numbers = [];
$bed_numbers = [];

while ($room_row = mysqli_fetch_array($available_rooms_query)) {
    $room_types[] = $room_row['room_type'];
}

while ($room_row = mysqli_fetch_array($available_room_numbers_query)) {
    $room_numbers[] = $room_row['room_number'];
}

while ($room_row = mysqli_fetch_array($available_bed_numbers_query)) {
    $bed_numbers[] = $room_row['bed_number'];
}

if (isset($_POST['save-inpatient'])) {
    // Get the current values
    $current_room_type = sanitize($connection, $row['room_type']);
    $current_room_number = sanitize($connection, $row['room_number']);
    $current_bed_number = sanitize($connection, $row['bed_number']);

    // Get new selected values
    $room_type = sanitize($connection, $_POST['room_type']);
    $room_number = sanitize($connection, $_POST['room_number']);
    $bed_number = sanitize($connection, $_POST['bed_number']);

    // Update the inpatient record
    $update_query = mysqli_prepare($connection, "UPDATE tbl_inpatient SET room_type = ?, room_number = ?, bed_number = ? WHERE id = ?");
    mysqli_stmt_bind_param($update_query, 'siii', $room_type, $room_number, $bed_number, $id);
    
    if (mysqli_stmt_execute($update_query)) {
        // Update previous bed status
        $update_bed_status_query = mysqli_prepare($connection, "UPDATE tbl_bedallocation SET status = 'Available' WHERE room_type = ? AND room_number = ? AND bed_number = ?");
        mysqli_stmt_bind_param($update_bed_status_query, 'sii', $current_room_type, $current_room_number, $current_bed_number);
        mysqli_stmt_execute($update_bed_status_query);

        // Update new bed status
        $update_new_bed_status_query = mysqli_prepare($connection, "UPDATE tbl_bedallocation SET status = 'Occupied' WHERE room_type = ? AND room_number = ? AND bed_number = ?");
        mysqli_stmt_bind_param($update_new_bed_status_query, 'sii', $room_type, $room_number, $bed_number);
        mysqli_stmt_execute($update_new_bed_status_query);

        echo "
        <script src='https://cdn.jsdelivr.net/npm/sweetalert2@10'></script>
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                Swal.fire({
                    icon: 'success',
                    title: 'Success',
                    text: 'Room assignment completed successfully!',
                    confirmButtonColor: '#12369e'
                }).then(() => {
                    window.location.href = 'inpatients.php';
                });
            });
        </script>";
    } else {
        echo "
        <script src='https://cdn.jsdelivr.net/npm/sweetalert2@10'></script>
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'Room assignment failed!',
                    confirmButtonColor: '#12369e'
                });
            });
        </script>";
    }
}

?>

<div class="page-wrapper">
    <div class="content">
        <div class="row">
            <div class="col-sm-4">
                <h4 class="page-title">Insert Room</h4>
            </div>
            <div class="col-sm-8 text-right m-b-20">
                <a href="inpatients.php" class="btn btn-primary float-right">Back</a>
            </div>
        </div>
        <div class="row">
            <div class="col-lg-8 offset-lg-2">
                <form method="post">
                    <div class="form-group">
                        <label>Room Type <span class="text-danger">*</span></label>
                        <select class="form-control" name="room_type" id="room_type" required>
                            <option value="">Select</option>
                            <?php foreach ($room_types as $type) : ?>
                                <option value="<?php echo $type; ?>"><?php echo $type; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Room Number <span class="text-danger">*</span></label>
                        <select class="form-control" name="room_number" id="room_number" required>
                            <option value="">Select</option>
                            <!-- Options will be dynamically populated based on the selected room type -->
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Bed Number <span class="text-danger">*</span></label>
                        <select class="form-control" name="bed_number" id="bed_number" required>
                            <option value="">Select</option>
                            <!-- Options will be dynamically populated based on the selected room number -->
                        </select>
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
    document.getElementById('room_type').addEventListener('change', function() {
        var selectedRoomType = this.value;
        fetchRoomNumbers(selectedRoomType);
    });

    document.getElementById('room_number').addEventListener('change', function() {
        var selectedRoomNumber = this.value;
        fetchBedNumbers(selectedRoomNumber);
    });

    function fetchRoomNumbers(selectedRoomType) {
        var roomNumberSelect = document.getElementById('room_number');
        roomNumberSelect.innerHTML = '<option value="">Select</option>'; // Reset room number options

        if (selectedRoomType !== "") {
            fetch('your_server_script_room_number.php?room_type=' + selectedRoomType)
                .then(response => response.json())
                .then(data => {
                    data.forEach(function(number) {
                        var option = document.createElement('option');
                        option.value = number;
                        option.text = number;
                        roomNumberSelect.appendChild(option);
                    });
                })
                .catch(error => console.error('Error fetching room numbers:', error));
        }
    }

    function fetchBedNumbers(selectedRoomNumber) {
        var bedNumberSelect = document.getElementById('bed_number');
        bedNumberSelect.innerHTML = '<option value="">Select</option>'; // Reset bed number options

        if (selectedRoomNumber !== "") {
            fetch('your_server_script_bed_number.php?room_number=' + selectedRoomNumber)
                .then(response => response.json())
                .then(data => {
                    data.forEach(function(number) {
                        // Check if bed is available before adding to dropdown
                        isBedAvailable(selectedRoomNumber, number).then(isAvailable => {
                            if (isAvailable) {
                                var option = document.createElement('option');
                                option.value = number;
                                option.text = number;
                                bedNumberSelect.appendChild(option);
                            }
                        });
                    });
                })
                .catch(error => console.error('Error fetching bed numbers:', error));
        }
    }

    async function isBedAvailable(roomNumber, bedNumber) {
        const response = await fetch('check_bed_availability.php?room_number=' + roomNumber + '&bed_number=' + bedNumber);
        const data = await response.json();
        return data.available;
    }
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