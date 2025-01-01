<?php
session_start();
if (empty($_SESSION['name'])) {
    header('location:index.php');
}
include('header.php');
include('includes/connection.php');

// Determine the current shift
$currentHour = date('H');

if ($currentHour >= 6 && $currentHour < 14) {
    $shift_start = date('Y-m-d H-i-s') . ' 06:00:00';
    $shift_end = date('Y-m-d') . ' 13:59:59';
} elseif ($currentHour >= 14 && $currentHour < 22) {
    $shift_start = date('Y-m-d') . ' 14:00:00';
    $shift_end = date('Y-m-d') . ' 21:59:59';
} else {
    // Night shift handling: 10:00 PM to 6:00 AM spans two days
    $shift_start = date('Y-m-d') . ' 22:00:00';
    $shift_end = date('Y-m-d', strtotime('+1 day')) . ' 05:59:59';
}

// Fetch counts based on the current shift time range
$in_progress_tests = mysqli_fetch_row(mysqli_query($connection, "
    SELECT COUNT(*) 
    FROM tbl_laborder 
    WHERE status='In-Progress' 
    AND requested_date BETWEEN '$shift_start' AND '$shift_end'
"))[0];

$completed_tests = mysqli_fetch_row(mysqli_query($connection, "
    SELECT COUNT(*) 
    FROM tbl_laborder 
    WHERE status='Completed' 
    AND requested_date BETWEEN '$shift_start' AND '$shift_end'
"))[0];

$cancelled_tests = mysqli_fetch_row(mysqli_query($connection, "
    SELECT COUNT(*) 
    FROM tbl_laborder 
    WHERE status LIKE 'Cancelled%' 
    AND requested_date BETWEEN '$shift_start' AND '$shift_end'
"))[0];

$stat_tests = mysqli_fetch_row(mysqli_query($connection, "
    SELECT COUNT(*) 
    FROM tbl_laborder 
    WHERE stat='STAT' 
    AND status NOT LIKE 'Completed' 
    AND status NOT LIKE 'Cancelled%' 
    AND requested_date BETWEEN '$shift_start' AND '$shift_end'
"))[0];
?>

<div class="page-wrapper">
    <div class="content">
        <div class="row">
            <!-- Widgets -->
            <div class="col-md-3">
                <div class="dash-widget">
                    <span class="dash-widget-bg1"><i class="fa-solid fa-vials"></i></span>
                    <div class="dash-widget-info text-right">
                        <h3><?php echo $in_progress_tests; ?></h3>
                        <span class="widget-title1">In-Progress Tests <i class="fa fa-check" aria-hidden="true"></i></span>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="dash-widget">
                    <span class="dash-widget-bg2"><i class="fa-solid fa-vials"></i></span>
                    <div class="dash-widget-info text-right">
                        <h3><?php echo $completed_tests; ?></h3>
                        <span class="widget-title2">Completed Tests <i class="fa fa-check" aria-hidden="true"></i></span>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="dash-widget">
                    <span class="dash-widget-bg3"><i class="fa-solid fa-vials"></i></span>
                    <div class="dash-widget-info text-right">
                        <h3><?php echo $cancelled_tests; ?></h3>
                        <span class="widget-title3">Cancelled Tests <i class="fa fa-check" aria-hidden="true"></i></span>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="dash-widget">
                    <span class="dash-widget-bg4"><i class="fa-solid fa-vials"></i></span>
                    <div class="dash-widget-info text-right">
                        <h3><?php echo $stat_tests; ?></h3>
                        <span class="widget-title4">Stat Tests <i class="fa fa-check" aria-hidden="true"></i></span>
                    </div>
                </div>
            </div>

            <!-- Completed Tests Chart -->
            <div class="col-12 col-md-6 col-lg-6 col-xl-6">
                <div class="card">
                    <div class="card-header">
                        <h4 class="card-title d-inline-block">Completed Tests Overview</h4>
                    </div>
                    <div class="card-body">
                        <canvas id="completedTestsChart"></canvas>
                    </div>
                </div>
            </div>

            <!-- Cancelled Tests Chart -->
            <div class="col-12 col-md-6 col-lg-6 col-xl-6">
                <div class="card">
                    <div class="card-header">
                        <h4 class="card-title d-inline-block">Cancelled Tests Overview</h4>
                    </div>
                    <div class="card-body">
                        <canvas id="cancelledTestsChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include('footer.php'); ?>

<script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-annotation@1.0.1/dist/chartjs-plugin-annotation.min.js"></script>
<script>
    // Get the canvas elements
    var ctxCompletedTests = document.getElementById('completedTestsChart').getContext('2d');
    var ctxCancelledTests = document.getElementById('cancelledTestsChart').getContext('2d');

    // Fetch shift data
    fetch('shift-chart-data.php')
    .then(response => response.json())
    .then(data => {
        // Ensure data has the correct structure
        console.log(data); // For debugging

        new Chart(ctxCompletedTests, {
            type: 'bar',
            data: {
                labels: Object.keys(data.Completed),
                datasets: [{
                    label: 'Completed Tests',
                    data: Object.values(data.Completed),
                    backgroundColor: 'rgba(15, 54, 159, 0.2)',
                    borderColor: 'rgba(15, 54, 159, 1)',
                    borderWidth: 1
                }]
            },
            options: {
                scales: {
                    y: { beginAtZero: true }
                }
            }
        });

        new Chart(ctxCancelledTests, {
            type: 'bar',
            data: {
                labels: Object.keys(data.Cancelled),
                datasets: [{
                    label: 'Cancelled Tests',
                    data: Object.values(data.Cancelled),
                    backgroundColor: 'rgba(197, 16, 20, 0.2)',
                    borderColor: 'rgba(197, 16, 20, 1)',
                    borderWidth: 1
                }]
            },
            options: {
                scales: {
                    y: { beginAtZero: true }
                }
            }
        });
    })
    .catch(error => console.error('Error fetching data:', error));
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
