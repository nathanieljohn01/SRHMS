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

function sanitize($connection, $input) {
    return mysqli_real_escape_string($connection, $input);
}

$role = isset($_SESSION['role']) ? $_SESSION['role'] : null;

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['patientId'])) {
    // Sanitize the patient ID input
    $patientId = sanitize($connection, $_POST['patientId']);

    // Prepare and bind the query
    $patient_query = $connection->prepare("SELECT * FROM tbl_laborder WHERE id = ?");
    $patient_query->bind_param("s", $patientId);  // "s" stands for string
    
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

        // Fetch the last CBC ID and generate a new one
        $last_cbc_query = $connection->prepare("SELECT cbc_id FROM tbl_cbc ORDER BY id DESC LIMIT 1");
        $last_cbc_query->execute();
        $last_cbc_result = $last_cbc_query->get_result();
        $last_cbc = $last_cbc_result->fetch_array(MYSQLI_ASSOC);

        if ($last_cbc) {
            $last_id_number = (int) substr($last_cbc['cbc_id'], 4); 
            $new_cbc_id = 'CBC-' . ($last_id_number + 1);
        } else {
            $new_cbc_id = 'CBC-1';  
        }

        // Assign the generated ID to $cbc_id
        $cbc_id = $new_cbc_id;

        // Sanitize user inputs and set NULL if empty
        $hemoglobin = !empty($_POST['hemoglobin']) ? mysqli_real_escape_string($connection, $_POST['hemoglobin']) : NULL;
        $hematocrit = !empty($_POST['hematocrit']) ? mysqli_real_escape_string($connection, $_POST['hematocrit']) : NULL;
        $red_blood_cells = !empty($_POST['red_blood_cells']) ? mysqli_real_escape_string($connection, $_POST['red_blood_cells']) : NULL;
        $white_blood_cells = !empty($_POST['white_blood_cells']) ? mysqli_real_escape_string($connection, $_POST['white_blood_cells']) : NULL;
        $esr = !empty($_POST['esr']) ? mysqli_real_escape_string($connection, $_POST['esr']) : NULL;
        $segmenters = !empty($_POST['segmenters']) ? mysqli_real_escape_string($connection, $_POST['segmenters']) : NULL;
        $lymphocytes = !empty($_POST['lymphocytes']) ? mysqli_real_escape_string($connection, $_POST['lymphocytes']) : NULL;
        $eosinophils = !empty($_POST['eosinophils']) ? mysqli_real_escape_string($connection, $_POST['eosinophils']) : NULL;
        $monocytes = !empty($_POST['monocytes']) ? mysqli_real_escape_string($connection, $_POST['monocytes']) : NULL;
        $bands = !empty($_POST['bands']) ? mysqli_real_escape_string($connection, $_POST['bands']) : NULL;
        $platelets = !empty($_POST['platelets']) ? mysqli_real_escape_string($connection, $_POST['platelets']) : NULL;

        // Prepare the query to insert with NULL values for empty fields
        $query = "
            INSERT INTO tbl_cbc (cbc_id, patient_id, patient_name, gender, dob, hemoglobin, hematocrit, red_blood_cells, white_blood_cells, esr, segmenters, lymphocytes, eosinophils, monocytes, bands, platelets, date_time) 
            VALUES ('$cbc_id', '$patient_id', '$name', '$gender', '$dob', 
                    " . ($hemoglobin !== NULL ? "'$hemoglobin'" : "NULL") . ", 
                    " . ($hematocrit !== NULL ? "'$hematocrit'" : "NULL") . ", 
                    " . ($red_blood_cells !== NULL ? "'$red_blood_cells'" : "NULL") . ", 
                    " . ($white_blood_cells !== NULL ? "'$white_blood_cells'" : "NULL") . ", 
                    " . ($esr !== NULL ? "'$esr'" : "NULL") . ", 
                    " . ($segmenters !== NULL ? "'$segmenters'" : "NULL") . ", 
                    " . ($lymphocytes !== NULL ? "'$lymphocytes'" : "NULL") . ", 
                    " . ($eosinophils !== NULL ? "'$eosinophils'" : "NULL") . ", 
                    " . ($monocytes !== NULL ? "'$monocytes'" : "NULL") . ", 
                    " . ($bands !== NULL ? "'$bands'" : "NULL") . ", 
                    " . ($platelets !== NULL ? "'$platelets'" : "NULL") . ", NOW())
        ";

        // Execute the query
        if ($connection->query($query) === TRUE) {
            echo "Record added successfully";
        } else {
            echo "Error: " . $query . "<br>" . $connection->error;
        }

        // Redirect or show a success me  ssage
        header('Location: cbc.php');
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
                <h4 class="page-title">Completed Blood Count</h4>
            </div>
            <?php if ($role == 1 || $role == 5): ?>
                <div class="col-sm-10 col-9 m-b-20">
                    <form method="POST" action="cbc.php" id="addPatientForm" class="form-inline">
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
        <input class="form-control" type="text" id="cbcSearchInput" onkeyup="filterCBC()" placeholder="Search Patient ID or Patient Name">
            <table class="datatable table table-stripped" id="cbcTable">
                <thead style="background-color: #CCCCCC;">
                    <tr>
                        <th>CBC ID</th>
                        <th>Patient ID</th>
                        <th>Patient Name</th>
                        <th>Gender</th>
                        <th>Age</th>
                        <th>Date and Time</th>
                        <th>Hemoglobin</th>
                        <th>Hematocrit</th>
                        <th>Red Blood Cells</th>
                        <th>White Blood Cells</th>
                        <th>ESR</th>
                        <th>Segmenters</th>
                        <th>Lymphocytes</th>
                        <th>Monocytes</th>
                        <th>Bands</th>
                        <th>Platelets</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    if (isset($_GET['ids'])) {
                        $id = $_GET['ids'];
                        $update_query = mysqli_query($connection, "UPDATE tbl_cbc SET deleted = 1 WHERE id='$id'");
                    }
                    $fetch_query = mysqli_query($connection, "SELECT * FROM tbl_cbc WHERE deleted = 0 ORDER BY date_time ASC");
                    while ($row = mysqli_fetch_array($fetch_query)) {
                        $dob = $row['dob'];
                        $date = str_replace('/', '-', $dob); 
                        $dob = date('Y-m-d', strtotime($date));
                        $year = (date('Y') - date('Y',strtotime($dob)));
                        $date_time = date('F d, Y g:i A', strtotime($row['date_time']));
                    ?>
                    <tr>
                        <td><?php echo $row['cbc_id']; ?></td>
                        <td><?php echo $row['patient_id']; ?></td>
                        <td><?php echo $row['patient_name']; ?></td>
                        <td><?php echo $row['gender']; ?></td>
                        <td><?php echo $year; ?></td>
                        <td><?php echo date('F d, Y g:i A', strtotime($row['date_time'])); ?></td>
                        <td><?php echo $row['hemoglobin']; ?></td>
                        <td><?php echo $row['hematocrit']; ?></td>
                        <td><?php echo $row['red_blood_cells']; ?></td>
                        <td><?php echo $row['white_blood_cells']; ?></td>
                        <td><?php echo $row['esr']; ?></td>
                        <td><?php echo $row['segmenters']; ?></td>
                        <td><?php echo $row['lymphocytes']; ?></td>
                        <td><?php echo $row['monocytes']; ?></td>
                        <td><?php echo $row['bands']; ?></td>
                        <td><?php echo $row['platelets']; ?></td>
                        <td class="text-right">
                        <div class="dropdown dropdown-action">
                                <a href="#" class="action-icon dropdown-toggle" data-toggle="dropdown" aria-expanded="false"><i class="fa fa-ellipsis-v"></i></a>
                                <div class="dropdown-menu dropdown-menu-right">
                                    <?php if ($can_print): ?>
                                    <form action="generate-cbc.php" method="get">
                                        <input type="hidden" name="id" value="<?php echo $row['cbc_id']; ?>">
                                        <div class="form-group">
                                            <input type="text" class="form-control" id="filename" name="filename" placeholder="Enter File Name" aria-label="Enter File Name" aria-describedby="basic-addon2">
                                        </div>
                                        <button class="btn btn-primary btn-sm custom-btn" type="submit"><i class="fa fa-file-pdf-o m-r-5"></i> Generate Result</button>
                                    </form>
                                    <?php endif; ?>
                                    <?php if ($editable): ?>
                                        
                                        <!-- Edit and Delete Options -->
                                        <a class="dropdown-item" href="edit-cbc.php?id=<?php echo $row['cbc_id']; ?>"><i class="fa fa-pencil m-r-5"></i> Insert and Edit</a>
                                        <a class="dropdown-item" href="cbc.php?id=<?php echo $row['cbc_id']; ?>" onclick="return confirmDelete()"><i class="fa fa-trash-o m-r-5"></i> Delete</a>
                                    <?php else: ?>
                                        <!-- Edit and Delete Options Disabled -->
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

<script language="JavaScript" type="text/javascript">
    function confirmDelete() {
        return confirm('Are you sure you want to delete this item?');
    }

    function filterCBC() {
        var input, filter, table, tr, td, i, txtValue;
        input = document.getElementById("cbcSearchInput");
        filter = input.value.toUpperCase();
        table = document.getElementById("cbcTable");
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
            url: "search-cbc.php", // Backend script to fetch patients
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

