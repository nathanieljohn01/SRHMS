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

// Fetch the visitor pass data using prepared statements
$fetch_query = mysqli_prepare($connection, "SELECT * FROM tbl_visitorpass WHERE id = ?");
mysqli_stmt_bind_param($fetch_query, "s", $id);
mysqli_stmt_execute($fetch_query);
$result = mysqli_stmt_get_result($fetch_query);
$row = mysqli_fetch_array($result);
mysqli_stmt_close($fetch_query);

// Handle form submission for updating the visitor pass
if (isset($_POST['save-pass'])) {
    // Sanitize user inputs
    $visitor_name = sanitize($connection, $_POST['visitor_name']);
    $contact_number = sanitize($connection, $_POST['contact_number']);
    $purpose = sanitize($connection, $_POST['purpose']);

    // Update the visitor pass record using prepared statements
    $update_query = mysqli_prepare($connection, "UPDATE tbl_visitorpass SET visitor_name = ?, contact_number = ?, purpose = ? WHERE id = ?");
    mysqli_stmt_bind_param($update_query, 'sssi', $visitor_name, $contact_number, $purpose, $id);

    // Execute the update query
    if (mysqli_stmt_execute($update_query)) {
        $msg = "Visitor pass updated successfully";
    } else {
        $msg = "Error updating visitor pass";
    }

    // Close the prepared statement
    mysqli_stmt_close($update_query);
}
?>

<div class="page-wrapper">
    <div class="content">
        <div class="row">
            <div class="col-sm-4">
                <h4 class="page-title">Edit Visitor Pass</h4>
            </div>
            <div class="col-sm-8 text-right m-b-20">
                <a href="visitor-pass.php" class="btn btn-primary btn-rounded float-right">Back</a>
            </div>
        </div>
        <div class="row">
            <div class="col-lg-8 offset-lg-2">
                <form method="post">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Visitor ID</label>
                                <input class="form-control" type="text" name="visitor_id" value="<?php echo $row['visitor_id']; ?>" disabled>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Visitor Name</label>
                                <input class="form-control" type="text" name="visitor_name" value="<?php echo $row['visitor_name']; ?>" required>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Contact Number</label>
                                <input class="form-control" type="text" name="contact_number" value="<?php echo $row['contact_number']; ?>" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Purpose</label>
                                <input class="form-control" type="text" name="purpose" value="<?php echo $row['purpose']; ?>" disabled>
                            </div>
                        </div>
                    </div>
                    <div class="m-t-20 text-center">
                        <button class="btn btn-primary submit-btn" name="save-pass">Save Visitor Pass</button>
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