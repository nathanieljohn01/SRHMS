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

// Adjusted SQL query to order the months by date
$fetch_sold_query = mysqli_query($connection, "SELECT SUM(quantity) AS sold_quantity, DATE_FORMAT(invoice_datetime, '%M') AS month, MONTH(invoice_datetime) AS month_num FROM tbl_pharmacy_invoice GROUP BY MONTH(invoice_datetime) ORDER BY month_num");

$sold_data = [];
$transaction_months = [];
while($row = mysqli_fetch_assoc($fetch_sold_query)) {
    $sold_data[] = $row['sold_quantity'];
    $transaction_months[] = $row['month'];
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
                        <span class="widget-title1">Medicines <i class="fa fa-check" aria-hidden="true"></i></span>
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

            <!-- Expiring Soon Widget -->
            <div class="col-md-3">
                <div class="dash-widget">
                    <span class="dash-widget-bg3"><i class="fa fa-clock"></i></span>
                    <?php
                    $currentDate = date("Y-m-d");
                    $oneWeekLater = date("Y-m-d", strtotime("+1 week", strtotime($currentDate)));
                    $fetch_expiring_query = mysqli_query($connection, "SELECT COUNT(*) AS expiring_soon FROM tbl_medicines WHERE expiration_date BETWEEN '$currentDate' AND '$oneWeekLater'");
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
                    <span class="dash-widget-bg4"><i class="fa fa-money"></i></span>
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
                        <h4 class="card-title">New Medicines Added Per Month</h4>
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
                        <h4 class="card-title">Medicines Sold/Reduced Per Month</h4>
                    </div>
                    <div class="card-body">
                        <canvas id="soldMedicineChart"></canvas>
                    </div>
                </div>
            </div>
        </div> <!-- End of Row for charts -->

    </div>
</div>

<!-- Scripts -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
// Chart.js for New Medicines Per Month
var ctx = document.getElementById('newMedicineChart').getContext('2d');
var newMedicineChart = new Chart(ctx, {
    type: 'bar',
    data: {
        labels: <?php echo json_encode($months); ?>, // Months for new medicines added
        datasets: [{
            label: 'New Medicines Added',
            data: <?php echo json_encode($medicine_data); ?>, // New medicine data from the database
            backgroundColor: 'rgba(15, 54, 159, 0.2)',
            borderColor: 'rgba(15, 54, 159, 1)',
            borderWidth: 1
        }]
    },
    options: {
            responsive: true,
            maintainAspectRatio: false,
        scales: {
            y: {
                beginAtZero: true
            }
        }
    }
});

var soldCtx = document.getElementById('soldMedicineChart').getContext('2d');
var soldMedicineChart = new Chart(soldCtx, {
    type: 'bar', // Change chart type to bar
    data: {
        labels: <?php echo json_encode($transaction_months); ?>, // Months for transactions
        datasets: [{
            label: 'Medicines Sold/Reduced',
            data: <?php echo json_encode($sold_data); ?>, // Quantity sold/reduced data from the database
            backgroundColor: 'rgba(197, 16, 20, 0.2)', // Bar fill color
            borderColor: 'rgba(197, 16, 20, 1)',       // Bar border color
            borderWidth: 1                              // Bar border thickness
        }]
    },
    options: {
            responsive: true,
            maintainAspectRatio: false,
        scales: {
            y: {
                beginAtZero: true,                      // Start Y-axis at zero
                ticks: {
                    stepSize: 1                         // Adjust step size as needed
                }
            },
            x: {
                ticks: {
                    autoSkip: true,                     // Auto-skip labels if there are too many
                    maxTicksLimit: 12                   // Limit number of ticks for months
                }
            }
        },
        plugins: {
            legend: {
                position: 'top',                        // Position legend at the top
            },
            tooltip: {
                callbacks: {
                    label: function(tooltipItem) {
                        return 'Sold: ' + tooltipItem.raw; // Customize tooltip display
                    }
                }
            }
        }
    }
});

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

<?php 
include('footer.php');
?>

<!-- Custom Button Styling -->
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
