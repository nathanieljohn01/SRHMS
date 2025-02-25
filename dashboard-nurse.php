<?php
session_start();
if(empty($_SESSION['name'])) {
    header('location:index.php');
}
include('header.php');
include('includes/connection.php');
?>
<div class="page-wrapper">
    <div class="content">
        <div class="row">
            <!-- Summary Header -->
            <div class="col-12 mb-4">
                <h4 class="text-primary">Nurse's Dashboard</h4>
                <p class="text-muted">Welcome back, <?php echo $_SESSION['name']; ?></p>
            </div>

            <!-- Enhanced Stat Cards -->
            <div class="col-md-6 col-sm-6 col-lg-6 col-xl-3">
                <div class="dash-widget hover-effect">
                    <span class="dash-widget-bg1"><i class="fa fa-bed fa-pulse"></i></span>
                    <?php
                    $fetch_query = mysqli_query($connection, "SELECT COUNT(*) AS total FROM tbl_patient WHERE status=1 AND patient_type='InPatient'"); 
                    $inpatients = mysqli_fetch_row($fetch_query);
                    ?>
                    <div class="dash-widget-info text-right">
                        <h3 class="counter"><?php echo $inpatients[0]; ?></h3>
                        <span class="widget-title1">Active In-Patients <i class="fa fa-check" aria-hidden="true"></i></span>
                    </div>
                </div>
            </div>

            <div class="col-md-6 col-sm-6 col-lg-6 col-xl-3">
                <div class="dash-widget hover-effect">
                    <span class="dash-widget-bg2"><i class="fa fa-procedures"></i></span>
                    <?php
                    $fetch_query = mysqli_query($connection, "SELECT COUNT(*) AS total FROM tbl_ward WHERE occupied=1"); 
                    $occupied_beds = mysqli_fetch_row($fetch_query);
                    ?>
                    <div class="dash-widget-info text-right">
                        <h3 class="counter"><?php echo $occupied_beds[0]; ?></h3>
                        <span class="widget-title2">Occupied Beds <i class="fa fa-check" aria-hidden="true"></i></span>
                    </div>
                </div>
            </div>

            <div class="col-md-6 col-sm-6 col-lg-6 col-xl-3">
                <div class="dash-widget hover-effect">
                    <span class="dash-widget-bg3"><i class="fa fa-tasks"></i></span>
                    <?php
                    $today = date('Y-m-d');
                    $fetch_query = mysqli_query($connection, "SELECT COUNT(*) AS total FROM tbl_task WHERE nurse_id={$_SESSION['user_id']} AND DATE(due_date)='$today' AND status='Pending'"); 
                    $pending_tasks = mysqli_fetch_row($fetch_query);
                    ?>
                    <div class="dash-widget-info text-right">
                        <h3 class="counter"><?php echo $pending_tasks[0]; ?></h3>
                        <span class="widget-title3">Pending Tasks <i class="fa fa-check" aria-hidden="true"></i></span>
                    </div>
                </div>
            </div>

            <div class="col-md-6 col-sm-6 col-lg-6 col-xl-3">
                <div class="dash-widget hover-effect">
                    <span class="dash-widget-bg4"><i class="fa fa-calendar-check"></i></span>
                    <?php
                    $fetch_query = mysqli_query($connection, "SELECT COUNT(*) AS total FROM tbl_task WHERE nurse_id={$_SESSION['user_id']} AND DATE(due_date)='$today' AND status='Completed'"); 
                    $completed_tasks = mysqli_fetch_row($fetch_query);
                    ?>
                    <div class="dash-widget-info text-right">
                        <h3 class="counter"><?php echo $completed_tasks[0]; ?></h3>
                        <span class="widget-title4">Completed Tasks <i class="fa fa-check" aria-hidden="true"></i></span>
                    </div>
                </div>
            </div>

            <!-- Patient Care Tasks -->
            <div class="col-12 col-lg-8 mt-4">
                <div class="card shadow-sm">
                    <div class="card-header bg-white">
                        <h4 class="card-title text-primary">Today's Patient Care Tasks</h4>
                        <div class="card-tools float-right">
                            <a href="add-task.php" class="btn btn-primary btn-sm">
                                <i class="fa fa-plus"></i> Add New Task
                            </a>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Time</th>
                                        <th>Patient</th>
                                        <th>Task</th>
                                        <th>Priority</th>
                                        <th>Status</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $tasks_query = mysqli_query($connection, 
                                        "SELECT t.*, p.name as patient_name 
                                         FROM tbl_task t 
                                         JOIN tbl_patient p ON t.patient_id = p.id 
                                         WHERE t.nurse_id = {$_SESSION['user_id']} 
                                         AND DATE(t.due_date) = '$today'
                                         ORDER BY t.priority DESC, t.due_date ASC
                                         LIMIT 5");
                                    
                                    while($row = mysqli_fetch_assoc($tasks_query)) {
                                        $priority_class = '';
                                        switch($row['priority']) {
                                            case 'High': $priority_class = 'danger'; break;
                                            case 'Medium': $priority_class = 'warning'; break;
                                            case 'Low': $priority_class = 'info'; break;
                                        }
                                        
                                        $status_class = $row['status'] == 'Completed' ? 'success' : 'warning';
                                        ?>
                                        <tr>
                                            <td><?php echo date('h:i A', strtotime($row['due_date'])); ?></td>
                                            <td><?php echo $row['patient_name']; ?></td>
                                            <td><?php echo $row['task_description']; ?></td>
                                            <td><span class="badge badge-<?php echo $priority_class; ?>"><?php echo $row['priority']; ?></span></td>
                                            <td><span class="badge badge-<?php echo $status_class; ?>"><?php echo $row['status']; ?></span></td>
                                            <td>
                                                <div class="btn-group">
                                                    <a href="view-task.php?id=<?php echo $row['id']; ?>" class="btn btn-sm btn-info">
                                                        <i class="fa fa-eye"></i>
                                                    </a>
                                                    <?php if($row['status'] != 'Completed') { ?>
                                                    <a href="complete-task.php?id=<?php echo $row['id']; ?>" class="btn btn-sm btn-success">
                                                        <i class="fa fa-check"></i>
                                                    </a>
                                                    <?php } ?>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php } ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Ward Occupancy -->
            <div class="col-12 col-lg-4 mt-4">
                <div class="card shadow-sm">
                    <div class="card-header bg-white">
                        <h4 class="card-title text-primary">Ward Occupancy</h4>
                    </div>
                    <div class="card-body">
                        <canvas id="wardOccupancyChart" height="300"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Add CSS -->
<style>
.hover-effect {
    transition: transform 0.3s ease-in-out;
}
.hover-effect:hover {
    transform: translateY(-5px);
}
.counter {
    animation: countUp 2s ease-out;
}
.dash-widget {
    border-radius: 10px;
    box-shadow: 0 4px 6px rgba(0,0,0,0.1);
}
@keyframes countUp {
    from { opacity: 0; transform: translateY(20px); }
    to { opacity: 1; transform: translateY(0); }
}
.fa-pulse {
    animation: pulse 2s infinite;
}
@keyframes pulse {
    0% { transform: scale(1); }
    50% { transform: scale(1.1); }
    100% { transform: scale(1); }
}
.card {
    border: none;
    border-radius: 10px;
    margin-bottom: 20px;
}
.badge {
    padding: 5px 10px;
    border-radius: 20px;
}
.btn-group .btn {
    margin: 0 2px;
}
</style>

<!-- Enhanced Chart Scripts -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Initialize ward occupancy chart
    const ctx = document.getElementById('wardOccupancyChart').getContext('2d');
    
    <?php
    // Fetch ward occupancy data
    $ward_query = mysqli_query($connection, 
        "SELECT w.ward_name, 
                COUNT(CASE WHEN w.occupied = 1 THEN 1 END) as occupied,
                COUNT(*) as total
         FROM tbl_ward w
         GROUP BY w.ward_name");
    
    $labels = [];
    $occupied = [];
    $available = [];
    
    while($row = mysqli_fetch_assoc($ward_query)) {
        $labels[] = $row['ward_name'];
        $occupied[] = $row['occupied'];
        $available[] = $row['total'] - $row['occupied'];
    }
    ?>

    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: <?php echo json_encode($labels); ?>,
            datasets: [{
                label: 'Occupied',
                data: <?php echo json_encode($occupied); ?>,
                backgroundColor: 'rgba(255, 99, 132, 0.8)',
                borderColor: 'rgba(255, 99, 132, 1)',
                borderWidth: 1
            }, {
                label: 'Available',
                data: <?php echo json_encode($available); ?>,
                backgroundColor: 'rgba(75, 192, 192, 0.8)',
                borderColor: 'rgba(75, 192, 192, 1)',
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom'
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    stacked: true
                },
                x: {
                    stacked: true
                }
            }
        }
    });
});
</script>

<?php 
 include('footer.php');
?>