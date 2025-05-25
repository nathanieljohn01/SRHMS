<?php
session_start();
include('includes/connection.php');

if (empty($_SESSION['name'])) {
    die("Unauthorized access");
}

if (!isset($_GET['inpatient_id'])) {
    die("Invalid request");
}

$inpatientId = mysqli_real_escape_string($connection, $_GET['inpatient_id']);

// Fetch existing treatments
$query = "SELECT 
            m.id, 
            t.medicine_name as name, 
            t.medicine_brand as brand, 
            m.category, 
            t.total_quantity as quantity, 
            t.price, 
            m.expiration_date,
            m.quantity as available_quantity
          FROM tbl_treatment t
          JOIN tbl_medicines m ON t.medicine_name = m.medicine_name AND t.medicine_brand = m.medicine_brand
          WHERE t.inpatient_id = '$inpatientId'";

$result = mysqli_query($connection, $query);
$treatments = [];

while ($row = mysqli_fetch_assoc($result)) {
    // Calculate available quantity by adding back the currently allocated quantity
    $row['available_quantity'] += $row['quantity'];
    $treatments[] = $row;
}

header('Content-Type: application/json');
echo json_encode($treatments);
?>