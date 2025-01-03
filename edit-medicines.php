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
$expiryDate = $row['expiration_date'];

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

    // Prepare the update query using a prepared statement
    $stmt = mysqli_prepare($connection, "UPDATE tbl_medicines SET medicine_name = ?, medicine_brand = ?, category = ?, weight_measure = ?, unit_measure = ?, quantity = ?, expiration_date = ?, price = ? WHERE id = ?");

    // Bind parameters
    mysqli_stmt_bind_param($stmt, 'ssssssdsd', $medicine_name, $medicine_brand, $category, $weight_measure, $unit_measure, $quantity, $expiration_date, $price, $id);

    // Execute the query and check if it was successful
    if (mysqli_stmt_execute($stmt)) {
        $msg = "Medicine updated successfully";
    } else {
        $msg = "Error updating medicine record: " . mysqli_error($connection);
    }

    // Close the statement
    mysqli_stmt_close($stmt);
}
?>

<div class="page-wrapper">
    <div class="content">
        <div class="row">
            <div class="col-sm-4">
                <h4 class="page-title">Edit Medicine</h4>
            </div>
            <div class="col-sm-8 text-right m-b-20">
                <a href="medicines.php" class="btn btn-primary btn-rounded float-right">Back</a>
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
                            <input type="text" class="form-control datetimepicker-input" data-target="#expiry_date" name="expiration_date" value="<?php echo $expiryDate; ?>"/>
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

<script type="text/javascript">
    <?php if (isset($msg)) { echo 'swal("' . $msg . '");'; } ?>
</script>

<script type="text/javascript">
    $(function () {
        $('#expiry_date').datetimepicker({
            format: 'YYYY-MM-DD',
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
