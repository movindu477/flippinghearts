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

    // Get top 10 scorers
    $sql = "SELECT TOP 10 username, score, level, last_login 
            FROM Users 
            WHERE score > 0 
            ORDER BY score DESC, last_login DESC";

    $stmt = sqlsrv_query($conn, $sql);

    $leaderboard = [];
    $currentUserRank = null;
    $currentUserData = null;
    $rank = 1;

    if ($stmt) {
        while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
            $userData = [
                'username' => $row['username'],
                'score' => $row['score'],
                'level' => $row['level'] ?: 1,
                'last_login' => $row['last_login'] ? $row['last_login']->format('Y-m-d H:i:s') : null,
                'rank' => $rank
            ];

            $leaderboard[] = $userData;

            // Check if this is the current user
            if ($row['username'] === $_SESSION['username']) {
                $currentUserRank = $rank;
                $currentUserData = $userData;
            }

            $rank++;
        }
        sqlsrv_free_stmt($stmt);
    }

    // If current user is not in top 10, get their rank and data separately
    if (!$currentUserRank) {
        $userSql = "SELECT username, score, level, last_login,
                           (SELECT COUNT(*) + 1 
                            FROM Users u2 
                            WHERE u2.score > Users.score) as rank
                    FROM Users 
                    WHERE user_id = ?";

        $userParams = array($_SESSION['user_id']);
        $userStmt = sqlsrv_query($conn, $userSql, $userParams);

        if ($userStmt && sqlsrv_has_rows($userStmt)) {
            $userRow = sqlsrv_fetch_array($userStmt, SQLSRV_FETCH_ASSOC);
            $currentUserRank = $userRow['rank'];
            $currentUserData = [
                'username' => $userRow['username'],
                'score' => $userRow['score'],
                'level' => $userRow['level'] ?: 1,
                'last_login' => $userRow['last_login'] ? $userRow['last_login']->format('Y-m-d H:i:s') : null,
                'rank' => $currentUserRank
            ];
        }
        if ($userStmt)
            sqlsrv_free_stmt($userStmt);
    }

    echo json_encode([
        'success' => true,
        'leaderboard' => $leaderboard,
        'currentUser' => $currentUserData,
        'totalPlayers' => $rank - 1
    ]);

    sqlsrv_close($conn);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Database error occurred']);
}
?>