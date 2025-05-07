<?php
include('includes/connection.php');

if(isset($_POST['patientId'])) {
    $patientId = $_POST['patientId'];
    $output = '';
    
    // Get patient information
    $patient_query = $connection->prepare("SELECT first_name, last_name, dob, gender FROM tbl_patient WHERE patient_id = ?");
    $patient_query->bind_param('s', $patientId);
    $patient_query->execute();
    $patient_result = $patient_query->get_result();
    $patient_data = $patient_result->fetch_assoc();

    $patient_name = htmlspecialchars($patient_data['first_name'] . ' ' . $patient_data['last_name']);
    $dob = !empty($patient_data['dob']) ? date('F d, Y', strtotime($patient_data['dob'])) : 'N/A';
    $raw_dob = str_replace('/', '-', $patient_data['dob']);
    $formatted_dob = date('Y-m-d', strtotime($raw_dob));
    $age = date_diff(date_create($formatted_dob), date_create('today'))->y;
    $gender = htmlspecialchars($patient_data['gender']);
    $current_date = date('F d, Y');

    // Patient information header
    $output .= '
    <div class="card mb-3">
        <div class="card-body">
            <h5 class="card-title">Patient Information</h5>
            <br>
            <div class="row">
                <div class="col-md-6">
                    <p><strong>Patient Name:</strong> '.$patient_name.'</p>
                    <p><strong>Age:</strong> '.$age.'</p>
                </div>
                <div class="col-md-6">
                    <p><strong>Gender:</strong> '.$gender.'</p>
                    <p><strong>Report Date:</strong> '.$current_date.'</p>
                </div>
            </div>
        </div>
    </div>';

    // Function to create a result table
    function createResultTable($title, $results) {
        if (empty($results)) return '';
        
        $table = '
        <div class="card mb-3">
            <div class="card-header">
                <h5 class="card-title mb-0">'.$title.'</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Time</th>
                                <th>Results</th>
                            </tr>
                        </thead>
                        <tbody>';
        
        foreach ($results as $row) {
            $datetime = new DateTime($row['date_time']);
            $table .= '
                <tr>
                    <td>'.$datetime->format('M d, Y').'</td>
                    <td>'.$datetime->format('h:i A').'</td>
                    <td style="white-space: pre-wrap;">'.$row['results'].'</td>
                </tr>';
        }
        
        $table .= '
                        </tbody>
                    </table>
                </div>
            </div>
        </div>';
        
        return $table;
    }

    // Fetch CBC records
    $cbcResults = [];
    $cbcQuery = $connection->prepare("
        SELECT date_time, 
               CONCAT(
                   'Hemoglobin: ', hemoglobin, ' g/dL\n',
                   'Hematocrit: ', hematocrit, ' %\n',
                   'RBC Count: ', red_blood_cells, ' x10^6/uL\n',
                   'WBC Count: ', white_blood_cells, ' x10^3/uL\n',
                   'ESR: ', esr, ' mm/hr\n',
                   'Segmenters: ', segmenters, ' %\n',
                   'Lymphocytes: ', lymphocytes, ' %\n',
                   'Monocytes: ', monocytes, ' %\n',
                   'Eosinophils: ', eosinophils, ' %\n',
                   'Bands: ', bands, ' %\n',
                   'Platelets: ', platelets, ' x10^3/uL\n'
               ) AS results
        FROM tbl_cbc
        WHERE patient_id = ? AND deleted = 0
        ORDER BY date_time DESC"
    );
    $cbcQuery->bind_param('s', $patientId);
    $cbcQuery->execute();
    $cbcResult = $cbcQuery->get_result();
    while ($row = $cbcResult->fetch_assoc()) {
        $cbcResults[] = $row;
    }
    $output .= createResultTable('COMPLETE BLOOD COUNT (CBC)', $cbcResults);

    // Fetch PBS records
    $pbsResults = [];
    $pbsQuery = $connection->prepare("
        SELECT date_time, 
               CONCAT(
                   'RBC Morphology: ', rbc_morphology, '\n',
                   'Platelet Count: ', platelet_count, ' K/uL\n',
                   'Toxic Granules: ', toxic_granules, '\n',
                   'Abnormal Cells: ', abnormal_cells, '\n',
                   'Segmenters: ', segmenters, ' %\n',
                   'Lymphocytes: ', lymphocytes, ' %\n',
                   'Monocytes: ', monocytes, ' %\n',
                   'Eosinophils: ', eosinophils, ' %\n',
                   'Bands: ', bands, ' %\n',
                   'Reticulocyte Count: ', reticulocyte_count, ' %\n',
                   'Remarks: ', IFNULL(remarks, 'None')
               ) AS results
        FROM tbl_pbs
        WHERE patient_id = ? AND deleted = 0
        ORDER BY date_time DESC"
    );
    $pbsQuery->bind_param('s', $patientId);
    $pbsQuery->execute();
    $pbsResult = $pbsQuery->get_result();
    while ($row = $pbsResult->fetch_assoc()) {
        $pbsResults[] = $row;
    }
    $output .= createResultTable('PERIPHERAL BLOOD SMEAR (PBS)', $pbsResults);

    // Fetch PT/PTT records
    $ptpttResults = [];
    $ptpttQuery = $connection->prepare("
        SELECT date_time, 
               CONCAT(
                   'PT Control: ', pt_control, ' sec\n',
                   'PT Test: ', pt_test, ' sec\n',
                   'PT INR: ', pt_inr, '\n',
                   'PT Activity: ', pt_activity, ' %\n',
                   'PTT Control: ', ptt_control, ' sec\n',
                   'PTT Patient: ', ptt_patient_result, ' sec\n',
                   'Remarks: ', IFNULL(ptt_remarks, 'None')
               ) AS results
        FROM tbl_ptptt
        WHERE patient_id = ? AND deleted = 0
        ORDER BY date_time DESC"
    );
    $ptpttQuery->bind_param('s', $patientId);
    $ptpttQuery->execute();
    $ptpttResult = $ptpttQuery->get_result();
    while ($row = $ptpttResult->fetch_assoc()) {
        $ptpttResults[] = $row;
    }
    $output .= createResultTable('PT/PTT COAGULATION TEST', $ptpttResults);

    // Fetch Urinalysis records
    $urinalysisResults = [];
    $urinalysisQuery = $connection->prepare("
        SELECT date_time, 
               CONCAT(
                   'MACROSCOPIC:\n',
                   'Color: ', IFNULL(color, 'N/A'), '\n',
                   'Transparency: ', IFNULL(transparency, 'N/A'), '\n',
                   'Reaction: ', IFNULL(reaction, 'N/A'), '\n',
                   'Protein: ', IFNULL(protein, 'N/A'), '\n',
                   'Glucose: ', IFNULL(glucose, 'N/A'), '\n',
                   'Specific Gravity: ', IFNULL(specific_gravity, 'N/A'), '\n',
                   'Ketone: ', IFNULL(ketone, 'N/A'), '\n',
                   'Urobilinogen: ', IFNULL(urobilinogen, 'N/A'), '\n',
                   'Pregnancy Test: ', IFNULL(pregnancy_test, 'N/A'), '\n\n',
                   'MICROSCOPIC:\n',
                   'Pus Cells: ', IFNULL(pus_cells, 'N/A'), ' /hpf\n',
                   'RBC: ', IFNULL(red_blood_cells, 'N/A'), ' /hpf\n',
                   'Epithelial Cells: ', IFNULL(epithelial_cells, 'N/A'), ' /hpf\n',
                   'Urates/Phosphates: ', IFNULL(a_urates_a_phosphates, 'N/A'), '\n',
                   'Mucus Threads: ', IFNULL(mucus_threads, 'N/A'), '\n',
                   'Bacteria: ', IFNULL(bacteria, 'N/A'), '\n',
                   'Calcium Oxalates: ', IFNULL(calcium_oxalates, 'N/A'), '\n',
                   'Uric Acid Crystals: ', IFNULL(uric_acid_crystals, 'N/A'), '\n',
                   'Pus Cell Clumps: ', IFNULL(pus_cells_clumps, 'N/A'), '\n',
                   'Coarse Granular Cast: ', IFNULL(coarse_granular_cast, 'N/A'), '\n',
                   'Hyaline Cast: ', IFNULL(hyaline_cast, 'N/A')
               ) AS results
        FROM tbl_urinalysis
        WHERE patient_id = ?
        ORDER BY date_time DESC"
    );
    $urinalysisQuery->bind_param('s', $patientId);
    $urinalysisQuery->execute();
    $urinalysisResult = $urinalysisQuery->get_result();
    while ($row = $urinalysisResult->fetch_assoc()) {
        $urinalysisResults[] = $row;
    }
    $output .= createResultTable('URINALYSIS', $urinalysisResults);

    if (empty($cbcResults) && empty($pbsResults) && empty($ptpttResults) && empty($urinalysisResults)) {
        echo '<div class="alert alert-info">No laboratory results found for this patient.</div>';
    } else {
        echo $output;
    }
}
?>