<?php
session_start();
if (empty($_SESSION['name'])) {
    header('location:index.php');
    exit;
}
include('header.php');
include('includes/connection.php');

// Ensure the 'id' parameter is sanitized and cast to an integer
$id = (int) $_GET['id'];  // Cast to integer for security

// Fetch bed allocation details using a prepared statement
$fetch_query = $connection->prepare("SELECT * FROM tbl_bedallocation WHERE id = ?");
$fetch_query->bind_param("i", $id);  // "i" for integer
$fetch_query->execute();
$result = $fetch_query->get_result();
$row = $result->fetch_array(MYSQLI_ASSOC);

if (isset($_POST['save-bed-allotment'])) {
    // Sanitize POST inputs using your sanitize function
    $room_type = sanitize($connection, $_POST['room_type']);
    $room_number = sanitize($connection, $_POST['room_number']);
    $bed_number = sanitize($connection, $_POST['bed_number']);

    // Add your bed availability check logic here
    $bed_available = true; // Replace with your actual availability check

    if ($bed_available) {
        // Update the bed allotment with sanitized values
        $update_query = $connection->prepare("UPDATE tbl_bedallocation SET room_type = ?, room_number = ?, bed_number = ? WHERE id = ?");
        $update_query->bind_param("sssi", $room_type, $room_number, $bed_number, $id);  // "s" for string, "i" for integer

        if ($update_query->execute()) {
            $msg = "Bed allotment updated successfully";
        } else {
            $msg = "Error!";
        }
    } else {
        $msg = "No available beds in the selected category.";
    }
}

// Sanitize function definition
function sanitize($connection, $input) {
    return mysqli_real_escape_string($connection, htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8'));
}
?>


<div class="page-wrapper">
    <div class="content">
        <div class="row">
            <div class="col-sm-4">
                <h4 class="page-title">Edit Bed Allotment</h4>
            </div>
            <div class="col-sm-8 text-right m-b-20">
                <a href="bedallotment.php" class="btn btn-primary btn-rounded float-right">Back</a>
            </div>
        </div>
        <div class="row">
            <div class="col-lg-8 offset-lg-2">
                <form method="post">
                    <div class="form-group">
                        <label>Room Type<span class="text-danger">*</span></label>
                        <select class="form-control" name="room_type" required>
                            <option value="OB Ward" <?php if ($row['room_type'] == 'OB Ward') echo 'selected'; ?>>OB Ward</option>
                            <option value="Surgery Ward" <?php if ($row['room_type'] == 'Surgery Ward') echo 'selected'; ?>>Surgery Ward</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Room Number<span class="text-danger">*</span></label>
                        <input class="form-control" type="text" name="room_number" value="<?php echo $row['room_number']; ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Bed Number<span class="text-danger">*</span></label>
                        <input class="form-control" type="text" name="bed_number" value="<?php echo $row['bed_number']; ?>" required>
                    </div>
                    <div class="m-t-20 text-center">
                        <button name="save-bed-allotment" class="btn btn-primary submit-btn">Save</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php
include('footer.php');
?>

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