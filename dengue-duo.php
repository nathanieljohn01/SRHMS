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

        // Fetch the last Dengue Duo ID and generate a new one
        $last_dd_query = $connection->prepare("SELECT dd_id FROM tbl_dengueduo ORDER BY id DESC LIMIT 1");
        $last_dd_query->execute();
        $last_dd_result = $last_dd_query->get_result();
        $last_dd = $last_dd_result->fetch_array(MYSQLI_ASSOC);

        if ($last_dd) {
            $last_id_number = (int) substr($last_dd['dd_id'], 3);
            $new_dd_id = 'DD-' . ($last_id_number + 1);
        } else {
            $new_dd_id = 'DD-1';
        }

        // Assign the generated ID to $dd_id
        $dd_id = $new_dd_id;

        // Sanitize user inputs and set NULL if empty
        $ns1ag = !empty($_POST['ns1ag']) ? sanitize($connection, $_POST['ns1ag']) : NULL;
        $igg = !empty($_POST['igg']) ? sanitize($connection, $_POST['igg']) : NULL;
        $igm = !empty($_POST['igm']) ? sanitize($connection, $_POST['igm']) : NULL;

        // Prepare the query to insert with NULL values for empty fields
        $insert_query = $connection->prepare("INSERT INTO tbl_dengueduo (dd_id, patient_id, patient_name, gender, dob, ns1ag, igg, igm, date_time) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())");
        
        // Bind parameters
        $insert_query->bind_param("ssssssss", $dd_id, $patient_id, $name, $gender, $dob, $ns1ag, $igg, $igm);

        // Execute the query
        if ($insert_query->execute()) {
            echo "Record added successfully";
        } else {
            echo "Error: " . $insert_query->error;
        }

        // Redirect or show a success message
        header('Location: dengueduo.php');
        exit;
    } else {
        echo "<script>alert('Patient not found. Please check the Patient ID.');</script>";
    }
}

ob_end_flush(); // Flush output buffer
?>

<div class="page-wrapper">
    <div class="content">
        <div class="row">
            <div class="col-sm-4 col-3">
                <h4 class="page-title">Dengue Duo</h4>
            </div>
            <?php if ($role == 1 || $role == 5): ?>
                <div class="col-sm-10 col-9 m-b-20">
                    <form method="POST" action="dengue-duo.php" id="addPatientForm" class="form-inline">
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
            <table class="datatable table table-bordered table-hover" id="dengueduoTable">
                <thead style="background-color: #CCCCCC;">
                    <tr>
                        <th>Dengueduo ID</th>
                        <th>Patient ID</th>
                        <th>Patient Name</th>
                        <th>Gender</th>
                        <th>Age</th>
                        <th>Date and Time</th>
                        <th>NS1Ag</th>
                        <th>IgG</th>
                        <th>IgM</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $fetch_query = mysqli_query($connection, "SELECT * FROM tbl_dengueduo WHERE deleted = 0 ORDER BY date_time ASC");
                    while ($row = mysqli_fetch_array($fetch_query)) {
                        $dob = $row['dob'];
                        $date = str_replace('/', '-', $dob);
                        $dob = date('Y-m-d', strtotime($date));
                        $year = (date('Y') - date('Y', strtotime($dob)));
                        $date_time = date('F d, Y g:i A', strtotime($row['date_time']));
                    ?>
                    <tr>
                        <td><?php echo $row['dd_id']; ?></td>
                        <td><?php echo $row['patient_id']; ?></td>
                        <td><?php echo $row['patient_name']; ?></td>
                        <td><?php echo $row['gender']; ?></td>
                        <td><?php echo $year; ?></td>
                        <td><?php echo $date_time; ?></td>
                        <td><?php echo $row['ns1ag']; ?></td>
                        <td><?php echo $row['igg']; ?></td>
                        <td><?php echo $row['igm']; ?></td>
                        <td class="text-right">
                            <div class="dropdown dropdown-action">
                                <a href="#" class="action-icon dropdown-toggle" data-toggle="dropdown" aria-expanded="false"><i class="fa fa-ellipsis-v"></i></a>
                                <div class="dropdown-menu dropdown-menu-right">
                                    <?php if ($can_print): ?>
                                    <form action="generate-dengueduo.php" method="get">
                                        <input type="hidden" name="id" value="<?php echo $row['dd_id']; ?>">
                                        <div class="form-group">
                                            <input type="text" class="form-control" id="filename" name="filename" placeholder="Enter File Name">
                                        </div>
                                        <button class="btn btn-primary btn-sm custom-btn" type="submit"><i class="fa fa-file-pdf-o m-r-5"></i> Generate Result</button>
                                    </form>
                                    <?php endif; ?>
                                    <?php if ($editable): ?>
                                        <a class="dropdown-item" href="edit-dengue-duo.php?id=<?php echo $row['dd_id']; ?>"><i class="fa fa-pencil m-r-5"></i> Insert and Edit</a>
                                        <a class="dropdown-item" href="dengue-duo.php?id=<?php echo $row['dd_id']; ?>" onclick="return confirmDelete()"><i class="fa fa-trash-o m-r-5"></i> Delete</a>
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
    document.getElementById("dengueDuoSearchInput").value = '';
    filterDengueDuo();
}

let canPrint, userRole;

$(document).ready(function() {
    canPrint = <?php echo $can_print ? 'true' : 'false' ?>;
    userRole = <?php echo $_SESSION['role']; ?>;
});

function filterDengueDuo() {
    var input = document.getElementById("dengueDuoSearchInput").value;
    
    $.ajax({
        url: 'fetch_dengueduo.php',
        type: 'GET',
        data: { query: input },
        success: function(response) {
            var data = JSON.parse(response);
            updateDengueDuoTable(data);
        },
        error: function(xhr, status, error) {
            alert('Error fetching data. Please try again.');
        }
    });
}

function updateDengueDuoTable(data) {
    var tbody = $('#dengueDuoTable tbody');
    tbody.empty();
    
    data.forEach(function(record) {
        tbody.append(`
            <tr>
                <td>${record.dengue_id}</td>
                <td>${record.patient_id}</td>
                <td>${record.patient_name}</td>
                <td>${record.gender}</td>
                <td>${record.age}</td>
                <td>${record.date_time}</td>
                <td>${record.NS1Ag}</td>
                <td>${record.IgG}</td>
                <td>${record.IgM}</td>
                <td class="text-right">
                    <div class="dropdown dropdown-action">
                        <a href="#" class="action-icon dropdown-toggle" data-toggle="dropdown" aria-expanded="false">
                            <i class="fa fa-ellipsis-v"></i>
                        </a>
                        <div class="dropdown-menu dropdown-menu-right">
                            ${getActionButtons(record.dengue_id)}
                        </div>
                    </div>
                </td>
            </tr>
        `);
    });
}

function getActionButtons(dengueId) {
    let buttons = '';
    
    if (canPrint) {
        buttons += `
            <form action="generate-dengueduo.php" method="get">
                <input type="hidden" name="id" value="${dengueId}">
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
            <a class="dropdown-item" href="edit-dengueduo.php?id=${dengueId}">
                <i class="fa fa-pencil m-r-5"></i> Insert and Edit
            </a>
            <a class="dropdown-item" href="dengueduo.php?ids=${dengueId}" onclick="return confirmDelete()">
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
        url: "search-dengueduo.php",
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

