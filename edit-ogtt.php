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

// Get OGTT test ID for fetching existing data
if (isset($_GET['id'])) {
    $ogtt_id = sanitize($connection, $_GET['id']);

    // Fetch existing OGTT data using prepared statement
    $fetch_query = mysqli_prepare($connection, "SELECT * FROM tbl_ogtt WHERE ogtt_id = ?");
    mysqli_stmt_bind_param($fetch_query, "s", $ogtt_id);
    mysqli_stmt_execute($fetch_query);
    $result = mysqli_stmt_get_result($fetch_query);
    $ogtt_data = mysqli_fetch_array($result);
    mysqli_stmt_close($fetch_query);

    if (!$ogtt_data) {
        echo "OGTT test data not found.";
        exit;
    }
}

// Handle form submission for editing OGTT test data
if (isset($_POST['edit-ogtt'])) {
    // Sanitize inputs
    $ogtt_id = sanitize($connection, $_POST['ogtt_id']);
    $patient_name = sanitize($connection, $_POST['patient_name']);
    $date_time = sanitize($connection, $_POST['date_time']);
    $fbs = sanitize($connection, $_POST['fbs'] ?? NULL);
    $first_hour = sanitize($connection, $_POST['first_hour'] ?? NULL);
    $second_hour = sanitize($connection, $_POST['second_hour'] ?? NULL);
    $third_hour = sanitize($connection, $_POST['third_hour'] ?? NULL);

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

    // Update OGTT data using prepared statement
    $update_query = mysqli_prepare($connection, "UPDATE tbl_ogtt SET patient_name = ?, dob = ?, gender = ?, date_time = ?, fbs = ?, first_hour = ?, second_hour = ?, third_hour = ? WHERE ogtt_id = ?");

    // Bind all parameters as strings
    mysqli_stmt_bind_param($update_query, "sssssssss", $patient_name, $dob, $gender, $date_time, $fbs, $first_hour, $second_hour, $third_hour, $ogtt_id);

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
                    text: 'OGTT test updated successfully!',
                    confirmButtonColor: '#12369e'
                }).then(() => {
                    // Redirect to the relevant page or refresh
                    window.location.href = 'ogtt.php';
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
                    text: 'Error updating the OGTT test!',
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
                <h4 class="page-title">Edit OGTT Result</h4>
            </div>
            <div class="col-sm-8 text-right mb-3">
                <a href="ogtt.php" class="btn btn-primary"><i class="fa fa-arrow-left"></i> Back</a>
            </div>
        </div>
        <div class="row">
            <div class="col-lg-10 offset-lg-1">
                <form method="post">
                    <div class="form-group row">
                        <div class="col-sm-6">
                            <label for="ogtt_id">OGTT ID</label>
                            <input class="form-control" type="text" name="ogtt_id" id="ogtt_id" value="<?php echo htmlspecialchars($ogtt_data['ogtt_id']); ?>" readonly>
                        </div>
                        <div class="col-sm-6">
                            <label for="patient_name">Patient Name</label>
                            <input class="form-control" type="text" name="patient_name" id="patient_name" value="<?php echo htmlspecialchars($ogtt_data['patient_name']); ?>" readonly>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="date_time">Date and Time</label>
                        <input type="datetime-local" class="form-control" name="date_time" id="date_time" value="<?php echo date('Y-m-d\TH:i', strtotime($ogtt_data['date_time'])); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="fbs">Fasting Blood Sugar (FBS)</label>
                        <input class="form-control" type="number" name="fbs" id="fbs" value="<?php echo htmlspecialchars($ogtt_data['fbs']); ?>" 
                            step="0.01" min="2" max="20">
                    </div>

                    <div class="form-group">
                        <label for="first_hour">1-Hour Sample</label>
                        <input class="form-control" type="number" name="first_hour" id="first_hour" value="<?php echo htmlspecialchars($ogtt_data['first_hour']); ?>" 
                            step="0.01" min="2" max="20">
                    </div>

                    <div class="form-group">
                        <label for="second_hour">2-Hour Sample</label>
                        <input class="form-control" type="number" name="second_hour" id="second_hour" value="<?php echo htmlspecialchars($ogtt_data['second_hour']); ?>" 
                            step="0.01" min="2" max="20">
                    </div>

                    <div class="form-group">
                        <label for="third_hour">3-Hour Sample</label>
                        <input class="form-control" type="number" name="third_hour" id="third_hour" value="<?php echo htmlspecialchars($ogtt_data['third_hour']); ?>" 
                            step="0.01" min="2" max="20">
                    </div>
                    
                    <div class="text-center mt-4">
                        <button class="btn btn-primary submit-btn" name="edit-ogtt"><i class="fas fa-save mr-2"></i>Update Result</button>
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