<?php
session_start();

// Database configuration
$db_host = 'localhost';
$db_name = 'whatsapp_clone';
$db_user = 'root';
$db_pass = '';

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Create uploads directory
if (!file_exists('uploads')) {
    mkdir('uploads', 0777, true);
}

// Default profile picture
if (!file_exists('uploads/default.jpg')) {
    file_put_contents('uploads/default.jpg', '');
}

try {
    $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8mb4", $db_user, $db_pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    
} catch(PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

// Helper functions
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function getUserById($id) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$id]);
    return $stmt->fetch();
}

// API Routes
$action = $_GET['action'] ?? 'home';

// Login API
if ($action == 'api_login') {
    header('Content-Type: application/json');
    
    if ($_SERVER['REQUEST_METHOD'] != 'POST') {
        echo json_encode(['success' => false, 'error' => 'Invalid request']);
        exit;
    }
    
    $identifier = $_POST['identifier'] ?? '';
    $password = $_POST['password'] ?? '';
    
    $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? OR phone_number = ?");
    $stmt->execute([$identifier, $identifier]);
    $user = $stmt->fetch();
    
    if ($user && password_verify($password, $user['password'])) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        
        unset($user['password']);
        echo json_encode(['success' => true, 'user' => $user]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Invalid credentials']);
    }
    exit;
}

// Logout API
if ($action == 'api_logout') {
    session_destroy();
    echo json_encode(['success' => true]);
    exit;
}

// Get current user
if ($action == 'api_get_current_user') {
    header('Content-Type: application/json');
    
    if (!isLoggedIn()) {
        echo json_encode(['logged_in' => false]);
        exit;
    }
    
    $user = getUserById($_SESSION['user_id']);
    if ($user) {
        unset($user['password']);
        echo json_encode(['logged_in' => true, 'user' => $user]);
    } else {
        echo json_encode(['logged_in' => false]);
    }
    exit;
}

// Get WebSocket token
if ($action == 'api_get_ws_token') {
    header('Content-Type: application/json');
    
    if (!isLoggedIn()) {
        echo json_encode(['error' => 'Not logged in']);
        exit;
    }
    
    $userId = $_SESSION['user_id'];
    
    // Call your Node.js server to get token
    $ch = curl_init('http://localhost:3000/api/auth/token');
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['userId' => $userId]));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode == 200) {
        $data = json_decode($response, true);
        echo json_encode(['success' => true, 'token' => $data['token']]);
    } else {
        echo json_encode(['error' => 'Could not connect to chat server']);
    }
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Real-time WhatsApp Clone</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://cdn.socket.io/4.7.2/socket.io.min.js"></script>
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
            --sent-message: #DCF8C6;
            --received-message: #FFFFFF;
            --header-bg: #075E54;
            --bg-primary: #f0f2f5;
            --bg-secondary: #ffffff;
            --text-primary: #111b21;
            --text-secondary: #667781;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            height: 100vh;
            overflow: hidden;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }

        .app-wrapper {
            max-width: 1400px;
            margin: 20px auto;
            height: calc(100vh - 40px);
            background: var(--bg-secondary);
            border-radius: 20px;
            overflow: hidden;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
        }

        /* Auth Screen */
        .auth-screen {
            display: flex;
            height: 100%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }

        .auth-left {
            flex: 1;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            color: white;
            padding: 40px;
        }

        .auth-left i {
            font-size: 8rem;
            margin-bottom: 30px;
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.05); }
        }

        .auth-left h1 {
            font-size: 3rem;
            font-weight: 700;
            margin-bottom: 20px;
        }

        .auth-right {
            width: 450px;
            background: white;
            padding: 40px;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        .auth-tabs {
            display: flex;
            gap: 20px;
            margin-bottom: 30px;
            border-bottom: 2px solid #f0f0f0;
            padding-bottom: 10px;
        }

        .auth-tab {
            flex: 1;
            text-align: center;
            padding: 10px;
            cursor: pointer;
            color: #666;
            font-weight: 600;
            transition: all 0.3s;
        }

        .auth-tab.active {
            color: var(--whatsapp-teal);
            border-bottom: 2px solid var(--whatsapp-teal);
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-control {
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            padding: 12px 15px;
            font-size: 14px;
            transition: all 0.3s;
            width: 100%;
        }

        .form-control:focus {
            border-color: var(--whatsapp-teal);
            box-shadow: 0 0 0 3px rgba(18, 140, 126, 0.1);
            outline: none;
        }

        .btn-success {
            background: linear-gradient(135deg, var(--whatsapp-teal), var(--whatsapp-dark-green));
            border: none;
            padding: 12px;
            font-size: 16px;
            font-weight: 600;
            border-radius: 8px;
            color: white;
            width: 100%;
            cursor: pointer;
            transition: all 0.3s;
        }

        .btn-success:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(18, 140, 126, 0.3);
        }

        /* Main App */
        .main-app {
            display: flex;
            height: 100%;
            background: var(--bg-primary);
        }

        .sidebar {
            width: 350px;
            background: var(--bg-secondary);
            border-right: 1px solid #e9edef;
            display: flex;
            flex-direction: column;
        }

        .sidebar-header {
            background: var(--header-bg);
            color: white;
            padding: 10px 15px;
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
        }

        .connection-status {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            background: #ff4444;
            transition: background 0.3s;
        }

        .connection-status.connected {
            background: #4caf50;
            animation: pulse 2s infinite;
        }

        .search-box {
            padding: 10px;
            background: var(--bg-primary);
        }

        .search-box input {
            width: 100%;
            padding: 10px;
            border: none;
            border-radius: 20px;
            outline: none;
        }

        .chats-list {
            flex: 1;
            overflow-y: auto;
        }

        .chat-item {
            display: flex;
            align-items: center;
            padding: 12px 15px;
            cursor: pointer;
            transition: background 0.3s;
            border-bottom: 1px solid #e9edef;
        }

        .chat-item:hover {
            background: rgba(0,0,0,0.05);
        }

        .chat-item.active {
            background: rgba(18, 140, 126, 0.1);
        }

        .chat-avatar-container {
            position: relative;
            margin-right: 15px;
        }

        .chat-avatar {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            object-fit: cover;
        }

        .online-indicator {
            position: absolute;
            bottom: 2px;
            right: 2px;
            width: 12px;
            height: 12px;
            background: #4caf50;
            border-radius: 50%;
            border: 2px solid var(--bg-secondary);
            display: none;
        }

        .online-indicator.online {
            display: block;
        }

        .chat-info {
            flex: 1;
        }

        .chat-name {
            font-weight: 600;
            color: var(--text-primary);
        }

        .chat-last-message {
            font-size: 13px;
            color: var(--text-secondary);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .chat-time {
            font-size: 11px;
            color: var(--text-secondary);
        }

        .unread-badge {
            background: var(--whatsapp-green);
            color: white;
            border-radius: 50%;
            min-width: 20px;
            height: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 11px;
            margin-left: 10px;
        }

        .chat-area {
            flex: 1;
            display: flex;
            flex-direction: column;
            background: var(--bg-primary);
        }

        .chat-header {
            background: var(--bg-secondary);
            padding: 10px 15px;
            display: flex;
            align-items: center;
            border-bottom: 1px solid #e9edef;
        }

        .chat-header-avatar {
            width: 45px;
            height: 45px;
            border-radius: 50%;
            object-fit: cover;
            margin-right: 15px;
        }

        .chat-header-info {
            flex: 1;
        }

        .chat-header-info h5 {
            margin: 0;
            color: var(--text-primary);
        }

        .chat-header-info small {
            color: var(--text-secondary);
        }

        .messages-container {
            flex: 1;
            overflow-y: auto;
            padding: 20px;
            display: flex;
            flex-direction: column;
        }

        .message-wrapper {
            display: flex;
            margin-bottom: 10px;
        }

        .message-wrapper.sent {
            justify-content: flex-end;
        }

        .message-wrapper.received {
            justify-content: flex-start;
        }

        .message-bubble {
            max-width: 65%;
            padding: 10px 15px;
            border-radius: 10px;
            position: relative;
        }

        .sent .message-bubble {
            background: var(--sent-message);
            border-top-right-radius: 0;
        }

        .received .message-bubble {
            background: var(--received-message);
            border-top-left-radius: 0;
        }

        .message-time {
            font-size: 11px;
            color: var(--text-secondary);
            margin-top: 5px;
            text-align: right;
        }

        .typing-indicator {
            display: flex;
            gap: 3px;
            padding: 10px 15px;
            background: var(--bg-secondary);
            border-radius: 20px;
            width: fit-content;
            margin-bottom: 10px;
        }

        .typing-indicator span {
            width: 8px;
            height: 8px;
            background: var(--text-secondary);
            border-radius: 50%;
            animation: typing 1.4s infinite;
        }

        .typing-indicator span:nth-child(2) { animation-delay: 0.2s; }
        .typing-indicator span:nth-child(3) { animation-delay: 0.4s; }

        @keyframes typing {
            0%, 60%, 100% { transform: translateY(0); }
            30% { transform: translateY(-5px); }
        }

        .message-input-area {
            background: var(--bg-secondary);
            padding: 10px 15px;
            display: flex;
            gap: 10px;
        }

        .message-input-wrapper {
            flex: 1;
            background: var(--bg-primary);
            border-radius: 25px;
            padding: 10px 20px;
        }

        .message-input-wrapper input {
            width: 100%;
            border: none;
            outline: none;
            background: transparent;
            color: var(--text-primary);
        }

        .send-button {
            background: var(--whatsapp-teal);
            color: white;
            width: 45px;
            height: 45px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.3s;
        }

        .send-button:hover {
            transform: scale(1.1);
        }

        .empty-state {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            height: 100%;
            color: var(--text-secondary);
            text-align: center;
            padding: 20px;
        }

        .new-chat-btn {
            position: fixed;
            bottom: 30px;
            right: 30px;
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: var(--whatsapp-teal);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            cursor: pointer;
            box-shadow: 0 5px 15px rgba(0,0,0,0.3);
            transition: all 0.3s;
        }

        .new-chat-btn:hover {
            transform: scale(1.1);
        }

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
        }

        .toast-success { background: #4caf50; }
        .toast-error { background: #f44336; }
        .toast-info { background: #2196f3; }

        @keyframes slideIn {
            from { transform: translateX(100%); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
        }

        @media (max-width: 768px) {
            .app-wrapper {
                margin: 0;
                height: 100vh;
                border-radius: 0;
            }
            .auth-left {
                display: none;
            }
            .auth-right {
                width: 100%;
            }
            .sidebar {
                width: 100%;
                position: absolute;
                left: 0;
                top: 0;
                bottom: 0;
                z-index: 10;
                transform: translateX(0);
            }
            .sidebar.hidden {
                transform: translateX(-100%);
            }
            .chat-area {
                width: 100%;
            }
            .back-button {
                display: block;
            }
        }
    </style>
</head>
<body>
    <div class="app-wrapper">
        <!-- Auth Screen -->
        <div id="authScreen" class="auth-screen">
            <div class="auth-left">
                <i class="fab fa-whatsapp"></i>
                <h1>WhatsApp Clone</h1>
                <p>Real-time messaging with WebSockets</p>
            </div>
            <div class="auth-right">
                <div class="auth-tabs">
                    <div class="auth-tab active" onclick="switchTab('login')">Login</div>
                    <div class="auth-tab" onclick="switchTab('register')">Register</div>
                </div>
                
                <!-- Login Form -->
                <div id="loginForm">
                    <div class="form-group">
                        <input type="text" class="form-control" id="loginIdentifier" placeholder="Username or Phone">
                    </div>
                    <div class="form-group">
                        <input type="password" class="form-control" id="loginPassword" placeholder="Password">
                    </div>
                    <button class="btn-success" onclick="handleLogin()" id="loginBtn">
                        Login
                    </button>
                </div>
                
                <!-- Register Form -->
                <div id="registerForm" style="display: none;">
                    <div class="form-group">
                        <input type="text" class="form-control" id="regFullName" placeholder="Full Name">
                    </div>
                    <div class="form-group">
                        <input type="text" class="form-control" id="regUsername" placeholder="Username">
                    </div>
                    <div class="form-group">
                        <input type="text" class="form-control" id="regPhone" placeholder="Phone Number">
                    </div>
                    <div class="form-group">
                        <input type="password" class="form-control" id="regPassword" placeholder="Password">
                    </div>
                    <button class="btn-success" onclick="handleRegister()" id="registerBtn">
                        Register
                    </button>
                </div>
            </div>
        </div>
        
        <!-- Main App -->
        <div id="mainApp" class="main-app" style="display: none;">
            <!-- Sidebar -->
            <div class="sidebar" id="sidebar">
                <div class="sidebar-header">
                    <img id="profilePic" src="uploads/default.jpg" class="profile-thumb">
                    <span>WhatsApp</span>
                    <div class="connection-status" id="connectionStatus"></div>
                </div>
                
                <div class="search-box">
                    <input type="text" id="searchInput" placeholder="Search chats...">
                </div>
                
                <div id="chatsList" class="chats-list"></div>
            </div>
            
            <!-- Chat Area -->
            <div class="chat-area" id="chatArea">
                <div class="empty-state">
                    <i class="fab fa-whatsapp" style="font-size: 5rem;"></i>
                    <h3>Welcome to WhatsApp Clone</h3>
                    <p id="welcomeUser"></p>
                    <p>Select a chat to start messaging</p>
                </div>
            </div>
            
            <!-- New Chat Button -->
            <div class="new-chat-btn" onclick="showNewChat()">
                <i class="fas fa-plus"></i>
            </div>
        </div>
    </div>
    
    <!-- New Chat Modal -->
    <div class="modal fade" id="newChatModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">New Chat</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="text" class="form-control" id="userSearch" placeholder="Search users...">
                    <div id="searchResults" style="margin-top: 10px; max-height: 300px; overflow-y: auto;"></div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Global variables
        let currentUser = null;
        let currentChat = null;
        let socket = null;
        let socketConnected = false;
        let typingTimeout = null;
        let chats = [];
        let messages = [];
        let newChatModal = null;
        
        // Initialize
        window.onload = function() {
            checkSession();
            newChatModal = new bootstrap.Modal(document.getElementById('newChatModal'));
        };
        
        // Auth functions
        function switchTab(tab) {
            document.querySelectorAll('.auth-tab').forEach(t => t.classList.remove('active'));
            if (tab === 'login') {
                document.querySelector('.auth-tab:first-child').classList.add('active');
                document.getElementById('loginForm').style.display = 'block';
                document.getElementById('registerForm').style.display = 'none';
            } else {
                document.querySelector('.auth-tab:last-child').classList.add('active');
                document.getElementById('loginForm').style.display = 'none';
                document.getElementById('registerForm').style.display = 'block';
            }
        }
        
        async function handleLogin() {
            const identifier = document.getElementById('loginIdentifier').value;
            const password = document.getElementById('loginPassword').value;
            
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
                    document.getElementById('authScreen').style.display = 'none';
                    document.getElementById('mainApp').style.display = 'flex';
                    document.getElementById('profilePic').src = 'uploads/' + (currentUser.profile_pic || 'default.jpg');
                    document.getElementById('welcomeUser').innerText = `Welcome, ${currentUser.full_name}!`;
                    
                    await connectWebSocket();
                    loadChats();
                } else {
                    alert(data.error || 'Login failed');
                }
            } catch (error) {
                console.error('Login error:', error);
                alert('Login failed');
            }
        }
        
        async function handleRegister() {
            const fullName = document.getElementById('regFullName').value;
            const username = document.getElementById('regUsername').value;
            const phone = document.getElementById('regPhone').value;
            const password = document.getElementById('regPassword').value;
            
            // You'll need to implement the register API endpoint
            alert('Registration - API endpoint needed');
        }
        
        async function checkSession() {
            try {
                const response = await fetch('?action=api_get_current_user');
                const data = await response.json();
                
                if (data.logged_in && data.user) {
                    currentUser = data.user;
                    document.getElementById('authScreen').style.display = 'none';
                    document.getElementById('mainApp').style.display = 'flex';
                    document.getElementById('profilePic').src = 'uploads/' + (currentUser.profile_pic || 'default.jpg');
                    document.getElementById('welcomeUser').innerText = `Welcome back, ${currentUser.full_name}!`;
                    
                    await connectWebSocket();
                    loadChats();
                }
            } catch (error) {
                console.error('Session check error:', error);
            }
        }
        
        // WebSocket functions
        async function connectWebSocket() {
            try {
                const response = await fetch('?action=api_get_ws_token');
                const data = await response.json();
                
                if (data.error) {
                    console.error('Failed to get token:', data.error);
                    return;
                }
                
                socket = io('http://localhost:3000', {
                    auth: { token: data.token },
                    transports: ['websocket']
                });
                
                socket.on('connect', () => {
                    console.log('Connected to WebSocket');
                    socketConnected = true;
                    document.getElementById('connectionStatus').classList.add('connected');
                });
                
                socket.on('disconnect', () => {
                    console.log('Disconnected from WebSocket');
                    socketConnected = false;
                    document.getElementById('connectionStatus').classList.remove('connected');
                });
                
                socket.on('message-sent', (data) => {
                    if (data.success) {
                        messages.push(data.message);
                        if (currentChat && currentChat.id === data.chatId) {
                            renderMessages();
                        }
                    }
                });
                
                socket.on('new-message', (data) => {
                    if (currentChat && currentChat.id === data.chatId) {
                        messages.push(data.message);
                        renderMessages();
                        
                        // Mark as read
                        socket.emit('mark-read', {
                            chatId: currentChat.id,
                            messageIds: [data.message.id]
                        });
                    } else {
                        // Update unread count
                        const chat = chats.find(c => c.id === data.chatId);
                        if (chat) {
                            chat.unread_count = (chat.unread_count || 0) + 1;
                            renderChats();
                        }
                    }
                    
                    // Update chat list
                    updateChatLastMessage(data.chatId, data.message);
                });
                
                socket.on('user-typing', (data) => {
                    if (currentChat && currentChat.id === data.chatId) {
                        const typingDiv = document.getElementById('typingIndicator');
                        if (data.isTyping) {
                            if (!typingDiv) {
                                const indicator = document.createElement('div');
                                indicator.id = 'typingIndicator';
                                indicator.className = 'typing-indicator';
                                indicator.innerHTML = '<span></span><span></span><span></span>';
                                document.getElementById('messagesContainer').appendChild(indicator);
                            }
                        } else {
                            if (typingDiv) typingDiv.remove();
                        }
                    }
                });
                
                socket.on('user-status', (data) => {
                    // Update user status in UI
                    updateUserStatus(data.userId, data.status);
                });
                
                socket.on('chat-updated', () => {
                    loadChats();
                });
                
            } catch (error) {
                console.error('WebSocket connection error:', error);
            }
        }
        
        // Chat functions
        async function loadChats() {
            if (!socket || !socketConnected) return;
            
            socket.emit('get-chats', {}, (response) => {
                if (response.success) {
                    chats = response.chats;
                    renderChats();
                }
            });
        }
        
        function renderChats() {
            const container = document.getElementById('chatsList');
            
            if (!chats || chats.length === 0) {
                container.innerHTML = '<div class="empty-state">No chats yet</div>';
                return;
            }
            
            container.innerHTML = '';
            chats.forEach(chat => {
                const chatItem = document.createElement('div');
                chatItem.className = `chat-item ${currentChat && currentChat.id === chat.id ? 'active' : ''}`;
                chatItem.onclick = () => selectChat(chat);
                
                const lastMessage = chat.last_message ? 
                    (chat.last_sender_id === currentUser.id ? 'You: ' : '') + chat.last_message : 'No messages';
                
                chatItem.innerHTML = `
                    <div class="chat-avatar-container">
                        <img src="uploads/${chat.partner.pic || 'default.jpg'}" class="chat-avatar">
                        <span class="online-indicator ${chat.partner.status === 'online' ? 'online' : ''}"></span>
                    </div>
                    <div class="chat-info">
                        <div style="display: flex; justify-content: space-between;">
                            <span class="chat-name">${escapeHtml(chat.partner.name)}</span>
                            <span class="chat-time">${chat.last_message_time || ''}</span>
                        </div>
                        <div style="display: flex; justify-content: space-between;">
                            <span class="chat-last-message">${escapeHtml(lastMessage)}</span>
                            ${chat.unread_count > 0 ? `<span class="unread-badge">${chat.unread_count}</span>` : ''}
                        </div>
                    </div>
                `;
                
                container.appendChild(chatItem);
            });
        }
        
        function selectChat(chat) {
            currentChat = chat;
            
            // Load messages
            socket.emit('get-messages', { chatId: chat.id }, (response) => {
                if (response.success) {
                    messages = response.messages;
                    renderChatView();
                }
            });
        }
        
        function renderChatView() {
            document.getElementById('chatArea').innerHTML = `
                <div class="chat-header">
                    <button class="back-button" onclick="showChatList()" style="display: none;">←</button>
                    <img src="uploads/${currentChat.partner.pic || 'default.jpg'}" class="chat-header-avatar">
                    <div class="chat-header-info">
                        <h5>${escapeHtml(currentChat.partner.name)}</h5>
                        <small id="chatStatus">${currentChat.partner.status === 'online' ? '🟢 online' : '○ offline'}</small>
                    </div>
                </div>
                <div class="messages-container" id="messagesContainer"></div>
                <div class="message-input-area">
                    <div class="message-input-wrapper">
                        <input type="text" id="messageInput" placeholder="Type a message" onkeypress="handleKeyPress(event)">
                    </div>
                    <div class="send-button" onclick="sendMessage()">
                        <i class="fas fa-paper-plane"></i>
                    </div>
                </div>
            `;
            
            renderMessages();
        }
        
        function renderMessages() {
            const container = document.getElementById('messagesContainer');
            if (!container) return;
            
            container.innerHTML = '';
            
            messages.forEach(msg => {
                const isSent = msg.sender_id == currentUser.id;
                const time = new Date(msg.created_at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
                
                const messageDiv = document.createElement('div');
                messageDiv.className = `message-wrapper ${isSent ? 'sent' : 'received'}`;
                messageDiv.innerHTML = `
                    <div class="message-bubble">
                        <div>${escapeHtml(msg.message)}</div>
                        <div class="message-time">${time}</div>
                    </div>
                `;
                
                container.appendChild(messageDiv);
            });
            
            container.scrollTop = container.scrollHeight;
        }
        
        function sendMessage() {
            const input = document.getElementById('messageInput');
            const message = input.value.trim();
            
            if (!message || !currentChat || !socket || !socketConnected) {
                alert('Cannot send message');
                return;
            }
            
            socket.emit('send-message', {
                chatId: currentChat.id,
                message: message
            }, (response) => {
                if (response.error) {
                    alert(response.error);
                } else {
                    input.value = '';
                }
            });
        }
        
        function handleKeyPress(e) {
            if (e.key === 'Enter') {
                sendMessage();
                return;
            }
            
            // Typing indicator
            if (typingTimeout) clearTimeout(typingTimeout);
            
            socket.emit('typing', {
                chatId: currentChat.id,
                isTyping: true
            });
            
            typingTimeout = setTimeout(() => {
                socket.emit('typing', {
                    chatId: currentChat.id,
                    isTyping: false
                });
            }, 1000);
        }
        
        function showChatList() {
            document.getElementById('sidebar').classList.remove('hidden');
        }
        
        function updateChatLastMessage(chatId, message) {
            const chat = chats.find(c => c.id === chatId);
            if (chat) {
                chat.last_message = message.message;
                chat.last_message_time = formatTime(message.created_at);
                chat.last_sender_id = message.sender_id;
                renderChats();
            }
        }
        
        function updateUserStatus(userId, status) {
            chats.forEach(chat => {
                if (chat.partner.id === userId) {
                    chat.partner.status = status;
                }
            });
            renderChats();
            
            if (currentChat && currentChat.partner.id === userId) {
                const statusElement = document.getElementById('chatStatus');
                if (statusElement) {
                    statusElement.innerHTML = status === 'online' ? '🟢 online' : '○ offline';
                }
            }
        }
        
        function formatTime(datetime) {
            if (!datetime) return '';
            const date = new Date(datetime);
            const now = new Date();
            const diff = (now - date) / 1000;
            
            if (diff < 60) return 'just now';
            if (diff < 3600) return Math.floor(diff / 60) + 'm';
            if (diff < 86400) return Math.floor(diff / 3600) + 'h';
            return date.toLocaleDateString();
        }
        
        function showNewChat() {
            newChatModal.show();
            document.getElementById('userSearch').value = '';
            document.getElementById('searchResults').innerHTML = '';
        }
        
        function escapeHtml(unsafe) {
            if (!unsafe) return '';
            return unsafe
                .replace(/&/g, "&amp;")
                .replace(/</g, "&lt;")
                .replace(/>/g, "&gt;")
                .replace(/"/g, "&quot;")
                .replace(/'/g, "&#039;");
        }
    </script>
</body>
</html>
