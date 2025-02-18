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
                <h4 class="page-title">Payment Processing</h4>
            </div>
            <div class="col-sm-8 col-9 text-right m-b-20">
                <a href="add-payment.php" class="btn btn-primary float-right"><i class="fa fa-plus"></i> Add Payment</a>
            </div>
        </div>
        
        <div class="table-responsive">
            <div class="input-group">
                <input class="form-control mb-3" type="text" id="paymentSearchInput" onkeyup="filterPayments()" placeholder="Search for Payment">
                <div class="input-group-append">
                    <button class="btn btn-outline-primary mb-3" type="button" onclick="clearSearch()">
                        <i class="fa fa-times"></i>
                    </button>
                </div>
            </div>

            <table class="datatable table table-hover table-striped" id="paymentTable">
                <thead style="background-color: #CCCCCC;">
                    <tr>
                        <th>Payment ID</th>
                        <th>Patient Name</th>
                        <th>Patient Type</th>
                        <th>Amount to Pay</th>
                        <th>Amount Paid</th>
                        <th>Transaction Date and Time</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    if(isset($_GET['ids'])){
                        $id = mysqli_real_escape_string($connection, $_GET['ids']);
                        $update_query = $connection->prepare("UPDATE tbl_payment SET deleted = 1 WHERE id = ?");
                        $update_query->bind_param("s", $id);
                        $update_query->execute();
                    }
                    
                    $fetch_query = mysqli_query($connection, "SELECT * FROM tbl_payment WHERE deleted = 0");
                    while ($row = mysqli_fetch_array($fetch_query)) {
                    ?>
                    <tr>
                        <td><?php echo $row['payment_id']; ?></td>
                        <td><?php echo $row['patient_name']; ?></td>
                        <td><?php echo $row['patient_type']; ?></td>
                        <td><?php echo $row['amount_to_pay']; ?></td>
                        <td><?php echo $row['amount_paid']; ?></td>
                        <td><?php echo date('F d, Y g:i A', strtotime($row['payment_datetime'])); ?></td>
                        <td class="text-right">
                            <div class="dropdown dropdown-action">
                                <a href="#" class="action-icon dropdown-toggle" data-toggle="dropdown" aria-expanded="false"><i class="fa fa-ellipsis-v"></i></a>
                                <div class="dropdown-menu dropdown-menu-right">
                                    <a class="dropdown-item" href="generate-receipt.php?id=<?php echo $row['id']; ?>"><i class="fa fa-file-text-o m-r-5"></i> Generate Receipt</a>
                                    <a class="dropdown-item" href="payment-processing.php?ids=<?php echo $row['id']; ?>" onclick="return confirmDelete()"><i class="fa fa-trash-o m-r-5"></i> Delete</a>
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

<script>
function clearSearch() {
    document.getElementById("paymentSearchInput").value = '';
    filterPayments();
}

function filterPayments() {
    var input, filter, table, tr, td, i, txtValue;
    input = document.getElementById("paymentSearchInput");
    filter = input.value.toUpperCase();
    table = document.getElementById("paymentTable");
    tr = table.getElementsByTagName("tr");
    
    for (i = 0; i < tr.length; i++) {
        var matchFound = false;
        for (var j = 0; j < tr[i].cells.length; j++) {
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

function confirmDelete(){
    return confirm('Are you sure you want to delete this Payment Record?');
}

$(document).ready(function() {
    $('#paymentTable').DataTable();
});
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
    color: gray;
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



   