<?php
session_start();
if(empty($_SESSION['name'])) {
    header('location:index.php');
}
include('header.php');
include('includes/connection.php');
?>
<div class="page-wrapper">
    <div class="content">
        <div class="row">
            <div class="col-sm-4 col-3">
                <h4 class="page-title">Employees</h4>
            </div>
            <?php if ($_SESSION['role'] == 1) { ?>
            <div class="col-sm-8 col-9 text-right m-b-20">
                <a href="add-employee.php" class="btn btn-primary float-right"><i class="fa fa-plus"></i> Add Employee</a>
            </div>
            <?php } ?>
        </div>
        <div class="table-responsive">
        <h5 class="font-weight-bold mb-2">Search Employees:</h5>
            <div class="input-group mb-3">
                <div class="position-relative w-100">
                    <!-- Search Icon -->
                    <i class="fa fa-search position-absolute text-secondary" style="top: 50%; left: 12px; transform: translateY(-50%);"></i>
                    <!-- Input Field -->
                    <input class="form-control" type="text" id="employeeSearchInput" onkeyup="filterEmployees()" style="padding-left: 35px; padding-right: 35px;">
                    <!-- Clear Button -->
                    <button class="position-absolute border-0 bg-transparent text-secondary" type="button" onclick="clearSearch()" style="top: 50%; right: 10px; transform: translateY(-50%);">
                        <i class="fa fa-times"></i>
                    </button>
                </div>
            </div>
        </div>
        <div class="table-responsive">            
            <table class="datatable table table-hover" id="employeeTable">
                <thead style="background-color: #CCCCCC;">
                    <tr>
                        <th>Profile Picture</th>
                        <th>Name</th>
                        <th>Username</th>
                        <th>Email</th>
                        <th>Mobile</th>
                        <th>Specialization</th>
                        <th>Join Date</th>
                        <th>Role</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    if (isset($_GET['ids'])) {
                        $id = $_GET['ids'];
                        $delete_query = mysqli_query($connection, "DELETE FROM tbl_employee WHERE id='$id'");
                        if ($delete_query) {
                            echo "<script>
                                showSuccess('Employee deleted successfully!', true);
                            </script>";
                        } else {
                            echo "<script>
                                showError('Error deleting employee: " . addslashes(mysqli_error($connection)) . "');
                            </script>";
                        }
                    }
                    
                    $fetch_query = mysqli_query($connection, "SELECT * FROM tbl_employee WHERE deleted = 0");
                    while ($row = mysqli_fetch_array($fetch_query)) {
                    ?>
                    <tr>
                        <td><img src="fetch-image-employee.php?id=<?php echo $row['id']; ?>" alt="Profile Picture" width="80"></td>
                        <td><?php echo $row['first_name']." ".$row['last_name']; ?></td>
                        <td><?php echo $row['username']; ?></td>
                        <td><?php echo $row['emailid']; ?></td>
                        <td><?php echo $row['phone']; ?></td>
                        <td><?php echo $row['specialization']; ?></td>
                        <td><?php echo $row['joining_date']; ?></td>
                        <td>
                            <?php 
                            switch ($row['role']) {
                                case "3": echo '<span class="custom-badge status-green">Nurse 1</span>'; break;
                                case "10": echo '<span class="custom-badge status-green">Nurse 2</span>'; break;
                                case "2": echo '<span class="custom-badge status-red">Doctor</span>'; break;
                                case "1": echo '<span class="custom-badge status-grey">Admin</span>'; break;
                                case "4": echo '<span class="custom-badge status-blue">Pharmacist</span>'; break;
                                case "5": echo '<span class="custom-badge status-purple">Medtech</span>'; break;
                                case "6": echo '<span class="custom-badge status-orange">Radtech</span>'; break;
                                case "7": echo '<span class="custom-badge status-purple">Billing Clerk</span>'; break;
                                case "8": echo '<span class="custom-badge status-pink">Cashier</span>'; break;
                                case "9": echo '<span class="custom-badge status-grey">Housekeeping Attendant</span>'; break;
                            }
                            ?>
                        </td>
                        <td class="text-right">
                            <div class="dropdown dropdown-action">
                                <a href="#" class="action-icon dropdown-toggle" data-toggle="dropdown" aria-expanded="false"><i class="fa fa-ellipsis-v"></i></a>
                                <div class="dropdown-menu dropdown-menu-right">
                                    <a class="dropdown-item" href="edit-employee.php?id=<?php echo $row['id'];?>"><i class="fa fa-pencil m-r-5"></i> Edit</a>
                                    <a class="dropdown-item delete-btn" data-id="<?php echo $row['id'];?>" href="#"><i class="fa fa-trash-o m-r-5"></i> Delete</a>
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

<?php include('footer.php'); ?>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@10"></script>
<script>
$(document).ready(function() {
    // Initialize DataTable
    var table = $('#employeeTable').DataTable({
        // ... (keep existing DataTable options)
        drawCallback: function() {
            // Update delete buttons after table redraw
            initializeDeleteButtons();
        }
    });
    
    // Initialize delete buttons
    function initializeDeleteButtons() {
        $('.delete-btn').off('click').on('click', function(e) {
            e.preventDefault();
            const id = $(this).data('id');
            
            showConfirm(
                'Delete Employee?',
                'Are you sure you want to delete this employee? This action cannot be undone!',
                () => {
                    setTimeout(() => {
                        window.location.href = 'employees.php?ids=' + id;
                    }, 500);
                }
            );
        });
    }
    
    // Handle search functionality
    $('#employeeSearchInput').on('keyup', function() {
        showLoading('Searching...');
        filterEmployees();
        Swal.close();
    });
    
    // Handle AJAX errors globally
    $(document).ajaxError(function(event, jqXHR, settings, error) {
        showError('Error fetching data. Please try again.');
    });
});

// Function to handle employee deletion
function deleteEmployee(id) {
    showConfirm(
        'Delete Employee?',
        'Are you sure you want to delete this employee? This action cannot be undone!',
        () => {
            setTimeout(() => {
                window.location.href = 'employees.php?ids=' + id;
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
        $(this).attr('onclick', `return deleteEmployee('${id}')`);
    });
});

function showSuccess(message, reload) {
    Swal.fire({
        icon: 'success',
        title: 'Success',
        text: message,
        showConfirmButton: false,
        timer: 2000
    }).then(() => {
        if (reload) {
            window.location.reload();
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
        if (result.isConfirmed) {
            callback();
        }
    });
}

function showLoading(message) {
    Swal.fire({
        icon: 'info',
        title: message,
        showConfirmButton: false,
        allowOutsideClick: false
    });
}

function filterEmployees() {
    var input = document.getElementById("employeeSearchInput").value;
    
    $.ajax({
        url: 'fetch_employee.php',
        type: 'GET',
        data: { query: input },
        success: function(response) {
            var data = JSON.parse(response);
            updateEmployeeTable(data);
        },
        error: function(xhr, status, error) {
            alert('Error fetching data. Please try again.');
        }
    });
}

function updateEmployeeTable(data) {
    var tbody = $('#employeeTable tbody');
    tbody.empty();
    
    data.forEach(function(record) {
        tbody.append(`
            <tr>
                <td><img src="fetch-image-employee.php?id=${record.id}" alt="Profile Picture" width="80"></td>
                <td>${record.name}</td>
                <td>${record.username}</td>
                <td>${record.emailid}</td>
                <td>${record.phone}</td>
                <td>${record.specialization}</td>
                <td>${record.joining_date}</td>
                <td>${record.role}</td>
                <td class="text-right">
                    <div class="dropdown dropdown-action">
                        <a href="#" class="action-icon dropdown-toggle" data-toggle="dropdown" aria-expanded="false">
                            <i class="fa fa-ellipsis-v"></i>
                        </a>
                        <div class="dropdown-menu dropdown-menu-right">
                            <a class="dropdown-item" href="edit-employee.php?id=${record.id}">
                                <i class="fa fa-pencil m-r-5"></i> Edit
                            </a>
                            <a class="dropdown-item delete-btn" data-id="${record.id}" href="#">
                                <i class="fa fa-trash-o m-r-5"></i> Delete
                            </a>
                        </div>
                    </div>
                </td>
            </tr>
        `);
    });
}

function clearSearch() {
    document.getElementById("employeeSearchInput").value = '';
    filterEmployees();
}
</script>

<style>
.btn-primary {
    background: #12369e;
    border: none;
}

.btn-primary:hover {
    background: #05007E;
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
    background-color:rgb(255, 255, 255);
    border: 1px solid rgb(228, 228, 228);
}
</style>
