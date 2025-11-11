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

// Handle login form submission
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['login'])) {
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);

    if (empty($username) || empty($password)) {
        $login_error = "Please fill in all fields";
    } else {
        try {
            $conn = sqlsrv_connect($serverName, $connectionOptions);

            if ($conn === false) {
                $login_error = "Database connection failed";
            } else {
                $sql = "SELECT user_id, username, password, score FROM Users WHERE username = ?";
                $params = array($username);
                $stmt = sqlsrv_query($conn, $sql, $params);

                if ($stmt && sqlsrv_has_rows($stmt)) {
                    $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);

                    if (password_verify($password, $row['password'])) {
                        $_SESSION['user_id'] = $row['user_id'];
                        $_SESSION['username'] = $row['username'];
                        $_SESSION['score'] = $row['score'];
                        $login_success = true;

                        // Update last login time
                        $update_sql = "UPDATE Users SET last_login = GETDATE() WHERE user_id = ?";
                        $update_params = array($row['user_id']);
                        sqlsrv_query($conn, $update_sql, $update_params);
                    } else {
                        $login_error = "Invalid credentials";
                    }
                } else {
                    $login_error = "Invalid credentials";
                }

                if ($stmt)
                    sqlsrv_free_stmt($stmt);
                sqlsrv_close($conn);
            }
        } catch (Exception $e) {
            $login_error = "Database error occurred";
        }
    }
}

// Check if user is logged in
if (isset($_SESSION['username'])) {
    $current_screen = 'game';
} else {
    $current_screen = 'login';
}

// Handle logout
if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: index.php");
    exit();
}

// Check if redirected from registration
if (isset($_GET['registered'])) {
    $registration_complete = true;
}

// Check if this is first visit
$is_first_visit = !isset($_POST['login']) && !isset($_GET['registered']);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Flipping Hearts</title>
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

        .logout-btn {
            background: linear-gradient(135deg, #ff6b6b, #ee5a52);
            color: white;
            padding: 10px 20px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s ease;
            border: none;
            cursor: pointer;
            display: inline-block;
        }

        .logout-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(255, 107, 107, 0.3);
        }

        /* Ensure forms are properly positioned */
        .screen {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            display: none;
            justify-content: center;
            align-items: center;
        }

        .screen.active {
            display: flex;
        }

        /* Modern Score Display */
        .score-display {
            background: linear-gradient(135deg, rgba(255, 107, 157, 0.9), rgba(255, 143, 171, 0.9));
            padding: 12px 20px;
            border-radius: 15px;
            color: white;
            font-weight: 700;
            backdrop-filter: blur(10px);
            border: 2px solid rgba(255, 255, 255, 0.3);
            box-shadow: 0 8px 25px rgba(255, 107, 157, 0.3);
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 1.1rem;
            transition: all 0.3s ease;
        }

        .score-display:hover {
            transform: translateY(-2px);
            box-shadow: 0 12px 30px rgba(255, 107, 157, 0.4);
        }

        .score-icon {
            font-size: 1.3rem;
            animation: pulse 2s infinite;
        }

        @keyframes pulse {

            0%,
            100% {
                transform: scale(1);
            }

            50% {
                transform: scale(1.1);
            }
        }

        /* Score Popup Styles */
        .score-popup {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            padding: 20px;
            border-radius: 15px;
            text-align: center;
            margin: 15px 0;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
        }

        .score-popup h4 {
            font-size: 1.3rem;
            margin-bottom: 10px;
            color: white;
        }

        .final-score {
            font-size: 2.5rem;
            font-weight: 900;
            background: linear-gradient(45deg, #ff6b9d, #ff8fab);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin: 10px 0;
        }

        .score-breakdown {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
            margin: 15px 0;
        }

        .score-item {
            background: rgba(255, 255, 255, 0.1);
            padding: 10px;
            border-radius: 10px;
            backdrop-filter: blur(10px);
        }

        .score-label {
            font-size: 0.9rem;
            opacity: 0.9;
        }

        .score-value {
            font-size: 1.2rem;
            font-weight: 700;
            color: #ff6b9d;
        }

        /* Time Bonus Popup */
        .time-bonus-popup {
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: linear-gradient(135deg, #4CAF50, #45a049);
            color: white;
            padding: 30px;
            border-radius: 20px;
            text-align: center;
            z-index: 4000;
            box-shadow: 0 20px 40px rgba(76, 175, 80, 0.4);
            animation: popIn 0.5s ease-out;
        }

        .time-bonus-popup h3 {
            font-size: 2rem;
            margin-bottom: 10px;
        }

        .time-bonus-popup p {
            font-size: 1.2rem;
            margin-bottom: 20px;
        }

        .bonus-seconds {
            font-size: 3rem;
            font-weight: 900;
            background: linear-gradient(45deg, #ffeb3b, #ffc107);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin: 10px 0;
        }

        @keyframes popIn {
            0% {
                transform: translate(-50%, -50%) scale(0.5);
                opacity: 0;
            }

            100% {
                transform: translate(-50%, -50%) scale(1);
                opacity: 1;
            }
        }

        /* Game Session Score Display */
        .session-score {
            background: linear-gradient(135deg, rgba(106, 17, 203, 0.9), rgba(37, 117, 252, 0.9));
            padding: 12px 20px;
            border-radius: 15px;
            color: white;
            font-weight: 700;
            backdrop-filter: blur(10px);
            border: 2px solid rgba(255, 255, 255, 0.3);
            box-shadow: 0 8px 25px rgba(106, 17, 203, 0.3);
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 1.1rem;
            margin-left: 15px;
        }
    </style>
</head>

<body>
    <?php if ($current_screen === 'game'): ?>
        <!-- Countdown Screen -->
        <div id="countdownScreen" class="screen countdown-screen">
            <div class="countdown-content">
                <div class="countdown-number" id="countdownNumber">3</div>
                <div class="countdown-text">Get Ready!</div>
            </div>
        </div>

        <!-- Game Screen -->
        <div id="gameScreen" class="screen">
            <div class="game-container">
                <!-- Header -->
                <div class="game-header">
                    <div class="header-left">
                        <div class="logo">❤️</div>
                        <div class="game-title-small">Flipping Hearts</div>
                        <div class="welcome-message" id="welcomeMessage">Welcome,
                            <?php echo htmlspecialchars($_SESSION['username']); ?>!
                        </div>
                        <div class="score-display">
                            <span class="score-icon">🏆</span>
                            Total Score: <span id="currentScore"><?php echo $_SESSION['score']; ?></span>
                        </div>
                        <div class="session-score">
                            <span class="score-icon">🎮</span>
                            Game Score: <span id="sessionScore">0</span>
                        </div>
                    </div>
                    <div class="header-right">
                        <div class="stats">
                            <div class="stat">
                                <div class="stat-icon">⏱️</div>
                                <div class="stat-value" id="timerValue">60s</div>
                            </div>
                            <div class="stat">
                                <div class="stat-icon">🎯</div>
                                <div class="stat-value" id="matchesValue">0/8</div>
                            </div>
                            <div class="stat">
                                <div class="stat-icon">🔄</div>
                                <div class="stat-value" id="movesValue">0</div>
                            </div>
                        </div>
                        <div class="logout-section">
                            <a href="index.php?logout=1" class="logout-btn">Logout</a>
                        </div>
                    </div>
                </div>

                <!-- Main Content -->
                <div class="game-content">
                    <!-- Left Panel - Progress -->
                    <div class="left-panel">
                        <div class="progress-container">
                            <div class="liquid-timer">
                                <div class="liquid-fill" id="liquidFill"></div>
                                <div class="timer-bubbles">
                                    <div class="bubble bubble-1"></div>
                                    <div class="bubble bubble-2"></div>
                                    <div class="bubble bubble-3"></div>
                                    <div class="bubble bubble-4"></div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Center Panel - Game Board -->
                    <div class="center-panel">
                        <div class="character-left">
                            <div class="character character-cartoon">
                                <img src="images/cartoon.png" alt="Cartoon Character" class="character-img">
                                <div class="speech-bubble" id="cartoonSpeech">
                                    Find all the hearts! ❤️
                                </div>
                            </div>
                        </div>

                        <div id="gameBoard" class="game-board"></div>

                        <div class="character-right">
                            <div class="character character-carrot">
                                <img src="images/carrot.png" alt="Carrot Character" class="character-img">
                                <div class="speech-bubble" id="carrotSpeech">
                                    Don't find me! 🥕
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Right Panel - Visual Feedback -->
                    <div class="right-panel">
                        <div class="feedback-container">
                            <div class="pulse-animation" id="pulseAnimation">
                                <div class="pulse-circle"></div>
                                <div class="pulse-circle"></div>
                                <div class="pulse-circle"></div>
                            </div>
                            <div class="status-message" id="statusMessage">
                                Ready to Play!
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Victory Popup -->
        <div id="victoryPopup" class="popup-screen">
            <div class="popup-content">
                <div class="popup-icon">🎉</div>
                <h3>Victory!</h3>
                <p>Congratulations! You found all the hearts!</p>
                <div class="score-popup">
                    <h4>Game Results</h4>
                    <div class="final-score" id="finalScoreVictory">0</div>
                    <div class="score-breakdown">
                        <div class="score-item">
                            <div class="score-label">Hearts Found</div>
                            <div class="score-value" id="heartsFoundVictory">0</div>
                        </div>
                        <div class="score-item">
                            <div class="score-label">Time Bonus</div>
                            <div class="score-value" id="timeBonusVictory">0</div>
                        </div>
                        <div class="score-item">
                            <div class="score-label">Moves</div>
                            <div class="score-value" id="movesVictory">0</div>
                        </div>
                        <div class="score-item">
                            <div class="score-label">Total Score</div>
                            <div class="score-value" id="totalScoreVictory">0</div>
                        </div>
                    </div>
                </div>
                <div class="popup-buttons">
                    <button id="playAgain" class="popup-btn confirm-btn">Play Again</button>
                    <a href="index.php?logout=1" class="popup-btn logout-btn">Logout</a>
                </div>
            </div>
        </div>

        <!-- Game Over Popup -->
        <div id="gameOverPopup" class="popup-screen">
            <div class="popup-content">
                <div class="popup-icon">⏰</div>
                <h3>Time's Up!</h3>
                <p>Better luck next time!</p>
                <div class="score-popup">
                    <h4>Game Results</h4>
                    <div class="final-score" id="finalScoreGameOver">0</div>
                    <div class="score-breakdown">
                        <div class="score-item">
                            <div class="score-label">Hearts Found</div>
                            <div class="score-value" id="heartsFoundGameOver">0</div>
                        </div>
                        <div class="score-item">
                            <div class="score-label">Time Bonus</div>
                            <div class="score-value" id="timeBonusGameOver">0</div>
                        </div>
                        <div class="score-item">
                            <div class="score-label">Moves</div>
                            <div class="score-value" id="movesGameOver">0</div>
                        </div>
                        <div class="score-item">
                            <div class="score-label">Total Score</div>
                            <div class="score-value" id="totalScoreGameOver">0</div>
                        </div>
                    </div>
                </div>
                <div class="popup-buttons">
                    <button id="playAgainGameOver" class="popup-btn confirm-btn">Play Again</button>
                    <a href="index.php?logout=1" class="popup-btn logout-btn">Logout</a>
                </div>
            </div>
        </div>

        <!-- Heart API Bonus Screen -->
        <div id="bonusScreen" class="screen">
            <div class="bonus-container">
                <div class="bonus-header">
                    <h2>⏰ Bonus Time Challenge! ⏰</h2>
                    <p>Count the hearts correctly to earn extra time and continue your game!</p>
                </div>
                <div class="api-content">
                    <div id="heartChallenge">
                        <div class="loading-heart">Loading bonus challenge... ❤️</div>
                    </div>
                    <div class="bonus-buttons">
                        <button id="backToGame" class="bonus-btn back-btn">← Back to Game</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Time Bonus Popup -->
        <div id="timeBonusPopup" class="time-bonus-popup" style="display: none;">
            <h3>🎉 Congratulations! 🎉</h3>
            <p>You counted the hearts correctly!</p>
            <div class="bonus-seconds" id="bonusSeconds">+30s</div>
            <p>Extra time added to your game!</p>
        </div>

    <?php else: ?>
        <!-- Splash Screen - Only show on first visit -->
        <div id="splashScreen" class="screen <?php echo $is_first_visit ? 'active' : ''; ?>">
            <div class="splash-content">
                <h1 class="game-title">Flipping Hearts</h1>
                <div class="loading-container">
                    <div class="loading-bar">
                        <div class="loading-progress"></div>
                    </div>
                    <p class="loading-text">Loading...</p>
                </div>
            </div>
        </div>

        <!-- Login Form -->
        <div id="loginScreen" class="screen <?php echo $current_screen === 'login' ? 'active' : ''; ?>">
            <div class="login-container">
                <div class="login-header">
                    <h2>Welcome Back!</h2>
                    <p>Enter your credentials to continue</p>
                </div>

                <?php if (isset($login_error)): ?>
                    <div class="error-message"><?php echo $login_error; ?></div>
                <?php endif; ?>

                <?php if (isset($registration_complete)): ?>
                    <div class="success-message">Registration successful! Please login with your credentials.</div>
                <?php endif; ?>

                <?php if (isset($login_success)): ?>
                    <!-- Success Message -->
                    <div id="successMessage" class="success-popup active">
                        <div class="success-content">
                            <div class="success-icon">✓</div>
                            <h3>Login Successful!</h3>
                            <p>Welcome to Flipping Hearts!</p>
                        </div>
                    </div>
                    <script>
                        setTimeout(function () {
                            window.location.href = 'index.php';
                        }, 2000);
                    </script>
                <?php endif; ?>

                <form method="POST" class="login-form">
                    <input type="hidden" name="login" value="1">
                    <div class="input-group">
                        <div class="input-icon">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                stroke-width="2">
                                <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                                <circle cx="12" cy="7" r="4"></circle>
                            </svg>
                        </div>
                        <input type="text" name="username" placeholder="Enter username" required
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
                        <input type="password" name="password" placeholder="Enter password" required>
                    </div>
                    <button type="submit" class="login-btn">
                        <span class="btn-text">Login</span>
                        <div class="btn-loader"></div>
                    </button>
                    <div class="register-link">
                        <p>New to game? <a href="register.php">REGISTER</a></p>
                    </div>
                </form>
            </div>
        </div>

        <!-- Success Message Popup -->
        <div id="successMessage" class="success-popup">
            <div class="success-content">
                <div class="success-icon">✓</div>
                <h3>Login Successful!</h3>
                <p>Welcome to Flipping Hearts!</p>
            </div>
        </div>
    <?php endif; ?>

    <script src="index.js"></script>
    <script>
        // Handle splash screen transition
        document.addEventListener('DOMContentLoaded', function () {
            const splashScreen = document.getElementById('splashScreen');
            const loginScreen = document.getElementById('loginScreen');

            // Only run splash screen transition on first visit
            if (splashScreen && splashScreen.classList.contains('active')) {
                setTimeout(function () {
                    splashScreen.classList.remove('active');

                    // Show login screen after splash on first visit
                    setTimeout(function () {
                        if (loginScreen) {
                            loginScreen.classList.add('active');
                        }
                    }, 500);
                }, 3000);
            }

            // Auto-start game if user is logged in
            <?php if ($current_screen === 'game'): ?>
                setTimeout(function () {
                    // Let the original index.js handle the game initialization
                    if (typeof startCountdown === 'function') {
                        startCountdown();
                    }
                }, 1000);
            <?php endif; ?>
        });

        // Function to update score in database (called when game ends)
        function updateScoreInDatabase(finalScore, gameData) {
            fetch('update_score.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    score: finalScore,
                    gameData: gameData
                })
            })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        console.log('Score updated successfully in database');
                        // Update the displayed score
                        document.getElementById('currentScore').textContent = data.newScore;
                    } else {
                        console.error('Failed to update score:', data.message);
                    }
                })
                .catch(error => console.error('Error updating score:', error));
        }

        // Function to show time bonus popup
        function showTimeBonusPopup(seconds) {
            const popup = document.getElementById('timeBonusPopup');
            const bonusSeconds = document.getElementById('bonusSeconds');

            bonusSeconds.textContent = `+${seconds}s`;
            popup.style.display = 'block';

            setTimeout(() => {
                popup.style.display = 'none';
            }, 3000);
        }
    </script>
</body>

</html>