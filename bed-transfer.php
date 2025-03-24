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
                    $msg = "Patient already exists in the transfer records.";
                    $status = "warning"; // Yellow icon
                } else {
                    // Insert the patient into tbl_transfer
                    $insert_query = $connection->prepare("
                        INSERT INTO tbl_transfer (inpatient_id, patient_id, patient_name, gender, dob) 
                        VALUES (?, ?, ?, ?, ?)
                    ");
                    
                    if (!$insert_query) {
                        $msg = "Error in prepared statement: " . $connection->error;
                        $status = "error"; // Red icon
                    } else {
                        $insert_query->bind_param('sssss', $inpatient_id, $patient_id, $name, $gender, $dob);
                        
                        if ($insert_query->execute()) {
                            echo "<script>
                                Swal.fire({
                                    title: 'Processing...',
                                    text: 'Transferring patient...',
                                    allowOutsideClick: false,
                                    showConfirmButton: false,
                                    willOpen: () => {
                                        Swal.showLoading();
                                    }
                                });
        
                                setTimeout(() => {
                                    Swal.fire({
                                        title: 'Success!',
                                        text: 'Patient transferred successfully.',
                                        icon: 'success',
                                        confirmButtonColor: '#12369e'
                                    }).then(() => {
                                        window.location.href = 'bed-transfer.php';
                                    });
                                }, 1000);
                            </script>";
                        } else {
                            echo "<script>
                                Swal.fire({
                                    title: 'Processing...',
                                    text: 'Transferring patient...',
                                    allowOutsideClick: false,
                                    showConfirmButton: false,
                                    willOpen: () => {
                                        Swal.showLoading();
                                    }
                                });
        
                                setTimeout(() => {
                                    Swal.fire({
                                        title: 'Error!',
                                        text: 'Failed to transfer patient. Please try again.',
                                        icon: 'error',
                                        confirmButtonColor: '#d33'
                                    });
                                }, 1000);
                            </script>";
                        }
                    }
                }
            }
        } else {
            echo "<script>
                Swal.fire({
                    title: 'Processing...',
                    text: 'Checking patient details...',
                    allowOutsideClick: false,
                    showConfirmButton: false,
                    willOpen: () => {
                        Swal.showLoading();
                    }
                });
        
                setTimeout(() => {
                    Swal.fire({
                        title: 'Patient Not Found!',
                        text: 'Please check the Patient ID and try again.',
                        icon: 'warning',
                        confirmButtonColor: '#12369e'
                    });
                }, 1000);
            </script>";
        }
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
                        <input class="form-control" type="text" id="transferSearchInput" onkeyup="filterTransfer()" placeholder="Search" style="padding-left: 35px; padding-right: 35px;">
                        <!-- Clear Button -->
                        <button class="position-absolute border-0 bg-transparent text-secondary" type="button" onclick="clearSearch()" style="top: 50%; right: 10px; transform: translateY(-50%);">
                            <i class="fa fa-times"></i>
                        </button>
                    </div>
                </div>
            </div>
        </div>
        <div class="table-responsive">
            <table class="datatable table table-hover table-striped" id="transferTable">
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
                        $id = sanitize($connection, $_GET['ids']);
                        $update_query = $connection->prepare("UPDATE tbl_transfer SET deleted = 1 WHERE id = ?");
                        $update_query->bind_param("s", $id);
                        $update_query->execute();
                    }
                    $fetch_query = mysqli_query($connection, "select * from tbl_transfer where deleted = 0 order by id desc");
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
                                        echo '<a class="dropdown-item" href="#" onclick="return confirmDelete(\''.$row['id'].'\')"><i class="fa fa-trash-o m-r-5"></i> Delete</a>';
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
function confirmDelete(id) {
    return Swal.fire({
        title: 'Delete Patient Record?',
        text: 'Are you sure you want to delete this Patient record? This action cannot be undone!',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#12369e',
        confirmButtonText: 'Yes, delete it!'
    }).then((result) => {
        if (result.isConfirmed) {
            window.location.href = 'bed-transfer.php?ids=' + id;  
        }
    });
}
</script>

<script>
    function clearSearch() {
        document.getElementById("transferSearchInput").value = '';
        filterTransfer();
    }
    function filterTransfer() {
        var input = document.getElementById("transferSearchInput").value;
        
        $.ajax({
            url: 'fetch_transfer.php',
            method: 'GET',
            data: { query: input },
            success: function(response) {
                var data = JSON.parse(response);
                updateTransferTable(data);
            }
        });
    }

    function updateTransferTable(data) {
        var tbody = $('#transferTable tbody');
        tbody.empty();
        
        data.forEach(function(row) {
            var actionButtons = '';
            if (role == 3) {
                if (!row.has_room) {
                    actionButtons += `<a class="dropdown-item" href="change-room.php?id=${row.id}">
                        <i class="fa fa-pencil m-r-5"></i> Insert New Room</a>`;
                }
            }
            
            if (role == 1) {
                actionButtons += `<a class="dropdown-item" href="bed-transfer.php?ids=${row.id}" onclick="return confirmDelete()">
                    <i class="fa fa-trash-o m-r-5"></i> Delete</a>`;
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
                <td>${row.transfer_date}</td>
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

    // Add this at the top of your script
    var role = <?php echo json_encode($_SESSION['role']); ?>;


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
    .search-icon-bg {
    background-color: #fff; 
    border: none; 
    color: #6c757d; 
    }
</style>