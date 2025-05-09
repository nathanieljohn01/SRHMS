<?php
session_start();
if (empty($_SESSION['name'])) {
    header('location:index.php');
}
include('header.php');
include('includes/connection.php');

if (isset($_GET['msg'])) {
    $msg = $_GET['msg'];
    echo "<script>alert('$msg');</script>";
}
?>

<!-- Include SweetAlert CSS and JS -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.all.min.js"></script>

<div class="page-wrapper">
    <div class="content">
        <div class="row">
            <div class="col-sm-4 col-3">
                <h4 class="page-title">Housekeeping Schedule</h4>
            </div>
            <div class="col-sm-8 col-9 text-right m-b-20">
                <a href="add-housekeeping-schedule.php" class="btn btn-primary float-right"><i class="fa fa-plus"></i> Add Schedule</a>
            </div>
        </div>
        <div class="table-responsive">
        <h5 class="font-weight-bold mb-2">Search Room:</h5>
            <div class="input-group mb-3">
                <div class="position-relative w-100">
                    <!-- Search Icon -->
                    <i class="fa fa-search position-absolute text-secondary" style="top: 50%; left: 12px; transform: translateY(-50%);"></i>
                    <!-- Input Field -->
                    <input class="form-control" type="text" id="patientSearchInput" onkeyup="filterSchedules()" placeholder="Search" style="padding-left: 35px; padding-right: 35px;">
                    <!-- Clear Button -->
                    <button class="position-absolute border-0 bg-transparent text-secondary" type="button" onclick="clearSearch()" style="top: 50%; right: 10px; transform: translateY(-50%);">
                        <i class="fa fa-times"></i>
                    </button>
                </div>
            </div>
        </div>
        <div class="table-responsive">
            <table class="datatable table table-hover" id="patientTable">
                <thead style="background-color: #CCCCCC;">
                    <tr>
                        <th>Room Type</th>
                        <th>Room Number</th>
                        <th>Bed Number</th>
                        <th>Schedule Date</th>
                        <th>Task Description</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    if (isset($_GET['ids'])) {
                        $id = intval($_GET['ids']); 
                        if ($id) {
                            $update_query = mysqli_prepare($connection, "UPDATE tbl_housekeeping_schedule SET deleted = 1 WHERE id = ?");
                            mysqli_stmt_bind_param($update_query, "i", $id);
                            mysqli_stmt_execute($update_query);
                            mysqli_stmt_close($update_query);
                        }
                    }
                    $fetch_query = mysqli_query($connection, "SELECT * FROM tbl_housekeeping_schedule WHERE deleted = 0");
                    while ($row = mysqli_fetch_array($fetch_query)) {
                        $bed_query = mysqli_query($connection, "SELECT status FROM tbl_bedallocation WHERE room_number='{$row['room_number']}' AND bed_number='{$row['bed_number']}'");
                        $bed_row = mysqli_fetch_assoc($bed_query);
                        $isEditable = ($bed_row && $bed_row['status'] === 'Available') ? 'disabled' : '';
                        $isDisabled = ($bed_row && $bed_row['status'] === 'Available') ? 'disabled' : '';
                        $schedule_date_time = date('F d Y g:i A', strtotime($row['schedule_date_time']));
                    ?>
                        <tr>
                            <td><?php echo $row['room_type']; ?></td>
                            <td><?php echo $row['room_number']; ?></td>
                            <td><?php echo $row['bed_number']; ?></td>
                            <td><?php echo $schedule_date_time; ?></td>
                            <td><?php echo $row['task_description']; ?></td>
                            <td class="text-right">
                                <div class="dropdown dropdown-action">
                                    <a href="#" class="action-icon dropdown-toggle" data-toggle="dropdown" aria-expanded="false"><i class="fa fa-ellipsis-v"></i></a>
                                    <div class="dropdown-menu dropdown-menu-right">
                                    <?php if ($_SESSION['role'] == 1 || $_SESSION['role'] == 3): ?>   
                                    <a class="dropdown-item <?php echo $isDisabled; ?>" href="#" onclick="return confirmCompletion(<?php echo $row['id']; ?>);">
                                        <i class="fa fa-check m-r-5"></i> Complete Task
                                    </a>
                                    <?php endif; ?>
                                        <a class="dropdown-item edit-link <?php echo $isEditable; ?>" href="edit-housekeeping-schedule.php?id=<?php echo $row['id']; ?>"><i class="fa fa-pencil m-r-5"></i> Edit</a>
                                        <?php if ($_SESSION['role'] == 1): ?>   
                                        <a class="dropdown-item" href="#" onclick="return confirmDelete('<?php echo $row['id']; ?>')"><i class="fa fa-trash m-r-5"></i> Delete </a>
                                        <?php endif; ?>
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

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script language="JavaScript" type="text/javascript">
    function confirmCompletion(id) {
        return Swal.fire({
            title: 'Mark Task as Complete?',
            text: 'Are you sure you want to mark this task as completed?',
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#12369e',
            confirmButtonText: 'Yes',
            showLoaderOnConfirm: true,
            preConfirm: () => {
                return fetch(`complete-housekeeping.php?id=${id}`)
                    .then(response => {
                        if (!response.ok) {
                            throw new Error(response.statusText);
                        }
                        return response.json();
                    })
                    .catch(error => {
                        Swal.showValidationMessage(
                            `Request failed: ${error}`
                        );
                    });
            },
            allowOutsideClick: () => !Swal.isLoading()
        }).then((result) => {
            if (result.isConfirmed) {
                Swal.fire(
                    'Completed!',
                    'The task has been marked as completed.',
                    'success'
                ).then(() => {
                    location.reload();
                });
            }
        });
    }
    function confirmDelete(id) {
        return Swal.fire({
            title: 'Delete Housekeeping Record?',
            text: 'Are you sure you want to delete this Housekeeping record? This action cannot be undone!',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#12369e',
            confirmButtonText: 'OK'
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = 'housekeeping-schedule.php?ids=' + id;  
            }
        });
    }


    var userRole = <?php echo json_encode($_SESSION['role']); ?>;

    function filterSchedules() {
        var input = document.getElementById("patientSearchInput").value;
        
        $.ajax({
            url: 'fetch_housekeeping_schedule.php',
            type: 'GET',
            data: { query: input },
            success: function(response) {
                var data = JSON.parse(response);
                updateScheduleTable(data);
            },
            error: function(xhr, status, error) {
                alert('Error fetching data. Please try again.');
            }
        });
    }

    function updateScheduleTable(data) {
        var tbody = $('#patientTable tbody');
        tbody.empty();
        
        data.forEach(function(record) {
            // Check if bed is available to determine if buttons should be disabled
            var isEditable = record.bed_status === 'Available' ? 'disabled' : '';
            var isDisabled = record.bed_status === 'Available' ? 'disabled' : '';
            
            // Create action buttons based on user role
            var actionButtons = '';
            
            // Complete Task button for roles 1 and 3
            if (userRole == 1 || userRole == 3) {
                actionButtons += `
                    <a class="dropdown-item ${isDisabled}" href="#" onclick="return confirmCompletion(${record.id});">
                        <i class="fa fa-check m-r-5"></i> Complete Task
                    </a>
                `;
            }
            
            // Edit button for all roles
            actionButtons += `
                <a class="dropdown-item edit-link ${isEditable}" href="edit-housekeeping-schedule.php?id=${record.id}">
                    <i class="fa fa-pencil m-r-5"></i> Edit
                </a>
            `;
            
            // Delete button only for role 1 (admin)
            if (userRole == 1) {
                actionButtons += `
                    <a class="dropdown-item" href="#" onclick="return confirmDelete('${record.id}')">
                        <i class="fa fa-trash m-r-5"></i> Delete
                    </a>
                `;
            }
            
            tbody.append(`
                <tr>
                    <td>${record.room_type}</td>
                    <td>${record.room_number}</td>
                    <td>${record.bed_number}</td>
                    <td>${record.schedule_date_time}</td>
                    <td>${record.task_description}</td>
                    <td class="text-right">
                        <div class="dropdown dropdown-action">
                            <a href="#" class="action-icon dropdown-toggle" data-toggle="dropdown" aria-expanded="false">
                                <i class="fa fa-ellipsis-v"></i>
                            </a>
                            <div class="dropdown-menu dropdown-menu-right">
                                ${actionButtons}
                            </div>
                        </div>
                    </td>
                </tr>
            `);
        });
    }

    function clearSearch() {
        document.getElementById("patientSearchInput").value = '';
        filterSchedules();
    }

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
.btn-primary {
    background: #12369e;
    border: none;
}
.btn-primary:hover {
    background: #1342C6;
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

<script>
    function clearSearch() {
        document.getElementById("patientSearchInput").value = '';
        filterpatients();
    }  
    function filterpatients() {
        var input, filter, table, tr, td, i, j, txtValue;
        input = document.getElementById("patientSearchInput");
        filter = input.value.toUpperCase();
        table = document.getElementById("patientTable");
        tr = table.getElementsByTagName("tr");

        // Disable DataTable's default search functionality
        if ($.fn.DataTable.isDataTable("#patientTable")) {
            var hemoTableInstance = $('#patientTable').DataTable();
            hemoTableInstance.search('').draw();  // Clear existing search
            hemoTableInstance.page.len(-1).draw();  // Show all rows temporarily
        }

        // Manual filtering
        for (i = 0; i < tr.length; i++) {
            var matchFound = false;
            for (j = 0; j < tr[i].cells.length; j++) {
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
                tr[i].style.display = ""; // Show matching rows
            } else {
                tr[i].style.display = "none"; // Hide non-matching rows
            }
        }

        // Re-enable pagination when input is cleared
        if (filter.trim() === "") {
            if ($.fn.DataTable.isDataTable("#patientTable")) {
                var hemoTableInstance = $('#patientTable').DataTable();
                hemoTableInstance.page.len(10).draw(); // Reset pagination length
            }
        }
    }

    // Initialize DataTable
    $(document).ready(function() {
        $('#patientTable').DataTable();
    });

</script>

<script>
function confirmCompletion(id) {
    Swal.fire({
        title: 'Are you sure?',
        text: "Do you want to mark this task as complete?",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#12369e',
        cancelButtonColor: '#f62d51',
        confirmButtonText: 'Yes, complete it!'
    }).then((result) => {
        if (result.isConfirmed) {
            window.location.href = 'complete-housekeeping.php?id=' + id;
        }
    })
}
</script>