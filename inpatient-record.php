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
        // Start transaction
        $connection->begin_transaction();
        
        try {
            // 1. First get all current treatments for this inpatient
            $current_treatments = [];
            $current_query = $connection->prepare("SELECT medicine_name, medicine_brand, total_quantity FROM tbl_treatment WHERE inpatient_id = ?");
            $current_query->bind_param("s", $inpatientId);
            $current_query->execute();
            $current_result = $current_query->get_result();
            
            while ($row = $current_result->fetch_assoc()) {
                $key = $row['medicine_name'] . '|' . $row['medicine_brand'];
                $current_treatments[$key] = $row['total_quantity'];
            }
            
            // 2. Delete all existing treatments for this inpatient
            $delete_query = $connection->prepare("DELETE FROM tbl_treatment WHERE inpatient_id = ?");
            $delete_query->bind_param("s", $inpatientId);
            $delete_query->execute();

            // 3. Reset medicine quantities in inpatient record
            $reset_query = $connection->prepare("UPDATE tbl_inpatient_record SET medicine_name = '', medicine_brand = '', total_quantity = 0 WHERE inpatient_id = ?");
            $reset_query->bind_param("s", $inpatientId);
            $reset_query->execute();

            // 4. Process each selected medicine
            $new_medicines = [];
            $new_quantities = [];
            
            foreach ($selectedMedicines as $medicine) {
                $medicineId = sanitize($connection, $medicine['id']);
                $medicineName = sanitize($connection, $medicine['name']);
                $medicineBrand = sanitize($connection, $medicine['brand']);
                $quantity = intval($medicine['quantity']);
                $price = floatval($medicine['price']);
                $totalPrice = $quantity * $price;

                // Get patient details from inpatient record
                $patient_query = $connection->prepare("SELECT patient_id, patient_name FROM tbl_inpatient_record WHERE inpatient_id = ?");
                $patient_query->bind_param("s", $inpatientId);
                $patient_query->execute();
                $patient_result = $patient_query->get_result();
                $patient = $patient_result->fetch_assoc();

                // Insert new treatment record
                $insertQuery = $connection->prepare("
                    INSERT INTO tbl_treatment (inpatient_id, patient_id, patient_name, medicine_name, medicine_brand, total_quantity, price, total_price, treatment_date)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
                ");
                $insertQuery->bind_param("ssssssss", $inpatientId, $patient['patient_id'], $patient['patient_name'], $medicineName, $medicineBrand, $quantity, $price, $totalPrice);
                
                if (!$insertQuery->execute()) {
                    throw new Exception("Error inserting treatment: " . $connection->error);
                }

                // Track new medicines for inventory update
                $key = $medicineName . '|' . $medicineBrand;
                $new_medicines[$key] = $medicineId;
                $new_quantities[$key] = $quantity;
                
                // Update inpatient record with concatenated medicine info
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
                    throw new Exception("Error updating inpatient record: " . $connection->error);
                }
            }

            // 5. Update medicine inventory - first add back quantities from removed treatments
            foreach ($current_treatments as $key => $old_quantity) {
                // If this medicine was in old treatments but not in new ones, add back its quantity
                if (!isset($new_quantities[$key])) {
                    list($name, $brand) = explode('|', $key);
                    
                    $update_query = $connection->prepare("
                        UPDATE tbl_medicines 
                        SET quantity = quantity + ? 
                        WHERE medicine_name = ? AND medicine_brand = ?
                    ");
                    $update_query->bind_param("iss", $old_quantity, $name, $brand);
                    
                    if (!$update_query->execute()) {
                        throw new Exception("Error returning medicine to inventory: " . $connection->error);
                    }
                }
            }
            
            // 6. Update medicine inventory - subtract quantities for new/updated treatments
            foreach ($new_quantities as $key => $new_quantity) {
                list($name, $brand) = explode('|', $key);
                $medicineId = $new_medicines[$key];
                
                // Calculate net change (new quantity minus old quantity if it existed)
                $net_change = $new_quantity;
                if (isset($current_treatments[$key])) {
                    $net_change = $new_quantity - $current_treatments[$key];
                }
                
                // Only update if there's a net change
                if ($net_change != 0) {
                    $update_query = $connection->prepare("
                        UPDATE tbl_medicines 
                        SET quantity = quantity - ? 
                        WHERE id = ?
                    ");
                    $update_query->bind_param("is", $net_change, $medicineId);
                    
                    if (!$update_query->execute()) {
                        throw new Exception("Error updating medicine inventory: " . $connection->error);
                    }
                }
            }

            // Commit transaction
            $connection->commit();
            $msg = "Treatment updated successfully.";
            
        } catch (Exception $e) {
            // Rollback transaction on error
            $connection->rollback();
            $msg = $e->getMessage();
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
            <?php if ($_SESSION['role'] == 1 || $_SESSION['role'] == 9): ?>
                <div class="col-sm-10 col-9 m-b-20">
                    <form method="POST" action="inpatient-record.php" id="addPatientForm" class="form-inline">
                        <div class="input-group w-50">
                            <div class="input-group-prepend">
                                <span class="input-group-text">
                                    <i class="fas fa-search text-secondary"></i> <!-- Search icon -->
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
                            <td> <?php echo htmlspecialchars($row['doctor_incharge']); ?></td>
                            <td>
                                <?php if ($_SESSION['role'] == 2) { ?>
                                <form action="generate-result.php" method="get">
                                    <input type="hidden" name="patient_id" value="<?php echo $row['patient_id']; ?>">
                                    <button class="btn btn-primary custom-btn" type="submit">
                                        <i class="fa fa-file-pdf m-r-5"></i> View Result
                                    </button>
                                </form>
                                <?php } ?>
                            </td>
                            <td>
                                <?php if ($_SESSION['role'] == 2) { 
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
                                <?php 
                                    }
                                } 
                                ?>
                            </td>
                            <td><?php echo htmlspecialchars($row['diagnosis']); ?></td>
                            <td>
                                <?php if (!empty($row['treatments'])): ?>
                                    <!-- Display Treatment Details if Present -->
                                    <div><?php echo nl2br(strip_tags($row['treatments'], '<br>')); ?></div>
                                <?php else: ?>
                                    <div>No treatments added</div>
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
                                        <?php if ($_SESSION['role'] == 9 && empty($row['doctor_incharge'])) { ?>
                                            <button class="dropdown-item select-doctor-btn" data-toggle="modal" data-target="#doctorModal" data-id="<?php echo htmlspecialchars($row['inpatient_id']); ?>"><i class="fa fa-user-md m-r-5"></i> Select Doctor</button>
                                        <?php } ?>
                                        <?php if ($_SESSION['role'] == 9) { ?>
                                            <button class="dropdown-item treatment-btn" data-toggle="modal" data-target="#treatmentModal" data-id="<?php echo htmlspecialchars($row['inpatient_id']); ?>"><i class="fa fa-medkit m-r-5"></i> Insert/Edit Treatments</button>
                                        <?php } ?>
                                        <?php if ($_SESSION['role'] == 1) { ?>
                                            <a class="dropdown-item" href="#" onclick="return confirmDelete('<?php echo $row['id']; ?>')"><i class="fa fa-trash m-r-5"></i> Delete</a>
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

<!-- Update the treatment modal section to preload existing treatments -->
<div id="treatmentModal" class="modal fade" tabindex="-1" role="dialog" aria-labelledby="treatmentModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="treatmentModalLabel">Manage Treatments</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <form id="medicineSelectionForm" method="POST" action="inpatient-record.php">
                    <input type="hidden" name="inpatientIdTreatment" id="inpatientIdTreatment">
                    
                    <div class="form-group">
                        <label for="medicineSearchInput">Search Medicines</label>
                        <input type="text" class="form-control" id="medicineSearchInput" placeholder="Enter medicine name or brand" onkeyup="searchMedicines()">
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

                    <h5>Current Treatments</h5>
                    <ul id="selectedMedicinesList" class="list-group">
                        <!-- Selected medicines will populate here -->
                    </ul>
                    <input type="hidden" name="selectedMedicines" id="selectedMedicines">
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                <button type="submit" class="btn btn-primary" form="medicineSelectionForm">Save Treatments</button>
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

<!-- jQuery first -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<!-- Then Popper.js -->
<script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
<!-- Then Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.min.js"></script>
<!-- Then other libraries -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

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
document.querySelector('#addPatientForm').addEventListener('submit', function(event) {
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
<script>
   $(document).on('click', '.diagnosis-btn', function () {
    const inpatientId = $(this).data('id');
    $('#diagnosisModal').modal('show');
    // Populate modal or handle accordingly
    });

    $(document).on('click', '.select-doctor-btn', function () {
        const inpatientId = $(this).data('id');
        $('#doctorModal').modal('show');
        // Populate modal or handle accordingly
    });

    $(document).on('click', '.treatment-btn', function () {
        const inpatientId = $(this).data('id');
        $('#treatmentModal').modal('show');
        // Populate modal or handle accordingly
    });
</script>

<script>
let selectedMedicines = [];

// Function to load existing treatments when modal opens
function loadExistingTreatments(inpatientId) {
    $.ajax({
        url: 'fetch-existing-treatments.php',
        type: 'GET',
        data: { inpatient_id: inpatientId },
        dataType: 'json',
        success: function(data) {
            selectedMedicines = data;
            updateSelectedMedicinesUI();
        },
        error: function() {
            alert('Error loading existing treatments');
        }
    });
}

// Update the treatment button click handler
$(document).on('click', '.treatment-btn', function() {
    var inpatientId = $(this).data('id');
    $('#inpatientIdTreatment').val(inpatientId);
    selectedMedicines = []; // Clear the array
    $('#selectedMedicinesList').html(''); // Clear the UI
    $('#selectedMedicines').val(''); // Clear the hidden input
    
    // Load existing treatments
    loadExistingTreatments(inpatientId);
});

// Function to search medicines (updated to exclude already selected medicines)
function searchMedicines() {
    const query = document.getElementById('medicineSearchInput').value.trim();
    const inpatientId = $('#inpatientIdTreatment').val();

    if (query.length > 2) {
        $.ajax({
            url: 'search-medicines.php',
            type: 'GET',
            data: { 
                query: query,
                exclude: selectedMedicines.map(m => m.id) // Exclude already selected medicines
            },
            success: function(data) {
                $('#medicineSearchResults').html(data);
            },
            error: function() {
                alert('Error fetching medicines. Please try again later.');
            }
        });
    } else {
        $('#medicineSearchResults').html('<tr><td colspan="8">Please enter at least 3 characters to search.</td></tr>');
    }
}

// Function to add medicine to the selected list (updated to handle updates)
function addMedicineToList(id, name, brand, category, availableQuantity, price, expiration_date, event) {
    if (event) {
        event.preventDefault();
        event.stopPropagation();
    }

    const quantityInput = parseInt(document.getElementById(`quantityInput-${id}`).value, 10);

    if (quantityInput <= 0 || quantityInput > availableQuantity) {
        alert('Invalid quantity. Please enter a value between 1 and ' + availableQuantity);
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
            available_quantity: availableQuantity
        };
        selectedMedicines.push(medicine);
    }

    updateSelectedMedicinesUI();
}

// Function to update the selected medicines UI (updated to show available quantity)
function updateSelectedMedicinesUI() {
    $('#selectedMedicinesList').html('');

    selectedMedicines.forEach((medicine, index) => {
        const listItem = `
            <li class="list-group-item d-flex justify-content-between align-items-center">
                <div>
                    <strong>${medicine.name} (${medicine.brand})</strong><br>
                    <small>Category: ${medicine.category}</small><br>
                    <small>Exp: ${medicine.expiration_date}</small><br>
                    <small>Price: ${medicine.price} PHP each</small>
                </div>
                <div class="d-flex align-items-center">
                    <div class="mr-3">
                        <label>Quantity:</label>
                        <input type="number" 
                               class="form-control form-control-sm" 
                               value="${medicine.quantity}" 
                               min="1" 
                               max="${medicine.available_quantity}"
                               onchange="updateMedicineQuantity(${index}, this.value)">
                    </div>
                    <button type="button" 
                            class="btn btn-danger btn-sm" 
                            onclick="removeMedicineFromList(${index})">
                        Remove
                    </button>
                </div>
            </li>`;
        $('#selectedMedicinesList').append(listItem);
    });

    $('#selectedMedicines').val(JSON.stringify(selectedMedicines));
}

// New function to update quantity of existing medicine
function updateMedicineQuantity(index, newQuantity) {
    const medicine = selectedMedicines[index];
    const availableQty = medicine.available_quantity;
    newQuantity = parseInt(newQuantity);
    
    if (newQuantity <= 0 || newQuantity > availableQty) {
        alert('Invalid quantity. Please enter a value between 1 and ' + availableQty);
        return false;
    }
    
    selectedMedicines[index].quantity = newQuantity;
    updateSelectedMedicinesUI();
    return true;
}

// Function to remove a medicine from the selected list
function removeMedicineFromList(index) {
    selectedMedicines.splice(index, 1);
    updateSelectedMedicinesUI();
}
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
            // Lab Result button - only show for role 2 (doctor) and if they are the doctor in charge
            var labResultButton = '';
            if (row.user_role == 2 && doctor_name == row.doctor_incharge) {
                labResultButton = `
                    <form action="generate-result.php" method="get">
                        <input type="hidden" name="patient_id" value="${row.patient_id}">
                        <button class="btn btn-primary custom-btn" type="submit">
                            <i class="fa fa-file-pdf m-r-5"></i> View Result
                        </button>
                    </form>`;
            }

            // Radiology button - only show for role 2 (doctor) and if has_radiology is true
            var radiologyButton = '';
            if (row.user_role == 2 && doctor_name == row.doctor_incharge) {
                radiologyButton = `
                    <button class="btn btn-primary custom-btn" onclick="showRadiologyImages('${row.patient_id}')">
                        <i class="fa fa-image m-r-5"></i> View Images
                    </button>`;
            }
            
            // Prepare action buttons based on user role
            var actionButtons = '';
            
            // Diagnosis button for doctors
            if (row.user_role == 2 && doctor_name == row.doctor_incharge) {
                actionButtons += `
                    <button class="dropdown-item diagnosis-btn" 
                        data-toggle="modal" 
                        data-target="#diagnosisModal" 
                        data-id="${row.inpatient_id}" 
                        ${row.diagnosis ? 'disabled' : ''}>
                        <i class="fa fa-stethoscope m-r-5"></i> Diagnosis
                    </button>`;
            }
            
            // Select Doctor button for role 3 (nurse/staff)
            if (row.user_role == 3 && !row.doctor_incharge) {
                actionButtons += `
                    <button class="dropdown-item select-doctor-btn" 
                        data-toggle="modal" 
                        data-target="#doctorModal" 
                        data-id="${row.inpatient_id}">
                        <i class="fa fa-user-md m-r-5"></i> Select Doctor
                    </button>`;
            }
            
            // Add/Edit Treatments button for role 9 (pharmacist)
            if (row.user_role == 9) {
                actionButtons += `
                    <button class="dropdown-item treatment-btn" 
                        data-toggle="modal" 
                        data-target="#treatmentModal" 
                        data-id="${row.inpatient_id}">
                        <i class="fa fa-medkit m-r-5"></i> Insert/Edit Treatments
                    </button>`;
            }
            
            // Edit and Delete buttons for admin
            if (row.user_role == 1) {
                actionButtons += `
                    <a class="dropdown-item" href="edit-inpatient-record.php?id=${row.id}">
                        <i class="fa fa-pencil m-r-5"></i> Edit
                    </a>
                    <a class="dropdown-item" href="#" onclick="return confirmDelete('${row.id}')">
                        <i class="fa fa-trash m-r-5"></i> Delete
                    </a>`;
            }

            // Build the table row
            tbody.append(`<tr>
                <td>${row.patient_id}</td>
                <td>${row.inpatient_id}</td>
                <td>${row.patient_name}</td>
                <td>${row.age}</td>
                <td>${row.gender}</td>
                <td>${row.doctor_incharge || 'Not assigned'}</td>
                <td>${labResultButton}</td>
                <td>${radiologyButton}</td>
                <td>${row.diagnosis || ''}</td>
                <td>${row.treatments || 'No treatments added'}</td>
                <td>${row.room_type}</td>
                <td>${row.room_number}</td>
                <td>${row.bed_number}</td>
                <td>${row.admission_date}</td>
                <td>${row.discharge_date}</td>
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
    
    // Delegate the click event to a parent that stays in the DOM (e.g. #inpatientTable)
    $('#inpatientTable').on('click', '.dropdown-toggle', function (e) {
        e.preventDefault(); // Prevent default action if it's a link

        var $el = $(this).next('.dropdown-menu');
        var isVisible = $el.is(':visible');

        // Hide all dropdowns
        $('.dropdown-menu').slideUp(400);

        // If this wasn't already visible, slide it down
        if (!isVisible) {
            $el.stop(true, true).slideDown(400);
        }

        // Prevent the event from bubbling to document
        e.stopPropagation();
    });

    // Click outside to close all dropdowns
    $(document).on('click', function (e) {
        if (!$(e.target).closest('.dropdown').length) {
            $('.dropdown-menu').slideUp(400);
        }
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
</script>

<script>
// Image viewer variables
var currentZoom = 1;
var currentRotation = 0;
var isDragging = false;
var startX, startY, translateX = 0, translateY = 0;

function updateImageTransform() {
    const transform = `translate(${translateX}px, ${translateY}px) rotate(${currentRotation}deg) scale(${currentZoom})`;
    $('#viewedImage').css('transform', transform);
}

function openImageViewer(imageId, examType, imageSrc) {
    $('#imageViewerTitle').text(examType);
    $('#viewedImage').attr('src', imageSrc);
    
    // Reset viewer state
    currentZoom = 1;
    currentRotation = 0;
    translateX = 0;
    translateY = 0;
    updateImageTransform();
    
    $('#imageViewerModal').modal('show');
}

function showRadiologyImages(patientId) {
    // Show loading state
    $('#radiologyImagesContainer').html(`
        <div class="col-12 text-center py-5">
            <div class="spinner-border text-primary" role="status">
                <span class="sr-only">Loading...</span>
            </div>
            <p class="mt-2">Loading images...</p>
        </div>
    `);
    
    // Show the modal
    $('#radiologyModal').modal('show');
    
    // Fetch radiology images
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
    // Initialize zoom controls
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
    
    // Rotation controls
    $('.rotate-left-btn').on('click', function() {
        currentRotation -= 90;
        updateImageTransform();
    });
    
    $('.rotate-right-btn').on('click', function() {
        currentRotation += 90;
        updateImageTransform();
    });
    
    // Drag functionality
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
.btn-sm {
    min-width: 110px; /* Adjust as needed */
    padding: 0.25rem 0.5rem;
    font-size: 0.875rem;
    line-height: 1.5;
}

.custom-btn {
    min-width: 120px; /* Adjust as needed */
    padding: 0.95rem 0.30rem;
    font-size: 0.900rem;
    line-height: 1.5;
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
.input-group-text {
    background-color:rgb(249, 249, 249);
    border: 1px solid rgb(212, 212, 212);
    color: gray;
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

/* Responsive adjustments */
@media (max-width: 768px) {
    .modal-footer {
        flex-wrap: wrap;
    }
    
    .zoom-controls, .btn-group {
        margin-bottom: 8px;
    }
}
#radiologyImagesContainer .card {
    transition: transform 0.2s;
    cursor: pointer;
}

#radiologyImagesContainer .card:hover {
    transform: scale(1.02);
    box-shadow: 0 4px 8px rgba(0,0,0,0.1);
}

#radiologyImagesContainer .card-img-top {
    object-fit: cover;
    height: 200px;
}
</style>