<?php
session_start();
if(empty($_SESSION['name'])) {
    header('location:index.php');
}
include('header.php');
include('includes/connection.php');

// Get totals for pie chart
$pending_query = "SELECT SUM(total_due) as total FROM tbl_payment WHERE remaining_balance > 0";
$paid_query = "SELECT SUM(total_due) as total FROM tbl_payment WHERE remaining_balance = 0";

$pending_total = mysqli_fetch_assoc(mysqli_query($connection, $pending_query))['total'] ?? 0;
$paid_total = mysqli_fetch_assoc(mysqli_query($connection, $paid_query))['total'] ?? 0;
?>

<div class="page-wrapper">
    <div class="content">
        <div class="row">
            <!-- Transaction Summary Widgets -->
            <div class="col-md-6 col-sm-6 col-lg-6 col-xl-3">
                <div class="dash-widget">
                    <span class="dash-widget-bg1"><i class="fa fa-exchange-alt"></i></span>
                    <?php
                    $query = "SELECT COUNT(payment_id) as total FROM tbl_payment";
                    $result = mysqli_query($connection, $query);
                    $total = mysqli_fetch_assoc($result);
                    ?>
                    <div class="dash-widget-info text-right">
                        <h3><?php echo $total['total']; ?></h3>
                        <span class="widget-title1">Total Transactions</span>
                    </div>
                </div>
            </div>

            <div class="col-md-6 col-sm-6 col-lg-6 col-xl-3">
                <div class="dash-widget">
                    <span class="dash-widget-bg2"><i class="fas fa-money-bill-alt"></i></span>
                    <?php
                    $query = "SELECT SUM(total_due) as total FROM tbl_payment";
                    $result = mysqli_query($connection, $query);
                    $revenue = mysqli_fetch_assoc($result);
                    ?>
                    <div class="dash-widget-info text-right">
                        <h3>₱<?php echo number_format($revenue['total'], 2); ?></h3>
                        <span class="widget-title2">Total Revenue</span>
                    </div>
                </div>
            </div>

            <div class="col-md-6 col-sm-6 col-lg-6 col-xl-3">
                <div class="dash-widget">
                    <span class="dash-widget-bg3"><i class="fa fa-clock"></i></span>
                    <?php
                    $query = "SELECT COUNT(payment_id) as total FROM tbl_payment WHERE remaining_balance > 0";
                    $result = mysqli_query($connection, $query);
                    $pending = mysqli_fetch_assoc($result);
                    ?>
                    <div class="dash-widget-info text-right">
                        <h3><?php echo $pending['total']; ?></h3>
                        <span class="widget-title3">Pending Payments</span>
                    </div>
                </div>
            </div>

            <div class="col-md-6 col-sm-6 col-lg-6 col-xl-3">
                <div class="dash-widget">
                    <span class="dash-widget-bg4"><i class="fa fa-check-circle"></i></span>
                    <?php
                    $query = "SELECT COUNT(payment_id) as total FROM tbl_payment WHERE remaining_balance = 0";
                    $result = mysqli_query($connection, $query);
                    $paid = mysqli_fetch_assoc($result);
                    ?>
                    <div class="dash-widget-info text-right">
                        <h3><?php echo $paid['total']; ?></h3>
                        <span class="widget-title4">Paid Payments</span>
                    </div>
                </div>
            </div>

            <!-- Revenue Chart -->
            <div class="col-12 col-md-6 col-lg-6 col-xl-6">
                <div class="card">
                    <div class="card-header">
                        <h4 class="card-title d-inline-block">Revenue Overview</h4>
                        <div class="float-right">
                            <select id="revenueTimeRange" class="form-control">
                                <option value="weekly">Weekly</option>
                                <option value="monthly" selected>Monthly</option>
                                <option value="yearly">Yearly</option>
                            </select>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="chart-container">
                            <canvas id="revenueChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Payment Distribution Chart -->
            <div class="col-12 col-md-6 col-lg-6 col-xl-6">
                <div class="card">
                    <div class="card-header">
                        <h4 class="card-title d-inline-block">Payment Distribution</h4>
                        <div class="float-right">
                            <select id="distributionTimeRange" class="form-control">
                                <option value="weekly">Weekly</option>
                                <option value="monthly" selected>Monthly</option>
                                <option value="yearly">Yearly</option>
                            </select>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="chart-container">
                            <canvas id="distributionChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Payment Status Pie Chart -->
            <div class="col-12 col-md-12 col-lg-12 col-xl-12">
                <div class="card">
                    <div class="card-header">
                        <h4 class="card-title d-inline-block">Payment Status</h4>
                    </div>
                    <div class="card-body">
                        <div class="chart-container">
                            <canvas id="paymentStatusChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include('footer.php'); ?>

<!-- Chart.js and jQuery -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-annotation@1.0.1/dist/chartjs-plugin-annotation.min.js"></script>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

<script>
// Chart instances
var revenueChart, distributionChart, paymentStatusChart;

// Label generators
function getWeeklyLabels() {
    const labels = [];
    for (let i = 1; i <= 8; i++) {
        labels.push(`Week ${i}`);
    }
    return labels;
}

function getMonthlyLabels() {
    const months = [];
    const now = new Date();
    const currentDate = new Date(now.getFullYear(), now.getMonth() - 11, 1);
    
    while (currentDate <= now) {
        const monthName = currentDate.toLocaleString('default', { month: 'short' });
        const year = currentDate.getFullYear();
        const label = (currentDate.getMonth() === 0 || months.length === 0) ? 
            `${monthName} ${year}` : monthName;
        months.push(label);
        currentDate.setMonth(currentDate.getMonth() + 1);
    }
    return months;
}

function getYearlyLabels() {
    const years = [];
    const now = new Date();
    const currentYear = now.getFullYear();
    for (let i = currentYear - 4; i <= currentYear; i++) {
        years.push(i.toString());
    }
    return years;
}

// Data fetcher
function fetchPaymentData(timeRange, dataType) {
    return $.ajax({
        url: 'fetch_payment_data.php',
        type: 'GET',
        dataType: 'json',
        data: { timeRange, dataType }
    }).fail(function(jqXHR, textStatus, errorThrown) {
        console.error(`Error fetching ${dataType} data:`, textStatus, errorThrown);
        return [];
    });
}

// Chart configuration objects
const chartConfigs = {
    revenue: {
        type: 'line',
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                tooltip: {
                    callbacks: {
                        label: ctx => '₱' + ctx.raw.toLocaleString('en-US', {minimumFractionDigits: 2})
                    }
                }
            },
            scales: {
                x: { grid: { display: false } },
                y: { 
                    beginAtZero: true,
                    ticks: { callback: value => '₱' + value.toLocaleString('en-US') }
                }
            }
        }
    },
    distribution: {
        type: 'bar',
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { position: 'top' },
                tooltip: {
                    callbacks: {
                        label: ctx => ctx.dataset.label + ': ' + ctx.raw.toLocaleString('en-US')
                    }
                }
            },
            scales: {
                x: { grid: { display: false } },
                y: { beginAtZero: true }
            }
        }
    },
    paymentStatus: {
        type: 'pie',
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { position: 'top' },
                tooltip: {
                    callbacks: {
                        label: ctx => `${ctx.label}: ₱${ctx.raw.toLocaleString('en-US', {minimumFractionDigits: 2})}`
                    }
                }
            }
        }
    }
};

// Chart initializers
function initChart(chartId, config, labels, datasets) {
    const ctx = document.getElementById(chartId).getContext('2d');
    if (window[chartId]) window[chartId].destroy();
    
    // Create gradient for revenue chart
    if (chartId === 'revenueChart') {
        const gradient = ctx.createLinearGradient(0, 0, 0, 400);
        gradient.addColorStop(0, 'rgba(15, 54, 159, 0.8)');
        gradient.addColorStop(1, 'rgba(15, 54, 159, 0.1)');
        datasets[0].backgroundColor = gradient;
    }
    
    window[chartId] = new Chart(ctx, {
        type: config.type,
        data: { labels, datasets },
        options: config.options
    });
}

function initRevenueChart(timeRange = 'monthly') {
    const labels = timeRange === 'weekly' ? getWeeklyLabels() : 
                 timeRange === 'monthly' ? getMonthlyLabels() : getYearlyLabels();
    
    const dataset = {
        label: 'Total Revenue',
        data: Array(labels.length).fill(0),
        borderColor: 'rgba(15, 54, 159, 1)',
        borderWidth: 2,
        tension: 0.3,
        fill: true,
        pointBackgroundColor: '#fff',
        pointBorderWidth: 2,
        pointRadius: 5
    };
    
    initChart('revenueChart', chartConfigs.revenue, labels, [dataset]);
    
    fetchPaymentData(timeRange, 'revenue').then(data => {
        revenueChart.data.datasets[0].data = data;
        revenueChart.update();
    });
}

function initDistributionChart(timeRange = 'monthly') {
    const labels = timeRange === 'weekly' ? getWeeklyLabels() : 
                  timeRange === 'monthly' ? getMonthlyLabels() : getYearlyLabels();
    
    const datasets = [
        {
            label: 'Inpatient',
            backgroundColor: 'rgba(197, 16, 20, 0.8)',
            data: Array(labels.length).fill(0),
            borderRadius: 6
        },
        {
            label: 'Outpatient',
            backgroundColor: 'rgba(120, 182, 35, 0.8)',
            data: Array(labels.length).fill(0),
            borderRadius: 6
        },
        {
            label: 'Hemodialysis',
            backgroundColor: 'rgba(255, 193, 7, 0.8)',
            data: Array(labels.length).fill(0),
            borderRadius: 6
        },
        {
            label: 'Newborn',
            backgroundColor: 'rgba(156, 39, 176, 0.8)',
            data: Array(labels.length).fill(0),
            borderRadius: 6
        }
    ];
    
    initChart('distributionChart', chartConfigs.distribution, labels, datasets);
    
    Promise.all([
        fetchPaymentData(timeRange, 'inpatient'),
        fetchPaymentData(timeRange, 'outpatient'),
        fetchPaymentData(timeRange, 'hemodialysis'),
        fetchPaymentData(timeRange, 'newborn')
    ]).then(results => {
        results.forEach((data, i) => {
            distributionChart.data.datasets[i].data = data;
        });
        distributionChart.update();
    });
}

function initPaymentStatusChart() {
    const ctx = document.getElementById('paymentStatusChart').getContext('2d');
    
    // Create gradients using the requested colors
    const pendingGradient = ctx.createLinearGradient(0, 0, 0, 400);
    pendingGradient.addColorStop(0, 'rgba(197, 16, 20, 0.9)');  // Red top
    pendingGradient.addColorStop(1, 'rgba(197, 16, 20, 0.6)');  // Red bottom
    
    const paidGradient = ctx.createLinearGradient(0, 0, 0, 400);
    paidGradient.addColorStop(0, 'rgba(15, 54, 159, 0.9)');     // Blue top
    paidGradient.addColorStop(1, 'rgba(15, 54, 159, 0.6)');     // Blue bottom
    
    const labels = ['Pending Payments', 'Paid Payments'];
    const datasets = [{
        data: [<?php echo $pending_total; ?>, <?php echo $paid_total; ?>],
        backgroundColor: [
            pendingGradient,
            paidGradient
        ],
        borderColor: [
            'rgba(197, 16, 20, 1)',
            'rgba(15, 54, 159, 1)'
        ],
        borderWidth: 2,
        hoverBackgroundColor: [
            'rgba(197, 16, 20, 1)',
            'rgba(15, 54, 159, 1)'
        ],
        hoverBorderColor: '#fff',
        hoverBorderWidth: 3
    }];
    
    initChart('paymentStatusChart', chartConfigs.paymentStatus, labels, datasets);
}

// Initialize on load
$(document).ready(function() {
    initRevenueChart();
    initDistributionChart();
    initPaymentStatusChart();
    
    $('#revenueTimeRange').change(function() {
        initRevenueChart($(this).val());
    });
    
    $('#distributionTimeRange').change(function() {
        initDistributionChart($(this).val());
    });
});
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

.btn-outline-primary, .btn-outline-secondary {
    border-radius: 20px;
    padding: 8px 16px;
    font-size: 14px;
    transition: all 0.3s ease;
    border-width: 2px;
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


