<?php
session_start();
if (empty($_SESSION['name'])) {
    header('location:index.php');
    exit();
}
include('header.php');
include('includes/connection.php');

function sanitize($connection, $input) {
    return mysqli_real_escape_string($connection, trim(htmlspecialchars($input, ENT_QUOTES, 'UTF-8')));
}

if (isset($_POST['add-employee'])) {
    // Sanitize all inputs
    $employee_data = [
        'first_name' => sanitize($connection, $_POST['first_name']),
        'last_name' => sanitize($connection, $_POST['last_name']),
        'username' => sanitize($connection, $_POST['username']),
        'emailid' => sanitize($connection, $_POST['emailid']),
        'pwd' => password_hash(sanitize($connection, $_POST['pwd']), PASSWORD_DEFAULT),
        'dob' => sanitize($connection, $_POST['dob']),
        'joining_date' => sanitize($connection, $_POST['joining_date']),
        'gender' => sanitize($connection, $_POST['gender']),
        'address' => sanitize($connection, $_POST['address']),
        'specialization' => sanitize($connection, $_POST['specialization']),
        'bio' => sanitize($connection, $_POST['bio']),
        'role' => sanitize($connection, $_POST['role']),
        'status' => sanitize($connection, $_POST['status'])
    ];

    // Handle profile picture upload
    $profile_picture = null;
    $upload_error = null;
    
    if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] === UPLOAD_ERR_OK) {
        // Validate file
        $allowed_types = ['image/jpeg' => 'jpg', 'image/jpg' => 'jpg', 'image/png' => 'png', 'image/bmp' => 'bmp'];
        $file_type = $_FILES['profile_picture']['type'];
        $file_size = $_FILES['profile_picture']['size'];
        $max_size = 1 * 1024 * 1024; // 1MB

        if (array_key_exists($file_type, $allowed_types)) {
            if ($file_size <= $max_size) {
                // Generate unique filename
                $file_ext = $allowed_types[$file_type];
                $new_filename = uniqid('profile_', true) . '.' . $file_ext;
                $upload_path = 'uploads/profiles/' . $new_filename;
                
                // Move uploaded file
                if (move_uploaded_file($_FILES['profile_picture']['tmp_name'], $upload_path)) {
                    // Store only the path in database (recommended approach)
                    $profile_picture = $upload_path;
                } else {
                    $upload_error = "Failed to move uploaded file";
                }
            } else {
                $upload_error = "File size exceeds 1MB limit";
            }
        } else {
            $upload_error = "Only JPEG, JPG, BMP, and PNG files are allowed";
        }
    } else {
        $upload_error = $_FILES['profile_picture']['error'] ?? 'No file uploaded';
    }

    if ($upload_error) {
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Upload Error',
                text: '".addslashes($upload_error)."'
            });
        </script>";
    } else {
        // Prepare SQL (storing file path instead of BLOB)
        $stmt = $connection->prepare("INSERT INTO tbl_employee 
            (first_name, last_name, specialization, username, emailid, password, dob, 
             joining_date, gender, address, bio, role, status, profile_picture) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        
        $stmt->bind_param("sssssssssssssss", 
            $employee_data['first_name'], 
            $employee_data['last_name'], 
            $employee_data['specialization'], 
            $employee_data['username'], 
            $employee_data['emailid'], 
            $employee_data['pwd'], 
            $employee_data['dob'], 
            $employee_data['joining_date'], 
            $employee_data['gender'], 
            $employee_data['address'], 
            $employee_data['bio'], 
            $employee_data['role'], 
            $employee_data['status'], 
            $profile_picture);

        if ($stmt->execute()) {
            $msg = "Employee created successfully!";
            echo "<script>
                Swal.fire({
                    icon: 'success',
                    title: 'Success',
                    text: '".addslashes($msg)."'
                }).then(() => {
                    window.location.href = 'employees.php';
                });
            </script>";
        } else {
            // Delete uploaded file if database insert failed
            if ($profile_picture && file_exists($profile_picture)) {
                unlink($profile_picture);
            }
            
            echo "<script>
                Swal.fire({
                    icon: 'error',
                    title: 'Database Error',
                    text: '".addslashes($stmt->error)."'
                });
            </script>";
        }
        
        $stmt->close();
    }
}
?>

<div class="page-wrapper">
    <div class="content">
        <div class="row">
            <div class="col-sm-4">
                <h4 class="page-title">Add Employee</h4>
            </div>
            <div class="col-sm-8 text-right m-b-20">
                <a href="employees.php" class="btn btn-primary float-right"><i class="fa fa-arrow-left"></i> Back</a>
            </div>
        </div>
        <div class="row">
            <div class="col-lg-8 offset-lg-2">
                <form method="post" enctype="multipart/form-data">
                    <div class="row">
                        <!-- Profile Picture -->
                        <div class="col-sm-6">
                            <div class="form-group">
                                <label>Profile Picture</label>
                                <input type="file" class="form-control-file" name="profile_picture" required
                                    accept="image/jpeg,image/jpg,image/png,image/bmp">
                                <small class="form-text text-muted">Required. Image must be less than 1MB in size.</small>
                            </div>
                        </div>

                        <!-- First Name -->
                        <div class="col-sm-6">
                            <div class="form-group">
                                <label>First Name <span class="text-danger">*</span></label>
                                <input class="form-control" type="text" name="first_name" required>
                            </div>
                        </div>
                        <!-- Last Name -->
                        <div class="col-sm-6">
                            <div class="form-group">
                                <label>Last Name</label>
                                <input class="form-control" type="text" name="last_name">
                            </div>
                        </div>
                        <!-- Username -->
                        <div class="col-sm-6">
                            <div class="form-group">
                                <label>Username <span class="text-danger">*</span></label>
                                <input class="form-control" type="text" name="username" required>
                            </div>
                        </div>
                        <!-- Email -->
                        <div class="col-sm-6">
                            <div class="form-group">
                                <label>Email <span class="text-danger">*</span></label>
                                <input class="form-control" type="email" name="emailid" required>
                            </div>
                        </div>
                        <!-- Password -->
                        <div class="col-sm-6">
                            <div class="form-group">
                                <label>Password</label>
                                <input class="form-control" type="password" name="pwd" required>
                            </div>
                        </div>                
                        <!-- Joining Date -->
                        <div class="col-sm-6">
                            <div class="form-group">
                                <label>Joining Date <span class="text-danger">*</span></label>
                                <div class="cal-icon">
                                    <input class="form-control datetimepicker" type="text" name="joining_date" required>
                                </div>
                            </div>
                        </div>
                        <!-- Date of Birth -->
                        <div class="col-sm-6">
                            <div class="form-group">
                                <label>Date of Birth <span class="text-danger">*</span></label>
                                <div class="cal-icon">
                                    <input class="form-control datetimepicker" type="text" name="dob" required>
                                </div>
                            </div>
                        </div>
                        <!-- Gender -->
                        <div class="col-sm-6">
                            <div class="form-group gender-select">
                                <label class="gen-label">Gender:</label>
                                <div class="form-check-inline">
                                    <label class="form-check-label">
                                        <input type="radio" name="gender" class="form-check-input" value="Male"> Male
                                    </label>
                                </div>
                                <div class="form-check-inline">
                                    <label class="form-check-label">
                                        <input type="radio" name="gender" class="form-check-input" value="Female"> Female
                                    </label>
                                </div>
                            </div>
                        </div>
                        <!-- Role -->
                        <div class="col-sm-6">
                            <div class="form-group">
                                <label>Role</label>
                                <select class="form-control"  name="role" id="role" onchange="toggleSpecialization()" required>
                                    <option value="">Select</option>
                                    <?php
                                    $fetch_query = mysqli_query($connection, "SELECT title, role FROM tbl_role");
                                    while($roleData = mysqli_fetch_array($fetch_query)) { 
                                    ?>
                                    <option value="<?php echo $roleData['role']; ?>"><?php echo $roleData['title']; ?></option>
                                    <?php } ?>
                                </select>
                            </div>
                        </div>
                        <!-- Address -->
                        <div class="col-sm-12">
                            <div class="form-group">
                                <label>Address</label>
                                <input type="text" class="form-control" name="address" required>
                            </div>
                        </div>
                        <!-- Short Biography -->
                        <div class="col-sm-12">
                            <div class="form-group">
                                <label>Short Biography</label>
                                <textarea class="form-control" rows="3" cols="30" name="bio"></textarea>
                            </div>
                        </div>
                        <!-- Specialization (conditionally displayed) -->
                        <div class="col-sm-6" id="specializationField" style="display: none;">
                            <div class="form-group">
                                <label>Specialization</label>
                                <input class="form-control" type="text" name="specialization">
                            </div>
                        </div>
                    </div>
                    <!-- Status -->
                    <div class="form-group">
                        <label class="display-block">Status</label>
                        <div class="form-check form-check-inline">
                            <input class="form-check-input" type="radio" name="status" id="employee_active" value="1" checked>
                            <label class="form-check-label" for="employee_active">Active</label>
                        </div>
                        <div class="form-check form-check-inline">
                            <input class="form-check-input" type="radio" name="status" id="employee_inactive" value="0">
                            <label class="form-check-label" for="employee_inactive">Inactive</label>
                        </div>
                    </div>
                    <div class="m-t-20 text-center">
                        <button class="btn btn-primary submit-btn" name="add-employee">Create Employee</button>
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
    function toggleSpecialization() {
        var role = document.getElementById('role').value;
        var specializationField = document.getElementById('specializationField');

        if (role == 2) {
            specializationField.style.display = 'block';
        } else {
            specializationField.style.display = 'none';
        }
    }

    window.onload = function() {
        toggleSpecialization();
    };
</script>
<style>
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
</style>