<?php
session_start();
if (empty($_SESSION['name'])) {
    header('location:index.php');
    exit;
}

// filepath: /c:/xampp/htdocs/hms/generate-result.php
include('includes/connection.php');

// Include TCPDF library
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

// Function to render lab results table with improved formatting
function renderLabResultsTable($pdf, $testType, $data, $columns, $widths) {
    $pdf->SetFont('helvetica', 'B', 11);
    $pdf->SetFillColor(230, 230, 230);
    
    // Test Type Header
    $pdf->Cell(0, 7, $testType, 0, 1, 'L', true);
    $pdf->Ln(2);
    
    // Table header
    foreach ($columns as $index => $header) {
        $pdf->Cell($widths[$index], 7, htmlspecialchars($header), 1, 0, 'C', true);
    }
    $pdf->Ln();
    
    $pdf->SetFont('helvetica', '', 9);
    $fill = false;
    
    // Table rows
    foreach ($data as $row) {
        $pdf->SetFillColor(245, 245, 245);
        $pdf->Cell($widths[0], 6, htmlspecialchars(date('m/d/Y', strtotime($row['date_time']))), 1, 0, 'C', $fill);
        $pdf->Cell($widths[1], 6, htmlspecialchars(date('h:i A', strtotime($row['date_time']))), 1, 0, 'C', $fill);
        
        // Results with proper formatting
        $pdf->SetFont('helvetica', '', 9);
        $results = explode('\n', $row['results']);
        $first = true;
        
        foreach ($results as $result) {
            if (!$first) {
                $pdf->Cell($widths[0] + $widths[1], 6, '', 0, 1);
                $pdf->Cell($widths[0] + $widths[1], 6, '', 0, 0);
            }
            $pdf->MultiCell($widths[2], 6, htmlspecialchars($result), 1, 'L', $fill);
            $first = false;
        }
        
        $fill = !$fill;
    }
    $pdf->Ln(8);
}

// Fetch CBC records
$cbcResults = [];
$cbcQuery = $connection->prepare("
    SELECT date_time, 
           CONCAT(
               'Hemoglobin: ', hemoglobin, ' g/dL\n',
               'Hematocrit: ', hematocrit, ' %\n',
               'RBC: ', red_blood_cells, ' M/uL\n',
               'WBC: ', white_blood_cells, ' K/uL\n',
               'ESR: ', esr, ' mm/hr\n',
               'Segmenters: ', segmenters, ' %\n',
               'Lymphocytes: ', lymphocytes, ' %\n',
               'Monocytes: ', monocytes, ' %\n',
               'Bands: ', bands, ' %\n',
               'Platelets: ', platelets, ' K/uL'
           ) AS results
    FROM tbl_cbc
    WHERE patient_id = ?
    ORDER BY date_time DESC
");
$cbcQuery->bind_param('s', $patient_id);
$cbcQuery->execute();
$cbcResult = $cbcQuery->get_result();
while ($row = $cbcResult->fetch_assoc()) {
    $cbcResults[] = $row;
}
if (!empty($cbcResults)) {
    renderLabResultsTable($pdf, 'COMPLETE BLOOD COUNT (CBC)', $cbcResults, ['Date', 'Time', 'Results'], [25, 20, 135]);
}

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
    ORDER BY date_time DESC
");
$pbsQuery->bind_param('s', $patient_id);
$pbsQuery->execute();
$pbsResult = $pbsQuery->get_result();
while ($row = $pbsResult->fetch_assoc()) {
    $pbsResults[] = $row;
}
if (!empty($pbsResults)) {
    renderLabResultsTable($pdf, 'PERIPHERAL BLOOD SMEAR (PBS)', $pbsResults, ['Date', 'Time', 'Results'], [25, 20, 135]);
}

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
    ORDER BY date_time DESC
");
$ptpttQuery->bind_param('s', $patient_id);
$ptpttQuery->execute();
$ptpttResult = $ptpttQuery->get_result();
while ($row = $ptpttResult->fetch_assoc()) {
    $ptpttResults[] = $row;
}
if (!empty($ptpttResults)) {
    renderLabResultsTable($pdf, 'PT/PTT COAGULATION TEST', $ptpttResults, ['Date', 'Time', 'Results'], [25, 20, 135]);
}

// Fetch Macroscopic Urinalysis records
$macroResults = [];
$macroQuery = $connection->prepare("
    SELECT date_time, 
           CONCAT(
               'Color: ', IFNULL(color, 'N/A'), '\n',
               'Transparency: ', IFNULL(transparency, 'N/A'), '\n',
               'Reaction: ', IFNULL(reaction, 'N/A'), '\n',
               'Protein: ', IFNULL(protein, 'N/A'), '\n',
               'Glucose: ', IFNULL(glucose, 'N/A'), '\n',
               'Specific Gravity: ', IFNULL(specific_gravity, 'N/A'), '\n',
               'Ketone: ', IFNULL(ketone, 'N/A'), '\n',
               'Urobilinogen: ', IFNULL(urobilinogen, 'N/A'), '\n',
               'Pregnancy Test: ', IFNULL(pregnancy_test, 'N/A')
           ) AS results
    FROM tbl_urinalysis
    WHERE patient_id = ?
    ORDER BY date_time DESC
");
$macroQuery->bind_param('s', $patient_id);
$macroQuery->execute();
$macroResult = $macroQuery->get_result();
while ($row = $macroResult->fetch_assoc()) {
    $macroResults[] = $row;
}
if (!empty($macroResults)) {
    renderLabResultsTable($pdf, 'MACROSCOPIC URINALYSIS', $macroResults, ['Date', 'Time', 'Results'], [25, 20, 135]);
}

// Fetch Microscopic Urinalysis records
$microResults = [];
$microQuery = $connection->prepare("
    SELECT date_time, 
           CONCAT(
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
    ORDER BY date_time DESC
");
$microQuery->bind_param('s', $patient_id);
$microQuery->execute();
$microResult = $microQuery->get_result();
while ($row = $microResult->fetch_assoc()) {
    $microResults[] = $row;
}
if (!empty($microResults)) {
    renderLabResultsTable($pdf, 'MICROSCOPIC URINALYSIS', $microResults, ['Date', 'Time', 'Results'], [25, 20, 135]);
}

// Output the PDF
$pdf->Output('Lab_Results_' . $patient_name . '_' . date('Ymd') . '.pdf', 'I');
?>