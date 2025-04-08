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

// Fetch PT/PTT details
if (isset($_GET['id'])) {
    $ptptt_id = mysqli_real_escape_string($connection, $_GET['id']);
    mysqli_query($connection, "SET NAMES 'utf8'");
    $filename = isset($_GET['filename']) ? mysqli_real_escape_string($connection, $_GET['filename']) : 'ptptt_report_' . $ptptt_id;
}

// Extend TCPDF class to create custom Header
class MYPDF extends TCPDF {
    public function Header() {
        $image_file = __DIR__ . '/assets/img/srchlogo.png';
        $this->Image($image_file, 15, 8, 20, '', 'PNG', '', 'T', false, 300, '', false, false, 0, false, false, false);
        
        $pageWidth = $this->getPageWidth();
        $centerPosition = ($pageWidth - 30) / 2 + 15;
        
        // Hospital Name
        $this->SetFont('helvetica', 'B', 14);
        $this->SetXY($centerPosition - ($this->GetStringWidth('SANTA ROSA COMMUNITY HOSPITAL')/2), 15);
        $this->Cell(0, 5, 'SANTA ROSA COMMUNITY HOSPITAL', 0, 1);
        
        // Address
        $this->SetFont('helvetica', '', 10);
        $this->SetXY($centerPosition - ($this->GetStringWidth('LM Subdivision, Market Area, Santa Rosa City, Laguna')/2), 20);
        $this->Cell(0, 5, 'LM Subdivision, Market Area, Santa Rosa City, Laguna', 0, 1);
        
        // Contact Information
        $this->SetXY($centerPosition - ($this->GetStringWidth('Email: srcityhospital1995@gmail.com')/2), 25);
        $this->Cell(0, 5, 'Email: srcityhospital1995@gmail.com', 0, 1);
        
        // Horizontal line
        $this->Line(15, 32, $pageWidth-15, 32);
        $this->Ln(10);
    }
}

// Create new PDF document
$pdf = new MYPDF('L', 'mm', 'A4', true, 'UTF-8', false);

// Set document information
$pdf->SetCreator('Santa Rosa Hospital');
$pdf->SetAuthor('Santa Rosa Community Hospital');
$pdf->SetTitle('PT/PTT Report');
$pdf->SetSubject('Coagulation Test');
$pdf->SetKeywords('PT, PTT, Coagulation, Report');

// Set header and footer fonts
$pdf->setHeaderFont(['helvetica', '', 16]);
$pdf->setFooterFont(['helvetica', '', 14]);

// Set margins
$pdf->SetMargins(15, 25, 15);
$pdf->SetHeaderMargin(10);
$pdf->SetFooterMargin(10);

// Set auto page breaks
$pdf->SetAutoPageBreak(TRUE, 15);

// Set image scale factor
$pdf->setImageScale(PDF_IMAGE_SCALE_RATIO);

// Add a page
$pdf->AddPage();

// Output PT/PTT details
$html = '';

// Prepare the query using prepared statement
$query = "SELECT * FROM tbl_ptptt WHERE ptptt_id = ?";
$stmt = mysqli_prepare($connection, $query);
mysqli_stmt_bind_param($stmt, 's', $ptptt_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

while ($row = $result->fetch_assoc()) {
    // Calculate age
    $dob = date('Y-m-d', strtotime(str_replace('/', '-', $row['dob'])));
    $year = (date('Y') - date('Y', strtotime($dob)));

    // Patient Information
    $html .= '<div style="text-align: center; margin-bottom: 20px;">
                <h3>PROTHROMBIN TIME / PARTIAL THROMBOPLASTIN TIME (PT/PTT)</h3>
                <p><strong>Patient Name:</strong> ' . htmlspecialchars($row['patient_name'], ENT_QUOTES, 'UTF-8') . ' | 
                <strong>Age:</strong> ' . $year . ' | 
                <strong>Gender:</strong> ' . htmlspecialchars($row['gender'], ENT_QUOTES, 'UTF-8') . ' | 
                <strong>Date and Time:</strong> ' . date('F d Y g:i A', strtotime($row['date_time'])) . '</p>
            </div>';

    // PT Results
    $html .= '<h4 style="font-size: 16px; text-align: center;">Prothrombin Time (PT) Results</h4>';
    $html .= '<table border="1" cellpadding="5" style="width: 100%;">
                <thead style="background-color: #CCCCCC;">
                    <tr>
                        <th>PT Control</th>
                        <th>PT Test</th>
                        <th>PT INR</th>
                        <th>PT Activity</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>' . htmlspecialchars($row['pt_control'] ?: 'N/A') . '</td>
                        <td>' . htmlspecialchars($row['pt_test'] ?: 'N/A') . '</td>
                        <td>' . htmlspecialchars($row['pt_inr'] ?: 'N/A') . '</td>
                        <td>' . htmlspecialchars($row['pt_activity'] ?: 'N/A') . '</td>
                    </tr>
                </tbody>
            </table>';

    // PTT Results
    $html .= '<h4 style="font-size: 16px; text-align: center; margin-top: 15px;">Partial Thromboplastin Time (PTT) Results</h4>';
    $html .= '<table border="1" cellpadding="5" style="width: 100%;">
                <thead style="background-color: #CCCCCC;">
                    <tr>
                        <th>PTT Control</th>
                        <th>PTT Result</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>' . htmlspecialchars($row['ptt_control'] ?: 'N/A') . '</td>
                        <td>' . htmlspecialchars($row['ptt_patient_result'] ?: 'N/A') . '</td>
                    </tr>
                </tbody>
            </table>';

    // Reference Ranges
    $html .= '<h4 style="font-size: 14px; margin-top: 10px;">Reference Ranges:</h4>';
    $html .= '<ul style="margin-left: 20px;">
                <li>PT Control: 10.5-15.1 seconds</li>
                <li>PT Test: 11-18 seconds</li>
                <li>PT INR: 0.85-1.15</li>
                <li>PT Activity: 70-130%</li>
                <li>PTT Control: 29.4-42.3 seconds</li>
                <li>PTT Result: 27-32</li>
              </ul>';

    // Remarks
    $html .= '<h4 style="font-size: 16px; margin-top: 15px;">Remarks:</h4>';
    $html .= '<div style="border: 1px solid #000; padding: 10px; min-height: 50px;">';
    $html .= htmlspecialchars($row['ptt_remarks'] ?: 'No significant findings');
    $html .= '</div>';

    // Medical Technologist Signature
    $html .= '<div style="position: absolute; bottom: 20px; left: 30px; text-align: left; width: 150px; font-weight: bold;">';
    $html .= '<br>';
    $html .= '<br>';
    $html .= '<strong>____________________</strong><br>';
    $html .= '<strong>Medical Technologist</strong><br>';
    $html .= '<br>';
    $html .= '<strong>____________________</strong><br>';
    $html .= '<strong>Pathologist</strong><br>';
    $html .= '</div>';
}

// Output the HTML content as a PDF
$pdf->writeHTML($html, true, false, true, false, '');

// Close and output PDF document
$pdf->Output($filename . '.pdf', 'D');
?>