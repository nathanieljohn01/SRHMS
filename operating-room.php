<?php
session_start();
if(empty($_SESSION['name']))
{
    header('location:index.php');
}
include('header.php');
include('includes/connection.php');
?>

<div class="page-wrapper">
    <div class="content">
        <div class="row">
            <div class="col-sm-4 col-3">
                <h4 class="page-title">Operating Room Records</h4>
            </div>
            <div class="col-sm-8 col-9 text-right m-b-20">
                <?php 
                if ($_SESSION['role'] == 1 || $_SESSION['role'] == 3) {  
                    echo '<a href="add-operating-room.php" class="btn btn-primary float-right"><i class="fa fa-plus"></i> Add Operating Room </a>';
                }
                ?>
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
                        <input class="form-control" type="text" id="operatingRoomSearchInput" onkeyup="filterOperatingRooms()" style="padding-left: 35px; padding-right: 35px;">
                        <!-- Clear Button -->
                        <button class="position-absolute border-0 bg-transparent text-secondary" type="button" onclick="clearSearch()" style="top: 50%; right: 10px; transform: translateY(-50%);">
                            <i class="fa fa-times"></i>
                        </button>
                    </div>
                </div>
            </div>
        </div>
        <div class="table-responsive">
            <table class="datatable table table-hover" id="operatingRoomTable">
                <thead style="background-color: #CCCCCC;">
                    <tr>
                        <th>Patient ID</th>
                        <th>Patient Name</th>
                        <th>Operation Status</th>
                        <th>Current Surgery</th>
                        <th>Surgeon</th>
                        <th>Start Time</th>
                        <th>End Time</th>
                        <th>Notes</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    if(isset($_GET['ids'])){
                        try {
                            $id = mysqli_real_escape_string($connection, $_GET['ids']);
                            $delete_query = mysqli_query($connection, "DELETE FROM tbl_operating_room WHERE id='$id'");
                            
                            if ($delete_query) {
                                echo "<script>
                                    showSuccess('Operating room record deleted successfully!', true);
                                </script>";
                            } else {
                                throw new Exception(mysqli_error($connection));
                            }
                        } catch (Exception $e) {
                            echo "<script>
                                showError('Error deleting record: " . addslashes($e->getMessage()) . "');
                            </script>";
                        }
                    }
                    $fetch_query = mysqli_query($connection, "SELECT * FROM tbl_operating_room WHERE deleted = 0");
                    while($row = mysqli_fetch_array($fetch_query))
                    {
                    ?>
                        <tr>
                            <td><?php echo $row['patient_id']; ?></td>
                            <td><?php echo $row['patient_name']; ?></td>
                            <td><?php echo $row['operation_status']; ?></td>
                            <td><?php echo $row['current_surgery']; ?></td>
                            <td><?php echo $row['surgeon']; ?></td>
                            <td><?php echo $row['start_time']; ?></td>
                            <td><?php echo $row['end_time']; ?></td>
                            <td><?php echo $row['notes']; ?></td>
                            <td class="text-right">
                                <div class="dropdown dropdown-action">
                                    <a href="#" class="action-icon dropdown-toggle" data-toggle="dropdown" aria-expanded="false"><i class="fa fa-ellipsis-v"></i></a>
                                    <div class="dropdown-menu dropdown-menu-right">
                                    <?php 
                                    if ($_SESSION['role'] == 1 || $_SESSION['role'] == 3) {
                                        echo '<a class="dropdown-item" href="edit-operating-room.php?id='.$row['id'].'"><i class="fa fa-tasks m-r-5"></i> Update Progress</a>';
                                        echo '<a class="dropdown-item delete-btn" data-id="'.$row['id'].'" href="#"><i class="fa fa-trash-o m-r-5"></i> Delete</a>';
                                    }
                                    ?>
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
    return confirm('Are you sure you want to delete this Operating Room Record?');
}
</script>

<script>
    function clearSearch() {
        document.getElementById("operatingRoomSearchInput").value = '';
        filterOperatingRooms();
    }
    function filterOperatingRooms() {
        var input = document.getElementById("operatingRoomSearchInput").value;
        
        $.ajax({
            url: 'fetch_operating_room.php',
            method: 'GET',
            data: { query: input },
            success: function(response) {
                var data = JSON.parse(response);
                updateOperatingRoomsTable(data);
            }
        });
    }

    function updateOperatingRoomsTable(data) {
        var tbody = $('#operatingRoomTable tbody');
        tbody.empty();
        
        data.forEach(function(row) {
            let actionButtons = '';
            if (<?php echo $_SESSION['role']; ?> == 1 || <?php echo $_SESSION['role']; ?> == 3) {
                actionButtons = `
                    <a class="dropdown-item" href="edit-operating-room.php?id=${row.id}">
                        <i class="fa fa-tasks m-r-5"></i> Update Progress
                    </a>
                    <a class="dropdown-item delete-btn" data-id="${row.id}" href="#">
                        <i class="fa fa-trash-o m-r-5"></i> Delete
                    </a>
                `;
            }

            tbody.append(`
                <tr>
                    <td>${row.patient_id}</td>
                    <td>${row.patient_name}</td>
                    <td>${row.operation_status}</td>
                    <td>${row.current_surgery}</td>
                    <td>${row.surgeon}</td>
                    <td>${row.start_time}</td>
                    <td>${row.end_time}</td>
                    <td>${row.notes}</td>
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
</script>

<script>
$(document).ready(function() {
    // Handle form submission
    $('#operatingRoomForm').on('submit', function(e) {
        e.preventDefault();
        
        // Basic validation
        const required = ['patient_id', 'surgery_type', 'surgery_date', 'surgeon'];
        let isValid = true;
        
        required.forEach(field => {
            if (!$(`#${field}`).val()) {
                showError(`Please fill in ${field.replace('_', ' ')}`);
                isValid = false;
            }
        });
        
        if (!isValid) return;
        
        // Show loading state
        showLoading('Saving record...');
        
        // Submit the form
        this.submit();
    });
    
    // Handle delete confirmation
    $('.delete-btn').on('click', function(e) {
        e.preventDefault();
        const id = $(this).data('id');
        
        showConfirm(
            'Delete Record?',
            'Are you sure you want to delete this operating room record? This action cannot be undone!',
            () => {
                setTimeout(() => {
                    window.location.href = 'operating-room.php?ids=' + id;
                }, 500);
            }
        );
    });
    
    // Initialize datepicker with better UX
    $('.datetimepicker').datetimepicker({
        format: 'YYYY-MM-DD HH:mm',
        icons: {
            up: "fa fa-chevron-up",
            down: "fa fa-chevron-down",
            next: 'fa fa-chevron-right',
            previous: 'fa fa-chevron-left'
        },
        minDate: moment()
    });
    
    // Handle AJAX errors globally
    $(document).ajaxError(function(event, jqXHR, settings, error) {
        showError('Error fetching data. Please try again.');
    });
});

// Function to handle record deletion
function deleteRecord(id) {
    showConfirm(
        'Delete Record?',
        'Are you sure you want to delete this operating room record? This action cannot be undone!',
        () => {
            setTimeout(() => {
                window.location.href = 'operating-room.php?ids=' + id;
            }, 500);
        }
    );
    return false;
}

// Update onclick handlers in table
$(document).ready(function() {
    // Update delete links
    $('a[onclick*="confirm"]').each(function() {
        const id = $(this).attr('href').split('=')[1];
        $(this).attr('onclick', `return deleteRecord('${id}')`);
    });
});
</script>

<script>
function showSuccess(message, redirect) {
    Swal.fire({
        icon: 'success',
        title: 'Success',
        text: message,
        showConfirmButton: false,
        timer: 2000
    }).then(() => {
        if (redirect) {
            window.location.href = 'operating-room.php';
        }
    });
}

function showError(message) {
    Swal.fire({
        icon: 'error',
        title: 'Error',
        text: message,
        showConfirmButton: false,
        timer: 2000
    });
}

function showConfirm(title, message, callback) {
    Swal.fire({
        icon: 'warning',
        title: title,
        text: message,
        showCancelButton: true,
        confirmButtonText: 'Yes',
        cancelButtonText: 'No'
    }).then((result) => {
        if (result.value) {
            callback();
        }
    });
}

function showLoading(message) {
    Swal.fire({
        icon: 'info',
        title: 'Loading',
        text: message,
        showConfirmButton: false,
        allowOutsideClick: false
    });
}
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
</style>
