<?php
session_start();
ob_start(); // Start output buffering
if (empty($_SESSION['name'])) {
    header('location:index.php');
    exit;
}
include('header.php');
include('includes/connection.php');

if ($_SESSION['role'] == 1) {
    $editable = true;
} else {
    $editable = false;
}

$can_print = ($_SESSION['role'] == 5);

// Function to sanitize inputs
function sanitize($connection, $input) {
    return mysqli_real_escape_string($connection, htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8'));
}

$role = isset($_SESSION['role']) ? $_SESSION['role'] : null;

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['patientId'])) {
    // Sanitize the patient ID input
    $patientId = sanitize($connection, $_POST['patientId']);

    // Prepare and bind the query for fetching patient details
    $patient_query = $connection->prepare("SELECT * FROM tbl_laborder WHERE id = ?");
    $patient_query->bind_param("s", $patientId);
    
    // Execute the query
    $patient_query->execute();
    $patient_result = $patient_query->get_result();
    $patient = $patient_result->fetch_array(MYSQLI_ASSOC);

    if ($patient) {
        // Retrieve patient details
        $patient_id = $patient['patient_id'];
        $name = $patient['patient_name'];
        $gender = $patient['gender'];
        $dob = $patient['dob'];

        // Fetch the last Electrolytes ID and generate a new one
        $last_query = $connection->prepare("SELECT electrolytes_id FROM tbl_electrolytes ORDER BY id DESC LIMIT 1");
        $last_query->execute();
        $last_result = $last_query->get_result();
        $last_entry = $last_result->fetch_array(MYSQLI_ASSOC);

        if ($last_entry) {
            $last_id_number = (int) substr($last_entry['electrolytes_id'], 4); 
            $new_id = 'ELEC-' . ($last_id_number + 1);
        } else {
            $new_id = 'ELEC-1';  
        }

        // Assign the generated ID to $electrolytes_id
        $electrolytes_id = $new_id;

        // Sanitize user inputs and set NULL if empty
        $sodium = !empty($_POST['sodium']) ? sanitize($connection, $_POST['sodium']) : NULL;
        $potassium = !empty($_POST['potassium']) ? sanitize($connection, $_POST['potassium']) : NULL;
        $chloride = !empty($_POST['chloride']) ? sanitize($connection, $_POST['chloride']) : NULL;
        $calcium = !empty($_POST['calcium']) ? sanitize($connection, $_POST['calcium']) : NULL;

        // Prepare the query to insert with NULL values for empty fields
        $insert_query = $connection->prepare("INSERT INTO tbl_electrolytes (electrolytes_id, patient_id, patient_name, gender, dob, sodium, potassium, chloride, calcium, date_time) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
        
        // Bind parameters
        $insert_query->bind_param("sssssssss", 
            $electrolytes_id, $patient_id, $name, $gender, $dob,
            $sodium, $potassium, $chloride, $calcium
        );

        // Execute the query
        if ($insert_query->execute()) {
            echo "<script>showSuccess('Record added successfully', true);</script>";
        } else {
            echo "<script>showError('Error: " . $insert_query->error . "');</script>";
        }

        // Redirect or show a success message
        header('Location: electrolytes.php');
        exit;
    } else {
        echo "<script>showError('Patient not found. Please check the Patient ID.');</script>";
    }
}

ob_end_flush(); // Flush output buffer
?>

<div class="page-wrapper">
    <div class="content">
        <div class="row">
            <div class="col-sm-4 col-3">
                <h4 class="page-title">Electrolytes</h4>
            </div>
            <?php if ($role == 1 || $role == 5): ?>
                <div class="col-sm-10 col-9 m-b-20">
                    <form method="POST" action="electrolytes.php" id="addPatientForm" class="form-inline">
                        <div class="input-group w-50">
                            <div class="input-group-prepend">
                                <span class="input-group-text">
                                    <i class="fas fa-search"></i> 
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
            <h5 class="font-weight-bold mb-2">Search Patient:</h5>
            <div class="input-group mb-3">
                <div class="position-relative w-100">
                    <i class="fa fa-search position-absolute text-secondary" style="top: 50%; left: 12px; transform: translateY(-50%);"></i>
                    <input class="form-control" type="text" id="electrolytesSearchInput" onkeyup="filterElectrolytes()" style="padding-left: 35px; padding-right: 35px;">
                    <button class="position-absolute border-0 bg-transparent text-secondary" type="button" onclick="clearSearch()" style="top: 50%; right: 10px; transform: translateY(-50%);">
                        <i class="fa fa-times"></i>
                    </button>
                </div>
            </div>
        </div>
        <div class="table-responsive">
            <table class="datatable table table-bordered table-hover" id="electrolytesTable">
                <thead style="background-color: #CCCCCC;">
                    <tr>
                        <th>Electrolytes ID</th>
                        <th>Patient ID</th>
                        <th>Patient Name</th>
                        <th>Gender</th>
                        <th>Age</th>
                        <th>Date and Time</th>
                        <th>Sodium</th>
                        <th>Potassium</th>
                        <th>Chloride</th>
                        <th>Calcium</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $fetch_query = mysqli_query($connection, "SELECT * FROM tbl_electrolytes WHERE deleted = 0 ORDER BY date_time ASC");
                    while ($row = mysqli_fetch_array($fetch_query)) {
                        $dob = $row['dob'];
                        $date = str_replace('/', '-', $dob);
                        $dob = date('Y-m-d', strtotime($date));
                        $year = (date('Y') - date('Y', strtotime($dob)));
                        $date_time = date('F d, Y g:i A', strtotime($row['date_time']));

                        ?>
                        <tr>
                            <td><?php echo $row['electrolytes_id']; ?></td>
                            <td><?php echo $row['patient_id']; ?></td>
                            <td><?php echo $row['patient_name']; ?></td>
                            <td><?php echo $row['gender']; ?></td>
                            <td><?php echo $year; ?></td>
                            <td><?php echo date('F d, Y g:i A', strtotime($row['date_time'])); ?></td>
                            <td><?php echo $row['sodium']; ?></td>
                            <td><?php echo $row['potassium']; ?></td>
                            <td><?php echo $row['chloride']; ?></td>
                            <td><?php echo $row['calcium']; ?></td>
                            <td class="text-right">
                                <div class="dropdown dropdown-action">
                                    <a href="#" class="action-icon dropdown-toggle" data-toggle="dropdown" aria-expanded="false"><i class="fa fa-ellipsis-v"></i></a>
                                    <div class="dropdown-menu dropdown-menu-right">
                                        <?php if ($can_print): ?>
                                        <form action="generate-electrolytes.php" method="get">
                                            <input type="hidden" name="id" value="<?php echo $row['electrolytes_id']; ?>">
                                            <button class="btn btn-primary btn-sm" type="submit"><i class="fa fa-file-pdf-o m-r-5"></i> Generate Result</button>
                                        </form>
                                        <?php endif; ?>
                                        <?php if ($editable): ?>
                                            <a class="dropdown-item" href="edit-electrolytes.php?id=<?php echo $row['electrolytes_id']; ?>"><i class="fa fa-pencil m-r-5"></i> Edit</a>
                                            <a class="dropdown-item" href="electrolytes.php?id=<?php echo $row['electrolytes_id']; ?>" onclick="return confirmDelete()"><i class="fa fa-trash-o m-r-5"></i> Delete</a>
                                        <?php else: ?>
                                            <a class="dropdown-item disabled" href="#"><i class="fa fa-pencil m-r-5"></i> Edit</a>
                                            <a class="dropdown-item disabled" href="#"><i class="fa fa-trash-o m-r-5"></i> Delete</a>
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

<?php
include('footer.php');
?>

<script>
$(document).ready(function() {
    // Handle form submission
    $('#electrolytesForm').on('submit', function(e) {
        e.preventDefault();
        
        // Basic validation
        const required = ['patient_id', 'sodium', 'potassium', 'chloride', 'bicarbonate'];
        let isValid = true;
        let emptyFields = [];
        
        required.forEach(field => {
            if (!$(`#${field}`).val()) {
                isValid = false;
                emptyFields.push(field.replace('_', ' '));
            }
        });
        
        if (!isValid) {
            showError(`Please fill in the following fields: ${emptyFields.join(', ')}`);
            return;
        }
        
        // Validate numeric fields
        const numeric = ['sodium', 'potassium', 'chloride', 'bicarbonate'];
        let invalidFields = [];
        
        numeric.forEach(field => {
            const value = $(`#${field}`).val();
            if (value && !$.isNumeric(value)) {
                isValid = false;
                invalidFields.push(field.replace('_', ' '));
            }
        });
        
        if (invalidFields.length > 0) {
            showError(`The following fields must be numeric: ${invalidFields.join(', ')}`);
            return;
        }
        
        // Show loading state
        showLoading('Saving electrolytes results...');
        
        // Submit the form
        this.submit();
    });
    
    // Handle delete confirmation
    $('.delete-btn').on('click', function(e) {
        e.preventDefault();
        const id = $(this).data('id');
        
        showConfirm(
            'Delete Record?',
            'Are you sure you want to delete this electrolytes record? This action cannot be undone!',
            () => {
                // Show loading state
                showLoading('Deleting record...');
                setTimeout(() => {
                    window.location.href = 'electrolytes.php?ids=' + id;
                }, 500);
            }
        );
    });
    
    // Handle patient search with better UX
    $('#patientSearch').on('keyup', function() {
        const query = $(this).val();
        if (query.length < 2) return;
        
        showLoading('Searching for patient...');
        
        $.ajax({
            url: 'search_patient.php',
            type: 'POST',
            data: { query: query },
            success: function(response) {
                Swal.close();
                try {
                    const data = JSON.parse(response);
                    if (data.success) {
                        // Update patient info fields
                        $('#patient_name').val(data.name);
                        $('#patient_age').val(data.age);
                        $('#patient_gender').val(data.gender);
                        
                        // Show success message
                        showSuccess('Patient found!');
                    } else {
                        showError('Patient not found');
                    }
                } catch (e) {
                    showError('Error searching for patient');
                }
            },
            error: function() {
                showError('Error searching for patient');
            }
        });
    });
    
    // Add reference range validation
    const referenceRanges = {
        sodium: { min: 135, max: 145, unit: 'mEq/L' },
        potassium: { min: 3.5, max: 5.0, unit: 'mEq/L' },
        chloride: { min: 98, max: 106, unit: 'mEq/L' },
        bicarbonate: { min: 22, max: 29, unit: 'mEq/L' }
    };
    
    // Add input handlers for reference range warnings
    Object.keys(referenceRanges).forEach(field => {
        $(`#${field}`).on('change', function() {
            const value = parseFloat($(this).val());
            const range = referenceRanges[field];
            
            if (value < range.min || value > range.max) {
                Swal.fire({
                    icon: 'warning',
                    title: 'Out of Range',
                    text: `${field} value (${value} ${range.unit}) is outside the normal range (${range.min}-${range.max} ${range.unit}). Do you want to continue?`,
                    showCancelButton: true,
                    confirmButtonText: 'Yes',
                    cancelButtonText: 'No'
                }).then((result) => {
                    if (!result.value) {
                        $(this).val('');
                        $(this).focus();
                    }
                });
            }
        });
    });
    
    // Handle AJAX errors globally
    $(document).ajaxError(function(event, jqXHR, settings, error) {
        showError('Error fetching data. Please try again.');
    });
});

// Function to handle record deletion
function deleteRecord(id) {
    showConfirm(
        'Delete Record?',
        'Are you sure you want to delete this electrolytes record? This action cannot be undone!',
        () => {
            // Show loading state
            showLoading('Deleting record...');
            setTimeout(() => {
                window.location.href = 'electrolytes.php?ids=' + id;
            }, 500);
        }
    );
    return false;
}

// Update onclick handlers in table
$(document).ready(function() {
    // Update delete links
    $('a[onclick*="confirm"]').each(function() {
        const id = $(this).attr('href').split('=')[1];
        $(this).attr('onclick', `return deleteRecord('${id}')`);
    });
});

function confirmDelete() {
    return confirm('Are you sure you want to delete this item?');
}

function clearSearch() {
    document.getElementById("electrolytesSearchInput").value = '';
    filterElectrolytes();
}

function filterElectrolytes() {
    var input = document.getElementById("electrolytesSearchInput").value;
    $.ajax({
        url: 'fetch_electrolytes.php',
        type: 'GET',
        data: { query: input },
        success: function(response) {
            var data = JSON.parse(response);
            updateElectrolytesTable(data);
        },
        error: function() {
            alert('Error fetching data. Please try again.');
        }
    });
}

function updateElectrolytesTable(data) {
    var tbody = $('#electrolytesTable tbody');
    tbody.empty();
    data.forEach(function(record) {
        tbody.append(`
            <tr>
                <td>${record.electrolytes_id}</td>
                <td>${record.patient_id}</td>
                <td>${record.patient_name}</td>
                <td>${record.gender}</td>
                <td>${record.age}</td>
                <td>${record.date_time}</td>
                <td>${record.sodium}</td>
                <td>${record.potassium}</td>
                <td>${record.chloride}</td>
                <td>${record.calcium}</td>
            </tr>
        `);
    });
}

function showSuccess(message, redirect = false) {
    Swal.fire({
        icon: 'success',
        title: 'Success',
        text: message,
        showConfirmButton: false,
        timer: 2000
    }).then(() => {
        if (redirect) {
            window.location.href = 'electrolytes.php';
        }
    });
}

function showError(message) {
    Swal.fire({
        icon: 'error',
        title: 'Error',
        text: message,
        showConfirmButton: false,
        timer: 2000
    });
}

function showLoading(message) {
    Swal.fire({
        icon: 'info',
        title: 'Loading',
        text: message,
        showConfirmButton: false,
        allowOutsideClick: false
    });
}

function showConfirm(title, message, callback) {
    Swal.fire({
        icon: 'question',
        title: title,
        text: message,
        showCancelButton: true,
        confirmButtonText: 'Yes',
        cancelButtonText: 'No'
    }).then((result) => {
        if (result.value) {
            callback();
        }
    });
}
</script>

<style>
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
    color: gray;
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
</style> 
