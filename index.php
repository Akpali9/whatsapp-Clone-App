<?php
// ============================================
// WHATSAPP CLONE - COMPLETE WITH CHAT INTEGRATION
// ============================================

session_start();

// ============================================
// DATABASE CONFIGURATION
// ============================================
$db_host = 'localhost';
$db_name = 'whatsapp_clone';
$db_user = 'root';
$db_pass = '';

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Create uploads directory
$upload_dirs = ['uploads', 'uploads/status', 'uploads/files', 'uploads/wallpapers'];
foreach ($upload_dirs as $dir) {
    if (!file_exists($dir)) {
        mkdir($dir, 0777, true);
    }
}

// Default profile picture
if (!file_exists('uploads/default.jpg')) {
    file_put_contents('uploads/default.jpg', '');
}

// ============================================
// DATABASE CONNECTION
// ============================================
try {
    // Connect to MySQL
    $pdo = new PDO("mysql:host=$db_host", $db_user, $db_pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Create database if not exists
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `$db_name` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    
    // Connect to the database
    $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8mb4", $db_user, $db_pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    
    // ============================================
    // CREATE TABLES
    // ============================================
    
    // Users table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS users (
            id INT PRIMARY KEY AUTO_INCREMENT,
            phone_number VARCHAR(20) UNIQUE NOT NULL,
            username VARCHAR(50) UNIQUE NOT NULL,
            full_name VARCHAR(100) NOT NULL,
            password VARCHAR(255) NOT NULL,
            about TEXT DEFAULT 'Hey there! I am using WhatsApp Clone',
            profile_pic VARCHAR(255) DEFAULT 'default.jpg',
            status ENUM('online', 'offline', 'away') DEFAULT 'offline',
            last_seen DATETIME DEFAULT CURRENT_TIMESTAMP,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            
            INDEX idx_status (status),
            INDEX idx_username (username),
            INDEX idx_phone (phone_number)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    
    // Chats table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS chats (
            id INT PRIMARY KEY AUTO_INCREMENT,
            user1_id INT NOT NULL,
            user2_id INT NOT NULL,
            last_message_id INT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            
            FOREIGN KEY (user1_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (user2_id) REFERENCES users(id) ON DELETE CASCADE,
            UNIQUE KEY unique_chat (user1_id, user2_id),
            
            INDEX idx_user1 (user1_id),
            INDEX idx_user2 (user2_id),
            INDEX idx_updated (updated_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    
    // Messages table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS messages (
            id INT PRIMARY KEY AUTO_INCREMENT,
            chat_id INT NOT NULL,
            sender_id INT NOT NULL,
            message TEXT NOT NULL,
            is_read BOOLEAN DEFAULT FALSE,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            
            FOREIGN KEY (chat_id) REFERENCES chats(id) ON DELETE CASCADE,
            FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE,
            
            INDEX idx_chat (chat_id),
            INDEX idx_created (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    
    // Contacts table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS contacts (
            user_id INT NOT NULL,
            contact_id INT NOT NULL,
            contact_name VARCHAR(100),
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            
            PRIMARY KEY (user_id, contact_id),
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (contact_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    
    // Create test users if none exist
    $stmt = $pdo->query("SELECT COUNT(*) FROM users");
    if ($stmt->fetchColumn() == 0) {
        // Create test users
        $password = password_hash('test123', PASSWORD_DEFAULT);
        
        $users = [
            ['+1234567890', 'john_doe', 'John Doe', $password],
            ['+1234567891', 'jane_smith', 'Jane Smith', $password],
            ['+1234567892', 'bob_wilson', 'Bob Wilson', $password],
            ['+1234567893', 'alice_brown', 'Alice Brown', $password]
        ];
        
        $stmt = $pdo->prepare("INSERT INTO users (phone_number, username, full_name, password) VALUES (?, ?, ?, ?)");
        foreach ($users as $user) {
            $stmt->execute($user);
        }
        
        // Get user IDs
        $userIds = $pdo->query("SELECT id FROM users")->fetchAll();
        
        // Create contacts
        $contactStmt = $pdo->prepare("INSERT INTO contacts (user_id, contact_id, contact_name) VALUES (?, ?, ?)");
        $chatStmt = $pdo->prepare("INSERT INTO chats (user1_id, user2_id) VALUES (?, ?)");
        
        // Add contacts and create chats
        for ($i = 0; $i < count($userIds); $i++) {
            for ($j = $i + 1; $j < count($userIds); $j++) {
                $contactStmt->execute([$userIds[$i]['id'], $userIds[$j]['id'], $userIds[$j]['full_name']]);
                $contactStmt->execute([$userIds[$j]['id'], $userIds[$i]['id'], $userIds[$i]['full_name']]);
                $chatStmt->execute([$userIds[$i]['id'], $userIds[$j]['id']]);
            }
        }
        
        // Add some sample messages
        $messageStmt = $pdo->prepare("INSERT INTO messages (chat_id, sender_id, message) VALUES (?, ?, ?)");
        $chats = $pdo->query("SELECT id, user1_id, user2_id FROM chats")->fetchAll();
        
        $sampleMessages = [
            "Hey! How are you?",
            "I'm good, thanks! How about you?",
            "Pretty good. Want to catch up later?",
            "Sure, that sounds great!",
            "What time works for you?",
            "How about 3 PM?",
            "Perfect! See you then.",
            "Can't wait!"
        ];
        
        foreach ($chats as $chat) {
            $messageStmt->execute([$chat['id'], $chat['user1_id'], $sampleMessages[array_rand($sampleMessages)]]);
            $messageStmt->execute([$chat['id'], $chat['user2_id'], $sampleMessages[array_rand($sampleMessages)]]);
        }
    }
    
} catch(PDOException $e) {
    die("Database Error: " . $e->getMessage());
}

// ============================================
// HELPER FUNCTIONS
// ============================================
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function getUserById($id) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$id]);
    return $stmt->fetch();
}

function formatTime($datetime) {
    $timestamp = strtotime($datetime);
    $now = time();
    $diff = $now - $timestamp;
    
    if ($diff < 60) return 'Just now';
    if ($diff < 3600) return floor($diff / 60) . ' min ago';
    if ($diff < 86400) return floor($diff / 3600) . ' hours ago';
    return date('d M', $timestamp);
}

// ============================================
// API ROUTES
// ============================================
$action = $_GET['action'] ?? 'home';

// ============================================
// REGISTER API
// ============================================
if ($action == 'api_register') {
    header('Content-Type: application/json');
    
    if ($_SERVER['REQUEST_METHOD'] != 'POST') {
        echo json_encode(['success' => false, 'error' => 'Invalid request method']);
        exit;
    }
    
    $full_name = trim($_POST['full_name'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $password = $_POST['password'] ?? '';
    
    $errors = [];
    
    if (empty($full_name)) $errors['full_name'] = 'Full name is required';
    if (empty($username)) $errors['username'] = 'Username is required';
    if (empty($phone)) $errors['phone'] = 'Phone number is required';
    if (empty($password)) $errors['password'] = 'Password is required';
    elseif (strlen($password) < 6) $errors['password'] = 'Password must be at least 6 characters';
    
    if (!empty($errors)) {
        echo json_encode(['success' => false, 'errors' => $errors]);
        exit;
    }
    
    try {
        // Check if username exists
        $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
        $stmt->execute([$username]);
        if ($stmt->fetch()) {
            echo json_encode(['success' => false, 'errors' => ['username' => 'Username already taken']]);
            exit;
        }
        
        // Check if phone exists
        $stmt = $pdo->prepare("SELECT id FROM users WHERE phone_number = ?");
        $stmt->execute([$phone]);
        if ($stmt->fetch()) {
            echo json_encode(['success' => false, 'errors' => ['phone' => 'Phone number already registered']]);
            exit;
        }
        
        // Insert user
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("INSERT INTO users (phone_number, username, full_name, password) VALUES (?, ?, ?, ?)");
        $stmt->execute([$phone, $username, $full_name, $hashedPassword]);
        
        echo json_encode(['success' => true, 'message' => 'Registration successful!']);
        
    } catch(PDOException $e) {
        echo json_encode(['success' => false, 'error' => 'Database error']);
    }
    exit;
}

// ============================================
// LOGIN API
// ============================================
if ($action == 'api_login') {
    header('Content-Type: application/json');
    
    if ($_SERVER['REQUEST_METHOD'] != 'POST') {
        echo json_encode(['success' => false, 'error' => 'Invalid request method']);
        exit;
    }
    
    $identifier = trim($_POST['identifier'] ?? '');
    $password = $_POST['password'] ?? '';
    
    try {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? OR phone_number = ?");
        $stmt->execute([$identifier, $identifier]);
        $user = $stmt->fetch();
        
        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            
            // Update status
            $pdo->prepare("UPDATE users SET status = 'online', last_seen = NOW() WHERE id = ?")->execute([$user['id']]);
            
            unset($user['password']);
            echo json_encode(['success' => true, 'user' => $user]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Invalid credentials']);
        }
    } catch(PDOException $e) {
        echo json_encode(['success' => false, 'error' => 'Database error']);
    }
    exit;
}

// ============================================
// LOGOUT API
// ============================================
if ($action == 'api_logout') {
    header('Content-Type: application/json');
    
    if (isLoggedIn()) {
        $pdo->prepare("UPDATE users SET status = 'offline', last_seen = NOW() WHERE id = ?")
            ->execute([$_SESSION['user_id']]);
        session_destroy();
    }
    
    echo json_encode(['success' => true]);
    exit;
}

// ============================================
// GET CURRENT USER
// ============================================
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
        session_destroy();
        echo json_encode(['logged_in' => false]);
    }
    exit;
}

// ============================================
// GET ALL USERS (for contacts)
// ============================================
if ($action == 'api_get_users') {
    header('Content-Type: application/json');
    
    if (!isLoggedIn()) {
        echo json_encode(['error' => 'Not logged in']);
        exit;
    }
    
    try {
        $stmt = $pdo->prepare("
            SELECT u.*, 
                   CASE WHEN c.contact_id IS NOT NULL THEN 1 ELSE 0 END as is_contact
            FROM users u
            LEFT JOIN contacts c ON c.contact_id = u.id AND c.user_id = ?
            WHERE u.id != ?
            ORDER BY u.status DESC, u.full_name ASC
        ");
        $stmt->execute([$_SESSION['user_id'], $_SESSION['user_id']]);
        $users = $stmt->fetchAll();
        
        foreach ($users as &$user) {
            unset($user['password']);
        }
        
        echo json_encode($users);
    } catch(PDOException $e) {
        echo json_encode(['error' => 'Database error']);
    }
    exit;
}

// ============================================
// GET CHATS
// ============================================
if ($action == 'api_get_chats') {
    header('Content-Type: application/json');
    
    if (!isLoggedIn()) {
        echo json_encode(['error' => 'Not logged in']);
        exit;
    }
    
    $userId = $_SESSION['user_id'];
    
    try {
        $stmt = $pdo->prepare("
            SELECT c.*, 
                   u1.id as user1_id, u1.full_name as user1_name, u1.username as user1_username, 
                   u1.profile_pic as user1_pic, u1.status as user1_status,
                   u2.id as user2_id, u2.full_name as user2_name, u2.username as user2_username, 
                   u2.profile_pic as user2_pic, u2.status as user2_status,
                   m.message as last_message,
                   m.created_at as last_message_time,
                   m.sender_id as last_sender_id,
                   (SELECT COUNT(*) FROM messages WHERE chat_id = c.id AND sender_id != ? AND is_read = 0) as unread_count
            FROM chats c
            JOIN users u1 ON c.user1_id = u1.id
            JOIN users u2 ON c.user2_id = u2.id
            LEFT JOIN messages m ON c.last_message_id = m.id
            WHERE c.user1_id = ? OR c.user2_id = ?
            ORDER BY c.updated_at DESC
        ");
        $stmt->execute([$userId, $userId, $userId]);
        $chats = $stmt->fetchAll();
        
        $result = [];
        foreach ($chats as $chat) {
            $partner = ($chat['user1_id'] == $userId) ? 
                ['id' => $chat['user2_id'], 'name' => $chat['user2_name'], 'username' => $chat['user2_username'], 
                 'pic' => $chat['user2_pic'], 'status' => $chat['user2_status']] :
                ['id' => $chat['user1_id'], 'name' => $chat['user1_name'], 'username' => $chat['user1_username'], 
                 'pic' => $chat['user1_pic'], 'status' => $chat['user1_status']];
            
            $result[] = [
                'id' => $chat['id'],
                'partner' => $partner,
                'last_message' => $chat['last_message'],
                'last_message_time' => $chat['last_message_time'] ? formatTime($chat['last_message_time']) : '',
                'last_sender_id' => $chat['last_sender_id'],
                'unread_count' => $chat['unread_count']
            ];
        }
        
        echo json_encode($result);
    } catch(PDOException $e) {
        echo json_encode(['error' => 'Database error']);
    }
    exit;
}

// ============================================
// GET MESSAGES
// ============================================
if ($action == 'api_get_messages') {
    header('Content-Type: application/json');
    
    if (!isLoggedIn() || !isset($_GET['chat_id'])) {
        echo json_encode(['error' => 'Invalid request']);
        exit;
    }
    
    $chatId = $_GET['chat_id'];
    $userId = $_SESSION['user_id'];
    
    try {
        // Mark messages as read
        $pdo->prepare("UPDATE messages SET is_read = 1 WHERE chat_id = ? AND sender_id != ? AND is_read = 0")
            ->execute([$chatId, $userId]);
        
        // Get messages
        $stmt = $pdo->prepare("
            SELECT m.*, u.full_name, u.username, u.profile_pic
            FROM messages m
            JOIN users u ON m.sender_id = u.id
            WHERE m.chat_id = ?
            ORDER BY m.created_at ASC
        ");
        $stmt->execute([$chatId]);
        $messages = $stmt->fetchAll();
        
        echo json_encode($messages);
    } catch(PDOException $e) {
        echo json_encode(['error' => 'Database error']);
    }
    exit;
}

// ============================================
// SEND MESSAGE
// ============================================
if ($action == 'api_send_message') {
    header('Content-Type: application/json');
    
    if (!isLoggedIn() || $_SERVER['REQUEST_METHOD'] != 'POST') {
        echo json_encode(['error' => 'Invalid request']);
        exit;
    }
    
    $senderId = $_SESSION['user_id'];
    $chatId = $_POST['chat_id'] ?? 0;
    $message = trim($_POST['message'] ?? '');
    
    if (empty($message)) {
        echo json_encode(['error' => 'Message cannot be empty']);
        exit;
    }
    
    try {
        // Insert message
        $stmt = $pdo->prepare("INSERT INTO messages (chat_id, sender_id, message) VALUES (?, ?, ?)");
        $stmt->execute([$chatId, $senderId, $message]);
        $messageId = $pdo->lastInsertId();
        
        // Update chat's last message
        $pdo->prepare("UPDATE chats SET last_message_id = ?, updated_at = NOW() WHERE id = ?")
            ->execute([$messageId, $chatId]);
        
        // Get the created message
        $stmt = $pdo->prepare("SELECT m.*, u.full_name, u.username, u.profile_pic FROM messages m JOIN users u ON m.sender_id = u.id WHERE m.id = ?");
        $stmt->execute([$messageId]);
        $newMessage = $stmt->fetch();
        
        echo json_encode(['success' => true, 'message' => $newMessage]);
    } catch(PDOException $e) {
        echo json_encode(['error' => 'Database error']);
    }
    exit;
}

// ============================================
// CREATE CHAT (start new conversation)
// ============================================
if ($action == 'api_create_chat') {
    header('Content-Type: application/json');
    
    if (!isLoggedIn() || $_SERVER['REQUEST_METHOD'] != 'POST') {
        echo json_encode(['error' => 'Invalid request']);
        exit;
    }
    
    $userId = $_SESSION['user_id'];
    $otherUserId = $_POST['user_id'] ?? 0;
    
    if ($userId == $otherUserId) {
        echo json_encode(['error' => 'Cannot chat with yourself']);
        exit;
    }
    
    try {
        // Check if chat exists
        $stmt = $pdo->prepare("
            SELECT id FROM chats 
            WHERE (user1_id = ? AND user2_id = ?) OR (user1_id = ? AND user2_id = ?)
        ");
        $stmt->execute([$userId, $otherUserId, $otherUserId, $userId]);
        $existingChat = $stmt->fetch();
        
        if ($existingChat) {
            echo json_encode(['success' => true, 'chat_id' => $existingChat['id']]);
        } else {
            // Create new chat
            $stmt = $pdo->prepare("INSERT INTO chats (user1_id, user2_id) VALUES (?, ?)");
            $stmt->execute([$userId, $otherUserId]);
            $chatId = $pdo->lastInsertId();
            
            echo json_encode(['success' => true, 'chat_id' => $chatId]);
        }
    } catch(PDOException $e) {
        echo json_encode(['error' => 'Database error']);
    }
    exit;
}

// ============================================
// ADD CONTACT
// ============================================
if ($action == 'api_add_contact') {
    header('Content-Type: application/json');
    
    if (!isLoggedIn() || $_SERVER['REQUEST_METHOD'] != 'POST') {
        echo json_encode(['error' => 'Invalid request']);
        exit;
    }
    
    $userId = $_SESSION['user_id'];
    $contactId = $_POST['contact_id'] ?? 0;
    $contactName = trim($_POST['contact_name'] ?? '');
    
    try {
        $stmt = $pdo->prepare("INSERT INTO contacts (user_id, contact_id, contact_name) VALUES (?, ?, ?)");
        $stmt->execute([$userId, $contactId, $contactName]);
        
        echo json_encode(['success' => true]);
    } catch(PDOException $e) {
        echo json_encode(['error' => 'Contact already exists']);
    }
    exit;
}

// ============================================
// SEARCH USERS
// ============================================
if ($action == 'api_search_users') {
    header('Content-Type: application/json');
    
    if (!isLoggedIn() || !isset($_GET['q'])) {
        echo json_encode([]);
        exit;
    }
    
    $userId = $_SESSION['user_id'];
    $search = '%' . $_GET['q'] . '%';
    
    try {
        $stmt = $pdo->prepare("
            SELECT id, full_name, username, phone_number, profile_pic, about,
                   CASE WHEN c.contact_id IS NOT NULL THEN 1 ELSE 0 END as is_contact
            FROM users u
            LEFT JOIN contacts c ON c.contact_id = u.id AND c.user_id = ?
            WHERE u.id != ? AND (u.full_name LIKE ? OR u.username LIKE ? OR u.phone_number LIKE ?)
            LIMIT 20
        ");
        $stmt->execute([$userId, $userId, $search, $search, $search]);
        $users = $stmt->fetchAll();
        
        echo json_encode($users);
    } catch(PDOException $e) {
        echo json_encode([]);
    }
    exit;
}

// ============================================
// HTML PAGE
// ============================================
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
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
            --sent-message: #DCF8C6;
            --received-message: #FFFFFF;
            --header-bg: #075E54;
        }

        body {
            background: linear-gradient(135deg, #0f2027, #203a43, #2c5364);
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            height: 100vh;
            overflow: hidden;
        }

        .app-wrapper {
            max-width: 1400px;
            margin: 20px auto;
            height: calc(100vh - 40px);
            background: white;
            border-radius: 20px;
            overflow: hidden;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
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
            font-size: 8rem;
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
            to { transform: translateX(0%); opacity: 1; }
        }

        /* Main App */
        .main-app {
            display: flex;
            height: 100%;
            background: #f0f2f5;
        }

        .sidebar {
            width: 350px;
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

        .logout-btn {
            cursor: pointer;
            font-size: 1.2rem;
            opacity: 0.8;
            transition: opacity 0.3s;
        }

        .logout-btn:hover {
            opacity: 1;
        }

        .search-box {
            padding: 10px;
            background: #f0f2f5;
        }

        .search-box input {
            width: 100%;
            padding: 10px 15px;
            border: none;
            border-radius: 20px;
            outline: none;
            font-size: 14px;
        }

        .chats-list {
            flex: 1;
            overflow-y: auto;
        }

        .chat-item {
            display: flex;
            align-items: center;
            padding: 15px;
            cursor: pointer;
            transition: background 0.3s;
            border-bottom: 1px solid #f0f2f5;
        }

        .chat-item:hover {
            background: #f5f6f6;
        }

        .chat-item.active {
            background: #e8f5fe;
        }

        .chat-avatar {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            object-fit: cover;
            margin-right: 15px;
            position: relative;
        }

        .online-indicator {
            position: absolute;
            bottom: 2px;
            right: 2px;
            width: 12px;
            height: 12px;
            background: #4caf50;
            border-radius: 50%;
            border: 2px solid white;
        }

        .chat-info {
            flex: 1;
        }

        .chat-name {
            font-weight: 600;
            margin-bottom: 5px;
        }

        .chat-last-message {
            font-size: 13px;
            color: #667781;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            max-width: 200px;
        }

        .chat-time {
            font-size: 11px;
            color: #667781;
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
            font-weight: 600;
            margin-left: 10px;
        }

        .chat-area {
            flex: 1;
            display: flex;
            flex-direction: column;
            background: #efeae2;
            position: relative;
        }

        .chat-header {
            background: #f0f2f5;
            padding: 15px 20px;
            display: flex;
            align-items: center;
            border-left: 1px solid #d1d7db;
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
            color: #111b21;
        }

        .chat-header-info small {
            color: #667781;
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
            word-wrap: break-word;
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
            color: #667781;
            margin-top: 5px;
            text-align: right;
        }

        .message-input-area {
            background: #f0f2f5;
            padding: 15px 20px;
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .message-input-wrapper {
            flex: 1;
            background: white;
            border-radius: 25px;
            padding: 10px 20px;
        }

        .message-input-wrapper input {
            width: 100%;
            border: none;
            outline: none;
            font-size: 15px;
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
            background: var(--whatsapp-dark-green);
            transform: scale(1.05);
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
            z-index: 100;
        }

        .new-chat-btn:hover {
            transform: scale(1.1);
            background: var(--whatsapp-dark-green);
        }

        .modal-content {
            border-radius: 15px;
        }

        .modal-header {
            background: var(--header-bg);
            color: white;
            border-bottom: none;
        }

        .modal-header .btn-close {
            filter: brightness(0) invert(1);
        }

        .user-search-item {
            display: flex;
            align-items: center;
            padding: 10px;
            border-bottom: 1px solid #f0f2f5;
            cursor: pointer;
            transition: background 0.3s;
        }

        .user-search-item:hover {
            background: #f5f6f6;
        }

        .user-search-item img {
            width: 45px;
            height: 45px;
            border-radius: 50%;
            object-fit: cover;
            margin-right: 15px;
        }

        .user-search-item .user-info {
            flex: 1;
        }

        .user-search-item .user-name {
            font-weight: 600;
        }

        .user-search-item .user-detail {
            font-size: 12px;
            color: #667781;
        }

        .contact-badge {
            background: #e8f5fe;
            color: var(--whatsapp-teal);
            padding: 3px 8px;
            border-radius: 12px;
            font-size: 11px;
            margin-left: 10px;
        }
    </style>
</head>
<body>
    <div class="app-wrapper">
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
                    
                    <div class="mt-3 text-center">
                        <small class="text-muted">Test user: john_doe / test123</small>
                    </div>
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
            <!-- Sidebar -->
            <div class="sidebar">
                <div class="sidebar-header">
                    <img id="profilePic" src="uploads/default.jpg" alt="Profile" class="profile-thumb">
                    <span class="app-title">WhatsApp</span>
                    <i class="fas fa-sign-out-alt logout-btn" onclick="logout()"></i>
                </div>
                
                <div class="search-box">
                    <input type="text" id="searchInput" placeholder="Search chats or users..." onkeyup="searchChats(this.value)">
                </div>
                
                <div class="chats-list" id="chatsList">
                    <div class="empty-state">
                        <i class="fas fa-comments"></i>
                        <h6>No chats yet</h6>
                        <p>Click the + button to start a new conversation</p>
                    </div>
                </div>
            </div>
            
            <!-- Chat Area -->
            <div class="chat-area" id="chatArea">
                <div class="empty-state">
                    <i class="fab fa-whatsapp"></i>
                    <h4>Welcome to WhatsApp Clone</h4>
                    <p id="welcomeUser"></p>
                    <p>Select a chat to start messaging</p>
                </div>
            </div>
            
            <!-- New Chat Button -->
            <div class="new-chat-btn" onclick="showNewChatModal()">
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
                    <div class="form-group">
                        <input type="text" class="form-control" id="userSearchInput" placeholder="Search users..." onkeyup="searchUsers(this.value)">
                    </div>
                    <div id="userSearchResults" style="max-height: 400px; overflow-y: auto;">
                        <div class="text-center text-muted py-3">
                            Type to search users
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // ========== GLOBAL VARIABLES ==========
        let currentUser = null;
        let currentChat = null;
        let chats = [];
        let messages = [];
        let pollingInterval = null;
        let newChatModal = null;
        
        // ========== INITIALIZATION ==========
        window.onload = function() {
            checkSession();
            newChatModal = new bootstrap.Modal(document.getElementById('newChatModal'));
        };
        
        // ========== AUTH FUNCTIONS ==========
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
            document.getElementById('registerAlert').style.display = 'none';
            document.getElementById('registerSuccess').style.display = 'none';
        }
        
        function clearFieldErrors() {
            document.querySelectorAll('.error-message').forEach(el => {
                el.style.display = 'none';
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
                btn.innerHTML = buttonId === 'loginBtn' ? '<span>Login</span>' : '<span>Create Account</span>';
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
                        document.getElementById(fieldId).classList.remove('error');
                        document.getElementById(fieldId + 'Error').style.display = 'none';
                        document.getElementById(fieldId + 'Success').style.display = 'block';
                    } else {
                        document.getElementById(fieldId).classList.add('error');
                        document.getElementById(fieldId + 'Error').innerHTML = data.message;
                        document.getElementById(fieldId + 'Error').style.display = 'block';
                        document.getElementById(fieldId + 'Success').style.display = 'none';
                    }
                } catch (error) {
                    console.error('Availability check failed:', error);
                }
            }, 500);
        }
        
        // ========== LOGIN ==========
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
                    document.getElementById('welcomeUser').innerHTML = `Welcome, ${currentUser.full_name}!`;
                    
                    loadChats();
                    startPolling();
                } else {
                    showToast(data.error || 'Login failed', 'error');
                }
            } catch (error) {
                console.error('Login error:', error);
                showToast('Login failed. Please try again.', 'error');
            } finally {
                setLoading('loginBtn', false);
            }
        }
        
        // ========== REGISTER ==========
        async function handleRegister() {
            clearFieldErrors();
            clearAlerts();
            
            const fullName = document.getElementById('regFullName').value.trim();
            const username = document.getElementById('regUsername').value.trim();
            const phone = document.getElementById('regPhone').value.trim();
            const password = document.getElementById('regPassword').value;
            
            if (!fullName || !username || !phone || !password) {
                showToast('Please fill in all fields', 'error');
                return;
            }
            
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
                    
                    document.getElementById('regFullName').value = '';
                    document.getElementById('regUsername').value = '';
                    document.getElementById('regPhone').value = '';
                    document.getElementById('regPassword').value = '';
                    
                    setTimeout(() => {
                        switchTab('login');
                    }, 2000);
                    
                    showToast('Registration successful!', 'success');
                } else {
                    if (data.errors) {
                        Object.keys(data.errors).forEach(key => {
                            showFieldError('reg' + key.charAt(0).toUpperCase() + key.slice(1), data.errors[key]);
                        });
                    } else {
                        showToast(data.error || 'Registration failed', 'error');
                    }
                }
            } catch (error) {
                console.error('Register error:', error);
                showToast('Registration failed. Please try again.', 'error');
            } finally {
                setLoading('registerBtn', false);
            }
        }
        
        // ========== LOGOUT ==========
        async function logout() {
            try {
                await fetch('?action=api_logout');
                if (pollingInterval) clearInterval(pollingInterval);
                
                document.getElementById('authScreen').style.display = 'flex';
                document.getElementById('mainApp').style.display = 'none';
                currentChat = null;
                showToast('Logged out successfully', 'success');
            } catch (error) {
                console.error('Logout error:', error);
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
                    
                    loadChats();
                    startPolling();
                }
            } catch (error) {
                console.error('Session check error:', error);
            }
        }
        
        // ========== CHAT FUNCTIONS ==========
        async function loadChats() {
            try {
                const response = await fetch('?action=api_get_chats');
                const data = await response.json();
                
                if (data.error) {
                    console.error(data.error);
                    return;
                }
                
                chats = data;
                renderChats();
            } catch (error) {
                console.error('Load chats error:', error);
            }
        }
        
        function renderChats() {
            const container = document.getElementById('chatsList');
            
            if (!chats || chats.length === 0) {
                container.innerHTML = `
                    <div class="empty-state">
                        <i class="fas fa-comments"></i>
                        <h6>No chats yet</h6>
                        <p>Click the + button to start a new conversation</p>
                    </div>
                `;
                return;
            }
            
            container.innerHTML = '';
            chats.forEach(chat => {
                const chatItem = document.createElement('div');
                chatItem.className = `chat-item ${currentChat && currentChat.id === chat.id ? 'active' : ''}`;
                chatItem.onclick = () => selectChat(chat);
                
                const lastMessage = chat.last_message ? 
                    (chat.last_sender_id === currentUser.id ? 'You: ' : '') + chat.last_message : 'No messages yet';
                
                chatItem.innerHTML = `
                    <div style="position: relative;">
                        <img src="uploads/${chat.partner.pic || 'default.jpg'}" class="chat-avatar">
                        ${chat.partner.status === 'online' ? '<span class="online-indicator"></span>' : ''}
                    </div>
                    <div class="chat-info">
                        <div style="display: flex; justify-content: space-between;">
                            <div class="chat-name">${chat.partner.name}</div>
                            <div class="chat-time">${chat.last_message_time || ''}</div>
                        </div>
                        <div style="display: flex; justify-content: space-between;">
                            <div class="chat-last-message">${lastMessage}</div>
                            ${chat.unread_count > 0 ? 
                                `<div class="unread-badge">${chat.unread_count}</div>` : ''}
                        </div>
                    </div>
                `;
                
                container.appendChild(chatItem);
            });
        }
        
        async function selectChat(chat) {
            currentChat = chat;
            renderChats(); // Update active state
            
            document.getElementById('chatArea').innerHTML = `
                <div class="chat-header">
                    <img src="uploads/${chat.partner.pic || 'default.jpg'}" class="chat-header-avatar">
                    <div class="chat-header-info">
                        <h5>${chat.partner.name}</h5>
                        <small>${chat.partner.status === 'online' ? 'online' : 'offline'}</small>
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
        }
        
        async function loadMessages(chatId) {
            try {
                const response = await fetch(`?action=api_get_messages&chat_id=${chatId}`);
                const data = await response.json();
                
                if (data.error) {
                    console.error(data.error);
                    return;
                }
                
                messages = data;
                renderMessages();
            } catch (error) {
                console.error('Load messages error:', error);
            }
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
        
        async function sendMessage() {
            const input = document.getElementById('messageInput');
            const message = input.value.trim();
            
            if (!message || !currentChat) return;
            
            const formData = new FormData();
            formData.append('chat_id', currentChat.id);
            formData.append('message', message);
            
            try {
                const response = await fetch('?action=api_send_message', {
                    method: 'POST',
                    body: formData
                });
                const data = await response.json();
                
                if (data.success) {
                    input.value = '';
                    messages.push(data.message);
                    renderMessages();
                    loadChats(); // Update chat list
                } else {
                    showToast(data.error || 'Failed to send message', 'error');
                }
            } catch (error) {
                console.error('Send message error:', error);
                showToast('Failed to send message', 'error');
            }
        }
        
        function handleKeyPress(e) {
            if (e.key === 'Enter') {
                sendMessage();
            }
        }
        
        // ========== SEARCH FUNCTIONS ==========
        function searchChats(query) {
            if (!query) {
                renderChats();
                return;
            }
            
            const filtered = chats.filter(chat => 
                chat.partner.name.toLowerCase().includes(query.toLowerCase()) ||
                chat.partner.username.toLowerCase().includes(query.toLowerCase())
            );
            
            const container = document.getElementById('chatsList');
            container.innerHTML = '';
            
            if (filtered.length === 0) {
                container.innerHTML = '<div class="empty-state">No chats found</div>';
                return;
            }
            
            filtered.forEach(chat => {
                const chatItem = document.createElement('div');
                chatItem.className = `chat-item ${currentChat && currentChat.id === chat.id ? 'active' : ''}`;
                chatItem.onclick = () => selectChat(chat);
                
                chatItem.innerHTML = `
                    <img src="uploads/${chat.partner.pic || 'default.jpg'}" class="chat-avatar">
                    <div class="chat-info">
                        <div class="chat-name">${chat.partner.name}</div>
                        <div class="chat-last-message">${chat.last_message || 'No messages'}</div>
                    </div>
                `;
                
                container.appendChild(chatItem);
            });
        }
        
        // ========== NEW CHAT MODAL ==========
        function showNewChatModal() {
            document.getElementById('userSearchInput').value = '';
            document.getElementById('userSearchResults').innerHTML = '<div class="text-center text-muted py-3">Type to search users</div>';
            newChatModal.show();
        }
        
        async function searchUsers(query) {
            if (query.length < 2) {
                document.getElementById('userSearchResults').innerHTML = '<div class="text-center text-muted py-3">Type at least 2 characters</div>';
                return;
            }
            
            try {
                const response = await fetch(`?action=api_search_users&q=${encodeURIComponent(query)}`);
                const users = await response.json();
                
                const resultsDiv = document.getElementById('userSearchResults');
                
                if (users.length === 0) {
                    resultsDiv.innerHTML = '<div class="text-center text-muted py-3">No users found</div>';
                    return;
                }
                
                resultsDiv.innerHTML = '';
                users.forEach(user => {
                    const userDiv = document.createElement('div');
                    userDiv.className = 'user-search-item';
                    userDiv.onclick = () => startChatWithUser(user);
                    
                    userDiv.innerHTML = `
                        <img src="uploads/${user.profile_pic || 'default.jpg'}">
                        <div class="user-info">
                            <div>
                                <span class="user-name">${user.full_name}</span>
                                ${user.is_contact ? '<span class="contact-badge">Contact</span>' : ''}
                            </div>
                            <div class="user-detail">@${user.username} · ${user.phone_number}</div>
                        </div>
                    `;
                    
                    resultsDiv.appendChild(userDiv);
                });
            } catch (error) {
                console.error('Search users error:', error);
            }
        }
        
        async function startChatWithUser(user) {
            newChatModal.hide();
            
            const formData = new FormData();
            formData.append('user_id', user.id);
            
            try {
                const response = await fetch('?action=api_create_chat', {
                    method: 'POST',
                    body: formData
                });
                const data = await response.json();
                
                if (data.success) {
                    // Add to contacts if not already
                    if (!user.is_contact) {
                        const contactForm = new FormData();
                        contactForm.append('contact_id', user.id);
                        contactForm.append('contact_name', user.full_name);
                        await fetch('?action=api_add_contact', {
                            method: 'POST',
                            body: contactForm
                        });
                    }
                    
                    // Refresh chats and select the new one
                    await loadChats();
                    
                    // Find the new chat
                    const newChat = chats.find(c => c.partner.id === user.id);
                    if (newChat) {
                        selectChat(newChat);
                    }
                }
            } catch (error) {
                console.error('Create chat error:', error);
            }
        }
        
        // ========== POLLING ==========
        function startPolling() {
            if (pollingInterval) clearInterval(pollingInterval);
            
            pollingInterval = setInterval(() => {
                if (currentUser) {
                    loadChats();
                    if (currentChat) {
                        loadMessages(currentChat.id);
                    }
                }
            }, 3000);
        }
        
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
    </script>
</body>
</html>
