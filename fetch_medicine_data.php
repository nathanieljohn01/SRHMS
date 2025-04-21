<?php
include('includes/connection.php');

$timeRange = $_GET['timeRange'] ?? 'monthly';
$dataType = $_GET['dataType'] ?? 'new'; // 'new' or 'sold'

$data = [];
$labels = [];

if ($timeRange === 'weekly') {
    // Get current week of the year (1-52)
    $currentWeek = date('W');
    $currentYear = date('Y');
    
    // We'll show last 12 weeks including current week
    $startWeek = max(1, $currentWeek - 11);
    
    for ($week = $startWeek; $week <= $currentWeek; $week++) {
        // Get start date (Monday) of the week
        $startDate = new DateTime();
        $startDate->setISODate($currentYear, $week);
        $startDate = $startDate->format('Y-m-d');
        
        // Get end date (Sunday) of the week
        $endDate = new DateTime($startDate);
        $endDate->modify('+6 days');
        $endDate = $endDate->format('Y-m-d');
        
        if ($dataType === 'new') {
            $query = "SELECT COUNT(*) as count FROM tbl_medicines 
                     WHERE new_added_date BETWEEN '$startDate' AND '$endDate'";
        } else { // sold
            $query = "SELECT SUM(quantity) as count FROM tbl_pharmacy_invoice 
                     WHERE DATE(invoice_datetime) BETWEEN '$startDate' AND '$endDate'";
        }
        
        $result = mysqli_query($connection, $query);
        $row = mysqli_fetch_assoc($result);
        $data[] = (int)($row['count'] ?? 0);
        $labels[] = "Week $week";
    }
} elseif ($timeRange === 'monthly') {
    $currentYear = date('Y');
    $monthNames = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 
                  'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
    
    for ($month = 1; $month <= 12; $month++) {
        if ($dataType === 'new') {
            $query = "SELECT COUNT(*) as count FROM tbl_medicines 
                     WHERE MONTH(new_added_date) = $month 
                     AND YEAR(new_added_date) = $currentYear";
        } else { // sold
            $query = "SELECT SUM(quantity) as count FROM tbl_pharmacy_invoice 
                     WHERE MONTH(invoice_datetime) = $month 
                     AND YEAR(invoice_datetime) = $currentYear";
        }
        
        $result = mysqli_query($connection, $query);
        $row = mysqli_fetch_assoc($result);
        $data[] = (int)($row['count'] ?? 0);
        $labels[] = $monthNames[$month - 1];
    }
} else { // yearly
    $currentYear = date('Y');
    $years = range($currentYear - 4, $currentYear);
    
    foreach ($years as $year) {
        if ($dataType === 'new') {
            $query = "SELECT COUNT(*) as count FROM tbl_medicines 
                     WHERE YEAR(new_added_date) = $year";
        } else { // sold
            $query = "SELECT SUM(quantity) as count FROM tbl_pharmacy_invoice 
                     WHERE YEAR(invoice_datetime) = $year";
        }
        
        $result = mysqli_query($connection, $query);
        $row = mysqli_fetch_assoc($result);
        $data[] = (int)($row['count'] ?? 0);
        $labels[] = (string)$year;
    }
}

$response = [
    'success' => true,
    'data' => $data,
    'labels' => $labels,
    'timeRange' => $timeRange,
    'dataType' => $dataType
];

header('Content-Type: application/json');
echo json_encode($response);
?>