<?php
session_start();
if (empty($_SESSION['name'])) {
    header('location:index.php');
    exit();
}

include('header.php');
include('includes/connection.php');

$role = isset($_SESSION['role']) ? $_SESSION['role'] : null;

// Sanitize the GET parameter for deleting an invoice
if (isset($_GET['ids'])) {
    $id = filter_var($_GET['ids'], FILTER_SANITIZE_NUMBER_INT);
    if ($id) {
        // Using prepared statement to prevent SQL injection
        $update_query = mysqli_prepare($connection, "UPDATE tbl_pharmacy_invoice SET deleted = 1 WHERE invoice_id = ?");
        mysqli_stmt_bind_param($update_query, "i", $id);
        mysqli_stmt_execute($update_query);
        mysqli_stmt_close($update_query);
    }
}

// Fetch data using a safe query
$fetch_query = mysqli_query($connection, "
    SELECT invoice_id, patient_id, patient_name, 
    GROUP_CONCAT(CONCAT(medicine_name, ' - ', medicine_brand, ' (₱', price, ') - ', quantity, ' pcs (₱', price * quantity, ')') SEPARATOR '\n') as medicine_details, 
    SUM(price * quantity) as total_price, invoice_datetime 
    FROM tbl_pharmacy_invoice 
    GROUP BY invoice_id
");

?>
    <div class="page-wrapper">
        <div class="content">
            <div class="row">
                <div class="col-6">
                    <h4 class="page-title">Pharmacy Invoice</h4>
                </div>
                <div class="col-6 text-right">
                    <a href="add-pharmacy-invoice.php" class="btn btn-primary float-right"><i class="fa fa-plus"></i> Add Invoice</a>
                </div>
            </div>
            <div class="table-responsive">
                <div class="sticky-search">
                <h5 class="font-weight-bold mb-2">Search Patient:</h5>
                    <div class="input-group mb-3">
                        <div class="position-relative w-100">
                            <!-- Search Icon -->
                            <i class="fa fa-search position-absolute text-secondary" style="top: 50%; left: 12px; transform: translateY(-50%);"></i>
                            <!-- Input Field -->
                            <input class="form-control" type="text" id="medicineSearchInput" onkeyup="filterMedicines()" placeholder="Search" style="padding-left: 35px; padding-right: 35px;">
                            <!-- Clear Button -->
                            <button class="position-absolute border-0 bg-transparent text-secondary" type="button" onclick="clearSearch()" style="top: 50%; right: 10px; transform: translateY(-50%);">
                                <i class="fa fa-times"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            <div class="table-responsive">
                <table class="datatable table table-bordered table-hover" id="medicineTable">
                    <thead style="background-color: #CCCCCC">
                        <tr>
                            <th>Patient ID</th>
                            <th>Invoice ID</th>
                            <th>Patient Name</th>
                            <th>Medicine Details</th>
                            <th>Total Cost</th>
                            <th>Invoice Date and Time</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        while ($row = mysqli_fetch_array($fetch_query)) {
                            // Sanitize the output to avoid XSS attacks
                            $patient_id = htmlspecialchars($row['patient_id'], ENT_QUOTES, 'UTF-8');
                            $invoice_id = htmlspecialchars($row['invoice_id'], ENT_QUOTES, 'UTF-8');
                            $patient_name = htmlspecialchars($row['patient_name'], ENT_QUOTES, 'UTF-8');
                            $medicine_details = htmlspecialchars($row['medicine_details'], ENT_QUOTES, 'UTF-8'); // Sanitize
                            $medicine_details = nl2br($medicine_details); // Convert newlines to <br> for display
                            $total_price = htmlspecialchars($row['total_price'], ENT_QUOTES, 'UTF-8');
                            $invoice_datetime = htmlspecialchars($row['invoice_datetime'], ENT_QUOTES, 'UTF-8');
                        ?>
                        <tr>
                            <td><?php echo htmlspecialchars($row['patient_id'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?php echo htmlspecialchars($row['invoice_id'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?php echo htmlspecialchars($row['patient_name'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?php echo $medicine_details; ?></td> <!-- Display medicine details with line breaks -->
                            <td><?php echo number_format($row['total_price'], 2); ?></td>
                            <td><?php echo date('F d, Y g:i A', strtotime($row['invoice_datetime'])); ?></td>
                            <td>
                                <div class="dropdown dropdown-action">
                                    <a href="#" class="action-icon dropdown-toggle" data-toggle="dropdown" aria-expanded="false">
                                        <i class="fa <?php echo ($_SESSION['role'] == 4) ? 'fa-file-pdf' : 'fa-ellipsis-v'; ?>"></i>
                                    </a>
                                    <div class="dropdown-menu dropdown-menu-right">
                                        <?php if ($_SESSION['role'] == 4): ?>
                                            <form action="generate-pharmacy-pdf.php" method="get">
                                                <input type="hidden" name="id" value="<?php echo htmlspecialchars($row['invoice_id'], ENT_QUOTES, 'UTF-8'); ?>">
                                                <div class="form-group mb-2">
                                                    <input type="text" class="form-control" name="filename" placeholder="Filename (required)" required>
                                                </div>
                                                <button class="btn btn-primary btn-sm custom-btn" type="submit"><i class="fa fa-file-pdf m-r-5"></i> Generate Invoice</button>
                                            </form>
                                        <?php endif; ?>
                                        <?php if ($_SESSION['role'] == 1): ?>
                                            <a class="dropdown-item" href="#" onclick="return confirmDelete('<?php echo $row['invoice_id']; ?>')">
                                                <i class="fa fa-trash m-r-5"></i> Delete 
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    <?php } ?>
                </tbody>
            </table>
        </div>
    </div>
</div> 

<?php
include('footer.php');
?>
<script language="JavaScript" type="text/javascript">
    function confirmDelete(id) {
        return Swal.fire({
            title: 'Delete Invoice?',
            text: 'Are you sure you want to delete this invoice? This action cannot be undone!',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#12369e',
            confirmButtonText: 'Yes, delete it!',
            cancelButtonText: 'Cancel'
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = 'pharmacy-invoice.php?ids=' + id;
            }
        });
    }

    function clearSearch() {
        document.getElementById("medicineSearchInput").value = '';
        filterMedicines();
    }
    function filterMedicines() {
        var input = document.getElementById("medicineSearchInput").value;
        
        $.ajax({
            url: 'fetch_pharmacy.php',
            method: 'GET',
            data: { query: input },
            success: function(response) {
                var data = JSON.parse(response);
                updatePharmacyTable(data);
            }
        });
    }

    var role = <?php echo json_encode($_SESSION['role']); ?>;

    function updatePharmacyTable(data) {
        var tbody = $('#medicineTable tbody');
        tbody.empty();
        
        data.forEach(function(row) {
            var actionContent = '';
            
            if (role == 4) {
                actionContent = `
                    <form class="generate-invoice-form" action="generate-pharmacy-pdf.php" method="get">
                        <input type="hidden" name="id" value="${row.invoice_id}">
                        <div class="form-group">
                            <input type="text" class="form-control" name="filename" placeholder="Enter File Name" required>
                        </div>
                        <button class="btn btn-primary btn-block mt-1" type="submit">
                            <i class="fa fa-file-pdf m-r-5"></i> Generate Invoice
                        </button>
                    </form>
                `;
            } else if (role == 1) {
                actionContent = `
                    <div class="dropdown dropdown-action">
                        <a href="#" class="action-icon dropdown-toggle" data-toggle="dropdown" aria-expanded="false">
                            <i class="fa fa-ellipsis-v"></i>
                        </a>
                        <div class="dropdown-menu dropdown-menu-right">
                            <a class="dropdown-item delete-invoice" href="#" data-id="${row.invoice_id}">
                                <i class="fa fa-trash m-r-5"></i> Delete
                            </a>
                        </div>
                    </div>
                `;
            }
            
            tbody.append(`
                <tr>
                    <td>${row.patient_id}</td>
                    <td>${row.invoice_id}</td>
                    <td>${row.patient_name}</td>
                    <td>${row.medicine_details}</td>
                    <td>${row.total_price}</td>
                    <td>${row.invoice_datetime}</td>
                    <td>${actionContent}</td>
                </tr>
            `);
        });
    }

    function initDropdownHandlers() {
        // Handle delete button clicks
        $(document).off('click', '.delete-invoice').on('click', '.delete-invoice', function(e) {
            e.preventDefault();
            var id = $(this).data('id');
            confirmDelete(id);
        });

        // Handle form submissions to prevent default behavior
        $(document).off('submit', '.generate-invoice-form').on('submit', '.generate-invoice-form', function(e) {
            // Let the form submit normally
            return true;
        });
    }

    // Initialize handlers when page loads
    $(document).ready(function() {
        initDropdownHandlers();
        
        // Existing dropdown toggle code
        $('.dropdown-toggle').on('click', function (e) {
            var $el = $(this).next('.dropdown-menu');
            var isVisible = $el.is(':visible');
            
            // Hide all dropdowns
            $('.dropdown-menu').slideUp('400');
            
            // If this wasn't already visible, slide it down
            if (!isVisible) {
                $el.stop(true, true).slideDown('400');
            }
            
            e.stopPropagation();
        });

        // Click outside to close all dropdowns
        $(document).on('click', function (e) {
            if (!$(e.target).closest('.dropdown').length) {
                $('.dropdown-menu').slideUp('400');
            }
        });
    });
    $('#medicineTable').on('click', '.dropdown-toggle', function (e) {
        e.preventDefault(); // Prevent default action if it's a link

        var $el = $(this).next('.dropdown-menu');
        var isVisible = $el.is(':visible');

        // Hide all dropdowns
        $('.dropdown-menu').slideUp(400);

        // If this wasn't already visible, slide it down
        if (!isVisible) {
            $el.stop(true, true).slideDown(400);
        }

        // Prevent the event from bubbling to document
        e.stopPropagation();
    });

// Click outside to close all dropdowns
$(document).on('click', function (e) {
    if (!$(e.target).closest('.dropdown').length) {
        $('.dropdown-menu').slideUp(400);
    }
});
</script>

<style>
.btn-outline-primary {
    background-color:rgb(252, 252, 252);
    color: gray;
    border: 1px solid rgb(228, 228, 228);
}
.btn-outline-primary:hover {
    background-color: #12369e;
    color: #fff;
}
.btn-outline-secondary {
    color:rgb(90, 90, 90);
    border: 1px solid rgb(228, 228, 228);
}
.btn-outline-secondary:hover {
    background-color: #12369e;
    color: #fff;
}
.input-group-text {
    background-color:rgb(249, 249, 249);
    border: 1px solid rgb(212, 212, 212);
    color: gray;
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

select.form-control:focus {
    border-color: #12369e; /* Border color on focus */
    box-shadow: 0 0 0 .2rem rgba(38, 143, 255, .25); /* Shadow on focus */
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
.custom-btn {
    padding: 5px 27px; /* Adjust padding as needed */
    font-size: 12px; /* Adjust font size as needed */
}
</style>