<?php
session_start();
if (empty($_SESSION['name'])) {
    header('location:index.php');
    exit();
}
include('header.php');
include('includes/connection.php');

// Initialize total cost variable
$total_cost = 0;

$fetch_query = mysqli_query($connection, "SELECT MAX(id) as id FROM tbl_pharmacy_invoice");
$row = mysqli_fetch_row($fetch_query);
$inv_id = ($row[0] == 0) ? 1 : $row[0] + 1;

if (isset($_POST['add-invoice'])) {
    // Sanitize and escape patient name and ID
    $invoice_id = 'INV-' . $inv_id;
    $invoice_id = mysqli_real_escape_string($connection, $invoice_id);

    $patient_name = mysqli_real_escape_string($connection, $_POST['patient_name']);
    $patient_id = intval($_POST['patient_id']);

    // Fetch patient details
    $fetch_query = mysqli_query($connection, "SELECT patient_id, gender, dob, patient_type FROM tbl_patient WHERE CONCAT(first_name, ' ', last_name) = '$patient_name'");
    $patient = mysqli_fetch_assoc($fetch_query);

    $patient_id = $patient['patient_id']; // Assign patient_id from the fetched data
    $gender = $patient['gender'];
    $dob = $patient['dob'];
    $patient_type = $patient['patient_type'];

    // Sanitize and escape medicine details
    $medicine_ids = array_map('intval', $_POST['medicine_id']); // Ensure all medicine IDs are integers
    $quantities = array_map('intval', $_POST['quantity']); // Ensure all quantities are integers

    // Initialize variables for medicine details
    $medicines_details = [];
    $total_cost = 0;

    // Loop through selected medicines
    foreach ($medicine_ids as $key => $medicine_id) {
        // Get medicine details from tbl_medicines including price
        $get_medicine_query = mysqli_query($connection, "SELECT medicine_name, medicine_brand, expiration_date, price, quantity FROM tbl_medicines WHERE id = $medicine_id AND deleted = 0");
        $medicine_row = mysqli_fetch_assoc($get_medicine_query);

        $medicine_name = mysqli_real_escape_string($connection, $medicine_row['medicine_name']);
        $medicine_brand = mysqli_real_escape_string($connection, $medicine_row['medicine_brand']);
        $expiration_date = mysqli_real_escape_string($connection, $medicine_row['expiration_date']);
        $price = round(floatval($medicine_row['price']), 2); // Round the price to 2 decimal places
        $item_total_price = round($price * $quantities[$key], 2); // Round the total price for each medicine to 2 decimal places        
        $available_quantity = intval($medicine_row['quantity']); // Ensure quantity is an integer

        // Check if available quantity is sufficient
        if ($available_quantity < $quantities[$key]) {
            $msg = "Insufficient quantity for medicine: $medicine_name";
            break; // Exit loop if quantity is insufficient
        }

        // Deduct selected quantity from available quantity
        $updated_quantity = $available_quantity - $quantities[$key];
        mysqli_query($connection, "UPDATE tbl_medicines SET quantity = $updated_quantity WHERE id = $medicine_id AND deleted = 0");

        // Store medicine details to insert later
        $medicines_details[] = [
            'medicine_name' => $medicine_name,
            'medicine_brand' => $medicine_brand,
            'quantity' => $quantities[$key],
            'price' => $price,
            'item_total_price' => $price * $quantities[$key]
        ];

        // Calculate total price for each medicine
        $total_cost += $price * $quantities[$key]; // Add this item's total price to the total cost
    }

    // Insert all medicines in one transaction under the same invoice ID
    if (empty($msg)) {
        // Insert invoice details into tbl_pharmacy_invoice
        foreach ($medicines_details as $medicine) {
            $insert_invoice_query = "INSERT INTO tbl_pharmacy_invoice (invoice_id, patient_id, patient_name, medicine_name, medicine_brand, quantity, price, total_price, total_cost, invoice_datetime) 
            VALUES ('$invoice_id', '$patient_id', '$patient_name', '{$medicine['medicine_name']}', '{$medicine['medicine_brand']}', {$medicine['quantity']}, '{$medicine['price']}', '{$medicine['item_total_price']}', '$total_cost', NOW())";

            if (!mysqli_query($connection, $insert_invoice_query)) {
                $msg = "Error inserting invoice!";
                break; // Exit loop if insertion fails
            }
        }
    }

    if (empty($msg)) {
        $msg = "Invoice(s) added successfully";
    }
}
?>

<div class="page-wrapper">
    <div class="content">
        <div class="row">
            <div class="col-sm-4">
                <h4 class="page-title">Add Pharmacy Invoice</h4>
            </div>
            <div class="col-sm-8 text-right m-b-20">
                <a href="pharmacy-invoice.php" class="btn btn-primary btn-rounded float-right">Back</a>
            </div>
        </div>
        <div class="container">
            <form method="post" action="add-pharmacy-invoice.php">
                <div class="row">
                    <!-- Invoice ID -->
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>Invoice ID</label>
                            <input class="form-control" type="text" name="invoice_id" value="<?php echo 'INV-' . (isset($inv_id) ? $inv_id : '1'); ?>" disabled>
                        </div>
                    </div>

                    <!-- Patient Search -->
                    <div class="col-md-6">
                        <div class="form-group position-relative">
                            <label for="patient_name_search">Patient Name</label>
                            <input type="text" class="form-control" id="patient_name_search" name="patient_name" placeholder="Search for a patient" autocomplete="off" required>
                            <input type="hidden" id="patient_id" name="patient_id">
                            <!-- Search Results Container -->
                            <div id="patient_name_results" class="search-results"></div>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-12">
                        <div class="table-responsive">
                            <h4>Medicines</h4>
                            <input class="form-control" type="text" id="medicineSearchInput" onkeyup="filterMedicines()" placeholder="Search for Medicine">
                            <table class="datatable table table-hover" id="medicineTable">
                                <thead style="background-color: #CCCCCC;">
                                    <tr>
                                        <th>Medicine Name</th>
                                        <th>Medicine Brand</th>
                                        <th>Available Stock</th>
                                        <th>Price</th>
                                        <th>Expiration Date</th>
                                        <th>Days to Expire</th>
                                        <th>Select</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $fetch_medicines_query = mysqli_query($connection, "SELECT id, medicine_name, medicine_brand, quantity, price, expiration_date FROM tbl_medicines WHERE deleted = 0");
                                    while ($medicine_row = mysqli_fetch_array($fetch_medicines_query)) {
                                        $expiration_date = strtotime($medicine_row['expiration_date']);
                                        $current_date = strtotime(date('Y-m-d'));
                                        $days_to_expire = round(($expiration_date - $current_date) / (60 * 60 * 24));

                                        // Check if stock is low (20 or less)
                                        $is_low_stock = $medicine_row['quantity'] <= 20;

                                        if ($days_to_expire > 1) { // Medicine is still valid
                                    ?>
                                            <tr class="<?php echo $is_low_stock ? 'low-stock' : ''; ?>">
                                                <td><?php echo $medicine_row['medicine_name']; ?></td>
                                                <td><?php echo $medicine_row['medicine_brand']; ?></td>
                                                <td><?php echo $medicine_row['quantity']; ?></td>
                                                <td><?php echo $medicine_row['price']; ?></td>
                                                <td><?php echo date('F d, Y', strtotime($medicine_row['expiration_date'])); ?></td>
                                                <td><?php echo $days_to_expire > 0 ? $days_to_expire . ' days' : 'Expired!'; ?></td>
                                                <td class="checkbox-container">
                                                    <input type="number" name="quantity[]" class="medicine-quantity" value="1" min="1" max="<?php echo $medicine_row['quantity']; ?>" onchange="calculateTotal()">
                                                    <input type="checkbox" name="medicine_id[]" value="<?php echo $medicine_row['id']; ?>">
                                                    <?php if ($is_low_stock) { ?>
                                                        <span class="low-stock-warning">Low Stock!</span>
                                                    <?php } ?>
                                                </td>
                                            </tr>
                                        <?php
                                        }
                                    }
                                    ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                <br>
                <p>Total Cost: <span id="totalCost">0</span></p>

                <!-- Selected Medicines -->
                <div class="row">
                    <div class="col-md-12">
                        <h4>Selected Medicines</h4>
                        <ul id="selectedMedicinesList"></ul>
                    </div>
                </div>

                <!-- Submit Button -->
                <div class="row">
                    <div class="col-md-12">
                        <button type="submit" class="btn btn-primary" name="add-invoice">Submit</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include('footer.php'); ?>

<script type="text/javascript">
    <?php
    if (isset($msg)) {
        echo 'swal("' . $msg . '");';
    }
    ?>
</script>

<script>
    function filterMedicines() {
        var input, filter, table, tr, td, i, txtValue;
        input = document.getElementById("medicineSearchInput");
        filter = input.value.toUpperCase();
        table = document.getElementById("medicineTable");
        tr = table.getElementsByTagName("tr");

        for (i = 0; i < tr.length; i++) {
            var matchFound = false;
            for (var j = 0; j < tr[i].cells.length; j++) {
                td = tr[i].cells[j];
                if (td) {
                    txtValue = td.textContent || td.innerText;
                    if (txtValue.toUpperCase().indexOf(filter) > -1) {
                        matchFound = true;
                        break;
                    }
                }
            }
            if (matchFound || i === 0) {
                tr[i].style.display = "";
            } else {
                tr[i].style.display = "none";
            }
        }
    }

    function calculateTotal() {
    var totalCost = 0;
    var selectedMedicinesList = document.getElementById('selectedMedicinesList');
    selectedMedicinesList.innerHTML = ''; // Clear previous list

    var allRows = document.querySelectorAll('#medicineTable tbody tr');
    allRows.forEach(function(row) {
        var checkbox = row.querySelector('input[type="checkbox"]');
        if (checkbox.checked) {
            var quantity = parseFloat(row.querySelector('.medicine-quantity').value);
            var price = parseFloat(row.querySelector('td:nth-child(4)').textContent);
            var itemTotal = price * quantity;
            totalCost += itemTotal;

            // Add selected medicine to the list
            var medicineName = row.querySelector('td:nth-child(1)').textContent;
            var medicineListItem = document.createElement('li');
            medicineListItem.textContent = medicineName + ' x ' + quantity + ' - ₱' + itemTotal.toFixed(2);
            selectedMedicinesList.appendChild(medicineListItem);
        }
    });

    document.getElementById('totalCost').textContent = '₱' + totalCost.toFixed(2); 
    }

    document.addEventListener('change', function(event) {
        if (event.target && event.target.classList.contains('medicine-quantity')) {
            calculateTotal();
        }
        if (event.target && event.target.type === 'checkbox') {
            calculateTotal();
        }
    });

    $(document).ready(function() {
    $('#patient_name_search').keyup(function() {
        var query = $(this).val();

        if (query.length > 2) { // Only search when 3 or more characters are typed
            $.ajax({
                url: 'search-patient.php',  // Path to your search PHP file
                method: 'GET',
                data: { query: query },
                success: function(data) {
                    $('#patient_name_results').html(data).show();
                }
            });
        } else {
            $('#patient_name_results').hide(); // Hide results if query length is less than 3
        }
    });

    $(document).on('click', '.patient-result', function() {
        var patientName = $(this).text();
        var patientId = $(this).data('id');
        $('#patient_name_search').val(patientName);
        $('#patient_id').val(patientId);
        $('#patient_name_results').hide();
    });

    $(document).click(function(e) {
        if (!$(e.target).closest('#patient_name_search').length) {
            $('#patient_name_results').hide();
        }
    });
});

</script>
<style>
.btn-primary {
            background: #12369e;
            border: none;
        }
        .btn-primary:hover {
            background: #05007E;
        }
        .form-control {
            border-radius: .375rem; /* Rounded corners */
            border-color: #ced4da; /* Border color */
            background-color: #f8f9fa; /* Background color */
        }
        select.form-control {
            border-radius: .375rem; /* Rounded corners */
            border: 1px solid; /* Border color */
            border-color: #ced4da; /* Border color */
            background-color: #f8f9fa; /* Background color */
            padding: .375rem 2.5rem .375rem .75rem; /* Adjust padding to make space for the larger arrow */
            font-size: 1rem; /* Font size */
            line-height: 1.5; /* Line height */
            height: calc(2.25rem + 2px); /* Adjust height */
            -webkit-appearance: none; /* Remove default styling on WebKit browsers */
            -moz-appearance: none; /* Remove default styling on Mozilla browsers */
            appearance: none; /* Remove default styling on other browsers */
            background: url('data:image/svg+xml;charset=UTF-8,%3Csvg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="20" height="20"%3E%3Cpath d="M7 10l5 5 5-5z" fill="%23aaa"/%3E%3C/svg%3E') no-repeat right 0.75rem center;
            background-size: 20px; /* Size of the custom arrow */
        }

        /* Custom styles for the table */

/* Checkbox container styling */
.checkbox-container {
    align-items: center; /* Center vertically */
    justify-content: center; /* Center horizontally */
    gap: 15px; /* Add space between checkbox and label */
    margin-top: 10px; /* Add margin for better spacing */
}

/* Style for the checkbox itself */
.checkbox-container input[type="checkbox"] {
    margin-right: 10px; /* Space between checkbox and label */
    transform: scale(1.5); /* Increase the size of the checkbox */
    cursor: pointer; /* Add pointer cursor when hovering over checkbox */
    transition: transform 0.3s ease, box-shadow 0.3s ease; /* Smooth transition effects */
}

/* Label styling */
.checkbox-container label {
    font-size: 1.1em; /* Increase font size for better readability */
    font-weight: 500; /* Set a medium boldness */
    color: #333; /* Darker text for contrast */
    transition: color 0.3s ease; /* Smooth color transition */
}

/* Style for the total cost display */
#totalCost {
    font-weight: bold; /* Make total cost bold */
    font-size: 1.3em; /* Increase font size */
    color: gray; /* Set color to green for total cost */
    margin-top: 20px; /* Add space between total cost and the table */
    text-align: center; /* Center-align total cost */
    padding: 10px; /* Add padding for better presentation */
    border-radius: 8px; /* Rounded corners for smooth effect */
    background-color: #f1f1f1; /* Light background for the total cost section */
    box-shadow: 0 4px 8px rgba(0, 123, 255, 0.2); /* Subtle shadow effect */
    transition: background-color 0.3s ease, box-shadow 0.3s ease; /* Smooth transition effects */
}

/* Hover effect for total cost */
#totalCost:hover {
    background-color: #e0e0e0; /* Slightly darker background on hover */
    box-shadow: 0 4px 12px rgba(0, 123, 255, 0.3); /* Larger shadow on hover */
}

.form-group {
        position: relative;
    }
    /* Search results styling */
    .search-results {
        max-height: 200px;
        overflow-y: auto;
        border: 1px solid #ddd;
        border-radius: 5px;
        display: none;
        background: #fff;
        position: absolute;
        z-index: 1000;
        width: 100%;
    }

    .search-results .patient-result {
        padding: 8px 12px;
        cursor: pointer;
        list-style: none;
        border-bottom: 1px solid #ddd;
    }

    .search-results .patient-result:hover {
        background-color: #12369e;
        color: white;
    }

    /* Styling for the search input field */
    #patient_name_search {
        padding-right: 30px;
    }

    #patient_name_search:focus {
        box-shadow: 0 0 5px rgba(63, 81, 181, 0.5);
    }

    /* Clear search icon inside the input (optional) */
    #patient_name_search + .clear-search {
        position: absolute;
        top: 50%;
        right: 10px;
        transform: translateY(-50%);
        cursor: pointer;
    }

    /* Add styling for the low stock warning */
.low-stock {
    background-color:#f62d51; /* Light red background for low stock */
    color: #a6131b; /* Dark red text color */
}

.low-stock-warning {
    color: #a6131b; /* Red color for low stock text */
    font-weight: bold;
    margin-left: 10px;
    font-size: 13px;
}

</style>
