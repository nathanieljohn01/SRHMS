<?php
session_start();
if(empty($_SESSION['name']))
{
    header('location:index.php');
}
include('header.php');
include('includes/connection.php');
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
                    echo '<a href="add-newborn.php" class="btn btn-primary btn-rounded float-right"><i class="fa fa-plus"></i> Add Newborn </a>';
                }
                ?>
            </div>
        </div>
        <div class="table-responsive">
            <input class="form-control" type="text" id="newbornSearchInput" onkeyup="filterNewborns()" placeholder="Search for Newborn">
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
                    if(isset($_GET['ids'])){
                        $id = $_GET['ids'];
                        $update_query = mysqli_query($connection, "UPDATE tbl_newborn SET deleted = 1 WHERE id='$id'");
                    }
                    $fetch_query = mysqli_query($connection, "SELECT * FROM tbl_newborn WHERE deleted = 0");
                    while($row = mysqli_fetch_array($fetch_query))
                    {
                    ?>
                        <tr>
                            <td><?php echo $row['newborn_id']; ?></td>
                            <td><?php echo $row['first_name']; ?></td>
                            <td><?php echo $row['last_name']; ?></td>
                            <td><?php echo $row['gender']; ?></td>
                            <td><?php echo $row['dob']; ?></td>
                            <td><?php echo $row['tob']; ?></td>
                            <td><?php echo $row['birth_weight']; ?></td>
                            <td><?php echo $row['birth_height']; ?></td>
                            <td><?php echo $row['gestational_age']; ?></td>
                            <td><?php echo $row['physician']; ?></td>
                            <td class="text-right">
                                <div class="dropdown dropdown-action">
                                    <a href="#" class="action-icon dropdown-toggle" data-toggle="dropdown" aria-expanded="false"><i class="fa fa-ellipsis-v"></i></a>
                                    <div class="dropdown-menu dropdown-menu-right">
                                    <?php 
                                    if ($_SESSION['role'] == 1 | $_SESSION['role'] == 3) {
                                        echo '<a class="dropdown-item" href="edit-newborn.php?id='.$row['id'].'"><i class="fa fa-pencil m-r-5"></i> Edit</a>';
                                        echo '<a class="dropdown-item" href="newborn.php?ids='.$row['id'].'" onclick="return confirmDelete()"><i class="fa fa-trash-o m-r-5"></i> Delete</a>';
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
    return confirm('Are you sure you want to delete this Newborn Record?');
}
</script>

<script>
    function filterNewborns() {
        var input, filter, table, tr, td, i, txtValue;
        input = document.getElementById("newbornSearchInput");
        filter = input.value.toUpperCase();
        table = document.getElementById("newbornTable");
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
