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
        $this->Image($image_file, 15, 5, 30, '', 'PNG', '', 'T', false, 300, '', false, false, 0, false, false, false);
        
        // Hospital Name with larger, bold font
        $this->SetFont('helvetica', 'B', 16);
        $this->SetY(10);
        $this->Cell(0, 8, 'SANTA ROSA COMMUNITY HOSPITAL', 0, 1, 'C');
        
        // Address and Contact Details
        $this->SetFont('helvetica', '', 10);
        $this->Cell(0, 5, 'LM Subdivision, Market Area, Santa Rosa City, Laguna', 0, 1, 'C');
        $this->Cell(0, 5, 'Email: srcityhospital1995@gmail.com', 0, 1, 'C');
        
       
        // Decorative lines
        $this->Line(15, 40, 195, 40);

        $this->Ln(5);
    }
}

// Initialize TCPDF
$pdf = new MYPDF('P', 'mm', 'A4', true, 'UTF-8', false);

// Set default monospaced font
$pdf->SetDefaultMonospacedFont(PDF_FONT_MONOSPACED);

// Set margins
$pdf->SetMargins(15, 30, 15);
$pdf->SetHeaderMargin(10);
$pdf->SetFooterMargin(10);

// Set auto page breaks
$pdf->SetAutoPageBreak(TRUE, 15);

// Set image scale factor
$pdf->setImageScale(PDF_IMAGE_SCALE_RATIO);

// Add a page
$pdf->AddPage();

// Get patient name from database
$patient_query = $connection->prepare("SELECT CONCAT(first_name, ' ', last_name) AS patient_name FROM tbl_patient WHERE patient_id = ?");
$patient_query->bind_param('s', $patient_id);
$patient_query->execute();
$patient_result = $patient_query->get_result();
$patient_data = $patient_result->fetch_assoc();
$patient_name = $patient_data['patient_name'];

// Section title: Lab Results
$pdf->SetFont('helvetica', 'B', 11);
$pdf->Cell(0, 10, 'Patient Name: ' . htmlspecialchars($patient_name), 0, 1, 'C');
$pdf->Ln(5);

// Function to render lab results table with XSS protection
function renderLabResultsTable($pdf, $testType, $data, $columns, $widths)
{
    $pdf->SetFont('helvetica', 'B', 10);
    $pdf->SetFillColor(220, 220, 220);

    // Table header
    foreach ($columns as $index => $header) {
        $pdf->Cell($widths[$index], 7, htmlspecialchars($header), 1, 0, 'C', true); // Escape column headers
    }
    $pdf->Ln();

    $pdf->SetFont('helvetica', '', 10);
    $pdf->SetFillColor(255, 255, 255);

    // Table rows
    foreach ($data as $row) {
        $pdf->Cell($widths[0], 7, htmlspecialchars($testType), 1, 0, 'C'); // Escape test type
        $pdf->Cell($widths[1], 7, htmlspecialchars(date('F d, Y g:i A', strtotime($row['date_time']))), 1, 0, 'C'); // Escape date/time
        
        // Bold font for the "Results" column, escape results data
        $pdf->SetFont('helvetica', 'B', 10);
        $results = explode('\n', htmlspecialchars($row['results']));
        foreach ($results as $result) {
            $pdf->MultiCell($widths[1], 7, $result, 1, 'L', false); // Escape results
        }
        $pdf->SetFont('helvetica', '', 10); // Reset to normal font for other rows
    }
    $pdf->Ln(5);
}

// Fetch CBC records
$cbcResults = [];
$cbcQuery = $connection->prepare("
    SELECT date_time, 
           CONCAT(
               'Hemoglobin: ', hemoglobin, '\n',
               'Hematocrit: ', hematocrit, '\n',
               'RBC: ', red_blood_cells, '\n',
               'WBC: ', white_blood_cells, '\n',
               'ESR: ', esr, '\n',
               'Segmenters: ', segmenters, '\n',
               'Lymphocytes: ', lymphocytes, '\n',
               'Monocytes: ', monocytes, '\n',
               'Bands: ', bands, '\n',
               'Platelets: ', platelets
           ) AS results
    FROM tbl_cbc
    WHERE patient_id = ?
");
$cbcQuery->bind_param('s', $patient_id);
$cbcQuery->execute();
$cbcResult = $cbcQuery->get_result();
while ($row = $cbcResult->fetch_assoc()) {
    $cbcResults[] = $row;
}
if (!empty($cbcResults)) {
    renderLabResultsTable($pdf, 'Complete Blood Count', $cbcResults, ['Test Type', 'Date and Time', 'Results'], [60, 60, 60]);
}

// Fetch Macroscopic Urinalysis records
$macroResults = [];
$macroQuery = $connection->prepare("
    SELECT date_time, 
           CONCAT(
               'Color: ', color, '\n',
               'Transparency: ', transparency, '\n',
               'Reaction: ', reaction, '\n',
               'Protein: ', protein, '\n',
               'Glucose: ', glucose, '\n',
               'Specific Gravity: ', specific_gravity, '\n',
               'Ketone: ', ketone, '\n',
               'Urobilinogen: ', urobilinogen, '\n',
               'Pregnancy Test: ', pregnancy_test
           ) AS results
    FROM tbl_urinalysis
    WHERE patient_id = ?
");
$macroQuery->bind_param('s', $patient_id);
$macroQuery->execute();
$macroResult = $macroQuery->get_result();
while ($row = $macroResult->fetch_assoc()) {
    $macroResults[] = $row;
}
if (!empty($macroResults)) {
    renderLabResultsTable($pdf, 'Macroscopic Urinalysis', $macroResults, ['Test Type', 'Date and Time', 'Results'], [60, 60, 60]);
}

// Fetch Microscopic Urinalysis records
$microResults = [];
$microQuery = $connection->prepare("
    SELECT date_time, 
           CONCAT(
               'Pus Cells: ', pus_cells, '\n',
               'RBC: ', red_blood_cells, '\n',
               'Epithelial Cells: ', epithelial_cells, '\n',
               'Urates/Phosphates: ', a_urates_a_phosphates, '\n',
               'Mucus Threads: ', mucus_threads, '\n',
               'Bacteria: ', bacteria, '\n',
               'Calcium Oxalates: ', calcium_oxalates, '\n',
               'Uric Acid Crystals: ', uric_acid_crystals, '\n',
               'Pus Cell Clumps: ', pus_cells_clumps, '\n',
               'Coarse Granular Cast: ', coarse_granular_cast, '\n',
               'Hyaline Cast: ', hyaline_cast
           ) AS results
    FROM tbl_urinalysis
    WHERE patient_id = ?
");
$microQuery->bind_param('s', $patient_id);
$microQuery->execute();
$microResult = $microQuery->get_result();
while ($row = $microResult->fetch_assoc()) {
    $microResults[] = $row;
}
if (!empty($microResults)) {
    renderLabResultsTable($pdf, 'Microscopic Urinalysis', $microResults, ['Test Type', 'Date and Time', 'Results'], [60, 60, 60]);
}

// Output the PDF
$pdf->Output('Lab_Results_Patient_' . $patient_id . '.pdf', 'I');
?>