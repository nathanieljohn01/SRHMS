<?php
include('includes/connection.php');

if (isset($_GET['patient_name'])) {
    $patient_name = $_GET['patient_name'];
    
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
    FROM tbl_billing_inpatient 
    WHERE patient_name = ? 
    AND deleted = 0 
    AND (status IS NULL OR status = '')";
              
    $stmt = $connection->prepare($query);
    $stmt->bind_param("s", $patient_name);
    $stmt->execute();
    
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    
    header('Content-Type: application/json');
    echo json_encode($row);
}
?>
