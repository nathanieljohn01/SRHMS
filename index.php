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

if (isset($_REQUEST['login'])) {
    $username = mysqli_real_escape_string($connection, $_REQUEST['username']);
    $pwd = mysqli_real_escape_string($connection, $_REQUEST['pwd']);  // Plain password input from user

    // Fetch the user record by username
    $fetch_query = mysqli_query($connection, "SELECT * FROM tbl_employee WHERE username = '$username'");
    $res = mysqli_num_rows($fetch_query);

    if ($res > 0) {
        $data = mysqli_fetch_array($fetch_query);
        
        // Get the stored hashed password
        $stored_hash = $data['password'];  // This is the hashed password stored in the database

        // Verify the password using password_verify()
        if (password_verify($pwd, $stored_hash)) {
            // If the password is correct, proceed with login
            $name = $data['first_name'] . ' ' . $data['last_name'];
            $role = $data['role'];
            $_SESSION['name'] = $name;
            $_SESSION['role'] = $role;

        if ($role == '1') {
            header('location: dashboard.php');
            exit;
        } else if ($role == '2') {
            $_SESSION['dashboard'] = true;
            header('location: dashboard-doctor.php');
            exit;
        } else if ($role == '3') {
            $_SESSION['dashboard'] = true;
            header('location: dashboard-nurse.php');
            exit;
        } else if ($role == '10') {
            $_SESSION['dashboard'] = true;
            header('location: dashboard-nurse.php');
            exit;
        } else if ($role == '4') {
            $_SESSION['dashboard'] = true;
            header('location: dashboard-pharmacy.php');
            exit;
        } else if ($role == '5') {
            $_SESSION['dashboard'] = true;
            header('location: dashboard-lab.php');
            exit;
        } else if ($role == '6') {
            $_SESSION['dashboard'] = true;
            header('location: dashboard-rad.php');
            exit;
        } else if ($role == '7') {
            $_SESSION['dashboard'] = true;
            header('location: dashboard-lab.php');
            exit;
        } else if ($role == '8') {
            $_SESSION['dashboard'] = true;
            header('location: dashboard-lab.php');
            exit;
        } else if ($role == '9') {
            $_SESSION['dashboard'] = true;
            header('location: dashboard-lab.php');
            exit;
        }
        
    } else {
        // Password mismatch error
        echo "<script>
        Swal.fire({
            icon: 'error',
            title: 'Login Failed',
            text: 'Incorrect username or password. Please try again.',
            confirmButtonColor: '#12369e',
            confirmButtonText: 'OK',
            backdrop: 'rgba(0, 0, 0, 0.3)',
            customClass: {
                confirmButton: 'swal2-confirm-btn'
            },
            showClass: {
                popup: 'animate__animated animate__fadeInDown'
            },
            hideClass: {
                popup: 'animate__animated animate__fadeOutUp'
            }
        });
        </script>";
        }

    } else {
        // Username not found
        echo "<script>
        Swal.fire({
            icon: 'error',
            title: 'Login Failed',
            text: 'Incorrect username or password. Please try again.',
            confirmButtonColor: '#12369e',
            confirmButtonText: 'OK',
            backdrop: 'rgba(0, 0, 0, 0.3)',
            customClass: {
                confirmButton: 'swal2-confirm-btn'
            },
            showClass: {
                popup: 'animate__animated animate__fadeInDown'
            },
            hideClass: {
                popup: 'animate__animated animate__fadeOutUp'
            }
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
