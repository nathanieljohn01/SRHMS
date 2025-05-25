<?php
session_start();
if (empty($_SESSION['name'])) 
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
                <h4 class="page-title">Visitor Pass</h4>
            </div>
            <div class="col-sm-8 col-9 text-right m-b-20">
                <a href="add-pass.php" class="btn btn-primary float-right"><i class="fa fa-plus"></i> Issue Pass</a>
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
                        <input class="form-control" type="text" id="visitorSearchInput" onkeyup="filterVisitor()" placeholder="Search"  style="padding-left: 35px; padding-right: 35px;">
                        <!-- Clear Button -->
                        <button class="position-absolute border-0 bg-transparent text-secondary" type="button" onclick="clearSearch()" style="top: 50%; right: 10px; transform: translateY(-50%);">
                            <i class="fa fa-times"></i>
                        </button>
                    </div>
                </div>
            </div>
        </div>
        <div class="table-responsive">
            <table class="datatable table table-hover" id="visitorTable">
                <thead style="background-color: #CCCCCC;">
                    <tr>
                        <th>Visitor ID</th>
                        <th>Visitor Name</th>
                        <th>Contact Number</th>
                        <th>Purpose</th>
                        <th>Check-in Time</th>
                        <th>Check-out Time</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    if (isset($_GET['ids'])) {
                        $id = mysqli_real_escape_string($connection, $_GET['ids']);
                        $update_query = $connection->prepare("UPDATE tbl_visitorpass SET deleted = 1 WHERE id = ?");
                    }
                    $fetch_query = mysqli_query($connection, "SELECT * FROM tbl_visitorpass WHERE deleted = 0");
                    while ($row = mysqli_fetch_array($fetch_query)) {
                        $check_in_time = date('F d, Y g:i A', strtotime($row['check_in_time']));
                        $check_out_time = ($row['check_out_time'] != NULL) ? date('F d, Y g:i A', strtotime($row['check_out_time'])) : ''; // If check_out_time is not NULL, format it, otherwise leave it empty
                        ?>
                        <tr>
                            <td><?php echo $row['visitor_id']; ?></td>
                            <td><?php echo $row['visitor_name']; ?></td>
                            <td><?php echo $row['contact_number']; ?></td>
                            <td><?php echo $row['purpose']; ?></td>
                            <td><?php echo $check_in_time; ?></td>
                            <td><?php echo $check_out_time; ?></td> 
                            <td class="text-right">
                                <div class="dropdown dropdown-action">
                                    <a href="#" class="action-icon dropdown-toggle" data-toggle="dropdown" aria-expanded="false"><i class="fa fa-ellipsis-v"></i></a>
                                    <div class="dropdown-menu dropdown-menu-right">
                                        <?php if (empty($row['check_out_time'])) { ?>
                                            <?php if ($_SESSION['role'] == 9): ?>
                                            <a class="dropdown-item" href="#" onclick="return confirmCheckout(<?php echo $row['id']; ?>)">
                                                <i class="fa fa-sign-out-alt m-r-5"></i> Check Out
                                            </a>
                                            <?php endif; ?>
                                        <?php } ?>
                                        <?php if ($_SESSION['role'] == 1): ?>
                                        <a class="dropdown-item" href="#" onclick="return confirmDelete(<?php echo $row['id']; ?>)">
                                            <i class="fa fa-trash m-r-5"></i> Delete
                                        </a>
                                        <?php endif; ?>
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
    function confirmDelete(id) {
        return Swal.fire({
            title: 'Delete Visitor Pass?',
            text: 'Are you sure you want to delete this visitor pass? This action cannot be undone!',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#12369e',
            confirmButtonText: 'OK'
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = 'visitor-pass.php?ids=' + id;
            }
        });
    }
    
    function confirmCheckout(id) {
        return Swal.fire({
            title: 'Check Out Visitor?',
            text: 'Are you sure you want to check out this visitor?',
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#12369e',
            confirmButtonText: 'Yes'
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = 'checkout.php?id=' + id;
            }
        });
    }
</script>
<script>
    function clearSearch() {
        document.getElementById("visitorSearchInput").value = '';
        filterVisitor();
    }

    function filterVisitor() {
        var input = document.getElementById("visitorSearchInput").value;
        
        $.ajax({
            url: 'fetch_visitor.php',
            type: 'GET',
            data: { query: input },
            success: function(response) {
                try {
                    var data = JSON.parse(response);
                    updateVisitorTable(data);
                } catch (e) {
                    console.error('JSON parse error:', e);
                }
            },
            error: function(xhr, status, error) {
                console.error('Ajax error:', error);
            }
        });
    }

    function updateVisitorTable(data) {
        var tbody = $('#visitorTable tbody');
        tbody.empty();
        
        data.forEach(function(record) {
            tbody.append(`
                <tr>
                    <td>${record.visitor_id}</td>
                    <td>${record.visitor_name}</td>
                    <td>${record.contact_number}</td>
                    <td>${record.purpose}</td>
                    <td>${record.check_in_time}</td>
                    <td>${record.check_out_time}</td>
                    <td class="text-right">
                        <div class="dropdown dropdown-action">
                            <a href="#" class="action-icon dropdown-toggle" data-toggle="dropdown" aria-expanded="false">
                                <i class="fa fa-ellipsis-v"></i>
                            </a>
                            <div class="dropdown-menu dropdown-menu-right">
                                ${getActionButtons(record)}
                            </div>
                        </div>
                    </td>
                </tr>
            `);
        });
    }

    function getActionButtons(record) {
        let buttons = '';
        var role = <?php echo json_encode($_SESSION['role']); ?>; // Get the user's role from PHP
        
        // Check Out button (only for role 10 and if not checked out)
        if (!record.check_out_time && role == 10) {
            buttons += `
                <a class="dropdown-item" href="#" onclick="return confirmCheckout(${record.id})">
                    <i class="fa fa-sign-out-alt m-r-5"></i> Check Out
                </a>
            `;
        }
        
        // Delete button (only for role 1)
        if (role == 1) {
            buttons += `
                <a class="dropdown-item" href="#" onclick="return confirmDelete(${record.id})">
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
    background: #1342C6;
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