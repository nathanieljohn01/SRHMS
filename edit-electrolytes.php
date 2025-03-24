<?php
session_start();
if (empty($_SESSION['name'])) {
    header('location:index.php');
    exit();
}
include('header.php');
include('includes/connection.php');

// Function to sanitize user inputs
function sanitize($connection, $input) {
    return mysqli_real_escape_string($connection, htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8'));
}

// Get electrolytes test ID for fetching existing data
if (isset($_GET['id'])) {
    $electrolytes_id = sanitize($connection, $_GET['id']);

    // Fetch existing electrolytes data using prepared statement
    $fetch_query = mysqli_prepare($connection, "SELECT * FROM tbl_electrolytes WHERE electrolytes_id = ?");
    mysqli_stmt_bind_param($fetch_query, "s", $electrolytes_id);
    mysqli_stmt_execute($fetch_query);
    $result = mysqli_stmt_get_result($fetch_query);
    $electrolytes_data = mysqli_fetch_array($result);
    mysqli_stmt_close($fetch_query);

    if (!$electrolytes_data) {
        echo "Electrolytes test data not found.";
        exit;
    }
}

// Handle form submission for editing electrolytes test data
if (isset($_POST['edit-electrolytes'])) {
    // Sanitize inputs
    $electrolytes_id = sanitize($connection, $_POST['electrolytes_id']);
    $patient_name = sanitize($connection, $_POST['patient_name']);
    $date_time = sanitize($connection, $_POST['date_time']);
    $sodium = sanitize($connection, $_POST['sodium'] ?? NULL);
    $potassium = sanitize($connection, $_POST['potassium'] ?? NULL);
    $chloride = sanitize($connection, $_POST['chloride'] ?? NULL);
    $calcium = sanitize($connection, $_POST['calcium'] ?? NULL);

    // Fetch patient information using prepared statement
    $patient_query = mysqli_prepare($connection, "SELECT patient_id, gender, dob FROM tbl_patient WHERE CONCAT(first_name, ' ', last_name) = ?");
    mysqli_stmt_bind_param($patient_query, "s", $patient_name);
    mysqli_stmt_execute($patient_query);
    $patient_result = mysqli_stmt_get_result($patient_query);
    $row = mysqli_fetch_array($patient_result);
    mysqli_stmt_close($patient_query);

    $patient_id = $row['patient_id'];
    $gender = $row['gender'];
    $dob = $row['dob'];

    // Update electrolytes data using prepared statement
    $update_query = mysqli_prepare($connection, "UPDATE tbl_electrolytes SET patient_name = ?, dob = ?, gender = ?, date_time = ?, sodium = ?, potassium = ?, chloride = ?, calcium = ? WHERE electrolytes_id = ?");

    // Bind all parameters as strings
    mysqli_stmt_bind_param($update_query, "sssssssss", $patient_name, $dob, $gender, $date_time, $sodium, $potassium, $chloride, $calcium, $electrolytes_id);

    // Execute the update query
    if (mysqli_stmt_execute($update_query)) {
        // SweetAlert success message
        echo "
        <script src='https://cdn.jsdelivr.net/npm/sweetalert2@10'></script>
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                Swal.fire({
                    icon: 'success',
                    title: 'Success',
                    text: 'Electrolytes test updated successfully!',
                    confirmButtonColor: '#12369e'
                }).then(() => {
                    // Redirect to the relevant page or refresh
                    window.location.href = 'electrolytes.php';
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
                    text: 'Error updating the electrolytes test!',
                    confirmButtonColor: '#12369e'
                });
            });
        </script>";
    }

    // Close the prepared statement
    mysqli_stmt_close($update_query);
}
?>

<div class="page-wrapper">
    <div class="content">
        <div class="row">
            <div class="col-sm-4">
                <h4 class="page-title">Edit Electrolytes Result</h4>
            </div>
            <div class="col-sm-8 text-right mb-3">
                <a href="electrolytes.php" class="btn btn-primary"><i class="fa fa-arrow-left"></i> Back</a>
            </div>
        </div>
        <div class="row">
            <div class="col-lg-10 offset-lg-1">
                <form method="post">
                    <div class="form-group row">
                        <div class="col-sm-6">
                            <label for="electrolytes_id">Electrolytes ID</label>
                            <input class="form-control" type="text" name="electrolytes_id" id="electrolytes_id" value="<?php echo htmlspecialchars($electrolytes_data['electrolytes_id']); ?>" readonly>
                        </div>
                        <div class="col-sm-6">
                            <label for="patient_name">Patient Name</label>
                            <input class="form-control" type="text" name="patient_name" id="patient_name" value="<?php echo htmlspecialchars($electrolytes_data['patient_name']); ?>" readonly>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="date_time">Date and Time</label>
                        <input type="datetime-local" class="form-control" name="date_time" id="date_time" value="<?php echo date('Y-m-d\TH:i', strtotime($electrolytes_data['date_time'])); ?>">
                    </div>
                    <div class="form-group">
                        <label for="sodium">Sodium (Na<sup>+</sup>)</label>
                        <input class="form-control" type="number" name="sodium" id="sodium" value="<?php echo htmlspecialchars($electrolytes_data['sodium']); ?>" 
                            step="0.01" min="100" max="200" required>
                    </div>

                    <div class="form-group">
                        <label for="potassium">Potassium (K<sup>+</sup>)</label>
                        <input class="form-control" type="number" name="potassium" id="potassium" value="<?php echo htmlspecialchars($electrolytes_data['potassium']); ?>" 
                            step="0.01" min="2.5" max="6.5" required>
                    </div>

                    <div class="form-group">
                        <label for="chloride">Chloride (Cl<sup>-</sup>)</label>
                        <input class="form-control" type="number" name="chloride" id="chloride" value="<?php echo htmlspecialchars($electrolytes_data['chloride']); ?>" 
                            step="0.01" min="70" max="130" required>
                    </div>

                    <div class="form-group">
                        <label for="calcium">Calcium (Ca<sup>2+</sup>)</label>
                        <input class="form-control" type="number" name="calcium" id="calcium" value="<?php echo htmlspecialchars($electrolytes_data['calcium']); ?>" 
                            step="0.01" min="1.0" max="3.0" required>
                    </div>
                    <div class="text-center mt-4">
                        <button class="btn btn-primary submit-btn" name="edit-electrolytes"><i class="fas fa-save mr-2"></i>Update Result</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php
include('footer.php');
?>


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
</style>
