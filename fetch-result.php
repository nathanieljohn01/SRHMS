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

    // Check database connection
    if (!$connection) {
        die("Database connection failed: " . mysqli_connect_error());
    }

    // Fetch the number of lab test records for the patient using prepared statements
    $fetch_lab_tests_stmt = mysqli_prepare($connection, "SELECT COUNT(*) AS num_records FROM tbl_cbc WHERE patient_id = ?");
    if (!$fetch_lab_tests_stmt) {
        die("Query preparation failed: " . mysqli_error($connection));
    }
    mysqli_stmt_bind_param($fetch_lab_tests_stmt, 's', $patient_id);
    mysqli_stmt_execute($fetch_lab_tests_stmt);
    mysqli_stmt_bind_result($fetch_lab_tests_stmt, $num_records);
    mysqli_stmt_fetch($fetch_lab_tests_stmt);
    mysqli_stmt_close($fetch_lab_tests_stmt);

    $fetch_lab_tests_stmt = mysqli_prepare($connection, "SELECT COUNT(*) AS num_records FROM tbl_urinalysis WHERE patient_id = ?");
    if (!$fetch_lab_tests_stmt) {
        die("Query preparation failed: " . mysqli_error($connection));
    }
    mysqli_stmt_bind_param($fetch_lab_tests_stmt, 's', $patient_id);
    mysqli_stmt_execute($fetch_lab_tests_stmt);
    mysqli_stmt_bind_result($fetch_lab_tests_stmt, $num_records);
    mysqli_stmt_fetch($fetch_lab_tests_stmt);
    mysqli_stmt_close($fetch_lab_tests_stmt);

?>

<!-- HTML content starts here -->
<div class="page-wrapper">
    <div class="content">
        <div class="row">
            <div class="col-sm-4 col-3">
                <h4 class="page-title">Lab Results</h4>
            </div>
            <div class="col-sm-8 col-9 text-right m-b-20">
                <a href="outpatients.php" class="btn btn-primary btn-rounded">Back</a>
            </div>
        </div>

        <!-- Table for lab tests -->
        <div class="table-responsive">
            <h4 class="mt-3" style="color: #393A39;"><strong>Complete Blood Count</strong></h4>
            <table class="datatable table table-bordered table-striped" id="labTestTable">
                <thead style="background-color: #CCCCCC;">
                    <tr>
                        <th>CBC ID</th>
                        <th>Date and Time</th>
                        <th>Hemoglobin</th>
                        <th>Hematocrit</th>
                        <th>Red Blood Cells</th>
                        <th>White Blood Cells</th>
                        <th>ESR</th>
                        <th>Segmenters</th>
                        <th>Lymphocytes</th>
                        <th>Monocytes</th>
                        <th>Bands</th>
                        <th>Platelets</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    // Fetch lab test records for the patient
                    $fetch_lab_tests_query = mysqli_query($connection, "SELECT cbc_id, date_time, hemoglobin, hematocrit, red_blood_cells, white_blood_cells, esr, segmenters, lymphocytes, monocytes, bands, platelets FROM tbl_cbc WHERE patient_id = '$patient_id'");
                    while ($lab_test_row = mysqli_fetch_assoc($fetch_lab_tests_query)) {
                    $date_time = date('F d, Y g:i A', strtotime($lab_test_row['date_time']));
                    ?>
                        <tr>
                            <td><?php echo $lab_test_row['cbc_id']; ?></td>
                            <td><?php echo $date_time; ?></td>
                            <td><?php echo $lab_test_row['hemoglobin']; ?></td>
                            <td><?php echo $lab_test_row['hematocrit']; ?></td>
                            <td><?php echo $lab_test_row['red_blood_cells']; ?></td>
                            <td><?php echo $lab_test_row['white_blood_cells']; ?></td>
                            <td><?php echo $lab_test_row['esr']; ?></td>
                            <td><?php echo $lab_test_row['segmenters']; ?></td>
                            <td><?php echo $lab_test_row['lymphocytes']; ?></td>
                            <td><?php echo $lab_test_row['monocytes']; ?></td>
                            <td><?php echo $lab_test_row['bands']; ?></td>
                            <td><?php echo $lab_test_row['platelets']; ?></td>
                        </tr>
                    <?php } ?>
                </tbody>
            </table>
        </div>

        <!-- Macroscopic Urinalysis Table -->
        <div class="table-responsive">
            <h4 class="mt-3" style="color: #222222;"><strong>Macroscopic Urinalysis</strong></h4>
            <table class="datatable table table-bordered table-striped" id="macroscopicTable">
                <thead style="background-color: #CCCCCC;">
                    <tr>
                        <th>Urinalysis ID</th>
                        <th>Date and Time</th>
                        <th>Color</th>
                        <th>Transparency</th>
                        <th>Reaction</th>
                        <th>Protein</th>
                        <th>Glucose</th>
                        <th>Specific Gravity</th>
                        <th>Ketone</th>
                        <th>Urobilinogen</th>
                        <th>Pregnancy Test</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    // Fetch macroscopic lab test records for the patient
                    $fetch_macroscopic_query = mysqli_query($connection, "SELECT urinalysis_id, date_time, color, transparency, reaction, protein, glucose, specific_gravity, ketone, urobilinogen, pregnancy_test FROM tbl_urinalysis WHERE patient_id = '$patient_id'");
                    while ($macroscopic_row = mysqli_fetch_assoc($fetch_macroscopic_query)) {
                        $date_time = date('F d, Y g:i A', strtotime($macroscopic_row['date_time']));
                        ?>
                        <tr>
                            <td><?php echo $macroscopic_row['urinalysis_id']; ?></td>
                            <td><?php echo $date_time; ?></td>
                            <td><?php echo $macroscopic_row['color']; ?></td>
                            <td><?php echo $macroscopic_row['transparency']; ?></td>
                            <td><?php echo $macroscopic_row['reaction']; ?></td>
                            <td><?php echo $macroscopic_row['protein']; ?></td>
                            <td><?php echo $macroscopic_row['glucose']; ?></td>
                            <td><?php echo $macroscopic_row['specific_gravity']; ?></td>
                            <td><?php echo $macroscopic_row['ketone']; ?></td>
                            <td><?php echo $macroscopic_row['urobilinogen']; ?></td>
                            <td><?php echo $macroscopic_row['pregnancy_test']; ?></td>
                        </tr>
                    <?php } ?>
                </tbody>
            </table>
        </div>

        <!-- Microscopic Urinalysis Table -->
        <div class="table-responsive">
            <h4 class="mt-3" style="color: #222222;"><strong>Microscopic Urinalysis</strong></h4>
            <table class="datatable table table-bordered table-striped" id="microscopicTable">
                <thead style="background-color: #CCCCCC;">
                    <tr>
                        <th>Urinalysis ID</th>
                        <th>Date and Time</th>
                        <th>Pus Cells</th>
                        <th>Red Blood Cells</th>
                        <th>Epithelial Cells</th>
                        <th>A Urates & A Phosphates</th>
                        <th>Mucus Threads</th>
                        <th>Bacteria</th>
                        <th>Calcium Oxalates</th>
                        <th>Uric Acid Crystals</th>
                        <th>Pus Cells Clumps</th>
                        <th>Coarse Granular Cast</th>
                        <th>Hyaline Cast</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    // Fetch microscopic lab test records for the patient
                    $fetch_microscopic_query = mysqli_query($connection, "SELECT urinalysis_id, date_time, pus_cells, red_blood_cells, epithelial_cells, a_urates_a_phosphates, mucus_threads, bacteria, calcium_oxalates, uric_acid_crystals, pus_cells_clumps, coarse_granular_cast, hyaline_cast FROM tbl_urinalysis WHERE patient_id = '$patient_id'");
                    while ($microscopic_row = mysqli_fetch_assoc($fetch_microscopic_query)) {
                        $date_time = date('F d, Y g:i A', strtotime($microscopic_row['date_time']));
                        ?>
                        <tr>
                            <td><?php echo $microscopic_row['urinalysis_id']; ?></td>
                            <td><?php echo $date_time; ?></td>
                            <td><?php echo $microscopic_row['pus_cells']; ?></td>
                            <td><?php echo $microscopic_row['red_blood_cells']; ?></td>
                            <td><?php echo $microscopic_row['epithelial_cells']; ?></td>
                            <td><?php echo $microscopic_row['a_urates_a_phosphates']; ?></td>
                            <td><?php echo $microscopic_row['mucus_threads']; ?></td>
                            <td><?php echo $microscopic_row['bacteria']; ?></td>
                            <td><?php echo $microscopic_row['calcium_oxalates']; ?></td>
                            <td><?php echo $microscopic_row['uric_acid_crystals']; ?></td>
                            <td><?php echo $microscopic_row['pus_cells_clumps']; ?></td>
                            <td><?php echo $microscopic_row['coarse_granular_cast']; ?></td>
                            <td><?php echo $microscopic_row['hyaline_cast']; ?></td>
                        </tr>
                    <?php } ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<!-- HTML content ends here -->

<?php
} else {
    header('location: index.php');
    exit();
}

include('footer.php');
?>


<script>
 function filterTests() {
    var input, filter, table, tr, td, i, txtValue;
    input = document.getElementById("labTestSearchInput");
    filter = input.value.toUpperCase();
    table = document.getElementById("labTestTable");
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
</script>

<style>
    /* Hide the length change dropdown */
    .dataTables_length {
    display: none;
    }

    /* Optional: Hide pagination if you don’t need it */
    .dataTables_paginate {
        display: none;
    }

    /* Optional: Hide the information row (showing X to Y of Z entries) */
    .dataTables_info {
        display: none;
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
</style>



