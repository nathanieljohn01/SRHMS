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

// Fetch electrolytes details
if (isset($_GET['id'])) {
    $electrolytes_id = mysqli_real_escape_string($connection, $_GET['id']);
    mysqli_query($connection, "SET NAMES 'utf8'");
    $filename = isset($_GET['filename']) ? mysqli_real_escape_string($connection, $_GET['filename']) : 'electrolytes_report_' . $electrolytes_id;
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

$pdf->SetCreator('Santa Rosa Hospital');
$pdf->SetAuthor('Santa Rosa Community Hospital');
$pdf->SetTitle('Electrolytes Report');
$pdf->SetSubject('Electrolytes');
$pdf->SetKeywords('Electrolytes, Sodium, Potassium, Chloride, Calcium');

$pdf->setHeaderFont(['helvetica', '', 16]);
$pdf->setFooterFont(['helvetica', '', 14]);

$pdf->SetMargins(15, 25, 15); // Reduced top margin from 25 to 20
$pdf->SetHeaderMargin(9); // Reduced from 10
$pdf->SetFooterMargin(9); // Reduced from 10

$pdf->SetAutoPageBreak(TRUE, 10); // Reduced from 15

$pdf->setImageScale(PDF_IMAGE_SCALE_RATIO);

$pdf->AddPage();

$html = '';

// Prepare the query using prepared statement
$query = "SELECT * FROM tbl_electrolytes WHERE electrolytes_id = ?";
$stmt = mysqli_prepare($connection, $query);
mysqli_stmt_bind_param($stmt, 's', $electrolytes_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

while ($row = $result->fetch_assoc()) {
    // Calculate age
    $dob = date('Y-m-d', strtotime(str_replace('/', '-', $row['dob'])));
    $year = (date('Y') - date('Y', strtotime($dob)));

    // Patient Information
    $html .= '<div style="text-align: center; margin-bottom: 10px;">
                <h3>Electrolytes</h3>
                <p><strong>Patient Name:</strong> ' . htmlspecialchars($row['patient_name'], ENT_QUOTES, 'UTF-8') . ' | 
                <strong>Age:</strong> ' . $year . ' | 
                <strong>Gender:</strong> ' . htmlspecialchars($row['gender'], ENT_QUOTES, 'UTF-8') . ' | 
                <strong>Date and Time:</strong> ' . date('F d Y g:i A', strtotime($row['date_time'])) . '</p>
            </div>';

    // Electrolytes Results (more compact)
    $html .= '<table border="1" cellpadding="5" style="width: 100%; font-size: 15px; margin-bottom: 5px;">';  // Smaller padding and font
    $html .= '<thead style="background-color: #CCCCCC;">
                <tr>
                    <th style="padding: 5px;">Test</th>
                    <th style="padding: 5px;">Result</th>
                    <th style="padding: 5px;">Unit</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td style="padding: 5px;"><strong>Sodium (Na+)</strong></td>
                    <td style="padding: 5px;">' . htmlspecialchars($row['sodium'] ?: 'N/A') . '</td>
                    <td style="padding: 5px;">mmol/L</td>
                </tr>
                <tr>
                    <td style="padding: 5px;"><strong>Potassium (K+)</strong></td>
                    <td style="padding: 5px;">' . htmlspecialchars($row['potassium'] ?: 'N/A') . '</td>
                    <td style="padding: 5px;">mmol/L</td>
                </tr>
                <tr>
                    <td style="padding: 5px;"><strong>Chloride (Cl-)</strong></td>
                    <td style="padding: 5px;">' . htmlspecialchars($row['chloride'] ?: 'N/A') . '</td>
                    <td style="padding: 5px;">mmol/L</td>
                </tr>
                <tr>
                    <td style="padding: 5px;"><strong>Calcium (Ca++)</strong></td>
                    <td style="padding: 5px;">' . htmlspecialchars($row['calcium'] ?: 'N/A') . '</td>
                    <td style="padding: 5px;">mmol/L</td>
                </tr>
            </tbody>
            </table>';

    // Reference Ranges (more compact)
    $html .= '<h5 style="font-size: 15px; text-align: center;margin: 5px 0 3px 0;">Reference Ranges</h5>';  // Tighter margins
    $html .= '<table border="1" cellpadding="4" style="width: 100%; font-size: 12px; margin-bottom: 5px;">';  // Smaller font
    $html .= '<thead style="background-color: #f2f2f2;">
                <tr>
                    <th style="padding: 5px;">Test</th>
                    <th style="padding: 5px;">Normal Range</th>
                    <th style="padding: 5px;">Critical Values</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td style="padding: 5px;">Sodium (Na+)</td>
                    <td style="padding: 5px;">135 - 148 mmol/L</td>
                    <td style="padding: 5px;">&lt;120 or &gt;160 mmol/L</td>
                </tr>
                <tr>
                    <td style="padding: 5px;">Potassium (K+)</td>
                    <td style="padding: 5px;">3.5 - 5.3 mmol/L</td>
                    <td style="padding: 5px;">&lt;2.5 or &gt;6.5 mmol/L</td>
                </tr>
                <tr>
                    <td style="padding: 5px;">Chloride (Cl-)</td>
                    <td style="padding: 5px;">98 - 107 mmol/L</td>
                    <td style="padding: 5px;">&lt;80 or &gt;115 mmol/L</td>
                </tr>
                <tr>
                    <td style="padding: 5px;">Calcium (Ca++)</td>
                    <td style="padding: 5px;">1.13 - 1.32 mmol/L</td>
                    <td style="padding: 5px;">&lt;0.8 or &gt;1.5 mmol/L</td>
                </tr>
            </tbody>
            </table>';

    // Interpretation Notes (more compact)
    $html .= '<div style="margin: 5px 0 10px 0; font-size: 11px;">';  // Reduced margin and font
    $html .= '<p><em>Note: Critical values may vary slightly depending on laboratory methods.</em></p>';
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