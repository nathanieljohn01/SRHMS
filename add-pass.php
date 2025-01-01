<?php
session_start();
if (empty($_SESSION['name'])) {
    header('location:index.php');
    exit();
}
include('header.php');
include('includes/connection.php');

// Function to sanitize user inputs
function sanitize($connection, $input) {
    return mysqli_real_escape_string($connection, htmlspecialchars(strip_tags(trim($input)), ENT_QUOTES, 'UTF-8'));
}

$fetch_query = mysqli_query($connection, "SELECT MAX(id) as id FROM tbl_visitorpass");
$row = mysqli_fetch_row($fetch_query);
$vst_id = $row[0] == 0 ? 1 : $row[0] + 1;


// Handle form submission
if (isset($_POST['save-pass'])) {
    $visitor_id = 'VST-' . sanitize($connection, $_POST['vst_id']); // Ensure visitor ID is sanitized
    $visitor_name = sanitize($connection, $_POST['visitor_name']);
    $contact_number = sanitize($connection, $_POST['contact_number']);
    $purpose = sanitize($connection, $_POST['purpose']);

    // Use prepared statements for insertion to prevent SQL injection
    $stmt = mysqli_prepare($connection, "INSERT INTO tbl_visitorpass (visitor_id, visitor_name, contact_number, purpose) VALUES (?, ?, ?, ?)");
    mysqli_stmt_bind_param($stmt, 'ssss', $visitor_id, $visitor_name, $contact_number, $purpose);

    // Execute the query and check if insertion was successful
    if (mysqli_stmt_execute($stmt)) {
        $msg = "Visitor pass issued successfully";
    } else {
        $msg = "Error issuing visitor pass";
    }

    // Close the statement
    mysqli_stmt_close($stmt);
}
?>

<div class="page-wrapper">
    <div class="content">
        <div class="row">
            <div class="col-sm-4">
                <h4 class="page-title">Add Visitor Pass</h4>
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
                                <label>Visitor ID <span class="text-danger">*</span></label>
                                <input class="form-control" type="text" name="visitor_id" value="<?php
                                    if (!empty($vst_id)) {
                                        echo 'VST-' . $vst_id;
                                    } else {
                                        echo "VST-1";
                                    } ?>" disabled>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Visitor Name</label>
                                <input class="form-control" type="text" name="visitor_name" required>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Contact Number</label>
                                <input class="form-control" type="text" name="contact_number" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Purpose</label>
                                <input type="text" class="form-control" id="patient-search" placeholder="Search for patient" autocomplete="off" />
                                <div id="patient-list" class="patient-list"></div>
                            </div>
                        </div>
                    </div>
                    <div class="m-t-20 text-center">
                        <button class="btn btn-primary submit-btn" name="save-pass">Issue Visitor Pass</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php
include('footer.php');
?>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

<script type="text/javascript">
<?php
    if(isset($msg)) {
        echo 'swal("' . $msg . '");';
    }
    ?>

document.addEventListener('DOMContentLoaded', function () {
    const searchInput = document.getElementById('patient-search');
    const patientList = document.getElementById('patient-list');

    // Event listener for keyup on the search input field
    searchInput.addEventListener('keyup', function () {
        const query = searchInput.value.trim();
        if (query.length > 2) {
            // Create the AJAX request
            fetch('search-ipt.php?query=' + query)  // This will call the search-ipt.php file
                .then(response => response.text())
                .then(data => {
                    patientList.innerHTML = '';  // Clear previous results
                    if (data.trim()) {
                        // Dynamically add the patient options from the PHP response
                        patientList.innerHTML = data;  // Add the list of patient options from the PHP file
                    } else {
                        patientList.innerHTML = '<div class="patient-option text-muted">No matching patients found</div>';
                    }
                    patientList.style.display = 'block';  // Show the list
                })
                .catch(error => console.error('Error:', error));
        } else {
            patientList.style.display = 'none';  // Hide the list if query length is less than 3
        }
    });

    // Event listener for when a patient option is clicked
    patientList.addEventListener('click', function (e) {
        if (e.target.classList.contains('patient-option')) {
            searchInput.value = e.target.innerText;  // Populate the input with the selected patient's name
            patientList.style.display = 'none';  // Hide the list after selection
        }
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

    #patient-search {
    position: relative; /* Makes sure the patient list is positioned below */
}

/* Styling the patient list */
.patient-list {
    max-height: 200px; /* Maximum height to prevent list overflow */
    overflow-y: auto; /* Scrollable if the list is long */
    border: 1px solid #ddd; /* Border color */
    border-radius: 5px; /* Rounded corners */
    background: #fff; /* Background color */
    position: absolute; /* Absolute positioning below the input */
    z-index: 1000; /* Ensures the list is on top of other elements */
    width: 93%; /* Adjust the width to match the input field */
    display: none; /* Initially hidden */
    box-shadow: 0px 4px 6px rgba(0, 0, 0, 0.1); /* Add subtle shadow */
}

/* Styling individual list items */
.patient-list .patient-option {
    padding: 8px 12px;
    cursor: pointer;
    list-style: none;
    border-bottom: 1px solid #ddd;
}

/* Hover effect on list items */
.patient-list .patient-option:hover {
    background-color: #12369e;
    color: white;
}

</style>
