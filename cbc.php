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
    $patient_query->bind_param("s", $patientId);  // "s" stands for string
    
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

        // Fetch the last CBC ID and generate a new one
        $last_cbc_query = $connection->prepare("SELECT cbc_id FROM tbl_cbc ORDER BY id DESC LIMIT 1");
        $last_cbc_query->execute();
        $last_cbc_result = $last_cbc_query->get_result();
        $last_cbc = $last_cbc_result->fetch_array(MYSQLI_ASSOC);

        if ($last_cbc) {
            $last_id_number = (int) substr($last_cbc['cbc_id'], 4); 
            $new_cbc_id = 'CBC-' . ($last_id_number + 1);
        } else {
            $new_cbc_id = 'CBC-1';  
        }

        // Assign the generated ID to $cbc_id
        $cbc_id = $new_cbc_id;

        // Sanitize user inputs and set NULL if empty
        $hemoglobin = !empty($_POST['hemoglobin']) ? sanitize($connection, $_POST['hemoglobin']) : NULL;
        $hematocrit = !empty($_POST['hematocrit']) ? sanitize($connection, $_POST['hematocrit']) : NULL;
        $red_blood_cells = !empty($_POST['red_blood_cells']) ? sanitize($connection, $_POST['red_blood_cells']) : NULL;
        $white_blood_cells = !empty($_POST['white_blood_cells']) ? sanitize($connection, $_POST['white_blood_cells']) : NULL;
        $esr = !empty($_POST['esr']) ? sanitize($connection, $_POST['esr']) : NULL;
        $segmenters = !empty($_POST['segmenters']) ? sanitize($connection, $_POST['segmenters']) : NULL;
        $lymphocytes = !empty($_POST['lymphocytes']) ? sanitize($connection, $_POST['lymphocytes']) : NULL;
        $eosinophils = !empty($_POST['eosinophils']) ? sanitize($connection, $_POST['eosinophils']) : NULL;
        $monocytes = !empty($_POST['monocytes']) ? sanitize($connection, $_POST['monocytes']) : NULL;
        $bands = !empty($_POST['bands']) ? sanitize($connection, $_POST['bands']) : NULL;
        $platelets = !empty($_POST['platelets']) ? sanitize($connection, $_POST['platelets']) : NULL;

        // Prepare the query to insert with NULL values for empty fields
        $insert_query = $connection->prepare("
            INSERT INTO tbl_cbc (cbc_id, patient_id, patient_name, gender, dob, hemoglobin, hematocrit, red_blood_cells, white_blood_cells, esr, segmenters, lymphocytes, eosinophils, monocytes, bands, platelets, date_time) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        
        // Bind parameters
        $insert_query->bind_param("ssssssssssssssss", 
            $cbc_id, $patient_id, $name, $gender, $dob,
            $hemoglobin, $hematocrit, $red_blood_cells, $white_blood_cells,
            $esr, $segmenters, $lymphocytes, $eosinophils, $monocytes, 
            $bands, $platelets
        );

        // Execute the query
        if ($insert_query->execute()) {
            echo "<script>showSuccess('Record added successfully', true);</script>";
        } else {
            echo "<script>showError('Error: " . $insert_query->error . "');</script>";
        }

        // Redirect or show a success message
        header('Location: cbc.php');
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
                <h4 class="page-title">Completed Blood Count</h4>
            </div>
            <?php if ($role == 1 || $role == 5): ?>
                <div class="col-sm-10 col-9 m-b-20">
                    <form method="POST" action="cbc.php" id="addPatientForm" class="form-inline">
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
            <div class="sticky-search">
            <h5 class="font-weight-bold mb-2">Search Patient:</h5>
                <div class="input-group mb-3">
                    <div class="position-relative w-100">
                        <!-- Search Icon -->
                        <i class="fa fa-search position-absolute text-secondary" style="top: 50%; left: 12px; transform: translateY(-50%);"></i>
                        <!-- Input Field -->
                        <input class="form-control" type="text" id="cbcSearchInput" onkeyup="filterCBC()" style="padding-left: 35px; padding-right: 35px;">
                        <!-- Clear Button -->
                        <button class="position-absolute border-0 bg-transparent text-secondary" type="button" onclick="clearSearch()" style="top: 50%; right: 10px; transform: translateY(-50%);">
                            <i class="fa fa-times"></i>
                        </button>
                    </div>
                </div>
            </div>
        </div>
        <div class="table-responsive">
            <table class="datatable table table-bordered table-hover" id="cbcTable">
                <thead style="background-color: #CCCCCC;">
                    <tr>
                        <th>CBC ID</th>
                        <th>Patient ID</th>
                        <th>Patient Name</th>
                        <th>Gender</th>
                        <th>Age</th>
                        <th>Date and Time</th>
                        <th>Hemoglobin</th>
                        <th>Hematocrit</th>
                        <th>Red Blood Cells</th>
                        <th>White Blood Cells</th>
                        <th>ESR</th>
                        <th>Segmenters</th>
                        <th>Lymphocytes</th>
                        <th>Monocytes</th>
                        <th>Bands</th>
                        <th>Platelets</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    if (isset($_GET['ids'])) {
                        $id = sanitize($connection, $_GET['ids']);
                        $update_query = $connection->prepare("UPDATE tbl_cbc SET deleted = 1 WHERE id = ?");
                        $update_query->bind_param("s", $id);
                        $update_query->execute();
                        echo "<script>showSuccess('Record deleted successfully', true);</script>";
                    }
                    $fetch_query = mysqli_query($connection, "SELECT * FROM tbl_cbc WHERE deleted = 0 ORDER BY date_time ASC");
                    while ($row = mysqli_fetch_array($fetch_query)) {
                        $dob = $row['dob'];
                        $date = str_replace('/', '-', $dob); 
                        $dob = date('Y-m-d', strtotime($date));
                        $year = (date('Y') - date('Y',strtotime($dob)));
                        $date_time = date('F d, Y g:i A', strtotime($row['date_time']));
                    ?>
                    <tr>
                        <td><?php echo $row['cbc_id']; ?></td>
                        <td><?php echo $row['patient_id']; ?></td>
                        <td><?php echo $row['patient_name']; ?></td>
                        <td><?php echo $row['gender']; ?></td>
                        <td><?php echo $year; ?></td>
                        <td><?php echo date('F d, Y g:i A', strtotime($row['date_time'])); ?></td>
                        <td><?php echo $row['hemoglobin']; ?></td>
                        <td><?php echo $row['hematocrit']; ?></td>
                        <td><?php echo $row['red_blood_cells']; ?></td>
                        <td><?php echo $row['white_blood_cells']; ?></td>
                        <td><?php echo $row['esr']; ?></td>
                        <td><?php echo $row['segmenters']; ?></td>
                        <td><?php echo $row['lymphocytes']; ?></td>
                        <td><?php echo $row['monocytes']; ?></td>
                        <td><?php echo $row['bands']; ?></td>
                        <td><?php echo $row['platelets']; ?></td>
                        <td class="text-right">
                        <div class="dropdown dropdown-action">
                                <a href="#" class="action-icon dropdown-toggle" data-toggle="dropdown" aria-expanded="false"><i class="fa fa-ellipsis-v"></i></a>
                                <div class="dropdown-menu dropdown-menu-right">
                                    <?php if ($can_print): ?>
                                    <form action="generate-cbc.php" method="get">
                                        <input type="hidden" name="id" value="<?php echo $row['cbc_id']; ?>">
                                        <div class="form-group">
                                            <input type="text" class="form-control" id="filename" name="filename" placeholder="Enter File Name" aria-label="Enter File Name" aria-describedby="basic-addon2">
                                        </div>
                                        <button class="btn btn-primary btn-sm custom-btn" type="submit"><i class="fa fa-file-pdf-o m-r-5"></i> Generate Result</button>
                                    </form>
                                    <?php endif; ?>
                                    <?php if ($editable): ?>
                                        
                                        <!-- Edit and Delete Options -->
                                        <a class="dropdown-item" href="edit-cbc.php?id=<?php echo $row['cbc_id']; ?>"><i class="fa fa-pencil m-r-5"></i> Insert and Edit</a>
                                        <a class="dropdown-item" href="cbc.php?ids=<?php echo $row['cbc_id']; ?>" onclick="return confirmDelete()"><i class="fa fa-trash-o m-r-5"></i> Delete</a>
                                    <?php else: ?>
                                        <!-- Edit and Delete Options Disabled -->
                                        <a class="dropdown-item disabled" href="#">
                                            <i class="fa fa-pencil m-r-5"></i> Edit
                                        </a>
                                        <a class="dropdown-item disabled" href="#">
                                            <i class="fa fa-trash-o m-r-5"></i> Delete
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
<?php
include('footer.php');
?>

<script language="JavaScript" type="text/javascript">
function confirmDelete() {
    return confirm('Are you sure you want to delete this item?');
}

function clearSearch() {
    document.getElementById("cbcSearchInput").value = '';
    filterCBC();
}

let canPrint, userRole;

$(document).ready(function() {
    canPrint = <?php echo $can_print ? 'true' : 'false' ?>;
    userRole = <?php echo $_SESSION['role']; ?>;
});

function filterCBC() {
    var input = document.getElementById("cbcSearchInput").value;
    
    $.ajax({
        url: 'fetch_cbc.php',
        type: 'GET',
        data: { query: input },
        success: function(response) {
            var data = JSON.parse(response);
            updateCBCTable(data);
        },
        error: function(xhr, status, error) {
            alert('Error fetching data. Please try again.');
        }
    });
}

function updateCBCTable(data) {
    var tbody = $('#cbcTable tbody');
    tbody.empty();
    
    data.forEach(function(record) {
        tbody.append(`
            <tr>
                <td>${record.cbc_id}</td>
                <td>${record.patient_id}</td>
                <td>${record.patient_name}</td>
                <td>${record.gender}</td>
                <td>${record.age}</td>
                <td>${record.date_time}</td>
                <td>${record.hemoglobin}</td>
                <td>${record.hematocrit}</td>
                <td>${record.red_blood_cells}</td>
                <td>${record.white_blood_cells}</td>
                <td>${record.esr}</td>
                <td>${record.segmenters}</td>
                <td>${record.lymphocytes}</td>
                <td>${record.monocytes}</td>
                <td>${record.bands}</td>
                <td>${record.platelets}</td>
                <td class="text-right">
                    <div class="dropdown dropdown-action">
                        <a href="#" class="action-icon dropdown-toggle" data-toggle="dropdown" aria-expanded="false">
                            <i class="fa fa-ellipsis-v"></i>
                        </a>
                        <div class="dropdown-menu dropdown-menu-right">
                            ${getActionButtons(record.cbc_id)}
                        </div>
                    </div>
                </td>
            </tr>
        `);
    });
}

function getActionButtons(cbcId) {
    let buttons = '';
    
    if (canPrint) {
        buttons += `
            <form action="generate-cbc.php" method="get">
                <input type="hidden" name="id" value="${cbcId}">
                <div class="form-group">
                    <input type="text" class="form-control" id="filename" name="filename" placeholder="Enter File Name" aria-label="Enter File Name" aria-describedby="basic-addon2">
                </div>
                <button class="btn btn-primary btn-sm custom-btn" type="submit">
                    <i class="fa fa-file-pdf-o m-r-5"></i> Generate Result
                </button>
            </form>
        `;
    }
    
    if (userRole === 1) {
        buttons += `
            <a class="dropdown-item" href="edit-cbc.php?id=${cbcId}">
                <i class="fa fa-pencil m-r-5"></i> Insert and Edit
            </a>
            <a class="dropdown-item" href="cbc.php?ids=${cbcId}" onclick="return confirmDelete()">
                <i class="fa fa-trash-o m-r-5"></i> Delete
            </a>
        `;
    } else {
        buttons += `
            <a class="dropdown-item disabled" href="#">
                <i class="fa fa-pencil m-r-5"></i> Edit
            </a>
            <a class="dropdown-item disabled" href="#">
                <i class="fa fa-trash-o m-r-5"></i> Delete
            </a>
        `;
    }
    
    return buttons;
}

function deleteRecord(id) {
    Swal.fire({
        title: 'Delete CBC Record?',
        text: 'Are you sure you want to delete this CBC record? This action cannot be undone!',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Yes, delete it!',
        cancelButtonText: 'No, cancel!',
        reverseButtons: true
    }).then((result) => {
        if (result.value) {
            setTimeout(() => {
                window.location.href = 'cbc.php?ids=' + id;
            }, 500);
        }
    });
    return false;
}

function searchPatients() {
    var input = document.getElementById("patientSearchInput").value;
    if (input.length < 2) {
        document.getElementById("searchResults").style.display = "none";
        document.getElementById("searchResults").innerHTML = "";
        return;
    }
    Swal.fire({
        title: 'Searching...',
        html: 'Please wait while we search for patients...',
        allowOutsideClick: false,
        showConfirmButton: false,
        onBeforeOpen: () => {
            Swal.showLoading();
        }
    });
    $.ajax({
        url: "search-cbc.php",
        method: "GET",
        data: { query: input },
        success: function(response) {
            Swal.close();
            try {
                const data = JSON.parse(response);
                if (data.success) {
                    // Update patient info fields
                    $('#patient_name').val(data.name);
                    $('#patient_age').val(data.age);
                    $('#patient_gender').val(data.gender);
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Patient not found',
                        text: 'Please check the patient ID and try again.'
                    });
                }
            } catch (e) {
                Swal.fire({
                    icon: 'error',
                    title: 'Error searching for patient',
                    text: 'Please try again later.'
                });
            }
        },
        error: function() {
            Swal.fire({
                icon: 'error',
                title: 'Error searching for patient',
                text: 'Please try again later.'
            });
        }
    });
}

function showError(message) {
    Swal.fire({
        icon: 'error',
        title: 'Error',
        text: message
    });
}

function showSuccess(message, redirect) {
    Swal.fire({
        icon: 'success',
        title: 'Success',
        text: message
    }).then((result) => {
        if (redirect) {
            window.location.href = 'cbc.php';
        }
    });
}

function showLoading(message) {
    Swal.fire({
        title: message,
        html: 'Please wait...',
        allowOutsideClick: false,
        showConfirmButton: false,
        onBeforeOpen: () => {
            Swal.showLoading();
        }
    });
}

function showConfirm(title, message, callback) {
    Swal.fire({
        title: title,
        text: message,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Yes, delete it!',
        cancelButtonText: 'No, cancel!',
        reverseButtons: true
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
