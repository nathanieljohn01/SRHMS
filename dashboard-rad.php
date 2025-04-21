<?php
session_start();
if(empty($_SESSION['name'])) {
    header('location:index.php');
    exit;
}
include('header.php');
include('includes/connection.php');

date_default_timezone_set('Asia/Manila');

// Determine the current shift
$currentHour = date('H');

if ($currentHour >= 6 && $currentHour < 14) {
    $shift_start = date('Y-m-d H-i-s') . ' 06:00:00';
    $shift_end = date('Y-m-d') . ' 13:59:59';
    $currentShift = 'Morning';
} elseif ($currentHour >= 14 && $currentHour < 22) {
    $shift_start = date('Y-m-d') . ' 14:00:00';
    $shift_end = date('Y-m-d') . ' 21:59:59';
    $currentShift = 'Afternoon';
} else {
    // Night shift handling: 10:00 PM to 6:00 AM spans two days
    $shift_start = date('Y-m-d') . ' 22:00:00';
    $shift_end = date('Y-m-d', strtotime('+1 day')) . ' 05:59:59';
    $currentShift = 'Night';
}

// Fetch counts from tbl_radiology
$in_progress_tests = mysqli_fetch_row(mysqli_query($connection, "
    SELECT COUNT(*) 
    FROM tbl_radiology 
    WHERE status='In-Progress' 
"))[0];

$completed_tests = mysqli_fetch_row(mysqli_query($connection, "
    SELECT COUNT(*) 
    FROM tbl_radiology 
    WHERE status='Completed' 
"))[0];

$cancelled_tests = mysqli_fetch_row(mysqli_query($connection, "
    SELECT COUNT(*) 
    FROM tbl_radiology 
    WHERE status LIKE 'Cancelled%' 
"))[0];

$total_tests = mysqli_fetch_row(mysqli_query($connection, "
    SELECT COUNT(*) 
    FROM tbl_radiology
"))[0];
?>

<div class="page-wrapper">
    <div class="content">
        <div class="row">
            <!-- Widgets -->
            <div class="col-md-3">
                <div class="dash-widget">
                    <span class="dash-widget-bg1"><i class="fa-solid fa-x-ray"></i></span>
                    <div class="dash-widget-info text-right">
                        <h3><?php echo $in_progress_tests; ?></h3>
                        <span class="widget-title1">In-Progress</span>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="dash-widget">
                    <span class="dash-widget-bg2"><i class="fa-solid fa-x-ray"></i></span>
                    <div class="dash-widget-info text-right">
                        <h3><?php echo $completed_tests; ?></h3>
                        <span class="widget-title2">Completed</span>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="dash-widget">
                    <span class="dash-widget-bg3"><i class="fa-solid fa-x-ray"></i></span>
                    <div class="dash-widget-info text-right">
                        <h3><?php echo $cancelled_tests; ?></h3>
                        <span class="widget-title3">Cancelled</span>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="dash-widget">
                    <span class="dash-widget-bg4"><i class="fa-solid fa-x-ray"></i></span>
                    <div class="dash-widget-info text-right">
                        <h3><?php echo $total_tests; ?></h3>
                        <span class="widget-title4">Total Tests</span>
                    </div>
                </div>
            </div>

            <div class="col-md-12">
                <div class="card">
                    <div class="card-body">
                        <h4 class="card-title">Current Shift: <span id="currentShiftLabel"><?php echo $currentShift; ?></span></h4>
                        <div class="current-shift">
                            <?php    
                            if ($currentShift === 'Morning') {
                                echo '<i id="currentShiftIcon" class="fa fa-sun shift-icon"></i>';
                                echo '<h3 id="currentShiftTime">6:00 AM - 2:00 PM</h3>';
                            } elseif ($currentShift === 'Afternoon') {
                                echo '<i id="currentShiftIcon" class="fa fa-cloud-sun shift-icon"></i>';
                                echo '<h3 id="currentShiftTime">2:00 PM - 10:00 PM</h3>';
                            } else {
                                echo '<i id="currentShiftIcon" class="fa fa-moon shift-icon"></i>';
                                echo '<h3 id="currentShiftTime">10:00 PM - 6:00 AM</h3>';
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
                        <div class="float-right">
                            <select id="completedTimeRange" class="form-control">
                                <option value="weekly">Weekly</option>
                                <option value="monthly" selected>Monthly</option>
                                <option value="yearly">Yearly</option>
                            </select>
                        </div>
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
                        <div class="float-right">
                            <select id="cancelledTimeRange" class="form-control">
                                <option value="weekly">Weekly</option>
                                <option value="monthly" selected>Monthly</option>
                                <option value="yearly">Yearly</option>
                            </select>
                        </div>
                    </div>
                    <div class="card-body">
                        <canvas id="cancelledTestsChart"></canvas>
                    </div>
                </div>
            </div>

            <div class="col-md-12 col-lg-12 col-xl-12">
                <div class="card">
                    <div class="card-header">
                        <h4 class="card-title d-inline-block">Radiology Test Distribution</h4>
                        <div class="float-right">
                            <select id="radTestDistributionRange" class="form-control">
                                <option value="weekly">Weekly</option>
                                <option value="monthly" selected>Monthly</option>
                                <option value="yearly">Yearly</option>
                            </select>
                        </div>
                    </div>
                    <div class="card-body">
                        <canvas id="radTestDistributionChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include('footer.php'); ?>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-annotation@1.0.1/dist/chartjs-plugin-annotation.min.js"></script>
<script>
// Global variables to store chart instances
var completedTestsChart;
var cancelledTestsChart;
var radTestDistributionChart;
var currentShift = '<?php echo strtolower($currentShift); ?>';

// Function to fetch test data via AJAX
function fetchTestData(timeRange, testType, shift = null) {
    return new Promise((resolve, reject) => {
        const params = {
            timeRange: timeRange,
            testType: testType
        };
        
        if (shift) {
            params.shift = shift;
        }
        
        $.ajax({
            url: 'fetch_rad_test_data.php',
            type: 'GET',
            data: params,
            success: function(response) {
                try {
                    const data = typeof response === 'string' ? JSON.parse(response) : response;
                    resolve(data);
                } catch (e) {
                    console.error('Error parsing response:', e);
                    reject(e);
                }
            },
            error: function(error) {
                console.error('AJAX Error:', error);
                reject(error);
            }
        });
    });
}

// Function to update the dashboard widgets
function updateDashboardWidgets(shift, timeRange) {
    $.ajax({
        url: 'fetch_rad_test_counts.php',
        type: 'GET',
        data: {
            shift: shift,
            timeRange: timeRange
        },
        success: function(response) {
            const data = JSON.parse(response);
            $('.dash-widget-info h3').eq(0).text(data.in_progress);
            $('.dash-widget-info h3').eq(1).text(data.completed);
            $('.dash-widget-info h3').eq(2).text(data.cancelled);
            $('.dash-widget-info h3').eq(3).text(data.total);
        },
        error: function(error) {
            console.error('Error fetching test counts:', error);
        }
    });
}

// Function to update shift display
function updateShiftDisplay(shift) {
    if (shift === 'current') {
        $('#currentShiftLabel').text('<?php echo $currentShift; ?> Shift');
        $('#currentShiftTime').text('<?php 
            echo $currentShift === 'Morning' ? '6:00 AM - 2:00 PM' : 
                 ($currentShift === 'Afternoon' ? '2:00 PM - 10:00 PM' : '10:00 PM - 6:00 AM'); 
        ?>');
        $('#currentShiftIcon').removeClass('fa-sun fa-cloud-sun fa-moon').addClass('fa-<?php 
            echo strtolower($currentShift) === 'morning' ? 'sun' : 
                 (strtolower($currentShift) === 'afternoon' ? 'cloud-sun' : 'moon'); 
        ?>');
    } else {
        $('#currentShiftLabel').text(shift.charAt(0).toUpperCase() + shift.slice(1) + ' Shift');
        if (shift === 'morning') {
            $('#currentShiftTime').text('6:00 AM - 2:00 PM');
            $('#currentShiftIcon').removeClass('fa-cloud-sun fa-moon').addClass('fa-sun');
        } else if (shift === 'afternoon') {
            $('#currentShiftTime').text('2:00 PM - 10:00 PM');
            $('#currentShiftIcon').removeClass('fa-sun fa-moon').addClass('fa-cloud-sun');
        } else {
            $('#currentShiftTime').text('10:00 PM - 6:00 AM');
            $('#currentShiftIcon').removeClass('fa-sun fa-cloud-sun').addClass('fa-moon');
        }
    }
}

// Function to initialize or update the completed tests chart
function initializeOrUpdateCompletedTestsChart(timeRange = 'monthly', shift = null) {
    const ctx = document.getElementById('completedTestsChart').getContext('2d');
    
    // Destroy previous chart if it exists
    if (completedTestsChart) {
        completedTestsChart.destroy();
    }
    
    // Create gradient
    let completedGradient = ctx.createLinearGradient(0, 0, 0, 400);
    completedGradient.addColorStop(0, 'rgba(15, 54, 159, 0.8)');
    completedGradient.addColorStop(1, 'rgba(15, 54, 159, 0.2)');

    // Create new chart
    completedTestsChart = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: [], // Will be filled via AJAX
            datasets: [{
                label: 'Completed Tests',
                data: [], // Will be filled via AJAX
                backgroundColor: completedGradient,
                borderColor: 'rgba(15, 54, 159, 1)',
                borderWidth: 1,
                borderRadius: 6,
                borderSkipped: false,
                barPercentage: 0.7
            }]
        },
        options: {  
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'top',
                    labels: {
                        font: {
                            size: 12,
                            family: "'Poppins', sans-serif"
                        },
                        padding: 20,
                        usePointStyle: true,
                        pointStyle: 'circle'
                    }
                },
                tooltip: {
                    backgroundColor: 'rgba(0,0,0,0.8)',
                    titleFont: {
                        size: 14,
                        family: "'Poppins', sans-serif"
                    },
                    bodyFont: {
                        size: 12,
                        family: "'Poppins', sans-serif"
                    },
                    padding: 12,
                    cornerRadius: 6,
                    displayColors: true,
                    mode: 'index',
                    intersect: false
                }
            },
            scales: {
                x: {
                    grid: {
                        display: false,
                        drawBorder: false
                    },
                    ticks: {
                        font: {
                            family: "'Poppins', sans-serif"
                        }
                    }
                },
                y: {
                    beginAtZero: true,
                    grid: {
                        color: 'rgba(0,0,0,0.05)',
                        drawBorder: false
                    },
                    ticks: {
                        font: {
                            family: "'Poppins', sans-serif"
                        },
                        padding: 10
                    }
                }
            },
            animation: {
                duration: 1500,
                easing: 'easeOutQuart'
            },
            interaction: {
                intersect: false,
                mode: 'index'
            }
        }
    });

    // Fetch data and update chart
    fetchTestData(timeRange, 'completed', shift).then(data => {
        completedTestsChart.data.labels = data.labels;
        completedTestsChart.data.datasets[0].data = data.values;
        completedTestsChart.update();
    }).catch(error => {
        console.error('Error fetching completed tests data:', error);
    });
}

// Function to initialize or update the cancelled tests chart
function initializeOrUpdateCancelledTestsChart(timeRange = 'monthly', shift = null) {
    const ctx = document.getElementById('cancelledTestsChart').getContext('2d');
    
    // Destroy previous chart if it exists
    if (cancelledTestsChart) {
        cancelledTestsChart.destroy();
    }
    
    // Create gradient
    let cancelledGradient = ctx.createLinearGradient(0, 0, 0, 400);
    cancelledGradient.addColorStop(0, 'rgba(197, 16, 20, 0.8)');
    cancelledGradient.addColorStop(1, 'rgba(197, 16, 20, 0.2)');

    // Create new chart
    cancelledTestsChart = new Chart(ctx, {
        type: 'line',
        data: {
            labels: [], // Will be filled via AJAX
            datasets: [{
                label: 'Cancelled Tests',
                data: [], // Will be filled via AJAX
                backgroundColor: cancelledGradient,
                borderColor: 'rgba(197, 16, 20, 1)',
                borderWidth: 2,
                tension: 0.3,
                fill: true,
                pointBackgroundColor: 'rgba(197, 16, 20, 1)',
                pointRadius: 4,
                pointHoverRadius: 6
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: false
                },
                tooltip: {
                    backgroundColor: 'rgba(0,0,0,0.8)',
                    titleFont: {
                        size: 14,
                        family: "'Poppins', sans-serif"
                    },
                    bodyFont: {
                        size: 12,
                        family: "'Poppins', sans-serif"
                    },
                    padding: 12,
                    cornerRadius: 6,
                    displayColors: false
                }
            },
            scales: {
                x: {
                    grid: {
                        display: false,
                        drawBorder: false
                    },
                    ticks: {
                        font: {
                            family: "'Poppins', sans-serif"
                        }
                    }
                },
                y: {
                    beginAtZero: true,
                    grid: {
                        color: 'rgba(0,0,0,0.05)',
                        drawBorder: false
                    },
                    ticks: {
                        font: {
                            family: "'Poppins', sans-serif"
                        },
                        padding: 10
                    }
                }
            },
            animation: {
                duration: 1500,
                easing: 'easeOutQuart'
            },
            elements: {
                line: {
                    tension: 0.3
                }
            }
        }
    });

    // Fetch data and update chart
    fetchTestData(timeRange, 'cancelled', shift).then(data => {
        cancelledTestsChart.data.labels = data.labels;
        cancelledTestsChart.data.datasets[0].data = data.values;
        cancelledTestsChart.update();
    }).catch(error => {
        console.error('Error fetching cancelled tests data:', error);
    });
}

// Function to initialize or update the radiology test distribution chart
function initializeOrUpdateRadTestDistributionChart(timeRange = 'monthly', shift = null) {
    const ctx = document.getElementById('radTestDistributionChart').getContext('2d');

    // Destroy previous chart if it exists
    if (radTestDistributionChart) {
        radTestDistributionChart.destroy();
    }

    // Create gradient colors
    const gradients = [
        createGradient(ctx, '#36A2EB', '#1e90ff'),
        createGradient(ctx, '#FF6384', '#ff416c'),
        createGradient(ctx, '#FFCE56', '#ffb347'),
        createGradient(ctx, '#4BC0C0', '#2bd2ff'),
        createGradient(ctx, '#9966FF', '#8e44ad'),
        createGradient(ctx, '#FF9F40', '#ff7f50'),
        createGradient(ctx, '#C7C7C7', '#aaaaaa'),
        createGradient(ctx, '#5366FF', '#3a4fff'),
        createGradient(ctx, '#289F40', '#43e97b'),
        createGradient(ctx, '#D2C7C7', '#999999')
    ];

    radTestDistributionChart = new Chart(ctx, {
        type: 'pie',
        data: {
            labels: [], // Will be filled via AJAX
            datasets: [{
                data: [],
                backgroundColor: gradients,
                borderColor: '#ffffff',
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'right',
                    labels: {
                        font: {
                            size: 12,
                            family: "'Poppins', sans-serif"
                        },
                        padding: 20,
                        usePointStyle: true,
                        pointStyle: 'circle'
                    }
                },
                tooltip: {
                    backgroundColor: 'rgba(0,0,0,0.8)',
                    titleFont: {
                        size: 14,
                        family: "'Poppins', sans-serif"
                    },
                    bodyFont: {
                        size: 12,
                        family: "'Poppins', sans-serif"
                    },
                    padding: 12,
                    cornerRadius: 6,
                    displayColors: true
                }
            },
            animation: {
                duration: 1500,
                easing: 'easeOutQuart'
            }
        }
    });

    // Fetch data and update chart
    fetchRadTestDistribution(timeRange, shift).then(data => {
        radTestDistributionChart.data.labels = data.labels;
        radTestDistributionChart.data.datasets[0].data = data.values;
        radTestDistributionChart.update();
    }).catch(error => {
        console.error('Error fetching radiology test distribution data:', error);
    });
}

// Helper to create a gradient
function createGradient(ctx, colorStart, colorEnd) {
    const gradient = ctx.createLinearGradient(0, 0, 300, 300);
    gradient.addColorStop(0, colorStart);
    gradient.addColorStop(1, colorEnd);
    return gradient;
}

// Function to fetch radiology test distribution data
function fetchRadTestDistribution(timeRange, shift = null) {
    return new Promise((resolve, reject) => {
        const params = {
            timeRange: timeRange,
            dataType: 'radTestDistribution'
        };
        
        if (shift) {
            params.shift = shift;
        }
        
        $.ajax({
            url: 'fetch_rad_distribution.php',
            type: 'GET',
            data: params,
            success: function(response) {
                try {
                    const data = typeof response === 'string' ? JSON.parse(response) : response;
                    resolve(data);
                } catch (e) {
                    console.error('Error parsing response:', e);
                    reject(e);
                }
            },
            error: function(error) {
                console.error('AJAX Error:', error);
                reject(error);
            }
        });
    });
}

// Initialize charts on page load
$(document).ready(function() {
    initializeOrUpdateCompletedTestsChart();
    initializeOrUpdateCancelledTestsChart();
    initializeOrUpdateRadTestDistributionChart();
    
    // Add event listeners for time range selectors (dropdowns)
    $('#completedTimeRange').change(function() {
        const timeRange = $(this).val();
        // Update the active time filter button to match
        $('.time-filter').removeClass('active');
        $(`.time-filter[data-range="${timeRange}"]`).addClass('active');
        initializeOrUpdateCompletedTestsChart(timeRange);
    });
    
    $('#cancelledTimeRange').change(function() {
        const timeRange = $(this).val();
        // Update the active time filter button to match
        $('.time-filter').removeClass('active');
        $(`.time-filter[data-range="${timeRange}"]`).addClass('active');
        initializeOrUpdateCancelledTestsChart(timeRange);
    });
    
    $('#radTestDistributionRange').change(function() {
        const timeRange = $(this).val();
        // Update the active time filter button to match
        $('.time-filter').removeClass('active');
        $(`.time-filter[data-range="${timeRange}"]`).addClass('active');
        initializeOrUpdateRadTestDistributionChart(timeRange);
    });
    
    // Shift filter buttons
    $('.shift-filter').click(function() {
        $('.shift-filter').removeClass('active');
        $(this).addClass('active');
        const shift = $(this).data('shift');
        
        // Update shift display
        updateShiftDisplay(shift);
        
        // Update dashboard widgets
        const timeRange = $('.time-filter.active').data('range');
        updateDashboardWidgets(shift === 'current' ? currentShift : shift, timeRange);
        
        // Update charts
        initializeOrUpdateCompletedTestsChart(timeRange, shift === 'current' ? null : shift);
        initializeOrUpdateCancelledTestsChart(timeRange, shift === 'current' ? null : shift);
        initializeOrUpdateRadTestDistributionChart(timeRange, shift === 'current' ? null : shift);
    });
    
    // Time filter buttons
    $('.time-filter').click(function() {
        const timeRange = $(this).data('range');
        
        // Update active state
        $('.time-filter').removeClass('active');
        $(this).addClass('active');
        
        // Update dropdowns to match
        $('#completedTimeRange').val(timeRange);
        $('#cancelledTimeRange').val(timeRange);
        $('#radTestDistributionRange').val(timeRange);
        
        // Update dashboard widgets
        const shift = $('.shift-filter.active').data('shift');
        updateDashboardWidgets(shift === 'current' ? currentShift : shift, timeRange);
        
        // Update charts with the new time range
        initializeOrUpdateCompletedTestsChart(timeRange, shift === 'current' ? null : shift);
        initializeOrUpdateCancelledTestsChart(timeRange, shift === 'current' ? null : shift);
        initializeOrUpdateRadTestDistributionChart(timeRange, shift === 'current' ? null : shift);
    });
});

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
</script>

<style>
/* Widget Styles */
.dash-widget {
    border-radius: 12px;
    overflow: hidden;
    box-shadow: 0 4px 12px rgba(0,0,0,0.08);
    transition: all 0.3s ease;
    height: 80%;
}

.dash-widget:hover {
    transform: translateY(-5px);
    box-shadow: 0 8px 20px rgba(0,0,0,0.12);
}

.dash-widget-bg1, .dash-widget-bg2, .dash-widget-bg3, .dash-widget-bg4 {
    width: 80px;
    height: 80px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 28px;
    box-shadow: 0 4px 8px rgba(0,0,0,0.1);
}

.dash-widget-info {
    padding: 20px;
}

.dash-widget-info h3 {
    font-size: 28px;
    font-weight: 700;
    margin-bottom: 5px;
    color: #2c3e50;
}

/* Delayed animations for widgets */
.dash-widget:nth-child(1) { animation-delay: 0.1s; }
.dash-widget:nth-child(2) { animation-delay: 0.2s; }
.dash-widget:nth-child(3) { animation-delay: 0.3s; }
.dash-widget:nth-child(4) { animation-delay: 0.4s; }
.widget-title1, .widget-title2, .widget-title3, .widget-title4 {
    font-size: 14px;
    color: #7f8c8d;
}

/* Filter Controls */
.filter-controls {
    margin-top: 20px;
    display: flex;
    justify-content: center;
    flex-wrap: wrap;
    gap: 15px;
}

.btn-group {
    display: flex;
    flex-wrap: wrap;
    justify-content: center;
    gap: 8px;
}

.btn-outline-primary, .btn-outline-secondary {
    border-radius: 20px;
    padding: 8px 16px;
    font-size: 14px;
    transition: all 0.3s ease;
    border-width: 2px;
}

.btn-outline-primary {
    border-color: #12369e;
    color: #12369e;
}

.btn-outline-secondary {
    border-color: #6c757d;
    color: #6c757d;
}

.btn-outline-primary:hover, .btn-outline-secondary:hover {
    transform: translateY(-2px);
}

.btn-outline-primary.active {
    background-color: #12369e;
    color: white;
    box-shadow: 0 4px 8px rgba(18, 54, 158, 0.2);
}

.btn-outline-secondary.active {
    background-color: #6c757d;
    color: white;
    box-shadow: 0 4px 8px rgba(108, 117, 125, 0.2);
}

/* Current Shift Display */
.current-shift {
    text-align: center;
    padding: 20px;
    background: rgba(248, 249, 250, 0.7);
    border-radius: 10px;
    margin-bottom: 20px;
    border: 1px solid rgba(0,0,0,0.05);
}

.shift-icon {
    font-size: 48px;
    margin-bottom: 15px;
    display: block;
}

.fa-sun {
    color: #FDB813;
    text-shadow: 0 0 10px rgba(253, 184, 19, 0.3);
}

.fa-cloud-sun {
    color: #FF8C00;
    text-shadow: 0 0 10px rgba(255, 140, 0, 0.3);
}

.fa-moon {
    color: #4A4A8F;
    text-shadow: 0 0 10px rgba(74, 74, 143, 0.3);
}

#currentShiftLabel {
    font-weight: 600;
    color: #333;
    font-size: 1.2rem;
}

#currentShiftTime {
    margin: 10px 0;
    font-size: 18px;
    color: #333;
    font-weight: 500;
}

/* Form Controls */
.form-control {
    border-radius: .375rem;
    border-color: #ced4da;
    background-color: #f8f9fa;
    transition: all 0.3s ease;
}

select.form-control {
    border-radius: .375rem;
    border: 1px solid #ced4da;
    background-color: #f8f9fa;
    padding: .375rem 2.5rem .375rem .75rem;
    font-size: 1rem;
    line-height: 1.5;
    height: calc(2.25rem + 2px);
    -webkit-appearance: none;
    -moz-appearance: none;
    appearance: none;
    background-image: url('data:image/svg+xml;charset=UTF-8,%3Csvg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="20" height="20"%3E%3Cpath d="M7 10l5 5 5-5z" fill="%23aaa"/%3E%3C/svg%3E');
    background-repeat: no-repeat;
    background-position: right 0.75rem center;
    background-size: 20px;
}

select.form-control:focus {
    border-color: #12369e;
    box-shadow: 0 0 0 .2rem rgba(18, 54, 158, 0.25);
}

/* Card Styles */
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

.card-header {
    background: white;
    border-bottom: 1px solid rgba(0,0,0,0.05);
    padding: 20px;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.card-header h4 {
    font-weight: 600;
    color: #2c3e50;
    margin: 0;
}

.card-body {
    padding: 20px;
}

/* Canvas/Chart Styles */
canvas {
    width: 100% !important;
    height: 300px !important;
}
</style>