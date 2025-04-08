<?php
session_start();
if (empty($_SESSION['name'])) {
    header('location:index.php');
    exit;
}

include('includes/connection.php');
require_once('vendor/tecnickcom/tcpdf/tcpdf.php');
require_once('vendor/autoload.php');

// Check if dd_id parameter exists
if (!isset($_GET['dd_id'])) {
    die('<script>alert("Error: Test ID is required"); window.history.back();</script>');
}

$dd_id = mysqli_real_escape_string($connection, $_GET['dd_id']);
$filename = isset($_GET['filename']) ? 
    htmlspecialchars($_GET['filename'], ENT_QUOTES, 'UTF-8') : 
    'dengueduo_' . $dd_id;

class MYPDF extends TCPDF {
    public function Header() {
        $image_file = __DIR__ . '/assets/img/srchlogo.png';
        $this->Image($image_file, 15, 8, 20, '', 'PNG', '', 'T', false, 300, '', false, false, 0, false, false, false);
        
        $pageWidth = $this->getPageWidth();
        $centerPosition = ($pageWidth - 30) / 2 + 15;
        
        $this->SetFont('helvetica', 'B', 14);
        $this->SetXY($centerPosition - ($this->GetStringWidth('SANTA ROSA COMMUNITY HOSPITAL')/2), 15);
        $this->Cell(0, 5, 'SANTA ROSA COMMUNITY HOSPITAL', 0, 1);
        
        $this->SetFont('helvetica', '', 10);
        $this->SetXY($centerPosition - ($this->GetStringWidth('LM Subdivision, Market Area, Santa Rosa City, Laguna')/2), 20);
        $this->Cell(0, 5, 'LM Subdivision, Market Area, Santa Rosa City, Laguna', 0, 1);
        
        $this->SetXY($centerPosition - ($this->GetStringWidth('Email: srcityhospital1995@gmail.com')/2), 25);
        $this->Cell(0, 5, 'Email: srcityhospital1995@gmail.com', 0, 1);
        
        $this->Line(15, 32, $pageWidth-15, 32);
        $this->Ln(10);
    }
}

$pdf = new MYPDF('P', 'mm', 'A4', true, 'UTF-8', false);

$pdf->SetCreator('Santa Rosa Hospital');
$pdf->SetAuthor('Santa Rosa Community Hospital');
$pdf->SetTitle('Dengue Duo Test Report');
$pdf->SetSubject('Dengue Duo Test');
$pdf->SetKeywords('Dengue, Duo, Test, Report');

$pdf->setHeaderFont(['helvetica', '', 16]);
$pdf->setFooterFont(['helvetica', '', 14]);
$pdf->SetDefaultMonospacedFont(PDF_FONT_MONOSPACED);
$pdf->SetMargins(15, 25, 15);
$pdf->SetHeaderMargin(10);
$pdf->SetFooterMargin(10);
$pdf->SetAutoPageBreak(TRUE, 15);
$pdf->setImageScale(PDF_IMAGE_SCALE_RATIO);
$pdf->AddPage();

$html = '';

$query = "SELECT * FROM tbl_dengueduo WHERE dd_id = ?";
$stmt = mysqli_prepare($connection, $query);
mysqli_stmt_bind_param($stmt, 'i', $dd_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

while ($row = mysqli_fetch_assoc($result)) {
    $dob = date('Y-m-d', strtotime(str_replace('/', '-', $row['dob'])));
    $year = (date('Y') - date('Y', strtotime($dob)));

    $html .= '<div style="text-align: center; margin-bottom: 20px;">
                <h3>Dengue Duo</h3>
                <p><strong>Patient Name:</strong> ' . htmlspecialchars($row['patient_name'], ENT_QUOTES, 'UTF-8') . ' | 
                <strong>Age:</strong> ' . $year . ' | 
                <strong>Gender:</strong> ' . htmlspecialchars($row['gender'], ENT_QUOTES, 'UTF-8') . ' | 
                <strong>Date and Time:</strong> ' . date('F d Y g:i A', strtotime($row['date_time'])) . '</p>
            </div>';

    $html .= '<table border="1" cellpadding="5" style="width: 100%; text-align: center;">
                <thead style="background-color: #CCCCCC;">
                    <tr>   
                        <th>NS1 Antigen</th>
                        <th>IgG</th>
                        <th>IgM</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><strong>' . htmlspecialchars($row['ns1ag'], ENT_QUOTES, 'UTF-8') . '</strong></td>
                        <td><strong>' . htmlspecialchars($row['igg'], ENT_QUOTES, 'UTF-8') . '</strong></td>
                        <td><strong>' . htmlspecialchars($row['igm'], ENT_QUOTES, 'UTF-8') . '</strong></td>
                    </tr>
                </tbody>
            </table>';

    $html .= '<div style="margin-top: 20px;">
                <h4 style="font-size: 14px;">Interpretation:</h4>';
    
    $ns1ag = strtoupper($row['ns1ag']);
    $igg = strtoupper($row['igg']);
    $igm = strtoupper($row['igm']);
    
    if ($ns1ag == 'POSITIVE' && $igm == 'POSITIVE') {
        $html .= '<p>Results suggest an acute dengue infection.</p>';
    } elseif ($ns1ag == 'POSITIVE' && $igm == 'NEGATIVE') {
        $html .= '<p>Results suggest an early acute dengue infection.</p>';
    } elseif ($ns1ag == 'NEGATIVE' && $igm == 'POSITIVE') {
        $html .= '<p>Results suggest a recent dengue infection (within 2-4 weeks).</p>';
    } elseif ($igg == 'POSITIVE' && $igm == 'NEGATIVE') {
        $html .= '<p>Results suggest a past dengue infection.</p>';
    } else {
        $html .= '<p>No evidence of current or recent dengue infection.</p>';
    }
    
    $html .= '</div>';

    $html .= '<div style="margin-top: 20px;">
                <h4 style="font-size: 14px;">Remarks:</h4>
                <p>' . htmlspecialchars($row['remarks'], ENT_QUOTES, 'UTF-8') . '</p>
            </div>';

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

$pdf->writeHTML($html, true, false, true, false, '');
$pdf->Output($filename . '.pdf', 'D');