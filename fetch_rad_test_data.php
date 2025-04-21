<?php
include('includes/connection.php');

// Set timezone to Philippines (UTC+8)
date_default_timezone_set('Asia/Manila');

$timeRange = $_GET['timeRange'] ?? 'monthly';
$testType = $_GET['testType'] ?? 'completed';
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
    $startOfMonth = new DateTime('first day of this month', new DateTimeZone('Asia/Manila'));
    $endOfMonth = new DateTime('last day of this month', new DateTimeZone('Asia/Manila'));

    $current = clone $startOfMonth;
    $week = 1;

    while ($current <= $endOfMonth) {
        // Get start of the week (Monday)
        $weekStart = clone $current;
        $dayOfWeek = $weekStart->format('N'); // 1 (Mon) to 7 (Sun)
        if ($dayOfWeek > 1) {
            $weekStart->modify('last monday');
        }

        // Get end of the week (Sunday)
        $weekEnd = clone $weekStart;
        $weekEnd->modify('+6 days');

        // Cap the weekEnd at end of the month
        if ($weekEnd > $endOfMonth) {
            $weekEnd = clone $endOfMonth;
        }

        // Format date range
        $startDate = $weekStart->format('Y-m-d');
        $endDate = $weekEnd->format('Y-m-d');

        // Default WHERE clause
        $whereClause = "update_date BETWEEN '$startDate 00:00:00' AND '$endDate 23:59:59'";

        // Add shift filtering if applicable
        if ($shift) {
            $shiftDates = [];
            $shiftCurrent = clone $weekStart;
            while ($shiftCurrent <= $weekEnd) {
                $shiftRange = getShiftRange($shift, $shiftCurrent->format('Y-m-d'));
                $shiftDates[] = "(update_date BETWEEN '{$shiftRange[0]}' AND '{$shiftRange[1]}')";
                $shiftCurrent->modify('+1 day');
            }
            $whereClause = "(" . implode(" OR ", $shiftDates) . ")";
        }

        // Query the count
        $query = "SELECT COUNT(*) as count FROM tbl_radiology 
                  WHERE status " . ($testType === 'completed' ? "= 'Completed'" : "LIKE 'Cancelled%'") . "
                  AND $whereClause";

        $result = mysqli_query($connection, $query);
        $row = mysqli_fetch_assoc($result);

        $data['labels'][] = "Week $week";
        $data['values'][] = (int)$row['count'];

        // Move to next week
        $current->modify('+7 days');
        $week++;
    }
} elseif ($timeRange === 'monthly') {
    $currentMonth = date('n');
    
    for ($month = 1; $month <= $currentMonth; $month++) {
        $monthName = date('F', mktime(0, 0, 0, $month, 1));
        
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
        
        $query = "SELECT COUNT(*) as count FROM tbl_radiology 
                  WHERE status " . ($testType === 'completed' ? "= 'Completed'" : "LIKE 'Cancelled%'") . "
                  AND $whereClause";
        
        $result = mysqli_query($connection, $query);
        $row = mysqli_fetch_assoc($result);
        
        $data['labels'][] = $monthName;
        $data['values'][] = (int)$row['count'];
    }
} else { // yearly
    $currentYear = date('Y');
    
    for ($year = $currentYear - 4; $year <= $currentYear; $year++) {
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
        
        $query = "SELECT COUNT(*) as count FROM tbl_radiology 
                  WHERE status " . ($testType === 'completed' ? "= 'Completed'" : "LIKE 'Cancelled%'") . "
                  AND $whereClause";
        
        $result = mysqli_query($connection, $query);
        $row = mysqli_fetch_assoc($result);
        
        $data['labels'][] = $year;
        $data['values'][] = (int)$row['count'];
    }
}

header('Content-Type: application/json');
echo json_encode($data);
?>
