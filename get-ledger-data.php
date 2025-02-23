<?php
include('includes/connection.php');

// Get DataTables parameters
$draw = $_POST['draw'];
$start = $_POST['start'];
$length = $_POST['length'];
$search = $_POST['search']['value'];
$patientType = $_POST['patientType'];
$paymentStatus = $_POST['paymentStatus'];
$searchTerm = $_POST['searchTerm'];

// Base query
$query = "SELECT 
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
            SELECT patient_name, remaining_balance
            FROM tbl_billing_inpatient
            WHERE id IN (
                SELECT MAX(id)
                FROM tbl_billing_inpatient
                GROUP BY patient_name
            )
        ) bi ON p.patient_name = bi.patient_name AND p.patient_type = 'Inpatient'
        WHERE p.deleted = 0";

// Apply filters
if ($patientType) {
    $query .= " AND p.patient_type = '$patientType'";
}

if ($paymentStatus) {
    if ($paymentStatus == 'Fully Paid') {
        $query .= " HAVING status = 'Fully Paid'";
    } else {
        $query .= " HAVING status = 'Partially Paid'";
    }
}

if ($searchTerm) {
    $query .= " AND (p.patient_name LIKE '%$searchTerm%' OR p.patient_id LIKE '%$searchTerm%')";
}

// Group by clause
$query .= " GROUP BY p.patient_id, p.patient_name, p.patient_type, p.total_due, bi.remaining_balance";

// Get total count for pagination
$countQuery = "SELECT COUNT(DISTINCT p.patient_id) as total FROM tbl_payment p WHERE p.deleted = 0";
if ($patientType) {
    $countQuery .= " AND p.patient_type = '$patientType'";
}
if ($searchTerm) {
    $countQuery .= " AND (p.patient_name LIKE '%$searchTerm%' OR p.patient_id LIKE '%$searchTerm%')";
}
$totalRecords = mysqli_fetch_assoc(mysqli_query($connection, $countQuery))['total'];

// Apply ordering
$orderColumn = isset($_POST['order'][0]['column']) ? $_POST['order'][0]['column'] : 1;
$orderDir = isset($_POST['order'][0]['dir']) ? $_POST['order'][0]['dir'] : 'asc';
$columns = array('patient_id', 'patient_name', 'patient_type', 'total_due', 'total_paid', 'balance', 'status');
$orderBy = $columns[$orderColumn];
$query .= " ORDER BY $orderBy $orderDir";

// Apply pagination
$query .= " LIMIT $start, $length";

// Execute final query
$result = mysqli_query($connection, $query);
$data = array();

while ($row = mysqli_fetch_assoc($result)) {
    $data[] = array(
        'patient_id' => $row['patient_id'],
        'patient_name' => $row['patient_name'],
        'patient_type' => $row['patient_type'],
        'total_due' => $row['total_due'],
        'total_paid' => $row['total_paid'],
        'balance' => $row['balance'],
        'status' => $row['status']
    );
}

// Prepare response
$response = array(
    "draw" => intval($draw),
    "recordsTotal" => intval($totalRecords),
    "recordsFiltered" => intval($totalRecords),
    "data" => $data
);

echo json_encode($response);
?>
