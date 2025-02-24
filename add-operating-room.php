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

if (isset($_REQUEST['add-operating-room'])) {
    // Sanitize and prepare inputs
    $patient_id = sanitize_input($connection, $_REQUEST['patient_id']);
    
    // Fetch patient name based on selected patient_id using prepared statement
    $patient_query = mysqli_prepare($connection, "SELECT CONCAT(first_name, ' ', last_name) AS patient_name FROM tbl_patient WHERE patient_id = ?");
    mysqli_stmt_bind_param($patient_query, 's', $patient_id); 
    mysqli_stmt_execute($patient_query);
    $patient_result = mysqli_stmt_get_result($patient_query);
    $patient_row = mysqli_fetch_assoc($patient_result);
    $patient_name = $patient_row['patient_name'];

    // Sanitize other inputs
    $current_surgery = sanitize_input($connection, $_REQUEST['current_surgery']);
    $surgeon = sanitize_input($connection, $_REQUEST['surgeon']);
    $start_time = date("g:i A", strtotime(sanitize_input($connection, $_REQUEST['start_time'])));
    $notes = sanitize_input($connection, $_REQUEST['notes']);
    $operation_type = sanitize_input($connection, $_REQUEST['operation_type']);

    // Fetch the price for the selected operation type
    $fetch_price_query = mysqli_prepare($connection, "SELECT operation_type, price FROM tbl_operation WHERE id = ?");
    mysqli_stmt_bind_param($fetch_price_query, 'i', $operation_type);
    mysqli_stmt_execute($fetch_price_query);
    mysqli_stmt_store_result($fetch_price_query);
    mysqli_stmt_bind_result($fetch_price_query, $operation_type, $price);

    // Check if any rows were returned
    if (mysqli_stmt_num_rows($fetch_price_query) > 0) {
        mysqli_stmt_fetch($fetch_price_query);
    } else {
        $msg = "Invalid operation type selected.";
        $msg = addslashes($msg); // Escape the message for JavaScript
        echo "
        <script src='https://cdn.jsdelivr.net/npm/sweetalert2@10'></script>
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: '$msg'
                });
            });
        </script>";
        exit;
    }
    
    // Insert new operating room record using prepared statement
    $insert_query = mysqli_prepare($connection, "INSERT INTO tbl_operating_room (patient_id, patient_name, current_surgery, surgeon, start_time, notes, operation_type, price) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    mysqli_stmt_bind_param($insert_query, 'sssssssd', $patient_id, $patient_name, $current_surgery, $surgeon, $start_time, $notes, $operation_type, $price);

    if (mysqli_stmt_execute($insert_query)) {
        // Update operation status to 'In-progress'
        $update_status_query = mysqli_prepare($connection, "UPDATE tbl_operating_room SET operation_status = 'In-Progress' WHERE patient_id = ?");
        mysqli_stmt_bind_param($update_status_query, 's', $patient_id);
    
        if (mysqli_stmt_execute($update_status_query)) {
            // Success message
            $msg = "Operating room record added successfully.";
            $msg = addslashes($msg); // Escape the message for JavaScript
            echo "
            <script src='https://cdn.jsdelivr.net/npm/sweetalert2@10'></script>
            <script>
                var style = document.createElement('style');
                style.innerHTML = '.swal2-confirm { background-color: #12369e !important; color: white !important; border: none !important; } .swal2-confirm:hover { background-color: #05007E !important; } .swal2-confirm:focus { box-shadow: 0 0 0 0.2rem rgba(18, 54, 158, 0.5) !important; }';
                document.head.appendChild(style);

                // Trigger SweetAlert
                Swal.fire({
                    icon: 'success',
                    title: 'Success!',
                    text: '$msg',
                    confirmButtonColor: '#12369e'
                }).then(() => {
                    window.location.href = 'operating-room.php'; // Redirect after SweetAlert
                });
            </script>";
            exit; // Make sure the script stops after this point
        } else {
            // Error updating operation status
            $msg = "Error updating operation status!";
            $msg = addslashes($msg); // Escape the message for JavaScript
            echo "
            <script src='https://cdn.jsdelivr.net/npm/sweetalert2@10'></script>
            <script>
                document.addEventListener('DOMContentLoaded', function() {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: '$msg'
                    });
                });
            </script>";
            exit; // Make sure the script stops here as well
        }
    } else {
        // Error inserting operating room record
        $msg = "Error adding the operating room record!";
        $msg = addslashes($msg); // Escape the message for JavaScript
        echo "
        <script src='https://cdn.jsdelivr.net/npm/sweetalert2@10'></script>
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: '$msg'
                });
            });
        </script>";
        exit; // Ensure script stops here if this block executes
    }
    
    // Close prepared statements
    mysqli_stmt_close($patient_query);
    mysqli_stmt_close($fetch_price_query);
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
                <a href="operating-room.php" class="btn btn-primary float-right"><i class="fa fa-arrow-left"></i> Back</a>
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
                                <label>Surgery Name</label>
                                <input type="text" class="form-control" name="current_surgery" required>
                            </div>
                        </div>

                        <!-- Surgeon Selection -->
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Surgeon</label>
                                <select class="form-control" name="surgeon" required>
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
                
                    <!-- Operation Type and Start Time -->
                    <div class="row">
                        <!-- Operation Type -->
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Operation Type</label>
                                <select class="form-control" name="operation_type" id="operation_type" required>
                                    <option value="">Select Operation Type</option>
                                    <?php
                                    // Fetch operation types and prices from the database
                                    $fetch_operations_query = mysqli_query($connection, "SELECT id, operation_type, price FROM tbl_operation");

                                    if (!$fetch_operations_query) {
                                        echo '<option value="">Error fetching operations</option>';
                                    } else {
                                        while ($row = mysqli_fetch_array($fetch_operations_query)) {
                                            echo '<option value="' . $row['id'] . '" data-price="' . $row['price'] . '">' . htmlspecialchars($row['operation_type']) . '</option>';
                                        }
                                    }
                                    ?>
                                </select>
                            </div>
                        </div>

                        <!-- Start Time -->
                        <div class="col-md-6">
                            <div class="form-group position-relative">
                                <label for="start_time">Start Time</label>
                                <div class="input-icon">
                                    <input type="time" id="start_time" class="form-control" name="start_time" required>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Notes -->
                    <div class="row">
                        <div class="col-md-12">
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
