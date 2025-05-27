<?php
session_start();
include('includes/connection.php');

if (empty($_SESSION['name'])) {
    die("Unauthorized access");
}

if (!isset($_GET['newborn_id'])) {
    die("Invalid request");
}

$newbornId = mysqli_real_escape_string($connection, $_GET['newborn_id']);

// Fetch existing treatments
$query = "SELECT 
            m.id, 
            n.medicine_name as name, 
            n.medicine_brand as brand, 
            m.category, 
            n.total_quantity as quantity, 
            n.price, 
            m.expiration_date,
            m.quantity as available_quantity
          FROM tbl_treatment n
          JOIN tbl_medicines m ON n.medicine_name = m.medicine_name AND n.medicine_brand = m.medicine_brand
          WHERE n.newborn_id = '$newbornId'";

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