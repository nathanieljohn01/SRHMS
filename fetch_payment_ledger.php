<?php
include('includes/connection.php');

$query = isset($_GET['query']) ? $_GET['query'] : '';
$patient_type = isset($_GET['patient_type']) ? $_GET['patient_type'] : '';
$payment_status = isset($_GET['payment_status']) ? $_GET['payment_status'] : '';

$sql = "SELECT 
            p.patient_id,
            p.patient_name,
            p.patient_type,
            CASE 
                WHEN p.patient_type = 'Inpatient' THEN bi.total_due
                ELSE p.total_due
            END as total_due,
            SUM(p.amount_paid) as total_paid,
            CASE 
                WHEN p.patient_type = 'Inpatient' THEN bi.remaining_balance
                ELSE 0
            END as balance,
            CASE 
                WHEN p.patient_type = 'Inpatient' AND bi.remaining_balance <= 0 THEN 'Fully Paid'
                WHEN p.patient_type != 'Inpatient' THEN 'Fully Paid'
                ELSE 'Partially Paid'
            END as status,
            COALESCE(bi.billing_id, '') as billing_id
        FROM tbl_payment p
        LEFT JOIN (
            SELECT patient_name, remaining_balance, billing_id, total_due
            FROM tbl_billing_inpatient
            WHERE id IN (
                SELECT MAX(id)
                FROM tbl_billing_inpatient
                GROUP BY patient_name, billing_id
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

$sql .= " GROUP BY p.patient_id, p.patient_name, p.patient_type, bi.total_due, bi.remaining_balance, bi.billing_id";

if (!empty($payment_status)) {
    if ($payment_status == 'Fully Paid') {
        $sql .= " HAVING status = 'Fully Paid'";
    } else {
        $sql .= " HAVING status = 'Partially Paid'";
    }
}

$sql .= " ORDER BY p.patient_name, bi.billing_id";

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
