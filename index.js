// ---------------- USER MANAGEMENT ----------------
const UserManager = {
    // Initialize users in localStorage
    init: function() {
        if (!localStorage.getItem('flippingHeartsUsers')) {
            localStorage.setItem('flippingHeartsUsers', JSON.stringify({}));
        }
    },

    // Register new user
    register: function(username, password) {
        const users = this.getUsers();
        
        if (users[username]) {
            return { success: false, message: 'Username already exists!' };
        }
        
        if (username.length < 3) {
            return { success: false, message: 'Username must be at least 3 characters long!' };
        }
        
        if (password.length < 4) {
            return { success: false, message: 'Password must be at least 4 characters long!' };
        }
        
        users[username] = { password: password, createdAt: new Date().toISOString() };
        this.saveUsers(users);
        
        return { success: true, message: 'Registration successful! Redirecting to login...' };
    },

    // Login user
    login: function(username, password) {
        const users = this.getUsers();
        
        if (!users[username]) {
            return { success: false, message: 'Username not found! Please register first.' };
        }
        
        if (users[username].password !== password) {
            return { success: false, message: 'Invalid password!' };
        }
        
        // Store current user session
        localStorage.setItem('currentUser', username);
        
        return { success: true, message: 'Login successful!' };
    },

    // Get all users
    getUsers: function() {
        return JSON.parse(localStorage.getItem('flippingHeartsUsers') || '{}');
    },

    // Save users
    saveUsers: function(users) {
        localStorage.setItem('flippingHeartsUsers', JSON.stringify(users));
    }
};

// ---------------- GAME STATE ----------------
const gameState = {
    currentScreen: 'splash',
    cards: [],
    flippedCards: [],
    totalPairs: 8,
    gameStarted: false,
    timer: null,
    timeLeft: 60,
    totalTime: 60,
    moves: 0,
    apiCompleted: false,
    heartCards: [],
    apiRedirectTimer: null,
    heartCardsFlipped: 0,
    currentHeartChallenge: null,
    gameWon: false,
    heartAnswer: null,
    sessionScore: 0, // Current game session score
    totalScore: 0,   // Total score from database
    heartsFound: 0,
    timeBonus: 0,
    savedGameState: null
};

// ---------------- CARD TYPES ----------------
const cardTypes = [
    { type: 'heart', icon: '❤️' },
    { type: 'carrot', icon: '🥕' }
];

// ---------------- CHARACTER MESSAGES ----------------
const cartoonMessages = [
    "Find all the hearts! ❤️",
    "Good job! You found a heart!",
    "Excellent! Keep going!",
    "You're doing perfect!",
    "Almost there! Find more hearts!",
    "Wonderful! Another heart found!",
    "You're a heart-finding expert!",
    "Keep up the great work!",
    "Fantastic! The hearts love you!",
    "You're making great progress!"
];

const carrotMessages = [
    "Don't find me! 🥕",
    "Ahh ahh! You found me!",
    "Oh no! I've been discovered!",
    "You weren't supposed to find me!",
    "My evil plan is ruined!",
    "Curses! You found a carrot!",
    "Nooo! Don't flip me!",
    "I was hiding so well!",
    "You're too good at this!",
    "My carrot powers are fading!"
];

// ---------------- HEART API ----------------
const heartAPI = 'https://marcconrad.com/uob/heart/api.php';

// ---------------- INITIALIZATION ----------------
document.addEventListener('DOMContentLoaded', initializeGame);

function initializeGame() {
    UserManager.init();
    showScreen('splash');
    setTimeout(() => showScreen('login'), 3000);
    document.getElementById('loginForm').addEventListener('submit', handleLogin);
    document.getElementById('playAgain').addEventListener('click', resetGame);
    document.getElementById('playAgainGameOver').addEventListener('click', resetGame);
    
    // Add event listeners for bonus screen buttons
    document.getElementById('backToGame').addEventListener('click', backToGame);
}

// ---------------- SCREEN CONTROL ----------------
function showScreen(screenName) {
    document.querySelectorAll('.screen').forEach(s => s.classList.remove('active'));
    document.getElementById(screenName + 'Screen').classList.add('active');
    gameState.currentScreen = screenName;
}

// ---------------- LOGIN ----------------
function handleLogin(e) {
    e.preventDefault();
    const username = document.getElementById('username').value.trim();
    const password = document.getElementById('password').value.trim();
    const loginBtn = document.querySelector('.login-btn');

    loginBtn.classList.add('loading');
    
    setTimeout(() => {
        const result = UserManager.login(username, password);
        loginBtn.classList.remove('loading');
        
        if (result.success) {
            showSuccessMessage();
        } else {
            alert(result.message);
            loginBtn.style.animation = 'shake 0.5s';
            setTimeout(() => (loginBtn.style.animation = ''), 500);
        }
    }, 1000);
}

function showSuccessMessage() {
    const popup = document.getElementById('successMessage');
    document.querySelector('.login-btn').classList.remove('loading');
    popup.classList.add('active');

    setTimeout(() => {
        popup.classList.remove('active');
        startCountdown();
    }, 2000);
}

// ---------------- COUNTDOWN ----------------
function startCountdown() {
    showScreen('countdown');
    let countdown = 3;
    const num = document.getElementById('countdownNumber');
    const text = document.querySelector('.countdown-text');

    num.textContent = countdown;
    const interval = setInterval(() => {
        countdown--;
        if (countdown > 0) {
            num.textContent = countdown;
            num.style.animation = 'none';
            setTimeout(() => (num.style.animation = 'countdownPop 1s ease-out'), 10);
        } else {
            num.textContent = 'GO!';
            text.textContent = 'Game Started!';
            clearInterval(interval);
            setTimeout(() => {
                showScreen('game');
                initializeGameBoard();
                startGame();
            }, 1000);
        }
    }, 1000);
}

// ---------------- GAME BOARD ----------------
function initializeGameBoard() {
    const board = document.getElementById('gameBoard');
    board.innerHTML = '';
    Object.assign(gameState, {
        cards: [],
        flippedCards: [],
        gameStarted: false,
        timeLeft: gameState.totalTime,
        apiCompleted: false,
        heartCards: [],
        heartCardsFlipped: 0,
        gameWon: false,
        heartAnswer: null,
        moves: 0,
        sessionScore: 0, // Reset session score for new game
        heartsFound: 0,
        timeBonus: 0,
        savedGameState: null
    });

    updateUI();
    updateWelcomeMessage();

    const cardValues = [];
    for (let i = 0; i < gameState.totalPairs; i++) {
        const type = cardTypes[Math.floor(Math.random() * cardTypes.length)];
        cardValues.push(type, type);
    }
    shuffleArray(cardValues);

    cardValues.forEach((data, i) => {
        const card = document.createElement('div');
        card.className = 'card';
        card.dataset.index = i;
        card.dataset.value = data.type;
        card.innerHTML = `
            <div class="card-inner">
                <div class="card-front"><div class="icon">${data.icon}</div></div>
                <div class="card-back">?</div>
            </div>`;
        card.addEventListener('click', () => flipCard(card));
        board.appendChild(card);
        gameState.cards.push(card);
        
        if (data.type === 'heart') {
            gameState.heartCards.push(card);
        }
    });

    resetTimer();
    updateStatusMessage('Find all the ❤️! Avoid 🥕!');
    updateCharacterMessage('cartoon', "Find all the hearts! ❤️");
    updateCharacterMessage('carrot', "Don't find me! 🥕");
}

// ---------------- UPDATE WELCOME MESSAGE ----------------
function updateWelcomeMessage() {
    const currentUser = localStorage.getItem('currentUser');
    const welcomeElement = document.getElementById('welcomeMessage');
    
    if (currentUser && welcomeElement) {
        welcomeElement.textContent = `Welcome, ${currentUser}!`;
        welcomeElement.style.display = 'block';
    }
}

// ---------------- SCORING SYSTEM ----------------
function addScore(points, reason = '') {
    gameState.sessionScore += points;
    updateSessionScoreDisplay();
    
    console.log(`+${points} points${reason ? ` for ${reason}` : ''}. Total: ${gameState.sessionScore}`);
    
    // Show score animation
    showScoreAnimation(points);
}

function showScoreAnimation(points) {
    const scoreElement = document.getElementById('sessionScore');
    scoreElement.style.transform = 'scale(1.2)';
    scoreElement.style.color = '#ff6b9d';
    
    setTimeout(() => {
        scoreElement.style.transform = 'scale(1)';
        scoreElement.style.color = '';
    }, 300);
}

function updateSessionScoreDisplay() {
    document.getElementById('sessionScore').textContent = gameState.sessionScore;
}

// ---------------- CARD FLIP ----------------
function flipCard(card) {
    if (!gameState.gameStarted || card.classList.contains('flipped') || gameState.flippedCards.length >= 2)
        return;

    card.classList.add('flipped');
    gameState.flippedCards.push(card);
    const type = card.dataset.value;
    gameState.moves++;
    updateUI();

    if (type === 'carrot') {
        const randomMessage = carrotMessages[Math.floor(Math.random() * carrotMessages.length)];
        updateCharacterMessage('carrot', randomMessage);
        updateStatusMessage('Carrot found! Resetting hearts... 🥕');
        
        // No points for carrot, but penalty for reset
        addScore(-5, 'carrot penalty');
        
        setTimeout(() => {
            gameState.cards.forEach(c => c.classList.remove('flipped'));
            gameState.flippedCards = [];
            gameState.heartCardsFlipped = 0;
            gameState.heartsFound = 0;
            updateUI();
            setTimeout(() => updateCharacterMessage('carrot', "Don't find me! 🥕"), 2000);
        }, 800);
        return;
    }

    if (type === 'heart') {
        gameState.heartCardsFlipped++;
        gameState.heartsFound++;
        
        // Add points for finding a heart
        addScore(10, 'finding heart');
        
        setTimeout(() => {
            checkVictory();
        }, 100);
    }

    if (gameState.flippedCards.length === 2) {
        const [first, second] = gameState.flippedCards;
        if (first.dataset.value === 'heart' && second.dataset.value === 'heart') {
            const randomMessage = cartoonMessages[Math.floor(Math.random() * cartoonMessages.length)];
            updateCharacterMessage('cartoon', randomMessage);
            updateStatusMessage('Hearts Found! ❤️');
            
            // Bonus points for matching hearts
            addScore(25, 'matching hearts');
            
            gameState.flippedCards = [];
            updateUI();
        } else {
            setTimeout(() => {
                first.classList.remove('flipped');
                second.classList.remove('flipped');
                if (first.dataset.value === 'heart') {
                    gameState.heartCardsFlipped--;
                    gameState.heartsFound--;
                }
                if (second.dataset.value === 'heart') {
                    gameState.heartCardsFlipped--;
                    gameState.heartsFound--;
                }
                gameState.flippedCards = [];
                updateUI();
            }, 1000);
        }
    } else if (gameState.flippedCards.length === 1 && type === 'heart') {
        const encouragingMessages = cartoonMessages.filter(msg => 
            msg.includes("Good job") || msg.includes("Excellent") || msg.includes("Wonderful")
        );
        const randomMessage = encouragingMessages[Math.floor(Math.random() * encouragingMessages.length)];
        updateCharacterMessage('cartoon', randomMessage);
    }
}

// ---------------- CHARACTER MESSAGES ----------------
function updateCharacterMessage(character, message) {
    const speechBubble = document.getElementById(character + 'Speech');
    if (speechBubble) {
        speechBubble.textContent = message;
        speechBubble.style.animation = 'none';
        setTimeout(() => {
            speechBubble.style.animation = 'fadeInOut 2s ease-in-out infinite';
        }, 10);
    }
}

// ---------------- GAME LOGIC ----------------
function startGame() {
    if (gameState.gameStarted) return;
    gameState.gameStarted = true;
    startTimer();
    updateStatusMessage('Game Started! ❤️ Avoid 🥕!');
}

function startTimer() {
    gameState.timeLeft = gameState.totalTime;
    updateUI();
    gameState.timer = setInterval(() => {
        gameState.timeLeft--;
        updateUI();
        if (gameState.timeLeft <= 10 && gameState.timeLeft > 0) {
            updateStatusMessage('Hurry Up! ⏰');
            updateCharacterMessage('cartoon', "Hurry! Time is running out!");
        }
        if (gameState.timeLeft <= 0) {
            clearInterval(gameState.timer);
            gameOver();
        }
    }, 1000);
}

function resetTimer() {
    clearInterval(gameState.timer);
    gameState.timeLeft = gameState.totalTime;
    updateUI();
}

function addExtraTime(seconds) {
    gameState.timeLeft += seconds;
    updateUI();
    updateStatusMessage(`+${seconds} seconds bonus time! ⏰`);
    updateCharacterMessage('cartoon', `Great! You got ${seconds} extra seconds!`);
    
    // Show time bonus popup
    if (typeof showTimeBonusPopup === 'function') {
        showTimeBonusPopup(seconds);
    }
}

function updateUI() {
    document.getElementById('matchesValue').textContent = `${gameState.heartCardsFlipped}/${gameState.heartCards.length}`;
    document.getElementById('movesValue').textContent = gameState.moves;
    document.getElementById('timerValue').textContent = `${gameState.timeLeft}s`;
    const liquid = document.getElementById('liquidFill');
    if (liquid) liquid.style.height = `${(gameState.timeLeft / gameState.totalTime) * 100}%`;
}

function updateStatusMessage(msg) {
    const el = document.getElementById('statusMessage');
    el.textContent = msg;
    el.style.animation = 'none';
    setTimeout(() => (el.style.animation = 'textGlow 2s ease-in-out infinite'), 10);
}

// ---------------- VICTORY CHECK ----------------
function checkVictory() {
    const allHeartsFlipped = gameState.heartCardsFlipped === gameState.heartCards.length;
    if (allHeartsFlipped) {
        gameWon();
    }
}

// ---------------- FINAL SCORE CALCULATION ----------------
function calculateFinalScore() {
    // Base score from session
    let finalScore = gameState.sessionScore;
    
    // Time bonus (more time left = more points)
    const timeBonus = Math.floor(gameState.timeLeft * 2);
    finalScore += timeBonus;
    
    // Perfect game bonus (all hearts found)
    if (gameState.heartCardsFlipped === gameState.heartCards.length) {
        finalScore += 100; // Perfect game bonus
    }
    
    // Efficiency bonus (less moves = more points)
    const efficiencyBonus = Math.max(0, 50 - gameState.moves);
    finalScore += efficiencyBonus;
    
    return {
        finalScore,
        timeBonus,
        efficiencyBonus,
        perfectBonus: gameState.heartCardsFlipped === gameState.heartCards.length ? 100 : 0
    };
}

// ---------------- WIN / GAME OVER ----------------
function gameWon() {
    clearInterval(gameState.timer);
    gameState.apiCompleted = true;
    gameState.gameStarted = false;
    gameState.gameWon = true;
    
    // Calculate final score
    const scoreData = calculateFinalScore();
    
    updateStatusMessage('Victory! 🎉');
    updateCharacterMessage('cartoon', "You did it! All hearts found! 🎉");
    updateCharacterMessage('carrot', "You win... this time! 🥕");
    
    setTimeout(() => {
        gameState.cards.forEach(card => {
            if (card.dataset.value === 'carrot' && !card.classList.contains('flipped')) {
                card.classList.add('flipped');
            }
        });
    }, 500);
    
    // Update victory popup with score details
    document.getElementById('finalScoreVictory').textContent = scoreData.finalScore;
    document.getElementById('heartsFoundVictory').textContent = gameState.heartsFound;
    document.getElementById('timeBonusVictory').textContent = scoreData.timeBonus;
    document.getElementById('movesVictory').textContent = gameState.moves;
    document.getElementById('totalScoreVictory').textContent = scoreData.finalScore;
    
    // Update final score in database
    updateScoreInDatabase(scoreData.finalScore, {
        heartsFound: gameState.heartsFound,
        timeBonus: scoreData.timeBonus,
        moves: gameState.moves,
        gameType: 'victory',
        sessionScore: gameState.sessionScore
    });
    
    // Show victory popup
    setTimeout(() => {
        document.getElementById('victoryPopup').classList.add('active');
    }, 1000);
}

function gameOver() {
    clearInterval(gameState.timer);
    gameState.gameStarted = false;

    const allHeartsFlipped = gameState.heartCardsFlipped === gameState.heartCards.length;
    
    if (!allHeartsFlipped && !gameState.apiCompleted) {
        // Save current game state before showing bonus challenge
        gameState.savedGameState = {
            cards: [...gameState.cards],
            flippedCards: [...gameState.flippedCards],
            heartCardsFlipped: gameState.heartCardsFlipped,
            heartsFound: gameState.heartsFound,
            moves: gameState.moves,
            sessionScore: gameState.sessionScore,
            heartCards: [...gameState.heartCards]
        };
        
        updateStatusMessage('Time\'s Up! Better luck next time! ⏰');
        updateCharacterMessage('cartoon', "Oh no! Time's up! Try the bonus challenge!");
        updateCharacterMessage('carrot', "Haha! You lost! 🥕");
        
        // Show bonus challenge instead of game over popup
        setTimeout(() => {
            showBonusChallenge();
        }, 1500);
    } else if (allHeartsFlipped) {
        // Player won but time ran out - still show victory
        gameWon();
    } else {
        // Game over without finding all hearts
        const scoreData = calculateFinalScore();
        
        // Update game over popup with score details
        document.getElementById('finalScoreGameOver').textContent = scoreData.finalScore;
        document.getElementById('heartsFoundGameOver').textContent = gameState.heartsFound;
        document.getElementById('timeBonusGameOver').textContent = scoreData.timeBonus;
        document.getElementById('movesGameOver').textContent = gameState.moves;
        document.getElementById('totalScoreGameOver').textContent = scoreData.finalScore;
        
        // Update final score in database
        updateScoreInDatabase(scoreData.finalScore, {
            heartsFound: gameState.heartsFound,
            timeBonus: scoreData.timeBonus,
            moves: gameState.moves,
            gameType: 'game_over',
            sessionScore: gameState.sessionScore
        });
        
        // Show game over popup
        setTimeout(() => {
            document.getElementById('gameOverPopup').classList.add('active');
        }, 1500);
    }
}

// ---------------- UPDATE SCORE IN DATABASE ----------------
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
            // Update the displayed total score
            document.getElementById('currentScore').textContent = data.newScore;
        } else {
            console.error('Failed to update score:', data.message);
        }
    })
    .catch(error => console.error('Error updating score:', error));
}

// ---------------- BONUS CHALLENGE FUNCTIONS ----------------
function showBonusChallenge() {
    showScreen('bonus');
    loadHeartChallenge();
}

function loadHeartChallenge() {
    const heartChallenge = document.getElementById('heartChallenge');
    heartChallenge.innerHTML = '<div class="loading-heart">Loading heart challenge... ❤️</div>';
    
    console.log('Fetching heart challenge from API...');
    
    fetch(heartAPI)
        .then(response => {
            if (!response.ok) {
                throw new Error('Network response was not ok');
            }
            return response.json();
        })
        .then(data => {
            console.log('Heart challenge data received:', data);
            gameState.currentHeartChallenge = data;
            gameState.heartAnswer = data.solution;
            displayHeartChallenge(data);
        })
        .catch(error => {
            console.error('Error fetching heart challenge:', error);
            heartChallenge.innerHTML = `
                <div class="error-message">
                    <p>Failed to load heart challenge.</p>
                    <p>Please check your internet connection.</p>
                    <button onclick="loadHeartChallenge()" class="bonus-btn back-btn">Retry</button>
                </div>
            `;
        });
}

function displayHeartChallenge(data) {
    const heartChallenge = document.getElementById('heartChallenge');
    
    if (data.question) {
        heartChallenge.innerHTML = `
            <div class="challenge-content">
                <h3>Bonus Time Challenge! ⏰</h3>
                <p class="challenge-description">Count the hearts correctly to earn extra time and continue your game!</p>
                <img src="${data.question}" alt="Heart Challenge" class="heart-image" onload="console.log('Image loaded successfully')" onerror="console.log('Image failed to load')">
                <p class="challenge-hint">How many hearts do you see in the image above? Enter the number below!</p>
                <div class="answer-input-container">
                    <input type="number" id="heartAnswerInput" class="answer-input" placeholder="Enter heart count" min="0">
                    <button id="submitAnswerBtn" class="submit-btn">Submit Answer</button>
                </div>
                <div id="answerFeedback" class="answer-feedback"></div>
            </div>
        `;
        
        setTimeout(() => {
            const submitBtn = document.getElementById('submitAnswerBtn');
            const answerInput = document.getElementById('heartAnswerInput');
            
            if (submitBtn && answerInput) {
                submitBtn.addEventListener('click', submitAnswer);
                answerInput.addEventListener('keypress', function(e) {
                    if (e.key === 'Enter') {
                        submitAnswer();
                    }
                });
                
                answerInput.focus();
            }
        }, 100);
    } else {
        heartChallenge.innerHTML = `
            <div class="error-message">
                <p>No challenge available at the moment.</p>
                <button onclick="loadHeartChallenge()" class="bonus-btn back-btn">Try Again</button>
            </div>
        `;
    }
}

function submitAnswer() {
    const userAnswer = document.getElementById('heartAnswerInput').value.trim();
    const feedback = document.getElementById('answerFeedback');
    
    if (!userAnswer) {
        feedback.textContent = 'Please enter a number!';
        feedback.className = 'answer-feedback error';
        return;
    }
    
    const userAnswerNum = parseInt(userAnswer);
    const correctAnswer = gameState.heartAnswer;
    
    if (userAnswerNum === correctAnswer) {
        feedback.innerHTML = '🎉 <strong>Correct!</strong> Well done! You counted the hearts perfectly! 🎉';
        feedback.className = 'answer-feedback success';
        
        // Add bonus points for correct answer
        addScore(50, 'bonus challenge');
        
        showCelebration();
        
        // Return to game with extra time after 3 seconds
        setTimeout(() => {
            resumeGameWithExtraTime(30); // 30 seconds extra time
        }, 3000);
    } else {
        let hint = '';
        if (userAnswerNum < correctAnswer) {
            hint = ' (Too low! Try a higher number)';
        } else {
            hint = ' (Too high! Try a lower number)';
        }
        
        feedback.innerHTML = `❌ <strong>Incorrect!</strong> ${userAnswer} is not the right number of hearts.${hint}`;
        feedback.className = 'answer-feedback error';
        
        const input = document.getElementById('heartAnswerInput');
        input.style.animation = 'shake 0.5s';
        setTimeout(() => {
            input.style.animation = '';
            input.focus();
            input.select();
        }, 500);
    }
}

function resumeGameWithExtraTime(extraSeconds) {
    showScreen('game');
    
    // Add extra time to the timer and set liquid timer to half full
    addExtraTime(extraSeconds);
    
    // Restart the timer if it was stopped
    if (!gameState.gameStarted) {
        gameState.gameStarted = true;
        startTimer();
    }
    
    updateStatusMessage(`Bonus! +${extraSeconds} seconds! Continue playing!`);
    updateCharacterMessage('cartoon', "Great! You got extra time! Find more hearts! ❤️");
    updateCharacterMessage('carrot', "Oh no! You got more time! 🥕");
}

function showCelebration() {
    const challengeContent = document.querySelector('.challenge-content');
    const confetti = document.createElement('div');
    confetti.className = 'celebration-confetti';
    confetti.innerHTML = `
        <div class="confetti"></div>
        <div class="confetti"></div>
        <div class="confetti"></div>
        <div class="confetti"></div>
        <div class="confetti"></div>
        <div class="confetti"></div>
        <div class="confetti"></div>
        <div class="confetti"></div>
        <div class="confetti"></div>
        <div class="confetti"></div>
    `;
    challengeContent.appendChild(confetti);
    
    setTimeout(() => {
        confetti.remove();
    }, 3000);
}

function backToGame() {
    // If user goes back without solving, show game over
    showScreen('game');
    gameOver();
}

// ---------------- RESET GAME ----------------
function resetGame() {
    if (gameState.apiRedirectTimer) {
        clearTimeout(gameState.apiRedirectTimer);
        gameState.apiRedirectTimer = null;
    }
    
    document.getElementById('victoryPopup').classList.remove('active');
    document.getElementById('gameOverPopup').classList.remove('active');
    initializeGameBoard();
    startGame();
}

// ---------------- HELPER ----------------
function shuffleArray(arr) {
    for (let i = arr.length - 1; i > 0; i--) {
        const j = Math.floor(Math.random() * (i + 1));
        [arr[i], arr[j]] = [arr[j], arr[i]];
    }
    return arr;
}

// ---------------- CSS STYLES ----------------
const style = document.createElement('style');
style.textContent = `
@keyframes shake {
  0%,100% {transform:translateX(0);}
  25% {transform:translateX(-5px);}
  75% {transform:translateX(5px);}
}

@keyframes confettiFall {
  0% { transform: translateY(-100px) rotate(0deg); opacity: 1; }
  100% { transform: translateY(500px) rotate(360deg); opacity: 0; }
}

@keyframes timeBonus {
  0% { transform: scale(1); opacity: 1; }
  50% { transform: scale(1.2); opacity: 0.8; }
  100% { transform: scale(1); opacity: 1; }
}

#gameBoard {
  transition: transform .6s ease, opacity .6s ease;
  transform-style: preserve-3d;
}

.challenge-content {
    text-align: center;
    position: relative;
}

.challenge-content h3 {
    color: #333;
    margin-bottom: 1rem;
    font-size: 1.3rem;
}

.challenge-description {
    color: #666;
    font-size: 1.1rem;
    margin-bottom: 1.5rem;
    font-weight: 500;
}

.challenge-hint {
    color: #666;
    font-style: italic;
    margin-top: 1rem;
    margin-bottom: 1.5rem;
    font-size: 1.1rem;
}

.answer-input-container {
    display: flex;
    gap: 10px;
    justify-content: center;
    align-items: center;
    margin: 1.5rem 0;
}

.answer-input {
    padding: 12px 16px;
    border: 2px solid #ddd;
    border-radius: 8px;
    font-size: 1.1rem;
    width: 200px;
    text-align: center;
    transition: border-color 0.3s ease;
}

.answer-input:focus {
    outline: none;
    border-color: #ff6b6b;
    box-shadow: 0 0 0 3px rgba(255, 107, 107, 0.1);
}

.submit-btn {
    padding: 12px 24px;
    background: linear-gradient(135deg, #ff6b6b, #ff8e8e);
    color: white;
    border: none;
    border-radius: 8px;
    font-size: 1rem;
    cursor: pointer;
    transition: transform 0.2s ease, box-shadow 0.2s ease;
}

.submit-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(255, 107, 107, 0.3);
}

.answer-feedback {
    margin-top: 1rem;
    padding: 15px;
    border-radius: 8px;
    font-weight: bold;
    font-size: 1.1rem;
    transition: all 0.3s ease;
    min-height: 60px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.answer-feedback.success {
    background-color: #d4edda;
    color: #155724;
    border: 2px solid #c3e6cb;
}

.answer-feedback.error {
    background-color: #f8d7da;
    color: #721c24;
    border: 2px solid #f5c6cb;
}

.error-message {
    text-align: center;
    color: #666;
}

.error-message p {
    margin-bottom: 1rem;
}

.heart-image {
    max-width: 100%;
    height: auto;
    border-radius: 8px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
    border: 3px solid #ff6b6b;
    margin: 10px 0;
}

.celebration-confetti {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    pointer-events: none;
}

.confetti {
    position: absolute;
    width: 10px;
    height: 10px;
    background: #ff6b6b;
    top: -10px;
    animation: confettiFall 3s linear forwards;
}

.confetti:nth-child(1) { left: 10%; animation-delay: 0s; background: #ff6b6b; }
.confetti:nth-child(2) { left: 20%; animation-delay: 0.3s; background: #ff8e8e; }
.confetti:nth-child(3) { left: 30%; animation-delay: 0.6s; background: #ff6b6b; }
.confetti:nth-child(4) { left: 40%; animation-delay: 0.9s; background: #ff8e8e; }
.confetti:nth-child(5) { left: 50%; animation-delay: 1.2s; background: #ff6b6b; }
.confetti:nth-child(6) { left: 60%; animation-delay: 1.5s; background: #ff8e8e; }
.confetti:nth-child(7) { left: 70%; animation-delay: 1.8s; background: #ff6b6b; }
.confetti:nth-child(8) { left: 80%; animation-delay: 2.1s; background: #ff8e8e; }
.confetti:nth-child(9) { left: 90%; animation-delay: 2.4s; background: #ff6b6b; }
.confetti:nth-child(10) { left: 95%; animation-delay: 2.7s; background: #ff8e8e; }

.loading-heart {
    text-align: center;
    font-size: 1.2rem;
    color: #ff6b6b;
    padding: 2rem;
}

.time-bonus-notification {
    position: fixed;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    background: linear-gradient(135deg, #4CAF50, #45a049);
    color: white;
    padding: 20px 30px;
    border-radius: 15px;
    font-size: 1.5rem;
    font-weight: bold;
    z-index: 1000;
    animation: timeBonus 2s ease-in-out;
    box-shadow: 0 10px 30px rgba(76, 175, 80, 0.4);
}

/* Additional styles for better layout */
.bonus-btn {
    padding: 10px 20px;
    margin: 10px;
    border: none;
    border-radius: 5px;
    cursor: pointer;
    font-size: 1rem;
    transition: all 0.3s ease;
}

.back-btn {
    background: #6c757d;
    color: white;
}

.back-btn:hover {
    background: #5a6268;
}
`;
document.head.appendChild(style);


