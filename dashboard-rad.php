<?php
session_start();
if(empty($_SESSION['name'])) {
	header('location:index.php');
	exit;
}
include('header.php');
include('includes/connection.php');
?>
<div class="page-wrapper">
    <div class="content">
        <div class="row">
            <div class="col-md-6 col-sm-6 col-lg-6 col-xl-3">
                <div class="dash-widget">
                    <span class="dash-widget-bg1"><i class="fa fa-user-o"></i></span>
                    <?php
                    $fetch_query = mysqli_query($connection, "SELECT COUNT(*) AS total FROM tbl_patient WHERE status=1"); 
                    $patient = mysqli_fetch_row($fetch_query);
                    ?>
                    <div class="dash-widget-info text-right">
                        <h3><?php echo $patient[0]; ?></h3>
                        <span class="widget-title1">Patients <i class="fa fa-check" aria-hidden="true"></i></span>
                    </div>
                </div>
            </div>

            <div class="col-md-6 col-sm-6 col-lg-6 col-xl-3">
                <div class="dash-widget">
                    <span class="dash-widget-bg2"><i class="fa fa-user-o" aria-hidden="true"></i></span>
                    <?php
                    $fetch_query = mysqli_query($connection, "SELECT COUNT(*) AS total FROM tbl_patient WHERE patient_type='OutPatient' AND status=1"); 
                    $outpatient = mysqli_fetch_row($fetch_query);
                    ?>
                    <div class="dash-widget-info text-right">
                        <h3><?php echo $outpatient[0]; ?></h3>
                        <span class="widget-title2">Out Patients <i class="fa fa-check" aria-hidden="true"></i></span>
                    </div>
                </div>
            </div>
            <div class="col-md-6 col-sm-6 col-lg-6 col-xl-3">
                <div class="dash-widget">
                    <span class="dash-widget-bg3"><i class="fa fa-user-o" aria-hidden="true"></i></span>
                    <?php
                    $fetch_query = mysqli_query($connection, "SELECT COUNT(*) AS total FROM tbl_patient WHERE patient_type='InPatient' AND status=1"); 
                    $inpatient = mysqli_fetch_row($fetch_query);
                    ?>
                    <div class="dash-widget-info text-right">
                        <h3><?php echo $inpatient[0]; ?></h3>
                        <span class="widget-title3">In Patients <i class="fa fa-check" aria-hidden="true"></i></span>
                    </div>
                </div>
            </div>
            <div class="col-md-6 col-sm-6 col-lg-6 col-xl-3">
                <div class="dash-widget">
                    <span class="dash-widget-bg4"><i class="fa fa-user-o" aria-hidden="true"></i></span>
                    <?php
                    $fetch_query = mysqli_query($connection, "SELECT COUNT(*) AS total FROM tbl_hemodialysis"); 
                    $hemodialysis = mysqli_fetch_row($fetch_query);
                    ?>
                    <div class="dash-widget-info text-right">
                        <h3><?php echo $hemodialysis[0]; ?></h3>
                        <span class="widget-title4">Hemodialysis <i class="fa fa-check" aria-hidden="true"></i></span>
                    </div>
                </div>
            </div>
                       <!-- New Patients Chart -->
                       <div class="col-12 col-md-6 col-lg-6 col-xl-6">
                <div class="card">
                    <div class="card-header">
                        <h4 class="card-title d-inline-block">Patients Overview</h4>
                    </div>
                    <div class="card-body">
                        <canvas id="patientChart"></canvas>
                    </div>
                </div>
            </div>

            <!-- Total Patients Chart -->
            <div class="col-12 col-md-6 col-lg-6 col-xl-6">
                <div class="card">
                    <div class="card-header">
                        <h4 class="card-title d-inline-block">Total Patients Overview</h4>
                    </div>
                    <div class="card-body">
                        <canvas id="totalPatientChart"></canvas>
                    </div>
                </div>
            </div>

            <?php
// Create an array of months from current month to December
$current_month = date('n');
$months = array();
for ($i = $current_month; $i <= 12; $i++) {
    $months[] = $i;
}
?>

        </div>
    </div>
</div>
<?php 
 include('footer.php');
?>

<!-- Include Chart.js library -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
    // Get the canvas element
    var ctx = document.getElementById('patientChart').getContext('2d');

    // Create the chart
    var patientChart = new Chart(ctx, {
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
                label: 'Total Patients',
                data: [
                    <?php
                    $total_patients = array();
                    foreach ($months as $month) {
                        $result = mysqli_query($connection, "SELECT COUNT(*) AS total FROM tbl_patient WHERE MONTH(created_at) = '$month' AND YEAR(created_at) = YEAR(NOW())");
                        $row = mysqli_fetch_assoc($result);
                        $total_patients[] = $row['total'];
                    }
                    echo implode(',', $total_patients);
                    ?>
                ],
                backgroundColor: 'rgba(120, 182, 35, 0.5)',
                borderColor: 'rgba(120, 182, 35, 1)',
                borderWidth: 1,
                lineTension: 0.3
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                yAxes: [{
                    ticks: {
                        beginAtZero: true,
                        callback: function(value) {
                            if (value % 1 === 0) {
                                return value;
                            }
                        }
                    }
                }]
            },
            animation: {
                duration: 2000,
            },
            hover: {
                animationDuration: 2000,
            },
            responsiveAnimationDuration: 2000,
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