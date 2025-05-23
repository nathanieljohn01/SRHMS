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
                <a href="add-patient.php" class="btn btn-primary float-right"><i class="fa fa-plus"></i> Add Patient</a>
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


        <!-- Patient Table 1 -->
        <div class="table-responsive">
            <table class="datatable table table-hover table-striped" id="patientTable">
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
                                    if ($_SESSION['role'] == 3 ) {
                                        }
                                        echo '<a class="dropdown-item" href="edit-patient.php?id='.$row['id'].'"><i class="fa fa-pencil m-r-5"></i> Edit</a>';
                                    if ($_SESSION['role'] == 1 ) {
                                        echo '<a class="dropdown-item" href="#" onclick="return confirmDelete(\''.$row['id'].'\')"><i class="fa fa-trash m-r-5"></i> Delete</a>';
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
                        <th>Weight</th>
                        <th>Height</th>
                        <th>Temperature</th>
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
                            <td><?php echo $row['weight']; ?> kg</td>
                            <td><?php echo $row['height']; ?> cm</td>
                            <td><?php echo $row['temperature']; ?> °C</td>
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
            window.location.href = 'patients.php?ids=' + id;  
        }
    });
}

function clearSearch() {
    document.getElementById("patientSearchInput").value = '';
    filterPatients();
}
var role = <?php echo json_encode($_SESSION['role']); ?>;

function filterPatients() {
    var input = document.getElementById("patientSearchInput").value;
    
    $.ajax({
        url: 'fetch_patients.php',
        type: 'GET',
        data: { query: input },
        success: function(response) {
            var data = JSON.parse(response);
            updatePatientTables(data);
        },
        error: function(xhr, status, error) {
            alert('Error fetching data. Please try again.');
        }
    });
}

function updatePatientTables(data) {
    var tbody1 = $('#patientTable tbody');
    var tbody2 = $('#patientTable2 tbody');
    tbody1.empty();
    tbody2.empty();
    
    data.forEach(function(record) {
        // First table row
        tbody1.append(`
            <tr data-patient-id="${record.id}">
                <td>${record.patient_id}</td>
                <td>${record.patient_type}</td>
                <td>${record.name}</td>
                <td>${record.age}</td>
                <td>${record.dob}</td>
                <td>${record.gender}</td>
                <td>${record.civil_status}</td>
                <td>${record.address}</td>
                <td>${record.date_time}</td>
                <td class="text-right">
                    <div class="dropdown dropdown-action">
                        <a href="#" class="action-icon dropdown-toggle" data-toggle="dropdown" aria-expanded="false">
                            <i class="fa fa-ellipsis-v"></i>
                        </a>
                        <div class="dropdown-menu dropdown-menu-right">
                            ${getActionButtons(record.id)}
                        </div>
                    </div>
                </td>
            </tr>
        `);

        // Second table row
        tbody2.append(`
            <tr data-patient-id="${record.id}">
                <td>${record.email}</td>
                <td>${record.contact_number}</td>
                <td>${record.weight}</td>
                <td>${record.height}</td>
                <td>${record.temperature}</td>
                <td>${record.blood_pressure}</td>
                <td>${record.menstruation}</td>
                <td>${record.last_menstrual_period}</td>
                <td>${record.message}</td>
            </tr>
        `);
    });
}

function getActionButtons(id) {
    if (role == 1) {
        return `
            <a class="dropdown-item" href="edit-patient.php?id=${id}">
                <i class="fa fa-pencil m-r-5"></i> Edit
            </a>
            <a class="dropdown-item" href="patients.php?ids=${id}" onclick="return confirmDelete()">
                <i class="fa fa-trash m-r-5"></i> Delete
            </a>
        `;
    }
    return '';
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

.action-icon {
    cursor: pointer;
}

</style>
