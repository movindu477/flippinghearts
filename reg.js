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

// ---------------- INITIALIZATION ----------------
document.addEventListener('DOMContentLoaded', function() {
    // Initialize user management
    UserManager.init();
    
    document.getElementById('registerForm').addEventListener('submit', function(e) {
        e.preventDefault();
        
        const username = document.getElementById('username').value.trim();
        const password = document.getElementById('password').value.trim();
        const registerBtn = document.querySelector('.register-btn');
        const messageDiv = document.getElementById('message');
        
        // Basic validation
        if (username.length < 3) {
            showMessage('Username must be at least 3 characters long!', 'error');
            return;
        }
        
        if (password.length < 4) {
            showMessage('Password must be at least 4 characters long!', 'error');
            return;
        }
        
        registerBtn.classList.add('loading');
        messageDiv.style.display = 'none';
        
        // Use JavaScript registration
        setTimeout(() => {
            const result = UserManager.register(username, password);
            registerBtn.classList.remove('loading');
            
            if (result.success) {
                showMessage(result.message, 'success');
                // Redirect to login page after 2 seconds
                setTimeout(() => {
                    window.location.href = 'index.html';
                }, 2000);
            } else {
                showMessage(result.message, 'error');
            }
        }, 1000);
    });
});

// ---------------- MESSAGE DISPLAY ----------------
function showMessage(message, type) {
    const messageDiv = document.getElementById('message');
    messageDiv.textContent = message;
    messageDiv.className = 'message ' + type;
    messageDiv.style.display = 'block';
}