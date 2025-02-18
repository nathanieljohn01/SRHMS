<?php
session_start();
if (empty($_SESSION['name'])) {
    header('location:index.php');
    exit; // Stop further execution
}

include('header.php');
include('includes/connection.php');

// Define the sanitize function
function sanitize($connection, $input) {
    return mysqli_real_escape_string($connection, trim(htmlspecialchars($input, ENT_QUOTES, 'UTF-8')));
}

// Fetch max deceased ID
$stmt = $connection->prepare("SELECT MAX(id) AS id FROM tbl_deceased");
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();
$deceased_id = ($row['id'] == 0) ? 1 : $row['id'] + 1;

if (isset($_REQUEST['save-deceased'])) {
    $deceased_id = 'DC-' . $deceased_id;

    // Sanitize inputs
    $patient_name = sanitize($connection, $_REQUEST['patient_name']);
    $dod = sanitize($connection, DateTime::createFromFormat('d/m/Y', $_REQUEST['dod'])->format('F j, Y'));
    $tod = sanitize($connection, date("g:i A", strtotime($_REQUEST['tod'])));
    $cod = sanitize($connection, $_REQUEST['cod']);
    $physician = sanitize($connection, $_REQUEST['physician']);
    $next_of_kin_contact = sanitize($connection, $_REQUEST['next_of_kin_contact']);
    $discharge_status = sanitize($connection, $_REQUEST['discharge_status']);

    // Get patient ID based on the patient name
    $fetch_stmt = $connection->prepare("SELECT patient_id FROM tbl_patient WHERE CONCAT(first_name, ' ', last_name) = ? AND deleted = 0");
    $fetch_stmt->bind_param("s", $patient_name);
    $fetch_stmt->execute();
    $fetch_result = $fetch_stmt->get_result();
    $patient_row = $fetch_result->fetch_assoc();
    $patient_id = $patient_row['patient_id'];

    // Check if deceased with the same name already exists
    $check_stmt = $connection->prepare("SELECT * FROM tbl_deceased WHERE patient_name = ?");
    $check_stmt->bind_param("s", $patient_name);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();

    if ($check_result->num_rows > 0) {
        $msg = "Deceased with the same name already exists.";
        // SweetAlert error message for duplicate record
        echo "
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: '$msg'
                });
            });
        </script>";
    } else {
        // Insert new deceased record
        $insert_stmt = $connection->prepare("INSERT INTO tbl_deceased (deceased_id, patient_id, patient_name, dod, tod, cod, physician, next_of_kin_contact, discharge_status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $insert_stmt->bind_param("sssssssss", $deceased_id, $patient_id, $patient_name, $dod, $tod, $cod, $physician, $next_of_kin_contact, $discharge_status);
    
        if ($insert_stmt->execute()) {
            $msg = "Deceased added successfully";
            // SweetAlert success message for successful insertion
            echo "
            <script src='https://cdn.jsdelivr.net/npm/sweetalert2@10'></script>
            <script>
                document.addEventListener('DOMContentLoaded', function() {
                    var style = document.createElement('style');
                    style.innerHTML = '.swal2-confirm { background-color: #12369e !important; color: white !important; border: none !important; } .swal2-confirm:hover { background-color: #05007E !important; } .swal2-confirm:focus { box-shadow: 0 0 0 0.2rem rgba(18, 54, 158, 0.5) !important; }';
                    document.head.appendChild(style);
    
                    Swal.fire({
                        icon: 'success',
                        title: 'Success!',
                        text: '$msg',
                        confirmButtonColor: '#12369e'
                    }).then(() => {
                        window.location.href = 'deceased.php'; // Redirect to the deceased records page or any page you want
                    });
                });
            </script>";
        } else {
            $msg = "Error!";
            // SweetAlert error message for insertion failure
            echo "
            <script>
                document.addEventListener('DOMContentLoaded', function() {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: '$msg'
                    });
                });
            </script>";
        }
    
        $insert_stmt->close();
    }
    
    $check_stmt->close();
    $fetch_stmt->close();
    
}
?>

<div class="page-wrapper">
    <div class="content">
        <div class="row">
            <div class="col-sm-4">
                <h4 class="page-title">Add Deceased</h4>
            </div>
            <div class="col-sm-8 text-right mb-3">
                <a href="deceased.php" class="btn btn-primary float-right">Back</a>
            </div>
        </div>
        <div class="row">
            <div class="col-lg-8 offset-lg-2">
                <form method="post">
                    <div class="row">
                        <div class="col-sm-6">
                            <div class="form-group">
                                <label>Deceased ID</label>
                                <input class="form-control" type="text" name="deceased_id" value="<?php if(!empty($deceased_id)) { echo 'DC-' . $deceased_id; } else { echo 'DC-1'; } ?>" disabled>
                            </div>
                        </div>
                        <div class="col-sm-6">
                            <div class="form-group position-relative">
                                <label for="patient_name_search">Patient Name</label>
                                <input type="text" class="form-control" id="patient_name_search" name="patient_name" placeholder="Search for a patient" autocomplete="off" required>
                                <input type="hidden" id="patient_id" name="patient_id">
                                <!-- Search Results Container -->
                                <div id="patient_name_results" class="search-results"></div>
                            </div>
                        </div>
                        <div class="col-sm-6">
                            <div class="form-group">
                                <label>Date of Death</label>
                                <div class="cal-icon">
                                    <input type="text" class="form-control datetimepicker" name="dod" required>
                                </div>
                            </div>
                        </div>
                        <div class="col-sm-6">
                            <div class="form-group position-relative">
                                <label for="tod">Time of Death</label>
                                <input type="time" id="tod" class="form-control" name="tod" required>
                            </div>
                        </div>
                        <div class="col-sm-6">
                            <div class="form-group">
                                <label>Cause of Death</label>
                                <input class="form-control" type="text" name="cod">
                            </div>
                        </div>
                        <div class="col-sm-6">
                            <div class="form-group">
                                <label>Physician</label>
                                <select class="form-control" name="physician" required>
                                    <option value="">Select Physician</option>
                                    <?php
                                    $physician_query = mysqli_query($connection, "SELECT id, first_name, last_name FROM tbl_employee WHERE role = 2 AND specialization = 'Cardiologist'");
                                    while ($physician_row = mysqli_fetch_assoc($physician_query)) {
                                        $physician_name = $physician_row['first_name'] . ' ' . $physician_row['last_name'];
                                        echo "<option value='$physician_name'>$physician_name</option>";
                                    }
                                    ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-sm-6">
                            <div class="form-group">
                                <label>Next of Kin Contact</label>
                                <input class="form-control" type="text" name="next_of_kin_contact">
                            </div>
                        </div>
                        <div class="col-sm-6">
                            <div class="form-group">
                                <label>Discharge Status</label>
                                <input class="form-control" type="text" name="discharge_status">
                            </div>
                        </div>
                        <div class="col-12 mt-3 text-center">
                            <button class="btn btn-primary submit-btn" name="save-deceased">Save</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php 
include('footer.php');
?>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script type="text/javascript">
$(document).ready(function() {
    $('#patient_name_search').keyup(function() {
        var query = $(this).val();

        if (query.length > 2) { // Only search when 3 or more characters are typed
            $.ajax({
                url: 'search-patient.php',  // Path to your search PHP file
                method: 'GET',
                data: { query: query },
                success: function(data) {
                    $('#patient_name_results').html(data).show();
                }
            });
        } else {
            $('#patient_name_results').hide(); // Hide results if query length is less than 3
        }
    });

    $(document).on('click', '.patient-result', function() {
        var patientName = $(this).text();
        var patientId = $(this).data('id');
        $('#patient_name_search').val(patientName);
        $('#patient_id').val(patientId);
        $('#patient_name_results').hide();
    });

    $(document).click(function(e) {
        if (!$(e.target).closest('#patient_name_search').length) {
            $('#patient_name_results').hide();
        }
    });
});
</script>

<style>
.btn-primary.submit-btn {
        border-radius: 4px; 
        padding: 10px 20px;
        font-size: 16px;
    }    
/* Optional: Style the input field */
.form-control {
    border-radius: .375rem; /* Rounded corners */
    border-color: #ced4da; /* Border color */
    background-color: #f8f9fa; /* Background color */
}

.btn-primary {
    background: #12369e;
    border: none;
}
.btn-primary:hover {
    background: #05007E;
}
.form-group {
    position: relative;
}

.cal-icon {
    position: relative;
}

.cal-icon input {
    padding-right: 30px; /* Adjust the padding to make space for the icon */
}

.cal-icon::after {
    content: '\f073'; /* FontAwesome calendar icon */
    font-family: 'FontAwesome';
    position: absolute;
    right: 10px; /* Adjust this value to align the icon properly */
    top: 50%;
    transform: translateY(-50%);
    pointer-events: none;
    color: #aaa; /* Adjust color as needed */
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
         /* Search results styling */
 .search-results {
        max-height: 200px;
        overflow-y: auto;
        border: 1px solid #ddd;
        border-radius: 5px;
        display: none;
        background: #fff;
        position: absolute;
        z-index: 1000;
        width: 100%;
    }

    .search-results .patient-result {
        padding: 8px 12px;
        cursor: pointer;
        list-style: none;
        border-bottom: 1px solid #ddd;
    }

    .search-results .patient-result:hover {
        background-color: #12369e;
        color: white;
    }

    /* Styling for the search input field */
    #patient_name_search {
        padding-right: 30px;
    }

    #patient_name_search:focus {
        box-shadow: 0 0 5px rgba(63, 81, 181, 0.5);
    }

    /* Clear search icon inside the input (optional) */
    #patient_name_search + .clear-search {
        position: absolute;
        top: 50%;
        right: 10px;
        transform: translateY(-50%);
        cursor: pointer;
    }

</style>
