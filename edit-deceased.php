<?php
session_start();
if (empty($_SESSION['name'])) {
    header('location:index.php');
    exit();
}
include('header.php');
include('includes/connection.php');

// Sanitize function for input sanitization and XSS protection
function sanitize($connection, $input) {
    return mysqli_real_escape_string($connection, htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8'));
}

// Fetch existing data if ID is provided
if (isset($_GET['id'])) {
    $deceased_id = sanitize($connection, $_GET['id']);  // Sanitize the ID

    // Fetch data using prepared statements
    $fetch_stmt = $connection->prepare("SELECT * FROM tbl_deceased WHERE deceased_id = ?");
    $fetch_stmt->bind_param("s", $deceased_id);
    $fetch_stmt->execute();
    $result = $fetch_stmt->get_result();
    $deceased_data = $result->fetch_assoc();

    if (!$deceased_data) {
        die('Record not found.');
    }
} else {
    die('No ID provided.');
}

if (isset($_POST['save-deceased'])) {
    $patient_name = sanitize($connection, $_POST['patient_name']);
    $dod = DateTime::createFromFormat('d/m/Y', $_POST['dod'])->format('F j, Y');
    $tod = date("g:i A", strtotime($_POST['tod']));
    $cod = sanitize($connection, $_POST['cod']);
    $physician = sanitize($connection, $_POST['physician']);
    $next_of_kin_contact = sanitize($connection, $_POST['next_of_kin_contact']);
    $discharge_status = sanitize($connection, $_POST['discharge_status']);

    // Fetch patient_id based on patient_name
    $fetch_patient_stmt = $connection->prepare("SELECT patient_id FROM tbl_patient WHERE CONCAT(first_name, ' ', last_name) = ? AND deleted = 0");
    $fetch_patient_stmt->bind_param("s", $patient_name);
    $fetch_patient_stmt->execute();
    $patient_result = $fetch_patient_stmt->get_result();
    $row = $patient_result->fetch_assoc();
    $patient_id = $row['patient_id'];

    // Prepare the update statement
    $update_stmt = $connection->prepare("UPDATE tbl_deceased SET patient_id = ?, patient_name = ?, dod = ?, tod = ?, cod = ?, physician = ?, next_of_kin_contact = ?, discharge_status = ? WHERE deceased_id = ?");
    $update_stmt->bind_param("sssssssss", $patient_id, $patient_name, $dod, $tod, $cod, $physician, $next_of_kin_contact, $discharge_status, $deceased_id);

    // Execute the update statement
    if ($update_stmt->execute()) {
        $msg = "Deceased record updated successfully";

        // SweetAlert success message
        echo "
        <script src='https://cdn.jsdelivr.net/npm/sweetalert2@10'></script>
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                Swal.fire({
                    icon: 'success',
                    title: 'Success',
                    text: 'Deceased record updated successfully',
                    confirmButtonColor: '#12369e'
                }).then(() => {
                    window.location.href = 'deceased-records.php'; // Adjust to the page you want to redirect to after success
                });
            });
        </script>";
    } else {
        $msg = "Error updating record!";

        // SweetAlert error message
        echo "
        <script src='https://cdn.jsdelivr.net/npm/sweetalert2@10'></script>
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'Error updating record: " . mysqli_error($connection) . "',
                });
            });
        </script>";
    }

    $update_stmt->close();
    $fetch_patient_stmt->close();
    $connection->close();

}
?>

<div class="page-wrapper">
    <div class="content">
        <div class="row">
            <div class="col-sm-4">
                <h4 class="page-title">Edit Deceased</h4>
            </div>
            <div class="col-sm-8 text-right mb-3">
                <a href="deceased.php" class="btn btn-primary float-right"><i class="fa fa-arrow-left"></i> Back</a>
            </div>
        </div>
        <div class="row">
            <div class="col-lg-8 offset-lg-2">
                <form method="post">
                    <div class="row">
                        <div class="col-sm-6">
                            <div class="form-group">
                                <label>Deceased ID</label>
                                <input class="form-control" type="text" name="deceased_id" value="<?php echo htmlspecialchars($deceased_data['deceased_id']); ?>" disabled>
                            </div>
                        </div>
                        <div class="col-sm-6">
                            <div class="form-group">
                                <label>Patient Name</label>
                                <select class="form-control" name="patient_name" required>
                                    <option value="">Select</option>
                                    <?php
                                    $fetch_query = mysqli_query($connection, "SELECT concat(first_name,' ',last_name) as name FROM tbl_patient WHERE deleted = 0");
                                    while ($row = mysqli_fetch_array($fetch_query)) {
                                        $selected = ($row['name'] == $deceased_data['patient_name']) ? 'selected' : '';
                                        echo "<option value='{$row['name']}' $selected>{$row['name']}</option>";
                                    }
                                    ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-sm-6">
                            <div class="form-group">
                                <label>Date of Death</label>
                                <div class="cal-icon">
                                    <input type="text" class="form-control datetimepicker" name="dod" value="<?php echo DateTime::createFromFormat('F j, Y', $deceased_data['dod'])->format('d/m/Y'); ?>" required>
                                </div>
                            </div>
                        </div>
                        <div class="col-sm-6">
                            <div class="form-group position-relative">
                                <label for="tod">Time of Death</label>
                                <input type="time" id="tod" class="form-control" name="tod" value="<?php echo date("H:i", strtotime($deceased_data['tod'])); ?>" required>
                            </div>
                        </div>
                        <div class="col-sm-6">
                            <div class="form-group">
                                <label>Cause of Death</label>
                                <input class="form-control" type="text" name="cod" value="<?php echo htmlspecialchars($deceased_data['cod']); ?>">
                            </div>
                        </div>
                        <div class="col-sm-6">
                            <div class="form-group">
                                <label>Physician</label>
                                <select class="form-control" name="physician" required>
                                    <option value="">Select Physician</option>
                                    <?php
                                    $physician_query = mysqli_query($connection, "SELECT id, first_name, last_name FROM tbl_employee WHERE role = 2 AND specialization = 'Cardiologist'");
                                    while ($physician_row = mysqli_fetch_assoc($physician_query)) {
                                        $physician_name = $physician_row['first_name'] . ' ' . $physician_row['last_name'];
                                        $selected = ($physician_name == $deceased_data['physician']) ? 'selected' : '';
                                        echo "<option value='$physician_name' $selected>$physician_name</option>";
                                    }
                                    ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-sm-6">
                            <div class="form-group">
                                <label>Next of Kin Contact</label>
                                <input class="form-control" type="text" name="next_of_kin_contact" value="<?php echo htmlspecialchars($deceased_data['next_of_kin_contact']); ?>">
                            </div>
                        </div>
                        <div class="col-sm-6">
                            <div class="form-group">
                                <label>Discharge Status</label>
                                <input class="form-control" type="text" name="discharge_status" value="<?php echo htmlspecialchars($deceased_data['discharge_status']); ?>">
                            </div>
                        </div>
                        <div class="col-12 mt-3 text-center">
                            <button class="btn btn-primary submit-btn" name="save-deceased"><i class="fas fa-save mr-2"></i>Save</button>
                        </div>
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
<style>
    .btn-primary.submit-btn {
        border-radius: 4px; 
        padding: 10px 20px;
        font-size: 16px;
    }
/* Optional: Style the input field */
.form-control {
    border-radius: .375rem; /* Rounded corners */
    border-color: #ced4da; /* Border color */
    background-color: #f8f9fa; /* Background color */
}

.btn-primary {
    background: #12369e;
    border: none;
}
.btn-primary:hover {
    background: #05007E;
}
.form-group {
    position: relative;
}

.cal-icon {
    position: relative;
}

.cal-icon input {
    padding-right: 30px; /* Adjust the padding to make space for the icon */
}

.cal-icon::after {
    content: '\f073'; /* FontAwesome calendar icon */
    font-family: 'FontAwesome';
    position: absolute;
    right: 10px; /* Adjust this value to align the icon properly */
    top: 50%;
    transform: translateY(-50%);
    pointer-events: none;
    color: #aaa; /* Adjust color as needed */
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
