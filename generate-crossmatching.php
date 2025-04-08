<?php
session_start();
if (empty($_SESSION['name'])) {
    header('location:index.php');
    exit;
}

include('includes/connection.php');
require_once('vendor/tecnickcom/tcpdf/tcpdf.php');
require_once('vendor/autoload.php');

// Fetch Crossmatching details
if (isset($_GET['id'])) {
    $crossmatching_id = mysqli_real_escape_string($connection, $_GET['id']);
    mysqli_query($connection, "SET NAMES 'utf8'");
    $filename = isset($_GET['filename']) ? htmlspecialchars($_GET['filename'], ENT_QUOTES, 'UTF-8') : 'crossmatching_' . $crossmatching_id;
}

// Extend TCPDF class for custom header
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

// Create new PDF document
$pdf = new MYPDF('L', 'mm', 'A4', true, 'UTF-8', false);

// Set document information
$pdf->SetCreator('Santa Rosa Hospital');
$pdf->SetAuthor('Santa Rosa Community Hospital');
$pdf->SetTitle('Crossmatching Report');
$pdf->SetSubject('Crossmatching');
$pdf->SetKeywords('Crossmatching, Blood, Report');

// Set header and footer fonts
$pdf->setHeaderFont(['helvetica', '', 16]);
$pdf->setFooterFont(['helvetica', '', 14]);

// Set margins
$pdf->SetMargins(15, 25, 15);
$pdf->SetHeaderMargin(10);
$pdf->SetFooterMargin(10);

// Set auto page breaks
$pdf->SetAutoPageBreak(TRUE, 15);

// Add a page
$pdf->AddPage();

// Output Crossmatching details
$html = '';

// Prepare and fetch Crossmatching details
$query = "SELECT * FROM tbl_crossmatching WHERE crossmatching_id = ?";
$stmt = mysqli_prepare($connection, $query);
mysqli_stmt_bind_param($stmt, 'i', $crossmatching_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

while ($row = mysqli_fetch_assoc($result)) {
    // Calculate age
    $dob = date('Y-m-d', strtotime(str_replace('/', '-', $row['dob'])));
    $year = (date('Y') - date('Y', strtotime($dob)));
    
    // Format dates
    $date_time = date('F d, Y g:i A', strtotime($row['date_time']));
    $extraction_date = !empty($row['extraction_date']) ? date('F d, Y', strtotime($row['extraction_date'])) : 'N/A';
    $expiration_date = !empty($row['expiration_date']) ? date('F d, Y', strtotime($row['expiration_date'])) : 'N/A';
    $dated = !empty($row['dated']) ? date('F d, Y', strtotime($row['dated'])) : 'N/A';
    $time_packed = !empty($row['time_packed']) ? date('g:i A', strtotime($row['time_packed'])) : 'N/A';
    $to_be_consumed_before = !empty($row['to_be_consumed_before']) ? date('g:i A', strtotime($row['to_be_consumed_before'])) : 'N/A';

    // Patient Information
    $html .= '<div style="text-align: center; margin-bottom: 20px;">
                <h3>Blood Crossmatching</h3>
                <p><strong>Patient Name:</strong> ' . htmlspecialchars($row['patient_name'], ENT_QUOTES, 'UTF-8') . ' | 
                <strong>Patient ID:</strong> ' . htmlspecialchars($row['patient_id'], ENT_QUOTES, 'UTF-8') . ' | 
                <strong>Age:</strong> ' . $year . ' | 
                <strong>Gender:</strong> ' . htmlspecialchars($row['gender'], ENT_QUOTES, 'UTF-8') . ' | 
                <strong>Date:</strong> ' . $date_time . '</p>
            </div>';

    // Patient Blood Information
    $html .= '<h4 style="font-size: 13px; text-align: center;">Patient Blood Information</h4>';
    $html .= '<table border="1" cellpadding="5" style="width: 100%;">
                <thead style="background-color: #CCCCCC;">
                    <tr>
                        <th>Patient Blood Type</th>
                        <th>Blood Component</th>
                        <th>Serial Number</th>
                        <th>Extraction Date</th>
                        <th>Expiration Date</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>' . htmlspecialchars($row['patient_blood_type'], ENT_QUOTES, 'UTF-8') . '</td>
                        <td>' . htmlspecialchars($row['blood_component'], ENT_QUOTES, 'UTF-8') . '</td>
                        <td>' . htmlspecialchars($row['serial_number'], ENT_QUOTES, 'UTF-8') . '</td>
                        <td>' . $extraction_date . '</td>
                        <td>' . $expiration_date . '</td>
                    </tr>
                </tbody>
            </table>';

    // Crossmatching Results
    $html .= '<h4 style="font-size: 13px; margin-top: 20px; text-align: center;">Crossmatching Results</h4>';
    $html .= '<table border="1" cellpadding="5" style="width: 100%;">
                <thead style="background-color: #CCCCCC;">
                    <tr>
                        <th>Major Crossmatching</th>
                        <th>Donor\'s Blood Type</th>
                        <th>Packed Red Blood Cell</th>
                        <th>Time Packed</th>
                        <th>Dated</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>' . htmlspecialchars($row['major_crossmatching'], ENT_QUOTES, 'UTF-8') . '</td>
                        <td>' . htmlspecialchars($row['donors_blood_type'], ENT_QUOTES, 'UTF-8') . '</td>
                        <td>' . htmlspecialchars($row['packed_red_blood_cell'], ENT_QUOTES, 'UTF-8') . '</td>
                        <td>' . $time_packed . '</td>
                        <td>' . $dated . '</td>
                    </tr>
                </tbody>
            </table>';

    // System Information
    $html .= '<h4 style="font-size: 13px; margin-top: 20px; text-align: center;">System Information</h4>';
    $html .= '<table border="1" cellpadding="5" style="width: 100%;">
                <thead style="background-color: #CCCCCC;">
                    <tr>
                        <th>Open System</th>
                        <th>Closed System</th>
                        <th>To Be Consumed Before</th>
                        <th>Hours</th>
                        <th>Minor Crossmatching</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>' . htmlspecialchars($row['open_system'], ENT_QUOTES, 'UTF-8') . '</td>
                        <td>' . htmlspecialchars($row['closed_system'], ENT_QUOTES, 'UTF-8') . '</td>
                        <td>' . $to_be_consumed_before . '</td>
                        <td>' . htmlspecialchars($row['hours'], ENT_QUOTES, 'UTF-8') . '</td>
                        <td>' . htmlspecialchars($row['minor_crossmatching'], ENT_QUOTES, 'UTF-8') . '</td>
                    </tr>
                </tbody>
            </table>';

    // Signatures
    $html .= '<div style="position: absolute; bottom: 20px; left: 30px; text-align: left; width: 150px; font-weight: bold;">';
    $html .= '<br><br>';
    $html .= '<strong>____________________</strong><br>';
    $html .= '<strong>Medical Technologist</strong><br><br>';
    $html .= '<strong>____________________</strong><br>';
    $html .= '<strong>Pathologist</strong><br>';
    $html .= '</div>';
}

// Write HTML content to PDF
$pdf->writeHTML($html, true, false, true, false, '');

// Close and output PDF document
$pdf->Output($filename . '.pdf', 'D');
?>