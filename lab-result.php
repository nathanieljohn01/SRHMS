<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no">
    <link rel="shortcut icon" href="assets/img/srchlogo.png">
    <title>Lab Result</title>
    <link rel="stylesheet" href="assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="assets/css/font-awesome.min.css">
    <link rel="stylesheet" href="assets/css/fontawesome.min.css">
    <link rel="stylesheet" href="assets/css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/css/select2.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Rubik:wght@400;500&display=swap');

        body {
            font-family: 'Rubik', sans-serif;
            background-color: #f4f4f9;
            margin: 0;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
        }

        .container {
            background: #fff;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 0 20px rgba(0, 0, 0, 0.1);
            width: 100%;
            max-width: 450px;
        }

        h1 {
            font-size: 24px;
            margin-bottom: 20px;
            text-align: center;
            color: #333;
        }

        label {
            font-weight: 500;
            margin-bottom: 5px;
            color: #333;
            display: block;
        }

        select, input {
            width: 100%;
            padding: 12px 20px;
            border: 2px solid #ccc;
            font-size: 16px;
            border-radius: 5px;
            transition: border-color 0.3s;
        }

        select:focus, input:focus {
            border-color: #12369e;
            outline: none;
        } 
        /* Remove the blue background color when an option is selected */
        .select2-container--default .select2-selection--single .select2-selection__rendered {
            background-color: #f9f9f9; /* Set to your desired background color */
            color: #333; /* Set text color */
        }

        /* Change the background color of the dropdown options */
        .select2-container--default .select2-results__option {
            background-color: #fff; /* Background color for options */
            color: #333; /* Text color for options */
        }

        /* Change the background color of the selected option */
        .select2-container--default .select2-results__option--highlighted[aria-selected] {
            background-color: gray; /* Background color for highlighted option */
            color: #fff; /* Text color for highlighted option */
        }

        /* Change the border color of the dropdown */
        .select2-container--default .select2-selection--single {
            height: 50px; /* Match the height of the default select */
            border: 2px solid #ccc;
            border-radius: 5px;
            padding: 10px;
            background-color: #f9f9f9; /* Light background for dropdown */
            transition: border-color 0.3s;
        }
        .select2-container--default .select2-selection--single:hover {
            border-color: #12369e; /* Change border color on hover */
        }
        /* Change the border color on focus */
        .select2-container--default .select2-selection--single:focus {
            border-color: #12369e; /* Border color on focus */
        }
         /* Style the Select2 arrow */
         .select2-container--default .select2-selection--single .select2-selection__arrow {
            height: 50px; /* Match the height of the select */
            width: 30px; /* Set a width for the arrow */
            right: 10px; /* Position the arrow */
            top: 50%; /* Center the arrow vertically */
            transform: translateY(-50%); /* Adjust for vertical centering */
            background-color: transparent; /* Make the background transparent */
            display: flex; /* Use flexbox for centering */
            align-items: center; /* Center the arrow vertically */
            justify-content: center; /* Center the arrow horizontally */
        }

        #testSelect {
            margin-top: 10px; /* Add space above the Select Test dropdown */
        }

        .form-group {
            margin-bottom: 8px; /* Ensure spacing between form elements */
        }

        button {
            width: 100%;
            padding: 10px;
            margin: 7px 0;
            border: none; /* Remove border for a cleaner look */
            color: #fff;
            cursor: pointer;
            transition: background-color 0.3s, transform 0.2s; /* Add transform for hover effect */
            font-size: 16px;
            font-weight: 500;
            text-align: center;
            border-radius: 5px;
            display: inline-block;
        }

        .view-button {
            background-color: #12369e;
        }

        .view-button:hover {
            background-color: #12369e;
            transform: scale(1.05); /* Slightly enlarge on hover */
        }

        .back-button {
            background-color: #CCCCCC;
            color: #333;
        }

        .back-button:hover {
            background-color: #aaa;
            transform: scale(1.05); /* Slightly enlarge on hover */
        }

        /* Add some space between the buttons */
        .button-group {
            margin-top: 20px;
        }
    </style>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.5.1/jquery.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/js/select2.min.js"></script>
    <script>
            $(document).ready(function() {
            $('#departmentSelect').select2({
                placeholder: 'Select Department'
            });
            $('#testSelect').select2({
                placeholder: 'Select Test'
            });

            $('#departmentSelect').on('change', function() {
                showTests(this.value);
            });
        });

        function showTests(department) {
            var tests = {
                "Hematology": ["Complete Blood Count"],
                "Clinical Microscopy": ["Urinalysis", "Fecalysis"]
            };

            var testSelect = $('#testSelect');
            testSelect.empty(); // Clear previous options

            if (tests[department]) {
                tests[department].forEach(function(test) {
                    var option = new Option(test, test);
                    testSelect.append(option);
                });
            }
            testSelect.trigger('change'); // Refresh the select2 plugin
        }

        function navigateToTest() {
            var selectedTest = $('#testSelect').val();
            var urlMap = {
                "Complete Blood Count": "cbc.php",
                "Urinalysis": "urinalysis.php",
                "Fecalysis": "fecalysis.php"
            };
            if (urlMap[selectedTest]) {
                window.location.href = urlMap[selectedTest];
            }
        }
    </script>
</head>
<body>
<div class="container">
    <h1>Select Lab Department and Test</h1>
    <form>
        <div class="form-group">
            <label for="departmentSelect">Select Department:</label>
            <select id="departmentSelect" data-placeholder="Select Department">
            <option value="" disabled selected>Select Department</option>
            <option value="Hematology">Hematology</option>
            <option value="Clinical Microscopy">Clinical Microscopy</option>
        </select>
        </div>
        <div class="form-group">
            <label for="testSelect">Select Test:</label>
            <select id="testSelect" data-placeholder="Select Test">
                <option value="" disabled selected>Select Test</option>
            </select>
        </div>

        <button type="button" class="view-button" onclick="navigateToTest()">View Test</button>
        <button type="button" class="back-button" onclick="window.location.href='dashboard.php'">Back to Dashboard</button>
    </form>
</div>
</body>
</html>
