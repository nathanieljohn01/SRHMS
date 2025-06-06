<?php
session_start();
if (empty($_SESSION['name'])) {
    header('location:index.php');
    exit; // Ensure script stops execution after redirection
}

include('includes/connection.php');

// Include TCPDF library
require_once('vendor/tecnickcom/tcpdf/tcpdf.php');
require_once('vendor/autoload.php');

// Fetch urinalysis details
if (isset($_GET['id'])) {
    // Sanitize and escape the input using mysqli_real_escape_string
    $urinalysis_id = mysqli_real_escape_string($connection, $_GET['id']);
    mysqli_query($connection, "SET NAMES 'utf8'");
    $filename = isset($_GET['filename']) ? mysqli_real_escape_string($connection, $_GET['filename']) : 'urinalysis_' . $urinalysis_id;
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

// Create new PDF document using the extended class
$pdf = new MYPDF('L', 'mm', 'A4', true, 'UTF-8', false);

// Set document information
$pdf->SetCreator('Santa Rosa Hospital');
$pdf->SetAuthor('Santa Rosa Community Hospital');
$pdf->SetTitle('Urinalysis Report');
$pdf->SetSubject('Urinalysis');
$pdf->SetKeywords('Urinalysis, Report');

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

// Output urinalysis details
$html = '';

// Prepare the query using a prepared statement to prevent SQL injection
$query = "SELECT * FROM tbl_urinalysis WHERE urinalysis_id = ?";
$stmt = mysqli_prepare($connection, $query);
mysqli_stmt_bind_param($stmt, 's', $urinalysis_id); // Bind parameter to prevent SQL injection
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);


while ($row = $result->fetch_assoc()) {
    // Calculate age
    $dob = date('Y-m-d', strtotime(str_replace('/', '-', $row['dob'])));
    $year = (date('Y') - date('Y', strtotime($dob)));

    // Patient Information
    $html .= '<div style="text-align: center; margin-bottom: 20px;">
                <h3>Urinalysis</h3>
                <p><strong>Patient Name:</strong> ' . htmlspecialchars($row['patient_name'], ENT_QUOTES, 'UTF-8') . ' | 
                <strong>Age:</strong> ' . $year . ' | 
                <strong>Gender:</strong> ' . htmlspecialchars($row['gender'], ENT_QUOTES, 'UTF-8') . ' | 
                <strong>Date and Time:</strong> ' . date('F d Y g:i A', strtotime($row['date_time'])) . '</p>
            </div>';


    // Macroscopic Table (sanitized with htmlspecialchars)
    $html .= '<h4 style="font-size: 16px; text-align: center;">Macroscopic</h4>';
    $html .= '<table border="1" cellpadding="5" style="width: 100%;">
                <thead style="background-color: #CCCCCC;">
                    <tr>   
                        <th>Color</th>
                        <th>Transparency</th>
                        <th>Reaction (pH)</th>
                        <th>Protein</th>
                        <th>Glucose</th>
                        <th>Specific Gravity</th>
                        <th>Ketone</th>
                        <th>Urobilinogen</th>
                        <th>Pregnancy Test</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><strong>' . htmlspecialchars($row['color']) . '</strong></td>
                        <td><strong>' . htmlspecialchars($row['transparency']) . '</strong></td>
                        <td><strong>' . htmlspecialchars($row['reaction']) . '</strong></td>
                        <td><strong>' . htmlspecialchars($row['protein']) . '</strong></td>
                        <td><strong>' . htmlspecialchars($row['glucose']) . '</strong></td>
                        <td><strong>' . htmlspecialchars($row['specific_gravity']) . '</strong></td>
                        <td><strong>' . htmlspecialchars($row['ketone']) . '</strong></td>
                        <td><strong>' . htmlspecialchars($row['urobilinogen']) . '</strong></td>
                        <td><strong>' . htmlspecialchars($row['pregnancy_test']) . '</strong></td>
                    </tr>
                </tbody>
            </table>';

    // Microscopic Table (sanitized with htmlspecialchars)
    $html .= '<h4 style="font-size: 16px; text-align: center;">Microscopic</h4>';
    $html .= '<table border="1" cellpadding="5" style="width: 100%;">
                <thead style="background-color: #CCCCCC;">
                    <tr>
                        <th>Pus Cells</th>
                        <th>Red Blood Cells</th>
                        <th>Epithelial Cells</th>
                        <th>A Urates/A Phosphates</th>
                        <th>Mucus Threads</th>
                        <th>Bacteria</th>
                        <th>Calcium Oxalates</th>
                        <th>Uric Acid Crystals</th>
                        <th>Pus Cells Clumps</th>
                        <th>Coarse Granular Cast</th>
                        <th>Hyaline Cast</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><strong>' . htmlspecialchars($row['pus_cells']) . '</strong></td>
                        <td><strong>' . htmlspecialchars($row['red_blood_cells']) . '</strong></td>
                        <td><strong>' . htmlspecialchars($row['epithelial_cells']) . '</strong></td>
                        <td><strong>' . htmlspecialchars($row['a_urates_a_phosphates']) . '</strong></td>
                        <td><strong>' . htmlspecialchars($row['mucus_threads']) . '</strong></td>
                        <td><strong>' . htmlspecialchars($row['bacteria']) . '</strong></td>
                        <td><strong>' . htmlspecialchars($row['calcium_oxalates']) . '</strong></td>
                        <td><strong>' . htmlspecialchars($row['uric_acid_crystals']) . '</strong></td>
                        <td><strong>' . htmlspecialchars($row['pus_cells_clumps']) . '</strong></td>
                        <td><strong>' . htmlspecialchars($row['coarse_granular_cast']) . '</strong></td>
                        <td><strong>' . htmlspecialchars($row['hyaline_cast']) . '</strong></td>
                    </tr>
                </tbody>
            </table>';

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
