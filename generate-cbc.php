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
    $cbc_id = $_GET['id'];
    mysqli_query($connection, "SET NAMES 'utf8'");
    $filename = isset($_GET['filename']) ? $_GET['filename'] : 'cbc_' . $cbc_id;
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

// Fetch CBC details
$fetch_query = mysqli_query($connection, "SELECT * FROM tbl_cbc WHERE cbc_id = '$cbc_id'");

foreach ($fetch_query as $row) {
    // Calculate age
    $dob = date('Y-m-d', strtotime(str_replace('/', '-', $row['dob'])));
    $year = (date('Y') - date('Y', strtotime($dob)));

    // Patient Information
    $html .= '<div style="text-align: center; margin-bottom: 20px;">
                <img src="assets/img/srchlogo.png" alt="Hospital Logo" style="max-width: 120px; height: 120px;">
                <h3>Complete Blood Count</h3>
                <p><strong>Patient Name:</strong> <strong>'.$row['patient_name'].'</strong> | <strong>Age:</strong> <strong>'.$year.'</strong> | <strong>Gender:</strong> <strong>'.$row['gender'].'</strong> | <strong>Date and Time:</strong> <strong>'.date('F d Y g:i A', strtotime($row['date_time'])).'</strong></p>
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
                        <td><strong>'.$row['hemoglobin'].'</strong></td>
                        <td><strong>'.$row['hematocrit'].'</strong></td>
                        <td><strong>'.$row['red_blood_cells'].'</strong></td>
                        <td><strong>'.$row['white_blood_cells'].'</strong></td>
                        <td><strong>'.$row['segmenters'].'</strong></td>
                        <td><strong>'.$row['lymphocytes'].'</strong></td>
                        <td><strong>'.$row['monocytes'].'</strong></td>
                        <td><strong>'.$row['bands'].'</strong></td>
                        <td><strong>'.$row['platelets'].'</strong></td>
                    </tr>
                </tbody>
            </table>';



            $html .= '<div style="text-align: left; width: 150px; font-weight: bold; margin-top: 30px;">'; // Increased margin-top
            $html .= '<br>'; //
            $html .= '<br>'; //
            $html .= '<strong>____________________</strong><br>'; // Space for signature
            $html .= '<strong>Medical Technologist</strong><br>'; // Name for Medical Technologist
            $html .= '<br>'; // Additional space
            $html .= '<strong>____________________</strong><br>'; // Space for Pathologist signature
            $html .= '<strong>Pathologist</strong><br>'; // Name for Pathologist
            $html .= '</div>';
}

$pdf->writeHTML($html, true, false, true, false, '');

// Close and output PDF document
$pdf->Output($filename . '.pdf', 'D');
?>
