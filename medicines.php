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
    $id = intval($_GET['ids']); 
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
                        <th>Last Updated</th>
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
                        $new_added_date = strtotime($row['new_added_date']);
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
                                <div class="d-flex flex-column align-items-center">
                                    <span class="quantity-value"><?php echo $row['quantity']; ?></span>
                                    <?php if ($is_low_stock) { ?>
                                        <span class="badge <?php echo $row['quantity'] <= 10 ? 'badge-critical' : 'badge-low-stock'; ?> mt-1">
                                            <?php echo $row['quantity'] <= 10 ? 'Critical' : 'Low Stock'; ?>
                                        </span>
                                    <?php } ?>
                                </div>
                            </td>
                            <td><?php echo date('F d, Y', strtotime($row['expiration_date'])); ?></td>
                            <td>
                                <?php if ($days_to_expire <= 30 && $days_to_expire > 0): ?>
                                    <span class="badge badge-expiring"><?php echo $days_to_expire == 1 ? 'Tomorrow' : $days_to_expire.' Days'; ?></span>
                                <?php elseif ($days_to_expire <= 0): ?>
                                    <span class="badge badge-expired">Expired</span>
                                <?php else: ?>
                                    <span class="badge badge-stock"><?php echo $days_to_expire.' Days'; ?></span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo $price; ?></td>
                            <td><?php echo date('F d, Y g:i A', strtotime($row['new_added_date'])); ?></td>
                            <td class="text-right">
                                <div class="dropdown dropdown-action">
                                    <a href="#" class="action-icon dropdown-toggle" data-toggle="dropdown" aria-expanded="false"><i class="fa fa-ellipsis-v"></i></a>
                                    <div class="dropdown-menu dropdown-menu-right">
                                        <?php 
                                        if ($_SESSION['role'] == 1 || $_SESSION['role'] == 4) {
                                            echo '<a class="dropdown-item" href="edit-medicines.php?id='.$row['id'].'"><i class="fa fa-pencil m-r-5"></i> Edit</a>';
                                            echo '<a class="dropdown-item" href="#" onclick="return confirmDelete(\''.$row['id'].'\')"><i class="fa fa-trash m-r-5"></i> Delete</a>';
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
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script language="JavaScript" type="text/javascript">
    function confirmDelete(id) {
        return Swal.fire({
            title: 'Delete Medicine Record?',
            text: 'Are you sure you want to delete this Medicine record? This action cannot be undone!',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#12369e',
            confirmButtonText: 'Yes, delete it!'
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = 'medicines.php?ids=' + id;  
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
                    <td>${new Date(row.new_added_date).toLocaleString('en-US', { 
                        month: 'long',
                        day: 'numeric',
                        year: 'numeric',
                        hour: 'numeric',
                        minute: 'numeric',
                        hour12: true,
                        timeZone: 'Asia/Manila'
                    }).replace(' at ', ' ')}</td>
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
                                    <i class="fa fa-trash m-r-5"></i> Delete
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
.badge {
    padding: 6px 16px;
    border-radius: 50px;
    font-size: 12px;
    font-weight: 600;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 100px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    box-shadow: 0 2px 6px rgba(0,0,0,0.08);
    transition: all 0.3s ease;
    border: 1px solid rgba(0, 0, 0, 0.05);
    color: #333;
}

.badge:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 10px rgba(0,0,0,0.12);
}

/* Stock Status Badges */
.badge-stock {
    background-color: #f8f9fa;
    border: 1px solid #dee2e6;
    color: #495057;
}

.badge-low-stock {
    background-color: #fff3cd;
    border: 1px solid #ffecb5;
    color:rgb(173, 133, 0);
}

.badge-critical {
    background-color: #f8d7da;
    border: 1px solid #f5c6cb;
    color: #a6131b;
}

.badge-expired {
    background-color: #a6131b;
    border: 1px solid #a6131b;
    color: white;
}

.badge-expiring {
    background-color: #fd7e14;
    border: 1px solid #fd7e14;
    color: white;
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
</style>
