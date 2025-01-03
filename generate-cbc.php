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

// Fetch CBC details
if (isset($_GET['id'])) {
    $cbc_id = mysqli_real_escape_string($connection, $_GET['id']); // Prevent SQL injection
    mysqli_query($connection, "SET NAMES 'utf8'");
    $filename = isset($_GET['filename']) ? htmlspecialchars($_GET['filename'], ENT_QUOTES, 'UTF-8') : 'cbc_' . $cbc_id;
}

// Create new PDF document
$pdf = new TCPDF('L', 'mm', 'A4', true, 'UTF-8', false);

// Set document information
$pdf->SetCreator('Your Creator');
$pdf->SetAuthor('Your Name');
$pdf->SetTitle('CBC Report');
$pdf->SetSubject('CBC');
$pdf->SetKeywords('CBC, Report');

// Set default header data
$pdf->SetHeaderData('assets/img/srchlogo.png', 78, 'Santa Rosa Community Hospital', '    City Government of Santa Rosa');

// Set header and footer fonts
$pdf->setHeaderFont(Array('helvetica', '', 14));
$pdf->setFooterFont(Array('helvetica', '', 14));

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
                <img src="assets/img/srchlogo.png" alt="Hospital Logo" style="max-width: 120px; height: 120px;">
                <h3>Complete Blood Count</h3>
                <p><strong>Patient Name:</strong> <strong>' . htmlspecialchars($row['patient_name'], ENT_QUOTES, 'UTF-8') . '</strong> | 
                <strong>Age:</strong> <strong>' . $year . '</strong> | 
                <strong>Gender:</strong> <strong>' . htmlspecialchars($row['gender'], ENT_QUOTES, 'UTF-8') . '</strong> | 
                <strong>Date and Time:</strong> <strong>' . date('F d Y g:i A', strtotime($row['date_time'])) . '</strong></p>
            </div>';

    // CBC Table
    $html .= '<h4 style="font-size: 16px; text-align: center;">CBC Results</h4>';
    $html .= '<table border="1" cellpadding="5" style="width: 100%;">
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
