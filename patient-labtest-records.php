<?php
session_start();
if (empty($_SESSION['name'])) {
    header('location:index.php');
    exit;
}

include('header.php');
include('includes/connection.php');

if (isset($_GET['patient_id'])) {
    $patient_id = $_GET['patient_id'];

    // Fetch the number of lab test records for the patient using prepared statements
    $fetch_lab_tests_stmt = mysqli_prepare($connection, "SELECT COUNT(*) AS num_records FROM tbl_laborder WHERE patient_id = ?");
    mysqli_stmt_bind_param($fetch_lab_tests_stmt, 's', $patient_id);
    mysqli_stmt_execute($fetch_lab_tests_stmt);
    mysqli_stmt_bind_result($fetch_lab_tests_stmt, $num_records);
    mysqli_stmt_fetch($fetch_lab_tests_stmt);
    mysqli_stmt_close($fetch_lab_tests_stmt);

    // Check if there are no lab test records for the patient
    if ($num_records == 0) {
        echo "
        <script src='https://cdn.jsdelivr.net/npm/sweetalert2@11'></script>
        <link rel='stylesheet' href='https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css' />
        <script>
            Swal.fire({
                title: 'No Test Records Found',
                icon: 'info',
                confirmButtonColor: '#12369e',
                confirmButtonText: 'Back',
                backdrop: 'rgba(0, 0, 0, 0.3)',
                customClass: {
                    confirmButton: 'swal2-confirm-btn'
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = 'lab-order-patients.php';
                }
            });
        </script>";
        exit();
    }

    // Fetch the patient's name using a prepared statement
    $fetch_patient_stmt = mysqli_prepare($connection, "SELECT patient_name FROM tbl_laborder WHERE patient_id = ?");
    mysqli_stmt_bind_param($fetch_patient_stmt, 's', $patient_id);
    mysqli_stmt_execute($fetch_patient_stmt);
    mysqli_stmt_bind_result($fetch_patient_stmt, $patient_name);
    mysqli_stmt_fetch($fetch_patient_stmt);
    mysqli_stmt_close($fetch_patient_stmt);

    // If updating the lab test status
    if (isset($_POST['update_status'])) {
        $selected_action = $_POST['selected_action'];
        $laborder_id = $_POST['laborder_id'];
    
        echo "
        <script src='https://cdn.jsdelivr.net/npm/sweetalert2@11'></script>
        <link rel='stylesheet' href='https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css'/>
        <script>
            Swal.fire({
                title: 'Processing...',
                text: 'Updating lab test status...',
                allowOutsideClick: false,
                showConfirmButton: false,
                willOpen: () => {
                    Swal.showLoading();
                }
            });
        </script>";
    
        if ($selected_action == 'Cancelled' && isset($_POST['cancel_reason'])) {
            $cancel_reason = $_POST['cancel_reason'];
            $status = "Cancelled - Remarks: " . mysqli_real_escape_string($connection, $cancel_reason);
        } else {
            $status = $selected_action;
        }
    
        $update_stmt = mysqli_prepare($connection, "UPDATE tbl_laborder SET status = ?, update_date = NOW() WHERE id = ?");
        mysqli_stmt_bind_param($update_stmt, 'si', $status, $laborder_id);
        
        if (mysqli_stmt_execute($update_stmt)) {
            mysqli_stmt_close($update_stmt);
            echo "
            <script>
                Swal.fire({
                    icon: 'success',
                    title: 'Success!',
                    text: 'Lab test status updated successfully!',
                    confirmButtonColor: '#12369e',
                }).then((result) => {
                    window.location = 'patient-labtest-records.php?patient_id=" . $_GET['patient_id'] . "';
                });
            </script>";
            exit();
        }
        // Update the lab test status using a prepared statement
        $update_stmt = mysqli_prepare($connection, "UPDATE tbl_laborder SET status = ?, update_date = NOW() WHERE id = ?");
        mysqli_stmt_bind_param($update_stmt, 'si', $status, $laborder_id);
        mysqli_stmt_execute($update_stmt);
        mysqli_stmt_close($update_stmt);
    }
?>
<!-- HTML content starts here -->
<div class="page-wrapper">
    <div class="content">
        <div class="row align-items-center">
            <div class="col-sm-6">
                <h4 class="page-title">Laboratory Test Records</h4>
            </div>
            <div class="col-sm-6 text-right">
                <a href="lab-order-patients.php" class="btn btn-primary"><i class="fa fa-arrow-left"></i> Back</a>
            </div>
        </div>
        <div class="row mt-3">
            <div class="col-md-4">
                <div class="info-box">
                    <span class="info-label"><strong>Patient Name:</strong></span>
                    <span class="info-value"><?php echo htmlspecialchars($patient_name); ?></span>
                </div>
            </div>
        </div>
        <div class="row mt-3">
            <div class="col-md-12">
                <div class="form-group">
                    <div class="input-group">
                        <input type="text" id="labTestSearchInput" onkeyup="filterTests()" placeholder="Search for lab tests" class="form-control">
                            <div class="input-group-append">
                            <button class="btn btn-outline-primary" type="button" onclick="clearSearch()">
                                <i class="fa fa-times"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <!-- Table for lab tests -->
        <div class="table-responsive">
            <table class="datatable table table-hover" id="labTestTable">
                <thead style="background-color: #CCCCCC;">
                    <tr>
                        <th>Lab Tests</th>
                        <th>Lab Department</th>
                        <th>Category</th>
                        <th>Status</th>
                        <th>Requested Date</th>
                        <th>Update Date</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $fetch_lab_tests_query = mysqli_query($connection, "SELECT id, lab_test, lab_department, stat, status, requested_date, update_date FROM tbl_laborder WHERE patient_id = '$patient_id'");
                    while ($lab_test_row = mysqli_fetch_assoc($fetch_lab_tests_query)) {
                        $requested_date = date('F d, Y g:i A', strtotime($lab_test_row['requested_date']));
                        $update_date = $lab_test_row['update_date'] ? date('F d, Y g:i A', strtotime($lab_test_row['update_date'])) : '';
                    ?>
                        <tr>
                            <td><?php echo htmlspecialchars($lab_test_row['lab_test']); ?></td>
                            <td><?php echo htmlspecialchars($lab_test_row['lab_department']); ?></td>
                            <td><?php echo ($lab_test_row['stat'] == 'STAT') ? '<span class="custom-badge status-red">STAT</span>' : htmlspecialchars($lab_test_row['stat']); ?></td>
                            <td><?php echo htmlspecialchars($lab_test_row['status']); ?></td>
                            <td><?php echo htmlspecialchars($requested_date); ?></td>
                            <td><?php echo htmlspecialchars($update_date); ?></td>
                            <td>
                                <form method="post">
                                    <?php
                                    $disable_button = ($lab_test_row['status'] == 'Completed' || strpos($lab_test_row['status'], 'Cancelled') !== false || !empty($lab_test_row['update_date'])) ? 'disabled' : '';
                                    ?>
                                    <div class="dropdown action-dropdown">
                                        <button class="btn btn-link p-0" type="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false" <?php echo $disable_button; ?>>
                                            <i class="fa fa-ellipsis-v fa-lg"></i>
                                        </button>
                                        <div class="dropdown-menu dropdown-menu-left">
                                            <button type="submit" class="dropdown-item" name="selected_action" value="Completed" <?php echo $disable_button; ?>><i class="fa fa-check-circle m-r-5"></i> Completed</button>
                                            <button type="button" class="dropdown-item" data-toggle="modal" data-target="#cancelReasonModal_<?php echo $lab_test_row['id']; ?>" <?php echo $disable_button; ?>><i class="fa fa-times-circle m-r-5"></i> Cancelled</button>
                                        </div>
                                    </div>
                                    <input type="hidden" name="update_status" value="1">
                                    <input type="hidden" name="laborder_id" value="<?php echo $lab_test_row['id']; ?>">
                                </form>
                                
                                <!-- Modal -->
                                <div class="modal fade" id="cancelReasonModal_<?php echo $lab_test_row['id']; ?>" tabindex="-1" role="dialog" aria-labelledby="cancelReasonLabel_<?php echo $lab_test_row['id']; ?>" aria-hidden="true">
                                    <div class="modal-dialog" role="document">
                                        <div class="modal-content">
                                            <div class="modal-header">
                                                <h5 class="modal-title" id="cancelReasonLabel_<?php echo $lab_test_row['id']; ?>">Enter Cancellation Reason</h5>
                                                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                                    <span aria-hidden="true">&times;</span>
                                                </button>
                                            </div>
                                            <form method="post">
                                                <div class="modal-body">
                                                    <div class="form-group">
                                                        <label for="cancelReason_<?php echo $lab_test_row['id']; ?>">Reason</label>
                                                        <textarea name="cancel_reason" id="cancelReason_<?php echo $lab_test_row['id']; ?>" class="form-control" required></textarea>
                                                    </div>
                                                    <input type="hidden" name="update_status" value="1">
                                                    <input type="hidden" name="selected_action" value="Cancelled">
                                                    <input type="hidden" name="laborder_id" value="<?php echo $lab_test_row['id']; ?>">
                                                </div>
                                                <div class="modal-footer">
                                                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                                                    <button type="submit" class="btn btn-primary">Submit</button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    <?php } ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php
} else {
    header('location: index.php');
    exit();
}

include('footer.php');
?>

<script>
function clearSearch() {
    document.getElementById("labTestSearchInput").value = '';
    filterTests();
}
function filterTests() {
    var input, filter, table, tr, td, i, j, txtValue;
    input = document.getElementById("labTestSearchInput");
    filter = input.value.toUpperCase();
    table = document.getElementById("labTestTable");
    tr = table.getElementsByTagName("tr");

    // If DataTable is initialized, clear its search and use manual filtering
    if ($.fn.DataTable.isDataTable("#labTestTable")) {
        var labTestTableInstance = $('#labTestTable').DataTable();
        labTestTableInstance.search('').draw();  // Clear DataTable search
        labTestTableInstance.page.len(-1).draw();  // Show all rows temporarily
    }

    // Manual filtering logic
    for (i = 1; i < tr.length; i++) {  // Start from 1 to skip the header row
        tr[i].style.display = "none";  // Hide all rows initially
        td = tr[i].getElementsByTagName("td");
        for (j = 0; j < td.length; j++) {
            if (td[j]) {
                txtValue = td[j].textContent || td[j].innerText;
                if (txtValue.toUpperCase().indexOf(filter) > -1) {
                    tr[i].style.display = "";  // Show the row if a match is found
                    break;
                }
            }
        }
    }

    // Restore DataTable pagination if input is empty
    if (filter.trim() === "") {
        if ($.fn.DataTable.isDataTable("#labTestTable")) {
            var labTestTableInstance = $('#labTestTable').DataTable();
            labTestTableInstance.page.len(10).draw();  // Reset pagination to default
        }
    }
}

// Initialize DataTable
$(document).ready(function() {
    $('#labTestTable').DataTable();
});

$(document).ready(function() {
    // When clicking a dropdown button
    $('.action-dropdown .btn-link').on('click', function(e) {
        e.preventDefault();
        e.stopPropagation();
        
        // Close all other dropdowns first
        $('.action-dropdown .dropdown-menu').not($(this).next()).removeClass('show');
        
        // Toggle current dropdown
        $(this).next('.dropdown-menu').toggleClass('show');
    });

    // Close dropdown when clicking outside
    $(document).on('click', function(e) {
        if (!$(e.target).closest('.action-dropdown').length) {
            $('.dropdown-menu').removeClass('show');
        }
    });
});

</script>
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
.custom-badge {
    border-radius: 4px;
    display: inline-block;
    font-size: 12px;
    min-width: 95px;
    padding: 1px 10px;
    text-align: center;
}
.status-red,
a.status-red {
    background-color: #ffe5e6;
    border: 1px solid #fe0000;
    color: #fe0000;
}

@keyframes fadeInUp {
    from {
        opacity: 0;
        transform: translateY(30px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}
.swal2-confirm-btn {
    background-color: #12369e; /* Button background color */
    color: white; /* Button text color */
    border: none; /* Remove border */
    border-radius: 5px; /* Add border radius for rounded corners */
}
.info-box {
    background-color: #f9f9f9;
    border: 1px solid #ddd;
    padding: 10px 15px; /* Adjusted padding for better spacing */
    border-radius: 6px; /* Slightly larger border radius for a smoother look */
    display: flex;
    align-items: center;
    justify-content: space-between;
    font-size: 14px; /* Reduced font size */
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1); /* Added subtle shadow for depth */
    transition: background-color 0.3s ease; /* Smooth transition for background color */
}

.info-box:hover {
    background-color: #f1f1f1; /* Slightly darker background on hover */
}

.info-label {
    color: #333;
    font-weight: bold;
    margin-right: 10px; /* Added margin for spacing */
}

.info-value {
    color: #555;
}

.action-dropdown {
    position: relative;
    display: flex;
    align-items: right;
    justify-content: right;
}
.action-dropdown .dropdown-menu {
    position: absolute;
    left: -50px; /* This moves the box to the left */
    min-width: 120px;
    margin-top: 0;
    border-radius: 4px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.15);
}
.action-dropdown .btn-link {
    color: #333;
    font-size: 14px;
    padding: 2px 6px;
    transition: all 0.2s ease;
    line-height: 1;
    display: flex;
    align-items: center;
    justify-content: center;
    width: 24px;
    height: 24px;
}
.action-dropdown .btn-link:hover {
    color: #12369e;
    transform: scale(1.05);
}
.action-dropdown .dropdown-item {
    padding: 8px 15px;
}
.action-dropdown .btn-link[disabled] {
    color: #999;
    cursor: not-allowed;
    opacity: 0.6;
}
.action-dropdown .btn-link[disabled]:hover {
    transform: none;
}
.dropdown-item[disabled] {
    cursor: not-allowed;
    opacity: 0.6;
    pointer-events: none;
}
</style>