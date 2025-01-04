<?php
session_start();
if (empty($_SESSION['name'])) {
    header('location:index.php');
    exit();
}

include('header.php');
include('includes/connection.php');

// Fetch the last billing ID
$fetch_query = mysqli_query($connection, "SELECT MAX(id) AS id FROM tbl_billing_inpatient");
$row = mysqli_fetch_row($fetch_query);
$bl_id = ($row[0] == 0) ? 1 : $row[0] + 1;

if (isset($_POST['add-billing'])) {
    $billing_id = 'BL-' . $bl_id;
    $patient_name = mysqli_real_escape_string($connection, $_POST['patient_name']);
    $patient_type = $_POST['patient_type'];

    // Fetch patient details based on patient name
    $patient_query = $connection->prepare("
        SELECT ipr.patient_id, ipr.patient_name, ipr.dob, ipr.gender, ipr.admission_date, ipr.discharge_date, ipr.diagnosis, p.address 
        FROM tbl_inpatient_record AS ipr
        JOIN tbl_patient AS p 
        ON ipr.patient_id = p.patient_id 
        WHERE ipr.patient_name = ?");
    $patient_query->bind_param("s", $patient_name);
    $patient_query->execute();
    $patient_result = $patient_query->get_result();
    $patient = $patient_result->fetch_array(MYSQLI_ASSOC);

    if ($patient) {
        // Retrieve patient details and handle NULL values
        $patient_id = !empty($patient['patient_id']) ? $patient['patient_id'] : "NULL";
        $dob = !empty($patient['dob']) ? "'" . $patient['dob'] . "'" : "NULL";
        $gender = !empty($patient['gender']) ? "'" . $patient['gender'] . "'" : "NULL";
        $admission_date = !empty($patient['admission_date']) ? "'" . $patient['admission_date'] . "'" : "NULL";
        $discharge_date = !empty($patient['discharge_date']) ? "'" . $patient['discharge_date'] . "'" : "NULL";
        $diagnosis = !empty($patient['diagnosis']) ? "'" . $patient['diagnosis'] . "'" : "NULL";
        $address = !empty($patient['address']) ? "'" . $patient['address'] . "'" : "NULL";

        // Default values for fees
        $room_fee = isset($_POST['room_fee']) && $_POST['room_fee'] !== '' ? $_POST['room_fee'] : 0;
        $lab_fee = isset($_POST['lab_fee']) && $_POST['lab_fee'] !== '' ? $_POST['lab_fee'] : 0;
        $medication_fee = 0;
        $readers_fee = isset($_POST['readers_fee']) && $_POST['readers_fee'] !== '' ? $_POST['readers_fee'] : 0;
        $others_fee = 0;

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

        // Apply discounts if checked
        $apply_discount = isset($_POST['discount_checkbox']) && $_POST['discount_checkbox'] == 'on';
        $room_fee = isset($_POST['room_fee']) ? floatval($_POST['room_fee']) : 0;
        $lab_fee = isset($_POST['lab_fee']) ? floatval($_POST['lab_fee']) : 0;
        $medication_fee = isset($_POST['medication_fee']) ? floatval($_POST['medication_fee']) : 0;
        $supplies_fee = isset($_POST['supplies_fee']) ? floatval($_POST['supplies_fee']) : 0;
        $professional_fee = isset($_POST['professional_fee']) ? floatval($_POST['professional_fee']) : 0;
        $readers_fee = isset($_POST['readers_fee']) ? floatval($_POST['readers_fee']) : 0;

        // Fixed operating room fee
        $operating_room_fee = isset($_POST['operating_room_fee_checkbox']) && $_POST['operating_room_fee_checkbox'] == 'on' ? 1150 : 0;

        // Calculate combined fees before applying any discounts
        $combined_fees = $room_fee + $lab_fee + $medication_fee + $operating_room_fee + $supplies_fee + $others_fee + $professional_fee + $readers_fee;

        // Apply Professional Fee Discount (12%)
        $apply_professional_fee_discount = isset($_POST['professional_fee_discount_checkbox']) && $_POST['professional_fee_discount_checkbox'] == 'on';
        $pf_discount_amount = $apply_professional_fee_discount ? $professional_fee * 0.12 : 0; // 12% of the original professional fee

        // Apply VAT Exempt Discount (12%)
        $apply_vat_exempt_discount = isset($_POST['vat_exempt_checkbox']) && $_POST['vat_exempt_checkbox'] == 'on';
        $vat_exempt_discount_amount = $apply_vat_exempt_discount ? $combined_fees * 0.12 : 0;

        // Apply Senior/PWD Discount (20%) to the remaining combined fees after VAT Exempt Discount
        $senior_pwd_discount_amount = $apply_discount ? ($combined_fees - $vat_exempt_discount_amount) * 0.2 : 0;

        // Calculate total due after applying all discounts
        $total_due = $combined_fees - $pf_discount_amount - $vat_exempt_discount_amount - $senior_pwd_discount_amount;

        // Calculate total discounts
        $total_discount = $pf_discount_amount + $vat_exempt_discount_amount + $senior_pwd_discount_amount;

        // Calculate non-discounted total
        $non_discounted_total = $room_fee + $lab_fee + $medication_fee + $operating_room_fee + $supplies_fee + $professional_fee + $readers_fee + $others_fee;

        // Insert billing details
        if ($patient_type == 'inpatient') {
            $query = "
            INSERT INTO tbl_billing_inpatient
            (billing_id, patient_id, patient_name, dob, gender, admission_date, discharge_date, diagnosis, address, lab_fee, room_fee, medication_fee, operating_room_fee, supplies_fee, total_due, non_discounted_total, discount_amount, professional_fee, pf_discount_amount, readers_fee, others_fee, vat_exempt_discount_amount) 
            VALUES ('$billing_id', '$patient_id', '$patient_name', $dob, $gender, $admission_date, $discharge_date, $diagnosis, $address, $lab_fee, $room_fee, $medication_fee, $operating_room_fee, $supplies_fee, $total_due, $non_discounted_total, $total_discount, $professional_fee, $pf_discount_amount, $readers_fee, $others_fee, $vat_exempt_discount_amount)
            ";
        } elseif ($patient_type == 'hemodialysis') {
            $query = "
            INSERT INTO tbl_billing_hemodialysis
            (billing_id, patient_id, patient_name, dob, gender, admission_date, discharge_date, diagnosis, address, lab_fee, room_fee, medication_fee, operating_room_fee, supplies_fee, total_due, non_discounted_total, discount_amount, professional_fee, pf_discount_amount, readers_fee, others_fee) 
            VALUES ('$billing_id', '$patient_id', '$patient_name', $dob, $gender, $admission_date, $discharge_date, $diagnosis, $address, $lab_fee, $room_fee, $medication_fee, $operating_room_fee, $supplies_fee, $total_due, $non_discounted_total, $total_discount, $professional_fee, $pf_discount_amount, $readers_fee, $others_fee)
            ";
        } else {
            $msg = "Error: Patient type not selected.";
        }

        if (isset($query) && mysqli_query($connection, $query)) {
            $msg = "Billing successfully added for $patient_type.";
        } else {
            $msg = "Error: " . mysqli_error($connection);
        }

        $medication_query->close();
        }

        $patient_query->close();
    }
?>

<div class="page-wrapper">
    <div class="content">
        <div class="row">
            <div class="col-sm-4">
                <h4 class="page-title">Add Account</h4>
            </div>
            <div class="col-sm-8 text-right mb-3">
                <a href="billing.php" class="btn btn-primary btn-rounded float-right">Back</a>
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
                <div id="inpatient-section" class="patient-type-section" style="display:none;">
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
                    <!-- Medication Fee Section -->
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
                    <!-- Lab Fee Section -->
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
                </div>
                <!-- Hemodialysis Fields Section (Initially Hidden) -->
                <div id="hemodialysis-section" class="patient-type-section" style="display:none;">
                    <!-- Medication Fee Section -->
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
                        <tbody id="medication-fee-container-hemo"></tbody>
                    </table>
                    <!-- Lab Fee Section -->
                    <h4>Lab Charges</h4> 
                    <table class="table table-bordered">
                        <thead style="background-color:rgba(204, 204, 204, 0.1);">
                            <tr>
                                <th>Lab Test</th>
                                <th>Price</th>
                            </tr>
                        </thead>
                        <tbody id="lab-tests-container-hemo"></tbody>
                    </table>
                </div>

                <!-- Newborn Fields Section (Initially Hidden) -->
                <div id="newborn-section" class="patient-type-section" style="display:none;">
                    <h4>Room Charges</h4> 
                    <table class="table table-bordered">
                        <thead style="background-color:rgba(204, 204, 204, 0.1);">
                            <tr>
                                <th>Admission Date</th>
                                <th>Discharge Date</th>
                                <th>Room Type</th>
                            </tr>
                        </thead>
                        <tbody id="room-fee-container-newborn"></tbody>
                    </table>
                    <!-- Medication Fee Section -->
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
                        <tbody id="medication-fee-container-newborn"></tbody>
                    </table>
                    <!-- Lab Fee Section -->
                    <h4>Lab Charges</h4> 
                    <table class="table table-bordered">
                        <thead style="background-color:rgba(204, 204, 204, 0.1);">
                            <tr>
                                <th>Lab Test</th>
                                <th>Price</th>
                            </tr>
                        </thead>
                        <tbody id="lab-tests-container-newborn"></tbody>
                    </table>
                </div>
                <!-- Operating Room Fee Section -->
                <div class="form-group row">
                    <div class="col-sm-4">
                        <label for="operating-room-fee-checkbox">Include Operating Room Fee (₱1150)</label>
                        <input type="checkbox" class="form-control" id="operating-room-fee-checkbox" name="operating_room_fee_checkbox" value="on">
                    </div>
                </div>
                <!-- Add Supplies Section in HTML -->
                <div class="form-group">
                    <label for="supplies-fee">Supplies</label>
                    <input type="number" class="form-control" id="supplies-fee" name="supplies_fee" placeholder="Enter supplies fee">
                </div>
                <!-- Others Fee Section -->
                <div class="form-group row">
                    <div class="col-sm-12">
                        <label>Others:</label>
                        <div id="other-items-container" class="row">
                            <div class="other-item col-md-6">
                                <input type="text" class="form-control mb-2" name="others[0][name]" placeholder="Item Name">
                            </div>
                            <div class="other-item col-md-6">
                                <input type="number" class="form-control mb-2" name="others[0][cost]" placeholder="Item Cost">
                            </div>
                        </div>
                        <button type="button" id="add-other-item" class="btn btn-info mt-3">Add More Items</button>
                    </div>
                </div>
                <div class="form-group row d-flex align-items-center">
                    <div class="col-sm-4">
                        <label for="professional-fee-discount-checkbox">Professional's Fee Discount (12%)</label>
                        <input type="checkbox" class="form-control" id="professional-fee-discount-checkbox" name="professional_fee_discount_checkbox" value="on">
                    </div>
                    <div class="col-sm-4">
                        <label for="vat-exempt-checkbox">VAT Exempt Discount (12%)</label>
                        <input type="checkbox" class="form-control" id="vat-exempt-checkbox" name="vat_exempt_checkbox" value="on">
                    </div>
                    <div class="col-sm-4">
                        <label for="discount-checkbox">Senior/PWD Discount (20%)</label>
                        <input type="checkbox" class="form-control" id="discount-checkbox" name="discount_checkbox" value="on">
                    </div>
                </div>
                <!-- Professional Fee and Readers Fee -->
                <div class="form-group row">
                    <div class="col-sm-6">
                        <label for="professional-fee">Professional's Fee</label>
                        <input type="number" class="form-control" id="professional-fee" name="professional_fee" placeholder="Enter Professional Fee" required>
                    </div>
                    <div class="col-sm-6">
                        <label for="readers-fee">Reader's Fee</label>
                        <input type="number" class="form-control" id="readers-fee" name="readers_fee" placeholder="Enter Readers Fee">
                    </div>
                </div>
                <div class="form-group row">
                    <div class="col-sm-6 mb-3">
                        <div class="professional-fee-discount-box">
                            <h5>Professional Fee Discount (12%):</h5>
                            <p id="professional-fee-discount">₱0.00</p> <!-- Display professional fee discount here -->
                        </div>
                    </div>
                    <div class="col-sm-6 mb-3">
                        <div class="vat-exempt-discount-box">
                            <h5>VAT Exempt Discount (12%):</h5>
                            <p id="vat-exempt-discount">₱0.00</p> <!-- Display VAT exempt discount here -->
                        </div>
                    </div>
                    <div class="col-sm-6 mb-3">
                        <div class="discount-amount-box">
                            <h5>Senior/PWD Discount (20%):</h5>
                            <p id="discount-amount">₱0.00</p> <!-- Display discount amount here -->
                        </div>
                    </div>
                    <div class="col-sm-6 mb-3">
                        <div class="total-due-box">
                            <h5>Total Due: (Amount to pay)</h5>
                            <p id="total-due">₱0.00</p> <!-- Display total due here -->
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

<script type="text/javascript">
<?php
if (isset($msg)) {
    echo 'swal("' . $msg . '");';
}
?>
document.addEventListener('DOMContentLoaded', function () { 
    // Select relevant elements
    const patientTypeSelect = document.getElementById('patient-type-select');
    const professionalFeeDiscountCheckbox = document.getElementById('professional-fee-discount-checkbox');
    const discountCheckbox = document.getElementById('discount-checkbox');
    const vatExemptCheckbox = document.getElementById('vat-exempt-checkbox');
    const operatingRoomFeeCheckbox = document.getElementById('operating-room-fee-checkbox');
    const searchInput = document.getElementById('patient-search');
    const patientList = document.getElementById('patient-list');
    const labTestsContainer = document.getElementById('lab-tests-container');
    const roomFeeContainer = document.getElementById('room-fee-container');
    const medicationFeeContainer = document.getElementById('medication-fee-container');
    const readersFeeInput = document.getElementById('readers-fee');
    const othersContainer = document.getElementById('other-items-container'); // Container for others items
    const suppliesFeeInput = document.getElementById('supplies-fee'); // Supplies fee input
    let labFeeTotal = 0;
    let roomFeeTotal = 0;
    let medicationFeeTotal = 0;
    let othersFee = 0; // Track total others fee
    let suppliesFee = 0; // Track total supplies fee
    let discountAmount = 0;
    let totalDue = 0;

    function calculateTotalDue() {
        const professionalFee = parseFloat(document.getElementById('professional-fee').value) || 0;
        const readersFee = parseFloat(document.getElementById('readers-fee').value) || 0;
        suppliesFee = parseFloat(suppliesFeeInput.value) || 0;

        // Calculate others fee from input fields
        othersFee = 0;
        document.querySelectorAll('#other-items-container input[name^="others"]').forEach(input => {
            if (input.name.includes('[cost]')) {
                othersFee += parseFloat(input.value) || 0;
            }
        });

        // Operating room fee based on checkbox
        const operatingRoomFee = operatingRoomFeeCheckbox.checked ? 1150 : 0;

        // Calculate combined fees before applying any discounts
        let combinedFees = labFeeTotal + roomFeeTotal + medicationFeeTotal + othersFee + suppliesFee + operatingRoomFee + professionalFee + readersFee;

        // Variables for discount amounts
        let professionalFeeDiscountAmount = 0; // For the Professional Fee discount (12%)
        let vatExemptDiscountAmount = 0; // For the VAT Exempt discount (12%)
        let seniorPwdDiscountAmount = 0; // For the Senior/PWD discount (20%)

        // Apply Professional Fee Discount (12%)
        if (professionalFeeDiscountCheckbox.checked) {
            professionalFeeDiscountAmount = professionalFee * 0.12; // 12% of the original Professional Fee
        }

        // Apply VAT Exempt Discount (12%)
        if (vatExemptCheckbox.checked) {
            vatExemptDiscountAmount = combinedFees * 0.12; // 12% of the combined fees
            combinedFees -= vatExemptDiscountAmount;
        }

        // Apply Senior/PWD Discount (20%)
        if (discountCheckbox.checked) {
            seniorPwdDiscountAmount = combinedFees * 0.20; // 20% of the remaining combined fees after VAT Exempt Discount
            combinedFees -= seniorPwdDiscountAmount;
        }

        // Calculate total due after applying all discounts
        totalDue = combinedFees - professionalFeeDiscountAmount;

        // Calculate total discounts
        discountAmount = professionalFeeDiscountAmount + vatExemptDiscountAmount + seniorPwdDiscountAmount;

        // Update the total due on the page
        document.getElementById('total-due').innerText = totalDue.toFixed(2);

        // Update the discount boxes
        document.getElementById('professional-fee-discount').innerText = '₱' + professionalFeeDiscountAmount.toFixed(2);
        document.getElementById('vat-exempt-discount').innerText = '₱' + vatExemptDiscountAmount.toFixed(2);
        document.getElementById('discount-amount').innerText = '₱' + seniorPwdDiscountAmount.toFixed(2);
    }

    // Add event listeners to recalculate total due when inputs change
    document.getElementById('professional-fee').addEventListener('input', calculateTotalDue);
    document.getElementById('readers-fee').addEventListener('input', calculateTotalDue);
    suppliesFeeInput.addEventListener('input', calculateTotalDue);
    professionalFeeDiscountCheckbox.addEventListener('change', calculateTotalDue);
    vatExemptCheckbox.addEventListener('change', calculateTotalDue);
    discountCheckbox.addEventListener('change', calculateTotalDue);
    operatingRoomFeeCheckbox.addEventListener('change', calculateTotalDue); // Add event listener for operating room fee checkbox
    othersContainer.addEventListener('input', calculateTotalDue); // Add event listener for others fee inputs

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
            document.getElementById('inpatient-section').style.display = 'block';
        } else if (selectedType === 'hemodialysis') {
            document.getElementById('hemodialysis-section').style.display = 'block';
        } else if (selectedType === 'newborn') {
            document.getElementById('newborn-section').style.display = 'block';
        }
    });

    // Trigger change event to display the correct section on page load
    patientTypeSelect.dispatchEvent(new Event('change'));

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
        }
    });

    // Function to add more "Other Items"
    document.getElementById('add-other-item').addEventListener('click', function () {
        const container = document.getElementById('other-items-container');
        const itemCount = Math.floor(container.children.length / 2); // Count pairs of inputs

        // Create new item name and cost inputs with col-md-6
        const newItemHTML = `
            <div class="other-item col-md-6">
                <input type="text" class="form-control mb-2" name="others[${itemCount}][name]" placeholder="Item Name" required>
            </div>
            <div class="other-item col-md-6">
                <input type="number" class="form-control mb-2" name="others[${itemCount}][cost]" placeholder="Item Cost" required>
            </div>
        `;

        container.insertAdjacentHTML('beforeend', newItemHTML);
    });

    // Form submission to create hidden inputs for billing
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
    });
});
</script>

<style>
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
    .patient-list {
        max-height: 200px; /* Maximum height to prevent list overflow */
        overflow-y: auto; /* Scrollable if the list is long */
        border: 1px solid #ddd; /* Border color */
        border-radius: 5px; /* Rounded corners */
        background: #fff; /* Background color */
        position: absolute; /* Absolute positioning below the input */
        z-index: 1000; /* Ensures the list is on top of other elements */
        width: 93%; /* Adjust the width to match the input field */
        display: none; /* Initially hidden */
        box-shadow: 0px 4px 6px rgba(0, 0, 0, 0.1); /* Add subtle shadow */
    }

    .patient-list .patient-option {
        padding: 8px 12px;
        cursor: pointer;
        list-style: none;
        border-bottom: 1px solid #ddd;
    }

    .patient-list .patient-option:hover {
        background-color: #12369e;
        color: white;
    }
 
    input[type="checkbox"] {
        width: 20px; /* Smaller checkbox */
        height: 15px; /* Smaller checkbox */
    }
    /* Individual box styling */
    .total-due-box, .discount-amount-box {
        padding: 15px;
        background-color: #f8f9fa;
        border-radius: 8px;
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        text-align: center; /* Center the content */
    }

    /* Title styling */
    .total-due-box h5, .discount-amount-box h5 {
        font-size: 18px;
        color: #343a40;
        margin-bottom: 10px;
    }

    /* Value styling */
    #total-due {
        font-size: 24px;
        color: black; /* Green color for total due */
    }

    #discount-amount {
        font-size: 24px;
        color: black; /* Red color for discount amount */
    }

    /* Small screen adjustments (for mobile responsiveness) */
    @media (max-width: 576px) {
        .total-due-box, .discount-amount-box {
            text-align: left; /* Left-align text for mobile screens */
        }
    }

    /* Professional Fee Discount Box */
    .professional-fee-discount-box {
        padding: 15px;
        background-color: #f8f9fa;
        border-radius: 8px;
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        text-align: center; /* Center the content */
    }

    .professional-fee-discount-box h5 {
        font-size: 18px;
        color: #343a40;
        margin-bottom: 10px;
    }

    .professional-fee-discount-box p {
        font-size: 24px;
        color: black; /* Color for professional fee discount */
    }

    /* VAT Exempt Discount Box */
    .vat-exempt-discount-box {
        padding: 15px;
        background-color: #f8f9fa;
        border-radius: 8px;
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        text-align: center; /* Center the content */
    }

    .vat-exempt-discount-box h5 {
        font-size: 18px;
        color: #343a40;
        margin-bottom: 10px;
    }

    .vat-exempt-discount-box p {
        font-size: 24px;
        color: black; /* Color for VAT exempt discount */
    }
    /* Add responsive adjustments */
    .form-group {
        margin-bottom: 15px;
    }

    .col-sm-6, .col-sm-12 {
        margin-bottom: 15px;
    }

    /* Ensure form elements scale properly on mobile devices */
    @media (max-width: 768px) {
        .col-sm-6 {
            width: 100%;
        }
    }

    /* Card design for sections */
    .patient-type-section {
        border: 1px solid #ccc;
        padding: 15px;
        border-radius: 8px;
        box-shadow: 0px 2px 5px rgba(0, 0, 0, 0.1);
        margin-bottom: 20px;
    }


    table th, table td {
        padding: 8px;
        text-align: left;
    }

    table th {
        background-color: #cccccc;
    }

    .float-right {
        float: right;
    }
</style>
