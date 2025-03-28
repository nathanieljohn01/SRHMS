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
        SELECT DISTINCT b.*, 
               GROUP_CONCAT(o.item_name ORDER BY o.date_time DESC SEPARATOR ', ') AS other_items,
               GROUP_CONCAT(o.item_cost ORDER BY o.date_time DESC SEPARATOR ', ') AS other_costs
        FROM tbl_billing_hemodialysis b
        LEFT JOIN tbl_billing_others o ON b.billing_id = o.billing_id
        WHERE b.deleted = 0
        GROUP BY b.billing_id, b.patient_id, b.transaction_datetime
        ORDER BY b.transaction_datetime DESC
    ";
} elseif ($patientType === 'newborn') {
    $query = "
        SELECT DISTINCT b.*, 
               GROUP_CONCAT(o.item_name ORDER BY o.date_time DESC SEPARATOR ', ') AS other_items,
               GROUP_CONCAT(o.item_cost ORDER BY o.date_time DESC SEPARATOR ', ') AS other_costs
        FROM tbl_billing_newborn b
        LEFT JOIN tbl_billing_others o ON b.billing_id = o.billing_id
        WHERE b.deleted = 0
        GROUP BY b.billing_id, b.newborn_id, b.transaction_datetime
        ORDER BY b.transaction_datetime DESC
    ";
} else {
    $query = "
        SELECT DISTINCT b.*, 
               GROUP_CONCAT(o.item_name ORDER BY o.date_time DESC SEPARATOR ', ') AS other_items,
               GROUP_CONCAT(o.item_cost ORDER BY o.date_time DESC SEPARATOR ', ') AS other_costs
        FROM tbl_billing_inpatient b
        LEFT JOIN tbl_billing_others o ON b.billing_id = o.billing_id
        WHERE b.deleted = 0
        GROUP BY b.billing_id, b.patient_id, b.transaction_datetime
        ORDER BY b.transaction_datetime DESC
    ";
}

?>

<div class="page-wrapper">
    <div class="content">
        <div class="row align-items-center mb-3">
            <div class="col-md-3">
                <h4 class="page-title">Statement Of Account</h4>
            </div>
            
            <div class="col-md-9">
                <div class="d-flex justify-content-end align-items-center">
                    <!-- Breadcrumb Navigation -->
                    <nav aria-label="breadcrumb" class="mr-4">
                        <ol class="breadcrumb bg-transparent p-0 mb-0" style="font-size: 1.1rem;">
                            <li class="breadcrumb-item">
                                <a href="javascript:void(0);" onclick="showTable('inpatient')" 
                                class="<?= ($patientType === 'inpatient') ? 'text-dark font-weight-bold' : 'text-secondary' ?>"
                                style="padding: 0.08rem 0.15rem; margin: -0.1rem;">
                                Inpatient
                                </a>
                            </li>
                            <li class="breadcrumb-item">
                                <a href="javascript:void(0);" onclick="showTable('hemodialysis')" 
                                class="<?= ($patientType === 'hemodialysis') ? 'text-dark font-weight-bold' : 'text-secondary' ?>"
                                style="padding: 0.08rem 0.15rem; margin: -0.1rem;">
                                Hemodialysis
                                </a>
                            </li>
                            <li class="breadcrumb-item">
                                <a href="javascript:void(0);" onclick="showTable('newborn')" 
                                class="<?= ($patientType === 'newborn') ? 'text-dark font-weight-bold' : 'text-secondary' ?>"
                                style="padding: 0.08rem 0.15rem; margin: -0.1rem;">
                                Newborn
                                </a>
                            </li>
                        </ol>
                    </nav>
                    
                    <a href="add-billing.php" class="btn btn-primary">
                        <i class="fa fa-plus"></i> Add Account
                    </a>
                </div>
            </div>      
        </div>  

        <div class="table-responsive">
        <h5 class="font-weight-bold mb-2">Search Patient:</h5>
            <div class="input-group mb-3">
                <div class="position-relative w-100">
                    <!-- Search Icon -->
                    <i class="fa fa-search position-absolute text-secondary" style="top: 50%; left: 12px; transform: translateY(-50%);"></i>
                    <!-- Input Field -->
                    <input class="form-control" type="text" id="patientSearchInput" onkeyup="filterPatients()" placeholder="Search" style="padding-left: 35px; padding-right: 35px;">
                    <!-- Clear Button -->
                    <button class="position-absolute border-0 bg-transparent text-secondary" type="button" onclick="clearSearch()" style="top: 50%; right: 10px; transform: translateY(-50%);">
                        <i class="fa fa-times"></i>
                    </button>
                </div>
            </div>
        </div>

        <div class="table-responsive">
            <h4 class="mt-4">Patient Information</h4>
            <!-- Patient Information Table -->
            <table class="datatable table table-hover table-striped" id="patientInfoTable">
                <thead style="background-color: #CCCCCC;">
                    <tr>
                        <th>Billing ID</th>
                        <th>Patient ID</th>
                        <th>Patient Name</th>
                        <th>Age</th>
                        <th>Gender</th>
                        <th>Address</th>
                        <th>Diagnosis</th>
                        <th>Admission Date</th>
                        <th>Discharge Date</th>
                        <th>Date & Time</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    if(isset($_GET['ids'])) {
                        $id = intval($_GET['ids']);
                        $type = $_GET['type'] ?? '';
                        
                        if ($type == 'inpatient') {
                            $stmt = $connection->prepare("UPDATE tbl_billing_inpatient SET deleted = 1 WHERE id = ?");
                            $stmt->bind_param("i", $id);
                            $stmt->execute();
                            $stmt->close();
                        } 
                        else if ($type == 'hemodialysis') {
                            $stmt = $connection->prepare("UPDATE tbl_billing_hemodialysis SET deleted = 1 WHERE id = ?");
                            $stmt->bind_param("i", $id);
                            $stmt->execute(); 
                            $stmt->close();
                        } 
                        else if ($type == 'newborn') {
                            $stmt = $connection->prepare("UPDATE tbl_billing_newborn SET deleted = 1 WHERE id = ?");
                            $stmt->bind_param("i", $id);
                            $stmt->execute(); 
                            $stmt->close();
                        }                        
                    }
                    
                    $patient_query = mysqli_query($connection, $query);
                    while ($row = mysqli_fetch_array($patient_query)) {
                        // Calculate age with proper format for newborns
                        $dob = $row['dob'];
                        if (strpos($dob, '/') !== false) {
                            $dateParts = explode('/', $dob);
                            if (count($dateParts) === 3) {
                                $dob = $dateParts[2].'-'.$dateParts[1].'-'.$dateParts[0];
                            }
                        }
                        
                        try {
                            $dobDate = new DateTime($dob);
                            $now = new DateTime();
                            
                            if ($patientType === 'newborn') {
                                $diff = $now->diff($dobDate);
                                if ($diff->y > 0) {
                                    $age = $diff->y;
                                } elseif ($diff->m > 0) {
                                    $age = $diff->m . ' month' . ($diff->m > 1 ? 's' : '');
                                } else {
                                    $age = $diff->d . ' day' . ($diff->d > 1 ? 's' : '');
                                }
                            } else {
                                $age = $now->diff($dobDate)->y;
                            }
                        } catch (Exception $e) {
                            $age = 'N/A';
                        }
                    ?>
                    <tr data-billing-id="<?php echo $row['billing_id']; ?>">
                        <td><?php echo $row['billing_id']; ?></td>
                        <td>
                            <?php 
                            if ($patientType === 'newborn') {
                                echo $row['newborn_id']; 
                            } else {
                                echo $row['patient_id']; 
                            }
                            ?>
                        </td>
                        <td><?php echo $row['patient_name']; ?></td>
                        <td><?php echo $age; ?></td> <!-- Updated to use the calculated age -->
                        <td><?php echo $row['gender']; ?></td>
                        <td><?php echo $row['address']; ?></td>
                        <td><?php echo $row['diagnosis']; ?></td>
                        <td>
                            <?php 
                            if (!empty($row['admission_date'])) {
                                $admissionDate = str_replace('/', '-', $row['admission_date']);
                                echo date('F d, Y g:i A', strtotime($admissionDate));
                            }
                            ?>
                        </td>
                        <td>
                            <?php 
                            if (!empty($row['discharge_date'])) {
                                $dischargeDate = str_replace('/', '-', $row['discharge_date']);
                                echo date('F d, Y g:i A', strtotime($dischargeDate));
                            }
                            ?>
                        </td>
                        <td>
                            <?php 
                            $transactionDate = str_replace('/', '-', $row['transaction_datetime']);
                            echo date('F d, Y g:i A', strtotime($transactionDate));
                            ?>
                        </td>
                        <td class="text-right">
                            <div class="dropdown dropdown-action">
                                <a href="#" class="action-icon dropdown-toggle" data-toggle="dropdown" aria-expanded="false">
                                    <i class="fa fa-ellipsis-v"></i>
                                </a>
                                <div class="dropdown-menu dropdown-menu-right">
                                    <?php
                                    if ($_SESSION['role'] == 1) {
                                        echo '<a class="dropdown-item" href="generate-soa.php?id='.$row['billing_id'].'"><i class="fa fa-file-pdf-o m-r-5"></i> Generate SOA</a>';
                                        echo '<a class="dropdown-item" href="edit-billing.php?id='.$row['billing_id'].'"><i class="fa fa-pencil m-r-5"></i> Edit</a>';
                                        echo '<a class="dropdown-item" href="#" onclick="return confirmDelete(\''.$row['id'].'\', \''.$patientType.'\')">
                                                <i class="fa fa-trash-o m-r-5"></i> Delete
                                            </a>';
                                    }
                                    ?>
                                </div>
                            </div>
                        </td>
                    </tr>
                    <?php } ?>
                </tbody>
            </table>
            <!-- Charges Details Table -->
            <h4 class="mt-4">Charges Details</h4>
            <table class="datatable table table-bordered table-hover" id="billingTable">
                <thead style="background-color: #CCCCCC;">
                    <tr>
                        <th>Room Charges</th>
                        <th>Laboratory Charges</th>
                        <th>Radiology Charges</th>
                        <th>Medication Charges</th>
                        <th>Operating Room Charges</th>
                        <th>Supplies Charges</th> 
                        <th>Other Charges</th> 
                        <th>Professional Fee</th>
                        <th>Reader's Fee</th>
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
                        <td><?php echo number_format($row['rad_fee'], 2); ?></td>
                        <td><?php echo number_format($row['medication_fee'], 2); ?></td>
                        <td><?php echo number_format($row['operating_room_fee'], 2); ?></td>
                        <td><?php echo $row['supplies_fee']; ?></td>
                        <td><?php echo $otherItems . ' (' . $otherCosts . ')'; ?></td>
                        <td><?php echo number_format($row['professional_fee'], 2); ?></td>
                        <td><?php echo number_format($row['readers_fee'], 2); ?></td>
                    </tr>
                    <?php } ?>
                </tbody>
            </table>
            <!-- Discount Details Table -->
            <h4 class="mt-6">Discount Details</h4>
            <table class="datatable table table-bordered table-hover" id="discountTable">
                <thead style="background-color: #CCCCCC;">
                    <tr>
                        <th>Room Discount</th>
                        <th>Laboratory Discount</th>
                        <th>Radiology Discount</th>
                        <th>Medication Discount</th>
                        <th>Operating Room Discount</th>
                        <th>Supplies Discount</th>
                        <th>Other Items Discount</th>
                        <th>Professional Fee Discount</th>
                        <th>Reader's Fee Discount</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $discount_details_query = mysqli_query($connection, $query);
                    while ($row = mysqli_fetch_array($discount_details_query)) {
                    ?>
                    <tr data-billing-id="<?php echo $row['billing_id']; ?>">
                        <td><?php echo number_format($row['room_discount'], 2); ?></td>
                        <td><?php echo number_format($row['lab_discount'], 2); ?></td>
                        <td><?php echo number_format($row['rad_discount'], 2); ?></td>
                        <td><?php echo number_format($row['med_discount'], 2); ?></td>
                        <td><?php echo number_format($row['or_discount'], 2); ?></td>
                        <td><?php echo number_format($row['supplies_discount'], 2); ?></td>
                        <td><?php echo number_format($row['other_discount'], 2); ?></td>
                        <td><?php echo number_format($row['pf_discount'], 2); ?></td>
                        <td><?php echo number_format($row['readers_discount'], 2); ?></td>
                    </tr>
                    <?php } ?>
                </tbody>
            </table>
            <!-- Amount Details Table -->
            <h4 class="mt-6">Amount Details</h4>
            <table class="datatable table table-bordered table-hover" id="amountTable">
                <thead style="background-color: #CCCCCC;">
                    <tr>
                        <th>VAT Exempt</th>
                        <th>Senior Discount</th>
                        <th>PWD Discount</th>
                        <th>PhilHealth First Case</th>
                        <th>PhilHealth Second Case</th>
                        <th>PhilHealth PF</th>
                        <th>PhilHealth HB</th>
                        <th>Subtotal</th>
                        <th>Amount Due</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    // Fetch discount details
                    $discount_query = mysqli_query($connection, $query); 
                    while ($row = mysqli_fetch_array($discount_query)) {
                    ?>
                    <tr data-billing-id="<?php echo $row['billing_id']; ?>">
                        <td><?php echo number_format($row['vat_exempt_discount_amount'], 2); ?></td>
                        <td><?php echo number_format($row['discount_amount'], 2); ?></td>      
                        <td><?php echo number_format($row['pwd_discount_amount'], 2); ?></td>
                        <td><?php echo $row['first_case']; ?></td>
                        <td><?php echo $row['second_case']; ?></td>
                        <td><?php echo number_format($row['philhealth_pf'], 2); ?></td>
                        <td><?php echo number_format($row['philhealth_hb'], 2); ?></td>
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
function confirmDelete(id, type) {
    return Swal.fire({
        title: 'Delete Billing Record?',
        text: 'Are you sure you want to delete this billing record? This action cannot be undone!',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#12369e',
        confirmButtonText: 'Yes, delete it!',
        cancelButtonText: 'Cancel'
    }).then((result) => {
        if (result.isConfirmed) {
            window.location.href = `billing.php?ids=${id}&type=${type}`;
        }
    });
}

function showTable(patientType) {
    window.location.href = `billing.php?patient_type=${patientType}`;
}

function clearSearch() {
    document.getElementById("patientSearchInput").value = '';
    filterPatients();
}

function filterPatients() {
    var input = document.getElementById("patientSearchInput");
    var filter = input.value.toUpperCase();
    var patientTable = document.getElementById("patientInfoTable");
    var patientRows = patientTable.getElementsByTagName("tr");

    // Loop through all table rows
    for (var i = 1; i < patientRows.length; i++) {
        var cells = patientRows[i].getElementsByTagName("td");
        var showRow = false;
        
        // Search through each cell in the row
        for (var j = 0; j < cells.length; j++) {
            var cell = cells[j];
            if (cell) {
                var text = cell.textContent || cell.innerText;
                if (text.toUpperCase().indexOf(filter) > -1) {
                    showRow = true;
                    break;
                }
            }
        }
        
        // Show/hide the row based on search match
        patientRows[i].style.display = showRow ? "" : "none";
        
        // Show/hide corresponding rows in other tables
        var billingId = patientRows[i].getAttribute("data-billing-id");
        updateRelatedRows(billingId, showRow);
    }
}

function updateRelatedRows(billingId, show) {
    var tables = ["billingTable", "amountTable", "discountTable"];
    tables.forEach(function(tableId) {
        var rows = document.querySelectorAll(`#${tableId} tr[data-billing-id="${billingId}"]`);
        rows.forEach(function(row) {
            row.style.display = show ? "" : "none";
        });
    });
}


function updateBillingTables(data) {
    var patientTableBody = $('#patientInfoTable tbody');
    var billingTableBody = $('#billingTable tbody');
    var discountTableBody = $('#discountTable tbody');
    var amountTableBody = $('#amountTable tbody');
    
    // Clear all tables
    patientTableBody.empty();
    billingTableBody.empty();
    discountTableBody.empty();
    amountTableBody.empty();
    
    data.forEach(function(record) {
        // Update Patient Info Table
        patientTableBody.append(`
            <tr data-billing-id="${record.patient_info.billing_id}">
                <td>${record.patient_info.billing_id}</td>
                <td>${record.patient_info.patient_id}</td>
                <td>${record.patient_info.patient_name}</td>
                <td>${record.patient_info.age}</td>
                <td>${record.patient_info.address}</td>
                <td>${record.patient_info.diagnosis}</td>
                <td>${record.patient_info.admission_date}</td>
                <td>${record.patient_info.discharge_date}</td>
                <td>${record.patient_info.transaction_date}</td>
                <td class="text-right">
                    <div class="dropdown dropdown-action">
                        <a href="#" class="action-icon dropdown-toggle" data-toggle="dropdown" aria-expanded="false">
                            <i class="fa fa-ellipsis-v"></i>
                        </a>
                        <div class="dropdown-menu dropdown-menu-right">
                            ${getActionButtons(record.patient_info.billing_id)}
                        </div>
                    </div>
                </td>
            </tr>
        `);

        // Update Charges Table
        billingTableBody.append(`
            <tr data-billing-id="${record.patient_info.billing_id}">
                <td>${record.charges.room_fee}</td>
                <td>${record.charges.lab_fee}</td>
                <td>${record.charges.rad_fee}</td>
                <td>${record.charges.medication_fee}</td>
                <td>${record.charges.operating_room_fee}</td>
                <td>${record.charges.supplies_fee}</td>
                <td>${record.charges.other_items}</td>
                <td>${record.charges.professional_fee}</td>
                <td>${record.charges.readers_fee}</td>
            </tr>
        `);

        // Update Discount Table
        discountTableBody.append(`
            <tr data-billing-id="${record.patient_info.billing_id}">
                <td>${record.discounts.room_discount}</td>
                <td>${record.discounts.lab_discount}</td>
                <td>${record.discounts.rad_discount}</td>
                <td>${record.discounts.med_discount}</td>
                <td>${record.discounts.or_discount}</td>
                <td>${record.discounts.supplies_discount}</td>
                <td>${record.discounts.other_discount}</td>
                <td>${record.discounts.pf_discount}</td>
                <td>${record.discounts.readers_discount}</td>
            </tr>
        `);

        // Update Amount Table
        amountTableBody.append(`
            <tr data-billing-id="${record.patient_info.billing_id}">
                <td>${record.amounts.vat_exempt}</td>
                <td>${record.amounts.senior_discount}</td>
                <td>${record.amounts.pwd_discount}</td>
                <td>${record.amounts.first_case}</td>
                <td>${record.amounts.second_case}</td>
                <td>${record.amounts.philhealth_pf}</td>
                <td>${record.amounts.philhealth_hb}</td>
                <td>${record.amounts.subtotal}</td>
                <td><strong>${record.amounts.total_due}</strong></td>
            </tr>
        `);
    });

    // Show/hide tables based on results
    ['billingTable', 'discountTable', 'amountTable'].forEach(tableId => {
        document.getElementById(tableId).style.display = data.length > 0 ? '' : 'none';
    });
}

function getActionButtons(billingId, type) {
    if (userRole === 1) {
        return `
            <a class="dropdown-item" href="edit-billing.php?id=${billingId}">
                <i class="fa fa-pencil m-r-5"></i> Edit
            </a>
            <a class="dropdown-item" href="#" onclick="return confirmDelete('${billingId}', '${type}')">
                <i class="fa fa-trash-o m-r-5"></i> Delete
            </a>
        `;
    }
    return '';
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
#billingTable_wrapper .dataTables_length,
#discountTable_wrapper .dataTables_length,
#amountTable_wrapper .dataTables_length{
    display: none;
}
.btn-outline-primary {
    background-color:rgb(252, 252, 252);
    color: gray;
    border: 1px solid rgb(228, 228, 228);
}
.btn-outline-primary:hover {
    background-color: #12369e;
    color: #fff;
}

.input-group-text {
    background-color:rgb(255, 255, 255);
    border: 1px solid rgb(228, 228, 228);
    color: gray;
}
.btn-secondary{
    background: #CCCCCC;
    color: black;
    border: 1px solid rgb(189, 189, 189);
}
.btn-secondary:hover {
    background:rgb(133, 133, 133);
    border: 1px solid rgb(189, 189, 189);
}
.btn-primary {
    background: #12369e;
    border: none;
}
.btn-primary:hover {
    background: #05007E;
}
.dropdown-action .action-icon {
    color: #777;
    font-size: 18px;
    display: inline-block;
    padding: 0 10px;
}

.dropdown-menu {
    border: 1px solid rgba(0, 0, 0, 0.1);
    border-radius: 3px;
    transform-origin: top right;
    box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
}

.dropdown-item {
    padding: 7px 15px;
    color: #333;
}

.dropdown-item:hover {
    background-color: #f8f9fa;
    color: #12369e;
}

.dropdown-item i {
    margin-right: 8px;
    color: #777;
}

.dropdown-item:hover i {
    color: #12369e;
}

.action-icon {
    cursor: pointer;
}
.btn-black {
    background: #c0c3c6; /* Lighter Gray */
    border: none;
    color: #212529; /* Black Text */
    box-shadow: 0 2px 4px rgba(26, 12, 12, 0.1); /* Softer Shadow */
    transition: all 0.3s ease;
}

.btn-black:hover {
    background: #b3b6b9; /* Slightly Darker Gray on Hover */
    transform: translateY(-1px);
    box-shadow: 0 4px 6px rgba(73, 80, 87, 0.3); /* Slightly deeper shadow */
}

.btn-black:active {
    transform: translateY(0);
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
}
</style>
