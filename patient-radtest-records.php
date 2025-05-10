<?php
session_start();
if (empty($_SESSION['name'])) {
    header('location:index.php');
    exit;
}

include('header.php');
include('includes/connection.php');

if (isset($_GET['patient_id'])) {
    $patient_id = mysqli_real_escape_string($connection, $_GET['patient_id']);

    $check_patient_stmt = mysqli_prepare($connection, 
        "SELECT COUNT(*) FROM tbl_patient WHERE patient_id = ?");
    mysqli_stmt_bind_param($check_patient_stmt, 's', $patient_id);
    mysqli_stmt_execute($check_patient_stmt);
    mysqli_stmt_bind_result($check_patient_stmt, $patient_exists);
    mysqli_stmt_fetch($check_patient_stmt);
    mysqli_stmt_close($check_patient_stmt);
    
    if (!$patient_exists) {
        die("Patient not found");
    }

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
    
        echo "
        <script src='https://cdn.jsdelivr.net/npm/sweetalert2@11'></script>
        <link rel='stylesheet' href='https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css'/>
        <script>
            Swal.fire({
                title: 'Processing...',
                text: 'Updating radiology status...',
                allowOutsideClick: false,
                showConfirmButton: false,
                willOpen: () => {
                    Swal.showLoading();
                }
            });
        </script>";
    
        if ($selected_action == 'Cancelled' && isset($_POST['cancel_reason'])) {
            $cancel_reason = $_POST['cancel_reason'];
            $status = "Cancelled - Remarks: " . mysqli_real_escape_string($connection, $cancel_reason);
        } else {
            $status = $selected_action;
        }
    
        $update_stmt = mysqli_prepare($connection, "UPDATE tbl_radiology SET status = ?, update_date = NOW() WHERE id = ?");
        mysqli_stmt_bind_param($update_stmt, 'si', $status, $radiology_id);
        
        if (mysqli_stmt_execute($update_stmt)) {
            mysqli_stmt_close($update_stmt);
            echo "
            <script>
                Swal.fire({
                    icon: 'success',
                    title: 'Success!',
                    text: 'Radiology status updated successfully!',
                    confirmButtonColor: '#12369e',
                }).then((result) => {
                    window.location = 'patient-radtest-records.php?patient_id=" . $_GET['patient_id'] . "';
                });
            </script>";
            exit();
        } else {
            echo "
            <script>
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'Failed to update radiology status',
                    confirmButtonColor: '#12369e'
                });
            </script>";
            exit();
        }
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
                <a href="radiology-patients.php" class="btn btn-primary"><i class="fa fa-arrow-left"></i> Back</a>
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
                                        <button class="btn btn-primary" onclick="openImageUploadModal(<?php echo $radiology_test_row['id']; ?>)"><i class="fa fa-upload m-r-5"></i>Insert Image</button>
                                    <?php else: ?>
                                        <button class="btn btn-primary" onclick="showImage(<?php echo $radiology_test_row['id']; ?>)"><i class="fa fa-image m-r-5"></i>View Image</button>
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
                                    $disable_button = ($radiology_test_row['status'] == 'Completed' || strpos($radiology_test_row['status'], 'Cancelled') !== false || !empty($radiology_test_row['update_date'])) ? 'disabled' : '';
                                    ?>
                                    <div class="dropdown action-dropdown">
                                        <button class="btn btn-link p-0" type="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false" <?php echo $disable_button; ?>>
                                            <i class="fa fa-ellipsis-v fa-lg"></i>
                                        </button>
                                        <div class="dropdown-menu dropdown-menu-left">
                                            <button type="submit" class="dropdown-item" name="selected_action" value="Completed" <?php echo $disable_button; ?>><i class="fa fa-check-circle m-r-5"></i> Completed</button>
                                            <button type="button" class="dropdown-item" data-toggle="modal" data-target="#cancelReasonModal_<?php echo $radiology_test_row['id']; ?>" <?php echo $disable_button; ?>><i class="fa fa-times-circle m-r-5"></i> Cancelled</button>
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
                                                <h5 class="modal-title" id="cancelReasonLabel_<?php echo $radiology_test_row['id']; ?>">Enter Cancellation Reason</h5>
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
                                                    <input type="hidden" name="radiology_id" value="<?php echo $radiology_test_row['id']; ?>">
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

<!-- Improved Image Upload Modal -->
<div id="imageUploadModal" class="modal fade" tabindex="-1" role="dialog" aria-labelledby="uploadModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header">
                <h5 class="modal-title" id="uploadModalLabel">
                    <i class="fas fa-upload mr-2"></i>Upload Radiographic Image
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <form id="uploadImageForm" enctype="multipart/form-data">
                    <input type="hidden" name="radiologyId" id="modalRadiologyId">
                    
                    <div class="form-group">
                        <label for="radiologyImage" class="font-weight-bold">Select Image File</label>
                        <div class="custom-file">
                            <input type="file" class="custom-file-input" name="radiologyImage" id="radiologyImage" accept="image/jpeg,image/png,image/gif" required>
                            <label class="custom-file-label" for="radiologyImage">Choose file (Max 2MB)</label>
                        </div>
                        <small class="form-text text-muted">Allowed formats: JPEG, PNG, GIF</small>
                    </div>
                    
                    <div class="preview-container text-center mb-3" style="display:none;">
                        <img id="imagePreview" src="#" class="img-thumbnail" style="max-height: 200px; display: none;">
                        <button type="button" id="clearPreview" class="btn btn-sm btn-danger mt-2" style="display:none;">
                            <i class="fas fa-times"></i> Remove
                        </button>
                    </div>
                    
                    <div class="progress mb-3" style="height: 20px; display: none;">
                        <div id="uploadProgress" class="progress-bar progress-bar-striped progress-bar-animated" 
                             role="progressbar" style="width: 0%"></div>
                    </div>
                    
                    <div class="d-flex justify-content-between align-items-center">
                        <div id="uploadStatus" class="text-muted small"></div>
                        <button type="submit" class="btn btn-primary px-4">
                            <i class="fas fa-cloud-upload-alt mr-2"></i>Upload
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Enhanced Image Viewer Modal -->
<!-- Improved Image Viewer Modal -->
<div id="imageModal" class="modal fade" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="imageModalTitle">Radiographic Image</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body p-0">
                <div class="image-container" style="height: 80vh; overflow: hidden; position: relative;">
                    <img id="modalImage" src="" class="img-fluid" 
                         style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); 
                                max-width: none; cursor: grab; transition: transform 0.1s ease-out;">
                </div>
            </div>
            <div class="modal-footer d-flex justify-content-between align-items-center">
                <div class="zoom-controls btn-group btn-group-sm">
                    <button class="btn btn-outline-secondary zoom-out-btn" title="Zoom Out" onclick="adjustZoom(-0.2)">
                        <i class="fas fa-search-minus"></i>
                    </button>
                    <button class="btn btn-outline-secondary zoom-reset-btn" title="Reset Zoom" onclick="resetZoom()">
                        <i class="fas fa-expand"></i>
                    </button>
                    <button class="btn btn-outline-secondary zoom-in-btn" title="Zoom In" onclick="adjustZoom(0.2)">
                        <i class="fas fa-search-plus"></i>
                    </button>
                </div>
                <div class="rotation-controls btn-group btn-group-sm">
                    <button class="btn btn-outline-secondary rotate-left-btn" title="Rotate Left" onclick="rotateImage(-90)">
                        <i class="fas fa-undo"></i>
                    </button>
                    <button class="btn btn-outline-secondary rotate-right-btn" title="Rotate Right" onclick="rotateImage(90)">
                        <i class="fas fa-redo"></i>
                    </button>
                </div>
                <div class="ml-auto">
                    <a id="downloadLink" href="#" class="btn btn-primary mr-2" download>
                        <i class="fas fa-download mr-1"></i> Download
                    </a>
                </div>
            </div>
        </div>
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

    // Improved Image Upload Handling
    function openImageUploadModal(radiologyId) {
        $('#modalRadiologyId').val(radiologyId);
        $('#imagePreview').hide();
        $('#clearPreview').hide();
        $('#radiologyImage').val('');
        $('.custom-file-label').text('Choose file (Max 2MB)');
        $('#imageUploadModal').modal('show');
    }

    // File Preview and Validation
    $('#radiologyImage').on('change', function() {
        const file = this.files[0];
        const preview = $('#imagePreview');
        const clearBtn = $('#clearPreview');
        
        if (file) {
            const reader = new FileReader();
            
            reader.onload = function(e) {
                preview.attr('src', e.target.result).fadeIn();
                $('.preview-container').show();
                clearBtn.show();
                $('.custom-file-label').text(file.name);
            }
            
            reader.readAsDataURL(file);
        }
    });

    $('#clearPreview').on('click', function() {
        $('#radiologyImage').val('');
        $('#imagePreview').attr('src', '#').hide();
        $('.custom-file-label').text('Choose file (Max 2MB)');
        $(this).hide();
    });

    // Enhanced AJAX Upload with Progress
    $('#uploadImageForm').on('submit', function(e) {
        e.preventDefault();
        
        const formData = new FormData(this);
        const progressBar = $('.progress');
        const progressFill = $('#uploadProgress');
        const uploadStatus = $('#uploadStatus');
        
        progressBar.show();
        uploadStatus.text('Uploading...');
        
        $.ajax({
            url: 'upload-radiology-image.php',
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            xhr: function() {
                const xhr = new window.XMLHttpRequest();
                xhr.upload.addEventListener('progress', function(e) {
                    if (e.lengthComputable) {
                        const percent = Math.round((e.loaded / e.total) * 100);
                        progressFill.css('width', percent + '%').text(percent + '%');
                    }
                });
                return xhr;
            },
            success: function(response) {
                try {
                    response = JSON.parse(response);
                    if (response.success) {
                        uploadStatus.html('<i class="fas fa-check-circle text-success"></i> Upload successful!');
                        setTimeout(function() {
                            $('#imageUploadModal').modal('hide');
                            location.reload();
                        }, 1500);
                    } else {
                        uploadStatus.html('<i class="fas fa-times-circle text-danger"></i> ' + (response.error || 'Upload failed'));
                    }
                } catch (e) {
                    uploadStatus.html('<i class="fas fa-times-circle text-danger"></i> Invalid server response');
                }
            },
            error: function() {
                uploadStatus.html('<i class="fas fa-times-circle text-danger"></i> Upload failed');
            },
            complete: function() {
                progressBar.hide();
            }
        });
    });

let currentZoom = 1;
let currentRotation = 0;
let isDragging = false;
let startPos = { x: 0, y: 0 };
let translatePos = { x: 0, y: 0 };

function showImage(imageId) {
    $('#imageModal').modal('show');
    $('#imageModalTitle').html(`<i class="fas fa-spinner fa-spin"></i> Loading Image...`);
    
    // Reset viewer state
    currentZoom = 1;
    currentRotation = 0;
    translatePos = { x: 0, y: 0 };
    $('.zoom-reset-btn span').text('100%');
    
    // Load image
    const img = $('#modalImage');
    img.attr('src', `fetch-image.php?id=${imageId}`).on('load', function() {
        // Display patient name instead of image ID
        $('#imageModalTitle').text(`Radiographic Image - <?php echo htmlspecialchars($patient_name); ?>`);
        resetImagePosition();
        
        // Set download link
        $('#downloadLink').attr({
            'href': `fetch-image.php?id=${imageId}`,
            'download': `radiology_${imageId}.jpg`
        });
    }).on('error', function() {
        $('#imageModalTitle').html('<i class="fas fa-exclamation-triangle text-danger"></i> Failed to load image');
    });
}

function adjustZoom(change) {
    currentZoom = Math.max(0.1, Math.min(currentZoom + change, 5));
    updateImageTransform();
}

function resetZoom() {
    currentZoom = 1;
    translatePos = { x: 0, y: 0 };
    updateImageTransform();
}

function rotateImage(degrees) {
    currentRotation += degrees;
    updateImageTransform();
}

function updateImageTransform() {
    const img = $('#modalImage');
    img.css('transform', 
        `translate(-50%, -50%) 
         translate(${translatePos.x}px, ${translatePos.y}px)
         scale(${currentZoom}) 
         rotate(${currentRotation}deg)`);
}

// Drag to pan functionality
$('.image-container').on('mousedown', function(e) {
    if (currentZoom <= 1) return;
    
    isDragging = true;
    startPos = { x: e.clientX, y: e.clientY };
    $('#modalImage').addClass('grabbing');
    e.preventDefault();
});

$(document).on('mousemove', function(e) {
    if (!isDragging) return;
    
    const dx = e.clientX - startPos.x;
    const dy = e.clientY - startPos.y;
    
    translatePos.x += dx;
    translatePos.y += dy;
    
    startPos = { x: e.clientX, y: e.clientY };
    updateImageTransform();
    e.preventDefault();
});

$(document).on('mouseup', function() {
    isDragging = false;
    $('#modalImage').removeClass('grabbing');
});

function resetImagePosition() {
    currentZoom = 1;
    currentRotation = 0;
    translatePos = { x: 0, y: 0 };
    updateImageTransform();
}

// Close modal when pressing ESC
$(document).on('keydown', function(e) {
    if (e.key === 'Escape' && $('#imageModal').hasClass('show')) {
        $('#imageModal').modal('hide');
    }
});

    $(document).ready(function() {
    // When clicking a dropdown button
    $('.action-dropdown .btn-link').on('click', function(e) {
        e.preventDefault();
        e.stopPropagation();
        
        // Close all other dropdowns first
        $('.action-dropdown .dropdown-menu').not($(this).next()).removeClass('show');
        
        // Toggle current dropdown
        $(this).next('.dropdown-menu').toggleClass('show');
    });

    // Close dropdown when clicking outside
    $(document).on('click', function(e) {
        if (!$(e.target).closest('.action-dropdown').length) {
            $('.dropdown-menu').removeClass('show');
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
    background-color:rgb(249, 249, 249);
    border: 1px solid rgb(212, 212, 212);
    color: gray;
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
/* Image Viewer Enhancements */
/* Image Viewer Styles */
#imageModal .modal-content {
    border: none;
    box-shadow: 0 5px 20px rgba(0,0,0,0.2);
}

#imageModal .modal-header {
    padding: 12px 20px;
    border-bottom: 1px solid rgba(255,255,255,0.1);
}

#imageModal .modal-body {
    background-color: black;
}

#imageModal .modal-footer {
    padding: 12px 20px;
    border-top: 1px solid #e9ecef;
}

.zoom-controls .btn, 
.rotation-controls .btn {
    width: 32px;
    height: 32px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
   
}

.zoom-controls .btn:hover, 
.rotation-controls .btn:hover {
    background-color: #12396e;
}

#modalImage {
    transition: transform 0.15s ease-out;
}

#modalImage.grabbing {
    cursor: grabbing;
}

/* Custom File Input */
.custom-file-label::after {
    content: "Browse";
}

/* Progress Bar */
.progress {
    border-radius: 10px;
}

.progress-bar {
    font-size: 12px;
}

/* Responsive Adjustments */
@media (max-width: 768px) {
    .modal-dialog {
        margin: 0.5rem auto;
    }
    
    .modal-content {
        border-radius: 0;
    }
}
.action-dropdown {
    position: relative;
    display: flex;
    align-items: right;
    justify-content: right;
}
.action-dropdown .dropdown-menu {
    position: absolute;
    left: -50px; /* This moves the box to the left */
    min-width: 120px;
    margin-top: 0;
    border-radius: 4px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.15);
}
.action-dropdown .btn-link {
    color: #333;
    font-size: 14px;
    padding: 2px 6px;
    transition: all 0.2s ease;
    line-height: 1;
    display: flex;
    align-items: center;
    justify-content: center;
    width: 24px;
    height: 24px;
}
.action-dropdown .btn-link:hover {
    color: #12369e;
    transform: scale(1.05);
}
.action-dropdown .dropdown-item {
    padding: 8px 15px;
}
.dropdown-item[disabled] {
    cursor: not-allowed;
    opacity: 0.6;
    pointer-events: none;
}

#imageModal .modal-dialog {
    width: 95%;
    height: 95vh;
    margin: 0 auto;
    display: flex;
}

#imageModal #modalImage {
    max-height: 100%;
    max-width: 100%;
    object-fit: contain;
}
#imageModal .zoom-controls button {
    width: 40px;
    height: 40px;
    font-size: 1.2rem;
    margin: 0 5px;
}

#imageModal .btn-group-sm button {
    width: 40px;
    height: 40px;
    font-size: 1rem;
}
</style>