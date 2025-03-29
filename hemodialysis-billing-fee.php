<?php
include('includes/connection.php');

if (isset($_GET['patient_name'])) {
    $patient_name = $_GET['patient_name'];
    
    // First, get the hemodialysis billing details
    $query = "SELECT 
        room_fee, medication_fee, lab_fee, rad_fee, operating_room_fee,
        supplies_fee, others_fee, professional_fee, readers_fee,
        room_discount, lab_discount, rad_discount, med_discount,
        or_discount, supplies_discount, other_discount, pf_discount,
        readers_discount, philhealth_pf, philhealth_hb, 
        vat_exempt_discount_amount, discount_amount,
        pwd_discount_amount, total_due, remaining_balance, non_discounted_total,
        (room_fee - COALESCE(room_discount, 0)) as net_room_fee,
        (medication_fee - COALESCE(med_discount, 0)) as net_medication_fee,
        (lab_fee - COALESCE(lab_discount, 0)) as net_lab_fee,
        (rad_fee - COALESCE(rad_discount, 0)) as net_rad_fee,
        (operating_room_fee - COALESCE(or_discount, 0)) as net_or_fee,
        (supplies_fee - COALESCE(supplies_discount, 0)) as net_supplies_fee,
        (others_fee - COALESCE(other_discount, 0)) as net_others_fee,
        (professional_fee - COALESCE(pf_discount, 0)) as net_pf_fee,
        (readers_fee - COALESCE(readers_discount, 0)) as net_readers_fee,
        (COALESCE(room_discount, 0) + COALESCE(med_discount, 0) + 
         COALESCE(lab_discount, 0) + COALESCE(rad_discount, 0) + 
         COALESCE(or_discount, 0) + COALESCE(supplies_discount, 0) + 
         COALESCE(other_discount, 0) + COALESCE(pf_discount, 0) + 
         COALESCE(readers_discount, 0) + COALESCE(philhealth_pf, 0) + 
         COALESCE(philhealth_hb, 0) + COALESCE(vat_exempt_discount_amount, 0) + 
         COALESCE(discount_amount, 0) + COALESCE(pwd_discount_amount, 0)) as total_discount,
        (room_fee + medication_fee + lab_fee + rad_fee + operating_room_fee + 
         supplies_fee + others_fee + professional_fee + readers_fee) as total_amount
    FROM tbl_billing_hemodialysis 
    WHERE patient_name = ? 
    AND deleted = 0 
    ORDER BY id DESC 
    LIMIT 1";
              
    $stmt = $connection->prepare($query);
    $stmt->bind_param("s", $patient_name);
    $stmt->execute();
    
    $result = $stmt->get_result();
    $billingData = $result->fetch_assoc();
    
    // Get total payments made by this patient for hemodialysis
    $paymentQuery = "SELECT COALESCE(SUM(amount_paid), 0) as total_paid 
                    FROM tbl_payment 
                    WHERE patient_name = ?
                    AND patient_type = 'Hemodialysis'";
    $paymentStmt = $connection->prepare($paymentQuery);
    $paymentStmt->bind_param("s", $patient_name);
    $paymentStmt->execute();
    $paymentResult = $paymentStmt->get_result();
    $paymentData = $paymentResult->fetch_assoc();
    
    // Calculate remaining balance if not already set in billing data
    if (!isset($billingData['remaining_balance']) || $billingData['remaining_balance'] === null) {
        $totalAmount = $billingData['total_amount'];
        $totalDiscount = $billingData['total_discount'];
        $totalPaid = $paymentData['total_paid'];
        
        $billingData['remaining_balance'] = $totalAmount - $totalDiscount - $totalPaid;
    }
    
    // Ensure remaining balance is not negative
    $billingData['remaining_balance'] = max(0, $billingData['remaining_balance']);
    
    header('Content-Type: application/json');
    echo json_encode($billingData);
}
?>