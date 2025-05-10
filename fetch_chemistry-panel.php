<?php
include('includes/connection.php');

$query = isset($_GET['query']) ? mysqli_real_escape_string($connection, $_GET['query']) : '';

$sql = "SELECT * FROM tbl_employee WHERE deleted = 0";

if(!empty($query)) {
    $sql .= " AND (
        first_name LIKE '%$query%'
        OR last_name LIKE '%$query%'
        OR username LIKE '%$query%'
        OR emailid LIKE '%$query%'
        OR phone LIKE '%$query%'
        OR specialization LIKE '%$query%'
        OR joining_date LIKE '%$query%'
        OR CASE 
            WHEN role = '3' THEN 'Nurse 1'
            WHEN role = '10' THEN 'Nurse 2'
            WHEN role = '2' THEN 'Doctor'
            WHEN role = '1' THEN 'Admin'
            WHEN role = '4' THEN 'Pharmacist'
            WHEN role = '5' THEN 'Medtech'
            WHEN role = '6' THEN 'Radtech'
            WHEN role = '7' THEN 'Billing Clerk'
            WHEN role = '8' THEN 'Cashier'
            WHEN role = '9' THEN 'Housekeeping Attendant'
        END LIKE '%$query%'
    )";
}

$result = mysqli_query($connection, $sql);
$data = array();

while($row = mysqli_fetch_assoc($result)) {
    // Get role text
    $role_text = '';
    switch ($row['role']) {
        case "3": $role_text = '<span class="custom-badge status-green">Nurse 1</span>'; break;
        case "10": $role_text = '<span class="custom-badge status-green">Nurse 2</span>'; break;
        case "2": $role_text = '<span class="custom-badge status-red">Doctor</span>'; break;
        case "1": $role_text = '<span class="custom-badge status-grey">Admin</span>'; break;
        case "4": $role_text = '<span class="custom-badge status-blue">Pharmacist</span>'; break;
        case "5": $role_text = '<span class="custom-badge status-purple">Medtech</span>'; break;
        case "6": $role_text = '<span class="custom-badge status-orange">Radtech</span>'; break;
        case "7": $role_text = '<span class="custom-badge status-purple">Billing Clerk</span>'; break;
        case "8": $role_text = '<span class="custom-badge status-pink">Cashier</span>'; break;
        case "9": $role_text = '<span class="custom-badge status-grey">Housekeeping Attendant</span>'; break;
    }

    $data[] = array(
        'id' => $row['id'],
        'name' => $row['first_name'] . " " . $row['last_name'],
        'username' => $row['username'],
        'emailid' => $row['emailid'],
        'phone' => $row['phone'],
        'specialization' => $row['specialization'],
        'joining_date' => $row['joining_date'],
        'role' => $role_text
    );
}

echo json_encode($data);
?>
