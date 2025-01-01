<?php
session_start();
if(empty($_SESSION['name'])) {
    header('location:index.php');
    exit;
}

include('header.php');
include('includes/connection.php');

// Prepare queries to fetch room types, room numbers, and bed numbers
$available_room_types_query = mysqli_query($connection, "SELECT DISTINCT room_type FROM tbl_bedallocation");
$available_room_numbers_query = mysqli_query($connection, "SELECT DISTINCT room_number FROM tbl_bedallocation");
$available_bed_numbers_query = mysqli_query($connection, "SELECT DISTINCT bed_number FROM tbl_bedallocation");

$room_types = [];
$room_numbers = [];
$bed_numbers = [];

while ($row = mysqli_fetch_array($available_room_types_query)) {
    $room_types[] = $row['room_type'];
}
while ($row = mysqli_fetch_array($available_room_numbers_query)) {
    $room_numbers[] = $row['room_number'];
}
while ($row = mysqli_fetch_array($available_bed_numbers_query)) {
    $bed_numbers[] = $row['bed_number'];
}

// Fetch the next inpatient ID
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
    $transfer_date = $_REQUEST['transfer_date'];

    // Retrieve patient details including patient_id
    $fetch_patient_stmt = $connection->prepare("SELECT outpatient_id, gender, dob FROM tbl_outpatient WHERE CONCAT(first_name, ' ', last_name) = ? AND deleted = 0");
    $fetch_patient_stmt->bind_param("s", $patient_name);
    $fetch_patient_stmt->execute();
    $fetch_patient_result = $fetch_patient_stmt->get_result();
    $patient_row = $fetch_patient_result->fetch_assoc();
    
    $patient_id = $patient_row['outpatient_id'];
    $gender = $patient_row['gender'];
    $dob = $patient_row['dob'];
    $fetch_patient_stmt->close();

    // Insert new inpatient record
    $insert_stmt = $connection->prepare("INSERT INTO tbl_inpatient (inpatient_id, patient_name, dob, gender, room_type, room_number, bed_number, admission_date) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    $insert_stmt->bind_param("ssssssss", $inpatient_id, $patient_name, $dob, $gender, $room_type, $room_number, $bed_number, $admission_date);
    $insert_success = $insert_stmt->execute();
    $insert_stmt->close();

    if ($insert_success) {
        // Update patient type to Inpatient
        $update_patient_stmt = $connection->prepare("UPDATE tbl_patient SET patient_type = 'Inpatient' WHERE id = ?");
        $update_patient_stmt->bind_param("s", $patient_id);
        $update_patient_success = $update_patient_stmt->execute();
        $update_patient_stmt->close();

        if ($update_patient_success) {
            // Update bed status
            $update_bed_status_stmt = $connection->prepare("UPDATE tbl_bedallocation SET status='Occupied' WHERE room_number = ? AND bed_number = ?");
            $update_bed_status_stmt->bind_param("ss", $room_number, $bed_number);
            $update_bed_status_success = $update_bed_status_stmt->execute();
            $update_bed_status_stmt->close();

            if ($update_bed_status_success) {
                // Insert transfer record
                $insert_transfer_stmt = $connection->prepare("INSERT INTO tbl_transfer (patient_name, gender, dob, room_type, room_number, bed_number, transfer_date) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $insert_transfer_stmt->bind_param("sssssss", $patient_name, $gender, $dob, $room_type, $room_number, $bed_number, $transfer_date);
                $insert_transfer_success = $insert_transfer_stmt->execute();
                $insert_transfer_stmt->close();

                $msg = $insert_transfer_success ? "Inpatient created successfully, bed status updated to Occupied, and transfer record inserted" : "Error inserting transfer record!";
            } else {
                $msg = "Error updating bed status!";
            }
        } else {
            $msg = "Error updating patient type!";
        }
    } else {
        $msg = "Error inserting inpatient record!";
    }
}
?>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/datepicker/1.0.10/datepicker.min.css">
<link rel="stylesheet" type="text/css" href="assets/css/bootstrap-datetimepicker.css">
<link rel="stylesheet" type="text/css" href="assets/css/bootstrap-datetimepicker.min.css">

<div class="page-wrapper">
    <div class="content">
        <div class="row">
            <div class="col-sm-4">
                <h4 class="page-title">Admit Patient as Inpatient</h4>
            </div>
            <div class="col-sm-8 text-right mb-3">
                <a href="bed-transfer.php" class="btn btn-primary btn-rounded">Back</a>
            </div>
        </div>
        <div class="row">
            <div class="col-lg-10 offset-lg-1">
                <form method="post">
                    <div class="form-group row">
                        <div class="col-md-6">
                            <label>Inpatient ID <span class="text-danger">*</span></label>
                            <input class="form-control" type="text" name="inpatient_id" value="<?php
                                if (!empty($ipt_id)) {
                                    echo 'IPT-' . $ipt_id;
                                } else {
                                    echo "IPT-1";
                                } ?>" disabled>
                        </div>
                        <div class="col-md-6">
                            <label>Patient Name (Outpatient)</label>
                            <select class="form-control" name="patient_name" required>
                                <option value="">Select</option>
                                <?php while ($outpatient_row = mysqli_fetch_assoc($fetch_outpatient_query)) { ?>
                                    <option><?php echo htmlspecialchars($outpatient_row['patient_name']); ?></option>
                                <?php } ?>
                            </select>
                        </div>
                    </div>
                    <div class="form-group row">
                        <div class="col-md-6">
                            <label>Room Type</label>
                            <select class="form-control" name="room_type" id="room_type" required>
                                <option value="">Select</option>
                                <?php foreach ($room_types as $type) : ?>
                                    <option value="<?php echo htmlspecialchars($type); ?>"><?php echo htmlspecialchars($type); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label>Room Number</label>
                            <select class="form-control" name="room_number" id="room_number" required>
                                <option value="">Select</option>
                                <!-- Options will be dynamically populated based on the selected room type -->
                            </select>
                        </div>
                    </div>
                    <div class="form-group row">
                        <div class="col-md-6">
                            <label>Bed Number</label>
                            <select class="form-control" name="bed_number" id="bed_number" required>
                                <option value="">Select</option>
                                <!-- Options will be dynamically populated based on the selected room number -->
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label>Transfer Date and Time</label>
                            <input type="datetime-local" class="form-control" name="transfer_date" id="transfer_date">
                        </div>
                    </div>
                    <div class="form-group row">
                        <div class="col-md-6">
                            <label>Admission Date and Time</label>
                            <input type="datetime-local" class="form-control" name="admission_date" id="admission_date">
                        </div>
                    </div>
                    <div class="text-center mt-4">
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
    </style>