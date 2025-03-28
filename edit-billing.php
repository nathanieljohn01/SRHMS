<?php
session_start();
if (empty($_SESSION['name'])) {
    header('location:index.php');
    exit();
}

include('header.php');
include('includes/connection.php');

// Get billing ID from URL
$billing_id = isset($_GET['id']) ? mysqli_real_escape_string($connection, $_GET['id']) : null;

if (!$billing_id) {
    header('location:billing.php');
    exit();
}

// Determine which billing table this record belongs to
$billing_type = null;
$billing_data = null;

// Check each billing table
$tables = ['tbl_billing_inpatient', 'tbl_billing_hemodialysis', 'tbl_billing_newborn'];
foreach ($tables as $table) {
    $safe_table = mysqli_real_escape_string($connection, $table);
    $query = "SELECT * FROM $safe_table WHERE billing_id = '".mysqli_real_escape_string($connection, $billing_id)."'";
    $result = mysqli_query($connection, $query);
    if (mysqli_num_rows($result)) {
        $billing_type = $table;
        $billing_data = mysqli_fetch_assoc($result);
        break;
    }
}

if (!$billing_type || !$billing_data) {
    echo "<script>
        Swal.fire({
            icon: 'error',
            title: 'Error',
            text: 'Billing record not found',
            confirmButtonColor: '#12369e'
        }).then(() => {
            window.location.href = 'billing.php';
        });
    </script>";
    exit();
}

// Determine patient type based on table
$patient_type = '';
if ($billing_type === 'tbl_billing_inpatient') {
    $patient_type = 'inpatient';
} elseif ($billing_type === 'tbl_billing_hemodialysis') {
    $patient_type = 'hemodialysis';
} elseif ($billing_type === 'tbl_billing_newborn') {
    $patient_type = 'newborn';
}

// Get patient name from billing data
$patient_name = $billing_data['patient_name'];

// Get other fees from the database
$other_fees_query = "SELECT * FROM tbl_billing_others WHERE billing_id = '".mysqli_real_escape_string($connection, $billing_id)."' AND deleted = 0";
$other_fees_result = mysqli_query($connection, $other_fees_query);
$other_fees = [];
while ($row = mysqli_fetch_assoc($other_fees_result)) {
    $other_fees[] = $row;
}

// Input validation function
function validateInput($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

if (isset($_POST['update-billing'])) {
    // Get form data
    $patient_name = validateInput(mysqli_real_escape_string($connection, $_POST['patient_name']));
    
    // PhilHealth fields
    $first_case = isset($_POST['first_case']) ? mysqli_real_escape_string($connection, $_POST['first_case']) : '';
    $second_case = isset($_POST['second_case']) ? mysqli_real_escape_string($connection, $_POST['second_case']) : '';
    $philhealth_pf = isset($_POST['philhealth_pf']) ? mysqli_real_escape_string($connection, $_POST['philhealth_pf']) : '';
    $philhealth_hb = isset($_POST['philhealth_hb']) ? mysqli_real_escape_string($connection, $_POST['philhealth_hb']) : '';

    // Fees
    $room_fee = isset($_POST['room_fee']) && $_POST['room_fee'] !== '' ? mysqli_real_escape_string($connection, $_POST['room_fee']) : 0;
    $lab_fee = isset($_POST['lab_fee']) && $_POST['lab_fee'] !== '' ? mysqli_real_escape_string($connection, $_POST['lab_fee']) : 0;
    $rad_fee = isset($_POST['rad_fee']) && $_POST['rad_fee'] !== '' ? mysqli_real_escape_string($connection, $_POST['rad_fee']) : 0;
    $medication_fee = isset($_POST['medication_fee']) && $_POST['medication_fee'] !== '' ? mysqli_real_escape_string($connection, $_POST['medication_fee']) : 0;
    $operating_room_fee = isset($_POST['operating_room_fee']) && $_POST['operating_room_fee'] !== '' ? mysqli_real_escape_string($connection, $_POST['operating_room_fee']) : 0;
    $supplies_fee = isset($_POST['supplies_fee']) && $_POST['supplies_fee'] !== '' ? mysqli_real_escape_string($connection, $_POST['supplies_fee']) : 0;
    $professional_fee = isset($_POST['professional_fee']) && $_POST['professional_fee'] !== '' ? mysqli_real_escape_string($connection, $_POST['professional_fee']) : 0;
    $readers_fee = isset($_POST['readers_fee']) && $_POST['readers_fee'] !== '' ? mysqli_real_escape_string($connection, $_POST['readers_fee']) : 0;
    $others_fee = isset($_POST['others_fee']) && $_POST['others_fee'] !== '' ? mysqli_real_escape_string($connection, $_POST['others_fee']) : 0;

    // Get checkbox states
    $vat_exempt_checkbox = isset($_POST['vat_exempt_checkbox']) ? mysqli_real_escape_string($connection, $_POST['vat_exempt_checkbox']) : 'off';
    $discount_checkbox = isset($_POST['discount_checkbox']) ? mysqli_real_escape_string($connection, $_POST['discount_checkbox']) : 'off';
    $pwd_discount_checkbox = isset($_POST['pwd_discount_checkbox']) ? mysqli_real_escape_string($connection, $_POST['pwd_discount_checkbox']) : 'off';

    // Calculate non-discounted total first
    $non_discounted_total = floatval($room_fee) + floatval($lab_fee) + floatval($rad_fee) + 
                            floatval($medication_fee) + floatval($operating_room_fee) + 
                            floatval($supplies_fee) + floatval($professional_fee) + 
                            floatval($readers_fee) + floatval($others_fee);

    // First apply PWD discount (20%)
    if ($pwd_discount_checkbox == 'on') {
        $pwd_discount_amount = $non_discounted_total * 0.20;
        $after_pwd = $non_discounted_total - $pwd_discount_amount;
    } else {
        $pwd_discount_amount = 0;
        $after_pwd = $non_discounted_total;
    }

    // Then apply VAT Exempt (12%)
    if ($vat_exempt_checkbox == 'on') {
        $vat_exempt_discount_amount = $after_pwd * 0.12;
        $after_vat = $after_pwd - $vat_exempt_discount_amount;
    } else {
        $vat_exempt_discount_amount = 0;
        $after_vat = $after_pwd;
    }

    // Finally apply Senior discount (20%) on the total amount
    if ($discount_checkbox == 'on') {
        $total_discount = $after_vat * 0.20;
    } else {
        $total_discount = 0;
    }

    // Calculate final total due
    $total_due = $non_discounted_total - $pwd_discount_amount - $vat_exempt_discount_amount - $total_discount - floatval($philhealth_pf) - floatval($philhealth_hb);
    
    // Set remaining balance equal to total due for updated billing
    $remaining_balance = $total_due;

    // Ensure values are not negative
    $total_due = max(0, $total_due);
    $remaining_balance = max(0, $remaining_balance);

    // Calculate discount amounts for individual items
    if ($vat_exempt_checkbox == 'on') {
        $room_after_vat = $room_fee - ($room_fee * 0.12);
        $lab_after_vat = $lab_fee - ($lab_fee * 0.12);
        $rad_after_vat = $rad_fee - ($rad_fee * 0.12);
        $med_after_vat = $medication_fee - ($medication_fee * 0.12);
        $or_after_vat = $operating_room_fee - ($operating_room_fee * 0.12);
        $supplies_after_vat = $supplies_fee - ($supplies_fee * 0.12);
        $other_after_vat = $others_fee - ($others_fee * 0.12);
        $pf_after_vat = $professional_fee - ($professional_fee * 0.12);
        $readers_after_vat = $readers_fee - ($readers_fee * 0.12);
    } else {
        $room_after_vat = $room_fee;
        $lab_after_vat = $lab_fee;
        $rad_after_vat = $rad_fee;
        $med_after_vat = $medication_fee;
        $or_after_vat = $operating_room_fee;
        $supplies_after_vat = $supplies_fee;
        $other_after_vat = $others_fee;
        $pf_after_vat = $professional_fee;
        $readers_after_vat = $readers_fee;
    }

    if ($discount_checkbox == 'on' || $pwd_discount_checkbox == 'on') {
        $room_discount = ($room_fee - $room_after_vat) + ($room_after_vat * 0.20);
        $lab_discount = ($lab_fee - $lab_after_vat) + ($lab_after_vat * 0.20);
        $rad_discount = ($rad_fee - $rad_after_vat) + ($rad_after_vat * 0.20);
        $med_discount = ($medication_fee - $med_after_vat) + ($med_after_vat * 0.20);
        $or_discount = ($operating_room_fee - $or_after_vat) + ($or_after_vat * 0.20);
        $supplies_discount = ($supplies_fee - $supplies_after_vat) + ($supplies_after_vat * 0.20);
        $other_discount = ($others_fee - $other_after_vat) + ($other_after_vat * 0.20);
        $pf_discount = ($professional_fee - $pf_after_vat) + ($pf_after_vat * 0.20);
        $readers_discount = ($readers_fee - $readers_after_vat) + ($readers_after_vat * 0.20);
    } else {
        $room_discount = $room_fee - $room_after_vat;
        $lab_discount = $lab_fee - $lab_after_vat;
        $rad_discount = $rad_fee - $rad_after_vat;
        $med_discount = $medication_fee - $med_after_vat;
        $or_discount = $operating_room_fee - $or_after_vat;
        $supplies_discount = $supplies_fee - $supplies_after_vat;
        $other_discount = $others_fee - $other_after_vat;
        $pf_discount = $professional_fee - $pf_after_vat;
        $readers_discount = $readers_fee - $readers_after_vat;
    }

    // Update query based on patient type
    $safe_billing_id = mysqli_real_escape_string($connection, $billing_id);
    
    if ($patient_type == 'inpatient') {
        $query = "UPDATE tbl_billing_inpatient SET
            patient_name = '".mysqli_real_escape_string($connection, $patient_name)."',
            lab_fee = '".mysqli_real_escape_string($connection, $lab_fee)."',
            room_fee = '".mysqli_real_escape_string($connection, $room_fee)."',
            medication_fee = '".mysqli_real_escape_string($connection, $medication_fee)."',
            operating_room_fee = '".mysqli_real_escape_string($connection, $operating_room_fee)."',
            supplies_fee = '".mysqli_real_escape_string($connection, $supplies_fee)."',
            professional_fee = '".mysqli_real_escape_string($connection, $professional_fee)."',
            readers_fee = '".mysqli_real_escape_string($connection, $readers_fee)."',
            others_fee = '".mysqli_real_escape_string($connection, $others_fee)."',
            rad_fee = '".mysqli_real_escape_string($connection, $rad_fee)."',
            total_due = '".mysqli_real_escape_string($connection, $total_due)."',
            non_discounted_total = '".mysqli_real_escape_string($connection, $non_discounted_total)."',
            discount_amount = '".mysqli_real_escape_string($connection, $total_discount)."',
            pwd_discount_amount = '".mysqli_real_escape_string($connection, $pwd_discount_amount)."',
            vat_exempt_discount_amount = '".mysqli_real_escape_string($connection, $vat_exempt_discount_amount)."',
            first_case = '".mysqli_real_escape_string($connection, $first_case)."',
            second_case = '".mysqli_real_escape_string($connection, $second_case)."',
            philhealth_pf = '".mysqli_real_escape_string($connection, $philhealth_pf)."',
            philhealth_hb = '".mysqli_real_escape_string($connection, $philhealth_hb)."',
            room_discount = '".mysqli_real_escape_string($connection, $room_discount)."',
            lab_discount = '".mysqli_real_escape_string($connection, $lab_discount)."',
            rad_discount = '".mysqli_real_escape_string($connection, $rad_discount)."',
            med_discount = '".mysqli_real_escape_string($connection, $med_discount)."',
            or_discount = '".mysqli_real_escape_string($connection, $or_discount)."',
            supplies_discount = '".mysqli_real_escape_string($connection, $supplies_discount)."',
            other_discount = '".mysqli_real_escape_string($connection, $other_discount)."',
            pf_discount = '".mysqli_real_escape_string($connection, $pf_discount)."',
            readers_discount = '".mysqli_real_escape_string($connection, $readers_discount)."',
            remaining_balance = '".mysqli_real_escape_string($connection, $remaining_balance)."'
            WHERE billing_id = '".$safe_billing_id."'";
    } elseif ($patient_type == 'hemodialysis') {
        $query = "UPDATE tbl_billing_hemodialysis SET
            patient_name = '".mysqli_real_escape_string($connection, $patient_name)."',
            lab_fee = '".mysqli_real_escape_string($connection, $lab_fee)."',
            room_fee = '".mysqli_real_escape_string($connection, $room_fee)."',
            medication_fee = '".mysqli_real_escape_string($connection, $medication_fee)."',
            operating_room_fee = '".mysqli_real_escape_string($connection, $operating_room_fee)."',
            supplies_fee = '".mysqli_real_escape_string($connection, $supplies_fee)."',
            professional_fee = '".mysqli_real_escape_string($connection, $professional_fee)."',
            readers_fee = '".mysqli_real_escape_string($connection, $readers_fee)."',
            others_fee = '".mysqli_real_escape_string($connection, $others_fee)."',
            rad_fee = '".mysqli_real_escape_string($connection, $rad_fee)."',
            total_due = '".mysqli_real_escape_string($connection, $total_due)."',
            non_discounted_total = '".mysqli_real_escape_string($connection, $non_discounted_total)."',
            discount_amount = '".mysqli_real_escape_string($connection, $total_discount)."',
            pwd_discount_amount = '".mysqli_real_escape_string($connection, $pwd_discount_amount)."',
            vat_exempt_discount_amount = '".mysqli_real_escape_string($connection, $vat_exempt_discount_amount)."',
            first_case = '".mysqli_real_escape_string($connection, $first_case)."',
            second_case = '".mysqli_real_escape_string($connection, $second_case)."',
            philhealth_pf = '".mysqli_real_escape_string($connection, $philhealth_pf)."',
            philhealth_hb = '".mysqli_real_escape_string($connection, $philhealth_hb)."',
            room_discount = '".mysqli_real_escape_string($connection, $room_discount)."',
            lab_discount = '".mysqli_real_escape_string($connection, $lab_discount)."',
            rad_discount = '".mysqli_real_escape_string($connection, $rad_discount)."',
            med_discount = '".mysqli_real_escape_string($connection, $med_discount)."',
            or_discount = '".mysqli_real_escape_string($connection, $or_discount)."',
            supplies_discount = '".mysqli_real_escape_string($connection, $supplies_discount)."',
            other_discount = '".mysqli_real_escape_string($connection, $other_discount)."',
            pf_discount = '".mysqli_real_escape_string($connection, $pf_discount)."',
            readers_discount = '".mysqli_real_escape_string($connection, $readers_discount)."',
            remaining_balance = '".mysqli_real_escape_string($connection, $remaining_balance)."'
            WHERE billing_id = '".$safe_billing_id."'";
    } elseif ($patient_type == 'newborn') {
        $query = "UPDATE tbl_billing_newborn SET
            patient_name = '".mysqli_real_escape_string($connection, $patient_name)."',
            lab_fee = '".mysqli_real_escape_string($connection, $lab_fee)."',
            room_fee = '".mysqli_real_escape_string($connection, $room_fee)."',
            medication_fee = '".mysqli_real_escape_string($connection, $medication_fee)."',
            operating_room_fee = '".mysqli_real_escape_string($connection, $operating_room_fee)."',
            supplies_fee = '".mysqli_real_escape_string($connection, $supplies_fee)."',
            professional_fee = '".mysqli_real_escape_string($connection, $professional_fee)."',
            readers_fee = '".mysqli_real_escape_string($connection, $readers_fee)."',
            others_fee = '".mysqli_real_escape_string($connection, $others_fee)."',
            rad_fee = '".mysqli_real_escape_string($connection, $rad_fee)."',
            total_due = '".mysqli_real_escape_string($connection, $total_due)."',
            non_discounted_total = '".mysqli_real_escape_string($connection, $non_discounted_total)."',
            discount_amount = '".mysqli_real_escape_string($connection, $total_discount)."',
            pwd_discount_amount = '".mysqli_real_escape_string($connection, $pwd_discount_amount)."',
            vat_exempt_discount_amount = '".mysqli_real_escape_string($connection, $vat_exempt_discount_amount)."',
            first_case = '".mysqli_real_escape_string($connection, $first_case)."',
            second_case = '".mysqli_real_escape_string($connection, $second_case)."',
            philhealth_pf = '".mysqli_real_escape_string($connection, $philhealth_pf)."',
            philhealth_hb = '".mysqli_real_escape_string($connection, $philhealth_hb)."',
            room_discount = '".mysqli_real_escape_string($connection, $room_discount)."',
            lab_discount = '".mysqli_real_escape_string($connection, $lab_discount)."',
            rad_discount = '".mysqli_real_escape_string($connection, $rad_discount)."',
            med_discount = '".mysqli_real_escape_string($connection, $med_discount)."',
            or_discount = '".mysqli_real_escape_string($connection, $or_discount)."',
            supplies_discount = '".mysqli_real_escape_string($connection, $supplies_discount)."',
            other_discount = '".mysqli_real_escape_string($connection, $other_discount)."',
            pf_discount = '".mysqli_real_escape_string($connection, $pf_discount)."',
            readers_discount = '".mysqli_real_escape_string($connection, $readers_discount)."',
            remaining_balance = '".mysqli_real_escape_string($connection, $remaining_balance)."'
            WHERE billing_id = '".$safe_billing_id."'";
    }

    // Handle other items
    if (isset($_POST['others'])) {
        // First mark all existing items as deleted
        $delete_query = "UPDATE tbl_billing_others SET deleted = 1 WHERE billing_id = '".$safe_billing_id."'";
        mysqli_query($connection, $delete_query);
        
        // Then insert the new/updated items
        foreach ($_POST['others'] as $item) {
            $item_name = mysqli_real_escape_string($connection, $item['name']);
            $item_cost = mysqli_real_escape_string($connection, $item['cost']);

            $insert_query = "
                INSERT INTO tbl_billing_others (billing_id, item_name, item_cost, date_time)
                VALUES ('".$safe_billing_id."', '".$item_name."', '".$item_cost."', NOW())
                ON DUPLICATE KEY UPDATE 
                item_name = VALUES(item_name),
                item_cost = VALUES(item_cost),
                deleted = 0
            ";
            mysqli_query($connection, $insert_query);
        }
    }

    if (mysqli_query($connection, $query)) {
        echo "
        <script src='https://cdn.jsdelivr.net/npm/sweetalert2@10'></script>
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                Swal.fire({
                    icon: 'success',
                    title: 'Success!',
                    text: 'Billing account updated successfully!',
                    confirmButtonColor: '#12369e'
                }).then(() => {
                    window.location.href = 'billing.php';
                });
            });
        </script>";
    } else {
        echo "
        <script src='https://cdn.jsdelivr.net/npm/sweetalert2@10'></script>
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                Swal.fire({
                    icon: 'error',
                    title: 'Database Error',
                    text: 'Failed to update billing account. Please try again.',
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
                            <h4 class="page-title">Edit Statement of Account</h4>
                        </div>
                        <div class="col-md-6 text-right">
                            <a href="billing.php" class="btn btn-primary">
                                <i class="fas fa-arrow-left mr-2"></i> Back
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card billing-card">
            <div class="card-header">
                <h5 class="card-title">Account Information</h5>
            </div>
            <div class="card-body">
                <form method="post" action="">
                    <!-- Patient Information Section -->
                    <div class="form-section">
                        <h6 class="section-title">Patient Details</h6>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Patient Type</label>
                                    <input class="form-control" type="text" value="<?php echo ucfirst($patient_type); ?>" disabled>
                                    <input type="hidden" name="patient_type" value="<?php echo $patient_type; ?>">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Billing ID</label>
                                    <input class="form-control" type="text" value="<?php echo $billing_id; ?>" disabled>
                                    <input type="hidden" name="billing_id" value="<?php echo $billing_id; ?>">
                                </div>
                            </div>
                        </div>
                        <div class="row">
                        <div class="col-md-12">
                            <div class="form-group">
                                <label>Patient Name</label>
                                <input type="text" class="form-control" value="<?php echo $billing_data['patient_name']; ?>" disabled>
                                <input type="hidden" name="patient_name" value="<?php echo $billing_data['patient_name']; ?>">
                            </div>
                        </div>
                    </div>

                    <!-- Charges Section -->
                    <div class="form-section charges-section">
                        <h6 class="section-title">Service Charges</h6>
                        <div id="patient-section">
                            <!-- Room Charges -->
                            <div class="charge-group">
                                <h5>Room Charges</h5>
                                <div class="table-responsive">
                                    <table class="table">
                                        <thead>
                                            <tr>
                                                <th>Description</th>
                                                <th>Amount</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr>
                                                <td>Room Fee</td>
                                                <td>
                                                    <input type="number" step="0.01" class="form-control" value="<?php echo $billing_data['room_fee']; ?>" disabled>
                                                    <input type="hidden" name="room_fee" value="<?php echo $billing_data['room_fee']; ?>">
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>

                            <!-- Medicine Charges -->
                            <div class="charge-group">
                                <h5>Medicine Charges</h5>
                                <div class="table-responsive">
                                    <table class="table">
                                        <thead>
                                            <tr>
                                                <th>Description</th>
                                                <th>Amount</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr>
                                                <td>Medication Fee</td>
                                                <td>
                                                    <input type="number" step="0.01" class="form-control" value="<?php echo $billing_data['medication_fee']; ?>" disabled>
                                                    <input type="hidden" name="medication_fee" value="<?php echo $billing_data['medication_fee']; ?>">
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>

                            <!-- Lab Charges -->
                            <div class="charge-group">
                                <h5>Lab Tests</h5>
                                <div class="table-responsive">
                                    <table class="table">
                                        <thead>
                                            <tr>
                                                <th>Description</th>
                                                <th>Amount</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr>
                                                <td>Lab Fee</td>
                                                <td>
                                                    <input type="number" step="0.01" class="form-control" value="<?php echo $billing_data['lab_fee']; ?>" disabled>
                                                    <input type="hidden" name="lab_fee" value="<?php echo $billing_data['lab_fee']; ?>">
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>

                            <!-- Radiology Charges -->
                            <div class="charge-group">
                                <h5>Radiology Tests</h5>
                                <div class="table-responsive">
                                    <table class="table">
                                        <thead>
                                            <tr>
                                                <th>Description</th>
                                                <th>Amount</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr>
                                                <td>Radiology Fee</td>
                                                <td>
                                                    <input type="number" step="0.01" class="form-control" value="<?php echo $billing_data['rad_fee']; ?>" disabled>
                                                    <input type="hidden" name="rad_fee" value="<?php echo $billing_data['rad_fee']; ?>">
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>

                            <!-- Operation Room -->
                            <div class="charge-group">
                                <h5>Operation Room</h5>
                                <div class="table-responsive">
                                    <table class="table">
                                        <thead>
                                            <tr>
                                                <th>Description</th>
                                                <th>Amount</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr>
                                                <td>Operating Room Fee</td>
                                                <td>
                                                    <input type="number" step="0.01" class="form-control" value="<?php echo $billing_data['operating_room_fee']; ?>" disabled>
                                                    <input type="hidden" name="operating_room_fee" value="<?php echo $billing_data['operating_room_fee']; ?>">
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>

                        <!-- Additional Fees -->
                        <div class="additional-fees">
                            <h5>Additional Fees</h5>
                            <div class="row">
                                <div class="col-md-12">
                                    <div class="form-group">
                                        <label for="supplies-fee">Supplies</label>
                                        <input type="number" step="0.01" min="0" class="form-control" id="supplies-fee" name="supplies_fee" value="<?php echo $billing_data['supplies_fee']; ?>">
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="professional-fee">Professional Fee</label>
                                        <input type="number" step="0.01" min="0" class="form-control" id="professional-fee" name="professional_fee" value="<?php echo $billing_data['professional_fee']; ?>">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="readers-fee">Reader's Fee</label>
                                        <input type="number" step="0.01" min="0" class="form-control" id="readers-fee" name="readers_fee" value="<?php echo $billing_data['readers_fee']; ?>">
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Other Items -->
                        <div class="col-md-12">
                            <div class="other-items">
                                <label>Other Charges</label>
                                <div id="other-items-container">
                                    <?php 
                                    $other_index = 0;
                                    foreach ($other_fees as $item): 
                                    ?>
                                    <div class="row align-items-center other-item mb-2" id="item-row-<?php echo $other_index; ?>">
                                        <div class="col-md-6">
                                            <input type="text" class="form-control" name="others[<?php echo $other_index; ?>][name]" value="<?php echo htmlspecialchars($item['item_name']); ?>" placeholder="Item description" required>
                                        </div>
                                        <div class="col-md-5">
                                            <input type="number" step="0.01" class="form-control" name="others[<?php echo $other_index; ?>][cost]" value="<?php echo $item['item_cost']; ?>" placeholder="Amount" required>
                                        </div>
                                        <div class="col-md-1">
                                            <button type="button" class="btn btn-sm btn-danger remove-item" data-id="item-row-<?php echo $other_index; ?>">
                                                <i class="fas fa-times"></i>
                                            </button>
                                        </div>
                                    </div>
                                    <?php 
                                    $other_index++;
                                    endforeach; 
                                    ?>
                                    
                                    <?php if (empty($other_fees)): ?>
                                    <div class="row align-items-center other-item mb-2" id="item-row-0">
                                        <div class="col-md-6">
                                            <input type="text" class="form-control" name="others[0][name]" placeholder="Item description" required>
                                        </div>
                                        <div class="col-md-5">
                                            <input type="number" step="0.01" class="form-control" name="others[0][cost]" placeholder="Amount" required>
                                        </div>
                                        <div class="col-md-1">
                                            <button type="button" class="btn btn-sm btn-danger remove-item" disabled>
                                                <i class="fas fa-times"></i>
                                            </button>
                                        </div>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>    
                            <button type="button" id="add-other-item" class="btn btn-sm btn-primary mt-2">
                                <i class="fas fa-plus mr-1"></i> Add Item
                            </button>
                        </div>
                    </div>

                    <!-- Discounts Section -->
                    <div class="form-section discounts-section">
                        <h6 class="section-title">Discounts & Deductions</h6>
                        <div class="row">
                            <div class="col-md-4">
                                <div class="discount-option">
                                    <div class="custom-control custom-checkbox">
                                        <input type="checkbox" class="custom-control-input" id="vat-exempt-checkbox" name="vat_exempt_checkbox" value="on" <?php echo ($billing_data['vat_exempt_discount_amount'] > 0) ? 'checked' : ''; ?>>
                                        <label class="custom-control-label" for="vat-exempt-checkbox">VAT Exempt (12%)</label>
                                    </div>
                                    <div class="discount-value" id="vat-exempt-discount" style="
                                            font-size: 1.3rem;
                                            font-weight: 600;
                                            color: rgb(73, 73, 73);
                                            background: #ffffff;
                                            padding: 6px 12px;
                                            border-radius: 6px;
                                            display: inline-block;
                                            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
                                        ">₱<?php echo number_format($billing_data['vat_exempt_discount_amount'], 2); ?>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="discount-option">
                                    <div class="custom-control custom-checkbox">
                                        <input type="checkbox" class="custom-control-input" id="discount-checkbox" name="discount_checkbox" value="on" <?php echo ($billing_data['discount_amount'] > 0) ? 'checked' : ''; ?>>
                                        <label class="custom-control-label" for="discount-checkbox">Senior Discount (20%)</label>
                                    </div>
                                    <div class="discount-value" id="discount-amount" style="
                                            font-size: 1.3rem;
                                            font-weight: 600;
                                            color: rgb(60, 60, 60);
                                            background: #ffffff;
                                            padding: 6px 12px;
                                            border-radius: 6px;
                                            display: inline-block;
                                            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
                                        ">₱<?php echo number_format($billing_data['discount_amount'], 2); ?>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="discount-option">
                                    <div class="custom-control custom-checkbox">
                                        <input type="checkbox" class="custom-control-input" id="pwd-discount-checkbox" name="pwd_discount_checkbox" value="on" <?php echo ($billing_data['pwd_discount_amount'] > 0) ? 'checked' : ''; ?>>
                                        <label class="custom-control-label" for="pwd-discount-checkbox">PWD Discount (20%)</label>
                                    </div>
                                    <div class="discount-value" id="pwd-discount" style="
                                            font-size: 1.3rem;
                                            font-weight: 600;
                                            color: rgb(55, 55, 55);
                                            background: #ffffff;
                                            padding: 6px 12px;
                                            border-radius: 6px;
                                            display: inline-block;
                                            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
                                        ">₱<?php echo number_format($billing_data['pwd_discount_amount'], 2); ?>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- PhilHealth Section -->
                        <div class="philhealth-section">
                            <h5>PhilHealth Discounts</h5>
                            <div class="form-group">
                                <select class="form-control" id="case-rate-select" name="case_rate">
                                    <option value="">Select Case Rate</option>
                                    <option value="first" <?php echo (!empty($billing_data['first_case'])) ? 'selected' : ''; ?>>First Case Rate</option>
                                    <option value="second" <?php echo (!empty($billing_data['second_case'])) ? 'selected' : ''; ?>>Second Case Rate</option>
                                </select>
                            </div>
                            
                            <div id="first-case-rate-container" style="display: <?php echo (!empty($billing_data['first_case'])) ? 'block' : 'none'; ?>;">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label>Professional Fee Discount</label>
                                        <input type="number" step="0.01" min="0" class="form-control" id="professional-fee-discount" name="philhealth_pf" value="<?php echo $billing_data['philhealth_pf']; ?>" <?php echo (empty($billing_data['first_case'])) ? 'disabled' : ''; ?>>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label>Hospital Bill Discount</label>
                                        <input type="number" step="0.01" min="0" class="form-control" id="hospital-bill-discount" name="philhealth_hb" value="<?php echo $billing_data['philhealth_hb']; ?>" <?php echo (empty($billing_data['first_case'])) ? 'disabled' : ''; ?>>
                                    </div>
                                </div>
                                <?php if (!empty($billing_data['first_case'])): ?>
                                <div class="col-sm-12 rvs-icd-input">
                                    <label>RVS or ICD Code</label>
                                    <input type="text" class="form-control" name="first_case" value="<?php echo $billing_data['first_case']; ?>">
                                </div>
                                <?php endif; ?>
                            </div>
                            
                            <div id="second-case-rate-container" style="display: <?php echo (!empty($billing_data['second_case'])) ? 'block' : 'none'; ?>;">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label>Professional Fee Discount</label>
                                        <input type="number" step="0.01" min="0" class="form-control" id="professional-fee-discount-second" name="philhealth_pf" value="<?php echo $billing_data['philhealth_pf']; ?>" <?php echo (empty($billing_data['second_case'])) ? 'disabled' : ''; ?>>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label>Hospital Bill Discount</label>
                                        <input type="number" step="0.01" min="0" class="form-control" id="hospital-bill-discount-second" name="philhealth_hb" value="<?php echo $billing_data['philhealth_hb']; ?>" <?php echo (empty($billing_data['second_case'])) ? 'disabled' : ''; ?>>
                                    </div>
                                </div>
                                <?php if (!empty($billing_data['second_case'])): ?>
                                <div class="col-sm-12 rvs-icd-input">
                                    <label>RVS or ICD Code</label>
                                    <input type="text" class="form-control" name="second_case" value="<?php echo $billing_data['second_case']; ?>">
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Summary Section -->
                    <div class="form-section summary-section">
                        <h6 class="section-title">Account Summary</h6>
                        <div class="summary-card">
                            <div class="summary-row">
                                <span class="summary-label">Total Charges:</span>
                                <span class="summary-value" id="total-charges">₱<?php echo number_format($billing_data['non_discounted_total'], 2); ?></span>
                            </div>
                            <div class="summary-row">
                                <span class="summary-label">Total Discounts:</span>
                                <span class="summary-value" id="total-discounts">₱<?php echo number_format($billing_data['pwd_discount_amount'] + $billing_data['vat_exempt_discount_amount'] + $billing_data['discount_amount'], 2); ?></span>
                            </div>
                            <div class="summary-row">
                                <span class="summary-label">Total PhilHealth Discounts:</span>
                                <span class="summary-value" id="total-deduction">₱<?php echo number_format($billing_data['philhealth_pf'] + $billing_data['philhealth_hb'], 2); ?></span>
                            </div>
                            <div class="summary-row total-due">
                                <span class="summary-label" style="font-weight: bold;">Total Amount Due:</span>
                                <span class="summary-value" id="total-due">₱<?php echo number_format($billing_data['total_due'], 2); ?></span>
                            </div>
                        </div>
                    </div>
                    <!-- Submit Button -->
                    <div class="form-submit text-center mt-4">
                        <button type="submit" name="update-billing" class="btn btn-primary btn-lg">
                            <i class="fas fa-save mr-2"></i> Update Statement
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php
include('footer.php');
?>

<script src="assets/js/moment.min.js"></script>
<script src="assets/js/bootstrap-datetimepicker.js"></script>
<script src="assets/js/bootstrap-datetimepicker.min.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const pwdDiscountCheckbox = document.getElementById('pwd-discount-checkbox');
    const discountCheckbox = document.getElementById('discount-checkbox');
    const vatExemptCheckbox = document.getElementById('vat-exempt-checkbox');
    const readersFeeInput = document.getElementById('readers-fee');
    const othersContainer = document.getElementById('other-items-container');
    const suppliesFeeInput = document.getElementById('supplies-fee');
    const firstPFInput = document.getElementById('professional-fee-discount');
    const firstHBInput = document.getElementById('hospital-bill-discount');
    const secondPFInput = document.getElementById('professional-fee-discount-second');
    const secondHBInput = document.getElementById('hospital-bill-discount-second');
    let othersFee = 0;
    let suppliesFee = 0;
    let discountAmount = 0;
    let totalDue = 0;

    function calculateTotalDue() {
        const roomFee = parseFloat(document.querySelector('input[name="room_fee"]').value) || 0;
        const labFee = parseFloat(document.querySelector('input[name="lab_fee"]').value) || 0;
        const radFee = parseFloat(document.querySelector('input[name="rad_fee"]').value) || 0;
        const medicationFee = parseFloat(document.querySelector('input[name="medication_fee"]').value) || 0;
        const orFee = parseFloat(document.querySelector('input[name="operating_room_fee"]').value) || 0;
        const professionalFee = parseFloat(document.getElementById('professional-fee').value) || 0;
        const readersFee = parseFloat(readersFeeInput.value) || 0;
        suppliesFee = parseFloat(suppliesFeeInput.value) || 0;

        othersFee = 0;
        document.querySelectorAll('#other-items-container input[name^="others"]').forEach(input => {
            if (input.name.includes('[cost]')) {
                othersFee += parseFloat(input.value) || 0;
            }
        });

        // Calculate non-discounted total (sum of ALL charges)
        const nonDiscountedTotal = labFee + radFee + roomFee + 
                                medicationFee + orFee + othersFee + 
                                suppliesFee + professionalFee + readersFee;
        
        // Update total charges display (non-discounted amount)
        document.getElementById('total-charges').innerText = '₱' + nonDiscountedTotal.toFixed(2);

        // Get PhilHealth deductions
        const firstPF = parseFloat(firstPFInput.value) || 0;
        const firstHB = parseFloat(firstHBInput.value) || 0;
        const secondPF = parseFloat(secondPFInput.value) || 0;
        const secondHB = parseFloat(secondHBInput.value) || 0;

        // Calculate total PhilHealth deductions
        const philhealthDeduction = firstPF + firstHB + secondPF + secondHB;

        document.getElementById('total-deduction').innerText = '₱' + philhealthDeduction.toFixed(2);

        // Calculate combined fees including the OR fee
        let combinedFees = labFee + radFee + roomFee + medicationFee + orFee + othersFee + suppliesFee + professionalFee + readersFee;

        let pwdDiscountAmount = 0;
        let vatExemptDiscountAmount = 0;
        let seniorPwdDiscountAmount = 0;

        // Apply discounts based on checkbox states
        if (pwdDiscountCheckbox.checked) {
            pwdDiscountAmount = combinedFees * 0.20;
            combinedFees -= pwdDiscountAmount;
        }

        if (vatExemptCheckbox.checked) {
            vatExemptDiscountAmount = combinedFees * 0.12;
            combinedFees -= vatExemptDiscountAmount;
        }

        if (discountCheckbox.checked) {
            seniorPwdDiscountAmount = combinedFees * 0.20;
            combinedFees -= seniorPwdDiscountAmount;
        }

        // Subtract PhilHealth deductions from total and ensure it doesn't go below 0
        totalDue = Math.max(combinedFees - philhealthDeduction, 0);

        // Sum up the total discount amount
        discountAmount = pwdDiscountAmount + vatExemptDiscountAmount + seniorPwdDiscountAmount;

        document.getElementById('total-discounts').innerText = '₱' + discountAmount.toFixed(2);

        // Update the UI with the calculated amounts
        document.getElementById('total-due').innerText = '₱' + totalDue.toFixed(2);
        document.getElementById('pwd-discount').innerText = '₱' + pwdDiscountAmount.toFixed(2);
        document.getElementById('vat-exempt-discount').innerText = '₱' + vatExemptDiscountAmount.toFixed(2);
        document.getElementById('discount-amount').innerText = '₱' + seniorPwdDiscountAmount.toFixed(2);
    }

    // Add event listeners for all fee inputs
    document.querySelectorAll('input[name="room_fee"], input[name="lab_fee"], input[name="rad_fee"], input[name="medication_fee"], input[name="operating_room_fee"], #professional-fee, #readers-fee, #supplies-fee').forEach(input => {
        input.addEventListener('input', calculateTotalDue);
    });

    [firstPFInput, firstHBInput, secondPFInput, secondHBInput].forEach(input => {
        input.addEventListener('input', calculateTotalDue);
    });

    pwdDiscountCheckbox.addEventListener('change', calculateTotalDue);
    vatExemptCheckbox.addEventListener('change', calculateTotalDue);
    discountCheckbox.addEventListener('change', calculateTotalDue);
    othersContainer.addEventListener('input', calculateTotalDue);

    // Add event listener for the "Add Item" button
    document.getElementById('add-other-item').addEventListener('click', function () {
        const container = document.getElementById('other-items-container');
        const itemCount = container.querySelectorAll('.other-item').length; // Count existing items

        const newItemHTML = `
            <div class="row align-items-center other-item mb-2" id="item-row-${itemCount}">
                <div class="col-md-6">
                    <input type="text" class="form-control" name="others[${itemCount}][name]" placeholder="Item description" required>
                </div>
                <div class="col-md-5">
                    <input type="number" step="0.01" class="form-control" name="others[${itemCount}][cost]" placeholder="Amount" required>
                </div>
                <div class="col-md-1">
                    <button type="button" class="btn btn-sm btn-danger remove-item" data-id="item-row-${itemCount}">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            </div>
        `;

        container.insertAdjacentHTML('beforeend', newItemHTML);

        // Enable all remove buttons except for the first item
        const removeButtons = document.querySelectorAll('.remove-item');
        if (removeButtons.length > 1) {
            removeButtons.forEach(button => {
                button.disabled = false;
            });
        }
    });

    // Single event delegation for remove buttons
    document.getElementById('other-items-container').addEventListener('click', function (event) {
        if (event.target.closest('.remove-item')) {
            const button = event.target.closest('.remove-item');
            const itemId = button.getAttribute('data-id');
            document.getElementById(itemId)?.remove();
            
            // After removal, check if we need to disable the first remove button
            const remainingItems = document.querySelectorAll('.other-item');
            if (remainingItems.length === 1) {
                remainingItems[0].querySelector('.remove-item').disabled = true;
            }
            
            // Re-index the remaining items
            const items = document.querySelectorAll('.other-item');
            items.forEach((item, index) => {
                item.id = `item-row-${index}`;
                const inputs = item.querySelectorAll('input');
                inputs[0].name = `others[${index}][name]`;
                inputs[1].name = `others[${index}][cost]`;
                item.querySelector('.remove-item').setAttribute('data-id', `item-row-${index}`);
            });
        }
    });

    // Add event listener for PWD checkbox
    pwdDiscountCheckbox.addEventListener('change', function () {
        const isChecked = pwdDiscountCheckbox.checked;

        // Enable/disable VAT Exempt and Senior Discount based on PWD checkbox
        vatExemptCheckbox.disabled = isChecked;
        discountCheckbox.disabled = isChecked;
    });

    // Add event listener for VAT Exempt checkbox
    vatExemptCheckbox.addEventListener('change', function () {
        const isChecked = vatExemptCheckbox.checked;

        // Disable PWD Discount if VAT Exempt is checked
        pwdDiscountCheckbox.disabled = isChecked || discountCheckbox.checked;
    });

    // Add event listener for Senior Citizen Discount checkbox
    discountCheckbox.addEventListener('change', function () {
        const isChecked = discountCheckbox.checked;

        // Disable PWD Discount if Senior Citizen Discount is checked
        pwdDiscountCheckbox.disabled = isChecked || vatExemptCheckbox.checked;
    });

    // Case rate select functionality
    const caseRateSelect = document.getElementById("case-rate-select");
    const firstCaseRateContainer = document.getElementById("first-case-rate-container");
    const secondCaseRateContainer = document.getElementById("second-case-rate-container");
    const firstCaseInputs = firstCaseRateContainer.querySelectorAll("input");
    const secondCaseInputs = secondCaseRateContainer.querySelectorAll("input");

    caseRateSelect.addEventListener("change", function () {
        if (this.value === "first") {
            firstCaseRateContainer.style.display = "block";  
            secondCaseRateContainer.style.display = "none"; 

            firstCaseInputs.forEach(input => input.removeAttribute("disabled"));
            secondCaseInputs.forEach(input => input.setAttribute("disabled", "true"));
        } else if (this.value === "second") {
            secondCaseRateContainer.style.display = "block";  
            firstCaseRateContainer.style.display = "none";  

            secondCaseInputs.forEach(input => input.removeAttribute("disabled"));
            firstCaseInputs.forEach(input => input.setAttribute("disabled", "true"));
        } else {
            firstCaseRateContainer.style.display = "none";
            secondCaseRateContainer.style.display = "none";

            firstCaseInputs.forEach(input => input.setAttribute("disabled", "true"));
            secondCaseInputs.forEach(input => input.setAttribute("disabled", "true"));
        }
        calculateTotalDue();
    });

    // Initialize the form calculation
    calculateTotalDue();
});
</script>

<style>
.btn-primary.submit-btn {
    border-radius: 4px; 
    padding: 10px 20px;
    font-size: 16px;
    font-weight: 500;
    transition: all 0.3s ease;
    box-shadow: 0 2px 4px rgba(18, 54, 158, 0.2);
}

.btn-primary {
    background: #12369e;
    border: none;
}

.btn-primary:hover {
    background: #05007E;
    transform: translateY(-1px);
    box-shadow: 0 4px 8px rgba(18, 54, 158, 0.3);
}

.form-control {
    border-radius: .375rem;
    border-color: #ced4da;
    background-color: #f8f9fa;
}

select.form-control {
    border-radius: .375rem;
    border: 1px solid;
    border-color: #ced4da;
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

.fees-container {
    padding: 18px;
    background-color: rgb(241, 241, 241);
    border-radius: 12px;
    box-shadow: 0 6px 12px rgba(0, 0, 0, 0.15);
    margin-bottom: 25px;
    border: 1px solid #e0e4e8;
    transition: box-shadow 0.3s ease, transform 0.3s ease;
}

.btn-primary.submit-btn {
    border-radius: 6px;
    padding: 12px 24px;
    font-size: 16px;
    font-weight: 500;
    transition: all 0.3s ease;
    box-shadow: 0 2px 4px rgba(18, 54, 158, 0.2);
}

.form-control {
    border-radius: 6px;
    border: 2px solid #e0e4e8;
    background-color: #f8f9fa;
    padding: 10px 15px;
    transition: all 0.3s ease;
}

.form-control:focus {
    border-color: #12369e;
    box-shadow: 0 0 0 3px rgba(18, 54, 158, 0.1);
    background-color: #ffffff;
}

.patient-list {
    max-height: 200px;
    overflow-y: auto;
    border: 1px solid #e0e4e8;
    border-radius: 8px;
    background: #fff;
    position: absolute;
    z-index: 1000;
    width: 93%;
    display: none;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
}

.patient-list .patient-option {
    padding: 10px 15px;
    cursor: pointer;
    list-style: none;
    border-bottom: 1px solid #e0e4e8;
    transition: all 0.2s ease;
}

.patient-list .patient-option:hover {
    background-color: #12369e;
    color: white;
}

input[type="checkbox"] {
    width: 18px;
    height: 18px;
    border-radius: 4px;
    border: 2px solid #12369e;
    cursor: pointer;
}

#total-due, #discount-amount, #pwd-discount, #vat-exempt-discount {
    font-size: 26px;
    font-weight: 700;
    color:rgb(49, 49, 49);
}

.table::-webkit-scrollbar {
    width: 10px;
}

.table::-webkit-scrollbar-thumb {
    background-color: #12369e;
}

.patient-type-section {
    padding: 20px;
    background-color:rgb(241, 241, 241);
    border-radius: 12px;
    box-shadow: 0 6px 12px rgba(0, 0, 0, 0.15);
    margin-bottom: 25px;
    border: 1px solid #e0e4e8;
    transition: box-shadow 0.3s ease, transform 0.3s ease;
}

.patient-type-section:hover {
    box-shadow: 0 8px 16px rgba(0, 0, 0, 0.2);
    transform: translateY(-4px);
}

.patient-type-section h5 {
    font-size: 18px;
    color: #343a40;
    margin-bottom: 12px;
    font-weight: 600;
}

.button-container {
    display: flex;
    justify-content: center;
    align-items: center;
    margin-top: 25px;
    padding: 10px 0;
}

.button-container {
    display: flex;
    justify-content: space-between;
    margin-top: 1px;
}

#add-other-item, #remove-other-item {
    width: 100%;
    padding: 10px 20px;
    font-size: 14px;
    border-radius: 6px;
}

#add-other-item {
    background-color: #12369e;
    color: white;
}

#add-other-item:hover {
    background-color: #05007E;
}

#remove-other-item {
    background-color:rgb(216, 35, 53);
    color: white;
}

#remove-other-item:hover {
    background-color:rgb(201, 18, 36);
}

@media (max-width: 768px) {
    .button-container {
        flex-direction: column;
        align-items: center;
    }

    #add-other-item, #remove-other-item {
        width: 80%;
    }
}

.form-group {
    margin-bottom: 20px;
}

@media (max-width: 768px) {
    .form-section {
        padding: 15px;
    }
    
    .section-title {
        font-size: 1rem;
    }
    
    .table-responsive {
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
    }
    
    .discount-option {
        margin-bottom: 15px;
    }
    
    .other-item .col-md-1 {
        text-align: right;
    }
}

.billing-card {
    border: none;
    border-radius: 10px;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
}

.billing-card .card-header {
    background-color: var(--primary-color);
    color: black;
    border-radius: 10px 10px 0 0 !important;
    padding: 15px 20px;
    border-bottom: none;
}

.billing-card .card-header .card-title {
    color: black;
    font-weight: 600;
    margin-bottom: 0;
}

.form-section {
    background-color: white;
    border-radius: 8px;
    padding: 20px;
    margin-bottom: 20px;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
    border: 1px solid var(--border-color);
}

.section-title {
    color: black;
    font-weight: 600;
    margin-bottom: 20px;
    padding-bottom: 10px;
    border-bottom: 1px solid var(--border-color);
    font-size: 1.1rem;
}

.form-control {
    border-radius: 6px;
    border: 1px solid var(--border-color);
    padding: 10px 15px;
    height: calc(2.25rem + 8px);
    transition: all 0.3s;
}

.form-control:focus {
    border-color:#12369e;
    box-shadow: 0 0 0 0.2rem rgba(18, 54, 158, 0.1);
}

select.form-control {
    -webkit-appearance: none;
    -moz-appearance: none;
    appearance: none;
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' fill='%236c757d' viewBox='0 0 16 16'%3E%3Cpath d='M7.247 11.14 2.451 5.658C1.885 5.013 2.345 4 3.204 4h9.592a1 1 0 0 1 .753 1.659l-4.796 5.48a1 1 0 0 1-1.506 0z'/%3E%3C/svg%3E");
    background-repeat: no-repeat;
    background-position: right 12px center;
    background-size: 16px 12px;
}

.search-container {
    position: relative;
}

.search-results {
    position: absolute;
    z-index: 1000;
    width: 100%;
    max-height: 250px;
    overflow-y: auto;
    background: white;
    border: 1px solid var(--border-color);
    border-radius: 0 0 6px 6px;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
    display: none;
}

.search-results .patient-option {
    padding: 10px 15px;
    cursor: pointer;
    transition: all 0.2s;
}

.search-results .patient-option:hover {
    background-color: #12369e;
    color: white;
}

.charge-group {
    margin-bottom: 25px;
}

.charge-group h5 {
    font-size: 1rem;
    font-weight: 600;
    color:rgb(90, 90, 90);
    margin-bottom: 15px;
    padding-bottom: 8px;
    border-bottom: 1px solid var(--border-color);
}

.table {
    width: 100%;
    margin-bottom: 1rem;
    color: black;
    border-collapse: separate;
    border-spacing: 0;
    border-radius: 6px;
    overflow: hidden;
}

.table thead th {
    background-color: #CCCCCC;
    color: black;
    font-weight: 600;
    border: none;
    padding: 12px 15px;
}

.table tbody tr:hover {
    background-color: rgba(0, 0, 0, 0.04);
}

.table td {
    padding: 12px 15px;
    vertical-align: middle;
    border-top: 1px solid var(--border-color);
}

.additional-fees {
    background-color: var(--light-gray);
    border: 1px solid var(--border-color);
    border-radius: 6px;
    padding: 15px;
    border-radius: 6px;
    margin-top: 20px;
}

.additional-fees h5 {
    color : black;
    font-size: 1rem;
    font-weight: 600;
    margin-bottom: 15px;
}

.other-items {
    margin-top: 20px;
}

.other-item {
    background-color: white;
    padding: 10px;
    border-radius: 6px;
    margin-bottom: 10px;
    border: 1px solid var(--border-color);
}

.remove-item {
    width: 30px;
    height: 30px;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 0;
}

.discount-option {
    background-color: var(--light-gray);
    padding: 15px;
    border-radius: 6px;
    height: 100%;
}

.custom-checkbox {
    margin-bottom: 10px;
}

.discount-value {
    font-weight: 600;
    font-size: 1.1rem;
    color: #12369e;
}

.philhealth-section {
    margin-top: 20px;
    padding-top: 15px;
    border-top: 1px solid var(--border-color);
}

.philhealth-section h5 {
    color: black;
    font-size: 1rem;
    font-weight: 600;
    margin-bottom: 15px;
}

.summary-card {
    background-color: var(--light-gray);
    border-radius: 6px;
    padding: 20px;
}

.summary-row {
    display: flex;
    justify-content: space-between;
    padding: 10px 0;
    border-bottom: 1px solid var(--border-color);
}

.summary-row:last-child {
    border-bottom: none;
}

.summary-label {
    font-weight: 500;
}

.summary-value {
    font-weight: 600;
    color:rgb(70, 70, 70);
}

.total-due {
    font-size: 1.2rem;
    color: #12369e;
    margin-top: 10px;
    font-weight: 600;
}

.custom-control-input:checked ~ .custom-control-label::before {
    background-color:#12369e;
    border-color: #12369e;
    box-shadow: 0 0 0 0.2rem rgba(18, 54, 158, 0.1);
}

.custom-control-label {
    cursor: pointer;
    user-select: none;
}
</style>