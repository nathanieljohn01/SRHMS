<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="shortcut icon" type="image/x-icon" href="assets/img/srchlogo.png">
    <title>Santa Rosa Community HMS</title>
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <link rel="stylesheet" type="text/css" href="assets/css/bootstrap.min.css">
    <link href="https://fontawesome.com/icons/categories/medical-health" rel="stylesheet">
    <link rel="stylesheet" type="text/css" href="assets/css/font-awesome.min.css">
    <link rel="stylesheet" type="text/css" href="assets/css/fontawesome.min.css">
    <link rel="stylesheet" type="text/css" href="assets/css/all.min.css">
    <link rel="stylesheet" type="text/css" href="assets/css/select2.min.css">
    <link rel="stylesheet" type="text/css" href="assets/css/bootstrap-datetimepicker.css">
    <link rel="stylesheet" type="text/css" href="assets/css/bootstrap-datetimepicker.min.css">
    <link href="https://cdn.datatables.net/1.13.4/css/jquery.dataTables.min.css" rel="stylesheet">
    <link rel="stylesheet" type="text/css" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" integrity="sha512-DTOQO9RWCH3ppGqcWaEA1BIZOC6xxalwEsw9c2QQeAIftl+Vegovlnee1c9QX4TctnWMn13TZye+giMm8e2LwA==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/fontawesome.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
</head>
<body>
    <div class="main-wrapper">
            <div class="header">
                <div class="header-left">
                    <a href="" class="logo">
                        <img src="assets/img/srchlogo.png" width="35" height="35" alt="">  <span>Santa Rosa Community HMS</span>
                    </a>
                </div>
                <a id="toggle_btn" href="javascript:void(0);"><i class="fa fa-bars"></i></a>
                <a id="mobile_btn" class="mobile_btn float-left" href="#sidebar"><i class="fa fa-bars"></i></a>
                <ul class="nav user-menu float-right">
                    <li class="nav-item dropdown has-arrow">
                        <a href="#" class="dropdown-toggle nav-link user-link" data-toggle="dropdown">
                            <span><?php echo $_SESSION['name']; ?></span>
                        </a>
                        <div class="dropdown-menu custom-dropdown">
                            <a class="dropdown-item" href="logout.php">Logout</a>
                        </div>
                    </li>
                </ul>
                <div class="dropdown mobile-user-menu float-right">
                    <a href="#" class="dropdown-toggle" data-toggle="dropdown" aria-expanded="false" aria-haspopup="true"> <i class="fa fa-ellipsis-v"></i></a>
                    <div class="dropdown-menu dropdown-menu-right">
                        <a class="dropdown-item" href="logout.php">Logout</a>
                    </div>
                </div>
            </div>
        </div>
        <div class="sidebar" id="sidebar">
            <div class="sidebar-inner slimscroll">
                <div id="sidebar-menu" class="sidebar-menu">
                    <?php
                    if ($_SESSION['role'] == 1) { ?>
                        <ul>
                        <li class="<?php echo (basename($_SERVER['PHP_SELF']) == 'dashboard.php') ? 'active' : ''; ?>">
                            <a href="dashboard.php"><i class="fa fa-dashboard"></i> <span>Dashboard</span></a>
                        </li>
                        <li class="<?php echo (basename($_SERVER['PHP_SELF']) == 'employees.php') ? 'active' : ''; ?>">
                            <a href="employees.php"><i class="fa-solid fa-users"></i> <span>Employees</span></a>
                        </li>
                        <li class="sidenav">
                            <a href="#triageSubmenu" data-toggle="collapse" aria-expanded="false" class="dropdown-toggle" aria-haspopup="true">
                                <i class="fa-solid fa-laptop-medical"></i> <span>Triage</span>
                            </a>
                            <ul class="collapse list-unstyled" id="triageSubmenu">
                                <li class="<?php echo (basename($_SERVER['PHP_SELF']) == 'patients.php') ? 'active' : ''; ?>">
                                    <a href="patients.php"><i class="fa-solid fa-wheelchair"></i> <span>Patient Registration</span></a>
                                </li>
                                <li class="<?php echo (basename($_SERVER['PHP_SELF']) == 'schedule.php') ? 'active' : ''; ?>">
                                    <a href="schedule.php"><i class="fa-solid fa-calendar"></i> <span>Doctors Schedule</span></a>
                                </li>
                            </ul>
                        </li>
                        <li class="sidenav">
                            <a href="#patientsSubmenu" data-toggle="collapse" aria-expanded="false" class="dropdown-toggle" aria-haspopup="true"> 
                                <i class="fa fa-user"></i> <span>Patients</span>
                            </a>
                            <ul class="collapse list-unstyled" id="patientsSubmenu">
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
                        <li class="sidenav">
                            <a href="#wardmanagementSubmenu" data-toggle="collapse" aria-expanded="false" class="dropdown-toggle" aria-haspopup="true">
                                <i class="fa-solid fa-clipboard-user"></i> <span>Ward Management</span>
                            </a>
                            <ul class="collapse list-unstyled" id="wardmanagementSubmenu">
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
                        <li class="sidenav">
                            <a href="#bedmanagementSubmenu" data-toggle="collapse" aria-expanded="false" class="dropdown-toggle" aria-haspopup="true">
                                <i class="fa-solid fa-bed"></i> <span>Bed Management</span>
                            </a>
                            <ul class="collapse list-unstyled" id="bedmanagementSubmenu">
                                <li class="<?php echo (basename($_SERVER['PHP_SELF']) == 'bedallotment.php') ? 'active' : ''; ?>">
                                    <a href="bedallotment.php"><i class="fa-solid fa-bed"></i> <span>Bed Allotment</span></a>
                                </li>
                                <li class="<?php echo (basename($_SERVER['PHP_SELF']) == 'housekeeping-schedule.php') ? 'active' : ''; ?>">
                                    <a href="housekeeping-schedule.php"><i class="fa-solid fa-calendar"></i> <span>Housekeeping Schedule</span></a>
                                </li>
                            </ul>
                        </li>
                        <li class="sidenav">
                            <a href="#pharmacySubmenu" data-toggle="collapse" aria-expanded="false" class="dropdown-toggle" aria-haspopup="true">
                                <i class="fa-solid fa-prescription"></i> <span>Pharmacy</span>
                            </a>
                            <ul class="collapse list-unstyled" id="pharmacySubmenu">
                                <li class="<?php echo (basename($_SERVER['PHP_SELF']) == 'medicines.php') ? 'active' : ''; ?>">
                                    <a href="medicines.php"><i class="fa-solid fa-pills"></i> <span>Drugs and Medication</span></a>
                                </li>
                                <li class="<?php echo (basename($_SERVER['PHP_SELF']) == 'pharmacy-invoice.php') ? 'active' : ''; ?>">
                                    <a href="pharmacy-invoice.php"><i class="fa-solid fa-receipt"></i> <span>Pharmacy Invoice</span></a>
                                </li>
                            </ul>
                        </li>
                        <li class="sidenav">
                            <a href="#patientcareSubmenu" data-toggle="collapse" aria-expanded="false" class="dropdown-toggle" aria-haspopup="true">
                                <i class="fa-solid fa-hospital-user"></i> <span>Patient Care</span>
                            </a>
                            <ul class="collapse list-unstyled" id="patientcareSubmenu">
                                <li class="<?php echo (basename($_SERVER['PHP_SELF']) == 'newborn.php') ? 'active' : ''; ?>">
                                    <a href="newborn.php"><i class="fa-solid fa-baby"></i> <span>Newborn</span></a>
                                </li>
                                <li class="<?php echo (basename($_SERVER['PHP_SELF']) == 'deceased.php') ? 'active' : ''; ?>">
                                    <a href="deceased.php"><i class="fa-solid fa-skull"></i> <span>Deceased</span></a>
                                </li>
                            </ul>
                        </li>
                        <li class="sidenav">
                            <a href="#laboratorySubmenu" data-toggle="collapse" aria-expanded="false" class="dropdown-toggle" aria-haspopup="true">
                                <i class="fa-solid fa-syringe"></i><span>Laboratory Information</span>
                            </a>
                            <ul class="collapse list-unstyled" id="laboratorySubmenu">
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
                        <li class="sidenav">
                            <a href="operating-room.php"><i class="fa fa-exclamation-triangle"></i> <span>Operating Room</span></a>
                        </li>
                        <li class="<?php echo (basename($_SERVER['PHP_SELF']) == 'radiology.php') ? 'active' : ''; ?>">
                            <a href="radiology.php"><i class="fa-solid fa-radiation"></i> <span>Radiology Information</span></a>
                        </li>
                        <li class="<?php echo (basename($_SERVER['PHP_SELF']) == 'billing.php') ? 'active' : ''; ?>">
                            <a href="billing.php"><i class="fas fa-file-invoice-dollar"></i> <span>Billing</span></a>
                        </li>
                    </ul>
                    <?php } else if ($_SESSION['role'] == 2) { ?>
                        <ul>
                            <li class="active">
                                <a href="dashboard-doctor.php"><i class="fa fa-dashboard"></i> <span>Dashboard</span></a>
                            </li>
                            <li class="sidenav">
                                <a href="#doctorsSubmenu" data-toggle="collapse" aria-expanded="false" class="dropdown-toggle"><i class="fa fa-user-md"></i> <span>Doctors</span></a>
                                <ul class="collapse list-unstyled" id="doctorsSubmenu">
                            <li>
                                <a href="doctors.php"><i class="fa fa-user-md"></i> <span>Doctors</span></a>
                            </li>
                            <li>
                                <a href="schedule.php"><i class="fa fa-calendar"></i> <span>Doctors Schedule</span></a>
                            </li>
                            </ul>
                            </li>
                            <li class="sidenav">
                                <a href="#patientsSubmenu" data-toggle="collapse" aria-expanded="false" class="dropdown-toggle"><i class="fa fa-wheelchair"></i> <span>Patients</span></a>
                                <ul class="collapse list-unstyled" id="patientsSubmenu">
                                    <li>
                                        <a href="outpatients.php"><i class="fa fa-wheelchair"></i> <span>Outpatient Section</span></a>
                                    </li>
                                    <li>
                                        <a href="inpatients.php"><i class="fa fa-wheelchair"></i> <span>Inpatient Section</span></a>
                                    </li>
                                    <li>
                                        <a href="hemodialysis.php"><i class="fa fa-wheelchair"></i> <span>Hemodialysis Section</span></a>
                                    </li>
                                </ul>
                            </li>
                            <li class="sidenav">
                                <a href="#wardmanagementSubmenu" data-toggle="collapse" aria-expanded="false" class="dropdown-toggle" aria-haspopup="true">
                                <i class="fa-solid fa-clipboard-user"></i> <span>Ward Management</span>
                            </a>
                                <ul class="collapse list-unstyled" id="wardmanagementSubmenu">
                            <li>
                                <a href="inpatient-record.php"><i class="fa-solid fa-id-card-clip"></i> <span>Inpatient Record</span></a>
                            </li>
                                </ul>
                            </li>
                        </ul>
                    <?php } else if ($_SESSION['role'] == 3) { ?>
                        <ul>
                        <li class="active">
                                <a href="dashboard-nurse.php"><i class="fa fa-dashboard"></i> <span>Dashboard</span></a>
                            </li>
                        <li class="sidenav">
                            <a href="#triageSubmenu" data-toggle="collapse" aria-expanded="false" class="dropdown-toggle" aria-haspopup="true">
                             <i class="fa-solid fa-laptop-medical"></i> <span>Triage</span>
                            </a>
                                <ul class="collapse list-unstyled" id="triageSubmenu">
                                    <li>
                                        <a href="patients.php"><i class="fa-solid fa-wheelchair"></i> <span>Patient Registraton</span></a>
                                    </li>
                                    <li>
                                        <a href="schedule.php"><i class="fa-solid fa-calendar"></i> <span>Doctors Schedule</span></a>
                                    </li>
                                    <li>
                                        <a href="visitor-pass.php"><i class="fa-solid fa-user"></i> <span>Visitor Pass</span></a>
                                    </li>
                                </ul>
                            </li>
                            <li class="sidenav">
                                    <a href="#patientsSubmenu" data-toggle="collapse" aria-expanded="false" class="dropdown-toggle" aria-haspopup="true"> 
                                        <i class="fa fa-user"></i> <span>Patients</span>
                                    </a>
                                    <ul class="collapse list-unstyled" id="patientsSubmenu">
                                        <li>
                                            <a href="outpatients.php"><i class="fa-solid fa-user"></i> <span>Outpatient Section</span></a>
                                        </li>
                                        <li>
                                            <a href="inpatients.php"><i class="fa-solid fa-user"></i> <span>Inpatient Section</span></a>
                                        </li>
                                        <li>
                                            <a href="hemodialysis.php"><i class="fa-solid fa-user"></i> <span>Hemodialysis Section</span></a>
                                        </li>
                                    </ul>
                                </li>
                                <li class="sidenav">
                                    <a href="#wardmanagementSubmenu" data-toggle="collapse" aria-expanded="false" class="dropdown-toggle" aria-haspopup="true">
                                    <i class="fa-solid fa-clipboard-user"></i> <span>Ward Management</span>
                                </a>
                                    <ul class="collapse list-unstyled" id="wardmanagementSubmenu">
                                <li>
                                    <a href="visitor-pass.php"><i class="fa-solid fa-user"></i> <span>Visitor Pass</span></a>
                                </li>
                                <li>
                                    <a href="inpatient-record.php"><i class="fa-solid fa-id-card-clip"></i> <span>Inpatient Record</span></a>
                                </li>
                                <li>
                                    <a href="bed-transfer.php"><i class="fa-solid fa-bed"></i> <span>Bed Transfer</span></a>
                                </li>
                                    </ul>
                                </li>
                                <li class="sidenav">
                                    <a href="#bedmanagementSubmenu" data-toggle="collapse" aria-expanded="false" class="dropdown-toggle" aria-haspopup="true">
                                    <i class="fa-solid fa-bed"></i> <span>Bed Management</span>
                                </a>
                                    <ul class="collapse list-unstyled" id="bedmanagementSubmenu">
                                <li>
                                    <a href="bedallotment.php"><i class="fa-solid fa-bed"></i> <span>Bed Allotment</span></a>
                                </li>
                                <li>
                                    <a href="housekeeping-schedule.php"><i class="fa-solid fa-calendar"></i> <span>Housekeeping Schedule</span></a>
                                </li>
                                    </ul>
                                </li>
                                <li class="sidenav">
                                <a href="#patientcareSubmenu" data-toggle="collapse" aria-expanded="false" class="dropdown-toggle" aria-haspopup="true">
                                    <i class="fa-solid fa-hospital-user"></i> <span>Patient Care</span>
                                </a>
                                <ul class="collapse list-unstyled" id="patientcareSubmenu">
                                    <li>
                                        <a href="newborn.php"><i class="fa-solid fa-baby"></i> <span>Newborn</span></a>
                                    </li>
                                    <li>
                                        <a href="deceased.php"><i class="fa-solid fa-skull"></i> <span>Deceased</span></a>
                                    </li>
                                </ul>
                            </li>
                            <li class="sidenav">
                                <a href="#laboratorySubmenu" data-toggle="collapse" aria-expanded="false" class="dropdown-toggle" aria-haspopup="true"> 
                                    <i class="fa-solid fa-syringe"></i><span>Laboratory Information</span>
                                </a>
                                <ul class="collapse list-unstyled" id="laboratorySubmenu">
                                    <li>
                                        <a href="lab-result.php"> <i class="fa-solid fa-clipboard-check"></i> <span>Lab Result</span></a>
                                    </li>
                                </ul>
                            </li>
                            <li class="active">
                                <a href="operating-room.php"><i class="fa fa-exclamation-triangle"></i> <span>Operating Room</span></a>
                            </li>
                            </ul>
                    <?php } else if ($_SESSION['role'] == 4) { ?>
                        <ul>
                            <li class="active">
                                <a href="dashboard-pharmacy.php"><i class="fa fa-dashboard"></i> <span>Dashboard</span></a>
                            </li>
                            <li class="sidenav">
                                <a href="#pharmacySubmenu" data-toggle="collapse" aria-expanded="false" class="dropdown-toggle" aria-haspopup="true">
                                    <i class="fa-solid fa-prescription"></i> <span>Pharmacy</span>
                                </a>
                                <ul class="collapse list-unstyled" id="pharmacySubmenu">
                                    <li>
                                        <a href="medicines.php"><i class="fa fa-pills"></i> <span>Drugs and Medication</span></a>
                                    </li>
                                    <li>
                                        <a href="pharmacy-invoice.php"><i class="fa-solid fa-receipt"></i> <span>Pharmacy Invoice</span></a>
                                    </li>
                                </ul>
                            </li>
                        </ul>
                        <?php } else if ($_SESSION['role'] == 5) { ?>
                            <ul>
                            <li class="active">
                                <a href="dashboard-lab.php"><i class="fa fa-dashboard"></i> <span>Dashboard</span></a>
                            </li>
                            <li class="sidenav">
                                <a href="#laboratorySubmenu" data-toggle="collapse" aria-expanded="false" class="dropdown-toggle" aria-haspopup="true"> 
                                    <i class="fa-solid fa-syringe"></i><span>Laboratory Information</span>
                                </a>
                                <ul class="collapse list-unstyled" id="laboratorySubmenu">
                                    <li>
                                        <a href="lab-tests.php"> <i class="fa-solid fa-vials"></i> <span>Lab Tests</span></a>
                                    </li>
                                    <li>
                                        <a href="lab-order-patients.php"> <i class="fa-solid fa-microscope"></i> <span>Lab Order</span></a>
                                    </li>
                                    <li>
                                        <a href="lab-result.php"> <i class="fa-solid fa-clipboard-check"></i> <span>Lab Result</span></a>
                                    </li>
                                </ul>
                            </li>
                        </ul>
                            <?php } else if ($_SESSION['role'] == 6) { ?>
                        <ul>
                            <li class="active">
                                <a href="dashboard-rad.php"><i class="fa fa-dashboard"></i> <span>Dashboard</span></a>
                            </li>
                            <li>
                                <a href="radiology.php"><i class="fa-solid fa-radiation"></i> <span>Radiology Information</span></a>
                            </li>
                        </ul>
                            <?php } else if ($_SESSION['role'] == 7) { ?>
                        <ul>
                            <li class="active">
                                <a href="dashboard.php"><i class="fa fa-dashboard"></i> <span>Dashboard</span></a>
                            </li>
                            <?php } else if ($_SESSION['role'] == 8) { ?>
                        <ul>
                            <li class="active">
                                <a href="dashboard.php"><i class="fa fa-dashboard"></i> <span>Dashboard</span></a>
                            </li>
                            <?php } else if ($_SESSION['role'] == 9) { ?>
                        <ul>
                            <li class="active">
                                <a href="dashboard.php"><i class="fa fa-dashboard"></i> <span>Dashboard</span></a>
                            </li>
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
<script>
    // Toggle Sidebar
    function toggleSidebar() {
        const sidebar = document.getElementById("sidebar");
        const mainWrapper = document.getElementById("main-wrapper");
        
        if (sidebar && mainWrapper) { // Check if elements exist
            sidebar.classList.toggle("active");
            mainWrapper.classList.toggle("active");
        }
    }

    // Mobile Menu Toggle
    function toggleMobileMenu() {
        const sidebar = document.getElementById("sidebar");
        
        if (sidebar) { // Check if element exists
            sidebar.classList.toggle("active");
        }
    }

    // Dropdown Menu Toggle
    function initDropdownMenu() {
        const dropdownToggles = document.getElementsByClassName("dropdown-toggle");
        
        Array.from(dropdownToggles).forEach(toggle => {
            toggle.addEventListener("click", function (event) {
                event.stopPropagation(); // Prevent event bubbling
                this.classList.toggle("active");
                const dropdownMenu = this.nextElementSibling;
                if (dropdownMenu) {
                    dropdownMenu.classList.toggle("show"); // Use 'show' class for Bootstrap dropdown
                }
            });
        });
    }

    // Close dropdown menus when clicking outside
    function closeDropdowns(event) {
        const dropdownMenus = document.querySelectorAll(".dropdown-menu.show");
        dropdownMenus.forEach(menu => {
            if (!menu.contains(event.target)) {
                menu.classList.remove("show");
                const toggle = menu.previousElementSibling;
                if (toggle) {
                    toggle.classList.remove("active");
                }
            }
        });
    }

    // Initialize dropdown menu toggles on DOMContentLoaded
    document.addEventListener("DOMContentLoaded", function() {
        initDropdownMenu();
        document.addEventListener("click", closeDropdowns); // Close dropdowns on click outside
    });
</script>
<style>
    .custom-dropdown {
    background-color: #f9f9f9; /* Slightly lighter background */
    border: 1px solid #ddd; /* Softer border color */
    border-radius: 5px; /* Rounded corners */
    padding: 5px; /* Padding inside the dropdown */
    box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1); /* Subtle shadow for depth */
    min-width: 150px; /* Set minimum width to keep the dropdown compact */
}

.custom-dropdown .dropdown-item {
    padding: 9px 12px; /* Adjust padding for a balanced look */
    font-size: 14px; /* Keep a clean font size */
    color: #333; /* Darker text color for readability */
    background-color: #fff; /* White background for the items */
    border-radius: 4px; /* Subtle rounded corners for each item */
    transition: background-color 0.3s ease, color 0.3s ease, box-shadow 0.3s ease; /* Smooth transitions for hover effects */
}

.custom-dropdown .dropdown-item:hover {
    background-color: #e0e0e0; /* Soft gray hover background */
    color: #000; /* Darker text on hover */
    box-shadow: 0 2px 6px rgba(0, 0, 0, 0.1); /* Add a slight shadow on hover */
    border-radius: 5px; /* Slight rounding on hover */
    cursor: pointer; /* Change cursor on hover */
}

.custom-dropdown .dropdown-item:active {
    background-color: #cccccc; /* Darker gray when clicked */
    color: #000; /* Keep the text color dark */
    box-shadow: inset 0 2px 4px rgba(0, 0, 0, 0.1); /* Inner shadow for pressed effect */
}
/* Style for active menu items */
.sidebar-menu .active a {
    background-color: #007bff; /* Blue background */
    color: white; /* White text */
}

.sidebar-menu .active a:hover {
    background-color: #CCCCCC; /* Darker blue on hover */
}

</style>
</html>


