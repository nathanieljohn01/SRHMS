<?php
session_start();
if (empty($_SESSION['name'])) {
    header('location:index.php');
}
include('header.php');
include('includes/connection.php');
?>

<div class="page-wrapper">
    <div class="content">
        <div class="row">
            <div class="col-sm-4 col-3">
                <h4 class="page-title">Housekeeping Schedule</h4>
            </div>
            <div class="col-sm-8 col-9 text-right m-b-20">
                <a href="add-housekeeping-schedule.php" class="btn btn-primary btn-rounded float-right"><i class="fa fa-plus"></i> Add Schedule</a>
            </div>
        </div>
        <div class="table-responsive">
            <table class="datatable table table-stripped">
                <thead>
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
                        $id = $_GET['ids'];
                        $delete_query = mysqli_query($connection, "DELETE FROM tbl_housekeeping_schedule WHERE id='$id'");
                    }
                    $fetch_query = mysqli_query($connection, "SELECT * FROM tbl_housekeeping_schedule");
                    while ($row = mysqli_fetch_array($fetch_query)) {
                        // Check the status of the bed allocation
                        $bed_query = mysqli_query($connection, "SELECT status FROM tbl_bedallocation WHERE room_number='{$row['room_number']}' AND bed_number='{$row['bed_number']}'");
                        $bed_row = mysqli_fetch_assoc($bed_query);
                        // If the bed status is 'Available', set $isEditable to 'disabled'
                        // If the bed status is 'Available', set $isDisabled to 'disabled'
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
                                        <a class="dropdown-item <?php echo $isDisabled; ?>" href="complete-housekeeping.php?id=<?php echo $row['id']; ?>"><i class="fa fa-check m-r-5"></i> Complete</a>                      
                                        <a class="dropdown-item edit-link <?php echo $isEditable; ?>" href="edit-housekeeping-schedule.php?id=<?php echo $row['id']; ?>"><i class="fa fa-pencil m-r-5"></i> Edit</a>
                                        <a class="dropdown-item" href="housekeeping-schedule.php?ids=<?php echo $row['id']; ?>" onclick="return confirmDelete()"><i class="fa fa-trash-o m-r-5"></i> Delete</a>
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
    function confirmDelete() {
        return confirm('Are you sure you want to delete this item?');
    }
</script>
<style>
.btn-primary {
            background: #12369e;
            border: none;
        }
        .btn-primary:hover {
            background: #1342C6;
        }
</style>