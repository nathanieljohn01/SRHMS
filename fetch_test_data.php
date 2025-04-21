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
    $currentDate = new DateTime('now', new DateTimeZone('Asia/Manila'));
    $startOfMonth = new DateTime('first day of this month', new DateTimeZone('Asia/Manila'));

    $daysInMonth = $startOfMonth->format('t');
    $firstDayOfWeek = $startOfMonth->format('N');
    $totalWeeks = ceil(($daysInMonth + $firstDayOfWeek - 1) / 7);

    for ($week = 1; $week <= $totalWeeks; $week++) {
        $weekStart = clone $startOfMonth;
        $daysToAdd = ($week - 1) * 7 - ($firstDayOfWeek - 1);
        if ($daysToAdd < 0) $daysToAdd = 0;
        $weekStart->add(new DateInterval('P'.$daysToAdd.'D'));

        $weekEnd = clone $weekStart;
        $weekEnd->add(new DateInterval('P6D'));

        if ($weekEnd > $currentDate) {
            $weekEnd = clone $currentDate;
        }
        if ($weekEnd->format('m') != $startOfMonth->format('m')) {
            $weekEnd = new DateTime('last day of this month', new DateTimeZone('Asia/Manila'));
        }

        $startDate = $weekStart->format('Y-m-d');
        $endDate = $weekEnd->format('Y-m-d');

        $whereClause = "created_at BETWEEN '$startDate 00:00:00' AND '$endDate 23:59:59'";
        if ($shift) {
            $shiftDates = [];
            $current = clone $weekStart;
            while ($current <= $weekEnd) {
                $shiftRange = getShiftRange($shift, $current->format('Y-m-d'));
                $shiftDates[] = "(created_at BETWEEN '{$shiftRange[0]}' AND '{$shiftRange[1]}')";
                $current->add(new DateInterval('P1D'));
            }
            $whereClause = "(" . implode(" OR ", $shiftDates) . ")";
        }

        $query = "SELECT COUNT(*) as count FROM tbl_laborder 
                  WHERE status " . ($testType === 'completed' ? "= 'Completed'" : "LIKE 'Cancelled%'") . "
                  AND $whereClause";

        $result = mysqli_query($connection, $query);
        $row = mysqli_fetch_assoc($result);

        $data['labels'][] = "Week $week";
        $data['values'][] = (int)$row['count'];
    }
} elseif ($timeRange === 'monthly') {
    $currentMonth = date('n');

    for ($month = 1; $month <= $currentMonth; $month++) {
        $monthName = date('F', mktime(0, 0, 0, $month, 1));

        $whereClause = "MONTH(created_at) = $month AND YEAR(created_at) = YEAR(NOW())";
        if ($shift) {
            $shiftDates = [];
            $daysInMonth = cal_days_in_month(CAL_GREGORIAN, $month, date('Y'));
            for ($day = 1; $day <= $daysInMonth; $day++) {
                $date = date('Y-m-d', mktime(0, 0, 0, $month, $day, date('Y')));
                $shiftRange = getShiftRange($shift, $date);
                $shiftDates[] = "(created_at BETWEEN '{$shiftRange[0]}' AND '{$shiftRange[1]}')";
            }
            $whereClause = "(" . implode(" OR ", $shiftDates) . ")";
        }

        $query = "SELECT COUNT(*) as count FROM tbl_laborder 
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
        $whereClause = "YEAR(created_at) = $year";
        if ($shift) {
            $shiftDates = [];
            for ($month = 1; $month <= 12; $month++) {
                $daysInMonth = cal_days_in_month(CAL_GREGORIAN, $month, $year);
                for ($day = 1; $day <= $daysInMonth; $day++) {
                    $date = date('Y-m-d', mktime(0, 0, 0, $month, $day, $year));
                    $shiftRange = getShiftRange($shift, $date);
                    $shiftDates[] = "(created_at BETWEEN '{$shiftRange[0]}' AND '{$shiftRange[1]}')";
                }
            }
            $whereClause = "(" . implode(" OR ", $shiftDates) . ")";
        }

        $query = "SELECT COUNT(*) as count FROM tbl_laborder 
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
