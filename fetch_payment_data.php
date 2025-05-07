<?php
session_start();
if(empty($_SESSION['name'])) {
    header('HTTP/1.1 403 Forbidden');
    die(json_encode(['error' => 'Unauthorized access']));
}

include('includes/connection.php');

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Get and validate parameters
$timeRange = $_GET['timeRange'] ?? 'monthly';
$dataType = $_GET['dataType'] ?? 'revenue';

// Validate inputs
if (!in_array($timeRange, ['weekly', 'monthly', 'yearly']) || 
    !in_array($dataType, ['revenue', 'inpatient', 'outpatient', 'hemodialysis', 'newborn'])) {
    header('HTTP/1.1 400 Bad Request');
    die(json_encode(['error' => 'Invalid parameters']));
}

// Set date range based on time selection
$now = new DateTime();
$startDate = clone $now;

if ($timeRange === 'weekly') {
    $startDate->modify('monday this week')->modify('-7 weeks');
    $mysqlFormat = '%x-W%v';
    $periodFormat = 'o-\WW';
} elseif ($timeRange === 'monthly') {
    $startDate->modify('first day of this month')->modify('-11 months');
    $mysqlFormat = '%Y-%m';
    $periodFormat = 'Y-m';
} else { // yearly
    $startDate->modify('-4 years');
    $mysqlFormat = '%Y';
    $periodFormat = 'Y';
}

// Build query based on data type
if ($dataType === 'revenue') {
    $query = "SELECT 
        DATE_FORMAT(payment_datetime, ?) as period, 
        COALESCE(SUM(total_due), 0) as value 
        FROM tbl_payment 
        WHERE payment_datetime >= ?
        GROUP BY period
        ORDER BY period";
} else {
    $query = "SELECT 
        DATE_FORMAT(payment_datetime, ?) as period, 
        COALESCE(COUNT(*), 0) as value 
        FROM tbl_payment 
        WHERE patient_type = ? AND payment_datetime >= ?
        GROUP BY period
        ORDER BY period";
}

// Execute query
$stmt = $connection->prepare($query);
if (!$stmt) {
    die(json_encode(['error' => 'Prepare failed: ' . $connection->error]));
}

$startDateStr = $startDate->format('Y-m-d');
if ($dataType === 'revenue') {
    $stmt->bind_param("ss", $mysqlFormat, $startDateStr);
} else {
    $type = ucfirst($dataType); // Convert to proper case
    $stmt->bind_param("sss", $mysqlFormat, $type, $startDateStr);
}
$stmt->execute();
$result = $stmt->get_result();

// Generate all expected periods
$allPeriods = [];
$current = clone $startDate;
$end = clone $now;

if ($timeRange === 'weekly') {
    for ($i = 0; $i < 8; $i++) {
        $weekNum = $current->format('W');
        $year = $current->format('o');
        $periodKey = $year . '-W' . str_pad($weekNum, 2, '0', STR_PAD_LEFT);
        $allPeriods[$periodKey] = 0;
        $current->modify('+1 week');
    }
} elseif ($timeRange === 'monthly') {
    $current->modify('first day of this month');
    while ($current <= $end) {
        $allPeriods[$current->format($periodFormat)] = 0;
        $current->modify('+1 month');
    }
} else { // yearly
    for ($year = (int)$startDate->format('Y'); $year <= (int)$end->format('Y'); $year++) {
        $allPeriods[(string)$year] = 0;
    }
}

// Fill with actual data
while ($row = $result->fetch_assoc()) {
    $periodKey = $row['period'];
    if ($timeRange === 'weekly') {
        if (preg_match('/^(\d{4})-W(\d{1,2})$/', $periodKey, $matches)) {
            $periodKey = $matches[1] . '-W' . str_pad($matches[2], 2, '0', STR_PAD_LEFT);
        }
    }
    if (isset($allPeriods[$periodKey])) {
        $allPeriods[$periodKey] = (float)$row['value'];
    }
}

// Convert to simple array maintaining order
$outputData = array_values($allPeriods);

header('Content-Type: application/json');
echo json_encode($outputData);
?>