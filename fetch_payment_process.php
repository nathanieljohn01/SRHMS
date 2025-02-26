<?php
include('includes/connection.php');

// Set proper content type
header('Content-Type: application/json');

try {
    // Get search query and sanitize it
    $query = isset($_GET['query']) ? mysqli_real_escape_string($connection, $_GET['query']) : '';
    $patient_type = isset($_GET['patient_type']) ? $_GET['patient_type'] : '';

    $sql = "SELECT * FROM tbl_payment WHERE deleted = 0";

    if (!empty($query)) {
        $sql .= " AND (
            payment_id LIKE '%" . $query . "%' OR 
            patient_name LIKE '%" . $query . "%' OR 
            patient_type LIKE '%" . $query . "%' OR
            amount_to_pay LIKE '%" . $query . "%' OR
            amount_paid LIKE '%" . $query . "%' OR
            remaining_balance LIKE '%" . $query . "%'
        )";
    }

    if (!empty($patient_type)) {
        $sql .= " AND patient_type = '$patient_type'";
    }

    $sql .= " ORDER BY payment_datetime DESC";

    $result = mysqli_query($connection, $sql);
    
    if (!$result) {
        throw new Exception(mysqli_error($connection));
    }

    $data = array();
    while ($row = mysqli_fetch_assoc($result)) {
        // Format the data
        $row['payment_datetime'] = date('F d, Y g:i A', strtotime($row['payment_datetime']));
        $row['total_due'] = number_format($row['amount_to_pay'], 2, '.', '');
        $row['amount_to_pay'] = number_format($row['amount_to_pay'], 2, '.', '');
        $row['amount_paid'] = number_format($row['amount_paid'], 2, '.', '');
        $row['remaining_balance'] = number_format($row['remaining_balance'], 2, '.', '');
        
        // Sanitize the output
        $row['payment_id'] = htmlspecialchars($row['payment_id'], ENT_QUOTES, 'UTF-8');
        $row['patient_name'] = htmlspecialchars($row['patient_name'], ENT_QUOTES, 'UTF-8');
        $row['patient_type'] = htmlspecialchars($row['patient_type'], ENT_QUOTES, 'UTF-8');
        
        $data[] = $row;
    }

    echo json_encode($data);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>
