<?php
session_start();
include('includes/connection.php');

header('Content-Type: application/json');

if (empty($_SESSION['name'])) {
    echo json_encode(['error' => 'Unauthorized access']);
    exit();
}

// Get and sanitize search parameters
$query = isset($_GET['query']) ? trim($_GET['query']) : '';
$status_filter = isset($_GET['status']) ? trim($_GET['status']) : '';

// Base SQL query
$sql = "SELECT 
            p.id as payment_id,
            p.patient_id,
            p.patient_name,
            p.patient_type,
            CASE
                WHEN p.patient_type = 'Inpatient' THEN bi.total_due
                WHEN p.patient_type = 'Hemodialysis' THEN bh.total_due
                WHEN p.patient_type = 'Newborn' THEN bn.total_due
                WHEN p.patient_type = 'Outpatient' THEN SUM(p.amount_to_pay)
                ELSE 0
            END as total_due,
            SUM(p.amount_paid) as total_paid,
            CASE 
                WHEN p.patient_type = 'Inpatient' THEN bi.remaining_balance
                WHEN p.patient_type = 'Hemodialysis' THEN bh.remaining_balance
                WHEN p.patient_type = 'Newborn' THEN bn.remaining_balance
                WHEN p.patient_type = 'Outpatient' THEN (SUM(p.amount_to_pay) - SUM(p.amount_paid))
                ELSE 0
            END as balance,
            CASE 
                WHEN (p.patient_type = 'Inpatient' AND bi.remaining_balance > 0) OR 
                     (p.patient_type = 'Hemodialysis' AND bh.remaining_balance > 0) OR
                     (p.patient_type = 'Newborn' AND bn.remaining_balance > 0) OR
                     (p.patient_type = 'Outpatient' AND (SUM(p.amount_to_pay) - SUM(p.amount_paid)) > 0) THEN 'Partially Paid'
                ELSE 'Fully Paid'
            END as status
        FROM tbl_payment p
        LEFT JOIN (
            SELECT patient_name, patient_id, remaining_balance, billing_id, total_due
            FROM tbl_billing_inpatient
            WHERE deleted = 0 AND id IN (
                SELECT MAX(id)
                FROM tbl_billing_inpatient
                GROUP BY patient_id
            )
        ) bi ON p.patient_name = bi.patient_name AND p.patient_type = 'Inpatient'
        LEFT JOIN (
            SELECT patient_name, patient_id, remaining_balance, billing_id, total_due
            FROM tbl_billing_hemodialysis
            WHERE deleted = 0 AND id IN (
                SELECT MAX(id)
                FROM tbl_billing_hemodialysis
                GROUP BY patient_id
            )
        ) bh ON p.patient_name = bh.patient_name AND p.patient_type = 'Hemodialysis'
        LEFT JOIN (
            SELECT patient_name, newborn_id as patient_id, remaining_balance, billing_id, total_due
            FROM tbl_billing_newborn
            WHERE deleted = 0 AND id IN (
                SELECT MAX(id)
                FROM tbl_billing_newborn
                GROUP BY newborn_id
            )
        ) bn ON p.patient_name = bn.patient_name AND p.patient_type = 'Newborn'
        WHERE p.deleted = 0";

// Add search filter if query exists
if (!empty($query)) {
    $safe_query = mysqli_real_escape_string($connection, $query);
    $sql .= " AND (p.patient_name LIKE '%$safe_query%' OR p.patient_id LIKE '%$safe_query%')";
}

$sql .= " GROUP BY p.patient_id, p.patient_name, p.patient_type";

// Add status filter if specified
if (!empty($status_filter)) {
    $safe_status = mysqli_real_escape_string($connection, $status_filter);
    $sql .= " HAVING status = '$safe_status'";
}

$sql .= " ORDER BY p.patient_name";

$result = mysqli_query($connection, $sql);

if (!$result) {
    echo json_encode(['error' => 'Database error: ' . mysqli_error($connection)]);
    exit();
}

$data = [];
while ($row = mysqli_fetch_assoc($result)) {
    // Format numeric values
    $row['total_due'] = number_format($row['total_due'], 2);
    $row['total_paid'] = number_format($row['total_paid'], 2);
    $row['balance'] = number_format($row['balance'], 2);
    
    $data[] = $row;
}

echo json_encode($data);
?>