<?php
session_start();
ob_start();
if (empty($_SESSION['name'])) {
    header('location:index.php');
    exit();
}
include('header.php');
include('includes/connection.php');

// Function to sanitize inputs
function sanitize($connection, $input) {
    return mysqli_real_escape_string($connection, htmlspecialchars(strip_tags(trim($input)), ENT_QUOTES, 'UTF-8'));
}

$role = isset($_SESSION['role']) ? $_SESSION['role'] : null;

// Process Diagnosis Form Submission
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['diagnosis']) && isset($_POST['patientId'])) {
    $diagnosis = sanitize($connection, $_POST['diagnosis']);
    $patientId = sanitize($connection, $_POST['patientId']);
    
    $update_query = $connection->prepare("UPDATE tbl_newborn SET diagnosis=? WHERE newborn_id=?");
    $update_query->bind_param("ss", $diagnosis, $patientId);

    if ($update_query->execute()) {
        echo "<script>showSuccess('Diagnosis added successfully.', true);</script>";
    } else {
        echo "<script>showError('Error adding diagnosis.');</script>";
    }
    $update_query->close();
}

// Process Treatment Form Submission
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['selectedMedicines']) && isset($_POST['newbornIdTreatment'])) {
    $newbornId = sanitize($connection, $_POST['newbornIdTreatment']);
    $selectedMedicines = json_decode($_POST['selectedMedicines'], true);

    if ($selectedMedicines) {
        // Start transaction
        $connection->begin_transaction();
        
        try {
            // 1. First get all current treatments for this newborn
            $current_treatments = [];
            $current_query = $connection->prepare("SELECT medicine_name, medicine_brand, total_quantity FROM tbl_treatment WHERE newborn_id = ?");
            $current_query->bind_param("s", $newbornId);
            $current_query->execute();
            $current_result = $current_query->get_result();
            
            while ($row = $current_result->fetch_assoc()) {
                $key = $row['medicine_name'] . '|' . $row['medicine_brand'];
                $current_treatments[$key] = $row['total_quantity'];
            }
            
            // 2. Delete all existing treatments for this newborn
            $delete_query = $connection->prepare("DELETE FROM tbl_treatment WHERE newborn_id = ?");
            $delete_query->bind_param("s", $newbornId);
            $delete_query->execute();

            // 3. Reset medicine quantities in newborn record
            $reset_query = $connection->prepare("UPDATE tbl_newborn SET medicine_name = '', medicine_brand = '', total_quantity = 0 WHERE newborn_id = ?");
            $reset_query->bind_param("s", $newbornId);
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

                // Get newborn details from newborn record
                $newborn_query = $connection->prepare("SELECT first_name, last_name FROM tbl_newborn WHERE newborn_id = ?");
                $newborn_query->bind_param("s", $newbornId);
                $newborn_query->execute();
                $newborn_result = $newborn_query->get_result();
                $newborn = $newborn_result->fetch_assoc();
                $patientName = $newborn['first_name'] . ' ' . $newborn['last_name'];

                // Insert new treatment record
                $insertQuery = $connection->prepare("
                    INSERT INTO tbl_treatment (newborn_id, patient_name, medicine_name, medicine_brand, total_quantity, price, total_price, treatment_date)
                    VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
                ");
                $insertQuery->bind_param("ssssiss", $newbornId, $patientName, $medicineName, $medicineBrand, $quantity, $price, $totalPrice);
                
                if (!$insertQuery->execute()) {
                    throw new Exception("Error inserting treatment: " . $connection->error);
                }

                // Track new medicines for inventory update
                $key = $medicineName . '|' . $medicineBrand;
                $new_medicines[$key] = $medicineId;
                $new_quantities[$key] = $quantity;
                
                // Update newborn record with concatenated medicine info
                $updateNewbornQuery = $connection->prepare("
                    UPDATE tbl_newborn
                    SET 
                        medicine_name = IF(medicine_name IS NULL OR medicine_name = '', ?, CONCAT(medicine_name, ', ', ?)),
                        medicine_brand = IF(medicine_brand IS NULL OR medicine_brand = '', ?, CONCAT(medicine_brand, ', ', ?)),
                        total_quantity = IF(total_quantity IS NULL, ?, total_quantity + ?)
                    WHERE newborn_id = ?
                ");
                $updateNewbornQuery->bind_param("sssssis", $medicineName, $medicineName, $medicineBrand, $medicineBrand, $quantity, $quantity, $newbornId);
                
                if (!$updateNewbornQuery->execute()) {
                    throw new Exception("Error updating newborn record: " . $connection->error);
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
        // If no medicines selected, just clear all treatments
        $connection->begin_transaction();
        try {
            // Get current treatments to return to inventory
            $current_treatments = [];
            $current_query = $connection->prepare("SELECT medicine_name, medicine_brand, total_quantity FROM tbl_treatment WHERE newborn_id = ?");
            $current_query->bind_param("s", $newbornId);
            $current_query->execute();
            $current_result = $current_query->get_result();
            
            while ($row = $current_result->fetch_assoc()) {
                $key = $row['medicine_name'] . '|' . $row['medicine_brand'];
                $current_treatments[$key] = $row['total_quantity'];
            }
            
            // Delete all treatments
            $delete_query = $connection->prepare("DELETE FROM tbl_treatment WHERE newborn_id = ?");
            $delete_query->bind_param("s", $newbornId);
            $delete_query->execute();

            // Reset newborn record
            $reset_query = $connection->prepare("UPDATE tbl_newborn SET medicine_name = '', medicine_brand = '', total_quantity = 0 WHERE newborn_id = ?");
            $reset_query->bind_param("s", $newbornId);
            $reset_query->execute();

            // Return all medicines to inventory
            foreach ($current_treatments as $key => $quantity) {
                list($name, $brand) = explode('|', $key);
                
                $update_query = $connection->prepare("
                    UPDATE tbl_medicines 
                    SET quantity = quantity + ? 
                    WHERE medicine_name = ? AND medicine_brand = ?
                ");
                $update_query->bind_param("iss", $quantity, $name, $brand);
                
                if (!$update_query->execute()) {
                    throw new Exception("Error returning medicine to inventory: " . $connection->error);
                }
            }
            
            $connection->commit();
            $msg = "All treatments removed successfully.";
        } catch (Exception $e) {
            $connection->rollback();
            $msg = $e->getMessage();
        }
    }
}
ob_end_flush();
?>

<div class="page-wrapper">
    <div class="content">
        <!-- Page Title & Add Button -->
        <div class="row">
            <div class="col-sm-4 col-3">
                <h4 class="page-title">Newborn Records</h4>
            </div>
            <div class="col-sm-8 col-9 text-right m-b-20">
                <?php if ($_SESSION['role'] == 1 || $_SESSION['role'] == 10): ?>
                    <a href="add-newborn.php" class="btn btn-primary float-right">
                        <i class="fa fa-plus"></i> Add Newborn
                    </a>
                <?php endif; ?>
            </div>
        </div>

        <!-- Search Bar -->
        <div class="table-responsive">
            <div class="sticky-search">
                <h5 class="font-weight-bold mb-2">Search Patient:</h5>
                <div class="input-group mb-3">
                    <div class="position-relative w-100">
                        <i class="fa fa-search position-absolute text-secondary" style="top: 50%; left: 12px; transform: translateY(-50%);"></i>
                        <input class="form-control" type="text" id="newbornSearchInput" onkeyup="filterNewborns()" placeholder="Search" style="padding-left: 35px; padding-right: 35px;">
                        <button class="position-absolute border-0 bg-transparent text-secondary" type="button" onclick="clearSearch()" style="top: 50%; right: 10px; transform: translateY(-50%);">
                            <i class="fa fa-times"></i>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Newborn Table -->
        <div class="table-responsive">
            <table class="datatable table table-hover" id="newbornTable">
                <thead style="background-color: #CCCCCC;">
                    <tr>
                        <th>Newborn ID</th>
                        <th>First Name</th>
                        <th>Last Name</th>
                        <th>Gender</th>
                        <th>Address</th>
                        <th>Date of Birth</th>
                        <th>Time of Birth</th>
                        <th>Birth Weight</th>
                        <th>Birth Height</th>
                        <th>Physician</th>
                        <th>Diagnosis</th>
                        <th>Medications</th>
                        <th>Room Type</th>
                        <th>Admission Date and Time</th>
                        <th>Discharge Date and Time</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    // Handle record deletion
                    if (isset($_GET['ids']) && filter_var($_GET['ids'], FILTER_VALIDATE_INT)) {
                        $id = sanitize($connection, $_GET['ids']); // Sanitize input
                        $update_query = $connection->prepare("UPDATE tbl_newborn SET deleted = 1 WHERE id = ?");
                        $update_query->bind_param("i", $id);
                        $update_query->execute();
                        $update_query->close();
                    }

                    // Fetch newborn records
                    if ($_SESSION['role'] == 2) {
                        $fetch_query = $connection->prepare("
                            SELECT n.*, 
                                GROUP_CONCAT(CONCAT(t.medicine_name, ' (', t.medicine_brand, ') - ', t.total_quantity, ' pcs') SEPARATOR '<br>') AS treatments
                            FROM tbl_newborn n
                            LEFT JOIN tbl_treatment t ON n.newborn_id = t.newborn_id
                            WHERE n.deleted = 0 AND n.physician = ?
                            GROUP BY n.newborn_id
                        ");
                        $fetch_query->bind_param("s", $_SESSION['name']);
                    } else {
                        $fetch_query = $connection->prepare("
                            SELECT n.*, 
                                GROUP_CONCAT(CONCAT(t.medicine_name, ' (', t.medicine_brand, ') - ', t.total_quantity, ' pcs') SEPARATOR '<br>') AS treatments
                            FROM tbl_newborn n
                            LEFT JOIN tbl_treatment t ON n.newborn_id = t.newborn_id
                            WHERE n.deleted = 0
                            GROUP BY n.newborn_id
                        ");
                    }
                    $fetch_query->execute();
                    $result = $fetch_query->get_result();      
                    while ($row = $result->fetch_assoc()):
                        $admission_date = date('F d, Y g:i A', strtotime($row['admission_date']));
                        $discharge_date = $row['discharge_date'] ? date('F d, Y g:i A', strtotime($row['discharge_date'])) : 'N/A';
                        
                        $treatmentDetails = $row['treatments'] ?: 'No treatments added';
                    ?>
                        <tr>
                            <td><?= htmlspecialchars($row['newborn_id']); ?></td>
                            <td><?= htmlspecialchars($row['first_name']); ?></td>
                            <td><?= htmlspecialchars($row['last_name']); ?></td>
                            <td><?= htmlspecialchars($row['gender']); ?></td>
                            <td><?= htmlspecialchars($row['address']); ?></td>
                            <td><?= htmlspecialchars($row['dob']); ?></td>
                            <td><?= htmlspecialchars($row['tob']); ?></td>
                            <td><?= htmlspecialchars($row['birth_weight']); ?> kg</td>
                            <td><?= htmlspecialchars($row['birth_height']); ?> cm</td>
                            <td><?= htmlspecialchars($row['physician']); ?></td>
                            <td><?= htmlspecialchars($row['diagnosis']); ?></td>
                            <td>
                                <?php if (!empty($row['treatments'])): ?>
                                    <div><?= nl2br(strip_tags($row['treatments'], '<br>')); ?></div>
                                <?php endif; ?>
                            </td>
                            <td><?= htmlspecialchars($row['room_type']); ?></td>
                            <td><?= $admission_date; ?></td>
                            <td><?= $discharge_date; ?></td>
                            <td class="text-right">
                                <div class="dropdown dropdown-action">
                                    <a href="#" class="action-icon dropdown-toggle" data-toggle="dropdown" aria-expanded="false">
                                        <i class="fa fa-ellipsis-v"></i>
                                    </a>
                                    <div class="dropdown-menu dropdown-menu-right">
                                        <?php if ($_SESSION['role'] == 2) { ?>
                                            <button class="dropdown-item diagnosis-btn" data-toggle="modal" data-target="#diagnosisModal" data-id="<?php echo $row['newborn_id']; ?>" <?php echo !empty($row['diagnosis']) ? 'disabled' : ''; ?>>
                                                <i class="fa fa-stethoscope m-r-5"></i> Diagnosis
                                            </button>
                                        <?php } ?>
                                        <?php if ($_SESSION['role'] == 9) { ?>
                                        <button class="dropdown-item treatment-btn" data-toggle="modal" data-target="#treatmentModal" data-id="<?php echo $row['newborn_id']; ?>">
                                                <i class="fa fa-pills m-r-5"></i> Insert/Edit Treatments
                                            </button>
                                        <?php } ?>
                                        <?php if ($_SESSION['role'] == 1 || $_SESSION['role'] == 9): ?>
                                            <a class="dropdown-item" href="edit-newborn.php?id=<?= htmlspecialchars($row['id']); ?>">
                                                <i class="fa fa-pencil m-r-5"></i> Edit
                                            </a>
                                            <?php endif; ?>
                                            <?php if ($_SESSION['role'] == 1): ?>
                                            <a class="dropdown-item" href="#" onclick="return confirmDelete('<?php echo $row['id']; ?>')">
                                                <i class="fa fa-trash m-r-5"></i> Delete
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Diagnosis Modal -->
<div id="diagnosisModal" class="modal fade" role="dialog">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h4 class="modal-title">Diagnosis</h4>
            </div>
            <div class="modal-body">
                <form id="diagnosisForm" method="post" action="newborn.php">
                    <div class="form-group">
                        <label for="diagnosis">Enter Diagnosis:</label>
                        <input type="text" class="form-control" id="diagnosis" name="diagnosis">
                    </div>
                    <input type="hidden" id="newbornId" name="patientId">
                    <button type="submit" class="btn btn-primary">Submit</button>
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Treatment Modal -->
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
                <form id="medicineSelectionForm" method="POST" action="newborn.php">
                    <input type="hidden" name="newbornIdTreatment" id="newbornIdTreatment">
                    
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

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://ajax.googleapis.com/ajax/libs/jquery/3.5.1/jquery.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script language="JavaScript" type="text/javascript">
function confirmDelete(id) {
    return Swal.fire({
        title: 'Delete Newborn Record?',
        text: 'Are you sure you want to delete this Newborn record? This action cannot be undone!',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#12369e',
        confirmButtonText: 'Yes, delete it!'
    }).then((result) => {
        if (result.isConfirmed) {
            window.location.href = 'newborn.php?ids=' + id;
        }
    });
}
</script>

<script>
let selectedMedicines = [];

// Function to load existing treatments when modal opens
function loadExistingTreatments(newbornId) {
    $.ajax({
        url: 'fetch-existing-nb-treatments.php',
        type: 'GET',
        data: { newborn_id: newbornId },
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
    var newbornId = $(this).data('id');
    $('#newbornIdTreatment').val(newbornId);
    selectedMedicines = []; // Clear the array
    $('#selectedMedicinesList').html(''); // Clear the UI
    $('#selectedMedicines').val(''); // Clear the hidden input
    
    // Load existing treatments
    loadExistingTreatments(newbornId);
});

// Function to search medicines (updated to exclude already selected medicines)
function searchMedicines() {
    const query = document.getElementById('medicineSearchInput').value.trim();
    const newbornId = $('#newbornIdTreatment').val();

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

// Reset selected medicines when opening the modal
$(document).on('click', '.treatment-btn', function(){
    var newbornId = $(this).data('id');
    $('#newbornIdTreatment').val(newbornId);
    selectedMedicines = []; // Clear the array
    $('#selectedMedicinesList').html(''); // Clear the UI
    $('#selectedMedicines').val(''); // Clear the hidden input
});

$(document).on('click', '.diagnosis-btn', function() {
    var newbornId = $(this).data('id');
    $('#newbornId').val(newbornId); // Update the hidden field with newborn ID
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

 $('#newbornTable').on('click', '.dropdown-toggle', function (e) {
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
    function clearSearch() {
        document.getElementById("newbornSearchInput").value = '';
        filterNewborns();
    }

    var role = <?php echo json_encode($_SESSION['role']); ?>;
    var doctor_name = <?php echo json_encode($_SESSION['name']); ?>;

    function filterNewborns() {
        var input = document.getElementById("newbornSearchInput").value;
        
        $.ajax({
            url: 'fetch_newborn.php',
            method: 'GET',
            data: { 
                query: input,
                role: role,
                doctor: doctor_name
            },
            success: function(response) {
                try {
                    var data = JSON.parse(response);
                    updateNewbornsTable(data);
                } catch (e) {
                    console.error("Error parsing JSON response:", e);
                    console.log("Response:", response);
                }
            },
            error: function(xhr, status, error) {
                console.error("AJAX error:", error);
            }
        });
    }

    
    function updateNewbornsTable(data) {
    var tbody = $('#newbornTable tbody');
    tbody.empty();

    data.forEach(function(row) {
        let treatmentContent = row.treatments
            ? `<div>${row.treatments.replace(/\n/g, '<br>')}</div>`
            : '';

        let diagnosisBtn = '';
        if (role == 2) {
            const disabled = row.diagnosis ? 'disabled' : '';
            diagnosisBtn = `
                <button class="dropdown-item diagnosis-btn" data-toggle="modal" data-target="#diagnosisModal" data-id="${row.newborn_id}" ${disabled}>
                    <i class="fa fa-stethoscope m-r-5"></i> Diagnosis
                </button>
            `;
        }

        let treatmentBtn = '';
        if (role == 9) {
            treatmentBtn = `
                <button class="dropdown-item treatment-btn" data-toggle="modal" data-target="#treatmentModal" data-id="${row.newborn_id}">
                    <i class="fa fa-pills m-r-5"></i> Insert/Edit Treatments
                </button>
            `;
        }

        let editDeleteBtns = '';
        if (role == 1 || role == 9) {
            editDeleteBtns += `
                <a class="dropdown-item" href="edit-newborn.php?id=${row.id}">
                    <i class="fa fa-pencil m-r-5"></i> Edit
                </a>
            `;
        }
        if (role == 1) {
            editDeleteBtns += `
                <a class="dropdown-item" href="#" onclick="return confirmDelete('${row.id}')">
                    <i class="fa fa-trash m-r-5"></i> Delete
                </a>
            `;
        }

        tbody.append(`
            <tr>
                <td>${row.newborn_id}</td>
                <td>${row.first_name}</td>
                <td>${row.last_name}</td>
                <td>${row.gender}</td>
                <td>${row.address}</td>
                <td>${row.dob}</td>
                <td>${row.tob}</td>
                <td>${row.birth_weight} kg</td>
                <td>${row.birth_height} cm</td>
                <td>${row.physician}</td>
                <td>${row.diagnosis || ''}</td>
                <td>
                    ${treatmentContent || `
                        <button class="btn btn-primary btn-sm treatment-btn mt-2" data-toggle="modal" data-target="#treatmentModal" data-id="${row.newborn_id}">
                            <i class="fa fa-stethoscope m-r-5"></i> Add/Edit Treatments
                        </button>
                    `}
                </td>
                <td>${row.room_type}</td>
                <td>${row.admission_date}</td>
                <td>${row.discharge_date}</td>
                <td class="text-right">
                    <div class="dropdown dropdown-action">
                        <a href="#" class="action-icon dropdown-toggle" data-toggle="dropdown" aria-expanded="false">
                            <i class="fa fa-ellipsis-v"></i>
                        </a>
                        <div class="dropdown-menu dropdown-menu-right">
                            ${diagnosisBtn}
                            ${treatmentBtn}
                            ${editDeleteBtns}
                        </div>
                    </div>
                </td>
            </tr>
        `);
    });
}

</script>

<style>
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
</style>
