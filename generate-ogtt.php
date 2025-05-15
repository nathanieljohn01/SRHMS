<?php
session_start();
if (empty($_SESSION['name'])) {
    header('location:index.php');
    exit;
}

include('includes/connection.php');
require_once('vendor/tecnickcom/tcpdf/tcpdf.php');
require_once('vendor/autoload.php');

// Check if ogtt_id parameter exists
if (!isset($_GET['id'])) {
    die('<script>alert("Error: Test ID is required"); window.history.back();</script>');
}

$ogtt_id = mysqli_real_escape_string($connection, $_GET['id']);
$filename = isset($_GET['filename']) ? 
    htmlspecialchars($_GET['filename'], ENT_QUOTES, 'UTF-8') : 
    'ogtt_' . $ogtt_id;

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
$pdf->SetTitle('OGTT Test Report');
$pdf->SetSubject('Oral Glucose Tolerance Test');
$pdf->SetKeywords('OGTT, Glucose, Diabetes, Test, Report');

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

$query = "SELECT * FROM tbl_ogtt WHERE ogtt_id = ?";
$stmt = mysqli_prepare($connection, $query);
mysqli_stmt_bind_param($stmt, 's', $ogtt_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

while ($row = mysqli_fetch_assoc($result)) {
    $dob = date('Y-m-d', strtotime(str_replace('/', '-', $row['dob'])));
    $year = (date('Y') - date('Y', strtotime($dob)));

    $html .= '<div style="text-align: center; margin-bottom: 20px;">
                <h3>Oral Glucose Tolerance Test (OGTT)</h3>
                <p><strong>Patient Name:</strong> ' . htmlspecialchars($row['patient_name'], ENT_QUOTES, 'UTF-8') . ' | 
                <strong>Age:</strong> ' . $year . ' | 
                <strong>Gender:</strong> ' . htmlspecialchars($row['gender'], ENT_QUOTES, 'UTF-8') . ' | 
                <strong>Date and Time:</strong> ' . date('F d Y g:i A', strtotime($row['date_time'])) . '</p>
            </div>';

    $html .= '<table border="1" cellpadding="5" style="width: 100%; text-align: center;">
                <thead style="background-color: #CCCCCC;">
                    <tr>   
                        <th>Fasting Blood Sugar</th>
                        <th>1 Hour</th>
                        <th>2 Hours</th>
                        <th>3 Hours</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><strong>' . htmlspecialchars($row['fbs'], ENT_QUOTES, 'UTF-8') . ' mg/dL</strong></td>
                        <td><strong>' . htmlspecialchars($row['first_hour'], ENT_QUOTES, 'UTF-8') . ' mg/dL</strong></td>
                        <td><strong>' . htmlspecialchars($row['second_hour'], ENT_QUOTES, 'UTF-8') . ' mg/dL</strong></td>
                        <td><strong>' . htmlspecialchars($row['third_hour'], ENT_QUOTES, 'UTF-8') . ' mg/dL</strong></td>
                    </tr>
                </tbody>
            </table>';

    $html .= '<div style="margin-top: 20px;">
                <h4 style="font-size: 14px;">Interpretation:</h4>';
    
    $fbs = (float)$row['fbs'];
    $hour1 = (float)$row['first_hour'];
    $hour2 = (float)$row['second_hour'];
    $hour3 = (float)$row['third_hour'];
    
    // Normal ranges
    $normal_fbs = "< 100 mg/dL";
    $impaired_fbs = "100-125 mg/dL";
    $diabetic_fbs = "≥ 126 mg/dL";
    
    $normal_2hr = "< 140 mg/dL";
    $impaired_2hr = "140-199 mg/dL";
    $diabetic_2hr = "≥ 200 mg/dL";
    
    // Interpretation logic
    if ($fbs >= 126 || $hour2 >= 200) {
        $html .= '<p><strong>Diagnosis:</strong> Diabetes Mellitus</p>';
    } elseif (($fbs >= 100 && $fbs <= 125) || ($hour2 >= 140 && $hour2 <= 199)) {
        $html .= '<p><strong>Diagnosis:</strong> Impaired Glucose Tolerance</p>';
    } else {
        $html .= '<p><strong>Diagnosis:</strong> Normal Glucose Tolerance</p>';
    }
    
    $html .= '<p><strong>Reference Ranges:</strong><br>
              - Fasting: ' . $normal_fbs . ' (Normal), ' . $impaired_fbs . ' (Impaired), ' . $diabetic_fbs . ' (Diabetic)<br>
              - 2-Hour: ' . $normal_2hr . ' (Normal), ' . $impaired_2hr . ' (Impaired), ' . $diabetic_2hr . ' (Diabetic)</p>';
    
    $html .= '</div>';

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