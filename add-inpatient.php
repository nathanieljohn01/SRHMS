<?php
session_start();
if(empty($_SESSION['name'])) {
    header('location:index.php');
}
include('header.php');
include('includes/connection.php');

// Fetch available room types, room numbers, and bed numbers
$available_room_types_query = mysqli_query($connection, "SELECT DISTINCT room_type FROM tbl_bedallocation");
$available_room_numbers_query = mysqli_query($connection, "SELECT DISTINCT room_number FROM tbl_bedallocation");
$available_bed_numbers_query = mysqli_query($connection, "SELECT DISTINCT bed_number FROM tbl_bedallocation");

$room_types = [];
$room_numbers = [];
$bed_numbers = [];

// Fetch room types
while ($row = mysqli_fetch_array($available_room_types_query)) {
    $room_types[] = $row['room_type'];
}

// Fetch room numbers
while ($row = mysqli_fetch_array($available_room_numbers_query)) {
    $room_numbers[] = $row['room_number'];
}

// Fetch bed numbers
while ($row = mysqli_fetch_array($available_bed_numbers_query)) {
    $bed_numbers[] = $row['bed_number'];
}

// Fetch maximum inpatient id and increment
$fetch_query = mysqli_query($connection, "SELECT MAX(id) as id FROM tbl_inpatient");
$row = mysqli_fetch_row($fetch_query);
$ipt_id = ($row[0] == 0) ? 1 : $row[0] + 1;

if (isset($_REQUEST['save-inpatient'])) {
    $inpatient_id = 'IPT-' . $ipt_id;
    $patient_name = $_REQUEST['patient_name'];
    $room_type = $_REQUEST['room_type'];
    $room_number = $_REQUEST['room_number'];
    $bed_number = $_REQUEST['bed_number'];
    $admission_date = $_REQUEST['admission_date'];

    // Prepare query to fetch patient details
    $query = "SELECT gender, dob FROM tbl_patient WHERE CONCAT(first_name, ' ', last_name) = ? AND deleted = 0";
    $fetch_query = mysqli_prepare($connection, $query);
    mysqli_stmt_bind_param($fetch_query, 's', $patient_name); // 's' for string
    mysqli_stmt_execute($fetch_query);
    $result = mysqli_stmt_get_result($fetch_query);
    $row = mysqli_fetch_array($result);

    if ($row) {
        $gender = $row['gender'];
        $dob = $row['dob'];

        // Insert inpatient record
        $insert_query = mysqli_prepare($connection, "INSERT INTO tbl_inpatient (inpatient_id, patient_name, dob, gender, room_type, room_number, bed_number, admission_date) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        mysqli_stmt_bind_param($insert_query, 'ssssssss', $inpatient_id, $patient_name, $dob, $gender, $room_type, $room_number, $bed_number, $admission_date); // 's' for string parameters

        if (mysqli_stmt_execute($insert_query)) {
            // Update bed status
            $update_bed_status_query = mysqli_prepare($connection, "UPDATE tbl_bedallocation SET status = 'Occupied' WHERE room_number = ? AND bed_number = ?");
            mysqli_stmt_bind_param($update_bed_status_query, 'ss', $room_number, $bed_number); // 's' for string parameters

            if (mysqli_stmt_execute($update_bed_status_query)) {
                $msg = "Inpatient created successfully and bed status updated to Occupied";
            } else {
                $msg = "Error updating bed status!";
            }
        } else {
            $msg = "Error inserting inpatient record!";
        }

        // Close prepared statements
        mysqli_stmt_close($insert_query);
        mysqli_stmt_close($update_bed_status_query);
    } else {
        $msg = "Patient not found or already deleted.";
    }

    // Close the fetch query statement
    mysqli_stmt_close($fetch_query);
}
?>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/datepicker/1.0.10/datepicker.min.css">
<link rel="stylesheet" type="text/css" href="assets/css/bootstrap-datetimepicker.css">
<link rel="stylesheet" type="text/css" href="assets/css/bootstrap-datetimepicker.min.css">

<div class="page-wrapper">
    <div class="content">
        <div class="row">
            <div class="col-sm-4">
                <h4 class="page-title">Add Inpatient</h4>
            </div>
            <div class="col-sm-8 text-right m-b-20">
                <a href="inpatients.php" class="btn btn-primary btn-rounded float-right">Back</a>
            </div>
        </div>
        <div class="row">
            <div class="col-lg-8 offset-lg-2">
                <form method="post">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Inpatient ID <span class="text-danger">*</span></label>
                                <input class="form-control" type="text" name="inpatient_id" value="<?php
                                    if (!empty($ipt_id)) {
                                        echo 'IPT-' . $ipt_id;
                                    } else {
                                        echo "IPT-1";
                                    } ?>" disabled>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Patient Name</label>
                                <select class="form-control" name="patient_name" required>
                                    <option value="">Select</option>
                                    <?php
                                    $fetch_query = mysqli_query($connection, "SELECT concat(first_name,' ',last_name) as name, patient_type FROM tbl_patient WHERE patient_type = 'Inpatient' AND deleted = 0");
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
                                <label>Room Type</label>
                                <select class="form-control" name="room_type" id="room_type" required>
                                    <option value="">Select</option>
                                    <?php foreach ($room_types as $type) : ?>
                                        <option value="<?php echo $type; ?>"><?php echo $type; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Room Number</label>
                                <select class="form-control" name="room_number" id="room_number" required>
                                    <option value="">Select</option>
                                    <!-- Options will be dynamically populated based on the selected room type -->
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Bed Number</label>
                                <select class="form-control" name="bed_number" id="bed_number" required>
                                    <option value="">Select</option>
                                    <!-- Options will be dynamically populated based on the selected room number -->
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Admission Date and Time</label>
                                <input type="datetime-local" class="form-control" name="admission_date" id="admission_date">
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

