<?php
session_start();

// Database configuration
$serverName = "localhost\\SQLEXPRESS";
$connectionOptions = array(
    "Database" => "flipping_hearts",
    "Uid" => "",
    "PWD" => "",
    "TrustServerCertificate" => true,
    "Encrypt" => false,
    "ConnectionPooling" => false
);

// Handle registration form submission
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['register'])) {
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);

    if (empty($username) || empty($password)) {
        $register_error = "Please fill in all fields";
    } elseif (strlen($username) < 3 || strlen($username) > 50) {
        $register_error = "Username must be 3-50 characters";
    } elseif (strlen($password) < 6) {
        $register_error = "Password must be at least 6 characters";
    } else {
        try {
            $conn = sqlsrv_connect($serverName, $connectionOptions);

            if ($conn === false) {
                $register_error = "Database connection failed";
            } else {
                // Check if username exists
                $check_sql = "SELECT username FROM Users WHERE username = ?";
                $check_params = array($username);
                $check_stmt = sqlsrv_query($conn, $check_sql, $check_params);

                if ($check_stmt && sqlsrv_has_rows($check_stmt)) {
                    $register_error = "Username already taken";
                } else {
                    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                    $insert_sql = "INSERT INTO Users (username, password, created_at) VALUES (?, ?, GETDATE())";
                    $insert_params = array($username, $hashed_password);
                    $insert_stmt = sqlsrv_query($conn, $insert_sql, $insert_params);

                    if ($insert_stmt) {
                        $register_success = true;
                        sqlsrv_free_stmt($insert_stmt);
                    } else {
                        $register_error = "Registration failed";
                    }
                }

                if ($check_stmt)
                    sqlsrv_free_stmt($check_stmt);
                sqlsrv_close($conn);
            }
        } catch (Exception $e) {
            $register_error = "Database error occurred";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - Flipping Hearts</title>
    <link rel="stylesheet" href="index.css">
    <style>
        .error-message {
            color: #ff6b6b;
            background: rgba(255, 107, 107, 0.1);
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 15px;
            text-align: center;
            border: 1px solid rgba(255, 107, 107, 0.3);
        }

        .success-message {
            color: #51cf66;
            background: rgba(81, 207, 102, 0.1);
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 15px;
            text-align: center;
            border: 1px solid rgba(81, 207, 102, 0.3);
        }

        /* Ensure forms are properly positioned */
        .screen {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            display: flex;
            justify-content: center;
            align-items: center;
        }
    </style>
</head>

<body>
    <!-- Register Form -->
    <div id="registerScreen" class="screen">
        <div class="login-container">
            <div class="login-header">
                <h2>Create Account</h2>
                <p>Join the Flipping Hearts adventure!</p>
            </div>

            <?php if (isset($register_error)): ?>
                <div class="error-message"><?php echo $register_error; ?></div>
            <?php endif; ?>

            <?php if (isset($register_success)): ?>
                <!-- Success Message -->
                <div id="successMessage" class="success-popup active">
                    <div class="success-content">
                        <div class="success-icon">✓</div>
                        <h3>Registration Successful!</h3>
                        <p>Your account has been created successfully!</p>
                    </div>
                </div>
                <script>
                    setTimeout(function () {
                        window.location.href = 'index.php?registered=1';
                    }, 2000);
                </script>
            <?php endif; ?>

            <form method="POST" class="login-form">
                <input type="hidden" name="register" value="1">
                <div class="input-group">
                    <div class="input-icon">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                            stroke-width="2">
                            <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                            <circle cx="12" cy="7" r="4"></circle>
                        </svg>
                    </div>
                    <input type="text" name="username" placeholder="Choose a username" required
                        value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>">
                </div>
                <div class="input-group">
                    <div class="input-icon">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                            stroke-width="2">
                            <path
                                d="M21 2l-2 2m-7.61 7.61a5.5 5.5 0 1 1-7.778 7.778 5.5 5.5 0 0 1 7.777-7.777zm0 0L15.5 7.5m0 0l3 3L22 7l-3-3m-3.5 3.5L19 4">
                            </path>
                        </svg>
                    </div>
                    <input type="password" name="password" placeholder="Create a password" required>
                </div>
                <button type="submit" class="login-btn">
                    <span class="btn-text">Register</span>
                    <div class="btn-loader"></div>
                </button>
                <div class="register-link">
                    <p>Already have an account? <a href="index.php">LOGIN HERE</a></p>
                </div>
            </form>
        </div>
    </div>

    <!-- Success Message Popup -->
    <div id="successMessage" class="success-popup">
        <div class="success-content">
            <div class="success-icon">✓</div>
            <h3>Registration Successful!</h3>
            <p>Your account has been created successfully!</p>
        </div>
    </div>

    <script>
        // Handle form submission loading state
        document.addEventListener('DOMContentLoaded', function () {
            const registerForm = document.querySelector('.login-form');
            const loginBtn = document.querySelector('.login-btn');
            const btnText = document.querySelector('.btn-text');
            const btnLoader = document.querySelector('.btn-loader');

            if (registerForm) {
                registerForm.addEventListener('submit', function () {
                    // Show loading state
                    if (loginBtn && btnText && btnLoader) {
                        loginBtn.classList.add('loading');
                    }
                });
            }
        });
    </script>
</body>

</html>