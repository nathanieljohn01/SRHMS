<?php
session_start();
ob_start(); // Start output buffering
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

// Function to sanitize inputs
function sanitize($connection, $input) {
    return mysqli_real_escape_string($connection, htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8'));
}

$role = isset($_SESSION['role']) ? $_SESSION['role'] : null;

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['patientId'])) {
    // Sanitize the patient ID input
    $patientId = sanitize($connection, $_POST['patientId']);

    // Prepare and bind the query for fetching patient details
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

        // Fetch the last Electrolytes ID and generate a new one
        $last_query = $connection->prepare("SELECT electrolytes_id FROM tbl_electrolytes ORDER BY id DESC LIMIT 1");
        $last_query->execute();
        $last_result = $last_query->get_result();
        $last_entry = $last_result->fetch_array(MYSQLI_ASSOC);

        if ($last_entry) {
            $last_id_number = (int) substr($last_entry['electrolytes_id'], 4); 
            $new_id = 'ELEC-' . ($last_id_number + 1);
        } else {
            $new_id = 'ELEC-1';  
        }

        // Assign the generated ID to $electrolytes_id
        $electrolytes_id = $new_id;

        // Sanitize user inputs and set NULL if empty
        $sodium  = sanitize($connection, $_POST['sodium'] ?? NULL);
        $potassium = sanitize($connection, $_POST['potassium'] ?? NULL);
        $chloride = sanitize($connection, $_POST['chloride'] ?? NULL);
        $calcium = sanitize($connection, $_POST['calcium'] ?? NULL);

        // Prepare the query to insert with NULL values for empty fields
        $insert_query = $connection->prepare("INSERT INTO tbl_electrolytes (electrolytes_id, patient_id, patient_name, gender, dob, sodium, potassium, chloride, calcium, date_time) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
        
        // Bind parameters
        $insert_query->bind_param("sssssssss", 
            $electrolytes_id, $patient_id, $name, $gender, $dob,
            $sodium, $potassium, $chloride, $calcium
        );

        // Execute the query
        if ($insert_query->execute()) {
            echo "<script>
                Swal.fire({
                    title: 'Processing...',
                    text: 'Saving Electrolytes record...',
                    allowOutsideClick: false, 
                    showConfirmButton: false,
                    willOpen: () => {
                        Swal.showLoading();
                    }
                });

                setTimeout(() => {
                    Swal.fire({
                        title: 'Success!',
                        text: 'Electrolytes record added successfully.',
                        icon: 'success',
                        confirmButtonColor: '#12369e'
                    }).then(() => {
                        window.location.href = 'electrolytes.php';
                    });
                }, 1000);
            </script>";
        } else {
            echo "<script>
                Swal.fire({
                    title: 'Error!', 
                    text: 'Failed to add Electrolytes record. Please try again.',
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

ob_end_flush(); // Flush output buffer
?>

<div class="page-wrapper">
    <div class="content">
        <div class="row">
            <div class="col-sm-4 col-3">
                <h4 class="page-title">Electrolytes</h4>
            </div>
            <?php if ($role == 1 || $role == 5): ?>
                <div class="col-sm-10 col-9 m-b-20">
                    <form method="POST" action="electrolytes.php" id="addPatientForm" class="form-inline">
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
                    <input class="form-control" type="text" id="electrolytesSearchInput" onkeyup="filterElectrolytes()" placeholder="Search" style="padding-left: 35px; padding-right: 35px;">
                    <button class="position-absolute border-0 bg-transparent text-secondary" type="button" onclick="clearSearch()" style="top: 50%; right: 10px; transform: translateY(-50%);">
                        <i class="fa fa-times"></i>
                    </button>
                </div>
            </div>
        </div>
        <div class="table-responsive">
            <table class="datatable table table-bordered table-hover" id="electrolytesTable">
                <thead style="background-color: #CCCCCC;">
                    <tr>
                        <th>Electrolytes ID</th>
                        <th>Patient ID</th>
                        <th>Patient Name</th>
                        <th>Gender</th>
                        <th>Age</th>
                        <th>Date and Time</th>
                        <th>Sodium</th>
                        <th>Potassium</th>
                        <th>Chloride</th>
                        <th>Calcium</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    if (isset($_GET['electrolytes_id'])) {
                        $electrolytes_id = sanitize($connection, $_GET['electrolytes_id']);
                        $update_query = $connection->prepare("UPDATE tbl_electrolytes SET deleted = 1 WHERE electrolytes_id = ?");
                        $update_query->bind_param("s", $electrolytes_id);
                        $update_query->execute();
                        echo "<script>showSuccess('Record deleted successfully', true);</script>";
                    }
                    
                    $fetch_query = mysqli_query($connection, "SELECT * FROM tbl_electrolytes WHERE deleted = 0 ORDER BY date_time ASC");
                    while ($row = mysqli_fetch_array($fetch_query)) {
                        $dob = $row['dob'];
                        $date = str_replace('/', '-', $dob);
                        $dob = date('Y-m-d', strtotime($date));
                        $year = (date('Y') - date('Y', strtotime($dob)));
                        $date_time = date('F d, Y g:i A', strtotime($row['date_time']));

                        ?>
                        <tr>
                            <td><?php echo $row['electrolytes_id']; ?></td>
                            <td><?php echo $row['patient_id']; ?></td>
                            <td><?php echo $row['patient_name']; ?></td>
                            <td><?php echo $row['gender']; ?></td>
                            <td><?php echo $year; ?></td>
                            <td><?php echo date('F d, Y g:i A', strtotime($row['date_time'])); ?></td>
                            <td><?php echo $row['sodium']; ?></td>
                            <td><?php echo $row['potassium']; ?></td>
                            <td><?php echo $row['chloride']; ?></td>
                            <td><?php echo $row['calcium']; ?></td>
                            <td class="text-right">
                                <div class="dropdown dropdown-action">
                                    <a href="#" class="action-icon dropdown-toggle" data-toggle="dropdown" aria-expanded="false"><i class="fa fa-ellipsis-v"></i></a>
                                    <div class="dropdown-menu dropdown-menu-right">
                                        <?php if ($can_print): ?>
                                        <form action="generate-electrolytes.php" method="get">
                                            <input type="hidden" name="id" value="<?php echo $row['electrolytes_id']; ?>">
                                            <button class="btn btn-primary btn-sm" type="submit"><i class="fa fa-file-pdf-o m-r-5"></i> Generate Result</button>
                                        </form>
                                        <?php endif; ?>
                                        <?php if ($editable): ?>
                                            <a class="dropdown-item" href="edit-electrolytes.php?id=<?php echo $row['electrolytes_id']; ?>"><i class="fa fa-pencil m-r-5"></i> Insert and Edit</a>
                                            <a class="dropdown-item" href="#" onclick="return confirmDelete('<?php echo $row['electrolytes_id']; ?>')"><i class="fa fa-trash-o m-r-5"></i> Delete</a>
                                        <?php else: ?>
                                            <a class="dropdown-item disabled" href="#"><i class="fa fa-pencil m-r-5"></i> Insert and Edit</a>
                                            <a class="dropdown-item disabled" href="#"><i class="fa fa-trash-o m-r-5"></i> Delete</a>
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
function searchPatients() {
    var input = document.getElementById("patientSearchInput").value;
    if (input.length < 2) {
        document.getElementById("searchResults").style.display = "none";
        document.getElementById("searchResults").innerHTML = "";
        return;
    }
    $.ajax({
        url: "search-electrolytes.php",
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

// Function to handle record deletion
function confirmDelete(electrolytes_id) {
    return Swal.fire({
        title: 'Delete Electrolytes Record?',
        text: 'Are you sure you want to delete this Electrolytes record? This action cannot be undone!',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#12369e',
        confirmButtonText: 'Yes, delete it!'
    }).then((result) => {
        if (result.isConfirmed) {
            window.location.href = 'electrolytes.php?electrolytes_id=' + electrolytes_id;
        }
    });
}

function clearSearch() {
    document.getElementById("electrolytesSearchInput").value = '';
    filterElectrolytes();
}

let canPrint, userRole, editable;

$(document).ready(function() {
    canPrint = <?php echo $can_print ? 'true' : 'false' ?>;
    userRole = <?php echo $_SESSION['role']; ?>;
    editable = <?php echo $editable ? 'true' : 'false' ?>;
});

function filterElectrolytes() {
    var input = document.getElementById("electrolytesSearchInput").value;
    $.ajax({
        url: 'fetch_electrolytes.php',
        type: 'GET',
        data: { query: input },
        success: function(response) {
            var data = JSON.parse(response);
            updateElectrolytesTable(data);
        },
        error: function() {
            alert('Error fetching data. Please try again.');
        }
    });
}

function updateElectrolytesTable(data) {
    var tbody = $('#electrolytesTable tbody');
    tbody.empty();
    data.forEach(function(record) {
        tbody.append(`
            <tr>
                <td>${record.electrolytes_id}</td>
                <td>${record.patient_id}</td>
                <td>${record.patient_name}</td>
                <td>${record.gender}</td>
                <td>${record.age}</td>
                <td>${record.date_time}</td>
                <td>${record.sodium}</td>
                <td>${record.potassium}</td>
                <td>${record.chloride}</td>
                <td>${record.calcium}</td>
                <td class="text-right">
                    <div class="dropdown dropdown-action">
                        <a href="#" class="action-icon dropdown-toggle" data-toggle="dropdown" aria-expanded="false">
                            <i class="fa fa-ellipsis-v"></i>
                        </a>
                        <div class="dropdown-menu dropdown-menu-right">
                            ${getActionButtons(record.electrolytes_id)}
                        </div>
                    </div>
                </td>
            </tr>
        `);
    });
}

function getActionButtons(electrolytesId) {
    let buttons = '';
    
    if (canPrint) {
        buttons += `
            <form action="generate-electrolytes.php" method="get">
                <input type="hidden" name="id" value="${electrolytesId}">
                <div class="form-group">
                    <input type="text" class="form-control" id="filename" name="filename" placeholder="Enter File Name" aria-label="Enter File Name" aria-describedby="basic-addon2">
                </div>
                <button class="btn btn-primary btn-sm custom-btn" type="submit">
                    <i class="fa fa-file-pdf-o m-r-5"></i> Generate Result
                </button>
            </form>
        `;
    }
    
    if (userRole === 1) {
        buttons += `
            <a class="dropdown-item" href="edit-electrolytes.php?id=${electrolytesId}">
                <i class="fa fa-pencil m-r-5"></i> Insert and Edit
            </a>
            <a class="dropdown-item" href="electrolytes.php?ids=${electrolytesId}" onclick="return confirmDelete()">
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
