<?php
include('includes/connection.php');

if (isset($_GET['query'])) {
    $query = mysqli_real_escape_string($connection, $_GET['query']);
    $result = mysqli_query($connection, "SELECT id, medicine_name, medicine_brand, category, quantity, price, expiration_date FROM tbl_medicines WHERE medicine_name LIKE '%$query%' OR medicine_brand LIKE '%$query%'");

    if (mysqli_num_rows($result) > 0) {
        while ($row = mysqli_fetch_assoc($result)) {
            echo "<tr>
                <td>{$row['medicine_name']}</td>
                <td>{$row['medicine_brand']}</td>
                <td>{$row['category']}</td>
                <td>{$row['expiration_date']}</td>
                <td>{$row['quantity']}</td>
                <td>{$row['price']} PHP</td>
                <td>
                    <input 
                        type='number' 
                        id='quantityInput-{$row['id']}' 
                        class='form-control' 
                        min='1' 
                        max='{$row['quantity']}' 
                        value='1'>
                    <button 
                        class='btn btn-primary btn-sm mt-1' 
                        onclick=\"addMedicineToList(
                            '{$row['id']}', 
                            '{$row['medicine_name']}', 
                            '{$row['medicine_brand']}',
                            '{$row['category']}',
                            {$row['quantity']}, 
                            {$row['price']}, 
                            '{$row['expiration_date']}', 
                            event
                        )\">Add</button>
                </td>
            </tr>";
        }
    } else {
        echo "<tr><td colspan='6'>No medicines found for your search query.</td></tr>";
    }
} else {
    echo "<tr><td colspan='6'>Invalid query. Please try again.</td></tr>";
}
?>
