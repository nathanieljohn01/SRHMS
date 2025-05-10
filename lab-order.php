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
        echo "<script>showError('Patient name cannot be empty!');</script>";
        exit;
    }

    // Fetch patient details
    $fetch_patient_stmt = mysqli_prepare($connection, "
        SELECT patient_id, patient_type, gender, dob 
        FROM tbl_patient 
        WHERE CONCAT(first_name, ' ', last_name) = ?
    ");
    mysqli_stmt_bind_param($fetch_patient_stmt, 's', $patient_name);
    mysqli_stmt_execute($fetch_patient_stmt);
    mysqli_stmt_bind_result($fetch_patient_stmt, $patient_id, $patient_type, $gender, $dob);
    mysqli_stmt_fetch($fetch_patient_stmt);
    mysqli_stmt_close($fetch_patient_stmt);

    if (!$patient_id) {
        echo "<script>showError('Patient not found!');</script>";
        exit;
    }

    // Ensure stat is a valid input
    $stat_status = isset($_POST['stat']) && $_POST['stat'] === 'on' ? 'STAT' : 'Regular';

    // Validate and sanitize shift input
    $shift = isset($_POST['shift']) ? htmlspecialchars(trim($_POST['shift']), ENT_QUOTES, 'UTF-8') : 'Regular';

    if (isset($_POST['lab_test']) && !empty($_POST['lab_test'])) {
        $success = true;
        
        // Prepare the insert statement for tbl_laborder
        $insert_stmt = mysqli_prepare($connection, "
            INSERT INTO tbl_laborder 
            (test_id, patient_id, patient_name, patient_type, gender, dob, lab_department, lab_test, price, status, requested_date, stat, shift) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'In-Progress', NOW(), ?, ?)
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
                continue;
            }
    
            // Extract department and price
            $department = htmlspecialchars(trim($lab_test_data['lab_department']), ENT_QUOTES, 'UTF-8');
            $price = (float)$lab_test_data['lab_test_price'];
    
            // Bind and execute the insert statement
            mysqli_stmt_bind_param($insert_stmt, 'ssssssssdss', $test_id, $patient_id, $patient_name, $patient_type, $gender, $dob, $department, $selected_lab_test, $price, $stat_status, $shift);
            if (!mysqli_stmt_execute($insert_stmt)) {
                $success = false;
                break;
            }
        }
    
        mysqli_stmt_close($insert_stmt);
    
        if ($success) {
            echo "
            <script src='https://cdn.jsdelivr.net/npm/sweetalert2@10'></script>
            <script>
                document.addEventListener('DOMContentLoaded', function() {
                    Swal.fire({
                        icon: 'success',
                        title: 'Success!',
                        text: 'Lab order created successfully!',
                        confirmButtonColor: '#12369e'
                    }).then(() => {
                        window.location.href = 'lab-order-patients.php';
                    });
                });
            </script>";
        } else {
            echo "
            <script src='https://cdn.jsdelivr.net/npm/sweetalert2@10'></script>
            <script>
                document.addEventListener('DOMContentLoaded', function() {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: 'Error saving data: " . addslashes(mysqli_error($connection)) . "',
                        confirmButtonColor: '#12369e'
                    });
                });
            </script>";
        }
    } else {
        echo "
        <script src='https://cdn.jsdelivr.net/npm/sweetalert2@10'></script>
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                Swal.fire({
                    icon: 'error',
                    title: 'Validation Error',
                    text: 'Please select at least one lab test.',
                    confirmButtonColor: '#12369e'
                });
            });
        </script>";
    }
}
?>
<div class="page-wrapper">
    <div class="content">
        <div class="row">
            <div class="col-sm-6">
                <h4 class="page-title">Add Order</h4>
            </div>
            <div class="col-sm-6 text-right m-b-20">
                <a href="lab-order-patients.php" class="btn btn-primary float-right">Back</a>
            </div>
        </div>
        <div class="row justify-content-center">
            <div class="col-xl-10 col-lg-12">
                <form method="post" id="labOrderForm" onsubmit="return validateForm()">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Test ID <span class="text-danger">*</span></label>
                                <input class="form-control" type="text" name="test_id" value="<?php
                                    if (!empty($tst_id)) {
                                        echo 'TEST-' . $tst_id;
                                    } else {
                                        echo "TEST-1";
                                    }
                                ?>" disabled>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Patient Name</label>
                                <input type="text" class="form-control" id="patient-search" name="patient_name" placeholder="Search for patient" required>
                                <div id="patient-list" class="patient-list"></div>
                            </div>
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
                                <label>Priority</label>
                                <div class="d-flex align-items-center">
                                    <label class="switch mr-2">
                                        <input type="checkbox" name="stat" id="statToggle">
                                        <span class="slider round"></span>
                                    </label>
                                    <span id="statStatusText" class="text-muted">Regular</span>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="table-responsive">
                        <div class="sticky-search">
                            <h5 class="font-weight-bold mb-2">Search for Laboratory Test:</h5>
                            <div class="input-group mb-3">
                                <div class="position-relative w-100">
                                    <!-- Search Icon -->
                                    <i class="fa fa-search position-absolute text-secondary" style="top: 50%; left: 12px; transform: translateY(-50%);"></i>
                                    <!-- Input Field -->
                                    <input class="form-control" type="text" id="labTestSearchInput" onkeyup="filterTests()" placeholder="Search" style="padding-left: 35px; padding-right: 35px;">
                                    <!-- Clear Button -->
                                    <button class="position-absolute border-0 bg-transparent text-secondary" type="button" onclick="clearSearch()" style="top: 50%; right: 10px; transform: translateY(-50%);">
                                        <i class="fa fa-times"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="table-responsive">
                        <table class="datatable table table-hover" id="labTestTable">
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
                                $fetch_tests_query = mysqli_query($connection, "
                                    SELECT lab_department, lab_test, code, lab_test_price 
                                    FROM tbl_labtest 
                                    WHERE status = 'Available'
                                    ORDER BY lab_department, lab_test
                                ");
                                
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

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script type="text/javascript">
    function validateForm() {
        var checkboxes = document.querySelectorAll('input[name="lab_test[]"]:checked');

        if (checkboxes.length === 0) {
            showError("Please select at least one from the lab tests.");
            return false; 
        }
        return true;
    }
    // STAT Toggle Functionality
    document.getElementById('statToggle').addEventListener('change', function() {
        const statusText = document.getElementById('statStatusText');
        if(this.checked) {
            statusText.textContent = 'STAT';
            statusText.classList.remove('text-muted');
            statusText.classList.add('text-danger', 'font-weight-bold');
        } else {
            statusText.textContent = 'Regular';
            statusText.classList.remove('text-danger', 'font-weight-bold');
            statusText.classList.add('text-muted');
        }
    });
</script>

<script>
 function clearSearch() {
    document.getElementById("labTestSearchInput").value = '';
    filterTests();
 }  
 function filterTests() {
    var input = document.getElementById("labTestSearchInput").value;
    
    $.ajax({
        url: 'fetch_lab_order.php',
        method: 'GET',
        data: { query: input },
        success: function(response) {
            var data = JSON.parse(response);
            updateLabTestTable(data);
        }
    });
}

function updateLabTestTable(data) {
    var tbody = $('#labTestTable tbody');
    tbody.empty();
    
    data.forEach(function(row) {
        tbody.append(`
            <tr>
                <td>${row.lab_test}</td>
                <td>${row.code}</td>
                <td>${row.lab_department}</td>
                <td>${row.lab_test_price}</td>
                <td><input type="checkbox" name="lab_test[]" value="${row.lab_test}"></td>
            </tr>
        `);
    });
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
    /* STAT Switch Styling */
    .switch {
        position: relative;
        display: inline-block;
        width: 60px;  /* Increased from 50px */
        height: 34px; /* Increased from 30px */
        margin-top: 3px; /* Better vertical alignment */
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
        background-color: #e9ecef; /* Lighter inactive state */
        border: 1px solid #ced4da; /* Matching form control borders */
        transition: .4s;
    }

    .slider:before {
        position: absolute;
        content: "";
        height: 26px;  /* Larger knob */
        width: 26px;   /* Larger knob */
        left: 4px;
        bottom: 4px;
        background-color: white;
        box-shadow: 0 1px 3px rgba(0,0,0,0.2); /* Subtle depth */
        transition: .4s;
    }

    input:checked + .slider {
        background-color: #dc3545; /* Emergency red */
        border-color: #dc3545;    /* Matching border */
    }

    input:checked + .slider:before {
        transform: translateX(26px); /* Adjusted for new size */
    }

    /* Rounded sliders */
    .slider.round {
        border-radius: 34px;
    }

    .slider.round:before {
        border-radius: 50%;
    }

    /* Status text styling */
    #statStatusText {
        margin-left: 8px;
        font-size: 0.9rem;
        font-weight: 500;
        vertical-align: middle;
    }
    .btn-primary.submit-btn {
        border-radius: 4px; 
        padding: 10px 20px;
        font-size: 16px;
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
        color:rgb(90, 90, 90);
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
</style>
