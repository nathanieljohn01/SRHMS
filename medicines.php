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
                <a href="add-medicines.php" class="btn btn-primary btn-rounded float-right"><i class="fa fa-plus"></i> Add Medicine</a>
            </div>
        </div>
        <div class="table-responsive">
            <input class="form-control" type="text" id="medicineSearchInput" onkeyup="filterMedicines()" placeholder="Search for Medicine">
            <table class="datatable table table-hover" id="medicineTable">
                <thead style="background-color: #CCCCCC;">
                    <tr>
                        <th>Generic Name</th>
                        <th>Brand Name</th>
                        <th>Drug Classification</th>
                        <th>Weight and Measurement</th>
                        <th>Unit of Measurement</th>
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

                        $is_low_stock = $row['quantity'] <= 20;
                    ?>
                        <tr>
                            <td><?php echo $medicine_name; ?></td>
                            <td><?php echo $medicine_brand; ?></td>
                            <td><?php echo $category; ?></td>
                            <td><?php echo $weight_measure; ?></td>
                            <td><?php echo $unit_measure; ?></td>
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
                                            echo '<a class="dropdown-item" href="medicines.php?ids='.$row['id'].'" onclick="return confirmDelete()"><i class="fa fa-trash-o m-r-5"></i> Delete</a>';
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
    function confirmDelete() {
        return confirm('Are you sure you want to delete this item?');
    }

    function filterMedicines() {
        var input, filter, table, tr, td, i, txtValue;
        input = document.getElementById("medicineSearchInput");
        filter = input.value.toUpperCase();
        table = document.getElementById("medicineTable");
        tr = table.getElementsByTagName("tr");

        for (i = 0; i < tr.length; i++) {
            var matchFound = false;
            for (var j = 0; j < tr[i].cells.length; j++) {
                td = tr[i].cells[j];
                if (td) {
                    txtValue = td.textContent || td.innerText;
                    if (txtValue.toUpperCase().indexOf(filter) > -1) {
                        matchFound = true;
                        break;
                    }
                }
            }
            if (matchFound || i === 0) {
                tr[i].style.display = "";
            } else {
                tr[i].style.display = "none";
            }
        }
    }
</script>
<style>
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
