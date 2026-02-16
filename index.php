<?php
// ============================================
// WHATSAPP-STYLE CHAT APPLICATION - FIXED LOGIN/REGISTER
// ============================================

session_start();

// ========== DATABASE SETUP ==========
$db_file = 'whatsapp_clone.db';

try {
    $pdo = new PDO("sqlite:$db_file");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Enable foreign keys
    $pdo->exec("PRAGMA foreign_keys = ON");
    
    // Create tables
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            phone_number TEXT UNIQUE NOT NULL,
            username TEXT UNIQUE NOT NULL,
            full_name TEXT NOT NULL,
            about TEXT DEFAULT 'Hey there! I am using WhatsApp Clone',
            profile_pic TEXT DEFAULT 'default.jpg',
            status TEXT DEFAULT 'offline',
            last_seen DATETIME DEFAULT CURRENT_TIMESTAMP,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            wallpaper TEXT DEFAULT 'default-wallpaper.jpg'
        );
        
        CREATE TABLE IF NOT EXISTS chats (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user1_id INTEGER NOT NULL,
            user2_id INTEGER NOT NULL,
            last_message_id INTEGER,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user1_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (user2_id) REFERENCES users(id) ON DELETE CASCADE,
            UNIQUE(user1_id, user2_id)
        );
        
        CREATE TABLE IF NOT EXISTS messages (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            chat_id INTEGER NOT NULL,
            sender_id INTEGER NOT NULL,
            message TEXT,
            message_type TEXT DEFAULT 'text',
            file_path TEXT,
            file_name TEXT,
            file_size INTEGER,
            duration INTEGER,
            is_read INTEGER DEFAULT 0,
            is_delivered INTEGER DEFAULT 0,
            is_starred INTEGER DEFAULT 0,
            reply_to_id INTEGER,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (chat_id) REFERENCES chats(id) ON DELETE CASCADE,
            FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (reply_to_id) REFERENCES messages(id) ON DELETE SET NULL
        );
        
        CREATE TABLE IF NOT EXISTS contacts (
            user_id INTEGER NOT NULL,
            contact_id INTEGER NOT NULL,
            contact_name TEXT,
            is_blocked INTEGER DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (user_id, contact_id),
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (contact_id) REFERENCES users(id) ON DELETE CASCADE
        );
    ");
    
    // Create directories
    $directories = ['uploads', 'uploads/status', 'uploads/files', 'uploads/wallpapers'];
    foreach ($directories as $dir) {
        if (!file_exists($dir)) {
            mkdir($dir, 0777, true);
        }
    }
    
    // Create default images
    if (!file_exists('uploads/default.jpg')) {
        // Create a simple default image using GD or copy a placeholder
        file_put_contents('uploads/default.jpg', '');
    }
    
} catch(PDOException $e) {
    die("Database error: " . $e->getMessage());
}

// ========== HELPER FUNCTIONS ==========
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function getUserById($id) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

// ========== API ROUTES ==========
$action = $_GET['action'] ?? 'home';

// ============================================
// REGISTER API - FIXED
// ============================================
if ($action == 'api_register') {
    header('Content-Type: application/json');
    
    if ($_SERVER['REQUEST_METHOD'] != 'POST') {
        echo json_encode(['success' => false, 'error' => 'Invalid request method']);
        exit;
    }
    
    // Get and sanitize input
    $full_name = trim($_POST['full_name'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $password = $_POST['password'] ?? '';
    
    $errors = [];
    
    // Validate Full Name
    if (empty($full_name)) {
        $errors['full_name'] = 'Full name is required';
    } elseif (strlen($full_name) < 2) {
        $errors['full_name'] = 'Full name must be at least 2 characters';
    } elseif (strlen($full_name) > 50) {
        $errors['full_name'] = 'Full name must be less than 50 characters';
    }
    
    // Validate Username
    if (empty($username)) {
        $errors['username'] = 'Username is required';
    } elseif (!preg_match('/^[a-zA-Z0-9_]{3,20}$/', $username)) {
        $errors['username'] = 'Username must be 3-20 characters and can only contain letters, numbers, and underscores';
    }
    
    // Validate Phone
    if (empty($phone)) {
        $errors['phone'] = 'Phone number is required';
    } elseif (!preg_match('/^[0-9+\-\s()]{10,15}$/', $phone)) {
        $errors['phone'] = 'Please enter a valid phone number (10-15 digits)';
    }
    
    // Validate Password
    if (empty($password)) {
        $errors['password'] = 'Password is required';
    } elseif (strlen($password) < 6) {
        $errors['password'] = 'Password must be at least 6 characters';
    }
    
    // If validation errors, return them
    if (!empty($errors)) {
        echo json_encode(['success' => false, 'errors' => $errors]);
        exit;
    }
    
    try {
        // Check if username or phone already exists
        $checkStmt = $pdo->prepare("SELECT username, phone_number FROM users WHERE username = ? OR phone_number = ?");
        $checkStmt->execute([$username, $phone]);
        $existing = $checkStmt->fetch(PDO::FETCH_ASSOC);
        
        if ($existing) {
            $fieldErrors = [];
            if ($existing['username'] === $username) {
                $fieldErrors['username'] = 'Username is already taken';
            }
            if ($existing['phone_number'] === $phone) {
                $fieldErrors['phone'] = 'Phone number is already registered';
            }
            echo json_encode(['success' => false, 'errors' => $fieldErrors]);
            exit;
        }
        
        // Hash password
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        
        // Insert new user
        $stmt = $pdo->prepare("INSERT INTO users (phone_number, username, full_name, password) VALUES (?, ?, ?, ?)");
        $result = $stmt->execute([$phone, $username, $full_name, $hashedPassword]);
        
        if ($result) {
            echo json_encode([
                'success' => true, 
                'message' => 'Registration successful! You can now login.'
            ]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Registration failed. Please try again.']);
        }
        
    } catch(PDOException $e) {
        error_log("Registration error: " . $e->getMessage());
        
        // Check for duplicate entry
        if (strpos($e->getMessage(), 'UNIQUE constraint failed') !== false) {
            if (strpos($e->getMessage(), 'username') !== false) {
                echo json_encode(['success' => false, 'errors' => ['username' => 'Username already exists']]);
            } elseif (strpos($e->getMessage(), 'phone_number') !== false) {
                echo json_encode(['success' => false, 'errors' => ['phone' => 'Phone number already exists']]);
            } else {
                echo json_encode(['success' => false, 'error' => 'Registration failed. Please try again.']);
            }
        } else {
            echo json_encode(['success' => false, 'error' => 'Database error occurred. Please try again.']);
        }
    }
    exit;
}

// ============================================
// LOGIN API - FIXED
// ============================================
if ($action == 'api_login') {
    header('Content-Type: application/json');
    
    if ($_SERVER['REQUEST_METHOD'] != 'POST') {
        echo json_encode(['success' => false, 'error' => 'Invalid request method']);
        exit;
    }
    
    // Get input
    $identifier = trim($_POST['identifier'] ?? '');
    $password = $_POST['password'] ?? '';
    
    $errors = [];
    
    if (empty($identifier)) {
        $errors['identifier'] = 'Phone or username is required';
    }
    
    if (empty($password)) {
        $errors['password'] = 'Password is required';
    }
    
    if (!empty($errors)) {
        echo json_encode(['success' => false, 'errors' => $errors]);
        exit;
    }
    
    try {
        // Find user by username or phone
        $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? OR phone_number = ?");
        $stmt->execute([$identifier, $identifier]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user && password_verify($password, $user['password'])) {
            // Set session
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['logged_in'] = true;
            
            // Update user status
            $updateStmt = $pdo->prepare("UPDATE users SET status = 'online', last_seen = CURRENT_TIMESTAMP WHERE id = ?");
            $updateStmt->execute([$user['id']]);
            
            // Remove password from response
            unset($user['password']);
            
            echo json_encode([
                'success' => true, 
                'user' => $user,
                'message' => 'Login successful!'
            ]);
        } else {
            echo json_encode([
                'success' => false, 
                'errors' => ['login' => 'Invalid username/phone or password']
            ]);
        }
        
    } catch(PDOException $e) {
        error_log("Login error: " . $e->getMessage());
        echo json_encode(['success' => false, 'error' => 'Database error occurred. Please try again.']);
    }
    exit;
}

// ============================================
// CHECK AVAILABILITY API
// ============================================
if ($action == 'api_check_availability') {
    header('Content-Type: application/json');
    
    if ($_SERVER['REQUEST_METHOD'] != 'POST') {
        echo json_encode(['success' => false, 'error' => 'Invalid request method']);
        exit;
    }
    
    $field = $_POST['field'] ?? '';
    $value = trim($_POST['value'] ?? '');
    
    if (!in_array($field, ['username', 'phone'])) {
        echo json_encode(['valid' => false, 'error' => 'Invalid field']);
        exit;
    }
    
    $dbField = ($field === 'username') ? 'username' : 'phone_number';
    
    try {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE $dbField = ?");
        $stmt->execute([$value]);
        $exists = $stmt->fetch() !== false;
        
        echo json_encode([
            'available' => !$exists,
            'field' => $field,
            'message' => $exists ? "$field is already taken" : "$field is available"
        ]);
        
    } catch(PDOException $e) {
        error_log("Availability check error: " . $e->getMessage());
        echo json_encode(['available' => false, 'error' => 'Database error']);
    }
    exit;
}

// ============================================
// LOGOUT API
// ============================================
if ($action == 'api_logout') {
    header('Content-Type: application/json');
    
    if (isLoggedIn()) {
        $stmt = $pdo->prepare("UPDATE users SET status = 'offline', last_seen = CURRENT_TIMESTAMP WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        
        // Clear session
        $_SESSION = array();
        session_destroy();
    }
    
    echo json_encode(['success' => true]);
    exit;
}

// ============================================
// GET CURRENT USER API
// ============================================
if ($action == 'api_get_current_user') {
    header('Content-Type: application/json');
    
    if (!isLoggedIn()) {
        echo json_encode(['logged_in' => false]);
        exit;
    }
    
    $user = getUserById($_SESSION['user_id']);
    unset($user['password']);
    
    echo json_encode([
        'logged_in' => true,
        'user' => $user
    ]);
    exit;
}

// ========== MAIN APPLICATION PAGE ==========
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>WhatsApp Clone</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        :root {
            --whatsapp-green: #25D366;
            --whatsapp-dark-green: #075E54;
            --whatsapp-teal: #128C7E;
            --whatsapp-light-green: #DCF8C6;
            --whatsapp-bg: #E5DDD5;
            --header-bg: #075E54;
            --sent-message: #DCF8C6;
            --received-message: #FFFFFF;
        }

        body {
            background: linear-gradient(135deg, #0f2027, #203a43, #2c5364);
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
            height: 100vh;
            overflow: hidden;
        }

        .app-wrapper {
            max-width: 1800px;
            margin: 0 auto;
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 10px;
        }

        .whatsapp-container {
            width: 100%;
            height: 100%;
            max-height: 1000px;
            background: white;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            display: flex;
            flex-direction: column;
        }

        /* Auth Screen */
        .auth-screen {
            display: flex;
            height: 100%;
            background: linear-gradient(135deg, #075E54, #128C7E);
        }

        .auth-left {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 4rem;
            opacity: 0.2;
        }

        .auth-right {
            width: 450px;
            background: white;
            padding: 40px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            overflow-y: auto;
            border-radius: 20px 0 0 20px;
        }

        .auth-logo {
            text-align: center;
            margin-bottom: 30px;
        }

        .auth-logo i {
            font-size: 60px;
            color: var(--whatsapp-green);
        }

        .auth-logo h2 {
            color: var(--whatsapp-dark-green);
            font-weight: 300;
        }

        .auth-tabs {
            display: flex;
            border-bottom: 2px solid #f0f0f0;
            margin-bottom: 25px;
        }

        .auth-tab {
            flex: 1;
            text-align: center;
            padding: 10px;
            cursor: pointer;
            color: #666;
            font-weight: 500;
            transition: all 0.3s;
        }

        .auth-tab.active {
            color: var(--whatsapp-teal);
            border-bottom: 2px solid var(--whatsapp-teal);
            margin-bottom: -2px;
        }

        .form-group {
            margin-bottom: 20px;
            position: relative;
        }

        .form-control {
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            padding: 12px 15px;
            font-size: 14px;
            transition: all 0.3s;
        }

        .form-control:focus {
            border-color: var(--whatsapp-teal);
            box-shadow: 0 0 0 3px rgba(18, 140, 126, 0.1);
            outline: none;
        }

        .form-control.error {
            border-color: #dc3545;
        }

        .error-message {
            color: #dc3545;
            font-size: 12px;
            margin-top: 5px;
            display: none;
        }

        .success-message {
            color: #28a745;
            font-size: 12px;
            margin-top: 5px;
            display: none;
        }

        .alert {
            border-radius: 8px;
            padding: 12px;
            margin-bottom: 20px;
            display: none;
        }

        .alert-danger {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .btn-success {
            background: var(--whatsapp-teal);
            border: none;
            padding: 12px;
            font-size: 16px;
            font-weight: 500;
            border-radius: 8px;
            transition: all 0.3s;
        }

        .btn-success:hover {
            background: var(--whatsapp-dark-green);
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(18, 140, 126, 0.3);
        }

        .loading-spinner {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 3px solid #f3f3f3;
            border-top: 3px solid var(--whatsapp-teal);
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin-right: 10px;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        /* Main App */
        .main-app {
            display: flex;
            height: 100%;
            background: #f0f2f5;
        }

        .sidebar {
            width: 400px;
            background: white;
            border-right: 1px solid #e9edef;
            display: flex;
            flex-direction: column;
        }

        .sidebar-header {
            background: var(--header-bg);
            color: white;
            padding: 15px 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .profile-thumb {
            width: 45px;
            height: 45px;
            border-radius: 50%;
            object-fit: cover;
            cursor: pointer;
            border: 2px solid rgba(255,255,255,0.3);
        }

        .app-title {
            font-size: 1.3rem;
            font-weight: 500;
        }

        .empty-state {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            height: 100%;
            color: #8696a0;
            text-align: center;
            padding: 20px;
        }

        .empty-state i {
            font-size: 5rem;
            margin-bottom: 20px;
            color: var(--whatsapp-teal);
        }

        /* Toast */
        .toast-notification {
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 15px 25px;
            border-radius: 8px;
            color: white;
            font-weight: 500;
            z-index: 9999;
            animation: slideIn 0.3s ease;
            box-shadow: 0 5px 20px rgba(0,0,0,0.2);
        }

        .toast-success { background: #28a745; }
        .toast-error { background: #dc3545; }
        .toast-info { background: #17a2b8; }

        @keyframes slideIn {
            from { transform: translateX(100%); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
        }

        @media (max-width: 768px) {
            .auth-right {
                width: 100%;
                border-radius: 0;
                padding: 20px;
            }
            
            .auth-left {
                display: none;
            }
            
            .sidebar {
                width: 100%;
            }
        }
    </style>
</head>
<body>
    <div class="app-wrapper">
        <div class="whatsapp-container">
            <!-- Auth Screen -->
            <div id="authScreen" class="auth-screen">
                <div class="auth-left">
                    <i class="fab fa-whatsapp"></i>
                </div>
                <div class="auth-right">
                    <div class="auth-logo">
                        <i class="fab fa-whatsapp"></i>
                        <h2>WhatsApp Clone</h2>
                        <p class="text-muted">Connect with friends and family</p>
                    </div>
                    
                    <div class="auth-tabs">
                        <div class="auth-tab active" onclick="switchTab('login')">Login</div>
                        <div class="auth-tab" onclick="switchTab('register')">Register</div>
                    </div>
                    
                    <!-- Login Form -->
                    <div id="loginForm">
                        <div id="loginAlert" class="alert alert-danger"></div>
                        
                        <div class="form-group">
                            <input type="text" class="form-control" id="loginIdentifier" placeholder="Username or Phone">
                            <div class="error-message" id="loginIdentifierError"></div>
                        </div>
                        
                        <div class="form-group">
                            <input type="password" class="form-control" id="loginPassword" placeholder="Password">
                            <div class="error-message" id="loginPasswordError"></div>
                        </div>
                        
                        <button class="btn btn-success w-100" onclick="handleLogin()" id="loginBtn">
                            <span>Login</span>
                        </button>
                    </div>
                    
                    <!-- Register Form -->
                    <div id="registerForm" style="display: none;">
                        <div id="registerAlert" class="alert alert-danger"></div>
                        <div id="registerSuccess" class="alert alert-success"></div>
                        
                        <div class="form-group">
                            <input type="text" class="form-control" id="regFullName" placeholder="Full Name">
                            <div class="error-message" id="regFullNameError"></div>
                        </div>
                        
                        <div class="form-group">
                            <input type="text" class="form-control" id="regUsername" placeholder="Username" onkeyup="checkAvailability('username')">
                            <div class="error-message" id="regUsernameError"></div>
                            <div class="success-message" id="regUsernameSuccess">Username is available</div>
                        </div>
                        
                        <div class="form-group">
                            <input type="tel" class="form-control" id="regPhone" placeholder="Phone Number" onkeyup="checkAvailability('phone')">
                            <div class="error-message" id="regPhoneError"></div>
                            <div class="success-message" id="regPhoneSuccess">Phone number is available</div>
                        </div>
                        
                        <div class="form-group">
                            <input type="password" class="form-control" id="regPassword" placeholder="Password (min. 6 characters)">
                            <div class="error-message" id="regPasswordError"></div>
                        </div>
                        
                        <button class="btn btn-success w-100" onclick="handleRegister()" id="registerBtn">
                            <span>Create Account</span>
                        </button>
                    </div>
                </div>
            </div>
            
            <!-- Main App -->
            <div id="mainApp" class="main-app" style="display: none;">
                <div class="sidebar">
                    <div class="sidebar-header">
                        <img id="profilePic" src="uploads/default.jpg" alt="Profile" class="profile-thumb">
                        <span class="app-title">WhatsApp</span>
                        <i class="fas fa-sign-out-alt" onclick="logout()" style="cursor: pointer;"></i>
                    </div>
                    
                    <div class="empty-state">
                        <i class="fab fa-whatsapp"></i>
                        <h4>Welcome to WhatsApp Clone</h4>
                        <p>You are logged in successfully!</p>
                        <p id="welcomeUser"></p>
                    </div>
                </div>
                
                <div class="chat-area empty-state">
                    <i class="fas fa-comments"></i>
                    <h4>No chat selected</h4>
                    <p>Choose a conversation to start messaging</p>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        // ========== GLOBAL VARIABLES ==========
        let currentUser = null;
        let availabilityTimeout = null;
        
        // ========== UI FUNCTIONS ==========
        function switchTab(tab) {
            document.querySelectorAll('.auth-tab').forEach(t => t.classList.remove('active'));
            if (tab === 'login') {
                document.querySelector('.auth-tab:first-child').classList.add('active');
                document.getElementById('loginForm').style.display = 'block';
                document.getElementById('registerForm').style.display = 'none';
                clearAlerts();
            } else {
                document.querySelector('.auth-tab:last-child').classList.add('active');
                document.getElementById('loginForm').style.display = 'none';
                document.getElementById('registerForm').style.display = 'block';
                clearAlerts();
            }
        }
        
        function clearAlerts() {
            document.getElementById('loginAlert').style.display = 'none';
            document.getElementById('loginAlert').innerHTML = '';
            document.getElementById('registerAlert').style.display = 'none';
            document.getElementById('registerAlert').innerHTML = '';
            document.getElementById('registerSuccess').style.display = 'none';
            document.getElementById('registerSuccess').innerHTML = '';
        }
        
        function clearFieldErrors() {
            document.querySelectorAll('.error-message').forEach(el => {
                el.style.display = 'none';
                el.innerHTML = '';
            });
            document.querySelectorAll('.success-message').forEach(el => {
                el.style.display = 'none';
            });
            document.querySelectorAll('.form-control').forEach(el => {
                el.classList.remove('error');
            });
        }
        
        function showFieldError(fieldId, message) {
            const field = document.getElementById(fieldId);
            const errorDiv = document.getElementById(fieldId + 'Error');
            if (field && errorDiv) {
                field.classList.add('error');
                errorDiv.innerHTML = message;
                errorDiv.style.display = 'block';
            }
        }
        
        function showFieldSuccess(fieldId) {
            const field = document.getElementById(fieldId);
            const successDiv = document.getElementById(fieldId + 'Success');
            if (field && successDiv) {
                field.classList.remove('error');
                successDiv.style.display = 'block';
            }
        }
        
        function showToast(message, type = 'info') {
            const toast = document.createElement('div');
            toast.className = `toast-notification toast-${type}`;
            toast.innerHTML = message;
            document.body.appendChild(toast);
            
            setTimeout(() => {
                toast.remove();
            }, 3000);
        }
        
        function setLoading(buttonId, isLoading) {
            const btn = document.getElementById(buttonId);
            if (isLoading) {
                btn.disabled = true;
                btn.innerHTML = '<span class="loading-spinner"></span> Please wait...';
            } else {
                btn.disabled = false;
                btn.innerHTML = btnId === 'loginBtn' ? '<span>Login</span>' : '<span>Create Account</span>';
            }
        }
        
        // ========== AVAILABILITY CHECK ==========
        function checkAvailability(field) {
            const value = field === 'username' ? 
                document.getElementById('regUsername').value.trim() : 
                document.getElementById('regPhone').value.trim();
            
            const fieldId = field === 'username' ? 'regUsername' : 'regPhone';
            
            if (availabilityTimeout) {
                clearTimeout(availabilityTimeout);
            }
            
            if (value.length < 3) {
                document.getElementById(fieldId + 'Error').style.display = 'none';
                document.getElementById(fieldId + 'Success').style.display = 'none';
                return;
            }
            
            availabilityTimeout = setTimeout(async () => {
                const formData = new FormData();
                formData.append('field', field);
                formData.append('value', value);
                
                try {
                    const response = await fetch('?action=api_check_availability', {
                        method: 'POST',
                        body: formData
                    });
                    const data = await response.json();
                    
                    if (data.available) {
                        showFieldSuccess(fieldId);
                    } else {
                        showFieldError(fieldId, data.message || `${field} is already taken`);
                    }
                } catch (error) {
                    console.error('Availability check failed:', error);
                }
            }, 500);
        }
        
        // ========== LOGIN FUNCTION ==========
        async function handleLogin() {
            clearFieldErrors();
            clearAlerts();
            
            const identifier = document.getElementById('loginIdentifier').value.trim();
            const password = document.getElementById('loginPassword').value;
            
            let hasError = false;
            
            if (!identifier) {
                showFieldError('loginIdentifier', 'Username or phone is required');
                hasError = true;
            }
            
            if (!password) {
                showFieldError('loginPassword', 'Password is required');
                hasError = true;
            }
            
            if (hasError) return;
            
            setLoading('loginBtn', true);
            
            const formData = new FormData();
            formData.append('identifier', identifier);
            formData.append('password', password);
            
            try {
                const response = await fetch('?action=api_login', {
                    method: 'POST',
                    body: formData
                });
                const data = await response.json();
                
                if (data.success) {
                    currentUser = data.user;
                    showToast('Login successful!', 'success');
                    
                    // Update UI
                    document.getElementById('authScreen').style.display = 'none';
                    document.getElementById('mainApp').style.display = 'flex';
                    document.getElementById('profilePic').src = 'uploads/' + (currentUser.profile_pic || 'default.jpg');
                    document.getElementById('welcomeUser').innerHTML = `Welcome, ${currentUser.full_name}!`;
                    
                    // Check session
                    checkSession();
                } else {
                    if (data.errors) {
                        if (data.errors.login) {
                            document.getElementById('loginAlert').innerHTML = data.errors.login;
                            document.getElementById('loginAlert').style.display = 'block';
                        } else {
                            Object.keys(data.errors).forEach(key => {
                                showFieldError('login' + key.charAt(0).toUpperCase() + key.slice(1), data.errors[key]);
                            });
                        }
                    } else if (data.error) {
                        document.getElementById('loginAlert').innerHTML = data.error;
                        document.getElementById('loginAlert').style.display = 'block';
                    }
                }
            } catch (error) {
                console.error('Login error:', error);
                showToast('Login failed. Please try again.', 'error');
            } finally {
                setLoading('loginBtn', false);
            }
        }
        
        // ========== REGISTER FUNCTION ==========
        async function handleRegister() {
            clearFieldErrors();
            clearAlerts();
            
            const fullName = document.getElementById('regFullName').value.trim();
            const username = document.getElementById('regUsername').value.trim();
            const phone = document.getElementById('regPhone').value.trim();
            const password = document.getElementById('regPassword').value;
            
            let hasError = false;
            
            // Client-side validation
            if (!fullName) {
                showFieldError('regFullName', 'Full name is required');
                hasError = true;
            } else if (fullName.length < 2) {
                showFieldError('regFullName', 'Full name must be at least 2 characters');
                hasError = true;
            }
            
            if (!username) {
                showFieldError('regUsername', 'Username is required');
                hasError = true;
            } else if (!/^[a-zA-Z0-9_]{3,20}$/.test(username)) {
                showFieldError('regUsername', 'Username must be 3-20 characters and can only contain letters, numbers, and underscores');
                hasError = true;
            }
            
            if (!phone) {
                showFieldError('regPhone', 'Phone number is required');
                hasError = true;
            } else if (!/^[0-9+\-\s()]{10,15}$/.test(phone)) {
                showFieldError('regPhone', 'Please enter a valid phone number');
                hasError = true;
            }
            
            if (!password) {
                showFieldError('regPassword', 'Password is required');
                hasError = true;
            } else if (password.length < 6) {
                showFieldError('regPassword', 'Password must be at least 6 characters');
                hasError = true;
            }
            
            if (hasError) return;
            
            setLoading('registerBtn', true);
            
            const formData = new FormData();
            formData.append('full_name', fullName);
            formData.append('username', username);
            formData.append('phone', phone);
            formData.append('password', password);
            
            try {
                const response = await fetch('?action=api_register', {
                    method: 'POST',
                    body: formData
                });
                const data = await response.json();
                
                if (data.success) {
                    document.getElementById('registerSuccess').innerHTML = data.message;
                    document.getElementById('registerSuccess').style.display = 'block';
                    
                    // Clear form
                    document.getElementById('regFullName').value = '';
                    document.getElementById('regUsername').value = '';
                    document.getElementById('regPhone').value = '';
                    document.getElementById('regPassword').value = '';
                    
                    // Switch to login after 2 seconds
                    setTimeout(() => {
                        switchTab('login');
                    }, 2000);
                    
                    showToast('Registration successful!', 'success');
                } else {
                    if (data.errors) {
                        Object.keys(data.errors).forEach(key => {
                            showFieldError('reg' + key.charAt(0).toUpperCase() + key.slice(1), data.errors[key]);
                        });
                    } else if (data.error) {
                        document.getElementById('registerAlert').innerHTML = data.error;
                        document.getElementById('registerAlert').style.display = 'block';
                    }
                }
            } catch (error) {
                console.error('Registration error:', error);
                showToast('Registration failed. Please try again.', 'error');
            } finally {
                setLoading('registerBtn', false);
            }
        }
        
        // ========== LOGOUT FUNCTION ==========
        async function logout() {
            try {
                await fetch('?action=api_logout');
                document.getElementById('authScreen').style.display = 'flex';
                document.getElementById('mainApp').style.display = 'none';
                showToast('Logged out successfully', 'success');
            } catch (error) {
                console.error('Logout error:', error);
                showToast('Logout failed', 'error');
            }
        }
        
        // ========== SESSION CHECK ==========
        async function checkSession() {
            try {
                const response = await fetch('?action=api_get_current_user');
                const data = await response.json();
                
                if (data.logged_in && data.user) {
                    currentUser = data.user;
                    document.getElementById('authScreen').style.display = 'none';
                    document.getElementById('mainApp').style.display = 'flex';
                    document.getElementById('profilePic').src = 'uploads/' + (currentUser.profile_pic || 'default.jpg');
                    document.getElementById('welcomeUser').innerHTML = `Welcome back, ${currentUser.full_name}!`;
                }
            } catch (error) {
                console.error('Session check error:', error);
            }
        }
        
        // ========== INITIALIZATION ==========
        window.onload = function() {
            checkSession();
        };
        
        // Handle enter key
        document.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                if (document.getElementById('loginForm').style.display !== 'none') {
                    handleLogin();
                } else {
                    handleRegister();
                }
            }
        });
    </script>
</body>
</html>
