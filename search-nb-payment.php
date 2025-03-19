<?php
include('includes/connection.php');

if (isset($_GET['search'])) {
    $search = trim($_GET['search']);

    // Get newborns with unpaid bills
    $query = "
        SELECT DISTINCT 
            n.newborn_id,
            CONCAT(n.first_name, ' ', n.last_name) AS patient_name
        FROM tbl_newborn n
        INNER JOIN tbl_billing_newborn b ON n.newborn_id = b.newborn_id
        WHERE CONCAT(n.first_name, ' ', n.last_name) LIKE ?
            AND b.deleted = 0
            AND b.remaining_balance > 0
            AND b.status != 'Paid'
        GROUP BY n.newborn_id, n.first_name, n.last_name
        ORDER BY n.first_name, n.last_name
        LIMIT 10";

    if ($stmt = $connection->prepare($query)) {
        $search_term = "%{$search}%";
        $stmt->bind_param("s", $search_term);
        $stmt->execute();

        $result = $stmt->get_result();
        $newborns = array();

        while ($row = $result->fetch_assoc()) {
            $newborns[] = array(
                'newborn_id' => $row['newborn_id'],  // Keeping 'patient_id' for consistency in JS
                'patient_name' => $row['patient_name']
            );
        }

        header('Content-Type: application/json');
        echo json_encode($newborns);

        $stmt->close();
    }
}
?>
