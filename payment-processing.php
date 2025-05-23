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
                        <input class="form-control" type="text" id="paymentSearchInput" onkeyup="filterPayments()" placeholder="Search" style="padding-left: 35px; padding-right: 35px;">
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
                        <th>Remaining Balance</th>
                        <th>Transaction Date and Time</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    if (isset($_GET['ids'])) {
                        $id = intval($_GET['ids']); 
                        if ($id) {
                            $update_query = mysqli_prepare($connection, "UPDATE tbl_payment SET deleted = 1 WHERE id = ?");
                            mysqli_stmt_bind_param($update_query, "i", $id);
                            mysqli_stmt_execute($update_query);
                            mysqli_stmt_close($update_query);
                        }
                    }
                    while ($row = mysqli_fetch_array($fetch_query)) {
                        // Sanitize output to prevent XSS
                        $payment_id = htmlspecialchars($row['payment_id'], ENT_QUOTES, 'UTF-8');
                        $patient_name = htmlspecialchars($row['patient_name'], ENT_QUOTES, 'UTF-8');
                        $patient_type = htmlspecialchars($row['patient_type'], ENT_QUOTES, 'UTF-8');
                        $total_due = number_format($row['total_due'], 2);
                        $amount_paid = number_format($row['amount_paid'], 2);
                        $amount_to_pay = number_format($row['amount_to_pay'], 2);
                        $remaining_balance = number_format($row['remaining_balance'], 2);
                    ?>
                        <tr>
                            <td><?php echo $payment_id; ?></td>
                            <td><?php echo $patient_name; ?></td>
                            <td><?php echo $patient_type; ?></td>
                            <td>₱<?php echo $total_due; ?></td>
                            <td>₱<?php echo $amount_to_pay; ?></td>
                            <td>₱<?php echo $amount_paid; ?></td>
                            <td>₱<?php echo $remaining_balance; ?></td>
                            <td><?php echo date('F d, Y g:i A', strtotime($row['payment_datetime'])); ?></td>
                            <td class="text-right">
                                <div class="dropdown dropdown-action">
                                    <a href="#" class="action-icon dropdown-toggle" data-toggle="dropdown" aria-expanded="false">
                                        <i class="fa fa-ellipsis-v"></i>
                                    </a>
                                    <div class="dropdown-menu dropdown-menu-right">
                                        <a class="dropdown-item" href="generate-receipt.php?id=<?php echo $row['id']; ?>">
                                            <i class="fa fa-file-text m-r-5"></i> Generate Receipt
                                        </a>
                                        <a class="dropdown-item" href="#" onclick="return confirmDelete('<?php echo $row['id']; ?>')">
                                            <i class="fa fa-trash m-r-5"></i> Delete 
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

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script language="JavaScript" type="text/javascript">
    function confirmDelete(id) {
        return Swal.fire({
            title: 'Delete Payment Record?',
            text: 'Are you sure you want to delete this Payment record? This action cannot be undone!',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#12369e',
            confirmButtonText: 'Yes, delete it!'
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = 'payment-processing.php?ids=' + id;  
            }
        });
    }
</script>

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
        dataType: 'json',
        success: function(response) {
            if (Array.isArray(response)) {
                updatePaymentTable(response);
            } else {
                console.error("Invalid response format");
            }
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
                <td>${row.total_due}</td>
                <td>${row.amount_to_pay}</td>
                <td>${row.amount_paid}</td>
                <td>${row.remaining_balance}</td>
                <td>${row.payment_datetime}</td>
                <td class="text-right">
                    <div class="dropdown dropdown-action">
                        <a href="#" class="action-icon dropdown-toggle" data-toggle="dropdown" aria-expanded="false">
                            <i class="fa fa-ellipsis-v"></i>
                        </a>
                        <div class="dropdown-menu dropdown-menu-right">
                            <a class="dropdown-item" href="generate-receipt.php?id=${row.id}">
                                <i class="fa fa-file-text m-r-5"></i> Generate Receipt
                            </a>
                            <a class="dropdown-item" href="#" onclick="return confirmDelete('${row.id}')">
                                <i class="fa fa-trash m-r-5"></i> Delete
                            </a>
                        </div>
                    </div>
                </td>
            </tr>
        `);
    });
}

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
    background-color:rgb(249, 249, 249);
    border: 1px solid rgb(212, 212, 212);
    color: gray;
}
.form-control {
    border-radius: .375rem; /* Rounded corners */
    border-color: #ced4da; /* Border color */
    background-color: #f8f9fa; /* Background color */
}
select.form-control {
    border-radius: .375rem; /* Rounded corners */
    border: 1px solid; /* Border color */
    border-color: #ced4da; /* Border color */
    background-color: #f8f9fa; /* Background color */
    padding: .375rem 2.5rem .375rem .75rem; /* Adjust padding to make space for the larger arrow */
    font-size: 1rem; /* Font size */
    line-height: 1.5; /* Line height */
    height: calc(2.25rem + 2px); /* Adjust height */
    -webkit-appearance: none; /* Remove default styling on WebKit browsers */
    -moz-appearance: none; /* Remove default styling on Mozilla browsers */
    appearance: none; /* Remove default styling on other browsers */
    background: url('data:image/svg+xml;charset=UTF-8,%3Csvg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="20" height="20"%3E%3Cpath d="M7 10l5 5 5-5z" fill="%23aaa"/%3E%3C/svg%3E') no-repeat right 0.75rem center;
    background-size: 20px; /* Size of the custom arrow */
}

select.form-control:focus {
    border-color: #12369e; /* Border color on focus */
    box-shadow: 0 0 0 .2rem rgba(38, 143, 255, .25); /* Shadow on focus */
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

.payment-status {
    padding: 5px 10px;
    border-radius: 4px;
    font-size: 12px;
    font-weight: bold;
}
.status-paid {
    background-color: #28a745;
    color: white;
}
.status-partial {
    background-color: #ffc107;
    color: black;
}
.status-unpaid {
    background-color: #dc3545;
    color: white;
}
</style>