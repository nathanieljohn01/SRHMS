<?php
session_start();
if (empty($_SESSION['name'])) {
    header('location:index.php');
    exit;
}

include('header.php');
include('includes/connection.php');

// Define the sanitize function
function sanitize($connection, $input) {
    return mysqli_real_escape_string($connection, trim(htmlspecialchars($input, ENT_QUOTES, 'UTF-8')));
}

if (isset($_POST['add-bed-allotment'])) {
    // Use the sanitize function
    $room_type = sanitize($connection, $_POST['room_type']);
    $room_number = sanitize($connection, $_POST['room_number']);
    $bed_number = sanitize($connection, $_POST['bed_number']);

    // Check if the combination of room number and bed number already exists
    $check_stmt = $connection->prepare("SELECT * FROM tbl_bedallocation WHERE room_number = ? AND bed_number = ?");
    $check_stmt->bind_param("ss", $room_number, $bed_number);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    
    if ($bed_available) {
        // Update the bed allotment with sanitized values
        $update_query = $connection->prepare("UPDATE tbl_bedallocation SET room_type = ?, room_number = ?, bed_number = ? WHERE id = ?");
        $update_query->bind_param("sssi", $room_type, $room_number, $bed_number, $id);  // "s" for string, "i" for integer
    
        if ($update_query->execute()) {
            // SweetAlert success message
            echo "
            <script src='https://cdn.jsdelivr.net/npm/sweetalert2@10'></script>
            <script>
                Swal.fire({
                    icon: 'success',
                    title: 'Success!',
                    text: 'Bed allotment updated successfully',
                    confirmButtonColor: '#12369e'
                }).then(() => {
                    window.location.href = 'bed-allotment.php'; // Adjust the redirection URL if needed
                });
            </script>";
        } else {
            // SweetAlert error message
            echo "
            <script src='https://cdn.jsdelivr.net/npm/sweetalert2@10'></script>
            <script>
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'Error updating bed allotment',
                    confirmButtonColor: '#12369e'
                });
            </script>";
        }
    } else {
        // SweetAlert info message
        echo "
        <script src='https://cdn.jsdelivr.net/npm/sweetalert2@10'></script>
        <script>
            Swal.fire({
                icon: 'info',
                title: 'No Available Beds',
                text: 'No available beds in the selected category.',
                confirmButtonColor: '#12369e'
            });
        </script>";
    }
}    
?>

<div class="page-wrapper">
    <div class="content">
        <div class="row">
            <div class="col-sm-4">
                <h4 class="page-title">Add Bed Allotment</h4>
            </div>
            <div class="col-sm-8 text-right m-b-20">
                <a href="bedallotment.php" class="btn btn-primary float-right"><i class="fa fa-arrow-left"></i> Back</a>
            </div>
        </div>
        <div class="row">
            <div class="col-lg-8 offset-lg-2">
                <form method="post">
                    <div class="form-group">
                        <label>Room Type</label>
                        <input class="form-control" type="text" name="room_type" required>
                    </div>
                    <div class="form-group">
                        <label>Room Number</label>
                        <input class="form-control" type="text" name="room_number" required>
                    </div>
                    <div class="form-group">
                        <label>Bed Number</label>
                        <input class="form-control" type="text" name="bed_number" required>
                    </div>
                    <div class="m-t-20 text-center">
                        <button class="btn btn-primary submit-btn" name="add-bed-allotment">Add Bed Allotment</button>
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
    echo htmlspecialchars($msg, ENT_QUOTES, 'UTF-8');
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
</style>
<style>
  .btn-primary.submit-btn {
    border-radius: 4px; /* Mas maliit para hindi sobrang rounded */
    padding: 10px 20px;
    font-size: 16px;
  }
</style>