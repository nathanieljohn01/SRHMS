<?php
include('includes/connection.php');

// Set timezone to Philippines (UTC+8)
date_default_timezone_set('Asia/Manila');

$timeRange = $_GET['timeRange'] ?? 'monthly';
$shift = $_GET['shift'] ?? null;

$data = ['labels' => [], 'values' => []];

// Function to get shift time range in PH Time
function getShiftRange($shift, $date) {
    if ($shift === 'morning') {
        return [
            $date . ' 06:00:00',
            $date . ' 13:59:59'
        ];
    } elseif ($shift === 'afternoon') {
        return [
            $date . ' 14:00:00',
            $date . ' 21:59:59'
        ];
    } else { // night
        return [
            $date . ' 22:00:00',
            date('Y-m-d', strtotime($date . ' +1 day')) . ' 05:59:59'
        ];
    }
}

if ($timeRange === 'weekly') {
    $currentDate = new DateTime('now', new DateTimeZone('Asia/Manila'));
    $startOfMonth = new DateTime('first day of this month', new DateTimeZone('Asia/Manila'));
    
    // Calculate total weeks in month (including partial weeks)
    $daysInMonth = $startOfMonth->format('t');
    $firstDayOfWeek = $startOfMonth->format('N'); // 1 (Monday) through 7 (Sunday)
    $totalWeeks = ceil(($daysInMonth + $firstDayOfWeek - 1) / 7);
    
    for ($week = 1; $week <= $totalWeeks; $week++) {
        // Calculate start and end dates for each week
        $weekStart = clone $startOfMonth;
        $daysToAdd = ($week - 1) * 7 - ($firstDayOfWeek - 1);
        if ($daysToAdd < 0) $daysToAdd = 0;
        $weekStart->add(new DateInterval('P'.$daysToAdd.'D'));
        
        $weekEnd = clone $weekStart;
        $weekEnd->add(new DateInterval('P6D'));
        
        // Adjust end date if it's beyond current date or month end
        if ($weekEnd > $currentDate) {
            $weekEnd = clone $currentDate;
        }
        if ($weekEnd->format('m') != $startOfMonth->format('m')) {
            $weekEnd = new DateTime('last day of this month', new DateTimeZone('Asia/Manila'));
        }
        
        $startDate = $weekStart->format('Y-m-d');
        $endDate = $weekEnd->format('Y-m-d');
        
        // If shift is specified, adjust the query
        $whereClause = "update_date BETWEEN '$startDate 00:00:00' AND '$endDate 23:59:59'";
        if ($shift) {
            $shiftDates = [];
            $current = clone $weekStart;
            while ($current <= $weekEnd) {
                $shiftRange = getShiftRange($shift, $current->format('Y-m-d'));
                $shiftDates[] = "(update_date BETWEEN '{$shiftRange[0]}' AND '{$shiftRange[1]}')";
                $current->add(new DateInterval('P1D'));
            }
            $whereClause = "(" . implode(" OR ", $shiftDates) . ")";
        }
        
        $query = "SELECT test_type, COUNT(*) as count FROM tbl_radiology 
                  WHERE $whereClause
                  GROUP BY test_type 
                  ORDER BY count DESC 
                  LIMIT 10";
        
        $result = mysqli_query($connection, $query);
        $weekData = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $weekData[$row['test_type']] = (int)$row['count'];
        }
        
        // For weekly distribution, we'll sum up all test types across weeks
        foreach ($weekData as $testType => $count) {
            if (!isset($data['values'][$testType])) {
                $data['values'][$testType] = 0;
            }
            $data['values'][$testType] += $count;
        }
    }
    
    // Format the weekly data for chart
    arsort($data['values']);
    $data['labels'] = array_keys($data['values']);
    $data['values'] = array_values($data['values']);
    
} elseif ($timeRange === 'monthly') {
    $currentMonth = date('n');
    
    for ($month = 1; $month <= $currentMonth; $month++) {
        $monthName = date('F', mktime(0, 0, 0, $month, 1));
        
        // If shift is specified, adjust the query
        $whereClause = "MONTH(update_date) = $month AND YEAR(update_date) = YEAR(NOW())";
        if ($shift) {
            $shiftDates = [];
            $daysInMonth = cal_days_in_month(CAL_GREGORIAN, $month, date('Y'));
            for ($day = 1; $day <= $daysInMonth; $day++) {
                $date = date('Y-m-d', mktime(0, 0, 0, $month, $day, date('Y')));
                $shiftRange = getShiftRange($shift, $date);
                $shiftDates[] = "(update_date BETWEEN '{$shiftRange[0]}' AND '{$shiftRange[1]}')";
            }
            $whereClause = "(" . implode(" OR ", $shiftDates) . ")";
        }
        
        $query = "SELECT test_type, COUNT(*) as count FROM tbl_radiology 
                  WHERE $whereClause
                  GROUP BY test_type 
                  ORDER BY count DESC 
                  LIMIT 10";
        
        $result = mysqli_query($connection, $query);
        $monthData = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $monthData[$row['test_type']] = (int)$row['count'];
        }
        
        // For monthly distribution, we'll sum up all test types across months
        foreach ($monthData as $testType => $count) {
            if (!isset($data['values'][$testType])) {
                $data['values'][$testType] = 0;
            }
            $data['values'][$testType] += $count;
        }
    }
    
    // Format the monthly data for chart
    arsort($data['values']);
    $data['labels'] = array_keys($data['values']);
    $data['values'] = array_values($data['values']);
    
} else { // yearly
    $currentYear = date('Y');
    
    for ($year = $currentYear - 4; $year <= $currentYear; $year++) {
        // If shift is specified, adjust the query
        $whereClause = "YEAR(update_date) = $year";
        if ($shift) {
            $shiftDates = [];
            for ($month = 1; $month <= 12; $month++) {
                $daysInMonth = cal_days_in_month(CAL_GREGORIAN, $month, $year);
                for ($day = 1; $day <= $daysInMonth; $day++) {
                    $date = date('Y-m-d', mktime(0, 0, 0, $month, $day, $year));
                    $shiftRange = getShiftRange($shift, $date);
                    $shiftDates[] = "(update_date BETWEEN '{$shiftRange[0]}' AND '{$shiftRange[1]}')";
                }
            }
            $whereClause = "(" . implode(" OR ", $shiftDates) . ")";
        }
        
        $query = "SELECT test_type, COUNT(*) as count FROM tbl_radiology 
                  WHERE $whereClause
                  GROUP BY test_type 
                  ORDER BY count DESC 
                  LIMIT 10";
        
        $result = mysqli_query($connection, $query);
        $yearData = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $yearData[$row['test_type']] = (int)$row['count'];
        }
        
        // For yearly distribution, we'll sum up all test types across years
        foreach ($yearData as $testType => $count) {
            if (!isset($data['values'][$testType])) {
                $data['values'][$testType] = 0;
            }
            $data['values'][$testType] += $count;
        }
    }
    
    // Format the yearly data for chart
    arsort($data['values']);
    $data['labels'] = array_keys($data['values']);
    $data['values'] = array_values($data['values']);
}

// Ensure we don't return empty data
if (empty($data['labels'])) {
    $data['labels'] = ['No Data'];
    $data['values'] = [0];
}

header('Content-Type: application/json');
echo json_encode($data);
?>
