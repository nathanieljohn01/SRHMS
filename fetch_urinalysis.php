<?php
include('includes/connection.php');

$query = isset($_GET['query']) ? $_GET['query'] : '';

$sql = "SELECT * FROM tbl_urinalysis WHERE deleted = 0";

if(!empty($query)) {
    $sql .= " AND (
        urinalysis_id LIKE '%$query%'
        OR patient_id LIKE '%$query%'
        OR patient_name LIKE '%$query%'
        OR gender LIKE '%$query%'
        OR color LIKE '%$query%'
        OR transparency LIKE '%$query%'
        OR reaction LIKE '%$query%'
        OR protein LIKE '%$query%'
        OR glucose LIKE '%$query%'
        OR specific_gravity LIKE '%$query%'
        OR ketone LIKE '%$query%'
        OR urobilinogen LIKE '%$query%'
        OR pregnancy_test LIKE '%$query%'
        OR pus_cells LIKE '%$query%'
        OR red_blood_cells LIKE '%$query%'
        OR epithelial_cells LIKE '%$query%'
        OR a_urates_a_phosphates LIKE '%$query%'
        OR mucus_threads LIKE '%$query%'
        OR bacteria LIKE '%$query%'
        OR calcium_oxalates LIKE '%$query%'
        OR uric_acid_crystals LIKE '%$query%'
        OR pus_cells_clumps LIKE '%$query%'
        OR coarse_granular_cast LIKE '%$query%'
        OR hyaline_cast LIKE '%$query%'
        OR DATE_FORMAT(date_time, '%M %d, %Y %l:%i %p') LIKE '%$query%'
    )";
}

$result = mysqli_query($connection, $sql);
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
        'urinalysis_id' => $row['urinalysis_id'],
        'patient_id' => $row['patient_id'],
        'patient_name' => $row['patient_name'],
        'gender' => $row['gender'],
        'age' => $year,
        'date_time' => $date_time,
        'color' => $row['color'],
        'transparency' => $row['transparency'],
        'reaction' => $row['reaction'],
        'protein' => $row['protein'],
        'glucose' => $row['glucose'],
        'specific_gravity' => $row['specific_gravity'],
        'ketone' => $row['ketone'],
        'urobilinogen' => $row['urobilinogen'],
        'pregnancy_test' => $row['pregnancy_test'],
        'pus_cells' => $row['pus_cells'],
        'red_blood_cells' => $row['red_blood_cells'],
        'epithelial_cells' => $row['epithelial_cells'],
        'a_urates_a_phosphates' => $row['a_urates_a_phosphates'],
        'mucus_threads' => $row['mucus_threads'],
        'bacteria' => $row['bacteria'],
        'calcium_oxalates' => $row['calcium_oxalates'],
        'uric_acid_crystals' => $row['uric_acid_crystals'],
        'pus_cells_clumps' => $row['pus_cells_clumps'],
        'coarse_granular_cast' => $row['coarse_granular_cast'],
        'hyaline_cast' => $row['hyaline_cast']
    );
}

echo json_encode($data);
?>
