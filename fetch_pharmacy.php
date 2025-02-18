<?php
include('includes/connection.php');

$query = isset($_GET['query']) ? trim($_GET['query']) : '';
$search_term = "%{$query}%";

$sql = "SELECT 
            invoice_id, 
            patient_id, 
            patient_name,
            GROUP_CONCAT(
                CONCAT(
                    medicine_name, 
                    ' - ', 
                    medicine_brand, 
                    ' (₱', price, ') - ', 
                    quantity, 
                    ' pcs (₱', price * quantity, ')'
                ) 
                SEPARATOR '\n'
            ) as medicine_details,
            SUM(price * quantity) as total_price, 
            invoice_datetime
        FROM tbl_pharmacy_invoice
        WHERE deleted = 0
        AND (
            patient_id LIKE ? OR
            invoice_id LIKE ? OR
            patient_name LIKE ? OR
            medicine_name LIKE ? OR
            medicine_brand LIKE ?
        )
        GROUP BY invoice_id
        ORDER BY invoice_datetime DESC";

$stmt = mysqli_prepare($connection, $sql);
mysqli_stmt_bind_param($stmt, 'sssss', 
    $search_term, $search_term, $search_term, $search_term, $search_term
);

mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$invoices = array();

while ($row = mysqli_fetch_assoc($result)) {
    $medicine_details = nl2br(htmlspecialchars($row['medicine_details']));
    
    $invoices[] = array(
        'patient_id' => htmlspecialchars($row['patient_id']),
        'invoice_id' => htmlspecialchars($row['invoice_id']),
        'patient_name' => htmlspecialchars($row['patient_name']),
        'medicine_details' => $medicine_details,
        'total_price' => number_format($row['total_price'], 2),
        'invoice_datetime' => date('F d, Y g:i A', strtotime($row['invoice_datetime']))
    );
}

echo json_encode($invoices);
