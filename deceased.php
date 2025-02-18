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
                        $id = $_GET['ids'];

                        // Ensure the ID is a valid integer to prevent malicious input
                        if (filter_var($id, FILTER_VALIDATE_INT)) {
                            $delete_query = mysqli_prepare($connection, "UPDATE tbl_deceased SET deleted = 1 WHERE deceased_id = ?");
                            mysqli_stmt_bind_param($delete_query, 'i', $id); // 'i' denotes integer type
                            if (mysqli_stmt_execute($delete_query)) {
                                // Successfully deleted
                            } else {
                                echo "Error deleting record.";
                            }
                            mysqli_stmt_close($delete_query);
                        } else {
                            echo "Invalid ID.";
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
                                        echo '<a class="dropdown-item" href="deceased.php?ids='. htmlspecialchars($row['deceased_id']) .'" onclick="return confirmDelete()"><i class="fa fa-trash-o m-r-5"></i> Delete</a>';
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
                <a class="dropdown-item" href="deceased.php?ids=${deceasedId}" onclick="return confirmDelete()">
                    <i class="fa fa-trash-o m-r-5"></i> Delete
                </a>
            `;
        }
        
        return buttons;
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
