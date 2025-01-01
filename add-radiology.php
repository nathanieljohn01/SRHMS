<?php
session_start();
if (empty($_SESSION['name'])) {
    header('location:index.php');
}
include('header.php');
include('includes/connection.php');

$id = $_GET['id'];
$fetch_query = mysqli_prepare($connection, "SELECT * FROM tbl_radiology WHERE id = ?");
mysqli_stmt_bind_param($fetch_query, 'i', $id);  // 'i' for integer parameter
mysqli_stmt_execute($fetch_query);
$result = mysqli_stmt_get_result($fetch_query);
$row = mysqli_fetch_array($result);

// Handle the form submission for updating radiology data
if (isset($_POST['add-radiology'])) {
    // Sanitize input
    $exam_type = sanitize($connection, $_POST['exam_type']);
    $step = sanitize($connection, $_POST['step']);
    $price = sanitize($connection, $_POST['price']);

    // Handle file upload (radiographic image)
    $radiographic_image = null;
    if (isset($_FILES['radiographic_image']) && $_FILES['radiographic_image']['error'] == UPLOAD_ERR_OK) {
        // Get the file content and convert to BLOB
        $image_tmp = $_FILES['radiographic_image']['tmp_name'];
        $radiographic_image = file_get_contents($image_tmp);  // Convert image to BLOB
    }

    // Update the radiology data in the database
    $update_query = "UPDATE tbl_radiology SET exam_type = ?, step = ?, radiographic_image = ?, price = ? WHERE id = ?";

    $stmt = mysqli_prepare($connection, $update_query);
    
    // Bind the parameters (ssbd for strings, binary data, and double)
    mysqli_stmt_bind_param($stmt, "ssbdi", $exam_type, $step, $radiographic_image, $price, $id);

    // Bind the radiographic image as BLOB
    mysqli_stmt_send_long_data($stmt, 2, $radiographic_image); // The index 2 corresponds to the image field

    if (mysqli_stmt_execute($stmt)) {
        $msg = "Radiology data updated successfully!";
    } else {
        $msg = "Error: " . mysqli_error($connection);
    }
}

function sanitize($connection, $input) {
    return mysqli_real_escape_string($connection, $input);
}
?>

<div class="page-wrapper">
    <div class="content">
        <div class="row">
            <div class="col-sm-4">
                <h4 class="page-title">Insert Image</h4>
            </div>
            <div class="col-sm-8 text-right m-b-20">
                <a href="radiology.php" class="btn btn-primary btn-rounded float-right">Back</a>
            </div>
        </div>
        <form method="POST" enctype="multipart/form-data">
            <div class="row">
                <div class="col-md-6">
                    <div class="form-group">
                        <label>Exam Type</label>
                        <select class="form-control" name="exam_type" required>
                            <option value="">Select</option>
                            <option value="X-Ray">X-Ray</option>
                            <option value="CT-Scan">CT-Scan</option>
                            <option value="MRI">MRI</option>
                            <option value="Ultrasound">Ultrasound</option>
                        </select>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group">
                        <label>Radiographic Image</label>
                        <input type="file" class="form-control-file" name="radiographic_image" required>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group">
                        <label>Step</label>
                        <input type="text" class="form-control" name="step" required>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group">
                        <label>Price</label>
                        <input type="text" class="form-control" name="price" required>
                    </div>
                </div>
                <div class="col-md-12 text-center m-t-20">
                    <button type="submit" name="add-radiology" class="btn btn-primary submit-btn">Save</button>
                </div>
            </div>
        </form>
    </div>
</div>

<?php include('footer.php'); ?>

<script type="text/javascript">
    <?php
    if (isset($msg)) {
        echo 'swal("' . addslashes($msg) . '");';
    }
    ?>
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

        select.form-control:focus {
            border-color: #12369e; /* Border color on focus */
            box-shadow: 0 0 0 .2rem rgba(38, 143, 255, .25); /* Shadow on focus */
        }
</style>
