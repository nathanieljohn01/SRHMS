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
                <h4 class="page-title">Doctor's Schedule</h4>
            </div>
            <div class="col-sm-8 col-9 text-right m-b-20">
                <?php 
                // Check user role
                if ($_SESSION['role'] == 1) {
                    // Show Add Schedule button for roles 1 and 2
                    echo '<a href="add-schedule.php" class="btn btn-primary btn-rounded float-right"><i class="fa fa-plus"></i> Add Schedule</a>';
                }
                ?>
            </div>
        </div>
        <div class="table-responsive">
            <table class="datatable table table-hover">
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
                    if (isset($_GET['ids'])) {
                        $id = $_GET['ids'];
                        // Sanitize $id before using it in the query
                        $id = mysqli_real_escape_string($connection, $id);
                        // Update query to set deleted = 1
                        $update_query = mysqli_query($connection, "UPDATE tbl_schedule SET deleted = 1 WHERE id='$id'");
                    }

                    $fetch_query = mysqli_query($connection, "SELECT * FROM tbl_schedule WHERE deleted = 0");
                    while ($row = mysqli_fetch_array($fetch_query)) {
                    ?>
                    <tr>
                        <td><?php echo $row['doctor_name']; ?></td>
                        <td><?php echo $row['specialization']; ?></td>
                        <td><?php echo $row['available_days']; ?></td>
                        <td><?php echo $row['start_time'] . ' - ' . $row['end_time']; ?></td>
                        <td><?php echo $row['message']; ?></td>
                        <?php if ($row['status'] == 1) { ?>
                            <td><span class="custom-badge status-green">Available</span></td>
                        <?php } else { ?>
                            <td><span class="custom-badge status-red">Not Available</span></td>
                        <?php } ?>
                        <td class="text-right">
                            <div class="dropdown dropdown-action">
                                <a href="#" class="action-icon dropdown-toggle" data-toggle="dropdown" aria-expanded="false"><i class="fa fa-ellipsis-v"></i></a>
                                <div class="dropdown-menu dropdown-menu-right">
                                    <?php 
                                    if ($_SESSION['role'] == 1 ) {
                                        echo '<a class="dropdown-item" href="edit-schedule.php?id=' . $row['id'] . '"><i class="fa fa-pencil m-r-5"></i> Edit</a>';
                                        echo '<a class="dropdown-item" href="schedule.php?ids=' . $row['id'] . '" onclick="return confirmDelete()"><i class="fa fa-trash-o m-r-5"></i> Delete</a>';
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
        return confirm('Are you sure you want to delete this Schedule?');
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
</style>
