<?php
session_start();
if (empty($_SESSION['name'])) {
    header('location:index.php');
}
include('header.php');
include('includes/connection.php');

// Function to sanitize user inputs
function sanitize($connection, $input) {
    return mysqli_real_escape_string($connection, $input);
}

$role = isset($_SESSION['role']) ? $_SESSION['role'] : null;

// Fetch patient details from tbl_patient based on patient_id
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['patientId'])) {
    // Sanitize the patient ID input
    $patientId = sanitize($connection, $_POST['patientId']);

    // Prepare and bind the query
    $patient_query = $connection->prepare("SELECT * FROM tbl_patient WHERE id = ?");
    $patient_query->bind_param("s", $patientId);  // "s" stands for string
    
    // Execute the query
    $patient_query->execute();
    $patient_result = $patient_query->get_result();
    $patient = $patient_result->fetch_array(MYSQLI_ASSOC);

if ($patient) {
    // Retrieve patient details
    $patient_id = $patient['patient_id'];
    $name = $patient['first_name'] . ' ' . $patient['last_name'];
    $gender = $patient['gender'];
    $dob = $patient['dob'];
    $patient_type = $patient['patient_type'];

    // Fetch the last outpatient_id and increment it
    $last_radiology_query = $connection->prepare("SELECT radiology_id FROM tbl_radiology ORDER BY id DESC LIMIT 1");
    $last_radiology_query->execute();
    $last_radiology_result = $last_radiology_query->get_result();
    $last_radiology = $last_radiology_result->fetch_array(MYSQLI_ASSOC);

    // Generate new outpatient_id
    if ($last_radiology) {
        $last_id_number = (int) substr($last_radiology['radiology_id'], 4); // Remove "OPT-" and convert to int
        $new_radiology_id = 'RD-' . ($last_id_number + 1);
    } else {
        $new_radiology_id = 'RD-1';  // Starting value if no outpatient_id exists
    }

    // Insert the patient into tbl_outpatient
    $insert_query = $connection->prepare("
        INSERT INTO tbl_radiology (radiology_id, patient_id, patient_name, gender, dob, patient_type, date_time) 
        VALUES (?, ?, ?, ?, ?, ?, NOW())
    ");
    $insert_query->bind_param("ssssss", $new_radiology_id, $patient_id, $name, $gender, $dob, $patient_type);
    
    if ($insert_query->execute()) {
        echo "<script>
                window.location.replace('radiology.php');
              </script>";
    } else {
        echo "<script>alert('Error: " . mysqli_error($connection) . "');</script>";
    }
    exit;    
  }
}
ob_end_flush(); // Flush output buffer
?>
<div class="page-wrapper">
    <div class="content">
        <div class="row">
            <div class="col-sm-4 col-3">
                <h4 class="page-title">Radiology</h4>
            </div>
            <?php if ($role == 1 || $role == 6): ?>
                <div class="col-sm-10 col-9 m-b-20">
                    <form method="POST" action="radiology.php" id="addPatientForm" class="form-inline">
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
            <input class="form-control" type="text" id="radiologySearchInput" onkeyup="filterRadiology()" placeholder="Search Patient ID or Patient Name">
            <table class="datatable table table-hover" id="radiologyTable">
                <thead style="background-color: #CCCCCC;">
                    <tr>
                        <th>Radiology ID</th>
                        <th>Patient ID</th>
                        <th>Patient Name</th>
                        <th>Age</th>
                        <th>Gender</th>
                        <th>Patient Type</th>
                        <th>Exam Type</th>
                        <th>Step</th>
                        <th>Radiographic Image</th>
                        <th>Date and Time</th>
                        <th>Price</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    if (isset($_GET['id'])) {
                        $id = $_GET['id'];
                        $update_query = mysqli_query($connection, "UPDATE tbl_radiology SET deleted = 1 WHERE id='$id'");
                    }
                    $fetch_query = mysqli_query($connection, "SELECT * FROM tbl_radiology WHERE deleted = 0 ORDER BY date_time ASC");
                    while ($row = mysqli_fetch_array($fetch_query)) {
                        $dob = $row['dob'];
                        $date = str_replace('/', '-', $dob); 
                        $dob = date('Y-m-d', strtotime($date));
                        $year = (date('Y') - date('Y', strtotime($dob)));

                        $date_time = date('d F, Y g:i A', strtotime($row['date_time']));
                    ?>
                        <tr>
                            <td><?php echo $row['radiology_id']; ?></td>
                            <td><?php echo $row['patient_id']; ?></td>
                            <td><?php echo $row['patient_name']; ?></td>
                            <td><?php echo $year; ?></td>
                            <td><?php echo $row['gender']; ?></td>
                            <td><?php echo $row['patient_type']; ?></td>
                            <td><?php echo $row['exam_type']; ?></td>
                            <td><?php echo $row['step']; ?></td>
                            <td>
                                <button class="btn btn-primary" onclick="showImage(<?php echo $row['id']; ?>)">View Image</button>
                            </td>
                            <td><?php echo $date_time; ?></td>
                            <td><?php echo $row['price']; ?></td>
                            <td class="text-right">
                                <div class="dropdown dropdown-action">
                                    <a href="#" class="action-icon dropdown-toggle" data-toggle="dropdown" aria-expanded="false"><i class="fa fa-ellipsis-v"></i></a>
                                    <div class="dropdown-menu dropdown-menu-right">
                                        <?php 
                                        if ($role == 1 || $role == 6) {
                                            echo '<a class="dropdown-item" href="add-radiology.php?id='.$row['id'].'"><i class="fa fa-upload m-r-5"></i> Insert Image</a>';  
                                        }
                                        ?>
                                        <?php 
                                        if ($_SESSION['role'] == 1) {
                                            echo '<a class="dropdown-item" href="edit-radiology.php?id='.$row['id'].'"><i class="fa fa-pencil m-r-5"></i> Edit</a>';
                                            echo '<a class="dropdown-item" href="radiology.php?id='.$row['id'].'" onclick="return confirmDelete()"><i class="fa fa-trash-o m-r-5"></i> Delete</a>';
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

<?php include('footer.php'); ?>

<div id="imageModal" class="modal">
    <span class="close" id="closeButton" onclick="closeModal()">&times;</span>
    <div class="modal-content">
        <img id="modalImage" src="" onclick="zoomImage('in')" oncontextmenu="zoomImage('out'); return false;">
        <a id="downloadLink" href="#" download="image.jpg" class="btn btn-primary">Download Image</a>
    </div>
</div>

<style>
.modal {
    display: none; /* Hide the modal by default */
    position: fixed;
    z-index: 9999;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    overflow: auto;
    background-color: rgba(0,0,0,0.8);
}

.modal-content {
    max-height: 96vh;
    overflow-y: auto;
    overflow-x: auto; 
    margin: auto;
    padding: 20px;
    background: #fff;
    border-radius: 10px;
}

.modal-content img {
    max-width: 100%;
    height: auto;
    cursor: zoom-in;
}

.close {
    font-size: 40px;
    color: #ffffff;
    position: absolute;
    top: 20px;
    right: 20px;
    z-index: 9999; 
    cursor: pointer; 
}

.close:hover {
    background-color: gray; 
}
</style>

<script language="JavaScript" type="text/javascript">
    function confirmDelete() {
        return confirm('Are you sure you want to delete this item?');
    }

    function filterRadiology() {
        var input, filter, table, tr, td, i, txtValue;
        input = document.getElementById("radiologySearchInput");
        filter = input.value.toUpperCase();
        table = document.getElementById("radiologyTable");
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
            url: "search-radiology.php", // Backend script to fetch patients
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

    // Function to show the larger image in modal
    function showImage(imageId) {
        var modal = document.getElementById("imageModal");
        var modalImg = document.getElementById("modalImage");
        var downloadLink = document.getElementById("downloadLink");
        var imageUrl = "fetch-image.php?id=" + imageId;
        
        modal.style.display = "block";
        modalImg.src = imageUrl;
        downloadLink.href = imageUrl; // Set the download link URL
        downloadLink.download = "image_" + imageId + ".jpg"; // Optional: Customize the filename
        modalImg.style.transform = "scale(1)"; // Set the zoom level to default when showing the image
        modalImg.style.left = "50%"; // Center the image horizontally
        modalImg.style.top = "50%"; // Center the image vertically
        modalImg.style.transformOrigin = "50% 50%"; // Set the transform origin to center
    }

    // Function to zoom the image
    function zoomImage(zoomType) {
        var modalImg = document.getElementById("modalImage");
        var zoomLevel = parseFloat(modalImg.style.transform.replace("scale(", "").replace(")", "")); // Get the current zoom level

        if (zoomType === 'in') {
            zoomLevel += 0.1; // Increase the zoom level
        } else if (zoomType === 'out' && zoomLevel > 1) {
            zoomLevel -= 0.1; // Decrease the zoom level, but make sure it doesn't go below 1
        }

        modalImg.style.transform = "scale(" + zoomLevel + ")"; // Set the new zoom level
    }

    // Function to close the modal
    function closeModal() {
        var modal = document.getElementById("imageModal");
        modal.style.display = "none";
    }
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
</style>
