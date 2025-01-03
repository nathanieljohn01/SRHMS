<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=0">
    <link rel="shortcut icon" type="image/x-icon" href="assets/img/srchlogo.png">
    <title>Santa Rosa Community HMS</title>
    <link rel="stylesheet" type="text/css" href="assets/css/bootstrap.min.css">
    <link rel="stylesheet" type="text/css" href="assets/css/font-awesome.min.css">
    <link rel="stylesheet" type="text/css" href="assets/css/all.min.css">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Rubik:400,700&display=swap">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css"/>
    <style>
        body {
            font-family: 'Rubik', sans-serif;
            background: url('assets/img/background.jpg') no-repeat center center fixed;
            background-size: cover;
            height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            margin: 0;
        }

        .main-wrapper {
            width: 100%;
            max-width: 400px;
            margin: auto;
        }

        .account-page {
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
        }

        .account-box {
            background: rgba(255, 255, 255, 0.95);
            padding: 40px;
            box-shadow: 0 0 20px rgba(0, 0, 0, 0.2);
            text-align: center;
            border-radius: 12px;
            animation: fadeInUp 0.8s ease;
        }

        .account-logo img {
            width: 100px;
            margin-bottom: 20px;
        }

        .form-group label {
            font-weight: bold;
            font-size: 14px;
            display: block;
            text-align: left;
            margin-bottom: 5px;
        }

        .form-control {
            padding: 10px 15px;
            border: 2px solid #ccc;
            transition: border-color 0.3s;
            border-radius: 5px;
        }

        .form-control:focus {
            border-color: #12369e;
            box-shadow: none;
        }

        .account-btn {
            background: #12369e;
            border: none;
            color: #fff;
            padding: 12px 20px;
            transition: background 0.3s;
            width: 100%;
            font-size: 16px;
            font-weight: bold;
            margin-top: 10px;
            border-radius: 8px;
        }

        .account-btn:hover {
            background: #05007E;
        }

        .account-btn:focus {
            box-shadow: none;
        }

        .account-logo {
            margin-bottom: 30px;
        }

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
    </style>
</head>

<body>
<?php
session_start();
include('includes/connection.php');

$stmt_cleanup = $connection->prepare("DELETE FROM tbl_attempts WHERE attempt_time < DATE_SUB(NOW(), INTERVAL 24 HOUR)");
$stmt_cleanup->execute();

if (!isset($_SESSION['attempts'])) {
    $_SESSION['attempts'] = 0;
    $_SESSION['last_attempt_time'] = time();
}

if (isset($_REQUEST['login'])) {
    $username = mysqli_real_escape_string($connection, $_REQUEST['username']);
    $pwd = mysqli_real_escape_string($connection, $_REQUEST['pwd']); // User input password
    
    // Prepare statement to fetch user record
    $stmt = $connection->prepare("SELECT * FROM tbl_employee WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $data = $result->fetch_assoc();
        $stored_hash = $data['password']; // Hashed password from the database

        // Prepare statement to check attempts from tbl_attempts
        $stmt_attempt = $connection->prepare("SELECT * FROM tbl_attempts WHERE username = ? ORDER BY attempt_time DESC LIMIT 1");
        $stmt_attempt->bind_param("s", $username);
        $stmt_attempt->execute();
        $attempt_result = $stmt_attempt->get_result();
        $attempt_data = $attempt_result->fetch_assoc();

        $current_time = time();
        $time_diff = isset($attempt_data['last_attempt_time']) ? $current_time - strtotime($attempt_data['last_attempt_time']) : 0;

        if ($attempt_data['attempts'] >= 5 && $time_diff < 60) {
            // Too many failed attempts
            echo "<script>
                Swal.fire({
                    icon: 'error',
                    title: 'Too Many Attempts',
                    text: 'Please wait for 1 minute before trying again.',
                    confirmButtonColor: '#12369e',
                    confirmButtonText: 'OK'
                });
            </script>";
        } else {
            if ($time_diff >= 60) {
                // Reset attempts if more than 1 minute has passed
                $attempt_time = date('Y-m-d H:i:s');
                $stmt_reset = $connection->prepare("UPDATE tbl_attempts SET attempts = 0, last_attempt_time = ? WHERE username = ?");
                $stmt_reset->bind_param("ss", $attempt_time, $username);
                $stmt_reset->execute();
            }

            if (password_verify($pwd, $stored_hash)) {
                // Successful login
                $_SESSION['name'] = $data['first_name'] . ' ' . $data['last_name'];
                $_SESSION['role'] = $data['role'];

                $attempt_time = date('Y-m-d H:i:s');
                $stmt_success = $connection->prepare("INSERT INTO tbl_attempts (username, attempt_time, success, attempts, last_attempt_time) 
                                                      VALUES (?, ?, 1, 0, ?)");
                $stmt_success->bind_param("sss", $username, $attempt_time, $attempt_time);
                $stmt_success->execute();

                // Redirect based on role
                $role = $data['role'];
                switch ($role) {
                    case '1':
                        header('location: dashboard.php');
                        break;
                    case '2':
                        header('location: dashboard-doctor.php');
                        break;
                    case '3': // Nurse 1
                    case '10': // Nurse 2
                        header('location: dashboard-nurse.php');
                        break;
                    case '4':
                        header('location: dashboard-pharmacy.php');
                        break;
                    case '5':
                        header('location: dashboard-lab.php');
                        break;
                    case '6':
                        header('location: dashboard-rad.php');
                        break;
                    case '7':
                    case '8':
                        header('location: dashboard-pharmacy.php');
                        break;
                    case '9':
                        header('location: dashboard-lab.php');
                        break;
                    default:
                        echo "<script>alert('Invalid role');</script>";
                }
                exit;
            } else {
                // Password mismatch - increment attempts
                $attempt_time = date('Y-m-d H:i:s');
                $new_attempts = isset($attempt_data['attempts']) ? $attempt_data['attempts'] + 1 : 1;
                $stmt_fail = $connection->prepare("INSERT INTO tbl_attempts (username, attempt_time, success, attempts, last_attempt_time) 
                                                   VALUES (?, ?, 0, ?, ?)");
                $stmt_fail->bind_param("ssis", $username, $attempt_time, $new_attempts, $attempt_time);
                $stmt_fail->execute();

                echo "<script>
                    Swal.fire({
                        icon: 'error',
                        title: 'Login Failed',
                        text: 'Incorrect username or password. Please try again.',
                        confirmButtonColor: '#12369e',
                        confirmButtonText: 'OK'
                    });
                </script>";
            }
        }
    } else {
        // Username not found
        $attempt_time = date('Y-m-d H:i:s');
        $stmt_not_found = $connection->prepare("INSERT INTO tbl_attempts (username, attempt_time, success, attempts, last_attempt_time) 
                                                VALUES (?, ?, 0, 1, ?)");
        $stmt_not_found->bind_param("sss", $username, $attempt_time, $attempt_time);
        $stmt_not_found->execute();
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Login Failed',
                text: 'Incorrect username or password. Please try again.',
                confirmButtonColor: '#12369e',
                confirmButtonText: 'OK'
            });
        </script>";
    }
}
?>

    <div class="main-wrapper">
        <div class="account-page">
            <div class="account-box">
                <form method="post" class="form-signin">
                    <div class="account-logo">
                        <a href="index-2.html"><img src="assets/img/srchlogo.png" alt="Logo"></a>
                    </div>
                    <div class="form-group">
                        <label>Username</label>
                        <input type="text" autofocus="" class="form-control" name="username" required>
                    </div>
                    <div class="form-group">
                        <label>Password</label>
                        <input type="password" class="form-control" name="pwd" required>
                    </div>
                    <br>
                    <div class="form-group text-center">
                        <button type="submit" name="login" class="btn btn-primary account-btn">Login</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="assets/js/jquery-3.2.1.min.js"></script>
    <script src="assets/js/popper.min.js"></script>
    <script src="assets/js/bootstrap.min.js"></script>
    <script src="assets/js/app.js"></script>
    
</body>

</html>
