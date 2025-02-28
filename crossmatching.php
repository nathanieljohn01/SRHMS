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
        $patient_blood_type = sanitize($connection, $_POST['patient_blood_type']);
        $blood_component = sanitize($connection, $_POST['blood_component']);
        $serial_number = sanitize($connection, $_POST['serial_number']);
        $extraction_date = sanitize($connection, $_POST['extraction_date']);
        $expiration_date = sanitize($connection, $_POST['expiration_date']);
        $major_crossmatching = sanitize($connection, $_POST['major_crossmatching']);
        $donors_blood_type = sanitize($connection, $_POST['donors_blood_type']);
        $packed_red_blood_cell = sanitize($connection, $_POST['packed_red_blood_cell']);
        $time_packed = sanitize($connection, $_POST['time_packed']);
        $dated = sanitize($connection, $_POST['dated']);
        $open_system = sanitize($connection, $_POST['open_system']);
        $closed_system = sanitize($connection, $_POST['closed_system']);
        $to_be_consumed_before = sanitize($connection, $_POST['to_be_consumed_before']);
        $hours = sanitize($connection, $_POST['hours']);
        $minor_crossmatching = sanitize($connection, $_POST['minor_crossmatching']);

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
            "sssssssssssssssssss", 
            $new_id, $patient_id, $name, $gender, $dob, 
            $patient_blood_type, $blood_component, $serial_number, $extraction_date, $expiration_date, 
            $major_crossmatching, $donors_blood_type, $packed_red_blood_cell, $time_packed, $dated, 
            $open_system, $closed_system, $to_be_consumed_before, $hours, $minor_crossmatching
        );

        // Execute and check for success
        if ($insert_query->execute()) {
            echo "<script>alert('Crossmatching record added successfully'); window.location.href='crossmatching.php';</script>";
        } else {
            echo "<script>alert('Error: " . $insert_query->error . "');</script>";
        }
    } else {
        echo "<script>alert('Patient not found. Please check the Patient ID.');</script>";
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
            <table class="datatable table table-bordered table-hover" id="crossmatchingTable">
                <thead style="background-color: #CCCCCC;">
                    <tr>
                        <th>Crossmatching ID</th>
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
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $fetch_query = mysqli_query($connection, "SELECT * FROM tbl_crossmatching WHERE deleted = 0 ORDER BY extraction_date ASC");
                    while ($row = mysqli_fetch_array($fetch_query)) {

                        $dob = $row['dob'];
                        $date = str_replace('/', '-', $dob);
                        $dob = date('Y-m-d', strtotime($date));
                        $year = (date('Y') - date('Y', strtotime($dob)));
                        $date_time = date('F d, Y g:i A', strtotime($row['date_time']));
                        $extraction_date = date('F d, Y', strtotime($row['extraction_date']));
                        $expiration_date = date('F d, Y', strtotime($row['expiration_date']));
                        $dated = date('F d, Y', strtotime($row['dated']));
                    ?>
                    <tr>
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
                        <td><?php echo $row['major_crossmatching']; ?></td>
                        <td><?php echo $row['donor_blood_type']; ?></td>
                        <td><?php echo $row['packed_red_blood_cell']; ?></td>
                        <td><?php echo $row['time_packed']; ?></td>
                        <td><?php echo $dated; ?></td>
                        <td><?php echo $row['open_system']; ?></td>
                        <td><?php echo $row['closed_system']; ?></td>
                        <td><?php echo $row['to_be_consumed_before']; ?></td>
                        <td><?php echo $row['hours']; ?></td>
                        <td><?php echo $row['minor_crossmatching']; ?></td>
                        <td class="text-right">
                            <div class="dropdown dropdown-action">
                                <a href="#" class="action-icon dropdown-toggle" data-toggle="dropdown" aria-expanded="false"><i class="fa fa-ellipsis-v"></i></a>
                                <div class="dropdown-menu dropdown-menu-right">
                                    <?php if ($can_print): ?>
                                    <form action="generate-crossmatching.php" method="get">
                                        <input type="hidden" name="id" value="<?php echo $row['crossmatching_id']; ?>">
                                        <div class="form-group">
                                            <input type="text" class="form-control" id="filename" name="filename" placeholder="Enter File Name">
                                        </div>
                                        <button class="btn btn-primary btn-sm custom-btn" type="submit"><i class="fa fa-file-pdf-o m-r-5"></i> Generate Result</button>
                                    </form>
                                    <?php endif; ?>
                                    <?php if ($editable): ?>
                                        <a class="dropdown-item" href="edit-crossmatching.php?id=<?php echo $row['crossmatching_id']; ?>"><i class="fa fa-pencil m-r-5"></i> Insert and Edit</a>
                                        <a class="dropdown-item" href="crossmatching.php?id=<?php echo $row['crossmatching_id']; ?>" onclick="return confirmDelete()"><i class="fa fa-trash-o m-r-5"></i> Delete</a>
                                    <?php else: ?>
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
    document.getElementById("crossmatchingSearchInput").value = '';
    filterCrossmatching();
}

let canPrint, userRole;

$(document).ready(function() {
    canPrint = <?php echo $can_print ? 'true' : 'false' ?>;
    userRole = <?php echo $_SESSION['role']; ?>;
});

function filterCrossmatching() {
    var input = document.getElementById("crossmatchingSearchInput").value;
    
    $.ajax({
        url: 'fetch_crossmatching.php',
        type: 'GET',
        data: { query: input },
        success: function(response) {
            var data = JSON.parse(response);
            updateCrossmatchingTable(data);
        },
        error: function(xhr, status, error) {
            alert('Error fetching data. Please try again.');
        }
    });
}

function updateCrossmatchingTable(data) {
    var tbody = $('#crossmatchingTable tbody');
    tbody.empty();
    
    data.forEach(function(record) {
        tbody.append(`
            <tr>
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
                <td class="text-right">
                    <div class="dropdown dropdown-action">
                        <a href="#" class="action-icon dropdown-toggle" data-toggle="dropdown" aria-expanded="false">
                            <i class="fa fa-ellipsis-v"></i>
                        </a>
                        <div class="dropdown-menu dropdown-menu-right">
                            ${getActionButtons(record.crossmatch_id)}
                        </div>
                    </div>
                </td>
            </tr>
        `);
    });
}

function getActionButtons(crossmatchId) {
    let buttons = '';
    
    if (canPrint) {
        buttons += `
            <form action="generate-crossmatching.php" method="get">
                <input type="hidden" name="id" value="${crossmatchId}">
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
            <a class="dropdown-item" href="edit-crossmatching.php?id=${crossmatchId}">
                <i class="fa fa-pencil m-r-5"></i> Insert and Edit
            </a>
            <a class="dropdown-item" href="crossmatching.php?ids=${crossmatchId}" onclick="return confirmDelete()">
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

