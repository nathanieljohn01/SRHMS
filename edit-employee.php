<?php
session_start();
if (empty($_SESSION['name'])) {
    header('location:index.php');
    exit();
}
include('header.php');
include('includes/connection.php');

// Sanitize function for input sanitization and XSS protection
function sanitize($connection, $input) {
    return mysqli_real_escape_string($connection, htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8'));
}

// Sanitize the `id` parameter
$id = sanitize($connection, $_GET['id']);

// Fetch existing employee data
$fetch_query = mysqli_query($connection, "SELECT * FROM tbl_employee WHERE id='$id'");
$row = mysqli_fetch_array($fetch_query);

$msg = ''; // Initialize $msg

// Update employee data
if (isset($_POST['save-emp'])) {
    // Sanitize user inputs
    $first_name = sanitize($connection, $_POST['first_name']);
    $last_name = sanitize($connection, $_POST['last_name']);
    $username = sanitize($connection, $_POST['username']);
    $emailid = sanitize($connection, $_POST['emailid']);
    $pwd = sanitize($connection, $_POST['pwd']);  // Password entered by user
    $dob = sanitize($connection, $_POST['dob']);
    $joining_date = sanitize($connection, $_POST['joining_date']);
    $gender = sanitize($connection, $_POST['gender']);
    $address = sanitize($connection, $_POST['address']);
    $bio = sanitize($connection, $_POST['bio']);
    $role = sanitize($connection, $_POST['role']);
    $status = isset($_POST['status']) ? sanitize($connection, $_POST['status']) : '';
    $specialization = isset($_POST['specialization']) ? sanitize($connection, $_POST['specialization']) : '';

    // Encrypt the password before saving it
    $hashed_password = password_hash($pwd, PASSWORD_BCRYPT);  // Using Bcrypt hashing

    // Handle image upload
    $profile_picture = null;
    $profile_picture_updated = false;

    if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] === UPLOAD_ERR_OK) {
        $fileTmpPath = $_FILES['profile_picture']['tmp_name'];
        $fileType = $_FILES['profile_picture']['type'];
        $fileData = file_get_contents($fileTmpPath);

        // Check for valid image file types
        $validTypes = ['image/jpeg', 'image/png', 'image/gif'];
        if (in_array($fileType, $validTypes)) {
            $profile_picture = $fileData;
            $profile_picture_updated = true;
        } else {
            $msg = "Invalid file type. Only JPEG, PNG, and GIF are allowed.";
        }
    }

    if (!$msg) {
        // Prepare the update statement
        if ($profile_picture_updated) {
            $stmt = mysqli_prepare($connection, "UPDATE tbl_employee SET first_name=?, last_name=?, specialization=?, username=?, emailid=?, password=?, dob=?, joining_date=?, gender=?, address=?, phone=?, bio=?, role=?, status=?, profile_picture=? WHERE id=?");
    
            // Bind parameters with 'b' for BLOB (image data)
            mysqli_stmt_bind_param($stmt, 'sssssssssssssssi', $first_name, $last_name, $specialization, $username, $emailid, $hashed_password, $dob, $joining_date, $gender, $address, $phone, $bio, $role, $status, $profile_picture, $id);
        } else {
            // Update employee data without profile picture
            $stmt = mysqli_prepare($connection, "UPDATE tbl_employee SET first_name=?, last_name=?, specialization=?, username=?, emailid=?, password=?, dob=?, joining_date=?, gender=?, address=?, bio=?, role=?, status=? WHERE id=?");
    
            // Bind parameters excluding 'profile_picture'
            mysqli_stmt_bind_param($stmt, 'sssssssssssssi', $first_name, $last_name, $specialization, $username, $emailid, $hashed_password, $dob, $joining_date, $gender, $address, $bio, $role, $status, $id);
        }
    
        // Execute the query and check for errors
        if (mysqli_stmt_execute($stmt)) {
            $msg = "Employee updated successfully";
    
            // SweetAlert success message
            echo "
            <script src='https://cdn.jsdelivr.net/npm/sweetalert2@10'></script>
            <script>
                document.addEventListener('DOMContentLoaded', function() {
                    Swal.fire({
                        icon: 'success',
                        title: 'Success',
                        text: 'Employee updated successfully',
                        confirmButtonColor: '#12369e'
                    }).then(() => {
                        window.location.href = 'employees.php'; // Adjust the page to redirect to after success
                    });
                });
            </script>";
        } else {
            $msg = "Error: " . mysqli_error($connection);
    
            // SweetAlert error message
            echo "
            <script src='https://cdn.jsdelivr.net/npm/sweetalert2@10'></script>
            <script>
                document.addEventListener('DOMContentLoaded', function() {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: 'Error updating employee: " . mysqli_error($connection) . "',
                    });
                });
            </script>";
        }
    
        mysqli_stmt_close($stmt);
    }    
}
?>

<div class="page-wrapper">
    <div class="content">
        <div class="row">
            <div class="col-sm-4">
                <h4 class="page-title">Edit Employee</h4>
            </div>
            <div class="col-sm-8 text-right mb-3">
                <a href="employees.php" class="btn btn-primary float-right"><i class="fa fa-arrow-left"></i> Back</a>
            </div>
        </div>
        <div class="row">
            <div class="col-lg-8 offset-lg-2">
                <form method="post" enctype="multipart/form-data">
                    <div class="row">
                        <!-- Profile Picture -->
                        <div class="col-sm-12">
                            <div class="form-group profile-picture-group">
                                <label>Profile Picture</label>
                                <input type="file" class="form-control-file" name="profile_picture">
                                <?php if ($row['profile_picture']): ?>
                                    <img src="fetch-image-employee.php?id=<?php echo $row['id']; ?>" 
                                        alt="Employee Profile" width="105">
                                <?php endif; ?>
                            </div>
                        </div>
                  
                        <!-- First Name -->
                        <div class="col-sm-6">
                            <div class="form-group">
                                <label>First Name <span class="text-danger">*</span></label>
                                <input class="form-control" type="text" name="first_name" value="<?php echo $row['first_name']; ?>">
                            </div>
                        </div>
                        
                        <!-- Last Name -->
                        <div class="col-sm-6">
                            <div class="form-group">
                                <label>Last Name</label>
                                <input class="form-control" type="text" name="last_name" value="<?php echo $row['last_name']; ?>">
                            </div>
                        </div>
                        
                        <!-- Username -->
                        <div class="col-sm-6">
                            <div class="form-group">
                                <label>Username <span class="text-danger">*</span></label>
                                <input class="form-control" type="text" name="username" value="<?php echo $row['username']; ?>">
                            </div>
                        </div>
                        
                        <!-- Email -->
                        <div class="col-sm-6">
                            <div class="form-group">
                                <label>Email <span class="text-danger">*</span></label>
                                <input class="form-control" type="email" name="emailid" value="<?php echo $row['emailid']; ?>">
                            </div>
                        </div>
                        
                        <!-- Password -->
                        <div class="col-sm-6">
                            <div class="form-group">
                                <label>Password</label>
                                <input class="form-control" type="password" name="pwd" value="<?php echo $row['password']; ?>">
                            </div>
                        </div>
                        
                        <!-- Specialization (Conditional) -->
                        <?php if ($row['role'] == 2): ?>
                        <div class="col-sm-6">
                            <div class="form-group">
                                <label>Specialization</label>
                                <input class="form-control" type="text" name="specialization" value="<?php echo $row['specialization']; ?>">
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <!-- Joining Date -->
                        <div class="col-sm-6">
                            <div class="form-group">
                                <label>Joining Date</label>
                                <input class="form-control datetimepicker" name="joining_date" value="<?php echo $row['joining_date']; ?>">
                            </div>
                        </div>
                        
                        <!-- Date of Birth -->
                        <div class="col-sm-6">
                            <div class="form-group">
                                <label>Date of Birth <span class="text-danger">*</span></label>
                                <input class="form-control datetimepicker" name="dob" required value="<?php echo $row['dob']; ?>">
                            </div>
                        </div>
                                    
                        <!-- Gender -->
                        <div class="col-sm-12">
                            <div class="form-group gender-select">
                                <label>Gender:</label>
                                <div class="form-check form-check-inline">
                                    <input type="radio" name="gender" class="form-check-input" value="Male" <?php if($row['gender'] == 'Male') echo 'checked'; ?>> Male
                                </div>
                                <div class="form-check form-check-inline">
                                    <input type="radio" name="gender" class="form-check-input" value="Female" <?php if($row['gender'] == 'Female') echo 'checked'; ?>> Female
                                </div>
                            </div>
                        </div>
                        
                        <!-- Address -->
                        <div class="col-sm-6">
                            <div class="form-group">
                                <label>Address</label>
                                <input type="text" class="form-control" name="address" value="<?php echo $row['address']; ?>">
                            </div>
                        </div>

                        <!-- Role -->
                        <div class="col-sm-6">
                            <div class="form-group">
                                <label>Role</label>
                                <select class="form-control" name="role">
                                    <option value="">Select</option>
                                    <?php
                                    $fetch_query = mysqli_query($connection, "SELECT title, role FROM tbl_role");
                                    while($role = mysqli_fetch_array($fetch_query)) {
                                    ?>
                                    <option value="<?php echo $role['role']; ?>" <?php if($role['role'] == $row['role']) echo 'selected'; ?>>
                                        <?php echo $role['title']; ?>
                                    </option>
                                    <?php } ?>
                                </select>
                            </div>
                        </div>
                        
                        <!-- Short Biography -->
                        <div class="col-sm-12">
                            <div class="form-group">
                                <label>Short Biography</label>
                                <textarea class="form-control" rows="3" name="bio"><?php echo $row['bio']; ?></textarea>
                            </div>
                        </div>
                        
                        <!-- Status -->
                        <div class="col-sm-12">
                            <div class="form-group">
                                <label>Status:</label>
                                <div class="d-flex align-items-center">
                                    <div class="form-check form-check-inline">
                                        <input class="form-check-input" type="radio" name="status" id="active" value="1" 
                                            <?php if($row['status'] == 1) echo 'checked'; ?>>
                                        <label class="form-check-label" for="active">Active</label>
                                    </div>
                                    <div class="form-check form-check-inline">
                                        <input class="form-check-input" type="radio" name="status" id="inactive" value="0" 
                                            <?php if($row['status'] == 0) echo 'checked'; ?>>
                                        <label class="form-check-label" for="inactive">Inactive</label>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Submit Button -->
                        <div class="col-sm-12 text-center mt-3">
                            <button class="btn btn-primary submit-btn" name="save-emp"><i class="fas fa-save mr-2"></i>Update</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php 
include('footer.php');
?>
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

.cal-icon {
    position: relative;
}

.cal-icon input {
    padding-right: 30px; /* Adjust the padding to make space for the icon */
}

.cal-icon::after {
    content: '\f073'; /* FontAwesome calendar icon */
    font-family: 'FontAwesome';
    position: absolute;
    right: 10px; /* Adjust this value to align the icon properly */
    top: 50%;
    transform: translateY(-50%);
    pointer-events: none;
    color: #aaa; /* Adjust color as needed */
}
.time-icon {
position: relative;
}

.time-icon input {
    padding-right: 30px; /* Adjust the padding to make space for the icon */
}

.time-icon::after {
    position: absolute;
    right: 10px; /* Adjust this value to align the icon properly */
    top: 50%;
    transform: translateY(-50%);
    pointer-events: none;
    color: #aaa; /* Adjust color as needed */
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
/* Align the label properly */
.gender-select label {
    display: block;
    margin-bottom: 5px;
}

/* Ensure radio buttons are inline but aligned properly */
.gender-select .form-check-inline {
    display: flex;
    align-items: center;
    gap: 5px; /* Add spacing between radio button and label */
}

/* Adjust margin for better spacing */
.gender-select .form-check {
    margin-bottom: 0;
}

/* Ensure label and input are aligned properly */
.profile-picture-group {
    display: flex;
    flex-direction: column;
    align-items: start;
    gap: 12px;
    padding: 10px;
}

.profile-picture-group img {
    display: block;
    max-width: 200px;
    height: auto;
    border-radius: 6px;
    border: 1px solid #e0e0e0;
}
</style>
