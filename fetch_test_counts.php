<?php
include('includes/connection.php');

$shift = $_GET['shift'] ?? null;
$timeRange = $_GET['timeRange'] ?? 'daily';

$response = [
    'in_progress' => 0,
    'completed' => 0,
    'cancelled' => 0,
    'stat' => 0
];

// Function to get time range based on filter
function getTimeRange($timeRange, $shift = null) {
    $now = new DateTime();
    
    if ($timeRange === 'daily') {
        if ($shift === 'morning') {
            return [
                $now->format('Y-m-d') . ' 06:00:00',
                $now->format('Y-m-d') . ' 13:59:59'
            ];
        } elseif ($shift === 'afternoon') {
            return [
                $now->format('Y-m-d') . ' 14:00:00',
                $now->format('Y-m-d') . ' 21:59:59'
            ];
        } elseif ($shift === 'night') {
            return [
                $now->format('Y-m-d') . ' 22:00:00',
                $now->format('Y-m-d', strtotime('+1 day')) . ' 05:59:59'
            ];
        } else {
            return [
                $now->format('Y-m-d') . ' 00:00:00',
                $now->format('Y-m-d') . ' 23:59:59'
            ];
        }
    } elseif ($timeRange === 'weekly') {
        $start = (clone $now)->modify('this week')->format('Y-m-d 00:00:00');
        $end = (clone $now)->modify('this week +6 days')->format('Y-m-d 23:59:59');
        return [$start, $end];
    } elseif ($timeRange === 'monthly') {
        $start = (clone $now)->modify('first day of this month')->format('Y-m-d 00:00:00');
        $end = (clone $now)->modify('last day of this month')->format('Y-m-d 23:59:59');
        return [$start, $end];
    } else { // yearly
        $start = $now->format('Y-01-01 00:00:00');
        $end = $now->format('Y-12-31 23:59:59');
        return [$start, $end];
    }
}

// Get the time range
$timeRange = getTimeRange($timeRange, $shift);
$startDate = $timeRange[0];
$endDate = $timeRange[1];

// Fetch counts
$result = mysqli_query($connection, "
    SELECT COUNT(*) as count FROM tbl_laborder 
    WHERE status='In-Progress' 
    AND created_at BETWEEN '$startDate' AND '$endDate'
");
$response['in_progress'] = mysqli_fetch_assoc($result)['count'];

$result = mysqli_query($connection, "
    SELECT COUNT(*) as count FROM tbl_laborder 
    WHERE status='Completed' 
    AND created_at BETWEEN '$startDate' AND '$endDate'
");
$response['completed'] = mysqli_fetch_assoc($result)['count'];

$result = mysqli_query($connection, "
    SELECT COUNT(*) as count FROM tbl_laborder 
    WHERE status LIKE 'Cancelled%' 
    AND created_at BETWEEN '$startDate' AND '$endDate'
");
$response['cancelled'] = mysqli_fetch_assoc($result)['count'];

$result = mysqli_query($connection, "
    SELECT COUNT(*) as count FROM tbl_laborder 
    WHERE stat='STAT' 
    AND status NOT LIKE 'Completed' 
    AND status NOT LIKE 'Cancelled%'
    AND created_at BETWEEN '$startDate' AND '$endDate'
");
$response['stat'] = mysqli_fetch_assoc($result)['count'];

// fetch_test_counts.php
header('Content-Type: application/json');
echo json_encode([
    'in_progress' => $in_progress,
    'completed' => $completed,
    'cancelled' => $cancelled,
    'stat' => $stat
]);
?>