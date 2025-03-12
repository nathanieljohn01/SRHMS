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
        <div class="input-group">
            <input class="form-control" type="text" id="deceasedSearchInput" onkeyup="filterDeceased()" placeholder="Search for Patient">
            <div class="input-group-append">
                    <button class="btn btn-outline-primary" type="button" onclick="clearSearch()">
                        <i class="fa fa-times"></i>
                    </button>
                </div>
            </div>
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
                    if (isset($_GET['ids'])) {
                        try {
                            // Show loading state first
                            echo "<script>showLoading('Processing request...');</script>";
                            
                            $id = mysqli_real_escape_string($connection, $_GET['ids']);
                            $delete_query = mysqli_query($connection, "UPDATE tbl_deceased SET deleted = 1 WHERE deceased_id = '$id'");
                            
                            if ($delete_query) {
                                echo "<script>
                                    showSuccess('Deceased record deleted successfully!', true);
                                </script>";
                            } else {
                                throw new Exception(mysqli_error($connection));
                            }
                        } catch (Exception $e) {
                            echo "<script>
                                showError('Error deleting record: " . addslashes($e->getMessage()) . "');
                            </script>";
                        }
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
                                        echo '<a class="dropdown-item delete-btn" data-id="'. htmlspecialchars($row['deceased_id']) .'" href="#"><i class="fa fa-trash-o m-r-5"></i> Delete</a>';
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

<script language="JavaScript" type="text/javascript">
function confirmDelete(){
    return confirm('Are you sure you want to delete this Deceased Record?');
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
                    <i class="fa fa-trash-o m-r-5"></i> Delete
                </a>
            `;
        }
        
        return buttons;
    }
</script>

<script>
$(document).ready(function() {
    // Handle delete confirmation
    $('.delete-btn').on('click', function(e) {
        e.preventDefault();
        const id = $(this).data('id');
        
        showConfirm(
            'Delete Record?',
            'Are you sure you want to delete this deceased record? This action cannot be undone!',
            () => {
                // Show loading state
                showLoading('Deleting record...');
                setTimeout(() => {
                    window.location.href = 'deceased.php?ids=' + id;
                }, 500);
            }
        );
    });
});

// Function to handle record deletion
function deleteRecord(id) {
    showConfirm(
        'Delete Record?',
        'Are you sure you want to delete this deceased record? This action cannot be undone!',
        () => {
            // Show loading state
            showLoading('Deleting record...');
            setTimeout(() => {
                window.location.href = 'deceased.php?ids=' + id;
            }, 500);
        }
    );
    return false;
}

// Update onclick handlers in table
$(document).ready(function() {
    // Update delete links
    $('a[onclick*="confirm"]').each(function() {
        const id = $(this).attr('href').split('=')[1];
        $(this).attr('onclick', `return deleteRecord('${id}')`);
    });
});
</script>

<script>
    function showLoading(message) {
        Swal.fire({
            title: message,
            allowOutsideClick: false,
            showConfirmButton: false,
            onRender: () => {
                Swal.showLoading();
            }
        });
    }

    function showSuccess(message, redirect) {
        Swal.fire({
            title: 'Success',
            text: message,
            icon: 'success',
            allowOutsideClick: false,
            showConfirmButton: true,
            confirmButtonText: 'OK'
        }).then((result) => {
            if (redirect) {
                window.location.href = 'deceased.php';
            }
        });
    }

    function showError(message) {
        Swal.fire({
            title: 'Error',
            text: message,
            icon: 'error',
            allowOutsideClick: false,
            showConfirmButton: true,
            confirmButtonText: 'OK'
        });
    }

    function showConfirm(title, message, callback) {
        Swal.fire({
            title: title,
            text: message,
            icon: 'warning',
            allowOutsideClick: false,
            showCancelButton: true,
            confirmButtonText: 'Yes',
            cancelButtonText: 'No'
        }).then((result) => {
            if (result.value) {
                callback();
            }
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
    .btn-primary {
        background: #12369e;
        border: none;
    }
    .btn-primary:hover {
        background: #05007E;
    }
</style>
