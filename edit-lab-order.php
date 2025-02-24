<?php
session_start();
if (empty($_SESSION['name'])) {
    header('location:index.php');
}
include('header.php');
include('includes/connection.php');

$order_id = $_GET['id']; // Assuming you have a way to get the order ID for editing
$fetch_order_query = mysqli_query($connection, "SELECT * FROM tbl_laborder WHERE id='$order_id'");
$order_row = mysqli_fetch_assoc($fetch_order_query);

$fetch_query = mysqli_query($connection, "SELECT max(id) AS id FROM tbl_laborder");
$row = mysqli_fetch_row($fetch_query);
if ($row[0] == 0) {
    $tst_id = 1;
} else {
    $tst_id = $row[0] + 1;
}

$defaultDepartment = "Hematology";
$labTestsQuery = "SELECT id, lab_test FROM tbl_labtest WHERE lab_department = '$defaultDepartment'";
$labTestsResult = $connection->query($labTestsQuery);

if ($labTestsResult->num_rows > 0) {
    $labTests = array();
    while ($row = $labTestsResult->fetch_assoc()) {
        $labTests[] = $row;
    }
} else {
    echo "No lab tests found";
}

if (isset($_POST['save-order'])) {
    $test_id = 'TEST-' . $tst_id;
    $patient_name = $_POST['patient_name'];
    $price =  $_POST['price']; // Price will be handled as a DECIMAL value
    $lab_department = $_POST['labDepartment'];
    $lab_test = $_POST['labTest'];
    $status = $_POST['status'];

    // Prepare and bind the query to prevent SQL injection
    $update_query = mysqli_prepare($connection, "UPDATE tbl_laborder SET test_id=?, patient_name=?, price=?, lab_department=?, lab_test=?, status=? WHERE id=?");
    
    // Bind the parameters with proper types: 's' for string, 'd' for decimal, 'i' for integer
    mysqli_stmt_bind_param($update_query, 'sssdssi', $test_id, $patient_name, $price, $lab_department, $lab_test, $status, $order_id);

    // Execute the query
    if (mysqli_stmt_execute($update_query)) {
        $msg = "Lab Order updated successfully";
        $fetch_query = mysqli_query($connection, "SELECT * FROM tbl_laborder WHERE id='$order_id'");
        $order_row = mysqli_fetch_assoc($fetch_query);
    } else {
        $msg = "Error!";
    }

    // Close the prepared statement
    mysqli_stmt_close($update_query);
}
?>

<div class="page-wrapper">
    <div class="content">
        <div class="row">
            <div class="col-sm-4">
                <h4 class="page-title">Edit Lab Order</h4>
            </div>
            <div class="col-sm-8 text-right m-b-20">
                <a href="lab-order.php" class="btn btn-primary btn-rounded float-right"><i class="fa fa-arrow-left"></i> Back</a>
            </div>
        </div>
        <div class="row">
            <div class="col-lg-8 offset-lg-2">
                <form method="post">
                    <div class="form-group">
                        <label>Test ID <span class="text-danger">*</span></label>
                        <input class="form-control" type="text" name="test_id" value="<?php echo $order_row['test_id']; ?>" disabled>
                    </div>
                    <div class="form-group">
                        <label>Patient Name <span class="text-danger">*</span></label>
                        <input class="form-control" type="text" name="patient_name" value="<?php echo $order_row['patient_name']; ?>">
                    </div>
                    <div class="form-group">
                    <label>Lab Department</label>
                        <select class="form-control" name="labDepartment" id="labDepartment" required>
                        </select>
                    </div>
                    <div class="form-group">
                    <label>Lab Test</label>
                        <select class="form-control" name="labTest" id="labTest" required>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Status<span class="text-danger">*</span></label>
                        <select class="form-control" name="status" id="status">
                            <option value="">Select</option>
                            <option value="Completed">Completed</option>
                            <option value="Cancelled">Cancelled</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Price <span class="text-danger">*</span></label>
                        <input class="form-control" type="text" name="price" value="<?php echo $order_row['price']; ?>">
                    </div>
                    <div class="m-t-20 text-center">
                        <button name="save-order" class="btn btn-primary submit-btn">Save</button>
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

<script>
document.addEventListener('DOMContentLoaded', function(){
    var departmentDropdown = document.getElementById('labDepartment');
    var labtestDropdown = document.getElementById('labTest');

    var orderDepartment = "<?php echo $order_row['lab_department']; ?>";
    var orderTest = "<?php echo $order_row['lab_test']; ?>";

    //get muna si lab department
    var xhr = new XMLHttpRequest();
    xhr.onreadystatechange = function(){
        if (xhr.readyState === XMLHttpRequest.DONE) {
            if (xhr.status === 200) {
                departmentDropdown.innerHTML = xhr.responseText;

                // Setting the default value for lab department
                departmentDropdown.value = orderDepartment;
                departmentDropdown.dispatchEvent(new Event('change'));
            } else {
                console.error('Error fetching lab departments');
            }
        }
    };

    xhr.open('POST', 'get-lab-departments.php', true);
    xhr.send();

    //get lab tests
    departmentDropdown.addEventListener('change', function(){
        var selectedCategory = this.value;

        var xhrTests = new XMLHttpRequest();

        xhrTests.onreadystatechange = function(){
            if (xhrTests.readyState === XMLHttpRequest.DONE) {
                if (xhrTests.status === 200) {
                    labtestDropdown.innerHTML = xhrTests.responseText;

                    labtestDropdown.value = orderTest;
                } else {
                    console.error('Error fetching lab tests');
                }
            }
        };

        xhrTests.open('POST', 'get-lab-test.php', true);
        xhrTests.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
        xhrTests.send('category=' + encodeURIComponent(selectedCategory));
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

        select.form-control:focus {
            border-color: #12369e; /* Border color on focus */
            box-shadow: 0 0 0 .2rem rgba(38, 143, 255, .25); /* Shadow on focus */
        }
</style>