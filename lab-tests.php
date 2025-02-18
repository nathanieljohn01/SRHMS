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
            <div class="col-sm-12">
                <h4 class="page-title">Laboratory Tests</h4>
            </div>
        </div>
        <div class="table-responsive">
            <div class="input-group">
            <input class="form-control mb-3" type="text" id="labTestSearchInput" onkeyup="filterTests()" placeholder="Search for Lab Test">
                <div class="input-group-append">
                    <button class="btn btn-outline-primary mb-3" type="button" onclick="clearSearch()">
                        <i class="fa fa-times"></i>
                    </button>
                </div>
            </div>
            <table class="datatable table table-hover" id="labTestTable">
                <thead style="background-color: #CCCCCC;">
                    <tr>
                        <th>Lab Department</th> 
                        <th>Lab Test</th>
                        <th>Lab Code</th>
                        <th>Status</th>
                        <th>Action</th> <!-- Added Action column -->
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $fetch_lab_tests_query = mysqli_query($connection, "SELECT lab_department, lab_test, code, status FROM tbl_labtest");
                    while ($test_row = mysqli_fetch_array($fetch_lab_tests_query)) 
                    {
                    ?>
                        <tr>
                            <td><?php echo $test_row['lab_department']; ?></td> 
                            <td><?php echo $test_row['lab_test']; ?></td>
                            <td><?php echo $test_row['code']; ?></td>
                            <td><?php echo $test_row['status']; ?></td>
                            <td>
                                <label class="switch">
                                    <input type="checkbox" <?php echo $test_row['status'] === 'Available' ? 'checked' : ''; ?> onclick="toggleStatus(this, '<?php echo $test_row['lab_test']; ?>')">
                                    <span class="slider round"></span>
                                </label>
                            </td> <!-- Added toggle switch for status -->
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
     function clearSearch() {
        document.getElementById("labTestSearchInput").value = '';
        filterTests();
     }
     function filterTests() {
        var input, filter, table, tr, td, i, j, txtValue;
        input = document.getElementById("labTestSearchInput");
        filter = input.value.toUpperCase();
        table = document.getElementById("labTestTable");
        tr = table.getElementsByTagName("tr");

        // If DataTable is initialized, clear its search and use manual filtering
        if ($.fn.DataTable.isDataTable("#labTestTable")) {
            var labTestTableInstance = $('#labTestTable').DataTable();
            labTestTableInstance.search('').draw();  // Clear DataTable search
            labTestTableInstance.page.len(-1).draw();  // Show all rows temporarily
        }

        // Manual filtering logic
        for (i = 0; i < tr.length; i++) {
            var matchFound = false;
            for (j = 0; j < tr[i].cells.length; j++) {
                td = tr[i].cells[j];
                if (td) {
                    txtValue = td.textContent || td.innerText;
                    if (txtValue.toUpperCase().indexOf(filter) > -1) {
                        matchFound = true;
                        break;
                    }
                }
            }
            // Show row if a match is found, or if it's the header row
            if (matchFound || i === 0) {
                tr[i].style.display = "";
            } else {
                tr[i].style.display = "none";
            }
        }

        // Restore DataTable pagination if input is empty
        if (filter.trim() === "") {
            if ($.fn.DataTable.isDataTable("#labTestTable")) {
                var labTestTableInstance = $('#labTestTable').DataTable();
                labTestTableInstance.page.len(10).draw(); // Reset pagination to default
            }
        }
    }

    // Initialize DataTable
    $(document).ready(function() {
        $('#labTestTable').DataTable();
    });
    
    function toggleStatus(checkbox, labTest) {
        var statusCell = checkbox.parentNode.parentNode.previousElementSibling; // Get the cell containing the status
        var newStatus = checkbox.checked ? 'Available' : 'Not Available'; // Set the new status based on checkbox state

        // Send AJAX request to update the status in the database
        var xhttp = new XMLHttpRequest();
        xhttp.onreadystatechange = function() {
            if (this.readyState == 4 && this.status == 200) {
                // Update the status in the UI if the request is successful
                statusCell.innerText = newStatus;
            }
        };
        xhttp.open("POST", "update_lab_status.php", true);
        xhttp.setRequestHeader("Content-type", "application/x-www-form-urlencoded");
        xhttp.send("lab_test=" + encodeURIComponent(labTest) + "&status=" + encodeURIComponent(newStatus));
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
    .switch {
        position: relative;
        display: inline-block;
        width: 60px;
        height: 34px;
    }

    .switch input {
        opacity: 0;
        width: 0;
        height: 0;
    }

    .slider {
        position: absolute;
        cursor: pointer;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background-color: #ccc;
        transition: .4s;
        border-radius: 34px;
    }

    .slider:before {
        position: absolute;
        content: "";
        height: 26px;
        width: 26px;
        left: 4px;
        bottom: 4px;
        background-color: white;
        transition: .4s;
        border-radius: 50%;
    }

    input:checked + .slider {
        background-color: #12369e;
    }

    input:checked + .slider:before {
        transform: translateX(26px);
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