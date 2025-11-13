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

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'User not logged in']);
    exit;
}

try {
    $conn = sqlsrv_connect($serverName, $connectionOptions);

    if ($conn === false) {
        echo json_encode(['success' => false, 'message' => 'Database connection failed']);
        exit;
    }

    // Get the POST data
    $input = json_decode(file_get_contents('php://input'), true);
    $newScore = $input['score'] ?? 0;
    $levelUp = $input['levelUp'] ?? false;
    $currentLevel = $input['currentLevel'] ?? 1;

    // Check if level column exists
    $checkColumnSql = "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'Users' AND COLUMN_NAME = 'level'";
    $checkStmt = sqlsrv_query($conn, $checkColumnSql);
    $levelColumnExists = sqlsrv_has_rows($checkStmt);
    if ($checkStmt)
        sqlsrv_free_stmt($checkStmt);

    // Get current user score and level
    if ($levelColumnExists) {
        $sql = "SELECT score, level FROM Users WHERE user_id = ?";
    } else {
        $sql = "SELECT score FROM Users WHERE user_id = ?";
    }

    $params = array($_SESSION['user_id']);
    $stmt = sqlsrv_query($conn, $sql, $params);

    if ($stmt && sqlsrv_has_rows($stmt)) {
        $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
        $currentScore = $row['score'];
        $currentLevel = $levelColumnExists ? $row['level'] : 1;

        // Update score (only if new score is higher)
        if ($newScore > $currentScore) {
            $updateSql = "UPDATE Users SET score = ? WHERE user_id = ?";
            $updateParams = array($newScore, $_SESSION['user_id']);
            sqlsrv_query($conn, $updateSql, $updateParams);
            $_SESSION['score'] = $newScore;
        } else {
            $newScore = $currentScore; // Keep the current score if it's higher
        }

        // Handle level up
        $newLevel = $currentLevel;
        if ($levelUp && $levelColumnExists) {
            $newLevel = $currentLevel + 1;
            $levelSql = "UPDATE Users SET level = ? WHERE user_id = ?";
            $levelParams = array($newLevel, $_SESSION['user_id']);
            sqlsrv_query($conn, $levelSql, $levelParams);
            $_SESSION['level'] = $newLevel;
        }

        echo json_encode([
            'success' => true,
            'newScore' => $newScore,
            'newLevel' => $newLevel
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'User not found']);
    }

    if ($stmt)
        sqlsrv_free_stmt($stmt);
    sqlsrv_close($conn);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Database error occurred']);
}
?>