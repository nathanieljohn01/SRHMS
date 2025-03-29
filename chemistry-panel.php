<?php
session_start();
ob_start();
if (empty($_SESSION['name'])) {
    header('location:index.php');
    exit;
}
include('header.php');
include('includes/connection.php');

if ($_SESSION['role'] == 1) {
    $editable = true;
} else {
    $editable = false;
}

$can_print = ($_SESSION['role'] == 5);

function sanitize($connection, $input) {
    return mysqli_real_escape_string($connection, htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8'));
}

$role = isset($_SESSION['role']) ? $_SESSION['role'] : null;

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['patientId'])) {
    $patientId = sanitize($connection, $_POST['patientId']);

    $patient_query = $connection->prepare("SELECT * FROM tbl_laborder WHERE id = ?");
    $patient_query->bind_param("s", $patientId);
    $patient_query->execute();
    $patient_result = $patient_query->get_result();
    $patient = $patient_result->fetch_array(MYSQLI_ASSOC);

    if ($patient) {
        $patient_id = $patient['patient_id'];
        $name = $patient['patient_name'];
        $gender = $patient['gender'];
        $dob = $patient['dob'];
    
        $last_chem_query = $connection->prepare("SELECT chem_id FROM tbl_chemistry ORDER BY id DESC LIMIT 1");
        $last_chem_query->execute();
        $last_chem_result = $last_chem_query->get_result();
        $last_chem = $last_chem_result->fetch_array(MYSQLI_ASSOC);
    
        if ($last_chem) {
            $last_id_number = (int) substr($last_chem['chem_id'], 5);
            $new_chem_id = 'CHEM-' . ($last_id_number + 1);
        } else {
            $new_chem_id = 'CHEM-1';
        }
    
        $chem_id = $new_chem_id;
    
        // Chemistry Panel Fields
        $fbs = sanitize($connection, $_POST['fbs'] ?? NULL);
        $ppbs = sanitize($connection, $_POST['ppbs'] ?? NULL);
        $bun = sanitize($connection, $_POST['bun'] ?? NULL);
        $crea = sanitize($connection, $_POST['crea'] ?? NULL);
        $bua = sanitize($connection, $_POST['bua'] ?? NULL);
        $tc = sanitize($connection, $_POST['tc'] ?? NULL);
        $tg = sanitize($connection, $_POST['tg'] ?? NULL);
        $hdl = sanitize($connection, $_POST['hdl'] ?? NULL);
        $ldl = sanitize($connection, $_POST['ldl'] ?? NULL);
        $vldl = sanitize($connection, $_POST['vldl'] ?? NULL);
        $ast = sanitize($connection, $_POST['ast'] ?? NULL);
        $alt = sanitize($connection, $_POST['alt'] ?? NULL);
        $alp = sanitize($connection, $_POST['alp'] ?? NULL);
        $remarks = sanitize($connection, $_POST['remarks'] ?? NULL);
    
        $insert_query = $connection->prepare("INSERT INTO tbl_chemistry (
            chem_id, patient_id, patient_name, gender, dob, 
            fbs, ppbs, bun, crea, bua, 
            tc, tg, hdl, ldl, vldl, 
            ast, alt, alp, remarks, date_time
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
        
        $insert_query->bind_param("ssssssssssssssssssss", 
            $chem_id, $patient_id, $name, $gender, $dob,
            $fbs, $ppbs, $bun, $crea, $bua,
            $tc, $tg, $hdl, $ldl, $vldl,
            $ast, $alt, $alp, $remarks
        );
    
        if ($insert_query->execute()) {
            echo "<script>
                Swal.fire({
                    title: 'Processing...',
                    text: 'Saving Chemistry Panel record...',
                    allowOutsideClick: false,
                    showConfirmButton: false,
                    willOpen: () => {
                        Swal.showLoading();
                    }
                });
        
                setTimeout(() => {
                    Swal.fire({
                        title: 'Success!',
                        text: 'Chemistry Panel record added successfully.',
                        icon: 'success',
                        confirmButtonColor: '#12369e'
                    }).then(() => {
                        window.location.href = 'chemistrypanel.php';
                    });
                }, 1000);
            </script>";
        } else {
            echo "<script>
                Swal.fire({
                    title: 'Error!',
                    text: 'Failed to add Chemistry Panel record. Please try again.',
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

ob_end_flush();
?>

<div class="page-wrapper">
    <div class="content">
        <div class="row">
            <div class="col-sm-4 col-3">
                <h4 class="page-title">Chemistry Panel</h4>
            </div>
            <?php if ($role == 1 || $role == 5): ?>
                <div class="col-sm-10 col-9 m-b-20">
                    <form method="POST" action="chemistry-panel.php" id="addPatientForm" class="form-inline">
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
            <h5 class="font-weight-bold mb-2">Search Patient:</h5>
            <div class="input-group mb-3">
                <div class="position-relative w-100">
                    <i class="fa fa-search position-absolute text-secondary" style="top: 50%; left: 12px; transform: translateY(-50%);"></i>
                    <input class="form-control" type="text" id="chemSearchInput" onkeyup="filterChemistry()" placeholder="Search" style="padding-left: 35px; padding-right: 35px;">
                    <button class="position-absolute border-0 bg-transparent text-secondary" type="button" onclick="clearSearch()" style="top: 50%; right: 10px; transform: translateY(-50%);">
                        <i class="fa fa-times"></i>
                    </button>
                </div>
            </div>
        </div>
        <div class="table-responsive">
            <table class="datatable table table-bordered table-hover" id="chemTable">
                <thead style="background-color: #CCCCCC;">
                    <tr>
                        <th>Chem ID</th>
                        <th>Patient ID</th>
                        <th>Patient Name</th>
                        <th>Gender</th>
                        <th>Age</th>
                        <th>Date and Time</th>
                        <th>FBS</th>
                        <th>2HR PPBS</th>
                        <th>BUN</th>
                        <th>CREA</th>
                        <th>BUA</th>
                        <th>TC</th>
                        <th>TG</th>
                        <th>HDL-C</th>
                        <th>LDL-C</th>
                        <th>VLDL</th>
                        <th>AST/SGOT</th>
                        <th>ALT/SGPT</th>
                        <th>ALP</th>
                        <th>Remarks</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    if (isset($_GET['chem_id'])) {
                        $chem_id = sanitize($connection, $_GET['chem_id']);
                        $update_query = $connection->prepare("UPDATE tbl_chemistry SET deleted = 1 WHERE chem_id = ?");
                        $update_query->bind_param("s", $chem_id);
                        $update_query->execute();
                        echo "<script>showSuccess('Record deleted successfully', true);</script>";
                    }

                    $fetch_query = mysqli_query($connection, "SELECT * FROM tbl_chemistry WHERE deleted = 0 ORDER BY date_time ASC");
                    while ($row = mysqli_fetch_array($fetch_query)) {
                        $dob = $row['dob'];
                        $date = str_replace('/', '-', $dob);
                        $dob = date('Y-m-d', strtotime($date));
                        $year = (date('Y') - date('Y', strtotime($dob)));
                        $date_time = date('F d, Y g:i A', strtotime($row['date_time']));
                    ?>
                    <tr>
                        <td><?php echo $row['chem_id']; ?></td>
                        <td><?php echo $row['patient_id']; ?></td>
                        <td><?php echo $row['patient_name']; ?></td>
                        <td><?php echo $row['gender']; ?></td>
                        <td><?php echo $year; ?></td>
                        <td><?php echo $date_time; ?></td>
                        <td><?php echo $row['fbs']; ?></td>
                        <td><?php echo $row['ppbs']; ?></td>
                        <td><?php echo $row['bun']; ?></td>
                        <td><?php echo $row['crea']; ?></td>
                        <td><?php echo $row['bua']; ?></td>
                        <td><?php echo $row['tc']; ?></td>
                        <td><?php echo $row['tg']; ?></td>
                        <td><?php echo $row['hdl']; ?></td>
                        <td><?php echo $row['ldl']; ?></td>
                        <td><?php echo $row['vldl']; ?></td>
                        <td><?php echo $row['ast']; ?></td>
                        <td><?php echo $row['alt']; ?></td>
                        <td><?php echo $row['alp']; ?></td>
                        <td><?php echo $row['remarks']; ?></td>
                        <td class="text-right">
                            <div class="dropdown dropdown-action">
                                <a href="#" class="action-icon dropdown-toggle" data-toggle="dropdown" aria-expanded="false"><i class="fa fa-ellipsis-v"></i></a>
                                <div class="dropdown-menu dropdown-menu-right">
                                    <?php if ($can_print): ?>
                                    <form action="generate-chemistry.php" method="get">
                                        <input type="hidden" name="id" value="<?php echo $row['chem_id']; ?>">
                                        <div class="form-group">
                                            <input type="text" class="form-control" id="filename" name="filename" placeholder="Enter File Name">
                                        </div>
                                        <button class="btn btn-primary btn-sm custom-btn" type="submit"><i class="fa fa-file-pdf-o m-r-5"></i> Generate Result</button>
                                    </form>
                                    <?php endif; ?>
                                    <?php if ($editable): ?>
                                        <a class="dropdown-item" href="edit-chemistry-panel.php?id=<?php echo $row['chem_id']; ?>"><i class="fa fa-pencil m-r-5"></i> Insert and Edit</a>
                                        <a class="dropdown-item" href="#" onclick="return confirmDelete('<?php echo $row['chem_id']; ?>')"><i class="fa fa-trash-o m-r-5"></i> Delete</a>
                                    <?php else: ?>
                                        <a class="dropdown-item disabled" href="#">
                                            <i class="fa fa-pencil m-r-5"></i> Edit
                                        </a>
                                        <a class="dropdown-item disabled" href="#">
                                            <i class="fa fa-trash-o m-r-5"></i> Delete
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
<?php
include('footer.php');
?>

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
</script>

<script language="JavaScript" type="text/javascript">
function confirmDelete(chem_id) {
    return Swal.fire({
        title: 'Delete Chemistry Panel Record?',
        text: 'Are you sure you want to delete this Chemistry Panel record? This action cannot be undone!',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#12369e',
        confirmButtonText: 'Yes, delete it!'
    }).then((result) => {
        if (result.isConfirmed) {
            window.location.href = 'chemistrypanel.php?chem_id=' + chem_id;
        }
    });
}

function clearSearch() {
    document.getElementById("chemSearchInput").value = '';
    filterChemistry();
}

let canPrint, userRole, editable;

$(document).ready(function() {
    canPrint = <?php echo $can_print ? 'true' : 'false' ?>;
    userRole = <?php echo $_SESSION['role']; ?>;
    editable = <?php echo $editable ? 'true' : 'false' ?>;
});

function filterChemistry() {
    var input = document.getElementById("chemSearchInput").value;
    
    $.ajax({
        url: 'fetch_chemistry-panel.php',
        type: 'GET',
        data: { query: input },
        success: function(response) {
            var data = JSON.parse(response);
            updateChemistryTable(data);
        },
        error: function(xhr, status, error) {
            alert('Error fetching data. Please try again.');
        }
    });
}

function updateChemistryTable(data) {
    var tbody = $('#chemTable tbody');
    tbody.empty();
    data.forEach(function(record) {
        tbody.append(`
            <tr>
                <td>${record.chem_id}</td>  
                <td>${record.patient_id}</td>
                <td>${record.patient_name}</td>
                <td>${record.gender}</td>
                <td>${record.age}</td>
                <td>${record.date_time}</td>
                <td>${record.fbs}</td>
                <td>${record.ppbs}</td>
                <td>${record.bun}</td>
                <td>${record.crea}</td>
                <td>${record.bua}</td>
                <td>${record.tc}</td>
                <td>${record.tg}</td>
                <td>${record.hdl}</td>
                <td>${record.ldl}</td>
                <td>${record.vldl}</td>
                <td>${record.ast}</td>
                <td>${record.alt}</td>
                <td>${record.alp}</td>
                <td>${record.remarks}</td>
                <td class="text-right">
                    <div class="dropdown dropdown-action">
                        <a href="#" class="action-icon dropdown-toggle" data-toggle="dropdown" aria-expanded="false">
                            <i class="fa fa-ellipsis-v"></i>
                        </a>
                        <div class="dropdown-menu dropdown-menu-right">
                            ${getActionButtons(record.chem_id)}
                        </div>
                    </div>
                </td>
            </tr>
        `);
    });
}

function getActionButtons(chemId) {
    let buttons = '';
    
    if (canPrint) {
        buttons += `
            <form action="generate-chemistry.php" method="get">
                <input type="hidden" name="id" value="${chemId}">
                <div class="form-group">
                    <input type="text" class="form-control" id="filename" name="filename" placeholder="Enter File Name">
                </div>
                <button class="btn btn-primary btn-sm custom-btn" type="submit">
                    <i class="fa fa-file-pdf-o m-r-5"></i> Generate Result
                </button>
            </form>
        `;
    }
    
    if (editable) {
        buttons += `
            <a class="dropdown-item" href="edit-chemistry.php?id=${chemId}">
                <i class="fa fa-pencil m-r-5"></i> Insert and Edit
            </a>
            <a class="dropdown-item" href="#" onclick="return confirmDelete('${chemId}')">
                <i class="fa fa-trash-o m-r-5"></i> Delete
            </a>
        `;
    } else {
        buttons += `
            <a class="dropdown-item disabled" href="#">
                <i class="fa fa-pencil m-r-5"></i> Edit
            </a>
            <a class="dropdown-item disabled" href="#">
                <i class="fa fa-trash-o m-r-5"></i> Delete
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
        url: "search-chemistry-panel.php",
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
    if (!isVisible) {
        $el.stop(true, true).slideDown('400');
    }
    $(document).on('click', function (e) {
        if (!$(e.target).closest('.dropdown').length) {
            $('.dropdown-menu').slideUp('400');
        }
    });
});
</script>

<style>
.dropdown-action .dropdown-menu {
    position: absolute;
    left: -100px;
    min-width: 80px;
    margin-top: -14px;
    border-radius: 4px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.15);
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
</style>