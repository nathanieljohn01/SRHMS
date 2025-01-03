<?php
session_start();
if (empty($_SESSION['name'])) {
    header('location:index.php');
    exit;
}

include('header.php');
include('includes/connection.php');

// Fetch the latest test ID
$fetch_query = mysqli_query($connection, "SELECT MAX(id) AS id FROM tbl_laborder");
$row = mysqli_fetch_assoc($fetch_query);
$tst_id = ($row['id'] == 0) ? 1 : $row['id'] + 1;

if (isset($_POST['save-order'])) {
    // Sanitize and validate inputs
    $test_id = 'TEST-' . $tst_id;

    // Use htmlspecialchars to prevent XSS for patient name and other outputs
    $patient_name = htmlspecialchars(trim($_POST['patient_name']), ENT_QUOTES, 'UTF-8');

    // Validate patient name (could be improved with regex for specific format, if required)
    if (empty($patient_name)) {
        echo "Patient name cannot be empty!";
        exit;
    }

    // Fetch patient details
    $fetch_patient_stmt = mysqli_prepare($connection, "
        SELECT patient_id, gender, dob 
        FROM tbl_patient 
        WHERE CONCAT(first_name, ' ', last_name) = ?
    ");
    mysqli_stmt_bind_param($fetch_patient_stmt, 's', $patient_name);
    mysqli_stmt_execute($fetch_patient_stmt);
    mysqli_stmt_bind_result($fetch_patient_stmt, $patient_id, $gender, $dob);
    mysqli_stmt_fetch($fetch_patient_stmt);
    mysqli_stmt_close($fetch_patient_stmt);

    if (!$patient_id) {
        echo "Patient not found!";
        exit;
    }

    // Ensure stat is a valid input
    $stat_status = isset($_POST['stat']) && $_POST['stat'] === 'on' ? 'STAT' : 'Regular';

    // Validate and sanitize shift input
    $shift = isset($_POST['shift']) ? htmlspecialchars(trim($_POST['shift']), ENT_QUOTES, 'UTF-8') : 'Regular';

    if (isset($_POST['lab_test']) && !empty($_POST['lab_test'])) {
        // Prepare the insert statement for tbl_laborder
        $insert_stmt = mysqli_prepare($connection, "
            INSERT INTO tbl_laborder 
            (test_id, patient_id, patient_name, gender, dob, lab_department, lab_test, price, status, requested_date, stat, shift) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'In-Progress', NOW(), ?, ?)
        ");

        foreach ($_POST['lab_test'] as $selected_lab_test) {
            // Sanitize lab test name
            $selected_lab_test = htmlspecialchars(trim($selected_lab_test), ENT_QUOTES, 'UTF-8');

            // Fetch lab test details
            $fetch_lab_test_stmt = mysqli_prepare($connection, "
                SELECT lab_department, lab_test_price 
                FROM tbl_labtest 
                WHERE lab_test = ?
            ");
            mysqli_stmt_bind_param($fetch_lab_test_stmt, 's', $selected_lab_test);
            mysqli_stmt_execute($fetch_lab_test_stmt);
            $lab_test_result = mysqli_stmt_get_result($fetch_lab_test_stmt);
            $lab_test_data = mysqli_fetch_assoc($lab_test_result);
            mysqli_stmt_close($fetch_lab_test_stmt);

            if (!$lab_test_data) {
                continue; // Skip if no lab test data found
            }

            // Extract department and price
            $department = htmlspecialchars(trim($lab_test_data['lab_department']), ENT_QUOTES, 'UTF-8');
            $price = (float)$lab_test_data['lab_test_price']; // Ensure price is treated as a decimal

            // Bind and execute the insert statement
            mysqli_stmt_bind_param($insert_stmt, 'sssssssdss', $test_id, $patient_id, $patient_name, $gender, $dob, $department, $selected_lab_test, $price, $stat_status, $shift);
            if (mysqli_stmt_execute($insert_stmt)) {
                $msg = "Lab order created successfully!";
            } else {
                $msg = "Error saving data: " . mysqli_error($connection);
                break; // Exit the loop on failure
            }
        }

        mysqli_stmt_close($insert_stmt);
        echo htmlspecialchars($msg, ENT_QUOTES, 'UTF-8'); // Display the appropriate message, ensuring no XSS
    } else {
        echo "Please select at least one lab test.";
    }
}
?>

<div class="page-wrapper">
    <div class="content">
        <div class="row">
            <div class="col-sm-4">
                <h4 class="page-title">Add Order</h4>
            </div>
            <div class="col-sm-8 text-right m-b-20">
                <a href="lab-order-patients.php" class="btn btn-primary btn-rounded float-right">Back</a>
            </div>
        </div>
        <div class="row">
            <div class="col-lg-8 offset-lg-2">
                <form method="post" class="container mt-5" id="labOrderForm" onsubmit="return validateForm()">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Test ID <span class="text-danger">*</span></label>
                                <input class="form-control" type="text" name="test_id" value="<?php
                                    if (!empty($tst_id)) {
                                        echo 'TEST-' . $tst_id;
                                    } else {
                                        echo "TEST-1";
                                    } ?>" disabled>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label>Patient Name</label>
                            <input type="text" class="form-control" id="patient-search" name="patient_name" placeholder="Search for patient" required>
                            <div id="patient-list" class="patient-list"></div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Shift</label>
                                <select class="form-control" name="shift" required>
                                    <option value="">Select Shift</option>
                                    <option value="Morning">Morning</option>
                                    <option value="Afternoon">Afternoon</option>
                                    <option value="Night">Night</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>STAT</label><br>
                                <label class="switch">
                                    <input type="checkbox" name="stat" value="1">
                                    <span class="slider round"></span>
                                </label>
                            </div>
                        </div>
                    </div>
                    <div class="table-responsive">
                        <h4>Lab Tests</h4>
                        <input class="form-control" type="text" id="labTestSearchInput" onkeyup="filterTests()" placeholder="Search for Lab Test">
                        <table class="table table-hover" id="labTestTable">
                        <thead style="background-color: #CCCCCC;">
                                <tr>
                                    <th>Lab Test</th>
                                    <th>Code</th>
                                    <th>Lab Department</th>
                                    <th>Price</th>
                                    <th>Select</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                               $fetch_tests_query = mysqli_query($connection, "SELECT lab_department, lab_test, code, lab_test_price FROM tbl_labtest WHERE status = 'Available'");
                                while ($test_row = mysqli_fetch_array($fetch_tests_query)) {
                                ?>
                                    <tr>
                                        <td><?php echo $test_row['lab_test']; ?></td>
                                        <td><?php echo $test_row['code']; ?></td>
                                        <td><?php echo $test_row['lab_department']; ?></td>
                                        <td><?php echo $test_row['lab_test_price']; ?></td>
                                        <td><input type="checkbox" name="lab_test[]" value="<?php echo $test_row['lab_test']; ?>"></td>
                                    </tr>
                                <?php } ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="m-t-20 text-center">
                        <button class="btn btn-primary submit-btn" name="save-order">Save</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php
include('footer.php');
?>

<script type="text/javascript">
    <?php
    if (isset($msg)) {
        echo 'swal("' . $msg . '");';
    }
    ?>
</script>

<script type="text/javascript">
    function validateForm() {
        var checkboxes = document.querySelectorAll('input[name="lab_test[]"]:checked');

        if (checkboxes.length === 0) {
            alert("Please select at least one from the lab tests.");
            return false; 
        }
        return true;
    }
</script>

<script>
 function filterTests() {
    var input, filter, table, tr, td, i, txtValue;
    input = document.getElementById("labTestSearchInput");
    filter = input.value.toUpperCase();
    table = document.getElementById("labTestTable");
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

document.addEventListener('DOMContentLoaded', function () {
    const searchInput = document.getElementById('patient-search');
    const patientList = document.getElementById('patient-list');

    // Event listener for keyup on the search input field
    searchInput.addEventListener('keyup', function () {
        const query = searchInput.value.trim();
        if (query.length > 2) {
            // Create the AJAX request
            fetch('search-lab.php?query=' + query)  // This will call the search-ipt.php file
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
</style>

<style>
    .switch {
        position: relative;
        display: inline-block;
        width: 60px;
        height: 34px;
    }

    .switch input {
        opacity: 0;
        width: 0;
        height: 0;
    }

    .slider {
        position: absolute;
        cursor: pointer;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background-color: #ccc;
        transition: .4s;
        border-radius: 34px;
    }

    .slider:before {
        position: absolute;
        content: "";
        height: 26px;
        width: 26px;
        left: 4px;
        bottom: 4px;
        background-color: white;
        transition: .4s;
        border-radius: 50%;
    }

    input:checked + .slider {
        background-color: #12369e;
    }

    input:checked + .slider:before {
        transform: translateX(26px);
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