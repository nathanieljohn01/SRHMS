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
                // Get inpatient billing details
                $total_due_query = mysqli_query($connection, "
                    SELECT total_due, remaining_balance 
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
                
                // Update inpatient billing
                $update_balance = mysqli_query($connection, "
                    UPDATE tbl_billing_inpatient 
                    SET remaining_balance = $initial_remaining,
                        status = CASE 
                            WHEN $initial_remaining <= 0 THEN 'Paid'
                            ELSE status 
                        END
                    WHERE patient_name = '$patient_name' 
                    ORDER BY id DESC LIMIT 1
                ");
                
                if (!$update_balance) {
                    throw new Exception("Error updating balance: " . mysqli_error($connection));
                }
    
                // If payment is complete (remaining balance is 0), update billing_others
                if ($initial_remaining <= 0) {
                    // Get the billing_id from the latest inpatient billing
                    $billing_id_query = mysqli_query($connection, "
                        SELECT billing_id 
                        FROM tbl_billing_inpatient 
                        WHERE patient_name = '$patient_name' 
                        ORDER BY id DESC LIMIT 1
                    ");
                    
                    if (!$billing_id_query) {
                        throw new Exception("Error getting billing ID: " . mysqli_error($connection));
                    }
                    
                    $billing_row = mysqli_fetch_assoc($billing_id_query);
                    if ($billing_row) {
                        $billing_id = mysqli_real_escape_string($connection, $billing_row['billing_id']);
                        
                        // Update all related records in billing_others
                        $update_others = mysqli_query($connection, "
                            UPDATE tbl_billing_others 
                            SET is_billed = 1 
                            WHERE billing_id = '$billing_id'
                        ");
                        
                        if (!$update_others) {
                            throw new Exception("Error updating billing others: " . mysqli_error($connection));
                        }
    
                        // Update inpatient record
                        $update_inpatient = mysqli_query($connection, "
                            UPDATE tbl_inpatient_record 
                            SET is_billed = 1 
                            WHERE patient_name = '$patient_name'
                        ");
                        
                        if (!$update_inpatient) {
                            throw new Exception("Error updating inpatient record: " . mysqli_error($connection));
                        }
    
                        // Update operating room record
                        $update_or = mysqli_query($connection, "
                            UPDATE tbl_operating_room 
                            SET is_billed = 1 
                            WHERE patient_name = '$patient_name'
                        ");
                        
                        if (!$update_or) {
                            throw new Exception("Error updating operating room record: " . mysqli_error($connection));
                        }
    
                        // Update lab orders
                        $update_lab = mysqli_query($connection, "
                            UPDATE tbl_laborder 
                            SET is_billed = 1 
                            WHERE patient_name = '$patient_name'
                            AND deleted = 0
                        ");
                        
                        if (!$update_lab) {
                            throw new Exception("Error updating lab orders: " . mysqli_error($connection));
                        }
    
                        // Update radiology orders
                        $update_rad = mysqli_query($connection, "
                            UPDATE tbl_radiology 
                            SET is_billed = 1 
                            WHERE patient_name = '$patient_name'
                            AND deleted = 0
                        ");
                        
                        if (!$update_rad) {
                            throw new Exception("Error updating radiology orders: " . mysqli_error($connection));
                        }
                  
                        // Update treatment records
                        $update_treatment = mysqli_query($connection, "
                            UPDATE tbl_treatment 
                            SET is_billed = 1 
                            WHERE patient_name = '$patient_name'
                        ");
                      
                        if (!$update_treatment) {
                            throw new Exception("Error updating treatment records: " . mysqli_error($connection));
                        }
                    }
                }
            } else if ($patient_type == 'Outpatient') {
                // Start explicit transaction
                mysqli_begin_transaction($connection);
                
                try {
                    // Escape variables for security
                    $escaped_patient_name = mysqli_real_escape_string($connection, $patient_name);
                    $escaped_patient_id = mysqli_real_escape_string($connection, $patient_id);
                    $escaped_payment_id = mysqli_real_escape_string($connection, $payment_id);
                    $escaped_patient_type = mysqli_real_escape_string($connection, $patient_type);
                    
                    // Calculate totals
                    $lab_query = mysqli_query($connection, "SELECT COALESCE(SUM(price), 0) as total_lab FROM tbl_laborder 
                        WHERE patient_name = '$escaped_patient_name' AND is_billed = 0 AND deleted = 0");
                    $rad_query = mysqli_query($connection, "SELECT COALESCE(SUM(price), 0) as total_rad FROM tbl_radiology 
                        WHERE patient_name = '$escaped_patient_name' AND is_billed = 0 AND deleted = 0");
                    
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
                        '$escaped_payment_id', '$escaped_patient_id', '$escaped_patient_name', '$escaped_patient_type',
                        $total_due, $amount_to_pay, $amount_paid, $initial_remaining, NOW()
                    )");
                    
                    if (!$insert_result) {
                        throw new Exception("Payment insert failed: " . mysqli_error($connection));
                    }
                    
                    // Update lab orders
                    $update_lab = mysqli_query($connection, "UPDATE tbl_laborder SET is_billed = 1 
                        WHERE patient_name = '$escaped_patient_name' AND is_billed = 0 AND deleted = 0");
                        
                    if (!$update_lab) {
                        throw new Exception("Lab update failed: " . mysqli_error($connection));
                    }
                    
                    // Update radiology orders
                    $update_rad = mysqli_query($connection, "UPDATE tbl_radiology SET is_billed = 1 
                        WHERE patient_name = '$escaped_patient_name' AND is_billed = 0 AND deleted = 0");
                        
                    if (!$update_rad) {
                        throw new Exception("Radiology update failed: " . mysqli_error($connection));
                    }
                    
                    // Commit the transaction
                    if (!mysqli_commit($connection)) {
                        throw new Exception("Transaction commit failed: " . mysqli_error($connection));
                    }
                    
                } catch (Exception $e) {
                    // Roll back on any error
                    mysqli_rollback($connection);
                    throw $e;
                }
            } else if ($patient_type == 'Hemodialysis') {
                // Escape inputs to prevent SQL injection
                $patient_name = mysqli_real_escape_string($connection, $patient_name);
                $payment_id = mysqli_real_escape_string($connection, $payment_id);
                $patient_id = mysqli_real_escape_string($connection, $patient_id);
                $patient_type = mysqli_real_escape_string($connection, $patient_type);
            
                // Get hemodialysis billing details
                $total_due_query = mysqli_query($connection, "
                    SELECT total_due, remaining_balance 
                    FROM tbl_billing_hemodialysis 
                    WHERE patient_name = '$patient_name' 
                    ORDER BY id DESC LIMIT 1
                ");
                
                if (!$total_due_query) {
                    throw new Exception("Error fetching billing details: " . mysqli_error($connection));
                }
                
                $amount_to_pay = $lab_total + $rad_total;
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
                
                // Update hemodialysis billing
                $update_balance = mysqli_query($connection, "
                    UPDATE tbl_billing_hemodialysis 
                    SET remaining_balance = $initial_remaining,
                        status = CASE 
                            WHEN $initial_remaining <= 0 THEN 'Paid'
                            ELSE status 
                        END
                    WHERE patient_name = '$patient_name' 
                    ORDER BY id DESC LIMIT 1
                ");
                
                if (!$update_balance) {
                    throw new Exception("Error updating balance: " . mysqli_error($connection));
                }
            
                // If payment is complete (remaining balance is 0), update related records
                if ($initial_remaining <= 0) {
                    // Get the billing_id from the latest hemodialysis billing
                    $billing_id_query = mysqli_query($connection, "
                        SELECT billing_id 
                        FROM tbl_billing_hemodialysis 
                        WHERE patient_name = '$patient_name' 
                        ORDER BY id DESC LIMIT 1
                    ");
                    
                    if (!$billing_id_query) {
                        throw new Exception("Error getting billing ID: " . mysqli_error($connection));
                    }
                    
                    $billing_row = mysqli_fetch_assoc($billing_id_query);
                    if ($billing_row) {
                        $billing_id = mysqli_real_escape_string($connection, $billing_row['billing_id']);
                        
                        // Update all related records in billing_others
                        $update_others = mysqli_query($connection, "
                            UPDATE tbl_billing_others 
                            SET is_billed = 1 
                            WHERE billing_id = '$billing_id'
                        ");
                        
                        if (!$update_others) {
                            throw new Exception("Error updating billing others: " . mysqli_error($connection));
                        }
            
                        // Update hemodialysis record
                        $update_hemodialysis = mysqli_query($connection, "
                            UPDATE tbl_hemodialysis 
                            SET is_billed = 1 
                            WHERE patient_name = '$patient_name'
                        ");
                        
                        if (!$update_hemodialysis) {
                            throw new Exception("Error updating hemodialysis record: " . mysqli_error($connection));
                        }
            
                        // Update lab orders
                        $update_lab = mysqli_query($connection, "
                            UPDATE tbl_laborder 
                            SET is_billed = 1 
                            WHERE patient_name = '$patient_name'
                            AND deleted = 0
                        ");
                        
                        if (!$update_lab) {
                            throw new Exception("Error updating lab orders: " . mysqli_error($connection));
                        }
            
                        // Update radiology orders
                        $update_rad = mysqli_query($connection, "
                            UPDATE tbl_radiology 
                            SET is_billed = 1 
                            WHERE patient_name = '$patient_name'
                            AND deleted = 0
                        ");
                        
                        if (!$update_rad) {
                            throw new Exception("Error updating radiology orders: " . mysqli_error($connection));
                        }
            
                        // Update treatment records
                        $update_treatment = mysqli_query($connection, "
                            UPDATE tbl_treatment 
                            SET is_billed = 1 
                            WHERE patient_name = '$patient_name'
                        ");
                        
                        if (!$update_treatment) {
                            throw new Exception("Error updating treatment records: " . mysqli_error($connection));
                        }
                    }
                }            
            } else if ($patient_type == 'Newborn') {
                // Escape user inputs to prevent SQL injection
                $patient_name = mysqli_real_escape_string($connection, $patient_name);
                $patient_id = mysqli_real_escape_string($connection, $patient_id);
                $payment_id = mysqli_real_escape_string($connection, $payment_id);
                $patient_type = mysqli_real_escape_string($connection, $patient_type);
            
                // Get newborn billing details
                $total_due_query = mysqli_query($connection, "
                    SELECT total_due, remaining_balance 
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
                
                // Escape and sanitize the amount paid input
                $amount_paid = isset($_POST['amount_paid']) ? $_POST['amount_paid'] : '0';
                $amount_paid = str_replace(',', '', $amount_paid);
                $amount_paid = floatval($amount_paid);
                
                $initial_remaining = max(0, $amount_to_pay - $amount_paid);
            
                // Insert payment record using newborn_id as patient_id
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
            
                // Update newborn billing status and remaining balance
                $update_balance = mysqli_query($connection, "
                    UPDATE tbl_billing_newborn 
                    SET remaining_balance = $initial_remaining,
                        status = CASE 
                            WHEN $initial_remaining <= 0 THEN 'Paid'
                            ELSE status 
                        END
                    WHERE patient_name = '$patient_name' 
                    ORDER BY id DESC LIMIT 1
                ");
            
                if (!$update_balance) {
                    throw new Exception("Error updating balance: " . mysqli_error($connection));
                }
            
                // If payment is complete (remaining balance is 0), update related records
                if ($initial_remaining <= 0) {
                    // Get the billing_id from the latest newborn billing
                    $billing_id_query = mysqli_query($connection, "
                        SELECT billing_id 
                        FROM tbl_billing_newborn 
                        WHERE patient_name = '$patient_name' 
                        ORDER BY id DESC LIMIT 1
                    ");
            
                    if (!$billing_id_query) {
                        throw new Exception("Error getting billing ID: " . mysqli_error($connection));
                    }
            
                    $billing_row = mysqli_fetch_assoc($billing_id_query);
                    if ($billing_row) {
                        $billing_id = mysqli_real_escape_string($connection, $billing_row['billing_id']);
            
                        // Update all related records in billing_others
                        $update_others = mysqli_query($connection, "
                            UPDATE tbl_billing_others 
                            SET is_billed = 1 
                            WHERE billing_id = '$billing_id'
                        ");
            
                        if (!$update_others) {
                            throw new Exception("Error updating billing others: " . mysqli_error($connection));
                        }
            
                        // Update newborn record
                        $update_newborn = mysqli_query($connection, "
                            UPDATE tbl_newborn
                            SET is_billed = 1 
                            WHERE CONCAT(first_name, ' ', last_name) = '$patient_name'
                        ");
            
                        if (!$update_newborn) {
                            throw new Exception("Error updating newborn record: " . mysqli_error($connection));
                        }
            
                        // Update lab orders
                        $update_lab = mysqli_query($connection, "
                            UPDATE tbl_laborder 
                            SET is_billed = 1 
                            WHERE patient_name = '$patient_name'
                            AND deleted = 0
                        ");
            
                        if (!$update_lab) {
                            throw new Exception("Error updating lab orders: " . mysqli_error($connection));
                        }
            
                        // Update radiology orders  
                        $update_rad = mysqli_query($connection, "
                            UPDATE tbl_radiology 
                            SET is_billed = 1 
                            WHERE patient_name = '$patient_name'
                            AND deleted = 0
                        ");
            
                        if (!$update_rad) {
                            throw new Exception("Error updating radiology orders: " . mysqli_error($connection));
                        }
            
                        // Update treatment records
                        $update_treatment = mysqli_query($connection, "
                            UPDATE tbl_treatment 
                            SET is_billed = 1 
                            WHERE patient_name = '$patient_name'
                        ");
            
                        if (!$update_treatment) {
                            throw new Exception("Error updating treatment records: " . mysqli_error($connection));
                        }
                    }
                }
            
                // Commit transaction after all queries are successful
                if (!mysqli_commit($connection)) {
                    throw new Exception("Transaction commit failed: " . mysqli_error($connection));
                }
            }
            
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
                        text: 'Error details: " . addslashes($e->getMessage()) . "',
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

                        <div class="col-md-3">
                            <div class="form-group">
                                <label class="font-weight-bold">Amount to Pay</label>
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text bg-light">₱</span>
                                    </div>
                                    <input type="number" step="0.01" class="form-control" name="amount_to_pay" required readonly>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-3">
                            <div class="form-group">
                                <label class="font-weight-bold">Amount Paid</label>
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text bg-light">₱</span>
                                    </div>
                                    <input type="number" step="0.01" class="form-control" name="amount_paid" required onchange="calculateRemainingBalance()">
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="row mt-3">
                        <div class="col-md-12">
                            <!-- Laboratory Fees Card -->
                            <div class="card mb-4" id="outpatientLabTable" style="display: none;">
                                <div class="card-header">
                                    <h5 class="card-title mb-0"><i class="fas fa-flask mr-2"></i>Laboratory Fees</h5>
                                </div>
                                <div class="card-body p-0">
                                    <div class="table-responsive">
                                        <table class="table table-hover mb-0">
                                            <thead>
                                                <tr>
                                                    <th>Lab Test</th>
                                                    <th class="text-right">Price</th>
                                                    <th>Request Date</th>
                                                </tr>
                                            </thead>
                                            <tbody></tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>

                            <!-- Radiology Fees Card -->
                            <div class="card mb-4" id="outpatientRadTable" style="display: none;">
                                <div class="card-header">
                                    <h5 class="card-title mb-0"><i class="fas fa-x-ray mr-2"></i>Radiology Fees</h5>
                                </div>
                                <div class="card-body p-0">
                                    <div class="table-responsive">
                                        <table class="table table-hover mb-0">
                                            <thead>
                                                <tr>
                                                    <th>Radiology Test</th>
                                                    <th class="text-right">Price</th>
                                                    <th>Request Date</th>
                                                </tr>
                                            </thead>
                                            <tbody></tbody>
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
const outpatientLabTable = document.getElementById('outpatientLabTable');
const outpatientRadTable = document.getElementById('outpatientRadTable');
const inpatientBillingTable = document.getElementById('inpatientBillingTable');
const hemodialysisBillingTable = document.getElementById('hemodialysisBillingTable');
const newbornBillingTable = document.getElementById('newbornBillingTable'); // Added Newborn Billing Table
const patientTypeSelect = document.getElementById('patient_type');
let currentTotalDue = 0;

function togglePatientSearch() {
    const selectedType = patientTypeSelect.value;
    patientSearch.parentElement.parentElement.style.display = 'block';

    // Clear all tables and amounts
    outpatientLabTable.style.display = 'none';
    outpatientRadTable.style.display = 'none';
    inpatientBillingTable.style.display = 'none';
    hemodialysisBillingTable.style.display = 'none';
    newbornBillingTable.style.display = 'none'; // Hide Newborn Billing Table

    // Clear table contents
    outpatientLabTable.querySelector('tbody').innerHTML = '';
    outpatientRadTable.querySelector('tbody').innerHTML = '';
    inpatientBillingTable.querySelector('tbody').innerHTML = '';
    hemodialysisBillingTable.querySelector('tbody').innerHTML = '';
    newbornBillingTable.querySelector('tbody').innerHTML = ''; // Clear Newborn Billing Table

    // Clear all amounts and spans
    const fieldsToReset = [
        'inpatientGrossAmount', 'inpatientPhilhealthPF', 'inpatientPhilhealthHB', 'inpatientVatExempt', 
        'inpatientSeniorDiscount', 'inpatientPWDDiscount', 'inpatientAmountPaid', 
        'inpatientAmountDue', 'inpatientRemainingBalance',

        'hemodialysisGrossAmount', 'hemodialysisPhilhealthPF', 'hemodialysisPhilhealthHB', 
        'hemodialysisVatExempt', 'hemodialysisSeniorDiscount', 'hemodialysisPWDDiscount', 
        'hemodialysisAmountPaid', 'hemodialysisAmountDue', 'hemodialysisRemainingBalance',

        'newbornGrossAmount', 'newbornPhilhealthPF', 'newbornPhilhealthHB', 'newbornVatExempt', 
        'newbornSeniorDiscount', 'newbornPWDDiscount', 'newbornAmountPaid', 
        'newbornAmountDue', 'newbornRemainingBalance' // Reset Newborn Billing Amounts
    ];

    fieldsToReset.forEach(id => document.getElementById(id).textContent = '₱0.00');
    
    currentTotalDue = 0;

    // Show appropriate table based on type
    if (selectedType === 'Outpatient') {
        outpatientLabTable.style.display = 'table';
        outpatientRadTable.style.display = 'table';
    } else if (selectedType === 'Inpatient') {
        inpatientBillingTable.style.display = 'table';
    } else if (selectedType === 'Hemodialysis') {
        hemodialysisBillingTable.style.display = 'table';
    } else if (selectedType === 'Newborn') { // Show Newborn Billing Table
        newbornBillingTable.style.display = 'table';
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
        const searchEndpoint = selectedType === 'Outpatient' ? 'search-opt.php' : 
                             selectedType === 'Inpatient' ? 'search-ipt-payment.php' : 
                             selectedType === 'Hemodialysis' ? 'search-hemodialysis-payment.php' : 
                             selectedType === 'Newborn' ? 'search-nb-payment.php' : ''; // Newborn case

        if (searchEndpoint) {
            fetch(searchEndpoint + '?search=' + encodeURIComponent(this.value))
                .then(response => response.json())
                .then(data => {
                    searchResults.style.display = 'block';
                    searchResults.innerHTML = data.map(patient => {
                        const id = selectedType === 'Newborn' ? patient.newborn_id : patient.patient_id; // Ensure newborn_id is used
                        return `<li class="search-result" data-id="${id}">${patient.patient_name}</li>`;
                    }).join('');
                })
                .catch(error => console.error('Error fetching data:', error)); // Debugging
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
        } else if (selectedType === 'Inpatient') {
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
                    
                    // Get amount paid from tbl_payment
                    fetch('get-total-payments.php?patient_name=' + patientName)
                        .then(response => response.json())
                        .then(paymentData => {
                            const amountPaid = parseFloat(paymentData.total_paid || 0);
                            document.getElementById('inpatientAmountPaid').textContent = `₱${amountPaid.toFixed(2)}`;
                        });
                    
                    // Calculate final amount due
                    const totalDue = subTotal - vatExempt - seniorDiscount - pwdDiscount - philHealthPF - philHealthHB;
                    document.getElementById('inpatientAmountDue').textContent = `₱${totalDue.toFixed(2)}`;
                    currentTotalDue = totalDue;
                    
                    // Show remaining balance and set as amount_to_pay
                    const remainingBalance = parseFloat(data.remaining_balance || 0);
                    document.getElementById('inpatientRemainingBalance').textContent = `₱${remainingBalance.toFixed(2)}`;
                    updateAmountToPay(remainingBalance);
                });
        } else if (selectedType === 'Hemodialysis') {
            // For hemodialysis patients, fetch their billing data
            fetch('hemodialysis-billing-fee.php?patient_name=' + patientName)
                .then(response => response.json())
                .then(data => {
                    const tbody = hemodialysisBillingTable.querySelector('tbody');
                    tbody.innerHTML = '';
                    hemodialysisBillingTable.style.display = 'table';

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
                    fetch('get-total-payments.php?patient_name=' + patientName)
                        .then(response => response.json())
                        .then(paymentData => {
                            const amountPaid = parseFloat(paymentData.total_paid || 0);
                            document.getElementById('hemodialysisAmountPaid').textContent = `₱${amountPaid.toFixed(2)}`;
                        });
                    
                    // Calculate final amount due
                    const totalDue = subTotal - vatExempt - seniorDiscount - pwdDiscount - philHealthPF - philHealthHB;
                    document.getElementById('hemodialysisAmountDue').textContent = `₱${totalDue.toFixed(2)}`;
                    currentTotalDue = totalDue;
                    
                    // Show remaining balance and set as amount_to_pay
                    const remainingBalance = parseFloat(data.remaining_balance || 0);
                    document.getElementById('hemodialysisRemainingBalance').textContent = `₱${remainingBalance.toFixed(2)}`;
                    updateAmountToPay(remainingBalance);
                });
        } else if (selectedType === 'Newborn') {
            fetch('newborn-billing-fee.php?patient_name=' + patientName)
                .then(response => response.json())
                .then(data => {
                    const tbody = newbornBillingTable.querySelector('tbody');
                    tbody.innerHTML = '';
                    newbornBillingTable.style.display = 'table';

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
                    fetch('get-total-payments.php?patient_name=' + patientName)
                        .then(response => response.json())
                        .then(paymentData => {
                            const amountPaid = parseFloat(paymentData.total_paid || 0);
                            document.getElementById('newbornAmountPaid').textContent = `₱${amountPaid.toFixed(2)}`;
                        });

                    // Calculate final amount due
                    const totalDue = subTotal - vatExempt - seniorDiscount - pwdDiscount - philHealthPF - philHealthHB;
                    document.getElementById('newbornAmountDue').textContent = `₱${totalDue.toFixed(2)}`;
                    currentTotalDue = totalDue;

                    // Show remaining balance and set as amount_to_pay
                    const remainingBalance = parseFloat(data.remaining_balance || 0);
                    document.getElementById('newbornRemainingBalance').textContent = `₱${remainingBalance.toFixed(2)}`;
                    updateAmountToPay(remainingBalance);
                });
        }
    }
});

function updateAmountToPay(total) {
    document.querySelector('input[name="amount_to_pay"]').value = total.toFixed(2);
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
</style>
