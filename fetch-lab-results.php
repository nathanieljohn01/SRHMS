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
            
            // Add remarks if they exist
            if (!empty($row['remarks'])) {
                $table .= '
                <tr>
                    <td colspan="2"><strong>Remarks:</strong></td>
                    <td>'.htmlspecialchars($row['remarks']).'</td>
                </tr>';
            }
        }
        
        $table .= '
                        </tbody>
                    </table>
                </div>
            </div>
        </div>';
        
        return $table;
    }

    // Function to create crossmatching table
    function createCrossmatchingTable($title, $results) {
        if (empty($results)) return '';
        
        $table = '
        <div class="card mb-3">
            <div class="card-header">
                <h5 class="card-title mb-0">'.$title.'</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">';
        
        foreach ($results as $row) {
            // Format dates
            $date_time = date('F d, Y h:i A', strtotime($row['date_time']));
            $extraction_date = !empty($row['extraction_date']) ? date('F d, Y', strtotime($row['extraction_date'])) : 'N/A';
            $expiration_date = !empty($row['expiration_date']) ? date('F d, Y', strtotime($row['expiration_date'])) : 'N/A';
            $dated = !empty($row['dated']) ? date('F d, Y', strtotime($row['dated'])) : 'N/A';
            $time_packed = !empty($row['time_packed']) ? date('h:i A', strtotime($row['time_packed'])) : 'N/A';
            $to_be_consumed_before = !empty($row['to_be_consumed_before']) ? date('h:i A', strtotime($row['to_be_consumed_before'])) : 'N/A';

            $table .= '
                    <h6 class="mb-2">Patient Blood Information</h6>
                    <table class="table table-bordered mb-4">
                        <tr>
                            <th style="width: 30%">Patient Blood Type</th>
                            <td>'.htmlspecialchars($row['patient_blood_type']).'</td>
                        </tr>
                        <tr>
                            <th>Blood Component</th>
                            <td>'.htmlspecialchars($row['blood_component']).'</td>
                        </tr>
                        <tr>
                            <th>Serial Number</th>
                            <td>'.htmlspecialchars($row['serial_number']).'</td>
                        </tr>
                        <tr>
                            <th>Extraction Date</th>
                            <td>'.$extraction_date.'</td>
                        </tr>
                        <tr>
                            <th>Expiration Date</th>
                            <td>'.$expiration_date.'</td>
                        </tr>
                    </table>
                    
                    <h6 class="mb-2">Crossmatching Results</h6>
                    <table class="table table-bordered mb-4">
                        <tr>
                            <th style="width: 30%">Major Crossmatching</th>
                            <td>'.htmlspecialchars($row['major_crossmatching']).'</td>
                        </tr>
                        <tr>
                            <th>Donor\'s Blood Type</th>
                            <td>'.htmlspecialchars($row['donors_blood_type']).'</td>
                        </tr>
                        <tr>
                            <th>Packed Red Blood Cell</th>
                            <td>'.htmlspecialchars($row['packed_red_blood_cell']).'</td>
                        </tr>
                        <tr>
                            <th>Time Packed</th>
                            <td>'.$time_packed.'</td>
                        </tr>
                        <tr>
                            <th>Dated</th>
                            <td>'.$dated.'</td>
                        </tr>
                    </table>
                    
                    <h6 class="mb-2">System Information</h6>
                    <table class="table table-bordered">
                        <tr>
                            <th style="width: 30%">Open System</th>
                            <td>'.htmlspecialchars($row['open_system']).'</td>
                        </tr>
                        <tr>
                            <th>Closed System</th>
                            <td>'.htmlspecialchars($row['closed_system']).'</td>
                        </tr>
                        <tr>
                            <th>To Be Consumed Before</th>
                            <td>'.$to_be_consumed_before.'</td>
                        </tr>
                        <tr>
                            <th>Hours</th>
                            <td>'.htmlspecialchars($row['hours']).'</td>
                        </tr>
                        <tr>
                            <th>Minor Crossmatching</th>
                            <td>'.htmlspecialchars($row['minor_crossmatching']).'</td>
                        </tr>
                    </table>';
        }
        
        $table .= '
                </div>
            </div>
        </div>';
        
        return $table;
    }

    // Function to create Dengue Duo table
    function createDengueDuoTable($title, $results) {
        if (empty($results)) return '';
        
        $table = '
        <div class="card mb-3">
            <div class="card-header">
                <h5 class="card-title mb-0">'.$title.'</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">';
        
        foreach ($results as $row) {
            $date_time = date('F d, Y h:i A', strtotime($row['date_time']));
            $dob = date('Y-m-d', strtotime(str_replace('/', '-', $row['dob'])));
            $year = (date('Y') - date('Y', strtotime($dob)));
            
            $table .= '
                    <h6 class="mb-3">Test Date: '.$date_time.'</h6>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <p><strong>Patient Name:</strong> '.htmlspecialchars($row['patient_name']).'</p>
                        </div>
                        <div class="col-md-3">
                            <p><strong>Age:</strong> '.$year.'</p>
                        </div>
                        <div class="col-md-3">
                            <p><strong>Gender:</strong> '.htmlspecialchars($row['gender']).'</p>
                        </div>
                    </div>
                    
                    <table class="table table-bordered mb-4">
                        <thead>
                            <tr>
                                <th>Test</th>
                                <th>Result</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>NS1 Antigen</td>
                                <td>'.htmlspecialchars($row['ns1ag']).'</td>
                            </tr>
                            <tr>
                                <td>IgG</td>
                                <td>'.htmlspecialchars($row['igg']).'</td>
                            </tr>
                            <tr>
                                <td>IgM</td>
                                <td>'.htmlspecialchars($row['igm']).'</td>
                            </tr>
                        </tbody>
                    </table>
                    
                    <h6 class="mb-2">Interpretation:</h6>';
            
            $ns1ag = strtoupper($row['ns1ag']);
            $igg = strtoupper($row['igg']);
            $igm = strtoupper($row['igm']);
            
            if ($ns1ag == 'POSITIVE' && $igm == 'POSITIVE') {
                $table .= '<p>Results suggest an acute dengue infection.</p>';
            } elseif ($ns1ag == 'POSITIVE' && $igm == 'NEGATIVE') {
                $table .= '<p>Results suggest an early acute dengue infection.</p>';
            } elseif ($ns1ag == 'NEGATIVE' && $igm == 'POSITIVE') {
                $table .= '<p>Results suggest a recent dengue infection (within 2-4 weeks).</p>';
            } elseif ($igg == 'POSITIVE' && $igm == 'NEGATIVE') {
                $table .= '<p>Results suggest a past dengue infection.</p>';
            } else {
                $table .= '<p>No evidence of current or recent dengue infection.</p>';
            }
            
            if (!empty($row['remarks'])) {
                $table .= '
                    <h6 class="mb-2 mt-3">Remarks:</h6>
                    <p>'.htmlspecialchars($row['remarks']).'</p>';
            }
        }
        
        $table .= '
                </div>
            </div>
        </div>';
        
        return $table;
    }

    // ====================== HEMATOLOGY DEPARTMENT ======================
    $output .= '<div class="department-section mb-4">
                    <h4 class="department-title">HEMATOLOGY</h4>
                    <div class="department-content">';

    // Fetch CBC records (Hematology)
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

    // Fetch PBS records (Hematology)
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

    // Fetch PT/PTT records (Hematology)
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

    $output .= '</div></div>'; // Close Hematology department section

    // ====================== BLOOD BANK DEPARTMENT ======================
    $output .= '<div class="department-section mb-4">
                    <h4 class="department-title">BLOOD BANK</h4>
                    <div class="department-content">';

    // Fetch Crossmatching records (Blood Bank)
    $crossmatchingResults = [];
    $crossmatchingQuery = $connection->prepare("
        SELECT * FROM tbl_crossmatching 
        WHERE patient_id = ?
        ORDER BY date_time DESC"
    );
    $crossmatchingQuery->bind_param('s', $patientId);
    $crossmatchingQuery->execute();
    $crossmatchingResult = $crossmatchingQuery->get_result();
    while ($row = $crossmatchingResult->fetch_assoc()) {
        $crossmatchingResults[] = $row;
    }
    $output .= createCrossmatchingTable('BLOOD CROSSMATCHING', $crossmatchingResults);

    $output .= '</div></div>'; // Close Blood Bank department section

    // ====================== CLINICAL MICROSCOPY DEPARTMENT ======================
    $output .= '<div class="department-section mb-4">
                    <h4 class="department-title">CLINICAL MICROSCOPY</h4>
                    <div class="department-content">';

    // Fetch Urinalysis records (Clinical Microscopy)
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

    // Fetch Fecalysis records (Clinical Microscopy)
    $fecalysisResults = [];
    $fecalysisQuery = $connection->prepare("
        SELECT date_time, 
               CONCAT(
                   'MACROSCOPIC:\n',
                   'Color: ', IFNULL(color, 'N/A'), '\n',
                   'Consistency: ', IFNULL(consistency, 'N/A'), '\n',
                   'Occult Blood: ', IFNULL(occult_blood, 'N/A'), '\n\n',
                   'MICROSCOPIC:\n',
                   'Pus Cells: ', IFNULL(pus_cells, 'N/A'), '\n',
                   'Ova or Parasite: ', IFNULL(ova_or_parasite, 'N/A'), '\n',
                   'Yeast Cells: ', IFNULL(yeast_cells, 'N/A'), '\n',
                   'Fat Globules: ', IFNULL(fat_globules, 'N/A'), '\n',
                   'RBC: ', IFNULL(rbc, 'N/A'), '\n',
                   'Bacteria: ', IFNULL(bacteria, 'N/A')
               ) AS results
        FROM tbl_fecalysis
        WHERE patient_id = ?
        ORDER BY date_time DESC"
    );
    $fecalysisQuery->bind_param('s', $patientId);
    $fecalysisQuery->execute();
    $fecalysisResult = $fecalysisQuery->get_result();
    while ($row = $fecalysisResult->fetch_assoc()) {
        $fecalysisResults[] = $row;
    }
    $output .= createResultTable('FECALYSIS', $fecalysisResults);

    $output .= '</div></div>'; // Close Clinical Microscopy department section

    // ====================== CLINICAL CHEMISTRY DEPARTMENT ======================
    $output .= '<div class="department-section mb-4">
                    <h4 class="department-title">CLINICAL CHEMISTRY</h4>
                    <div class="department-content">';

    // Fetch Chemistry Panel records (Clinical Chemistry)
    $chemistryResults = [];
    $chemistryQuery = $connection->prepare("
        SELECT date_time,
            CONCAT(
                'FBS: ', IFNULL(fbs, 'N/A'), ' mg/dL\n',
                'PPBS: ', IFNULL(ppbs, 'N/A'), ' mg/dL\n',
                'BUN: ', IFNULL(bun, 'N/A'), ' mg/dL\n',
                'Creatinine: ', IFNULL(crea, 'N/A'), ' mg/dL\n',
                'Uric Acid: ', IFNULL(bua, 'N/A'), ' mg/dL\n',
                'Total Cholesterol: ', IFNULL(tc, 'N/A'), ' mg/dL\n',
                'Triglycerides: ', IFNULL(tg, 'N/A'), ' mg/dL\n',
                'HDL: ', IFNULL(hdl, 'N/A'), ' mg/dL\n',
                'LDL: ', IFNULL(ldl, 'N/A'), ' mg/dL\n',
                'VLDL: ', IFNULL(vldl, 'N/A'), ' mg/dL\n',
                'AST: ', IFNULL(ast, 'N/A'), ' U/L\n',
                'ALT: ', IFNULL(alt, 'N/A'), ' U/L\n',
                'ALP: ', IFNULL(alp, 'N/A'), ' U/L'
            ) AS results,
            remarks
        FROM tbl_chemistry
        WHERE patient_id = ?
        ORDER BY date_time DESC"
    );
    $chemistryQuery->bind_param('s', $patientId);
    $chemistryQuery->execute();
    $chemistryResult = $chemistryQuery->get_result();
    while ($row = $chemistryResult->fetch_assoc()) {
        $chemistryResults[] = $row;
    }
    $output .= createResultTable('CHEMISTRY PANEL', $chemistryResults);

    // Fetch Electrolytes records (Clinical Chemistry)
    $electrolytesResults = [];
    $electrolytesQuery = $connection->prepare("
        SELECT date_time,
            CONCAT(
                'Sodium (Na+): ', IFNULL(sodium, 'N/A'), ' mmol/L\n',
                'Potassium (K+): ', IFNULL(potassium, 'N/A'), ' mmol/L\n',
                'Chloride (Cl-): ', IFNULL(chloride, 'N/A'), ' mmol/L\n',
                'Calcium (Ca++): ', IFNULL(calcium, 'N/A'), ' mmol/L'
            ) AS results
        FROM tbl_electrolytes
        WHERE patient_id = ?
        ORDER BY date_time DESC"
    );
    $electrolytesQuery->bind_param('s', $patientId);
    $electrolytesQuery->execute();
    $electrolytesResult = $electrolytesQuery->get_result();
    while ($row = $electrolytesResult->fetch_assoc()) {
        $electrolytesResults[] = $row;
    }
    $output .= createResultTable('ELECTROLYTES', $electrolytesResults);

    // Fetch OGTT records (Clinical Chemistry)
$ogttResults = [];
$ogttQuery = $connection->prepare("
    SELECT date_time,
        CONCAT(
            'Fasting: ', IFNULL(fbs, 'N/A'), ' mg/dL\n',
            '1 Hour: ', IFNULL(first_hour, 'N/A'), ' mg/dL\n',
            '2 Hours: ', IFNULL(second_hour, 'N/A'), ' mg/dL\n',
            '3 Hours: ', IFNULL(third_hour, 'N/A'), ' mg/dL'
        ) AS results
    FROM tbl_ogtt
    WHERE patient_id = ?
    ORDER BY date_time DESC"
);

// Add console log to track execution
echo "<script>console.log('OGTT query prepared for patient ID: " . $patientId . "');</script>";

$ogttQuery->bind_param('s', $patientId);

if (!$ogttQuery->execute()) {
    echo "<script>console.error('OGTT query execution failed: " . addslashes($connection->error) . "');</script>";
} else {
    $ogttResult = $ogttQuery->get_result();
    $rowCount = $ogttResult->num_rows;
    echo "<script>console.log('OGTT query returned " . $rowCount . " rows');</script>";
    
    if ($rowCount > 0) {
        while ($row = $ogttResult->fetch_assoc()) {
            $ogttResults[] = $row;
            // Log each row for debugging
            echo "<script>console.log('OGTT row found: " . addslashes(json_encode($row)) . "');</script>";
        }
    } else {
        echo "<script>console.log('No OGTT records found for this patient');</script>";
    }
}

// Check if we have results to display
if (!empty($ogttResults)) {
    echo "<script>console.log('Creating OGTT result table with " . count($ogttResults) . " rows');</script>";
    $output .= createResultTable('ORAL GLUCOSE TOLERANCE TEST (OGTT)', $ogttResults);
} else {
    echo "<script>console.log('No OGTT results to display');</script>";
}


    $output .= '</div></div>'; // Close Clinical Chemistry department section

    // ====================== SEROLOGY DEPARTMENT ======================
    $output .= '<div class="department-section mb-4">
    <h4 class="department-title">SEROLOGY</h4>
    <div class="department-content">';

    // Fetch Dengue Duo records (Serology)
    $dengueResults = [];
    $dengueQuery = $connection->prepare("
        SELECT date_time, 
            CONCAT(
                'NS1 Antigen: ', IFNULL(ns1ag, 'N/A'), '\n',
                'IgG: ', IFNULL(igg, 'N/A'), '\n',
                'IgM: ', IFNULL(igm, 'N/A')
            ) AS results,
        remarks
        FROM tbl_dengueduo
        WHERE patient_id = ?
        ORDER BY date_time DESC"
    );
    $dengueQuery->bind_param('s', $patientId);
    $dengueQuery->execute();
    $dengueResult = $dengueQuery->get_result();
    while ($row = $dengueResult->fetch_assoc()) {
    $dengueResults[] = $row;
    }
    $output .= createResultTable('DENGUE DUO', $dengueResults);

    // Fetch HBsAg/VDRL records (Serology)
    $serologyResults = [];
    $serologyQuery = $connection->prepare("
        SELECT date_time, 
            CONCAT(
                'HBsAg: ', IFNULL(hbsag, 'N/A'), '\n',
                'VDRL: ', IFNULL(vdrl, 'N/A')
                ) AS results
        FROM tbl_serology
        WHERE patient_id = ?
        ORDER BY date_time DESC"
    );
    $serologyQuery->bind_param('s', $patientId);
    $serologyQuery->execute();
    $serologyResult = $serologyQuery->get_result();
    while ($row = $serologyResult->fetch_assoc()) {
    $serologyResults[] = $row;
    }
    $output .= createResultTable('HBsAg/VDRL', $serologyResults);

    $output .= '</div></div>'; // Close Serology department section

    if (empty($cbcResults) && empty($pbsResults) && empty($ptpttResults) && 
        empty($crossmatchingResults) && empty($urinalysisResults) && 
        empty($fecalysisResults) && empty($chemistryResults) && empty($dengueResults) && 
        empty($ogttResults)) {  // Added check for OGTT results
        echo '<div class="alert alert-info text-center p-4 my-3" style="border-left: 5px solid #12369e; background-color: #f8f9fa; box-shadow: 0 2px 10px rgba(0,0,0,0.1); border-radius: 8px;">
                <i class="fa fa-info-circle mr-2" style="color: #12369e; font-size: 24px;"></i>
                <div style="color: #12369e; font-weight: bold; font-size: 18px;">No Laboratory Results</div>
                <div class="mt-2">No laboratory results found for this patient.</div>
                </div>';
    } else {
        echo $output;
    }


}
?>