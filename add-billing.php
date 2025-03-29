<?php
session_start();
if (empty($_SESSION['name'])) {
    header('location:index.php');
    exit();
}

include('header.php');
include('includes/connection.php');

// Secure the query to fetch max billing ID
$fetch_max_billing_id = mysqli_query($connection, "
    SELECT MAX(CAST(SUBSTRING(billing_id, 4) AS UNSIGNED)) AS max_billing_id FROM (
        SELECT billing_id FROM tbl_billing_inpatient 
        UNION ALL 
        SELECT billing_id FROM tbl_billing_hemodialysis 
        UNION ALL 
        SELECT billing_id FROM tbl_billing_newborn
    ) AS all_billing
");

$row_max_billing_id = mysqli_fetch_assoc($fetch_max_billing_id);
$highest_id = isset($row_max_billing_id['max_billing_id']) ? (int)$row_max_billing_id['max_billing_id'] : 0;

// Generate the next billing ID
$bl_id = $highest_id + 1;
$billing_id = 'BL-' . $bl_id;

// Input validation function
function validateInput($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

if (isset($_POST['add-billing'])) {
    $billing_id = 'BL-' . $bl_id;
    $patient_name = validateInput(mysqli_real_escape_string($connection, $_POST['patient_name']));
    $patient_type = validateInput($_POST['patient_type']);

    // Validate patient type
    if (!in_array($patient_type, ['inpatient', 'hemodialysis', 'newborn'])) {
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Invalid Patient Type',
                text: 'Please select a valid patient type.'
            });
        </script>";
        exit();
    }

    // Fetch patient details based on patient type with proper escaping
    $patient_name_escaped = mysqli_real_escape_string($connection, $patient_name);
    
    if ($patient_type == 'inpatient') {
        $patient_result = mysqli_query($connection, "
            SELECT ipr.patient_id, ipr.patient_name, ipr.dob, ipr.gender, ipr.admission_date, ipr.discharge_date, ipr.diagnosis, p.address
            FROM tbl_inpatient_record AS ipr
            JOIN tbl_patient AS p
            ON ipr.patient_id = p.patient_id
            WHERE ipr.patient_name = '".$patient_name_escaped."'");
    
    } else if ($patient_type == 'hemodialysis') {
        $patient_result = mysqli_query($connection, "
            SELECT h.patient_id, h.patient_name, h.dob, h.gender, p.address
            FROM tbl_hemodialysis AS h
            JOIN tbl_patient AS p
            ON h.patient_id = p.patient_id
            WHERE h.patient_name = '".$patient_name_escaped."'");
    
    } else if ($patient_type == 'newborn') {
        $patient_result = mysqli_query($connection, "
            SELECT newborn_id, CONCAT(first_name, ' ', last_name) AS patient_name, dob, gender, admission_date, discharge_date, diagnosis, address
            FROM tbl_newborn
            WHERE CONCAT(first_name, ' ', last_name) = '".$patient_name_escaped."'");
    }
    
    $patient = mysqli_fetch_array($patient_result, MYSQLI_ASSOC);

    if ($patient) {
        // Retrieve patient details with proper escaping
        $patient_id = !empty($patient['patient_id']) ? mysqli_real_escape_string($connection, $patient['patient_id']) : "NULL";
        $newborn_id = !empty($patient['newborn_id']) ? mysqli_real_escape_string($connection, $patient['newborn_id']) : "NULL";
        $dob = !empty($patient['dob']) ? "'" . mysqli_real_escape_string($connection, $patient['dob']) . "'" : "NULL";
        $gender = !empty($patient['gender']) ? "'" . mysqli_real_escape_string($connection, $patient['gender']) . "'" : "NULL";
        $admission_date = !empty($patient['admission_date']) ? "'" . mysqli_real_escape_string($connection, $patient['admission_date']) . "'" : "NULL";
        $discharge_date = !empty($patient['discharge_date']) ? "'" . mysqli_real_escape_string($connection, $patient['discharge_date']) . "'" : "NULL";
        $diagnosis = !empty($patient['diagnosis']) ? "'" . mysqli_real_escape_string($connection, $patient['diagnosis']) . "'" : "NULL";
        $address = !empty($patient['address']) ? "'" . mysqli_real_escape_string($connection, $patient['address']) . "'" : "NULL";

        // PhilHealth fields with proper escaping
        $first_case = isset($_POST['first_case']) ? mysqli_real_escape_string($connection, $_POST['first_case']) : '';
        $second_case = isset($_POST['second_case']) ? mysqli_real_escape_string($connection, $_POST['second_case']) : '';
        $philhealth_pf = isset($_POST['philhealth_pf']) ? mysqli_real_escape_string($connection, $_POST['philhealth_pf']) : '0';
        $philhealth_hb = isset($_POST['philhealth_hb']) ? mysqli_real_escape_string($connection, $_POST['philhealth_hb']) : '0';

        // Numeric fields with validation
        $room_fee = isset($_POST['room_fee']) && is_numeric($_POST['room_fee']) ? mysqli_real_escape_string($connection, $_POST['room_fee']) : '0';
        $lab_fee = isset($_POST['lab_fee']) && is_numeric($_POST['lab_fee']) ? mysqli_real_escape_string($connection, $_POST['lab_fee']) : '0';
        $rad_fee = isset($_POST['rad_fee']) && is_numeric($_POST['rad_fee']) ? mysqli_real_escape_string($connection, $_POST['rad_fee']) : '0';
        $medication_fee = '0';
        $operating_room_fee = isset($_POST['operating_room_fee']) && is_numeric($_POST['operating_room_fee']) ? mysqli_real_escape_string($connection, $_POST['operating_room_fee']) : '0';
        $supplies_fee = isset($_POST['supplies_fee']) && is_numeric($_POST['supplies_fee']) ? mysqli_real_escape_string($connection, $_POST['supplies_fee']) : '0';
        $professional_fee = isset($_POST['professional_fee']) && is_numeric($_POST['professional_fee']) ? mysqli_real_escape_string($connection, $_POST['professional_fee']) : '0';
        $readers_fee = isset($_POST['readers_fee']) && is_numeric($_POST['readers_fee']) ? mysqli_real_escape_string($connection, $_POST['readers_fee']) : '0';
        $others_fee = isset($_POST['others_fee']) && is_numeric($_POST['others_fee']) ? mysqli_real_escape_string($connection, $_POST['others_fee']) : '0';
        
        // Calculate medication fee using prepared statement
        $medication_query = $connection->prepare("SELECT SUM(total_price) AS medication_fee FROM tbl_treatment WHERE patient_name = ? AND deleted = 0");
        $medication_query->bind_param("s", $patient_name);
        $medication_query->execute();
        $medication_result = $medication_query->get_result();
        if ($medication = $medication_result->fetch_assoc()) {
            $medication_fee = mysqli_real_escape_string($connection, $medication['medication_fee'] ?? '0');
        }

        // Handle others fees with proper escaping
        if (isset($_POST['others']) && !empty($_POST['others'])) {
            foreach ($_POST['others'] as $item) {
                $item_name = mysqli_real_escape_string($connection, $item['name']);
                $item_cost = mysqli_real_escape_string($connection, $item['cost']);

                $insert_query = "
                    INSERT INTO tbl_billing_others (billing_id, item_name, item_cost, date_time)
                    VALUES ('".mysqli_real_escape_string($connection, $billing_id)."', 
                            '".$item_name."', 
                            '".$item_cost."', 
                            NOW())
                ";
                mysqli_query($connection, $insert_query);
            }

            $others_fee_query = "
            SELECT SUM(item_cost) AS others_fee
            FROM tbl_billing_others
            WHERE billing_id = '".mysqli_real_escape_string($connection, $billing_id)."' AND deleted = 0
            ";

            $others_fee_result = mysqli_query($connection, $others_fee_query);
            $others_fee_row = mysqli_fetch_assoc($others_fee_result);
            $others_fee = mysqli_real_escape_string($connection, $others_fee_row['others_fee'] ?? '0');
        }

        // Get checkbox states
        $vat_exempt_checkbox = isset($_POST['vat_exempt_checkbox']) ? mysqli_real_escape_string($connection, $_POST['vat_exempt_checkbox']) : 'off';
        $discount_checkbox = isset($_POST['discount_checkbox']) ? mysqli_real_escape_string($connection, $_POST['discount_checkbox']) : 'off';
        $pwd_discount_checkbox = isset($_POST['pwd_discount_checkbox']) ? mysqli_real_escape_string($connection, $_POST['pwd_discount_checkbox']) : 'off';

        // Calculate discounts (no SQL here, just math)
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

        // Calculate totals (just math, no SQL)
        $non_discounted_total = floatval($room_fee) + floatval($lab_fee) + floatval($rad_fee) + 
                            floatval($medication_fee) + floatval($operating_room_fee) + 
                            floatval($supplies_fee) + floatval($professional_fee) + 
                            floatval($readers_fee) + floatval($others_fee);

        if ($pwd_discount_checkbox == 'on') {
            $pwd_discount_amount = $non_discounted_total * 0.20;
            $after_pwd = $non_discounted_total - $pwd_discount_amount;
        } else {
            $pwd_discount_amount = 0;
            $after_pwd = $non_discounted_total;
        }

        if ($vat_exempt_checkbox == 'on') {
            $vat_exempt_discount_amount = $after_pwd * 0.12;
            $after_vat = $after_pwd - $vat_exempt_discount_amount;
        } else {
            $vat_exempt_discount_amount = 0;
            $after_vat = $after_pwd;
        }

        if ($discount_checkbox == 'on') {
            $total_discount = $after_vat * 0.20;
        } else {
            $total_discount = 0;
        }

        $total_due = $non_discounted_total - $pwd_discount_amount - $vat_exempt_discount_amount - $total_discount - floatval($philhealth_pf) - floatval($philhealth_hb);
        $remaining_balance = max(0, $total_due);

        // Insert billing details with all values properly escaped
        $safe_billing_id = mysqli_real_escape_string($connection, $billing_id);
        $safe_patient_name = mysqli_real_escape_string($connection, $patient_name);
        
        if ($patient_type == 'inpatient') {
            $query = "INSERT INTO tbl_billing_inpatient
            (billing_id, patient_id, patient_name, dob, gender, admission_date, discharge_date, diagnosis, address, 
            lab_fee, room_fee, medication_fee, operating_room_fee, supplies_fee, total_due, non_discounted_total, 
            discount_amount, professional_fee, pwd_discount_amount, readers_fee, others_fee, rad_fee, 
            vat_exempt_discount_amount, first_case, second_case, philhealth_pf, philhealth_hb,
            room_discount, lab_discount, rad_discount, med_discount, or_discount, supplies_discount, 
            other_discount, pf_discount, readers_discount, remaining_balance) 
            VALUES ('".$safe_billing_id."', 
                    '".$patient_id."', 
                    '".$safe_patient_name."', 
                    ".$dob.", 
                    ".$gender.", 
                    ".$admission_date.", 
                    ".$discharge_date.", 
                    ".$diagnosis.", 
                    ".$address.", 
                    '".$lab_fee."', 
                    '".$room_fee."', 
                    '".$medication_fee."', 
                    '".$operating_room_fee."', 
                    '".$supplies_fee."', 
                    '".mysqli_real_escape_string($connection, $total_due)."', 
                    '".mysqli_real_escape_string($connection, $non_discounted_total)."', 
                    '".mysqli_real_escape_string($connection, $total_discount)."', 
                    '".$professional_fee."', 
                    '".mysqli_real_escape_string($connection, $pwd_discount_amount)."', 
                    '".$readers_fee."', 
                    '".$others_fee."', 
                    '".$rad_fee."', 
                    '".mysqli_real_escape_string($connection, $vat_exempt_discount_amount)."', 
                    '".$first_case."', 
                    '".$second_case."', 
                    '".$philhealth_pf."', 
                    '".$philhealth_hb."', 
                    '".mysqli_real_escape_string($connection, $room_discount)."', 
                    '".mysqli_real_escape_string($connection, $lab_discount)."', 
                    '".mysqli_real_escape_string($connection, $rad_discount)."', 
                    '".mysqli_real_escape_string($connection, $med_discount)."', 
                    '".mysqli_real_escape_string($connection, $or_discount)."', 
                    '".mysqli_real_escape_string($connection, $supplies_discount)."', 
                    '".mysqli_real_escape_string($connection, $other_discount)."', 
                    '".mysqli_real_escape_string($connection, $pf_discount)."', 
                    '".mysqli_real_escape_string($connection, $readers_discount)."', 
                    '".mysqli_real_escape_string($connection, $remaining_balance)."')";

        } elseif ($patient_type == 'hemodialysis') {
            $query = "INSERT INTO tbl_billing_hemodialysis
            (billing_id, patient_id, patient_name, dob, gender, admission_date, discharge_date, diagnosis, address,
            lab_fee, room_fee, medication_fee, operating_room_fee, supplies_fee, total_due, non_discounted_total,
            discount_amount, professional_fee, pwd_discount_amount, readers_fee, others_fee, rad_fee,
            vat_exempt_discount_amount, first_case, second_case, philhealth_pf, philhealth_hb,
            room_discount, lab_discount, rad_discount, med_discount, or_discount, supplies_discount,
            other_discount, pf_discount, readers_discount, remaining_balance)
            VALUES ('".$safe_billing_id."', 
                    '".$patient_id."', 
                    '".$safe_patient_name."', 
                    ".$dob.", 
                    ".$gender.", 
                    ".$admission_date.", 
                    ".$discharge_date.", 
                    ".$diagnosis.", 
                    ".$address.", 
                    '".$lab_fee."', 
                    '".$room_fee."', 
                    '".$medication_fee."', 
                    '".$operating_room_fee."', 
                    '".$supplies_fee."', 
                    '".mysqli_real_escape_string($connection, $total_due)."', 
                    '".mysqli_real_escape_string($connection, $non_discounted_total)."', 
                    '".mysqli_real_escape_string($connection, $total_discount)."', 
                    '".$professional_fee."', 
                    '".mysqli_real_escape_string($connection, $pwd_discount_amount)."', 
                    '".$readers_fee."', 
                    '".$others_fee."', 
                    '".$rad_fee."', 
                    '".mysqli_real_escape_string($connection, $vat_exempt_discount_amount)."', 
                    '".$first_case."', 
                    '".$second_case."', 
                    '".$philhealth_pf."', 
                    '".$philhealth_hb."', 
                    '".mysqli_real_escape_string($connection, $room_discount)."', 
                    '".mysqli_real_escape_string($connection, $lab_discount)."', 
                    '".mysqli_real_escape_string($connection, $rad_discount)."', 
                    '".mysqli_real_escape_string($connection, $med_discount)."', 
                    '".mysqli_real_escape_string($connection, $or_discount)."', 
                    '".mysqli_real_escape_string($connection, $supplies_discount)."', 
                    '".mysqli_real_escape_string($connection, $other_discount)."', 
                    '".mysqli_real_escape_string($connection, $pf_discount)."', 
                    '".mysqli_real_escape_string($connection, $readers_discount)."', 
                    '".mysqli_real_escape_string($connection, $remaining_balance)."')";

        } elseif ($patient_type == 'newborn') {
            $query = "INSERT INTO tbl_billing_newborn
            (billing_id, newborn_id, patient_name, dob, gender, admission_date, discharge_date, diagnosis, address,
            lab_fee, room_fee, medication_fee, operating_room_fee, supplies_fee, total_due, non_discounted_total,
            discount_amount, professional_fee, pwd_discount_amount, readers_fee, others_fee, rad_fee,
            vat_exempt_discount_amount, first_case, second_case, philhealth_pf, philhealth_hb,
            room_discount, lab_discount, rad_discount, med_discount, or_discount, supplies_discount,
            other_discount, pf_discount, readers_discount, remaining_balance)
            VALUES ('".$safe_billing_id."', 
                    '".$newborn_id."', 
                    '".$safe_patient_name."', 
                    ".$dob.", 
                    ".$gender.", 
                    ".$admission_date.", 
                    ".$discharge_date.", 
                    ".$diagnosis.", 
                    ".$address.", 
                    '".$lab_fee."', 
                    '".$room_fee."', 
                    '".$medication_fee."', 
                    '".$operating_room_fee."', 
                    '".$supplies_fee."', 
                    '".mysqli_real_escape_string($connection, $total_due)."', 
                    '".mysqli_real_escape_string($connection, $non_discounted_total)."', 
                    '".mysqli_real_escape_string($connection, $total_discount)."', 
                    '".$professional_fee."', 
                    '".mysqli_real_escape_string($connection, $pwd_discount_amount)."', 
                    '".$readers_fee."', 
                    '".$others_fee."', 
                    '".$rad_fee."', 
                    '".mysqli_real_escape_string($connection, $vat_exempt_discount_amount)."', 
                    '".$first_case."', 
                    '".$second_case."', 
                    '".$philhealth_pf."', 
                    '".$philhealth_hb."', 
                    '".mysqli_real_escape_string($connection, $room_discount)."', 
                    '".mysqli_real_escape_string($connection, $lab_discount)."', 
                    '".mysqli_real_escape_string($connection, $rad_discount)."', 
                    '".mysqli_real_escape_string($connection, $med_discount)."', 
                    '".mysqli_real_escape_string($connection, $or_discount)."', 
                    '".mysqli_real_escape_string($connection, $supplies_discount)."', 
                    '".mysqli_real_escape_string($connection, $other_discount)."', 
                    '".mysqli_real_escape_string($connection, $pf_discount)."', 
                    '".mysqli_real_escape_string($connection, $readers_discount)."', 
                    '".mysqli_real_escape_string($connection, $remaining_balance)."')";
        }

        if (mysqli_query($connection, $query)) {
            echo "
            <script src='https://cdn.jsdelivr.net/npm/sweetalert2@10'></script>
            <script>
                document.addEventListener('DOMContentLoaded', function() {
                    Swal.fire({
                        icon: 'success',
                        title: 'Success!',
                        text: 'Billing account added successfully!',
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
                        text: 'Failed to add billing account. Please try again.',
                        confirmButtonColor: '#12369e'
                    });
                });
            </script>";
        }
    } else {
        $msg = "Error: Patient not found.";
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
                            <h4 class="page-title">Statement of Account</h4>
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
                                    <select class="form-control" id="patient-type-select" name="patient_type" required>
                                        <option value="">Select Patient Type</option>
                                        <option value="inpatient">Inpatient</option>
                                        <option value="hemodialysis">Hemodialysis</option>
                                        <option value="newborn">Newborn</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Billing ID</label>
                                    <input class="form-control" type="text" value="<?php echo 'BL-' . $bl_id; ?>" disabled>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-12">
                                <div class="form-group">
                                    <label>Patient Name</label>
                                    <div class="search-container">
                                        <input type="text" class="form-control" id="patient-search" name="patient_name" placeholder="Search patient name" autocomplete="off" required>
                                        <div id="patient-list" class="search-results"></div>
                                    </div>
                                </div>
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
                                                <th>Admission Date</th>
                                                <th>Discharge Date</th>
                                                <th>Room Type</th>
                                            </tr>
                                        </thead>
                                        <tbody id="room-fee-container"></tbody>
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
                                                <th>Medicine Name</th>
                                                <th>Quantity</th>
                                                <th>Price</th>
                                                <th>Total</th>
                                            </tr>
                                        </thead>
                                        <tbody id="medication-fee-container"></tbody>
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
                                                <th>Test Name</th>
                                                <th>Price</th>
                                            </tr>
                                        </thead>
                                        <tbody id="lab-tests-container"></tbody>
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
                                                <th>Test Name</th>
                                                <th>Price</th>
                                            </tr>
                                        </thead>
                                        <tbody id="rad-tests-container"></tbody>
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
                                                <th>Procedure</th>
                                                <th>Price</th>
                                            </tr>
                                        </thead>
                                        <tbody id="or-fee-container"></tbody>
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
                                            <input type="number" step="0.01" min="0" class="form-control" id="supplies-fee" name="supplies_fee" placeholder="Enter amount">
                                        </div>
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="professional-fee">Professional Fee</label>
                                            <input type="number" step="0.01" min="0" class="form-control" id="professional-fee" name="professional_fee" placeholder="Enter amount">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="readers-fee">Reader's Fee</label>
                                            <input type="number" step="0.01" min="0" class="form-control" id="readers-fee" name="readers_fee" placeholder="Enter amount">
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Other Items -->
                            <div class="col-md-12">
                                <div class="other-items">
                                    <label>Other Charges</label>
                                    <div id="other-items-container">
                                        <div class="row align-items-center other-item mb-2" id="item-row-0">
                                            <div class="col-md-6">
                                                <input type="text" class="form-control" name="others[0][name]" placeholder="Item description">
                                            </div>
                                            <div class="col-md-5">
                                                <input type="number" step="0.01" class="form-control" name="others[0][cost]" placeholder="Amount">
                                            </div>
                                            <div class="col-md-1">
                                                <button type="button" class="btn btn-sm btn-danger remove-item" disabled>
                                                    <i class="fas fa-times"></i>
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>    
                                <button type="button" id="add-other-item" class="btn btn-sm btn-primary mt-2">
                                    <i class="fas fa-plus mr-1"></i> Add Item
                                </button>
                            </div>

                    <!-- Discounts Section -->
                    <div class="form-section discounts-section">
                        <h6 class="section-title">Discounts & Deductions</h6>
                        <div class="row">
                            <div class="col-md-4">
                                <div class="discount-option">
                                    <div class="custom-control custom-checkbox">
                                        <input type="checkbox" class="custom-control-input" id="vat-exempt-checkbox" name="vat_exempt_checkbox" value="on">
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
                                        ">₱0.00
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="discount-option">
                                    <div class="custom-control custom-checkbox">
                                        <input type="checkbox" class="custom-control-input" id="discount-checkbox" name="discount_checkbox" value="on">
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
                                        ">₱0.00
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="discount-option">
                                    <div class="custom-control custom-checkbox">
                                        <input type="checkbox" class="custom-control-input" id="pwd-discount-checkbox" name="pwd_discount_checkbox" value="on">
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
                                        ">₱0.00
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
                                    <option value="first">First Case Rate</option>
                                    <option value="second">Second Case Rate</option>
                                </select>
                            </div>
                            
                            <div id="first-case-rate-container" style="display: none;">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label>Professional Fee Discount</label>
                                        <input type="number" step="0.01" min="0" class="form-control" id="professional-fee-discount" name="philhealth_pf" placeholder="Enter amount" disabled>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label>Hospital Bill Discount</label>
                                        <input type="number" step="0.01" min="0" class="form-control" id="hospital-bill-discount" name="philhealth_hb" placeholder="Enter amount" disabled>
                                    </div>
                                </div>
                            </div>
                            
                            <div id="second-case-rate-container" style="display: none;">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label>Professional Fee Discount</label>
                                        <input type="number" step="0.01" min="0" class="form-control" id="professional-fee-discount-second" name="philhealth_pf" placeholder="Enter amount" disabled>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label>Hospital Bill Discount</label>
                                        <input type="number" step="0.01" min="0" class="form-control" id="hospital-bill-discount-second" name="philhealth_hb" placeholder="Enter amount" disabled>
                                    </div>
                                </div>
                            </div>
                        </div>

                    <!-- Summary Section -->
                    <div class="form-section summary-section">
                        <h6 class="section-title">Account Summary</h6>
                        <div class="summary-card">
                            <div class="summary-row">
                                <span class="summary-label">Total Charges:</span>
                                <span class="summary-value" id="total-charges">₱0.00</span>
                            </div>
                            <div class="summary-row">
                                <span class="summary-label">Total Discounts:</span>
                                <span class="summary-value" id="total-discounts">₱0.00</span>
                            </div>
                            <div class="summary-row">
                                <span class="summary-label">Total PhilHealth Discounts:</span>
                                <span class="summary-value" id="total-deduction">₱0.00</span>
                            </div>
                            <div class="summary-row total-due">
                                <span class="summary-label" style="font-weight: bold;">Total Amount Due:</span>
                                <span class="summary-value" id="total-due">₱0.00</span>
                            </div>
                        </div>
                    </div>
                    <!-- Submit Button -->
                    <div class="form-submit text-center mt-4">
                        <button type="submit" name="add-billing" class="btn btn-primary btn-lg">
                            <i class="fas fa-file-invoice-dollar mr-2"></i> Generate Statement
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
    const patientTypeSelect = document.getElementById('patient-type-select');
    const pwdDiscountCheckbox = document.getElementById('pwd-discount-checkbox');
    const discountCheckbox = document.getElementById('discount-checkbox');
    const vatExemptCheckbox = document.getElementById('vat-exempt-checkbox');
    const searchInput = document.getElementById('patient-search');
    const patientList = document.getElementById('patient-list');
    const labTestsContainer = document.getElementById('lab-tests-container');
    const radiologyFeeContainer = document.getElementById('rad-tests-container');
    const roomFeeContainer = document.getElementById('room-fee-container');
    const medicationFeeContainer = document.getElementById('medication-fee-container');
    const readersFeeInput = document.getElementById('readers-fee');
    const othersContainer = document.getElementById('other-items-container');
    const suppliesFeeInput = document.getElementById('supplies-fee');
    const firstPFInput = document.getElementById('professional-fee-discount');
    const firstHBInput = document.getElementById('hospital-bill-discount');
    const secondPFInput = document.getElementById('professional-fee-discount-second');
    const secondHBInput = document.getElementById('hospital-bill-discount-second');
    const orFeeContainer = document.getElementById('or-fee-container'); // Added OR fee container
    let labFeeTotal = 0;
    let radFeeTotal = 0;
    let roomFeeTotal = 0;
    let medicationFeeTotal = 0;
    let orFeeTotal = 0; 
    let othersFee = 0;
    let suppliesFee = 0;
    let discountAmount = 0;
    let totalDue = 0;

    function calculateTotalDue() {
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
        const nonDiscountedTotal = labFeeTotal + radFeeTotal + roomFeeTotal + 
                                medicationFeeTotal + orFeeTotal + othersFee + 
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
        let combinedFees = labFeeTotal + radFeeTotal + roomFeeTotal + medicationFeeTotal + orFeeTotal + othersFee + suppliesFee + professionalFee + readersFee;

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

    [firstPFInput, firstHBInput, secondPFInput, secondHBInput].forEach(input => {
        input.addEventListener('input', calculateTotalDue);
    });

    document.getElementById('professional-fee').addEventListener('input', calculateTotalDue);
    document.getElementById('readers-fee').addEventListener('input', calculateTotalDue);
    suppliesFeeInput.addEventListener('input', calculateTotalDue);
    pwdDiscountCheckbox.addEventListener('change', calculateTotalDue);
    vatExemptCheckbox.addEventListener('change', calculateTotalDue);
    discountCheckbox.addEventListener('change', calculateTotalDue);
    othersContainer.addEventListener('input', calculateTotalDue);

    // Handle search input for patients
    searchInput.addEventListener('keyup', function () {
        const query = searchInput.value.trim();
        if (query.length > 2) {
            const patientType = patientTypeSelect.value;
            let searchUrl = 'search-billing.php?query=' + query;

            if (patientType === 'newborn') {
                searchUrl = 'search-nb-billing.php?query=' + query;
            } else if (patientType === 'hemodialysis') {
                searchUrl = 'search-hemo-billing.php?query=' + query;
            }

            fetch(searchUrl)
                .then(response => response.text())
                .then(data => {
                    patientList.innerHTML = '';
                    if (data.trim()) {
                        patientList.innerHTML = data;
                        patientList.style.display = 'block';
                    } else {
                        patientList.innerHTML = '<div class="patient-option text-muted">No matching patients found</div>';
                        patientList.style.display = 'block';
                    }
                })
                .catch(error => console.error('Error:', error));
        } else {
            patientList.style.display = 'none';
        }
    });

    // Function to display relevant sections based on patient type
    patientTypeSelect.addEventListener('change', function () {
        const selectedType = patientTypeSelect.value;

        // Hide all sections first
        document.querySelectorAll('.patient-type-section').forEach(section => section.style.display = 'none');

        // Show selected section
        if (selectedType === 'inpatient') {
            document.getElementById('patient-section').style.display = 'block';
        } else if (selectedType === 'hemodialysis') {
            document.getElementById('patient-section').style.display = 'block';
        } else if (selectedType === 'newborn') {
            document.getElementById('patient-section').style.display = 'block';
        }
    });
    
    // Trigger change event to display the correct section on page load
    patientTypeSelect.dispatchEvent(new Event('change'));

    // Clear all containers when patient type changes
    patientTypeSelect.addEventListener('change', function() {
        // Clear all container contents
        labTestsContainer.innerHTML = '';
        radiologyFeeContainer.innerHTML = '';
        roomFeeContainer.innerHTML = '';
        medicationFeeContainer.innerHTML = '';
        orFeeContainer.innerHTML = '';
        
        // Reset all fee totals
        labFeeTotal = 0;
        radFeeTotal = 0;
        roomFeeTotal = 0;
        medicationFeeTotal = 0;
        orFeeTotal = 0;
        
        // Clear the patient search input
        searchInput.value = '';
        
        // Recalculate total
        calculateTotalDue();
    });

    // Clear containers when clicking into search input
    searchInput.addEventListener('focus', function() {
        // Clear all container contents
        labTestsContainer.innerHTML = '';
        radiologyFeeContainer.innerHTML = '';
        roomFeeContainer.innerHTML = '';
        medicationFeeContainer.innerHTML = '';
        orFeeContainer.innerHTML = '';
        
        // Reset all fee totals 
        labFeeTotal = 0;
        radFeeTotal = 0;
        roomFeeTotal = 0;
        medicationFeeTotal = 0;
        orFeeTotal = 0;
        
        // Recalculate total
        calculateTotalDue();
    });

    // Event listener for selecting a patient
    patientList.addEventListener('click', function (e) {
        const patientOption = e.target.closest('.patient-option');
        if (patientOption) {
            const patientName = patientOption.innerText;
            searchInput.value = patientName;
            patientList.style.display = 'none';

            // Fetch lab tests for selected patient
            fetch('get-lab-fee.php?patient_name=' + patientName)
                .then(response => response.json())
                .then(data => {
                    labTestsContainer.innerHTML = '';
                    labFeeTotal = 0;
                    if (data.length === 0) return;

                    data.forEach(test => {
                        labFeeTotal += parseFloat(test.price);
                        const row = document.createElement('tr');
                        row.innerHTML = `<td>${test.lab_test}</td><td>${test.price}</td>`;
                        labTestsContainer.appendChild(row);
                    });

                    const totalRow = document.createElement('tr');
                    totalRow.innerHTML = `<td colspan="2" class="text-center" style="font-weight: bold;">Lab Fee: ₱${labFeeTotal}</td>`;
                    labTestsContainer.appendChild(totalRow);

                    // Recalculate total due after lab fee is added
                    calculateTotalDue();
                })
                .catch(error => console.error('Error:', error));

            // Fetch room fee for selected patient
            fetch('get-room-fee.php?patient_name=' + patientName)
                .then(response => response.json())
                .then(data => {
                    roomFeeContainer.innerHTML = '';
                    if (!data || !data.room_type || !data.discharge_date) return;

                    const row = document.createElement('tr');
                    row.innerHTML = `<td>${data.admission_date}</td><td>${data.discharge_date}</td><td>${data.room_type}</td>`;
                    roomFeeTotal = data.total_room_fee;
                    roomFeeContainer.appendChild(row);

                    const totalRow = document.createElement('tr');
                    totalRow.innerHTML = `<td colspan="3" class="text-center" style="font-weight: bold;">Room Fee: ₱${roomFeeTotal}</td>`;
                    roomFeeContainer.appendChild(totalRow);

                    // Recalculate total due after room fee is added
                    calculateTotalDue();
                })
                .catch(error => console.error('Error:', error));

            // Fetch medication fee for selected patient
            fetch('get-med-fee.php?patient_name=' + patientName)
                .then(response => response.json())
                .then(data => {
                    medicationFeeContainer.innerHTML = '';
                    medicationFeeTotal = 0;
                    if (data.medications.length === 0) return;

                    data.medications.forEach(med => {
                        medicationFeeTotal += parseFloat(med.total_price);
                        const row = document.createElement('tr');
                        row.innerHTML = `
                            <td>${med.name} (${med.brand})</td>
                            <td>${med.total_quantity}</td>
                            <td>${med.price}</td>
                            <td>₱${med.total_price}</td>
                        `;
                        medicationFeeContainer.appendChild(row);
                    });

                    const totalRow = document.createElement('tr');
                    totalRow.innerHTML = `<td colspan="4" class="text-center" style="font-weight: bold;">Medication Fee: ₱${medicationFeeTotal.toFixed(2)}</td>`;
                    medicationFeeContainer.appendChild(totalRow);

                    // Recalculate total due after medication fee is added
                    calculateTotalDue();
                })
                .catch(error => console.error('Error:', error));

            // Fetch radiology fee for selected patient
            fetch('get-rad-fee.php?patient_name=' + patientName)
                .then(response => response.json())
                .then(data => {
                    radiologyFeeContainer.innerHTML = '';
                    radFeeTotal = 0;  // Reset radiology fee before adding new values
                    if (data.length === 0) return;

                    // Loop through fetched data and add to the total
                    data.forEach(rad => {
                        radFeeTotal += parseFloat(rad.price);
                        const row = document.createElement('tr');
                        row.innerHTML = `<td>${rad.exam_type}</td><td>${rad.price}</td>`;
                        radiologyFeeContainer.appendChild(row);
                    });

                    // Add a row displaying the total radiology fee
                    const totalRow = document.createElement('tr');
                    totalRow.innerHTML = `<td colspan="2" class="text-center" style="font-weight: bold;">Radiology Fee: ₱${radFeeTotal}</td>`;
                    radiologyFeeContainer.appendChild(totalRow);

                    // Recalculate total due after radiology fee is added
                    calculateTotalDue();
                })
                .catch(error => console.error('Error:', error));

            // Fetch operation room fee for selected patient
            fetch('get-or-fee.php?patient_name=' + patientName)
                .then(response => response.json())
                .then(data => {
                    orFeeContainer.innerHTML = '';  // Clear existing OR fees
                    orFeeTotal = 0;
                    if (!data || data.length === 0) return;  // No data to display

                    // Loop through the OR fee data and add rows
                    data.forEach(orFee => {
                        orFeeTotal += parseFloat(orFee.price);  // Sum up the OR fee prices
                        const row = document.createElement('tr');
                        row.innerHTML = `<td>${orFee.operation_type}</td><td>₱${orFee.price}</td>`;  
                        orFeeContainer.appendChild(row);
                    });

                    // Add a row displaying the total OR fee
                    const totalRow = document.createElement('tr');
                    totalRow.innerHTML = `<td colspan="2" class="text-center" style="font-weight: bold;">Operation Room Fee: ₱${orFeeTotal.toFixed(2)}</td>`;
                    orFeeContainer.appendChild(totalRow);

                    // Recalculate total due after OR fee is added
                    calculateTotalDue();
                })
                .catch(error => console.error('Error:', error));
        }
    });

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

    // Single event delegation for remove buttons (should be outside the add button click handler)
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
        const isChecked = pwdDiscountCheckbox.checked;

        // Enable/disable VAT Exempt and Senior Discount based on PWD checkbox
        vatExemptCheckbox.disabled = isChecked;
        discountCheckbox.disabled = isChecked;

        document.querySelector('form').addEventListener('submit', function (e) {
        // Create hidden inputs for total due, discount amount, and fees
        const totalDueInput = document.createElement('input');
        totalDueInput.type = 'hidden';
        totalDueInput.name = 'total_due';
        totalDueInput.value = totalDue.toFixed(2);
        this.appendChild(totalDueInput);

        const discountAmountInput = document.createElement('input');
        discountAmountInput.type = 'hidden';
        discountAmountInput.name = 'discount_amount';
        discountAmountInput.value = discountAmount.toFixed(2);
        this.appendChild(discountAmountInput);

        // Create hidden inputs for lab, room, medication, professional, and readers fees
        const labFeeInput = document.createElement('input');
        labFeeInput.type = 'hidden';
        labFeeInput.name = 'lab_fee';
        labFeeInput.value = labFeeTotal;
        this.appendChild(labFeeInput);

        const radFeeInput = document.createElement('input');
        radFeeInput.type = 'hidden';
        radFeeInput.name = 'rad_fee';
        radFeeInput.value = radFeeTotal;
        this.appendChild(radFeeInput);

        const roomFeeInput = document.createElement('input');
        roomFeeInput.type = 'hidden';
        roomFeeInput.name = 'room_fee';
        roomFeeInput.value = roomFeeTotal;
        this.appendChild(roomFeeInput);

        const medicationFeeInput = document.createElement('input');
        medicationFeeInput.type = 'hidden';
        medicationFeeInput.name = 'medication_fee';
        medicationFeeInput.value = medicationFeeTotal;
        this.appendChild(medicationFeeInput);

        const orFeeInput = document.createElement('input');
        orFeeInput.type = 'hidden';
        orFeeInput.name = 'operating_room_fee';
        orFeeInput.value = orFeeTotal;
        this.appendChild(orFeeInput);

        const professionalFeeInput = document.createElement('input');
        professionalFeeInput.type = 'hidden';
        professionalFeeInput.name = 'professional_fee';
        professionalFeeInput.value = professionalFee;
        this.appendChild(professionalFeeInput);

        const readersFeeInput = document.createElement('input');
        readersFeeInput.type = 'hidden';
        readersFeeInput.name = 'readers_fee';
        readersFeeInput.value = readersFee;
        this.appendChild(readersFeeInput);

        // Add others_fee to the form
        const othersFeeInput = document.createElement('input');
        othersFeeInput.type = 'hidden';
        othersFeeInput.name = 'others_fee';
        othersFeeInput.value = othersFee.toFixed(2);  // Ensure it's passed as a number with two decimal places
        this.appendChild(othersFeeInput);

        // Add PhilHealth case rate inputs
        const firstPFHiddenInput = document.createElement('input');
        firstPFHiddenInput.type = 'hidden';
        firstPFHiddenInput.name = 'philhealth_pf_first';
        firstPFHiddenInput.value = (parseFloat(firstPFInput.value) || 0).toFixed(2);
        this.appendChild(firstPFHiddenInput);

        const firstHBHiddenInput = document.createElement('input');
        firstHBHiddenInput.type = 'hidden';
        firstHBHiddenInput.name = 'philhealth_hb_first';
        firstHBHiddenInput.value = (parseFloat(firstHBInput.value) || 0).toFixed(2);
        this.appendChild(firstHBHiddenInput);

        const secondPFHiddenInput = document.createElement('input');
        secondPFHiddenInput.type = 'hidden';
        secondPFHiddenInput.name = 'philhealth_pf_second';
        secondPFHiddenInput.value = (parseFloat(secondPFInput.value) || 0).toFixed(2);
        this.appendChild(secondPFHiddenInput);

        const secondHBHiddenInput = document.createElement('input');
        secondHBHiddenInput.type = 'hidden';
        secondHBHiddenInput.name = 'philhealth_hb_second';
        secondHBHiddenInput.value = (parseFloat(secondHBInput.value) || 0).toFixed(2);
        this.appendChild(secondHBHiddenInput);

        const caseRateSelect = document.getElementById('case-rate-select');
        if (caseRateSelect.value === 'first') {
            const firstCaseInput = document.createElement('input');
            firstCaseInput.type = 'hidden';
            firstCaseInput.name = 'first_case';
            firstCaseInput.value = document.querySelector('input[name="first_case"]').value;
            this.appendChild(firstCaseInput);
        } else if (caseRateSelect.value === 'second') {
            const secondCaseInput = document.createElement('input');
            secondCaseInput.type = 'hidden';
            secondCaseInput.name = 'second_case';
            secondCaseInput.value = document.querySelector('input[name="second_case"]').value;
            this.appendChild(secondCaseInput);
        }
    });

});
</script>
<script>
document.addEventListener("DOMContentLoaded", function () {
    const caseRateSelect = document.getElementById("case-rate-select");
    const firstCaseRateContainer = document.getElementById("first-case-rate-container");
    const secondCaseRateContainer = document.getElementById("second-case-rate-container");
    const firstCaseInputs = firstCaseRateContainer.querySelectorAll("input");
    const secondCaseInputs = secondCaseRateContainer.querySelectorAll("input");

    caseRateSelect.addEventListener("change", function () {
        if (this.value === "first") {
            firstCaseRateContainer.style.display = "flex";  
            secondCaseRateContainer.style.display = "none"; 

            firstCaseInputs.forEach(input => input.removeAttribute("disabled"));
            secondCaseInputs.forEach(input => input.setAttribute("disabled", "true"));
        } else if (this.value === "second") {
            secondCaseRateContainer.style.display = "flex";  
            firstCaseRateContainer.style.display = "none";  

            secondCaseInputs.forEach(input => input.removeAttribute("disabled"));
            firstCaseInputs.forEach(input => input.setAttribute("disabled", "true"));
        } else {
            firstCaseRateContainer.style.display = "none";
            secondCaseRateContainer.style.display = "none";

            firstCaseInputs.forEach(input => input.setAttribute("disabled", "true"));
            secondCaseInputs.forEach(input => input.setAttribute("disabled", "true"));
        }
    });
});

document.getElementById('case-rate-select').addEventListener('change', function() {
    const selectedValue = this.value;
    const firstContainer = document.getElementById('first-case-rate-container');
    const secondContainer = document.getElementById('second-case-rate-container');
    
    // Remove any existing RVS/ICD input
    const existingInput = document.querySelector('.rvs-icd-input');
    if (existingInput) {
        existingInput.remove();
    }
   
    // Create new input field
    if (selectedValue) {
        const inputDiv = document.createElement('div');
        inputDiv.className = 'col-sm-12 rvs-icd-input';
        inputDiv.innerHTML = `
            <label>Enter RVS or ICD Code</label>
            <input type="text" class="form-control" name="${selectedValue}_case" placeholder="Enter RVS or ICD Code">
        `;
       
        // Insert after the select dropdown
        this.closest('.form-group').appendChild(inputDiv);
    }
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
/* Enhanced Fees Container Box */
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

.btn-primary {
    background: #12369e;
    border: none;
}

.btn-primary:hover {
    background: #05007E;
    transform: translateY(-1px);
    box-shadow: 0 4px 8px rgba(18, 54, 158, 0.3);
}

/* Enhanced Form Controls */
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

/* Enhanced Patient List */
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

/* Enhanced Checkbox Style */
input[type="checkbox"] {
    width: 18px;
    height: 18px;
    border-radius: 4px;
    border: 2px solid #12369e;
    cursor: pointer;
}



/* Enhanced Value Styles */
#total-due, #discount-amount, #pwd-discount, #vat-exempt-discount {
    font-size: 26px;
    font-weight: 700;
    color:rgb(49, 49, 49);
}

/* Optional: Add a custom scrollbar if the table overflows */
.table::-webkit-scrollbar {
    width: 10px;
}

.table::-webkit-scrollbar-thumb {
    background-color: #12369

}
/* Enhanced Section Styles */
.patient-type-section {
    padding: 20px; /* Increased padding for better spacing */
    background-color:rgb(241, 241, 241); /* Lighter background for better contrast */
    border-radius: 12px; /* Slightly more rounded corners */
    box-shadow: 0 6px 12px rgba(0, 0, 0, 0.15); /* Deeper shadow for better depth */
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

/* Enhanced Button Container */
.button-container {
    display: flex;
    justify-content: center;
    align-items: center;
    margin-top: 25px;
    padding: 10px 0;
}

/* Ensure buttons are aligned side by side */
.button-container {
    display: flex;
    justify-content: space-between; /* Align buttons side by side */
    margin-top: 1px;
}

/* Styling for the Add and Remove buttons */
#add-other-item, #remove-other-item {
    width: 100%;  /* Ensure buttons take the full width of their respective columns */
    padding: 10px 20px;
    font-size: 14px;
    border-radius: 6px;
}

/* Styling for Add More Items button */
#add-other-item {
    background-color: #12369e;
    color: white;
}

#add-other-item:hover {
    background-color: #05007E;
}

/* Styling for Remove Item button */
#remove-other-item {
    background-color:rgb(216, 35, 53);
    color: white;
}

#remove-other-item:hover {
    background-color:rgb(201, 18, 36);
}

/* Adjust for mobile view */
@media (max-width: 768px) {
    .button-container {
        flex-direction: column;  /* Stack buttons vertically on small screens */
        align-items: center;
    }

    #add-other-item, #remove-other-item {
        width: 80%; /* Slightly smaller width on mobile */
    }
}

/* Enhanced Form Group Spacing */
.form-group {
    margin-bottom: 20px;
}

/* Responsive Improvements */

/* Responsive Adjustments */
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
/* Card Styling */
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

/* Form Sections */
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

/* Form Controls */
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

/* Search Container */
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

/* Charge Groups */
.charge-group {
    margin-bottom: 25px;
}

.charge-group h5 {
    font-size: 1rem;
    font-weight: 600;
    color:rgb(90, 90, 90);
    margin-bottom: 15px;
    padding-bottom: 8px;
}

.charge-group .table {
    background-color: #f9f9f9;
    border-radius: 6px;
    overflow: hidden;
}

/* Tables */
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

/* Additional Fees */
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

/* Other Items */
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

/* Discount Options */
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

/* PhilHealth Section */
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

/* Summary Section */
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