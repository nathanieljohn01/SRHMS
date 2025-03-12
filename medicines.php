<?php
session_start();
if (empty($_SESSION['name'])) {
    header('location:index.php');
    exit();
}

include('header.php');
include('includes/connection.php');

// Sanitize GET parameters
if (isset($_GET['ids'])) {
    $id = filter_var($_GET['ids'], FILTER_SANITIZE_NUMBER_INT);
    if ($id) {
        $update_query = mysqli_prepare($connection, "UPDATE tbl_medicines SET deleted = 1 WHERE id = ?");
        mysqli_stmt_bind_param($update_query, "i", $id);
        mysqli_stmt_execute($update_query);
        mysqli_stmt_close($update_query);
    }
}

// Fetch data with prepared statement
$fetch_query = mysqli_query($connection, "SELECT * FROM tbl_medicines WHERE deleted = 0 ORDER BY expiration_date ASC");
?>

<div class="page-wrapper">
    <div class="content">
        <div class="row">
            <div class="col-sm-4 col-3">
                <h4 class="page-title">Drugs and Medication</h4>
            </div>
            <div class="col-sm-8 col-9 text-right m-b-20">
                <a href="add-medicines.php" class="btn btn-primary float-right"><i class="fa fa-plus"></i> Add Medicine</a>
            </div>
        </div>
        <div class="table-responsive">
            <div class="sticky-search">
            <h5 class="font-weight-bold mb-2">Search Medicine:</h5>
                <div class="input-group mb-3">
                    <div class="position-relative w-100">
                        <!-- Search Icon -->
                        <i class="fa fa-search position-absolute text-secondary" style="top: 50%; left: 12px; transform: translateY(-50%);"></i>
                        <!-- Input Field -->
                        <input class="form-control" type="text" id="medicineSearchInput" onkeyup="filterMedicines()" style="padding-left: 35px; padding-right: 35px;">
                        <!-- Clear Button -->
                        <button class="position-absolute border-0 bg-transparent text-secondary" type="button" onclick="clearSearch()" style="top: 50%; right: 10px; transform: translateY(-50%);">
                            <i class="fa fa-times"></i>
                        </button>
                    </div>
                </div>
            </div>
        </div>
        <div class="table-responsive">
            <table class="datatable table table-hover" id="medicineTable">
                <thead style="background-color: #CCCCCC;">
                    <tr>
                        <th>Generic Name</th>
                        <th>Brand Name</th>
                        <th>Drug Classification</th>
                        <th>Weight & Measure</th>
                        <th>Quantity</th>
                        <th>Expiry Date</th>
                        <th>Days to Expire</th>
                        <th>Price</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    while ($row = mysqli_fetch_array($fetch_query)) {
                        // Sanitize output to prevent XSS
                        $medicine_name = htmlspecialchars($row['medicine_name'], ENT_QUOTES, 'UTF-8');
                        $medicine_brand = htmlspecialchars($row['medicine_brand'], ENT_QUOTES, 'UTF-8');
                        $category = htmlspecialchars($row['category'], ENT_QUOTES, 'UTF-8');
                        $weight_measure = htmlspecialchars($row['weight_measure'], ENT_QUOTES, 'UTF-8');
                        $unit_measure = htmlspecialchars($row['unit_measure'], ENT_QUOTES, 'UTF-8');
                        $price = htmlspecialchars($row['price'], ENT_QUOTES, 'UTF-8');
                        
                        $expiration_date = strtotime($row['expiration_date']);
                        $current_date = strtotime(date('Y-m-d'));
                        $days_to_expire = round(($expiration_date - $current_date) / (60 * 60 * 24));

                        // Check if stock is low (20 or less)
                        $is_low_stock = $row['quantity'] <= 20;
                    ?>
                        <tr>
                            <td><?php echo $medicine_name; ?></td>
                            <td><?php echo $medicine_brand; ?></td>
                            <td><?php echo $category; ?></td>
                            <td>
                                <!-- Correctly display weight & measure -->
                                <?php echo $weight_measure . ' ' . $unit_measure; ?>
                            </td>
                            <td>
                                <div style="display: flex; flex-direction: column; align-items: center;">
                                    <span><?php echo $row['quantity']; ?></span>
                                    <?php if ($is_low_stock) { ?>
                                        <span class="badge badge-warning" style="font-size: 12px; margin-top: 5px;">Low Stock</span>
                                    <?php } ?>
                                </div>
                            </td>
                            <td><?php echo date('F d, Y', strtotime($row['expiration_date'])); ?></td>
                            <td>
                                <?php if ($days_to_expire <= 30 && $days_to_expire > 0): ?>
                                    <span class="badge badge-danger" style="font-size: 12px;"><?php echo $days_to_expire . ' Days'; ?></span>
                                <?php elseif ($days_to_expire <= 0): ?>
                                    <span class="badge badge-danger" style="font-size: 12px; background-color: #e74c3c; color: #fff;">Expired</span>
                                <?php else: ?>
                                    <span><?php echo $days_to_expire . ' Days'; ?></span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo $price; ?></td>
                            <td class="text-right">
                                <div class="dropdown dropdown-action">
                                    <a href="#" class="action-icon dropdown-toggle" data-toggle="dropdown" aria-expanded="false">
                                        <i class="fa fa-ellipsis-v"></i>
                                    </a>
                                    <div class="dropdown-menu dropdown-menu-right">
                                        <?php 
                                        if ($_SESSION['role'] == 1) {
                                            echo '<a class="dropdown-item" href="edit-medicines.php?id='.$row['id'].'"><i class="fa fa-pencil m-r-5"></i> Edit</a>';
                                            echo '<a class="dropdown-item" href="#" onclick="return confirmDelete(\''.$row['id'].'\')"><i class="fa fa-trash-o m-r-5"></i> Delete</a>';
                                        }
                                        ?>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    <?php 
                    } 
                    ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php
include('footer.php');
?>

<script language="JavaScript" type="text/javascript">
    // Function to confirm delete with SweetAlert2
    function confirmDelete(id) {
        showConfirm(
            'Delete Medicine?',
            'Are you sure you want to delete this medicine? This action cannot be undone!',
            () => {
                setTimeout(() => {
                    window.location.href = 'medicines.php?ids=' + id;
                }, 500);
            }
        );
    }

    // Update onclick handlers in table
    $(document).ready(function() {
        // Update delete links
        $('a[onclick*="confirm"]').each(function() {
            const href = $(this).attr('href');
            const id = href.split('=')[1];
            $(this).attr('onclick', `return confirmDelete('${id}')`);
        });
        
        // Handle search error
        $('.search-error').each(function() {
            showError($(this).text());
        });
        
        // Handle success messages
        $('.success-message').each(function() {
            showSuccess($(this).text(), true);
        });
    });

    function clearSearch() {
        document.getElementById("medicineSearchInput").value = '';
        filterMedicines();
    }

    function filterMedicines() {
        var input = document.getElementById("medicineSearchInput").value;
        
        $.ajax({
            url: 'fetch_medicines.php',
            method: 'GET',
            data: { query: input },
            success: function(response) {
                var data = JSON.parse(response);
                updateMedicinesTable(data);
            }
        });
    }

    function updateMedicinesTable(data) {
        var tbody = $('#medicineTable tbody');
        tbody.empty();
        
        data.forEach(function(row) {
            const daysToExpire = calculateDaysToExpire(row.expiration_date);
            const isLowStock = row.quantity <= 20;
            
            tbody.append(`
                <tr>
                    <td>${row.medicine_name}</td>
                    <td>${row.medicine_brand}</td>
                    <td>${row.category}</td>
                    <td>${row.weight_measure} ${row.unit_measure}</td>
                    <td>
                        <div style="display: flex; flex-direction: column; align-items: center;">
                            <span>${row.quantity}</span>
                            ${isLowStock ? '<span class="badge badge-warning" style="font-size: 12px; margin-top: 5px;">Low Stock</span>' : ''}
                        </div>
                    </td>
                    <td>${formatDate(row.expiration_date)}</td>
                    <td>${formatExpiryBadge(daysToExpire)}</td>
                    <td>${row.price}</td>
                    <td class="text-right">
                        <div class="dropdown dropdown-action">
                            <a href="#" class="action-icon dropdown-toggle" data-toggle="dropdown" aria-expanded="false">
                                <i class="fa fa-ellipsis-v"></i>
                            </a>
                            <div class="dropdown-menu dropdown-menu-right">
                                <a class="dropdown-item" href="edit-medicines.php?id=${row.id}">
                                    <i class="fa fa-pencil m-r-5"></i> Edit
                                </a>
                                <a class="dropdown-item" href="#" onclick="return confirmDelete('${row.id}')">
                                    <i class="fa fa-trash-o m-r-5"></i> Delete
                                </a>
                            </div>
                        </div>
                    </td>
                </tr>
            `);
        });
    }

    function calculateDaysToExpire(expirationDate) {
        const expiry = new Date(expirationDate);
        const today = new Date();
        const diffTime = expiry - today;
        return Math.ceil(diffTime / (1000 * 60 * 60 * 24));
    }

    function formatDate(dateString) {
        const date = new Date(dateString);
        return date.toLocaleDateString('en-US', { month: 'long', day: 'numeric', year: 'numeric' });
    }

    function formatExpiryBadge(days) {
        if (days <= 30 && days > 0) {
            return `<span class="badge badge-danger" style="font-size: 12px;">${days} Days</span>`;
        } else if (days <= 0) {
            return '<span class="badge badge-danger" style="font-size: 12px; background-color: #e74c3c; color: #fff;">Expired</span>';
        }
        return `<span>${days} Days</span>`;
    }
</script>

<?php if (isset($error_message)): ?>
    <script>showError('<?php echo addslashes($error_message); ?>');</script>
<?php endif; ?>

<?php if (isset($success_message)): ?>
    <script>showSuccess('<?php echo addslashes($success_message); ?>', true);</script>
<?php endif; ?>

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
    background-color:rgb(255, 255, 255);
    border: 1px solid rgb(228, 228, 228);
    color: gray;
}
    .btn-primary {
        background: #12369e;
        border: none;
    }
    .btn-primary:hover {
        background: #05007E;
    }
    .custom-badge {
        border-radius: 4px;
        display: inline-block;
        font-size: 12px;
        min-width: 95px;
        padding: 1px 10px;
        text-align: center;
    }
    .status-red,
    a.status-red {
        background-color: #ffe5e6;
        border: 1px solid #fe0000;
        color: #fe0000;
    }

    .low-stock {
    background-color:#f62d51; /* Light red background for low stock */
    color: #721c24; /* Dark red text color */
    }

    .low-stock-warning {
        color: #d9534f; /* Red color for low stock text */
        font-weight: bold;
        margin-left: 10px;
        font-size: 13px;
    }
    .badge-danger {
    color: #ECECEC;
    background-color: #a6131b !important;
}
</style>

<script>
$(document).ready(function() {
    // Handle form submission
    $('#medicineForm').on('submit', function(e) {
        e.preventDefault();
        
        // Basic validation
        const required = ['medicine_name', 'category', 'quantity', 'unit'];
        let isValid = true;
        let emptyFields = [];
        
        required.forEach(field => {
            if (!$(`#${field}`).val()) {
                isValid = false;
                emptyFields.push(field.replace('_', ' '));
            }
        });
        
        if (!isValid) {
            showError(`Please fill in the following fields: ${emptyFields.join(', ')}`);
            return;
        }
        
        // Validate quantity
        const quantity = $('#quantity').val();
        if (!$.isNumeric(quantity) || quantity < 0) {
            showError('Quantity must be a positive number');
            return;
        }
        
        // Show loading state
        showLoading('Saving medicine details...');
        
        // Submit the form
        this.submit();
    });
    
    // Handle stock update form
    $('#stockUpdateForm').on('submit', function(e) {
        e.preventDefault();
        
        // Validate quantity
        const quantity = $('#update_quantity').val();
        if (!$.isNumeric(quantity) || quantity < 0) {
            showError('Quantity must be a positive number');
            return;
        }
        
        // Show loading state
        showLoading('Updating stock...');
        
        // Submit the form
        this.submit();
    });
    
    // Handle delete confirmation
    $('.delete-btn').on('click', function(e) {
        e.preventDefault();
        const id = $(this).data('id');
        
        showConfirm(
            'Delete Medicine?',
            'Are you sure you want to delete this medicine? This action cannot be undone!',
            () => {
                // Show loading state
                showLoading('Deleting medicine...');
                setTimeout(() => {
                    window.location.href = 'medicines.php?ids=' + id;
                }, 500);
            }
        );
    });
    
    // Handle low stock warning
    $('.quantity-field').each(function() {
        const quantity = parseInt($(this).text());
        const threshold = 10; // Set your threshold here
        
        if (quantity <= threshold) {
            const medicineName = $(this).closest('tr').find('.medicine-name').text();
            Swal.fire({
                icon: 'warning',
                title: 'Low Stock Alert',
                text: `${medicineName} is running low on stock (${quantity} remaining)`,
                showConfirmButton: true
            });
        }
    });
    
    // Handle medicine search
    $('#medicineSearch').on('keyup', function() {
        const query = $(this).val().toLowerCase();
        $('#medicineTable tbody tr').filter(function() {
            $(this).toggle($(this).text().toLowerCase().indexOf(query) > -1);
        });
    });
    
    // Handle AJAX errors globally
    $(document).ajaxError(function(event, jqXHR, settings, error) {
        showError('Error fetching data. Please try again.');
    });
});

// Function to handle medicine deletion
function deleteMedicine(id) {
    showConfirm(
        'Delete Medicine?',
        'Are you sure you want to delete this medicine? This action cannot be undone!',
        () => {
            // Show loading state
            showLoading('Deleting medicine...');
            setTimeout(() => {
                window.location.href = 'medicines.php?ids=' + id;
            }, 500);
        }
    );
    return false;
}

// Update onclick handlers in table
$(document).ready(function() {
    // Update delete links
    $('a[onclick*="confirm"]').each(function() {
        const id = $(this).attr('href').split('=')[1];
        $(this).attr('onclick', `return deleteMedicine('${id}')`);
    });
});

// SweetAlert2 helper functions
function showSuccess(message, redirect = false) {
    Swal.fire({
        icon: 'success',
        title: 'Success',
        text: message,
        showConfirmButton: false,
        timer: 2000
    }).then(() => {
        if (redirect) {
            window.location.reload();
        }
    });
}

function showError(message) {
    Swal.fire({
        icon: 'error',
        title: 'Error',
        text: message,
        showConfirmButton: false,
        timer: 2000
    });
}

function showLoading(message) {
    Swal.fire({
        title: message,
        text: 'Please wait...',
        allowOutsideClick: false,
        showConfirmButton: false,
        willOpen: () => {
            Swal.showLoading();
        }
    });
}

function showConfirm(title, message, callback) {
    Swal.fire({
        icon: 'warning',
        title: title,
        text: message,
        showCancelButton: true,
        confirmButtonText: 'Yes',
        cancelButtonText: 'No',
        reverseButtons: true
    }).then((result) => {
        if (result.value) {
            callback();
        }
    });
}
</script>
