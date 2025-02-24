<?php
session_start();
if (empty($_SESSION['name'])) {
    header('location:index.php');
}
include('header.php');
include('includes/connection.php');

// Fetch the inpatient record based on ID
$id = $_GET['id'];
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
// Handle the form submission
if (isset($_POST['save-inpatient'])) {
    $room_type = $_POST['room_type'];
    $room_number = $_POST['room_number'];
    $bed_number = $_POST['bed_number'];

   

    // Fetch patient gender and dob based on the patient name
    $fetch_patient_query = mysqli_prepare($connection, "SELECT gender, dob FROM tbl_patient WHERE CONCAT(first_name, ' ', last_name) = ? AND deleted = 0");
    mysqli_stmt_bind_param($fetch_patient_query, 's', $patient_name);  // 's' for string parameter
    mysqli_stmt_execute($fetch_patient_query);
    $patient_result = mysqli_stmt_get_result($fetch_patient_query);
    $patient_row = mysqli_fetch_array($patient_result);
    $gender = $patient_row['gender'];
    $dob = $patient_row['dob'];

    // Update the inpatient record
    $update_query = mysqli_prepare($connection, "UPDATE tbl_inpatient SET patient_name = ?, dob = ?, gender = ?, room_type = ?, room_number = ?, bed_number = ? WHERE id = ?");
    mysqli_stmt_bind_param($update_query, 'ssssiii', $patient_name, $dob, $gender, $room_type, $room_number, $bed_number, $id);
    $update_result = mysqli_stmt_execute($update_query);

    if ($update_result) {
        // Update the bed status to 'Available' for the previous bed
        $update_bed_status_query = mysqli_prepare($connection, "UPDATE tbl_bedallocation SET status = 'Available' WHERE room_type = ? AND room_number = ? AND bed_number = ?");
        mysqli_stmt_bind_param($update_bed_status_query, 'sii', $current_room_type, $current_room_number, $current_bed_number);
        mysqli_stmt_execute($update_bed_status_query);

        // Update the bed status to 'Occupied' for the new bed
        $update_new_bed_status_query = mysqli_prepare($connection, "UPDATE tbl_bedallocation SET status = 'Occupied' WHERE room_type = ? AND room_number = ? AND bed_number = ?");
        mysqli_stmt_bind_param($update_new_bed_status_query, 'sii', $room_type, $room_number, $bed_number);
        mysqli_stmt_execute($update_new_bed_status_query);

        $msg = "Inpatient updated successfully";
        // Re-fetch the updated inpatient record
        $fetch_query = mysqli_prepare($connection, "SELECT * FROM tbl_inpatient WHERE id = ?");
        mysqli_stmt_bind_param($fetch_query, 'i', $id);
        mysqli_stmt_execute($fetch_query);
        $result = mysqli_stmt_get_result($fetch_query);
        $row = mysqli_fetch_array($result);
    } else {
        $msg = "Error updating inpatient record";
    }
}
?>


<div class="page-wrapper">
    <div class="content">
        <div class="row">
            <div class="col-sm-4">
                <h4 class="page-title">Edit Inpatient</h4>
            </div>
            <div class="col-sm-8 text-right m-b-20">
                <a href="inpatients.php" class="btn btn-primary btn-rounded float-right"><i class="fa fa-arrow-left"></i> Back</a>
            </div>
        </div>
        <div class="row">
            <div class="col-lg-8 offset-lg-2">
                <form method="post">
                    <div class="form-group">
                        <label>Inpatient ID <span class="text-danger">*</span></label>
                        <input class="form-control" type="text" name="inpatient_id" value="<?php echo $row['inpatient_id']; ?>" disabled>
                    </div>
                    <div class="form-group">
                        <label>Patient Name <span class="text-danger">*</span></label>
                        <input class="form-control" type="text" name="patient_name" value="<?php echo $row['patient_name']; ?>">
                    </div>
                    <div class="form-group">
                        <label>Room Type <span class="text-danger">*</span></label>
                        <select class="form-control" name="room_type" id="room_type" required>
                            <option value="">Select</option>
                            <?php foreach ($room_types as $type) : ?>
                                <option value="<?php echo $type; ?>" <?php if ($row['room_type'] == $type) echo 'selected'; ?>><?php echo $type; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Room Number <span class="text-danger">*</span></label>
                        <select class="form-control" name="room_number" id="room_number" required>
                            <option value="">Select</option>
                            <?php foreach ($room_numbers as $number) : ?>
                                <option value="<?php echo $number; ?>" <?php if ($row['room_number'] == $number) echo 'selected'; ?>><?php echo $number; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Bed Number <span class="text-danger">*</span></label>
                        <select class="form-control" name="bed_number" id="bed_number" required>
                            <option value="">Select</option>
                            <?php foreach ($bed_numbers as $number) : ?>
                                <option value="<?php echo $number; ?>" <?php if ($row['bed_number'] == $number) echo 'selected'; ?>><?php echo $number; ?></option>
                            <?php endforeach; ?>
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
                        var option = document.createElement('option');
                        option.value = number;
                        option.text = number;
                        bedNumberSelect.appendChild(option);
                    });
                })
                .catch(error => console.error('Error fetching bed numbers:', error));
        }
    }
</script>

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

        select.form-control:focus {
            border-color: #12369e; /* Border color on focus */
            box-shadow: 0 0 0 .2rem rgba(38, 143, 255, .25); /* Shadow on focus */
        }
</style>