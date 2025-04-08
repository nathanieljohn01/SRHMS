<?php
session_start();
if (empty($_SESSION['name'])) {
    header('HTTP/1.1 401 Unauthorized');
    die(json_encode(['error' => 'Unauthorized']));
}

include('includes/connection.php');

$patientId = $_GET['patient_id'] ?? '';

if (empty($patientId)) {
    header('HTTP/1.1 400 Bad Request');
    die(json_encode(['error' => 'Patient ID is required']));
}

$query = "SELECT 
            p.id as payment_id,
            p.total_due,
            p.amount_to_pay,
            p.amount_paid,
            p.remaining_balance,
            DATE_FORMAT(p.payment_datetime, '%M %d, %Y %h:%i %p') as payment_datetime
          FROM tbl_payment p
          WHERE p.patient_id = ?
          AND p.deleted = 0
          ORDER BY p.payment_datetime";
          
$stmt = $connection->prepare($query);
$stmt->bind_param("s", $patientId);
$stmt->execute();
$result = $stmt->get_result();

$payments = [];
while ($row = $result->fetch_assoc()) {
    $row['total_due'] = number_format($row['total_due'], 2);
    $row['amount_to_pay'] = number_format($row['amount_to_pay'], 2);
    $row['amount_paid'] = number_format($row['amount_paid'], 2);
    $row['remaining_balance'] = number_format($row['remaining_balance'], 2);
    $row['payment_id'] = 'PAY-' . $row['payment_id'];  // Add this line
    $payments[] = $row;
}

header('Content-Type: application/json');
echo json_encode([
    'success' => true,
    'data' => $payments
]);