<?php
session_start();
if (empty($_SESSION['name'])) {
    header('location:index.php');
    exit;
}

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

// Initialize TCPDF
$pdf = new TCPDF('L', 'mm', 'A4', true, 'UTF-8', false);

// Set document information
$pdf->SetCreator('Santa Rosa Hospital');
$pdf->SetAuthor('Santa Rosa Community Hospital');
$pdf->SetTitle('Lab Results');
$pdf->SetSubject('Patient Lab Results');
$pdf->SetKeywords('Lab Results, Report');

// Set default header data
$pdf->SetHeaderData('assets/img/srchlogo.png', 1, 'Santa Rosa Community Hospital', 'City of Santa Rosa, Laguna');

$pdf->setHeaderFont(['helvetica', '', 14]);
$pdf->setFooterFont(['helvetica', '', 10]);

// Set default monospaced font
$pdf->SetDefaultMonospacedFont(PDF_FONT_MONOSPACED);

// Set margins
$pdf->SetMargins(20, 20, 20);
$pdf->SetHeaderMargin(5);
$pdf->SetFooterMargin(10);

// Set auto page breaks
$pdf->SetAutoPageBreak(TRUE, 15);

// Set image scale factor
$pdf->setImageScale(PDF_IMAGE_SCALE_RATIO);

// Add a page
$pdf->AddPage();

// Section title: Lab Results
$pdf->SetFont('helvetica', 'B', 14);
$pdf->Cell(0, 10, 'Lab Results for Patient ID: ' . $patient_id, 0, 1, 'L');
$pdf->Ln(5);

// Function to render lab results table
function renderLabResultsTable($pdf, $testType, $data, $columns, $widths)
{
    $pdf->SetFont('helvetica', 'B', 10);
    $pdf->SetFillColor(230, 230, 230);

    // Table header
    foreach ($columns as $index => $header) {
        $pdf->Cell($widths[$index], 7, $header, 1, 0, 'C', true);
    }
    $pdf->Ln();

    $pdf->SetFont('helvetica', '', 9);
    $pdf->SetFillColor(255, 255, 255);

    // Table rows
    foreach ($data as $row) {
        $pdf->Cell($widths[0], 6, $testType, 1, 0, 'L');
        $pdf->Cell($widths[1], 6, date('F d, Y g:i A', strtotime($row['date_time'])), 1, 0, 'C');
        
        // Bold font for the "Results" column
        $pdf->SetFont('helvetica', 'B', 9);
        $pdf->MultiCell($widths[2], 6, $row['results'], 1, 'L', false);
        $pdf->SetFont('helvetica', '', 9); // Reset to normal font for other rows
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
    renderLabResultsTable($pdf, 'Complete Blood Count', $cbcResults, ['Test Type', 'Date & Time', 'Results'], [40, 50, 110]);
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
    renderLabResultsTable($pdf, 'Macroscopic Urinalysis', $macroResults, ['Test Type', 'Date & Time', 'Results'], [40, 50, 110]);
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
    renderLabResultsTable($pdf, 'Microscopic Urinalysis', $microResults, ['Test Type', 'Date & Time', 'Results'], [40, 50, 110]);
}

// Output the PDF
$pdf->Output('Lab_Results_Patient_' . $patient_id . '.pdf', 'I');
?>
