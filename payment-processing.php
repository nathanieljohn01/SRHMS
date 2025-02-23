<?php
session_start();
if (empty($_SESSION['name'])) {
    header('location:index.php');
    exit();
}

include('header.php');
include('includes/connection.php');

// Handle deletion
if (isset($_GET['ids'])) {
    $id = filter_var($_GET['ids'], FILTER_SANITIZE_NUMBER_INT);
    if ($id) {
        $update_query = mysqli_prepare($connection, "UPDATE tbl_payment SET deleted = 1 WHERE id = ?");
        mysqli_stmt_bind_param($update_query, "i", $id);
        mysqli_stmt_execute($update_query);
        mysqli_stmt_close($update_query);
    }
}

// Fetch initial data
$fetch_query = mysqli_query($connection, "SELECT * FROM tbl_payment WHERE deleted = 0 ORDER BY payment_datetime DESC");
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
            <div class="sticky-search">
                <h5 class="font-weight-bold mb-2">Search Payment:</h5>
                <div class="input-group mb-3">
                    <div class="position-relative w-100">
                        <!-- Search Icon -->
                        <i class="fa fa-search position-absolute text-secondary" style="top: 50%; left: 12px; transform: translateY(-50%);"></i>
                        <!-- Input Field -->
                        <input class="form-control" type="text" id="paymentSearchInput" onkeyup="filterPayments()" style="padding-left: 35px; padding-right: 35px;">
                        <!-- Clear Button -->
                        <button class="position-absolute border-0 bg-transparent text-secondary" type="button" onclick="clearSearch()" style="top: 50%; right: 10px; transform: translateY(-50%);">
                            <i class="fa fa-times"></i>
                        </button>
                    </div>
                </div>
            </div>

            <table class="datatable table table-hover" id="paymentTable">
                <thead style="background-color: #CCCCCC;">
                    <tr>
                        <th>Payment ID</th>
                        <th>Patient Name</th>
                        <th>Patient Type</th>
                        <th>Total Due</th>
                        <th>Amount to Pay</th>
                        <th>Amount Paid</th>
                        <th>Transaction Date and Time</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    while ($row = mysqli_fetch_array($fetch_query)) {
                        // Sanitize output to prevent XSS
                        $payment_id = htmlspecialchars($row['payment_id'], ENT_QUOTES, 'UTF-8');
                        $patient_name = htmlspecialchars($row['patient_name'], ENT_QUOTES, 'UTF-8');
                        $patient_type = htmlspecialchars($row['patient_type'], ENT_QUOTES, 'UTF-8');
                        $total_due = number_format($row['total_due'], 2);
                        $amount_paid = number_format($row['amount_paid'], 2);
                        $amount_to_pay = number_format($row['amount_to_pay'], 2);
                    ?>
                        <tr>
                            <td><?php echo $payment_id; ?></td>
                            <td><?php echo $patient_name; ?></td>
                            <td><?php echo $patient_type; ?></td>
                            <td>₱<?php echo $total_due; ?></td>
                            <td>₱<?php echo $amount_to_pay; ?></td>
                            <td>₱<?php echo $amount_paid; ?></td>
                            <td><?php echo date('F d, Y g:i A', strtotime($row['payment_datetime'])); ?></td>
                            <td class="text-right">
                                <div class="dropdown dropdown-action">
                                    <a href="#" class="action-icon dropdown-toggle" data-toggle="dropdown" aria-expanded="false">
                                        <i class="fa fa-ellipsis-v"></i>
                                    </a>
                                    <div class="dropdown-menu dropdown-menu-right">
                                        <a class="dropdown-item" href="generate-receipt.php?id=<?php echo $row['id']; ?>">
                                            <i class="fa fa-file-text-o m-r-5"></i> Generate Receipt
                                        </a>
                                        <a class="dropdown-item" href="payment-processing.php?ids=<?php echo $row['id']; ?>" onclick="return confirmDelete()">
                                            <i class="fa fa-trash-o m-r-5"></i> Delete
                                        </a>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    <?php 
                    } 
                    ?>
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
    var input = document.getElementById("paymentSearchInput").value;
    
    $.ajax({
        url: 'fetch_payment_process.php',
        method: 'GET',
        data: { query: input },
        success: function(response) {
            var data = JSON.parse(response);
            updatePaymentTable(data);
        },
        error: function(xhr, status, error) {
            console.error("Error fetching data:", error);
        }
    });
}

function updatePaymentTable(data) {
    var tbody = $('#paymentTable tbody');
    tbody.empty();
    
    data.forEach(function(row) {
        tbody.append(`
            <tr>
                <td>${row.payment_id}</td>
                <td>${row.patient_name}</td>
                <td>${row.patient_type}</td>
                <td>₱${row.total_due}</td>
                <td>₱${row.amount_to_pay}</td>
                <td>₱${row.amount_paid}</td>
                <td>${row.payment_datetime}</td>
                <td class="text-right">
                    <div class="dropdown dropdown-action">
                        <a href="#" class="action-icon dropdown-toggle" data-toggle="dropdown" aria-expanded="false">
                            <i class="fa fa-ellipsis-v"></i>
                        </a>
                        <div class="dropdown-menu dropdown-menu-right">
                            <a class="dropdown-item" href="generate-receipt.php?id=${row.id}">
                                <i class="fa fa-file-text-o m-r-5"></i> Generate Receipt
                            </a>
                            <a class="dropdown-item" href="payment-processing.php?ids=${row.id}" onclick="return confirmDelete()">
                                <i class="fa fa-trash-o m-r-5"></i> Delete
                            </a>
                        </div>
                    </div>
                </td>
            </tr>
        `);
    });
}

function confirmDelete() {
    return confirm('Are you sure you want to delete this Payment Record?');
}

$(document).ready(function() {
    // Event listener for search input with debounce
    let searchTimeout;
    $('#paymentSearchInput').on('keyup', function() {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(filterPayments, 300);
    });
});
</script>

<style>
.btn-outline-primary {
    background-color: rgb(252, 252, 252);
    color: gray;
    border: 1px solid rgb(228, 228, 228);
}

.btn-outline-primary:hover {
    background-color: #12369e;
    color: #fff;
}

.btn-outline-secondary {
    color: rgb(90, 90, 90);
    border: 1px solid rgb(228, 228, 228);
}

.btn-outline-secondary:hover {
    background-color: #12369e;
    color: #fff;
}

.input-group-text {
    background-color: rgb(255, 255, 255);
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

.dropdown-action .action-icon {
    color: #777;
    font-size: 18px;
    display: inline-block;
    padding: 0 10px;
}

.dropdown-menu {
    border: 1px solid rgba(0, 0, 0, 0.1);
    border-radius: 3px;
    transform-origin: top right;
    box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
}

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

#paymentSearchInput {
    height: 38px;
}
</style>