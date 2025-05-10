<?php
session_start();
if(empty($_SESSION['name'])) {
    header('location:index.php');
    exit;
}
include('header.php');
include('includes/connection.php');

// Fetch data for new medicines added per month
$medicine_data = [];
$months = [];
$fetch_chart_data_query = mysqli_query($connection, "SELECT COUNT(*) AS count, DATE_FORMAT(new_added_date, '%M') AS month FROM tbl_medicines WHERE new_added_date IS NOT NULL GROUP BY MONTH(new_added_date)");
while($row = mysqli_fetch_assoc($fetch_chart_data_query)) {
    $medicine_data[] = $row['count'];
    $months[] = $row['month'];
}


?>

<div class="page-wrapper">
    <div class="content">
        <div class="row">
            <!-- Total Medicines Widget -->
            <div class="col-md-3">
                <div class="dash-widget">
                    <span class="dash-widget-bg1"><i class="fa fa-tablets"></i></span>
                    <?php
                    $fetch_total_query = mysqli_query($connection, "SELECT COUNT(*) AS total FROM tbl_medicines");
                    $total_medicines = mysqli_fetch_assoc($fetch_total_query);
                    ?>
                    <div class="dash-widget-info text-right">
                        <h3><?php echo $total_medicines['total']; ?></h3>
                        <span class="widget-title1">Medicines</span>
                    </div>
                </div>
            </div>

            <!-- New Medicines Added Widget -->
            <div class="col-md-3">
                <div class="dash-widget">
                    <span class="dash-widget-bg2"><i class="fa fa-tablets"></i></span>
                    <?php
                    $fetch_new_query = mysqli_query($connection, "SELECT COUNT(*) AS new FROM tbl_medicines WHERE new_added_date >= DATE_SUB(NOW(), INTERVAL 1 MONTH)");
                    $new_medicines = mysqli_fetch_assoc($fetch_new_query);
                    ?>
                    <div class="dash-widget-info text-right">
                        <h3><?php echo $new_medicines['new']; ?></h3>
                        <span class="widget-title2">New Medicines</span>
                    </div>
                </div>
            </div>

            <!-- Improved Expiring Soon Widget -->
            <div class="col-md-3">
                <div class="dash-widget">
                    <span class="dash-widget-bg3"><i class="fa fa-clock"></i></span>
                    <?php
                    $currentDate = date("Y-m-d");
                    $oneMonthLater = date("Y-m-d", strtotime("+1 month", strtotime($currentDate)));
                    
                    // Count medicines expiring within 30 days (matches your table badge logic)
                    $fetch_expiring_query = mysqli_query($connection, 
                        "SELECT COUNT(*) AS expiring_soon 
                        FROM tbl_medicines 
                        WHERE expiration_date BETWEEN '$currentDate' AND '$oneMonthLater'
                        AND expiration_date >= '$currentDate'");
                    
                    $expiring_soon = mysqli_fetch_assoc($fetch_expiring_query);
                    ?>
                    <div class="dash-widget-info text-right">
                        <h3><?php echo $expiring_soon['expiring_soon']; ?></h3>
                        <span class="widget-title3">Expiring Soon</span>
                    </div>
                </div>
            </div>

            <!-- Number of Transactions Widget -->
            <div class="col-md-3">
                <div class="dash-widget">
                                        <span class="dash-widget-bg4"><i class="fas fa-money-bill-alt"></i></span>
                    <?php
                    $fetch_transaction_count_query = mysqli_query($connection, "SELECT COUNT(id) AS total_transactions FROM tbl_pharmacy_invoice WHERE invoice_datetime");
                    $transaction_count = mysqli_fetch_assoc($fetch_transaction_count_query);
                    ?>
                    <div class="dash-widget-info text-right">
                        <h3><?php echo $transaction_count['total_transactions']; ?></h3>
                        <span class="widget-title4">Transactions</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Chart Section for New Medicines -->
        <div class="row">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h4 class="card-title d-inline-block">New Medicines Updated Overview</h4>
                        <div class="float-right">
                            <select id="newMedicineTimeRange" class="form-control">
                                <option value="weekly">Weekly</option>
                                <option value="monthly" selected>Monthly</option>
                                <option value="yearly">Yearly</option>
                            </select>
                        </div>
                    </div>
                    <div class="card-body">
                        <canvas id="newMedicineChart"></canvas>
                    </div>
                </div>
            </div>
            
            <!-- Chart Section for Reduced Medicines -->
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h4 class="card-title d-inline-block">Medicines Sold/Reduced Overview</h4>
                        <div class="float-right">
                            <select id="soldMedicineTimeRange" class="form-control">
                                <option value="weekly">Weekly</option>
                                <option value="monthly" selected>Monthly</option>
                                <option value="yearly">Yearly</option>
                            </select>
                        </div>
                    </div>
                    <div class="card-body">
                        <canvas id="soldMedicineChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
        <!-- End of Row for charts -->

    </div>
</div>

<!-- Scripts -->

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

<script>
$(document).ready(function() {
    // Variables to store chart instances
    var newMedicineChart;
    var soldMedicineChart;
    
    // Function to fetch medicine data via AJAX
    function fetchMedicineData(timeRange, dataType) {
        return $.ajax({
            url: 'fetch_medicine_data.php',
            type: 'GET',
            dataType: 'json',
            data: {
                timeRange: timeRange,
                dataType: dataType
            }
        });
    }
    
    // Function to initialize or update the new medicine chart (now as bar chart)
    function initializeOrUpdateNewMedicineChart(timeRange = 'monthly') {
        const ctx = document.getElementById('newMedicineChart').getContext('2d');
        
        // Destroy previous chart if it exists
        if (newMedicineChart) {
            newMedicineChart.destroy();
        }
        
        // Create gradient for the bar chart
        let gradient = ctx.createLinearGradient(0, 0, 0, 400);
        gradient.addColorStop(0, 'rgba(15, 54, 159, 0.8)');  // Darker blue at top
        gradient.addColorStop(0.5, 'rgba(15, 54, 159, 0.5)'); // Medium blue
        gradient.addColorStop(1, 'rgba(15, 54, 159, 0.2)');  // Lighter blue at bottom
        
        // Create new BAR chart with gradient
        newMedicineChart = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: [], // Will be filled via AJAX
                datasets: [{
                    label: 'New Medicines Updated',
                    data: [], // Will be filled via AJAX
                    backgroundColor: gradient,
                    borderColor: 'rgba(15, 54, 159, 1)',
                    borderWidth: 1,
                    borderRadius: 6,
                    borderSkipped: false,
                    hoverBackgroundColor: 'rgba(15, 54, 159, 0.9)' // Darker on hover
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: {
                            color: 'rgba(0, 0, 0, 0.05)'
                        }
                    },
                    x: {
                        grid: {
                            display: false
                        }
                    }
                },
                plugins: {
                    legend: {
                        position: 'top',
                        labels: {
                            usePointStyle: true,
                            padding: 20
                        }
                    },
                    tooltip: {
                        callbacks: {
                            label: function(tooltipItem) {
                                return 'New: ' + tooltipItem.raw;
                            }
                        }
                    }
                }
            }
        });
        
        // Fetch data and update chart
        fetchMedicineData(timeRange, 'new').then(response => {
            if (response.success) {
                newMedicineChart.data.labels = response.labels;
                newMedicineChart.data.datasets[0].data = response.data;
                newMedicineChart.update();
            }
        }).catch(error => {
            console.error('Error fetching new medicine data:', error);
        });
    }
    
    // Function to initialize or update the sold medicine chart (kept as line chart)
    function initializeOrUpdateSoldMedicineChart(timeRange = 'monthly') {
        const soldCtx = document.getElementById('soldMedicineChart').getContext('2d');
        
        // Destroy previous chart if it exists
        if (soldMedicineChart) {
            soldMedicineChart.destroy();
        }
        
        // Create gradient for the chart
        let gradient = soldCtx.createLinearGradient(0, 0, 0, 400);
        gradient.addColorStop(0, 'rgba(197, 16, 20, 0.8)');
        gradient.addColorStop(1, 'rgba(197, 16, 20, 0.2)');
        
        // Create new chart with placeholder data
        soldMedicineChart = new Chart(soldCtx, {
            type: 'line',
            data: {
                labels: [], // Will be filled via AJAX
                datasets: [{
                    label: 'Medicines Sold/Reduced',
                    data: [], // Will be filled via AJAX
                    backgroundColor: gradient,
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
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: {
                            color: 'rgba(0, 0, 0, 0.05)'
                        },
                        ticks: {
                            stepSize: 1
                        }
                    },
                    x: {
                        grid: {
                            display: false
                        },
                        ticks: {
                            autoSkip: true,
                            maxTicksLimit: 12
                        }
                    }
                },
                plugins: {
                    legend: {
                        position: 'top',
                        labels: {
                            usePointStyle: true,
                            padding: 20
                        }
                    },
                    tooltip: {
                        callbacks: {
                            label: function(tooltipItem) {
                                return 'Sold: ' + tooltipItem.raw;
                            }
                        }
                    }
                }
            }
        });
        
        // Fetch data and update chart
        fetchMedicineData(timeRange, 'sold').then(response => {
            if (response.success) {
                soldMedicineChart.data.labels = response.labels;
                soldMedicineChart.data.datasets[0].data = response.data;
                soldMedicineChart.update();
            }
        }).catch(error => {
            console.error('Error fetching sold medicine data:', error);
        });
    }
    
    // Initialize charts on page load
    initializeOrUpdateNewMedicineChart();
    initializeOrUpdateSoldMedicineChart();
    
    // Add event listeners for time range selectors
    $('#newMedicineTimeRange').change(function() {
        initializeOrUpdateNewMedicineChart($(this).val());
    });
    
    $('#soldMedicineTimeRange').change(function() {
        initializeOrUpdateSoldMedicineChart($(this).val());
    });
    
    // Dropdown toggle functionality
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
});
</script>

<?php 
include('footer.php');
?>

<!-- Custom Button Styling -->
<style>
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
.dash-widget-bg1, 
.dash-widget-bg2,
.dash-widget-bg3,
.dash-widget-bg4 {
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

.widget-title1, 
.widget-title2, 
.widget-title3, 
.widget-title4 {
    font-size: 14px;
    color: #7f8c8d;
}
/* Delayed animations for widgets */
.dash-widget:nth-child(1) { animation-delay: 0.1s; }
.dash-widget:nth-child(2) { animation-delay: 0.2s; }
.dash-widget:nth-child(3) { animation-delay: 0.3s; }
.dash-widget:nth-child(4) { animation-delay: 0.4s; }
.card:hover {
    box-shadow: 0 8px 20px rgba(0,0,0,0.12);
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
</style>
