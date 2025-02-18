<?php
session_start();
if (empty($_SESSION['name'])) {
    header('location:index.php');
    exit; // Ensure script stops execution after redirection
}

// filepath: /c:/xampp/htdocs/hms/generate-cbc.php
include('includes/connection.php');

// Include TCPDF library
require_once('vendor/tecnickcom/tcpdf/tcpdf.php');
require_once('vendor/autoload.php');

// Fetch CBC details
if (isset($_GET['id'])) {
    $cbc_id = mysqli_real_escape_string($connection, $_GET['id']); // Prevent SQL injection
    mysqli_query($connection, "SET NAMES 'utf8'");
    $filename = isset($_GET['filename']) ? htmlspecialchars($_GET['filename'], ENT_QUOTES, 'UTF-8') : 'cbc_' . $cbc_id;
}

// Extend TCPDF class to create custom Header
class MYPDF extends TCPDF {
    //Page header
    public function Header() {
        // Logo
        $image_file = __DIR__ . '/assets/img/srchlogo.png'; // Use absolute path
        $this->Image($image_file, 15, 5, 22, '', 'PNG', '', 'T', false, 300, '', false, false, 0, false, false, false);

        // Set font for the title
        $this->SetFont('helvetica', 'B', 16);

        // Calculate X position for center alignment
        $title = 'Santa Rosa Community Hospital';
        $this->SetY(15); // Set Y position
        $this->Cell(0, 5, $title, 0, 1, 'C', 0, '', 0, false, 'M', 'M'); // Centered title

        // Add some spacing after the title
        $this->Ln(2);

        // Set font for the subtitle
        $this->SetFont('helvetica', '', 14);
        $subtitle = 'City of Santa Rosa, Laguna';
        $this->Cell(0, 5, $subtitle, 0, 1, 'C', 0, '', 0, false, 'M', 'M'); // Centered subtitle

        // Draw a line below the header
        $this->Line(0, 30, 300, 30); // (X1, Y1, X2, Y2)

        // Add some spacing after the header
        $this->Ln(5);
    }
}

// Create new PDF document
$pdf = new MYPDF('L', 'mm', 'A4', true, 'UTF-8', false);

// Set document information
$pdf->SetCreator('Santa Rosa Hospital');
$pdf->SetAuthor('Santa Rosa Community Hospital');
$pdf->SetTitle('CBC Report');
$pdf->SetSubject('CBC');
$pdf->SetKeywords('CBC, Report');

// Set header and footer fonts
$pdf->setHeaderFont(['helvetica', '', 16]);
$pdf->setFooterFont(['helvetica', '', 14]);

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

// Output CBC details
$html = '';

// Prepare and fetch CBC details using a prepared statement
$query = "SELECT * FROM tbl_cbc WHERE cbc_id = ?";
$stmt = mysqli_prepare($connection, $query);
mysqli_stmt_bind_param($stmt, 'i', $cbc_id); // Bind parameter to prevent SQL injection
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

while ($row = mysqli_fetch_assoc($result)) {
    // Calculate age
    $dob = date('Y-m-d', strtotime(str_replace('/', '-', $row['dob'])));
    $year = (date('Y') - date('Y', strtotime($dob)));

    // Patient Information
    $html .= '<div style="text-align: center; margin-bottom: 20px;">
                <h3>Complete Blood Count</h3>
                <p><strong>Patient Name:</strong> ' . htmlspecialchars($row['patient_name'], ENT_QUOTES, 'UTF-8') . ' | 
                <strong>Age:</strong> ' . $year . ' | 
                <strong>Gender:</strong> ' . htmlspecialchars($row['gender'], ENT_QUOTES, 'UTF-8') . ' | 
                <strong>Date and Time:</strong> ' . date('F d Y g:i A', strtotime($row['date_time'])) . '</p>
            </div>';

    // CBC Table
    $html .= '<h4 style="font-size: 16px; text-align: center;">CBC Results</h4>';
    $html .= '<table border="1" cellpadding="5" style="width: 100%; text-align: center;">
                <thead style="background-color: #CCCCCC;">
                    <tr>   
                        <th>Hemoglobin</th>
                        <th>Hematocrit</th>
                        <th>Red Blood Cells</th>
                        <th>White Blood Cells</th>
                        <th>Segmenters</th>
                        <th>Lymphocytes</th>
                        <th>Monocytes</th>
                        <th>Bands</th>
                        <th>Platelets</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><strong>' . htmlspecialchars($row['hemoglobin'], ENT_QUOTES, 'UTF-8') . '</strong></td>
                        <td><strong>' . htmlspecialchars($row['hematocrit'], ENT_QUOTES, 'UTF-8') . '</strong></td>
                        <td><strong>' . htmlspecialchars($row['red_blood_cells'], ENT_QUOTES, 'UTF-8') . '</strong></td>
                        <td><strong>' . htmlspecialchars($row['white_blood_cells'], ENT_QUOTES, 'UTF-8') . '</strong></td>
                        <td><strong>' . htmlspecialchars($row['segmenters'], ENT_QUOTES, 'UTF-8') . '</strong></td>
                        <td><strong>' . htmlspecialchars($row['lymphocytes'], ENT_QUOTES, 'UTF-8') . '</strong></td>
                        <td><strong>' . htmlspecialchars($row['monocytes'], ENT_QUOTES, 'UTF-8') . '</strong></td>
                        <td><strong>' . htmlspecialchars($row['bands'], ENT_QUOTES, 'UTF-8') . '</strong></td>
                        <td><strong>' . htmlspecialchars($row['platelets'], ENT_QUOTES, 'UTF-8') . '</strong></td>
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

// Write HTML content to PDF
$pdf->writeHTML($html, true, false, true, false, '');

// Close and output PDF document
$pdf->Output($filename . '.pdf', 'D');
?>
