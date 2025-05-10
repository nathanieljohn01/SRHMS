<?php
session_start();
ob_start(); // Start output buffering
if(empty($_SESSION['name'])) {
    header('location:index.php');
    exit; // Stop further execution
}
include('header.php');
include('includes/connection.php');

// Function to sanitize user inputs
function sanitize($connection, $input) {
    return mysqli_real_escape_string($connection, $input);
}

$role = isset($_SESSION['role']) ? $_SESSION['role'] : null;
$editable = ($_SESSION['role'] == 1);
$can_print = ($_SESSION['role'] == 5);

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['patientId'])) {
    // Sanitize the patient ID input
    $patientId = sanitize($connection, $_POST['patientId']);

    // Prepare and bind the query
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
    
        // Fetch the last fecalysis ID and generate a new one
        $last_fecalysis_query = $connection->prepare("SELECT fecalysis_id FROM tbl_fecalysis ORDER BY fecalysis_id DESC LIMIT 1");
        $last_fecalysis_query->execute();
        $last_fecalysis_result = $last_fecalysis_query->get_result();
        $last_fecalysis = $last_fecalysis_result->fetch_array(MYSQLI_ASSOC);
    
        if ($last_fecalysis) {
            $last_id_number = (int) substr($last_fecalysis['fecalysis_id'], 3);  // Extract the number after "FC-"
            $new_fecalysis_id = 'FC-' . ($last_id_number + 1);
        } else {
            $new_fecalysis_id = 'FC-1';  // Starting value if no previous fecalysis ID exists
        }
    
        // Assign the generated ID to $fecalysis_id
        $fecalysis_id = $new_fecalysis_id;
    
        // Sanitize other inputs with default values if empty
        $color = sanitize($connection, $_POST['color'] ?? NULL);
        $consistency = sanitize($connection, $_POST['consistency'] ?? NULL);
        $occult_blood = sanitize($connection, $_POST['occult_blood'] ?? NULL);
        $ova_or_parasite = sanitize($connection, $_POST['ova_or_parasite'] ?? NULL);
        $yeast_cells = sanitize($connection, $_POST['yeast_cells'] ?? NULL);
        $fat_globules = sanitize($connection, $_POST['fat_globules'] ?? NULL);
        $pus_cells = sanitize($connection, $_POST['pus_cells'] ?? NULL);
        $rbc = sanitize($connection, $_POST['rbc'] ?? NULL);
        $bacteria = sanitize($connection, $_POST['bacteria'] ?? NULL);
    
        // Insert query for fecalysis
        $query = "
            INSERT INTO tbl_fecalysis (fecalysis_id, patient_id, patient_name, gender, dob, color, consistency, occult_blood, ova_or_parasite, yeast_cells, fat_globules, pus_cells, rbc, bacteria, date_time) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ";
    
        $stmt = $connection->prepare($query);
        $stmt->bind_param("ssssssssssssss", $fecalysis_id, $patient_id, $name, $gender, $dob, $color, $consistency, $occult_blood, $ova_or_parasite, $yeast_cells, $fat_globules, $pus_cells, $rbc, $bacteria);
        
        if ($stmt->execute()) {
            echo "
            <script>
                Swal.fire({
                    title: 'Processing...',
                    text: 'Saving fecalysis record...',
                    allowOutsideClick: false,
                    showConfirmButton: false,
                    willOpen: () => {
                        Swal.showLoading();
                    }
                });
    
                setTimeout(() => {
                    Swal.fire({
                        title: 'Success!',
                        text: 'Fecalysis record added successfully.',
                        icon: 'success',
                        confirmButtonColor: '#12369e',
                        confirmButtonText: 'OK'
                    }).then(() => {
                        window.location.href = 'fecalysis.php';
                    });
                }, 1000);
            </script>";
        } else {
            echo "
            <script>
                Swal.fire({
                    title: 'Error!',
                    text: 'Failed to add fecalysis record. Please try again.',
                    icon: 'error',
                    confirmButtonColor: '#12369e',
                    confirmButtonText: 'OK'
                });
            </script>";
        }
    } else {
        echo "
        <script>
            Swal.fire({
                title: 'Patient Not Found!',
                text: 'Please check the Patient ID and try again.',
                icon: 'error',
                confirmButtonColor: '#12369e',
                confirmButtonText: 'OK'
            });
        </script>";
    }    
}
ob_end_flush(); // Flush output buffer
?>

<div class="page-wrapper">
    <div class="content">
        <div class="row">
            <div class="col-sm-4 col-3">
                <h4 class="page-title">Fecalysis</h4>
            </div>
            <?php if ($role == 1 || $role == 5): ?>
                <div class="col-sm-10 col-9 m-b-20">
                    <form method="POST" action="fecalysis.php" id="addPatientForm" class="form-inline">
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
            <div class="sticky-search">
            <h5 class="font-weight-bold mb-2">Search Patient:</h5>
                <div class="input-group mb-3">
                    <div class="position-relative w-100">
                        <!-- Search Icon -->
                        <i class="fa fa-search position-absolute text-secondary" style="top: 50%; left: 12px; transform: translateY(-50%);"></i>
                        <!-- Input Field -->
                        <input class="form-control" type="text" id="combinedSearchInput" onkeyup="filterFecalysis()" placeholder="Search" style="padding-left: 35px; padding-right: 35px;">
                        <!-- Clear Button -->
                        <button class="position-absolute border-0 bg-transparent text-secondary" type="button" onclick="clearSearch()" style="top: 50%; right: 10px; transform: translateY(-50%);">
                            <i class="fa fa-times"></i>
                        </button>
                    </div>
                </div>
            </div>
        </div>
        <div class="table-responsive">
            <h4 class="mt-3">Macroscopic</h4>
            <table class="datatable table table-bordered table-hover" id="patientTableMacroscopic">
                <thead style="background-color: #CCCCCC;">
                    <tr>
                        <th>Fecalysis ID</th>
                        <th>Patient ID</th>
                        <th>Patient Name</th>
                        <th>Age</th>
                        <th>Gender</th>
                        <th>Date and Time</th>
                        <th>Color</th>
                        <th>Consistency</th>
                        <th>Occult Blood</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    if(isset($_GET['fecalysis_id'])){
                        $fecalysis_id = sanitize($connection, $_GET['fecalysis_id']);
                        $update_query = $connection->prepare("UPDATE tbl_fecalysis SET deleted = 1 WHERE fecalysis_id = ?");
                        $update_query->bind_param("s", $fecalysis_id);
                        $update_query->execute();
                    }
                    $fetch_query = mysqli_query($connection, "SELECT * FROM tbl_fecalysis WHERE deleted = 0 ORDER BY date_time ASC");
                    while ($row = mysqli_fetch_array($fetch_query)) {
                        $dob = $row['dob'];
                        $date = str_replace('/', '-', $dob); 
                        $dob = date('Y-m-d', strtotime($date));
                        $year = (date('Y') - date('Y', strtotime($dob)));
                    ?>
                    <tr data-fecalysis-id="${record.fecalysis_id}">
                        <td><?php echo $row['fecalysis_id']; ?></td>
                        <td><?php echo $row['patient_id']; ?></td>
                        <td><?php echo $row['patient_name']; ?></td>
                        <td><?php echo $year; ?></td>
                        <td><?php echo $row['gender']; ?></td>
                        <td><?php echo date('F d, Y g:i A', strtotime($row['date_time'])); ?></td>
                        <td><?php echo $row['color']; ?></td>
                        <td><?php echo $row['consistency']; ?></td>
                        <td><?php echo $row['occult_blood']; ?></td>
                        <td class="text-right">
                            <div class="dropdown dropdown-action">
                                <a href="#" class="action-icon dropdown-toggle" data-toggle="dropdown" aria-expanded="false"><i class="fa fa-ellipsis-v"></i></a>
                                <div class="dropdown-menu dropdown-menu-right" style="                                
                                        min-width: 200px;
                                        position: absolute;
                                        top: 50%;
                                        transform: translateY(-50%);
                                        right: 50%;
                                    ">
                                    <?php if ($can_print): ?>
                                        <div class="dropdown-item">
                                        <form action="generate-fecalysis.php" method="get" class="p-2">
                                            <input type="hidden" name="id" value="<?php echo $row['fecalysis_id']; ?>">
                                            <div class="form-group mb-2">
                                                <input type="text" class="form-control" name="filename" placeholder="Filename (required)" required>
                                            </div>
                                            <button class="btn btn-primary btn-sm custom-btn" type="submit">
                                                <i class="fa fa-file-pdf m-r-5"></i> Generate PDF
                                            </button>
                                        </form>
                                    </div>
                                    <div class="dropdown-divider"></div>
                                    <?php endif; ?>
                                    <a class="dropdown-item" href="edit-fecalysis.php?id=<?php echo $row['fecalysis_id']; ?>"><i class="fa fa-pencil m-r-5"></i> Insert and Edit</a>
                                    <?php if ($editable): ?>
                                        <a class="dropdown-item" href="#" onclick="return confirmDelete('<?php echo $row['fecalysis_id']; ?>')"><i class="fa fa-trash m-r-5"></i> Delete</a>
                                    <?php else: ?>
                                        <a class="dropdown-item disabled" href="#"><i class="fa fa-trash m-r-5"></i> Delete</a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </td>
                    </tr>
                    <?php } ?>
                </tbody>
            </table>
        </div>
        <div class="table-responsive">
            <h4 class="mt-4">Microscopic</h4>
            <table class="datatable table table-bordered table-hover" id="patientTableMicroscopic">
                <thead style="background-color: #CCCCCC;">
                    <tr>
                        <th>Fecalysis ID</th>
                        <th>Patient ID</th>
                        <th>Patient Name</th>
                        <th>Age</th>
                        <th>Gender</th>
                        <th>Date and Time</th>
                        <th>Pus Cells</th>
                        <th>Ova or Parasite</th>
                        <th>Yeast Cells</th>
                        <th>Fat Globules</th>
                        <th>Plus Cell</th>
                        <th>RBC</th>
                        <th>Bacteria</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    foreach ($fetch_query as $row) {
                        $dob = $row['dob'];
                        $date = str_replace('/', '-', $dob); 
                        $dob = date('Y-m-d', strtotime($date));
                        $year = (date('Y') - date('Y', strtotime($dob)));
                    ?>
                    <tr data-fecalysis-id="${record.fecalysis_id}">
                        <td><?php echo $row['fecalysis_id']; ?></td>
                        <td><?php echo $row['patient_id']; ?></td>
                        <td><?php echo $row['patient_name']; ?></td>
                        <td><?php echo $year; ?></td>
                        <td><?php echo $row['gender']; ?></td>
                        <td><?php echo date('F d, Y g:i A', strtotime($row['date_time'])); ?></td>
                        <td><?php echo $row['pus_cells']; ?></td>
                        <td><?php echo $row['ova_or_parasite']; ?></td>
                        <td><?php echo $row['yeast_cells']; ?></td>
                        <td><?php echo $row['fat_globules']; ?></td>
                        <td><?php echo $row['pus_cells']; ?></td>
                        <td><?php echo $row['rbc']; ?></td>
                        <td><?php echo $row['bacteria']; ?></td>
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
    function confirmDelete(fecalysis_id) {
        return Swal.fire({
            title: 'Delete Fecalysis Record?',
            text: 'Are you sure you want to delete this Fecalysis record? This action cannot be undone!',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#12369e',
            confirmButtonText: 'Yes, delete it!',
            cancelButtonText: 'Cancel'
        }).then((result) => {
        if (result.isConfirmed) {
            window.location.href = 'fecalysis.php?fecalysis_id=' + fecalysis_id;
        }
    });
}
</script>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
document.querySelector('form').addEventListener('submit', function(event) {
    event.preventDefault(); // Prevent form from submitting immediately

    Swal.fire({
        title: 'Processing...',
        text: 'Inserting laboratory results...',
        allowOutsideClick: false,
        showConfirmButton: false,
        willOpen: () => {
            Swal.showLoading();
        }
    });
    
    // Submit the form after showing the loading message
    setTimeout(() => {
        event.target.submit();
    }, 1000); // Adjust the timeout as needed
});
</script>

<script>
    function clearSearch() {
        document.getElementById("combinedSearchInput").value = '';
        filterFecalysis();
    }

    let canPrint, userRole, editable;

    $(document).ready(function() {
        canPrint = <?php echo $can_print ? 'true' : 'false' ?>;
        userRole = <?php echo $_SESSION['role']; ?>;
        editable = <?php echo $editable ? 'true' : 'false' ?>;
    });

    function filterFecalysis() {
        var input = document.getElementById("combinedSearchInput").value;
        console.log('Search input:', input);
        
        $.ajax({
            url: 'fetch_fecalysis.php',
            type: 'GET',
            data: { query: input },
            success: function(response) {
                console.log('Response received:', response);
                try {
                    var data = JSON.parse(response);
                    console.log('Parsed data:', data);
                    updateFecalysisTables(data);
                } catch (e) {
                    console.error('JSON parse error:', e);
                }
            },
            error: function(xhr, status, error) {
                console.error('Ajax error:', error);
            }
        });
    }

    function updateFecalysisTables(data) {
        var macroscopicTbody = $('#patientTableMacroscopic tbody');
        var microscopicTbody = $('#patientTableMicroscopic tbody');
        
        macroscopicTbody.empty();
        microscopicTbody.empty();
        
        data.forEach(function(record) {
            // Macroscopic table row
            macroscopicTbody.append(`
                <tr data-fecalysis-id="${record.fecalysis_id}">
                    <td>${record.fecalysis_id}</td>
                    <td>${record.patient_id}</td>
                    <td>${record.patient_name}</td>
                    <td>${record.age}</td>
                    <td>${record.gender}</td>
                    <td>${record.date_time}</td>
                    <td>${record.color}</td>
                    <td>${record.consistency}</td>
                    <td>${record.occult_blood}</td>
                    <td class="text-right">
                        <div class="dropdown dropdown-action">
                            <a href="#" class="action-icon dropdown-toggle" data-toggle="dropdown" aria-expanded="false">
                                <i class="fa fa-ellipsis-v"></i>
                            </a>
                            <div class="dropdown-menu dropdown-menu-right">
                                ${getActionButtons(record.fecalysis_id)}
                            </div>
                        </div>
                    </td>
                </tr>
            `);

            // Microscopic table row
            microscopicTbody.append(`
                <tr data-fecalysis-id="${record.fecalysis_id}">
                    <td>${record.fecalysis_id}</td>
                    <td>${record.patient_id}</td>
                    <td>${record.patient_name}</td>
                    <td>${record.age}</td>
                    <td>${record.gender}</td>
                    <td>${record.date_time}</td>
                    <td>${record.ova_or_parasite}</td>
                    <td>${record.yeast_cells}</td>
                    <td>${record.fat_globules}</td>
                    <td>${record.pus_cells}</td>
                    <td>${record.rbc}</td>
                    <td>${record.bacteria}</td>
                </tr>
            `);
        });
    }

    function getActionButtons(fecalysisId) {
        let buttons = '';
        
        if (canPrint) {
            buttons += `
                <form action="generate-fecalysis.php" method="get">
                    <input type="hidden" name="id" value="${fecalysisId}">
                    <div class="form-group">
                        <input type="text" class="form-control" id="filename" name="filename" placeholder="Enter File Name">
                    </div>
                    <button class="btn btn-primary btn-sm custom-btn" type="submit">
                        <i class="fa fa-file-pdf m-r-5"></i> Generate Result
                    </button>
                </form>
            `;
        }
        
        if (editable) {
            buttons += `
                <a class="dropdown-item" href="edit-fecalysis.php?id=${fecalysisId}">
                    <i class="fa fa-pencil m-r-5"></i> Insert and Edit
                </a>
                <a class="dropdown-item" href="fecalysis.php?ids=${fecalysisId}" onclick="return confirmDelete()">
                    <i class="fa fa-trash m-r-5"></i> Delete
                </a>
            `;
        } else {
            buttons += `
                <a class="dropdown-item disabled" href="#">
                    <i class="fa fa-pencil m-r-5"></i> Edit
                </a>
                <a class="dropdown-item disabled" href="#">
                    <i class="fa fa-trash m-r-5"></i> Delete
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
            url: "search-fecalysis.php",
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
    #patientTableMicroscopic_length {
        display: none;
    }

    #patientTableMacroscopic_paginate .paginate_button {
        display: none;
    }
</style>

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
.dropdown-action .action-icon {
    color: #777;
    font-size: 18px;
    display: inline-block;
    padding: 0 10px;
}
.custom-btn {
    padding: 5px 27px; /* Adjust padding as needed */
    font-size: 12px; /* Adjust font size as needed */
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

