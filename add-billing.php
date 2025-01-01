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
        SELECT ipr.patient_id, ipr.patient_name, ipr.dob, ipr.gender, ipr.admission_date, ipr.discharge_date, p.address 
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
        $address = !empty($patient['address']) ? "'" . $patient['address'] . "'" : "NULL";

        // Default values for fees
        $room_fee = isset($_POST['room_fee']) && $_POST['room_fee'] !== '' ? $_POST['room_fee'] : 0;
        $lab_fee = isset($_POST['lab_fee']) && $_POST['lab_fee'] !== '' ? $_POST['lab_fee'] : 0;
        $medication_fee = 0;
        $readers_fee = isset($_POST['readers_fee']) && $_POST['readers_fee'] !== '' ? $_POST['readers_fee'] : 0;
        $others_fee = 0;

        // Calculate medication fee
        $medication_query = $connection->prepare("SELECT SUM(total_price) AS medication_fee FROM tbl_treatment WHERE patient_name = ?");
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
            WHERE billing_id = '$billing_id' AND date_time = (SELECT MAX(date_time) FROM tbl_billing_others WHERE billing_id = '$billing_id')
            ";

            $others_fee_result = mysqli_query($connection, $others_fee_query);
            $others_fee_row = mysqli_fetch_assoc($others_fee_result);
            $others_fee = $others_fee_row['others_fee'] ?? 0;
        }

        // Apply discounts if checked
        $apply_discount = isset($_POST['discount_checkbox']) && $_POST['discount_checkbox'] == 'on';
        $discounted_room_fee = $apply_discount ? $room_fee * 0.8 : $room_fee;
        $discounted_lab_fee = $apply_discount ? $lab_fee * 0.8 : $lab_fee;
        $discounted_medication_fee = $apply_discount ? $medication_fee * 0.8 : $medication_fee;
        $others_fee_discounted = $apply_discount ? $others_fee * 0.8 : $others_fee;

        // Professional fee and discount handling
        $professional_fee = isset($_POST['professional_fee']) && $_POST['professional_fee'] !== '' ? floatval($_POST['professional_fee']) : 0;
        $apply_professional_fee_discount = isset($_POST['professional_fee_discount_checkbox']) && $_POST['professional_fee_discount_checkbox'] == 'on';

        // Calculate 12% Professional Fee Discount
        $pf_discount_amount = $apply_professional_fee_discount ? $professional_fee * 0.12 : 0; // 12% of the original professional fee
        $discounted_professional_fee = $professional_fee - $pf_discount_amount; // Subtract the 12% discount from the professional fee

        // Apply Senior/PWD Discount (20%) to the discounted professional fee
        if ($apply_discount) {
            $senior_pwd_discount_amount = $discounted_professional_fee * 0.2; // 20% of the already discounted professional fee
            $discounted_professional_fee -= $senior_pwd_discount_amount; // Subtract the Senior/PWD discount
        } else {
            $senior_pwd_discount_amount = 0;
        }

        // Calculate total discounts
        $total_discount = ($room_fee - $discounted_room_fee) +
                        ($lab_fee - $discounted_lab_fee) +
                        ($medication_fee - $discounted_medication_fee) +
                        ($others_fee - $others_fee_discounted) +
                        $pf_discount_amount +
                        $senior_pwd_discount_amount;

        // Calculate total due (fully discounted)
        $total_due = $discounted_room_fee +
                    $discounted_lab_fee +
                    $discounted_medication_fee +
                    $discounted_professional_fee +
                    $readers_fee +
                    $others_fee_discounted;

        // Calculate non-discounted total
        $non_discounted_total = $room_fee +
                                $lab_fee +
                                $medication_fee +
                                $professional_fee +
                                $readers_fee +
                                $others_fee;

        // Insert billing details
        if ($patient_type == 'inpatient') {
            $query = "
                INSERT INTO tbl_billing_inpatient
                (billing_id, patient_id, patient_name, dob, gender, admission_date, discharge_date, address, lab_fee, room_fee, medication_fee, total_due, non_discounted_total, discount_amount, professional_fee, pf_discount_amount, readers_fee, others_fee) 
                VALUES ('$billing_id', '$patient_id', '$patient_name', $dob, $gender, $admission_date, $discharge_date, $address, $lab_fee, $room_fee, $medication_fee, $total_due, $non_discounted_total, $total_discount, $professional_fee, $pf_discount_amount, $readers_fee, $others_fee)
            ";
        } elseif ($patient_type == 'hemodialysis') {
            $query = "
                INSERT INTO tbl_billing_hemodialysis
                (billing_id, patient_id, patient_name, dob, gender, admission_date, discharge_date, address, lab_fee, room_fee, medication_fee, total_due, non_discounted_total, discount_amount, professional_fee, pf_discount_amount, readers_fee, others_fee) 
                VALUES ('$billing_id', '$patient_id', '$patient_name', $dob, $gender, $admission_date, $discharge_date, $address, $lab_fee, $room_fee, $medication_fee, $total_due, $non_discounted_total, $total_discount, $professional_fee, $pf_discount_amount, $readers_fee, $others_fee)
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
        <div class="row">
            <div class="col-lg-8 offset-lg-2">
                <form method="post">
                    <input type="hidden" name="lab_fee" id="lab-fee-input">
                    <input type="hidden" name="room_fee" id="room-fee-input">

                    <!-- Patient Type Dropdown -->
                    <div class="form-group row">
                        <div class="col-sm-12">
                            <label for="patient-type-select">Patient Type</label>
                            <select class="form-control" id="patient-type-select" name="patient_type" required>
                                <option value="">Select Patient Type</option>
                                <option value="inpatient">Inpatient</option>
                                <option value="hemodialysis">Hemodialysis</option>
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

                    <!-- Senior Citizen / PWD Discount Checkbox -->
                    <div class="form-group row">
                        <div class="col-sm-6">
                            <label for="discount-checkbox">Senior Citizen/PWD Discount (20%)</label>
                            <input type="checkbox" class="form-control" id="discount-checkbox" name="discount_checkbox" value="on">
                        </div>
                        <div class="col-sm-6">
                            <label for="professional-fee-discount-checkbox">Professional's Fee Discount (12%)</label>
                            <input type="checkbox" class="form-control" id="professional-fee-discount-checkbox" name="professional_fee_discount_checkbox" value="on">
                        </div>
                    </div>

                    <!-- Inpatient Fields Section (Initially Hidden) -->
                    <div id="inpatient-section" class="patient-type-section" style="display:none;">
                        <table class="table table-bordered">
                            <thead style="background-color: #CCCCCC;">
                                <tr>
                                    <th>Lab Test</th>
                                    <th>Price</th>
                                </tr>
                            </thead>
                            <tbody id="lab-tests-container"></tbody>
                        </table>
                        <table class="table table-bordered">
                            <thead style="background-color: #CCCCCC;">
                                <tr>
                                    <th>Admission Date</th>
                                    <th>Discharge Date</th>
                                    <th>Room Type</th>
                                </tr>
                            </thead>
                            <tbody id="room-fee-container"></tbody>
                        </table>
                    </div>

                    <!-- Hemodialysis Fields Section (Initially Hidden) -->
                    <div id="hemodialysis-section" class="patient-type-section" style="display:none;">
                        <table class="table table-bordered">
                            <thead style="background-color: #CCCCCC;">
                                <tr>
                                    <th>Lab Test</th>
                                    <th>Price</th>
                                </tr>
                            </thead>
                            <tbody id="lab-tests-container-hemo"></tbody>
                        </table>
                    </div>

                    <!-- Medication Fee Section -->
                    <table class="table table-bordered">
                        <thead style="background-color: #CCCCCC;">
                            <tr>
                                <th>Medicine Name (Brand)</th>
                                <th>Total Quantity</th>
                                <th>Price</th>
                                <th>Total Price</th>
                            </tr>
                        </thead>
                        <tbody id="medication-fee-container"></tbody>
                    </table>
                    <!-- Others Fee Section -->
                    <div class="form-group row">
                        <div class="col-sm-12">
                            <label>Other Items</label>
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
                            <div class="total-due-box">
                                <h5>Total Due:</h5>
                                <p id="total-due">₱0.00</p> <!-- Display total due here -->
                            </div>
                        </div>
                        <div class="col-sm-6 mb-3">
                            <div class="discount-amount-box">
                                <h5>Discount Amount (Senior/PWD):</h5>
                                <p id="discount-amount">₱0.00</p> <!-- Display discount amount here -->
                            </div>
                        </div>
                        <div class="col-sm-6 mb-3">
                            <div class="professional-fee-discount-box">
                                <h5>Professional Fee Discount (12%):</h5>
                                <p id="professional-fee-discount">₱0.00</p> <!-- Display professional fee discount here -->
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
    const searchInput = document.getElementById('patient-search');
    const patientList = document.getElementById('patient-list');
    const labTestsContainer = document.getElementById('lab-tests-container');
    const roomFeeContainer = document.getElementById('room-fee-container');
    const medicationFeeContainer = document.getElementById('medication-fee-container');
    const readersFeeInput = document.getElementById('readers-fee');
    const othersContainer = document.getElementById('other-items-container'); // Container for others items
    let labFeeTotal = 0;
    let roomFeeTotal = 0;
    let medicationFeeTotal = 0;
    let othersFee = 0; // Track total others fee
    let discountAmount = 0;
    let totalDue = 0;


    // Function to calculate the total due including others and applying the discount
    function calculateTotalDue() {
    const professionalFee = parseFloat(document.getElementById('professional-fee').value) || 0;
    const readersFee = parseFloat(document.getElementById('readers-fee').value) || 0;

    let discountedProfessionalFee = professionalFee;

    // Variables for discount amounts
    let professionalFeeDiscountAmount = 0; // For the 12% discount
    let seniorPwdDiscountAmount = 0; // For the Senior/PWD discount (20%)

    // Apply Professional Fee Discount (12%) first
    if (professionalFeeDiscountCheckbox.checked) {
        professionalFeeDiscountAmount = professionalFee * 0.12; // 12% of the original Professional Fee
        discountedProfessionalFee = professionalFee - professionalFeeDiscountAmount; // Remaining Professional Fee after 12% discount
    }

    // Apply Senior/PWD Discount (20%) to the remaining Professional Fee
    if (discountCheckbox.checked) {
        seniorPwdDiscountAmount = discountedProfessionalFee * 0.2; // 20% of the discounted Professional Fee
        discountedProfessionalFee -= seniorPwdDiscountAmount; // Remaining Professional Fee after Senior/PWD discount
    }

    // Combine Room/Lab/Med Fee and Other Charges
    let combinedFee = roomFeeTotal + labFeeTotal + medicationFeeTotal + 300; // Add Other Charges (₱300)
    let combinedFeeDiscount = 0;
    if (discountCheckbox.checked) {
        combinedFeeDiscount = combinedFee * 0.2; // Apply 20% Senior/PWD discount
        combinedFee = combinedFee - combinedFeeDiscount; // Remaining combined fee after discount
    }

    // Calculate total due
    totalDue = combinedFee + discountedProfessionalFee + readersFee;

    // Calculate total discounts
    const totalDiscount = professionalFeeDiscountAmount + seniorPwdDiscountAmount + combinedFeeDiscount;

    // Update the displayed values
    document.getElementById('total-due').textContent = '₱' + totalDue.toFixed(2);
    document.getElementById('discount-amount').textContent = '₱' + totalDiscount.toFixed(2); // Total discount (Professional Fee + Combined Discounts)
    document.getElementById('professional-fee-discount').textContent = '₱' + professionalFeeDiscountAmount.toFixed(2); // 12% Professional Fee discount
}


    // Event listeners for discount checkboxes and fee inputs
    discountCheckbox.addEventListener('change', calculateTotalDue);
    professionalFeeDiscountCheckbox.addEventListener('change', calculateTotalDue);
    document.getElementById('professional-fee').addEventListener('input', calculateTotalDue);
    readersFeeInput.addEventListener('input', calculateTotalDue);

    // Handle search input for patients
    searchInput.addEventListener('keyup', function () {
        const query = searchInput.value.trim();
        if (query.length > 2) {
            fetch('search-billing.php?query=' + query)
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
</style>
