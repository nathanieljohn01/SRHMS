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
                <a href="add-employee.php" class="btn btn-primary btn-rounded float-right"><i class="fa fa-plus"></i> Add Employee</a>
            </div>
            <?php } ?>
        </div>
        <div class="table-responsive">
            <input class="form-control" type="text" id="employeeSearchInput" onkeyup="filterEmployees()" placeholder="Search for Employees">
            <table class="datatable table table-stripped" id="employeeTable">
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
                        // Sanitize $id before using it in the query
                        $id = mysqli_real_escape_string($connection, $id);
                        $delete_query = mysqli_query($connection, "UPDATE tbl_employee SET deleted = 1 WHERE id='$id'");
                        if ($delete_query) {
                            echo "<p>Employee marked as deleted successfully.</p>";
                        } else {
                            echo "<p>Error marking employee as deleted: " . mysqli_error($connection) . "</p>";
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
                                    <a class="dropdown-item" href="employees.php?ids=<?php echo $row['id'];?>" onclick="return confirmDelete()"><i class="fa fa-trash-o m-r-5"></i> Delete</a>
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

<script language="JavaScript" type="text/javascript">
function confirmDelete() {
    return confirm('Are you sure you want to delete this Employee?');
}

function filterEmployees() {
    var input, filter, table, tr, td, i, j, txtValue;
    input = document.getElementById("employeeSearchInput");
    filter = input.value.toUpperCase();
    table = document.getElementById("employeeTable");
    tr = table.getElementsByTagName("tr");

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
            tr[i].style.display = "";
        } else {
            tr[i].style.display = "none";
        }
    }
}

$(document).ready(function() {
    $('#employeeTable').DataTable();
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
</style>
