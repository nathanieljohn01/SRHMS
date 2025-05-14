<?php
session_start();
if (empty($_SESSION['name'])) {
    header('location:index.php');
    exit;
}

include('includes/connection.php');
require_once('vendor/tecnickcom/tcpdf/tcpdf.php');
require_once('vendor/autoload.php');

// Ensure that patient_id is set
if (!isset($_GET['patient_id']) || empty($_GET['patient_id'])) {
    die('Patient ID is required.');
}

// Sanitize patient_id
$patient_id = mysqli_real_escape_string($connection, $_GET['patient_id']);

// Extend TCPDF class with improved header and styling
class MYPDF extends TCPDF {
    public function Header() {
        // Logo
        $image_file = __DIR__ . '/assets/img/srchlogo.png';
        $this->Image($image_file, 15, 10, 25, '', 'PNG', '', 'T', false, 300, '', false, false, 0, false, false, false);
        
        // Hospital Name with larger, bold font
        $this->SetFont('helvetica', 'B', 14);
        $this->SetY(12);
        $this->Cell(0, 8, 'SANTA ROSA COMMUNITY HOSPITAL', 0, 1, 'C');
        
        // Address and Contact Details
        $this->SetFont('helvetica', '', 9);
        $this->Cell(0, 5, 'LM Subdivision, Market Area, Santa Rosa City, Laguna', 0, 1, 'C');
        $this->Cell(0, 5, 'Email: srcityhospital1995@gmail.com', 0, 1, 'C');
        
        // Decorative lines
        $this->Line(15, 35, 195, 35, array('width' => 0.5, 'color' => array(0, 0, 0)));
        $this->Line(15, 36, 195, 36, array('width' => 0.2, 'color' => array(100, 100, 100)));

        $this->Ln(8);
    }
    
    public function Footer() {
        $this->SetY(-15);
        $this->SetFont('helvetica', 'I', 8);
        $this->Cell(0, 10, 'Page '.$this->getAliasNumPage().'/'.$this->getAliasNbPages(), 0, 0, 'C');
    }
}

// Initialize TCPDF
$pdf = new MYPDF('P', 'mm', 'A4', true, 'UTF-8', false);

// Set document information
$pdf->SetCreator('Santa Rosa Community Hospital');
$pdf->SetAuthor('Laboratory Department');
$pdf->SetTitle('Laboratory Results');
$pdf->SetSubject('Patient Test Results');

// Set default monospaced font
$pdf->SetDefaultMonospacedFont(PDF_FONT_MONOSPACED);

// Set margins
$pdf->SetMargins(15, 40, 15);
$pdf->SetHeaderMargin(10);
$pdf->SetFooterMargin(15);

// Set auto page breaks
$pdf->SetAutoPageBreak(TRUE, 25);

// Set image scale factor
$pdf->setImageScale(PDF_IMAGE_SCALE_RATIO);

// Add a page
$pdf->AddPage();

// Get patient information from database
$patient_query = $connection->prepare("SELECT first_name, last_name, dob, gender FROM tbl_patient WHERE patient_id = ?");
$patient_query->bind_param('s', $patient_id);
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

// Patient information section
$pdf->SetFont('helvetica', 'B', 12);
$pdf->Cell(0, 8, 'LABORATORY TEST RESULTS', 0, 1, 'C');
$pdf->Ln(5);

$pdf->SetFont('helvetica', '', 10);
$pdf->Cell(40, 6, 'Patient Name:', 0, 0);
$pdf->SetFont('helvetica', 'B', 10);
$pdf->Cell(60, 6, $patient_name, 0, 0);
$pdf->SetFont('helvetica', '', 10);
$pdf->Cell(30, 6, 'Age:', 0, 0);
$pdf->SetFont('helvetica', 'B', 10);
$pdf->Cell(0, 6, $age , 0, 1);
$pdf->SetFont('helvetica', '', 10);
$pdf->Cell(40, 6, 'Gender:', 0, 0);
$pdf->SetFont('helvetica', 'B', 10);
$pdf->Cell(60, 6, $gender, 0, 0);
$pdf->SetFont('helvetica', '', 10);
$pdf->Cell(30, 6, 'Report Date:', 0, 0);
$pdf->SetFont('helvetica', 'B', 10);
$pdf->Cell(0, 6, $current_date, 0, 1);

$pdf->Ln(8);

// Function to render department header
function renderDepartmentHeader($pdf, $departmentName) {
    $pdf->SetFont('helvetica', 'B', 12);
    $pdf->SetFillColor(200, 220, 255); // Light blue background
    $pdf->Cell(0, 8, $departmentName, 0, 1, 'L', true);
    $pdf->Ln(4);
}

// New unified function to render all test results in crossmatching style
function renderTestResults($pdf, $testType, $data, $fields) {
    $pdf->SetFont('helvetica', 'B', 11);
    $pdf->SetFillColor(230, 230, 230);
    
    // Test Type Header
    $pdf->Cell(0, 7, $testType, 0, 1, 'L', true);
    $pdf->Ln(2);
    
    foreach ($data as $row) {
        // Format date/time
        $date_time = date('m/d/Y h:i A', strtotime($row['date_time']));
        
        $pdf->SetFont('helvetica', 'B', 10);
        $pdf->Cell(0, 6, 'Test Information', 0, 1);
        $pdf->SetFont('helvetica', '', 9);
        
        $pdf->Cell(60, 6, 'Date/Time:', 1, 0, 'L', true);
        $pdf->Cell(0, 6, $date_time, 1, 1);
        
        // Render each field
        foreach ($fields as $field => $label) {
            if (isset($row[$field])) {
                $value = !empty($row[$field]) ? htmlspecialchars($row[$field]) : 'N/A';
                
                // Handle special formatting for certain fields
                if (strpos($field, '_date') !== false || strpos($field, 'dated') !== false) {
                    $value = !empty($row[$field]) ? date('m/d/Y', strtotime($row[$field])) : 'N/A';
                } elseif (strpos($field, '_time') !== false || strpos($field, 'time_') !== false) {
                    $value = !empty($row[$field]) ? date('h:i A', strtotime($row[$field])) : 'N/A';
                }
                
                $pdf->Cell(60, 6, $label . ':', 1, 0, 'L', true);
                $pdf->Cell(0, 6, $value, 1, 1);
            }
        }
        
        $pdf->Ln(8);
    }
}

// ====================== HEMATOLOGY DEPARTMENT ======================
renderDepartmentHeader($pdf, 'HEMATOLOGY DEPARTMENT');

// Fetch CBC records
$cbcQuery = $connection->prepare("
    SELECT date_time, hemoglobin, hematocrit, red_blood_cells, white_blood_cells, 
           esr, segmenters, lymphocytes, monocytes, bands, platelets
    FROM tbl_cbc
    WHERE patient_id = ?
    ORDER BY date_time DESC
");
$cbcQuery->bind_param('s', $patient_id);
$cbcQuery->execute();
$cbcResult = $cbcQuery->get_result();
$cbcResults = $cbcResult->fetch_all(MYSQLI_ASSOC);

if (!empty($cbcResults)) {
    $cbcFields = [
        'hemoglobin' => 'Hemoglobin',
        'hematocrit' => 'Hematocrit',
        'red_blood_cells' => 'RBC',
        'white_blood_cells' => 'WBC',
        'esr' => 'ESR',
        'segmenters' => 'Segmenters',
        'lymphocytes' => 'Lymphocytes',
        'monocytes' => 'Monocytes',
        'bands' => 'Bands',
        'platelets' => 'Platelets'
    ];
    renderTestResults($pdf, 'COMPLETE BLOOD COUNT (CBC)', $cbcResults, $cbcFields);
}

// Fetch PBS records
$pbsQuery = $connection->prepare("
    SELECT date_time, rbc_morphology, platelet_count, toxic_granules, abnormal_cells,
           segmenters, lymphocytes, monocytes, eosinophils, bands, reticulocyte_count, remarks
    FROM tbl_pbs
    WHERE patient_id = ? AND deleted = 0
    ORDER BY date_time DESC
");
$pbsQuery->bind_param('s', $patient_id);
$pbsQuery->execute();
$pbsResult = $pbsQuery->get_result();
$pbsResults = $pbsResult->fetch_all(MYSQLI_ASSOC);

if (!empty($pbsResults)) {
    $pbsFields = [
        'rbc_morphology' => 'RBC Morphology',
        'platelet_count' => 'Platelet Count',
        'toxic_granules' => 'Toxic Granules',
        'abnormal_cells' => 'Abnormal Cells',
        'segmenters' => 'Segmenters',
        'lymphocytes' => 'Lymphocytes',
        'monocytes' => 'Monocytes',
        'eosinophils' => 'Eosinophils',
        'bands' => 'Bands',
        'reticulocyte_count' => 'Reticulocyte Count',
        'remarks' => 'Remarks'
    ];
    renderTestResults($pdf, 'PERIPHERAL BLOOD SMEAR (PBS)', $pbsResults, $pbsFields);
}

// Fetch PT/PTT records
$ptpttQuery = $connection->prepare("
    SELECT date_time, pt_control, pt_test, pt_inr, pt_activity, 
           ptt_control, ptt_patient_result, ptt_remarks
    FROM tbl_ptptt
    WHERE patient_id = ? AND deleted = 0
    ORDER BY date_time DESC
");
$ptpttQuery->bind_param('s', $patient_id);
$ptpttQuery->execute();
$ptpttResult = $ptpttQuery->get_result();
$ptpttResults = $ptpttResult->fetch_all(MYSQLI_ASSOC);

if (!empty($ptpttResults)) {
    $ptpttFields = [
        'pt_control' => 'PT Control',
        'pt_test' => 'PT Test',
        'pt_inr' => 'PT INR',
        'pt_activity' => 'PT Activity',
        'ptt_control' => 'PTT Control',
        'ptt_patient_result' => 'PTT Patient',
        'ptt_remarks' => 'Remarks'
    ];
    renderTestResults($pdf, 'PT/PTT COAGULATION TEST', $ptpttResults, $ptpttFields);
}

// ====================== BLOOD BANK DEPARTMENT ======================
renderDepartmentHeader($pdf, 'BLOOD BANK DEPARTMENT');

// Fetch Crossmatching records
$crossmatchingQuery = $connection->prepare("
    SELECT * FROM tbl_crossmatching 
    WHERE patient_id = ?
    ORDER BY date_time DESC
");
$crossmatchingQuery->bind_param('s', $patient_id);
$crossmatchingQuery->execute();
$crossmatchingResult = $crossmatchingQuery->get_result();
$crossmatchingResults = $crossmatchingResult->fetch_all(MYSQLI_ASSOC);

if (!empty($crossmatchingResults)) {
    $crossmatchingFields = [
        'patient_blood_type' => 'Patient Blood Type',
        'blood_component' => 'Blood Component',
        'serial_number' => 'Serial Number',
        'extraction_date' => 'Extraction Date',
        'expiration_date' => 'Expiration Date',
        'major_crossmatching' => 'Major Crossmatching',
        'donors_blood_type' => 'Donor\'s Blood Type',
        'packed_red_blood_cell' => 'Packed Red Blood Cell',
        'time_packed' => 'Time Packed',
        'dated' => 'Dated',
        'open_system' => 'Open System',
        'closed_system' => 'Closed System',
        'to_be_consumed_before' => 'To Be Consumed Before',
        'hours' => 'Hours',
        'minor_crossmatching' => 'Minor Crossmatching'
    ];
    renderTestResults($pdf, 'BLOOD CROSSMATCHING', $crossmatchingResults, $crossmatchingFields);
}

// ====================== CLINICAL MICROSCOPY DEPARTMENT ======================
renderDepartmentHeader($pdf, 'CLINICAL MICROSCOPY DEPARTMENT');

// Fetch Urinalysis records
$urinalysisQuery = $connection->prepare("
    SELECT date_time, color, transparency, reaction, protein, glucose, specific_gravity,
           ketone, urobilinogen, pregnancy_test, pus_cells, red_blood_cells,
           epithelial_cells, a_urates_a_phosphates, mucus_threads, bacteria,
           calcium_oxalates, uric_acid_crystals, pus_cells_clumps,
           coarse_granular_cast, hyaline_cast
    FROM tbl_urinalysis
    WHERE patient_id = ?
    ORDER BY date_time DESC
");
$urinalysisQuery->bind_param('s', $patient_id);
$urinalysisQuery->execute();
$urinalysisResult = $urinalysisQuery->get_result();
$urinalysisResults = $urinalysisResult->fetch_all(MYSQLI_ASSOC);

if (!empty($urinalysisResults)) {
    $macroFields = [
        'color' => 'Color',
        'transparency' => 'Transparency',
        'reaction' => 'Reaction',
        'protein' => 'Protein',
        'glucose' => 'Glucose',
        'specific_gravity' => 'Specific Gravity',
        'ketone' => 'Ketone',
        'urobilinogen' => 'Urobilinogen',
        'pregnancy_test' => 'Pregnancy Test'
    ];
    renderTestResults($pdf, 'MACROSCOPIC URINALYSIS', $urinalysisResults, $macroFields);
    
    $microFields = [
        'pus_cells' => 'Pus Cells',
        'red_blood_cells' => 'RBC',
        'epithelial_cells' => 'Epithelial Cells',
        'a_urates_a_phosphates' => 'Urates/Phosphates',
        'mucus_threads' => 'Mucus Threads',
        'bacteria' => 'Bacteria',
        'calcium_oxalates' => 'Calcium Oxalates',
        'uric_acid_crystals' => 'Uric Acid Crystals',
        'pus_cells_clumps' => 'Pus Cell Clumps',
        'coarse_granular_cast' => 'Coarse Granular Cast',
        'hyaline_cast' => 'Hyaline Cast'
    ];
    renderTestResults($pdf, 'MICROSCOPIC URINALYSIS', $urinalysisResults, $microFields);
}

// Fetch Fecalysis records
$fecalysisQuery = $connection->prepare("
    SELECT date_time, color, consistency, occult_blood, pus_cells, ova_or_parasite,
           yeast_cells, fat_globules, rbc, bacteria
    FROM tbl_fecalysis
    WHERE patient_id = ?
    ORDER BY date_time DESC
");
$fecalysisQuery->bind_param('s', $patient_id);
$fecalysisQuery->execute();
$fecalysisResult = $fecalysisQuery->get_result();
$fecalysisResults = $fecalysisResult->fetch_all(MYSQLI_ASSOC);

if (!empty($fecalysisResults)) {
    $fecalFields = [
        'color' => 'Color',
        'consistency' => 'Consistency',
        'occult_blood' => 'Occult Blood',
        'pus_cells' => 'Pus Cells',
        'ova_or_parasite' => 'Ova or Parasite',
        'yeast_cells' => 'Yeast Cells',
        'fat_globules' => 'Fat Globules',
        'rbc' => 'RBC',
        'bacteria' => 'Bacteria'
    ];
    renderTestResults($pdf, 'FECALYSIS', $fecalysisResults, $fecalFields);
}

// ====================== CLINICAL CHEMISTRY DEPARTMENT ======================
renderDepartmentHeader($pdf, 'CLINICAL CHEMISTRY DEPARTMENT');

// Fetch Chemistry Panel records
$chemistryQuery = $connection->prepare("
    SELECT date_time, fbs, ppbs, bun, crea, bua, tc, tg, hdl, ldl, vldl, ast, alt, alp, remarks
    FROM tbl_chemistry
    WHERE patient_id = ?
    ORDER BY date_time DESC
");
$chemistryQuery->bind_param('s', $patient_id);
$chemistryQuery->execute();
$chemistryResult = $chemistryQuery->get_result();
$chemistryResults = $chemistryResult->fetch_all(MYSQLI_ASSOC);

if (!empty($chemistryResults)) {
    $chemFields = [
        'fbs' => 'FBS',
        'ppbs' => 'PPBS',
        'bun' => 'BUN',
        'crea' => 'Creatinine',
        'bua' => 'Uric Acid',
        'tc' => 'Total Cholesterol',
        'tg' => 'Triglycerides',
        'hdl' => 'HDL',
        'ldl' => 'LDL',
        'vldl' => 'VLDL',
        'ast' => 'AST',
        'alt' => 'ALT',
        'alp' => 'ALP',
        'remarks' => 'Remarks'
    ];
    renderTestResults($pdf, 'CHEMISTRY PANEL', $chemistryResults, $chemFields);
}

// Fetch Electrolytes records
$electrolytesQuery = $connection->prepare("
    SELECT date_time, sodium, potassium, chloride, calcium
    FROM tbl_electrolytes
    WHERE patient_id = ?
    ORDER BY date_time DESC
");
$electrolytesQuery->bind_param('s', $patient_id);
$electrolytesQuery->execute();
$electrolytesResult = $electrolytesQuery->get_result();
$electrolytesResults = $electrolytesResult->fetch_all(MYSQLI_ASSOC);

if (!empty($electrolytesResults)) {
    $electrolytesFields = [
        'sodium' => 'Sodium (Na+)',
        'potassium' => 'Potassium (K+)',
        'chloride' => 'Chloride (Cl-)',
        'calcium' => 'Calcium (Ca++)'
    ];
    renderTestResults($pdf, 'ELECTROLYTES', $electrolytesResults, $electrolytesFields);
}

// ====================== SEROLOGY DEPARTMENT ======================
renderDepartmentHeader($pdf, 'SEROLOGY DEPARTMENT');

// Fetch Dengue Duo records
$dengueQuery = $connection->prepare("
    SELECT date_time, ns1ag, igg, igm, remarks
    FROM tbl_dengueduo
    WHERE patient_id = ?
    ORDER BY date_time DESC
");
$dengueQuery->bind_param('s', $patient_id);
$dengueQuery->execute();
$dengueResult = $dengueQuery->get_result();
$dengueResults = $dengueResult->fetch_all(MYSQLI_ASSOC);

if (!empty($dengueResults)) {
    $dengueFields = [
        'ns1ag' => 'NS1 Antigen',
        'igg' => 'IgG',
        'igm' => 'IgM',
        'remarks' => 'Remarks'
    ];
    renderTestResults($pdf, 'DENGUE DUO TEST', $dengueResults, $dengueFields);
}
// Output the PDF
$pdf->Output('Lab_Results_' . $patient_name . '_' . date('Ymd') . '.pdf', 'I');
?>