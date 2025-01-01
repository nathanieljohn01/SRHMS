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
    $invoice_id = $_GET['id'];
    mysqli_query($connection, "SET NAMES 'utf8'");
    $filename = isset($_GET['filename']) ? $_GET['filename'] : 'invoice_' . $invoice_id;
    $fetch_query = mysqli_query($connection, "SELECT invoice_id, patient_name, medicine_name, medicine_brand, price, quantity, invoice_datetime FROM tbl_pharmacy_invoice WHERE invoice_id = '$invoice_id'");
    if ($fetch_query && mysqli_num_rows($fetch_query) > 0) {
        // Initialize arrays to store medicine details
        $medicines = array();
        $brands = array();
        $prices = array();
        $quantities = array();
        
        while ($row = mysqli_fetch_assoc($fetch_query)) {
            // Populate arrays with medicine details
            $medicines[] = $row['medicine_name'];
            $brands[] = $row['medicine_brand'];
            $prices[] = $row['price'];
            $quantities[] = $row['quantity'];
            $patient_name = $row['patient_name']; // Assign patient name here
            $total_price = $row['price'] * $row['quantity']; // Calculate total price here
        }
    } else {
        // Handle case where no invoice is found with given ID
        // For example, redirect user to a 404 page or display an error message
        echo "Invoice not found";
        exit; // Stop script execution
    }
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
    <img src="assets/img/srchlogo.png" alt="Logo" style="max-width: 120px; height: 120;">
</div>

<div class="invoice-details" style="margin-top: 20px;">
    <div class="invoice-id" style="display: inline-block; margin-right: 20px;">Invoice ID: '.$invoice_id.'</div>
    <div class="invoice-date" style="display: inline-block; margin-right: 20px;">Date: '.date('F d Y g:i A').'</div>
    <div class="patient-name" style="display: inline-block;">Patient Name: '.$patient_name.'</div>
</div>

<div class="invoice-items" style="margin-top: 20px;">
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

// Iterate over medicine details arrays
$total_price = 0; // Initialize total price
for ($i = 0; $i < count($medicines); $i++) {
    $total_price_item = $prices[$i] * $quantities[$i]; // Calculate total price for current item
    $total_price += $total_price_item; // Accumulate total price for all items
    $html .= '<tr>
                <td>'.$medicines[$i].'</td>
                <td>'.$brands[$i].'</td>
                <td>'.$quantities[$i].' pcs</td>
                <td>'.$prices[$i].'</td> <!-- Use Unicode character for peso sign -->
                <td>'.$total_price_item.'</td> <!-- Use Unicode character for peso sign -->
            </tr>';
}

$html .= '</tbody>
    </table>
</div>

<div class="invoice-total" style="margin-top: 20px;">
    <div class="total">Total Cost:'.$total_price.'</div> 
</div>';

$pdf->writeHTML($html, true, false, true, false, '');

// Close and output PDF document
$pdf->Output($filename . '.pdf', 'D');
