<?php
include('includes/connection.php');

header('Content-Type: application/json');

try {
    $query = isset($_GET['query']) ? mysqli_real_escape_string($connection, $_GET['query']) : '';
    
    $sql = "SELECT 
                id,
                payment_id,
                patient_name, 
                patient_type,
                amount_to_pay as total_due,
                amount_to_pay,
                amount_paid,
                remaining_balance,
                payment_datetime
            FROM tbl_payment 
            WHERE deleted = 0";

    if (!empty($query)) {
        $sql .= " AND (
            payment_id LIKE '%$query%' OR 
            patient_name LIKE '%$query%' OR 
            patient_type LIKE '%$query%'
        )";
    }

    $sql .= " ORDER BY payment_datetime DESC";

    $result = mysqli_query($connection, $sql);
    
    if (!$result) {
        throw new Exception(mysqli_error($connection));
    }

    $data = array();
    while ($row = mysqli_fetch_assoc($result)) {
        $data[] = [
            'id' => $row['id'],
            'payment_id' => htmlspecialchars($row['payment_id']),
            'patient_name' => htmlspecialchars($row['patient_name']),
            'patient_type' => htmlspecialchars($row['patient_type']),
            'total_due' => '₱' . number_format($row['total_due'], 2),
            'amount_to_pay' => '₱' . number_format($row['amount_to_pay'], 2),
            'amount_paid' => '₱' . number_format($row['amount_paid'], 2),
            'remaining_balance' => '₱' . number_format($row['remaining_balance'], 2),
            'payment_datetime' => date('F d, Y g:i A', strtotime($row['payment_datetime']))
        ];
    }

    echo json_encode($data);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}