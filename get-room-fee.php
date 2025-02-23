<?php
include('includes/connection.php');

// Get the patient name from the AJAX request
$patient_name = isset($_GET['patient_name']) ? $_GET['patient_name'] : '';

// Ensure we have a valid patient name
if (!empty($patient_name)) {
    // Query to get the patient details
    $sql_patient = "SELECT patient_id, room_type, admission_date, discharge_date FROM tbl_inpatient_record WHERE patient_name = ? AND deleted = 0 AND is_billed = 0 AND discharge_date IS NOT NULL";
    if ($stmt_patient = mysqli_prepare($connection, $sql_patient)) {
        mysqli_stmt_bind_param($stmt_patient, 's', $patient_name);
        mysqli_stmt_execute($stmt_patient);
        $result_patient = mysqli_stmt_get_result($stmt_patient);

        // If the patient exists, fetch details
        if ($row_patient = mysqli_fetch_assoc($result_patient)) {
            $patient_id = $row_patient['patient_id'];
            $room_type = $row_patient['room_type'];
            $admission_date = $row_patient['admission_date'];
            $discharge_date = $row_patient['discharge_date'];

            // Initialize room fee
            $total_room_fee = 0;

            // Process dates only if discharge_date exists
            if (!empty($discharge_date)) {
                // Convert admission and discharge date to DateTime objects
                $admission_date_obj = new DateTime($admission_date);
                $discharge_date_obj = new DateTime($discharge_date);

                // Calculate the difference in time (in hours)
                $interval = $admission_date_obj->diff($discharge_date_obj);
                $total_hours = ($interval->days * 24) + $interval->h; // Convert days to hours and add remaining hours

                // Check if the patient stayed for at least 24 hours
                if ($total_hours >= 24) {
                    // Room fee starts after 24 hours
                    $daily_rate = 400; // Room fee per day for Surgery and OB Ward
                    $days_stayed = floor($total_hours / 24); // Calculate number of full days stayed
                    $total_room_fee = $days_stayed * $daily_rate; // Calculate total room fee
                }

                // Format the dates
                $formatted_discharge_date = $discharge_date_obj->format('F d, Y g:i A');
            } else {
                $formatted_discharge_date = "Not discharged yet";
            }

            // Format the admission date for display
            $formatted_admission_date = (new DateTime($admission_date))->format('F d, Y g:i A');

            // Send back the response as JSON
            echo json_encode([
                'room_type' => $room_type,
                'admission_date' => $formatted_admission_date,
                'discharge_date' => $formatted_discharge_date,
                'total_room_fee' => $total_room_fee
            ]);
        } else {
            echo json_encode(['error' => 'Patient not found']);
        }

        mysqli_stmt_close($stmt_patient);
    } else {
        echo json_encode(['error' => 'Error with the query']);
    }
} else {
    echo json_encode(['error' => 'No patient name provided']);
}
?>
