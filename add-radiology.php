<?php
session_start();
if (empty($_SESSION['name'])) {
    header('location:index.php');
    exit;
}

include('header.php');
include('includes/connection.php');

// Generate a new Radiology ID
$fetch_query = mysqli_query($connection, "SELECT MAX(id) AS id FROM tbl_radiology");
$row = mysqli_fetch_assoc($fetch_query);
$new_id = ($row['id'] == 0) ? 1 : $row['id'] + 1;
$radiology_id = 'RD-' . $new_id;

$msg = ""; // Initialize the message variable

if (isset($_POST['add-radiology'])) {
    // Sanitize and validate input
    $patient_name = htmlspecialchars(trim($_POST['patient_name']), ENT_QUOTES, 'UTF-8');
    $selected_tests = isset($_POST['rad_tests']) ? $_POST['rad_tests'] : []; // Handle multiple test selection

    if (empty($patient_name)) {
        $msg = "Patient name is required!";
    } elseif (empty($selected_tests)) {
        $msg = "Please select at least one radiology test!";
    } else {
        foreach ($selected_tests as $selected_test_type) {
            $selected_test_type = htmlspecialchars(trim($selected_test_type), ENT_QUOTES, 'UTF-8');

            // Fetch radiology test details
            $fetch_test_stmt = mysqli_prepare($connection, "
                SELECT exam_type, test_type, price 
                FROM tbl_radtest 
                WHERE test_type = ?
            ");
            mysqli_stmt_bind_param($fetch_test_stmt, 's', $selected_test_type);
            mysqli_stmt_execute($fetch_test_stmt);
            mysqli_stmt_bind_result($fetch_test_stmt, $exam_type, $test_type, $price);
            mysqli_stmt_fetch($fetch_test_stmt);
            mysqli_stmt_close($fetch_test_stmt);

            if (empty($exam_type) || empty($test_type) || empty($price)) {
                $msg = "Selected test type not found in the database!";
                break;
            } else {
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

                if (empty($patient_id)) {
                    $msg = "Patient not found!";
                    break;
                } else {
                    // Insert radiology order
                    $insert_stmt = mysqli_prepare($connection, "
                        INSERT INTO tbl_radiology 
                        (radiology_id, patient_id, patient_name, patient_type, gender, dob, exam_type, test_type, status, shift, requested_date, price) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'In-Progress', ?, NOW(), ?)
                    ");
                    mysqli_stmt_bind_param(
                        $insert_stmt,
                        'ssssssssd',
                        $radiology_id,
                        $patient_id,
                        $patient_name,
                        $patient_type,
                        $gender,
                        $dob,
                        $exam_type,
                        $test_type,
                        $shift,
                        $price
                    );

                    if (!mysqli_stmt_execute($insert_stmt)) {
                        $msg = "Failed to save the radiology order. Please try again.";
                        break;
                    }

                    mysqli_stmt_close($insert_stmt);
                }
            }
        }

        if (empty($msg)) {
            $msg = "Radiology order saved successfully!";
        }
    }

    // Display the message using SweetAlert
    echo "
    <script src='https://cdn.jsdelivr.net/npm/sweetalert2@11'></script>
    <script>
        Swal.fire({
            title: '" . (strpos($msg, 'successfully') !== false ? 'Success!' : 'Error!') . "',
            text: '$msg',
            icon: '" . (strpos($msg, 'successfully') !== false ? 'success' : 'error') . "',
            confirmButtonColor: '#12369e',
            confirmButtonText: 'OK'
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = 'radiology-patients.php';
            }
        });
    </script>
    <style>
        /* Custom SweetAlert Button Color */
        .swal2-confirm {
            background-color: #12369e !important;
            color: white !important;
            border: none !important;
        }
        
        /* Hover color for the confirm button */
        .swal2-confirm:hover {
            background-color: #05007E !important;
        }
        
        /* Adjust button focus styles (optional) */
        .swal2-confirm:focus {
            box-shadow: 0 0 0 0.2rem rgba(18, 54, 158, 0.5) !important;
        }
    </style>
            ";
}
?>

<div class="page-wrapper">
    <div class="content">
        <div class="row">
            <div class="col-sm-4">
                <h4 class="page-title">Add Radiology</h4>
            </div>
            <div class="col-sm-8 text-right m-b-20">
                <a href="radiology-patients.php" class="btn btn-primary float-right"><i class="fa fa-arrow-left"></i> Back</a>
            </div>
        </div>
        <form method="POST" enctype="multipart/form-data">
            <div class="row">
                <div class="col-md-6">
                    <div class="form-group">
                        <label>Test ID <span class="text-danger">*</span></label>
                        <input class="form-control" type="text" name="radiology_id" value="<?php
                            echo $radiology_id;
                        ?>" disabled>
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
            </div>
            <div class="table-responsive">
                <div class="sticky-search">
                <h5 class="font-weight-bold mb-2">Search Patient:</h5>
                    <div class="input-group mb-3">
                        <div class="position-relative w-100">
                            <!-- Search Icon -->
                            <i class="fa fa-search position-absolute text-secondary" style="top: 50%; left: 12px; transform: translateY(-50%);"></i>
                            <!-- Input Field -->
                            <input class="form-control" type="text" id="radTestSearchInput" onkeyup="filterRadTests()" style="padding-left: 35px; padding-right: 35px;">
                            <!-- Clear Button -->
                            <button class="position-absolute border-0 bg-transparent text-secondary" type="button" onclick="clearSearch()" style="top: 50%; right: 10px; transform: translateY(-50%);">
                                <i class="fa fa-times"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            <div class="table-responsive">
                <table class="datatable table table-hover" id="radTestTable">
                    <thead style="background-color: #CCCCCC;">
                        <tr>
                            <th>Exam Type</th>
                            <th>Test Type</th>
                            <th>Price</th>
                            <th>Select</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $fetch_radtest_query = mysqli_query($connection, "SELECT exam_type, test_type, price FROM tbl_radtest");
                        while ($radtest_row = mysqli_fetch_array($fetch_radtest_query)) {
                        ?>
                            <tr>
                                <td><?php echo $radtest_row['exam_type']; ?></td>
                                <td><?php echo $radtest_row['test_type']; ?></td>
                                <td><?php echo number_format($radtest_row['price'], 2); ?></td>
                                <td><input type="checkbox" name="rad_tests[]" value="<?php echo $radtest_row['test_type']; ?>"></td>
                            </tr>
                        <?php } ?>
                    </tbody>
                </table>
            </div>
            <div class="m-t-20 text-center">
                <button type="submit" name="add-radiology" class="btn btn-primary submit-btn">Save</button>
            </div>
        </form>
    </div>
</div>

<?php include('footer.php'); ?>

<script type="text/javascript">
    // Validate Form
    function validateForm() {
        var checkboxes = document.querySelectorAll('input[name="rad_tests[]"]:checked');

        if (checkboxes.length === 0) {
            Swal.fire({
                icon: 'warning',
                title: 'No Radiology Test Selected',
                text: 'Please select at least one radiology test before proceeding.',
                confirmButtonColor: '#12369e',
                confirmButtonText: 'OK'
            });
            return false; 
        }
        return true;
    }
</script>

<script>
    function clearSearch() {
        document.getElementById("radTestSearchInput").value = '';
        filterRadTests();
    }  
    // Search functionality for Radiology Test Table
    function filterRadTests() {
        var input = document.getElementById("radTestSearchInput").value;
        
        $.ajax({
            url: 'fetch_radiology.php',
            method: 'GET',
            data: { query: input },
            success: function(response) {
                var data = JSON.parse(response);
                updateRadTestTable(data);
            }
        });
    }

    function updateRadTestTable(data) {
        var tbody = $('#radTestTable tbody');
        tbody.empty();
        
        data.forEach(function(row) {
            tbody.append(`
                <tr>
                    <td>${row.exam_type}</td>
                    <td>${row.test_type}</td>
                    <td>${row.price}</td>
                    <td><input type="checkbox" name="rad_tests[]" value="${row.test_type}"></td>
                </tr>
            `);
        });
    }


    // Search functionality for Patient Name
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
    border-radius: .375rem;
    border-color: #ced4da;
    background-color: #f8f9fa;
}
select.form-control {
    border-radius: .375rem;
    border: 1px solid #ced4da;
    background-color: #f8f9fa;
    padding: .375rem 2.5rem .375rem .75rem;
    font-size: 1rem;
    line-height: 1.5;
    height: calc(2.25rem + 2px);
    -webkit-appearance: none;
    -moz-appearance: none;
    appearance: none;
    background: url('data:image/svg+xml;charset=UTF-8,%3Csvg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="20" height="20"%3E%3Cpath d="M7 10l5 5 5-5z" fill="%23aaa"/%3E%3C/svg%3E') no-repeat right 0.75rem center;
    background-size: 20px;
}
select.form-control:focus {
    border-color: #12369e;
    box-shadow: 0 0 0 0.2rem rgba(38, 143, 255, .25);
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
