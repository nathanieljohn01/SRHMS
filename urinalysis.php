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
        <input class="form-control" type="text" id="combinedSearchInput" onkeyup="filterCombinedResults()" placeholder="Search for Patient">
            <h4 class="mt-3">Macroscopic</h4>
            <table class="datatable table table-bordered" id="patientTableMacroscopic">
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
                    <tr>
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
            <table class="datatable table table-bordered" id="patientTableMicroscopic">
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
                    <tr>
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
   $(document).ready(function() {
    // Initialize DataTables only if not already initialized
    if (!$.fn.DataTable.isDataTable('#patientTableMacroscopic')) {
        $('#patientTableMacroscopic').DataTable({
            "paging": true,
            "searching": false,
            "info": false,
            "lengthChange": true, // Enable show entries
            "pagingType": "simple" // Simplified pagination
        });
    }

    if (!$.fn.DataTable.isDataTable('#patientTableMicroscopic')) {
        $('#patientTableMicroscopic').DataTable({
            "paging": true,
            "searching": false,
            "info": false,
            "lengthChange": true, // Enable show entries, but we'll hide it with CSS
            "pagingType": "simple" // Simplified pagination
        });
    }

        synchronizePagination();
    });

    function synchronizePagination() {
        var table1 = $('#patientTableMacroscopic').DataTable();
        var table2 = $('#patientTableMicroscopic').DataTable();

        $('#patientTableMacroscopic').on('page.dt', function () {
            var info = table1.page.info();
            table2.page(info.page).draw(false);
        });

        $('#patientTableMicroscopic').on('page.dt', function () {
            var info = table2.page.info();
            table1.page(info.page).draw(false);
        });
    }

    function filterCombinedResults() {
        var input, filter, table1, table2, tr1, tr2, td, i, txtValue;
        input = document.getElementById("combinedSearchInput");
        filter = input.value.toUpperCase();
        table1 = document.getElementById("patientTableMacroscopic");
        table2 = document.getElementById("patientTableMicroscopic");
        tr1 = table1.getElementsByTagName("tr");
        tr2 = table2.getElementsByTagName("tr");

        // Filter Macroscopic Table
        for (i = 0; i < tr1.length; i++) {
            var matchFound = false;
            for (var j = 0; j < tr1[i].cells.length; j++) {
                td = tr1[i].cells[j];
                if (td) {
                    txtValue = td.textContent || td.innerText;
                    if (txtValue.toUpperCase().indexOf(filter) > -1) {
                        matchFound = true;
                        break;
                    }
                }
            }
            if (matchFound || i === 0) {
                tr1[i].style.display = "";
            } else {
                tr1[i].style.display = "none";
            }
        }

        // Filter Microscopic Table
        for (i = 0; i < tr2.length; i++) {
            var matchFound = false;
            for (var j = 0; j < tr2[i].cells.length; j++) {
                td = tr2[i].cells[j];
                if (td) {
                    txtValue = td.textContent || td.innerText;
                    if (txtValue.toUpperCase().indexOf(filter) > -1) {
                        matchFound = true;
                        break;
                    }
                }
            }
            if (matchFound || i === 0) {
                tr2[i].style.display = "";
            } else {
                tr2[i].style.display = "none";
            }
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
