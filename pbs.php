<?php
session_start();
ob_start();
if (empty($_SESSION['name'])) {
    header('location:index.php');
    exit;
}
include('header.php');
include('includes/connection.php');

$editable = ($_SESSION['role'] == 1);
$can_print = ($_SESSION['role'] == 5);

function sanitize($connection, $input) {
    return mysqli_real_escape_string($connection, htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8'));
}

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['patientId'])) {
    $patientId = sanitize($connection, $_POST['patientId']);
    
    $patient_query = $connection->prepare("SELECT * FROM tbl_laborder WHERE id = ?");
    $patient_query->bind_param("s", $patientId);
    $patient_query->execute();
    $patient_result = $patient_query->get_result();
    $patient = $patient_result->fetch_array(MYSQLI_ASSOC);

    if ($patient) {
        // Generate new PBS ID
        $last_pbs = $connection->query("SELECT pbs_id FROM tbl_pbs ORDER BY id DESC LIMIT 1")->fetch_assoc();
        $new_pbs_id = $last_pbs ? 'PBS-'.(intval(substr($last_pbs['pbs_id'], 4)) + 1) : 'PBS-1';

        $rbc_morphology = sanitize($connection, $_POST['rbc_morphology'] ?? NULL);
        $platelet_count = sanitize($connection, $_POST['platelet_count'] ?? NULL);
        $toxic_granules = sanitize($connection, $_POST['toxic_granules'] ?? NULL);
        $abnormal_cells = sanitize($connection, $_POST['abnormal_cells'] ?? NULL);
        $segmenters = sanitize($connection, $_POST['segmenters'] ?? NULL);  // Note: Check spelling (segments vs segements)
        $lymphocytes = sanitize($connection, $_POST['lymphocytes'] ?? NULL);
        $monocytes = sanitize($connection, $_POST['monocytes'] ?? NULL);
        $eosinophils = sanitize($connection, $_POST['eosinophils'] ?? NULL);
        $bands = sanitize($connection, $_POST['bands'] ?? NULL);
        $reticulocyte_count = sanitize($connection, $_POST['reticulocyte_count'] ?? NULL);
        $remarks = sanitize($connection, $_POST['remarks'] ?? NULL);
        // Insert data
        $insert_query = $connection->prepare("INSERT INTO tbl_pbs (
            pbs_id, patient_id, patient_name, gender, dob, 
            rbc_morphology, platelet_count, toxic_granules, abnormal_cells,
            segmenters, lymphocytes, monocytes, eosinophils, bands, 
            reticulocyte_count, remarks, date_time
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");

        $insert_query->bind_param("ssssssssssssssss",  
            $new_pbs_id,
            $patient['patient_id'],
            $patient['patient_name'],
            $patient['gender'],
            $patient['dob'],
            $rbc_morphology,
            $platelet_count,
            $toxic_granules,
            $abnormal_cells,
            $segmenters,
            $lymphocytes,
            $monocytes,
            $eosinophils,
            $bands,
            $reticulocyte_count,
            $remarks
        );

        if ($insert_query->execute()) {
            echo "<script>
                Swal.fire({
                    title: 'Processing...',
                    text: 'Saving PBS record...',
                    allowOutsideClick: false,
                    showConfirmButton: false,
                    willOpen: () => {
                        Swal.showLoading();
                    }
                });
                
                setTimeout(() => {
                    Swal.fire({
                        title: 'Success!',
                        text: 'PBS record added successfully.',
                        icon: 'success',
                        confirmButtonColor: '#12369e'
                    }).then(() => {
                        window.location.href = 'pbs.php';
                    });
                }, 1000);
            </script>";
        } else {
            echo "<script>
                Swal.fire({
                    title: 'Error!',
                    text: 'Failed to add PBS record.',
                    icon: 'error',
                    confirmButtonColor: '#d33'
                });
            </script>";
        }
    } else {
        echo "<script>
            Swal.fire({
                title: 'Error!',
                text: 'Patient not found. Please try again.',
                icon: 'error',
                confirmButtonColor: '#12369e',
                confirmButtonText: 'OK'
            });
        </script>";
    }
}

// Handle deletion
if (isset($_GET['delete_id'])) {
    $pbs_id = sanitize($connection, $_GET['delete_id']);
    $update_query = $connection->prepare("UPDATE tbl_pbs SET deleted = 1 WHERE pbs_id = ?");
    $update_query->bind_param("s", $pbs_id);
    if ($update_query->execute()) {
        echo "<script>
            Swal.fire({
                title: 'Success!',
                text: 'PBS record deleted successfully.',
                icon: 'success',
                confirmButtonColor: '#12369e'
            }).then(() => {
                window.location.href = 'pbs.php';
            });
        </script>";
    }
}
?>

<div class="page-wrapper">
    <div class="content">
        <div class="row">
            <div class="col-sm-4 col-3">
                <h4 class="page-title">Peripheral Blood Smear</h4>
            </div>
            <?php if ($_SESSION['role'] == 1 || $_SESSION['role'] == 5): ?>
            <div class="col-sm-10 col-9 m-b-20">
                <form method="POST" action="pbs.php" id="addPatientForm" class="form-inline">
                    <div class="input-group w-50">
                        <div class="input-group-prepend">
                            <span class="input-group-text">
                                <i class="fas fa-search"></i> 
                            </span>
                        </div>
                        <input type="text" class="form-control search-input" id="patientSearchInput" 
                               name="patientSearchInput" placeholder="Enter Patient" onkeyup="searchPatients()">
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
            <h5 class="font-weight-bold mb-2">Search Patient:</h5>
            <div class="input-group mb-3">
                <div class="position-relative w-100">
                    <i class="fa fa-search position-absolute text-secondary" style="top: 50%; left: 12px; transform: translateY(-50%);"></i>
                    <input class="form-control" type="text" id="pbsSearchInput" onkeyup="filterPbs()" placeholder="Search" style="padding-left: 35px; padding-right: 35px;">
                    <button class="position-absolute border-0 bg-transparent text-secondary" type="button" onclick="clearSearch()" style="top: 50%; right: 10px; transform: translateY(-50%);">
                        <i class="fa fa-times"></i>
                    </button>
                </div>
            </div>
        </div>
        
        <div class="table-responsive">
            <table class="datatable table table-bordered table-hover" id="pbsTable">
                <thead style="background-color: #CCCCCC;">
                    <tr>
                        <th>PBS ID</th>
                        <th>Patient ID</th>
                        <th>Patient Name</th>
                        <th>Gender</th>
                        <th>Age</th>
                        <th>Date and Time</th>
                        <th>RBC</th>
                        <th>Platelet</th>
                        <th>Toxic Granules</th>
                        <th>Abnormal Cells</th>
                        <th>Segmenters</th>
                        <th>Lymphocytes</th>
                        <th>Monocytes</th>
                        <th>Eosinophils</th>
                        <th>Bands</th>
                        <th>Reticulocyte Count</th>
                        <th>Remarks</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $fetch_query = mysqli_query($connection, "SELECT * FROM tbl_pbs WHERE deleted = 0 ORDER BY date_time DESC");
                    while ($row = mysqli_fetch_array($fetch_query)) {
                        $dob = $row['dob'];
                        $date = str_replace('/', '-', $dob);
                        $dob = date('Y-m-d', strtotime($date));
                        $year = (date('Y') - date('Y', strtotime($dob)));
                        $date_time = date('F d, Y g:i A', strtotime($row['date_time']));
                    ?>
                    <tr>
                        <td><?php echo $row['pbs_id']; ?></td>
                        <td><?php echo $row['patient_id']; ?></td>
                        <td><?php echo $row['patient_name']; ?></td>
                        <td><?php echo $row['gender']; ?></td>
                        <td><?php echo $year; ?></td>
                        <td><?php echo $date_time; ?></td>
                        <td><?php echo $row['rbc_morphology'] ?: 'N/A'; ?></td>
                        <td><?php echo $row['platelet_count'] ?: 'N/A'; ?></td>
                        <td><?php echo $row['toxic_granules'] ?: 'N/A'; ?></td>
                        <td><?php echo $row['abnormal_cells'] ?: 'N/A'; ?></td>
                        <td><?php echo $row['segmenters'] ?: 'N/A'; ?></td>
                        <td><?php echo $row['lymphocytes'] ?: 'N/A'; ?></td>
                        <td><?php echo $row['monocytes'] ?: 'N/A'; ?></td>
                        <td><?php echo $row['eosinophils'] ?: 'N/A'; ?></td>
                        <td><?php echo $row['bands'] ?: 'N/A'; ?></td>
                        <td><?php echo $row['reticulocyte_count'] ?: 'N/A'; ?></td>
                        <td><?php echo $row['remarks'] ?: 'N/A'; ?></td>
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
                                        <form action="generate-pbs.php" method="get" class="p-2">
                                            <input type="hidden" name="id" value="<?php echo $row['pbs_id']; ?>">
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
                                    <a class="dropdown-item" href="edit-pbs.php?id=<?php echo $row['pbs_id']; ?>"><i class="fa fa-pencil m-r-5"></i>Insert and Edit</a>
                                    <?php if ($editable): ?>
                                        <a class="dropdown-item" href="#" onclick="return confirmDelete('<?php echo $row['pbs_id']; ?>')"><i class="fa fa-trash m-r-5"></i> Delete</a>
                                    <?php else: ?>
                                        <a class="dropdown-item disabled" href="#">
                                            <i class="fa fa-trash m-r-5"></i> Delete
                                        </a>
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

<?php include('footer.php'); ?>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
document.querySelector('form').addEventListener('submit', function(event) {
    event.preventDefault();
    Swal.fire({
        title: 'Processing...',
        text: 'Inserting laboratory results...',
        allowOutsideClick: false,
        showConfirmButton: false,
        willOpen: () => { Swal.showLoading(); }
    });
    setTimeout(() => { event.target.submit(); }, 1000);
});

function confirmDelete(pbs_id) {
    return Swal.fire({
        title: 'Delete PBS Record?',
        text: 'Are you sure you want to delete this PBS record? This action cannot be undone!',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#12369e',
        confirmButtonText: 'Yes, delete it!'
    }).then((result) => {
        if (result.isConfirmed) {
            window.location.href = 'pbs.php?delete_id=' + pbs_id;
        }
    });
}

function clearSearch() {
    document.getElementById("pbsSearchInput").value = '';
    filterPbs();
}

let canPrint = <?php echo $can_print ? 'true' : 'false'; ?>;
let editable = <?php echo $editable ? 'true' : 'false'; ?>;

function filterPbs() {
    var input = document.getElementById("pbsSearchInput").value;
    console.log('Searching for:', input); // Debug log

    $.ajax({
        url: 'fetch_pbs.php',
        type: 'GET',
        data: { query: input },
        success: function(response) {
            console.log('Response received:', response); // Debug log
            Swal.close();
            
            try {
                if (typeof response === 'string') {
                    response = JSON.parse(response);
                }

                if (response.error) {
                    throw new Error(response.error);
                }

                updatePbsTable(response);
            } catch (e) {
                console.error('Error:', e);
                Swal.fire({
                    title: 'Error',
                    text: 'Failed to process search results: ' + e.message,
                    icon: 'error',
                    confirmButtonColor: '#12369e'
                });
            }
        },
        error: function(xhr, status, error) {
            console.error('AJAX Error:', status, error);
            Swal.fire({
                title: 'Error',
                text: 'Failed to fetch data from server',
                icon: 'error',
                confirmButtonColor: '#12369e'
            });
        }
    });
}

function updatePbsTable(data) {
    var tbody = $('#pbsTable tbody');
    tbody.empty();

    if (!Array.isArray(data) || data.length === 0) {
        tbody.append(`
            <tr>
                <td colspan="18" class="text-center">No records found</td>
            </tr>
        `);
        return;
    }

    data.forEach(function(record) {
        tbody.append(`
            <tr>
                <td>${record.pbs_id || 'N/A'}</td>
                <td>${record.patient_id || 'N/A'}</td>
                <td>${record.patient_name || 'N/A'}</td>
                <td>${record.gender || 'N/A'}</td>
                <td>${record.age || 'N/A'}</td>
                <td>${record.date_time || 'N/A'}</td>
                <td>${record.rbc_morphology || 'N/A'}</td>
                <td>${record.platelet_count || 'N/A'}</td>
                <td>${record.toxic_granules || 'N/A'}</td>
                <td>${record.abnormal_cells || 'N/A'}</td>
                <td>${record.segmenters || 'N/A'}</td>
                <td>${record.lymphocytes || 'N/A'}</td>
                <td>${record.monocytes || 'N/A'}</td>
                <td>${record.eosinophils || 'N/A'}</td>
                <td>${record.bands || 'N/A'}</td>
                <td>${record.reticulocyte_count || 'N/A'}</td>
                <td>${record.remarks || 'N/A'}</td>
                <td class="text-right">
                    <div class="dropdown dropdown-action">
                        <a href="#" class="action-icon dropdown-toggle" data-toggle="dropdown" aria-expanded="false">
                            <i class="fa fa-ellipsis-v"></i>
                        </a>
                        <div class="dropdown-menu dropdown-menu-right" style="min-width: 200px; position: absolute; top: 50%; transform: translateY(-50%); right: 50%;">
                            ${canPrint ? `
                                <div class="dropdown-item">
                                    <form action="generate-pbs.php" method="get" class="p-2">
                                        <input type="hidden" name="id" value="${record.pbs_id}">
                                        <div class="form-group mb-2">
                                            <input type="text" class="form-control" name="filename" placeholder="Filename (required)" required>
                                        </div>
                                        <button class="btn btn-primary btn-sm custom-btn" type="submit">
                                            <i class="fa fa-file-pdf m-r-5"></i> Generate PDF
                                        </button>
                                    </form>
                                </div>
                                <div class="dropdown-divider"></div>
                            ` : ''}
                            <a class="dropdown-item" href="edit-pbs.php?id=${record.pbs_id}">
                                <i class="fa fa-pencil m-r-5"></i> Insert and Edit
                            </a>
                            ${editable ? `
                                <a class="dropdown-item" href="#" onclick="return confirmDelete('${record.pbs_id}')">
                                    <i class="fa fa-trash m-r-5"></i> Delete
                                </a>
                            ` : `
                                <a class="dropdown-item disabled" href="#">
                                    <i class="fa fa-trash m-r-5"></i> Delete
                                </a>
                            `}
                        </div>
                    </div>
                </td>
            </tr>
        `);
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
        url: "search-pbs.php",
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
    $('.dropdown-menu').slideUp('400');
    if (!isVisible) $el.stop(true, true).slideDown('400');
    $(document).on('click', function (e) {
        if (!$(e.target).closest('.dropdown').length) {
            $('.dropdown-menu').slideUp('400');
        }
    });
});
</script>

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
#patientTable2_length, #patientTable_paginate .paginate_button {
    display: none;
}
.dropdown-action .action-icon {
    color: #777;
    font-size: 18px;
    display: inline-block;
    padding: 0 10px;
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
.custom-btn {
    padding: 5px 27px; /* Adjust padding as needed */
    font-size: 12px; /* Adjust font size as needed */
}
</style>