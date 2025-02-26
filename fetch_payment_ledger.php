<?php
include('includes/connection.php');

$query = isset($_GET['query']) ? $_GET['query'] : '';
$patient_type = isset($_GET['patient_type']) ? $_GET['patient_type'] : '';
$payment_status = isset($_GET['payment_status']) ? $_GET['payment_status'] : '';

$sql = "SELECT 
            p.patient_id,
            p.patient_name,
            p.patient_type,
            p.total_due,
            SUM(p.amount_paid) as total_paid,
            CASE 
                WHEN p.patient_type = 'Inpatient' THEN bi.remaining_balance
                ELSE (p.total_due - SUM(p.amount_paid))
            END as balance,
            CASE 
                WHEN p.patient_type = 'Inpatient' AND bi.remaining_balance <= 0 THEN 'Fully Paid'
                WHEN p.patient_type != 'Inpatient' AND (p.total_due <= SUM(p.amount_paid)) THEN 'Fully Paid'
                ELSE 'Partially Paid'
            END as status
        FROM tbl_payment p
        LEFT JOIN (
            SELECT patient_name, remaining_balance, billing_id
            FROM tbl_billing_inpatient
            WHERE id IN (
                SELECT MAX(id)
                FROM tbl_billing_inpatient
                GROUP BY patient_name
            )
        ) bi ON p.patient_name = bi.patient_name AND p.patient_type = 'Inpatient'
        WHERE p.deleted = 0";

if (!empty($query)) {
    $sql .= " AND (p.patient_id LIKE '%$query%' 
              OR p.patient_name LIKE '%$query%')";
}

if (!empty($patient_type)) {
    $sql .= " AND p.patient_type = '$patient_type'";
}

if (!empty($payment_status)) {
    if ($payment_status == 'Fully Paid') {
        $sql .= " GROUP BY p.patient_id, p.patient_name, p.patient_type, p.total_due, bi.remaining_balance, bi.billing_id 
                  HAVING status = 'Fully Paid'";
    } else {
        $sql .= " GROUP BY p.patient_id, p.patient_name, p.patient_type, p.total_due, bi.remaining_balance, bi.billing_id 
                  HAVING status = 'Partially Paid'";
    }
} else {
    $sql .= " GROUP BY p.patient_id, p.patient_name, p.patient_type, p.total_due, bi.remaining_balance, bi.billing_id";
}

$sql .= " ORDER BY p.patient_name";

$result = mysqli_query($connection, $sql);
$data = array();

while ($row = mysqli_fetch_assoc($result)) {
    $row['total_due'] = number_format($row['total_due'], 2);
    $row['total_paid'] = number_format($row['total_paid'], 2);
    $row['balance'] = number_format($row['balance'], 2);
    $data[] = $row;
}

echo json_encode($data);
?>
