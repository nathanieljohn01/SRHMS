<?php
session_start();
if (empty($_SESSION['name'])) {
    header('location:index.php');
    exit();
}

include('header.php');
include('includes/connection.php');

// Process filtering parameters
$query = isset($_GET['query']) ? trim($_GET['query']) : '';

// Check if we're viewing payment history
$viewingPayments = isset($_GET['view_payments']);
$paymentHistory = array();
$patientNameForModal = '';

if ($viewingPayments) {
    $patientId = $_GET['view_payments'];
    $patientNameForModal = isset($_GET['patient_name']) ? urldecode($_GET['patient_name']) : '';
    
    $query = "SELECT 
                p.id as payment_id,
                p.total_due,
                p.amount_to_pay,
                p.amount_paid,
                p.remaining_balance,
                DATE_FORMAT(p.payment_datetime, '%M %d, %Y %h:%i %p') as payment_datetime
              FROM tbl_payment p
              WHERE p.patient_id = ?
              AND p.deleted = 0
              ORDER BY p.payment_datetime DESC";
              
    $stmt = $connection->prepare($query);
    $stmt->bind_param("s", $patientId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $row['total_due'] = number_format($row['total_due'], 2);
        $row['amount_to_pay'] = number_format($row['amount_to_pay'], 2);
        $row['amount_paid'] = number_format($row['amount_paid'], 2);
        $row['remaining_balance'] = number_format($row['remaining_balance'], 2);
        $paymentHistory[] = $row;
    }
}

// Build the SQL query for main table
$sql = "SELECT 
            p.id as payment_id,
            p.patient_id,
            p.patient_name,
            p.patient_type,
            CASE
                WHEN p.patient_type = 'Inpatient' THEN bi.total_due
                WHEN p.patient_type = 'Hemodialysis' THEN bh.total_due
                WHEN p.patient_type = 'Newborn' THEN bn.total_due
                WHEN p.patient_type = 'Outpatient' THEN SUM(p.amount_to_pay)
                ELSE 0
            END as total_due,
            SUM(p.amount_paid) as total_paid,
            CASE 
                WHEN p.patient_type = 'Inpatient' THEN bi.remaining_balance
                WHEN p.patient_type = 'Hemodialysis' THEN bh.remaining_balance
                WHEN p.patient_type = 'Newborn' THEN bn.remaining_balance
                WHEN p.patient_type = 'Outpatient' THEN (SUM(p.amount_to_pay) - SUM(p.amount_paid))
                ELSE 0
            END as balance,
            CASE 
                WHEN (p.patient_type = 'Inpatient' AND bi.remaining_balance > 0) OR 
                     (p.patient_type = 'Hemodialysis' AND bh.remaining_balance > 0) OR
                     (p.patient_type = 'Newborn' AND bn.remaining_balance > 0) OR
                     (p.patient_type = 'Outpatient' AND (SUM(p.amount_to_pay) - SUM(p.amount_paid)) > 0) THEN 'Partially Paid'
                ELSE 'Fully Paid'
            END as status
        FROM tbl_payment p
        LEFT JOIN (
            SELECT patient_name, patient_id, remaining_balance, billing_id, total_due
            FROM tbl_billing_inpatient
            WHERE deleted = 0 AND id IN (
                SELECT MAX(id)
                FROM tbl_billing_inpatient
                GROUP BY patient_id
            )
        ) bi ON p.patient_name = bi.patient_name AND p.patient_type = 'Inpatient'
        LEFT JOIN (
            SELECT patient_name, patient_id, remaining_balance, billing_id, total_due
            FROM tbl_billing_hemodialysis
            WHERE deleted = 0 AND id IN (
                SELECT MAX(id)
                FROM tbl_billing_hemodialysis
                GROUP BY patient_id
            )
        ) bh ON p.patient_name = bh.patient_name AND p.patient_type = 'Hemodialysis'
        LEFT JOIN (
            SELECT patient_name, newborn_id as patient_id, remaining_balance, billing_id, total_due
            FROM tbl_billing_newborn
            WHERE deleted = 0 AND id IN (
                SELECT MAX(id)
                FROM tbl_billing_newborn
                GROUP BY newborn_id
            )
        ) bn ON p.patient_name = bn.patient_name AND p.patient_type = 'Newborn'
        WHERE p.deleted = 0";

// Add search filter
if (!empty($query)) {
    $safe_query = mysqli_real_escape_string($connection, $query);
    $sql .= " AND (p.patient_name LIKE '%$safe_query%' OR p.patient_id LIKE '%$safe_query%')";
}

$sql .= " GROUP BY p.patient_id, p.patient_name, p.patient_type";
$sql .= " ORDER BY p.patient_name";

$result = mysqli_query($connection, $sql);
?>

<div class="page-wrapper">
    <div class="content">
        <div class="row">
            <div class="col-sm-4 col-3">
                <h4 class="page-title">Patient Ledger</h4>
            </div>
        </div>
        
        <div class="sticky-search bg-white p-4 mb-4 border rounded">
            <h5 class="font-weight-bold mb-3">Search Patient:</h5>
            <div class="row">
                <div class="col-md-8">
                    <div class="input-group mb-3">
                        <div class="position-relative w-100">
                            <i class="fa fa-search position-absolute text-secondary" style="top: 50%; left: 12px; transform: translateY(-50%);"></i>
                            <input class="form-control" type="text" id="paymentSearchInput" onkeyup="filterPayments()" placeholder="Search" style="padding-left: 35px; padding-right: 35px;">
                            <button class="position-absolute border-0 bg-transparent text-secondary" type="button" onclick="clearSearch()" style="top: 50%; right: 10px; transform: translateY(-50%);">
                                <i class="fa fa-times"></i>
                            </button>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="form-group mb-0">
                        <select class="form-control" id="statusFilter" onchange="filterPayments()">
                            <option value="">All Status</option>
                            <option value="Fully Paid">Fully Paid</option>
                            <option value="Partially Paid">Partially Paid</option>
                        </select>
                    </div>
                </div>
            </div>
        </div>
        <div class="table-responsive">
            <table class="datatable table table-hover" id="patientLedgerTable">
                <thead style="background-color: #CCCCCC;">
                    <tr>
                        <th>Patient ID</th>
                        <th>Patient Name</th>
                        <th>Patient Type</th>
                        <th>Total Due</th>
                        <th>Total Paid</th>
                        <th>Remaining Balance</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    if (mysqli_num_rows($result) > 0) {
                        while ($row = mysqli_fetch_assoc($result)) {
                            $status_class = $row['status'] == 'Fully Paid' ? 'status-paid' : 'status-partial';
                            ?>
                            <tr>
                                <td><?php echo htmlspecialchars($row['patient_id']); ?></td>
                                <td><?php echo htmlspecialchars($row['patient_name']); ?></td>
                                <td><?php echo htmlspecialchars($row['patient_type']); ?></td>
                                <td>₱<?php echo number_format($row['total_due'], 2); ?></td>
                                <td>₱<?php echo number_format($row['total_paid'], 2); ?></td>
                                <td>₱<?php echo number_format($row['balance'], 2); ?></td>
                                <td>
                                    <span class="payment-status <?php echo $status_class; ?> has-tooltip" data-tooltip="<?php echo $tooltip; ?>">
                                        <?php echo $row['status']; ?>
                                    </span>
                                </td>
                                <td class="text-right">
                                    <button class="btn btn-sm btn-primary view-payment-btn" 
                                            data-patient-id="<?php echo $row['patient_id']; ?>"
                                            data-patient-name="<?php echo htmlspecialchars($row['patient_name']); ?>">
                                        <i class="fa fa-eye"></i>
                                    </button>
                                </td>
                            </tr>
                            <?php
                        }
                    } else {
                        echo '<tr><td colspan="8" class="text-center">No records found</td></tr>';
                    }
                    ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Payment History Modal -->
<div class="modal fade" id="paymentDetailsModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h4 class="modal-title">Payment History for <span id="modalPatientName"></span></h4>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div class="sticky-search bg-white p-4 mb-4 border rounded">
                    <h5 class="font-weight-bold mb-3">Search Payments:</h5>
                    <div class="input-group mb-3">
                        <div class="position-relative w-100">
                            <i class="fa fa-search position-absolute text-secondary" style="top: 50%; left: 12px; transform: translateY(-50%);"></i>
                            <input class="form-control" type="text" id="modalSearchInput" placeholder="Search" style="padding-left: 35px; padding-right: 35px;">
                            <button class="position-absolute border-0 bg-transparent text-secondary" type="button" onclick="clearModalSearch()" style="top: 50%; right: 10px; transform: translateY(-50%);">
                                <i class="fa fa-times"></i>
                            </button>
                        </div>
                    </div>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover" id="paymentHistoryTable">
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
                        <tbody id="paymentHistoryBody">
                            <!-- Payment history will be loaded here via AJAX -->
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
$(document).ready(function() {
    // Handle view payment button click
    $(document).on('click', '.view-payment-btn', function() {
        var patientId = $(this).data('patient-id');
        var patientName = $(this).data('patient-name');
        
        // Set patient name in modal title
        $('#modalPatientName').text(patientName);
        
        // Show loading state
        $('#paymentHistoryBody').html('<tr><td colspan="6" class="text-center"><i class="fa fa-spinner fa-spin"></i> Loading payment history...</td></tr>');
        
        // Show modal
        $('#paymentDetailsModal').modal('show');
        
        // Load payment history via AJAX
        $.ajax({
            url: 'fetch_payment_history.php',
            method: 'GET',
            data: { patient_id: patientId },
            dataType: 'json',
            success: function(response) {
                if (response.success && response.data.length > 0) {
                    var html = '';
                    $.each(response.data, function(index, payment) {
                        html += `
                            <tr>
                                <td>${payment.payment_id}</td>
                                <td>₱${payment.total_due}</td>
                                <td>₱${payment.amount_to_pay}</td>
                                <td>₱${payment.amount_paid}</td>
                                <td>₱${payment.remaining_balance}</td>
                                <td>${payment.payment_datetime}</td>
                            </tr>
                        `;
                    });
                    $('#paymentHistoryBody').html(html);
                } else {
                    $('#paymentHistoryBody').html('<tr><td colspan="6" class="text-center">No payment history found</td></tr>');
                }
            },
            error: function(xhr, status, error) {
                $('#paymentHistoryBody').html('<tr><td colspan="6" class="text-center text-danger">Error loading payment history</td></tr>');
                console.error("Error loading payment history:", error);
            }
        });
    });

    // Filter modal table
    $('#modalSearchInput').on('keyup', function() {
        var value = $(this).val().toLowerCase();
        $('#paymentHistoryTable tbody tr').filter(function() {
            $(this).toggle($(this).text().toLowerCase().indexOf(value) > -1);
        });
    });
});

function clearModalSearch() {
    $('#modalSearchInput').val('');
    $('#modalSearchInput').keyup();
}

function filterPayments() {
    var input = document.getElementById("paymentSearchInput").value;
    var status = document.getElementById("statusFilter").value;
    
    $.ajax({
        url: 'fetch_patient_ledger.php',
        method: 'GET',
        data: { 
            query: input,
            status: status 
        },
        dataType: 'json',
        success: function(response) {
            if (Array.isArray(response)) {
                updatePatientTable(response);
            } else if (response.error) {
                console.error(response.error);
            } else {
                console.error("Invalid response format");
            }
        },
        error: function(xhr, status, error) {
            console.error("Error fetching data:", error);
        }
    });
}

function clearSearch() {
    document.getElementById("paymentSearchInput").value = '';
    document.getElementById("statusFilter").value = '';
    filterPayments();
}

function updatePatientTable(data) {
    var tbody = $('#patientLedgerTable tbody');
    tbody.empty();
    
    if (data.length === 0) {
        tbody.append('<tr><td colspan="8" class="text-center">No records found</td></tr>');
        return;
    }
    
    data.forEach(function(row) {
        var status_class = row.status == 'Fully Paid' ? 'status-paid' : 'status-partial';
        
        tbody.append(`
            <tr>
                <td>${row.patient_id}</td>
                <td>${row.patient_name}</td>
                <td>${row.patient_type}</td>
                <td>₱${row.total_due}</td>
                <td>₱${row.total_paid}</td>
                <td>₱${row.balance}</td>
                <td><span class="payment-status ${status_class}">${row.status}</span></td>
                <td class="text-right">
                    <button class="btn btn-sm btn-primary view-payment-btn" 
                            data-patient-id="${row.patient_id}"
                            data-patient-name="${row.patient_name}">
                        <i class="fa fa-eye"></i>
                    </button>
                </td>
            </tr>
        `);
    });
}
</script>

<style>
.modal-lg {
    max-width: 90%;
}

.btn-rounded {
    border-radius: 50px;
}

.sticky-search {
    position: sticky;
    top: 0;
    z-index: 100;
    box-shadow: 0 2px 5px rgba(0,0,0,0.1);
}

.payment-status {
    padding: 8px 16px;
    border-radius: 24px;
    font-size: 12px;
    font-weight: 700;
    display: inline-block;
    min-width: 110px;
    text-align: center;
    text-transform: uppercase;
    letter-spacing: 0.8px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    transition: all 0.3s cubic-bezier(0.25, 0.8, 0.25, 1);
    position: relative;
    overflow: hidden;
    border: none;
}

.status-paid {
    background: linear-gradient(135deg, #28a745 0%, #1e7e34 100%);
    color: white;
    text-shadow: 0 1px 2px rgba(0,0,0,0.2);
    box-shadow: 0 3px 10px rgba(40, 167, 69, 0.3);
}

.status-paid:hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(40, 167, 69, 0.4);
}

.status-paid:after {
    content: "";
    position: absolute;
    top: 0;
    left: -100%;
    width: 100%;
    height: 100%;
    background: linear-gradient(90deg, 
              rgba(255,255,255,0) 0%, 
              rgba(255,255,255,0.2) 50%, 
              rgba(255,255,255,0) 100%);
    animation: shine 3s infinite;
}

.status-partial {
    background: linear-gradient(135deg, #ffc107 0%, #ff9800 100%);
    color: #2a2a2a;
    box-shadow: 0 3px 10px rgba(255, 193, 7, 0.3);
    position: relative;
}

.status-partial:hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(255, 193, 7, 0.4);
}

.status-partial:before {
    content: "";
    position: absolute;
    top: 0;
    left: -100%;
    width: 100%;
    height: 100%;
    background: linear-gradient(90deg, 
              rgba(255,255,255,0) 0%, 
              rgba(255,255,255,0.2) 50%, 
              rgba(255,255,255,0) 100%);
    animation: shine 3s infinite;
}

@keyframes shine {
    0% { left: -100%; }
    20% { left: 100%; }
    100% { left: 100%; }
}
/* For smaller screens */
@media (max-width: 768px) {
    .payment-status {
        padding: 6px 12px;
        min-width: 90px;
        font-size: 11px;
    }
}

.btn-sm {
    padding: 0.25rem 0.5rem;
    font-size: 0.875rem;
    line-height: 1.5;
    border-radius: 0.2rem;
}

.btn-primary {
    background: #12369e;
    border: none;
}

.btn-primary:hover {
    background: #05007E;
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
</style>