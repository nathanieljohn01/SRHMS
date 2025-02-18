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

$editable = ($_SESSION['role'] == 1);
$can_print = ($_SESSION['role'] == 5);


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

        // Fetch the last urinalysis ID and generate a new one
        $last_ua_query = $connection->prepare("SELECT urinalysis_id FROM tbl_urinalysis ORDER BY urinalysis_id DESC LIMIT 1");
        $last_ua_query->execute();
        $last_ua_result = $last_ua_query->get_result();
        $last_ua = $last_ua_result->fetch_array(MYSQLI_ASSOC);

        if ($last_ua) {
            $last_id_number = (int) substr($last_ua['urinalysis_id'], 3);  // Extract the number after "UA-"
            $new_ua_id = 'UA-' . ($last_id_number + 1);
        } else {
            $new_ua_id = 'UA-1';  // Starting value if no previous urinalysis ID exists
        }

        // Assign the generated ID to $urinalysis_id
        $urinalysis_id = $new_ua_id;

       // Sanitize other inputs with default values if empty
        $color = !empty($_POST['color']) ? mysqli_real_escape_string($connection, $_POST['color']) : NULL;
        $transparency = !empty($_POST['transparency']) ? mysqli_real_escape_string($connection, $_POST['transparency']) : NULL;
        $reaction = !empty($_POST['reaction']) ? mysqli_real_escape_string($connection, $_POST['reaction']) : NULL;
        $protein = !empty($_POST['protein']) ? mysqli_real_escape_string($connection, $_POST['protein']) : NULL;
        $glucose = !empty($_POST['glucose']) ? mysqli_real_escape_string($connection, $_POST['glucose']) : NULL;
        $specific_gravity = !empty($_POST['specific_gravity']) ? mysqli_real_escape_string($connection, $_POST['specific_gravity']) : NULL;
        $ketone = !empty($_POST['ketone']) ? mysqli_real_escape_string($connection, $_POST['ketone']) : NULL;
        $urobilinogen = !empty($_POST['urobilinogen']) ? mysqli_real_escape_string($connection, $_POST['urobilinogen']) : NULL;
        $pregnancy_test = !empty($_POST['pregnancy_test']) ? mysqli_real_escape_string($connection, $_POST['pregnancy_test']) : NULL;

        // Microscopic results
        $pus_cells = !empty($_POST['pus_cells']) ? mysqli_real_escape_string($connection, $_POST['pus_cells']) : NULL;
        $red_blood_cells = !empty($_POST['red_blood_cells']) ? mysqli_real_escape_string($connection, $_POST['red_blood_cells']) : NULL;
        $epithelial_cells = !empty($_POST['epithelial_cells']) ? mysqli_real_escape_string($connection, $_POST['epithelial_cells']) : NULL;
        $a_urates_a_phosphates = !empty($_POST['a_urates_a_phosphates']) ? mysqli_real_escape_string($connection, $_POST['a_urates_a_phosphates']) : NULL;
        $mucus_threads = !empty($_POST['mucus_threads']) ? mysqli_real_escape_string($connection, $_POST['mucus_threads']) : NULL;
        $bacteria = !empty($_POST['bacteria']) ? mysqli_real_escape_string($connection, $_POST['bacteria']) : NULL;
        $calcium_oxalates = !empty($_POST['calcium_oxalates']) ? mysqli_real_escape_string($connection, $_POST['calcium_oxalates']) : NULL;
        $uric_acid_crystals = !empty($_POST['uric_acid_crystals']) ? mysqli_real_escape_string($connection, $_POST['uric_acid_crystals']) : NULL;
        $pus_cells_clumps = !empty($_POST['pus_cells_clumps']) ? mysqli_real_escape_string($connection, $_POST['pus_cells_clumps']) : NULL;
        $coarse_granular_cast = !empty($_POST['coarse_granular_cast']) ? mysqli_real_escape_string($connection, $_POST['coarse_granular_cast']) : NULL;
        $hyaline_cast = !empty($_POST['hyaline_cast']) ? mysqli_real_escape_string($connection, $_POST['hyaline_cast']) : NULL;

        // Insert query with updated macroscopic and microscopic fields
        $query = "
            INSERT INTO tbl_urinalysis (urinalysis_id, patient_id, patient_name, gender, dob, color, transparency, reaction, protein, glucose, specific_gravity, ketone, urobilinogen, pregnancy_test, pus_cells, red_blood_cells, epithelial_cells, a_urates_a_phosphates, mucus_threads, bacteria, calcium_oxalates, uric_acid_crystals, pus_cells_clumps, coarse_granular_cast, hyaline_cast, date_time) 
            VALUES ('$urinalysis_id', '$patient_id', '$name', '$gender', '$dob', '$color', '$transparency', '$reaction', '$protein', '$glucose', '$specific_gravity', '$ketone', '$urobilinogen', '$pregnancy_test', '$pus_cells', '$red_blood_cells', '$epithelial_cells', '$a_urates_a_phosphates', '$mucus_threads', '$bacteria', '$calcium_oxalates', '$uric_acid_crystals', '$pus_cells_clumps', '$coarse_granular_cast', '$hyaline_cast', NOW())
        ";

        // Execute the query
        if ($connection->query($query) === TRUE) {
            echo "Record added successfully";
        } else {
            echo "Error: " . $query . "<br>" . $connection->error;
        }
    
        // Redirect or show a success message
        header('Location: urinalysis.php');
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
                <h4 class="page-title">Urinalysis</h4>
            </div>
            <?php if ($role == 1 || $role == 5): ?>
                <div class="col-sm-10 col-9 m-b-20">
                    <form method="POST" action="urinalysis.php" id="addPatientForm" class="form-inline">
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
                        <input class="form-control" type="text" id="combinedSearchInput" onkeyup="filterUrinalysis()" style="padding-left: 35px; padding-right: 35px;">
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
                        <th>Urinalysis ID</th>
                        <th>Patient ID</th>
                        <th>Patient Name</th>
                        <th>Age</th>
                        <th>Gender</th>
                        <th>Date and Time</th>
                        <th>Color</th>
                        <th>Transparency</th>
                        <th>Reaction (pH)</th>
                        <th>Protein</th>
                        <th>Glucose</th>
                        <th>Specific Gravity</th>
                        <th>Ketone</th>
                        <th>Urobilinogen</th>
                        <th>Pregnancy Test</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    if(isset($_GET['ids'])){
                        $id = sanitize($connection, $_GET['ids']);
                        $update_query = $connection->prepare("UPDATE tbl_urinalysis SET deleted = 1 WHERE urinalysis_id = ?");
                        $update_query->bind_param("s", $id);
                        $update_query->execute();
                    }
                    $fetch_query = mysqli_query($connection, "SELECT * FROM tbl_urinalysis WHERE deleted = 0 ORDER BY date_time ASC");
                    while ($row = mysqli_fetch_array($fetch_query)) {
                        $dob = $row['dob'];
                        $date = str_replace('/', '-', $dob); 
                        $dob = date('Y-m-d', strtotime($date));
                        $year = (date('Y') - date('Y', strtotime($dob)));
                    ?>
                    <tr data-urinalysis-id="${record.urinalysis_id}">
                        <td><?php echo $row['urinalysis_id']; ?></td>
                        <td><?php echo $row['patient_id']; ?></td>
                        <td><?php echo $row['patient_name']; ?></td>
                        <td><?php echo $year; ?></td>
                        <td><?php echo $row['gender']; ?></td>
                        <td><?php echo date('F d, Y g:i A', strtotime($row['date_time'])); ?></td>
                        <td><?php echo $row['color']; ?></td>
                        <td><?php echo $row['transparency']; ?></td>
                        <td><?php echo $row['reaction']; ?></td>
                        <td><?php echo $row['protein']; ?></td>
                        <td><?php echo $row['glucose']; ?></td>
                        <td><?php echo $row['specific_gravity']; ?></td>
                        <td><?php echo $row['ketone']; ?></td>
                        <td><?php echo $row['urobilinogen']; ?></td>
                        <td><?php echo $row['pregnancy_test']; ?></td>
                        <td class="text-right">
                            <div class="dropdown dropdown-action">
                                <a href="#" class="action-icon dropdown-toggle" data-toggle="dropdown" aria-expanded="false"><i class="fa fa-ellipsis-v"></i></a>
                                <div class="dropdown-menu dropdown-menu-right">
                                    <?php if ($can_print): ?>
                                    <form action="generate-urinalysis.php" method="get">
                                        <input type="hidden" name="id" value="<?php echo $row['urinalysis_id']; ?>">
                                        <div class="form-group">
                                            <input type="text" class="form-control" id="filename" name="filename" placeholder="Enter File Name" aria-label="Enter File Name" aria-describedby="basic-addon2">
                                        </div>
                                        <button class="btn btn-primary btn-sm custom-btn" type="submit"><i class="fa fa-file-pdf-o m-r-5"></i> Generate Result</button>
                                    </form>
                                    <?php endif; ?>
                                    <?php if ($editable): ?>
                                        <a class="dropdown-item" href="edit-urinalysis.php?id=<?php echo $row['urinalysis_id']; ?>"><i class="fa fa-pencil m-r-5"></i> Insert and Edit</a>
                                        <a class="dropdown-item" href="urinalysis.php?id=<?php echo $row['urinalysis_id']; ?>" onclick="return confirmDelete()"><i class="fa fa-trash-o m-r-5"></i> Delete</a>
                                    <?php else: ?>
                                        <a class="dropdown-item disabled" href="#"><i class="fa fa-pencil m-r-5"></i> Edit</a>
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
        <div class="table-responsive">
            <h4 class="mt-4">Microscopic</h4>
            <table class="datatable table table-bordered table-hover" id="patientTableMicroscopic">
                <thead style="background-color: #CCCCCC;">
                    <tr>
                        <th>Urinalysis ID</th>
                        <th>Patient ID</th>
                        <th>Patient Name</th>
                        <th>Age</th>
                        <th>Gender</th>
                        <th>Date and Time</th>
                        <th>Pus Cells</th>
                        <th>Red Blood Cells</th>
                        <th>Epithelial Cells</th>
                        <th>A Urates/A Phosphates</th>
                        <th>Mucus Threads</th>
                        <th>Bacteria</th>
                        <th>Calcium Oxalates</th>
                        <th>Uric Acid Crystals</th>
                        <th>Pus Cells Clumps</th>
                        <th>Coarse Granular Cast</th>
                        <th>Hyaline Cast</th>
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
                    <tr data-urinalysis-id="${record.urinalysis_id}">
                        <td><?php echo $row['urinalysis_id']; ?></td>
                        <td><?php echo $row['patient_id']; ?></td>
                        <td><?php echo $row['patient_name']; ?></td>
                        <td><?php echo $year; ?></td>
                        <td><?php echo $row['gender']; ?></td>
                        <td><?php echo date('F d, Y g:i A', strtotime($row['date_time'])); ?></td>
                        <td><?php echo $row['pus_cells']; ?></td>
                        <td><?php echo $row['red_blood_cells']; ?></td>
                        <td><?php echo $row['epithelial_cells']; ?></td>
                        <td><?php echo $row['a_urates_a_phosphates']; ?></td>
                        <td><?php echo $row['mucus_threads']; ?></td>
                        <td><?php echo $row['bacteria']; ?></td>
                        <td><?php echo $row['calcium_oxalates']; ?></td>
                        <td><?php echo $row['uric_acid_crystals']; ?></td>
                        <td><?php echo $row['pus_cells_clumps']; ?></td>
                        <td><?php echo $row['coarse_granular_cast']; ?></td>
                        <td><?php echo $row['hyaline_cast']; ?></td>
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
</script>

<script>
    function clearSearch() {
        document.getElementById("combinedSearchInput").value = '';
        filterUrinalysis();
    }

    let canPrint, userRole, editable;

    $(document).ready(function() {
        canPrint = <?php echo $can_print ? 'true' : 'false' ?>;
        userRole = <?php echo $_SESSION['role']; ?>;
        editable = <?php echo $editable ? 'true' : 'false' ?>;
    });


    function filterUrinalysis() {
        var input = document.getElementById("combinedSearchInput").value;
        console.log('Search input:', input);
        
        $.ajax({
            url: 'fetch_urinalysis.php',
            type: 'GET',
            data: { query: input },
            success: function(response) {
                console.log('Response received:', response);
                try {
                    var data = JSON.parse(response);
                    console.log('Parsed data:', data);
                    updateUrinalysisTables(data);
                } catch (e) {
                    console.error('JSON parse error:', e);
                }
            },
            error: function(xhr, status, error) {
                console.error('Ajax error:', error);
            }
        });
    }


    function updateUrinalysisTables(data) {
        var macroscopicTbody = $('#patientTableMacroscopic tbody');
        var microscopicTbody = $('#patientTableMicroscopic tbody');
        
        macroscopicTbody.empty();
        microscopicTbody.empty();
        
        data.forEach(function(record) {
            // Macroscopic table row
            macroscopicTbody.append(`
                <tr data-urinalysis-id="${record.urinalysis_id}">
                    <td>${record.urinalysis_id}</td>
                    <td>${record.patient_id}</td>
                    <td>${record.patient_name}</td>
                    <td>${record.age}</td>
                    <td>${record.gender}</td>
                    <td>${record.date_time}</td>
                    <td>${record.color}</td>
                    <td>${record.transparency}</td>
                    <td>${record.reaction}</td>
                    <td>${record.protein}</td>
                    <td>${record.glucose}</td>
                    <td>${record.specific_gravity}</td>
                    <td>${record.ketone}</td>
                    <td>${record.urobilinogen}</td>
                    <td>${record.pregnancy_test}</td>
                    <td class="text-right">
                        <div class="dropdown dropdown-action">
                            <a href="#" class="action-icon dropdown-toggle" data-toggle="dropdown" aria-expanded="false">
                                <i class="fa fa-ellipsis-v"></i>
                            </a>
                            <div class="dropdown-menu dropdown-menu-right">
                                ${getActionButtons(record.urinalysis_id)}
                            </div>
                        </div>
                    </td>
                </tr>
            `);

            // Microscopic table row
            microscopicTbody.append(`
                <tr data-urinalysis-id="${record.urinalysis_id}">
                    <td>${record.urinalysis_id}</td>
                    <td>${record.patient_id}</td>
                    <td>${record.patient_name}</td>
                    <td>${record.age}</td>
                    <td>${record.gender}</td>
                    <td>${record.date_time}</td>
                    <td>${record.pus_cells}</td>
                    <td>${record.red_blood_cells}</td>
                    <td>${record.epithelial_cells}</td>
                    <td>${record.a_urates_a_phosphates}</td>
                    <td>${record.mucus_threads}</td>
                    <td>${record.bacteria}</td>
                    <td>${record.calcium_oxalates}</td>
                    <td>${record.uric_acid_crystals}</td>
                    <td>${record.pus_cells_clumps}</td>
                    <td>${record.coarse_granular_cast}</td>
                    <td>${record.hyaline_cast}</td>
                </tr>
            `);
        });
    }


    function getActionButtons(urinalysisId) {
        let buttons = '';
        
        if (canPrint) {  // Changed from can_print to canPrint
            buttons += `
                <form action="generate-urinalysis.php" method="get">
                    <input type="hidden" name="id" value="${urinalysisId}">
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
                <a class="dropdown-item" href="edit-urinalysis.php?id=${urinalysisId}">
                    <i class="fa fa-pencil m-r-5"></i> Insert and Edit
                </a>
                <a class="dropdown-item" href="urinalysis.php?ids=${urinalysisId}" onclick="return confirmDelete()">
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
                url: "search-ua.php", // Backend script to fetch patients
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
    #patientTableMicroscopic_length {
        display: none;
    }

    #patientTableMacroscopic_paginate .paginate_button {
        display: none;
    }

</style>

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
    .custom-btn {
        padding: 5px 27px; /* Adjust padding as needed */
        font-size: 12px; /* Adjust font size as needed */
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
