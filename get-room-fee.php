<?php
include('includes/connection.php');

// Get the patient name from the AJAX request
$patient_name = isset($_GET['patient_name']) ? $_GET['patient_name'] : '';

if (!empty($patient_name)) {
    // Query to get patient details from tbl_inpatient_record
    $sql_patient = "SELECT patient_id, room_type, admission_date, discharge_date 
                    FROM tbl_inpatient_record 
                    WHERE patient_name = ? 
                    AND deleted = 0 AND is_billed = 0 
                    AND discharge_date IS NOT NULL";
                    
    if ($stmt_patient = mysqli_prepare($connection, $sql_patient)) {
        mysqli_stmt_bind_param($stmt_patient, 's', $patient_name);
        mysqli_stmt_execute($stmt_patient);
        $result_patient = mysqli_stmt_get_result($stmt_patient);

        if ($row_patient = mysqli_fetch_assoc($result_patient)) {
            // Extract details
            $room_type = $row_patient['room_type'];
            $admission_date = $row_patient['admission_date'];
            $discharge_date = $row_patient['discharge_date'];

            // Initialize room fee
            $total_room_fee = 0;

            if (!empty($discharge_date)) {
                $admission_date_obj = new DateTime($admission_date);
                $discharge_date_obj = new DateTime($discharge_date);
                $interval = $admission_date_obj->diff($discharge_date_obj);
                $total_hours = ($interval->days * 24) + $interval->h;

                if ($total_hours >= 24) {
                    $daily_rate = 400;
                    $days_stayed = floor($total_hours / 24);
                    $total_room_fee = $days_stayed * $daily_rate;
                }

                $formatted_discharge_date = $discharge_date_obj->format('F d, Y g:i A');
            } else {
                $formatted_discharge_date = "Not discharged yet";
            }

            $formatted_admission_date = (new DateTime($admission_date))->format('F d, Y g:i A');

            // Send JSON response
            echo json_encode([
                'room_type' => $room_type,
                'admission_date' => $formatted_admission_date,
                'discharge_date' => $formatted_discharge_date,
                'total_room_fee' => $total_room_fee
            ]);
            exit;
        }
        mysqli_stmt_close($stmt_patient);
    }

    // If not found in inpatient, check tbl_newborn
    $sql_newborn = "SELECT newborn_id, room_type, admission_date, discharge_date 
                    FROM tbl_newborn 
                    WHERE CONCAT(first_name, ' ', last_name) = ? 
                    AND discharge_date IS NOT NULL";

    if ($stmt_newborn = mysqli_prepare($connection, $sql_newborn)) {
        mysqli_stmt_bind_param($stmt_newborn, 's', $patient_name);
        mysqli_stmt_execute($stmt_newborn);
        $result_newborn = mysqli_stmt_get_result($stmt_newborn);

        if ($row_newborn = mysqli_fetch_assoc($result_newborn)) {
            // Extract details
            $room_type = $row_newborn['room_type'];
            $admission_date = $row_newborn['admission_date'];
            $discharge_date = $row_newborn['discharge_date'];

            // Initialize room fee
            $total_room_fee = 0;

            if (!empty($discharge_date)) {
                $admission_date_obj = new DateTime($admission_date);
                $discharge_date_obj = new DateTime($discharge_date);
                $interval = $admission_date_obj->diff($discharge_date_obj);
                $total_hours = ($interval->days * 24) + $interval->h;

                if ($total_hours >= 24) {
                    $daily_rate = 250;
                    $days_stayed = floor($total_hours / 24);
                    $total_room_fee = $days_stayed * $daily_rate;
                }

                $formatted_discharge_date = $discharge_date_obj->format('F d, Y g:i A');
            } else {
                $formatted_discharge_date = "Not discharged yet";
            }

            $formatted_admission_date = (new DateTime($admission_date))->format('F d, Y g:i A');

            // Send JSON response
            echo json_encode([
                'room_type' => $room_type,
                'admission_date' => $formatted_admission_date,
                'discharge_date' => $formatted_discharge_date,
                'total_room_fee' => $total_room_fee
            ]);
            exit;
        }
        mysqli_stmt_close($stmt_newborn);
    }

    // If patient is not found in either table
    echo json_encode(['error' => 'Patient not found']);
} else {
    echo json_encode(['error' => 'No patient name provided']);
}
?>
