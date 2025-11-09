<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'User not logged in']);
    exit();
}

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

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['score'])) {
    $score = intval($_POST['score']);
    $user_id = $_SESSION['user_id'];

    try {
        $conn = sqlsrv_connect($serverName, $connectionOptions);

        if ($conn === false) {
            echo json_encode(['success' => false, 'message' => 'Database connection failed']);
            exit();
        }

        // Update user's score
        $sql = "UPDATE Users SET score = ? WHERE user_id = ?";
        $params = array($score, $user_id);
        $stmt = sqlsrv_query($conn, $sql, $params);

        if ($stmt) {
            $_SESSION['score'] = $score; // Update session score
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to update score']);
        }

        sqlsrv_free_stmt($stmt);
        sqlsrv_close($conn);

    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Database error occurred']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
}
?>