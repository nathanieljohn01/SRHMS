<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="description" content="Santa Rosa Community Hospital Management System">
    
    <!-- Security headers -->
    <meta http-equiv="Content-Security-Policy" content="default-src 'self' https:; script-src 'self' 'unsafe-inline' https:; style-src 'self' 'unsafe-inline' https:; img-src 'self' data: https:;">
    <meta http-equiv="X-Frame-Options" content="SAMEORIGIN">
    <meta http-equiv="X-Content-Type-Options" content="nosniff">
    
    <link rel="shortcut icon" type="image/x-icon" href="assets/img/srchlogo.png">
    <title>Santa Rosa Community HMS</title>

    <!-- Consolidated FontAwesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" integrity="sha512-DTOQO9RWCH3ppGqcWaEA1BIZOC6xxalwEsw9c2QQeAIftl+Vegovlnee1c9QX4TctnWMn13TZye+giMm8e2LwA==" crossorigin="anonymous" referrerpolicy="no-referrer" />

    <!-- Core styles with defer -->
    <link rel="stylesheet" href="assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/fontawesome.min.css">
    
    <!-- Third-party components -->
    <link rel="stylesheet" href="assets/css/select2.min.css">
    <link rel="stylesheet" href="assets/css/bootstrap-datetimepicker.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">

    <!-- Material Icons with preload -->
    <link rel="preload" as="style" href="https://fonts.googleapis.com/icon?family=Material+Icons" onload="this.onload=null;this.rel='stylesheet'">
</head>
   <body>
    <div class="main-wrapper">
        <div class="header">
            <div class="header-left">
                <a href="" class="logo">
                    <img src="assets/img/srchlogo.png" width="35" height="35" alt="SRCH Logo">
                    <span>Santa Rosa Community HMS</span>
                </a>
            </div>
            <a id="toggle_btn" href="javascript:void(0);" aria-label="Toggle Sidebar"><i class="fa fa-bars"></i></a>
            <a id="mobile_btn" class="mobile_btn float-left" href="#sidebar" aria-label="Mobile Menu"><i class="fa fa-bars"></i></a>
            <ul class="nav user-menu float-right">
                <li class="nav-item dropdown has-arrow">
                    <a href="#" class="dropdown-toggle nav-link user-link" data-toggle="dropdown">
                        <span><?php echo htmlspecialchars($_SESSION['name']); ?></span>
                    </a>
                    <div class="dropdown-menu custom-dropdown">
                        <a class="dropdown-item" href="logout.php">Logout</a>
                    </div>
                </li>
            </ul>
            <div class="dropdown mobile-user-menu float-right">
                <a href="#" class="dropdown-toggle" data-toggle="dropdown" aria-expanded="false" aria-haspopup="true"><i class="fa fa-ellipsis-v"></i></a>
                <div class="dropdown-menu dropdown-menu-right">
                    <a class="dropdown-item" href="logout.php">Logout</a>
                </div>
            </div>
        </div>
        <div class="sidebar" id="sidebar">
            <div class="sidebar-inner slimscroll">
                <div id="sidebar-menu" class="sidebar-menu">
                    <?php if ($_SESSION['role'] == 1) { ?>
                        <ul>
                            <li class="<?php echo (basename($_SERVER['PHP_SELF']) == 'dashboard.php') ? 'active' : ''; ?>">
                                <a href="dashboard.php"><i class="fa fa-dashboard"></i> <span>Dashboard</span></a>
                            </li>
                            <li class="<?php echo (basename($_SERVER['PHP_SELF']) == 'employees.php') ? 'active' : ''; ?>">
                                <a href="employees.php"><i class="fa-solid fa-users"></i> <span>Employees</span></a>
                            </li>
                            <li class="sidenav <?php echo (basename($_SERVER['PHP_SELF']) == 'patients.php' || basename($_SERVER['PHP_SELF']) == 'schedule.php') ? 'active' : ''; ?>">
   <li class="sidenav <?php echo (basename($_SERVER['PHP_SELF']) == 'patients.php' || basename($_SERVER['PHP_SELF']) == 'schedule.php') ? 'active' : ''; ?>">
    <a href="javascript:void(0);" data-toggle="collapse" aria-expanded="<?php echo (basename($_SERVER['PHP_SELF']) == 'patients.php' || basename($_SERVER['PHP_SELF']) == 'schedule.php') ? 'true' : 'false'; ?>" class="dropdown-toggle" aria-haspopup="true">
        <i class="fa-solid fa-laptop-medical"></i> <span>Triage</span>
    </a>
    <ul class="collapse list-unstyled <?php echo (basename($_SERVER['PHP_SELF']) == 'patients.php' || basename($_SERVER['PHP_SELF']) == 'schedule.php') ? 'show' : ''; ?>" id="triageSubmenu">
        <li class="<?php echo (basename($_SERVER['PHP_SELF']) == 'patients.php') ? 'active' : ''; ?>">
            <a href="patients.php"><i class="fa-solid fa-wheelchair"></i> <span>Patient Registration</span></a>
        </li>
        <li class="<?php echo (basename($_SERVER['PHP_SELF']) == 'schedule.php') ? 'active' : ''; ?>">
            <a href="schedule.php"><i class="fa-solid fa-calendar"></i> <span>Doctors Schedule</span></a>
        </li>
    </ul>
</li>
                            <li class="sidenav <?php echo (basename($_SERVER['PHP_SELF']) == 'outpatients.php' || basename($_SERVER['PHP_SELF']) == 'inpatients.php' || basename($_SERVER['PHP_SELF']) == 'hemodialysis.php') ? 'active' : ''; ?>">
                                <a href="javascript:void(0);" data-toggle="collapse" aria-expanded="<?php echo (basename($_SERVER['PHP_SELF']) == 'outpatients.php' || basename($_SERVER['PHP_SELF']) == 'inpatients.php' || basename($_SERVER['PHP_SELF']) == 'hemodialysis.php') ? 'true' : 'false'; ?>" class="dropdown-toggle" aria-haspopup="true"> 
                                    <i class="fa fa-user"></i> <span>Patients</span>
                                </a>
                                <ul class="collapse list-unstyled <?php echo (basename($_SERVER['PHP_SELF']) == 'outpatients.php' || basename($_SERVER['PHP_SELF']) == 'inpatients.php' || basename($_SERVER['PHP_SELF']) == 'hemodialysis.php') ? 'show' : ''; ?>" id="patientsSubmenu">
                                    <li class="<?php echo (basename($_SERVER['PHP_SELF']) == 'outpatients.php') ? 'active' : ''; ?>">
                                        <a href="outpatients.php"><i class="fa-solid fa-user"></i> <span>Outpatient Section</span></a>
                                    </li>
                                    <li class="<?php echo (basename($_SERVER['PHP_SELF']) == 'inpatients.php') ? 'active' : ''; ?>">
                                        <a href="inpatients.php"><i class="fa-solid fa-user"></i> <span>Inpatient Section</span></a>
                                    </li>
                                    <li class="<?php echo (basename($_SERVER['PHP_SELF']) == 'hemodialysis.php') ? 'active' : ''; ?>">
                                        <a href="hemodialysis.php"><i class="fa-solid fa-user"></i> <span>Hemodialysis Section</span></a>
                                    </li>
                                </ul>
                            </li>
                            <li class="sidenav <?php echo (basename($_SERVER['PHP_SELF']) == 'bedallotment.php' || basename($_SERVER['PHP_SELF']) == 'housekeeping-schedule.php') ? 'active' : ''; ?>">
                                <a href="javascript:void(0);" data-toggle="collapse" aria-expanded="<?php echo (basename($_SERVER['PHP_SELF']) == 'bedallotment.php' || basename($_SERVER['PHP_SELF']) == 'housekeeping-schedule.php') ? 'true' : 'false'; ?>" class="dropdown-toggle" aria-haspopup="true">
                                    <i class="fa-solid fa-bed"></i> <span>Bed Management</span>
                                </a>
                                <ul class="collapse list-unstyled <?php echo (basename($_SERVER['PHP_SELF']) == 'bedallotment.php' || basename($_SERVER['PHP_SELF']) == 'housekeeping-schedule.php') ? 'show' : ''; ?>" id="bedmanagementSubmenu">
                                    <li class="<?php echo (basename($_SERVER['PHP_SELF']) == 'bedallotment.php') ? 'active' : ''; ?>">
                                        <a href="bedallotment.php"><i class="fa-solid fa-bed"></i> <span>Bed Allotment</span></a>
                                    </li>
                                    <li class="<?php echo (basename($_SERVER['PHP_SELF']) == 'housekeeping-schedule.php') ? 'active' : ''; ?>">
                                        <a href="housekeeping-schedule.php"><i class="fa-solid fa-calendar"></i> <span>Housekeeping Schedule</span></a>
                                    </li>
                                </ul>
                            </li>
                            <li class="sidenav <?php echo (basename($_SERVER['PHP_SELF']) == 'visitor-pass.php' || basename($_SERVER['PHP_SELF']) == 'inpatient-record.php' || basename($_SERVER['PHP_SELF']) == 'bed-transfer.php') ? 'active' : ''; ?>">
                                <a href="javascript:void(0);" data-toggle="collapse" aria-expanded="<?php echo (basename($_SERVER['PHP_SELF']) == 'visitor-pass.php' || basename($_SERVER['PHP_SELF']) == 'inpatient-record.php' || basename($_SERVER['PHP_SELF']) == 'bed-transfer.php') ? 'true' : 'false'; ?>" class="dropdown-toggle" aria-haspopup="true">
                                    <i class="fa-solid fa-clipboard-user"></i> <span>Ward Management</span>
                                </a>
                                <ul class="collapse list-unstyled <?php echo (basename($_SERVER['PHP_SELF']) == 'visitor-pass.php' || basename($_SERVER['PHP_SELF']) == 'inpatient-record.php' || basename($_SERVER['PHP_SELF']) == 'bed-transfer.php') ? 'show' : ''; ?>" id="wardmanagementSubmenu">
                                    <li class="<?php echo (basename($_SERVER['PHP_SELF']) == 'visitor-pass.php') ? 'active' : ''; ?>">
                                        <a href="visitor-pass.php"><i class="fa-solid fa-user"></i> <span>Visitor Pass</span></a>
                                    </li>
                                    <li class="<?php echo (basename($_SERVER['PHP_SELF']) == 'inpatient-record.php') ? 'active' : ''; ?>">
                                        <a href="inpatient-record.php"><i class="fa-solid fa-id-card-clip"></i> <span>Inpatient Record</span></a>
                                    </li>
                                    <li class="<?php echo (basename($_SERVER['PHP_SELF']) == 'bed-transfer.php') ? 'active' : ''; ?>">
                                        <a href="bed-transfer.php"><i class="fa-solid fa-bed"></i> <span>Bed Transfer</span></a>
                                    </li>
                                </ul>
                            </li>
                            <li class="sidenav <?php echo (basename($_SERVER['PHP_SELF']) == 'medicines.php' || basename($_SERVER['PHP_SELF']) == 'pharmacy-invoice.php') ? 'active' : ''; ?>">
                                <a href="javascript:void(0);" data-toggle="collapse" aria-expanded="<?php echo (basename($_SERVER['PHP_SELF']) == 'medicines.php' || basename($_SERVER['PHP_SELF']) == 'pharmacy-invoice.php') ? 'true' : 'false'; ?>" class="dropdown-toggle" aria-haspopup="true">
                                    <i class="fa-solid fa-prescription"></i> <span>Pharmacy</span>
                                </a>
                                <ul class="collapse list-unstyled <?php echo (basename($_SERVER['PHP_SELF']) == 'medicines.php' || basename($_SERVER['PHP_SELF']) == 'pharmacy-invoice.php') ? 'show' : ''; ?>" id="pharmacySubmenu">
                                    <li class="<?php echo (basename($_SERVER['PHP_SELF']) == 'medicines.php') ? 'active' : ''; ?>">
                                        <a href="medicines.php"><i class="fa-solid fa-pills"></i> <span>Drugs and Medication</span></a>
                                    </li>
                                    <li class="<?php echo (basename($_SERVER['PHP_SELF']) == 'pharmacy-invoice.php') ? 'active' : ''; ?>">
                                        <a href="pharmacy-invoice.php"><i class="fa-solid fa-receipt"></i> <span>Pharmacy Invoice</span></a>
                                    </li>
                                </ul>
                            </li>
                            <li class="sidenav <?php echo (basename($_SERVER['PHP_SELF']) == 'newborn.php' || basename($_SERVER['PHP_SELF']) == 'deceased.php') ? 'active' : ''; ?>">
                                <a href="javascript:void(0);" data-toggle="collapse" aria-expanded="<?php echo (basename($_SERVER['PHP_SELF']) == 'newborn.php' || basename($_SERVER['PHP_SELF']) == 'deceased.php') ? 'true' : 'false'; ?>" class="dropdown-toggle" aria-haspopup="true">
                                    <i class="fa-solid fa-hospital-user"></i> <span>Patient Care</span>
                                </a>
                                <ul class="collapse list-unstyled <?php echo (basename($_SERVER['PHP_SELF']) == 'newborn.php' || basename($_SERVER['PHP_SELF']) == 'deceased.php') ? 'show' : ''; ?>" id="patientcareSubmenu">
                                    <li class="<?php echo (basename($_SERVER['PHP_SELF']) == 'newborn.php') ? 'active' : ''; ?>">
                                        <a href="newborn.php"><i class="fa-solid fa-baby"></i> <span>Newborn</span></a>
                                    </li>
                                    <li class="<?php echo (basename($_SERVER['PHP_SELF']) == 'deceased.php') ? 'active' : ''; ?>">
                                        <a href="deceased.php"><i class="fa-solid fa-skull"></i> <span>Deceased</span></a>
                                    </li>
                                </ul>
                            </li>
                            <li class="sidenav <?php echo (basename($_SERVER['PHP_SELF']) == 'lab-tests.php' || basename($_SERVER['PHP_SELF']) == 'lab-order-patients.php' || basename($_SERVER['PHP_SELF']) == 'lab-result.php') ? 'active' : ''; ?>">
                                <a href="javascript:void(0);" data-toggle="collapse" aria-expanded="<?php echo (basename($_SERVER['PHP_SELF']) == 'lab-tests.php' || basename($_SERVER['PHP_SELF']) == 'lab-order-patients.php' || basename($_SERVER['PHP_SELF']) == 'lab-result.php') ? 'true' : 'false'; ?>" class="dropdown-toggle" aria-haspopup="true"> 
                                    <i class="fa-solid fa-syringe"></i><span>Laboratory<br>Information</span>
                                </a>
                                <ul class="collapse list-unstyled <?php echo (basename($_SERVER['PHP_SELF']) == 'lab-tests.php' || basename($_SERVER['PHP_SELF']) == 'lab-order-patients.php' || basename($_SERVER['PHP_SELF']) == 'lab-result.php') ? 'show' : ''; ?>" id="laboratorySubmenu">
                                    <li class="<?php echo (basename($_SERVER['PHP_SELF']) == 'lab-tests.php') ? 'active' : ''; ?>">
                                        <a href="lab-tests.php"> <i class="fa-solid fa-vials"></i> <span>Lab Tests</span></a>
                                    </li>
                                   <li class="<?php echo (basename($_SERVER['PHP_SELF']) == 'lab-order-patients.php') ? 'active' : ''; ?>">
                                    <a href="lab-order-patients.php"><i class="fa-solid fa-notes-medical"></i> <span>Lab Orders</span></a>
                                </li>
                                <li class="<?php echo (basename($_SERVER['PHP_SELF']) == 'lab-result.php') ? 'active' : ''; ?>">
                                    <a href="lab-result.php"><i class="fa-solid fa-file-medical"></i> <span>Lab Results</span></a>
                                </li>
                                </ul>
                            </li>
                            <li class="<?php echo (basename($_SERVER['PHP_SELF']) == 'radiology.php') ? 'active' : ''; ?>">
                                <a href="radiology-patients.php"><i class="fa-solid fa-radiation"></i> <span>Radiology Information</span></a>
                            </li>
                            <li class="sidenav">
                                <a href="operating-room.php"><i class="fa fa-exclamation-triangle"></i> <span>Operating Room</span></a>
                            </li>
                            <li class="<?php echo (basename($_SERVER['PHP_SELF']) == 'billing.php') ? 'active' : ''; ?>">
                                <a href="billing.php"><i class="fas fa-file-invoice-dollar"></i> <span>Billing</span></a>
                            </li>
                            <li class="sidenav <?php echo (basename($_SERVER['PHP_SELF']) == 'payment-processing.php' || basename($_SERVER['PHP_SELF']) == 'patient-ledger.php') ? 'active' : ''; ?>">
                                <a href="javascript:void(0);" data-toggle="collapse" aria-expanded="<?php echo (basename($_SERVER['PHP_SELF']) == 'payment-processing.php' || basename($_SERVER['PHP_SELF']) == 'patient-ledger.php') ? 'true' : 'false'; ?>" class="dropdown-toggle" aria-haspopup="true">
                                    <i class="fa-solid fa-cash-register"></i><span>Cashier</span>
                                </a>
                                <ul class="collapse list-unstyled <?php echo (basename($_SERVER['PHP_SELF']) == 'payment-processing.php' || basename($_SERVER['PHP_SELF']) == 'patient-ledger.php') ? 'show' : ''; ?>" id="cashierSubmenu">
                                    <li class="<?php echo (basename($_SERVER['PHP_SELF']) == 'payment-processing.php') ? 'active' : ''; ?>">
                                        <a href="payment-processing.php"> <i class="fa-solid fa-credit-card"></i> <span>Payment Processing</span></a>
                                    </li>
                                    <li class="<?php echo (basename($_SERVER['PHP_SELF']) == 'patient-ledger.php') ? 'active' : ''; ?>">
                                        <a href="patient-ledger.php"> <i class="fa-solid fa-file-invoice-dollar"></i> <span>Patient Ledger</span></a>
                                    </li>
                                </ul>
                            </li>
                            <li class="<?php echo (basename($_SERVER['PHP_SELF']) == 'ehr.php') ? 'active' : ''; ?>">
                                <a href="ehr.php">
                                    <i class="fas fa-notes-medical"></i>
                                    <span>Electronic<br>Health Records</span>
                                </a>
                            </li>
                        </ul>
                    <?php } else if ($_SESSION['role'] == 2) { ?>
                        <ul>
                            <li class="<?php echo (basename($_SERVER['PHP_SELF']) == 'dashboard-doctor.php') ? 'active' : ''; ?>">
                                <a href="dashboard-doctor.php"><i class="fa fa-dashboard"></i> <span>Dashboard</span></a>
                            </li>
                            <li class="<?php echo (basename($_SERVER['PHP_SELF']) == 'schedule.php') ? 'active' : ''; ?>">
                                <a href="schedule.php"><i class="fa fa-calendar"></i> <span>Doctors Schedule</span></a>
                            </li>
                            <li class="sidenav <?php echo (basename($_SERVER['PHP_SELF']) == 'outpatients.php' || basename($_SERVER['PHP_SELF']) == 'inpatients.php' || basename($_SERVER['PHP_SELF']) == 'hemodialysis.php') ? 'active' : ''; ?>">
                                <a href="javascript:void(0);" data-toggle="collapse" aria-expanded="<?php echo (basename($_SERVER['PHP_SELF']) == 'outpatients.php' || basename($_SERVER['PHP_SELF']) == 'inpatients.php' || basename($_SERVER['PHP_SELF']) == 'hemodialysis.php') ? 'true' : 'false'; ?>" class="dropdown-toggle"><i class="fa fa-wheelchair"></i> <span>Patients</span></a>
                                <ul class="collapse list-unstyled <?php echo (basename($_SERVER['PHP_SELF']) == 'outpatients.php' || basename($_SERVER['PHP_SELF']) == 'inpatients.php' || basename($_SERVER['PHP_SELF']) == 'hemodialysis.php') ? 'show' : ''; ?>" id="patientsSubmenu">
                                    <li class="<?php echo (basename($_SERVER['PHP_SELF']) == 'outpatients.php') ? 'active' : ''; ?>">
                                        <a href="outpatients.php"><i class="fa fa-wheelchair"></i> <span>Outpatient Section</span></a>
                                    </li>
                                    <li class="<?php echo (basename($_SERVER['PHP_SELF']) == 'inpatients.php') ? 'active' : ''; ?>">
                                        <a href="inpatients.php"><i class="fa fa-wheelchair"></i> <span>Inpatient Section</span></a>
                                    </li>
                                    <li class="<?php echo (basename($_SERVER['PHP_SELF']) == 'hemodialysis.php') ? 'active' : ''; ?>">
                                        <a href="hemodialysis.php"><i class="fa fa-wheelchair"></i> <span>Hemodialysis Section</span></a>
                                    </li>
                                </ul>
                            </li>
                            <li class="sidenav <?php echo (basename($_SERVER['PHP_SELF']) == 'inpatient-record.php') ? 'active' : ''; ?>">
                                <a href="javascript:void(0);" data-toggle="collapse" aria-expanded="<?php echo (basename($_SERVER['PHP_SELF']) == 'inpatient-record.php') ? 'true' : 'false'; ?>" class="dropdown-toggle" aria-haspopup="true">
                                    <i class="fa-solid fa-clipboard-user"></i> <span>Ward Management</span>
                                </a>
                                <ul class="collapse list-unstyled <?php echo (basename($_SERVER['PHP_SELF']) == 'inpatient-record.php') ? 'show' : ''; ?>" id="wardmanagementSubmenu">
                                    <li class="<?php echo (basename($_SERVER['PHP_SELF']) == 'inpatient-record.php') ? 'active' : ''; ?>">
                                        <a href="inpatient-record.php"><i class="fa-solid fa-id-card-clip"></i> <span>Inpatient Record</span></a>
                                    </li>
                                </ul>
                            </li>
                            <li class="sidenav <?php echo (basename($_SERVER['PHP_SELF']) == 'newborn.php') ? 'active' : ''; ?>">
                                <a href="javascript:void(0);" data-toggle="collapse" aria-expanded="<?php echo (basename($_SERVER['PHP_SELF']) == 'newborn.php') ? 'true' : 'false'; ?>" class="dropdown-toggle" aria-haspopup="true">
                                    <i class="fa-solid fa-hospital-user"></i> <span>Patient Care</span>
                                </a>
                                <ul class="collapse list-unstyled <?php echo (basename($_SERVER['PHP_SELF']) == 'newborn.php') ? 'show' : ''; ?>" id="patientcareSubmenu">
                                    <li class="<?php echo (basename($_SERVER['PHP_SELF']) == 'newborn.php') ? 'active' : ''; ?>">
                                        <a href="newborn.php"><i class="fa-solid fa-baby"></i> <span>Newborn</span></a>
                                    </li>
                                </ul>
                            </li>
                        </ul>
                    <?php } else if ($_SESSION['role'] == 3) { ?>
                        <ul>
                            <li class="<?php echo (basename($_SERVER['PHP_SELF']) == 'dashboard-nurse.php') ? 'active' : ''; ?>">
                                <a href="dashboard-nurse.php"><i class="fa fa-dashboard"></i> <span>Dashboard</span></a>
                            </li>
                            <li class="sidenav <?php echo (basename($_SERVER['PHP_SELF']) == 'patients.php' || basename($_SERVER['PHP_SELF']) == 'schedule.php') ? 'active' : ''; ?>">
                                <a href="javascript:void(0);" data-toggle="collapse" aria-expanded="<?php echo (basename($_SERVER['PHP_SELF']) == 'patients.php' || basename($_SERVER['PHP_SELF']) == 'schedule.php') ? 'true' : 'false'; ?>" class="dropdown-toggle" aria-haspopup="true">
                                    <i class="fa-solid fa-laptop-medical"></i> <span>Triage</span>
                                </a>
                                <ul class="collapse list-unstyled <?php echo (basename($_SERVER['PHP_SELF']) == 'patients.php' || basename($_SERVER['PHP_SELF']) == 'schedule.php') ? 'show' : ''; ?>" id="triageSubmenu">
                                    <li class="<?php echo (basename($_SERVER['PHP_SELF']) == 'patients.php') ? 'active' : ''; ?>">
                                        <a href="patients.php"><i class="fa-solid fa-wheelchair"></i> <span>Patient Registration</span></a>
                                    </li>
                                    <li class="<?php echo (basename($_SERVER['PHP_SELF']) == 'schedule.php') ? 'active' : ''; ?>">
                                        <a href="schedule.php"><i class="fa-solid fa-calendar"></i> <span>Doctors Schedule</span></a>
                                    </li>
                                </ul>
                            </li>
                            <li class="sidenav <?php echo (basename($_SERVER['PHP_SELF']) == 'outpatients.php' || basename($_SERVER['PHP_SELF']) == 'inpatients.php' || basename($_SERVER['PHP_SELF']) == 'hemodialysis.php') ? 'active' : ''; ?>">
                                <a href="javascript:void(0);" data-toggle="collapse" aria-expanded="<?php echo (basename($_SERVER['PHP_SELF']) == 'outpatients.php' || basename($_SERVER['PHP_SELF']) == 'inpatients.php' || basename($_SERVER['PHP_SELF']) == 'hemodialysis.php') ? 'true' : 'false'; ?>" class="dropdown-toggle" aria-haspopup="true"> 
                                    <i class="fa fa-user"></i> <span>Patients</span>
                                </a>
                                <ul class="collapse list-unstyled <?php echo (basename($_SERVER['PHP_SELF']) == 'outpatients.php' || basename($_SERVER['PHP_SELF']) == 'inpatients.php' || basename($_SERVER['PHP_SELF']) == 'hemodialysis.php') ? 'show' : ''; ?>" id="patientsSubmenu">
                                    <li class="<?php echo (basename($_SERVER['PHP_SELF']) == 'outpatients.php') ? 'active' : ''; ?>">
                                        <a href="outpatients.php"><i class="fa-solid fa-user"></i> <span>Outpatient Section</span></a>
                                    </li>
                                    <li class="<?php echo (basename($_SERVER['PHP_SELF']) == 'inpatients.php') ? 'active' : ''; ?>">
                                        <a href="inpatients.php"><i class="fa-solid fa-user"></i> <span>Inpatient Section</span></a>
                                    </li>
                                    <li class="<?php echo (basename($_SERVER['PHP_SELF']) == 'hemodialysis.php') ? 'active' : ''; ?>">
                                        <a href="hemodialysis.php"><i class="fa-solid fa-user"></i> <span>Hemodialysis Section</span></a>
                                    </li>
                                </ul>
                            </li>
                        </ul>
                    <?php } else if ($_SESSION['role'] == 9) { ?>
                        <ul>
                            <li class="<?php echo (basename($_SERVER['PHP_SELF']) == 'dashboard-nurse.php') ? 'active' : ''; ?>">
                                <a href="dashboard-nurse.php"><i class="fa fa-dashboard"></i> <span>Dashboard</span></a>
                            </li>
                            <li class="sidenav <?php echo (basename($_SERVER['PHP_SELF']) == 'bedallotment.php' || basename($_SERVER['PHP_SELF']) == 'housekeeping-schedule.php') ? 'active' : ''; ?>">
                                <a href="javascript:void(0);" data-toggle="collapse" aria-expanded="<?php echo (basename($_SERVER['PHP_SELF']) == 'bedallotment.php' || basename($_SERVER['PHP_SELF']) == 'housekeeping-schedule.php') ? 'true' : 'false'; ?>" class="dropdown-toggle" aria-haspopup="true">
                                    <i class="fa-solid fa-bed"></i> <span>Bed Management</span>
                                </a>
                                <ul class="collapse list-unstyled <?php echo (basename($_SERVER['PHP_SELF']) == 'bedallotment.php' || basename($_SERVER['PHP_SELF']) == 'housekeeping-schedule.php') ? 'show' : ''; ?>" id="bedmanagementSubmenu">
                                    <li class="<?php echo (basename($_SERVER['PHP_SELF']) == 'bedallotment.php') ? 'active' : ''; ?>">
                                        <a href="bedallotment.php"><i class="fa-solid fa-bed"></i> <span>Bed Allotment</span></a>
                                    </li>
                                    <li class="<?php echo (basename($_SERVER['PHP_SELF']) == 'housekeeping-schedule.php') ? 'active' : ''; ?>">
                                        <a href="housekeeping-schedule.php"><i class="fa-solid fa-calendar"></i> <span>Housekeeping Schedule</span></a>
                                    </li>
                                </ul>
                            </li>
                            <li class="sidenav <?php echo (basename($_SERVER['PHP_SELF']) == 'visitor-pass.php' || basename($_SERVER['PHP_SELF']) == 'inpatient-record.php' || basename($_SERVER['PHP_SELF']) == 'bed-transfer.php') ? 'active' : ''; ?>">
                                <a href="javascript:void(0);" data-toggle="collapse" aria-expanded="<?php echo (basename($_SERVER['PHP_SELF']) == 'visitor-pass.php' || basename($_SERVER['PHP_SELF']) == 'inpatient-record.php' || basename($_SERVER['PHP_SELF']) == 'bed-transfer.php') ? 'true' : 'false'; ?>" class="dropdown-toggle" aria-haspopup="true">
                                    <i class="fa-solid fa-clipboard-user"></i> <span>Ward Management</span>
                                </a>
                                <ul class="collapse list-unstyled <?php echo (basename($_SERVER['PHP_SELF']) == 'visitor-pass.php' || basename($_SERVER['PHP_SELF']) == 'inpatient-record.php' || basename($_SERVER['PHP_SELF']) == 'bed-transfer.php') ? 'show' : ''; ?>" id="wardmanagementSubmenu">
                                    <li class="<?php echo (basename($_SERVER['PHP_SELF']) == 'visitor-pass.php') ? 'active' : ''; ?>">
                                        <a href="visitor-pass.php"><i class="fa-solid fa-user"></i> <span>Visitor Pass</span></a>
                                    </li>
                                    <li class="<?php echo (basename($_SERVER['PHP_SELF']) == 'inpatient-record.php') ? 'active' : ''; ?>">
                                        <a href="inpatient-record.php"><i class="fa-solid fa-id-card-clip"></i> <span>Inpatient Record</span></a>
                                    </li>
                                    <li class="<?php echo (basename($_SERVER['PHP_SELF']) == 'bed-transfer.php') ? 'active' : ''; ?>">
                                        <a href="bed-transfer.php"><i class="fa-solid fa-bed"></i> <span>Bed Transfer</span></a>
                                    </li>
                                </ul>
                            </li>
                            <li class="sidenav <?php echo (basename($_SERVER['PHP_SELF']) == 'newborn.php' || basename($_SERVER['PHP_SELF']) == 'deceased.php') ? 'active' : ''; ?>">
                                <a href="javascript:void(0);" data-toggle="collapse" aria-expanded="<?php echo (basename($_SERVER['PHP_SELF']) == 'newborn.php' || basename($_SERVER['PHP_SELF']) == 'deceased.php') ? 'true' : 'false'; ?>" class="dropdown-toggle" aria-haspopup="true">
                                    <i class="fa-solid fa-hospital-user"></i> <span>Patient Care</span>
                                </a>
                                <ul class="collapse list-unstyled <?php echo (basename($_SERVER['PHP_SELF']) == 'newborn.php' || basename($_SERVER['PHP_SELF']) == 'deceased.php') ? 'show' : ''; ?>" id="patientcareSubmenu">
                                    <li class="<?php echo (basename($_SERVER['PHP_SELF']) == 'newborn.php') ? 'active' : ''; ?>">
                                        <a href="newborn.php"><i class="fa-solid fa-baby"></i> <span>Newborn</span></a>
                                    </li>
                                    <li class="<?php echo (basename($_SERVER['PHP_SELF']) == 'deceased.php') ? 'active' : ''; ?>">
                                        <a href="deceased.php"><i class="fa-solid fa-skull"></i> <span>Deceased</span></a>
                                    </li>
                                </ul>
                            </li>
                        </ul>
                    <?php } else if ($_SESSION['role'] == 4) { ?>
                        <ul>
                            <li class="<?php echo (basename($_SERVER['PHP_SELF']) == 'dashboard-pharmacy.php') ? 'active' : ''; ?>">
                                <a href="dashboard-pharmacy.php"><i class="fa fa-dashboard"></i> <span>Dashboard</span></a>
                            </li>
                            <li class="sidenav <?php echo (basename($_SERVER['PHP_SELF']) == 'medicines.php' || basename($_SERVER['PHP_SELF']) == 'pharmacy-invoice.php') ? 'active' : ''; ?>">
                                <a href="javascript:void(0);" data-toggle="collapse" aria-expanded="<?php echo (basename($_SERVER['PHP_SELF']) == 'medicines.php' || basename($_SERVER['PHP_SELF']) == 'pharmacy-invoice.php') ? 'true' : 'false'; ?>" class="dropdown-toggle" aria-haspopup="true">
                                    <i class="fa-solid fa-prescription"></i> <span>Pharmacy</span>
                                </a>
                                <ul class="collapse list-unstyled <?php echo (basename($_SERVER['PHP_SELF']) == 'medicines.php' || basename($_SERVER['PHP_SELF']) == 'pharmacy-invoice.php') ? 'show' : ''; ?>" id="pharmacySubmenu">
                                    <li class="<?php echo (basename($_SERVER['PHP_SELF']) == 'medicines.php') ? 'active' : ''; ?>">
                                        <a href="medicines.php"><i class="fa fa-pills"></i> <span>Drugs and Medication</span></a>
                                    </li>
                                    <li class="<?php echo (basename($_SERVER['PHP_SELF']) == 'pharmacy-invoice.php') ? 'active' : ''; ?>">
                                        <a href="pharmacy-invoice.php"><i class="fa-solid fa-receipt"></i> <span>Pharmacy Invoice</span></a>
                                    </li>
                                </ul>
                            </li>
                        </ul>
                    <?php } else if ($_SESSION['role'] == 5) { ?>
                        <ul>
                            <li class="<?php echo (basename($_SERVER['PHP_SELF']) == 'dashboard-lab.php') ? 'active' : ''; ?>">
                                <a href="dashboard-lab.php"><i class="fa fa-dashboard"></i> <span>Dashboard</span></a>
                            </li>
                            <li class="sidenav <?php echo (basename($_SERVER['PHP_SELF']) == 'lab-tests.php' || basename($_SERVER['PHP_SELF']) == 'lab-order-patients.php' || basename($_SERVER['PHP_SELF']) == 'lab-result.php') ? 'active' : ''; ?>">
                                <a href="javascript:void(0);" data-toggle="collapse" aria-expanded="<?php echo (basename($_SERVER['PHP_SELF']) == 'lab-tests.php' || basename($_SERVER['PHP_SELF']) == 'lab-order-patients.php' || basename($_SERVER['PHP_SELF']) == 'lab-result.php') ? 'true' : 'false'; ?>" class="dropdown-toggle" aria-haspopup="true"> 
                                    <i class="fa-solid fa-syringe"></i><span>Laboratory<br>Information</span>
                                </a>
                                <ul class="collapse list-unstyled <?php echo (basename($_SERVER['PHP_SELF']) == 'lab-tests.php' || basename($_SERVER['PHP_SELF']) == 'lab-order-patients.php' || basename($_SERVER['PHP_SELF']) == 'lab-result.php') ? 'show' : ''; ?>" id="laboratorySubmenu">
                                    <li class="<?php echo (basename($_SERVER['PHP_SELF']) == 'lab-tests.php') ? 'active' : ''; ?>">
                                        <a href="lab-tests.php"> <i class="fa-solid fa-vials"></i> <span>Lab Tests</span></a>
                                    </li>
                                    <li class="<?php echo (basename($_SERVER['PHP_SELF']) == 'lab-order-patients.php') ? 'active' : ''; ?>">
                                        <a href="lab-order-patients.php"> <i class="fa-solid fa-microscope"></i> <span>Lab Order</span></a>
                                    </li>
                                    <li class="<?php echo (basename($_SERVER['PHP_SELF']) == 'lab-result.php') ? 'active' : ''; ?>">
                                        <a href="lab-result.php"> <i class="fa-solid fa-clipboard-check"></i> <span>Lab Result</span></a>
                                    </li>
                                </ul>
                            </li>
                        </ul>
                    <?php } else if ($_SESSION['role'] == 6) { ?>
                        <ul>
                            <li class="<?php echo (basename($_SERVER['PHP_SELF']) == 'dashboard-rad.php') ? 'active' : ''; ?>">
                                <a href="dashboard-rad.php"><i class="fa fa-dashboard"></i> <span>Dashboard</span></a>
                            </li>
                            <li class="<?php echo (basename($_SERVER['PHP_SELF']) == 'radiology-patients.php') ? 'active' : ''; ?>">
                                <a href="radiology-patients.php"><i class="fa-solid fa-radiation"></i> <span>Radiology Information</span></a>
                            </li>
                        </ul>
                    <?php } else if ($_SESSION['role'] == 7) { ?>
                        <ul>
                            <li class="<?php echo (basename($_SERVER['PHP_SELF']) == 'dashboard-billing.php') ? 'active' : ''; ?>">
                                <a href="dashboard-billing.php"><i class="fa fa-dashboard"></i> <span>Dashboard</span></a>
                            </li>
                            <li class="<?php echo (basename($_SERVER['PHP_SELF']) == 'billing.php') ? 'active' : ''; ?>">
                                <a href="billing.php"><i class="fa-solid fa-money-bill-wave"></i> <span>Billing</span></a>
                            </li>
                        </ul>
                    <?php } else if ($_SESSION['role'] == 8) { ?>
                        <ul>
                            <li class="active">
                                <a href="dashboard-cashier.php"><i class="fa fa-dashboard"></i> <span>Dashboard</span></a>
                            </li>
                            <li class="sidenav <?php echo (basename($_SERVER['PHP_SELF']) == 'payment-processing.php' || basename($_SERVER['PHP_SELF']) == 'patient-ledger.php') ? 'active' : ''; ?>">
                                <a href="javascript:void(0);" data-toggle="collapse" aria-expanded="<?php echo (basename($_SERVER['PHP_SELF']) == 'payment-processing.php' || basename($_SERVER['PHP_SELF']) == 'patient-ledger.php') ? 'true' : 'false'; ?>" class="dropdown-toggle" aria-haspopup="true">
                                    <i class="fa-solid fa-cash-register"></i><span>Cashier</span>
                                </a>
                                <ul class="collapse list-unstyled <?php echo (basename($_SERVER['PHP_SELF']) == 'payment-processing.php' || basename($_SERVER['PHP_SELF']) == 'patient-ledger.php') ? 'show' : ''; ?>" id="cashierSubmenu">
                                    <li class="<?php echo (basename($_SERVER['PHP_SELF']) == 'payment-processing.php') ? 'active' : ''; ?>">
                                        <a href="payment-processing.php"> <i class="fa-solid fa-credit-card"></i> <span>Payment Processing</span></a>
                                    </li>
                                    <li class="<?php echo (basename($_SERVER['PHP_SELF']) == 'patient-ledger.php') ? 'active' : ''; ?>">
                                        <a href="patient-ledger.php"> <i class="fa-solid fa-file-invoice-dollar"></i> <span>Patient Ledger</span></a>
                                    </li>
                                </ul>
                            </li>
                        </ul>
                    <?php } else { ?>
                        <ul>
                            <li> 
                            </li>
                        </ul>
                    <?php } ?>
                </div>
            </div>
        </div>
    </div>
</body>
<script src="assets/js/jquery-3.6.0.min.js"></script>
<script src="assets/js/bootstrap.bundle.min.js"></script>
<script src="assets/js/select2.min.js"></script>
<script src="assets/js/moment.min.js"></script>
<script src="assets/js/bootstrap-datetimepicker.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>


<script>
// Improved sidebar toggle with smooth transitions
   document.addEventListener("DOMContentLoaded", function() {
    const sidebar = document.getElementById("sidebar");
    const mainWrapper = document.getElementById("main-wrapper");
    const mobileBtn = document.getElementById("mobile_btn");
    const pageWrapper = document.querySelector(".page-wrapper");

    // Cache DOM queries
    const dropdownToggles = Array.from(document.getElementsByClassName("dropdown-toggle"));

    // Use Intersection Observer for better performance
    const observer = new IntersectionObserver(entries => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.classList.add('visible');
            }
        });
    }, { threshold: 1.0 });

    // Smooth toggle with CSS transform             
    function toggleSidebar() {
        if (sidebar && mainWrapper) {
            requestAnimationFrame(() => {
                sidebar.style.transform = sidebar.classList.contains('active') ? 
                    'translateX(0)' : 'translateX(-100%)';
                sidebar.classList.toggle("active");
                mainWrapper.classList.toggle("active");
            });
        }
    }

    // Enhanced mobile menu handler
    function handleMobileMenu(e) {
        e.preventDefault();
        const isActive = sidebar.classList.contains('active');
        
        requestAnimationFrame(() => {
            sidebar.style.transition = 'transform 0.3s cubic-bezier(0.4, 0, 0.2, 1)';
            sidebar.classList.toggle("active");
            pageWrapper.classList.toggle("slide");
        });
    }

    // Optimized dropdown handling with animation frames
    dropdownToggles.forEach(toggle => {
        toggle.addEventListener("click", (e) => {
            e.stopPropagation();
            const menu = toggle.nextElementSibling;
            
            requestAnimationFrame(() => {
                toggle.classList.toggle("active");
                if (menu) {
                    menu.style.transition = 'transform 0.2s ease-in-out';
                    menu.classList.toggle("show");
                }
            });
        });
    });

    // Throttled resize handler
    const throttledResize = throttle(() => {
        if (window.innerWidth <= 768) {
            sidebar?.classList.remove("active");
            mainWrapper?.classList.remove("active");
        }
    }, 250);

    window.addEventListener("resize", throttledResize);

    if (mobileBtn) {
        mobileBtn.addEventListener("click", handleMobileMenu);
    }

    // Optimized click handler for dropdowns
    document.addEventListener("click", (e) => {
        const openMenus = document.querySelectorAll(".dropdown-menu.show");
        if (!openMenus.length) return;

        requestAnimationFrame(() => {
            openMenus.forEach(menu => {
                if (!menu.contains(e.target)) {
                    menu.classList.remove("show");
                    menu.previousElementSibling?.classList.remove("active");
                }
            });
        });
    });

    // Add event listener to sidebar menu items
    const menuItems = document.querySelectorAll('#sidebar .sidebar-menu a');
    menuItems.forEach(item => {
        item.addEventListener('click', () => {
            // Minimize the sidebar when a menu item is clicked
            toggleSidebar();
        });
    });
});

// Throttle function for better performance
function throttle(func, limit) {
    let inThrottle;
    return function(...args) {
        if (!inThrottle) {
            func.apply(this, args);
            inThrottle = true;
            setTimeout(() => inThrottle = false, limit);
        }
    };
}

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
/* Minimalist Dropdown *//* Dropdown Styling */
.custom-dropdown {
    background-color: #fff;
    border: 1px solid #e0e0e0;
    border-radius: 8px;
    padding: 6px 0;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
    min-width: 160px;
    opacity: 0;
    transform: translateY(10px);
    animation: dropdownFadeIn 0.3s ease-out forwards;
    transition: box-shadow 0.3s ease;
}

@keyframes dropdownFadeIn {
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.custom-dropdown .dropdown-item {
    padding: 10px 18px;
    color: #333;
    font-size: 14px;
    transition: background-color 0.2s ease, color 0.2s ease;
    cursor: pointer;
}

.custom-dropdown .dropdown-item:hover {
    background-color: #f0f4ff;
    color: #12369e;
    padding-left: 22px;
}

.custom-dropdown .dropdown-item:active {
    background-color: #e6ebff;
}

/* Minimalist Sidebar Menu */
.sidebar-menu a {
    display: block;
    padding: 14px 18px;
    color: #333;
    font-size: 15px;
    font-weight: 400;
    text-decoration: none;
    transition: all 0.3s ease;
    border-left: 3px solid transparent;
}

.sidebar-menu a:hover {
    background-color: #f2f2f2;
    color: #12369e;
    border-left: 3px solid #12369e;
}

.sidebar-menu .active > a,
.sidebar-menu .sidenav.active > a {
    background-color: #f2f2f2;
    color: #12369e;
    border-left: 3px solid #12369e;
}

.sidebar-menu .active > a:hover,
.sidebar-menu .sidenav.active > a:hover {
    background-color: transparent; /* Very light background on hover */
    color: #12369e;
    border-left: 3px solid #12369e;
}

/* Active child menu item */
.sidebar-menu .collapse.list-unstyled li.active > a {
    background-color: #f2f2f2;
    color: #12369e;
    border-left: 3px solid #12369e;
    transition: all 0.2s ease; /* Smooth transition */
}

/* Active item hover state */
.sidebar-menu .collapse.list-unstyled li.active > a:hover {
    background-color: #f9f9f9; /* Very light background on hover */
    color: #12369e;
}

/* Inactive child menu items */
.sidebar-menu .collapse.list-unstyled li:not(.active) > a {
    color: #888;
    background-color: transparent;
    border-left: 3px solid transparent;
    transition: all 0.2s ease; /* Smooth transition */
}

/* Inactive item hover state */
.sidebar-menu .collapse.list-unstyled li:not(.active) > a:hover {
    color: #12369e;
    background-color: #f9f9f9; /* Very light background on hover */
    border-left: 3px solid #12369e;
}

/* Parent menu item hover */
.sidebar-menu > li.sidenav > a:hover {
    background-color: transparent;
    color: #12369e;
}

/* Mobile adaptations */
@media (max-width: 768px) {
    .sidebar {
        transform: translateX(-100%);
        transition: transform 0.3s ease-in-out;
    }

    .sidebar.active {
        transform: translateX(0);
    }

    .page-wrapper {
        margin-left: 0;
    }

    .sidebar ~ .page-wrapper {
        margin-left: 0;
    }
}

</style>
</html>