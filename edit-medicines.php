<?php
session_start();
if (empty($_SESSION['name'])) {
    header('location:index.php');
    exit();
}

include('header.php');
include('includes/connection.php');

// Function to sanitize inputs
function sanitize($connection, $input) {
    return mysqli_real_escape_string($connection, htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8'));
}

$id = sanitize($connection, $_GET['id']);

// Fetch existing medicine data
$fetch_query = mysqli_query($connection, "SELECT * FROM tbl_medicines WHERE id='$id'");
$row = mysqli_fetch_array($fetch_query);

$msg = ''; // Initialize message variable

if (isset($_POST['save-medicine'])) {
    // Sanitize user inputs
    $medicine_name = sanitize($connection, $_POST['medicine_name']);
    $medicine_brand = sanitize($connection, $_POST['medicine_brand']);
    $category = sanitize($connection, $_POST['category']);
    $weight_measure = sanitize($connection, $_POST['weight_measure']);
    $unit_measure = sanitize($connection, $_POST['unit_measure']);
    $quantity = sanitize($connection, $_POST['quantity']);
    $expiration_date = sanitize($connection, $_POST['expiration_date']);
    $price = sanitize($connection, $_POST['price']);

    // Prepare the update query without bind_param
    $update_query = "UPDATE tbl_medicines SET medicine_name = '$medicine_name', medicine_brand = '$medicine_brand', category = '$category', weight_measure = '$weight_measure', unit_measure = '$unit_measure', quantity = '$quantity', expiration_date = '$expiration_date', price = '$price' WHERE id = '$id'";

    // Execute the query and check if it was successful
    if (mysqli_query($connection, $update_query)) {
        // Display SweetAlert success message
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
                    text: 'Medicine updated successfully',
                    confirmButtonColor: '#12369e'
                }).then(() => {
                    window.location.href = 'medicines.php';
                });
            });
        </script>";
    } else {
        // Display SweetAlert error message
        echo "<script>
            document.addEventListener('DOMContentLoaded', function() {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'Error updating medicine record: " . mysqli_error($connection) . "'
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
                <h4 class="page-title">Edit Medicine</h4>
            </div>
            <div class="col-sm-8 text-right m-b-20">
                <a href="medicines.php" class="btn btn-primary float-right"><i class="fa fa-arrow-left"></i> Back</a>
            </div>
        </div>
        <div class="row">
            <div class="col-lg-8 offset-lg-2">
                <form method="post">
                    <div class="form-group">
                        <label>Generic Name<span class="text-danger">*</span></label>
                        <input class="form-control" type="text" name="medicine_name" value="<?php echo $row['medicine_name']; ?>">
                    </div>
                    <div class="form-group">
                        <label>Brand Name<span class="text-danger">*</span></label>
                        <input class="form-control" type="text" name="medicine_brand" value="<?php echo $row['medicine_brand']; ?>">
                    </div>
                    <div class="form-group">
                        <label>Drug Classification<span class="text-danger">*</span></label>
                        <input class="form-control" type="text" name="category" value="<?php echo $row['category']; ?>">
                    </div>
                    <div class="form-group">
                        <label>Weight and Measurement<span class="text-danger">*</span></label>
                        <input class="form-control" type="text" name="weight_measure" value="<?php echo $row['weight_measure']; ?>">
                    </div>
                    <div class="form-group">
                        <label>Unit of Measurement<span class="text-danger">*</span></label>
                        <select class="form-control" name="unit_measure">
                            <option value="">Select</option>
                            <option value="kg" <?php if ($row['unit_measure'] == 'kg') echo 'selected'; ?>>Kg</option>
                            <option value="g" <?php if ($row['unit_measure'] == 'g') echo 'selected'; ?>>g</option>
                            <option value="mg" <?php if ($row['unit_measure'] == 'mg') echo 'selected'; ?>>mg</option>
                            <option value="mcg" <?php if ($row['unit_measure'] == 'mcg') echo 'selected'; ?>>mcg</option>
                            <option value="l" <?php if ($row['unit_measure'] == 'l') echo 'selected'; ?>>L</option>
                            <option value="ml" <?php if ($row['unit_measure'] == 'ml') echo 'selected'; ?>>ml</option>
                            <option value="cc" <?php if ($row['unit_measure'] == 'cc') echo 'selected'; ?>>cc</option>
                            <option value="mol" <?php if ($row['unit_measure'] == 'mol') echo 'selected'; ?>>mol</option>
                            <option value="mmol" <?php if ($row['unit_measure'] == 'mmol') echo 'selected'; ?>>mmol</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Quantity</label>
                        <input class="form-control" type="number" name="quantity" value="<?php echo $row['quantity']; ?>">
                    </div>
                    <div class="form-group">
                        <label for="expiry_date">Expiration Date</label>
                        <div class="input-group date" id="expiry_date" data-target-input="nearest">
                            <input type="text" class="form-control datetimepicker-input" data-target="#expiry_date" name="expiration_date" value="<?php echo $row['expiration_date']; ?>"/>
                            <div class="input-group-append" data-target="#expiry_date" data-toggle="datetimepicker">
                                <div class="input-group-text"><i class="fa fa-calendar"></i></div>
                            </div>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Price</label>
                        <input class="form-control" type="text" name="price" value="<?php echo $row['price']; ?>">
                    </div>
                    <div class="m-t-20 text-center">
                        <button name="save-medicine" class="btn btn-primary submit-btn">Save</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php include('footer.php'); ?>

<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
<script src="https://maxcdn.bootstrapcdn.com/bootstrap/4.0.0/js/bootstrap.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/moment.js/2.29.1/moment.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/tempusdominus-bootstrap-4/5.39.0/js/tempusdominus-bootstrap-4.min.js"></script>

<?php
if (isset($msg)) {
    echo 'swal("' . $msg . '");';
}
?>

<script type="text/javascript">
    $(function () {
        $('#expiry_date').datetimepicker({
            format: 'YYYY-MM-DD',
        });
    });
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
        border-radius: .375rem;
        border-color: #ced4da;
        background-color: #f8f9fa;
    }
    select.form-control {
        border-radius: .375rem;
        border: 1px solid;
        border-color: #ced4da;
        background-color: #f8f9fa;
        padding: .375rem 2.5rem .375rem .75rem;
        font-size: 1rem;
        line-height: 1.5;
        height: calc(2.25rem + 2px);
        -webkit-appearance: none;
        -moz-appearance: none;
        appearance: none;
        background: url('data:image/svg+xml;charset=UTF-8,%3Csvg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="20" height="20"%3E%3Cpath d="M7 10l5 5 5-5z" fill="%23aaa"/%3E%3C/svg%3E') no-repeat right 0.75rem center;
        background-size: 20px;
    }
    select.form-control:focus {
        border-color: #12369e;
        box-shadow: 0 0 0 .2rem rgba(38, 143, 255, .25);
    }
</style>
