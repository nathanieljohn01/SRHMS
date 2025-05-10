<?php
session_start();
if (empty($_SESSION['name'])) {
    header('location:index.php');
    exit;
}
include('header.php');
include('includes/connection.php');

// Function to sanitize inputs
function sanitize($connection, $input) {
    return mysqli_real_escape_string($connection, htmlspecialchars(strip_tags(trim($input))));
}

$role = isset($_SESSION['role']) ? $_SESSION['role'] : null;
?>

<div class="page-wrapper">
    <div class="content">
        <div class="row">
            <div class="col-sm-4 col-3">
                <h4 class="page-title">Electronic Health Records</h4>
            </div>
        </div>

        <!-- Search Bar -->
        <div class="table-responsive">
            <div class="sticky-search">
            <h5 class="font-weight-bold mb-2">Search Patient:</h5>
                <div class="input-group mb-3">
                    <div class="position-relative w-100">
                        <!-- Search Icon -->
                        <i class="fa fa-search position-absolute text-secondary" style="top: 50%; left: 12px; transform: translateY(-50%);"></i>
                        <!-- Input Field -->
                        <input class="form-control" type="text" id="patientSearchInput" onkeyup="filterPatients()" placeholder="Search" style="padding-left: 35px; padding-right: 35px;">
                        <!-- Clear Button -->
                        <button class="position-absolute border-0 bg-transparent text-secondary" type="button" onclick="clearSearch()" style="top: 50%; right: 10px; transform: translateY(-50%);">
                            <i class="fa fa-times"></i>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Patient Table -->
        <div class="table-responsive">
            <table class="datatable table table-hover" id="patientTable">
                <thead style="background-color: #CCCCCC;">
                    <tr>
                        <th>Patient ID</th>
                        <th>Patient Type</th>
                        <th>First Name</th>
                        <th>Last Name</th>
                        <th>Age</th>
                        <th>Gender</th>
                        <th>Date Time</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $fetch_query = mysqli_query($connection, "SELECT * FROM tbl_patient WHERE deleted = 0 ORDER BY last_name ASC");
                    while ($row = mysqli_fetch_array($fetch_query)) {
                        $dob = $row['dob'];
                        $date = str_replace('/', '-', $dob);
                        $dob = date('Y-m-d', strtotime($date));
                        $year = (date('Y') - date('Y',strtotime($dob)));
                    ?>
                    <tr>
                        <td><?php echo htmlspecialchars($row['patient_id']); ?></td>
                        <td><?php echo htmlspecialchars($row['patient_type']); ?></td>
                        <td><?php echo htmlspecialchars($row['first_name']); ?></td>
                        <td><?php echo htmlspecialchars($row['last_name']); ?></td>
                        <td><?php echo $year; ?></td>
                        <td><?php echo htmlspecialchars($row['gender']); ?></td>
                        <td><?php echo date('F d, Y g:i A', strtotime($row['date_time'])); ?></td>
                        <td>
                            <button class="btn btn-primary btn-sm" onclick="viewEHR('<?php echo $row['patient_id']; ?>')">
                                View EHR
                            </button>
                        </td>
                    </tr>
                    <?php } ?>
                </tbody>
            </table>
        </div>

        <!-- EHR Modal -->
        <div class="modal fade" id="ehrModal" tabindex="-1" role="dialog">
            <div class="modal-dialog modal-lg" role="document">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Electronic Health Record</h5>
                        <button type="button" class="close" data-dismiss="modal">&times;</button>
                    </div>
                    <div class="modal-body">
                        <!-- Tabs -->
                        <ul class="nav nav-tabs">
                            <li class="nav-item">
                                <a class="nav-link active" data-toggle="tab" href="#labResults">Lab Results</a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" data-toggle="tab" href="#radiologyResults">Radiology Results</a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" data-toggle="tab" href="#medications">Medications</a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" data-toggle="tab" href="#diagnosis">Diagnosis</a>
                            </li>
                        </ul>

                        <!-- Tab Content -->
                        <div class="tab-content mt-3">
                            <!-- Lab Results -->
                            <div class="tab-pane fade show active" id="labResults">
                                <div id="labResultsContent"></div>
                            </div>

                            <!-- Radiology Results -->
                            <div class="tab-pane fade" id="radiologyResults">
                                <div id="radiologyContent"></div>
                            </div>

                            <!-- Medications -->
                            <div class="tab-pane fade" id="medications">
                                <div id="medicationsContent"></div>
                            </div>

                            <!-- Diagnosis -->
                            <div class="tab-pane fade" id="diagnosis">
                                <div id="diagnosisContent"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
include('footer.php');
?>

<script src="assets/js/jquery-3.2.1.min.js"></script>
<script src="assets/js/popper.min.js"></script>
<script src="assets/js/bootstrap.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
function filterPatients() {
    var input = document.getElementById("patientSearchInput").value;
    $.ajax({
        url: 'fetch_ehr_patients.php',
        type: 'GET',
        data: { query: input },
        success: function(response) {
            var data = JSON.parse(response);
            updatePatientsTable(data);
        },
        error: function() {
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: 'Failed to fetch patient data. Please try again.',
                confirmButtonColor: '#12369e'
            });
        }
    });
}

function updatePatientsTable(data) {
    var tbody = $('#patientTable tbody');
    tbody.empty();
    
    if (data.length === 0) {
        tbody.append(`
            <tr>
                <td colspan="8" class="text-center">No patients found</td>
            </tr>
        `);
        return;
    }
    
    data.forEach(function(row) {
        tbody.append(`
            <tr>
                <td>${row.patient_id}</td>
                <td>${row.patient_type}</td>
                <td>${row.first_name}</td>
                <td>${row.last_name}</td>
                <td>${row.age}</td>
                <td>${row.gender}</td>
                <td>${row.date_time}</td>
                <td>
                    <button class="btn btn-primary btn-sm" onclick="viewEHR('${row.patient_id}')">
                        <i class="fa fa-eye"></i> View EHR
                    </button>
                </td>
            </tr>
        `);
    });
}

function clearSearch() {
    document.getElementById("patientSearchInput").value = '';
    filterPatients();
}

function viewEHR(patientId) {
    // Load lab results
    $.ajax({
        url: 'fetch-lab-results.php',
        type: 'POST',
        data: { patientId: patientId },
        success: function(response) {
            $('#labResultsContent').html(response);
        }
    });

    // Load radiology results
    $.ajax({
        url: 'fetch-radiology-results.php',
        type: 'POST',
        data: { patientId: patientId },
        success: function(response) {
            $('#radiologyContent').html(response);
        }
    });

    // Load medications
    $.ajax({
        url: 'fetch-medications.php',
        type: 'POST',
        data: { patientId: patientId },
        success: function(response) {
            $('#medicationsContent').html(response);
        }
    });

    // Load diagnosis
    $.ajax({
        url: 'fetch-diagnosis.php',
        type: 'POST',
        data: { patientId: patientId },
        success: function(response) {
            $('#diagnosisContent').html(response);
        }
    });

    $('#ehrModal').modal('show');
}
</script>

<style>
/* Enhanced Modal Styling */
#ehrModal .modal-content {
    border-radius: 0.5rem;
    border: none;
    box-shadow: 0 5px 15px rgba(0,0,0,0.1);
}

#ehrModal .modal-title {
    font-weight: 600;
}

#ehrModal .close {
    color: black;
    opacity: 0.8;
    text-shadow: none;
}

#ehrModal .close:hover {
    opacity: 1;
}

#ehrModal .nav-tabs {
    border-bottom: 2px solid #dee2e6;
}

#ehrModal .nav-tabs .nav-link {
    border: none;
    color: #495057;
    font-weight: 500;
    padding: 0.75rem 1.25rem;
}

#ehrModal .nav-tabs .nav-link.active {
    color: #12369e;
    border-bottom: 3px solid #12369e;
    background: transparent;
}

#ehrModal .tab-content {
    padding: 1.5rem 0;
}

/* Responsive Modal Styles */
@media (max-width: 767px) {
    #ehrModal .modal-dialog {
        margin: 0;
        width: 100%;
        max-width: 100%;
        height: 100%;
    }
    
    #ehrModal .modal-content {
        height: 100%;
        border-radius: 0;
        border: none;
    }
    
    #ehrModal .modal-body {
        padding: 15px;
        overflow-y: auto;
        -webkit-overflow-scrolling: touch;
    }
    
    #ehrModal .nav-tabs {
        display: flex;
        flex-wrap: nowrap;
        overflow-x: auto;
        white-space: nowrap;
        -webkit-overflow-scrolling: touch;
    }
    
    #ehrModal .nav-link {
        padding: 0.5rem;
        font-size: 0.85rem;
    }
    
    #ehrModal .tab-content {
        padding: 1rem 0;
    }
    
    /* Make tables scrollable on mobile */
    .table-responsive {
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
    }
}

/* For tablets */
@media (min-width: 768px) and (max-width: 991px) {
    #ehrModal .modal-dialog {
        max-width: 90%;
        margin: 1.75rem auto;
    }
}

/* Additional responsive tweaks */
@media (max-width: 575px) {
    #ehrModal .modal-header {
        padding: 0.75rem;
    }
    
    #ehrModal .modal-title {
        font-size: 1.1rem;
    }
    
    #ehrModal .close {
        font-size: 1.5rem;
    }
    
    .btn-sm {
        min-width: auto;
        padding: 0.2rem 0.4rem;
        font-size: 0.8rem;
    }
}
/* Button Enhancements */
.btn-primary {
    background: #12369e;
    border: none;
    border-radius: 0.25rem;
    padding: 0.375rem 0.75rem;
    font-weight: 500;
    transition: all 0.2s;
}

.btn-primary:hover {
    background: #0d2b7a;
    transform: translateY(-1px);
    box-shadow: 0 2px 5px rgba(0,0,0,0.1);
}

.btn-sm {
    min-width: 110px;
    padding: 0.25rem 0.5rem;
    font-size: 0.875rem;
}

/* Card Styling for Results */
.card {
    border: none;
    border-radius: 0.5rem;
    box-shadow: 0 2px 10px rgba(0,0,0,0.05);
    margin-bottom: 1rem;
}

.card-header {
    background: #f8f9fa;
    border-bottom: 1px solid rgba(0,0,0,0.05);
    font-weight: 600;
}

.nav-tabs .nav-link {
    color: #333;
}
.nav-tabs .nav-link.active {
    color: #12369e;
    font-weight: bold;
}

.modal-lg {
    max-width: 900px;
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
/* Custom SweetAlert Button Color */
.swal2-confirm {
    background-color: #12369e !important;
    color: white !important;
    border: none !important;
}

/* Hover color for the confirm button */
.swal2-confirm:hover {
    background-color: #05007E !important;
}

/* Adjust button focus styles (optional) */
.swal2-confirm:focus {
    box-shadow: 0 0 0 0.2rem rgba(18, 54, 158, 0.5) !important;
}
.btn-primary {
    background: #12369e;
    border: none;
}

.btn-primary:hover {
    background: #05007E;
}
</style>