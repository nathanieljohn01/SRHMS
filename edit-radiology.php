<?php
session_start();
if (empty($_SESSION['name'])) {
    header('location:index.php');
}
include('header.php');
include('includes/connection.php');

// Fetch existing radiology data
if (isset($_GET['id'])) {
    $id = $_GET['id'];
    $fetch_query = mysqli_query($connection, "SELECT * FROM tbl_radiology WHERE id = '$id'");
    $row = mysqli_fetch_array($fetch_query);
}

// Update radiology data
if (isset($_POST['edit-radiology'])) {
    $patient_name = $_POST['patient_name'];
    $exam_type = $_POST['exam_type'];
    $step = $_POST['step'];
    $date_time = $_POST['date_time'];
    $price = $_POST['price'];

    // Retrieve the patient's ID and type based on the selected name
    $fetch_query = mysqli_query($connection, "SELECT patient_id, gender, dob, patient_type FROM tbl_patient WHERE CONCAT(first_name, ' ', last_name) = ?");
    
    // Prepare the statement to fetch patient data
    $stmt = mysqli_prepare($connection, "SELECT patient_id, gender, dob, patient_type FROM tbl_patient WHERE CONCAT(first_name, ' ', last_name) = ?");
    mysqli_stmt_bind_param($stmt, 's', $patient_name);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $row_patient = mysqli_fetch_array($result);
    $patient_id = $row_patient['patient_id'];
    $gender = $row_patient['gender'];
    $dob = $row_patient['dob'];
    $patient_type = $row_patient['patient_type'];

    // Handle image upload
    if (isset($_FILES['radiographic_image']) && $_FILES['radiographic_image']['error'] == 0) {
        $image = $_FILES['radiographic_image']['tmp_name'];
        $imageData = file_get_contents($image);

        // Prepare the statement for updating radiology data
        $update_query = mysqli_prepare($connection, "UPDATE tbl_radiology SET patient_id=?, patient_name=?, gender=?, dob=?, patient_type=?, exam_type=?, radiographic_image=?, step=?, date_time=?, price=? WHERE id=?");
        mysqli_stmt_bind_param($update_query, 'issssssssdi', $patient_id, $patient_name, $gender, $dob, $patient_type, $exam_type, $imageData, $step, $date_time, $price, $id);
    } else {
        // If no image is uploaded, update without image
        $update_query = mysqli_prepare($connection, "UPDATE tbl_radiology SET patient_id=?, patient_name=?, gender=?, dob=?, patient_type=?, exam_type=?, step=?, date_time=?, price=? WHERE id=?");
        mysqli_stmt_bind_param($update_query, 'issssssssi', $patient_id, $patient_name, $gender, $dob, $patient_type, $exam_type, $step, $date_time, $price, $id);
    }

    // Execute the update query
    $result = mysqli_stmt_execute($update_query);
    if ($result) {
        $msg = "Successfully updated!";
    } else {
        $msg = "Error updating record!";
    }

    // Close the statement
    mysqli_stmt_close($update_query);
}
?>

<div class="page-wrapper">
    <div class="content">
        <div class="row">
            <div class="col-sm-4">
                <h4 class="page-title">Edit Radiology</h4>
            </div>
            <div class="col-sm-8 text-right m-b-20">
                <a href="radiology.php" class="btn btn-primary btn-rounded float-right"><i class="fa fa-arrow-left"></i> Back</a>
            </div>
        </div>
        <form method="POST" enctype="multipart/form-data">
            <div class="row">
                <div class="col-md-6">
                    <div class="form-group">
                        <label>Patient Name</label>
                        <select class="form-control" name="patient_name" required>
                            <option value="">Select</option>
                            <?php
                            $fetch_query = mysqli_query($connection, "SELECT concat(first_name,' ',last_name) as name FROM tbl_patient");
                            while ($row_patient = mysqli_fetch_array($fetch_query)) {
                                $selected = ($row_patient['name'] == $row['patient_name']) ? 'selected' : '';
                                echo '<option value="' . $row_patient['name'] . '" ' . $selected . '>' . $row_patient['name'] . '</option>';
                            }
                            ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Exam Type</label>
                        <select class="form-control" name="exam_type" required>
                            <option value="">Select</option>
                            <option value="X-Ray" <?php if($row['exam_type'] == 'X-Ray') echo 'selected'; ?>>X-Ray</option>
                            <option value="CT-Scan" <?php if($row['exam_type'] == 'CT-Scan') echo 'selected'; ?>>CT-Scan</option>
                            <option value="MRI" <?php if($row['exam_type'] == 'MRI') echo 'selected'; ?>>MRI</option>
                            <option value="Ultrasound" <?php if($row['exam_type'] == 'Ultrasound') echo 'selected'; ?>>Ultrasound</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Step</label>
                        <input type="text" class="form-control" name="step" value="<?php echo $row['step']; ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Price</label>
                        <input type="text" class="form-control" name="price" value="<?php echo $row['price']; ?>" required>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group">
                        <label>Radiographic Image</label>
                        <input type="file" class="form-control-file" name="radiographic_image">
                        <?php if($row['radiographic_image']): ?>
                            <img src="fetch-image.php?id=<?php echo $row['id']; ?>" alt="Radiographic Image" width="105">
                        <?php endif; ?>
                    </div>
                    <div class="form-group">
                        <label>Date and Time</label>
                        <input type="datetime-local" class="form-control" name="date_time" value="<?php echo date('Y-m-d\TH:i', strtotime($row['date_time'])); ?>">
                    </div>
                </div>
            </div>
            <div class="m-t-20 text-center">
                <button type="submit" name="edit-radiology" class="btn btn-primary submit-btn"><i class="fas fa-save mr-2"></i>Update</button>
            </div>
        </form>
    </div>
</div>

<?php include('footer.php'); ?>

<script type="text/javascript">
    <?php
    if (isset($msg)) {
        echo 'swal("' . $msg . '");';
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
