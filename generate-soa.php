<?php
session_start();
if (empty($_SESSION['name'])) {
    header('location:index.php');
    exit;
}

include('includes/connection.php');
require_once('vendor/tecnickcom/tcpdf/tcpdf.php');
require_once('vendor/autoload.php');

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Validate inputs
if (!isset($_GET['id']) || empty($_GET['id'])) {
    die('Error: Billing ID is required.');
}

$billing_id = mysqli_real_escape_string($connection, $_GET['id']);
$patient_type = isset($_GET['type']) ? mysqli_real_escape_string($connection, $_GET['type']) : null;

// Define all billing tables
$billing_tables = [
    'inpatient' => 'tbl_billing_inpatient',
    'hemodialysis' => 'tbl_billing_hemodialysis',
    'newborn' => 'tbl_billing_newborn'
];

// If type isn't specified, search all tables
if ($patient_type === null) {
    $found = false;
    
    foreach ($billing_tables as $type => $table) {
        $query = "SELECT COUNT(*) as count FROM $table WHERE billing_id = '$billing_id' AND deleted = 0";
        $result = mysqli_query($connection, $query);
        
        if ($result && mysqli_fetch_assoc($result)['count'] > 0) {
            $patient_type = $type;
            $found = true;
            break;
        }
    }
    
    if (!$found) {
        die("Error: No active billing record found with ID $billing_id in any billing table.");
    }
} 
// If type is specified but invalid
elseif (!array_key_exists($patient_type, $billing_tables)) {
    die('Error: Invalid patient type specified. Must be inpatient, hemodialysis, or newborn.');
}

// Now we know which table to use
$table = $billing_tables[$patient_type];

// Get the billing data
// Get the billing data with patient type-specific queries
if ($patient_type === 'hemodialysis') {
    $query = "
        SELECT b.*, 
            CONCAT(p.first_name, ' ', p.last_name) AS patient_name,
            p.dob, p.gender, p.address,
            GROUP_CONCAT(o.item_name ORDER BY o.date_time DESC SEPARATOR ', ') AS other_items,
            GROUP_CONCAT(o.item_cost ORDER BY o.date_time DESC SEPARATOR ', ') AS other_costs
        FROM $table b
        LEFT JOIN tbl_patient p ON b.patient_id = p.patient_id
        LEFT JOIN tbl_billing_others o ON b.billing_id = o.billing_id
        WHERE b.billing_id = '$billing_id' AND b.deleted = 0
        GROUP BY b.billing_id
    ";
} elseif ($patient_type === 'newborn') {
    $query = "
        SELECT b.*, 
            CONCAT(n.first_name, ' ', n.last_name) AS patient_name,
            b.dob, b.gender, b.address,
            GROUP_CONCAT(o.item_name ORDER BY o.date_time DESC SEPARATOR ', ') AS other_items,
            GROUP_CONCAT(o.item_cost ORDER BY o.date_time DESC SEPARATOR ', ') AS other_costs
        FROM $table b
        LEFT JOIN tbl_newborn n ON b.newborn_id = n.newborn_id
        LEFT JOIN tbl_billing_others o ON b.billing_id = o.billing_id
        WHERE b.billing_id = '$billing_id' AND b.deleted = 0
        GROUP BY b.billing_id
    ";
} else {
    // Default inpatient query
    $query = "
        SELECT b.*, 
            CONCAT(p.first_name, ' ', p.last_name) AS patient_name,
            p.dob, p.gender, p.address,
            GROUP_CONCAT(o.item_name ORDER BY o.date_time DESC SEPARATOR ', ') AS other_items,
            GROUP_CONCAT(o.item_cost ORDER BY o.date_time DESC SEPARATOR ', ') AS other_costs
        FROM $table b
        LEFT JOIN tbl_patient p ON b.patient_id = p.patient_id
        LEFT JOIN tbl_billing_others o ON b.billing_id = o.billing_id
        WHERE b.billing_id = '$billing_id' AND b.deleted = 0
        GROUP BY b.billing_id
    ";
}
$result = mysqli_query($connection, $query);
if (!$result) {
    die("Database error: " . mysqli_error($connection));
}

$billing_data = mysqli_fetch_assoc($result);
if (!$billing_data) {
    die("Error: Could not retrieve billing data for ID $billing_id from $table");
}

// Calculate age with proper date format handling
$dob = $billing_data['dob'];
if (strpos($dob, '/') !== false) {
    // Handle DD/MM/YYYY format
    $dateParts = explode('/', $dob);
    if (count($dateParts) === 3) {
        $dob = $dateParts[2].'-'.$dateParts[1].'-'.$dateParts[0]; // Convert to YYYY-MM-DD
    }
}

try {
    $dobDate = new DateTime($dob);
    $now = new DateTime();
    $age = $now->diff($dobDate)->y;
} catch (Exception $e) {
    $age = 'N/A';
}

// Format dates with proper handling
$dob = $billing_data['dob'];
if (strpos($dob, '/') !== false) {
    // Handle DD/MM/YYYY format
    $dateParts = explode('/', $dob);
    if (count($dateParts) === 3) {
        $dob = $dateParts[2].'-'.$dateParts[1].'-'.$dateParts[0]; // Convert to YYYY-MM-DD
    }
}

try {
    $dobDate = new DateTime($dob);
    $now = new DateTime();
    
    if ($patient_type === 'newborn') {
        // For newborns, calculate age in days if less than 1 month, or months if less than 1 year
        $diff = $now->diff($dobDate);
        
        if ($diff->y > 0) {
            $age = $diff->y;
        } elseif ($diff->m > 0) {
            $age = $diff->m . ' month' . ($diff->m > 1 ? 's' : '');
        } else {
            $age = $diff->d . ' day' . ($diff->d > 1 ? 's' : '');
        }
    } else {
        // For regular patients, show age in years
        $age = $now->diff($dobDate)->y;
    }
} catch (Exception $e) {
    $age = 'N/A';
}

// Format dates with proper handling
function formatDate($dateString) {
    if (empty($dateString)) return 'N/A';
    
    if (strpos($dateString, '/') !== false) {
        $dateParts = explode('/', $dateString);
        if (count($dateParts) === 3) {
            $dateString = $dateParts[2].'-'.$dateParts[1].'-'.$dateParts[0];
        }
    }
    
    try {
        return date('F d, Y g:i A', strtotime($dateString));
    } catch (Exception $e) {
        return 'N/A';
    }
}

$admission_date = !empty($billing_data['admission_date']) ? formatDate($billing_data['admission_date']) : 'N/A';
$discharge_date = !empty($billing_data['discharge_date']) ? formatDate($billing_data['discharge_date']) : 'N/A';
$transaction_date = formatDate($billing_data['transaction_datetime']);

class SOAPDF extends TCPDF {
    public function Header() {
        // Logo
        $image_file = __DIR__ . '/assets/img/srchlogo.png';
        $this->Image($image_file, 15, 5, 24, '', 'PNG', '', 'T', false, 300, '', false, false, 0, false, false, false);
        
        // Hospital Name
        $this->SetFont('helvetica', 'B', 16);
        $this->SetY(10);
        $this->Cell(0, 8, 'SANTA ROSA COMMUNITY HOSPITAL', 0, 1, 'C');
        
        // Address and Contact
        $this->SetFont('helvetica', '', 10);
        $this->Cell(0, 5, 'LM Subdivision, Market Area, Santa Rosa City, Laguna', 0, 1, 'C');
        $this->Cell(0, 5, 'Email: srcityhospital1995@gmail.com', 0, 1, 'C');
        
        // Title
        $this->SetFont('helvetica', 'B', 14);
        $this->Cell(0, 10, 'STATEMENT OF ACCOUNT', 0, 1, 'C');
        
        // Line separator
        $this->Line(15, 40, 195, 40);
        $this->Ln(5);
    }

    // Changed to public method
    public function sectionHeader($title) {
        $this->SetFillColor(57, 99, 158); // SRCH blue
        $this->SetTextColor(255);
        $this->SetFont('helvetica', 'B', 12);
        $this->Cell(0, 8, $title, 0, 1, 'L', true);
        $this->SetTextColor(0);
        $this->Ln(2);
    }

    public function Footer() {
        $this->SetY(-15);
        $this->SetFont('helvetica', 'I', 8);
        $this->Cell(0, 10, 'Page '.$this->getAliasNumPage().'/'.$this->getAliasNbPages(), 0, 0, 'C');
    }
}
// Initialize PDF
$pdf = new SOAPDF('P', 'mm', 'A4', true, 'UTF-8', false);
$pdf->SetMargins(15, 30, 15);
$pdf->SetHeaderMargin(10);
$pdf->SetFooterMargin(15);
$pdf->SetAutoPageBreak(TRUE, 25);
$pdf->AddPage();

// Patient Information Section - Two-column layout
$pdf->sectionHeader('PATIENT INFORMATION');

$patient_id = ($patient_type === 'newborn') ? $billing_data['newborn_id'] : $billing_data['patient_id'];

// Define the information for left and right columns
$left_column = [
    'Billing ID' => $billing_data['billing_id'],
    'Patient ID' => $patient_id,
    'Patient Name' => $billing_data['patient_name'],
    'Age' => $age,
    'Gender' => $billing_data['gender'],
    'Address' => $billing_data['address']
];

$right_column = [
    'Diagnosis' => $billing_data['diagnosis'],
    'Date and Time' => $transaction_date,
    'Admission Date' => $admission_date,
    'Discharge Date' => $discharge_date,
    'First Case' => $billing_data['first_case'],
    'Second Case' => $billing_data['second_case']
];

$pdf->SetFont('helvetica', '', 10);

// Create two columns
$col_width = 105; // Each column width
$line_height = 6;

// Left Column
$pdf->SetFont('helvetica', 'B', 10);
$pdf->Ln(2);

// Get the maximum number of rows between both columns
$max_rows = max(count($left_column), count($right_column));

for ($i = 0; $i < $max_rows; $i++) {
    $left_key = array_keys($left_column)[$i] ?? null;
    $right_key = array_keys($right_column)[$i] ?? null;
    
    // Left column item
    if ($left_key) {
        $pdf->SetFont('helvetica', 'B', 9);
        $pdf->Cell(35, $line_height, $left_key . ':', 0, 0);
        $pdf->SetFont('helvetica', '', 9);
        $pdf->Cell($col_width - 35, $line_height, $left_column[$left_key], 0, 0);
    } else {
        $pdf->Cell($col_width, $line_height, '', 0, 0);
    }
    
    // Right column item
    if ($right_key) {
        $pdf->SetFont('helvetica', 'B', 9);
        $pdf->Cell(35, $line_height, $right_key . ':', 0, 0);
        $pdf->SetFont('helvetica', '', 9);
        $pdf->Cell($col_width - 35, $line_height, $right_column[$right_key], 0, 1);
    } else {
        $pdf->Cell($col_width, $line_height, '', 0, 1);
    }
}

$pdf->Ln(5); // Space after section

// Charges Table with improved styling
$pdf->sectionHeader('CHARGES DETAILS');

// Table header with better styling
$pdf->SetFillColor(57, 99, 158);
$pdf->SetTextColor(255);
$pdf->SetFont('helvetica', 'B', 10);
$pdf->Cell(90, 8, 'Description', 1, 0, 'C', true);
$pdf->Cell(45, 8, 'Gross Amount', 1, 0, 'C', true);
$pdf->Cell(45, 8, 'Net Amount', 1, 1, 'C', true);
$pdf->SetTextColor(0);

// Table rows with alternating colors
$charges = [
    'Room Charges' => ['amount' => $billing_data['room_fee'], 'discount' => $billing_data['room_discount']],
    'Laboratory Charges' => ['amount' => $billing_data['lab_fee'], 'discount' => $billing_data['lab_discount']],
    'Radiology Charges' => ['amount' => $billing_data['rad_fee'], 'discount' => $billing_data['rad_discount']],
    'Medication Charges' => ['amount' => $billing_data['medication_fee'], 'discount' => $billing_data['med_discount']],
    'Operating Room Charges' => ['amount' => $billing_data['operating_room_fee'], 'discount' => $billing_data['or_discount']],
    'Supplies Charges' => ['amount' => $billing_data['supplies_fee'], 'discount' => $billing_data['supplies_discount']],
    'Professional Fee' => ['amount' => $billing_data['professional_fee'], 'discount' => $billing_data['pf_discount']],
    'Reader\'s Fee' => ['amount' => $billing_data['readers_fee'], 'discount' => $billing_data['readers_discount']]
];

if (!empty($billing_data['other_items'])) {
    $other_items_total = array_sum(explode(',', $billing_data['other_costs']));
    $charges['Other Charges (' . $billing_data['other_items'] . ')'] = [
        'amount' => $other_items_total,
        'discount' => $billing_data['other_discount']
    ];
}

$fill = false;
foreach ($charges as $desc => $data) {
    $pdf->SetFillColor($fill ? 240 : 255, $fill ? 240 : 255, $fill ? 240 : 255);
    $net = $data['amount'] - $data['discount'];
    $pdf->Cell(90, 8, $desc, 1, 0, 'L', $fill);
    $pdf->Cell(45, 8, number_format($data['amount'], 2), 1, 0, 'R', $fill);
    $pdf->Cell(45, 8, number_format($net, 2), 1, 1, 'R', $fill);
    $fill = !$fill;
}

// Total row with emphasis
$total_charges = array_sum(array_column($charges, 'amount'));
$total_discounts = array_sum(array_column($charges, 'discount'));
$total_net = $total_charges - $total_discounts;

$pdf->SetFont('helvetica', 'B', 10);
$pdf->SetFillColor(220, 220, 220);
$pdf->Cell(90, 8, 'TOTAL', 1, 0, 'L', true);
$pdf->Cell(45, 8, number_format($total_charges, 2), 1, 0, 'R', true);
$pdf->Cell(45, 8, number_format($total_net, 2), 1, 1, 'R', true);

$pdf->Ln(8);

// Additional Discounts with better layout
$pdf->sectionHeader('ADDITIONAL DISCOUNTS');

$discounts = [
    'VAT Exempt' => $billing_data['vat_exempt_discount_amount'],
    'Senior Citizen Discount' => $billing_data['discount_amount'],
    'PWD Discount' => $billing_data['pwd_discount_amount'],
    'PhilHealth PF' => $billing_data['philhealth_pf'],
    'PhilHealth HB' => $billing_data['philhealth_hb']
];

$pdf->SetFont('helvetica', '', 10);
foreach ($discounts as $label => $amount) {
    $pdf->Cell(90, 6, $label . ':', 0, 0);
    $pdf->Cell(0, 6, number_format($amount, 2), 0, 1, 'R');
}

$pdf->Ln(8);

// Amount Due with prominent styling
$pdf->SetFont('helvetica', 'B', 14);
$pdf->SetFillColor(57, 99, 158);
$pdf->SetTextColor(255);
$pdf->Cell(90, 10, 'TOTAL AMOUNT DUE:', 0, 0, 'L', true);
$pdf->Cell(0, 10, number_format($billing_data['total_due'], 2), 0, 1, 'R', true);
$pdf->SetTextColor(0);

// Notes section with better formatting
$pdf->Ln(5); // Reduced space before notes
$pdf->SetFont('helvetica', 'I', 9);
$notes = "Notes:\n1. This is an official Statement of Account.\n2. Please settle within 30 days.\n3. For discrepancies, contact our billing department immediately.";

// Check if we need a new page for the notes
if ($pdf->GetY() + 20 > $pdf->GetPageHeight() - 25) {
    $pdf->AddPage();
}

$pdf->MultiCell(0, 5, $notes, 0, 'L');
// Output PDF
$pdf->Output('SOA_' . $billing_data['billing_id'] . '.pdf', 'I'); 