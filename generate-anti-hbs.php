<?php
session_start();
if (empty($_SESSION['name'])) {
    header('location:index.php');
    exit;
}

include('includes/connection.php');
require_once('vendor/tecnickcom/tcpdf/tcpdf.php');
require_once('vendor/autoload.php');

if (isset($_GET['id'])) {
    $anti_id = mysqli_real_escape_string($connection, $_GET['id']);
    mysqli_query($connection, "SET NAMES 'utf8'");
    $filename = isset($_GET['filename']) ? htmlspecialchars($_GET['filename'], ENT_QUOTES, 'UTF-8') : 'anti_hbsag_' . $anti_id;
}

class MYPDF extends TCPDF {
    public function Header() {
        $image_file = __DIR__ . '/assets/img/srchlogo.png';
        $this->Image($image_file, 15, 8, 20, '', 'PNG', '', 'T', false, 300, '', false, false, 0, false, false, false);
        
        // Get the current page width
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

$pdf = new MYPDF('P', 'mm', 'A4', true, 'UTF-8', false);

$pdf->SetCreator('Santa Rosa Hospital');
$pdf->SetAuthor('Santa Rosa Community Hospital');
$pdf->SetTitle('Anti-HBsAg Test Report');
$pdf->SetSubject('Anti-HBsAg');
$pdf->SetKeywords('Anti-HBsAg, Hepatitis B, Test Report');

$pdf->setHeaderFont(['helvetica', '', 14]);
$pdf->setFooterFont(['helvetica', '', 10]);
$pdf->SetMargins(15, 40, 15);
$pdf->SetHeaderMargin(15);
$pdf->SetFooterMargin(10);
$pdf->SetAutoPageBreak(TRUE, 20);
$pdf->AddPage();

$html = '';

$query = "SELECT * FROM tbl_anti_hbsag WHERE anti_id = ?";
$stmt = mysqli_prepare($connection, $query);
mysqli_stmt_bind_param($stmt, 'i', $anti_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

while ($row = mysqli_fetch_assoc($result)) {
    // Calculate age and format dates
    $dob = date('Y-m-d', strtotime(str_replace('/', '-', $row['dob'])));
    $year = (date('Y') - date('Y', strtotime($dob)));
    $date_time = date('M d, Y h:i A', strtotime($row['date_time']));

    // Patient Information
    $html .= '<div style="text-align: center; margin-bottom: 15px;">
                <h3 style="font-size: 14px; margin-bottom: 5px;">ANTI-HBsAg TEST REPORT</h3>
                <table style="width: 100%; font-size: 11px; border-collapse: collapse;">
                    <tr>
                        <td style="width: 20%; text-align: right; padding-right: 5px;"><strong>Name:</strong></td>
                        <td style="width: 30%; text-align: left;">' . htmlspecialchars($row['patient_name'], ENT_QUOTES, 'UTF-8') . '</td>
                        <td style="width: 15%; text-align: right; padding-right: 5px;"><strong>ID:</strong></td>
                        <td style="width: 35%; text-align: left;">' . htmlspecialchars($row['patient_id'], ENT_QUOTES, 'UTF-8') . '</td>
                    </tr>
                    <tr>
                        <td style="text-align: right; padding-right: 5px;"><strong>Age/Sex:</strong></td>
                        <td style="text-align: left;">' . $year . '/' . htmlspecialchars($row['gender'], ENT_QUOTES, 'UTF-8') . '</td>
                        <td style="text-align: right; padding-right: 5px;"><strong>Date:</strong></td>
                        <td style="text-align: left;">' . $date_time . '</td>
                    </tr>
                </table>
              </div>';

    // Test Results
    $html .= '<table border="1" cellpadding="4" style="width: 100%; font-size: 11px; margin-bottom: 15px;">
                <thead style="background-color: #f0f0f0;">
                    <tr>
                        <th width="30%">Test Name</th>
                        <th width="25%">Result</th>
                        <th width="25%">Method</th>
                        <th width="20%">Cut-off Value</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>Anti-HBsAg (Hepatitis B Surface Antibody)</td>
                        <td>' . htmlspecialchars($row['result'], ENT_QUOTES, 'UTF-8') . '</td>
                        <td>' . htmlspecialchars($row['method'], ENT_QUOTES, 'UTF-8') . '</td>
                        <td>' . htmlspecialchars($row['cutoff_value'], ENT_QUOTES, 'UTF-8') . '</td>
                    </tr>
                </tbody>
              </table>';

    // Interpretation
    $interpretation = '';
    if (strtoupper($row['result']) == 'REACTIVE' || strtoupper($row['result']) == 'POSITIVE') {
        $interpretation = 'Indicates presence of antibodies to Hepatitis B surface antigen, suggesting immunity either from vaccination or past infection.';
    } else {
        $interpretation = 'Indicates absence of detectable antibodies to Hepatitis B surface antigen.';
    }

    $html .= '<div style="font-size: 11px; margin-bottom: 15px;">
                <h4 style="font-size: 12px; margin-bottom: 5px;">INTERPRETATION:</h4>
                <p>' . $interpretation . '</p>
              </div>';

    // Reference Range
    $html .= '<div style="font-size: 10px; margin-bottom: 15px;">
                <h4 style="font-size: 12px; margin-bottom: 5px;">REFERENCE RANGE:</h4>
                <p><strong>Non-reactive/Negative:</strong> &lt; 10 mIU/mL</p>
                <p><strong>Reactive/Positive:</strong> ≥ 10 mIU/mL (indicates immunity)</p>
              </div>';

    // Signatures
    $html .= '<div style="margin-top: 30px;">
                <table style="width: 100%; font-size: 10px;">
                    <tr>
                        <td style="width: 50%; text-align: center;">
                            <span style="border-top: 1px solid #000; padding-top: 5px; display: inline-block; width: 60%;">Medical Technologist</span>
                        </td>
                        <td style="width: 50%; text-align: center;">
                            <span style="border-top: 1px solid #000; padding-top: 5px; display: inline-block; width: 60%;">Pathologist</span>
                        </td>
                    </tr>
                    <tr>
                        <td style="text-align: center; padding-top: 5px;">License No.: _______________</td>
                        <td style="text-align: center; padding-top: 5px;">License No.: _______________</td>
                    </tr>
                </table>
              </div>';

    // Footer Note
    $html .= '<div style="font-size: 9px; margin-top: 20px; text-align: center; font-style: italic;">
                <p>This is an electronically generated report. No signature is required.</p>
              </div>';
}

$pdf->writeHTML($html, true, false, true, false, '');
$pdf->Output($filename . '.pdf', 'D');
?>