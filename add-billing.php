<?php
session_start();
if (empty($_SESSION['name'])) {
    header('location:index.php');
    exit();
}

include('header.php');
include('includes/connection.php');

// Get the latest ID from both tables
$fetch_inpatient = mysqli_query($connection, "SELECT MAX(id) AS id FROM tbl_billing_inpatient");
$fetch_hemodialysis = mysqli_query($connection, "SELECT MAX(id) AS id FROM tbl_billing_hemodialysis");

$row_inpatient = mysqli_fetch_row($fetch_inpatient);
$row_hemodialysis = mysqli_fetch_row($fetch_hemodialysis);

// Compare and get the highest ID between the two tables
$highest_id = max($row_inpatient[0], $row_hemodialysis[0]);

// Generate the next billing ID
$bl_id = ($highest_id == 0) ? 1 : $highest_id + 1;

// Add input validation function
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

    // Fetch patient details based on patient type
    if ($patient_type == 'inpatient') {
        $patient_name_escaped = mysqli_real_escape_string($connection, $patient_name);
        $patient_result = mysqli_query($connection, "
            SELECT ipr.patient_id, ipr.patient_name, ipr.dob, ipr.gender, ipr.admission_date, ipr.discharge_date, ipr.diagnosis, p.address
            FROM tbl_inpatient_record AS ipr
            JOIN tbl_patient AS p
            ON ipr.patient_id = p.patient_id
            WHERE ipr.patient_name = '$patient_name_escaped'");
    } else if ($patient_type == 'hemodialysis') {
        $patient_name_escaped = mysqli_real_escape_string($connection, $patient_name);
        $patient_result = mysqli_query($connection, "
            SELECT h.patient_id, h.patient_name, h.dob, h.gender, p.address
            FROM tbl_hemodialysis AS h
            JOIN tbl_patient AS p
            ON h.patient_id = p.patient_id
            WHERE h.patient_name = '$patient_name_escaped'");
    }

    $patient = mysqli_fetch_array($patient_result, MYSQLI_ASSOC);

    if ($patient) {
        // Retrieve patient details and handle NULL values
        $patient_id = !empty($patient['patient_id']) ? $patient['patient_id'] : "NULL";
        $dob = !empty($patient['dob']) ? "'" . $patient['dob'] . "'" : "NULL";
        $gender = !empty($patient['gender']) ? "'" . $patient['gender'] . "'" : "NULL";
        $admission_date = !empty($patient['admission_date']) ? "'" . $patient['admission_date'] . "'" : "NULL";
        $discharge_date = !empty($patient['discharge_date']) ? "'" . $patient['discharge_date'] . "'" : "NULL";
        $diagnosis = !empty($patient['diagnosis']) ? "'" . $patient['diagnosis'] . "'" : "NULL";
        $address = !empty($patient['address']) ? "'" . $patient['address'] . "'" : "NULL";

        // PhilHealth fields
        $first_case = isset($_POST['first_case']) ? mysqli_real_escape_string($connection, $_POST['first_case']) : '';
        $second_case = isset($_POST['second_case']) ? mysqli_real_escape_string($connection, $_POST['second_case']) : '';
        $philhealth_pf = isset($_POST['philhealth_pf']) ? mysqli_real_escape_string($connection, $_POST['philhealth_pf']) : '';
        $philhealth_hb = isset($_POST['philhealth_hb']) ? mysqli_real_escape_string($connection, $_POST['philhealth_hb']) : '';

        // Default values for fees
        $room_fee = isset($_POST['room_fee']) && $_POST['room_fee'] !== '' ? $_POST['room_fee'] : 0;
        $lab_fee = isset($_POST['lab_fee']) && $_POST['lab_fee'] !== '' ? $_POST['lab_fee'] : 0;
        $rad_fee = isset($_POST['rad_fee']) && $_POST['rad_fee'] !== '' ? $_POST['rad_fee'] : 0;
        $medication_fee = 0;
        $operating_room_fee = isset($_POST['operating_room_fee']) && $_POST['operating_room_fee'] !== '' ? $_POST['operating_room_fee'] : 0;
        $supplies_fee = isset($_POST['supplies_fee']) && $_POST['supplies_fee'] !== '' ? $_POST['supplies_fee'] : 0;
        $professional_fee = isset($_POST['professional_fee']) && $_POST['professional_fee'] !== '' ? $_POST['professional_fee'] : 0;
        $readers_fee = isset($_POST['readers_fee']) && $_POST['readers_fee'] !== '' ? $_POST['readers_fee'] : 0;
        $others_fee = isset($_POST['others_fee']) && $_POST['others_fee'] !== '' ? $_POST['others_fee'] : 0;
        
        // Calculate medication fee
        $medication_query = $connection->prepare("SELECT SUM(total_price) AS medication_fee FROM tbl_treatment WHERE patient_name = ? AND deleted = 0");
        $medication_query->bind_param("s", $patient_name);
        $medication_query->execute();
        $medication_result = $medication_query->get_result();
        if ($medication = $medication_result->fetch_assoc()) {
            $medication_fee = $medication['medication_fee'] ?? 0;
        }

        // Handle others fees
        if (isset($_POST['others']) && !empty($_POST['others'])) {
            foreach ($_POST['others'] as $item) {
                $item_name = mysqli_real_escape_string($connection, $item['name']);
                $item_cost = mysqli_real_escape_string($connection, $item['cost']);

                $insert_query = "
                    INSERT INTO tbl_billing_others (billing_id, item_name, item_cost, date_time)
                    VALUES ('$billing_id', '$item_name', '$item_cost', NOW())
                ";
                mysqli_query($connection, $insert_query);
            }

            $others_fee_query = "
            SELECT SUM(item_cost) AS others_fee
            FROM tbl_billing_others
            WHERE billing_id = '$billing_id' AND deleted = 0
            ";

            $others_fee_result = mysqli_query($connection, $others_fee_query);
            $others_fee_row = mysqli_fetch_assoc($others_fee_result);
            $others_fee = $others_fee_row['others_fee'] ?? 0;
        }

    
        // Get checkbox states
        $vat_exempt_checkbox = isset($_POST['vat_exempt_checkbox']) ? $_POST['vat_exempt_checkbox'] : 'off';
        $discount_checkbox = isset($_POST['discount_checkbox']) ? $_POST['discount_checkbox'] : 'off';
        $pwd_discount_checkbox = isset($_POST['pwd_discount_checkbox']) ? $_POST['pwd_discount_checkbox'] : 'off';

        // First apply VAT Exempt if checked
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

        // Then apply Senior/PWD discount if checked
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

        // Calculate combined fees before applying any discounts
        $combined_fees = $room_fee + $lab_fee + $medication_fee + $operating_room_fee + $supplies_fee + $others_fee + $professional_fee + $readers_fee;

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
        
        // Set remaining balance equal to total due for new billing
        $remaining_balance = $total_due;

        // Ensure values are not negative
        $total_due = max(0, $total_due);
        $remaining_balance = max(0, $remaining_balance);

        // Insert billing details
        if ($patient_type == 'inpatient') {
            $query = "INSERT INTO tbl_billing_inpatient
            (billing_id, patient_id, patient_name, dob, gender, admission_date, discharge_date, diagnosis, address, 
            lab_fee, room_fee, medication_fee, operating_room_fee, supplies_fee, total_due, non_discounted_total, 
            discount_amount, professional_fee, pwd_discount_amount, readers_fee, others_fee, rad_fee, 
            vat_exempt_discount_amount, first_case, second_case, philhealth_pf, philhealth_hb,
            room_discount, lab_discount, rad_discount, med_discount, or_discount, supplies_discount, 
            other_discount, pf_discount, readers_discount, remaining_balance) 
            VALUES ('$billing_id', '$patient_id', '$patient_name', $dob, $gender, $admission_date, $discharge_date, 
            $diagnosis, $address, '$lab_fee', '$room_fee', '$medication_fee', '$operating_room_fee', 
            '$supplies_fee', '$total_due', '$non_discounted_total', '$total_discount', '$professional_fee', 
            '$pwd_discount_amount', '$readers_fee', '$others_fee', '$rad_fee', '$vat_exempt_discount_amount', 
            '$first_case', '$second_case', '$philhealth_pf', '$philhealth_hb', '$room_discount', '$lab_discount', 
            '$rad_discount', '$med_discount', '$or_discount', '$supplies_discount', '$other_discount', 
            '$pf_discount', '$readers_discount', '$remaining_balance')";

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
        } elseif ($patient_type == 'hemodialysis') {
            $query = "INSERT INTO tbl_billing_hemodialysis
            (billing_id, patient_id, patient_name, dob, gender, admission_date, discharge_date, diagnosis, address,
            lab_fee, room_fee, medication_fee, operating_room_fee, supplies_fee, total_due, non_discounted_total,
            discount_amount, professional_fee, pwd_discount_amount, readers_fee, others_fee, rad_fee,
            vat_exempt_discount_amount, first_case, second_case, philhealth_pf, philhealth_hb,
            room_discount, lab_discount, rad_discount, med_discount, or_discount, supplies_discount,
            other_discount, pf_discount, readers_discount, remaining_balance)
            VALUES ('$billing_id', '$patient_id', '$patient_name', $dob, $gender, $admission_date, $discharge_date, 
            $diagnosis, $address, '$lab_fee', '$room_fee', '$medication_fee', '$operating_room_fee', 
            '$supplies_fee', '$total_due', '$non_discounted_total', '$total_discount', '$professional_fee', 
            '$pwd_discount_amount', '$readers_fee', '$others_fee', '$rad_fee', '$vat_exempt_discount_amount', 
            '$first_case', '$second_case', '$philhealth_pf', '$philhealth_hb', '$room_discount', '$lab_discount', 
            '$rad_discount', '$med_discount', '$or_discount', '$supplies_discount', '$other_discount', 
            '$pf_discount', '$readers_discount', '$remaining_balance')";

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
            $msg = "Error: Patient type not selected.";
        }
    }
}
?>
<div class="page-wrapper">
    <div class="content">
        <div class="row">
            <div class="col-sm-4">
                <h4 class="page-title">Add Account</h4>
            </div>
            <div class="col-sm-8 text-right mb-3">
                <a href="billing.php" class="btn btn-primary float-right"><i class="fa fa-arrow-left"></i> Back</a>
            </div>
        </div>
        <div class="col-lg-10 offset-lg-1">
            <form method="post" action="">
                <!-- Patient Type Dropdown -->
                <div class="form-group row">
                    <div class="col-sm-12">
                        <label for="patient-type-select">Patient Type</label>
                        <select class="form-control" id="patient-type-select" name="patient_type" required>
                            <option value="">Select Patient Type</option>
                            <option value="inpatient">Inpatient</option>
                            <option value="hemodialysis">Hemodialysis</option>
                            <option value="newborn">Newborn</option>
                        </select>
                    </div>
                </div>
                <!-- Billing ID -->
                <div class="form-group row">
                    <div class="col-sm-6">
                        <label>Billing ID</label>
                        <input class="form-control" type="text" value="<?php echo 'BL-' . $bl_id; ?>" disabled>
                    </div>
                    <div class="col-md-6">
                        <label for="patient-search">Patient Name</label>
                        <input type="text" class="form-control" id="patient-search" name="patient_name" placeholder="Search for patient" autocomplete="off" required>
                        <div id="patient-list" class="patient-list"></div>
                    </div>
                </div>
                <!-- Inpatient Fields Section (Initially Hidden) -->
                <div id="patient-section" class="patient-type-section" style="display:none;">
                    <!-- Room Charges Section -->
                    <h4>Room Charges</h4> 
                    <table class="table table-bordered">
                        <thead style="background-color:rgba(204, 204, 204, 0.1);">
                            <tr>
                                <th>Admission Date</th>
                                <th>Discharge Date</th>
                                <th>Room Type</th>
                            </tr>
                        </thead>
                        <tbody id="room-fee-container"></tbody>
                    </table>

                    <!-- Medicine Charges Section -->
                    <h4>Medicine Charges</h4>    
                    <table class="table table-bordered">
                        <thead style="background-color:rgba(204, 204, 204, 0.1);">
                            <tr>
                                <th>Medicine Name (Brand)</th>
                                <th>Total Quantity</th>
                                <th>Price</th>
                                <th>Total Price</th>
                            </tr>
                        </thead>
                        <tbody id="medication-fee-container"></tbody>
                    </table>

                    <!-- Lab Charges Section -->
                    <h4>Lab Charges</h4> 
                    <table class="table table-bordered">
                        <thead style="background-color:rgba(204, 204, 204, 0.1);">
                            <tr>
                                <th>Lab Test</th>
                                <th>Price</th>
                            </tr>
                        </thead>
                        <tbody id="lab-tests-container"></tbody>
                    </table>

                    <!-- Radiology Charges Section -->
                    <h4>Radiology Charges</h4>
                    <table class="table table-bordered">
                        <thead style="background-color:rgba(204, 204, 204, 0.1);">
                            <tr>
                                <th>Radiology Test</th>
                                <th>Price</th>
                            </tr>
                        </thead>
                        <tbody id="rad-tests-container"></tbody>
                    </table>

                    <!-- Operation Room Fee Section -->
                    <h4>Operation Room Fee</h4>
                    <table class="table table-bordered">
                        <thead style="background-color:rgba(204, 204, 204, 0.1);">
                            <tr>
                                <th>Operation Type</th>
                                <th>Price</th>
                            </tr>
                        </thead>
                        <tbody id="or-fee-container">
                            <!-- OR Fee rows will be populated here by JavaScript -->
                        </tbody>
                    </table>
                </div>

                <div class="fees-container">
                    <h4 class="fees-title">Additional Fees</h4>
                        <!-- Supplies Fee Section -->
                        <div class="form-group row">
                            <div class="col-sm-12">
                                <label for="supplies-fee" class="form-label">Supplies</label>
                                <input type="number" class="form-control" id="supplies-fee" name="supplies_fee" placeholder="Enter supplies fee">
                            </div>
                        </div>

                        <!-- Others Fee Section -->
                        <div class="form-group row">
                        <div class="col-sm-12">
                            <label>Others:</label>
                            <div id="other-items-container">
                                <!-- Default input fields (initial row) -->
                                <div class="row align-items-center other-item mb-2">
                                    <div class="col-sm-6">
                                        <input type="text" class="form-control mb-2" name="others[0][name]" placeholder="Item Name">
                                    </div>
                                    <div class="col-sm-6">
                                        <input type="number" class="form-control" name="others[0][cost]" placeholder="Item Cost">
                                    </div>
                                </div>
                            </div>

                            <!-- Button to Add More Items -->
                            <div class="text-center mt-3">
                                <button type="button" id="add-other-item" class="btn btn-primary btn-info">Add More Items</button>
                            </div>
                        </div>
                    </div>


                    <!-- Professional Fee and Readers Fee -->
                    <div class="form-group row">
                        <div class="col-sm-6">
                            <label for="professional-fee">Professional's Fee</label>
                            <input type="number" class="form-control" id="professional-fee" name="professional_fee" placeholder="Enter Professional Fee">
                        </div>
                        <div class="col-sm-6">
                            <label for="readers-fee">Reader's Fee</label>
                            <input type="number" class="form-control" id="readers-fee" name="readers_fee" placeholder="Enter Readers Fee">
                        </div>
                    </div>
                </div>

                <div class="checkbox-container">
                <h4 class="discounts-title">Discounts</h4> <!-- Added title -->
                    <!-- Discounts Section -->
                    <div class="form-group row">
                        <div class="col-sm-4">
                            <div class="discount-checkbox-wrapper">
                                <label class="checkbox-label" for="vat-exempt-checkbox">
                                    <span>VAT Exempt (12%)</span>
                                    <input type="checkbox" id="vat-exempt-checkbox" name="vat_exempt_checkbox" value="on">
                                    <span class="checkmark"></span>
                                </label>
                            </div>
                        </div>
                        <div class="col-sm-4">
                            <div class="discount-checkbox-wrapper">
                                <label class="checkbox-label" for="discount-checkbox">
                                    <span>Senior Citizen Discount (20%)</span>
                                    <input type="checkbox" id="discount-checkbox" name="discount_checkbox" value="on">
                                    <span class="checkmark"></span>
                                </label>
                            </div>
                        </div>
                        <div class="col-sm-4">
                            <div class="discount-checkbox-wrapper">
                                <label class="checkbox-label" for="pwd-discount-checkbox">
                                    <span>PWD Discount (20%)</span>
                                    <input type="checkbox" id="pwd-discount-checkbox" name="pwd_discount_checkbox" value="on">
                                    <span class="checkmark"></span>
                                </label>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- PhilHealth Case Rate Section -->
                <div class="philhealth-box">
                    <h4>PhilHealth Case Rate</h4>
                    <div class="form-group row">
                        <div class="col-sm-12">
                            <select class="form-control" id="case-rate-select" name="case_rate">
                                <option value="">Select Case Rate</option>
                                <option value="first">First Case Rate</option>
                                <option value="second">Second Case Rate</option>
                            </select>
                        </div>
                    </div>
                    <!-- First Case Rate Fields (Hidden) -->
                    <div id="first-case-rate-container" class="form-group row" style="display: none;">
                        <div class="col-sm-6">
                            <label for="professional-fee-discount">Professional Fee Discount</label>
                            <input type="number" class="form-control" id="professional-fee-discount" name="philhealth_pf" placeholder="Enter Amount" disabled>
                        </div>
                        <div class="col-sm-6">
                            <label for="hospital-bill-discount">Hospital Bill Discount</label>
                            <input type="number" class="form-control" id="hospital-bill-discount" name="philhealth_hb" placeholder="Enter Amount" disabled>
                        </div>
                    </div>
                    <!-- Second Case Rate Fields (Hidden) -->
                    <div id="second-case-rate-container" class="form-group row" style="display: none;">
                        <div class="col-sm-6">
                            <label for="professional-fee-discount-second">Professional Fee Discount</label>
                            <input type="number" class="form-control" id="professional-fee-discount-second" name="philhealth_pf" placeholder="Enter Amount" disabled>
                        </div>
                        <div class="col-sm-6">
                            <label for="hospital-bill-discount-second">Hospital Bill Discount</label>
                            <input type="number" class="form-control" id="hospital-bill-discount-second" name="philhealth_hb" placeholder="Enter Amount" disabled>
                        </div>
                    </div>
                </div>

                <div class="form-group row">
                    <div class="col-sm-6 mb-3">
                        <div class="vat-exempt-discount-box">
                            <h5>VAT Exempt (12%):</h5>
                            <p id="vat-exempt-discount">₱0.00</p>
                        </div>
                    </div>
                    <div class="col-sm-6 mb-3">
                        <div class="discount-amount-box">
                            <h5>Senior Citizen Discount (20%):</h5>
                            <p id="discount-amount">₱0.00</p>
                        </div>
                    </div>
                    <div class="col-sm-6 mb-3">
                        <div class="pwd-discount-box">
                            <h5>PWD Discount (20%):</h5>
                            <p id="pwd-discount">₱0.00</p>
                        </div>
                    </div>
                    <div class="col-sm-6 mb-3">
                        <div class="total-due-box">
                            <h5>Total Due: (Amount to pay)</h5>
                            <p id="total-due">₱0.00</p>
                        </div>
                    </div>
                </div>
                <div class="mt-3 text-center">
                    <button class="btn btn-primary submit-btn" name="add-billing">Add Account</button>
                </div>
            </form>
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

        // Get PhilHealth deductions
        const firstPF = parseFloat(firstPFInput.value) || 0;
        const firstHB = parseFloat(firstHBInput.value) || 0;
        const secondPF = parseFloat(secondPFInput.value) || 0;
        const secondHB = parseFloat(secondHBInput.value) || 0;

        // Calculate total PhilHealth deductions
        const philhealthDeduction = firstPF + firstHB + secondPF + secondHB;

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

    document.getElementById('add-other-item').addEventListener('click', function () {
    const container = document.getElementById('other-items-container');
    const itemCount = Math.floor(container.children.length / 2); // Count pairs of inputs

    const newItemHTML = `
        <div class="row align-items-center other-item mb-2 position-relative" id="item-row-${itemCount}">
            <div class="col-sm-6">
                <input type="text" class="form-control" name="others[${itemCount}][name]" placeholder="Item Name" required>
            </div>
            <div class="col-sm-6 position-relative">
                <input type="number" class="form-control" name="others[${itemCount}][cost]" placeholder="Item Cost" required>
                <button type="button" class="remove-item btn btn-danger btn-sm position-absolute" 
                    style="right: -35px; top: 50%; transform: translateY(-50%);" 
                    data-id="item-row-${itemCount}">&times;
                </button>
            </div>
        </div>
    `;


    container.insertAdjacentHTML('beforeend', newItemHTML);
    });

    // Event delegation for "X" button to remove an item
    document.getElementById('other-items-container').addEventListener('click', function (event) {
        if (event.target.classList.contains('remove-item')) {
            const itemId = event.target.getAttribute('data-id');
            document.getElementById(itemId)?.remove();
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

/* Hover Effect */
.fees-container:hover {
    box-shadow: 0 8px 16px rgba(0, 0, 0, 0.2);
    transform: translateY(-4px);
}

.fees-container h4 {
    font-size: 18px;
    color: #343a40;
    margin-bottom: 15px;
    padding-bottom: 10px;
    border-bottom: 1px solid #e0e4e8;
}

.fees-container select,
.fees-container input {
    border: 2px solid #e0e4e8;
    transition: all 0.3s ease;
}

.fees-container select:focus,
.fees-container input:focus {
    border-color: #12369e;
    box-shadow: 0 0 0 3px rgba(18,54,158,0.1);
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

/* Enhanced Box Styles */
.total-due-box, .discount-amount-box, .pwd-discount-box, .vat-exempt-discount-box {
    padding: 20px;
    background-color: rgb(241, 241, 241);
    border-radius: 12px;
    box-shadow: 0 6px 12px rgba(0, 0, 0, 0.15);
    margin-bottom: 25px;
    border: 1px solid #e0e4e8;
    transition: box-shadow 0.3s ease, transform 0.3s ease;
    text-align: center;
}

.total-due-box:hover, .discount-amount-box:hover, 
.pwd-discount-box:hover, .vat-exempt-discount-box:hover {
    box-shadow: 0 8px 16px rgba(0, 0, 0, 0.2);
    transform: translateY(-4px);
}

/* Enhanced Title Styles */
.total-due-box h5, .discount-amount-box h5,
.pwd-discount-box h5, .vat-exempt-discount-box h5 {
    font-size: 16px;
    color: #343a40;
    margin-bottom: 12px;
    font-weight: 600;
}

/* Enhanced Value Styles */
#total-due, #discount-amount, #pwd-discount, #vat-exempt-discount {
    font-size: 26px;
    font-weight: 700;
    color:rgb(49, 49, 49);
}

/* Enhanced Table Styles */
.table {
    border-radius: 10px; /* Slightly larger radius for a smoother, modern look */
    overflow: hidden;
    border: 1px solid #e0e4e8;
    width: 100%; /* Ensure the table spans full width */
    margin-bottom: 20px; /* Add space below the table */
    background-color: rgb(241, 241, 241);
    
}

/* Table Header Styling */
table th {
    background-color: #CCCCCC; /* Darker background for the header */
    color: black; /* Text color for data cells */
    font-weight: 500;
    padding: 14px 18px; /* Increased padding for better readability */
    border-bottom: 2px solid #e0e4e8;
    text-align: left; /* Align header text to the left for consistency */
}

/* Table Row Styling */
table tr:nth-child(even) {
    background-color: #ffffff; /* Alternate row color for better readability */
}

table tr:nth-child(odd) {
    background-color: #ffffff; /* Light background for odd rows */
}

/* Table Data Cell Styling */
table td {
    padding: 14px 18px; /* Increased padding for readability */
    border-bottom: 1px solid #e0e4e8;
    color: #343a40; /* Text color for data cells */
}

/* Hover Effect on Rows */
table tr:hover {
    background-color: #f1f1f1; /* Light background on hover for rows */
    cursor: pointer; /* Change cursor to indicate interactivity */
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
@media (max-width: 768px) {
    .col-sm-6 {
        width: 100%;
        padding: 0 10px;
    }
    
    .total-due-box, .discount-amount-box,
    .pwd-discount-box, .vat-exempt-discount-box {
        margin-bottom: 15px;
    }
}
.discount-checkbox-wrapper {
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 10px;
    background: rgb(241, 241, 241);
    border-radius: 8px;
    margin: 5px 0;
}

.checkbox-label {
    display: flex;
    align-items: center;
    gap: 10px;
    cursor: pointer;
    margin: 0;
    font-weight: 500;
    color: #343a40;
}

.checkbox-label input[type="checkbox"] {
    width: 20px;
    height: 20px;
    margin: 0;
    cursor: pointer;
    accent-color: #12369e;
}

.checkbox-label span {
    font-size: 14px;
}

@media (max-width: 768px) {
    .discount-checkbox-wrapper {
        margin: 5px 0;
    }
}
/* Enhanced PhilHealth Box */
.philhealth-box {
    padding: 18px; /* Increased padding for better spacing */
    background-color:rgb(241, 241, 241); /* Lighter background for better contrast */
    border-radius: 12px; /* Slightly more rounded corners */
    box-shadow: 0 6px 12px rgba(0, 0, 0, 0.15); /* Deeper shadow for better depth */
    margin-bottom: 25px;
    border: 1px solid #e0e4e8;
    transition: box-shadow 0.3s ease, transform 0.3s ease;
}

/* Hover Effect */
.philhealth-box:hover {
    box-shadow: 0 8px 16px rgba(0, 0, 0, 0.2); /* More prominent shadow on hover */
    transform: translateY(-4px); /* Slight lift for interactivity */
}


.philhealth-box h4 {
    font-size: 18px;
    color: #343a40;
    margin-bottom: 15px;
    padding-bottom: 10px;
    border-bottom: 1px solid #e0e4e8;
}

.philhealth-box select,
.philhealth-box input {
    border: 2px solid #e0e4e8;
    transition: all 0.3s ease;
}

.philhealth-box select:focus,
.philhealth-box input:focus {
    border-color: #12369e;
    box-shadow: 0 0 0 3px rgba(18,54,158,0.1);
}
/* Enhanced Checkbox Container */
.checkbox-container {
    padding: 18px;
    background-color: rgb(241, 241, 241);
    border-radius: 12px;
    box-shadow: 0 6px 12px rgba(0, 0, 0, 0.15);
    margin-bottom: 25px;
    border: 1px solid #e0e4e8;
    transition: box-shadow 0.3s ease, transform 0.3s ease;
}

/* Hover Effect */
.checkbox-container:hover {
    box-shadow: 0 8px 16px rgba(0, 0, 0, 0.2);
    transform: translateY(-4px);
}

.checkbox-container h4 {
    font-size: 18px;
    color: #343a40;
    margin-bottom: 15px;
    padding-bottom: 10px;
    border-bottom: 1px solid #e0e4e8;
}
