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

// Fetch invoice details
if (isset($_GET['id'])) {
    $invoice_id = mysqli_real_escape_string($connection, $_GET['id']);
    $filename = isset($_GET['filename']) ? htmlspecialchars($_GET['filename'], ENT_QUOTES, 'UTF-8') : 'invoice_' . $invoice_id;

    // Use prepared statements to prevent SQL injection
    $stmt = $connection->prepare("SELECT invoice_id, patient_name, medicine_name, medicine_brand, price, quantity, invoice_datetime FROM tbl_pharmacy_invoice WHERE invoice_id = ?");
    $stmt->bind_param("s", $invoice_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result && $result->num_rows > 0) {
        // Initialize arrays to store medicine details
        $medicines = [];
        $brands = [];
        $prices = [];
        $quantities = [];

        while ($row = $result->fetch_assoc()) {
            // Populate arrays with medicine details
            $medicines[] = htmlspecialchars($row['medicine_name'], ENT_QUOTES, 'UTF-8');
            $brands[] = htmlspecialchars($row['medicine_brand'], ENT_QUOTES, 'UTF-8');
            $prices[] = htmlspecialchars($row['price'], ENT_QUOTES, 'UTF-8');
            $quantities[] = htmlspecialchars($row['quantity'], ENT_QUOTES, 'UTF-8');
            $patient_name = htmlspecialchars($row['patient_name'], ENT_QUOTES, 'UTF-8'); // Assign patient name here
            $total_price = $row['price'] * $row['quantity']; // Calculate total price here
        }
    } else {
        echo "Invoice not found";
        exit; // Stop script execution
    }
    $stmt->close();
} else {
    echo "Invalid request";
    exit;
}

// Create new PDF document
$pdf = new TCPDF('L', 'mm', 'A4', true, 'UTF-8', false);

// Set document information
$pdf->SetCreator('Your Creator');
$pdf->SetAuthor('Your Name');
$pdf->SetTitle('Pharmacy Invoice');
$pdf->SetSubject('Invoice');
$pdf->SetKeywords('Pharmacy, Invoice');

// Set default header data
$pdf->SetHeaderData('assets/img/srchlogo.png', 78, 'Santa Rosa Community Hospital', '        City of Santa Rosa, Laguna');

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

// Output invoice details
$html = '
<div class="invoice-title" style="margin-top: 20px; text-align: center;">
    <h2>Pharmacy Invoice</h2>
</div>

<div class="invoice-header">
    <img src="assets/img/srchlogo.png" alt="Logo" style="max-width: 90px; height: 80;">
</div>

<div class="invoice-details" style="margin-top: 10px;">
    <div class="invoice-id" style="display: inline-block; margin-right: 20px;">Invoice ID: ' . htmlspecialchars($invoice_id, ENT_QUOTES, 'UTF-8') . '</div>
    <div class="invoice-date" style="display: inline-block; margin-right: 20px;">Date: ' . date('F d Y g:i A') . '</div>
    <div class="patient-name" style="display: inline-block;">Patient Name: ' . htmlspecialchars($patient_name, ENT_QUOTES, 'UTF-8') . '</div>
</div>

<div class="invoice-items" style="margin-top: 10px;">
    <table border="1" cellpadding="5" style="width: 100%;">
        <thead>
            <tr>
                <th>Medicine Name</th>
                <th>Medicine Brand</th>
                <th>Quantity</th>
                <th>Price</th>
                <th>Total Price</th>
            </tr>
        </thead>
        <tbody>';

$total_price = 0; // Initialize total price
for ($i = 0; $i < count($medicines); $i++) {
    $total_price_item = $prices[$i] * $quantities[$i]; // Calculate total price for current item
    $total_price += $total_price_item; // Accumulate total price for all items
    $html .= '<tr>
                <td>' . $medicines[$i] . '</td>
                <td>' . $brands[$i] . '</td>
                <td>' . $quantities[$i] . ' pcs</td>
                <td>' . $prices[$i] . '</td>
                <td>' . $total_price_item . '</td>
            </tr>';
}

$html .= '</tbody>
    </table>
</div>

<div class="invoice-total" style="margin-top: 20px;">
    <div class="total">Total Cost: ' . $total_price . '</div>
</div>';

$pdf->writeHTML($html, true, false, true, false, '');

// Close and output PDF document
$pdf->Output($filename . '.pdf', 'D');
