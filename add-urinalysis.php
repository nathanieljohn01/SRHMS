<?php
session_start();
if (empty($_SESSION['name'])) {
    header('location:index.php');
    exit(); // Ensure that the script stops executing after redirection
}

include('header.php');
include('includes/connection.php');

// Fetch the next available urinalysis ID
$fetch_query = mysqli_query($connection, "SELECT MAX(id) AS id FROM tbl_urinalysis");
$row = mysqli_fetch_row($fetch_query);
$ua_id = ($row[0] == 0) ? 1 : $row[0] + 1;

if (isset($_REQUEST['add-urinalysis'])) {
    $urinalysis_id = 'UA-' . $ua_id;
    $patient_name = $_REQUEST['patient_name']; 
    $date_time = $_REQUEST['date_time'];
    $color = $_REQUEST['color'];
    $transparency = $_REQUEST['transparency'];
    $reaction = $_REQUEST['reaction'];
    $protein = $_REQUEST['protein'];
    $glucose = $_REQUEST['glucose'];
    $specific_gravity = $_REQUEST['specific_gravity'];
    $ketone = $_REQUEST['ketone'];
    $urobilinogen = $_REQUEST['urobilinogen'];
    $pregnancy_test = $_REQUEST['pregnancy_test'];
    $pus_cells = $_REQUEST['pus_cells'];
    $red_blood_cells = $_REQUEST['red_blood_cells'];
    $epithelial_cells = $_REQUEST['epithelial_cells'];
    $a_urates_a_phosphates = $_REQUEST['a_urates_a_phosphates'];
    $mucus_threads = $_REQUEST['mucus_threads'];
    $bacteria = $_REQUEST['bacteria'];
    $calcium_oxalates = $_REQUEST['calcium_oxalates'];
    $uric_acid_crystals = $_REQUEST['uric_acid_crystals'];
    $pus_cells_clumps = $_REQUEST['pus_cells_clumps'];
    $coarse_granular_cast = $_REQUEST['coarse_granular_cast'];
    $hyaline_cast = $_REQUEST['hyaline_cast'];

    // Get the Patient ID using the Patient Name from the database
    $fetch_query = mysqli_prepare($connection, "SELECT patient_id, gender, dob FROM tbl_patient WHERE CONCAT(first_name, ' ', last_name) = ?");
    mysqli_stmt_bind_param($fetch_query, 's', $patient_name);
    mysqli_stmt_execute($fetch_query);
    mysqli_stmt_bind_result($fetch_query, $patient_id, $gender, $dob);
    mysqli_stmt_fetch($fetch_query);
    
    // Check if patient data exists
    if (!$patient_id) {
        echo "Patient not found!";
        mysqli_stmt_close($fetch_query);
        exit();
    }

    mysqli_stmt_close($fetch_query);

    $insert_query = mysqli_prepare($connection, "
        INSERT INTO tbl_urinalysis 
        (urinalysis_id, patient_id, patient_name, dob, gender, date_time, color, transparency, reaction, protein, glucose, specific_gravity, ketone, urobilinogen, pregnancy_test, pus_cells, red_blood_cells, epithelial_cells, a_urates_a_phosphates, mucus_threads, bacteria, calcium_oxalates, uric_acid_crystals, pus_cells_clumps, coarse_granular_cast, hyaline_cast) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");

    mysqli_stmt_bind_param($insert_query, "ssssssssssssssssssssssssss", 
        $urinalysis_id, $patient_id, $patient_name, $dob, $gender, $date_time, $color, $transparency, $reaction, $protein, $glucose, 
        $specific_gravity, $ketone, $urobilinogen, $pregnancy_test, $pus_cells, $red_blood_cells, $epithelial_cells, 
        $a_urates_a_phosphates, $mucus_threads, $bacteria, $calcium_oxalates, $uric_acid_crystals, $pus_cells_clumps, 
        $coarse_granular_cast, $hyaline_cast);


    // Execute the query
    if (mysqli_stmt_execute($insert_query)) {
        echo "Urinalysis data inserted successfully.";
    } else {
        echo "Error: " . mysqli_stmt_error($insert_query);
    }

    // Close the prepared statement
    mysqli_stmt_close($insert_query);
}
?>

<div class="page-wrapper">
    <div class="content">
        <div class="row">
            <div class="col-sm-4">
                <h4 class="page-title">Add Urinalysis Result</h4>
            </div>
            <div class="col-sm-8 text-right mb-3">
                <a href="urinalysis.php" class="btn btn-primary btn-rounded float-right"><i class="fa fa-arrow-left"></i> Back</a>
            </div>
        </div>
        <div class="row">
            <div class="col-lg-8 offset-lg-2">
                <form method="post">
                    <div class="form-group row">
                        <div class="col-sm-6">
                            <label>Urinalysis ID</label>
                            <input class="form-control" type="text" name="urinalysis_id" value="<?php if(!empty($ua_id)) { echo 'UA-'.$ua_id; } else { echo 'UA-1'; } ?>" disabled>
                        </div>
                        <div class="col-md-6">
                            <label>Patient Name</label>
                            <input type="text" class="form-control" id="patient-search" name="patient_name" placeholder="Search for patient" required>
                            <div id="patient-list" class="patient-list"></div>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Date and Time</label>
                        <input type="datetime-local" class="form-control" name="date_time" id="date_time">
                    </div>
                    <h4 style="font-size: 18px;">Macroscopic</h4>
                    <div class="form-group">
                        <label>Color</label>
                        <input class="form-control" type="text" name="color">
                    </div>
                    <div class="form-group">
                        <label>Transparency</label>
                        <input class="form-control" type="text" name="transparency">
                    </div>
                    <div class="form-group">
                        <label>Reaction (pH)</label>
                        <input class="form-control" type="text" name="reaction">
                    </div>
                    <div class="form-group">
                        <label>Protein</label>
                        <input class="form-control" type="text" name="protein">
                    </div>
                    <div class="form-group">
                        <label>Glucose</label>
                        <input class="form-control" type="text" name="glucose">
                    </div>
                    <div class="form-group">
                        <label>Specific Gravity</label>
                        <input class="form-control" type="text" name="specific_gravity">
                    </div>
                    <div class="form-group">
                        <label>Ketone</label>
                        <input class="form-control" type="text" name="ketone">
                    </div>
                    <div class="form-group">
                        <label>Urobilinogen</label>
                        <input class="form-control" type="text" name="urobilinogen">
                    </div>
                    <div class="form-group">
                        <label>Pregnancy Test</label>
                        <input class="form-control" type="text" name="pregnancy_test">
                    </div>
                    <h4 style="font-size: 18px;">Microscopic</h4>
                    <div class="form-group">
                        <label>Pus Cells</label>
                        <input class="form-control" type="text" name="pus_cells">
                    </div>
                    <div class="form-group">
                        <label>Red Blood Cells</label>
                        <input class="form-control" type="text" name="red_blood_cells">
                    </div>
                    <div class="form-group">
                        <label>Epithelial Cells</label>
                        <input class="form-control" type="text" name="epithelial_cells">
                    </div>
                    <div class="form-group">
                        <label>A Urates/A Phosphates</label>
                        <input class="form-control" type="text" name="a_urates_a_phosphates">
                    </div>
                    <div class="form-group">
                        <label>Mucus Threads</label>
                        <input class="form-control" type="text" name="mucus_threads">
                    </div>
                    <div class="form-group">
                        <label>Bacteria</label>
                        <input class="form-control" type="text" name="bacteria">
                    </div>
                    <div class="form-group">
                        <label>Calcium Oxalates</label>
                        <input class="form-control" type="text" name="calcium_oxalates">
                    </div>
                    <div class="form-group">
                        <label>Uric Acid Crystals</label>
                        <input class="form-control" type="text" name="uric_acid_crystals">
                    </div>
                    <div class="form-group">
                        <label>Pus Cells Clumps</label>
                        <input class="form-control" type="text" name="pus_cells_clumps">
                    </div>
                    <div class="form-group">
                        <label>Coarse Granular Cast</label>
                        <input class="form-control" type="text" name="coarse_granular_cast">
                    </div>
                    <div class="form-group">
                        <label>Hyaline Cast</label>
                        <input class="form-control" type="text" name="hyaline_cast">
                    </div>
                    <div class="mt-3 text-center">
                        <button class="btn btn-primary submit-btn" name="add-urinalysis">Add Urinalysis</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php
include('footer.php');
?>

<script src="assets/js/moment.min.js"></script>
<script src="assets/js/bootstrap-datetimepicker.js"></script>
<script src="assets/js/bootstrap-datetimepicker.min.js"></script>

<script type="text/javascript">
    <?php
    if (isset($msg)) {
        echo 'swal("' . $msg . '");';
    }
    ?>

document.addEventListener('DOMContentLoaded', function () {
    const searchInput = document.getElementById('patient-search');
    const patientList = document.getElementById('patient-list');

    // Event listener for keyup on the search input field
    searchInput.addEventListener('keyup', function () {
        const query = searchInput.value.trim();
        if (query.length > 2) {
            // Create the AJAX request
            fetch('search-ua.php?query=' + query)  // This will call the search-ipt.php file
                .then(response => response.text())
                .then(data => {
                    patientList.innerHTML = '';  // Clear previous results
                    if (data.trim()) {
                        // Dynamically add the patient options from the PHP response
                        patientList.innerHTML = data;  // Add the list of patient options from the PHP file
                    } else {
                        patientList.innerHTML = '<div class="patient-option text-muted">No matching patients found</div>';
                    }
                    patientList.style.display = 'block';  // Show the list
                })
                .catch(error => console.error('Error:', error));
        } else {
            patientList.style.display = 'none';  // Hide the list if query length is less than 3
        }
    });

    // Event listener for when a patient option is clicked
    patientList.addEventListener('click', function (e) {
        if (e.target.classList.contains('patient-option')) {
            searchInput.value = e.target.innerText;  // Populate the input with the selected patient's name
            patientList.style.display = 'none';  // Hide the list after selection
        }
    });
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

#patient-search {
    position: relative; /* Makes sure the patient list is positioned below */
}

/* Styling the patient list */
.patient-list {
    max-height: 200px; /* Maximum height to prevent list overflow */
    overflow-y: auto; /* Scrollable if the list is long */
    border: 1px solid #ddd; /* Border color */
    border-radius: 5px; /* Rounded corners */
    background: #fff; /* Background color */
    position: absolute; /* Absolute positioning below the input */
    z-index: 1000; /* Ensures the list is on top of other elements */
    width: 93%; /* Adjust the width to match the input field */
    display: none; /* Initially hidden */
    box-shadow: 0px 4px 6px rgba(0, 0, 0, 0.1); /* Add subtle shadow */
}

/* Styling individual list items */
.patient-list .patient-option {
    padding: 8px 12px;
    cursor: pointer;
    list-style: none;
    border-bottom: 1px solid #ddd;
}

/* Hover effect on list items */
.patient-list .patient-option:hover {
    background-color: #12369e;
    color: white;
}
</style>