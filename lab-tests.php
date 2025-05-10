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
            <div class="sticky-search">
            <h5 class="font-weight-bold mb-2">Search for Lab Test:</h5>
                <div class="input-group mb-3">
                    <div class="position-relative w-100">
                        <!-- Search Icon -->
                        <i class="fa fa-search position-absolute text-secondary" style="top: 50%; left: 12px; transform: translateY(-50%);"></i>
                        <!-- Input Field -->
                        <input class="form-control" type="text" id="labTestSearchInput" onkeyup="filterTests()" placeholder="Search" style="padding-left: 35px; padding-right: 35px;">
                        <!-- Clear Button -->
                        <button class="position-absolute border-0 bg-transparent text-secondary" type="button" onclick="clearSearch()" style="top: 50%; right: 10px; transform: translateY(-50%);">
                            <i class="fa fa-times"></i>
                        </button>
                    </div>
                </div>
            </div>
        </div>
        <div class="table-responsive">
            <table class="datatable table table-hover" id="labTestTable">
                <thead style="background-color: #CCCCCC;">
                    <tr>
                        <th>Lab Department</th> 
                        <th>Lab Test</th>
                        <th>Lab Code</th>
                        <th>Status</th>
                        <th>Action</th> 
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
                                <div class="custom-control custom-switch">
                                    <input type="checkbox" class="custom-control-input" id="toggle_<?php echo $test_row['code']; ?>" 
                                        <?php echo $test_row['status'] === 'Available' ? 'checked' : ''; ?> 
                                        onchange="toggleStatus(this, '<?php echo $test_row['lab_test']; ?>')">
                                    <label class="custom-control-label" for="toggle_<?php echo $test_row['code']; ?>"></label>
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
        xhttp.open("POST", "update-lab-status.php", true);
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
    
    .custom-switch .custom-control-label::before {
        width: 3rem;
        height: 1.5rem;
        border-radius: 1rem;
        background-color: #e9ecef;
        border: none;
        transition: all 0.3s ease;
    }

    .custom-switch .custom-control-label::after {
        width: 1.25rem;
        height: 1.25rem;
        border-radius: 50%;
        background-color: #fff;
        box-shadow: 0 2px 4px rgba(0,0,0,0.2);
        transform: translateX(0.25rem);
        transition: all 0.3s ease;
    }

    .custom-switch .custom-control-input:checked ~ .custom-control-label::before {
        background-color: #12369e;
        border-color: #12369e;
    }

    .custom-switch .custom-control-input:checked ~ .custom-control-label::after {
        transform: translateX(1.5rem);
    }

    .custom-switch .custom-control-input:focus ~ .custom-control-label::before {
        box-shadow: 0 0 0 0.2rem rgba(18, 54, 158, 0.25);
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