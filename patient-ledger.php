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
                <div class="sticky-search bg-white p-4 mb-4 border rounded">
                    <h5 class="font-weight-bold mb-3">Search Payment:</h5>
                    <div class="row">
                        <div class="col-sm-6 col-md-4">
                            <div class="form-group mb-md-0">
                                <div class="position-relative">
                                    <i class="fa fa-search position-absolute text-secondary" style="top: 50%; left: 12px; transform: translateY(-50%);"></i>
                                    <input class="form-control" type="text" id="paymentSearchInput" onkeyup="filterPayments()" placeholder="Search" style="padding-left: 35px; padding-right: 35px;">
                                    <button class="position-absolute border-0 bg-transparent text-secondary" type="button" onclick="clearSearch()" style="top: 50%; right: 10px; transform: translateY(-50%);">
                                        <i class="fa fa-times"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                        <div class="col-sm-6 col-md-4">
                            <div class="form-group mb-md-0">
                                <select class="form-control" id="patientTypeFilter">
                                    <option value="">All Patient Types</option>
                                    <option value="Inpatient">Inpatient</option>
                                    <option value="Outpatient">Outpatient</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-sm-6 col-md-4">
                            <div class="form-group mb-0">
                                <select class="form-control" id="statusFilter">
                                    <option value="">All Status</option>
                                    <option value="Fully Paid">Fully Paid</option>
                                    <option value="Partially Paid">Partially Paid</option>
                                </select>
                            </div>
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
                <h4 class="modal-title">Payment History</h4>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div class="table-responsive">
                    <table class="datatable table table-hover">
                        <thead style="background-color: #CCCCCC;">
                            <tr>
                                <th>Payment ID</th>
                                <th>Total Due</th>
                                <th>Amount to Pay</th>
                                <th>Amount Paid</th>
                                <th>Remaining</th>
                                <th>Payment Date</th>
                            </tr>
                        </thead>
                        <tbody id="paymentDetailsBody">
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-primary" data-dismiss="modal">Close</button>
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
    
    if(data.length === 0) {
        tbody.append(`
            <tr>
                <td colspan="8" class="text-center py-4 text-muted">
                    <i class="fas fa-search mr-2"></i>No matching records found
                </td>
            </tr>
        `);
        return;
    }
    
    data.forEach(function(row) {
        const statusClass = getStatusClass(row.status);
        const balanceClass = parseFloat(row.balance) > 0 ? 'balance-positive' : 'balance-zero';
        const typeClass = getTypeClass(row.patient_type);
        
        tbody.append(`
            <tr>
                <td>${formatPatientId(row)}</td>
                <td>${row.patient_name}</td>
                <td><span class="patient-type-badge ${typeClass}">${row.patient_type}</span></td>
                <td class="amount-due">₱${formatCurrency(row.total_due)}</td>
                <td class="amount-paid">₱${formatCurrency(row.total_paid)}</td>
                <td class="amount-balance ${balanceClass}">₱${formatCurrency(row.balance)}</td>
                <td>
                    <span class="status-badge ${statusClass}">
                        <i class="fas ${getStatusIcon(row.status)}"></i>
                        ${row.status}
                    </span>
                </td>
                <td class="text-center">
                    <button type="button" 
                            class="btn btn-view-details btn-outline-primary" 
                            onclick="viewPaymentDetails('${row.patient_id}', '${row.patient_name}')"
                            data-toggle="tooltip" title="View Payment History">
                        <i class="fa fa-eye"></i>
                    </button>
                </td>
            </tr>
        `);
    });
    
    // Initialize tooltips
    $('[data-toggle="tooltip"]').tooltip();
}

// Helper functions
function formatPatientId(row) {
    if(row.patient_type === 'Newborn') {
        return `Newborn: ${row.patient_id}</span>`;
    }
    return row.patient_id;
}

function formatCurrency(amount) {
    return parseFloat(amount).toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ",");
}

function getStatusClass(status) {
    switch(status) {
        case 'Fully Paid': return 'status-fully-paid';
        case 'Partially Paid': return 'status-partially-paid';
        case 'Unpaid': return 'status-unpaid';
        case 'Overdue': return 'status-overdue';
        default: return 'status-partially-paid';
    }
}

function getTypeClass(type) {
    switch(type) {
        case 'Inpatient': return 'type-inpatient';
        case 'Outpatient': return 'type-outpatient';
        case 'Newborn': return 'type-newborn';
        default: return '';
    }
}

function getStatusIcon(status) {
    switch(status) {
        case 'Fully Paid': return 'fa-check-circle';
        case 'Partially Paid': return 'fa-clock';
        case 'Unpaid': return 'fa-exclamation-circle';
        case 'Overdue': return 'fa-exclamation-triangle';
        default: return 'fa-info-circle';
    }
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
                    <td>₱${parseFloat(payment.remaining_balance).toFixed(2)}</td>
                    <td>${payment.payment_datetime}</td>
                </tr>`;
            });
            
            $('#paymentDetailsBody').html(html);
            $('#paymentDetailsModal .modal-title').text(`Payment History - ${patientName}`);
            $('#paymentDetailsModal').modal('show');
        },
        error: function() {
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: 'Failed to fetch payment details'
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
    padding: 0.375rem 0.75rem;
    transition: all 0.2s ease;
    min-width: 38px;
}

.btn-outline-primary:hover {
    background-color: #12369e;
    color: #fff;
    border-color: #12369e;
}

.btn-outline-secondary {
    color: gray;
    border: 1px solid rgb(228, 228, 228);
    padding: 0.375rem 0.75rem;
    transition: all 0.2s ease;
}

.btn-outline-secondary:hover {
    background-color: #12369e;
    color: #fff;
    border-color: #12369e;
}

.input-group-text {
    background-color: rgb(255, 255, 255);
    border: 1px solid rgb(228, 228, 228);
    color: gray;
}

.btn-primary {
    background: #12369e;
    border: none;
    padding: 0.375rem 1rem;
    transition: all 0.2s ease;
}

.btn-primary:hover {
    background: #05007E;
}

.custom-badge {
    padding: 6px 12px;
    border-radius: 4px;
    font-size: 12px;
    font-weight: 500;
    display: inline-block;
    text-align: center;
    min-width: 80px;
}

.form-control {
    height: 38px;
    border: 1px solid #e3e3e3;
    border-radius: 4px;
    padding: 0.375rem 0.75rem;
}

.form-control:focus {
    border-color: #12369e;
    box-shadow: 0 0 0 0.2rem rgba(18, 54, 158, 0.25);
}

.sticky-search {
    position: sticky;
    top: 0;
    z-index: 100;
    box-shadow: 0 2px 4px rgba(0,0,0,0.05);
}

.modal-content {
    border-radius: 6px;
    overflow: hidden;
}

.modal-header {
    background-color: #f8f9fa;
    border-bottom: 1px solid #e3e3e3;
    padding: 1rem 1.5rem;
}

.modal-body {
    padding: 1.5rem;
}

.modal-footer {
    border-top: 1px solid #e3e3e3;
    padding: 1rem 1.5rem;
}

@media (max-width: 767.98px) {
    .form-group {
        margin-bottom: 1rem;
        background-color: rgb(241, 241, 241);
        box-shadow: 0 6px 12px rgba(0, 0, 0, 0.15);
        padding: 10px;
        border-radius: 4px;
        border: 1px solid #e9ecef;
    }
    
    .sticky-search {
        padding: 1rem !important;
    }
    
    .custom-badge {
        min-width: 70px;
        padding: 4px 8px;
    }
}
/* Status Badge System */
.status-badge {
    padding: 6px 12px;
    border-radius: 50px;
    font-size: 12px;
    font-weight: 600;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 100px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.05);
}

.status-badge i {
    margin-right: 5px;
    font-size: 10px;
}

.status-fully-paid {
    background-color: #e8f5e9;
    color: #2e7d32;
    border: 1px solid #c8e6c9;
}

.status-partially-paid {
    background-color: #fff3e0;
    color: #e65100;
    border: 1px solid #ffe0b2;
}

.status-unpaid {
    background-color: #ffebee;
    color: #c62828;
    border: 1px solid #ffcdd2;
}

.status-overdue {
    background-color: #f3e5f5;
    color: #6a1b9a;
    border: 1px solid #e1bee7;
}

/*Action Button */
.btn-view-details {
    width: 32px;
    height: 32px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border-radius: 50%;
    transition: all 0.2s;
}

.btn-view-details:hover {
    background-color: #12369e;
    color: white !important;
    transform: scale(1.1);
}
</style>
