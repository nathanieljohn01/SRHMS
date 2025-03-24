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

// Get urinalysis ID for fetching existing data
if (isset($_GET['id'])) {
    $urinalysis_id = sanitize($connection, $_GET['id']);

    // Fetch existing urinalysis data using prepared statement
    $fetch_query = mysqli_prepare($connection, "SELECT * FROM tbl_urinalysis WHERE urinalysis_id = ?");
    mysqli_stmt_bind_param($fetch_query, "s", $urinalysis_id);
    mysqli_stmt_execute($fetch_query);
    $result = mysqli_stmt_get_result($fetch_query);
    $urinalysis_data = mysqli_fetch_array($result);
    mysqli_stmt_close($fetch_query);

    if (!$urinalysis_data) {
        echo "Urinalysis data not found.";
        exit;
    }
}

// Handle form submission for editing urinalysis data
if (isset($_POST['edit-urinalysis'])) {
    // Sanitize inputs
    $urinalysis_id = sanitize($connection, $_POST['urinalysis_id']);
    $patient_name = sanitize($connection, $_POST['patient_name']);
    $date_time = sanitize($connection, $_POST['date_time']);
    $color = sanitize($connection, $_POST['color']);
    $transparency = sanitize($connection, $_POST['transparency']);
    $reaction = sanitize($connection, $_POST['reaction']);
    $protein = sanitize($connection, $_POST['protein']);
    $glucose = sanitize($connection, $_POST['glucose']);
    $specific_gravity = sanitize($connection, $_POST['specific_gravity']);
    $ketone = sanitize($connection, $_POST['ketone']);
    $urobilinogen = sanitize($connection, $_POST['urobilinogen']);
    $pregnancy_test = sanitize($connection, $_POST['pregnancy_test']);
    $pus_cells = sanitize($connection, $_POST['pus_cells']);
    $red_blood_cells = sanitize($connection, $_POST['red_blood_cells']);
    $epithelial_cells = sanitize($connection, $_POST['epithelial_cells']);
    $a_urates_a_phosphates = sanitize($connection, $_POST['a_urates_a_phosphates']);
    $mucus_threads = sanitize($connection, $_POST['mucus_threads']);
    $bacteria = sanitize($connection, $_POST['bacteria']);
    $calcium_oxalates = sanitize($connection, $_POST['calcium_oxalates']);
    $uric_acid_crystals = sanitize($connection, $_POST['uric_acid_crystals']);
    $pus_cells_clumps = sanitize($connection, $_POST['pus_cells_clumps']);
    $coarse_granular_cast = sanitize($connection, $_POST['coarse_granular_cast']);
    $hyaline_cast = sanitize($connection, $_POST['hyaline_cast']);

    // Fetch patient information using prepared statement
    $patient_query = mysqli_prepare($connection, "SELECT patient_id, gender, dob, patient_type FROM tbl_patient WHERE CONCAT(first_name, ' ', last_name) = ?");
    mysqli_stmt_bind_param($patient_query, "s", $patient_name);
    mysqli_stmt_execute($patient_query);
    $patient_result = mysqli_stmt_get_result($patient_query);
    $row = mysqli_fetch_array($patient_result);
    mysqli_stmt_close($patient_query);

    $patient_id = $row['patient_id'];
    $gender = $row['gender'];
    $dob = $row['dob'];

    // Update urinalysis data using prepared statement
    $update_query = mysqli_prepare($connection, "UPDATE tbl_urinalysis SET patient_name = ?, dob = ?, gender = ?, date_time = ?, color = ?, transparency = ?, reaction = ?, protein = ?, glucose = ?, specific_gravity = ?, ketone = ?, urobilinogen = ?, pregnancy_test = ?, pus_cells = ?, red_blood_cells = ?, epithelial_cells = ?, a_urates_a_phosphates = ?, mucus_threads = ?, bacteria = ?, calcium_oxalates = ?, uric_acid_crystals = ?, pus_cells_clumps = ?, coarse_granular_cast = ?, hyaline_cast = ? WHERE urinalysis_id = ?");

    // Bind all parameters as strings
    mysqli_stmt_bind_param($update_query, "sssssssssssssssssssssssss", $patient_name, $dob, $gender, $date_time, $color, $transparency, $reaction, $protein, $glucose, $specific_gravity, $ketone, $urobilinogen, $pregnancy_test, $pus_cells, $red_blood_cells, $epithelial_cells, $a_urates_a_phosphates, $mucus_threads, $bacteria, $calcium_oxalates, $uric_acid_crystals, $pus_cells_clumps, $coarse_granular_cast, $hyaline_cast, $urinalysis_id);

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
                    text: 'Urinalysis result updated successfully!',
                    confirmButtonColor: '#12369e'
                }).then(() => {
                    // Redirect to the relevant page or refresh
                    window.location.href = 'urinalysis.php'; // Adjust the URL as needed
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
                    text: 'Error updating the urinalysis result!',
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
                <h4 class="page-title">Edit Urinalysis Result</h4>
            </div>
            <div class="col-sm-8 text-right mb-3">
                <a href="urinalysis.php" class="btn btn-primary"><i class="fa fa-arrow-left"></i> Back</a>
            </div>
        </div>
        <div class="row">
            <div class="col-lg-10 offset-lg-1">
                <form method="post">
                    <div class="form-group row">
                        <div class="col-sm-6">
                            <label for="urinalysis_id">Urinalysis ID</label>
                            <input class="form-control" type="text" name="urinalysis_id" id="urinalysis_id" value="<?php echo htmlspecialchars($urinalysis_data['urinalysis_id']); ?>" readonly>
                        </div>
                        <div class="col-sm-6">
                            <label for="patient_name">Patient Name</label>
                            <input class="form-control" type="text" name="patient_name" id="patient_name" value="<?php echo htmlspecialchars($urinalysis_data['patient_name']); ?>" required>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="date_time">Date and Time</label>
                        <input type="datetime-local" class="form-control" name="date_time" id="date_time" value="<?php echo date('Y-m-d\TH:i', strtotime($urinalysis_data['date_time'])); ?>">
                    </div>
                    <h4 style="font-size: 18px;">Macroscopic</h4>
                    <div class="form-group">
                        <label for="color">Color</label>
                        <input class="form-control" type="text" name="color" id="color" value="<?php echo htmlspecialchars($urinalysis_data['color']); ?>">
                    </div>
                    <div class="form-group">
                        <label for="transparency">Transparency</label>
                        <input class="form-control" type="text" name="transparency" id="transparency" value="<?php echo htmlspecialchars($urinalysis_data['transparency']); ?>">
                    </div>
                    <div class="form-group">
                        <label for="reaction">Reaction (pH)</label>
                        <input class="form-control" type="text" name="reaction" id="reaction" value="<?php echo htmlspecialchars($urinalysis_data['reaction']); ?>">
                    </div>
                    <div class="form-group">
                        <label for="protein">Protein</label>
                        <input class="form-control" type="text" name="protein" id="protein" value="<?php echo htmlspecialchars($urinalysis_data['protein']); ?>">
                    </div>
                    <div class="form-group">
                        <label for="glucose">Glucose</label>
                        <input class="form-control" type="text" name="glucose" id="glucose" value="<?php echo htmlspecialchars($urinalysis_data['glucose']); ?>">
                    </div>
                    <div class="form-group">
                        <label for="specific_gravity">Specific Gravity</label>
                        <input class="form-control" type="text" name="specific_gravity" id="specific_gravity" value="<?php echo htmlspecialchars($urinalysis_data['specific_gravity']); ?>">
                    </div>
                    <div class="form-group">
                        <label for="ketone">Ketone</label>
                        <input class="form-control" type="text" name="ketone" id="ketone" value="<?php echo htmlspecialchars($urinalysis_data['ketone']); ?>">
                    </div>
                    <div class="form-group">
                        <label for="urobilinogen">Urobilinogen</label>
                        <input class="form-control" type="text" name="urobilinogen" id="urobilinogen" value="<?php echo htmlspecialchars($urinalysis_data['urobilinogen']); ?>">
                    </div>
                    <div class="form-group">
                        <label for="pregnancy_test">Pregnancy Test</label>
                        <input class="form-control" type="text" name="pregnancy_test" id="pregnancy_test" value="<?php echo htmlspecialchars($urinalysis_data['pregnancy_test']); ?>">
                    </div>
                    <h4 style="font-size: 20x;">Microscopic</h4>
                    <div class="form-group">
                        <label for="pus_cells">Pus Cells</label>
                        <input class="form-control" type="text" name="pus_cells" id="pus_cells" value="<?php echo htmlspecialchars($urinalysis_data['pus_cells']); ?>">
                    </div>
                    <div class="form-group">
                        <label for="red_blood_cells">Red Blood Cells</label>
                        <input class="form-control" type="text" name="red_blood_cells" id="red_blood_cells" value="<?php echo htmlspecialchars($urinalysis_data['red_blood_cells']); ?>">
                    </div>
                    <div class="form-group">
                        <label for="epithelial_cells">Epithelial Cells</label>
                        <input class="form-control" type="text" name="epithelial_cells" id="epithelial_cells" value="<?php echo htmlspecialchars($urinalysis_data['epithelial_cells']); ?>">
                    </div>
                    <div class="form-group">
                        <label for="a_urates_a_phosphates">A Urates/A Phosphates</label>
                        <input class="form-control" type="text" name="a_urates_a_phosphates" id="a_urates_a_phosphates" value="<?php echo htmlspecialchars($urinalysis_data['a_urates_a_phosphates']); ?>">
                    </div>
                    <div class="form-group">
                        <label for="mucus_threads">Mucus Threads</label>
                        <input class="form-control" type="text" name="mucus_threads" id="mucus_threads" value="<?php echo htmlspecialchars($urinalysis_data['mucus_threads']); ?>">
                    </div>
                    <div class="form-group">
                        <label for="bacteria">Bacteria</label>
                        <input class="form-control" type="text" name="bacteria" id="bacteria" value="<?php echo htmlspecialchars($urinalysis_data['bacteria']); ?>">
                    </div>
                    <div class="form-group">
                        <label for="calcium_oxalates">Calcium Oxalates</label>
                        <input class="form-control" type="text" name="calcium_oxalates" id="calcium_oxalates" value="<?php echo htmlspecialchars($urinalysis_data['calcium_oxalates']); ?>">
                    </div>
                    <div class="form-group">
                        <label for="uric_acid_crystals">Uric Acid Crystals</label>
                        <input class="form-control" type="text" name="uric_acid_crystals" id="uric_acid_crystals" value="<?php echo htmlspecialchars($urinalysis_data['uric_acid_crystals']); ?>">
                    </div>
                    <div class="form-group">
                        <label for="pus_cells_clumps">Pus Cells Clumps</label>
                        <input class="form-control" type="text" name="pus_cells_clumps" id="pus_cells_clumps" value="<?php echo htmlspecialchars($urinalysis_data['pus_cells_clumps']); ?>">
                    </div>
                    <div class="form-group">
                        <label for="coarse_granular_cast">Coarse Granular Cast</label>
                        <input class="form-control" type="text" name="coarse_granular_cast" id="coarse_granular_cast" value="<?php echo htmlspecialchars($urinalysis_data['coarse_granular_cast']); ?>">
                    </div>
                    <div class="form-group">
                        <label for="hyaline_cast">Hyaline Cast</label>
                        <input class="form-control" type="text" name="hyaline_cast" id="hyaline_cast" value="<?php echo htmlspecialchars($urinalysis_data['hyaline_cast']); ?>">
                    </div>
                    <div class="text-center mt-4">
                        <button class="btn btn-primary submit-btn" name="edit-urinalysis"><i class="fas fa-save mr-2"></i>Update Result</button>
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
