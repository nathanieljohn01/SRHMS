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
$doctor_name = isset($_SESSION['name']) ? $_SESSION['name'] : null;

// Update diagnosis in the database
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['diagnosis']) && isset($_POST['patientId'])) {
    $diagnosis = sanitize($connection, $_POST['diagnosis']);
    $patientId = sanitize($connection, $_POST['patientId']);

    $update_query = $connection->prepare("UPDATE tbl_outpatient SET diagnosis=? WHERE patient_id=?");
    $update_query->bind_param("ss", $diagnosis, $patientId);

    if ($update_query->execute()) {
        echo "<script>showSuccess('Diagnosis added successfully.', true);</script>";
    } else {
        echo "<script>showError('Error adding diagnosis.');</script>";
    }
    $update_query->close();
}

// Fetch patient details from tbl_patient based on patient_id
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['patientId'])) {
    $patientId = sanitize($connection, $_POST['patientId']);
    $patient_query = $connection->prepare("SELECT * FROM tbl_patient WHERE id = ?");
    $patient_query->bind_param("s", $patientId);
    $patient_query->execute();
    $patient_result = $patient_query->get_result();
    $patient = $patient_result->fetch_array(MYSQLI_ASSOC);

    if ($patient) {
        $patient_id = $patient['patient_id'];
        $name = $patient['first_name'] . ' ' . $patient['last_name'];
        $gender = $patient['gender'];
        $dob = $patient['dob'];
        $doctor_incharge = "";

        $last_outpatient_query = $connection->prepare("SELECT outpatient_id FROM tbl_outpatient ORDER BY id DESC LIMIT 1");
        $last_outpatient_query->execute();
        $last_outpatient_result = $last_outpatient_query->get_result();
        $last_outpatient = $last_outpatient_result->fetch_array(MYSQLI_ASSOC);

        if ($last_outpatient) {
            $last_id_number = (int) substr($last_outpatient['outpatient_id'], 4);
            $new_outpatient_id = 'OPT-' . ($last_id_number + 1);
        } else {
            $new_outpatient_id = 'OPT-1';
        }

        $insert_query = $connection->prepare("INSERT INTO tbl_outpatient (outpatient_id, patient_id, patient_name, gender, dob, doctor_incharge, date_time) VALUES (?, ?, ?, ?, ?, ?, NOW())");
        $insert_query->bind_param("ssssss", $new_outpatient_id, $patient_id, $name, $gender, $dob, $doctor_incharge);
        $insert_query->execute();

        echo "<script>
            Swal.fire({
                title: 'Success!',
                text: 'Outpatient record inserted successfully.',
                icon: 'success',
                confirmButtonText: 'OK',
                confirmButtonColor: '#12369e'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = 'outpatients.php';
                }
            });
        </script>";
        exit;
    } else {
        echo "<script>
            Swal.fire({
                title: 'Error!',
                text: 'Patient not found. Please check the Patient ID.',
                icon: 'error',
                confirmButtonText: 'OK'
            });
        </script>";
    }
    $patient_query->close();
}

// Process the form submission to assign doctor in charge
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['outpatientIdDoctor']) && isset($_POST['doctorId'])) {
    $outpatientId = sanitize($connection, $_POST['outpatientIdDoctor']);
    $doctorId = sanitize($connection, $_POST['doctorId']);

    $doctor_query = $connection->prepare("SELECT first_name, last_name FROM tbl_employee WHERE id = ?");
    $doctor_query->bind_param("s", $doctorId);
    $doctor_query->execute();
    $doctor_result = $doctor_query->get_result();
    $doctor = $doctor_result->fetch_array(MYSQLI_ASSOC);
    $doctor_name = $doctor['first_name'] . ' ' . $doctor['last_name'];

    $update_query = $connection->prepare("UPDATE tbl_outpatient SET doctor_incharge = ? WHERE outpatient_id = ?");
    $update_query->bind_param("ss", $doctor_name, $outpatientId);

    if ($update_query->execute()) {
        echo "<script>showSuccess('Doctor assigned successfully.', true);</script>";
    } else {
        echo "<script>showError('Error assigning doctor.');</script>";
    }

    $doctor_query->close();
    $update_query->close();
}

if (isset($_GET['ids'])) {
    $id = intval($_GET['ids']);
    $update_query = $connection->prepare("UPDATE tbl_outpatient SET deleted = 1 WHERE id = ?");
    $update_query->bind_param("i", $id);
    if ($update_query->execute()) {
        echo "<script>showSuccess('Outpatient record deleted successfully!', true);</script>";
        header('Location: outpatients.php');
        exit;
    } else {
        echo "<script>showError('Error deleting outpatient record.');</script>";
    }
}

ob_end_flush();
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
                            <input class="form-control" type="text" id="outpatientSearchInput" onkeyup="filterOutpatients()" style="padding-left: 35px; padding-right: 35px;">
                            <!-- Clear Button -->
                            <button class="position-absolute border-0 bg-transparent text-secondary" type="button" onclick="clearSearch()" style="top: 50%; right: 10px; transform: translateY(-50%);">
                                <i class="fa fa-times"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            <div class="table-responsive">
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
                        if ($role == 2) {
                            $fetch_query = $connection->prepare("SELECT * FROM tbl_outpatient WHERE deleted = 0 AND doctor_incharge = ?");
                            $fetch_query->bind_param("s", $doctor_name);
                        } else {
                            $fetch_query = $connection->prepare("SELECT * FROM tbl_outpatient WHERE deleted = 0");
                        }

                        $fetch_query->execute();
                        $result = $fetch_query->get_result();

                        while ($row = $result->fetch_assoc()) {
                            $dob = $row['dob'];
                            $date = str_replace('/', '-', $dob); 
                            $dob = date('Y-m-d', strtotime($date));
                            $year = (date('Y') - date('Y', strtotime($dob)));
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
                                    <button class="btn btn-primary btn-sm select-doctor-btn" data-toggle="modal" data-target="#doctorModal" data-id="<?php echo htmlspecialchars($row['outpatient_id']); ?>">Select Doctor</button>
                                <?php } else { ?>
                                    <?php echo htmlspecialchars($row['doctor_incharge']); ?>
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
                                    <?php if ($role == 2 && $doctor_name == $row['doctor_incharge']) { ?>
                                        <button class="dropdown-item diagnosis-btn" data-toggle="modal" data-target="#diagnosisModal" data-id="<?php echo $row['outpatient_id']; ?>" <?php echo !empty($row['diagnosis']) ? 'disabled' : ''; ?>><i class="fa fa-stethoscope m-r-5"></i> Diagnosis</button>
                                    <?php } ?>
                                    <?php 
                                    if ($role == 1) {
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
var role = <?php echo json_encode($_SESSION['role']); ?>;
var doctor_name = <?php echo json_encode($_SESSION['name']); ?>;
</script>

<script>
    function clearSearch() {
        document.getElementById("outpatientSearchInput").value = '';
        filterOutpatients();
    }
    function filterOutpatients() {
        var input = document.getElementById("outpatientSearchInput").value;
        
        $.ajax({
            url: 'fetch_outpatients.php',
            method: 'GET',
            data: { query: input },
            success: function(response) {
                var data = JSON.parse(response);
                updateTable(data);
            }
        });
    }

    function updateTable(data) {
        var tbody = $('#outpatientTable tbody');
        tbody.empty();
        
        data.forEach(function(row) {
            tbody.append(`<tr>
                <td>${row.patient_id}</td>
                <td>${row.outpatient_id}</td>
                <td>${row.patient_name}</td>
                <td>${row.age}</td>
                <td>${row.dob}</td>
                <td>${row.gender}</td>
                <td>${row.doctor_incharge}</td>
                <td>
                    <form action="generate-result.php" method="get">
                        <input type="hidden" name="patient_id" value="${row.patient_id}">
                        <button class="btn btn-primary btn-sm custom-btn" type="submit">
                            <i class="fa fa-file-pdf-o m-r-5"></i> View Result
                        </button>
                    </form>
                </td>
                <td>${row.diagnosis}</td>
                <td>${row.date_time}</td>
                <td class="text-right">
                    <div class="dropdown dropdown-action">
                        <a href="#" class="action-icon dropdown-toggle" data-toggle="dropdown" aria-expanded="false">
                            <i class="fa fa-ellipsis-v"></i>
                        </a>
                        <div class="dropdown-menu dropdown-menu-right">
                            ${role == 2 && doctor_name == row.doctor_incharge ? 
                                `<button class="dropdown-item diagnosis-btn" 
                                    data-toggle="modal" 
                                    data-target="#diagnosisModal" 
                                    data-id="${row.outpatient_id}" 
                                    ${row.diagnosis ? 'disabled' : ''}>
                                    <i class="fa fa-stethoscope m-r-5"></i> Diagnosis
                                </button>` : ''
                            }
                            ${role == 1 ? 
                                `<a class="dropdown-item" href="edit-outpatient.php?id=${row.id}">
                                    <i class="fa fa-pencil m-r-5"></i> Edit
                                </a>
                                <a class="dropdown-item" href="outpatients.php?ids=${row.id}" onclick="return confirmDelete()">
                                    <i class="fa fa-trash-o m-r-5"></i> Delete
                                </a>` : ''
                            }
                        </div>
                    </div>
                </td>
            </tr>`);
        });
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
    .search-icon-bg {
    background-color: #fff; 
    border: none; 
    color: #6c757d; 
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


