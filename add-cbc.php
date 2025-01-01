<?php
session_start();
if (empty($_SESSION['name'])) {
    header('location:index.php');
    exit;
}

include('header.php');
include('includes/connection.php');

// Fetch the latest CBC ID
$fetch_stmt = $connection->prepare("SELECT MAX(id) AS id FROM tbl_cbc");
$fetch_stmt->execute();
$fetch_result = $fetch_stmt->get_result();
$row = $fetch_result->fetch_assoc();
$cbc_id = ($row['id'] == 0) ? 1 : $row['id'] + 1;

if (isset($_REQUEST['add-cbc'])) {
    $cbc_id = 'CBC-' . $cbc_id;
    $patient_name = $_REQUEST['patient_name'];
    $date_time = $_REQUEST['date_time'];
    $hemoglobin = $_REQUEST['hemoglobin'];
    $hematocrit = $_REQUEST['hematocrit'];
    $red_blood_cells = $_REQUEST['red_blood_cells'];
    $white_blood_cells = $_REQUEST['white_blood_cells'];
    $esr = $_REQUEST['esr'];
    $segmenters = $_REQUEST['segmenters'];
    $lymphocytes = $_REQUEST['lymphocytes'];
    $eosinophils = $_REQUEST['eosinophils'];
    $monocytes = $_REQUEST['monocytes'];
    $bands = $_REQUEST['bands'];
    $platelets = $_REQUEST['platelets'];

    // Retrieve the Patient ID, gender, and dob using the Patient Name
    $query = "SELECT patient_id, gender, dob FROM tbl_patient WHERE CONCAT(first_name, ' ', last_name) = ? AND deleted = 0";
    $fetch_query = mysqli_prepare($connection, $query);
    mysqli_stmt_bind_param($fetch_query, 's', $patient_name); // 's' for string
    mysqli_stmt_execute($fetch_query);
    $result = mysqli_stmt_get_result($fetch_query);
    $row = mysqli_fetch_array($result);

    if ($row) {
        // Assign the patient details to variables
        $patient_id = $row['patient_id'];
        $gender = $row['gender'];
        $dob = $row['dob'];

        // Insert the CBC result into the database
        $insert_stmt = $connection->prepare("INSERT INTO tbl_cbc (cbc_id, patient_id, patient_name, dob, gender, date_time, hemoglobin, hematocrit, red_blood_cells, white_blood_cells, esr, segmenters, lymphocytes, eosinophils, monocytes, bands, platelets) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $insert_stmt->bind_param("sssssssssssssssss", $cbc_id, $patient_id, $patient_name, $dob, $gender, $date_time, $hemoglobin, $hematocrit, $red_blood_cells, $white_blood_cells, $esr, $segmenters, $lymphocytes, $eosinophils, $monocytes, $bands, $platelets);

        if ($insert_stmt->execute()) {
            $msg = "CBC result added successfully";
        } else {
            $msg = "Error!";
        }

        $insert_stmt->close();
    } else {
        $msg = "Patient not found!";
    }

    mysqli_stmt_close($fetch_query); // Close the fetch query statement
}

$fetch_stmt->close();
?>

<div class="page-wrapper">
    <div class="content">
        <div class="row">
            <div class="col-sm-4">
                <h4 class="page-title">Add CBC Result</h4>
            </div>
            <div class="col-sm-8 text-right m-b-20">
                <a href="cbc.php" class="btn btn-primary btn-rounded float-right">Back</a>
            </div>
        </div>
            <div class="col-lg-8 offset-lg-2">
                <form method="post">
                    <div class="form-group row">
                        <div class="col-sm-6">
                            <div class="form-group">
                                <label>CBC ID</label>
                                <input class="form-control" type="text" name="cbc_id" value="<?php if(!empty($cbc_id)) { echo 'CBC-'.$cbc_id; } else { echo "CBC-1"; } ?>" disabled>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label>Patient Name</label>
                            <input type="text" class="form-control" id="patient-search" placeholder="Search for patient" autocomplete="off" required>
                            <div id="patient-list" class="patient-list"></div>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Date and Time</label>
                        <input type="datetime-local" class="form-control" name="date_time" id="date_time">
                    </div>
                    <div class="form-group">
                        <label>Hemoglobin</label>
                        <input class="form-control" type="text" name="hemoglobin">
                    </div>
                    <div class="form-group">
                        <label>Hematocrit</label>
                        <input class="form-control" type="text" name="hematocrit">
                    </div>
                    <div class="form-group">
                        <label>Red Blood Cells</label>
                        <input class="form-control" type="text" name="red_blood_cells">
                    </div>
                    <div class="form-group">
                        <label>White Blood Cells</label>
                        <input class="form-control" type="text" name="white_blood_cells">
                    </div>
                    <div class="form-group">
                        <label>ESR</label>
                        <input class="form-control" type="text" name="esr">
                    </div>
                    <div class="form-group">
                        <label>Segmenters</label>
                        <input class="form-control" type="text" name="segmenters">
                    </div>
                    <div class="form-group">
                        <label>Lymphocytes</label>
                        <input class="form-control" type="text" name="lymphocytes">
                    </div>
                    <div class="form-group">
                        <label>Eosinophils</label>
                        <input class="form-control" type="text" name="eosinophils">
                    </div>
                    <div class="form-group">
                        <label>Monocytes</label>
                        <input class="form-control" type="text" name="monocytes">
                    </div>
                    <div class="form-group">
                        <label>Bands</label>
                        <input class="form-control" type="text" name="bands">
                    </div>
                    <div class="form-group">
                        <label>Platelets</label>
                        <input class="form-control" type="text" name="platelets">
                    </div>
                    <div class="m-t-20 text-center">
                        <button class="btn btn-primary submit-btn" name="add-cbc">Add Hematology</button>
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
<script src="assets/js/jquery.min.js"></script>
<link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/1.10.25/css/jquery.dataTables.css">
<script type="text/javascript" charset="utf8" src="https://cdn.datatables.net/1.10.25/js/jquery.dataTables.js"></script>

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
            fetch('search-cbc.php?query=' + query)  
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

        select.form-control:focus {
            border-color: #12369e; /* Border color on focus */
            box-shadow: 0 0 0 .2rem rgba(38, 143, 255, .25); /* Shadow on focus */
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