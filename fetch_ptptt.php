<?php
include('includes/connection.php');

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Verify database connection
if (!$connection) {
    header('Content-Type: application/json');
    http_response_code(500);
    echo json_encode(['error' => 'Database connection failed: ' . mysqli_connect_error()]);
    exit;
}

$query = isset($_GET['query']) ? mysqli_real_escape_string($connection, $_GET['query']) : '';

try {
    // Log the query for debugging
    error_log("SQL Query: " . $query);

    $sql = "SELECT p.*, TIMESTAMPDIFF(YEAR, p.dob, CURDATE()) AS age 
            FROM tbl_ptptt p 
            WHERE p.deleted = 0";

    if (!empty($query)) {
        $sql .= " AND (
            p.ptptt_id LIKE '%$query%' OR
            p.patient_id LIKE '%$query%' OR
            p.patient_name LIKE '%$query%' OR
            p.pt_control LIKE '%$query%' OR
            p.pt_test LIKE '%$query%' OR
            p.pt_inr LIKE '%$query%' OR
            p.pt_activity LIKE '%$query%' OR
            p.ptt_control LIKE '%$query%' OR
            p.ptt_patient_result LIKE '%$query%' OR
            p.ptt_remarks LIKE '%$query%' OR
            DATE_FORMAT(p.date_time, '%M %d, %Y %h:%i %p') LIKE '%$query%'
        )";
    }

    $sql .= " ORDER BY p.date_time DESC";

    // Log the final SQL query for debugging
    error_log("Final SQL Query: " . $sql);

    $result = mysqli_query($connection, $sql);
    
    if (!$result) {
        throw new Exception("Query failed: " . mysqli_error($connection) . "\nSQL: " . $sql);
    }

    $data = [];

    while($row = mysqli_fetch_assoc($result)) {
        // Calculate age
        $dob = $row['dob'];
        $date = str_replace('/', '-', $dob);
        $dob = date('Y-m-d', strtotime($date));
        $year = (date('Y') - date('Y', strtotime($dob)));
        
        // Format date
        $date_time = date('F d, Y g:i A', strtotime($row['date_time']));

        $data[] = [
            'ptptt_id' => $row['ptptt_id'],
            'patient_id' => $row['patient_id'],
            'patient_name' => $row['patient_name'],
            'gender' => $row['gender'],
            'age' => $year,
            'date_time' => $date_time,
            'pt_control' => $row['pt_control'] ?? 'N/A',
            'pt_test' => $row['pt_test'] ?? 'N/A',
            'pt_inr' => $row['pt_inr'] ?? 'N/A',
            'pt_activity' => $row['pt_activity'] ?? 'N/A',
            'ptt_control' => $row['ptt_control'] ?? 'N/A',
            'ptt_patient_result' => $row['ptt_patient_result'] ?? 'N/A',
            'ptt_remarks' => $row['ptt_remarks'] ?? 'N/A'
        ];
    }

    header('Content-Type: application/json');
    echo json_encode($data);

} catch (Exception $e) {
    error_log("Error in fetch_ptptt.php: " . $e->getMessage());
    header('Content-Type: application/json');
    http_response_code(500);
    echo json_encode([
        'error' => 'Database error: ' . $e->getMessage(),
        'details' => [
            'message' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine()
        ]
    ]);
}
