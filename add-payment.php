<?php
session_start();
if (empty($_SESSION['name'])) {
    header('location:index.php');
    exit();
}

include('header.php');
include('includes/connection.php');

// Generate payment ID
$fetch_query = mysqli_query($connection, "SELECT MAX(id) as id FROM tbl_payment");
$row = mysqli_fetch_row($fetch_query);
$pay_id = $row[0] == 0 ? 1 : $row[0] + 1;

if (isset($_POST['submit_payment'])) {
    $payment_id = 'PAY-' . $pay_id;
    $patient_name = $_POST['patient_name'];
    $patient_type = $_POST['patient_type'];
    $amount_to_pay = $_POST['amount_to_pay'];
    $amount_paid = $_POST['amount_paid'];
    $total_due = 0; // Initialize total_due

    // Validation checks
    $errors = [];
    
    if (empty($patient_name)) {
        $errors[] = "Patient name is required";
    }
    
    if (empty($patient_type)) {
        $errors[] = "Patient type is required";
    }
    
    if (!is_numeric($amount_to_pay) || $amount_to_pay <= 0) {
        $errors[] = "Amount to pay must be greater than 0";
    }
    
    if (!is_numeric($amount_paid) || $amount_paid <= 0) {
        $errors[] = "Amount paid must be greater than 0";
    }

    // Fetch patient_id
    $patient_id = null;
    $patient_query = $connection->prepare("SELECT patient_id FROM tbl_patient WHERE CONCAT(first_name, ' ', last_name) = ?");
    $patient_query->bind_param("s", $patient_name);
    $patient_query->execute();
    $patient_query->bind_result($patient_id);
    $patient_query->fetch();
    $patient_query->close();

    if (!$patient_id) {
        $errors[] = "Patient not found in the system.";
    }
    
    if (empty($errors)) {
        $connection->begin_transaction();
        
        try {
            // Get total_due from tbl_billing_inpatient if patient is Inpatient
            if ($patient_type == 'Inpatient') {
                $total_due_query = $connection->prepare("
                    SELECT total_due 
                    FROM tbl_billing_inpatient 
                    WHERE patient_name = ? 
                    ORDER BY id DESC LIMIT 1
                ");
                $total_due_query->bind_param("s", $patient_name);
                $total_due_query->execute();
                $total_due_result = $total_due_query->get_result();
                $total_due_row = $total_due_result->fetch_assoc();
                
                if ($total_due_row) {
                    $total_due = $total_due_row['total_due'];
                } else {
                    $total_due = $amount_to_pay; // Fallback if no billing record found
                }
            } else {
                $total_due = $amount_to_pay; // For non-inpatient, use amount_to_pay as total_due
            }

            // Insert into payment table with total_due
            $insert_query = $connection->prepare("INSERT INTO tbl_payment
                (payment_id, patient_id, patient_name, patient_type, total_due, amount_to_pay, amount_paid, payment_datetime)
                VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");
            
            $insert_query->bind_param("sissddd", $payment_id, $patient_id, $patient_name, $patient_type, $total_due, $amount_to_pay, $amount_paid);
            
            if ($insert_query->execute()) {
                // Update remaining balance in billing table based on patient type
                if ($patient_type == 'Inpatient') {
                    // Get current remaining balance
                    $balance_query = $connection->prepare("
                        SELECT remaining_balance 
                        FROM tbl_billing_inpatient 
                        WHERE patient_name = ? 
                        ORDER BY id DESC LIMIT 1
                    ");
                    $balance_query->bind_param("s", $patient_name);
                    $balance_query->execute();
                    $balance_result = $balance_query->get_result();
                    $balance_row = $balance_result->fetch_assoc();
                    
                    if ($balance_row) {
                        // Calculate new remaining balance
                        $current_balance = $balance_row['remaining_balance'];
                        $new_balance = max(0, $current_balance - $amount_paid);
                        
                        // Update the remaining balance and status if needed
                        $update_balance = $connection->prepare("
                            UPDATE tbl_billing_inpatient 
                            SET remaining_balance = ?,
                                status = CASE 
                                    WHEN ? <= 0 THEN 'Paid'
                                    ELSE status 
                                END
                            WHERE patient_name = ? 
                            ORDER BY id DESC LIMIT 1
                        ");
                        $update_balance->bind_param("dds", $new_balance, $new_balance, $patient_name);
                        $update_balance->execute();
                    }
                }

                // Only update is_billed status if payment is complete (remaining balance is 0)
                if ($amount_paid >= $amount_to_pay) {
                    // Update lab orders
                    $update_lab = $connection->prepare("UPDATE tbl_laborder SET is_billed = 1 WHERE patient_id = ? AND is_billed = 0");
                    $update_lab->bind_param("i", $patient_id);
                    $update_lab->execute();

                    // Update radiology orders
                    $update_rad = $connection->prepare("UPDATE tbl_radiology SET is_billed = 1 WHERE patient_id = ? AND is_billed = 0");
                    $update_rad->bind_param("i", $patient_id);
                    $update_rad->execute();
                }

                $connection->commit();

                echo "
                <script src='https://cdn.jsdelivr.net/npm/sweetalert2@10'></script>
                <script>
                    document.addEventListener('DOMContentLoaded', function() {
                        var style = document.createElement('style');
                        style.innerHTML = '.swal2-confirm { background-color: #12369e !important; color: white !important; border: none !important; } .swal2-confirm:hover { background-color: #05007E !important; } .swal2-confirm:focus { box-shadow: 0 0 0 0.2rem rgba(18, 54, 158, 0.5) !important; }';
                        document.head.appendChild(style);

                        Swal.fire({
                            icon: 'success',
                            title: 'Success!',
                            text: 'Payment recorded successfully!',
                            confirmButtonColor: '#12369e'
                        }).then(() => {
                            window.location.href = 'payment-processing.php';
                        });
                    });
                </script>";
            }
        } catch (Exception $e) {
            $connection->rollback();
            
            echo "
            <script src='https://cdn.jsdelivr.net/npm/sweetalert2@10'></script>
            <script>
                document.addEventListener('DOMContentLoaded', function() {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: 'An error occurred while processing payment.',
                        confirmButtonColor: '#12369e'
                    });
                });
            </script>";
        }
    } else {
        // Display validation errors
        echo "
        <script src='https://cdn.jsdelivr.net/npm/sweetalert2@10'></script>
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                Swal.fire({
                    icon: 'error',
                    title: 'Validation Error',
                    html: '" . implode("<br>", $errors) . "',
                    confirmButtonColor: '#12369e'
                });
            });
        </script>";
    }
}
?>

<div class="page-wrapper">
    <div class="content">
        <div class="row">
            <div class="col-sm-4">
                <h4 class="page-title">Add Payment</h4>
            </div>
            <div class="col-sm-8 text-right mb-3">
                <a href="payment-processing.php" class="btn btn-primary float-right">Back</a>
            </div>
        </div>

        <div class="card">
            <div class="card-body">
                <form method="POST">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Payment ID</label>
                                <input class="form-control" type="text" name="payment_id" value="<?php if(!empty($pay_id)) { echo 'PAY-'.$pay_id; } else { echo 'PAY-1'; } ?>" disabled>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Patient Type</label>
                                <select class="form-control" name="patient_type" id="patient_type" required onchange="togglePatientSearch()">
                                    <option value="">Select Type</option>
                                    <option value="Inpatient">Inpatient</option>
                                    <option value="Outpatient">Outpatient</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Patient Name</label>
                                <div class="input-group">
                                    <input type="text" class="form-control" id="patient_search" placeholder="Search patient name" autocomplete="off">
                                    <input type="hidden" name="patient_name" id="selected_patient_name">
                                    <div class="search-results" id="search_results" style="display:none;"></div>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-3">
                            <div class="form-group">
                                <label>Amount to Pay</label>
                                <input type="number" step="0.01" class="form-control" name="amount_to_pay" required readonly>
                            </div>
                        </div>

                        <div class="col-md-3">
                            <div class="form-group">
                                <label>Amount Paid</label>
                                <input type="number" step="0.01" class="form-control" name="amount_paid" required onchange="calculateRemainingBalance()">
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-12">
                            <!-- Laboratory Fees Table -->
                            <table class="table table-bordered mt-4" id="outpatientLabTable" style="display: none;">
                                <thead style="background-color: #CCCCCC;">
                                    <tr>
                                        <th>Lab Test</th>
                                        <th>Lab Price</th>
                                        <th>Request Date</th>
                                    </tr>
                                </thead>
                                <tbody></tbody>
                            </table>

                            <!-- Radiology Fees Table -->
                            <table class="table table-bordered mt-4" id="outpatientRadTable" style="display: none;">
                                <thead style="background-color: #CCCCCC;">
                                    <tr>
                                        <th>Radiology Test</th>
                                        <th>Price</th>
                                        <th>Request Date</th>
                                    </tr>
                                </thead>
                                <tbody></tbody>
                            </table>

                            <!-- Inpatient Billing Table -->
                            <table class="table table-bordered mt-4" id="inpatientBillingTable" style="display: none;">
                                <thead style="background-color: #CCCCCC;">
                                    <tr>
                                        <th>Description</th>
                                        <th>Amount</th>
                                        <th>Discount</th>
                                        <th>Net Amount</th>
                                    </tr>
                                </thead>
                                <tbody></tbody>
                                <tfoot>
                                    <tr>
                                        <th colspan="4">Total Fees: <span id="inpatientGrossAmount" class="float-right">₱0.00</span></th>
                                    </tr>
                                    <tr>
                                        <td colspan="4" class="pl-4">Less:</td>
                                    </tr>
                                    <tr>
                                        <td colspan="4" class="pl-5">PhilHealth (PF) <span id="inpatientPhilhealthPF" class="float-right">₱0.00</span></td>
                                    </tr>
                                    <tr>
                                        <td colspan="4" class="pl-5">PhilHealth (HB) <span id="inpatientPhilhealthHB" class="float-right">₱0.00</span></td>
                                    </tr>
                                    <tr>
                                        <td colspan="4" class="pl-5">VAT Exempt <span id="inpatientVatExempt" class="float-right">₱0.00</span></td>
                                    </tr>
                                    <tr>
                                        <td colspan="4" class="pl-5">Senior Citizen Discount <span id="inpatientSeniorDiscount" class="float-right">₱0.00</span></td>
                                    </tr>
                                    <tr>
                                        <td colspan="4" class="pl-5">PWD Discount <span id="inpatientPWDDiscount" class="float-right">₱0.00</span></td>
                                    </tr>
                                    <tr class="font-weight-bold">
                                        <th colspan="4">Total Amount Due: <span id="inpatientAmountDue" class="float-right">₱0.00</span></th>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>

                    <div class="row mt-4">
                        <div class="col-md-12">
                            <button type="submit" name="submit_payment" class="btn btn-primary">Submit Payment</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php include('footer.php'); ?>

<script>
const patientSearch = document.getElementById('patient_search');
const searchResults = document.getElementById('search_results');
const outpatientLabTable = document.getElementById('outpatientLabTable');
const outpatientRadTable = document.getElementById('outpatientRadTable');
const patientTypeSelect = document.getElementById('patient_type');
let currentTotalDue = 0;

function togglePatientSearch() {
    const selectedType = patientTypeSelect.value;
    patientSearch.parentElement.parentElement.style.display = 'block';
    
    // Clear all tables and amounts
    outpatientLabTable.style.display = 'none';
    outpatientRadTable.style.display = 'none';
    inpatientBillingTable.style.display = 'none';
    
    // Clear table contents
    outpatientLabTable.querySelector('tbody').innerHTML = '';
    outpatientRadTable.querySelector('tbody').innerHTML = '';
    inpatientBillingTable.querySelector('tbody').innerHTML = '';
    
    // Clear all amounts and spans
    document.getElementById('inpatientGrossAmount').textContent = '₱0.00';
    document.getElementById('inpatientPhilhealthPF').textContent = '₱0.00';
    document.getElementById('inpatientPhilhealthHB').textContent = '₱0.00';
    document.getElementById('inpatientVatExempt').textContent = '₱0.00';
    document.getElementById('inpatientSeniorDiscount').textContent = '₱0.00';
    document.getElementById('inpatientPWDDiscount').textContent = '₱0.00';
    document.getElementById('inpatientAmountDue').textContent = '₱0.00';
    currentTotalDue = 0;
    
    // Show appropriate table based on type
    if (selectedType === 'Outpatient') {
        outpatientLabTable.style.display = 'table';
        outpatientRadTable.style.display = 'table';
    } else if (selectedType === 'Inpatient') {
        inpatientBillingTable.style.display = 'table';
    }
    
    // Clear search and amounts
    patientSearch.value = '';
    document.getElementById('selected_patient_name').value = '';
    document.querySelector('input[name="amount_to_pay"]').value = '';
    document.querySelector('input[name="amount_paid"]').value = '';
}

function calculateRemainingBalance() {
    const amountPaidInput = document.querySelector('input[name="amount_paid"]');
    const amountToPayInput = document.querySelector('input[name="amount_to_pay"]');
    const amountPaid = parseFloat(amountPaidInput.value) || 0;
    
    if (amountPaid > currentTotalDue) {
        amountPaidInput.value = currentTotalDue.toFixed(2);
        amountToPayInput.value = '0.00';
    } else {
        const remainingBalance = currentTotalDue - amountPaid;
        amountToPayInput.value = remainingBalance.toFixed(2);
    }
}

patientSearch.addEventListener('keyup', function() {
    const selectedType = patientTypeSelect.value;
    if (this.value.length > 0) {
        const searchEndpoint = selectedType === 'Outpatient' ? 'search-opt.php' : 'search-ipt-payment.php';
        fetch(searchEndpoint + '?search=' + this.value)
            .then(response => response.json())
            .then(data => {
                searchResults.style.display = 'block';
                searchResults.innerHTML = data.map(patient => 
                    `<li class="search-result" data-id="${patient.patient_id}">${patient.patient_name}</li>`
                ).join('');
            });
    } else {
        searchResults.style.display = 'none';
    }
});

document.addEventListener('click', function(e) {
    if (e.target && e.target.classList.contains('search-result')) {
        const patientName = e.target.textContent;
        patientSearch.value = patientName;
        document.getElementById('selected_patient_name').value = patientName;
        searchResults.style.display = 'none';

        const selectedType = patientTypeSelect.value;
        let totalAmount = 0;

        if (selectedType === 'Outpatient') {
            // Fetch laboratory fees
            fetch('opt-lab-fee.php?patient_name=' + patientName)
                .then(response => response.json())
                .then(data => {
                    outpatientLabTable.style.display = 'table';
                    const tbody = outpatientLabTable.querySelector('tbody');
                    tbody.innerHTML = '';

                    data.forEach(bill => {
                        const row = document.createElement('tr');
                        row.innerHTML = `
                            <td>${bill.lab_test}</td>
                            <td>₱${bill.lab_price}</td>
                            <td>${bill.lab_requested_date}</td>
                        `;
                        tbody.appendChild(row);
                        totalAmount += parseFloat(bill.lab_price);
                    });
                    updateAmountToPay(totalAmount);
                });

            // Fetch radiology fees
            fetch('opt-rad-fee.php?patient_name=' + patientName)
                .then(response => response.json())
                .then(data => {
                    outpatientRadTable.style.display = 'table';
                    const tbody = outpatientRadTable.querySelector('tbody');
                    tbody.innerHTML = '';

                    data.forEach(bill => {
                        const row = document.createElement('tr');
                        row.innerHTML = `
                            <td>${bill.test_type}</td>
                            <td>₱${bill.price}</td>
                            <td>${bill.requested_date}</td>
                        `;
                        tbody.appendChild(row);
                        totalAmount += parseFloat(bill.price);
                    });
                    updateAmountToPay(totalAmount);
                });
        } else {
            // For inpatients, fetch their billing data
            fetch('ipt-billing-fee.php?patient_name=' + patientName)
                .then(response => response.json())
                .then(data => {
                    const tbody = inpatientBillingTable.querySelector('tbody');
                    tbody.innerHTML = '';
                    inpatientBillingTable.style.display = 'table';

                    // Add rows for each fee type
                    const fees = [
                        { name: 'Room Fee', fee: data.room_fee, discount: data.room_discount, net: data.net_room_fee },
                        { name: 'Medication Fee', fee: data.medication_fee, discount: data.med_discount, net: data.net_medication_fee },
                        { name: 'Laboratory Fee', fee: data.lab_fee, discount: data.lab_discount, net: data.net_lab_fee },
                        { name: 'Radiology Fee', fee: data.rad_fee, discount: data.rad_discount, net: data.net_rad_fee },
                        { name: 'Operating Room Fee', fee: data.operating_room_fee, discount: data.or_discount, net: data.net_or_fee },
                        { name: 'Supplies Fee', fee: data.supplies_fee, discount: data.supplies_discount, net: data.net_supplies_fee },
                        { name: 'Others Fee', fee: data.others_fee, discount: data.other_discount, net: data.net_others_fee },
                        { name: 'Professional Fee', fee: data.professional_fee, discount: data.pf_discount, net: data.net_pf_fee },
                        { name: 'Readers Fee', fee: data.readers_fee, discount: data.readers_discount, net: data.net_readers_fee }
                    ];

                    fees.forEach(item => {
                        if (parseFloat(item.fee || 0) > 0) {  // Only show rows with fees
                            const row = document.createElement('tr');
                            row.innerHTML = `
                                <td>${item.name}</td>
                                <td>₱${parseFloat(item.fee || 0).toFixed(2)}</td>
                                <td>₱${parseFloat(item.discount || 0).toFixed(2)}</td>
                                <td>₱${parseFloat(item.net || 0).toFixed(2)}</td>
                            `;
                            tbody.appendChild(row);
                        }
                    });

                    // Show total fees
                    const subTotal = parseFloat(data.total_amount || 0);
                    document.getElementById('inpatientGrossAmount').textContent = `₱${subTotal.toFixed(2)}`;
                    
                    // Show all discounts
                    const philHealthPF = parseFloat(data.philhealth_pf || 0);
                    const philHealthHB = parseFloat(data.philhealth_hb || 0);
                    const vatExempt = parseFloat(data.vat_exempt_discount_amount || 0);
                    const seniorDiscount = parseFloat(data.discount_amount || 0);
                    const pwdDiscount = parseFloat(data.pwd_discount_amount || 0);
                    
                    document.getElementById('inpatientPhilhealthPF').textContent = `₱${philHealthPF.toFixed(2)}`;
                    document.getElementById('inpatientPhilhealthHB').textContent = `₱${philHealthHB.toFixed(2)}`;
                    document.getElementById('inpatientVatExempt').textContent = `₱${vatExempt.toFixed(2)}`;
                    document.getElementById('inpatientSeniorDiscount').textContent = `₱${seniorDiscount.toFixed(2)}`;
                    document.getElementById('inpatientPWDDiscount').textContent = `₱${pwdDiscount.toFixed(2)}`;
                    
                    // Calculate final amount due
                    const totalDue = subTotal - vatExempt - seniorDiscount - pwdDiscount - philHealthPF - philHealthHB;
                    document.getElementById('inpatientAmountDue').textContent = `₱${totalDue.toFixed(2)}`;
                    currentTotalDue = totalDue;
                    updateAmountToPay(totalDue);
                });
        }
    }
});

function updateAmountToPay(total) {
    document.querySelector('input[name="amount_to_pay"]').value = total.toFixed(2);
}

</script>
<style>
.btn-primary.submit-btn {
    border-radius: 4px; 
    padding: 10px 20px;
    font-size: 16px;
}
.btn-primary {
    background: #12369e;
    border: none;
}
.btn-primary:hover {
    background: #05007E;
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
.search-results {
    position: absolute;
    top: 100%;
    left: 0;
    right: 0;
    z-index: 1000;
    max-height: 200px;
    overflow-y: auto;
    background: white;
    border: 1px solid #ddd;
    border-radius: 4px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.search-result {
    padding: 8px 15px;
    cursor: pointer;
    list-style: none;
    border-bottom: 1px solid #eee;
}

.search-result:hover {
    background-color: #12369e;
    color: white;
}

.search-result:last-child {
    border-bottom: none;
}

.input-group {
    position: relative;
}
</style>
