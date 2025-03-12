<?php
session_start();
if (empty($_SESSION['name'])) {
    header('location:index.php');
}
include('header.php');
include('includes/connection.php');

function sanitize($data) {
    return htmlspecialchars(strip_tags($data), ENT_QUOTES, 'UTF-8');
}

$msg = null;

// Handle treatment submission
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['selectedMedicines']) && isset($_POST['newbornIdTreatment'])) {
    error_log("POST data received: " . print_r($_POST, true)); // Debug log
    
    $newbornId = trim(sanitize($_POST['newbornIdTreatment']));
    // Ensure newborn ID has the correct format (NB-X)
    if (strpos($newbornId, 'NB-') === false) {
        $newbornId = 'NB-' . $newbornId;
    }
    error_log("Formatted newborn ID: " . $newbornId); // Debug log
    
    $selectedMedicines = json_decode($_POST['selectedMedicines'], true);
    error_log("Decoded medicines: " . print_r($selectedMedicines, true)); // Debug log

    if ($selectedMedicines && $newbornId) {
        // First get the patient name from tbl_newborn
        $getPatientQuery = $connection->prepare("SELECT first_name, last_name FROM tbl_newborn WHERE newborn_id = ? AND deleted = 0");
        if (!$getPatientQuery) {
            echo json_encode(['error' => 'Error preparing patient query: ' . $connection->error]);
            exit;
        }
        
        $getPatientQuery->bind_param("s", $newbornId);
        if (!$getPatientQuery->execute()) {
            echo json_encode(['error' => 'Error executing patient query: ' . $getPatientQuery->error]);
            exit;
        }
        
        $patientResult = $getPatientQuery->get_result();
        if (!$patientResult || $patientResult->num_rows === 0) {
            error_log("No patient found with newborn ID: " . $newbornId);
            error_log("SQL Query: SELECT first_name, last_name FROM tbl_newborn WHERE newborn_id = '" . $newbornId . "' AND deleted = 0");
            echo json_encode(['error' => 'Patient not found with newborn ID: ' . $newbornId]);
            exit;
        }
        
        $patient = $patientResult->fetch_assoc();
        $patientName = $patient['first_name'] . ' ' . $patient['last_name'];
        error_log("Found patient: " . $patientName); // Debug log

        foreach ($selectedMedicines as $medicine) {
            $medicineId = sanitize($medicine['id']);
            $medicineName = sanitize($medicine['name']);
            $medicineBrand = sanitize($medicine['brand']);
            $quantity = intval($medicine['quantity']);
            $price = floatval($medicine['price']);
            $totalPrice = $quantity * $price;

            error_log("Processing medicine: " . $medicineName); // Debug log

            // Insert into tbl_treatment
            $insertQuery = $connection->prepare("
                INSERT INTO tbl_treatment (newborn_id, patient_name, medicine_name, medicine_brand, total_quantity, price, total_price, treatment_date)
                SELECT ?, CONCAT(first_name, ' ', last_name), ?, ?, ?, ?, ?, NOW()
                FROM tbl_newborn
                WHERE newborn_id = ?
            ");
            if (!$insertQuery) {
                echo json_encode(['error' => 'Error preparing insert query: ' . $connection->error]);
                exit;
            }

            $insertQuery->bind_param("ssssdds", $newbornId, $medicineName, $medicineBrand, $quantity, $price, $totalPrice, $newbornId);
            if (!$insertQuery->execute()) {
                echo json_encode(['error' => 'Error inserting treatment: ' . $insertQuery->error]);
                exit;
            }
            error_log("Treatment inserted successfully"); // Debug log

            // Update medicine quantity
            $updateMedicineQuery = $connection->prepare("UPDATE tbl_medicines SET quantity = quantity - ? WHERE id = ?");
            if (!$updateMedicineQuery) {
                echo json_encode(['error' => 'Error preparing medicine update query: ' . $connection->error]);
                exit;
            }

            $updateMedicineQuery->bind_param("is", $quantity, $medicineId);
            if (!$updateMedicineQuery->execute()) {
                echo json_encode(['error' => 'Error updating medicine quantity: ' . $updateMedicineQuery->error]);
                exit;
            }
            error_log("Medicine quantity updated"); // Debug log

            // Update newborn record
            $updateNewbornQuery = $connection->prepare("
                UPDATE tbl_newborn 
                SET 
                    medicine_name = CASE 
                        WHEN medicine_name IS NULL OR medicine_name = '' THEN ?
                        ELSE CONCAT(medicine_name, ', ', ?)
                    END,
                    medicine_brand = CASE
                        WHEN medicine_brand IS NULL OR medicine_brand = '' THEN ?
                        ELSE CONCAT(medicine_brand, ', ', ?)
                    END,
                    total_quantity = CASE
                        WHEN total_quantity IS NULL OR total_quantity = 0 THEN ?
                        ELSE total_quantity + ?
                    END
                WHERE newborn_id = ?
            ");
            if (!$updateNewbornQuery) {
                echo json_encode(['error' => 'Error preparing newborn update query: ' . $connection->error]);
                exit;
            }

            $updateNewbornQuery->bind_param("ssssiis", $medicineName, $medicineName, $medicineBrand, $medicineBrand, $quantity, $quantity, $newbornId);
            if (!$updateNewbornQuery->execute()) {
                error_log("Failed to update newborn record. SQL State: " . $updateNewbornQuery->sqlstate);
                error_log("Error: " . $updateNewbornQuery->error);
                echo json_encode(['error' => 'Error updating newborn record: ' . $updateNewbornQuery->error . ' (SQL State: ' . $updateNewbornQuery->sqlstate . ')']);
                exit;
            }
            error_log("Newborn record updated"); // Debug log
        }

        echo json_encode(['success' => 'Treatment added successfully']);
        exit;
    } else {
        $error = [];
        if (!$selectedMedicines) $error[] = 'No medicines selected';
        if (!$newbornId) $error[] = 'No newborn ID provided';
        echo json_encode(['error' => implode(', ', $error)]);
        exit;
    }
}

// Handle discharge submission
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['discharge_id'])) {
    ob_clean(); // Clear any previous output
    header('Content-Type: application/json');
    $response = array();
    
    try {
        $discharge_id = trim(sanitize($_POST['discharge_id']));
        
        // Update discharge datetime
        $update_query = "UPDATE tbl_newborn SET discharge_datetime = NOW() WHERE newborn_id = ? AND deleted = 0";
        $stmt = $connection->prepare($update_query);
        if (!$stmt) {
            throw new Exception("Error preparing update: " . $connection->error);
        }
        
        $stmt->bind_param("s", $discharge_id);
        if (!$stmt->execute()) {
            throw new Exception("Error updating discharge time: " . $stmt->error);
        }
        
        if ($stmt->affected_rows > 0) {
            // Get the updated discharge time
            $get_time_query = "SELECT discharge_datetime FROM tbl_newborn WHERE newborn_id = ? AND deleted = 0";
            $stmt = $connection->prepare($get_time_query);
            $stmt->bind_param("s", $discharge_id);
            $stmt->execute();
            $time_result = $stmt->get_result();
            $time_row = $time_result->fetch_assoc();
            
            $response['success'] = true;
            $response['message'] = "Newborn discharged successfully.";
            $response['discharge_datetime'] = $time_row['discharge_datetime'];
        } else {
            throw new Exception("Failed to update discharge time.");
        }
        
    } catch (Exception $e) {
        $response['success'] = false;
        $response['error'] = $e->getMessage();
    }
    
    echo json_encode($response);
    exit;
}

?>

<div class="page-wrapper">
    <div class="content">
        <div class="row">
            <div class="col-sm-4 col-3">
                <h4 class="page-title">Newborn Records</h4>
            </div>
            <div class="col-sm-8 col-9 text-right m-b-20">
                <?php 
                if ($_SESSION['role'] == 1 || $_SESSION['role'] == 3) {  
                    echo '<a href="add-newborn.php" class="btn btn-primary float-right"><i class="fa fa-plus"></i> Add Newborn </a>';
                }
                ?>
            </div>
        </div>
        <div class="table-responsive">
            <div class="sticky-search">
            <h5 class="font-weight-bold mb-2">Search Patient:</h5>
                <div class="input-group mb-3">
                    <div class="position-relative w-100">
                        <!-- Search Icon -->
                        <i class="fa fa-search position-absolute text-secondary" style="top: 50%; left: 12px; transform: translateY(-50%);"></i>
                        <!-- Input Field -->
                        <input class="form-control" type="text" id="newbornSearchInput" onkeyup="filterNewborns()" style="padding-left: 35px; padding-right: 35px;">
                        <!-- Clear Button -->
                        <button class="position-absolute border-0 bg-transparent text-secondary" type="button" onclick="clearSearch()" style="top: 50%; right: 10px; transform: translateY(-50%);">
                            <i class="fa fa-times"></i>
                        </button>
                    </div>
                </div>
            </div>
        </div>
        <div class="table-responsive">
            <table class="datatable table table-hover" id="newbornTable">
                <thead style="background-color: #CCCCCC;">
                    <tr>
                        <th>Newborn ID</th>
                        <th>First Name</th>
                        <th>Last Name</th>
                        <th>Gender</th>
                        <th>Date of Birth</th>
                        <th>Time of Birth</th>
                        <th>Birth Weight</th>
                        <th>Birth Height</th>
                        <th>Medications</th>
                        <th>Room Type</th>
                        <th>Admission Date and Time</th>
                        <th>Discharge Date and Time</th>
                        <th>Physician</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    if(isset($_GET['ids'])){
                        $id = sanitize($_GET['ids']);
                        $update_query = $connection->prepare("UPDATE tbl_newborn SET deleted = 1 WHERE id = ?");
                        $update_query->bind_param("s", $id);
                        $update_query->execute();
                    }

                    $query = "SELECT n.*, 
                             GROUP_CONCAT(
                                 CONCAT(t.medicine_name, ' (', t.medicine_brand, ') - ', t.total_quantity, ' pcs')
                                 ORDER BY t.treatment_date 
                                 SEPARATOR '<br>'
                             ) as treatments
                             FROM tbl_newborn n 
                             LEFT JOIN tbl_treatment t ON n.newborn_id = t.newborn_id 
                             WHERE n.deleted = 0 
                             GROUP BY n.id, n.newborn_id 
                             ORDER BY n.admission_datetime DESC";
                    
                    $result = $connection->query($query);
                    
                    if ($result->num_rows > 0) {
                        while($row = $result->fetch_assoc()) {
                            // Format dates using PHP date function
                            $admission_datetime = date('F j, Y g:i A', strtotime($row['admission_datetime']));
                            $discharge_datetime = !empty($row['discharge_datetime']) ? date('F j, Y g:i A', strtotime($row['discharge_datetime'])) : 'N/A';
                    ?>
                            <tr>
                                <td><?php echo htmlspecialchars($row['newborn_id']); ?></td>
                                <td><?php echo htmlspecialchars($row['first_name']); ?></td>
                                <td><?php echo htmlspecialchars($row['last_name']); ?></td>
                                <td><?php echo htmlspecialchars($row['gender']); ?></td>
                                <td><?php echo htmlspecialchars($row['dob']); ?></td>
                                <td><?php echo htmlspecialchars($row['tob']); ?></td>
                                <td><?php echo htmlspecialchars($row['birth_weight']); ?></td>
                                <td><?php echo htmlspecialchars($row['birth_height']); ?></td>
                                <td>
                                    <?php if (!empty($row['treatments'])): ?>
                                        <div class="text-wrap" style="max-width: 200px;">
                                            <?php echo $row['treatments']; ?>
                                        </div>
                                    <?php else: ?>
                                        <?php if (empty($row['discharge_datetime'])): ?>
                                            <button class="btn btn-primary btn-sm treatment-btn" onclick="openTreatmentModal('<?php echo htmlspecialchars($row['newborn_id']); ?>')">
                                                <i class="fa fa-plus m-r-5"></i> Add Treatments
                                            </button>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo htmlspecialchars($row['room_type']); ?></td>
                                <td><?php echo $admission_datetime; ?></td>
                                <td><?php echo $discharge_datetime; ?></td>
                                <td><?php echo htmlspecialchars($row['physician']); ?></td>
                                <td class="text-right">
                                    <div class="dropdown dropdown-action">
                                        <a href="#" class="action-icon dropdown-toggle" data-toggle="dropdown" aria-expanded="false"><i class="fa fa-ellipsis-v"></i></a>
                                        <div class="dropdown-menu dropdown-menu-right">
                                        <?php if ($_SESSION['role'] == 1 || $_SESSION['role'] == 3): ?>
                                                    <?php if (empty($row['discharge_datetime'])): ?>
                                                        <a class="dropdown-item" href="#" onclick="dischargeNewborn('<?php echo htmlspecialchars($row['newborn_id']); ?>', event)">
                                                            <i class="fa fa-sign-out m-r-5"></i> Discharge
                                                        </a>
                                                        <a class="dropdown-item" href="#" onclick="openTreatmentModal('<?php echo htmlspecialchars($row['newborn_id']); ?>')">
                                                            <i class="fa fa-plus m-r-5"></i> Add Treatment
                                                        </a>
                                                    <?php endif; ?>
                                                    <a class="dropdown-item" href="newborn.php?ids=<?php echo htmlspecialchars($row['id']); ?>" onclick="return confirmDelete()">
                                                        <i class="fa fa-trash-o m-r-5"></i> Delete
                                                    </a>
                                                <?php endif; ?>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                    <?php
                        }
                    }
                    ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Treatment Modal -->
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
                <form id="medicineSelectionForm" method="POST">
                    <input type="hidden" name="newbornIdTreatment" id="newbornIdTreatment">
                    <div id="currentNewbornId" class="mb-3 text-muted"></div>
                    
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
                        <table class="table table-hover">
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

<script>
// Function to filter newborns
function filterNewborns() {
    const searchQuery = $('#newbornSearchInput').val().trim();
    
    $.ajax({
        url: 'fetch_newborn.php',
        type: 'GET',
        data: { query: searchQuery },
        success: function(response) {
            try {
                const newborns = JSON.parse(response);
                let tbody = $('#newbornTable tbody');
                tbody.empty();
                
                newborns.forEach(function(row) {
                    const admissionDate = new Date(row.admission_datetime);
                    const dischargeDate = row.discharge_datetime ? new Date(row.discharge_datetime) : null;
                    
                    let tr = `<tr>
                        <td>${row.newborn_id}</td>
                        <td>${row.first_name}</td>
                        <td>${row.last_name}</td>
                        <td>${row.gender}</td>
                        <td>${row.dob}</td>
                        <td>${row.tob}</td>
                        <td>${row.birth_weight}</td>
                        <td>${row.birth_height}</td>
                        <td>`;
                        
                    if (row.treatments) {
                        tr += `<div class="text-wrap" style="max-width: 200px;">${row.treatments}</div>`;
                    } else {
                        if (!row.discharge_datetime) {
                            tr += `<button class="btn btn-primary btn-sm treatment-btn" onclick="openTreatmentModal('${row.newborn_id}')">
                                <i class="fa fa-plus m-r-5"></i> Add Treatments
                            </button>`;
                        }
                    }
                    
                    tr += `</td>
                        <td>${row.room_type}</td>
                        <td>${formatDate(row.admission_datetime)}</td>
                        <td>${formatDate(row.discharge_datetime) || 'N/A'}</td>
                        <td>${row.physician}</td>
                        <td class="text-right">
                            <div class="dropdown dropdown-action">
                                <a href="#" class="action-icon dropdown-toggle" data-toggle="dropdown" aria-expanded="false">
                                    <i class="fa fa-ellipsis-v"></i>
                                </a>
                                <div class="dropdown-menu dropdown-menu-right">`;
                    
                    const userRole = '<?php echo $_SESSION['role']; ?>';
                    if (userRole === '1' || userRole === '3') {
                        if (!row.discharge_datetime) {
                            tr += `<a class="dropdown-item" href="#" onclick="dischargeNewborn('${row.newborn_id}', event)">
                                <i class="fa fa-sign-out m-r-5"></i> Discharge
                            </a>
                            <a class="dropdown-item" href="#" onclick="openTreatmentModal('${row.newborn_id}')">
                                <i class="fa fa-plus m-r-5"></i> Add Treatment
                            </a>`;
                        }
                        tr += `<a class="dropdown-item" href="newborn.php?ids=${row.id}" onclick="return confirmDelete()">
                            <i class="fa fa-trash-o m-r-5"></i> Delete
                        </a>`;
                    }
                    
                    tr += `</div></div></td></tr>`;
                    
                    tbody.append(tr);
                });
            } catch (e) {
                console.error('Error parsing response:', e);
            }
        },
        error: function(xhr, status, error) {
            console.error('Error fetching data:', error);
        }
    });
}

// Function to format date consistently
function formatDate(dateString) {
    if (!dateString) return 'N/A';
    const date = new Date(dateString);
    const months = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
    const month = months[date.getMonth()];
    const day = date.getDate();
    const year = date.getFullYear();
    let hours = date.getHours();
    const minutes = date.getMinutes().toString().padStart(2, '0');
    const ampm = hours >= 12 ? 'PM' : 'AM';
    hours = hours % 12;
    hours = hours ? hours : 12; // Convert 0 to 12
    return `${month} ${day}, ${year} ${hours}:${minutes} ${ampm}`;
}

// Function to clear search
function clearSearch() {
    $('#newbornSearchInput').val('');
    filterNewborns();
}

// Add user role to window object for access in dynamic content
const userRole = '<?php echo $_SESSION['role']; ?>';

// Add debounce to search
let searchTimeout;
$('#newbornSearchInput').on('keyup', function() {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(filterNewborns, 300);
});

// Initialize variables
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
                $('#medicineSearchResults').html(data);
            },
            error: function () {
                alert('Error fetching medicines. Please try again later.');
            }
        });
    } else {
        $('#medicineSearchResults').html('<tr><td colspan="7">Please enter at least 3 characters to search.</td></tr>');
    }
}

// Function to open treatment modal
function openTreatmentModal(newbornId) {
    console.log('openTreatmentModal called with ID:', newbornId);
    
    if (!newbornId) {
        console.error('No newborn ID provided to openTreatmentModal');
        return;
    }
    
    try {
        // Remove 'NB-' prefix if it exists, as we'll add it on the server side
        newbornId = newbornId.replace('NB-', '');
        console.log('Processed newborn ID:', newbornId);
        
        $('#newbornIdTreatment').val(newbornId);
        $('#currentNewbornId').text('Adding treatments for Newborn ID: NB-' + newbornId);
        selectedMedicines = [];
        $('#selectedMedicinesList').html('');
        $('#selectedMedicines').val('');
        
        $('#treatmentModal').modal('show');
    } catch (error) {
        console.error('Error in openTreatmentModal:', error);
    }
}

// Function to discharge newborn
function dischargeNewborn(id, event) {
    if (event) {
        event.preventDefault();
    }
    
    Swal.fire({
        title: 'Are you sure?',
        text: "You are about to discharge this newborn. This action cannot be undone!",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#12369e',
        cancelButtonColor: '#f62d51',
        confirmButtonText: 'Yes, discharge!'
    }).then((result) => {
        if (result.isConfirmed) {
            // Show loading state
            Swal.fire({
                title: 'Processing...',
                text: 'Discharging newborn...',
                allowOutsideClick: false,
                showConfirmButton: false,
                willOpen: () => {
                    Swal.showLoading();
                }
            });
            
            // Make the AJAX request
            $.ajax({
                url: window.location.pathname,
                type: 'POST',
                data: { discharge_id: id },
                complete: function() {
                    // Always reload after a short delay
                    setTimeout(() => {
                        window.location.reload();
                    }, 500);
                }
            });
        }
    });
}

// Document ready handler
$(document).ready(function() {
    // Initialize modal
    $('#treatmentModal').modal({
        backdrop: 'static',
        keyboard: false
    });
    
    // Handle form submission
    $('#medicineSelectionForm').on('submit', function(e) {
        e.preventDefault();
        
        if (selectedMedicines.length === 0) {
            Swal.fire({
                title: 'Error!',
                text: 'Please select at least one medicine.',
                icon: 'error',
                confirmButtonColor: '#f62d51'
            });
            return;
        }

        const newbornId = $('#newbornIdTreatment').val();
        
        if (!newbornId) {
            Swal.fire({
                title: 'Error!',
                text: 'No newborn ID provided',
                icon: 'error',
                confirmButtonColor: '#f62d51'
            });
            return;
        }
        
        // Show loading state
        Swal.fire({
            title: 'Processing...',
            text: 'Adding treatment...',
            allowOutsideClick: false,
            showConfirmButton: false,
            willOpen: () => {
                Swal.showLoading();
            }
        });
        
        $.ajax({
            url: window.location.pathname,
            type: 'POST',
            data: {
                selectedMedicines: JSON.stringify(selectedMedicines),
                newbornIdTreatment: newbornId
            },
            complete: function() {
                // Always reload after a short delay
                setTimeout(() => {
                    window.location.reload();
                }, 500);
            }
        });
    });
});

// Function to confirm delete
function confirmDelete() {
    event.preventDefault();
    
    Swal.fire({
        title: 'Are you sure?',
        text: "You are about to delete this record. This action cannot be undone!",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#12369e',
        cancelButtonColor: '#f62d51',
        confirmButtonText: 'Yes, delete!'
    }).then((result) => {
        if (result.isConfirmed) {
            // Show loading state
            Swal.fire({
                title: 'Processing...',
                text: 'Deleting record...',
                allowOutsideClick: false,
                showConfirmButton: false,
                willOpen: () => {
                    Swal.showLoading();
                }
            });
            
            // Get the delete URL from the clicked link
            const deleteUrl = event.target.closest('a').href;
            
            // Redirect after a short delay
            setTimeout(() => {
                window.location.href = deleteUrl;
            }, 500);
        }
    });
    
    return false;
}

// Function to add medicine to the selected list
function addMedicineToList(id, name, brand, category, availableQuantity, price, expiration_date, event) {
    if (event) {
        event.preventDefault();
        event.stopPropagation();
    }

    const quantityInput = parseInt(document.getElementById(`quantityInput-${id}`).value, 10);

    if (isNaN(quantityInput) || quantityInput <= 0) {
        alert('Please enter a valid quantity greater than 0.');
        return;
    }

    if (quantityInput > availableQuantity) {
        alert('Requested quantity exceeds available stock (' + availableQuantity + ' available).');
        return;
    }

    // Check if the medicine is already in the list
    const existingMedicineIndex = selectedMedicines.findIndex(medicine => medicine.id === id);

    if (existingMedicineIndex !== -1) {
        // If the medicine exists, update the quantity
        const newQuantity = selectedMedicines[existingMedicineIndex].quantity + quantityInput;
        if (newQuantity > availableQuantity) {
            alert('Total quantity would exceed available stock.');
            return;
        }
        selectedMedicines[existingMedicineIndex].quantity = newQuantity;
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

    // Clear the quantity input
    document.getElementById(`quantityInput-${id}`).value = '';

    // Update the UI
    updateSelectedMedicinesUI();
}

// Function to update the selected medicines UI
function updateSelectedMedicinesUI() {
    $('#selectedMedicinesList').html('');

    selectedMedicines.forEach((medicine, index) => {
        const totalPrice = (medicine.quantity * medicine.price).toFixed(2);
        const listItem = `
            <li class="list-group-item d-flex justify-content-between align-items-center">
                <div>
                    <strong>${medicine.name} (${medicine.brand})</strong><br>
                    <small class="text-muted">
                        Category: ${medicine.category}<br>
                        Quantity: ${medicine.quantity} @ ₱${medicine.price} each<br>
                        Total: ₱${totalPrice}<br>
                        Expiration: ${medicine.expiration_date}
                    </small>
                </div>
                <button 
                    type="button" 
                    class="btn btn-danger btn-sm" 
                    onclick="removeMedicineFromList(${index})">
                    <i class="fa fa-trash"></i>
                </button>
            </li>`;
        $('#selectedMedicinesList').append(listItem);
    });

    // Update hidden input with selected medicines data
    $('#selectedMedicines').val(JSON.stringify(selectedMedicines));
}

// Function to remove a medicine from the selected list
function removeMedicineFromList(index) {
    selectedMedicines.splice(index, 1);
    updateSelectedMedicinesUI();
}
</script>

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<?php include('footer.php'); ?>
