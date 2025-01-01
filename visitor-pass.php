<?php
session_start();
if (empty($_SESSION['name'])) 
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
                <h4 class="page-title">Visitor Pass</h4>
            </div>
            <div class="col-sm-8 col-9 text-right m-b-20">
                <a href="add-pass.php" class="btn btn-primary btn-rounded float-right"><i class="fa fa-plus"></i> Issue Pass</a>
            </div>
        </div>
        <div class="table-responsive">
            <table class="datatable table table-hover">
                <thead>
                    <tr>
                        <th>Visitor ID</th>
                        <th>Visitor Name</th>
                        <th>Contact Number</th>
                        <th>Purpose</th>
                        <th>Check-in Time</th>
                        <th>Check-out Time</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    if (isset($_GET['ids'])) {
                        $id = $_GET['ids'];
                        $delete_query = mysqli_query($connection, "DELETE FROM tbl_visitorpass WHERE id='$id'");
                    }
                    $fetch_query = mysqli_query($connection, "SELECT * FROM tbl_visitorpass");
                    while ($row = mysqli_fetch_array($fetch_query)) {
                        $check_in_time = date('F d, Y g:i A', strtotime($row['check_in_time']));
                        $check_out_time = ($row['check_out_time'] != NULL) ? date('F d, Y g:i A', strtotime($row['check_out_time'])) : ''; // If check_out_time is not NULL, format it, otherwise leave it empty
                        ?>
                        <tr>
                            <td><?php echo $row['visitor_id']; ?></td>
                            <td><?php echo $row['visitor_name']; ?></td>
                            <td><?php echo $row['contact_number']; ?></td>
                            <td><?php echo $row['purpose']; ?></td>
                            <td><?php echo $check_in_time; ?></td>
                            <td><?php echo $check_out_time; ?></td> <!-- Display check_out_time -->
                            <td class="text-right">
                                <div class="dropdown dropdown-action">
                                    <a href="#" class="action-icon dropdown-toggle" data-toggle="dropdown" aria-expanded="false"><i class="fa fa-ellipsis-v"></i></a>
                                    <div class="dropdown-menu dropdown-menu-right">
                                        <?php if (empty($row['check_out_time'])) { ?>
                                            <a class="dropdown-item" href="checkout.php?id=<?php echo $row['id']; ?>" onclick="return confirmCheckout()"><i class="fa fa-sign-out-alt m-r-5"></i> Check Out</a>
                                        <?php } ?>
                                        <a class="dropdown-item" href="visitor-pass.php?ids=<?php echo $row['id']; ?>" onclick="return confirmDelete()"><i class="fa fa-trash-o m-r-5"></i> Delete</a>
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
        return confirm('Are you sure you want to delete this visitor pass?');
    }
    
    function confirmCheckout() {
        return confirm('Are you sure you want to check out this visitor?');
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