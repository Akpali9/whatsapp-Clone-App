<?php
// ============================================
// WHATSAPP CLONE - REAL-TIME CHAT
// ============================================

session_start();

// Database configuration
$db_host = 'localhost';
$db_name = 'whatsapp_clone';
$db_user = 'root';
$db_pass = '';

// WebSocket server URL
$ws_server = 'http://localhost:3000'; // Node.js server

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Create uploads directory
$upload_dirs = [
    'uploads', 'uploads/status', 'uploads/files', 'uploads/wallpapers', 
    'uploads/themes', 'uploads/stories'
];
foreach ($upload_dirs as $dir) {
    if (!file_exists($dir)) {
        mkdir($dir, 0777, true);
    }
}

// Default profile picture
if (!file_exists('uploads/default.jpg')) {
    file_put_contents('uploads/default.jpg', '');
}

try {
    $pdo = new PDO("mysql:host=$db_host", $db_user, $db_pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `$db_name` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    
    $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8mb4", $db_user, $db_pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    
    // Create tables (same as before)
    // ... (keep all table creation code from previous version)
    
} catch(PDOException $e) {
    die("Database Error: " . $e->getMessage());
}

// ============================================
// API ROUTES (keep all existing API routes)
// ============================================

// Add new API endpoint to get WebSocket token
if ($action == 'api_get_ws_token') {
    header('Content-Type: application/json');
    
    if (!isLoggedIn()) {
        echo json_encode(['error' => 'Not logged in']);
        exit;
    }
    
    $userId = $_SESSION['user_id'];
    
    // Make request to Node.js server to get token
    $ch = curl_init("$ws_server/api/auth/token");
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['userId' => $userId]));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode == 200) {
        $data = json_decode($response, true);
        echo json_encode(['success' => true, 'token' => $data['token']]);
    } else {
        echo json_encode(['error' => 'Failed to get token']);
    }
    exit;
}

// Keep all other API routes from previous version...
// (Include all the API endpoints we had before)

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>WhatsApp Clone - Real-time Chat</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://cdn.socket.io/4.7.2/socket.io.min.js"></script>
    <style>
        /* Keep all existing styles */
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
            --border-color: #e9edef;
        }

        [data-theme="dark"] {
            --bg-primary: #111b21;
            --bg-secondary: #202c33;
            --text-primary: #e9edef;
            --text-secondary: #8696a0;
            --border-color: #2a3942;
            --sent-message: #005c4b;
            --received-message: #202c33;
            --header-bg: #1f2c33;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            height: 100vh;
            overflow: hidden;
            margin: 0;
            padding: 0;
            background: #0f2027;
        }

        .app-wrapper {
            max-width: 1400px;
            margin: 10px auto;
            height: calc(100vh - 20px);
            background: var(--bg-secondary);
            border-radius: 20px;
            overflow: hidden;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
        }

        /* Typing indicator */
        .typing-indicator {
            display: flex;
            align-items: center;
            gap: 3px;
            padding: 5px 10px;
            background: var(--bg-secondary);
            border-radius: 20px;
            width: fit-content;
            margin-top: 5px;
        }

        .typing-indicator span {
            width: 8px;
            height: 8px;
            background: var(--text-secondary);
            border-radius: 50%;
            animation: typing 1.4s infinite ease-in-out;
        }

        .typing-indicator span:nth-child(2) {
            animation-delay: 0.2s;
        }

        .typing-indicator span:nth-child(3) {
            animation-delay: 0.4s;
        }

        @keyframes typing {
            0%, 60%, 100% { transform: translateY(0); }
            30% { transform: translateY(-5px); }
        }

        /* Message status indicators */
        .message-status {
            font-size: 12px;
            margin-left: 5px;
        }

        .status-sent { color: #8696a0; }
        .status-delivered { color: #8696a0; }
        .status-read { color: #4fc3f7; }

        /* Online status */
        .online-indicator {
            position: absolute;
            bottom: 2px;
            right: 2px;
            width: 12px;
            height: 12px;
            background: #4caf50;
            border-radius: 50%;
            border: 2px solid var(--bg-secondary);
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0% { box-shadow: 0 0 0 0 rgba(76, 175, 80, 0.7); }
            70% { box-shadow: 0 0 0 5px rgba(76, 175, 80, 0); }
            100% { box-shadow: 0 0 0 0 rgba(76, 175, 80, 0); }
        }

        /* Keep all other styles from previous version */
        /* ... (include all CSS from previous version) ... */
    </style>
</head>
<body data-theme="light">
    <div class="app-wrapper">
        <!-- Auth Screen (same as before) -->
        <div id="authScreen" class="auth-screen">
            <!-- ... (keep auth screen HTML) ... -->
        </div>
        
        <!-- Main App with Real-time Features -->
        <div id="mainApp" class="main-app" style="display: none;">
            <!-- Sidebar -->
            <div class="sidebar" id="sidebar">
                <div class="sidebar-header">
                    <img id="profilePic" src="uploads/default.jpg" alt="Profile" class="profile-thumb" onclick="showProfileSettings()">
                    <span class="app-title">WhatsApp</span>
                    <div class="header-icons">
                        <div class="online-status-dot" id="connectionStatus" title="Connected"></div>
                        <i class="fas fa-circle" onclick="switchMainTab('status')" title="Status"></i>
                        <i class="fas fa-cog" onclick="showSettings()" title="Settings"></i>
                        <i class="fas fa-sign-out-alt" onclick="logout()" title="Logout"></i>
                    </div>
                </div>
                
                <div class="sidebar-tabs">
                    <div class="sidebar-tab active" onclick="switchMainTab('chats')">
                        <i class="fas fa-comment"></i> Chats
                    </div>
                    <div class="sidebar-tab" onclick="switchMainTab('status')">
                        <i class="fas fa-circle"></i> Status
                    </div>
                    <div class="sidebar-tab" onclick="switchMainTab('calls')">
                        <i class="fas fa-phone"></i> Calls
                    </div>
                </div>
                
                <div class="search-box">
                    <input type="text" id="searchInput" placeholder="Search..." onkeyup="handleSearch(this.value)">
                </div>
                
                <!-- Chats List -->
                <div id="chatsList" class="chats-list"></div>
                
                <!-- Status List -->
                <div id="statusList" class="chats-list" style="display: none;"></div>
                
                <!-- Calls List -->
                <div id="callsList" class="chats-list" style="display: none;"></div>
            </div>
            
            <!-- Chat Area with Real-time Features -->
            <div class="chat-area" id="chatArea">
                <div class="empty-state">
                    <i class="fab fa-whatsapp"></i>
                    <h4>Real-time WhatsApp Clone</h4>
                    <p id="welcomeUser"></p>
                    <p>Select a chat to start messaging</p>
                    <small class="text-muted" id="connectionStatusText">Connecting...</small>
                </div>
            </div>
            
            <!-- New Chat Button -->
            <div class="new-chat-btn" onclick="showNewChatModal()">
                <i class="fas fa-plus"></i>
            </div>
        </div>
    </div>
    
    <!-- Keep all modals from previous version -->
    <!-- New Chat Modal, Profile Modal, Status Modal, etc. -->
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // ========== GLOBAL VARIABLES ==========
        let currentUser = null;
        let currentChat = null;
        let currentStatusGroup = null;
        let chats = [];
        let messages = [];
        let socket = null;
        let socketConnected = false;
        let typingTimeout = null;
        let messageQueue = [];
        let unreadMessages = new Map();
        
        // ========== WEBSOCKET CONNECTION ==========
        async function connectWebSocket() {
            try {
                // Get token from server
                const response = await fetch('?action=api_get_ws_token');
                const data = await response.json();
                
                if (data.error) {
                    showToast('Failed to connect to chat server', 'error');
                    updateConnectionStatus(false);
                    return;
                }
                
                // Connect to WebSocket server
                socket = io('http://localhost:3000', {
                    auth: {
                        token: data.token
                    },
                    transports: ['websocket'],
                    reconnection: true,
                    reconnectionDelay: 1000,
                    reconnectionDelayMax: 5000,
                    reconnectionAttempts: 5
                });
                
                // Connection events
                socket.on('connect', () => {
                    console.log('Connected to WebSocket server');
                    socketConnected = true;
                    updateConnectionStatus(true);
                    showToast('Connected to chat server', 'success');
                    
                    // Send any queued messages
                    sendQueuedMessages();
                });
                
                socket.on('disconnect', () => {
                    console.log('Disconnected from WebSocket server');
                    socketConnected = false;
                    updateConnectionStatus(false);
                    showToast('Disconnected from chat server', 'error');
                });
                
                socket.on('connect_error', (error) => {
                    console.error('Connection error:', error);
                    socketConnected = false;
                    updateConnectionStatus(false);
                });
                
                // ========== REAL-TIME MESSAGE HANDLERS ==========
                
                // Message sent confirmation
                socket.on('message-sent', (data) => {
                    if (data.success) {
                        // Add message to current chat
                        messages.push(data.message);
                        if (currentChat && currentChat.id === data.chatId) {
                            renderMessages();
                        }
                        
                        // Update chat list
                        updateChatLastMessage(data.chatId, data.message);
                    }
                });
                
                // New message received
                socket.on('new-message', (data) => {
                    const { chatId, message } = data;
                    
                    // Add to messages if it's current chat
                    if (currentChat && currentChat.id === chatId) {
                        messages.push(message);
                        renderMessages();
                        
                        // Mark as read immediately
                        markMessagesAsRead([message.id]);
                    } else {
                        // Increment unread count
                        const chat = chats.find(c => c.id === chatId);
                        if (chat) {
                            chat.unread_count = (chat.unread_count || 0) + 1;
                            renderChats();
                            
                            // Show notification
                            showNotification(chat.partner.name, message.message);
                        }
                    }
                    
                    // Update chat list
                    updateChatLastMessage(chatId, message);
                });
                
                // Typing indicator
                socket.on('user-typing', (data) => {
                    const { chatId, userId, isTyping } = data;
                    
                    if (currentChat && currentChat.id === chatId) {
                        const typingDiv = document.getElementById('typingIndicator');
                        if (isTyping) {
                            if (!typingDiv) {
                                const indicator = document.createElement('div');
                                indicator.id = 'typingIndicator';
                                indicator.className = 'typing-indicator';
                                indicator.innerHTML = '<span></span><span></span><span></span>';
                                document.getElementById('messagesContainer').appendChild(indicator);
                            }
                        } else {
                            if (typingDiv) {
                                typingDiv.remove();
                            }
                        }
                    }
                });
                
                // Messages read
                socket.on('messages-read', (data) => {
                    const { chatId, messageIds } = data;
                    
                    if (currentChat && currentChat.id === chatId) {
                        // Update message status in UI
                        messages.forEach(msg => {
                            if (messageIds.includes(msg.id)) {
                                msg.is_read = 1;
                            }
                        });
                        renderMessages();
                    }
                });
                
                // Chat updated (for list refresh)
                socket.on('chat-updated', (data) => {
                    loadChats();
                });
                
                // New chat created
                socket.on('new-chat', (data) => {
                    loadChats();
                });
                
                // Message deleted
                socket.on('message-deleted', (data) => {
                    const { messageId, chatId } = data;
                    
                    if (currentChat && currentChat.id === chatId) {
                        messages = messages.filter(m => m.id !== messageId);
                        renderMessages();
                    }
                });
                
                // Message reaction
                socket.on('message-reaction', (data) => {
                    const { messageId, userId, reaction } = data;
                    // Handle reaction display
                });
                
                // Contact status update
                socket.on('contact-status', (data) => {
                    const { userId, status, lastSeen } = data;
                    
                    // Update chat list
                    chats.forEach(chat => {
                        if (chat.partner.id === userId) {
                            chat.partner.status = status;
                            chat.partner.lastSeen = lastSeen;
                        }
                    });
                    renderChats();
                    
                    // Update current chat header if open
                    if (currentChat && currentChat.partner.id === userId) {
                        updateChatHeader();
                    }
                });
                
            } catch (error) {
                console.error('WebSocket connection error:', error);
                updateConnectionStatus(false);
            }
        }
        
        // Update connection status in UI
        function updateConnectionStatus(connected) {
            const statusDot = document.getElementById('connectionStatus');
            const statusText = document.getElementById('connectionStatusText');
            
            if (statusDot) {
                statusDot.style.background = connected ? '#4caf50' : '#ff4444';
                statusDot.style.width = '10px';
                statusDot.style.height = '10px';
                statusDot.style.borderRadius = '50%';
                statusDot.style.marginRight = '10px';
            }
            
            if (statusText) {
                statusText.textContent = connected ? 'Connected' : 'Disconnected - Reconnecting...';
                statusText.style.color = connected ? '#4caf50' : '#ff4444';
            }
        }
        
        // Queue message when offline
        function queueMessage(chatId, message) {
            messageQueue.push({
                chatId,
                message,
                timestamp: Date.now()
            });
            showToast('Message queued - will send when connected', 'info');
        }
        
        // Send queued messages
        function sendQueuedMessages() {
            if (!socketConnected || messageQueue.length === 0) return;
            
            messageQueue.forEach(async (queued) => {
                await sendMessageWithSocket(queued.chatId, queued.message);
            });
            messageQueue = [];
        }
        
        // Send message via socket
        function sendMessageWithSocket(chatId, message, replyToId = null) {
            return new Promise((resolve, reject) => {
                if (!socketConnected) {
                    queueMessage(chatId, message);
                    resolve({ queued: true });
                    return;
                }
                
                socket.emit('send-message', {
                    chatId,
                    message,
                    replyToId
                }, (response) => {
                    if (response && response.error) {
                        reject(response.error);
                    } else {
                        resolve(response);
                    }
                });
            });
        }
        
        // Mark messages as read
        function markMessagesAsRead(messageIds) {
            if (!socketConnected || !currentChat) return;
            
            socket.emit('mark-read', {
                chatId: currentChat.id,
                messageIds
            });
        }
        
        // Send typing indicator
        function sendTypingIndicator(isTyping) {
            if (!socketConnected || !currentChat) return;
            
            socket.emit('typing', {
                chatId: currentChat.id,
                isTyping
            });
        }
        
        // Show browser notification
        function showNotification(title, body) {
            if (!("Notification" in window)) return;
            
            if (Notification.permission === "granted") {
                new Notification(title, { body });
            } else if (Notification.permission !== "denied") {
                Notification.requestPermission().then(permission => {
                    if (permission === "granted") {
                        new Notification(title, { body });
                    }
                });
            }
        }
        
        // ========== OVERRIDE EXISTING FUNCTIONS FOR REAL-TIME ==========
        
        // Override sendMessage function
        async function sendMessage() {
            const input = document.getElementById('messageInput');
            const message = input.value.trim();
            
            if (!message || !currentChat) return;
            
            // Optimistically add message to UI
            const tempMessage = {
                id: 'temp-' + Date.now(),
                chat_id: currentChat.id,
                sender_id: currentUser.id,
                message: message,
                created_at: new Date().toISOString(),
                is_read: 0,
                is_delivered: 0,
                full_name: currentUser.full_name,
                profile_pic: currentUser.profile_pic
            };
            
            messages.push(tempMessage);
            renderMessages();
            input.value = '';
            
            // Send via WebSocket
            try {
                await sendMessageWithSocket(currentChat.id, message);
            } catch (error) {
                console.error('Error sending message:', error);
                showToast('Failed to send message', 'error');
                // Remove temp message on failure
                messages = messages.filter(m => m.id !== tempMessage.id);
                renderMessages();
            }
        }
        
        // Override handleKeyPress to add typing indicator
        function handleKeyPress(e) {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                sendMessage();
            }
            
            // Typing indicator
            if (typingTimeout) clearTimeout(typingTimeout);
            sendTypingIndicator(true);
            
            typingTimeout = setTimeout(() => {
                sendTypingIndicator(false);
            }, 1000);
        }
        
        // Override selectChat to mark messages as read
        async function selectChat(chat) {
            currentChat = chat;
            
            // Mark unread messages as read
            const unreadMessageIds = messages
                .filter(m => !m.is_read && m.sender_id !== currentUser.id)
                .map(m => m.id);
            
            if (unreadMessageIds.length > 0) {
                markMessagesAsRead(unreadMessageIds);
            }
            
            // Rest of existing selectChat function
            renderChats();
            
            document.getElementById('chatArea').innerHTML = `
                <div class="chat-header">
                    <button class="back-button" onclick="showChatList()">
                        <i class="fas fa-arrow-left"></i>
                    </button>
                    <img src="uploads/${chat.partner.pic || 'default.jpg'}" class="chat-header-avatar">
                    <div class="chat-header-info">
                        <h5>${escapeHtml(chat.partner.name)}</h5>
                        <small id="chatStatus">${chat.partner.status === 'online' ? '🟢 online' : '○ offline'}</small>
                    </div>
                    <div class="chat-header-actions">
                        <i class="fas fa-phone" onclick="startCall('audio')" title="Audio Call"></i>
                        <i class="fas fa-video" onclick="startCall('video')" title="Video Call"></i>
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
            
            loadMessages(chat.id);
            
            if (window.innerWidth <= 768) {
                document.getElementById('sidebar').classList.add('hidden');
            }
        }
        
        // Update chat header with real-time status
        function updateChatHeader() {
            const statusElement = document.getElementById('chatStatus');
            if (statusElement && currentChat) {
                statusElement.innerHTML = currentChat.partner.status === 'online' ? 
                    '🟢 online' : '○ offline';
            }
        }
        
        // Update chat list with new message
        function updateChatLastMessage(chatId, message) {
            const chat = chats.find(c => c.id === chatId);
            if (chat) {
                chat.last_message = message.message;
                chat.last_message_time = formatTime(message.created_at);
                chat.last_sender_id = message.sender_id;
                renderChats();
            }
        }
        
        // Override renderMessages to add status indicators
        function renderMessages() {
            const container = document.getElementById('messagesContainer');
            if (!container) return;
            
            container.innerHTML = '';
            
            messages.forEach(msg => {
                if (msg.deleted_for_everyone) return;
                if (msg.deleted_for_me && msg.sender_id !== currentUser.id) return;
                
                const isSent = msg.sender_id == currentUser.id;
                const time = new Date(msg.created_at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
                
                let statusIcon = '';
                let statusClass = '';
                
                if (isSent) {
                    if (msg.is_read) {
                        statusIcon = '<i class="fas fa-check-double status-read"></i>';
                        statusClass = 'status-read';
                    } else if (msg.is_delivered) {
                        statusIcon = '<i class="fas fa-check-double status-delivered"></i>';
                        statusClass = 'status-delivered';
                    } else {
                        statusIcon = '<i class="fas fa-check status-sent"></i>';
                        statusClass = 'status-sent';
                    }
                }
                
                const messageDiv = document.createElement('div');
                messageDiv.className = `message-wrapper ${isSent ? 'sent' : 'received'}`;
                messageDiv.id = `message-${msg.id}`;
                
                messageDiv.innerHTML = `
                    <div class="message-bubble">
                        <div>${escapeHtml(msg.message)}</div>
                        <div class="message-time">
                            <span>${time}</span>
                            ${statusIcon ? `<span class="message-status ${statusClass}">${statusIcon}</span>` : ''}
                        </div>
                    </div>
                `;
                
                container.appendChild(messageDiv);
            });
            
            container.scrollTop = container.scrollHeight;
            
            // Mark visible messages as read
            if (currentChat && messages.length > 0) {
                const unreadIds = messages
                    .filter(m => !m.is_read && m.sender_id !== currentUser.id)
                    .map(m => m.id);
                
                if (unreadIds.length > 0) {
                    markMessagesAsRead(unreadIds);
                }
            }
        }
        
        // ========== INITIALIZATION ==========
        window.onload = function() {
            checkSession();
            
            // Request notification permission
            if ("Notification" in window) {
                Notification.requestPermission();
            }
        };
        
        // Override checkSession to connect WebSocket after login
        async function checkSession() {
            try {
                const response = await fetch('?action=api_get_current_user');
                const data = await response.json();
                
                if (data.logged_in && data.user) {
                    currentUser = data.user;
                    document.getElementById('authScreen').style.display = 'none';
                    document.getElementById('mainApp').style.display = 'flex';
                    document.getElementById('profilePic').src = 'uploads/' + (currentUser.profile_pic || 'default.jpg');
                    document.getElementById('myStatusPic').src = 'uploads/' + (currentUser.profile_pic || 'default.jpg');
                    document.getElementById('welcomeUser').innerHTML = `Welcome back, ${currentUser.full_name}!`;
                    
                    if (currentUser.theme) {
                        applyTheme(currentUser.theme);
                    }
                    
                    // Connect to WebSocket
                    await connectWebSocket();
                    
                    loadChats();
                    loadStatus();
                }
            } catch (error) {
                console.error('Session check error:', error);
            }
        }
        
        // Override handleLogin to connect WebSocket after login
        async function handleLogin() {
            clearFieldErrors();
            clearAlerts();
            
            const identifier = document.getElementById('loginIdentifier').value.trim();
            const password = document.getElementById('loginPassword').value;
            
            if (!identifier || !password) {
                showToast('Please fill in all fields', 'error');
                return;
            }
            
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
                    
                    document.getElementById('authScreen').style.display = 'none';
                    document.getElementById('mainApp').style.display = 'flex';
                    document.getElementById('profilePic').src = 'uploads/' + (currentUser.profile_pic || 'default.jpg');
                    document.getElementById('myStatusPic').src = 'uploads/' + (currentUser.profile_pic || 'default.jpg');
                    document.getElementById('welcomeUser').innerHTML = `Welcome, ${currentUser.full_name}!`;
                    
                    if (currentUser.theme) {
                        applyTheme(currentUser.theme);
                    }
                    
                    // Connect to WebSocket
                    await connectWebSocket();
                    
                    loadChats();
                    loadStatus();
                } else {
                    document.getElementById('loginAlert').innerHTML = data.error || 'Login failed';
                    document.getElementById('loginAlert').style.display = 'block';
                }
            } catch (error) {
                console.error('Login error:', error);
                showToast('Login failed. Please try again.', 'error');
            } finally {
                setLoading('loginBtn', false);
            }
        }
        
        // Override logout to disconnect WebSocket
        async function logout() {
            if (socket) {
                socket.disconnect();
            }
            
            try {
                await fetch('?action=api_logout');
                document.getElementById('authScreen').style.display = 'flex';
                document.getElementById('mainApp').style.display = 'none';
                currentChat = null;
                showToast('Logged out successfully', 'success');
            } catch (error) {
                console.error('Logout error:', error);
            }
        }
        
        // Keep all other existing functions (switchTab, switchMainTab, loadChats, loadStatus, etc.)
        // ... (include all functions from previous version) ...
        
        // ========== UTILITIES ==========
        function escapeHtml(unsafe) {
            if (!unsafe) return '';
            return unsafe
                .replace(/&/g, "&amp;")
                .replace(/</g, "&lt;")
                .replace(/>/g, "&gt;")
                .replace(/"/g, "&quot;")
                .replace(/'/g, "&#039;");
        }
        
        function formatTime(datetime) {
            const date = new Date(datetime);
            const now = new Date();
            const diff = (now - date) / 1000;
            
            if (diff < 60) return 'Just now';
            if (diff < 3600) return Math.floor(diff / 60) + ' min ago';
            if (diff < 86400) return Math.floor(diff / 3600) + ' hours ago';
            if (diff < 604800) return date.toLocaleDateString('en-US', { weekday: 'long' });
            return date.toLocaleDateString('en-US', { day: 'numeric', month: 'short' });
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
    </script>
</body>
</html>
