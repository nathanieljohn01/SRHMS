<?php
session_start();
ob_start(); // Start output buffering
if(empty($_SESSION['name'])) {
    header('location:index.php');
    exit; // stop further execution
}
include('header.php');
include('includes/connection.php');

// Function to sanitize user inputs
function sanitize($connection, $input) {
    return mysqli_real_escape_string($connection, htmlspecialchars(strip_tags(trim($input)), ENT_QUOTES, 'UTF-8'));
}

$role = isset($_SESSION['role']) ? $_SESSION['role'] : null;

// Fetch patient details from tbl_patient based on patient_id
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['patientId'])) {
    // Sanitize the patient ID input
    $patientId = sanitize($connection, $_POST['patientId']);

    // Prepare and bind the query
    $patient_query = $connection->prepare("SELECT * FROM tbl_patient WHERE id = ?");
    $patient_query->bind_param("s", $patientId); // "s" stands for string
    
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
    
        // Fetch last inpatient_id and increment it
        $last_inpatient_query = $connection->prepare("SELECT inpatient_id FROM tbl_inpatient ORDER BY id DESC LIMIT 1");
        $last_inpatient_query->execute();
        $last_inpatient_result = $last_inpatient_query->get_result();
        $last_inpatient = $last_inpatient_result->fetch_array(MYSQLI_ASSOC);
    
        // Generate new inpatient_id
        if ($last_inpatient) {
            $last_id_number = (int) substr($last_inpatient['inpatient_id'], 4);
            $new_inpatient_id = 'IPT-' . ($last_id_number + 1);
        } else {
            $new_inpatient_id = 'IPT-1';
        }
    
        // Insert into tbl_inpatient
        $insert_query = $connection->prepare("
            INSERT INTO tbl_inpatient (inpatient_id, patient_id, patient_name, gender, dob, admission_date, room_number, bed_number) 
            VALUES (?, ?, ?, ?, ?, NOW(), NULL, NULL)
        ");
        $insert_query->bind_param("sssss", $new_inpatient_id, $patient_id, $name, $gender, $dob);
        $insert_query->execute();
    
        // Redirect to inpatients.php instead of showing success message via PHP
        header("Location: inpatients.php?success=1");
        exit;
    }
    
    $patient_query->close();
}

ob_end_flush(); // Flush output buffer
?>
<div class="page-wrapper">
    <div class="content">
        <div class="row">
            <div class="col-sm-4 col-3">
                <h4 class="page-title">Inpatient</h4>
            </div>
            <?php if ($role == 1 || $role == 3): ?>
                <div class="col-sm-10 col-9 m-b-20">
                    <form method="POST" action="inpatients.php" id="addPatientForm" class="form-inline">
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
                        <input class="form-control" type="text" id="inpatientSearchInput" onkeyup="filterInpatients()" placeholder="Search" style="padding-left: 35px; padding-right: 35px;">
                        <!-- Clear Button -->
                        <button class="position-absolute border-0 bg-transparent text-secondary" type="button" onclick="clearSearch()" style="top: 50%; right: 10px; transform: translateY(-50%);">
                            <i class="fa fa-times"></i>
                        </button>
                    </div>
                </div>
            </div>
        </div>
        <div class="table-responsive">
            <table class="datatable table table-hover table-striped" id="inpatientTable">
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
                        <th>Admission Date & Time</th>
                        <th>Discharge Date & Time</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    if (isset($_GET['ids'])) {
                        // Sanitize the input to avoid SQL injection
                        $id = intval($_GET['ids']);  // Ensuring it's an integer
                        $update_query = mysqli_prepare($connection, "UPDATE tbl_inpatient SET deleted = 1 WHERE id = ?");
                        $update_query->bind_param("i", $id);
                        $update_query->execute();
                        echo "<script>showSuccess('Inpatient record deleted successfully!', true);</script>";
                    }
                    $fetch_query = mysqli_query($connection, "SELECT * FROM tbl_inpatient WHERE deleted = 0 ORDER BY id DESC");
                    while ($row = mysqli_fetch_array($fetch_query)) {
                        $dob = $row['dob'];
                        $date = str_replace('/', '-', $dob); 
                        $dob = date('Y-m-d', strtotime($date));
                        $year = (date('Y') - date('Y', strtotime($dob)));

                        $admission_date_time = date('F d, Y g:i A', strtotime($row['admission_date']));
                        $discharge_date_time = ($row['discharge_date']) ? date('F d, Y g:i A', strtotime($row['discharge_date'])) : 'N/A';
                    ?>
                        <tr>
                            <td><?php echo htmlspecialchars($row['patient_id']); ?></td>
                            <td><?php echo htmlspecialchars($row['inpatient_id']); ?></td>
                            <td><?php echo htmlspecialchars($row['patient_name']); ?></td>
                            <td><?php echo $year; ?></td>
                            <td><?php echo htmlspecialchars($row['gender']); ?></td>
                            <td><?php echo htmlspecialchars($row['room_type']); ?></td>
                            <td><?php echo htmlspecialchars($row['room_number']); ?></td>
                            <td><?php echo htmlspecialchars($row['bed_number']); ?></td>
                            <td><?php echo $admission_date_time; ?></td>
                            <td><?php echo $discharge_date_time; ?></td>
                            <td class="text-right">
                                <div class="dropdown dropdown-action">
                                    <a href="#" class="action-icon dropdown-toggle" data-toggle="dropdown" aria-expanded="false"><i class="fa fa-ellipsis-v"></i></a>
                                    <div class="dropdown-menu dropdown-menu-right">
                                        <?php if ($_SESSION['role'] == 1 || $_SESSION['role'] == 3): ?>
                                            <!-- Insert Room Link Disabled based on conditions -->
                                            <?php if (empty($row['room_type']) && empty($row['room_number']) && empty($row['bed_number'])): ?>
                                                <a class="dropdown-item" href="insert-room.php?id=<?php echo $row['id']; ?>"><i class="fa fa-pencil m-r-5"></i> Insert Room</a>
                                            <?php else: ?>
                                                <span class="dropdown-item disabled"><i class="fa fa-pencil m-r-5"></i> Insert Room</span>
                                            <?php endif; ?>
                                            <!-- Discharge Link Disabled based on discharge_date -->
                                            <?php if (empty($row['discharge_date'])): ?>
                                                <a class="dropdown-item" href="#" onclick="confirmDischarge(<?php echo $row['id']; ?>)"><i class="fa fa-sign-out-alt m-r-5"></i> Discharge</a>
                                            <?php else: ?>
                                                <span class="dropdown-item disabled"><i class="fa fa-sign-out-alt m-r-5"></i> Discharge</span>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                        <?php if ($_SESSION['role'] == 1): ?>
                                            <a class="dropdown-item" href="#" onclick="return confirmDelete('<?php echo $row['id']; ?>')"><i class="fa fa-trash m-r-5"></i> Delete</a>
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
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script language="JavaScript" type="text/javascript">
function confirmDelete(id) {
    return Swal.fire({
        title: 'Delete Patient Record?',
        text: 'Are you sure you want to delete this Patient record? This action cannot be undone!',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#12369e',
        confirmButtonText: 'OK'
    }).then((result) => {
        if (result.isConfirmed) {
            window.location.href = 'inpatients.php?id=' + id;
        }
    });
}

function confirmDischarge(id) {
    Swal.fire({
        title: 'Are you sure?',
        text: "You are about to discharge this inpatient. This action cannot be undone!",
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#12369e',
        confirmButtonText: 'Yes'
    }).then((result) => {
        if (result.isConfirmed) {
            Swal.fire({
                title: 'Processing...',
                text: 'Discharging inpatient...',
                allowOutsideClick: false,
                showConfirmButton: false,
                willOpen: () => {
                    Swal.showLoading();
                }
            });

            setTimeout(() => {
                window.location.href = 'discharge.php?id=' + id;
            }, 500); // Adjust timing as needed
        }
    });
}
</script>

<script>
    function clearSearch() {
        document.getElementById("inpatientSearchInput").value = '';
        filterInpatients();
    }
    function filterInpatients() {
        var input = document.getElementById("inpatientSearchInput").value;
        
        $.ajax({
            url: 'fetch_inpatients.php',
            method: 'GET',
            data: { query: input },
            success: function(response) {
                var data = JSON.parse(response);
                updateTable(data);
            }
        });
    }

    function updateTable(data) {
        var tbody = $('#inpatientTable tbody');
        tbody.empty();
        
        data.forEach(function(row) {
            var actionButtons = '';
            if (role == 3) {
                if (!row.room_type && !row.room_number && !row.bed_number) {
                    actionButtons += `<a class="dropdown-item" href="insert-room.php?id=${row.id}">
                        <i class="fa fa-pencil m-r-5"></i> Insert Room</a>`;
                } else {
                    actionButtons += `<span class="dropdown-item disabled">
                        <i class="fa fa-pencil m-r-5"></i> Insert Room</span>`;
                }
                
                if (!row.has_discharge) {
                    actionButtons += `<a class="dropdown-item" href="#" onclick="confirmDischarge(${row.id})">
                        <i class="fa fa-sign-out-alt m-r-5"></i> Discharge</a>`;
                } else {
                    actionButtons += `<span class="dropdown-item disabled">
                        <i class="fa fa-sign-out-alt m-r-5"></i> Discharge</span>`;
                }
            }
            
            if (role == 1) {
                actionButtons += ` <a class="dropdown-item" href="#" onclick="return confirmDelete('${row.id}')">
                        <i class="fa fa-trash m-r-5"></i> Delete</a>`;
            }

            tbody.append(`<tr>
                <td>${row.patient_id}</td>
                <td>${row.inpatient_id}</td>
                <td>${row.patient_name}</td>
                <td>${row.age}</td>
                <td>${row.gender}</td>
                <td>${row.room_type || ''}</td>
                <td>${row.room_number || ''}</td>
                <td>${row.bed_number || ''}</td>
                <td>${row.admission_date}</td>
                <td>${row.discharge_date}</td>
                <td class="text-right">
                    <div class="dropdown dropdown-action">
                        <a href="#" class="action-icon dropdown-toggle" data-toggle="dropdown" aria-expanded="false">
                            <i class="fa fa-ellipsis-v"></i>
                        </a>
                        <div class="dropdown-menu dropdown-menu-right">
                            ${actionButtons}
                        </div>
                    </div>
                </td>
            </tr>`);
        });
    }

    var role = <?php echo json_encode($_SESSION['role']); ?>;
    var doctor_name = <?php echo json_encode($_SESSION['name']); ?>;

    function searchPatients() {
        var input = document.getElementById("patientSearchInput").value;
        if (input.length < 2) {
            document.getElementById("searchResults").style.display = "none";
            document.getElementById("searchResults").innerHTML = "";
            return;
        }
        $.ajax({
            url: "search-inpatient.php", // Backend script to fetch patients
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


<style>
dropdown-item {
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