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
                <h4 class="page-title">Deceased Records</h4>
            </div>
            <div class="col-sm-8 col-9 text-right m-b-20">
                <?php 
                if ($_SESSION['role'] == 1 || $_SESSION['role'] == 3) {  
                    echo '<a href="add-deceased.php" class="btn btn-primary float-right"><i class="fa fa-plus"></i> Add Deceased </a>';
                }
                ?>
            </div>
        </div>
        <div class="table-responsive">
        <h5 class="font-weight-bold mb-2">Search Patient:</h5>
            <div class="input-group mb-3">
                <div class="position-relative w-100">
                    <!-- Search Icon -->
                    <i class="fa fa-search position-absolute text-secondary" style="top: 50%; left: 12px; transform: translateY(-50%);"></i>
                    <!-- Input Field -->
                    <input class="form-control" type="text" id="deceasedSearchInput" onkeyup="filterDeceased()" placeholder="Search" style="padding-left: 35px; padding-right: 35px;">
                    <!-- Clear Button -->
                    <button class="position-absolute border-0 bg-transparent text-secondary" type="button" onclick="clearSearch()" style="top: 50%; right: 10px; transform: translateY(-50%);">
                        <i class="fa fa-times"></i>
                    </button>
                </div>
            </div>
        </div>
        <div class="table-responsive">
            <table class="datatable table table-hover table-striped" id="deceasedTable">
                <thead style="background-color: #CCCCCC;">
                    <tr>
                        <th>Deceased ID</th>
                        <th>Patient ID</th>
                        <th>Patient Name</th>
                        <th>Date of Death</th>
                        <th>Time of Death</th>
                        <th>Cause of Death</th>
                        <th>Physician</th>
                        <th>Next of Kin Contact</th>
                        <th>Discharge Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    // Handling record deletion with prepared statements to prevent SQL injection
                    if(isset($_GET['deceased_id'])){
                        $deceased_id= sanitize($connection, $_GET['deceased_id']);
                        $update_query = $connection->prepare("UPDATE tbl_deceased SET deleted = 1 WHERE deceased_id = ?");
                        $update_query->bind_param("s", $deceased_id);
                        $update_query->execute();
                    }
                    
                    function sanitize($connection, $data) {
                        return mysqli_real_escape_string($connection, trim($data));
                    }
                    
                    // Fetching records from database with a prepared statement
                    $fetch_query = mysqli_query($connection, "SELECT * FROM tbl_deceased WHERE deleted = 0");
                    while ($row = mysqli_fetch_array($fetch_query)) {
                    ?>
                        <tr>
                            <td><?php echo htmlspecialchars($row['deceased_id']); ?></td>
                            <td><?php echo htmlspecialchars($row['patient_id']); ?></td>
                            <td><?php echo htmlspecialchars($row['patient_name']); ?></td>
                            <td><?php echo htmlspecialchars($row['dod']); ?></td>
                            <td><?php echo htmlspecialchars($row['tod']); ?></td>
                            <td><?php echo htmlspecialchars($row['cod']); ?></td>
                            <td><?php echo htmlspecialchars($row['physician']); ?></td>
                            <td><?php echo htmlspecialchars($row['next_of_kin_contact']); ?></td>
                            <td><?php echo htmlspecialchars($row['discharge_status']); ?></td>
                            <td class="text-right">
                                <div class="dropdown dropdown-action">
                                    <a href="#" class="action-icon dropdown-toggle" data-toggle="dropdown" aria-expanded="false"><i class="fa fa-ellipsis-v"></i></a>
                                    <div class="dropdown-menu dropdown-menu-right">
                                    <?php 
                                    if ($_SESSION['role'] == 1 || $_SESSION['role'] == 3) {
                                        echo '<a class="dropdown-item" href="edit-deceased.php?id='. htmlspecialchars($row['deceased_id']) .'"><i class="fa fa-pencil m-r-5"></i> Edit</a>';
                                        echo '<a class="dropdown-item" href="#" onclick="return confirmDelete(\''.$row['deceased_id'].'\')"><i class="fa fa-trash m-r-5"></i> Delete</a>';
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
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script language="JavaScript" type="text/javascript">
function confirmDelete(deceased_id) {
    return Swal.fire({
        title: 'Delete Deceased Record?',
        text: 'Are you sure you want to delete this Deceased record? This action cannot be undone!',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#12369e',
        confirmButtonText: 'Yes, delete it!'
    }).then((result) => {
        if (result.isConfirmed) {
            window.location.href = 'deceased.php?deceased_id=' + deceased_id;  
        }
    });
}
</script>

<script>
    function clearSearch() {
        document.getElementById("deceasedSearchInput").value = '';
        filterDeceased();
    }

    function filterDeceased() {
        var input = document.getElementById("deceasedSearchInput").value;

        $.ajax({
            url: 'fetch_deceased.php',
            type: 'GET',
            data: { query: input },
            success: function(response) {
                var data = JSON.parse(response);
                updateDeceasedTable(data);
            },
            error: function(xhr, status, error) {
                alert('Error fetching data. Please try again.');
            }
        });
    }


    function updateDeceasedTable(data) {
        var tbody = $('#deceasedTable tbody');
        tbody.empty();
        
        data.forEach(function(record) {
            tbody.append(`
                <tr>
                    <td>${record.deceased_id}</td>
                    <td>${record.patient_id}</td>
                    <td>${record.patient_name}</td>
                    <td>${record.dod}</td>
                    <td>${record.tod}</td>
                    <td>${record.cod}</td>
                    <td>${record.physician}</td>
                    <td>${record.next_of_kin_contact}</td>
                    <td>${record.discharge_status}</td>
                    <td class="text-right">
                        <div class="dropdown dropdown-action">
                            <a href="#" class="action-icon dropdown-toggle" data-toggle="dropdown" aria-expanded="false">
                                <i class="fa fa-ellipsis-v"></i>
                            </a>
                            <div class="dropdown-menu dropdown-menu-right">
                                ${getActionButtons(record.deceased_id)}
                            </div>
                        </div>
                    </td>
                </tr>
            `);
        });
    }

    var role = <?php echo json_encode($_SESSION['role']); ?>;

    function getActionButtons(deceasedId) {
        let buttons = '';
        
        if (role == 1 || role == 3) {
            buttons += `
                <a class="dropdown-item" href="edit-deceased.php?id=${deceasedId}">
                    <i class="fa fa-pencil m-r-5"></i> Edit
                </a>
                <a class="dropdown-item delete-btn" data-id="${deceasedId}" href="#">
                    <i class="fa fa-trash m-r-5"></i> Delete
                </a>
            `;
        }
        
        return buttons;
    }
    
    $('.dropdown-toggle').on('click', function (e) {
    var $el = $(this).next('.dropdown-menu');
    var isVisible = $el.is(':visible');
    
    // Hide all dropdowns
    $('.dropdown-menu').slideUp('400');
    
    // If this wasn't already visible, slide it down
    if (!isVisible) {
        $el.stop(true, true).slideDown('400');
    }
    
    // Close the dropdown if clicked outside of it
    $(document).on('click', function (e) {
        if (!$(e.target).closest('.dropdown').length) {
            $('.dropdown-menu').slideUp('400');
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
    .dropdown-action .action-icon {
        color: #777;
        font-size: 18px;
        display: inline-block;
        padding: 0 10px;
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
</style>
