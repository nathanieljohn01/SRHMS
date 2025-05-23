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
    $patient_name = mysqli_real_escape_string($connection, $_POST['patient_name']);
    $patient_type = mysqli_real_escape_string($connection, $_POST['patient_type']);
    
    // Validation checks
    $errors = [];
    
    if (empty($patient_name)) {
        $errors[] = "Patient name is required";
    }
    
    if (empty($patient_type)) {
        $errors[] = "Patient type is required";
    }

    // Fetch patient_id
    $patient_id = null;
    if ($patient_type == 'Newborn') {
        $patient_query = mysqli_query($connection, "SELECT newborn_id FROM tbl_newborn WHERE CONCAT(first_name, ' ', last_name) = '$patient_name'");
        if (!$patient_query) {
            $errors[] = "Database error: " . mysqli_error($connection);
        } else {
            $patient_row = mysqli_fetch_assoc($patient_query);
            if (!$patient_row) {
                $errors[] = "Newborn not found in the system.";
            } else {
                $patient_id = $patient_row['newborn_id'];
            }
        }
    } else {
        $patient_query = mysqli_query($connection, "SELECT patient_id FROM tbl_patient WHERE CONCAT(first_name, ' ', last_name) = '$patient_name'");
        if (!$patient_query) {
            $errors[] = "Database error: " . mysqli_error($connection);
        } else {
            $patient_row = mysqli_fetch_assoc($patient_query);
            if (!$patient_row) {
                $errors[] = "Patient not found in the system.";
            } else {
                $patient_id = $patient_row['patient_id'];
            }
        }
    }
 
    if (empty($errors)) {
        mysqli_begin_transaction($connection);
        
        try {
            $patient_name = mysqli_real_escape_string($connection, $patient_name);
            $patient_id = mysqli_real_escape_string($connection, $patient_id);
            $payment_id = mysqli_real_escape_string($connection, $payment_id);
            $patient_type = mysqli_real_escape_string($connection, $patient_type);
    
            if ($patient_type == 'Inpatient') {
                // Get inpatient billing details including billing_id
                $total_due_query = mysqli_query($connection, "
                    SELECT id, billing_id, total_due, remaining_balance 
                    FROM tbl_billing_inpatient 
                    WHERE patient_name = '$patient_name' 
                    ORDER BY id DESC LIMIT 1
                ");
                
                if (!$total_due_query) {
                    throw new Exception("Error fetching billing details: " . mysqli_error($connection));
                }
                
                $total_due_row = mysqli_fetch_assoc($total_due_query);
                $total_due = $total_due_row ? $total_due_row['total_due'] : 0;
                $amount_to_pay = $total_due_row ? $total_due_row['remaining_balance'] : 0;
                $billing_id = $total_due_row ? $total_due_row['billing_id'] : null;
                $billing_inpatient_id = $total_due_row ? $total_due_row['id'] : null;
                
                $amount_paid = str_replace(',', '', $_POST['amount_paid']);
                $amount_paid = floatval($amount_paid);
                $initial_remaining = max(0, $amount_to_pay - $amount_paid);
                
                // Insert payment record for inpatient
                $insert_query = "INSERT INTO tbl_payment (
                    payment_id, patient_id, patient_name, patient_type,
                    total_due, amount_to_pay, amount_paid, remaining_balance, payment_datetime
                ) VALUES (
                    '$payment_id', '$patient_id', '$patient_name', '$patient_type',
                    $total_due, $amount_to_pay, $amount_paid, $initial_remaining, NOW()
                )";
                
                if (!mysqli_query($connection, $insert_query)) {
                    throw new Exception("Error inserting payment: " . mysqli_error($connection));
                }
                
                // Update inpatient billing - force status update
                $update_balance = mysqli_query($connection, "
                    UPDATE tbl_billing_inpatient 
                    SET remaining_balance = $initial_remaining,
                        status = CASE 
                            WHEN $initial_remaining <= 0 THEN 'Paid'
                            ELSE 'Partial' 
                        END
                    WHERE id = $billing_inpatient_id
                ");
                
                if (!$update_balance) {
                    throw new Exception("Error updating balance: " . mysqli_error($connection));
                }
    
                // If payment is complete (remaining balance is 0), update all related records
                if ($initial_remaining <= 0 && $billing_id) {
                    // Prepare all update queries
                    $update_queries = [
                        "UPDATE tbl_billing_others SET is_billed = 1 WHERE billing_id = '$billing_id'",
                        "UPDATE tbl_inpatient_record SET is_billed = 1 WHERE patient_name = '$patient_name'",
                        "UPDATE tbl_operating_room SET is_billed = 1 WHERE patient_name = '$patient_name'",
                        "UPDATE tbl_laborder SET is_billed = 1 WHERE patient_name = '$patient_name' AND deleted = 0",
                        "UPDATE tbl_radiology SET is_billed = 1 WHERE patient_name = '$patient_name' AND deleted = 0",
                        "UPDATE tbl_treatment SET is_billed = 1 WHERE patient_name = '$patient_name'"
                    ];
                    
                    // Execute all updates
                    foreach ($update_queries as $query) {
                        if (!mysqli_query($connection, $query)) {
                            throw new Exception("Error updating records: " . mysqli_error($connection));
                        }
                    }
                }
            } 
            else if ($patient_type == 'Outpatient') {
                // Calculate totals
                $lab_query = mysqli_query($connection, "SELECT COALESCE(SUM(price), 0) as total_lab FROM tbl_laborder 
                    WHERE patient_name = '$patient_name' AND is_billed = 0 AND deleted = 0");
                $rad_query = mysqli_query($connection, "SELECT COALESCE(SUM(price), 0) as total_rad FROM tbl_radiology 
                    WHERE patient_name = '$patient_name' AND is_billed = 0 AND deleted = 0");
                
                $lab_total = mysqli_fetch_assoc($lab_query)['total_lab'];
                $rad_total = mysqli_fetch_assoc($rad_query)['total_rad'];
                
                $amount_to_pay = $lab_total + $rad_total;
                $total_due = $amount_to_pay;
                $amount_paid = $amount_to_pay; // Force full payment
                $initial_remaining = 0;
                
                // Insert payment record
                $insert_result = mysqli_query($connection, "INSERT INTO tbl_payment (
                    payment_id, patient_id, patient_name, patient_type,
                    total_due, amount_to_pay, amount_paid, remaining_balance, payment_datetime
                ) VALUES (
                    '$payment_id', '$patient_id', '$patient_name', '$patient_type',
                    $total_due, $amount_to_pay, $amount_paid, $initial_remaining, NOW()
                )");
                
                if (!$insert_result) {
                    throw new Exception("Payment insert failed: " . mysqli_error($connection));
                }
                
                // Update lab orders
                $update_lab = mysqli_query($connection, "UPDATE tbl_laborder SET is_billed = 1 
                    WHERE patient_name = '$patient_name' AND is_billed = 0 AND deleted = 0");
                    
                if (!$update_lab) {
                    throw new Exception("Lab update failed: " . mysqli_error($connection));
                }
                
                // Update radiology orders
                $update_rad = mysqli_query($connection, "UPDATE tbl_radiology SET is_billed = 1 
                    WHERE patient_name = '$patient_name' AND is_billed = 0 AND deleted = 0");
                    
                if (!$update_rad) {
                    throw new Exception("Radiology update failed: " . mysqli_error($connection));
                }
            } 
            else if ($patient_type == 'Hemodialysis') {
                // Get hemodialysis billing details including billing_id
                $total_due_query = mysqli_query($connection, "
                    SELECT id, billing_id, total_due, remaining_balance 
                    FROM tbl_billing_hemodialysis 
                    WHERE patient_name = '$patient_name' 
                    ORDER BY id DESC LIMIT 1
                ");
                
                if (!$total_due_query) {
                    throw new Exception("Error fetching billing details: " . mysqli_error($connection));
                }
                
                $total_due_row = mysqli_fetch_assoc($total_due_query);
                $total_due = $total_due_row ? $total_due_row['total_due'] : 0;
                $amount_to_pay = $total_due_row ? $total_due_row['remaining_balance'] : 0;
                $billing_id = $total_due_row ? $total_due_row['billing_id'] : null;
                $billing_hemodialysis_id = $total_due_row ? $total_due_row['id'] : null;
                
                $amount_paid = str_replace(',', '', $_POST['amount_paid']);
                $amount_paid = floatval($amount_paid);
                $initial_remaining = max(0, $amount_to_pay - $amount_paid);
                
                // Insert payment record for hemodialysis
                $insert_query = "INSERT INTO tbl_payment (
                    payment_id, patient_id, patient_name, patient_type,
                    total_due, amount_to_pay, amount_paid, remaining_balance, payment_datetime
                ) VALUES (
                    '$payment_id', '$patient_id', '$patient_name', '$patient_type',
                    $total_due, $amount_to_pay, $amount_paid, $initial_remaining, NOW()
                )";
            
                if (!mysqli_query($connection, $insert_query)) {
                    throw new Exception("Error inserting payment: " . mysqli_error($connection));
                }
                
                // Update hemodialysis billing - force status update
                $update_balance = mysqli_query($connection, "
                    UPDATE tbl_billing_hemodialysis 
                    SET remaining_balance = $initial_remaining,
                        status = CASE 
                            WHEN $initial_remaining <= 0 THEN 'Paid'
                            ELSE 'Partial' 
                        END
                    WHERE id = $billing_hemodialysis_id
                ");
                
                if (!$update_balance) {
                    throw new Exception("Error updating balance: " . mysqli_error($connection));
                }
            
                // If payment is complete (remaining balance is 0), update all related records
                if ($initial_remaining <= 0 && $billing_id) {
                    // Prepare all update queries
                    $update_queries = [
                        "UPDATE tbl_billing_others SET is_billed = 1 WHERE billing_id = '$billing_id'",
                        "UPDATE tbl_hemodialysis SET is_billed = 1 WHERE patient_name = '$patient_name'",
                        "UPDATE tbl_laborder SET is_billed = 1 WHERE patient_name = '$patient_name' AND deleted = 0",
                        "UPDATE tbl_radiology SET is_billed = 1 WHERE patient_name = '$patient_name' AND deleted = 0",
                        "UPDATE tbl_treatment SET is_billed = 1 WHERE patient_name = '$patient_name'"
                    ];
                    
                    // Execute all updates
                    foreach ($update_queries as $query) {
                        if (!mysqli_query($connection, $query)) {
                            throw new Exception("Error updating records: " . mysqli_error($connection));
                        }
                    }
                }
            } 
            else if ($patient_type == 'Newborn') {
                // Get newborn billing details including billing_id
                $total_due_query = mysqli_query($connection, "
                    SELECT id, billing_id, total_due, remaining_balance 
                    FROM tbl_billing_newborn 
                    WHERE patient_name = '$patient_name' 
                    ORDER BY id DESC LIMIT 1
                ");
            
                if (!$total_due_query) {
                    throw new Exception("Error fetching billing details: " . mysqli_error($connection));
                }
            
                $total_due_row = mysqli_fetch_assoc($total_due_query);
                $total_due = $total_due_row ? $total_due_row['total_due'] : 0;
                $amount_to_pay = $total_due_row ? $total_due_row['remaining_balance'] : 0;
                $billing_id = $total_due_row ? $total_due_row['billing_id'] : null;
                $billing_newborn_id = $total_due_row ? $total_due_row['id'] : null;
                
                $amount_paid = isset($_POST['amount_paid']) ? $_POST['amount_paid'] : '0';
                $amount_paid = str_replace(',', '', $amount_paid);
                $amount_paid = floatval($amount_paid);
                
                $initial_remaining = max(0, $amount_to_pay - $amount_paid);
            
                // Insert payment record
                $insert_query = "INSERT INTO tbl_payment (
                    payment_id, patient_id, patient_name, patient_type,
                    total_due, amount_to_pay, amount_paid, remaining_balance, payment_datetime
                ) VALUES (
                    '$payment_id', '$patient_id', '$patient_name', '$patient_type',
                    $total_due, $amount_to_pay, $amount_paid, $initial_remaining, NOW()
                )";
            
                if (!mysqli_query($connection, $insert_query)) {
                    throw new Exception("Error inserting payment record: " . mysqli_error($connection));
                }
            
                // Update newborn billing - force status update
                $update_balance = mysqli_query($connection, "
                    UPDATE tbl_billing_newborn 
                    SET remaining_balance = $initial_remaining,
                        status = CASE 
                            WHEN $initial_remaining <= 0 THEN 'Paid'
                            ELSE 'Partial' 
                        END
                    WHERE id = $billing_newborn_id
                ");
            
                if (!$update_balance) {
                    throw new Exception("Error updating balance: " . mysqli_error($connection));
                }
            
                // If payment is complete (remaining balance is 0), update all related records
                if ($initial_remaining <= 0 && $billing_id) {
                    // Prepare all update queries
                    $update_queries = [
                        "UPDATE tbl_billing_others SET is_billed = 1 WHERE billing_id = '$billing_id'",
                        "UPDATE tbl_newborn SET is_billed = 1 WHERE CONCAT(first_name, ' ', last_name) = '$patient_name'",
                        "UPDATE tbl_laborder SET is_billed = 1 WHERE patient_name = '$patient_name' AND deleted = 0",
                        "UPDATE tbl_radiology SET is_billed = 1 WHERE patient_name = '$patient_name' AND deleted = 0",
                        "UPDATE tbl_treatment SET is_billed = 1 WHERE patient_name = '$patient_name'"
                    ];
                    
                    // Execute all updates
                    foreach ($update_queries as $query) {
                        if (!mysqli_query($connection, $query)) {
                            throw new Exception("Error updating records: " . mysqli_error($connection));
                        }
                    }
                }
            }
            
            // Commit transaction if all queries succeed
            mysqli_commit($connection);
            
            echo "
            <script src='https://cdn.jsdelivr.net/npm/sweetalert2@10'></script>
            <script>
                document.addEventListener('DOMContentLoaded', function() {
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
        } catch (Exception $e) {
            mysqli_rollback($connection);
            
            // Log the error for debugging
            error_log("Payment Processing Error: " . $e->getMessage());
            
            echo "
            <script src='https://cdn.jsdelivr.net/npm/sweetalert2@10'></script>
            <script>
                document.addEventListener('DOMContentLoaded', function() {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: 'Error processing payment. Please try again.',
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
            <div class="col-sm-12">
                <div class="page-header">
                    <div class="row align-items-center">
                        <div class="col-md-6">
                            <h4 class="page-title">Add Payment</h4>
                        </div>
                        <div class="col-md-6 text-right">
                            <a href="payment-processing.php" class="btn btn-primary">
                                <i class="fas fa-arrow-left mr-2"></i> Back
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h5 class="card-title" style="font-weight: bold;">Payment Information</h5>
            </div>
            <div class="card-body">
                <form method="POST">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="font-weight-bold">Payment ID</label>
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text bg-light"><i class="fas fa-id-badge"></i></span>
                                    </div>
                                    <input class="form-control" type="text" name="payment_id" value="<?php if(!empty($pay_id)) { echo 'PAY-'.$pay_id; } else { echo 'PAY-1'; } ?>" readonly>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="font-weight-bold">Patient Type</label>
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text bg-light"><i class="fas fa-user-tag"></i></span>
                                    </div>
                                    <select class="form-control select2" name="patient_type" id="patient_type" required onchange="togglePatientSearch()">
                                        <option value="">Select Patient Type</option>
                                        <option value="Outpatient">Outpatient</option>
                                        <option value="Inpatient">Inpatient</option>
                                        <option value="Hemodialysis">Hemodialysis</option>
                                        <option value="Newborn">Newborn</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="font-weight-bold">Patient Name</label>
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text bg-light"><i class="fas fa-user"></i></span>
                                    </div>
                                    <input type="text" class="form-control" id="patient_search" placeholder="Search patient name" autocomplete="off">
                                    <input type="hidden" name="patient_name" id="selected_patient_name">
                                </div>
                                <div class="search-results" id="search_results" style="display:none;"></div>
                            </div>
                        </div>

                        <div class="col-md-2">
                            <div class="form-group">
                                <label class="font-weight-bold">Amount to Pay</label>
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text bg-light">₱</span>
                                    </div>
                                    <input type="number" step="0.01" class="form-control" name="amount_to_pay" id="amount_to_pay" required readonly>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-2">
                            <div class="form-group">
                                <label class="font-weight-bold">Amount Paid</label>
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text bg-light">₱</span>
                                    </div>
                                    <input type="number" step="0.01" class="form-control" name="amount_paid" id="amount_paid" required oninput="calculatePayment()">
                                </div>
                            </div>
                        </div>

                        <div class="col-md-2">
                            <div class="form-group">
                                <label class="font-weight-bold">Change</label>
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text bg-light">₱</span>
                                    </div>
                                    <input type="number" step="0.01" class="form-control" name="change" id="change" readonly style="background-color: #e8f5e9; font-weight: bold;">
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="row mt-3">
                        <div class="col-md-12">
                            <!-- Combined Outpatient Fees Card -->
                            <div class="card mb-4" id="outpatientFeesTable" style="display: none;">
                                <div class="card-header">
                                    <h5 class="card-title mb-0"><i class="fas fa-file-invoice-dollar mr-2"></i>Outpatient Fees</h5>
                                </div>
                                <div class="card-body p-0">
                                    <div class="table-responsive">
                                        <table class="table table-hover mb-0">
                                            <thead>
                                                <tr>
                                                    <th>Laboratory Test</th>
                                                    <th class="text-right">Price</th>
                                                    <th>Request Date</th>
                                                </tr>
                                            </thead>
                                            <tbody id="labFeesBody"></tbody>
                                            <thead>
                                                <tr>
                                                    <th>Radiology Test</th>
                                                    <th class="text-right">Price</th>
                                                    <th>Request Date</th>
                                                </tr>
                                            </thead>
                                            <tbody id="radFeesBody"></tbody>
                                            <tfoot class="font-weight-bold">
                                                <tr class="table-primary">
                                                    <td colspan="4" class="pl-4">Total Amount Due: <span id="outpatientTotalDue" class="float-right">₱0.00</span></td>
                                                </tr>
                                            </tfoot>
                                        </table>
                                    </div>
                                </div>
                            </div>

                            <!-- Inpatient Billing Card -->
                            <div class="card mb-4" id="inpatientBillingTable" style="display: none;">
                                <div class="card-header">
                                    <h5 class="card-title mb-0"><i class="fas fa-procedures mr-2"></i>Inpatient Billing Details</h5>
                                </div>
                                <div class="card-body p-0">
                                    <div class="table-responsive">
                                        <table class="table table-hover mb-0">
                                            <thead>
                                                <tr>
                                                    <th>Description</th>
                                                    <th class="text-right">Amount</th>
                                                    <th class="text-right">Discount</th>
                                                    <th class="text-right">Net Amount</th>
                                                </tr>
                                            </thead>
                                            <tbody></tbody>
                                            <tfoot class="font-weight-bold">
                                                <tr>
                                                    <td colspan="4" class="pl-4">Total Fees: <span id="inpatientGrossAmount" class="float-right">₱0.00</span></td>
                                                </tr>
                                                <tr class="text-muted">
                                                    <td colspan="4" class="pl-4"><small>Less:</small></td>
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
                                                <tr>
                                                    <td colspan="4" class="pl-5">Amount Already Paid <span id="inpatientAmountPaid" class="float-right">₱0.00</span></td>
                                                </tr>
                                                <tr class="table-primary">
                                                    <td colspan="4" class="pl-4">Total Amount Due: <span id="inpatientAmountDue" class="float-right">₱0.00</span></td>
                                                </tr>
                                                <tr class="table-active">
                                                    <td colspan="4" class="pl-4">Remaining Balance: <span id="inpatientRemainingBalance" class="float-right">₱0.00</span></td>
                                                </tr>
                                            </tfoot>
                                        </table>
                                    </div>
                                </div>
                            </div>

                            <!-- Hemodialysis Billing Card -->
                            <div class="card mb-4" id="hemodialysisBillingTable" style="display: none;">
                                <div class="card-header">
                                    <h5 class="card-title mb-0"><i class="fas fa-heartbeat mr-2"></i>Hemodialysis Billing Details</h5>
                                </div>
                                <div class="card-body p-0">
                                    <div class="table-responsive">
                                        <table class="table table-hover mb-0">
                                            <thead>
                                                <tr>
                                                    <th>Description</th>
                                                    <th class="text-right">Amount</th>
                                                    <th class="text-right">Discount</th>
                                                    <th class="text-right">Net Amount</th>
                                                </tr>
                                            </thead>
                                            <tbody></tbody>
                                            <tfoot class="font-weight-bold">
                                                <tr>
                                                    <td colspan="4" class="pl-4">Total Fees: <span id="hemodialysisGrossAmount" class="float-right">₱0.00</span></td>
                                                </tr>
                                                <tr class="text-muted">
                                                    <td colspan="4" class="pl-4"><small>Less:</small></td>
                                                </tr>
                                                <tr>
                                                    <td colspan="4" class="pl-5">PhilHealth (PF) <span id="hemodialysisPhilhealthPF" class="float-right">₱0.00</span></td>
                                                </tr>
                                                <tr>
                                                    <td colspan="4" class="pl-5">PhilHealth (HB) <span id="hemodialysisPhilhealthHB" class="float-right">₱0.00</span></td>
                                                </tr>
                                                <tr>
                                                    <td colspan="4" class="pl-5">VAT Exempt <span id="hemodialysisVatExempt" class="float-right">₱0.00</span></td>
                                                </tr>
                                                <tr>
                                                    <td colspan="4" class="pl-5">Senior Citizen Discount <span id="hemodialysisSeniorDiscount" class="float-right">₱0.00</span></td>
                                                </tr>
                                                <tr>
                                                    <td colspan="4" class="pl-5">PWD Discount <span id="hemodialysisPWDDiscount" class="float-right">₱0.00</span></td>
                                                </tr>
                                                <tr>
                                                    <td colspan="4" class="pl-5">Amount Already Paid <span id="hemodialysisAmountPaid" class="float-right">₱0.00</span></td>
                                                </tr>
                                                <tr class="table-primary">
                                                    <td colspan="4" class="pl-4">Total Amount Due: <span id="hemodialysisAmountDue" class="float-right">₱0.00</span></td>
                                                </tr>
                                                <tr class="table-active">
                                                    <td colspan="4" class="pl-4">Remaining Balance: <span id="hemodialysisRemainingBalance" class="float-right">₱0.00</span></td>
                                                </tr>
                                            </tfoot>
                                        </table>
                                    </div>
                                </div>
                            </div>

                            <!-- Newborn Billing Card -->
                            <div class="card mb-4" id="newbornBillingTable" style="display: none;">
                                <div class="card-header">
                                    <h5 class="card-title mb-0"><i class="fas fa-baby mr-2"></i>Newborn Billing Details</h5>
                                </div>
                                <div class="card-body p-0">
                                    <div class="table-responsive">
                                        <table class="table table-hover mb-0">
                                            <thead>
                                                <tr>
                                                    <th>Description</th>
                                                    <th class="text-right">Amount</th>
                                                    <th class="text-right">Discount</th>
                                                    <th class="text-right">Net Amount</th>
                                                </tr>
                                            </thead>
                                            <tbody></tbody>
                                            <tfoot class="font-weight-bold">
                                                <tr>
                                                    <td colspan="4" class="pl-4">Total Fees: <span id="newbornGrossAmount" class="float-right">₱0.00</span></td>
                                                </tr>
                                                <tr class="text-muted">
                                                    <td colspan="4" class="pl-4"><small>Less:</small></td>
                                                </tr>
                                                <tr>
                                                    <td colspan="4" class="pl-5">PhilHealth (PF) <span id="newbornPhilhealthPF" class="float-right">₱0.00</span></td>
                                                </tr>
                                                <tr>
                                                    <td colspan="4" class="pl-5">PhilHealth (HB) <span id="newbornPhilhealthHB" class="float-right">₱0.00</span></td>
                                                </tr>
                                                <tr>
                                                    <td colspan="4" class="pl-5">VAT Exempt <span id="newbornVatExempt" class="float-right">₱0.00</span></td>
                                                </tr>
                                                <tr>
                                                    <td colspan="4" class="pl-5">Senior Citizen Discount <span id="newbornSeniorDiscount" class="float-right">₱0.00</span></td>
                                                </tr>
                                                <tr>
                                                    <td colspan="4" class="pl-5">PWD Discount <span id="newbornPWDDiscount" class="float-right">₱0.00</span></td>
                                                </tr>
                                                <tr>
                                                    <td colspan="4" class="pl-5">Amount Already Paid <span id="newbornAmountPaid" class="float-right">₱0.00</span></td>
                                                </tr>
                                                <tr class="table-primary">
                                                    <td colspan="4" class="pl-4">Total Amount Due: <span id="newbornAmountDue" class="float-right">₱0.00</span></td>
                                                </tr>
                                                <tr class="table-active">
                                                    <td colspan="4" class="pl-4">Remaining Balance: <span id="newbornRemainingBalance" class="float-right">₱0.00</span></td>
                                                </tr>
                                            </tfoot>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="row mt-4">
                        <div class="col-md-12 text-center">
                            <button type="submit" name="submit_payment" class="btn btn-primary btn-lg">
                                <i class="fas fa-credit-card mr-2"></i> Submit Payment
                            </button>
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
const outpatientFeesTable = document.getElementById('outpatientFeesTable');
const inpatientBillingTable = document.getElementById('inpatientBillingTable');
const hemodialysisBillingTable = document.getElementById('hemodialysisBillingTable');
const newbornBillingTable = document.getElementById('newbornBillingTable');
const patientTypeSelect = document.getElementById('patient_type');
let currentTotalDue = 0;

function calculatePayment() {
    const amountPaid = parseFloat(document.getElementById('amount_paid').value) || 0;
    const amountToPay = parseFloat(document.getElementById('amount_to_pay').value) || 0;
    const changeInput = document.getElementById('change');
    
    if (amountPaid > amountToPay) {
        const change = amountPaid - amountToPay;
        changeInput.value = change.toFixed(2);
        document.getElementById('amount_paid').value = amountToPay.toFixed(2);
    } else {
        changeInput.value = '0.00';
    }
}

function togglePatientSearch() {
    const selectedType = patientTypeSelect.value;
    patientSearch.parentElement.parentElement.style.display = 'block';

    // Clear all tables and amounts
    outpatientFeesTable.style.display = 'none';
    inpatientBillingTable.style.display = 'none';
    hemodialysisBillingTable.style.display = 'none';
    newbornBillingTable.style.display = 'none';

    // Clear table contents
    document.getElementById('labFeesBody').innerHTML = '';
    document.getElementById('radFeesBody').innerHTML = '';
    inpatientBillingTable.querySelector('tbody').innerHTML = '';
    hemodialysisBillingTable.querySelector('tbody').innerHTML = '';
    newbornBillingTable.querySelector('tbody').innerHTML = '';

    // Clear all amounts and spans
    const fieldsToReset = [
        'outpatientTotalDue',
        'inpatientGrossAmount', 'inpatientPhilhealthPF', 'inpatientPhilhealthHB', 'inpatientVatExempt', 
        'inpatientSeniorDiscount', 'inpatientPWDDiscount', 'inpatientAmountPaid', 
        'inpatientAmountDue', 'inpatientRemainingBalance',

        'hemodialysisGrossAmount', 'hemodialysisPhilhealthPF', 'hemodialysisPhilhealthHB', 
        'hemodialysisVatExempt', 'hemodialysisSeniorDiscount', 'hemodialysisPWDDiscount', 
        'hemodialysisAmountPaid', 'hemodialysisAmountDue', 'hemodialysisRemainingBalance',

        'newbornGrossAmount', 'newbornPhilhealthPF', 'newbornPhilhealthHB', 'newbornVatExempt', 
        'newbornSeniorDiscount', 'newbornPWDDiscount', 'newbornAmountPaid', 
        'newbornAmountDue', 'newbornRemainingBalance'
    ];

    fieldsToReset.forEach(id => document.getElementById(id).textContent = '₱0.00');
    
    currentTotalDue = 0;

    // Show appropriate table based on type
    if (selectedType === 'Outpatient') {
        outpatientFeesTable.style.display = 'block';
    } else if (selectedType === 'Inpatient') {
        inpatientBillingTable.style.display = 'block';
    } else if (selectedType === 'Hemodialysis') {
        hemodialysisBillingTable.style.display = 'block';
    } else if (selectedType === 'Newborn') {
        newbornBillingTable.style.display = 'block';
    }

    // Clear search and amounts
    patientSearch.value = '';
    document.getElementById('selected_patient_name').value = '';
    document.getElementById('amount_to_pay').value = '';
    document.getElementById('amount_paid').value = '';
    document.getElementById('change').value = '0.00';
}

patientSearch.addEventListener('keyup', function() {
    const selectedType = patientTypeSelect.value;
    
    if (this.value.length > 0) {
        const searchEndpoint = selectedType === 'Outpatient' ? 'search-opt.php' : 
                             selectedType === 'Inpatient' ? 'search-ipt-payment.php' : 
                             selectedType === 'Hemodialysis' ? 'search-hemodialysis-payment.php' : 
                             selectedType === 'Newborn' ? 'search-nb-payment.php' : '';

        if (searchEndpoint) {
            fetch(searchEndpoint + '?search=' + encodeURIComponent(this.value))
                .then(response => response.json())
                .then(data => {
                    searchResults.style.display = 'block';
                    searchResults.innerHTML = data.map(patient => {
                        const id = selectedType === 'Newborn' ? patient.newborn_id : patient.patient_id;
                        return `<li class="search-result" data-id="${id}">${patient.patient_name}</li>`;
                    }).join('');
                })
                .catch(error => console.error('Error fetching data:', error));
        }
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
            outpatientFeesTable.style.display = 'block';
            
            // Clear previous results
            document.getElementById('labFeesBody').innerHTML = '';
            document.getElementById('radFeesBody').innerHTML = '';
            document.getElementById('outpatientTotalDue').textContent = '₱0.00';

            // Fetch both lab and radiology fees in parallel
            Promise.all([
                fetch('opt-lab-fee.php?patient_name=' + encodeURIComponent(patientName))
                    .then(response => {
                        if (!response.ok) throw new Error('Lab fees fetch failed');
                        return response.json();
                    }),
                fetch('opt-rad-fee.php?patient_name=' + encodeURIComponent(patientName))
                    .then(response => {
                        if (!response.ok) throw new Error('Radiology fees fetch failed');
                        return response.json();
                    })
            ])
            .then(([labData, radData]) => {
                const labFeesBody = document.getElementById('labFeesBody');
                const radFeesBody = document.getElementById('radFeesBody');
                
                // Process lab fees
                if (labData && labData.length > 0) {
                    labData.forEach(bill => {
                        const row = document.createElement('tr');
                        row.innerHTML = `
                            <td>${bill.lab_test || 'N/A'}</td>
                            <td class="text-right">₱${parseFloat(bill.lab_price || 0).toFixed(2)}</td>
                            <td>${bill.lab_requested_date || 'N/A'}</td>
                        `;
                        labFeesBody.appendChild(row);
                        totalAmount += parseFloat(bill.lab_price || 0);
                    });
                } else {
                    labFeesBody.innerHTML = '<tr><td colspan="3" class="text-center">No laboratory fees found</td></tr>';
                }

                // Process radiology fees
                if (radData && radData.length > 0) {
                    radData.forEach(bill => {
                        const row = document.createElement('tr');
                        row.innerHTML = `
                            <td>${bill.test_type || 'N/A'}</td>
                            <td class="text-right">₱${parseFloat(bill.price || 0).toFixed(2)}</td>
                            <td>${bill.requested_date || 'N/A'}</td>
                        `;
                        radFeesBody.appendChild(row);
                        totalAmount += parseFloat(bill.price || 0);
                    });
                } else {
                    radFeesBody.innerHTML = '<tr><td colspan="3" class="text-center">No radiology fees found</td></tr>';
                }

                // Update total amount
                document.getElementById('outpatientTotalDue').textContent = `₱${totalAmount.toFixed(2)}`;
                updateAmountToPay(totalAmount);
            })
            .catch(error => {
                console.error('Error fetching outpatient fees:', error);
                // Show error messages in the tables
                document.getElementById('labFeesBody').innerHTML = '<tr><td colspan="3" class="text-center text-danger">Error loading laboratory fees</td></tr>';
                document.getElementById('radFeesBody').innerHTML = '<tr><td colspan="3" class="text-center text-danger">Error loading radiology fees</td></tr>';
            });
        } else if (selectedType === 'Inpatient') {
            // For inpatients, fetch their billing data
            fetch('ipt-billing-fee.php?patient_name=' + encodeURIComponent(patientName))
                .then(response => {
                    if (!response.ok) throw new Error('Failed to fetch inpatient billing data');
                    return response.json();
                })
                .then(data => {
                    if (!data) throw new Error('No data returned for inpatient billing');
                    
                    const tbody = inpatientBillingTable.querySelector('tbody');
                    tbody.innerHTML = '';
                    inpatientBillingTable.style.display = 'block';

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
                        if (parseFloat(item.fee || 0) > 0) {
                            const row = document.createElement('tr');
                            row.innerHTML = `
                                <td>${item.name}</td>
                                <td class="text-right">₱${parseFloat(item.fee || 0).toFixed(2)}</td>
                                <td class="text-right">₱${parseFloat(item.discount || 0).toFixed(2)}</td>
                                <td class="text-right">₱${parseFloat(item.net || 0).toFixed(2)}</td>
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
                    
                    // Get amount paid from tbl_payment
                    fetch('get-total-payments.php?patient_name=' + encodeURIComponent(patientName))
                        .then(response => {
                            if (!response.ok) throw new Error('Failed to fetch payment data');
                            return response.json();
                        })
                        .then(paymentData => {
                            const amountPaid = parseFloat(paymentData.total_paid || 0);
                            document.getElementById('inpatientAmountPaid').textContent = `₱${amountPaid.toFixed(2)}`;
                            
                            // Calculate final amount due
                            const totalDue = subTotal - vatExempt - seniorDiscount - pwdDiscount - philHealthPF - philHealthHB;
                            document.getElementById('inpatientAmountDue').textContent = `₱${totalDue.toFixed(2)}`;
                            currentTotalDue = totalDue;
                            
                            // Show remaining balance and set as amount_to_pay
                            const remainingBalance = parseFloat(data.remaining_balance || totalDue - amountPaid);
                            document.getElementById('inpatientRemainingBalance').textContent = `₱${remainingBalance.toFixed(2)}`;
                            updateAmountToPay(remainingBalance > 0 ? remainingBalance : 0);
                        })
                        .catch(error => {
                            console.error('Error fetching payment data:', error);
                            // Calculate without payment data
                            const totalDue = subTotal - vatExempt - seniorDiscount - pwdDiscount - philHealthPF - philHealthHB;
                            document.getElementById('inpatientAmountDue').textContent = `₱${totalDue.toFixed(2)}`;
                            currentTotalDue = totalDue;
                            document.getElementById('inpatientRemainingBalance').textContent = `₱${totalDue.toFixed(2)}`;
                            updateAmountToPay(totalDue);
                        });
                })
                .catch(error => {
                    console.error('Error fetching inpatient billing data:', error);
                    // Show error in the table
                    const tbody = inpatientBillingTable.querySelector('tbody');
                    tbody.innerHTML = '<tr><td colspan="4" class="text-center text-danger">Error loading billing data</td></tr>';
                });
        } else if (selectedType === 'Hemodialysis') {
            // For hemodialysis patients, fetch their billing data
            fetch('hemodialysis-billing-fee.php?patient_name=' + encodeURIComponent(patientName))
                .then(response => {
                    if (!response.ok) throw new Error('Failed to fetch hemodialysis billing data');
                    return response.json();
                })
                .then(data => {
                    if (!data) throw new Error('No data returned for hemodialysis billing');
                    
                    const tbody = hemodialysisBillingTable.querySelector('tbody');
                    tbody.innerHTML = '';
                    hemodialysisBillingTable.style.display = 'block';

                    // Add rows for each fee type
                    const fees = [
                        { name: 'Medication Fee', fee: data.medication_fee, discount: data.med_discount, net: data.net_medication_fee },
                        { name: 'Laboratory Fee', fee: data.lab_fee, discount: data.lab_discount, net: data.net_lab_fee },
                        { name: 'Supplies Fee', fee: data.supplies_fee, discount: data.supplies_discount, net: data.net_supplies_fee },
                        { name: 'Others Fee', fee: data.others_fee, discount: data.other_discount, net: data.net_others_fee },
                        { name: 'Professional Fee', fee: data.professional_fee, discount: data.pf_discount, net: data.net_pf_fee },
                        { name: 'Readers Fee', fee: data.readers_fee, discount: data.readers_discount, net: data.net_readers_fee }
                    ];

                    fees.forEach(item => {
                        if (parseFloat(item.fee || 0) > 0) {
                            const row = document.createElement('tr');
                            row.innerHTML = `
                                <td>${item.name}</td>
                                <td class="text-right">₱${parseFloat(item.fee || 0).toFixed(2)}</td>
                                <td class="text-right">₱${parseFloat(item.discount || 0).toFixed(2)}</td>
                                <td class="text-right">₱${parseFloat(item.net || 0).toFixed(2)}</td>
                            `;
                            tbody.appendChild(row);
                        }
                    });

                    // Show total fees
                    const subTotal = parseFloat(data.total_amount || 0);
                    document.getElementById('hemodialysisGrossAmount').textContent = `₱${subTotal.toFixed(2)}`;
                    
                    // Show all discounts
                    const philHealthPF = parseFloat(data.philhealth_pf || 0);
                    const philHealthHB = parseFloat(data.philhealth_hb || 0);
                    const vatExempt = parseFloat(data.vat_exempt_discount_amount || 0);
                    const seniorDiscount = parseFloat(data.discount_amount || 0);
                    const pwdDiscount = parseFloat(data.pwd_discount_amount || 0);
                    
                    document.getElementById('hemodialysisPhilhealthPF').textContent = `₱${philHealthPF.toFixed(2)}`;
                    document.getElementById('hemodialysisPhilhealthHB').textContent = `₱${philHealthHB.toFixed(2)}`;
                    document.getElementById('hemodialysisVatExempt').textContent = `₱${vatExempt.toFixed(2)}`;
                    document.getElementById('hemodialysisSeniorDiscount').textContent = `₱${seniorDiscount.toFixed(2)}`;
                    document.getElementById('hemodialysisPWDDiscount').textContent = `₱${pwdDiscount.toFixed(2)}`;
                    
                    // Get amount paid from tbl_payment
                    fetch('get-total-payments.php?patient_name=' + encodeURIComponent(patientName))
                        .then(response => {
                            if (!response.ok) throw new Error('Failed to fetch payment data');
                            return response.json();
                        })
                        .then(paymentData => {
                            const amountPaid = parseFloat(paymentData.total_paid || 0);
                            document.getElementById('hemodialysisAmountPaid').textContent = `₱${amountPaid.toFixed(2)}`;
                            
                            // Calculate final amount due
                            const totalDue = subTotal - vatExempt - seniorDiscount - pwdDiscount - philHealthPF - philHealthHB;
                            document.getElementById('hemodialysisAmountDue').textContent = `₱${totalDue.toFixed(2)}`;
                            currentTotalDue = totalDue;
                            
                            // Show remaining balance and set as amount_to_pay
                            const remainingBalance = parseFloat(data.remaining_balance || totalDue - amountPaid);
                            document.getElementById('hemodialysisRemainingBalance').textContent = `₱${remainingBalance.toFixed(2)}`;
                            updateAmountToPay(remainingBalance > 0 ? remainingBalance : 0);
                        })
                        .catch(error => {
                            console.error('Error fetching payment data:', error);
                            // Calculate without payment data
                            const totalDue = subTotal - vatExempt - seniorDiscount - pwdDiscount - philHealthPF - philHealthHB;
                            document.getElementById('hemodialysisAmountDue').textContent = `₱${totalDue.toFixed(2)}`;
                            currentTotalDue = totalDue;
                            document.getElementById('hemodialysisRemainingBalance').textContent = `₱${totalDue.toFixed(2)}`;
                            updateAmountToPay(totalDue);
                        });
                })
                .catch(error => {
                    console.error('Error fetching hemodialysis billing data:', error);
                    // Show error in the table
                    const tbody = hemodialysisBillingTable.querySelector('tbody');
                    tbody.innerHTML = '<tr><td colspan="4" class="text-center text-danger">Error loading billing data</td></tr>';
                });
        } else if (selectedType === 'Newborn') {
            fetch('newborn-billing-fee.php?patient_name=' + encodeURIComponent(patientName))
                .then(response => {
                    if (!response.ok) throw new Error('Failed to fetch newborn billing data');
                    return response.json();
                })
                .then(data => {
                    if (!data) throw new Error('No data returned for newborn billing');
                    
                    const tbody = newbornBillingTable.querySelector('tbody');
                    tbody.innerHTML = '';
                    newbornBillingTable.style.display = 'block';

                    // Add rows for each fee type
                    const fees = [
                        { name: 'Room Fee', fee: data.room_fee, discount: data.room_discount, net: data.net_room_fee },
                        { name: 'Medication Fee', fee: data.medication_fee, discount: data.med_discount, net: data.net_medication_fee },
                        { name: 'Laboratory Fee', fee: data.lab_fee, discount: data.lab_discount, net: data.net_lab_fee },
                        { name: 'Supplies Fee', fee: data.supplies_fee, discount: data.supplies_discount, net: data.net_supplies_fee },
                        { name: 'Professional Fee', fee: data.professional_fee, discount: data.pf_discount, net: data.net_pf_fee },
                        { name: 'Readers Fee', fee: data.readers_fee, discount: data.readers_discount, net: data.net_readers_fee }
                    ];

                    fees.forEach(item => {
                        if (parseFloat(item.fee || 0) > 0) {
                            const row = document.createElement('tr');
                            row.innerHTML = `
                                <td>${item.name}</td>
                                <td class="text-right">₱${parseFloat(item.fee || 0).toFixed(2)}</td>
                                <td class="text-right">₱${parseFloat(item.discount || 0).toFixed(2)}</td>
                                <td class="text-right">₱${parseFloat(item.net || 0).toFixed(2)}</td>
                            `;
                            tbody.appendChild(row);
                        }
                    });

                    // Show total fees
                    const subTotal = parseFloat(data.total_amount || 0);
                    document.getElementById('newbornGrossAmount').textContent = `₱${subTotal.toFixed(2)}`;
                    
                    // Show all discounts
                    const philHealthPF = parseFloat(data.philhealth_pf || 0);
                    const philHealthHB = parseFloat(data.philhealth_hb || 0);
                    const vatExempt = parseFloat(data.vat_exempt_discount_amount || 0);
                    const seniorDiscount = parseFloat(data.discount_amount || 0);
                    const pwdDiscount = parseFloat(data.pwd_discount_amount || 0);
                    
                    document.getElementById('newbornPhilhealthPF').textContent = `₱${philHealthPF.toFixed(2)}`;
                    document.getElementById('newbornPhilhealthHB').textContent = `₱${philHealthHB.toFixed(2)}`;
                    document.getElementById('newbornVatExempt').textContent = `₱${vatExempt.toFixed(2)}`;
                    document.getElementById('newbornSeniorDiscount').textContent = `₱${seniorDiscount.toFixed(2)}`;
                    document.getElementById('newbornPWDDiscount').textContent = `₱${pwdDiscount.toFixed(2)}`;
                    
                    // Get amount paid from tbl_payment
                    fetch('get-total-payments.php?patient_name=' + encodeURIComponent(patientName))
                        .then(response => {
                            if (!response.ok) throw new Error('Failed to fetch payment data');
                            return response.json();
                        })
                        .then(paymentData => {
                            const amountPaid = parseFloat(paymentData.total_paid || 0);
                            document.getElementById('newbornAmountPaid').textContent = `₱${amountPaid.toFixed(2)}`;
                            
                            // Calculate final amount due
                            const totalDue = subTotal - vatExempt - seniorDiscount - pwdDiscount - philHealthPF - philHealthHB;
                            document.getElementById('newbornAmountDue').textContent = `₱${totalDue.toFixed(2)}`;
                            currentTotalDue = totalDue;
                            
                            // Show remaining balance and set as amount_to_pay
                            const remainingBalance = parseFloat(data.remaining_balance || totalDue - amountPaid);
                            document.getElementById('newbornRemainingBalance').textContent = `₱${remainingBalance.toFixed(2)}`;
                            updateAmountToPay(remainingBalance > 0 ? remainingBalance : 0);
                        })
                        .catch(error => {
                            console.error('Error fetching payment data:', error);
                            // Calculate without payment data
                            const totalDue = subTotal - vatExempt - seniorDiscount - pwdDiscount - philHealthPF - philHealthHB;
                            document.getElementById('newbornAmountDue').textContent = `₱${totalDue.toFixed(2)}`;
                            currentTotalDue = totalDue;
                            document.getElementById('newbornRemainingBalance').textContent = `₱${totalDue.toFixed(2)}`;
                            updateAmountToPay(totalDue);
                        });
                })
                .catch(error => {
                    console.error('Error fetching newborn billing data:', error);
                    // Show error in the table
                    const tbody = newbornBillingTable.querySelector('tbody');
                    tbody.innerHTML = '<tr><td colspan="4" class="text-center text-danger">Error loading billing data</td></tr>';
                });
        }
    }
});

function updateAmountToPay(total) {
    document.getElementById('amount_to_pay').value = total.toFixed(2);
    document.getElementById('amount_paid').value = '';
    document.getElementById('change').value = '0.00';
}

$('.dropdown-toggle').on('click', function (e) {
    var $el = $(this).next('.dropdown-menu');
    var isVisible = $el.is(':visible');
    
    // Hide all dropdowns
    $('.dropdown-menu').slideUp('400');
    
    // If this wasn't already visible, slide it down
    if (!isVisible) {
        $el.stop(true, true).slideDown('400');
    }
    
    // Close the dropdown if clicked outside of it
    $(document).on('click', function (e) {
        if (!$(e.target).closest('.dropdown').length) {
            $('.dropdown-menu').slideUp('400');
        }
    });
});
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
    border-radius: .375rem;
    border-color: #ced4da;
    background-color: #f8f9fa;
}
select.form-control {
    border-radius: .375rem;
    border: 1px solid #ced4da;
    background-color: #f8f9fa;
    padding: .375rem 2.5rem .375rem .75rem;
    font-size: 1rem;
    line-height: 1.5;
    height: calc(2.25rem + 2px);
    -webkit-appearance: none;
    -moz-appearance: none;
    appearance: none;
    background: url('data:image/svg+xml;charset=UTF-8,%3Csvg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="20" height="20"%3E%3Cpath d="M7 10l5 5 5-5z" fill="%23aaa"/%3E%3C/svg%3E') no-repeat right 0.75rem center;
    background-size: 20px;
}

/* Search results */
.search-results {
    position: absolute;
    z-index: 1000;
    width: 100%;
    max-height: 300px;
    overflow-y: auto;
    background: white;
    border: 1px solid #dee2e6;
    border-radius: 0.25rem;
    box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
    margin-top: 2px;
}
.search-result {
    padding: 0.75rem 1.25rem;
    cursor: pointer;
    list-style: none;
    border-bottom: 1px solid #f8f9fa;
    transition: all 0.2s;
}
.search-result:hover {
    background-color: #12369e;
    color: white;
}
.search-result:last-child {
    border-bottom: none;
}

/* Tables */
.table {
    font-size: 0.9rem;
}
.table th {
    border-top: none;
    font-weight: 600;
    text-transform: uppercase;
    font-size: 0.8rem;
    letter-spacing: 0.5px;
    color: #6c757d;
    background-color: #CCCCCC;
}
.table td, .table th {
    vertical-align: middle;
    padding: 0.75rem 1rem;
}
.table-hover tbody tr:hover {
    background-color: rgba(18, 54, 158, 0.05);
}

/* Responsive */
@media (max-width: 768px) {
    .card-body {
        padding: 1rem;
    }
    .table-responsive {
        border: none;
    }
}

/* Card headers */
.card-header {
    background-color: #CCCCCC;
    padding: 1rem 1.5rem;
    border-bottom: 1px solid rgba(0, 0, 0, 0.05);
}

.card-title {
    margin-bottom: 0.5rem;
    font-weight: 600;
    color: #343a40;
}
/* Amount styling */
.float-right {
    font-weight: 600;
}

/* Special table rows */
.table-primary td {
    background-color: rgba(18, 54, 158, 0.1) !important;
}
.table-active td {
    background-color: rgba(108, 117, 125, 0.1) !important;
}

/* Input group */
.input-group-text {
    background-color: #f8f9fa;
    border-color: #ced4da;
}

/* Change field styling */
#change {
    background-color: #f8f9fa !important;
    font-weight: bold;
    color: #2e7d32;
}

/* Section headers in tables */
.bg-light {
    background-color: #f8f9fa !important;
    font-weight: 600;
    text-transform: uppercase;
    font-size: 0.8rem;
    letter-spacing: 0.5px;
}
</style>