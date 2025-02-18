<?php
session_start();
if (empty($_SESSION['name'])) {
    header('location:index.php');
}

include('header.php');
include('includes/connection.php');
?>

<div class="page-wrapper">
    <div class="content">
        <div class="row">
            <div class="col-sm-4 col-3">
                <h4 class="page-title">Patient Information</h4>
            </div>
            <div class="col-sm-8 col-9 text-right m-b-20">
                <a href="lab-order.php" class="btn btn-primary float-right"><i class="fa fa-plus"></i> Add Order</a>
            </div>
        </div>
        <div class="table-responsive">
            <div class="sticky-search">
            <h5 class="font-weight-bold mb-2">Search Patient:</h5>
                <div class="input-group mb-3">
                    <div class="position-relative w-100">
                        <!-- Search Icon -->
                        <i class="fa fa-search position-absolute text-secondary" style="top: 50%; left: 12px; transform: translateY(-50%);"></i>
                        <!-- Input Field -->
                        <input class="form-control" type="text" id="patientSearchInput" onkeyup="filterPatients()" style="padding-left: 35px; padding-right: 35px;">
                        <!-- Clear Button -->
                        <button class="position-absolute border-0 bg-transparent text-secondary" type="button" onclick="clearSearch()" style="top: 50%; right: 10px; transform: translateY(-50%);">
                            <i class="fa fa-times"></i>
                        </button>
                    </div>
                </div>
            </div>
        </div>
        <div class="table-responsive">
            <table class="datatable table table-hover" id="patientTable">
                <thead style="background-color: #CCCCCC;">
                    <tr>
                        <th>Patient ID</th>
                        <th>Patient Type</th>
                        <th>First Name</th>
                        <th>Last Name</th>
                        <th>Age</th>
                        <th>Gender</th>
                        <th>Date Time</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $fetch_patients_query = mysqli_query($connection, "SELECT patient_id, patient_type, first_name, last_name, dob, gender, date_time FROM tbl_patient ORDER BY last_name ASC") or die(mysqli_error($connection));
                    while ($patient_row = mysqli_fetch_array($fetch_patients_query)) {
                        $dob = $patient_row['dob'];
                        $dob = date('Y-m-d', strtotime(str_replace('/', '-', $dob)));
                        $age = date('Y') - date('Y', strtotime($dob));
                    ?>
                        <tr class="clickable-row" data-href="patient-labtest-records.php?patient_id=<?php echo htmlspecialchars($patient_row['patient_id']); ?>">
                            <td><?php echo htmlspecialchars($patient_row['patient_id']); ?></td>
                            <td><?php echo htmlspecialchars($patient_row['patient_type']); ?></td>
                            <td><?php echo htmlspecialchars($patient_row['first_name']); ?></td>
                            <td><?php echo htmlspecialchars($patient_row['last_name']); ?></td>
                            <td><?php echo $age; ?></td>
                            <td><?php echo htmlspecialchars($patient_row['gender']); ?></td>
                            <td><?php echo date('F d, Y g:i A', strtotime($patient_row['date_time'])); ?></td>
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

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    document.addEventListener("DOMContentLoaded", function () {
        var rows = document.querySelectorAll('#patientTable tbody tr');
        rows.forEach(function (row) {
            row.addEventListener('click', function () {
                var href = this.getAttribute('data-href');
                if (href) {
                    window.location.href = href;
                }
            });
        });
    });

    function clearSearch() {
        document.getElementById("patientSearchInput").value = '';
        filterPatients();
    }

    function filterPatients() {
        var input = document.getElementById("patientSearchInput").value;
        
        $.ajax({
            url: 'fetch_lab_patients.php',
            method: 'GET',
            data: { query: input },
            success: function(response) {
                var data = JSON.parse(response);
                updatePatientsTable(data);
            }
        });
    }

    function updatePatientsTable(data) {
        var tbody = $('#patientTable tbody');
        tbody.empty();
        
        if (data.length === 0) {
            Swal.fire({
                icon: 'info',
                title: 'No Patients Found',
                text: 'Try searching for a different name or ID.',
            });
        }
        
        data.forEach(function(row) {
            tbody.append(`
                <tr class="clickable-row" data-href="patient-labtest-records.php?patient_id=${row.patient_id}">
                    <td>${row.patient_id}</td>
                    <td>${row.patient_type}</td>
                    <td>${row.first_name}</td>
                    <td>${row.last_name}</td>
                    <td>${row.age}</td>
                    <td>${row.gender}</td>
                    <td>${row.date_time}</td>
                </tr>
            `);
        });

        // Reattach click handlers for the new rows
        $('.clickable-row').click(function() {
            window.location = $(this).data('href');
        });
    }

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
    /* Custom SweetAlert Button Color */
    .swal2-confirm {
        background-color: #12369e !important;
        color: white !important;
        border: none !important;
    }
    
    /* Hover color for the confirm button */
    .swal2-confirm:hover {
        background-color: #05007E !important;
    }
    
    /* Adjust button focus styles (optional) */
    .swal2-confirm:focus {
        box-shadow: 0 0 0 0.2rem rgba(18, 54, 158, 0.5) !important;
    }
</style>

<style>
    .btn-primary {
        background: #12369e;
        border: none;
    }

    .btn-primary:hover {
        background: #05007E;
    }
</style>
