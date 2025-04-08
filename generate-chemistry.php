<?php
session_start();
if (empty($_SESSION['name'])) {
    header('location:index.php');
    exit; // Ensure script stops execution after redirection
}

// filepath: /c:/xampp/htdocs/hms/generate-chemistry.php
include('includes/connection.php');

// Include TCPDF library
require_once('vendor/tecnickcom/tcpdf/tcpdf.php');
require_once('vendor/autoload.php');

// Fetch Chemistry Panel details
if (isset($_GET['id'])) {
    $chem_id = mysqli_real_escape_string($connection, $_GET['id']); // Prevent SQL injection
    mysqli_query($connection, "SET NAMES 'utf8'");
    $filename = isset($_GET['filename']) ? htmlspecialchars($_GET['filename'], ENT_QUOTES, 'UTF-8') : 'chemistry_' . $chem_id;
}

// Extend TCPDF class to create custom Header
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
$pdf->SetTitle('Chemistry Panel Report');
$pdf->SetSubject('Chemistry Panel');
$pdf->SetKeywords('Chemistry, Panel, Report');

// Set header and footer fonts
$pdf->setHeaderFont(['helvetica', '', 16]);
$pdf->setFooterFont(['helvetica', '', 14]);

// Set default monospaced font
$pdf->SetDefaultMonospacedFont(PDF_FONT_MONOSPACED);

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

// Output Chemistry Panel details
$html = '';

// Prepare and fetch Chemistry Panel details using a prepared statement
$query = "SELECT * FROM tbl_chemistry WHERE chem_id = ?";
$stmt = mysqli_prepare($connection, $query);
mysqli_stmt_bind_param($stmt, 'i', $chem_id); // Bind parameter to prevent SQL injection
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

while ($row = mysqli_fetch_assoc($result)) {
    // Calculate age
    $dob = date('Y-m-d', strtotime(str_replace('/', '-', $row['dob'])));
    $year = (date('Y') - date('Y', strtotime($dob)));

    // Patient Information
    $html .= '<div style="text-align: center; margin-bottom: 20px;">
                <h3>Chemistry Panel</h3>
                <p><strong>Patient Name:</strong> ' . htmlspecialchars($row['patient_name'], ENT_QUOTES, 'UTF-8') . ' | 
                <strong>Age:</strong> ' . $year . ' | 
                <strong>Gender:</strong> ' . htmlspecialchars($row['gender'], ENT_QUOTES, 'UTF-8') . ' | 
                <strong>Date and Time:</strong> ' . date('F d Y g:i A', strtotime($row['date_time'])) . '</p>
            </div>';

    $html .= '<table border="1" cellpadding="5" style="width: 100%; text-align: center;">
                <thead style="background-color: #CCCCCC;">
                    <tr>   
                        <th>FBS</th>
                        <th>PPBS</th>
                        <th>BUN</th>
                        <th>Creatinine</th>
                        <th>Uric Acid</th>
                        <th>Total Cholesterol</th>
                        <th>Triglycerides</th>
                        <th>HDL</th>
                        <th>LDL</th>
                        <th>VLDL</th>
                        <th>AST</th>
                        <th>ALT</th>
                        <th>ALP</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><strong>' . htmlspecialchars($row['fbs'], ENT_QUOTES, 'UTF-8') . '</strong></td>
                        <td><strong>' . htmlspecialchars($row['ppbs'], ENT_QUOTES, 'UTF-8') . '</strong></td>
                        <td><strong>' . htmlspecialchars($row['bun'], ENT_QUOTES, 'UTF-8') . '</strong></td>
                        <td><strong>' . htmlspecialchars($row['crea'], ENT_QUOTES, 'UTF-8') . '</strong></td>
                        <td><strong>' . htmlspecialchars($row['bua'], ENT_QUOTES, 'UTF-8') . '</strong></td>
                        <td><strong>' . htmlspecialchars($row['tc'], ENT_QUOTES, 'UTF-8') . '</strong></td>
                        <td><strong>' . htmlspecialchars($row['tg'], ENT_QUOTES, 'UTF-8') . '</strong></td>
                        <td><strong>' . htmlspecialchars($row['hdl'], ENT_QUOTES, 'UTF-8') . '</strong></td>
                        <td><strong>' . htmlspecialchars($row['ldl'], ENT_QUOTES, 'UTF-8') . '</strong></td>
                        <td><strong>' . htmlspecialchars($row['vldl'], ENT_QUOTES, 'UTF-8') . '</strong></td>
                        <td><strong>' . htmlspecialchars($row['ast'], ENT_QUOTES, 'UTF-8') . '</strong></td>
                        <td><strong>' . htmlspecialchars($row['alt'], ENT_QUOTES, 'UTF-8') . '</strong></td>
                        <td><strong>' . htmlspecialchars($row['alp'], ENT_QUOTES, 'UTF-8') . '</strong></td>
                    </tr>
                </tbody>
            </table>';

    // Remarks section
    $html .= '<div style="margin-top: 20px;">
                <h4 style="font-size: 14px;">Remarks:</h4>
                <p>' . htmlspecialchars($row['remarks'], ENT_QUOTES, 'UTF-8') . '</p>
            </div>';

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

// Write HTML content to PDF
$pdf->writeHTML($html, true, false, true, false, '');

// Close and output PDF document
$pdf->Output($filename . '.pdf', 'D');
?>