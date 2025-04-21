<?php
session_start();
include('includes/connection.php');

// Set default timezone
date_default_timezone_set('Asia/Manila');

// Get parameters
$timeRange = isset($_GET['timeRange']) ? $_GET['timeRange'] : 'monthly';
$shift = isset($_GET['shift']) ? $_GET['shift'] : null;

// Determine date range based on time range
$startDate = '';
$endDate = date('Y-m-d H:i:s'); // Current date and time

switch ($timeRange) {
    case 'weekly':
        $startDate = date('Y-m-d H:i:s', strtotime('-1 week'));
        break;
    case 'monthly':
        $startDate = date('Y-m-d H:i:s', strtotime('-1 month'));
        break;
    case 'yearly':
        $startDate = date('Y-m-d H:i:s', strtotime('-1 year'));
        break;
    default:
        $startDate = date('Y-m-d H:i:s', strtotime('-1 month'));
}

// Build the query
$query = "SELECT lab_test, COUNT(*) as count FROM tbl_laborder WHERE created_at BETWEEN ? AND ?";

// Add shift filter if specified
if ($shift) {
    // Determine shift hours
    $shiftStart = '';
    $shiftEnd = '';
    
    switch ($shift) {
        case 'morning':
            $query .= " AND HOUR(created_at) >= 6 AND HOUR(created_at) < 14";
            break;
        case 'afternoon':
            $query .= " AND HOUR(created_at) >= 14 AND HOUR(created_at) < 22";
            break;
        case 'night':
            $query .= " AND (HOUR(created_at) >= 22 OR HOUR(created_at) < 6)";
            break;
    }
}

$query .= " GROUP BY lab_test ORDER BY count DESC LIMIT 10";

// Prepare and execute the query
$stmt = $connection->prepare($query);
$stmt->bind_param("ss", $startDate, $endDate);
$stmt->execute();
$result = $stmt->get_result();

// Process the results
$labels = [];
$values = [];

while ($row = $result->fetch_assoc()) {
    $labels[] = $row['lab_test'];
    $values[] = $row['count'];
}

// Return the data as JSON
echo json_encode([
    'labels' => $labels,
    'values' => $values
]);

$stmt->close();
$connection->close();
?>
