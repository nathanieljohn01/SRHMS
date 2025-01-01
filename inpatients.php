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
    return mysqli_real_escape_string($connection, $input);
}

$role = isset($_SESSION['role']) ? $_SESSION['role'] : null;

// Fetch patient details from tbl_patient based on patient_id
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['patientId'])) {
    // Sanitize the patient ID input
    $patientId = sanitize($connection, $_POST['patientId']);

    // Prepare and bind the query
    $patient_query = $connection->prepare("SELECT * FROM tbl_patient WHERE id = ?");
    $patient_query->bind_param("s", $patientId);  // "s" stands for string
    
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


        // Fetch the last inpatient_id and increment it
        $last_inpatient_query = $connection->prepare("SELECT inpatient_id FROM tbl_inpatient ORDER BY id DESC LIMIT 1");
        $last_inpatient_query->execute();
        $last_inpatient_result = $last_inpatient_query->get_result();
        $last_inpatient = $last_inpatient_result->fetch_array(MYSQLI_ASSOC);

        // Generate new inpatient_id
        if ($last_inpatient) {
            $last_id_number = (int) substr($last_inpatient['inpatient_id'], 4); // Remove "IPT-" and convert to int
            $new_inpatient_id = 'IPT-' . ($last_id_number + 1);
        } else {
            $new_inpatient_id = 'IPT-1';  // Starting value if no outpatient_id exists
        }

        // Insert the patient into tbl_inpatient with room_number and bed_number as NULL
        $insert_query = $connection->prepare("
        INSERT INTO tbl_inpatient (inpatient_id, patient_id, patient_name, gender, dob, admission_date, room_number, bed_number) 
        VALUES (?, ?, ?, ?, ?, NOW(), NULL, NULL)
        ");
        $insert_query->bind_param("sssss", $new_inpatient_id, $patient_id, $name, $gender, $dob);
        $insert_query->execute();

        // Redirect or show a success message
        header('Location: inpatients.php');
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
        <input class="form-control" type="text" id="inpatientSearchInput" onkeyup="filterInpatients()" placeholder="Search Patient ID or Patient Name">
            <table class="datatable table table-stripped" id="inpatientTable">
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
                    if(isset($_GET['ids'])){
                        $id = $_GET['ids'];
                        $delete_query = mysqli_query($connection, "delete from tbl_inpatient where id='$id'");
                    }
                    $fetch_query = mysqli_query($connection, "SELECT * FROM tbl_inpatient");
                    while($row = mysqli_fetch_array($fetch_query))
                    
                    {
                        $dob = $row['dob'];
                        $date = str_replace('/', '-', $dob); 
                        $dob = date('Y-m-d', strtotime($date));
                        $year = (date('Y') - date('Y',strtotime($dob)));

                        $admission_date_time = date('F d, Y g:i A', strtotime($row['admission_date']));
                        $discharge_date_time = ($row['discharge_date']) ? date('F d Y g:i A', strtotime($row['discharge_date'])) : 'N/A';
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
                            <td><?php echo $admission_date_time; ?></td>
                            <td><?php echo $discharge_date_time; ?></td>
                            <td class="text-right">
                                <div class="dropdown dropdown-action">
                                    <a href="#" class="action-icon dropdown-toggle" data-toggle="dropdown" aria-expanded="false"><i class="fa fa-ellipsis-v"></i></a>
                                    <div class="dropdown-menu dropdown-menu-right">
                                    <?php
                                        if ($_SESSION['role'] == 3) {
                                            // Check if room_type, room_number, or bed_number is not empty to disable Insert Room link
                                            if (empty($row['room_type']) && empty($row['room_number']) && empty($row['bed_number'])) {
                                                echo '<a class="dropdown-item" href="insert-room.php?id=' . $row['id'] . '"><i class="fa fa-pencil m-r-5"></i> Insert Room</a>';
                                            } else {
                                                echo '<span class="dropdown-item disabled"><i class="fa fa-pencil m-r-5"></i> Insert Room</span>';
                                            }
                                            // Check if discharge_date is empty to enable/disable Discharge link
                                            if (empty($row['discharge_date'])) {
                                                echo '<a class="dropdown-item" href="#" onclick="confirmDischarge(' . $row['id'] . ')"><i class="fa fa-sign-out-alt m-r-5"></i> Discharge</a>';
                                            } else {
                                                echo '<span class="dropdown-item disabled"><i class="fa fa-sign-out-alt m-r-5"></i> Discharge</span>';
                                            }  
                                        }
                                        ?>
                                        <?php 
                                    if ($_SESSION['role'] == 1) {
                                        echo '<a class="dropdown-item" href="inpatients.php?ids='.$row['id'].'" onclick="return confirmDelete()"><i class="fa fa-trash-o m-r-5"></i> Delete</a>';
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
     function filterInpatients() {
        var input, filter, table, tr, td, i, txtValue;
        input = document.getElementById("inpatientSearchInput");
        filter = input.value.toUpperCase();
        table = document.getElementById("inpatientTable");
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
</script>

<script>
function confirmDischarge(id) {
    Swal.fire({
        title: 'Are you sure?',
        text: "You are about to discharge this patient. This action cannot be undone!",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#12369e',
        cancelButtonColor: '#f62d51',
        confirmButtonText: 'Yes, discharge!'
    }).then((result) => {
        if (result.isConfirmed) {
            // Show success message with checkmark icon
            Swal.fire({
                title: 'Discharged!',
                text: 'The patient has been successfully discharged.',
                icon: 'success',
                confirmButtonColor: '#12369e'
            }).then(() => {
                // Redirect to discharge.php with the patient ID
                window.location.href = `discharge.php?id=${id}`;
            });
        }
    });
}
</script>

<!-- SweetAlert2 CSS -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">

<!-- SweetAlert2 JS -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>


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