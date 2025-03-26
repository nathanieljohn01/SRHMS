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
                WHEN p.patient_type = 'Hemodialysis' THEN bh.remaining_balance
                WHEN p.patient_type = 'Newborn' THEN bn.remaining_balance
                ELSE 0
            END as balance,
            CASE 
                WHEN (p.patient_type = 'Inpatient' AND bi.remaining_balance > 0) OR 
                     (p.patient_type = 'Hemodialysis' AND bh.remaining_balance > 0) OR
                     (p.patient_type = 'Newborn' AND bn.remaining_balance > 0) THEN 'Partially Paid'
                ELSE 'Fully Paid'
            END as status,
            COALESCE(bi.billing_id, bh.billing_id, bn.billing_id, '') as billing_id
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
        LEFT JOIN (
            SELECT patient_name, remaining_balance, billing_id, total_due
            FROM tbl_billing_hemodialysis
            WHERE id IN (
                SELECT MAX(id)
                FROM tbl_billing_hemodialysis
                GROUP BY patient_name, billing_id
            )
        ) bh ON p.patient_name = bh.patient_name AND p.patient_type = 'Hemodialysis'
        LEFT JOIN (
            SELECT patient_name, remaining_balance, billing_id, total_due
            FROM tbl_billing_newborn
            WHERE id IN (
                SELECT MAX(id)
                FROM tbl_billing_newborn
                GROUP BY patient_name, billing_id
            )
        ) bn ON p.patient_name = bn.patient_name AND p.patient_type = 'Newborn'
        WHERE p.deleted = 0";

if (!empty($query)) {
    $sql .= " AND (p.patient_id LIKE '%$query%' 
              OR p.patient_name LIKE '%$query%')";
}

if (!empty($patient_type)) {
    $sql .= " AND p.patient_type = '$patient_type'";
}

$sql .= " GROUP BY p.patient_id, p.patient_name, p.patient_type, 
          bi.total_due, bi.remaining_balance, bi.billing_id,
          bh.total_due, bh.remaining_balance, bh.billing_id,
          bn.total_due, bn.remaining_balance, bn.billing_id";

if (!empty($payment_status)) {
    if ($payment_status == 'Fully Paid') {
        $sql .= " HAVING status = 'Fully Paid'";
    } else {
        $sql .= " HAVING status = 'Partially Paid'";
    }
}

$sql .= " ORDER BY p.patient_name, COALESCE(bi.billing_id, bh.billing_id, bn.billing_id)";

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
