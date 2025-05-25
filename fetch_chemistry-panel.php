<?php
include('includes/connection.php');

header('Content-Type: application/json');

try {
    $query = isset($_GET['query']) ? mysqli_real_escape_string($connection, $_GET['query']) : '';

    $sql = "SELECT * FROM tbl_chemistry WHERE deleted = 0";

    if(!empty($query)) {
        $sql .= " AND (
            chem_id LIKE '%$query%'
            OR patient_id LIKE '%$query%'
            OR patient_name LIKE '%$query%'
            OR gender LIKE '%$query%'
            OR fbs LIKE '%$query%'
            OR ppbs LIKE '%$query%'
            OR bun LIKE '%$query%'
            OR crea LIKE '%$query%'
            OR bua LIKE '%$query%'
            OR tc LIKE '%$query%'
            OR tg LIKE '%$query%'
            OR hdl LIKE '%$query%'
            OR ldl LIKE '%$query%'
            OR vldl LIKE '%$query%'
            OR ast LIKE '%$query%'
            OR alt LIKE '%$query%'
            OR alp LIKE '%$query%'
            OR remarks LIKE '%$query%'
            OR DATE_FORMAT(date_time, '%M %d, %Y %l:%i %p') LIKE '%$query%'
        )";
    }

    $sql .= " ORDER BY date_time DESC";

    $result = mysqli_query($connection, $sql);
    
    if (!$result) {
        throw new Exception(mysqli_error($connection));
    }
    
    $data = array();

    while($row = mysqli_fetch_assoc($result)) {
        // Calculate age
        $dob = $row['dob'];
        $date = str_replace('/', '-', $dob);
        $dob = date('Y-m-d', strtotime($date));
        $year = (date('Y') - date('Y', strtotime($dob)));
        
        // Format date
        $date_time = date('F d, Y g:i A', strtotime($row['date_time']));
        
        $data[] = array(
            'chem_id' => $row['chem_id'] ?? 'N/A',
            'patient_id' => $row['patient_id'] ?? 'N/A',
            'patient_name' => $row['patient_name'] ?? 'N/A',
            'gender' => $row['gender'] ?? 'N/A',
            'age' => $year ?? 'N/A',
            'date_time' => $date_time ?? 'N/A',
            'fbs' => $row['fbs'] ?? 'N/A',
            'ppbs' => $row['ppbs'] ?? 'N/A',
            'bun' => $row['bun'] ?? 'N/A',
            'crea' => $row['crea'] ?? 'N/A',
            'bua' => $row['bua'] ?? 'N/A',
            'tc' => $row['tc'] ?? 'N/A',
            'tg' => $row['tg'] ?? 'N/A',
            'hdl' => $row['hdl'] ?? 'N/A',
            'ldl' => $row['ldl'] ?? 'N/A',
            'vldl' => $row['vldl'] ?? 'N/A',
            'ast' => $row['ast'] ?? 'N/A',
            'alt' => $row['alt'] ?? 'N/A',
            'alp' => $row['alp'] ?? 'N/A',
            'remarks' => $row['remarks'] ?? 'N/A'
        );
    }

    echo json_encode($data);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(array(
        'error' => true,
        'message' => 'An error occurred while fetching records: ' . $e->getMessage()
    ));
}
?>
