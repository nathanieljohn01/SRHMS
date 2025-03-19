<?php
session_start();
if (empty($_SESSION['name'])) {
    header('location:index.php');
}
include('header.php');
include('includes/connection.php');

date_default_timezone_set('Asia/Manila');

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
"))[0];

$completed_tests = mysqli_fetch_row(mysqli_query($connection, "
    SELECT COUNT(*) 
    FROM tbl_laborder 
    WHERE status='Completed' 
"))[0];

$cancelled_tests = mysqli_fetch_row(mysqli_query($connection, "
    SELECT COUNT(*) 
    FROM tbl_laborder 
    WHERE status LIKE 'Cancelled%' 
"))[0];

$stat_tests = mysqli_fetch_row(mysqli_query($connection, "
    SELECT COUNT(*) 
    FROM tbl_laborder 
    WHERE stat='STAT' 
    AND status NOT LIKE 'Completed' 
    AND status NOT LIKE 'Cancelled%' 
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

            <div class="col-md-12">
                <div class="card">
                    <div class="card-body">
                        <h4 class="card-title">Current Shift</h4>
                        <div class="current-shift">
                            <?php
                            if ($currentHour >= 6 && $currentHour < 14) {
                                echo '<i class="fa fa-sun shift-icon"></i>';
                                echo '<h3>Morning Shift (6:00 AM - 2:00 PM)</h3>';
                            } elseif ($currentHour >= 14 && $currentHour < 22) {
                                echo '<i class="fa fa-cloud-sun shift-icon"></i>';
                                echo '<h3>Afternoon Shift (2:00 PM - 10:00 PM)</h3>';
                            } else {
                                echo '<i class="fa fa-moon shift-icon"></i>';
                                echo '<h3>Night Shift (10:00 PM - 6:00 AM)</h3>';
                            }
                            ?>
                           <p class="text-muted">Current Time: <span id="currentTime"></span></p>
                        </div>
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
                responsive: true,
                maintainAspectRatio: false,
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
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: { beginAtZero: true }
                }
            }
        });
    })
    .catch(error => console.error('Error fetching data:', error));
</script>

<script>
function updateTime() {
    const now = new Date();
    let hours = now.getHours();
    let minutes = now.getMinutes();
    const ampm = hours >= 12 ? 'PM' : 'AM';
    
    hours = hours % 12;
    hours = hours ? hours : 12;
    minutes = minutes < 10 ? '0' + minutes : minutes;
    
    document.getElementById('currentTime').innerHTML = hours + ':' + minutes + ' ' + ampm;
}

// Update time every second
setInterval(updateTime, 1000);
// Initial call to display time immediately
updateTime();

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
.btn-primary {
    background: #12369e;
    border: none;
}
.btn-primary:hover {
    background: #05007E;
}
canvas {
    width: 100% !important;
    height: 300px !important; /* Palitan depende sa gusto mong height */
}
.current-shift {
    text-align: center;
    padding: 20px;
}

.current-shift h3 {
    color: #333;
    font-size: 24px;
    margin-bottom: 15px;
    font-weight: 600;
}

.current-shift p {
    font-size: 18px;
    margin: 0;
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

.card-title {
    color: #333;
    font-size: 20px;
    font-weight: 500;
    margin-bottom: 20px;
    text-align: center;
}

.text-muted {
    color: #6c757d;
}
.shift-icon {
    font-size: 48px;
    margin-bottom: 15px;
    display: block;
}

.fa-sun {
    color: #FDB813;
}

.fa-cloud-sun {
    color: #FF8C00;
}

.fa-moon {
    color: #4A4A8F;
}

.current-shift {
    text-align: center;
    padding: 20px;
}
</style>
