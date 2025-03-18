<?php
session_start();
ob_start();
if (empty($_SESSION['name'])) {
    header('location:index.php');
    exit;
}
include('header.php');
include('includes/connection.php');

// Function to sanitize inputs
function sanitize($connection, $input) {
    return mysqli_real_escape_string($connection, trim($input));
}

$role = isset($_SESSION['role']) ? $_SESSION['role'] : null;
$doctor_name = isset($_SESSION['name']) ? $_SESSION['name'] : null;

// Replace basic alerts with SweetAlert2 for form submission
if (isset($_POST['submit'])) {
    try {
        // Show loading state first
        echo "<script>showLoading('Saving schedule...');</script>";
        
        // Validate required fields
        $required_fields = ['doctor_id', 'schedule_date', 'start_time', 'end_time'];
        foreach ($required_fields as $field) {
            if (empty($_POST[$field])) {
                throw new Exception("Please fill in all required fields");
            }
        }
        
        // Additional validation for time slots
        $start_time = strtotime($_POST['start_time']);
        $end_time = strtotime($_POST['end_time']);
        if ($end_time <= $start_time) {
            throw new Exception("End time must be after start time");
        }
        
        // Process the form submission
        $insert_query = mysqli_query($connection, $insert_sql);
        
        if ($insert_query) {
            echo "<script>
                showSuccess('Schedule saved successfully!', true);
            </script>";
        } else {
            throw new Exception(mysqli_error($connection));
        }
    } catch (Exception $e) {
        echo "<script>
            showError('" . addslashes($e->getMessage()) . "');
        </script>";
    }
}

// Replace basic alerts with SweetAlert2 for deletion
if (isset($_GET['ids'])) {
    try {
        // Show loading state first
        echo "<script>showLoading('Processing request...');</script>";
        
        $id = mysqli_real_escape_string($connection, $_GET['ids']);
        $delete_query = mysqli_query($connection, "UPDATE tbl_schedule SET deleted = 1 WHERE schedule_id='$id'");
        
        if ($delete_query) {
            echo "<script>
                showSuccess('Schedule deleted successfully!', true);
            </script>";
        } else {
            throw new Exception(mysqli_error($connection));
        }
    } catch (Exception $e) {
        echo "<script>
            showError('Error deleting schedule: " . addslashes($e->getMessage()) . "');
        </script>";
    }
}
?>

<div class="page-wrapper">
    <div class="content">
        <div class="row">
            <div class="col-sm-4 col-3">
                <h4 class="page-title">Doctor's Schedule</h4>
            </div>
            <div class="col-sm-8 col-9 text-right m-b-20">
                <?php 
                // Check user role
                if ($_SESSION['role'] == 1) {
                    // Show Add Schedule button for roles 1 and 2
                    echo '<a href="add-schedule.php" class="btn btn-primary float-right"><i class="fa fa-plus"></i> Add Schedule</a>';
                }
                ?>
            </div>
        </div>
        <div class="table-responsive">
            <div class="sticky-search">
            <h5 class="font-weight-bold mb-2">Search Doctor:</h5>
                <div class="input-group mb-3">
                    <div class="position-relative w-100">
                        <!-- Search Icon -->
                        <i class="fa fa-search position-absolute text-secondary" style="top: 50%; left: 12px; transform: translateY(-50%);"></i>
                        <!-- Input Field -->
                        <input class="form-control" type="text" id="scheduleSearchInput" onkeyup="filterSchedule()" style="padding-left: 35px; padding-right: 35px;">
                        <!-- Clear Button -->
                        <button class="position-absolute border-0 bg-transparent text-secondary" type="button" onclick="clearSearch()" style="top: 50%; right: 10px; transform: translateY(-50%);">
                            <i class="fa fa-times"></i>
                        </button>
                    </div>
                </div>
            </div>
        </div>
        <div class="table-responsive">
            <table class="datatable table table-hover" id="scheduleTable">
                <thead style="background-color: #CCCCCC;">
                    <tr>
                        <th>Doctor Name</th>    
                        <th>Doctor Specialist</th>    
                        <th>Available Days</th>
                        <th>Available Time</th>
                        <th>Message</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    // Filter schedules by doctor_name if role is 2 (doctor)
                    if ($role == 2) {
                        $fetch_query = $connection->prepare("SELECT * FROM tbl_schedule WHERE deleted = 0 AND doctor_name = ?");
                        $fetch_query->bind_param("s", $doctor_name);
                    } else {
                        $fetch_query = $connection->prepare("SELECT * FROM tbl_schedule WHERE deleted = 0");
                    }

                    $fetch_query->execute();
                    $result = $fetch_query->get_result();

                    while ($row = $result->fetch_assoc()) {
                        $status = $row['status'] == 1 ? 'Available' : 'Not Available';
                        $status_class = $row['status'] == 1 ? 'status-green' : 'status-red';
                    ?>
                    <tr>
                        <td><?php echo $row['doctor_name']; ?></td>
                        <td><?php echo $row['specialization']; ?></td>
                        <td><?php echo $row['available_days']; ?></td>
                        <td><?php echo $row['start_time'] . ' - ' . $row['end_time']; ?></td>
                        <td><?php echo $row['message']; ?></td>
                        <td><span class="custom-badge <?php echo $status_class; ?>"><?php echo $status; ?></span></td>
                        <td class="text-right">
                            <div class="dropdown dropdown-action">
                                <a href="#" class="action-icon dropdown-toggle" data-toggle="dropdown" aria-expanded="false"><i class="fa fa-ellipsis-v"></i></a>
                                <div class="dropdown-menu dropdown-menu-right">
                                    <?php if ($role == 2 && $doctor_name == $row['doctor_name']) { ?>
                                        <a class="dropdown-item" href="edit-schedule.php?id=<?php echo $row['id']; ?>"><i class="fa fa-pencil m-r-5"></i> Edit</a>
                                    <?php } ?>
                                    <?php if ($role == 1) { ?>
                                        <a class="dropdown-item" href="edit-schedule.php?id=<?php echo $row['id']; ?>"><i class="fa fa-pencil m-r-5"></i> Edit</a>
                                        <a class="dropdown-item delete-btn" data-id="<?php echo $row['id']; ?>"><i class="fa fa-trash-o m-r-5"></i> Delete</a>
                                    <?php } ?>
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
include('footer.php');
?>
<script language="JavaScript" type="text/javascript">
    function confirmDelete(){
        return confirm('Are you sure you want to delete this Schedule?');
    }
</script>
<script>
    function filterSchedule() {
    var input, filter, table, tr, td, i, j, txtValue;
    input = document.getElementById("scheduleSearchInput");
    filter = input.value.toUpperCase();
    table = document.getElementById("scheduleTable");
    tr = table.getElementsByTagName("tr");

    // If DataTable is initialized, clear its search and use manual filtering
    if ($.fn.DataTable.isDataTable("#scheduleTable")) {
        var scheduleTableInstance = $('#scheduleTable').DataTable();
        scheduleTableInstance.search('').draw();  // Clear DataTable search
        scheduleTableInstance.page.len(-1).draw();  // Show all rows temporarily
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
        if ($.fn.DataTable.isDataTable("#scheduleTable")) {
            var scheduleTableInstance = $('#scheduleTable').DataTable();
            scheduleTableInstance.page.len(10).draw();  // Reset pagination to default
        }
    }
}

// Initialize DataTable
$(document).ready(function() {
    $('#scheduleTable').DataTable();
});

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
    background-color:rgb(255, 255, 255);
    border: 1px solid rgb(228, 228, 228);
    color: gray;
} 
.btn-primary {
    background: #12369e;
    border: none;
}
.btn-primary:hover {
    background: #05007E;
}
.dropdown-item {
    padding: 7px 15px;
    color: #333;
}

.dropdown-item:hover {
    background-color: #f8f9fa;
    color: #12369e;
}

.dropdown-item i {
    margin-right: 8px;
    color: #777;
}

.dropdown-item:hover i {
    color: #12369e;
}
</style>
