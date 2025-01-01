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
                <h4 class="page-title">Doctors</h4>
            </div>
        </div>
        <div class="table-responsive">
            <table class="datatable table table-hover">
                <thead style="background-color: #CCCCCC;">
                    <tr>
                        <th>Profile Picture</th>
                        <th>Name</th>
                        <th>Specialization</th>
                        <th>Email</th>
                        <th>DOB</th>
                        <th>Phone</th>
                        <th>Bio</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $fetch_query = mysqli_query($connection, "SELECT * FROM tbl_employee WHERE role=2");
                    while($row = mysqli_fetch_array($fetch_query)) {
                    ?>
                    <tr>
                        <td><img src="fetch-image-employee.php?id=<?php echo $row['id']; ?>" alt="Profile Picture" width="80"></td>
                        <td><?php echo $row['first_name']." ".$row['last_name']; ?></td>
                        <td><?php echo $row['specialization']; ?></td>
                        <td><?php echo $row['emailid']; ?></td>
                        <td><?php echo $row['dob']; ?></td>
                        <td><?php echo $row['phone']; ?></td>
                        <td><?php echo $row['bio']; ?></td>
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
<style>
.btn-primary {
            background: #12369e;
            border: none;
        }
        .btn-primary:hover {
            background: #05007E;
        }
</style>