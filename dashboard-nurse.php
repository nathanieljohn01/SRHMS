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
                <!-- Employee Widget -->
                <div class="dash-widget">
                    <span class="dash-widget-bg1"><i class="fa fa-users" aria-hidden="true"></i></span>
                    <?php
                    $fetch_query = mysqli_query($connection, "select count(*) as total from tbl_patient where patient_type='Inpatient' and status=1");
                    $doc = mysqli_fetch_row($fetch_query);
                    ?>
                    <div class="dash-widget-info text-right">
                        <h3><?php echo $doc[0]; ?></h3>
                        <span class="widget-title1">Inpatient <i class="fa fa-check" aria-hidden="true"></i></span>
                    </div>
                </div>
            </div>

            <div class="col-md-6 col-sm-6 col-lg-6 col-xl-3">
                <!-- Doctors Widget -->
                <div class="dash-widget">
                    <span class="dash-widget-bg2"><i class="fa fa-user-md" aria-hidden="true"></i></span>
                    <?php
                    $fetch_query = mysqli_query($connection, "select count(*) as total from tbl_patient where patient_type='Outpatient' and status=1");
                    $doc = mysqli_fetch_row($fetch_query);
                    ?>
                    <div class="dash-widget-info text-right">
                        <h3><?php echo $doc[0]; ?></h3>
                        <span class="widget-title2">Outpatient <i class="fa fa-check" aria-hidden="true"></i></span>
                    </div>
                </div>
            </div>

            <div class="col-md-6 col-sm-6 col-lg-6 col-xl-3">
                <!-- Nurses Widget -->
                <div class="dash-widget">
                    <span class="dash-widget-bg4"><i class="fa fa-user" aria-hidden="true"></i></span>
                    <?php
                    $fetch_query = mysqli_query($connection, "select count(*) as total from tbl_patient where patient_type='Hemodialysis' and status=1");
                    $doc = mysqli_fetch_row($fetch_query);
                    ?>
                    <div class="dash-widget-info text-right">
                        <h3 class="counter"><?php echo $pending_tasks[0]; ?></h3>
                        <span class="widget-title3">Pending Tasks <i class="fa fa-check" aria-hidden="true"></i></span>
                    </div>
                </div>
            </div>

            <div class="col-md-6 col-sm-6 col-lg-6 col-xl-3">
                <!-- Patients Widget -->
                <div class="dash-widget">
                    <span class="dash-widget-bg3"><i class="fa fa-users"></i></span>
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
.contact-info {
    padding: 10px;
    border-bottom: 1px solid #ddd; /* Optional: Adds a bottom border for separation */
}
canvas {
    width: 100% !important;
    height: 300px !important; /* Palitan depende sa gusto mong height */
}
canvas {
    width: 100% !important;
    height: 300px !important; /* Palitan depende sa gusto mong height */
}
.contact-list {
    list-style: none;
    padding: 0;
    margin: 0;
}

.doctor-card {
    padding: 15px;
    border-bottom: 1px solid #eee;
    transition: all 0.3s ease;
}

.doctor-card:hover {
    background-color: #f8f9fa;
}

.contact-info {
    display: flex;
    align-items: center;
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
.card {
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    border-radius: 8px;
    border: none;
    transition: transform 0.2s;
}

.card:hover {
    transform: translateY(-5px);
}

.card-body {
    padding: 25px;
}
</style>

<div class="col-12">
    <div class="card">
        <div class="card-header">
            <h4 class="card-title d-inline-block">Available Doctors</h4>
            <a href="doctors.php" class="btn btn-primary float-right">View all</a>
        </div>
        <div class="card-body">
            <ul class="contact-list">
                <?php
                $fetch_query = mysqli_query($connection, "
                    SELECT 
                        tbl_employee.id,
                        CONCAT(tbl_employee.first_name, ' ', tbl_employee.last_name) AS doctor_name,
                        tbl_employee.specialization,
                        tbl_schedule.available_days,
                        CONCAT(tbl_schedule.start_time, ' - ', tbl_schedule.end_time) AS available_time
                    FROM tbl_schedule
                    JOIN tbl_employee ON tbl_schedule.doctor_name = CONCAT(tbl_employee.first_name, ' ', tbl_employee.last_name)
                    WHERE tbl_schedule.status = 1 AND tbl_employee.role = 2
                    LIMIT 5
                ");
                
                while($row = mysqli_fetch_array($fetch_query)) {
                    $profile_picture_src = 'fetch-image-employee.php?id=' . $row['id'];
                ?>
                <li class="doctor-card">
                    <div class="contact-info">
                        <div class="doctor-image">
                            <img src="<?php echo $profile_picture_src; ?>" alt="Doctor Image">
                        </div>
                        <div class="doctor-details">
                            <h5 class="doctor-name"><?php echo htmlspecialchars($row['doctor_name']); ?></h5>
                            <p class="specialization"><?php echo htmlspecialchars($row['specialization']); ?></p>
                            <p class="schedule">
                                <i class="fa fa-calendar"></i> <?php echo htmlspecialchars($row['available_days']); ?><br>
                                <i class="fa fa-clock"></i> <?php echo htmlspecialchars($row['available_time']); ?>
                            </p>
                        </div>
                    </div>
                </li>
                <?php } ?>
            </ul>
        </div>
    </div>
</div>

<?php 
 include('footer.php');
?>

<!-- Include Chart.js library -->
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
            labels: [
                <?php foreach ($months as $month): ?>
                    '<?php echo date('F', mktime(0, 0, 0, $month, 1)); ?>',
                <?php endforeach; ?>
            ],
            datasets: [
                {
                    label: 'Inpatient',
                    backgroundColor: 'rgba(197, 16, 20, 0.2)', // Red for Inpatient
                    borderColor: 'rgba(197, 16, 20, 1)', // Border color for Inpatient
                    borderWidth: 1,
                    data: [
                        <?php foreach ($months as $month): ?>
                            <?php 
                                $result = mysqli_query($connection, "SELECT * FROM tbl_patient WHERE patient_type='Inpatient' AND MONTH(created_at) = '$month' AND YEAR(created_at) = YEAR(NOW())");
                                $count = mysqli_num_rows($result);
                                echo $count . ",";
                            ?>
                        <?php endforeach; ?>
                    ]
                },
                {
                    label: 'Outpatient',
                    backgroundColor: 'rgba(15, 54, 159, 0.2)', // Blue for Outpatient
                    borderColor: 'rgba(15, 54, 159, 1)', // Border color for Outpatient
                    borderWidth: 1,
                    data: [
                        <?php foreach ($months as $month): ?>
                            <?php 
                                $result = mysqli_query($connection, "SELECT * FROM tbl_patient WHERE patient_type='Outpatient' AND MONTH(created_at) = '$month' AND YEAR(created_at) = YEAR(NOW())");
                                $count = mysqli_num_rows($result);
                                echo $count . ",";
                            ?>
                        <?php endforeach; ?>
                    ]
                },
                {
                    label: 'Hemodialysis',
                    backgroundColor: 'rgba(120, 182, 35, 0.5)', // Green for Hemodialysis
                    borderColor: 'rgba(120, 182, 35, 1)', // Green color for Hemodialysis
                    borderWidth: 1,
                    data: [
                        <?php foreach ($months as $month): ?>
                            <?php 
                                $result = mysqli_query($connection, "SELECT * FROM tbl_patient WHERE patient_type='Hemodialysis' AND MONTH(created_at) = '$month' AND YEAR(created_at) = YEAR(NOW())");
                                $count = mysqli_num_rows($result);
                                echo $count . ",";
                            ?>
                        <?php endforeach; ?>
                    ]
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                yAxes: [{
                    ticks: {
                        beginAtZero: true
                    }
                }]
            }
        }
    });
</script>

<script>
    var ctxTotalPatients = document.getElementById('totalPatientChart').getContext('2d');
    var months = <?php echo json_encode($months); ?>;

    var totalPatientChart = new Chart(ctxTotalPatients, {
        type: 'line',
        data: {
            labels: months.map(month => new Date(Date.parse(month + " 1, 2000")).toLocaleString('en-US', { month: 'long' })),
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