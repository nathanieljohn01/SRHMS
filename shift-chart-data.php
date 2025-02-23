<?php
include('includes/connection.php');
header('Content-Type: application/json');

// Set Philippine timezone
date_default_timezone_set('Asia/Manila');

// Define shift times
$shifts = [
    'Morning' => ['start' => '06:00:00', 'end' => '14:00:00'],
    'Afternoon' => ['start' => '14:00:00', 'end' => '22:00:00'],
    'Night' => ['start' => '22:00:00', 'end' => '06:00:00']
];

// Initialize counts
$data = [
    'Completed' => [],
    'Cancelled' => []
];

// Get current date and next day in PH time
$current_date = date('Y-m-d');
$next_day = date('Y-m-d', strtotime($current_date . ' + 1 day'));


// Fetch and count lab orders for each shift
foreach ($shifts as $shiftName => $times) {
    if ($shiftName == 'Night') {
        $query_completed = "
            SELECT COUNT(*) AS count 
            FROM tbl_laborder 
            WHERE status = 'Completed'
              AND shift = '$shiftName'
              AND ((DATE(requested_date) = '$current_date' 
                   AND TIME(requested_date) >= '{$times['start']}')
                   OR (DATE(requested_date) = '$next_day' 
                       AND TIME(requested_date) <= '{$times['end']}'))
        ";
        
        $query_cancelled = "
            SELECT COUNT(*) AS count 
            FROM tbl_laborder 
            WHERE status LIKE 'Cancelled%'
              AND shift = '$shiftName'
              AND ((DATE(requested_date) = '$current_date' 
                   AND TIME(requested_date) >= '{$times['start']}')
                   OR (DATE(requested_date) = '$next_day' 
                       AND TIME(requested_date) <= '{$times['end']}'))
        ";
    } else {
        $query_completed = "
            SELECT COUNT(*) AS count 
            FROM tbl_laborder 
            WHERE status = 'Completed'
              AND shift = '$shiftName'
              AND DATE(requested_date) = '$current_date'
              AND TIME(requested_date) BETWEEN '{$times['start']}' AND '{$times['end']}'
        ";

        $query_cancelled = "
            SELECT COUNT(*) AS count 
            FROM tbl_laborder 
            WHERE status LIKE 'Cancelled%'
              AND shift = '$shiftName'
              AND DATE(requested_date) = '$current_date'
              AND TIME(requested_date) BETWEEN '{$times['start']}' AND '{$times['end']}'
        ";
    }

    $result_completed = mysqli_query($connection, $query_completed);
    $row_completed = mysqli_fetch_assoc($result_completed);
    $data['Completed'][$shiftName] = $row_completed['count'];

    $result_cancelled = mysqli_query($connection, $query_cancelled);
    $row_cancelled = mysqli_fetch_assoc($result_cancelled);
    $data['Cancelled'][$shiftName] = $row_cancelled['count'];
}

// Output JSON data
echo json_encode($data);

mysqli_close($connection);
?>
