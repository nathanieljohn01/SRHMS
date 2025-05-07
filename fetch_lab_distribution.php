<?php
session_start();
include('includes/connection.php');

date_default_timezone_set('Asia/Manila');

// Get parameters - only monthly and yearly now
$timeRange = $_GET['timeRange'] ?? 'monthly';
$shift = $_GET['shift'] ?? null;

// Calculate date ranges
$endDate = date('Y-m-d 23:59:59');

switch ($timeRange) {
    case 'monthly':
        $startDate = date('Y-m-01 00:00:00');
        break;
    case 'yearly':
        $startDate = date('Y-01-01 00:00:00');
        break;
    default:
        // Default to monthly if invalid value
        $timeRange = 'monthly';
        $startDate = date('Y-m-01 00:00:00');
}

// Build query
$query = "SELECT lab_test, COUNT(*) as count 
          FROM tbl_laborder 
          WHERE created_at BETWEEN ? AND ?";

// Add shift filter if specified
if ($shift) {
    $shift = strtolower($shift);
    switch ($shift) {
        case 'morning':
            $query .= " AND HOUR(created_at) BETWEEN 6 AND 13";
            break;
        case 'afternoon':
            $query .= " AND HOUR(created_at) BETWEEN 14 AND 21";
            break;
        case 'night':
            $query .= " AND (HOUR(created_at) >= 22 OR HOUR(created_at) < 6)";
            break;
    }
}

$query .= " GROUP BY lab_test ORDER BY count DESC LIMIT 10";

// Execute query
$stmt = $connection->prepare($query);
if (!$stmt) {
    header('Content-Type: application/json');
    die(json_encode(['error' => 'Prepare failed: ' . $connection->error]));
}

$stmt->bind_param("ss", $startDate, $endDate);
if (!$stmt->execute()) {
    header('Content-Type: application/json');
    die(json_encode(['error' => 'Execute failed: ' . $stmt->error]));
}

$result = $stmt->get_result();

$data = [
    'labels' => [],
    'values' => [],
    'meta' => [
        'timeRange' => $timeRange,
        'startDate' => $startDate,
        'endDate' => $endDate,
        'query' => $query
    ]
];

while ($row = $result->fetch_assoc()) {
    $data['labels'][] = $row['lab_test'];
    $data['values'][] = (int)$row['count'];
}

header('Content-Type: application/json');
echo json_encode($data);

$stmt->close();
$connection->close();
?>