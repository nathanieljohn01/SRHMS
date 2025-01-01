<?php
session_start();
if (empty($_SESSION['name'])) {
    header('location:index.php');
}
include('header.php');
include('includes/connection.php');

if (isset($_REQUEST['add-medicine'])) {
    // Sanitize user input using mysqli_real_escape_string
    $medicine_name = mysqli_real_escape_string($connection, $_REQUEST['medicine_name']);
    $medicine_brand = mysqli_real_escape_string($connection, $_REQUEST['medicine_brand']);
    $category = mysqli_real_escape_string($connection, $_REQUEST['category']);
    $weight_measure = mysqli_real_escape_string($connection, $_REQUEST['weight_measure']);
    $unit_measure = mysqli_real_escape_string($connection, $_REQUEST['unit_measure']);
    $quantity = mysqli_real_escape_string($connection, $_REQUEST['quantity']);
    $expiration_date = mysqli_real_escape_string($connection, $_REQUEST['expiration_date']);
    $price = mysqli_real_escape_string($connection, $_REQUEST['price']);

    // Validate numeric fields
    if (!is_numeric($quantity)) {
        $msg = "Quantity must be a valid number.";
    } elseif (!is_numeric($price)) {
        $msg = "Price must be a valid number.";
    } else {
        // Prepare the SQL query to insert medicine data
        $insert_query = "INSERT INTO tbl_medicines (medicine_name, medicine_brand, category, weight_measure, unit_measure, quantity, expiration_date, price, new_added_date) 
                         VALUES ('$medicine_name', '$medicine_brand', '$category', '$weight_measure', '$unit_measure', '$quantity', '$expiration_date', '$price', current_timestamp)";

        // Execute the query and check if the insertion was successful
        if (mysqli_query($connection, $insert_query)) {
            $msg = "Medicine added successfully";
        } else {
            $msg = "Error: " . mysqli_error($connection);
        }
    }
}
?>

<div class="page-wrapper">
    <div class="content">
        <div class="row">
            <div class="col-sm-4">
                <h4 class="page-title">Add Medicine</h4>
            </div>
            <div class="col-sm-8 text-right m-b-20">
                <a href="medicines.php" class="btn btn-primary btn-rounded float-right">Back</a>
            </div>
        </div>
        <div class="row">
            <div class="col-lg-8 offset-lg-2">
                <form method="post">
                    <div class="form-group">
                        <label>Generic Name</label>
                        <input class="form-control" type="text" name="medicine_name" required>
                    </div>
                    <div class="form-group">
                        <label>Brand Name</label>
                        <input class="form-control" type="text" name="medicine_brand" required>
                    </div>
                    <div class="form-group">
                        <label>Drug Classification</label>
                        <input class="form-control" type="text" name="category" required>
                    </div>
                    <div class="form-group">
                        <label>Weight and Measurement</label>
                        <input class="form-control" type="text" name="weight_measure" required>
                    </div>
                    <div class="form-group">
                        <label>Unit of Measurement</label>
                        <select class="form-control" name="unit_measure">
                            <option value="">Select</option>
                            <option value="kg">Kg</option>
                            <option value="g">g</option>
                            <option value="mg">mg</option>
                            <option value="mcg">mcg</option>
                            <option value="l">L</option>
                            <option value="ml">ml</option>
                            <option value="cc">cc</option>
                            <option value="mol">mol</option>
                            <option value="mmol">mmol</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Quantity</label>
                        <input class="form-control" type="number" name="quantity" required>
                    </div>
                    <div class="form-group">
                        <label for="expiry_date">Expiration Date</label>
                        <div class="input-group date" id="expiry_date" data-target-input="nearest">
                            <input type="text" class="form-control datetimepicker-input" data-target="#expiry_date" name="expiration_date"/>
                            <div class="input-group-append" data-target="#expiry_date" data-toggle="datetimepicker">
                                <div class="input-group-text"><i class="fa fa-calendar"></i></div>
                            </div>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Price</label>
                        <input class="form-control" type="text" name="price" required>
                    </div>
                    <div class="m-t-20 text-center">
                        <button class="btn btn-primary submit-btn" name="add-medicine">Add Medicine</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php
include('footer.php');
?>

<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
<script src="https://maxcdn.bootstrapcdn.com/bootstrap/4.0.0/js/bootstrap.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/moment.js/2.29.1/moment.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/tempusdominus-bootstrap-4/5.39.0/js/tempusdominus-bootstrap-4.min.js"></script>

<script type="text/javascript">
    <?php
    if (isset($msg)) {
        echo 'swal("' . $msg . '");';
    }
    ?>
</script>

<script type="text/javascript">
    $(function () {
      // Initialize DateTimePicker with date format
      $('#expiry_date').datetimepicker({
        format: 'YYYY-MM-DD' ,
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
</style>
