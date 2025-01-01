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
            <div class="col-6">
                <h4 class="page-title">Pharmacy Invoice</h4>
            </div>
            <div class="col-6 text-right">
                <a href="add-pharmacy-invoice.php" class="btn btn-primary btn-rounded float-right"><i class="fa fa-plus"></i> Add Transaction</a>
            </div>
        </div>
        <div class="table-responsive">
            <input class="form-control mb-3" type="text" id="medicineSearchInput" onkeyup="filterMedicines()" placeholder="Search for Patient">
            <table class="datatable table table-bordered" id="medicineTable">
                <thead style="background-color: #CCCCCC">
                    <tr>
                        <th>Patient ID</th>
                        <th>Invoice ID</th>
                        <th>Patient Name</th>
                        <th>Medicine Details</th>
                        <th>Total Cost</th>
                        <th>Invoice Date and Time</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    if(isset($_GET['ids'])){
                    $id = $_GET['ids'];
                    $update_query = mysqli_query($connection, "UPDATE tbl_pharmacy_invoice SET deleted = 1 WHERE id='$id'");
                    }
                   $fetch_query = mysqli_query($connection, "SELECT invoice_id, patient_id, patient_name, GROUP_CONCAT(CONCAT(medicine_name, ' - ', medicine_brand, ' (₱', price, ') - ', quantity, ' pcs (₱', price * quantity, ')') SEPARATOR '<br>') as medicine_details, SUM(price * quantity) as total_price, invoice_datetime FROM tbl_pharmacy_invoice GROUP BY invoice_id");
                    while ($row = mysqli_fetch_array($fetch_query)) {
                        ?>
                        <tr>
                            <td><?php echo $row['patient_id']; ?></td>
                            <td><?php echo $row['invoice_id']; ?></td>
                            <td><?php echo $row['patient_name']; ?></td>
                            <td><?php echo $row['medicine_details']; ?></td>
                            <td><?php echo $row['total_price']; ?></td>
                            <td><?php echo date('F d, Y g:i A', strtotime($row['invoice_datetime'])); ?></td>
                            <td>
                                <div class="dropdown dropdown-action">
                                    <a href="#" class="action-icon dropdown-toggle" data-toggle="dropdown" aria-expanded="false"><i class="fa fa-file-pdf-o"></i></a>
                                    <div class="dropdown-menu dropdown-menu-right">
                                        <form action="generate-pharmacy-pdf.php" method="get">
                                            <input type="hidden" name="id" value="<?php echo $row['invoice_id']; ?>">
                                            <div class="form-group">
                                                <input type="text" class="form-control" id="filename" name="filename" placeholder="Enter File Name" aria-label="Enter File Name" aria-describedby="basic-addon2">
                                            </div>
                                            <button class="btn btn-primary btn-block mt-1" type="submit"><i class="fa fa-file-pdf-o m-r-5"></i>Generate Invoice</button>
                                        </form>
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

    function filterMedicines() {
        var input, filter, table, tr, td, i, txtValue;
        input = document.getElementById("medicineSearchInput");
        filter = input.value.toUpperCase();
        table = document.getElementById("medicineTable");
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