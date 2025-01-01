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
                <a href="lab-order.php" class="btn btn-primary btn-rounded float-right"><i class="fa fa-plus"></i> Add Order</a>
            </div>
         </div>
         <input class="form-control mb-4" type="text" id="patientSearchInput" onkeyup="filterPatients()" placeholder="Search for Patient">
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
                            $fetch_patients_query = mysqli_query($connection, "SELECT patient_id, patient_type, first_name, last_name, dob, gender, date_time FROM tbl_patient");
                            while ($patient_row = mysqli_fetch_array($fetch_patients_query)) 
                            {
                                $dob = $patient_row['dob'];
                                $date = str_replace('/', '-', $dob); 
                                $dob = date('Y-m-d', strtotime($date));
                                $year = (date('Y') - date('Y',strtotime($dob)));
                            ?>
                                <tr class="clickable-row" data-href="patient-labtest-records.php?patient_id=<?php echo $patient_row['patient_id']; ?>">
                                    <td><?php echo $patient_row['patient_id']; ?></td> 
                                    <td><?php echo $patient_row['patient_type']; ?></td> 
                                    <td><?php echo $patient_row['first_name']; ?></td>
                                    <td><?php echo $patient_row['last_name']; ?></td>
                                    <td><?php echo $year; ?></td>
                                    <td><?php echo $patient_row['gender']; ?></td>
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

<script>
    document.addEventListener("DOMContentLoaded", function () {
        var table = document.getElementById('patientTable');
        var rows = table.getElementsByTagName('tr');

        for (var i = 0; i < rows.length; i++) {
            rows[i].addEventListener('click', function () {
                var href = this.getAttribute('data-href');
                if (href) {
                    window.location.href = href;
                }
            });
        }
    });
    function filterPatients() {
        var input, filter, table, tr, td, i, txtValue;
        input = document.getElementById("patientSearchInput");
        filter = input.value.toUpperCase();
        table = document.getElementById("patientTable");
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
    .btn-primary {
            background: #12369e;
            border: none;
        }
        .btn-primary:hover {
            background: #05007E;
        }
</style>