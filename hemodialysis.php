<?php
session_start();
if (empty($_SESSION['name'])) {
    header('location:index.php');
    exit();
}

include('header.php');
include('includes/connection.php');

// Function to sanitize user inputs using mysqli_real_escape_string
function sanitize($connection, $input) {
    return mysqli_real_escape_string($connection, htmlspecialchars(strip_tags(trim($input)), ENT_QUOTES, 'UTF-8'));
}

$role = isset($_SESSION['role']) ? $_SESSION['role'] : null;

// Fetch patient details from tbl_patient based on patient_id
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['patientId'])) {
    // Sanitize the patient ID input
    $patientId = sanitize($connection, $_POST['patientId']);

    // Prepare and execute query to fetch patient details, including the deleted flag
    $patient_query = $connection->prepare("SELECT * FROM tbl_patient WHERE id = ? AND deleted = 0");
    if ($patient_query === false) {
        die('Error in prepared statement: ' . $connection->error);
    }

    $patient_query->bind_param("s", $patientId); // "s" stands for string
    $patient_query->execute();
    $patient_result = $patient_query->get_result();
    $patient = $patient_result->fetch_array(MYSQLI_ASSOC);

    if ($patient) {
        // Retrieve sanitized patient details
        $patient_id = $patient['patient_id'];
        $name = sanitize($connection, $patient['first_name']) . ' ' . sanitize($connection, $patient['last_name']);
        $gender = sanitize($connection, $patient['gender']);
        $dob = sanitize($connection, $patient['dob']);

        // Fetch the last hemo-patient ID to generate a new one
        $last_hemopatient_query = $connection->prepare("SELECT hemopatient_id FROM tbl_hemodialysis ORDER BY id DESC LIMIT 1");
        if ($last_hemopatient_query === false) {
            die('Error in prepared statement: ' . $connection->error);
        }

        $last_hemopatient_query->execute();
        $last_hemopatient_result = $last_hemopatient_query->get_result();
        $last_hemopatient = $last_hemopatient_result->fetch_array(MYSQLI_ASSOC);

        // Generate new hemopatient ID based on the last one
        if ($last_hemopatient) {
            $last_id_number = (int) substr($last_hemopatient['hemopatient_id'], 4); 
            $new_hemopatient_id = 'HPT-' . ($last_id_number + 1);
        } else {
            $new_hemopatient_id = 'HPT-1';  
        }

        // Sanitize dialysis_report (leave empty for now)
        $dialysis_report = '';

        // Insert the new hemodialysis record
        $insert_query = $connection->prepare("
           INSERT INTO tbl_hemodialysis (hemopatient_id, patient_id, patient_name, gender, dob, dialysis_report, date_time, deleted) 
            VALUES (?, ?, ?, ?, ?, ?, NOW(), 0)
        ");
        if ($insert_query === false) {
            die('Error in prepared statement: ' . $connection->error);
        }

        // Bind sanitized values for insertion
        $insert_query->bind_param("ssssss", $new_hemopatient_id, $patient_id, $name, $gender, $dob, $dialysis_report);

        // Execute the insert query
        if ($insert_query->execute()) {
            echo "<script>
                    window.location.replace('hemodialysis.php');
                  </script>";
        } else {
            echo "<script>alert('Error: " . $connection->error . "');</script>";
        }
        exit();
    } else {
        echo "<script>alert('Patient not found or marked as deleted.');</script>";
    }
}

ob_end_flush(); // Flush output buffer
?>

<div class="page-wrapper">
    <div class="content">
        <div class="row">
            <div class="col-sm-4 col-3">
                <h4 class="page-title">Hemodialysis</h4>
            </div>
            <?php if ($role == 1 || $role == 3): ?>
                <div class="col-sm-10 col-9 m-b-20">
                    <form method="POST" action="hemodialysis.php" id="addPatientForm" class="form-inline">
                        <div class="input-group w-50">
                            <div class="input-group-prepend">
                                <span class="input-group-text">
                                    <i class="fas fa-search"></i> <!-- Search icon -->
                                </span>
                            </div>
                            <input
                                type="text"
                                class="form-control search-input"
                                id="patientSearchInput"
                                name="patientSearchInput"
                                placeholder="Enter Patient"
                                onkeyup="searchPatients()">
                            <div class="input-group-append">
                                <button type="submit" class="btn btn-primary" id="addPatientBtn" disabled>Add</button>
                            </div>
                        </div>
                        <input type="hidden" name="patientId" id="patientId">
                    </form>
                    <ul id="searchResults" class="list-group mt-2" style="max-height: 200px; overflow-y: auto; border: 1px solid #ccc; border-radius: 5px; display: none;"></ul>
                </div>
                <?php endif; ?>
        </div>
        <div class="table-responsive">
        <label for="patientSearchInput" class="font-weight-bold">Search Patient:</label>
        <input class="form-control" type="text" id="hemopatientSearchInput" onkeyup="filterHemopatients()" placeholder="Search for Patient">
            <table class="datatable table table-hover" id="hemopatientTable">
                <thead style="background-color: #CCCCCC;">
                    <tr>
                        <th>Hemo-patient ID</th>
                        <th>Patient ID</th>
                        <th>Patient Name</th>
                        <th>Age</th>
                        <th>Birthdate</th>
                        <th>Gender</th>
                        <th>Date and Time</th>
                        <th>Dialysis Report</th>
                        <th>Follow-up Date</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    if(isset($_GET['ids'])){
                        $id = sanitize($connection, $_GET['ids']);
                        $update_query = $connection->prepare("UPDATE tbl_hemodialysis SET deleted = 1 WHERE id = ?");
                        if ($update_query === false) {
                            die('Error in prepared statement: ' . $connection->error);
                        }
                        $update_query->bind_param("s", $id);
                        $update_query->execute();
                    }
                    $fetch_query = mysqli_query($connection, "select * from tbl_hemodialysis WHERE deleted = 0");
                    while($row = mysqli_fetch_array($fetch_query))
                    {
                        $dob = $row['dob'];
                        $date = str_replace('/', '-', $dob); 
                        $dob = date('Y-m-d', strtotime($date));
                        $year = (date('Y') - date('Y',strtotime($dob)));

                        $date_time = date('F d, Y g:i A', strtotime($row['date_time']));
                    ?>
                        <tr>
                            <td><?php echo $row['hemopatient_id']; ?></td>
                            <td><?php echo $row['patient_id']; ?></td>
                            <td><?php echo $row['patient_name']; ?></td>
                            <td><?php echo $year; ?></td>
                            <td><?php echo $row['dob']; ?></td>
                            <td><?php echo $row['gender']; ?></td>
                            <td><?php echo $date_time; ?></td>
                            <td>
                                <div class="dialysis-report">
                                    <?php
                                    // Convert new lines to <br> tags to preserve formatting
                                    $formatted_report = nl2br(htmlspecialchars($row['dialysis_report']));
                                    echo $formatted_report;
                                    ?>
                                </div>
                            </td>
                            <td>
                                <?php
                                // Assuming the format in database is DD/MM/YYYY
                                $follow_up_date = $row['follow_up_date'];

                                // Convert to 'Month Day, Year' format (e.g., December 28, 2024)
                                $formatted_date = date('F d, Y', strtotime(str_replace('/', '-', $follow_up_date)));

                                echo $formatted_date;
                                ?>
                            </td>
                            <td class="text-right">
                                <div class="dropdown dropdown-action">
                                    <a href="#" class="action-icon dropdown-toggle" data-toggle="dropdown" aria-expanded="false"><i class="fa fa-ellipsis-v"></i></a>
                                    <div class="dropdown-menu dropdown-menu-right">
                                    <?php 
                                    if ($_SESSION['role'] == 1 | $_SESSION['role'] == 3) {
                                        echo '<a class="dropdown-item" href="edit-hemo.php?id='.$row['id'].'"><i class="fa fa-pencil m-r-5"></i> Edit</a>';
                                        echo '<a class="dropdown-item" href="hemodialysis.php?ids='.$row['id'].'" onclick="return confirmDelete()"><i class="fa fa-trash-o m-r-5"></i> Delete</a>';
                                    }
                                    ?>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    <?php } ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php
include('footer.php');
?>
<script language="JavaScript" type="text/javascript">
function confirmDelete(){
    return confirm('Are you sure want to delete this Patient?');
}
</script>

<script>
     function filterHemopatients() {
        var input, filter, table, tr, td, i, txtValue;
        input = document.getElementById("hemopatientSearchInput");
        filter = input.value.toUpperCase();
        table = document.getElementById("hemopatientTable");
        tr = table.getElementsByTagName("tr");

        for (i = 0; i < tr.length; i++) {
            var matchFound = false;
            for (var j = 0; j < tr[i].cells.length; j++) {
                td = tr[i].cells[j];
                if (td) {
                    txtValue = td.textContent || td.innerText;
                    if (txtValue.toUpperCase().indexOf(filter) > -1) {
                        matchFound = true;
                        break;
                    }
                }
            }
            if (matchFound || i === 0) {
                tr[i].style.display = "";
            } else {
                tr[i].style.display = "none";
            }
        }
    }

    function searchPatients() {
        var input = document.getElementById("patientSearchInput").value;
        if (input.length < 2) {
            document.getElementById("searchResults").style.display = "none";
            document.getElementById("searchResults").innerHTML = "";
            return;
        }
        $.ajax({
            url: "search-hemo.php", // Backend script to fetch patients
            method: "GET",
            data: { query: input },
            success: function (data) {
                var results = document.getElementById("searchResults");
                results.innerHTML = data;
                results.style.display = "block";
            },
        });
    }

    // Select Patient from Search Results
    $(document).on("click", ".search-result", function () {
        var patientId = $(this).data("id");
        var patientName = $(this).text();

        $("#patientId").val(patientId); // Set the hidden input value
        $("#patientSearchInput").val(patientName); // Set input to selected patient name
        $("#addPatientBtn").prop("disabled", false); // Enable the Add button
        $("#searchResults").html("").hide(); // Clear and hide the dropdown
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
        #searchResults {
        max-height: 200px;
        overflow-y: auto;
        border: 1px solid #ddd;
        border-radius: 5px;
        display: none;
        background: #fff;
        position: absolute;
        z-index: 1000;
        width: 50%;
    }
    #searchResults li {
        padding: 8px 12px;
        cursor: pointer;
        list-style: none;
        border-bottom: 1px solid #ddd;
    }
    #searchResults li:hover {
        background-color: #12369e;
        color: white;
    }
    .form-inline .input-group {
        width: 100%;
    }
    .search-icon-bg {
    background-color: #fff; 
    border: none; 
    color: #6c757d; 
    }
    #hemopatientTable td {
    word-wrap: break-word; /* Allow long text to break into multiple lines */
    max-width: 300px; /* Optional: set a maximum width for the column */
}

</style>