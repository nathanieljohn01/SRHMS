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
    
    if ($check_result->num_rows > 0) {
        // Set the message for output
        $msg = "Bed already allotted for this room and bed number combination.";
    } else {
        // Proceed with allotment and set status to "Available"
        $insert_stmt = $connection->prepare("INSERT INTO tbl_bedallocation (room_type, room_number, bed_number, status) VALUES (?, ?, ?, 'Available')");
        $insert_stmt->bind_param("sss", $room_type, $room_number, $bed_number);
        
        if ($insert_stmt->execute()) {
            // Set the message for output
            $msg = "Bed allotted successfully";
        } else {
            // Set the message for output
            $msg = "Error!";
        }
        
        $insert_stmt->close();
    }
    
    $check_stmt->close();
}
?>

<div class="page-wrapper">
    <div class="content">
        <div class="row">
            <div class="col-sm-4">
                <h4 class="page-title">Add Bed Allotment</h4>
            </div>
            <div class="col-sm-8 text-right m-b-20">
                <a href="bedallotment.php" class="btn btn-primary btn-rounded float-right">Back</a>
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