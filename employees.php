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
                    <input class="form-control" type="text" id="employeeSearchInput" onkeyup="filterEmployees()" placeholder="Search" style="padding-left: 35px; padding-right: 35px;">
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
                        $id = intval($_GET['ids']); // Ensures $id is a valid integer
                    
                        if ($id > 0) { // Prevents invalid or empty IDs
                            $update_query = mysqli_prepare($connection, "UPDATE tbl_employee SET deleted = 1 WHERE id = ?");
                            mysqli_stmt_bind_param($update_query, "i", $id);
                            mysqli_stmt_execute($update_query);
                            mysqli_stmt_close($update_query);
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
                            $roleBadges = [
                                "3" => ['text' => 'Nurse 1', 'color' => 'emp-nurse', 'icon' => 'fa-user-nurse'],
                                "10" => ['text' => 'Nurse 2', 'color' => 'emp-nurse-alt', 'icon' => 'fa-user-nurse'],
                                "2" => ['text' => 'Doctor', 'color' => 'emp-doctor', 'icon' => 'fa-user-md'],
                                "1" => ['text' => 'Admin', 'color' => 'emp-admin', 'icon' => 'fa-user-cog'],
                                "4" => ['text' => 'Pharmacist', 'color' => 'emp-pharma', 'icon' => 'fa-prescription-bottle'],
                                "5" => ['text' => 'Medtech', 'color' => 'emp-medtech', 'icon' => 'fa-microscope'],
                                "6" => ['text' => 'Radtech', 'color' => 'emp-radtech', 'icon' => 'fa-x-ray'],
                                "7" => ['text' => 'Billing Clerk', 'color' => 'emp-billing', 'icon' => 'fa-file-invoice-dollar'],
                                "8" => ['text' => 'Cashier', 'color' => 'emp-cashier', 'icon' => 'fa-cash-register'],
                                "9" => ['text' => 'Housekeeping', 'color' => 'emp-housekeeping', 'icon' => 'fa-broom']
                            ];
                            
                            if (isset($roleBadges[$row['role']])) {
                                $badge = $roleBadges[$row['role']];
                                echo '<span class="emp-badge '.$badge['color'].'">';
                                echo '<i class="fas '.$badge['icon'].'"></i> ';
                                echo $badge['text'];
                                echo '</span>';
                            } else {
                                echo '<span class="emp-badge emp-default">Unknown Role</span>';
                            }
                            ?>
                        </td>
                        <td class="text-right">
                            <div class="dropdown dropdown-action">
                                <a href="#" class="action-icon dropdown-toggle" data-toggle="dropdown" aria-expanded="false"><i class="fa fa-ellipsis-v"></i></a>
                                <div class="dropdown-menu dropdown-menu-right">
                                    <a class="dropdown-item" href="edit-employee.php?id=<?php echo $row['id'];?>"><i class="fa fa-pencil m-r-5"></i> Edit</a>
                                    <a class="dropdown-item" href="#" onclick="return confirmDelete('<?php echo $row['id']; ?>')"><i class="fa fa-trash-o m-r-5"></i> Delete </a>
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
<script language="JavaScript" type="text/javascript">
function confirmDelete(id) {
    return Swal.fire({
        title: 'Delete Employee Record?',
        text: 'Are you sure you want to delete this Employee record? This action cannot be undone!',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#12369e',
        confirmButtonText: 'Yes, delete it!'
    }).then((result) => {
        if (result.isConfirmed) {
            window.location.href = 'employees.php?ids=' + id;  
        }
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
/* Employee Badge System */
.emp-badge {
    padding: 6px 12px;
    border-radius: 50px;
    font-size: 12px;
    font-weight: 600;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 100px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.05);
    transition: all 0.3s ease;
    border: 1px solid transparent;
}

.emp-badge i {
    margin-right: 6px;
    font-size: 12px;
}

/* Specific role colors */
.emp-nurse {
    background-color: #e8f5e9;
    color: #2e7d32;
    border-color: #c8e6c9;
}

.emp-nurse-alt {
    background-color: #e0f7fa;
    color: #00838f;
    border-color: #b2ebf2;
}

.emp-doctor {
    background-color: #e3f2fd;
    color: #1565c0;
    border-color: #bbdefb;
}

.emp-admin {
    background-color: #f5f5f5;
    color: #616161;
    border-color: #e0e0e0;
}

.emp-pharma {
    background-color: #fff8e1;
    color: #ff8f00;
    border-color: #ffecb3;
}

.emp-medtech {
    background-color: #f3e5f5; 
    color: #8e24aa;       
    border-color: #e1bee7;    
}

.emp-radtech {
    background-color: #e8eaf6;
    color: #3949ab;
    border-color: #c5cae9;
}

.emp-billing {
    background-color: #fff3e0;
    color: #ef6c00;
    border-color: #ffe0b2;
}

.emp-cashier {
    background-color: #fce4ec;
    color: #c2185b;
    border-color: #f8bbd0;
}

.emp-housekeeping {
    background-color: #e0f2f1;
    color: #00796b;
    border-color: #b2dfdb;
}

.emp-default {
    background-color: #f5f5f5;
    color: #616161;
    border-color: #e0e0e0;
}

/* Hover effects */
.emp-badge:hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 8px rgba(0,0,0,0.1);
}

/* Responsive adjustments */
@media (max-width: 767.98px) {
    .emp-badge {
        min-width: 70px;
        padding: 4px 8px;
        font-size: 10px;
    }
    
    .emp-badge i {
        font-size: 10px;
        margin-right: 4px;
    }
}
</style>
