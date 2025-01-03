<?php
session_start();
if (empty($_SESSION['name'])) {
    header('location:index.php');
    exit();
}
include('header.php');
include('includes/connection.php');

// Get the patient type from the URL or default to 'inpatient'
$patientType = isset($_GET['patient_type']) ? $_GET['patient_type'] : 'inpatient';

// Select the correct table based on patient type and prepare query
if ($patientType === 'hemodialysis') {
    $query = "
        SELECT b.billing_id, b.patient_id, b.patient_name, b.dob, b.address, b.diagnosis, b.admission_date, b.discharge_date, b.room_fee, b.lab_fee, b.medication_fee, b.operating_room_fee, 
               b.professional_fee, b.pf_discount_amount, b.readers_fee, b.discount_amount, b.total_due, b.non_discounted_total, 
               GROUP_CONCAT(o.item_name ORDER BY o.date_time DESC SEPARATOR ', ') AS other_items, 
               GROUP_CONCAT(o.item_cost ORDER BY o.date_time DESC SEPARATOR ', ') AS other_costs
        FROM tbl_billing_hemodialysis b
        LEFT JOIN tbl_billing_others o ON b.billing_id = o.billing_id
        WHERE b.deleted = 0
        GROUP BY b.billing_id
    ";
} else {
    $query = "
        SELECT b.billing_id, b.patient_id, b.patient_name, b.dob, b.address, b.diagnosis, b.admission_date, b.discharge_date, b.room_fee, b.lab_fee, b.medication_fee, b.operating_room_fee, 
               b.professional_fee, b.pf_discount_amount, b.readers_fee, b.discount_amount, b.total_due, b.non_discounted_total, 
               GROUP_CONCAT(o.item_name ORDER BY o.date_time DESC SEPARATOR ', ') AS other_items, 
               GROUP_CONCAT(o.item_cost ORDER BY o.date_time DESC SEPARATOR ', ') AS other_costs
        FROM tbl_billing_inpatient b
        LEFT JOIN tbl_billing_others o ON b.billing_id = o.billing_id
        WHERE b.deleted = 0
        GROUP BY b.billing_id
    ";
}

?>

<div class="page-wrapper">
    <div class="content">
        <div class="row">
            <div class="col-sm-4 col-3">
            <h4 class="page-title">Statement Of Account</h4>
            </div>
            <div class="col-sm-8 col-9 text-right m-b-20">
                <a href="add-billing.php" class="btn btn-primary btn-rounded float-right"><i class="fa fa-plus"></i> Add Account</a>
            </div>
        </div>

        <!-- Buttons to filter by Patient Type -->
        <div class="row mb-3">
            <div class="col-sm-4 col-3">
                <button id="inpatient-btn" class="btn btn-rounded btn-info mr-3 <?php echo ($patientType === 'inpatient') ? 'btn-black' : ''; ?>" onclick="showTable('inpatient')">Inpatient</button>
                <button id="hemodialysis-btn" class="btn btn-rounded btn-info <?php echo ($patientType === 'hemodialysis') ? 'btn-black' : ''; ?>" onclick="showTable('hemodialysis')">Hemodialysis</button>
            </div>
        </div>

        <!-- Search for Patient -->
        <div class="table-responsive">
            <input class="form-control" type="text" id="patientSearchInput" onkeyup="filterPatients()" placeholder="Search for Patient">

            <h4 class="mt-4">Patient Information</h4>
            <!-- Patient Information Table -->
            <table class="datatable table table-hover" id="patientInfoTable">
                <thead style="background-color: #CCCCCC;">
                    <tr>
                        <th>Billing ID</th>
                        <th>Patient ID</th>
                        <th>Patient Name</th>
                        <th>Age</th>
                        <th>Address</th>
                        <th>Diagnosis</th>
                        <th>Admission Date</th>
                        <th>Discharge Date</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $patient_query = mysqli_query($connection, $query); 
                    while ($row = mysqli_fetch_array($patient_query)) {
                        $dob = $row['dob'];
                        $date = str_replace('/', '-', $dob); 
                        $dob = date('Y-m-d', strtotime($date));
                        $year = (date('Y') - date('Y', strtotime($dob)));
                    ?>
                    <tr data-billing-id="<?php echo $row['billing_id']; ?>">
                        <td><?php echo $row['billing_id']; ?></td>
                        <td><?php echo $row['patient_id']; ?></td>
                        <td><?php echo $row['patient_name']; ?></td>
                        <td><?php echo $year; ?></td>
                        <td><?php echo $row['address']; ?></td>
                        <td><?php echo $row['diagnosis']; ?></td>
                        <td><?php echo date('F d, Y g:i A', strtotime($row['admission_date'])); ?></td>
                        <td><?php echo date('F d, Y g:i A', strtotime($row['discharge_date'])); ?></td>
                        <td class="text-right">
                            <div class="dropdown dropdown-action">
                                <a href="#" class="action-icon dropdown-toggle" data-toggle="dropdown" aria-expanded="false"><i class="fa fa-ellipsis-v"></i></a>
                                <div class="dropdown-menu dropdown-menu-right">
                                    <?php 
                                    if ($_SESSION['role'] == 1) {
                                        echo '<a class="dropdown-item" href="edit-billing.php?id='.$row['id'].'"><i class="fa fa-pencil m-r-5"></i> Edit</a>';
                                        echo '<a class="dropdown-item" href="billing.php?ids='.$row['id'].'" onclick="return confirmDelete()"><i class="fa fa-trash-o m-r-5"></i> Delete</a>';
                                    }
                                    ?>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    <?php } ?>
                </tbody>
            </table>
            <!-- Billing Details Table -->
            <h4 class="mt-6">Billing Details</h4>
            <table class="datatable table table-bordered" id="billingTable">
                <thead style="background-color: #CCCCCC;">
                    <tr>
                        <th>Room Charges</th>
                        <th>Laboratory Charges</th>
                        <th>Medication Charges</th>
                        <th>Other Charges</th> 
                        <th>Operating Room Charges</th>
                        <th>Professional's Fee</th>
                        <th>Reader's Fee</th>
                        <th>Senior/PWD Discount</th>
                        <th>Professional's Fee Discount</th>
                        <th>Subtotal</th>
                        <th>Amount Due</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    // Fetch billing details with other charges
                    $billing_query = mysqli_query($connection, $query); 
                    while ($row = mysqli_fetch_array($billing_query)) {
                        $otherItems = !empty($row['other_items']) ? $row['other_items'] : NULL;
                        $otherCosts = !empty($row['other_costs']) ? $row['other_costs'] : NULL;
                    ?>
                    <tr data-billing-id="<?php echo $row['billing_id']; ?>">
                        <td><?php echo number_format($row['room_fee'], 2); ?></td>
                        <td><?php echo number_format($row['lab_fee'], 2); ?></td>
                        <td><?php echo number_format($row['medication_fee'], 2); ?></td>
                        <td><?php echo $otherItems . ' (' . $otherCosts . ')'; ?></td>
                        <td><?php echo number_format($row['operating_room_fee'], 2); ?></td>
                        <td><?php echo number_format($row['professional_fee'], 2); ?></td>
                        <td><?php echo number_format($row['readers_fee'], 2); ?></td>
                        <td><?php echo number_format($row['discount_amount'], 2); ?></td>
                        <td><?php echo number_format($row['pf_discount_amount'], 2); ?></td>
                        <td><?php echo number_format($row['non_discounted_total'], 2); ?></td>
                        <td><strong><?php echo number_format($row['total_due'], 2); ?></strong></td>
                    </tr>
                    <?php } ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include('footer.php'); ?>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script language="JavaScript" type="text/javascript">
function confirmDelete(){
    return confirm('Are you sure want to delete this Patient?');
}

function showTable(patientType) {
    // This will reload the page with the selected patient type as part of the URL
    window.location.href = "billing.php?patient_type=" + patientType;
}

// JavaScript function for filtering patients
function filterPatients() {
    var input = document.getElementById("patientSearchInput");
    var filter = input.value.toUpperCase();
    var patientTable = document.getElementById("patientInfoTable");
    var billingTable = document.getElementById("billingTable");
    var patientRows = patientTable.getElementsByTagName("tr");
    var billingRows = billingTable.getElementsByTagName("tr");

    var patientMatchIds = [];

    // Filter Patient Information Table
    for (let i = 1; i < patientRows.length; i++) {
        let matchFound = false;
        for (let j = 0; j < patientRows[i].cells.length; j++) {
            let td = patientRows[i].cells[j];
            if (td) {
                let txtValue = td.textContent || td.innerText;
                if (txtValue.toUpperCase().indexOf(filter) > -1) {
                    matchFound = true;
                    break;
                }
            }
        }
        patientRows[i].style.display = matchFound ? "" : "none";
        if (matchFound) {
            patientMatchIds.push(patientRows[i].getAttribute("data-billing-id"));
        }
    }

    // Filter Billing Details Table based on Patient Information Table results
    for (let i = 1; i < billingRows.length; i++) {
        let billingId = billingRows[i].getAttribute("data-billing-id");
        billingRows[i].style.display = patientMatchIds.includes(billingId) ? "" : "none";
    }

    // Show or hide Billing Table if no matches are found
    billingTable.style.display = patientMatchIds.length > 0 ? "" : "none";
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
 #billingTable_wrapper .dataTables_length {
    display: none;
}
.btn-primary {
    background: #12369e;
    border: none;
}
.btn-primary:hover {
    background: #05007E;
}
.dropdown-menu {
    position: absolute;
    top: 0;
    right: 0;
    z-index: 9999;
    min-width: 150px; /* Adjust according to your preference */
}

.dropdown-toggle:focus {
    outline: none; /* Optional: removes the focus outline */
}

.action-icon {
    cursor: pointer;
}
.btn-black {
    background-color:rgb(4, 0, 107)!important;
    color: white;
}
</style>
