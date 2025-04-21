<?php
session_start();
ob_start();

if (empty($_SESSION['name'])) {
    header('location:index.php');
    exit; // Stop further execution
}

include('header.php');
include('includes/connection.php');

// Function to sanitize user inputs
function sanitize($connection, $input) {
    return mysqli_real_escape_string($connection, htmlspecialchars(strip_tags(trim($input)), ENT_QUOTES, 'UTF-8'));
}

// Initialize a message variable
$msg = null;

$role = isset($_SESSION['role']) ? $_SESSION['role'] : null;

// Process Diagnosis Form Submission
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['diagnosis']) && isset($_POST['inpatientIdDiagnosis'])) {
    $diagnosis = sanitize($connection, $_POST['diagnosis']);
    $inpatientId = sanitize($connection, $_POST['inpatientIdDiagnosis']);

    $update_query = $connection->prepare("UPDATE tbl_inpatient_record SET diagnosis = ? WHERE inpatient_id = ?");
    $update_query->bind_param("ss", $diagnosis, $inpatientId);
    $msg = $update_query->execute() ? "Diagnosis added successfully." : "Error adding diagnosis.";
}

// Process Treatment (Selected Medicines)
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['selectedMedicines']) && isset($_POST['inpatientIdTreatment'])) {
    $inpatientId = sanitize($connection, $_POST['inpatientIdTreatment']);
    $selectedMedicines = json_decode($_POST['selectedMedicines'], true);

    if ($selectedMedicines) {
        foreach ($selectedMedicines as $medicine) {
            $medicineId = sanitize($connection, $medicine['id']);
            $medicineName = sanitize($connection, $medicine['name']);
            $medicineBrand = sanitize($connection, $medicine['brand']);
            $quantity = intval($medicine['quantity']);
            $price = floatval($medicine['price']);
            $totalPrice = $quantity * $price;

            $insertQuery = $connection->prepare("
                INSERT INTO tbl_treatment (inpatient_id, patient_id, patient_name, medicine_name, medicine_brand, total_quantity, price, total_price, treatment_date)
                SELECT inpatient_id, patient_id, patient_name, ?, ?, ?, ?, ?, NOW()
                FROM tbl_inpatient_record
                WHERE inpatient_id = ?
            ");
            $insertQuery->bind_param("ssssss", $medicineName, $medicineBrand, $quantity, $price, $totalPrice, $inpatientId);
            if (!$insertQuery->execute()) {
                $msg = "Error inserting treatment: " . $connection->error;
                break;
            }

            $updateMedicineQuery = $connection->prepare("UPDATE tbl_medicines SET quantity = quantity - ? WHERE id = ?");
            $updateMedicineQuery->bind_param("is", $quantity, $medicineId);
            if (!$updateMedicineQuery->execute()) {
                $msg = "Error updating medicine quantity: " . $connection->error;
                break;
            }

            $updateInpatientQuery = $connection->prepare("
                UPDATE tbl_inpatient_record
                SET 
                    medicine_name = IF(medicine_name IS NULL OR medicine_name = '', ?, CONCAT(medicine_name, ', ', ?)),
                    medicine_brand = IF(medicine_brand IS NULL OR medicine_brand = '', ?, CONCAT(medicine_brand, ', ', ?)),
                    total_quantity = IF(total_quantity IS NULL, ?, total_quantity + ?)
                WHERE inpatient_id = ?
            ");
            $updateInpatientQuery->bind_param("sssssis", $medicineName, $medicineName, $medicineBrand, $medicineBrand, $quantity, $quantity, $inpatientId);
            if (!$updateInpatientQuery->execute()) {
                $msg = "Error updating inpatient record: " . $connection->error;
                break;
            }
        }

        if (!isset($msg)) {
            $msg = "Treatment added successfully.";
        }
    } else {
        $msg = "Error: Invalid medicines data.";
    }
}

// Process Patient Form Submission
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['patientId'])) {
    $patientId = sanitize($connection, $_POST['patientId']);

    // First check if patient exists with null discharge_date
    $check_query = $connection->prepare("
        SELECT r.* 
        FROM tbl_inpatient_record r
        JOIN tbl_inpatient i ON r.inpatient_id = i.inpatient_id
        WHERE r.patient_name = (SELECT patient_name FROM tbl_inpatient WHERE id = ?)
        AND i.discharge_date IS NULL
    ");
    $check_query->bind_param("s", $patientId);
    $check_query->execute();
    $check_result = $check_query->get_result();

    if ($check_result->num_rows > 0) {
        echo "<script>
            Swal.fire({
                title: 'Warning!',
                text: 'This patient already has an active record without a discharge date.',
                icon: 'warning',
                confirmButtonColor: '#12369e',
                confirmButtonText: 'OK'
            });
        </script>";
    } else {
        // Proceed with existing patient insertion logic
        $patient_query = $connection->prepare("SELECT * FROM tbl_inpatient WHERE id = ? AND discharge_date IS NULL");
        $patient_query->bind_param("s", $patientId);
        $patient_query->execute();
        $patient_result = $patient_query->get_result();
        $patient = $patient_result->fetch_assoc();

        if ($patient) {
            $inpatient_id = $patient['inpatient_id'];
            $patient_id = $patient['patient_id'];
            $name = sanitize($connection, $patient['patient_name']);
            $gender = sanitize($connection, $patient['gender']);
            $dob = sanitize($connection, $patient['dob']);
            $room_type = sanitize($connection, $patient['room_type']);
            $room_number = sanitize($connection, $patient['room_number']);
            $bed_number = sanitize($connection, $patient['bed_number']);
            $admission_date = sanitize($connection, $patient['admission_date']);
            $doctor_incharge = "";

            $insert_query = $connection->prepare("
                INSERT INTO tbl_inpatient_record (
                    inpatient_id, patient_id, patient_name, gender, dob, doctor_incharge, admission_date, room_type, room_number, bed_number
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $insert_query->bind_param("ssssssssss", $inpatient_id, $patient_id, $name, $gender, $dob, $doctor_incharge, $admission_date, $room_type, $room_number, $bed_number);
            $insert_query->execute();

            echo "<script>
                Swal.fire({
                    title: 'Success!',
                    text: 'Patient record added successfully!',
                    icon: 'success',
                    confirmButtonColor: '#12369e',
                    confirmButtonText: 'OK'
                }).then(() => {
                    window.location.href = 'inpatient-record.php';
                });
            </script>";
            exit;
        } else {
            echo "<script>
                Swal.fire({
                    title: 'Error!',
                    text: 'Patient not found. Please check the Patient ID.',
                    icon: 'error',
                    confirmButtonColor: '#12369e',
                    confirmButtonText: 'OK'
                });
            </script>";
        }
    }
}

// Process Assigning Doctor Form
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['inpatientIdDoctor']) && isset($_POST['doctorId'])) {
    $inpatientId = sanitize($connection, $_POST['inpatientIdDoctor']);
    $doctorId = sanitize($connection, $_POST['doctorId']);

    $doctor_query = $connection->prepare("SELECT first_name, last_name FROM tbl_employee WHERE id = ?");
    $doctor_query->bind_param("s", $doctorId);
    $doctor_query->execute();
    $doctor_result = $doctor_query->get_result();
    $doctor = $doctor_result->fetch_assoc();
    $doctor_name = sanitize($connection, $doctor['first_name'] . ' ' . $doctor['last_name']);

    $update_query = $connection->prepare("UPDATE tbl_inpatient_record SET doctor_incharge = ? WHERE inpatient_id = ?");
    $update_query->bind_param("ss", $doctor_name, $inpatientId);

    $msg = $update_query->execute() ? "Doctor assigned successfully." : "Error assigning doctor.";
}

ob_end_flush();
?>

<div class="page-wrapper">
    <div class="content">
        <div class="row">
            <div class="col-sm-4 col-3">
                <h4 class="page-title">Inpatient Record</h4>
            </div>
            <?php if ($_SESSION['role'] == 1 || $_SESSION['role'] == 3): ?>
                <div class="col-sm-10 col-9 m-b-20">
                    <form method="POST" action="inpatient-record.php" id="addPatientForm" class="form-inline">
                        <div class="input-group w-50">
                            <div class="input-group-prepend">
                                <span class="input-group-text">
                                    <i class="fas fa-search"></i> <!-- Search icon -->
                                </span>
                            </div>
                            <input
                                type="text"
                                class="form-control search-input"
                                id="patientSearchInput"
                                name="patientSearchInput"
                                placeholder="Enter Patient"
                                onkeyup="searchPatients()">
                            <div class="input-group-append">
                                <button type="submit" class="btn btn-outline-secondary" id="addPatientBtn" disabled>Add</button>
                            </div>
                        </div>
                        <input type="hidden" name="patientId" id="patientId">
                    </form>
                    <ul id="searchResults" class="list-group mt-2" style="max-height: 200px; overflow-y: auto; border: 1px solid #ccc; border-radius: 5px; display: none;"></ul>
                </div>
            <?php endif; ?>
        </div>
        <div class="table-responsive">
            <div class="sticky-search">
            <h5 class="font-weight-bold mb-2">Search Patient:</h5>
                <div class="input-group mb-3">
                    <div class="position-relative w-100">
                        <!-- Search Icon -->
                        <i class="fa fa-search position-absolute text-secondary" style="top: 50%; left: 12px; transform: translateY(-50%);"></i>
                        <!-- Input Field -->
                        <input class="form-control" type="text" id="inpatientSearchInput" onkeyup="filterInpatients()" placeholder="Search" style="padding-left: 35px; padding-right: 35px;">
                        <!-- Clear Button -->
                        <button class="position-absolute border-0 bg-transparent text-secondary" type="button" onclick="clearSearch()" style="top: 50%; right: 10px; transform: translateY(-50%);">
                            <i class="fa fa-times"></i>
                        </button>
                    </div>
                </div>
            </div>
        </div>
        <div class="table-responsive">
            <table class="datatable table table-hover" id="inpatientTable">
                <thead style="background-color: #CCCCCC;">
                    <tr>
                        <th>Patient ID</th>
                        <th>Inpatient ID</th>
                        <th>Patient Name</th>
                        <th>Age</th>
                        <th>Gender</th>
                        <th>Doctor Incharge</th>
                        <th>Lab Result</th>
                        <th>Radiographic Images</th>
                        <th>Diagnosis</th>
                        <th>Medications</th>
                        <th>Room Type</th>
                        <th>Room Number</th>
                        <th>Bed Number</th>
                        <th>Admission Date and Time</th>
                        <th>Discharge Date and Time</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                <?php
                    // Securely handle GET data for the deletion process
                    if (isset($_GET['ids'])) {
                        // Sanitize the incoming GET parameter to ensure it's safe to use in the query
                        $id = intval($_GET['ids']);  // Using intval to sanitize and ensure it's an integer
                        
                        // Perform the delete operation using prepared statements to prevent SQL injection
                        $update_query = mysqli_prepare($connection, "UPDATE tbl_inpatient_record SET deleted = 1 WHERE id = ?");
                        mysqli_stmt_bind_param($update_query, 'i', $id);
                        mysqli_stmt_execute($update_query);
                    }

                    // Fetch inpatient data using a safe query
                    if ($_SESSION['role'] == 2) {
                        $fetch_query = $connection->prepare("
                            SELECT r.*, i.discharge_date, 
                                GROUP_CONCAT(CONCAT(t.medicine_name, ' (', t.medicine_brand, ') - ', t.total_quantity, ' pcs') SEPARATOR '<br>') AS treatments
                            FROM tbl_inpatient_record r
                            LEFT JOIN tbl_inpatient i ON r.inpatient_id = i.inpatient_id
                            LEFT JOIN tbl_treatment t ON r.inpatient_id = t.inpatient_id
                            WHERE r.deleted = 0 AND r.doctor_incharge = ?
                            GROUP BY r.inpatient_id
                        ");
                        $fetch_query->bind_param("s", $_SESSION['name']);
                    } else {
                        $fetch_query = $connection->prepare("
                            SELECT r.*, i.discharge_date, 
                                GROUP_CONCAT(CONCAT(t.medicine_name, ' (', t.medicine_brand, ') - ', t.total_quantity, ' pcs') SEPARATOR '<br>') AS treatments
                            FROM tbl_inpatient_record r
                            LEFT JOIN tbl_inpatient i ON r.inpatient_id = i.inpatient_id
                            LEFT JOIN tbl_treatment t ON r.inpatient_id = t.inpatient_id
                            WHERE r.deleted = 0
                            GROUP BY r.inpatient_id
                        ");
                    }
                    $fetch_query->execute();
                    $result = $fetch_query->get_result();
                    while ($row = $result->fetch_assoc()) {
                        $dob = $row['dob'];
                        $date = str_replace('/', '-', $dob);
                        $dob = date('Y-m-d', strtotime($date));
                        $year = (date('Y') - date('Y', strtotime($dob)));

                        $admission_date_time = date('F d, Y g:i A', strtotime($row['admission_date']));
                        $discharge_date_time = ($row['discharge_date']) ? date('F d, Y g:i A', strtotime($row['discharge_date'])) : 'N/A';

                        // Combine medicine name, brand, and quantity
                        $treatmentDetails = $row['treatments'] ?: 'No treatments added';
                    ?>
                        <tr>
                            <td><?php echo htmlspecialchars($row['patient_id']); ?></td>
                            <td><?php echo htmlspecialchars($row['inpatient_id']); ?></td>
                            <td><?php echo htmlspecialchars($row['patient_name']); ?></td>
                            <td><?php echo $year; ?></td>
                            <td><?php echo htmlspecialchars($row['gender']); ?></td>
                            <td>
                                <?php if (empty($row['doctor_incharge'])) { ?>
                                    <button class="btn btn-primary btn-sm select-doctor-btn" data-toggle="modal" data-target="#doctorModal" data-id="<?php echo htmlspecialchars($row['inpatient_id']); ?>">Select Doctor</button>
                                <?php } else { ?>
                                    <?php echo htmlspecialchars($row['doctor_incharge']); ?>
                                <?php } ?>
                            </td>
                            <td>
                                <?php if ($_SESSION['role'] == 2 ||  $_SESSION['role'] == 1) { ?>
                                <form action="generate-result.php" method="get">
                                    <input type="hidden" name="patient_id" value="<?php echo htmlspecialchars($row['patient_id']); ?>">
                                    <button class="btn btn-primary btn-sm custom-btn" type="submit">
                                        <i class="fa fa-file-pdf-o m-r-5"></i> View Result
                                    </button>
                                </form>
                                <?php } ?>
                            </td>
                            <?php if ($_SESSION['role'] == 2 || $_SESSION['role'] == 1) { ?>
                                <td id="img-btn-<?php echo $row['patient_id']; ?>">
                                    <?php 
                                    $rad_query = $connection->prepare("SELECT COUNT(*) as count FROM tbl_radiology WHERE patient_id = ? AND radiographic_image IS NOT NULL AND radiographic_image != '' AND deleted = 0");
                                    $rad_query->bind_param("s", $row['patient_id']);
                                    $rad_query->execute();
                                    $rad_result = $rad_query->get_result();
                                    $rad_count = $rad_result->fetch_assoc()['count'];
                                    if ($rad_count > 0) {
                                    ?>
                                        <button class="btn btn-primary custom-btn" onclick="showRadiologyImages('<?php echo $row['patient_id']; ?>')">
                                            <i class="fa fa-image m-r-5"></i> View Images
                                        </button>
                                    <?php } ?>
                                </td>
                            <?php } ?>
                            <td><?php echo htmlspecialchars($row['diagnosis']); ?></td>
                            <td>
                                <?php if (!empty($row['treatments'])): ?>
                                    <!-- Display Treatment Details if Present -->
                                    <div><?php echo nl2br(strip_tags($row['treatments'], '<br>')); ?></div>
                                <?php else: ?>
                                    <?php if ($_SESSION['role'] == 10) { ?>
                                    <button class="btn btn-primary btn-sm treatment-btn mt-2" data-toggle="modal" data-target="#treatmentModal" data-id="<?php echo htmlspecialchars($row['inpatient_id']); ?>">
                                        <i class="fa fa-stethoscope m-r-5"></i> Add/Edit Treatments
                                    </button>
                                    <?php } ?>
                                <?php endif; ?>
                            </td>
                            <td><?php echo htmlspecialchars($row['room_type']); ?></td>
                            <td><?php echo htmlspecialchars($row['room_number']); ?></td>
                            <td><?php echo htmlspecialchars($row['bed_number']); ?></td>
                            <td><?php echo htmlspecialchars($admission_date_time); ?></td>
                            <td><?php echo htmlspecialchars($discharge_date_time); ?></td>
                            <td class="text-right">
                                <div class="dropdown dropdown-action">
                                    <a href="#" class="action-icon dropdown-toggle" data-toggle="dropdown" aria-expanded="false"><i class="fa fa-ellipsis-v"></i></a>
                                    <div class="dropdown-menu dropdown-menu-right">
                                        <?php if ($_SESSION['role'] == 2 && $_SESSION['name'] == $row['doctor_incharge']) { ?>
                                            <button class="dropdown-item diagnosis-btn" data-toggle="modal" data-target="#diagnosisModal" data-id="<?php echo htmlspecialchars($row['inpatient_id']); ?>" <?php echo !empty($row['diagnosis']) ? 'disabled' : ''; ?>><i class="fa fa-stethoscope m-r-5"></i> Diagnosis</button>
                                        <?php } ?>
                                        <?php if ($_SESSION['role'] == 1) { ?>
                                            <a class="dropdown-item" href="edit-inpatient-record.php?id=<?php echo htmlspecialchars($row['id']); ?>"><i class="fa fa-pencil m-r-5"></i> Edit</a>
                                            <a class="dropdown-item" href="#" onclick="return confirmDelete('<?php echo $row['id']; ?>')"><i class="fa fa-trash-o m-r-5"></i> Delete</a>
                                        <?php } ?>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    <?php } ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div id="treatmentModal" class="modal fade" tabindex="-1" role="dialog" aria-labelledby="treatmentModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="treatmentModalLabel">Select Medicines</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <form id="medicineSelectionForm" method="POST" action="inpatient-record.php">
                    <!-- Hidden input to pass inpatient ID -->
                    <input type="hidden" name="inpatientIdTreatment" id="inpatientIdTreatment">
                    
                    <!-- Medicine Search Section -->
                    <div class="form-group">
                        <label for="medicineSearchInput">Search Medicines</label>
                        <input
                            type="text"
                            class="form-control"
                            id="medicineSearchInput"
                            placeholder="Enter medicine name or brand"
                            onkeyup="searchMedicines()"
                        >
                    </div>
                    <div class="table-responsive mb-4">
                        <table class="table table-hover table-striped">
                            <thead style="background-color: #CCCCCC;">
                                <tr>
                                    <th>Medicine Name</th>
                                    <th>Medicine Brand</th>
                                    <th>Drug Classification</th>
                                    <th>Expiration Date</th>
                                    <th>Available Quantity</th>
                                    <th>Price</th>
                                    <th>Quantity</th>
                                </tr>
                            </thead>
                            <tbody id="medicineSearchResults">
                                <!-- Search results will populate here -->
                            </tbody>
                        </table>
                    </div>

                    <!-- Selected Medicines Section -->
                    <h5>Selected Medicines</h5>
                    <ul id="selectedMedicinesList" class="list-group">
                        <!-- Selected medicines will populate here -->
                    </ul>
                    <input type="hidden" name="selectedMedicines" id="selectedMedicines">
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                <button type="submit" class="btn btn-primary" form="medicineSelectionForm">Save Treatment</button>
            </div>
        </div>
    </div>
</div>

<!-- Diagnosis Modal -->
<div id="diagnosisModal" class="modal fade" role="dialog">
    <div class="modal-dialog">
        <!-- Modal content-->
        <div class="modal-content">
            <div class="modal-header">
                <h4 class="modal-title">Diagnosis</h4>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body">
                <!-- Form for diagnosis -->
                <form id="diagnosisForm" method="post" action="inpatient-record.php">
                    <div class="form-group">
                        <label for="diagnosis">Enter Diagnosis:</label>
                        <input type="text" class="form-control" id="diagnosis" name="diagnosis">
                    </div>
                    <input type="hidden" id="inpatientIdDiagnosis" name="inpatientIdDiagnosis">
                    <button type="submit" class="btn btn-primary">Submit</button>
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Doctor Modal -->
<div id="doctorModal" class="modal fade" role="dialog">
    <div class="modal-dialog">
        <!-- Modal content-->
        <div class="modal-content">
            <div class="modal-header">
                <h4 class="modal-title">Select Doctor</h4>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body">
                <!-- List of doctors -->
                <form id="doctorForm" method="post" action="inpatient-record.php">
                    <input type="hidden" id="inpatientIdDoctor" name="inpatientIdDoctor">
                    <div class="form-group">
                        <label for="doctor">Select Doctor:</label>
                        <select class="form-control" id="doctor" name="doctor">
                            <?php
                            // Fetch doctors from tbl_employee where role = 2 (doctor)
                            $doctor_query = mysqli_query($connection, "SELECT id, first_name, last_name FROM tbl_employee WHERE role = 2");
                            while ($doctor = mysqli_fetch_array($doctor_query)) {
                                $doctor_name = $doctor['first_name'] . ' ' . $doctor['last_name'];
                                echo "<option value='".$doctor['id']."'>".$doctor_name."</option>";
                            }
                            ?>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-primary">Assign Doctor</button>
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Radiology Images Grid Modal -->
<div class="modal fade" id="radiologyModal" tabindex="-1" role="dialog" aria-labelledby="radiologyModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="radiologyModalLabel">Radiographic Images</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div id="radiologyImagesContainer" class="row">
                    <!-- Images will be loaded here -->
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Image Viewer Modal -->
<div class="modal fade" id="imageViewerModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="imageViewerTitle">Radiographic Image</h5>
            </div>
            <div class="modal-body p-0">
                <div class="image-container" style="height: 80vh;">
                    <img id="viewedImage" src="" class="img-fluid" style="max-width: 100%; max-height: 100%; transform-origin: center center;">
                </div>
            </div>
            <div class="modal-footer d-flex justify-content-between align-items-center">
                <div class="zoom-controls btn-group btn-group-sm">
                    <button class="btn btn-outline-secondary zoom-out-btn" title="Zoom Out">
                        <i class="fas fa-search-minus"></i>
                    </button>
                    <button class="btn btn-outline-secondary zoom-reset-btn" title="Reset Zoom">
                        <i class="fas fa-expand"></i>
                    </button>
                    <button class="btn btn-outline-secondary zoom-in-btn" title="Zoom In">
                        <i class="fas fa-search-plus"></i>
                    </button>
                </div>
                <div class="rotation-controls btn-group btn-group-sm">
                    <button class="btn btn-outline-secondary rotate-left-btn" title="Rotate Left">
                        <i class="fas fa-undo"></i>
                    </button>
                    <button class="btn btn-outline-secondary rotate-right-btn" title="Rotate Right">
                        <i class="fas fa-redo"></i>
                    </button>
                </div>
                <div class="ml-auto">
                    <button type="button" class="btn btn-primary" data-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Success and Error Alerts -->
<div id="successAlert"></div>
<div id="errorAlert"></div>

<?php
include('footer.php');
?>
<script>
function confirmDelete(id) {
    return Swal.fire({
        title: 'Delete Patient Record?',
        text: 'Are you sure you want to delete this Patient record? This action cannot be undone!',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#12369e',
        confirmButtonText: 'Yes, delete it!'
    }).then((result) => {
        if (result.isConfirmed) {
            window.location.href = 'inpatient-record.php?ids=' + id;
        }
    });
}

</script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
document.querySelector('form').addEventListener('submit', function(event) {
    event.preventDefault(); // Prevent form from submitting immediately

    Swal.fire({
        title: 'Processing...',
        text: 'Inserting inpatient record...',
        allowOutsideClick: false,
        showConfirmButton: false,
        willOpen: () => {
            Swal.showLoading();
        }
    });

    // Submit the form after showing the loading message
    setTimeout(() => {
        event.target.submit();
    }, 1000); // Adjust the timeout as needed
});
</script>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    $(document).on('click', '.diagnosis-btn', function(){
        var inpatientId = $(this).data('id');
        $('#inpatientIdDiagnosis').val(inpatientId); // Update the ID of the hidden input field
    });
    $(document).on('click', '.treatment-btn', function(){
        var inpatientId = $(this).data('id');
        $('#inpatientIdTreatment').val(inpatientId); // Update the ID of the hidden input field
    });
</script>

<script>
let selectedMedicines = [];

// Function to search medicines
function searchMedicines() {
    const query = document.getElementById('medicineSearchInput').value.trim();

    if (query.length > 2) {
        $.ajax({
            url: 'search-medicines.php',
            type: 'GET',
            data: { query },
            success: function (data) {
                $('#medicineSearchResults').html(data); // Populate search results
            },
            error: function () {
                alert('Error fetching medicines. Please try again later.');
            }
        });
    } else {
        $('#medicineSearchResults').html('<tr><td colspan="7">Please enter at least 3 characters to search.</td></tr>');
    }
}

// Function to add medicine to the selected list
function addMedicineToList(id, name, brand, category, availableQuantity, price, expiration_date, event) {
    if (event) {
        event.preventDefault();
        event.stopPropagation();
    }

    const quantityInput = parseInt(document.getElementById(`quantityInput-${id}`).value, 10);

    if (quantityInput <= 0 || quantityInput > availableQuantity) {
        alert('Invalid quantity. Please try again.');
        return;
    }

    // Check if the medicine is already in the list
    const existingMedicineIndex = selectedMedicines.findIndex(medicine => medicine.id === id);

    if (existingMedicineIndex !== -1) {
        // If the medicine exists, update the quantity
        selectedMedicines[existingMedicineIndex].quantity = quantityInput;
    } else {
        // Add new medicine
        const medicine = {
            id,
            name,
            brand,
            category,
            quantity: quantityInput,
            price: parseFloat(price),
            expiration_date,
        };
        selectedMedicines.push(medicine);
    }

    // Update the UI
    updateSelectedMedicinesUI();
}

// Function to update the selected medicines UI
function updateSelectedMedicinesUI() {
    $('#selectedMedicinesList').html('');

    selectedMedicines.forEach((medicine, index) => {
        const listItem = `
            <li class="list-group-item d-flex justify-content-between align-items-center">
                <div>
                    <strong>${medicine.name} (${medicine.brand}) (${medicine.category})</strong> - 
                    ${medicine.quantity} pcs @ ${medicine.price} PHP each 
                    <small>(Exp: ${medicine.expiration_date})</small>
                </div>
                <button 
                    type="button" 
                    class="btn btn-danger btn-sm" 
                    onclick="removeMedicineFromList(${index})">
                    Remove
                </button>
            </li>`;
        $('#selectedMedicinesList').append(listItem);
    });

    $('#selectedMedicines').val(JSON.stringify(selectedMedicines));
}

// Function to remove a medicine from the selected list
function removeMedicineFromList(index) {
    selectedMedicines.splice(index, 1); // Remove the medicine by index
    updateSelectedMedicinesUI();
}

// Reset selected medicines when opening the modal
$('.treatment-btn').on('click', function () {
    const inpatientId = $(this).data('id');
    $('#inpatientIdTreatment').val(inpatientId);
    selectedMedicines = []; // Clear the array
    $('#selectedMedicinesList').html(''); // Clear the UI
    $('#selectedMedicines').val(''); // Clear the hidden input
});

</script>

<script>
    function clearSearch() {
        document.getElementById("inpatientSearchInput").value = '';
        filterInpatients();
    }
    function filterInpatients() {
        var input = document.getElementById("inpatientSearchInput").value;
        
        $.ajax({
            url: 'fetch_inpatient_record.php',
            method: 'GET',
            data: { query: input },
            success: function(response) {
                var data = JSON.parse(response);
                updateTable(data);
            }
        });
    }

    function updateTable(data) {
        var tbody = $('#inpatientTable tbody');
        tbody.empty();
        
        data.forEach(function(row) {
            // Doctor In-Charge button/display - show for role 3 (staff/nurse)
            var doctorButton = row.doctor_incharge ? 
                row.doctor_incharge : 
                (role == 3 ? 
                    `<button class="btn btn-primary btn-sm select-doctor-btn" 
                        data-toggle="modal" 
                        data-target="#doctorModal" 
                        data-id="${row.inpatient_id}">
                        Select Doctor
                    </button>` : 
                    row.doctor_incharge || 'Not assigned'
                );

            // View Result button - show for role 2 (doctor)
            var viewResultButton = (role == 2 || role == 1) ? 
                `<form action="generate-result.php" method="get">
                    <input type="hidden" name="patient_id" value="${row.patient_id}">
                    <button class="btn btn-primary btn-sm custom-btn" type="submit">
                        <i class="fa fa-file-pdf-o m-r-5"></i> View Result
                    </button>
                </form>` : 
                '';

            // Radiographic Images button - show for role 2 (doctor) and 1 (admin)
            var radiologyButton = '';
            if ((role == 2 || role == 1) && row.has_radiology_images) {
                radiologyButton = `
                    <button class="btn btn-primary custom-btn" onclick="showRadiologyImages('${row.patient_id}')">
                        <i class="fa fa-image m-r-5"></i> View Images
                    </button>`;
            }

            // Treatment display - show for role 2 (doctor)
            var treatmentContent = row.treatments && row.treatments !== 'No treatments added' ? 
                `<div>${row.treatments}</div>` : 
                (role == 10 ? 
                    `<button class="btn btn-primary btn-sm treatment-btn mt-2" 
                        data-toggle="modal" 
                        data-target="#treatmentModal" 
                        data-id="${row.inpatient_id}">
                        <i class="fa fa-stethoscope m-r-5"></i> Add/Edit Treatments
                    </button>` : 
                    'No treatments'
                );

            // Action buttons based on role
            var actionButtons = '';
            if (role == 2 && doctor_name == row.doctor_incharge) {
                actionButtons += `
                    <button class="dropdown-item diagnosis-btn" 
                        data-toggle="modal" 
                        data-target="#diagnosisModal" 
                        data-id="${row.inpatient_id}" 
                        ${row.diagnosis ? 'disabled' : ''}>
                        <i class="fa fa-stethoscope m-r-5"></i> Diagnosis
                    </button>`;
            }
            if (role == 1 || role == 3) {
                actionButtons += `
                    <a class="dropdown-item" href="edit-inpatient-record.php?id=${row.id}">
                        <i class="fa fa-pencil m-r-5"></i> Edit
                    </a>`;
            }
            if (role == 1) {
                actionButtons += `
                    <a class="dropdown-item" href="#" onclick="return confirmDelete('${row.id}')">
                        <i class="fa fa-trash-o m-r-5"></i> Delete
                    </a>`;
            }

            // Build the table row
            tbody.append(`<tr>
                <td>${row.patient_id}</td>
                <td>${row.inpatient_id}</td>
                <td>${row.patient_name}</td>
                <td>${row.age}</td>
                <td>${row.gender}</td>
                <td>${doctorButton}</td>
                <td>${viewResultButton}</td>
                <td>${radiologyButton}</td>
                <td>${row.diagnosis}</td>
                <td>${treatmentContent}</td>
                <td>${row.room_type}</td>
                <td>${row.room_number}</td>
                <td>${row.bed_number}</td>
                <td>${row.admission_date}</td>
                <td>${row.discharge_date || 'N/A'}</td>
                <td class="text-right">
                    <div class="dropdown dropdown-action">
                        <a href="#" class="action-icon dropdown-toggle" data-toggle="dropdown" aria-expanded="false">
                            <i class="fa fa-ellipsis-v"></i>
                        </a>
                        <div class="dropdown-menu dropdown-menu-right">
                            ${actionButtons}
                        </div>
                    </div>
                </td>
            </tr>`);
        });
    }

    // Add these variables at the top of your script
    var role = <?php echo json_encode($_SESSION['role']); ?>;
    var doctor_name = <?php echo json_encode($_SESSION['name']); ?>;

    function searchPatients() {
        var input = document.getElementById("patientSearchInput").value;
        if (input.length < 2) {
            document.getElementById("searchResults").style.display = "none";
            document.getElementById("searchResults").innerHTML = "";
            return;
        }
        $.ajax({
            url: "search-ipt-record.php", // Backend script to fetch patients
            method: "GET",
            data: { query: input },
            success: function (data) {
                var results = document.getElementById("searchResults");
                results.innerHTML = data;
                results.style.display = "block";
            },
        });
    }

    // Select Patient from Search Results
    $(document).on("click", ".search-result", function () {
        var patientId = $(this).data("id");
        var patientName = $(this).text();

        $("#patientId").val(patientId); // Set the hidden input value
        $("#patientSearchInput").val(patientName); // Set input to selected patient name
        $("#addPatientBtn").prop("disabled", false); // Enable the Add button
        $("#searchResults").html("").hide(); // Clear and hide the dropdown
    });
</script>

<script>
// This function will open the modal and set the outpatient_id dynamically
$(document).on('click', '.select-doctor-btn', function() {
    var inpatientId = $(this).data('id');
    $('#inpatientIdDoctor').val(inpatientId);
    $('#doctorModal').modal('show');
});

// When the form for assigning doctor is submitted
$('#doctorForm').submit(function(e) {
    e.preventDefault(); // Prevent default form submission
    var inpatientId = $('#inpatientIdDoctor').val();
    var doctorId = $('#doctor').val();

    // Send the selected doctor to be updated in the database
    $.ajax({
        url: 'inpatient-record.php', // Ensure the PHP file is the correct one to process the form
        type: 'POST',
        data: {
            inpatientIdDoctor: inpatientId,
            doctorId: doctorId
        },
        success: function(response) {
            // Handle success, e.g., update the table row or show a success message
            location.reload(); // Reload the page to show the updated doctor in charge
        },
        error: function(xhr, status, error) {
            // Handle any errors
            alert('Error assigning doctor');
        }
    });
});

$('.dropdown-toggle').on('click', function (e) {
        var $el = $(this).next('.dropdown-menu');
        var isVisible = $el.is(':visible');
        
        // Hide all dropdowns
        $('.dropdown-menu').slideUp('400');
        
        // If this wasn't already visible, slide it down
        if (!isVisible) {
            $el.stop(true, true).slideDown('400');
        }
        
        // Close the dropdown if clicked outside of it
        $(document).on('click', function (e) {
            if (!$(e.target).closest('.dropdown').length) {
                $('.dropdown-menu').slideUp('400');
            }
        });
    });
</script>

<script>
    // Viewer state variables
    let currentZoom = 1;
    let currentRotation = 0;
    let isDragging = false;
    let startX, startY, translateX = 0, translateY = 0;

    function updateImageTransform() {
        const transform = `translate(${translateX}px, ${translateY}px) rotate(${currentRotation}deg) scale(${currentZoom})`;
        $('#viewedImage').css('transform', transform);
    }

    function openImageViewer(imageId, examType, imageSrc) {
        $('#imageViewerTitle').text(examType);
        $('#viewedImage').attr('src', imageSrc);
        currentZoom = 1;
        currentRotation = 0;
        translateX = 0;
        translateY = 0;
        updateImageTransform();
        $('#imageViewerModal').modal('show');
    }

    function showRadiologyImages(patientId) {
        $('#radiologyImagesContainer').html(`
            <div class="col-12 text-center py-5">
                <div class="spinner-border text-primary" role="status">
                    <span class="sr-only">Loading...</span>
                </div>
                <p class="mt-2">Loading images...</p>
            </div>
        `);
        $('#radiologyModal').modal('show');

        $.ajax({
            url: 'fetch-radiology-images.php',
            type: 'GET',
            data: { patient_id: patientId },
            dataType: 'json',
            success: function(data) {
                if (data.images && data.images.length > 0) {
                    let content = '';
                    data.images.forEach(image => {
                        content += `
                            <div class="col-md-4 mb-3">
                                <div class="card h-100">
                                    <img src="fetch-image.php?id=${image.id}" 
                                         class="card-img-top" 
                                         style="height: 200px; object-fit: cover; cursor: pointer"
                                         onclick="openImageViewer('${image.id}', '${image.exam_type.replace(/'/g, "\\'")}', 'fetch-image.php?id=${image.id}')"
                                         alt="Radiology Image">
                                    <div class="card-body">
                                        <h6 class="card-title mb-1">${image.exam_type}</h6>
                                        <p class="card-text small text-muted">${image.test_type}</p>
                                    </div>
                                </div>
                            </div>`;
                    });
                    $('#radiologyImagesContainer').html(content);
                } else {
                    $('#radiologyImagesContainer').html(`
                        <div class="col-12 text-center py-5">
                            <div class="text-muted">
                                <i class="fas fa-image fa-3x mb-3"></i>
                                <p>No radiographic images found for this patient.</p>
                            </div>
                        </div>
                    `);
                }
            },
            error: function(xhr, status, error) {
                console.error('Error fetching images:', error);
                $('#radiologyImagesContainer').html(`
                    <div class="col-12 text-center py-5">
                        <div class="text-danger">
                            <i class="fas fa-exclamation-circle fa-3x mb-3"></i>
                            <p>Failed to load radiographic images. Please try again.</p>
                        </div>
                    </div>
                `);
            }
        });
    }

    $(document).ready(function() {
        $('.zoom-in-btn').on('click', function() {
            currentZoom *= 1.2;
            updateImageTransform();
        });

        $('.zoom-out-btn').on('click', function() {
            currentZoom /= 1.2;
            if (currentZoom < 0.5) currentZoom = 0.5;
            updateImageTransform();
        });

        $('.zoom-reset-btn').on('click', function() {
            currentZoom = 1;
            currentRotation = 0;
            translateX = 0;
            translateY = 0;
            updateImageTransform();
        });

        $('.rotate-left-btn').on('click', function() {
            currentRotation -= 90;
            updateImageTransform();
        });

        $('.rotate-right-btn').on('click', function() {
            currentRotation += 90;
            updateImageTransform();
        });

        const imageContainer = $('.image-container');

        imageContainer.on('mousedown touchstart', function(e) {
            isDragging = true;
            startX = (e.type === 'mousedown') ? e.pageX : e.originalEvent.touches[0].pageX;
            startY = (e.type === 'mousedown') ? e.pageY : e.originalEvent.touches[0].pageY;
            e.preventDefault();
        });

        $(document).on('mousemove touchmove', function(e) {
            if (!isDragging) return;
            const currentX = (e.type === 'mousemove') ? e.pageX : e.originalEvent.touches[0].pageX;
            const currentY = (e.type === 'mousemove') ? e.pageY : e.originalEvent.touches[0].pageY;
            translateX += (currentX - startX);
            translateY += (currentY - startY);
            startX = currentX;
            startY = currentY;
            updateImageTransform();
            e.preventDefault();
        });

        $(document).on('mouseup touchend', function() {
            isDragging = false;
        });
    });
</script> 
    
<style>
.dropdown-item {
    padding: 7px 15px;
    color: #333;
}

.dropdown-item:hover {
    background-color: #f8f9fa;
    color: #12369e;
}

.dropdown-item i {
    margin-right: 8px;
    color: #777;
}

.dropdown-item:hover i {
    color: #12369e;
}  
.sticky-search {
    position: sticky;
    left: 0;
    z-index: 100;
    width: 100%;
}

.btn-outline-primary {
    background-color:rgb(252, 252, 252);
    color: gray;
    border: 1px solid rgb(228, 228, 228);
}
.btn-outline-primary:hover {
    background-color: #12369e;
    color: #fff;
}
.btn-outline-secondary {
    color:rgb(90, 90, 90);
    border: 1px solid rgb(228, 228, 228);
}
.btn-outline-secondary:hover {
    background-color: #12369e;
    color: #fff;
}
.input-group-text {
    background-color:rgb(255, 255, 255);
    border: 1px solid rgb(228, 228, 228);
    color: gray;
}
.btn-primary {
        background: #12369e;
        border: none;
    }
    .btn-primary:hover {
        background: #05007E;
    }
    #searchResults {
    max-height: 200px;
    overflow-y: auto;
    border: 1px solid #ddd;
    border-radius: 5px;
    display: none;
    background: #fff;
    position: absolute;
    z-index: 1000;
    width: 50%;
}
#searchResults li {
    padding: 8px 12px;
    cursor: pointer;
    list-style: none;
    border-bottom: 1px solid #ddd;
}
#searchResults li:hover {
    background-color: #12369e;
    color: white;
}
.form-inline .input-group {
    width: 100%;
}
.search-icon-bg {
background-color: #fff; 
border: none; 
color: #6c757d; 
}

#treatmentModal .modal-content {
    box-shadow: 0 0 20px rgba(0, 0, 0, 0.5);
    border: none;
    border-radius: 8px;
    overflow: hidden;
}

#treatmentModal .modal-header {
    background: #ffffff;
    color: black;
    padding: 1.2rem;
    border-bottom: 1px solid #eee;
}

#treatmentModal .modal-title {
    font-weight: 600;
}

#treatmentModal .close {
    color: white;
    opacity: 1;
}

#treatmentModal .modal-body {
    padding: 1.5rem;
}

#treatmentModal .table thead {
    background: rgba(18, 54, 158, 0.05);
}

#treatmentModal .table-hover tbody tr:hover {
    background-color: rgba(18, 54, 158, 0.03);
    transition: all 0.2s ease;
}

#treatmentModal .form-control:focus {
    border-color: #12369e;
    box-shadow: 0 0 0 0.2rem rgba(18, 54, 158, 0.25);
}

#treatmentModal .modal-footer {
    border-top: 1px solid #eee;
    padding: 1rem;
}

#treatmentModal .btn-primary {
    background: #12369e;
    border: none;
    padding: 0.5rem 1.5rem;
    transition: all 0.3s ease;
}

#treatmentModal .btn-primary:hover {
    background: #05007E;
    transform: translateY(-1px);
}

#selectedMedicinesList {
    max-height: 300px;
    overflow-y: auto;
    margin-top: 1rem;
    border: 1px solid #eee;
    border-radius: 6px;
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
.image-container {
    background: #000;
    display: flex;
    align-items: center;
    justify-content: center;
    overflow: hidden;
    position: relative;
    height: 80vh;
}

#modalImage {
    transform-origin: center center;
    transition: transform 0.15s ease-out;
    max-height: 100%;
    max-width: 100%;
    position: absolute;
}

.modal-content {
    user-select: none;
}

.zoom-controls .btn, .btn-group-sm .btn {
    width: 32px;
    height: 32px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
}

.radiology-images-modal .swal2-content {
    padding: 20px;
}

.radiology-images-modal .card {
    transition: transform 0.2s;
}

.radiology-images-modal .card:hover {
    transform: scale(1.02);
}
</style>