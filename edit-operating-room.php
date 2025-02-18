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

// Get operating room ID from URL for editing
if (isset($_GET['id'])) {
    $operating_room_id = sanitize($connection, $_GET['id']);

    // Fetch current operating room data using prepared statements
    $query = mysqli_prepare($connection, "SELECT * FROM tbl_operating_room WHERE id = ?");
    mysqli_stmt_bind_param($query, "s", $operating_room_id);
    mysqli_stmt_execute($query);
    $result = mysqli_stmt_get_result($query);
    $operating_room = mysqli_fetch_assoc($result);
    mysqli_stmt_close($query);

    // Fetch patient name using prepared statements
    $patient_query = mysqli_prepare($connection, "SELECT patient_id, CONCAT(first_name, ' ', last_name) AS patient_name FROM tbl_patient WHERE patient_id = ?");
    mysqli_stmt_bind_param($patient_query, "s", $operating_room['patient_id']);
    mysqli_stmt_execute($patient_query);
    $patient_result = mysqli_stmt_get_result($patient_query);
    $patient_row = mysqli_fetch_assoc($patient_result);
    $patient_name = $patient_row['patient_name'];
    mysqli_stmt_close($patient_query);
}

// Handle form submission for editing
if (isset($_REQUEST['edit-operating-room'])) {
    // Sanitize inputs
    $patient_id = sanitize($connection, $_REQUEST['patient_id']);
    $current_surgery = sanitize($connection, $_REQUEST['current_surgery']);
    $surgeon = sanitize($connection, $_REQUEST['surgeon']);
    $end_time = date("g:i A", strtotime(sanitize($connection, $_REQUEST['end_time'])));
    $notes = sanitize($connection, $_REQUEST['notes']);
    $operation_status = sanitize($connection, $_REQUEST['operation_status']);

    // Handle remarks based on operation status
    if ($operation_status == 'Cancelled') {
        $remarks = sanitize($connection, $_REQUEST['remarks']);
        $operation_status = "Cancelled - Remarks: " . $remarks;
    }

    // Update the operating room record using prepared statements
    $update_query = mysqli_prepare($connection, "UPDATE tbl_operating_room 
        SET patient_id = ?, current_surgery = ?, surgeon = ?, end_time = ?, notes = ?, operation_status = ? 
        WHERE id = ?");
    
    // Bind all parameters as strings
    mysqli_stmt_bind_param($update_query, "ssssssi", $patient_id, $current_surgery, $surgeon, $end_time, $notes, $operation_status, $operating_room_id);
    
    // Execute and check if successful
    if (mysqli_stmt_execute($update_query)) {
        $msg = "Operating room record updated successfully!";

        // SweetAlert success message
        echo "
        <script src='https://cdn.jsdelivr.net/npm/sweetalert2@10'></script>
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                Swal.fire({
                    icon: 'success',
                    title: 'Success',
                    text: 'Operating room record updated successfully!',
                    confirmButtonColor: '#12369e'
                }).then(() => {
                    // Optional: Redirect after success
                    window.location.href = 'operating-room.php'; // Adjust the URL to your target page
                });
            });
        </script>";
    } else {
        $msg = "Error updating the operating room record!";

        // SweetAlert error message
        echo "
        <script src='https://cdn.jsdelivr.net/npm/sweetalert2@10'></script>
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'Error updating the operating room record!',
                    confirmButtonColor: '#12369e'
                });
            });
        </script>";
    }

    // Close the update statement
    mysqli_stmt_close($update_query);

}
?>

<div class="page-wrapper">
    <div class="content">
        <div class="row">
            <div class="col-sm-4">
                <h4 class="page-title">Edit Operating Room</h4>
            </div>
            <div class="col-sm-8 text-right m-b-20">
                <a href="operating-room.php" class="btn btn-primary float-right">Back</a>
            </div>
        </div>

        <!-- Form Section -->
        <div class="row">
            <div class="col-lg-8 offset-lg-2">
                <!-- Edit Operating Room Form -->
                <form method="post">
                    <!-- Patient Selection -->
                    <div class="row">
                        <div class="col-md-12">
                            <div class="form-group">
                                <label>Patient Name</label>
                                <select class="select form-control" name="patient_id" required>
                                    <option value="">Select Patient</option>
                                    <?php
                                    // Fetch patients from tbl_patient
                                    $fetch_query = mysqli_query($connection, "SELECT patient_id, CONCAT(first_name, ' ', last_name) AS patient_name FROM tbl_patient WHERE deleted = 0");
                                    if (!$fetch_query) {
                                        echo '<option value="">Error fetching patients</option>';
                                    } else {
                                        while ($row = mysqli_fetch_array($fetch_query)) {
                                            // Pre-select the current patient
                                            $selected = ($row['patient_id'] == $operating_room['patient_id']) ? 'selected' : '';
                                            echo '<option value="' . htmlspecialchars($row['patient_id']) . '" ' . $selected . '>' . htmlspecialchars($row['patient_name']) . '</option>';
                                        }
                                    }
                                    ?>
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- Current Surgery and Surgeon Selection -->
                    <div class="row mb-3">
                        <!-- Current Surgery -->
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Current Surgery</label>
                                <input type="text" class="form-control" name="current_surgery" value="<?php echo htmlspecialchars($operating_room['current_surgery']); ?>" required>
                            </div>
                        </div>

                        <!-- Surgeon Selection -->
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Surgeon</label>
                                <select class="select form-control" name="surgeon" required>
                                    <option value="">Select Surgeon</option>
                                    <?php
                                    // Fetch doctors (role=2) with their specializations from tbl_employee
                                    $fetch_surgeon_query = mysqli_query($connection, "SELECT CONCAT(first_name, ' ', last_name, ' - ', specialization) AS name_specialization FROM tbl_employee WHERE role=2 AND status=1");

                                    if (!$fetch_surgeon_query) {
                                        echo '<option value="">Error fetching surgeons</option>';
                                    } else {
                                        while ($row = mysqli_fetch_array($fetch_surgeon_query)) {
                                            // Pre-select the current surgeon
                                            $selected = ($row['name_specialization'] == $operating_room['surgeon']) ? 'selected' : '';
                                            echo '<option value="' . htmlspecialchars($row['name_specialization']) . '" ' . $selected . '>' . htmlspecialchars($row['name_specialization']) . '</option>';
                                        }
                                    }
                                    ?>
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- End Time -->
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <div class="form-group position-relative">
                                <label for="end_time">End Time</label>
                                <input type="time" id="end_time" class="form-control" name="end_time" required>
                            </div>
                        </div>

                        <!-- Operation Status -->
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Operation Status</label>
                                <select class="select form-control" name="operation_status" id="operation_status" required onchange="checkOperationStatus()">
                                    <option value="">Select Status</option>
                                    <option value="Completed" <?php echo ($operating_room['operation_status'] == 'Completed') ? 'selected' : ''; ?>>Completed</option>
                                    <option value="Cancelled" <?php echo (strpos($operating_room['operation_status'], 'Cancelled') !== false) ? 'selected' : ''; ?>>Cancelled</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- Remarks -->
                    <div class="row mb-3" id="remarks-section" style="display: none;">
                        <div class="col-md-12">
                            <div class="form-group">
                                <label>Remarks (if cancelled)</label>
                                <textarea cols="30" rows="4" class="form-control" name="remarks" placeholder="Enter remarks if cancelled"></textarea>
                            </div>
                        </div>
                    </div>

                    <!-- Notes -->
                    <div class="row mb-3">
                        <div class="col-md-12">
                            <div class="form-group">
                                <label>Notes</label>
                                <textarea cols="30" rows="4" class="form-control" name="notes"><?php echo htmlspecialchars($operating_room['notes']); ?></textarea>
                            </div>
                        </div>
                    </div>

                    <!-- Submit Button -->
                    <div class="m-t-20 text-center">
                        <button name="edit-operating-room" class="btn btn-primary submit-btn">Save</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php
include('footer.php');
?>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script type="text/javascript">
    <?php
        if(isset($msg)) {
            echo 'swal("' . $msg . '");';
        }
    ?>
</script>

<script>
function checkOperationStatus() {
    var operationStatus = document.getElementById('operation_status').value;
    var remarksSection = document.getElementById('remarks-section');

    // Show remarks section only if the operation status is "Cancelled"
    if (operationStatus === 'Cancelled') {
        remarksSection.style.display = 'block'; // Show remarks field
    } else {
        remarksSection.style.display = 'none'; // Hide remarks field
    }

    // Disable operation status selection if it is already "Completed" or "Cancelled"
    if (operationStatus === 'Completed' || operationStatus.includes('Cancelled')) {
        document.getElementById('operation_status').disabled = true; // Disable dropdown
    } else {
        document.getElementById('operation_status').disabled = false; // Enable dropdown
    }
}

// Check initial state on page load
document.addEventListener("DOMContentLoaded", function() {
    checkOperationStatus(); // Set initial state for remarks and operation status
});
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
        background: url('data:image/svg+xml;charset=UTF-8,%3Csvg viewBox="0 0 20 20" fill="currentColor" xmlns="http://www.w3.org/2000/svg"%3E%3Cpath fill-rule="evenodd" clip-rule="evenodd" d="M6.293 7.707a1 1 0 011.414-1.414L10 8.586l2.293-2.293a1 1 0 011.414 1.414l-3 3a1 1 0 01-1.414 0l-3-3z" /%3E%3C/svg%3E') no-repeat right 1rem center/16px 16px; /* Use custom arrow for select */
    }
    /* Styling for the select dropdown icon */
</style>
