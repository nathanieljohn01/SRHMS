<?php
include('includes/connection.php');

// Set timezone to Philippines (UTC+8)
date_default_timezone_set('Asia/Manila');

$timeRange = $_GET['timeRange'] ?? 'monthly';
$patientType = $_GET['patientType'] ?? 'All';

$data = [];

if ($timeRange === 'weekly') {
    $currentDate = new DateTime('now', new DateTimeZone('Asia/Manila'));
    $startOfMonth = new DateTime('first day of this month', new DateTimeZone('Asia/Manila'));
    
    // Calculate the current week number (ISO week number)
    $currentWeekNumber = $currentDate->format('W') - $startOfMonth->format('W') + 1;
    
    // Calculate total weeks in month
    $endOfMonth = new DateTime('last day of this month', new DateTimeZone('Asia/Manila'));
    $totalWeeks = $endOfMonth->format('W') - $startOfMonth->format('W') + 1;
    
    // For April 14, 2025 specifically (should be Week 3)
    $currentWeekNumber = min($currentWeekNumber, $totalWeeks);
    
    for ($week = 1; $week <= $currentWeekNumber; $week++) {
        // Calculate start and end dates for each week
        $weekStart = clone $startOfMonth;
        if ($week > 1) {
            $weekStart->add(new DateInterval('P'.(($week-1)*7).'D'));
        }
        
        $weekEnd = clone $weekStart;
        $weekEnd->add(new DateInterval('P6D'));
        
        // Adjust end date if it's beyond current date or month end
        if ($week == $currentWeekNumber) {
            $weekEnd = min($weekEnd, $currentDate);
        }
        $weekEnd = min($weekEnd, $endOfMonth);
        
        $startDate = $weekStart->format('Y-m-d 00:00:00');
        $endDate = $weekEnd->format('Y-m-d 23:59:59');
        
        if ($patientType === 'All') {
            $query = "SELECT COUNT(*) as count FROM tbl_patient 
                      WHERE created_at BETWEEN '$startDate' AND '$endDate'";
        } else {
            $query = "SELECT COUNT(*) as count FROM tbl_patient 
                      WHERE patient_type = '$patientType' 
                      AND created_at BETWEEN '$startDate' AND '$endDate'";
        }
        
        $result = mysqli_query($connection, $query);
        $row = mysqli_fetch_assoc($result);
        $data[] = (int)$row['count'];
    }
    
    // Ensure we always return data for all weeks up to current week
    while (count($data) < $currentWeekNumber) {
        $data[] = 0;
    }
} elseif ($timeRange === 'monthly') {
    $currentMonth = date('n');
    
    for ($month = 1; $month <= $currentMonth; $month++) {
        if ($patientType === 'All') {
            $query = "SELECT COUNT(*) as count FROM tbl_patient 
                      WHERE MONTH(created_at) = $month 
                      AND YEAR(created_at) = YEAR(NOW())";
        } else {
            $query = "SELECT COUNT(*) as count FROM tbl_patient 
                      WHERE patient_type = '$patientType' 
                      AND MONTH(created_at) = $month 
                      AND YEAR(created_at) = YEAR(NOW())";
        }
        
        $result = mysqli_query($connection, $query);
        $row = mysqli_fetch_assoc($result);
        $data[] = (int)$row['count'];
    }
} else { // yearly
    $currentYear = date('Y');
    
    for ($year = $currentYear - 4; $year <= $currentYear; $year++) {
        if ($patientType === 'All') {
            $query = "SELECT COUNT(*) as count FROM tbl_patient 
                      WHERE YEAR(created_at) = $year";
        } else {
            $query = "SELECT COUNT(*) as count FROM tbl_patient 
                      WHERE patient_type = '$patientType' 
                      AND YEAR(created_at) = $year";
        }
        
        $result = mysqli_query($connection, $query);
        $row = mysqli_fetch_assoc($result);
        $data[] = (int)$row['count'];
    }
}

header('Content-Type: application/json');
echo json_encode($data);
?>