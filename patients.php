<?php
session_start();
if (empty($_SESSION['name'])) {
    header('location:index.php');
}
include('header.php');
include('includes/connection.php');
?>

<div class="page-wrapper">
    <div class="content">
        <div class="row">
            <div class="col-sm-4 col-3">
                <h4 class="page-title">Patient Registration</h4>
            </div>
            <div class="col-sm-8 col-9 text-right m-b-20">
                <a href="add-patient.php" class="btn btn-primary btn-rounded float-right"><i class="fa fa-plus"></i> Add Patient</a>
            </div>
        </div>

        <!-- Search Bar -->
        <input class="form-control mb-4" type="text" id="patientSearchInput" onkeyup="filterPatients()" placeholder="Search for Patient">

        <!-- Patient Table 1 -->
        <div class="table-responsive">
            <table class="datatable table table-hover" id="patientTable">
                <thead style="background-color: #CCCCCC;">
                    <tr>
                        <th>Patient ID</th>
                        <th>Patient Type</th>
                        <th>Name</th>
                        <th>Age</th>
                        <th>Birthdate</th>
                        <th>Gender</th>
                        <th>Civil Status</th>
                        <th>Address</th>
                        <th>Date and Time</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    if(isset($_GET['ids'])){
                        $id = intval($_GET['ids']);
                        $stmt = $connection->prepare("UPDATE tbl_patient SET deleted = 1 WHERE id = ?");
                        $stmt->bind_param("i", $id);
                        $stmt->execute();
                        $stmt->close();
                    }
                    $fetch_query = mysqli_query($connection, "SELECT * FROM tbl_patient WHERE deleted = 0");
                    while ($row = mysqli_fetch_array($fetch_query)) {
                        $dob = $row['dob'];
                        $date = str_replace('/', '-', $dob); 
                        $dob = date('Y-m-d', strtotime($date));
                        $year = (date('Y') - date('Y',strtotime($dob)));

                        $date_time = date('F d, Y g:i A', strtotime($row['date_time']));
                    ?>
                        <tr data-patient-id="<?php echo $row['id']; ?>">
                            <td><?php echo $row['patient_id']; ?></td>
                            <td><?php echo $row['patient_type']; ?></td>
                            <td><?php echo $row['first_name'] . " " . $row['last_name']; ?></td>
                            <td><?php echo $year; ?></td>
                            <td><?php echo $row['dob']; ?></td>
                            <td><?php echo $row['gender']; ?></td>
                            <td><?php echo $row['civil_status']; ?></td>
                            <td><?php echo $row['address']; ?></td>
                            <td><?php echo $date_time; ?></td>
                            <td class="text-right">
                                <div class="dropdown dropdown-action">
                                    <a href="#" class="action-icon dropdown-toggle" data-toggle="dropdown" aria-expanded="false"><i class="fa fa-ellipsis-v"></i></a>
                                    <div class="dropdown-menu dropdown-menu-right">
                                    <?php 
                                    if ($_SESSION['role'] == 1) {
                                        echo '<a class="dropdown-item" href="edit-patient.php?id='.$row['id'].'"><i class="fa fa-pencil m-r-5"></i> Edit</a>';
                                        echo '<a class="dropdown-item" href="patients.php?ids=' . $row['id'] . '" onclick="return confirmDelete()"><i class="fa fa-trash-o m-r-5"></i> Delete</a>';
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

        <!-- Patient Table 2 -->
        <div class="table-responsive mt-5">
            <table class="datatable table table-hover" id="patientTable2">
                <thead style="background-color: #CCCCCC;">
                    <tr>
                        <th>Email</th>
                        <th>Contact Number</th>
                        <th>Weight (kg)</th>
                        <th>Height (ft)</th>
                        <th>Temperature (°C)</th>
                        <th>Blood Pressure</th>
                        <th>Menstruation</th>
                        <th>Last Menstrual Period</th>
                        <th>Other Concerns</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $fetch_query = mysqli_query($connection, "SELECT * FROM tbl_patient WHERE deleted = 0");
                    while ($row = mysqli_fetch_array($fetch_query)) {
                    ?>
                        <tr data-patient-id="<?php echo $row['id']; ?>">
                            <td><?php echo $row['email']; ?></td>
                            <td><?php echo $row['contact_number']; ?></td>
                            <td><?php echo $row['weight']; ?></td>
                            <td><?php echo $row['height']; ?></td>
                            <td><?php echo $row['temperature']; ?></td>
                            <td><?php echo $row['blood_pressure']; ?></td>
                            <td><?php echo $row['menstruation']; ?></td>
                            <td><?php echo $row['last_menstrual_period']; ?></td>
                            <td><?php echo $row['message']; ?></td>                      
                        </tr>
                    <?php } ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include('footer.php'); ?>
<!-- JavaScript Function for Filtering Both Tables -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
function confirmDelete(){
    return confirm('Are you sure want to delete this Patient?');
}

function filterPatients() {
    var input = document.getElementById("patientSearchInput");
    var filter = input.value.toUpperCase();

    // Get both patient tables
    var patientTable = document.getElementById("patientTable");
    var patientTable2 = document.getElementById("patientTable2");

    // Get all rows from both tables
    var patientRows = patientTable.getElementsByTagName("tr");
    var patientRows2 = patientTable2.getElementsByTagName("tr");

    // Create an array to keep track of matched patient IDs
    var matchedPatientIds = [];

    // Filter rows in the first table (Patient Information)
    for (let i = 1; i < patientRows.length; i++) {
        let matchFound = false;
        for (let j = 0; j < patientRows[i].cells.length; j++) {
            let cell = patientRows[i].cells[j];
            if (cell) {
                let txtValue = cell.textContent || cell.innerText;
                if (txtValue.toUpperCase().indexOf(filter) > -1) {
                    matchFound = true;
                    break;
                }
            }
        }
        patientRows[i].style.display = matchFound ? "" : "none";

        // Store matched patient ID
        if (matchFound) {
            matchedPatientIds.push(patientRows[i].getAttribute("data-patient-id"));
        }
    }

    // Filter rows in the second table (Additional Patient Details)
    for (let i = 1; i < patientRows2.length; i++) {
        let patientId2 = patientRows2[i].getAttribute("data-patient-id");
        patientRows2[i].style.display = matchedPatientIds.includes(patientId2) ? "" : "none";
    }
}
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
#patientTable2_length, #patientTable_paginate .paginate_button {
    display: none;
}

.btn-primary {
    background: #12369e;
    border: none;
}

.btn-primary:hover {
    background: #05007E;
}
/* CSS for preventing dropdown scroll issue */
.dropdown-menu {
    position: absolute;
    top: 0;
    right: 0;
    z-index: 9999;
    min-width: 150px; /* Adjust according to your preference */
}

.dropdown-toggle:focus {
    outline: none; /* Optional: removes the focus outline */
}

.action-icon {
    cursor: pointer;
}
</style>
