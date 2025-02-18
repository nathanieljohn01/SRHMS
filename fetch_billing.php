<?php
include('includes/connection.php');

$query = isset($_GET['query']) ? $_GET['query'] : '';
$patientType = isset($_GET['patient_type']) ? $_GET['patient_type'] : 'inpatient';

// Base query selection based on patient type
if ($patientType === 'hemodialysis') {
    $table = 'tbl_billing_hemodialysis';
} elseif ($patientType === 'newborn') {
    $table = 'tbl_billing_newborn';
} else {
    $table = 'tbl_billing_inpatient';
}

$sql = "
    SELECT b.*, 
           GROUP_CONCAT(o.item_name ORDER BY o.date_time DESC SEPARATOR ', ') AS other_items,
           GROUP_CONCAT(o.item_cost ORDER BY o.date_time DESC SEPARATOR ', ') AS other_costs
    FROM $table b
    LEFT JOIN tbl_billing_others o ON b.billing_id = o.billing_id
    WHERE b.deleted = 0
";

if (!empty($query)) {
    $sql .= " AND (
        b.billing_id LIKE '%$query%'
        OR b.patient_id LIKE '%$query%'
        OR b.patient_name LIKE '%$query%'
        OR b.diagnosis LIKE '%$query%'
        OR b.address LIKE '%$query%'
        OR DATE_FORMAT(b.admission_date, '%M %d, %Y %l:%i %p') LIKE '%$query%'
        OR DATE_FORMAT(b.discharge_date, '%M %d, %Y %l:%i %p') LIKE '%$query%'
        OR DATE_FORMAT(b.transaction_datetime, '%M %d, %Y %l:%i %p') LIKE '%$query%'
    )";
}

$sql .= " GROUP BY b.billing_id";

$result = mysqli_query($connection, $sql);
$data = array();

while ($row = mysqli_fetch_assoc($result)) {
    // Calculate age
    $dob = $row['dob'];
    $date = str_replace('/', '-', $dob);
    $dob = date('Y-m-d', strtotime($date));
    $year = (date('Y') - date('Y', strtotime($dob)));

    // Format dates
    $admission_date = date('F d, Y g:i A', strtotime($row['admission_date']));
    $discharge_date = date('F d, Y g:i A', strtotime($row['discharge_date']));
    $transaction_date = date('F d, Y g:i A', strtotime($row['transaction_datetime']));

    $data[] = array(
        'patient_info' => array(
            'billing_id' => $row['billing_id'],
            'patient_id' => $row['patient_id'],
            'patient_name' => $row['patient_name'],
            'age' => $year,
            'address' => $row['address'],
            'diagnosis' => $row['diagnosis'],
            'admission_date' => $admission_date,
            'discharge_date' => $discharge_date,
            'transaction_date' => $transaction_date
        ),
        'charges' => array(
            'room_fee' => number_format($row['room_fee'], 2),
            'lab_fee' => number_format($row['lab_fee'], 2),
            'rad_fee' => number_format($row['rad_fee'], 2),
            'medication_fee' => number_format($row['medication_fee'], 2),
            'operating_room_fee' => number_format($row['operating_room_fee'], 2),
            'supplies_fee' => number_format($row['supplies_fee'], 2),
            'other_items' => $row['other_items'] . ' (' . $row['other_costs'] . ')',
            'professional_fee' => number_format($row['professional_fee'], 2),
            'readers_fee' => number_format($row['readers_fee'], 2)
        ),
        'discounts' => array(
            'room_discount' => number_format($row['room_discount'], 2),
            'lab_discount' => number_format($row['lab_discount'], 2),
            'rad_discount' => number_format($row['rad_discount'], 2),
            'med_discount' => number_format($row['med_discount'], 2),
            'or_discount' => number_format($row['or_discount'], 2),
            'supplies_discount' => number_format($row['supplies_discount'], 2),
            'other_discount' => number_format($row['other_discount'], 2),
            'pf_discount' => number_format($row['pf_discount'], 2),
            'readers_discount' => number_format($row['readers_discount'], 2)
        ),
        'amounts' => array(
            'vat_exempt' => number_format($row['vat_exempt_discount_amount'], 2),
            'senior_discount' => number_format($row['discount_amount'], 2),
            'pwd_discount' => number_format($row['pwd_discount_amount'], 2),
            'first_case' => $row['first_case'],
            'second_case' => $row['second_case'],
            'philhealth_pf' => number_format($row['philhealth_pf'], 2),
            'philhealth_hb' => number_format($row['philhealth_hb'], 2),
            'subtotal' => number_format($row['non_discounted_total'], 2),
            'total_due' => number_format($row['total_due'], 2)
        )
    );
}

echo json_encode($data);
?>
