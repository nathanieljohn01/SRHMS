<?php
session_start();
if (empty($_SESSION['name'])) {
    header('location:index.php');
    exit;
}

include('header.php');
include('includes/connection.php');

if (isset($_GET['patient_id'])) {
    $patient_id = $_GET['patient_id'];

    // Fetch the number of radiology test records for the patient using prepared statements
    $fetch_radiology_tests_stmt = mysqli_prepare($connection, "SELECT COUNT(*) AS num_records FROM tbl_radiology WHERE patient_id = ?");
    mysqli_stmt_bind_param($fetch_radiology_tests_stmt, 's', $patient_id);
    mysqli_stmt_execute($fetch_radiology_tests_stmt);
    mysqli_stmt_bind_result($fetch_radiology_tests_stmt, $num_records);
    mysqli_stmt_fetch($fetch_radiology_tests_stmt);
    mysqli_stmt_close($fetch_radiology_tests_stmt);

    // Check if there are no radiology test records for the patient
    if ($num_records == 0) {
        echo "
        <script src='https://cdn.jsdelivr.net/npm/sweetalert2@11'></script>
        <link rel='stylesheet' href='https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css' />
        <script>
            Swal.fire({
                title: 'No Test Records Found',
                icon: 'info',
                confirmButtonColor: '#12369e',
                confirmButtonText: 'Back',
                backdrop: 'rgba(0, 0, 0, 0.3)',
                customClass: {
                    confirmButton: 'swal2-confirm-btn'
                },
                showClass: {
                    popup: 'animate__animated animate__fadeInDown'
                },
                hideClass: {
                    popup: 'animate__animated animate__fadeOutUp'
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = 'radiology-patients.php';
                }
            });
        </script>";
        exit();
    }

    // Fetch the patient's name using a prepared statement
    $fetch_patient_stmt = mysqli_prepare($connection, "SELECT patient_name FROM tbl_radiology WHERE patient_id = ?");
    mysqli_stmt_bind_param($fetch_patient_stmt, 's', $patient_id);
    mysqli_stmt_execute($fetch_patient_stmt);
    mysqli_stmt_bind_result($fetch_patient_stmt, $patient_name);
    mysqli_stmt_fetch($fetch_patient_stmt);
    mysqli_stmt_close($fetch_patient_stmt);

    // If updating the radiology test status
    if (isset($_POST['update_status'])) {
        $selected_action = $_POST['selected_action'];
        $radiology_id = $_POST['radiology_id'];

        if ($selected_action == 'Cancelled' && isset($_POST['cancel_reason'])) {
            $cancel_reason = $_POST['cancel_reason'];
            $status = "Cancelled - Remarks: " . mysqli_real_escape_string($connection, $cancel_reason);
        } else {
            $status = $selected_action;
        }

        // Update the radiology test status using a prepared statement
        $update_stmt = mysqli_prepare($connection, "UPDATE tbl_radiology SET status = ?, update_date = NOW() WHERE id = ?");
        mysqli_stmt_bind_param($update_stmt, 'si', $status, $radiology_id);
        mysqli_stmt_execute($update_stmt);
        mysqli_stmt_close($update_stmt);
    }
?>
<!-- HTML content starts here -->
<div class="page-wrapper">
    <div class="content">
        <div class="row align-items-center">
            <div class="col-sm-6">
                <h4 class="page-title">Radiology Test Records</h4>
            </div>
            <div class="col-sm-6 text-right">
                <a href="radiology-patients.php" class="btn btn-primary">Back</a>
            </div>
        </div>
        <div class="row mt-3">
            <div class="col-md-4">
                <div class="info-box">
                    <span class="info-label"><strong>Patient Name:</strong></span>
                    <span class="info-value"><?php echo htmlspecialchars($patient_name); ?></span>
                </div>
            </div>
        </div>
        <div class="row mt-3">
            <div class="col-md-12">
                <div class="form-group">
                    <div class="input-group">
                        <input type="text" id="radiologySearchInput" onkeyup="filterTests()" placeholder="Search for radiology tests" class="form-control">
                          <div class="input-group-append">
                            <button class="btn btn-outline-primary" type="button" onclick="clearSearch()">
                                <i class="fa fa-times"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <!-- Table for radiology tests -->
        <div class="table-responsive">
            <table class="datatable table table-hover" id="radiologyTable">
                <thead style="background-color: #CCCCCC;">
                    <tr>
                        <th>Test Type</th>
                        <th>Exam Type</th>
                        <th>Radiographic Image</th>
                        <th>Price</th>
                        <th>Status</th>
                        <th>Requested Date</th>
                        <th>Update Date</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $fetch_radiology_tests_query = mysqli_query($connection, "SELECT id, exam_type, test_type, radiographic_image, price, status, requested_date, update_date FROM tbl_radiology WHERE patient_id = '$patient_id'");
                    while ($radiology_test_row = mysqli_fetch_assoc($fetch_radiology_tests_query)) {
                        $requested_date = date('F d, Y g:i A', strtotime($radiology_test_row['requested_date']));
                        $update_date = $radiology_test_row['update_date'] ? date('F d, Y g:i A', strtotime($radiology_test_row['update_date'])) : '';
                    ?>
                        <tr>
                            <td><?php echo htmlspecialchars($radiology_test_row['exam_type']); ?></td>
                            <td><?php echo htmlspecialchars($radiology_test_row['test_type']); ?></td>
                            <td>
                                <?php if ($radiology_test_row['status'] === 'Completed'): ?>
                                    <?php if (empty($radiology_test_row['radiographic_image'])): ?>
                                        <button class="btn btn-primary" onclick="openImageUploadModal(<?php echo $radiology_test_row['id']; ?>)">Insert Image</button>
                                    <?php else: ?>
                                        <button class="btn btn-primary" onclick="showImage(<?php echo $radiology_test_row['id']; ?>)">View Image</button>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </td>
                            <td><?php echo htmlspecialchars($radiology_test_row['price']); ?></td>
                            <td><?php echo htmlspecialchars($radiology_test_row['status']); ?></td>
                            <td><?php echo htmlspecialchars($requested_date); ?></td>
                            <td><?php echo htmlspecialchars($update_date); ?></td>
                            <td>
                                <form method="post">
                                    <?php
                                    $disable_button = ($radiology_test_row['status'] == 'Completed' || strpos($radiology_test_row['status'], 'Cancelled') !== false) ? 'disabled' : '';
                                    ?>
                                    <div class="btn-group">
                                        <button type="button" class="btn btn-primary dropdown-toggle" data-toggle="dropdown" <?php echo $disable_button; ?>>
                                            <span>&#x22EE;</span>
                                        </button>
                                        <div class="dropdown-menu">
                                            <button type="submit" class="dropdown-item" name="selected_action" value="Completed">Completed</button>
                                            <button type="button" class="dropdown-item" data-toggle="modal" data-target="#cancelReasonModal_<?php echo $radiology_test_row['id']; ?>">Cancelled</button>
                                        </div>
                                    </div>
                                    <input type="hidden" name="update_status" value="1">
                                    <input type="hidden" name="radiology_id" value="<?php echo $radiology_test_row['id']; ?>">
                                </form>
                                <!-- Modal -->
                                <div class="modal fade" id="cancelReasonModal_<?php echo $radiology_test_row['id']; ?>" tabindex="-1" role="dialog" aria-labelledby="cancelReasonLabel_<?php echo $radiology_test_row['id']; ?>" aria-hidden="true">
                                    <div class="modal-dialog" role="document">
                                        <div class="modal-content">
                                            <div class="modal-header">
                                                <h5 class="modal-title" id="cancelReasonLabel_<?php echo $radiology_test_row['id']; ?>">Enter Cancel Reason</h5>
                                                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                                    <span aria-hidden="true">&times;</span>
                                                </button>
                                            </div>
                                            <form method="post">
                                                <div class="modal-body">
                                                    <div class="form-group">
                                                        <label for="cancelReason_<?php echo $radiology_test_row['id']; ?>">Reason</label>
                                                        <textarea name="cancel_reason" id="cancelReason_<?php echo $radiology_test_row['id']; ?>" class="form-control" required></textarea>
                                                    </div>
                                                    <input type="hidden" name="update_status" value="1">
                                                    <input type="hidden" name="selected_action" value="Cancelled">
                                                    <input type="hidden" name="laborder_id" value="<?php echo $radiology_test_row['id']; ?>">
                                                </div>
                                                <div class="modal-footer">
                                                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                                                    <button type="submit" class="btn btn-primary">Submit</button>
                                                </div>
                                            </form>
                                        </div>
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

<!-- Modal for uploading radiographic image -->
<div id="imageUploadModal" class="modal" tabindex="-1" role="dialog" aria-labelledby="uploadModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="uploadModalLabel">Upload Radiographic Image</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close" onclick="closeModal()">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <form id="uploadImageForm" enctype="multipart/form-data">
                    <input type="hidden" name="radiologyId" id="modalRadiologyId">
                    <div class="form-group">
                        <label for="radiologyImage">Choose Image</label>
                        <input type="file" name="radiologyImage" id="radiologyImage" class="form-control" accept="image/*" required>
                    </div>
                    <div id="uploadSpinner" style="display: none;">
                        <span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>
                        Uploading...
                    </div>
                    <div class="d-flex justify-content-end">
                        <button type="submit" class="btn btn-primary">Upload</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<div id="imageModal" class="modal">
    <span class="close" id="closeButton" onclick="closeModal()">&times;</span>
    <div class="modal-content">
        <img id="modalImage" src="" onclick="zoomImage('in')" oncontextmenu="zoomImage('out'); return false;">
        <a id="downloadLink" href="#" download="image.jpg" class="btn btn-primary">Download Image</a>
    </div>
</div>

<?php
} else {
    header('location: index.php');
    exit();
}

include('footer.php');
?>

<script>
    function clearSearch() {
        document.getElementById("radiologySearchInput").value = '';
        filterTests();
    }
    function filterTests() {
        var input, filter, table, tr, td, i, j, txtValue;
        input = document.getElementById("radiologySearchInput");
        filter = input.value.toUpperCase();
        table = document.getElementById("radiologyTable");
        tr = table.getElementsByTagName("tr");

        // If DataTable is initialized, clear its search and use manual filtering
        if ($.fn.DataTable.isDataTable("#radiologyTable")) {
            var radiologyTableInstance = $('#radiologyTable').DataTable();
            radiologyTableInstance.search('').draw();  // Clear DataTable search
            radiologyTableInstance.page.len(-1).draw();  // Show all rows temporarily
        }

        // Manual filtering logic
        for (i = 1; i < tr.length; i++) {  // Start from 1 to skip the header row
            tr[i].style.display = "none";  // Hide all rows initially
            td = tr[i].getElementsByTagName("td");
            for (j = 0; j < td.length; j++) {
                if (td[j]) {
                    txtValue = td[j].textContent || td[j].innerText;
                    if (txtValue.toUpperCase().indexOf(filter) > -1) {
                        tr[i].style.display = "";  // Show the row if a match is found
                        break;
                    }
                }
            }
        }

        // Restore DataTable pagination if input is empty
        if (filter.trim() === "") {
            if ($.fn.DataTable.isDataTable("#radiologyTable")) {
                var radiologyTableInstance = $('#radiologyTable').DataTable();
                radiologyTableInstance.page.len(10).draw();  // Reset pagination to default
            }
        }
    }

    // Initialize DataTable
    $(document).ready(function() {
        $('#radiologyTable').DataTable();
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

    function openImageUploadModal(radiologyId) {
        document.getElementById("modalRadiologyId").value = radiologyId;
        $('#imageUploadModal').modal('show');
    }

    $('#uploadImageForm').on('submit', function (e) {
    e.preventDefault();

    var formData = new FormData(this);
        $.ajax({
            url: 'upload-radiology-image.php', // Backend script for handling image upload
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function (response) {
                try {
                    response = JSON.parse(response);
                    if (response.success) {
                        var radiologyId = $("#modalRadiologyId").val();
                        var button = $("button[onclick='openImageUploadModal(" + radiologyId + ")']");
                        button.text("View Image").attr("onclick", "showImage(" + radiologyId + ")");
                        $('#imageUploadModal').modal('hide');
                    } else {
                        alert(response.error || 'Error uploading image.');
                    }
                } catch (e) {
                    alert('Unexpected response from the server.');
                }
            },
            error: function () {
                alert('Failed to upload the image. Please try again.');
            }
        });
    });

</script>
<style>
.btn-outline-primary {
    background-color:rgb(252, 252, 252);
    color: gray;
    border: 1px solid rgb(228, 228, 228);
}
.btn-outline-primary:hover {
    background-color: #12369e;
    color: #fff;
}
.btn-outline-secondary {
    color:rgb(90, 90, 90);
    border: 1px solid rgb(228, 228, 228);
}
.btn-outline-secondary:hover {
    background-color: #12369e;
    color: #fff;
}
.input-group-text {
    background-color:rgb(255, 255, 255);
    border: 1px solid rgb(228, 228, 228);
    color: gray;
}      
.btn-primary {
    background: #12369e;
    border: none;
}
.btn-primary:hover {
    background: #05007E;
}
.custom-badge {
    border-radius: 4px;
    display: inline-block;
    font-size: 12px;
    min-width: 95px;
    padding: 1px 10px;
    text-align: center;
}
.status-red,
a.status-red {
    background-color: #ffe5e6;
    border: 1px solid #fe0000;
    color: #fe0000;
}

@keyframes fadeInUp {
    from {
        opacity: 0;
        transform: translateY(30px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}
.swal2-confirm-btn {
    background-color: #12369e; /* Button background color */
    color: white; /* Button text color */
    border: none; /* Remove border */
    border-radius: 5px; /* Add border radius for rounded corners */
}
.info-box {
    background-color: #f9f9f9;
    border: 1px solid #ddd;
    padding: 10px 15px; /* Adjusted padding for better spacing */
    border-radius: 6px; /* Slightly larger border radius for a smoother look */
    display: flex;
    align-items: center;
    justify-content: space-between;
    font-size: 14px; /* Reduced font size */
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1); /* Added subtle shadow for depth */
    transition: background-color 0.3s ease; /* Smooth transition for background color */
}

.info-box:hover {
    background-color: #f1f1f1; /* Slightly darker background on hover */
}

.info-label {
    color: #333;
    font-weight: bold;
    margin-right: 10px; /* Added margin for spacing */
}

.info-value {
    color: #555;
}
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