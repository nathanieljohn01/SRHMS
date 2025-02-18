<?php
session_start();
if (empty($_SESSION['name'])) {
    header('location:index.php');
}
include('header.php');
include('includes/connection.php');

function sanitize($data) {
    return htmlspecialchars(strip_tags($data), ENT_QUOTES, 'UTF-8');
}

?>
<div class="page-wrapper">
    <div class="content">
        <div class="row">
            <div class="col-sm-4 col-3">
                <h4 class="page-title">Newborn Records</h4>
            </div>
            <div class="col-sm-8 col-9 text-right m-b-20">
                <?php 
                if ($_SESSION['role'] == 1 || $_SESSION['role'] == 3) {  
                    echo '<a href="add-newborn.php" class="btn btn-primary float-right"><i class="fa fa-plus"></i> Add Newborn </a>';
                }
                ?>
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
                        <input class="form-control" type="text" id="newbornSearchInput" onkeyup="filterNewborns()" style="padding-left: 35px; padding-right: 35px;">
                        <!-- Clear Button -->
                        <button class="position-absolute border-0 bg-transparent text-secondary" type="button" onclick="clearSearch()" style="top: 50%; right: 10px; transform: translateY(-50%);">
                            <i class="fa fa-times"></i>
                        </button>
                    </div>
                </div>
            </div>
        </div>
        <div class="table-responsive">
            <table class="datatable table table-hover" id="newbornTable">
                <thead style="background-color: #CCCCCC;">
                    <tr>
                        <th>Newborn ID</th>
                        <th>First Name</th>
                        <th>Last Name</th>
                        <th>Gender</th>
                        <th>Date of Birth</th>
                        <th>Time of Birth</th>
                        <th>Birth Weight</th>
                        <th>Birth Height</th>
                        <th>Gestational Age</th>
                        <th>Physician</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    if (isset($_GET['ids'])) {
                        $id = $_GET['ids'];

                        // Ensure the ID is a number to prevent SQL injection
                        if (filter_var($id, FILTER_VALIDATE_INT)) {
                            $update_query = mysqli_prepare($connection, "UPDATE tbl_newborn SET deleted = 1 WHERE id = ?");
                            mysqli_stmt_bind_param($update_query, 'i', $id); // 'i' denotes an integer
                            if (mysqli_stmt_execute($update_query)) {
                                // Successfully updated
                            } else {
                                echo "Error in deleting record.";
                            }
                            mysqli_stmt_close($update_query);
                        } else {
                            echo "Invalid ID.";
                        }
                    }
                    if ($_SESSION['role'] == 2) {
                        $doctor_name = $_SESSION['name'];
                        $fetch_query = mysqli_prepare($connection, "SELECT * FROM tbl_newborn WHERE deleted = 0 AND physician = ?");
                        mysqli_stmt_bind_param($fetch_query, 's', $doctor_name);
                        mysqli_stmt_execute($fetch_query);
                        $result = mysqli_stmt_get_result($fetch_query);
                        while ($row = mysqli_fetch_array($result)) {
                    ?>
                            <tr>
                                <td><?php echo htmlspecialchars($row['newborn_id']); ?></td>
                                <td><?php echo htmlspecialchars($row['first_name']); ?></td>
                                <td><?php echo htmlspecialchars($row['last_name']); ?></td>
                                <td><?php echo htmlspecialchars($row['gender']); ?></td>
                                <td><?php echo htmlspecialchars($row['dob']); ?></td>
                                <td><?php echo htmlspecialchars($row['tob']); ?></td>
                                <td><?php echo htmlspecialchars($row['birth_weight']); ?></td>
                                <td><?php echo htmlspecialchars($row['birth_height']); ?></td>
                                <td><?php echo htmlspecialchars($row['gestational_age']); ?></td>
                                <td><?php echo htmlspecialchars($row['physician']); ?></td>
                                <td class="text-right">
                                    <div class="dropdown dropdown-action">
                                        <a href="#" class="action-icon dropdown-toggle" data-toggle="dropdown" aria-expanded="false"><i class="fa fa-ellipsis-v"></i></a>
                                        <div class="dropdown-menu dropdown-menu-right">
                                            <a class="dropdown-item" href="edit-newborn.php?id=<?php echo htmlspecialchars($row['id']); ?>"><i class="fa fa-pencil m-r-5"></i> Edit</a>
                                            <a class="dropdown-item" href="newborn.php?ids=<?php echo htmlspecialchars($row['id']); ?>" onclick="return confirmDelete()"><i class="fa fa-trash-o m-r-5"></i> Delete</a>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                    <?php
                        }
                        mysqli_stmt_close($fetch_query);
                    } else {
                        $fetch_query = mysqli_query($connection, "SELECT * FROM tbl_newborn WHERE deleted = 0");
                        while ($row = mysqli_fetch_array($fetch_query)) {
                    ?>
                            <tr>
                                <td><?php echo htmlspecialchars($row['newborn_id']); ?></td>
                                <td><?php echo htmlspecialchars($row['first_name']); ?></td>
                                <td><?php echo htmlspecialchars($row['last_name']); ?></td>
                                <td><?php echo htmlspecialchars($row['gender']); ?></td>
                                <td><?php echo htmlspecialchars($row['dob']); ?></td>
                                <td><?php echo htmlspecialchars($row['tob']); ?></td>
                                <td><?php echo htmlspecialchars($row['birth_weight']); ?></td>
                                <td><?php echo htmlspecialchars($row['birth_height']); ?></td>
                                <td><?php echo htmlspecialchars($row['gestational_age']); ?></td>
                                <td><?php echo htmlspecialchars($row['physician']); ?></td>
                                <td class="text-right">
                                    <div class="dropdown dropdown-action">
                                        <a href="#" class="action-icon dropdown-toggle" data-toggle="dropdown" aria-expanded="false"><i class="fa fa-ellipsis-v"></i></a>
                                        <div class="dropdown-menu dropdown-menu-right">
                                        <?php 
                                        if ($_SESSION['role'] == 1 || $_SESSION['role'] == 3) {
                                            echo '<a class="dropdown-item" href="edit-newborn.php?id='. htmlspecialchars($row['id']) .'"><i class="fa fa-pencil m-r-5"></i> Edit</a>';
                                            echo '<a class="dropdown-item" href="newborn.php?ids='. htmlspecialchars($row['id']) .'" onclick="return confirmDelete()"><i class="fa fa-trash-o m-r-5"></i> Delete</a>';
                                        }
                                        ?>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                    <?php
                        }
                    }
                    ?>
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
    return confirm('Are you sure you want to delete this Newborn Record?');
}
</script>

<script>
    function clearSearch() {
        document.getElementById("newbornSearchInput").value = '';
        filterNewborns();
    }

    var role = <?php echo json_encode($_SESSION['role']); ?>;

    function filterNewborns() {
        var input = document.getElementById("newbornSearchInput").value;
        
        $.ajax({
            url: 'fetch_newborn.php',
            method: 'GET',
            data: { query: input },
            success: function(response) {
                var data = JSON.parse(response);
                updateNewbornsTable(data);
            }
        });
    }
    
    function updateNewbornsTable(data) {
        var tbody = $('#newbornTable tbody');
        tbody.empty();
    
        data.forEach(function(row) {
            let actionButtons = '';
            if (<?php echo $_SESSION['role']; ?> == 1 || <?php echo $_SESSION['role']; ?> == 3) {
                actionButtons = `
                    <a class="dropdown-item" href="edit-newborn.php?id=${row.id}">
                        <i class="fa fa-pencil m-r-5"></i> Edit
                    </a>
                    <a class="dropdown-item" href="newborn.php?ids=${row.id}" onclick="return confirmDelete()">
                        <i class="fa fa-trash-o m-r-5"></i> Delete
                    </a>
                `;
            }

            tbody.append(`
                <tr>
                    <td>${row.newborn_id}</td>
                    <td>${row.first_name}</td>
                    <td>${row.last_name}</td>
                    <td>${row.gender}</td>
                    <td>${row.dob}</td>
                    <td>${row.tob}</td>
                    <td>${row.birth_weight}</td>
                    <td>${row.birth_height}</td>
                    <td>${row.gestational_age}</td>
                    <td>${row.physician}</td>
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
                </tr>
            `);
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
