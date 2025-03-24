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

$role = isset($_SESSION['role']) ? $_SESSION['role'] : null;

// Function to sanitize inputs
function sanitize($connection, $input) {
    return mysqli_real_escape_string($connection, htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8'));
}

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
        $patient_id = $patient['patient_id'];
        $name = $patient['patient_name'];
        $gender = $patient['gender'];
        $dob = $patient['dob'];

        // Generate new crossmatching ID
        $last_query = $connection->prepare("SELECT crossmatching_id FROM tbl_crossmatching ORDER BY id DESC LIMIT 1");
        $last_query->execute();
        $last_result = $last_query->get_result();
        $last_entry = $last_result->fetch_array(MYSQLI_ASSOC);

        if ($last_entry) {
            $last_id_number = (int) substr($last_entry['crossmatching_id'], 8); 
            $new_id = 'XMATCH-' . ($last_id_number + 1);
        } else {
            $new_id = 'XMATCH-1';  
        }

        // Sanitize inputs
        $patient_blood_type = !empty($_POST['patient_blood_type']) ? sanitize($connection, $_POST['patient_blood_type']) : '';
        $blood_component = !empty($_POST['blood_component']) ? sanitize($connection, $_POST['blood_component']) : '';
        $serial_number = !empty($_POST['serial_number']) ? sanitize($connection, $_POST['serial_number']) : '';
        $extraction_date = !empty($_POST['extraction_date']) ? sanitize($connection, $_POST['extraction_date']) : '';
        $expiration_date = !empty($_POST['expiration_date']) ? sanitize($connection, $_POST['expiration_date']) : '';
        $major_crossmatching = !empty($_POST['major_crossmatching']) ? sanitize($connection, $_POST['major_crossmatching']) : '';
        $donors_blood_type = !empty($_POST['donors_blood_type']) ? sanitize($connection, $_POST['donors_blood_type']) : '';
        $packed_red_blood_cell = !empty($_POST['packed_red_blood_cell']) ? sanitize($connection, $_POST['packed_red_blood_cell']) : '';
        $time_packed = !empty($_POST['time_packed']) ? sanitize($connection, $_POST['time_packed']) : '';
        $dated = !empty($_POST['dated']) ? sanitize($connection, $_POST['dated']) : '';
        $open_system = !empty($_POST['open_system']) ? sanitize($connection, $_POST['open_system']) : '';
        $closed_system = !empty($_POST['closed_system']) ? sanitize($connection, $_POST['closed_system']) : '';
        $to_be_consumed_before = !empty($_POST['to_be_consumed_before']) ? sanitize($connection, $_POST['to_be_consumed_before']) : '';
        $hours = !empty($_POST['hours']) ? sanitize($connection, $_POST['hours']) : '';
        $minor_crossmatching = !empty($_POST['minor_crossmatching']) ? sanitize($connection, $_POST['minor_crossmatching']) : '';

        // Fixed INSERT query (removed extra comma and fixed parameter order)
        $insert_query = $connection->prepare("
            INSERT INTO tbl_crossmatching (
                crossmatching_id, patient_id, patient_name, gender, dob, 
                patient_blood_type, blood_component, serial_number, extraction_date, expiration_date, 
                major_crossmatching, donors_blood_type, packed_red_blood_cell, time_packed, dated, 
                open_system, closed_system, to_be_consumed_before, hours, minor_crossmatching, date_time
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");

        // Bind parameters correctly
        $insert_query->bind_param(
            "ssssssssssssssssssss",  
            $new_id, $patient_id, $name, $gender, $dob, 
            $patient_blood_type, $blood_component, $serial_number, $extraction_date, $expiration_date, 
            $major_crossmatching, $donors_blood_type, $packed_red_blood_cell, $time_packed, $dated, 
            $open_system, $closed_system, $to_be_consumed_before, $hours, $minor_crossmatching
        );
        
        // Execute and check for success
        if ($insert_query->execute()) {
            echo "<script>
                Swal.fire({
                    title: 'Processing...',
                    text: 'Saving crossmatching record...',
                    allowOutsideClick: false,
                    showConfirmButton: false,
                    willOpen: () => {
                        Swal.showLoading();
                    }
                });
        
                setTimeout(() => {
                    Swal.fire({
                        title: 'Success!',
                        text: 'Crossmatching record added successfully.',
                        icon: 'success',
                        confirmButtonColor: '#12369e',
                        confirmButtonText: 'OK'
                    }).then(() => {
                        window.location.href = 'crossmatching.php';
                    });
                }, 1000);
            </script>";
        } else {
            echo "<script>
                Swal.fire({
                    title: 'Error!',
                    text: 'Failed to add crossmatching record. Please try again.',
                    icon: 'error',
                    confirmButtonColor: '#12369e',
                    confirmButtonText: 'OK'
                });
            </script>";
        }
    } else {
        echo "<script>
            Swal.fire({
                title: 'Error!',
                text: 'Patient not found. Please try again.',
                icon: 'error',
                confirmButtonColor: '#12369e',
                confirmButtonText: 'OK'
            });
        </script>";
    }
}

ob_end_flush();
?>

<div class="page-wrapper">
    <div class="content">
        <div class="row">
            <div class="col-sm-4 col-3">
                <h4 class="page-title">Crossmatching</h4>
            </div>
            
            <?php if ($role == 1 || $role == 5): ?>
                <div class="col-sm-10 col-9 m-b-20">
                    <form method="POST" action="crossmatching.php" id="addPatientForm" class="form-inline">
                        <div class="input-group w-50">
                            <div class="input-group-prepend">
                                <span class="input-group-text">
                                    <i class="fas fa-search"></i>
                                </span>
                            </div>
                            <input type="text" class="form-control search-input" id="patientSearchInput" name="patientSearchInput" placeholder="Enter Patient" onkeyup="searchPatients()">
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
                    <input class="form-control" type="text" id="crossmatchingSearchInput" onkeyup="filterCrossmatching()" placeholder="Search" style="padding-left: 35px; padding-right: 35px;">
                    <button class="position-absolute border-0 bg-transparent text-secondary" type="button" onclick="clearSearch()" style="top: 50%; right: 10px; transform: translateY(-50%);">
                        <i class="fa fa-times"></i>
                    </button>
                </div>
            </div>
        </div>
        <!-- Patient Information Table -->
        <div class="table-responsive">
            <table class="datatable table table-bordered table-hover" id="patientTable1">
                <thead style="background-color: #CCCCCC;">
                    <tr>
                        <th>Cross
                            matching ID</th>
                        <th>Patient ID</th>
                        <th>Patient Name</th>
                        <th>Gender</th>
                        <th>Age</th>
                        <th>Date and Time</th>
                        <th>Patient Blood Type</th>
                        <th>Blood Component</th>
                        <th>Serial Number</th>
                        <th>Extraction Date</th>
                        <th>Expiration Date</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    if (isset($_GET['crossmatching_id'])) {
                        $crossmatching_id = sanitize($connection, $_GET['crossmatching_id']);
                        $update_query = $connection->prepare("UPDATE tbl_crossmatching SET deleted = 1 WHERE crossmatching_id = ?");
                        $update_query->bind_param("s", $crossmatching_id);
                        $update_query->execute();
                        echo "<script>showSuccess('Record deleted successfully', true);</script>";
                    }
                    $fetch_query = mysqli_query($connection, "SELECT * FROM tbl_crossmatching WHERE deleted = 0 ORDER BY date_time ASC");
                    while ($row = mysqli_fetch_array($fetch_query)) {
                        $dob = date('Y-m-d', strtotime(str_replace('/', '-', $row['dob'])));
                        $year = (date('Y') - date('Y', strtotime($dob)));
                        $date_time = date('F d, Y g:i A', strtotime($row['date_time']));
                        $extraction_date = !empty($row['extraction_date']) ? date('F d, Y', strtotime($row['extraction_date'])) : '';
                        $expiration_date = !empty($row['expiration_date']) ? date('F d, Y', strtotime($row['expiration_date'])) : '';

                    ?>
                    <tr data-crossmatching-id="${record.crossmatching_id}">
                        <td><?php echo $row['crossmatching_id']; ?></td>
                        <td><?php echo $row['patient_id']; ?></td>
                        <td><?php echo $row['patient_name']; ?></td>
                        <td><?php echo $row['gender']; ?></td>
                        <td><?php echo $year; ?></td>
                        <td><?php echo $date_time; ?></td>
                        <td><?php echo $row['patient_blood_type']; ?></td>
                        <td><?php echo $row['blood_component']; ?></td>
                        <td><?php echo $row['serial_number']; ?></td>
                        <td><?php echo $extraction_date; ?></td>
                        <td><?php echo $expiration_date; ?></td>
                        <td class="text-right">
                            <div class="dropdown dropdown-action">
                                <a href="#" class="action-icon dropdown-toggle" data-toggle="dropdown">
                                    <i class="fa fa-ellipsis-v"></i>
                                </a>
                                <div class="dropdown-menu dropdown-menu-right">
                                    <?php if ($can_print): ?>
                                        <form action="generate-crossmatching.php" method="get">
                                            <input type="hidden" name="id" value="<?= $row['crossmatching_id']; ?>">
                                            <div class="form-group">
                                                <input type="text" class="form-control" name="filename" placeholder="Enter File Name">
                                            </div>
                                            <button class="btn btn-primary btn-sm custom-btn" type="submit">
                                                <i class="fa fa-file-pdf-o m-r-5"></i> Generate Result
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                    <?php if ($editable): ?>
                                        <a class="dropdown-item" href="edit-crossmatching.php?id=<?= $row['crossmatching_id']; ?>">
                                            <i class="fa fa-pencil m-r-5"></i> Insert and Edit
                                        </a>
                                        <a class="dropdown-item" href="#" onclick="return confirmDelete('<?php echo $row['crossmatching_id']; ?>')">
                                            <i class="fa fa-trash-o m-r-5"></i> Delete
                                        </a>
                                    <?php else: ?>
                                        <a class="dropdown-item disabled">
                                            <i class="fa fa-pencil m-r-5"></i> Edit
                                        </a>
                                        <a class="dropdown-item disabled">
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

        <!-- Crossmatching Results Table -->
        <div class="table-responsive">
            <table class="datatable table table-bordered table-hover" id="patientTable2">
                <thead style="background-color: #CCCCCC;">
                    <tr>
                        <th>Major Crossmatching</th>
                        <th>Donor's Blood Type</th>
                        <th>Packed Red Blood Cell</th>
                        <th>Time Packed</th>
                        <th>Dated</th>
                        <th>Open System</th>
                        <th>Closed System</th>
                        <th>To Be Consumed Before</th>
                        <th>Hours</th>
                        <th>Minor Crossmatching</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $fetch_query = mysqli_query($connection, "SELECT * FROM tbl_crossmatching WHERE deleted = 0 ORDER BY date_time ASC");
                    while ($row = mysqli_fetch_array($fetch_query)) {
                        $dated = !empty($row['dated']) ? date('F d, Y', strtotime($row['dated'])) : '';
                        $time_packed = !empty($row['time_packed']) ? date('g:i A', strtotime($row['time_packed'])) : '';
                        $to_be_consumed_before = !empty($row['to_be_consumed_before']) ? date('g:i A', strtotime($row['to_be_consumed_before'])) : '';  
                    ?>
                    <tr data-crossmatching-id="${record.crossmatching_id}">
                        <td><?php echo $row['major_crossmatching']; ?></td>
                        <td><?php echo $row['donors_blood_type']; ?></td>
                        <td><?php echo $row['packed_red_blood_cell']; ?></td>
                        <td><?php echo $time_packed; ?></td>
                        <td><?php echo $dated; ?></td>
                        <td><?php echo $row['open_system']; ?></td>
                        <td><?php echo $row['closed_system']; ?></td>
                        <td><?php echo $to_be_consumed_before; ?></td>
                        <td><?php echo $row['hours']; ?></td>
                        <td><?php echo $row['minor_crossmatching']; ?></td>
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

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script language="JavaScript" type="text/javascript">
    function confirmDelete(crossmatching_id) {
        return Swal.fire({
            title: 'Delete Crossmatching Records?',
            text: 'Are you sure you want to delete this Crossmatching record? This action cannot be undone!',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#12369e',
            confirmButtonText: 'Yes, delete it!'
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = 'crossmatching.php?crossmatching_id=' + crossmatching_id;  
            }
        });
    }

function clearSearch() {
    document.getElementById("crossmatchingSearchInput").value = '';
    filterCrossmatching();
}

let canPrint, userRole, editable;

$(document).ready(function() {
    canPrint = <?php echo $can_print ? 'true' : 'false' ?>;
    userRole = <?php echo $_SESSION['role']; ?>;
    editable = <?php echo $editable ? 'true' : 'false' ?>;
});

function filterCrossmatching() {
    var input = document.getElementById("crossmatchingSearchInput").value;
    console.log('Search input:', input);
        
        $.ajax({
            url: 'fetch_crossmatching.php',
            type: 'GET',
            data: { query: input },
            success: function(response) {
                console.log('Response received:', response);
                try {
                    var data = JSON.parse(response);
                    console.log('Parsed data:', data);
                    updatePatientTables(data);
                } catch (e) {
                    console.error('JSON parse error:', e);
                }
            },
            error: function(xhr, status, error) {
                console.error('Ajax error:', error);
            }
        });
    }

function updatePatientTables(data) {
    var tbody1 = $('#patientTable1 tbody');
    var tbody2 = $('#patientTable2 tbody');
    tbody1.empty();
    tbody2.empty();

    data.forEach(function (record) {
        tbody1.append(`
            <tr data-crossmatching-id="${record.crossmatching_id}">
                <td>${record.crossmatching_id}</td>
                <td>${record.patient_id}</td>
                <td>${record.patient_name}</td>
                <td>${record.gender}</td>
                <td>${record.age}</td>
                <td>${record.date_time}</td>
                <td>${record.patient_blood_type}</td>
                <td>${record.blood_component}</td>
                <td>${record.serial_number}</td>
                <td>${record.extraction_date}</td>
                <td>${record.expiration_date}</td>
                <td class="text-right">
                    <div class="dropdown dropdown-action">
                        <a href="#" class="action-icon dropdown-toggle" data-toggle="dropdown">
                            <i class="fa fa-ellipsis-v"></i>
                        </a>
                        <div class="dropdown-menu dropdown-menu-right">
                            ${getActionButtons(record.crossmatching_id)}
                        </div>
                    </div>
                </td>
            </tr>
        `);

        tbody2.append(`
            <tr data-crossmatching-id="${record.crossmatching_id}">
                <td>${record.major_crossmatching}</td>
                <td>${record.donors_blood_type}</td>
                <td>${record.packed_red_blood_cell}</td>
                <td>${record.time_packed}</td>
                <td>${record.dated}</td>
                <td>${record.open_system}</td>
                <td>${record.closed_system}</td>
                <td>${record.to_be_consumed_before}</td>
                <td>${record.hours}</td>
                <td>${record.minor_crossmatching}</td>
            </tr>
        `);
    });
}


function getActionButtons(crossmatchingId) {
    let buttons = '';

    if (canPrint) {
        buttons += `
            <form action="generate-crossmatching.php" method="get">
                <input type="hidden" name="id" value="${crossmatchingId}">
                <div class="form-group">
                    <input type="text" class="form-control" name="filename" placeholder="Enter File Name">
                </div>
                <button class="btn btn-primary btn-sm custom-btn" type="submit">
                    <i class="fa fa-file-pdf-o m-r-5"></i> Generate Result
                </button>
            </form>
        `;
    }

    if (userRole === 1) {
        buttons += `
            <a class="dropdown-item" href="edit-crossmatching.php?id=${crossmatchingId}">
                <i class="fa fa-pencil m-r-5"></i> Insert and Edit
            </a>
            <a class="dropdown-item" href="crossmatching.php?ids=${crossmatchingId}" onclick="return confirmDelete()">
                <i class="fa fa-trash-o m-r-5"></i> Delete
            </a>
        `;
    } else {
        buttons += `
            <a class="dropdown-item disabled" href="#"><i class="fa fa-pencil m-r-5"></i> Edit</a>
            <a class="dropdown-item disabled" href="#"><i class="fa fa-trash-o m-r-5"></i> Delete</a>
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
        url: "search-crossmatching.php",
        method: "GET",
        data: { query: input },
        success: function (data) {
            var results = document.getElementById("searchResults");
            results.innerHTML = data;
            results.style.display = "block";
        }
    });
}

$(document).on("click", ".search-result", function () {
    var patientId = $(this).data("id");
    var patientName = $(this).text();

    $("#patientId").val(patientId);
    $("#patientSearchInput").val(patientName);
    $("#addPatientBtn").prop("disabled", false);
    $("#searchResults").html("").hide();
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
#patientTable2_length, #patientTable_paginate .paginate_button {
    display: none;
}
.dropdown-action .action-icon {
    color: #777;
    font-size: 18px;
    display: inline-block;
    padding: 0 10px;
}

.dropdown-menu {
    border: 1px solid rgba(0, 0, 0, 0.1);
    border-radius: 3px;
    transform-origin: top right;
    box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
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

