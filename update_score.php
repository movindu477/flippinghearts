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

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Get JSON input
    $input = json_decode(file_get_contents('php://input'), true);

    if (isset($input['score'])) {
        $score = intval($input['score']);
        $user_id = $_SESSION['user_id'];
        $gameData = isset($input['gameData']) ? $input['gameData'] : null;

        try {
            $conn = sqlsrv_connect($serverName, $connectionOptions);

            if ($conn === false) {
                echo json_encode(['success' => false, 'message' => 'Database connection failed']);
                exit();
            }

            // Get current score
            $get_sql = "SELECT score FROM Users WHERE user_id = ?";
            $get_params = array($user_id);
            $get_stmt = sqlsrv_query($conn, $get_sql, $get_params);

            if ($get_stmt && sqlsrv_has_rows($get_stmt)) {
                $row = sqlsrv_fetch_array($get_stmt, SQLSRV_FETCH_ASSOC);
                $current_score = $row['score'];

                // Add new score to current score
                $new_score = $current_score + $score;

                // Update user's score
                $update_sql = "UPDATE Users SET score = ? WHERE user_id = ?";
                $update_params = array($new_score, $user_id);
                $update_stmt = sqlsrv_query($conn, $update_sql, $update_params);

                if ($update_stmt) {
                    $_SESSION['score'] = $new_score; // Update session score

                    // Log game result if game data is provided
                    if ($gameData) {
                        $log_sql = "INSERT INTO GameResults (user_id, score_earned, hearts_found, time_bonus, total_moves, game_date) 
                                   VALUES (?, ?, ?, ?, ?, GETDATE())";
                        $log_params = array(
                            $user_id,
                            $score,
                            $gameData['heartsFound'] ?? 0,
                            $gameData['timeBonus'] ?? 0,
                            $gameData['moves'] ?? 0
                        );
                        sqlsrv_query($conn, $log_sql, $log_params);
                    }

                    echo json_encode([
                        'success' => true,
                        'newScore' => $new_score,
                        'scoreEarned' => $score
                    ]);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Failed to update score']);
                }

                sqlsrv_free_stmt($get_stmt);
                if ($update_stmt)
                    sqlsrv_free_stmt($update_stmt);
            } else {
                echo json_encode(['success' => false, 'message' => 'User not found']);
            }

            sqlsrv_close($conn);

        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Database error occurred: ' . $e->getMessage()]);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid request - no score provided']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}
?>