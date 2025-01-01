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
    $urinalysis_id = $_GET['id'];
    mysqli_query($connection, "SET NAMES 'utf8'");
    $filename = isset($_GET['filename']) ? $_GET['filename'] : 'urinalysis_' . $urinalysis_id;
}

// Create new PDF document
$pdf = new TCPDF('L', 'mm', 'A4', true, 'UTF-8', false);

// Set document information
$pdf->SetCreator('Your Creator');
$pdf->SetAuthor('Your Name');
$pdf->SetTitle('Urinalysis Report');
$pdf->SetSubject('Urinalysis');
$pdf->SetKeywords('Urinalysis, Report');

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

// Output urinalysis details
$html = '';

// Fetch urinalysis details
$fetch_query = mysqli_query($connection, "SELECT * FROM tbl_urinalysis WHERE urinalysis_id = '$urinalysis_id'");

foreach ($fetch_query as $row) {
    // Calculate age
    $dob = date('Y-m-d', strtotime(str_replace('/', '-', $row['dob'])));
    $year = (date('Y') - date('Y', strtotime($dob)));

    // Patient Information
    $html .= '<div style="text-align: center; margin-bottom: 20px;">
                <img src="assets/img/srchlogo.png" alt="Hospital Logo" style="max-width: 120px; height: 108px;">
                <h3>Urinalysis</h3>
                <p><strong>Patient Name:</strong> <strong>'.$row['patient_name'].'</strong> | <strong>Age:</strong> <strong>'.$year.'</strong> | <strong>Gender:</strong> <strong>'.$row['gender'].'</strong> | <strong>Date and Time:</strong> <strong>'.date('F d Y g:i A', strtotime($row['date_time'])).'</strong></p>
            </div>';

    // Macroscopic Table
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
                        <td><strong>'.$row['color'].'</strong></td>
                        <td><strong>'.$row['transparency'].'</strong></td>
                        <td><strong>'.$row['reaction'].'</strong></td>
                        <td><strong>'.$row['protein'].'</strong></td>
                        <td><strong>'.$row['glucose'].'</strong></td>
                        <td><strong>'.$row['specific_gravity'].'</strong></td>
                        <td><strong>'.$row['ketone'].'</strong></td>
                        <td><strong>'.$row['urobilinogen'].'</strong></td>
                        <td><strong>'.$row['pregnancy_test'].'</strong></td>
                    </tr>
                </tbody>
            </table>';

    // Microscopic Table
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
                        <td><strong>'.$row['pus_cells'].'</strong></td>
                        <td><strong>'.$row['red_blood_cells'].'</strong></td>
                        <td><strong>'.$row['epithelial_cells'].'</strong></td>
                        <td><strong>'.$row['a_urates_a_phosphates'].'</strong></td>
                        <td><strong>'.$row['mucus_threads'].'</strong></td>
                        <td><strong>'.$row['bacteria'].'</strong></td>
                        <td><strong>'.$row['calcium_oxalates'].'</strong></td>
                        <td><strong>'.$row['uric_acid_crystals'].'</strong></td>
                        <td><strong>'.$row['pus_cells_clumps'].'</strong></td>
                        <td><strong>'.$row['coarse_granular_cast'].'</strong></td>
                        <td><strong>'.$row['hyaline_cast'].'</strong></td>
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
    $pdf->writeHTML($html, true, false, true, false, '');

    // Close and output PDF document
    $pdf->Output($filename . '.pdf', 'D');
    ?>
