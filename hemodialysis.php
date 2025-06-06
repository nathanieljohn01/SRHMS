<?php
session_start();
ob_start();

if (empty($_SESSION['name'])) {
    header('location:index.php');
    exit();
}

include('header.php');
include('includes/connection.php');

// Function to sanitize user inputs using mysqli_real_escape_string
function sanitize($connection, $input) {
    return mysqli_real_escape_string($connection, htmlspecialchars(strip_tags(trim($input)), ENT_QUOTES, 'UTF-8'));
}

$msg = null;
$role = isset($_SESSION['role']) ? $_SESSION['role'] : null;
$doctor_name = isset($_SESSION['name']) ? $_SESSION['name'] : null;

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['selectedMedicines']) && isset($_POST['hemopatientIdTreatment'])) {
    $hemopatientId = sanitize($connection, $_POST['hemopatientIdTreatment']);
    $selectedMedicines = json_decode($_POST['selectedMedicines'], true);

    if ($selectedMedicines) {
        foreach ($selectedMedicines as $medicine) {
            $medicineId = sanitize($connection, $medicine['id']);
            $medicineName = sanitize($connection, $medicine['name']);
            $medicineBrand = sanitize($connection, $medicine['brand']);
            $quantity = intval($medicine['quantity']);
            $price = floatval($medicine['price']);
            $totalPrice = $quantity * $price;

            // Insert into tbl_treatment with hemopatient_id
            $insertQuery = $connection->prepare("
                INSERT INTO tbl_treatment (hemopatient_id, patient_id, patient_name, medicine_name, medicine_brand, total_quantity, price, total_price, treatment_date)
                SELECT hemopatient_id, patient_id, patient_name, ?, ?, ?, ?, ?, NOW()
                FROM tbl_hemodialysis
                WHERE hemopatient_id = ?
            ");
            $insertQuery->bind_param("sssdds", $medicineName, $medicineBrand, $quantity, $price, $totalPrice, $hemopatientId);
            
            if (!$insertQuery->execute()) {
                $msg = "Error inserting treatment: " . $connection->error;
                break;
            }

            // Update medicine quantity
            $updateMedicineQuery = $connection->prepare("UPDATE tbl_medicines SET quantity = quantity - ? WHERE id = ?");
            $updateMedicineQuery->bind_param("is", $quantity, $medicineId);
            
            if (!$updateMedicineQuery->execute()) {
                $msg = "Error updating medicine quantity: " . $connection->error;
                break;
            }

            // Update hemodialysis record
            $updateHemoQuery = $connection->prepare("
                UPDATE tbl_hemodialysis
                SET
                    medicine_name = IF(medicine_name IS NULL OR medicine_name = '', ?, CONCAT(medicine_name, ', ', ?)),
                    medicine_brand = IF(medicine_brand IS NULL OR medicine_brand = '', ?, CONCAT(medicine_brand, ', ', ?)),
                    total_quantity = IF(total_quantity IS NULL, ?, total_quantity + ?)
                WHERE hemopatient_id = ?
            ");
            $updateHemoQuery->bind_param("sssssis", $medicineName, $medicineName, $medicineBrand, $medicineBrand, $quantity, $quantity, $hemopatientId);
            
            if (!$updateHemoQuery->execute()) {
                $msg = "Error updating hemodialysis record: " . $connection->error;
                break;
            }
        }

        if (!isset($msg)) {
            echo "<script>showSuccess('Treatment added successfully.', true);</script>";
        } else {
            echo "<script>showError('" . addslashes($msg) . "');</script>";
        }
    } else {
        echo "<script>showError('Error: Invalid medicines data.');</script>";
    }
}

// Assign doctor to hemodialysis patient
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['hemopatientIdDoctor']) && isset($_POST['doctorId'])) {
    $hemopatientId = sanitize($connection, $_POST['hemopatientIdDoctor']);
    $doctorId = sanitize($connection, $_POST['doctorId']);

    $doctor_query = $connection->prepare("SELECT first_name, last_name FROM tbl_employee WHERE id = ?");
    $doctor_query->bind_param("s", $doctorId);
    $doctor_query->execute();
    $doctor_result = $doctor_query->get_result();
    $doctor = $doctor_result->fetch_array(MYSQLI_ASSOC);
    $doctor_name = $doctor['first_name'] . ' ' . $doctor['last_name'];

    $update_query = $connection->prepare("UPDATE tbl_hemodialysis SET doctor_incharge = ? WHERE hemopatient_id = ?");
    $update_query->bind_param("ss", $doctor_name, $hemopatientId);

    if ($update_query->execute()) {
        echo "<script>showSuccess('Doctor assigned successfully.', true);</script>";
    } else {
        echo "<script>showError('Error assigning doctor.');</script>";
    }

    $doctor_query->close();
    $update_query->close();
}

// Fetch patient details from tbl_patient based on patient_id
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['patientId'])) {
    // Sanitize the patient ID input
    $patientId = sanitize($connection, $_POST['patientId']);

    // Prepare and execute query to fetch patient details, including the deleted flag
    $patient_query = $connection->prepare("SELECT * FROM tbl_patient WHERE id = ? AND deleted = 0");
    if ($patient_query === false) {
        die('Error in prepared statement: ' . $connection->error);
    }

    $patient_query->bind_param("s", $patientId); // "s" stands for string
    $patient_query->execute();
    $patient_result = $patient_query->get_result();
    $patient = $patient_result->fetch_array(MYSQLI_ASSOC);

    if ($patient) {
        // Retrieve sanitized patient details
        $patient_id = $patient['patient_id'];
        $name = sanitize($connection, $patient['first_name']) . ' ' . sanitize($connection, $patient['last_name']);
        $gender = sanitize($connection, $patient['gender']);
        $dob = sanitize($connection, $patient['dob']);
        $doctor_incharge = "";

        // Fetch the last hemo-patient ID to generate a new one
        $last_hemopatient_query = $connection->prepare("SELECT hemopatient_id FROM tbl_hemodialysis ORDER BY id DESC LIMIT 1");
        if ($last_hemopatient_query === false) {
            die('Error in prepared statement: ' . $connection->error);
        }

        $last_hemopatient_query->execute();
        $last_hemopatient_result = $last_hemopatient_query->get_result();
        $last_hemopatient = $last_hemopatient_result->fetch_array(MYSQLI_ASSOC);

        // Generate new hemopatient ID based on the last one
        if ($last_hemopatient) {
            $last_id_number = (int) substr($last_hemopatient['hemopatient_id'], 4); 
            $new_hemopatient_id = 'HPT-' . ($last_id_number + 1);
        } else {
            $new_hemopatient_id = 'HPT-1';  
        }

        // Sanitize dialysis_report (leave empty for now)
        $dialysis_report = '';

        // Insert the new hemodialysis record
        $insert_query = $connection->prepare("
           INSERT INTO tbl_hemodialysis (hemopatient_id, patient_id, patient_name, gender, dob, dialysis_report, doctor_incharge, date_time, deleted) 
            VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), 0)
        ");
        if ($insert_query === false) {
            die('Error in prepared statement: ' . $connection->error);
        }

        // Bind sanitized values for insertion
        $insert_query->bind_param("sssssss", $new_hemopatient_id, $patient_id, $name, $gender, $dob, $dialysis_report, $doctor_incharge);

        // Execute the insert query
        if ($insert_query->execute()) {
            echo "<script>showSuccess('Patient added successfully.', true);</script>";
        } else {
            echo "<script>showError('Error: " . $connection->error . "');</script>";
        }
        exit();
    } else {
        echo "<script>showError('Patient not found or marked as deleted.');</script>";
    }
}

ob_end_flush(); // Flush output buffer
?>

<div class="page-wrapper">
    <div class="content">
        <div class="row">
            <div class="col-sm-4 col-3">
                <h4 class="page-title">Hemodialysis</h4>
            </div>
            <?php if ($role == 1 || $role == 3): ?>
                <div class="col-sm-10 col-9 m-b-20">
                    <form method="POST" action="hemodialysis.php" id="addPatientForm" class="form-inline">
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
                        <input class="form-control" type="text" id="hemopatientSearchInput" onkeyup="filterHemopatients()" placeholder="Search" style="padding-left: 35px; padding-right: 35px;">
                        <!-- Clear Button -->
                        <button class="position-absolute border-0 bg-transparent text-secondary" type="button" onclick="clearSearch()" style="top: 50%; right: 10px; transform: translateY(-50%);">
                            <i class="fa fa-times"></i>
                        </button>
                    </div>
                </div>
            </div>
        </div>
        <div class="table-responsive">
            <table class="datatable table table-hover table-striped" id="hemopatientTable">
                <thead style="background-color: #CCCCCC;">
                    <tr>
                        <th>Hemo-patient ID</th>
                        <th>Patient ID</th>
                        <th>Patient Name</th>
                        <th>Age</th>
                        <th>Birthdate</th>
                        <th>Gender</th>
                        <th>Doctor In-Charge</th>
                        <th>Date and Time</th>
                        <th>Lab Result</th>
                        <th>Medications</th>
                        <th>Dialysis Report</th>
                        <th>Follow-up Date</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    if(isset($_GET['ids'])){
                        $id = sanitize($connection, $_GET['ids']);
                        $update_query = $connection->prepare("UPDATE tbl_hemodialysis SET deleted = 1 WHERE id = ?");
                        if ($update_query === false) {
                            die('Error in prepared statement: ' . $connection->error);
                        }
                        $update_query->bind_param("s", $id);
                        $update_query->execute();
                        echo "<script>showSuccess('Record deleted successfully.', true);</script>";
                    }
                    
                    // Modify the fetch query to include doctor_incharge and filter by role if needed
                    if ($role == 2) {
                        $fetch_query = $connection->prepare("
                            SELECT h.*, 
                            GROUP_CONCAT(CONCAT(t.medicine_name, ' (', t.medicine_brand, ') - ', t.total_quantity, ' pcs') SEPARATOR '<br>') AS treatments
                            FROM tbl_hemodialysis h
                            LEFT JOIN tbl_treatment t ON h.hemopatient_id = t.hemopatient_id 
                            WHERE h.deleted = 0 AND h.doctor_incharge = ?
                            GROUP BY h.hemopatient_id
                        ");
                        $fetch_query->bind_param("s", $doctor_name);
                        $fetch_query->execute();
                        $fetch_result = $fetch_query->get_result();
                    } else {
                        $fetch_query = mysqli_query($connection, "
                            SELECT h.*, 
                            GROUP_CONCAT(CONCAT(t.medicine_name, ' (', t.medicine_brand, ') - ', t.total_quantity, ' pcs') SEPARATOR '<br>') AS treatments
                            FROM tbl_hemodialysis h
                            LEFT JOIN tbl_treatment t ON h.hemopatient_id = t.hemopatient_id 
                            WHERE h.deleted = 0
                            GROUP BY h.hemopatient_id
                        ");
                    }
                    
                    while($row = ($role == 2 ? $fetch_result->fetch_array(MYSQLI_ASSOC) : mysqli_fetch_array($fetch_query)))
                    {
                        $dob = $row['dob'];
                        $date = str_replace('/', '-', $dob); 
                        $dob = date('Y-m-d', strtotime($date));
                        $year = (date('Y') - date('Y',strtotime($dob)));

                        $date_time = date('F d, Y g:i A', strtotime($row['date_time']));
                        $treatmentDetails = $row['treatments'] ?: 'No treatments added';
                    ?>
                        <tr>
                            <td><?php echo $row['hemopatient_id']; ?></td>
                            <td><?php echo $row['patient_id']; ?></td>
                            <td><?php echo $row['patient_name']; ?></td>
                            <td><?php echo $year; ?></td>
                            <td><?php echo $row['dob']; ?></td>
                            <td><?php echo $row['gender']; ?></td>
                            <td>
                                <?php echo htmlspecialchars($row['doctor_incharge']); ?>
                            </td>
                            <td><?php echo $date_time; ?></td>
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
                                <?php if (!empty($row['treatments'])): ?>
                                    <!-- Display Treatment Details if Present -->
                                    <div><?php echo nl2br(strip_tags($row['treatments'], '<br>')); ?></div>
                                <?php else: ?>
                                    <div>No treatments added</div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="dialysis-report">
                                    <?php
                                    // Convert new lines to <br> tags to preserve formatting
                                    $formatted_report = nl2br(htmlspecialchars($row['dialysis_report']));
                                    echo $formatted_report;
                                    ?>
                                </div>
                            </td>
                            <td>
                                <?php
                                // Assuming the format in database is DD/MM/YYYY
                                $follow_up_date = $row['follow_up_date'];

                                // Convert to 'Month Day, Year' format (e.g., December 28, 2024)
                                $formatted_date = date('F d, Y', strtotime(str_replace('/', '-', $follow_up_date)));

                                echo $formatted_date;
                                ?>
                            </td>
                            <td class="text-right">
                                <div class="dropdown dropdown-action">
                                    <a href="#" class="action-icon dropdown-toggle" data-toggle="dropdown" aria-expanded="false"><i class="fa fa-ellipsis-v"></i></a>
                                    <div class="dropdown-menu dropdown-menu-right">
                                        <?php 
                                        if ($_SESSION['role'] == 1 || $_SESSION['role'] == 3) {
                                            echo '<a class="dropdown-item" href="edit-hemo.php?id='.$row['id'].'"><i class="fa fa-pencil m-r-5"></i> Update</a>';
                                        }
                                        ?>
                                        <?php if ($_SESSION['role'] == 3): ?>
                                            <button class="dropdown-item treatment-btn" data-toggle="modal" data-target="#treatmentModal" data-id="<?php echo htmlspecialchars($row['hemopatient_id']); ?>">
                                                <i class="fa fa-stethoscope m-r-5"></i> Insert/Edit Treatments
                                            </button>
                                        <?php endif; ?>
                                        <?php if ($_SESSION['role'] == 1): ?>
                                            <a class="dropdown-item" href="#" onclick="return confirmDelete('<?php echo $row['id']; ?>')">
                                                <i class="fa fa-trash m-r-5"></i> Delete
                                            </a>
                                        <?php endif; ?>
                                        <?php if ($_SESSION['role'] == 3 && empty($row['doctor_incharge'])): ?>
                                            <a class="dropdown-item select-doctor-btn" data-toggle="modal" data-target="#doctorModal" data-id="<?php echo htmlspecialchars($row['hemopatient_id']); ?>">
                                                <i class="fa fa-user-md m-r-5"></i> Select Doctor
                                            </a>
                                        <?php endif; ?>
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

<!-- Doctor Modal -->
<div id="doctorModal" class="modal fade" role="dialog">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h4 class="modal-title">Select Doctor</h4>
            </div>
            <div class="modal-body">
                <form id="doctorForm" method="post" action="hemodialysis.php">
                    <input type="hidden" id="hemopatientIdDoctor" name="hemopatientIdDoctor">
                    <div class="form-group">
                        <label for="doctor">Select Doctor:</label>
                        <select class="form-control" id="doctor" name="doctor">
                            <?php
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
                <form id="medicineSelectionForm" method="POST" action="hemodialysis.php">
                    <input type="hidden" name="hemopatientIdTreatment" id="hemopatientIdTreatment">
                    
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

<?php
include('footer.php');
?>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script language="JavaScript" type="text/javascript">
    function confirmDelete(id) {
        return Swal.fire({
            title: 'Delete Patient Record?',
            text: 'Are you sure you want to delete this Patient record? This action cannot be undone!',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#12369e',
            confirmButtonText: 'OK'
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = 'hemodialysis.php?ids=' + id;
            }
        });
    }
</script>

<script>
let selectedMedicines = [];

// Function to load existing treatments when modal opens
function loadExistingTreatments(hemopatientId) {
    $.ajax({
        url: 'fetch-existing-hemo-treatments.php',
        type: 'GET',
        data: { hemopatient_id: hemopatientId },
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
    var hemopatientId = $(this).data('id');
    $('#hemopatientIdTreatment').val(hemopatientId);
    selectedMedicines = []; // Clear the array
    $('#selectedMedicinesList').html(''); // Clear the UI
    $('#selectedMedicines').val(''); // Clear the hidden input
    
    // Load existing treatments
    loadExistingTreatments(hemopatientId);
});

// Function to search medicines (updated to exclude already selected medicines)
function searchMedicines() {
    const query = document.getElementById('medicineSearchInput').value.trim();
    const hemopatientId = $('#hemopatientIdTreatment').val();

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

// Doctor assignment functionality
$(document).on('click', '.select-doctor-btn', function() {
    var hemopatientId = $(this).data('id');
    $('#hemopatientIdDoctor').val(hemopatientId);
    $('#doctorModal').modal('show');
});

$('#doctorForm').submit(function(e) {
    e.preventDefault();
    var hemopatientId = $('#hemopatientIdDoctor').val();
    var doctorId = $('#doctor').val();

    $.ajax({
        url: 'hemodialysis.php',
        type: 'POST',
        data: {
            hemopatientIdDoctor: hemopatientId,
            doctorId: doctorId
        },
        success: function(response) {
            location.reload();
        },
        error: function(xhr, status, error) {
            alert('Error assigning doctor');
        }
    });
});

// Doctor assignment functionality
$('#doctorForm').submit(function(e) {
    e.preventDefault();
    var hemopatientId = $('#hemopatientIdDoctor').val();
    var doctorId = $('#doctor').val();

    $.ajax({
        url: 'hemodialysis.php',
        type: 'POST',
        data: {
            hemopatientIdDoctor: hemopatientId,
            doctorId: doctorId
        },
        success: function(response) {
            location.reload();
        },
        error: function(xhr, status, error) {
            alert('Error assigning doctor');
        }
    });
});

</script>

<script>
    function clearSearch() {
        document.getElementById("hemopatientSearchInput").value = '';
        filterHemopatients();
    }

    var role = <?php echo json_encode($_SESSION['role']); ?>;
    var doctor_name = <?php echo json_encode($_SESSION['name']); ?>;

    function filterHemopatients() {
        var input = document.getElementById("hemopatientSearchInput").value;
        
        $.ajax({
            url: 'fetch_hemo.php',
            type: 'GET',
            data: { query: input },
            success: function(response) {
                var data = JSON.parse(response);
                updateHemoTable(data);
            },
            error: function(xhr, status, error) {
                alert('Error fetching data. Please try again.');
            }
        });
    }

    function updateHemoTable(data) {
        var tbody = $('#hemopatientTable tbody');
        tbody.empty();
        
        data.forEach(function(record) {
            // Lab Result button - only show for role 2 (doctor) and if they are the doctor in charge
            var labResultContent = '';
            if (record.user_role == 2 && record.doctor_incharge == doctor_name) {
                labResultContent = `
                    <form action="generate-result.php" method="get">
                        <input type="hidden" name="patient_id" value="${record.patient_id}">
                        <button class="btn btn-primary custom-btn" type="submit">
                            <i class="fa fa-file-pdf m-r-5"></i> View Result
                        </button>
                    </form>
                `;
            }

            // Doctor assignment content
            var doctorContent = record.doctor_incharge ? 
                record.doctor_incharge : 
                (record.user_role == 3 ? 
                    `<button class="btn btn-primary btn-sm select-doctor-btn" data-toggle="modal" data-target="#doctorModal" data-id="${record.hemopatient_id}">
                        Select Doctor
                    </button>` : 
                    ''
                );

            // Treatment content
            var treatmentContent = record.treatments !== 'No treatments added' ?
                `<div>${record.treatments}</div>` :
                `<div>No treatments added</div>`;

            // Generate action buttons based on role
            let actionButtons = '';
            
            // Update button for roles 1 or 3
            if (record.user_role == 1 || record.user_role == 3) {
                actionButtons += `
                    <a class="dropdown-item" href="edit-hemo.php?id=${record.id}">
                        <i class="fa fa-pencil m-r-5"></i> Update
                    </a>
                `;
            }
            
            // Add/Edit Treatments button for role 3
            if (record.user_role == 3) {
                actionButtons += `
                    <button class="dropdown-item treatment-btn" data-toggle="modal" data-target="#treatmentModal" data-id="${record.hemopatient_id}">
                        <i class="fa fa-stethoscope m-r-5"></i> Add/Edit Treatments
                    </button>
                `;
            }
            
            // Delete button for role 1
            if (record.user_role == 1) {
                actionButtons += `
                    <a class="dropdown-item" href="#" onclick="return confirmDelete('${record.id}')">
                        <i class="fa fa-trash m-r-5"></i> Delete
                    </a>
                `;
            }
            
            // Select Doctor button for role 3 if no doctor assigned
            if (record.user_role == 3 && (!record.doctor_incharge || record.doctor_incharge.trim() === '')) {
                actionButtons += `
                    <a class="dropdown-item select-doctor-btn" data-toggle="modal" data-target="#doctorModal" data-id="${record.hemopatient_id}">
                        <i class="fa fa-user-md m-r-5"></i> Select Doctor
                    </a>
                `;
            }

            tbody.append(`
                <tr>
                    <td>${record.hemopatient_id}</td>
                    <td>${record.patient_id}</td>
                    <td>${record.patient_name}</td>
                    <td>${record.age}</td>
                    <td>${record.dob}</td>
                    <td>${record.gender}</td>
                    <td>${doctorContent}</td>
                    <td>${record.date_time}</td>
                    <td>${labResultContent}</td>
                    <td>${treatmentContent}</td>
                    <td><div class="dialysis-report">${record.dialysis_report}</div></td>
                    <td>${record.follow_up_date}</td>
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
                </tr>
            `);
        });
    }


    function getActionButtons(id, hemopatientId, doctorIncharge, role) {
        let buttons = '';

        // Show Update & Delete for roles 1 or 3
        if (role == 1 || role == 3) {
            buttons += `
                <a class="dropdown-item" href="edit-hemo.php?id=${id}">
                    <i class="fa fa-pencil m-r-5"></i> Update
                </a>
            `;
        }

        // Add the "Add/Edit Treatments" button for role 3
        if (role == 3) {
            buttons += `
                <button class="dropdown-item treatment-btn" data-toggle="modal" data-target="#treatmentModal" data-id="${hemopatientId}">
                    <i class="fa fa-stethoscope m-r-5"></i> Add/Edit Treatments
                </button>
            `;
        }

        // Show Delete only for role 1 (Admin)
        if (role == 1) {
            buttons += `
                <a class="dropdown-item" href="#" onclick="return confirmDelete('${id}')">
                    <i class="fa fa-trash m-r-5"></i> Delete
                </a>
            `;
        }

        // Show "Select Doctor" for role 3 (Nurse) if no doctor is assigned
        if (role == 3 && (!doctorIncharge || doctorIncharge.trim() === '')) {
            buttons += `
                <a class="dropdown-item select-doctor-btn" 
                data-toggle="modal" 
                data-target="#doctorModal" 
                data-id="${hemopatientId}">
                    <i class="fa fa-user-md m-r-5"></i> Select Doctor
                </a>
            `;
        }

        return buttons;
    }

    function searchPatients() {
        var input = document.getElementById("patientSearchInput").value;
        if (input.length < 2) {
            document.getElementById("searchResults").style.display = "none";
            document.getElementById("searchResults").innerHTML = "";
            return;
        }
        $.ajax({
            url: "search-hemo.php", // Backend script to fetch patients
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

    $('#hemopatientTable').on('click', '.dropdown-toggle', function (e) {
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

<style>
.btn-sm {
    min-width: 110px; /* Adjust as needed */
    padding: 0.25rem 0.5rem;
    font-size: 0.875rem;
    line-height: 1.5;
}    
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

#selectedMedicinesList .list-group-item {
    border-left: none;
    border-right: none;
    transition: all 0.2s ease;
}

#selectedMedicinesList .list-group-item:hover {
    background-color: rgba(18, 54, 158, 0.03);
}
</style>




