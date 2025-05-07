<?php
session_start();
ob_start();
if (empty($_SESSION['name'])) {
    header('location:index.php');
    exit;
}
include('header.php');
include('includes/connection.php');

function sanitize($connection, $input) {
    return mysqli_real_escape_string($connection, trim($input));
}

$role = isset($_SESSION['role']) ? $_SESSION['role'] : null;
$doctor_name = isset($_SESSION['name']) ? $_SESSION['name'] : null;

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['diagnosis']) && isset($_POST['patientId'])) {
    $diagnosis = sanitize($connection, $_POST['diagnosis']);
    $patientId = sanitize($connection, $_POST['patientId']);

    $update_query = $connection->prepare("UPDATE tbl_outpatient SET diagnosis=? WHERE patient_id=?");
    $update_query->bind_param("ss", $diagnosis, $patientId);

    if ($update_query->execute()) {
        echo "<script>showSuccess('Diagnosis added successfully.', true);</script>";
    } else {
        echo "<script>showError('Error adding diagnosis.');</script>";
    }
    $update_query->close();
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['patientId'])) {
    $patientId = sanitize($connection, $_POST['patientId']);
    $patient_query = $connection->prepare("SELECT * FROM tbl_patient WHERE id = ?");
    $patient_query->bind_param("s", $patientId);
    $patient_query->execute();
    $patient_result = $patient_query->get_result();
    $patient = $patient_result->fetch_array(MYSQLI_ASSOC);

    if ($patient) {
        $patient_id = $patient['patient_id'];
        $name = $patient['first_name'] . ' ' . $patient['last_name'];
        $gender = $patient['gender'];
        $dob = $patient['dob'];
        $doctor_incharge = "";

        $last_outpatient_query = $connection->prepare("SELECT outpatient_id FROM tbl_outpatient ORDER BY id DESC LIMIT 1");
        $last_outpatient_query->execute();
        $last_outpatient_result = $last_outpatient_query->get_result();
        $last_outpatient = $last_outpatient_result->fetch_array(MYSQLI_ASSOC);

        if ($last_outpatient) {
            $last_id_number = (int) substr($last_outpatient['outpatient_id'], 4);
            $new_outpatient_id = 'OPT-' . ($last_id_number + 1);
        } else {
            $new_outpatient_id = 'OPT-1';
        }

        $insert_query = $connection->prepare("INSERT INTO tbl_outpatient (outpatient_id, patient_id, patient_name, gender, dob, doctor_incharge, date_time) VALUES (?, ?, ?, ?, ?, ?, NOW())");
        $insert_query->bind_param("ssssss", $new_outpatient_id, $patient_id, $name, $gender, $dob, $doctor_incharge);
        $insert_query->execute();

        echo "<script>
            Swal.fire({
                title: 'Success!',
                text: 'Outpatient record inserted successfully.',
                icon: 'success',
                confirmButtonText: 'OK',
                confirmButtonColor: '#12369e'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = 'outpatients.php';
                }
            });
        </script>";
        exit;
    } else {
        echo "<script>
            Swal.fire({
                title: 'Error!',
                text: 'Patient not found. Please check the Patient ID.',
                icon: 'error',
                confirmButtonText: 'OK'
            });
        </script>";
    }
    $patient_query->close();
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['outpatientIdDoctor']) && isset($_POST['doctorId'])) {
    $outpatientId = sanitize($connection, $_POST['outpatientIdDoctor']);
    $doctorId = sanitize($connection, $_POST['doctorId']);

    $doctor_query = $connection->prepare("SELECT first_name, last_name FROM tbl_employee WHERE id = ?");
    $doctor_query->bind_param("s", $doctorId);
    $doctor_query->execute();
    $doctor_result = $doctor_query->get_result();
    $doctor = $doctor_result->fetch_array(MYSQLI_ASSOC);
    $doctor_name = $doctor['first_name'] . ' ' . $doctor['last_name'];

    $update_query = $connection->prepare("UPDATE tbl_outpatient SET doctor_incharge = ? WHERE outpatient_id = ?");
    $update_query->bind_param("ss", $doctor_name, $outpatientId);

    if ($update_query->execute()) {
        echo "<script>showSuccess('Doctor assigned successfully.', true);</script>";
    } else {
        echo "<script>showError('Error assigning doctor.');</script>";
    }

    $doctor_query->close();
    $update_query->close();
}

if (isset($_GET['ids'])) {
    $id = intval($_GET['ids']);
    $update_query = $connection->prepare("UPDATE tbl_outpatient SET deleted = 1 WHERE id = ?");
    $update_query->bind_param("i", $id);
    if ($update_query->execute()) {
        echo "<script>showSuccess('Outpatient record deleted successfully!', true);</script>";
        header('Location: outpatients.php');
        exit;
    } else {
        echo "<script>showError('Error deleting outpatient record.');</script>";
    }
}

ob_end_flush();
?>

<div class="page-wrapper">
    <div class="content">
        <div class="row">
            <div class="col-sm-4 col-3">
                <h4 class="page-title">Outpatient</h4>
            </div>
                <?php if ($role == 1 || $role == 3): ?>
                <div class="col-sm-10 col-9 m-b-20">
                    <form method="POST" action="outpatients.php" id="addPatientForm" class="form-inline">
                        <div class="input-group w-50">
                            <div class="input-group-prepend">
                                <span class="input-group-text">
                                    <i class="fas fa-search"></i>
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
                                <button type="submit" class="btn btn-outline-secondary" id="addPatientBtn" disabled>Add</button>
                            </div>
                        </div>
                        <input type="hidden" name="patientId" id="patientId">
                    </form>
                    <ul id="searchResults" class="list-group mt-2" style="max-height: 200px; overflow-y: auto; border: 1px solid #ccc; border-radius: 5px; display: none;"></ul>
                </div>
                <?php endif; ?>
             </div>
            <div class="table-responsive">
                <div class="sticky-search">
                <h5 class="font-weight-bold mb-2">Search Patient:</h5>
                    <div class="input-group mb-3">
                        <div class="position-relative w-100">
                            <i class="fa fa-search position-absolute text-secondary" style="top: 50%; left: 12px; transform: translateY(-50%);"></i>
                            <input class="form-control" type="text" id="outpatientSearchInput" onkeyup="filterOutpatients()" placeholder="Search" style="padding-left: 35px; padding-right: 35px;">
                            <button class="position-absolute border-0 bg-transparent text-secondary" type="button" onclick="clearSearch()" style="top: 50%; right: 10px; transform: translateY(-50%);">
                                <i class="fa fa-times"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            <div class="table-responsive">
            <table class="datatable table table-hover" id="outpatientTable">
                <thead style="background-color: #CCCCCC;">
                    <tr>
                        <th>Patient ID</th>
                        <th>Outpatient ID</th>
                        <th>Patient Name</th>
                        <th>Age</th>
                        <th>Birthdate</th>
                        <th>Gender</th>
                        <th>Doctor In-Charge</th>
                        <th>Lab Result</th>
                        <th>Radiographic Images</th>
                        <th>Diagnosis</th>
                        <th>Date and Time</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                        if ($role == 2) {
                            $fetch_query = $connection->prepare("SELECT * FROM tbl_outpatient WHERE deleted = 0 AND doctor_incharge = ?");
                            $fetch_query->bind_param("s", $doctor_name);
                        } else {
                            $fetch_query = $connection->prepare("SELECT * FROM tbl_outpatient WHERE deleted = 0");
                        }

                        $fetch_query->execute();
                        $result = $fetch_query->get_result();

                        while ($row = $result->fetch_assoc()) {
                            $dob = $row['dob'];
                            $date = str_replace('/', '-', $dob); 
                            $dob = date('Y-m-d', strtotime($date));
                            $year = (date('Y') - date('Y', strtotime($dob)));
                            $date_time = date('F d, Y g:i A', strtotime($row['date_time']));
                        ?>
                        <tr>
                            <td><?php echo $row['patient_id']; ?></td>
                            <td><?php echo $row['outpatient_id']; ?></td>
                            <td><?php echo $row['patient_name']; ?></td>
                            <td><?php echo $year; ?></td>
                            <td><?php echo $row['dob']; ?></td>
                            <td><?php echo $row['gender']; ?></td>
                            <td>
                                <?php if (empty($row['doctor_incharge'])) { ?>
                                    <?php if ($role == 3) { ?>
                                    <button class="btn btn-primary btn-sm select-doctor-btn" data-toggle="modal" data-target="#doctorModal" data-id="<?php echo htmlspecialchars($row['outpatient_id']); ?>">Select Doctor</button>
                                    <?php } ?>
                                <?php } else { ?>
                                    <?php echo htmlspecialchars($row['doctor_incharge']); ?>
                                <?php } ?>
                            </td>
                            <td>
                                <?php if ($_SESSION['role'] == 2 || $_SESSION['role'] == 1) { ?>
                                <form action="generate-result.php" method="get">
                                    <input type="hidden" name="patient_id" value="<?php echo $row['patient_id']; ?>">
                                    <button class="btn btn-primary custom-btn" type="submit">
                                        <i class="fa fa-file-pdf-o m-r-5"></i> View Result
                                    </button>
                                </form>
                                <?php } ?>
                            </td>
                            <?php if ($_SESSION['role'] == 2 || $_SESSION['role'] == 1) { ?>
                            <td id="img-btn-<?php echo $row['patient_id']; ?>">
                                <?php 
                                $rad_query = $connection->prepare("SELECT COUNT(*) as count FROM tbl_radiology WHERE patient_id = ? AND radiographic_image IS NOT NULL AND radiographic_image != '' AND deleted = 0");
                                $rad_query->bind_param("s", $row['patient_id']);
                                $rad_query->execute();
                                $rad_result = $rad_query->get_result();
                                $rad_count = $rad_result->fetch_assoc()['count'];
                                if ($rad_count > 0) {
                                ?>
                                <button class="btn btn-primary custom-btn" onclick="showRadiologyImages('<?php echo $row['patient_id']; ?>')">
                                    <i class="fa fa-image m-r-5"></i> View Images
                                </button>
                                <?php } ?>
                            </td>
                            <?php } ?>
                            <td><?php echo $row['diagnosis']; ?></td>
                            <td><?php echo $date_time; ?></td>
                            <td class="text-right">
                            <div class="dropdown dropdown-action">
                                <a href="#" class="action-icon dropdown-toggle" data-toggle="dropdown" aria-expanded="false"><i class="fa fa-ellipsis-v"></i></a>
                                <div class="dropdown-menu dropdown-menu-right">
                                    <?php if ($role == 2 && $doctor_name == $row['doctor_incharge']) { ?>
                                        <button class="dropdown-item diagnosis-btn" data-toggle="modal" data-target="#diagnosisModal" data-id="<?php echo $row['outpatient_id']; ?>" <?php echo !empty($row['diagnosis']) ? 'disabled' : ''; ?>><i class="fa fa-stethoscope m-r-5"></i> Diagnosis</button>
                                    <?php } ?>
                                    <?php if ($role == 1 || $role == 3) {
                                        echo '<a class="dropdown-item" href="edit-outpatient.php?id='.$row['id'].'"><i class="fa fa-pencil m-r-5"></i> Edit</a>';
                                    }
                                    ?>
                                    <?php if ($_SESSION['role'] == 1): ?>
                                        <a class="dropdown-item" href="#" onclick="return confirmDelete('<?php echo $row['id']; ?>')">
                                            <i class="fa fa-trash-o m-r-5"></i> Delete
                                        </a>
                                    <?php endif; ?>
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

<!-- Diagnosis Modal -->
<div id="diagnosisModal" class="modal fade" role="dialog">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h4 class="modal-title">Diagnosis</h4>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body">
                <form id="diagnosisForm" method="post" action="outpatients.php">
                    <div class="form-group">
                        <label for="diagnosis">Enter Diagnosis:</label>
                        <input type="text" class="form-control" id="diagnosis" name="diagnosis">
                    </div>
                    <input type="hidden" id="outpatientId" name="outpatientId">
                    <button type="submit" class="btn btn-primary">Submit</button>
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Doctor Modal -->
<div id="doctorModal" class="modal fade" role="dialog">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h4 class="modal-title">Select Doctor</h4>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body">
                <form id="doctorForm" method="post" action="outpatients.php">
                    <input type="hidden" id="outpatientIdDoctor" name="outpatientIdDoctor">
                    <div class="form-group">
                        <label for="doctor">Select Doctor:</label>
                        <select class="form-control" id="doctor" name="doctor">
                            <?php
                            $doctor_query = mysqli_query($connection, "SELECT id, first_name, last_name FROM tbl_employee WHERE role = 2");
                            while ($doctor = mysqli_fetch_array($doctor_query)) {
                                $doctor_name = $doctor['first_name'] . ' ' . $doctor['last_name'];
                                echo "<option value='".$doctor['id']."'>".$doctor_name."</option>";
                            }
                            ?>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-primary">Assign Doctor</button>
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Radiology Images Grid Modal -->
<div class="modal fade" id="radiologyModal" tabindex="-1" role="dialog" aria-labelledby="radiologyModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="radiologyModalLabel">Radiographic Images</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div id="radiologyImagesContainer" class="row">
                    <!-- Images will be loaded here -->
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Image Viewer Modal -->
<div class="modal fade" id="imageViewerModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="imageViewerTitle">Radiographic Image</h5>
            </div>
            <div class="modal-body p-0">
                <div class="image-container" style="height: 80vh;">
                    <img id="viewedImage" src="" class="img-fluid" style="max-width: 100%; max-height: 100%; transform-origin: center center;">
                </div>
            </div>
            <div class="modal-footer d-flex justify-content-between align-items-center">
                <div class="zoom-controls btn-group btn-group-sm">
                    <button class="btn btn-outline-secondary zoom-out-btn" title="Zoom Out">
                        <i class="fas fa-search-minus"></i>
                    </button>
                    <button class="btn btn-outline-secondary zoom-reset-btn" title="Reset Zoom">
                        <i class="fas fa-expand"></i>
                    </button>
                    <button class="btn btn-outline-secondary zoom-in-btn" title="Zoom In">
                        <i class="fas fa-search-plus"></i>
                    </button>
                </div>
                <div class="rotation-controls btn-group btn-group-sm">
                    <button class="btn btn-outline-secondary rotate-left-btn" title="Rotate Left">
                        <i class="fas fa-undo"></i>
                    </button>
                    <button class="btn btn-outline-secondary rotate-right-btn" title="Rotate Right">
                        <i class="fas fa-redo"></i>
                    </button>
                </div>
                <div class="ml-auto">
                    <button type="button" class="btn btn-primary" data-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Success and Error Alerts -->
<div id="successAlert"></div>
<div id="errorAlert"></div>

<?php
include('footer.php');
?>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
// Image viewer variables
var currentZoom = 1;
var currentRotation = 0;
var isDragging = false;
var startX, startY, translateX = 0, translateY = 0;

function updateImageTransform() {
    const transform = `translate(${translateX}px, ${translateY}px) rotate(${currentRotation}deg) scale(${currentZoom})`;
    $('#viewedImage').css('transform', transform);
}

function openImageViewer(imageId, examType, imageSrc) {
    $('#imageViewerTitle').text(examType);
    $('#viewedImage').attr('src', imageSrc);
    
    // Reset viewer state
    currentZoom = 1;
    currentRotation = 0;
    translateX = 0;
    translateY = 0;
    updateImageTransform();
    
    $('#imageViewerModal').modal('show');
}

function showRadiologyImages(patientId) {
    // Show loading state
    $('#radiologyImagesContainer').html(`
        <div class="col-12 text-center py-5">
            <div class="spinner-border text-primary" role="status">
                <span class="sr-only">Loading...</span>
            </div>
            <p class="mt-2">Loading images...</p>
        </div>
    `);
    
    // Show the modal
    $('#radiologyModal').modal('show');
    
    // Fetch radiology images
    $.ajax({
        url: 'fetch-radiology-images.php',
        type: 'GET',
        data: { patient_id: patientId },
        dataType: 'json',
        success: function(data) {
            if (data.images && data.images.length > 0) {
                let content = '';
                data.images.forEach(image => {
                    content += `
                        <div class="col-md-4 mb-3">
                            <div class="card h-100">
                                <img src="fetch-image.php?id=${image.id}" 
                                     class="card-img-top" 
                                     style="height: 200px; object-fit: cover; cursor: pointer"
                                     onclick="openImageViewer('${image.id}', '${image.exam_type.replace(/'/g, "\\'")}', 'fetch-image.php?id=${image.id}')"
                                     alt="Radiology Image">
                                <div class="card-body">
                                    <h6 class="card-title mb-1">${image.exam_type}</h6>
                                    <p class="card-text small text-muted">${image.test_type}</p>
                                </div>
                            </div>
                        </div>`;
                });
                $('#radiologyImagesContainer').html(content);
            } else {
                $('#radiologyImagesContainer').html(`
                    <div class="col-12 text-center py-5">
                        <div class="text-muted">
                            <i class="fas fa-image fa-3x mb-3"></i>
                            <p>No radiographic images found for this patient.</p>
                        </div>
                    </div>
                `);
            }
        },
        error: function(xhr, status, error) {
            console.error('Error fetching images:', error);
            $('#radiologyImagesContainer').html(`
                <div class="col-12 text-center py-5">
                    <div class="text-danger">
                        <i class="fas fa-exclamation-circle fa-3x mb-3"></i>
                        <p>Failed to load radiographic images. Please try again.</p>
                    </div>
                </div>
            `);
        }
    });
}

$(document).ready(function() {
    // Initialize zoom controls
    $('.zoom-in-btn').on('click', function() {
        currentZoom *= 1.2;
        updateImageTransform();
    });
    
    $('.zoom-out-btn').on('click', function() {
        currentZoom /= 1.2;
        if (currentZoom < 0.5) currentZoom = 0.5;
        updateImageTransform();
    });
    
    $('.zoom-reset-btn').on('click', function() {
        currentZoom = 1;
        currentRotation = 0;
        translateX = 0;
        translateY = 0;
        updateImageTransform();
    });
    
    // Rotation controls
    $('.rotate-left-btn').on('click', function() {
        currentRotation -= 90;
        updateImageTransform();
    });
    
    $('.rotate-right-btn').on('click', function() {
        currentRotation += 90;
        updateImageTransform();
    });
    
    // Drag functionality
    const imageContainer = $('.image-container');
    
    imageContainer.on('mousedown touchstart', function(e) {
        isDragging = true;
        startX = (e.type === 'mousedown') ? e.pageX : e.originalEvent.touches[0].pageX;
        startY = (e.type === 'mousedown') ? e.pageY : e.originalEvent.touches[0].pageY;
        e.preventDefault();
    });
    
    $(document).on('mousemove touchmove', function(e) {
        if (!isDragging) return;
        
        const currentX = (e.type === 'mousemove') ? e.pageX : e.originalEvent.touches[0].pageX;
        const currentY = (e.type === 'mousemove') ? e.pageY : e.originalEvent.touches[0].pageY;
        
        translateX += (currentX - startX);
        translateY += (currentY - startY);
        
        startX = currentX;
        startY = currentY;
        
        updateImageTransform();
        e.preventDefault();
    });
    
    $(document).on('mouseup touchend', function() {
        isDragging = false;
    });
    
    // Other existing functions
    function confirmDelete(id) {
        return Swal.fire({
            title: 'Delete Patient Record?',
            text: 'Are you sure you want to delete this Patient record? This action cannot be undone!',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#12369e',
            confirmButtonText: 'OK'
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = 'outpatients.php?ids=' + id;
            }
        });
    }

    function clearSearch() {
        document.getElementById("outpatientSearchInput").value = '';
        filterOutpatients();
    }
    
    function filterOutpatients() {
        var input = document.getElementById("outpatientSearchInput").value;
        
        $.ajax({
            url: 'fetch_outpatients.php',
            method: 'GET',
            data: { query: input },
            success: function(response) {
                var data = JSON.parse(response);
                updateTable(data);
            }
        });
    }

    function updateTable(data) {
        var tbody = $('#outpatientTable tbody');
        tbody.empty();
        
        const escapeHtml = (unsafe) => {
            return unsafe?.toString()?.replace(/[&<>"']/g, function(match) {
                return {
                    '&': '&amp;',
                    '<': '&lt;',
                    '>': '&gt;',
                    '"': '&quot;',
                    "'": '&#039;'
                }[match];
            }) || '';
        };

        data.forEach(function(row) {
            // Calculate age from DOB
            const dob = new Date(row.dob);
            const age = isNaN(dob.getTime()) ? '' : Math.floor((new Date() - dob) / (365.25 * 24 * 60 * 60 * 1000));
            
            // Format date and time
            const dateTime = new Date(row.date_time);
            const formattedDateTime = isNaN(dateTime.getTime()) ? '' : 
                dateTime.toLocaleDateString('en-US', { 
                    month: 'long', 
                    day: 'numeric', 
                    year: 'numeric',
                    hour: '2-digit',
                    minute: '2-digit'
                });
            
            // Doctor display logic
            const doctorDisplay = row.doctor_incharge ? 
                escapeHtml(row.doctor_incharge) : 
                (role == 3 ?  
                    `<button class="btn btn-primary btn-sm select-doctor-btn" 
                        data-toggle="modal" 
                        data-target="#doctorModal" 
                        data-id="${escapeHtml(row.outpatient_id)}">
                        Select Doctor
                    </button>` : 
                    ''
                );

            // View Result button (only for doctors and admins)
            const viewResultButton = (role == 2 || role == 1) ? 
                `<form action="generate-result.php" method="get">
                    <input type="hidden" name="patient_id" value="${escapeHtml(row.patient_id)}">
                    <button class="btn btn-primary btn-sm custom-btn" type="submit">
                        <i class="fa fa-file-pdf-o m-r-5"></i> View Result
                    </button>
                </form>` : 
                '';

            // Radiology Images button (only for doctors and admins)
            const radiologyImagesButton = (role == 2 || role == 1) ? 
                `<button class="btn btn-primary btn-sm" onclick="showRadiologyImages('${escapeHtml(row.patient_id)}')">
                    <i class="fa fa-image m-r-5"></i> View Images
                </button>` : 
                '';

            // Action buttons
            let actionButtons = '';
            
            // Diagnosis button (only for assigned doctors)
            if (role == 2 && doctor_name === row.doctor_incharge) {
                actionButtons += `
                    <button class="dropdown-item diagnosis-btn" 
                        data-toggle="modal" 
                        data-target="#diagnosisModal" 
                        data-id="${escapeHtml(row.outpatient_id)}" 
                        ${row.diagnosis ? 'disabled' : ''}>
                        <i class="fa fa-stethoscope m-r-5"></i> Diagnosis
                    </button>`;
            }
            
            // Edit and Delete buttons (for admins)
            if (role == 1) {
                actionButtons += `
                    <a class="dropdown-item" href="edit-outpatient.php?id=${escapeHtml(row.id)}">
                        <i class="fa fa-pencil m-r-5"></i> Edit
                    </a>
                    <a class="dropdown-item" href="#" onclick="return confirmDelete('${escapeHtml(row.id)}')">
                        <i class="fa fa-trash-o m-r-5"></i> Delete
                    </a>`;
            }

            // Treatment button (for assigned doctors)
            if (role == 2 && doctor_name === row.doctor_incharge) {
                actionButtons += `
                    <button class="dropdown-item treatment-btn" 
                        data-toggle="modal" 
                        data-target="#treatmentModal" 
                        data-id="${escapeHtml(row.outpatient_id)}">
                        <i class="fa fa-medkit m-r-5"></i> Treatment
                    </button>`;
            }

            // Build the table row
            tbody.append(`<tr>
                <td>${escapeHtml(row.patient_id)}</td>
                <td>${escapeHtml(row.outpatient_id)}</td>
                <td>${escapeHtml(row.patient_name)}</td>
                <td>${age}</td>
                <td>${escapeHtml(row.dob)}</td>
                <td>${escapeHtml(row.gender)}</td>
                <td>${doctorDisplay}</td>
                <td>${viewResultButton}</td>
                <td>${radiologyImagesButton}</td>
                <td>${escapeHtml(row.diagnosis)}</td>
                <td>${formattedDateTime}</td>
                <td class="text-right">
                    <div class="dropdown dropdown-action">
                        <a href="#" class="action-icon dropdown-toggle" data-toggle="dropdown" aria-expanded="false">
                            <i class="fa fa-ellipsis-v"></i>
                        </a>
                        <div class="dropdown-menu dropdown-menu-right">
                            ${actionButtons}
                        </div>
                    </div>
                </td>
            </tr>`);
        });
    }

    function searchPatients() {
        var input = document.getElementById("patientSearchInput").value;
        if (input.length < 2) {
            document.getElementById("searchResults").style.display = "none";
            document.getElementById("searchResults").innerHTML = "";
            return;
        }
        $.ajax({
            url: "search-outpatient.php",
            method: "GET",
            data: { query: input },
            success: function (data) {
                var results = document.getElementById("searchResults");
                results.innerHTML = data;
                results.style.display = "block";
            },
        });
    }

    $(document).on("click", ".search-result", function () {
        var patientId = $(this).data("id");
        var patientName = $(this).text();

        $("#patientId").val(patientId);
        $("#patientSearchInput").val(patientName);
        $("#addPatientBtn").prop("disabled", false);
        $("#searchResults").html("").hide();
    });
        
    $('.dropdown-toggle').on('click', function (e) {
        var $el = $(this).next('.dropdown-menu');
        var isVisible = $el.is(':visible');
        
        $('.dropdown-menu').slideUp('400');
        
        if (!isVisible) {
            $el.stop(true, true).slideDown('400');
        }
        
        $(document).on('click', function (e) {
            if (!$(e.target).closest('.dropdown').length) {
                $('.dropdown-menu').slideUp('400');
            }
        });
    });

    $(document).on('click', '.select-doctor-btn', function() {
        var outpatientId = $(this).data('id');
        $('#outpatientIdDoctor').val(outpatientId);
        $('#doctorModal').modal('show');
    });

    $('#doctorForm').submit(function(e) {
        e.preventDefault();
        var outpatientId = $('#outpatientIdDoctor').val();
        var doctorId = $('#doctor').val();

        $.ajax({
            url: 'outpatients.php',
            type: 'POST',
            data: {
                outpatientIdDoctor: outpatientId,
                doctorId: doctorId
            },
            success: function(response) {
                location.reload();
            },
            error: function(xhr, status, error) {
                alert('Error assigning doctor');
            }
        });
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
        .dropdown-menu {
        border: 1px solid rgba(0, 0, 0, 0.1);
        border-radius: 3px;
        transform-origin: top right;
        box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
    }

    .dropdown-item {
        padding: 7px 15px;
        color: #333;
    }

    .dropdown-item:hover {
        background-color: #f8f9fa;
        color: #12369e;
    }

    .dropdown-item i {
        margin-right: 8px;
        color: #777;
    }

    .dropdown-item:hover i {
        color: #12369e;
    }

    .image-container {
        background: #000;
        display: flex;
        align-items: center;
        justify-content: center;
        overflow: hidden;
        position: relative;
        height: 80vh;
    }

    #modalImage {
        transform-origin: center center;
        transition: transform 0.15s ease-out;
        max-height: 100%;
        max-width: 100%;
        position: absolute;
    }

    .modal-content {
        user-select: none;
    }

    .zoom-controls .btn, .btn-group-sm .btn {
        width: 32px;
        height: 32px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
    }

    .radiology-images-modal .swal2-content {
        padding: 20px;
    }

    .radiology-images-modal .card {
        transition: transform 0.2s;
    }

    .radiology-images-modal .card:hover {
        transform: scale(1.02);
    }

    /* Responsive adjustments */
    @media (max-width: 768px) {
        .modal-footer {
            flex-wrap: wrap;
        }
        
        .zoom-controls, .btn-group {
            margin-bottom: 8px;
        }
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
    #radiologyImagesContainer .card {
        transition: transform 0.2s;
        cursor: pointer;
    }

    #radiologyImagesContainer .card:hover {
        transform: scale(1.02);
        box-shadow: 0 4px 8px rgba(0,0,0,0.1);
    }

    #radiologyImagesContainer .card-img-top {
        object-fit: cover;
        height: 200px;
    }
</style>