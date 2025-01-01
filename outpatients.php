<?php
session_start();
ob_start(); // Start output buffering
if (empty($_SESSION['name'])) {
    header('location:index.php');
    exit; // Stop further execution
}
include('header.php');
include('includes/connection.php');

// Function to sanitize user inputs
function sanitize($connection, $input) {
    return mysqli_real_escape_string($connection, trim($input));
}

$role = isset($_SESSION['role']) ? $_SESSION['role'] : null;

// Update diagnosis in the database
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['diagnosis']) && isset($_POST['patientId'])) {
    // Sanitize inputs
    $diagnosis = sanitize($connection, $_POST['diagnosis']);
    $patientId = sanitize($connection, $_POST['patientId']);

    // Prepare and bind the query
    $update_query = $connection->prepare("UPDATE tbl_outpatient SET diagnosis=? WHERE patient_id=?");
    $update_query->bind_param("ss", $diagnosis, $patientId);

    // Execute the query
    if ($update_query->execute()) {
        echo '<script>alert("Diagnosis added successfully.");</script>';
    } else {
        echo '<script>alert("Error adding diagnosis.");</script>';
    }

    $update_query->close();
}

// Fetch patient details from tbl_patient based on patient_id
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['patientId'])) {
    // Sanitize the patient ID input
    $patientId = sanitize($connection, $_POST['patientId']);

    // Prepare and bind the query
    $patient_query = $connection->prepare("SELECT * FROM tbl_patient WHERE id = ?");
    $patient_query->bind_param("s", $patientId);

    // Execute the query
    $patient_query->execute();
    $patient_result = $patient_query->get_result();
    $patient = $patient_result->fetch_array(MYSQLI_ASSOC);

    if ($patient) {
        // Retrieve patient details
        $patient_id = $patient['patient_id'];
        $name = $patient['first_name'] . ' ' . $patient['last_name'];
        $gender = $patient['gender'];
        $dob = $patient['dob'];
        $doctor_incharge = ""; // Optional: Set manually later or through a form field

        // Fetch the last outpatient_id and increment it
        $last_outpatient_query = $connection->prepare("SELECT outpatient_id FROM tbl_outpatient ORDER BY id DESC LIMIT 1");
        $last_outpatient_query->execute();
        $last_outpatient_result = $last_outpatient_query->get_result();
        $last_outpatient = $last_outpatient_result->fetch_array(MYSQLI_ASSOC);

        // Generate new outpatient_id
        if ($last_outpatient) {
            $last_id_number = (int) substr($last_outpatient['outpatient_id'], 4); // Remove "OPT-" and convert to int
            $new_outpatient_id = 'OPT-' . ($last_id_number + 1);
        } else {
            $new_outpatient_id = 'OPT-1'; // Starting value if no outpatient_id exists
        }

        // Insert the patient into tbl_outpatient
        $insert_query = $connection->prepare("
            INSERT INTO tbl_outpatient (outpatient_id, patient_id, patient_name, gender, dob, doctor_incharge, date_time) 
            VALUES (?, ?, ?, ?, ?, ?, NOW())
        ");
        $insert_query->bind_param("ssssss", $new_outpatient_id, $patient_id, $name, $gender, $dob, $doctor_incharge);
        $insert_query->execute();

        // Redirect or show a success message
        header('Location: outpatients.php');
        exit;
    } else {
        echo "<script>alert('Patient not found. Please check the Patient ID.');</script>";
    }

    $patient_query->close();
}

// Process the form submission to assign doctor in charge
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['outpatientIdDoctor']) && isset($_POST['doctorId'])) {
    $outpatientId = sanitize($connection, $_POST['outpatientIdDoctor']);
    $doctorId = sanitize($connection, $_POST['doctorId']);

    // Fetch the doctor's name
    $doctor_query = $connection->prepare("SELECT first_name, last_name FROM tbl_employee WHERE id = ?");
    $doctor_query->bind_param("s", $doctorId);

    // Execute the query
    $doctor_query->execute();
    $doctor_result = $doctor_query->get_result();
    $doctor = $doctor_result->fetch_array(MYSQLI_ASSOC);
    $doctor_name = $doctor['first_name'] . ' ' . $doctor['last_name'];

    // Update the outpatient record with the doctor's name
    $update_query = $connection->prepare("UPDATE tbl_outpatient SET doctor_incharge = ? WHERE outpatient_id = ?");
    $update_query->bind_param("ss", $doctor_name, $outpatientId);

    if ($update_query->execute()) {
        echo '<script>alert("Doctor assigned successfully.");</script>';
    } else {
        echo '<script>alert("Error assigning doctor.");</script>';
    }

    $doctor_query->close();
    $update_query->close();
}

ob_end_flush(); // Flush output buffer
?>

<div class="page-wrapper">
    <div class="content">
        <div class="row">
            <div class="col-sm-4 col-3">
                <h4 class="page-title">Outpatient</h4>
            </div>
                <?php if ($role == 1 || $role == 3): ?>
                <div class="col-sm-10 col-9 m-b-20">
                    <form method="POST" action="outpatients.php" id="addPatientForm" class="form-inline">
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
        <label for="patientSearchInput" class="font-weight-bold">Search Patient:</label>
        <input class="form-control" type="text" id="outpatientSearchInput" onkeyup="filterOutpatients()" placeholder="Search Patient ID or Patient Name">
            <table class="datatable table table-hover" id="outpatientTable">
                <thead style="background-color: #CCCCCC;">
                    <tr>
                        <th>Patient ID</th>
                        <th>Outpatient ID</th>
                        <th>Patient Name</th>
                        <th>Age</th>
                        <th>Birthdate</th>
                        <th>Gender</th>
                        <th>Doctor In-Charge</th>
                        <th>Lab Result</th>
                        <th>Diagnosis</th>
                        <th>Date and Time</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    if(isset($_GET['ids'])){
                        $id = sanitize($connection, $_GET['ids']);
                        $update_query = mysqli_query($connection, "UPDATE tbl_outpatient SET deleted = 1 WHERE id='$id'");
                    }
                    $fetch_query = mysqli_query($connection, "select * from tbl_outpatient where deleted = 0");
                    while($row = mysqli_fetch_array($fetch_query))
                    {
                        $dob = $row['dob'];
                        $date = str_replace('/', '-', $dob); 
                        $dob = date('Y-m-d', strtotime($date));
                        $year = (date('Y') - date('Y',strtotime($dob)));

                        $date_time = date('F d, Y g:i A', strtotime($row['date_time']));
                    ?>
                    <tr>
                        <td><?php echo $row['patient_id']; ?></td>
                        <td><?php echo $row['outpatient_id']; ?></td>
                        <td><?php echo $row['patient_name']; ?></td>
                        <td><?php echo $year; ?></td>
                        <td><?php echo $row['dob']; ?></td>
                        <td><?php echo $row['gender']; ?></td>
                        <td>
                            <?php if (empty($row['doctor_incharge'])) { ?>
                                <button class="btn btn-primary btn-sm select-doctor-btn" data-toggle="modal" data-target="#doctorModal" data-id="<?php echo $row['outpatient_id']; ?>">Select Doctor</button>
                            <?php } else { ?>
                                <?php echo $row['doctor_incharge']; ?>
                            <?php } ?>
                        </td>
                        <td>
                        <form action="generate-result.php" method="get">
                            <input type="hidden" name="patient_id" value="<?php echo $row['patient_id']; ?>">
                            <button class="btn btn-primary btn-sm custom-btn" type="submit">
                                <i class="fa fa-file-pdf-o m-r-5"></i> View Result
                            </button>
                        </form>
                        </td>
                        <td><?php echo $row['diagnosis']; ?></td>
                        <td><?php echo $date_time; ?></td>
                        <td class="text-right">
                            <div class="dropdown dropdown-action">
                                <a href="#" class="action-icon dropdown-toggle" data-toggle="dropdown" aria-expanded="false"><i class="fa fa-ellipsis-v"></i></a>
                                <div class="dropdown-menu dropdown-menu-right">
                                    <?php if ($_SESSION['role'] == 2 && $_SESSION['name'] == $row['doctor_incharge']) { ?>
                                        <button class="dropdown-item diagnosis-btn" data-toggle="modal" data-target="#diagnosisModal" data-id="<?php echo $row['outpatient_id'];?>" <?php echo !empty($row['diagnosis']) ? 'disabled' : ''; ?>><i class="fa fa-stethoscope m-r-5"></i> Diagnosis</button>
                                    <?php } ?>
                                    <?php 
                                    if ($_SESSION['role'] == 1 ) {
                                        echo '<a class="dropdown-item" href="edit-outpatient.php?id='.$row['id'].'"><i class="fa fa-pencil m-r-5"></i> Edit</a>';
                                        echo '<a class="dropdown-item" href="outpatients.php?ids='.$row['id'].'" onclick="return confirmDelete()"><i class="fa fa-trash-o m-r-5"></i> Delete</a>';
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
                <form id="diagnosisForm" method="post" action="outpatients.php">
                    <div class="form-group">
                        <label for="diagnosis">Enter Diagnosis:</label>
                        <input type="text" class="form-control" id="diagnosis" name="diagnosis">
                    </div>
                    <input type="hidden" id="outpatientId" name="outpatientId">
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
                <form id="doctorForm" method="post" action="outpatients.php">
                    <input type="hidden" id="outpatientIdDoctor" name="outpatientIdDoctor">
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

<!-- Modal for displaying lab test results -->
<div class="modal fade" id="resultModal" tabindex="-1" aria-labelledby="resultModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="resultModalLabel">Lab Test Result</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <!-- The fetched content will be inserted here -->
            </div>
        </div>
    </div>
</div>

<!-- Success and Error Alerts -->
<div id="successAlert"></div>
<div id="errorAlert"></div>

<?php
include('footer.php');
?>
<script src="https://ajax.googleapis.com/ajax/libs/jquery/3.5.1/jquery.min.js"></script>
<script language="JavaScript" type="text/javascript">
    function confirmDelete(){
        return confirm('Are you sure want to delete this Patient?');
    }

    // Set outpatient id when diagnosis button clicked
    $(document).on('click', '.diagnosis-btn', function(){
        var outpatientId = $(this).data('id');
        $('#outpatientId').val(outpatientId);
    });
</script>
<script>
    function filterOutpatients() {
        var input, filter, table, tr, td, i, txtValue;
        input = document.getElementById("outpatientSearchInput");
        filter = input.value.toUpperCase();
        table = document.getElementById("outpatientTable");
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
            url: "search-outpatient.php", // Backend script to fetch patients
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

<script>
// This function will open the modal and set the outpatient_id dynamically
$(document).on('click', '.select-doctor-btn', function() {
    var outpatientId = $(this).data('id');
    $('#outpatientIdDoctor').val(outpatientId);
    $('#doctorModal').modal('show');
});

// When the form for assigning doctor is submitted
$('#doctorForm').submit(function(e) {
    e.preventDefault(); // Prevent default form submission
    var outpatientId = $('#outpatientIdDoctor').val();
    var doctorId = $('#doctor').val();

    // Send the selected doctor to be updated in the database
    $.ajax({
        url: 'outpatients.php', // Ensure the PHP file is the correct one to process the form
        type: 'POST',
        data: {
            outpatientIdDoctor: outpatientId,
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

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Get all "View Result" buttons
    const viewResultButtons = document.querySelectorAll('.view-result');

    // Loop through each button and add the click event listener
    viewResultButtons.forEach(button => {
        button.addEventListener('click', function() {
            var patient_id = this.getAttribute('data-id'); // Get patient_id from the button

            // Send the patient_id to fetch-result.php using AJAX
            $.ajax({
                url: 'fetch-result.php',
                method: 'GET',
                data: { patient_id: patient_id },
                success: function(response) {
                    // Display the fetched result in the modal
                    document.querySelector('#resultModal .modal-body').innerHTML = response;
                    
                    // Show the modal using Bootstrap 5's modal method
                    var myModal = new bootstrap.Modal(document.getElementById('resultModal'));
                    myModal.show();
                },
                error: function() {
                    alert('An error occurred while fetching the lab test result.');
                }
            });
        });
    });
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
