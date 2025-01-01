<?php
session_start();
ob_start(); // Start output buffering
if (empty($_SESSION['name'])) {
    header('location:index.php');
    exit;
}
include('header.php');
include('includes/connection.php');

// Function to sanitize user inputs
function sanitize($connection, $input) {
    return mysqli_real_escape_string($connection, htmlspecialchars(strip_tags(trim($input)), ENT_QUOTES, 'UTF-8'));
}

$role = isset($_SESSION['role']) ? $_SESSION['role'] : null;

// Process the form submission
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['patientId'])) {
    $msg = ''; // Initialize message variable
    $patientId = sanitize($connection, $_POST['patientId']); // Sanitize the patient ID input

    // Prepare and bind the query to fetch patient details from tbl_inpatient_record
    $patient_query = $connection->prepare("SELECT * FROM tbl_inpatient_record WHERE id = ?");
    if (!$patient_query) {
        $msg = "Error in prepared statement: " . $connection->error;
    } else {
        $patient_query->bind_param("s", $patientId);
        $patient_query->execute();
        $patient_result = $patient_query->get_result();
        $patient = $patient_result->fetch_assoc();

        if ($patient) {
            // Retrieve patient details
            $inpatient_id = sanitize($connection, $patient['inpatient_id']);
            $patient_id = sanitize($connection, $patient['patient_id']);
            $name = sanitize($connection, $patient['patient_name']);
            $gender = sanitize($connection, $patient['gender']);
            $dob = sanitize($connection, $patient['dob']);

            // Check if the patient is already in tbl_transfer
            $check_transfer_query = $connection->prepare("SELECT * FROM tbl_transfer WHERE inpatient_id = ?");
            if (!$check_transfer_query) {
                $msg = "Error in prepared statement: " . $connection->error;
            } else {
                $check_transfer_query->bind_param("s", $inpatient_id);
                $check_transfer_query->execute();
                $transfer_result = $check_transfer_query->get_result();

                if ($transfer_result->num_rows > 0) {
                    // Patient already exists in tbl_transfer
                    $msg = "Patient already exists in the transfer records.";
                } else {
                    // Insert the patient into tbl_transfer
                    $insert_query = $connection->prepare("
                        INSERT INTO tbl_transfer (inpatient_id, patient_id, patient_name, gender, dob) 
                        VALUES (?, ?, ?, ?, ?)
                    ");
                    if (!$insert_query) {
                        $msg = "Error in prepared statement: " . $connection->error;
                    } else {
                        $insert_query->bind_param('sssss', $inpatient_id, $patient_id, $name, $gender, $dob);
                        if ($insert_query->execute()) {
                            $msg = "Patient transferred successfully.";
                        } else {
                            $msg = "Error transferring patient: " . $connection->error;
                        }
                    }
                }
            }
        } else {
            $msg = "Patient not found. Please check the Patient ID.";
        }
    }

    // Display the message
    if (isset($msg)) {
        echo "<script>";
        echo "swal({ text: '" . addslashes($msg) . "', icon: '" . (strpos($msg, "successfully") !== false ? 'success' : 'error') . "' });";
        if (strpos($msg, "successfully") !== false) {
            echo "setTimeout(function() { window.location.href = 'transfer-record.php'; }, 2000);"; // Redirect if successful
        }
        echo "</script>";
    }
}

ob_end_flush(); // Flush output buffer
?>

<div class="page-wrapper">
    <div class="content">
        <div class="row">
            <div class="col-sm-4 col-3">
                <h4 class="page-title">Bed Transfer</h4>
            </div>
            <?php if ($role == 1 || $role == 3): ?>
                <div class="col-sm-10 col-9 m-b-20">
                    <form method="POST" action="bed-transfer.php" id="addPatientForm" class="form-inline">
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
                                <button type="submit" class="btn btn-primary" id="addPatientBtn" disabled>Add</button>
                            </div>
                        </div>
                        <input type="hidden" name="patientId" id="patientId">
                    </form>
                    <ul id="searchResults" class="list-group mt-2" style="max-height: 200px; overflow-y: auto; border: 1px solid #ccc; border-radius: 5px; display: none;"></ul>
                </div>
            <?php endif; ?>
        </div>
        <div class="table-responsive">
        <input class="form-control" type="text" id="transferSearchInput" onkeyup="filterTransfer()" placeholder="Search Patient ID or Patient Name">
            <table class="datatable table table-hover" id="transferTable">
                <thead style="background-color: #CCCCCC;">
                    <tr>
                        <th>Patient ID</th>
                        <th>Inpatient ID</th>
                        <th>Patient Name</th>
                        <th>Age</th>
                        <th>Gender</th>
                        <th>Room Type</th>
                        <th>Room Number</th>
                        <th>Bed Number</th>
                        <th>Transfer Date & Time</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    if(isset($_GET['ids'])){
                        $id = $_GET['ids'];
                        $update_query = mysqli_query($connection, "UPDATE tbl_transfer SET deleted = 1 WHERE id='$id'");
                    }
                    $fetch_query = mysqli_query($connection, "select * from tbl_transfer");
                    while($row = mysqli_fetch_array($fetch_query))
                    {
                        $dob = $row['dob'];
                        $date = str_replace('/', '-', $dob); 
                        $dob = date('Y-m-d', strtotime($date));
                        $year = (date('Y') - date('Y',strtotime($dob)));

                        $transfer_date_time = ($row['transfer_date']) ? date('F d Y g:i A', strtotime($row['transfer_date'])) : 'N/A';
                    ?>
                        <tr>
                            <td><?php echo $row['patient_id']; ?></td>
                            <td><?php echo $row['inpatient_id']; ?></td>
                            <td><?php echo $row['patient_name']; ?></td>
                            <td><?php echo $year; ?></td>
                            <td><?php echo $row['gender']; ?></td>
                            <td><?php echo $row['room_type']; ?></td>
                            <td><?php echo $row['room_number']; ?></td>
                            <td><?php echo $row['bed_number']; ?></td>
                            <td><?php echo $transfer_date_time; ?></td>
                            <td class="text-right">
                                <div class="dropdown dropdown-action">
                                    <a href="#" class="action-icon dropdown-toggle" data-toggle="dropdown" aria-expanded="false"><i class="fa fa-ellipsis-v"></i></a>
                                    <div class="dropdown-menu dropdown-menu-right">
                                    <?php if ($_SESSION['role'] == 3) { 
                                        // Check if room_type, room_number, or bed_number is not empty to disable Insert Room link
                                            if (empty($row['room_type']) && empty($row['room_number']) && empty($row['bed_number'])) {
                                                echo '<a class="dropdown-item" href="change-room.php?id=' . $row['id'] . '"><i class="fa fa-pencil m-r-5"></i> Insert New Room</a>';
                                           }
                                        }
                                    ?>
                                    <?php 
                                    if ($_SESSION['role'] == 1) {
                                        echo '<a class="dropdown-item" href="bed-transfer.php?ids='.$row['id'].'" onclick="return confirmDelete()"><i class="fa fa-trash-o m-r-5"></i> Delete</a>';
                                    }
                                    ?>
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
function confirmDelete(){
    return confirm('Are you sure want to delete this Patient?');
}

function confirmDischarge() {
    return confirm('Are you sure you want to discharge this Patient?');
}
</script>

<script>
     function filterTransfer() {
        var input, filter, table, tr, td, i, txtValue;
        input = document.getElementById("transferSearchInput");
        filter = input.value.toUpperCase();
        table = document.getElementById("transferTable");
        tr = table.getElementsByTagName("tr");

        for (i = 0; i < tr.length; i++) {
            var matchFound = false;
            for (var j = 0; j < tr[i].cells.length; j++) {
                td = tr[i].cells[j];
                if (td) {
                    txtValue = td.textContent || td.innerText;
                    if (txtValue.toUpperCase().indexOf(filter) > -1) {
                        matchFound = true;
                        break;
                    }
                }
            }
            if (matchFound || i === 0) {
                tr[i].style.display = "";
            } else {
                tr[i].style.display = "none";
            }
        }
    }

    function searchPatients() {
        var input = document.getElementById("patientSearchInput").value;
        if (input.length < 2) {
            document.getElementById("searchResults").style.display = "none";
            document.getElementById("searchResults").innerHTML = "";
            return;
        }
        $.ajax({
            url: "search-bedt.php", // Backend script to fetch patients
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
<style>
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
</style>