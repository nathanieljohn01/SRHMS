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
   
    $connection->begin_transaction();
   
    $insert_query = $connection->prepare("INSERT INTO tbl_payment
        (payment_id, patient_name, patient_type, amount_to_pay, amount_paid, payment_datetime)
        VALUES (?, ?, ?, ?, ?, NOW())");
   
    $insert_query->bind_param("sssdd", $payment_id, $patient_name, $patient_type, $amount_to_pay, $amount_paid);
   
    if ($insert_query->execute()) {
        $update_lab = $connection->prepare("UPDATE tbl_laborder SET deleted = 1 WHERE patient_name = ? AND deleted = 0");
        $update_lab->bind_param("s", $patient_name);
        $update_lab->execute();

        $update_rad = $connection->prepare("UPDATE tbl_radiology SET deleted = 1 WHERE patient_name = ? AND deleted = 0");
        $update_rad->bind_param("s", $patient_name);
        $update_rad->execute();

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
    } else {
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
                                <input type="number" step="0.01" class="form-control" name="amount_to_pay" required>
                            </div>
                        </div>

                        <div class="col-md-3">
                            <div class="form-group">
                                <label>Amount Paid</label>
                                <input type="number" step="0.01" class="form-control" name="amount_paid" required>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-12">
                            <div id="total_results" class="mt-4">
                                <!-- Laboratory Fees Table -->
                                <table class="table table-bordered" id="outpatientLabTable" style="display: none;">
                                    <thead class="thead-light">
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
                                    <thead class="thead-light">
                                        <tr>
                                            <th>Radiology Test</th>
                                            <th>Price</th>
                                            <th>Request Date</th>
                                        </tr>
                                    </thead>
                                    <tbody></tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <div class="row mt-3">
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

function togglePatientSearch() {
    const selectedType = patientTypeSelect.value;
    if (selectedType === 'Outpatient') {
        patientSearch.parentElement.parentElement.style.display = 'block'; // Changed to target the form-group
        outpatientLabTable.style.display = 'table'; // Show the bills table
        outpatientRadTable.style.display = 'table'; // Show the bills table
        patientSearch.value = '';
    } else {
        patientSearch.parentElement.parentElement.style.display = 'none';
        outpatientLabTable.style.display = 'none'; // Hide the bills table
        outpatientRadTable.style.display = 'none'; // Hide the bills table
    }
}
patientSearch.addEventListener('keyup', function() {
    const selectedType = patientTypeSelect.value;
    if (this.value.length > 0 && selectedType === 'Outpatient') {
        fetch('search-opt.php?search=' + this.value)
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

        let totalAmount = 0;

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


