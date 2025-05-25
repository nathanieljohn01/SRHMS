<?php
// Add these at the very top
header('Content-Type: application/json');
ob_start();

include('includes/connection.php');

// Function to sanitize inputs
function sanitize($connection, $input) {
    return mysqli_real_escape_string($connection, htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8'));
}

try {
    $query = isset($_GET['query']) ? sanitize($connection, $_GET['query']) : '';
    
    $sql = "SELECT *, 
            DATE_FORMAT(date_time, '%M %d, %Y %h:%i %p') AS formatted_date
            FROM tbl_ogtt 
            WHERE deleted = 0";

    if (!empty($query)) {
        $sql .= " AND (
            ogtt_id LIKE '%$query%'
            OR patient_id LIKE '%$query%'
            OR patient_name LIKE '%$query%'
            OR gender LIKE '%$query%'
            OR fbs LIKE '%$query%'
            OR first_hour LIKE '%$query%'
            OR second_hour LIKE '%$query%'
            OR third_hour LIKE '%$query%'
            OR DATE_FORMAT(date_time, '%M %d, %Y %h:%i %p') LIKE '%$query%'
        )";
    }

    $sql .= " ORDER BY date_time DESC";
    $result = mysqli_query($connection, $sql);

    if (!$result) {
        throw new Exception('Database error: ' . mysqli_error($connection));
    }

    $data = array();
    while($row = mysqli_fetch_assoc($result)) {
        // Calculate age
        $dob = $row['dob'];
        $date = str_replace('/', '-', $dob);
        $dob = date('Y-m-d', strtotime($date));
        $age = (date('Y') - date('Y', strtotime($dob)));
        
        $data[] = array(
            'ogtt_id' => $row['ogtt_id'],
            'patient_id' => $row['patient_id'],
            'patient_name' => $row['patient_name'],
            'gender' => $row['gender'],
            'age' => $age,
            'date_time' => $row['formatted_date'],
            'fbs' => $row['fbs'],
            'first_hour' => $row['first_hour'],
            'second_hour' => $row['second_hour'],
            'third_hour' => $row['third_hour']
        );
    }

    // Clear any output that might have been generated before
    ob_end_clean();
    echo json_encode($data);
    
} catch (Exception $e) {
    ob_end_clean();
    echo json_encode(['error' => $e->getMessage()]);
}
exit(); // Ensure no further output
?>