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
                <a href="add-housekeeping-schedule.php" class="btn btn-primary btn-rounded float-right"><i class="fa fa-plus"></i> Add Schedule</a>
            </div>
        </div>
        <div class="table-responsive">
            <table class="datatable table table-stripped">
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
                        $id = $_GET['ids'];
                        $update_query = mysqli_prepare($connection, "UPDATE tbl_inpatient_record SET deleted = 1 WHERE id = ?");
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
                                        <a class="dropdown-item <?php echo $isDisabled; ?>" href="#" onclick="confirmCompletion(<?php echo $row['id']; ?>);"><i class="fa fa-check m-r-5"></i> Complete</a>
                                        <a class="dropdown-item edit-link <?php echo $isEditable; ?>" href="edit-housekeeping-schedule.php?id=<?php echo $row['id']; ?>"><i class="fa fa-pencil m-r-5"></i> Edit</a>
                                        <a class="dropdown-item" href="housekeeping-schedule.php?ids=<?php echo $row['id']; ?>" onclick="return confirm('Are you sure you want to delete this schedule?')"><i class="fa fa-trash-o m-r-5"></i> Delete</a>
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

<style>
.btn-primary {
    background: #12369e;
    border: none;
}
.btn-primary:hover {
    background: #1342C6;
}
</style>

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