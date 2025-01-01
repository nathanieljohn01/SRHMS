<?php
session_start();
if (empty($_SESSION['name'])) {
    header('location:index.php');
    exit();
}
include('header.php');
include('includes/connection.php');

// Function to sanitize user input
function sanitize_input($connection, $input) {
    return mysqli_real_escape_string($connection, htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8'));
}

// Handle form submission
if (isset($_REQUEST['add-operating-room'])) {
    // Sanitize inputs
    $patient_id = sanitize_input($connection, $_REQUEST['patient_id']);
    
    // Fetch patient name based on selected patient_id using prepared statement
    $patient_query = mysqli_prepare($connection, "SELECT CONCAT(first_name, ' ', last_name) AS patient_name FROM tbl_patient WHERE patient_id = ?");
    mysqli_stmt_bind_param($patient_query, 's', $patient_id); // Binding parameter for patient_id
    mysqli_stmt_execute($patient_query);
    $patient_result = mysqli_stmt_get_result($patient_query);
    $patient_row = mysqli_fetch_assoc($patient_result);
    $patient_name = $patient_row['patient_name'];

    // Sanitize and prepare other inputs
    $current_surgery = sanitize_input($connection, $_REQUEST['current_surgery']);
    $surgeon = sanitize_input($connection, $_REQUEST['surgeon']);
    $start_time = date("g:i A", strtotime(sanitize_input($connection, $_REQUEST['start_time'])));
    $notes = sanitize_input($connection, $_REQUEST['notes']);

    // Check if the patient with the same name already exists in the operating room using prepared statement
    $check_query = mysqli_prepare($connection, "SELECT * FROM tbl_operating_room WHERE patient_name = ?");
    mysqli_stmt_bind_param($check_query, 's', $patient_name);
    mysqli_stmt_execute($check_query);
    $check_result = mysqli_stmt_get_result($check_query);

    if (mysqli_num_rows($check_result) > 0) {
        $msg = "Patient with the same name already exists in the operating room.";
    } else {
        // Insert new operating room record using prepared statement
        $insert_query = mysqli_prepare($connection, "INSERT INTO tbl_operating_room (patient_id, patient_name, current_surgery, surgeon, start_time, notes) VALUES (?, ?, ?, ?, ?, ?)");
        mysqli_stmt_bind_param($insert_query, 'ssssss', $patient_id, $patient_name, $current_surgery, $surgeon, $start_time, $notes);

        if (mysqli_stmt_execute($insert_query)) {
            // Now update the operation_status to 'In-progress' after insertion using prepared statement
            $update_status_query = mysqli_prepare($connection, "UPDATE tbl_operating_room SET operation_status = 'In-Progress' WHERE patient_id = ?");
            mysqli_stmt_bind_param($update_status_query, 's', $patient_id);
            
            if (mysqli_stmt_execute($update_status_query)) {
                $msg = "Operating room record added successfully, and operation status updated to 'In-Progress'.";
            } else {
                $msg = "Error updating operation status!";
            }
        } else {
            $msg = "Error adding the operating room record!";
        }
    }

    // Close prepared statements
    mysqli_stmt_close($patient_query);
    mysqli_stmt_close($check_query);
    mysqli_stmt_close($insert_query);
    mysqli_stmt_close($update_status_query);
}
?>

<div class="page-wrapper">
    <div class="content">
        <div class="row">
            <div class="col-sm-4">
                <h4 class="page-title">Add Operating Room</h4>
            </div>
            <div class="col-sm-8 text-right m-b-20">
                <a href="operating-room.php" class="btn btn-primary btn-rounded float-right">Back</a>
            </div>
        </div>

        <!-- Form Section -->
        <div class="row">
            <div class="col-lg-8 offset-lg-2">
                <!-- Operating Room Form -->
                <form method="post">
                    <!-- Patient Selection -->
                    <div class="row">
                        <div class="col-md-12">
                            <div class="form-group position-relative">
                                <label for="patient_name_search">Patient Name</label>
                                <input type="text" class="form-control" id="patient_name_search" name="patient_name" placeholder="Search for a patient" autocomplete="off" required>
                                <input type="hidden" id="patient_id" name="patient_id">
                                <!-- Search Results Container -->
                                <div id="patient_name_results" class="search-results"></div>
                            </div>
                        </div>
                    </div>

                    <!-- Current Surgery and Surgeon Selection -->
                    <div class="row">
                        <!-- Current Surgery -->
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Current Surgery</label>
                                <input type="text" class="form-control" name="current_surgery" required>
                            </div>
                        </div>

                        <!-- Surgeon Selection -->
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Surgeon</label>
                                <select class="select form-control" name="surgeon" required>
                                    <option value="">Select Surgeon</option>
                                    <?php
                                    // Fetch doctors with role=2 and display their name with specialization
                                    $fetch_surgeon_query = mysqli_query($connection, "SELECT CONCAT(first_name, ' ', last_name, ' - ', specialization) AS name_specialization FROM tbl_employee WHERE role=2 AND status=1");
                                    
                                    if (!$fetch_surgeon_query) {
                                        echo '<option value="">Error fetching surgeons</option>';
                                    } else {
                                        while ($row = mysqli_fetch_array($fetch_surgeon_query)) {
                                            echo '<option>' . htmlspecialchars($row['name_specialization']) . '</option>';
                                        }
                                    }
                                    ?>
                                </select>
                            </div>
                        </div>
                    </div>


                    <!-- Start Time -->
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group position-relative">
                                <label for="start_time">Start Time</label>
                                <div class="input-icon">
                                    <input type="time" id="start_time" class="form-control" name="start_time" required>
                                </div>
                            </div>
                        </div>

                    <!-- Notes -->
                    <div class="col-md-6">
                        <div class="form-group">
                                <label>Notes</label>
                                <textarea cols="30" rows="4" class="form-control" name="notes"></textarea>
                            </div>
                        </div>
                    </div>

                    <!-- Submit Button -->
                    <div class="m-t-20 text-center">
                        <button class="btn btn-primary submit-btn" name="add-operating-room">Add Operating Room</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- CSS -->
<style>
    /* Start Time Styling */
    .position-relative {
        position: relative;
    }

    .input-icon {
        position: relative;
    }

    .time-icon {
        padding-left: 40px;
    }

    .time-icon-input {
        position: absolute;
        top: 10px;
        left: 10px;
        color: #666;
    }

    /* Icon Styling */
    .fas.fa-clock {
        font-size: 1.2rem;
    }
    
    /* Form Layout */
    .form-group {
        margin-bottom: 20px;
    }

    /* Submit Button */
    .submit-btn {
        background-color: #12369e;
        border: none;
    }

    .submit-btn:hover {
        background-color: #05007E;
    }
</style>


<?php
include('footer.php');
?>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script type="text/javascript">
    <?php
        if(isset($msg)) {
            echo 'swal("' . $msg . '");';
        }
    ?>

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
.btn-primary {
    background: #12369e;
    border: none;
}
.btn-primary:hover {
    background: #05007E;
}
.time-icon {
    position: relative;
}

.time-icon input {
    padding-right: 30px; /* Adjust the padding to make space for the icon */
}

.time-icon::after {
    position: absolute;
    right: 10px; /* Adjust this value to align the icon properly */
    top: 50%;
    transform: translateY(-50%);
    pointer-events: none;
    color: #aaa; /* Adjust color as needed */
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
