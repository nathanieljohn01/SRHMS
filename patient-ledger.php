<?php
session_start();
if (empty($_SESSION['name'])) {
    header('location:index.php');
    exit();
}

include('header.php');
include('includes/connection.php');
?>

<div class="page-wrapper">
    <div class="content">
        <div class="row">
            <div class="col-sm-4 col-3">
                <h4 class="page-title">Patient Ledger</h4>
            </div>
            <div class="col-sm-8 col-9 text-right m-b-20">
                <a href="payment-processing.php" class="btn btn-primary float-right"><i class="fa fa-arrow-left"></i> Back</a>
            </div>
        </div>

       
            <div class="table-responsive">
                <div class="sticky-search">
                    <h5 class="font-weight-bold mb-2">Search Payment:</h5>
                    <div class="row">
                        <div class="col-sm-6 col-md-4">
                            <div class="position-relative w-100 mb-3">
                                <!-- Search Icon -->
                                <i class="fa fa-search position-absolute text-secondary" style="top: 50%; left: 12px; transform: translateY(-50%);"></i>
                                <!-- Input Field -->
                                <input class="form-control" type="text" id="paymentSearchInput" onkeyup="filterPayments()" placeholder="" style="padding-left: 35px; padding-right: 35px;">
                                <!-- Clear Button -->
                                <button class="position-absolute border-0 bg-transparent text-secondary" type="button" onclick="clearSearch()" style="top: 50%; right: 10px; transform: translateY(-50%);">
                                    <i class="fa fa-times"></i>
                                </button>
                            </div>
                        </div>
                        <div class="col-sm-6 col-md-4">
                            <select class="form-control mb-3" id="patientTypeFilter">
                                <option value="">All Patient Types</option>
                                <option value="Inpatient">Inpatient</option>
                                <option value="Outpatient">Outpatient</option>
                            </select>
                        </div>
                        <div class="col-sm-6 col-md-4">
                            <select class="form-control mb-3" id="statusFilter">
                                <option value="">All Status</option>
                                <option value="Fully Paid">Fully Paid</option>
                                <option value="Partially Paid">Partially Paid</option>
                            </select>
                        </div>
                    </div>
                </div>

                <table class="datatable table table-hover" id="ledgerTable">
                    <thead style="background-color: #CCCCCC;">
                        <tr>
                            <th>Patient ID</th>
                            <th>Patient Name</th>
                            <th>Patient Type</th>
                            <th>Total Due</th>
                            <th>Total Paid</th>
                            <th>Balance</th>
                            <th>Status</th>
                            <th>Details</th>
                        </tr>
                    </thead>
                    <tbody>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Payment Details Modal -->
<div class="modal fade" id="paymentDetailsModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Payment History Details</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead style="background-color: #CCCCCC;">
                            <tr>
                                <th>Payment ID</th>
                                <th>Total Due</th>
                                <th>Amount to Pay</th>
                                <th>Amount Paid</th>
                                <th>Payment Date</th>
                            </tr>
                        </thead>
                        <tbody id="paymentDetailsBody">
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<?php include('footer.php'); ?>

<script>
function clearSearch() {
    document.getElementById("paymentSearchInput").value = '';
    $('#patientTypeFilter').val('');
    $('#statusFilter').val('');
    filterPayments();
}

function filterPayments() {
    var searchQuery = document.getElementById("paymentSearchInput").value;
    var patientType = document.getElementById("patientTypeFilter").value;
    var paymentStatus = document.getElementById("statusFilter").value;
    
    $.ajax({
        url: 'fetch_payment_ledger.php',
        method: 'GET',
        data: { 
            query: searchQuery,
            patient_type: patientType,
            payment_status: paymentStatus
        },
        success: function(response) {
            var data = JSON.parse(response);
            updatePaymentTable(data);
        }
    });
}

function updatePaymentTable(data) {
    var tbody = $('#ledgerTable tbody');
    tbody.empty();
    
    data.forEach(function(row) {
        const statusClass = row.status === 'Fully Paid' ? 'status-green' : 'status-orange';
        
        tbody.append(`
            <tr>
                <td>${row.patient_id}</td>
                <td>${row.patient_name}</td>
                <td>${row.patient_type}</td>
                <td>₱${row.total_due}</td>
                <td>₱${row.total_paid}</td>
                <td>₱${row.balance}</td>
                <td><span class="custom-badge ${statusClass}">${row.status}</span></td>
                <td class="text-right">
                    <button type="button" 
                            class="btn btn-outline-primary btn-sm" 
                            onclick="viewPaymentDetails('${row.patient_id}', '${row.patient_name}')"
                            title="View Payment History">
                        <i class="fa fa-eye"></i>
                    </button>
                </td>
            </tr>
        `);
    });
}

function viewPaymentDetails(patientId, patientName) {
    $.ajax({
        url: 'get-payment-details.php',
        type: 'POST',
        data: {
            patient_id: patientId
        },
        success: function(response) {
            const payments = JSON.parse(response);
            let html = '';
            
            payments.forEach(payment => {
                html += `<tr>
                    <td>${payment.payment_id}</td>
                    <td>₱${parseFloat(payment.total_due).toFixed(2)}</td>
                    <td>₱${parseFloat(payment.amount_to_pay).toFixed(2)}</td>
                    <td>₱${parseFloat(payment.amount_paid).toFixed(2)}</td>
                    <td>${payment.payment_datetime}</td>
                </tr>`;
            });
            
            $('#paymentDetailsBody').html(html);
            $('#paymentDetailsModal .modal-title').text('Payment History');
            $('#paymentDetailsModal').modal('show');
        },
        error: function() {
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: 'Failed to load payment details',
                confirmButtonColor: '#12369e'
            });
        }
    });
}

$(document).ready(function() {
    // Initial load
    filterPayments();
    
    // Event listeners
    $('#paymentSearchInput').on('keyup', function() {
        filterPayments();
    });
    
    $('#patientTypeFilter, #statusFilter').on('change', function() {
        filterPayments();
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
    color: gray;
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

.custom-badge {
    padding: 5px 10px;
    border-radius: 4px;
    font-size: 13px;
    font-weight: 500;
}

.status-green {
    background-color: #e8f5e9;
    color: #43a047;
    border: 1px solid #43a047;
}

.status-orange {
    background-color: #fff3e0;
    color: #fb8c00;
    border: 1px solid #fb8c00;
}

.filter-row {
    background-color: #fff;
    padding: 15px;
    margin-bottom: 20px;
    border-radius: 4px;
}

.filter-row select {
    height: 38px;
}

.dataTables_wrapper .dataTables_length {
    margin-bottom: 15px;
}

.dataTables_wrapper .dataTables_length select {
    border: 1px solid #e3e3e3;
    border-radius: 3px;
    padding: 5px;
    margin: 0 5px;
}

.dataTables_wrapper .dataTables_paginate .paginate_button.current {
    background: #12369e;
    color: white !important;
    border: 1px solid #12369e;
}

.dataTables_wrapper .dataTables_paginate .paginate_button:hover {
    background: #05007E;
    color: white !important;
    border: 1px solid #05007E;
}

.table-hover tbody tr:hover {
    background-color: rgba(18, 54, 158, 0.05);
}

.dataTables_wrapper .dataTables_info {
    padding-top: 15px;
}

.modal-header {
    background-color: #f8f9fa;
}

.fa-eye {
    margin-right: 5px;
}

#paymentSearchInput {
    height: 38px;
}

.filter-row .col-sm-6 {
    margin-bottom: 10px;
}

@media (min-width: 768px) {
    .filter-row .col-sm-6 {
        margin-bottom: 0;
    }
}
</style>
