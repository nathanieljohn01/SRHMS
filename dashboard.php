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
            <!-- Widgets -->
            <div class="col-md-6 col-sm-6 col-lg-6 col-xl-3">
                <!-- Employee Widget -->
                <div class="dash-widget">
                    <span class="dash-widget-bg1"><i class="fa fa-users" aria-hidden="true"></i></span>
                    <?php
                    $fetch_query = mysqli_query($connection, "select count(*) as total from tbl_employee");
                    $doc = mysqli_fetch_row($fetch_query);
                    ?>
                    <div class="dash-widget-info text-right">
                        <h3><?php echo $doc[0]; ?></h3>
                        <span class="widget-title1">Employee</span>
                    </div>
                </div>
            </div>

            <div class="col-md-6 col-sm-6 col-lg-6 col-xl-3">
                <!-- Doctors Widget -->
                <div class="dash-widget">
                    <span class="dash-widget-bg2"><i class="fa fa-user-md" aria-hidden="true"></i></span>
                    <?php
                    $fetch_query = mysqli_query($connection, "select count(*) as total from tbl_employee where status=1 and role=2");
                    $doc = mysqli_fetch_row($fetch_query);
                    ?>
                    <div class="dash-widget-info text-right">
                        <h3><?php echo $doc[0]; ?></h3>
                        <span class="widget-title2">Doctors</span>
                    </div>
                </div>
            </div>

            <div class="col-md-6 col-sm-6 col-lg-6 col-xl-3">
                <!-- Nurses Widget -->
                <div class="dash-widget">
                    <span class="dash-widget-bg4"><i class="fa fa-user-nurse" aria-hidden="true"></i></span>
                    <?php
                    $fetch_query = mysqli_query($connection, "select count(*) as total from tbl_employee where status=1 and role=3");
                    $doc = mysqli_fetch_row($fetch_query);
                    ?>
                    <div class="dash-widget-info text-right">
                        <h3><?php echo $doc[0]; ?></h3>
                        <span class="widget-title4">Nurses</span>
                    </div>
                </div>
            </div>

            <div class="col-md-6 col-sm-6 col-lg-6 col-xl-3">
                <!-- Patients Widget -->
                <div class="dash-widget">
                    <span class="dash-widget-bg3"><i class="fa fa-user"></i></span>
                    <?php
                    $fetch_query = mysqli_query($connection, "select count(*) as total from tbl_patient where status=1");
                    $patient = mysqli_fetch_row($fetch_query);
                    ?>
                    <div class="dash-widget-info text-right">
                        <h3><?php echo $patient[0]; ?></h3>
                        <span class="widget-title3">Patients</span>
                    </div>
                </div>
            </div>

            <!-- New Patients Chart -->
            <div class="col-12 col-md-6 col-lg-6 col-xl-6">
                <div class="card">
                    <div class="card-header">
                        <h4 class="card-title d-inline-block">Patients Overview</h4>
                        <div class="float-right">
                            <select id="patientTimeRange" class="form-control">
                                <option value="weekly">Weekly</option>
                                <option value="monthly" selected>Monthly</option>
                                <option value="yearly">Yearly</option>
                            </select>
                        </div>
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
                        <div class="float-right">
                            <select id="totalPatientTimeRange" class="form-control">
                                <option value="weekly">Weekly</option>
                                <option value="monthly" selected>Monthly</option>
                                <option value="yearly">Yearly</option>
                            </select>
                        </div>
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

            <style>
            /* Updated CSS for doctor name */
            .contact-name {
                font-size: 1.1em; /* Slightly larger text */
                font-weight: bold; /* Bold text */
                color: #3c3c3c; /* Highlight color */
            }
            .contact-info {
                padding: 10px;
                border-bottom: 1px solid #ddd; /* Optional: Adds a bottom border for separation */
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
            /* Updated CSS for doctor name */
            .contact-name {
                font-size: 1.1em; /* Slightly larger text */
                font-weight: bold; /* Bold text */
                color: #3c3c3c; /* Highlight color */
            }
            .contact-info {
                padding: 10px;
                border-bottom: 1px solid #ddd; /* Optional: Adds a bottom border for separation */
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

            .doctor-image img {
                width: 80px;
                height: 80px;
                border-radius: 50%;
                object-fit: cover;
                border: 3px solid #fff;
                box-shadow: 0 0 10px rgba(0,0,0,0.1);
            }

            .doctor-details {
                margin-left: 20px;
            }

            .doctor-name {
                margin: 0;
                color: #333;
                font-size: 18px;
                font-weight: 600;
            }

            .card {
                border: none;
                border-radius: 12px;
                box-shadow: 0 4px 12px rgba(0,0,0,0.08);
                transition: all 0.3s ease;
                margin-bottom: 24px;
                overflow: hidden;
            }

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

            .specialization {
                color: rgba(15, 54, 159, 1);
                margin: 5px 0;
                font-weight: 500;
            }

            .schedule {
                color: #666;
                margin: 5px 0;
                font-size: 14px;
            }

            .schedule i {
                margin-right: 5px;
                color: gray;
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
            /* Delayed animations for widgets */
            .dash-widget:nth-child(1) { animation-delay: 0.1s; }
            .dash-widget:nth-child(2) { animation-delay: 0.2s; }
            .dash-widget:nth-child(3) { animation-delay: 0.3s; }
            .dash-widget:nth-child(4) { animation-delay: 0.4s; }

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
            </style>

            <!-- Include Chart.js library -->
            <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
            <script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-annotation@1.0.1/dist/chartjs-plugin-annotation.min.js"0></script>

            <script>
            // Global variables to store chart instances
            var patientChart;
            var totalPatientChart;

            // Function to generate weekly labels
            function getWeeklyLabels() {
                const weeks = [];
                const now = new Date();
                const currentWeek = Math.ceil(now.getDate() / 7);
                
                for (let i = 1; i <= currentWeek; i++) {
                    weeks.push(`Week ${i}`);
                }
                return weeks;
            }

            // Function to generate monthly labels
            function getMonthlyLabels() {
                const months = [];
                const now = new Date();
                const currentMonth = now.getMonth() + 1; // JavaScript months are 0-based
                
                for (let i = 1; i <= currentMonth; i++) {
                    const monthName = new Date(now.getFullYear(), i - 1, 1).toLocaleString('default', { month: 'long' });
                    months.push(monthName);
                }
                return months;
            }

            // Function to generate yearly labels
            function getYearlyLabels() {
                const years = [];
                const now = new Date();
                const currentYear = now.getFullYear();
                
                // Show last 5 years including current year
                for (let i = currentYear - 4; i <= currentYear; i++) {
                    years.push(i.toString());
                }
                return years;
            }

            // Function to fetch patient data via AJAX
            function fetchPatientData(timeRange, patientType) {
                return new Promise((resolve, reject) => {
                    $.ajax({
                        url: 'fetch_patient_data.php',
                        type: 'GET',
                        data: {
                            timeRange: timeRange,
                            patientType: patientType
                        },
                        success: function(response) {
                            let data;
                            try {
                                data = typeof response === 'string' ? JSON.parse(response) : response;
                            } catch (e) {
                                console.error('Error parsing response:', e);
                                data = [];
                            }
                            resolve(data);
                        },
                        error: function(error) {
                            reject(error);
                        }
                    });
                });
            }

            // Function to initialize or update the patient chart
            function initializeOrUpdatePatientChart(timeRange = 'monthly') {
                const ctx = document.getElementById('patientChart').getContext('2d');
                
                // Destroy previous chart if it exists
                if (patientChart) {
                    patientChart.destroy();
                }
                
                // Set labels based on time range
                let labels;
                if (timeRange === 'weekly') {
                    labels = getWeeklyLabels();
                } else if (timeRange === 'monthly') {
                    labels = getMonthlyLabels();
                } else { // yearly
                    labels = getYearlyLabels();
                }
                
                // Gradient backgrounds for the bars
                let inpatientGradient = ctx.createLinearGradient(0, 0, 0, 400);
                inpatientGradient.addColorStop(0, 'rgba(197, 16, 20, 0.8)');
                inpatientGradient.addColorStop(1, 'rgba(197, 16, 20, 0.2)');
                
                let outpatientGradient = ctx.createLinearGradient(0, 0, 0, 400);
                outpatientGradient.addColorStop(0, 'rgba(15, 54, 159, 0.8)');
                outpatientGradient.addColorStop(1, 'rgba(15, 54, 159, 0.2)');
                
                let hemodialysisGradient = ctx.createLinearGradient(0, 0, 0, 400);
                hemodialysisGradient.addColorStop(0, 'rgba(120, 182, 35, 0.8)');
                hemodialysisGradient.addColorStop(1, 'rgba(120, 182, 35, 0.2)');

                // Create new chart
                patientChart = new Chart(ctx, {
                    type: 'bar',
                    data: {
                        labels: labels,
                        datasets: [
                            {
                                label: 'Inpatient',
                                backgroundColor: inpatientGradient,
                                borderColor: 'rgba(197, 16, 20, 1)',
                                borderWidth: 1,
                                borderRadius: 6,
                                borderSkipped: false,
                                data: [], // Will be filled via AJAX
                                hidden: false
                            },
                            {
                                label: 'Outpatient',
                                backgroundColor: outpatientGradient,
                                borderColor: 'rgba(15, 54, 159, 1)',
                                borderWidth: 1,
                                borderRadius: 6,
                                borderSkipped: false,
                                data: [], // Will be filled via AJAX
                                hidden: false
                            },
                            {
                                label: 'Hemodialysis',
                                backgroundColor: hemodialysisGradient,
                                borderColor: 'rgba(120, 182, 35, 1)',
                                borderWidth: 1,
                                borderRadius: 6,
                                borderSkipped: false,
                                data: [], // Will be filled via AJAX
                                hidden: false
                            }
                        ]
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

                // Fetch data for all patient types
                Promise.all([
                    fetchPatientData(timeRange, 'Inpatient'),
                    fetchPatientData(timeRange, 'Outpatient'),
                    fetchPatientData(timeRange, 'Hemodialysis')
                ]).then(results => {
                    // Update chart data
                    patientChart.data.datasets[0].data = results[0];
                    patientChart.data.datasets[1].data = results[1];
                    patientChart.data.datasets[2].data = results[2];
                    patientChart.update();
                }).catch(error => {
                    console.error('Error fetching patient data:', error);
                });
            }

            // Function to initialize or update the total patient chart
            function initializeOrUpdateTotalPatientChart(timeRange = 'monthly') {
                const ctxTotalPatients = document.getElementById('totalPatientChart').getContext('2d');
                
                // Destroy previous chart if it exists
                if (totalPatientChart) {
                    totalPatientChart.destroy();
                }
                
                // Set labels based on time range
                let labels;
                if (timeRange === 'weekly') {
                    labels = getWeeklyLabels();
                } else if (timeRange === 'monthly') {
                    labels = getMonthlyLabels();
                } else { // yearly
                    labels = getYearlyLabels();
                }
                
                // Gradient for the line chart
                let lineGradient = ctxTotalPatients.createLinearGradient(0, 0, 0, 400);
                lineGradient.addColorStop(0, 'rgba(120, 182, 35, 0.8)');
                lineGradient.addColorStop(1, 'rgba(120, 182, 35, 0.1)');

                // Create new chart
                totalPatientChart = new Chart(ctxTotalPatients, {
                    type: 'line',
                    data: {
                        labels: labels,
                        datasets: [{
                            label: 'Total Patients',
                            data: [], // Will be filled via AJAX
                            backgroundColor: lineGradient,
                            borderColor: 'rgba(120, 182, 35, 1)',
                            borderWidth: 2,
                            tension: 0.3,
                            fill: true,
                            pointBackgroundColor: '#fff',
                            pointBorderColor: 'rgba(120, 182, 35, 1)',
                            pointBorderWidth: 2,
                            pointRadius: 5,
                            pointHoverRadius: 7
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

                // Fetch total patient data
                fetchPatientData(timeRange, 'All').then(data => {
                    console.log('Received data:', data);
                    // Update chart data
                    totalPatientChart.data.datasets[0].data = data;
                    totalPatientChart.update();
                    }).catch(error => {
                        console.error('Error fetching total patient data:', error);
                    });
            }

            // Initialize charts on page load
            $(document).ready(function() {
                initializeOrUpdatePatientChart();
                initializeOrUpdateTotalPatientChart();
                
                // Add event listeners for time range selectors
                $('#patientTimeRange').change(function() {
                    initializeOrUpdatePatientChart($(this).val());
                });
                
                $('#totalPatientTimeRange').change(function() {
                    initializeOrUpdateTotalPatientChart($(this).val());
                });
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