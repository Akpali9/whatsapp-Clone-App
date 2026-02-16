<?php
// ============================================
// WHATSAPP-STYLE CHAT APPLICATION
// ============================================

session_start();

// ========== DATABASE SETUP ==========
$db_file = 'whatsapp_clone.db';

try {
    $pdo = new PDO("sqlite:$db_file");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Create tables with WhatsApp-like structure
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            phone_number TEXT UNIQUE NOT NULL,
            username TEXT UNIQUE NOT NULL,
            full_name TEXT NOT NULL,
            about TEXT DEFAULT 'Hey there! I am using WhatsApp Clone',
            profile_pic TEXT DEFAULT 'default.jpg',
            status TEXT DEFAULT 'online',
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
        
        CREATE TABLE IF NOT EXISTS message_status (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            message_id INTEGER NOT NULL,
            user_id INTEGER NOT NULL,
            status TEXT DEFAULT 'sent',
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (message_id) REFERENCES messages(id) ON DELETE CASCADE,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            UNIQUE(message_id, user_id)
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
        
        CREATE TABLE IF NOT EXISTS groups (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL,
            description TEXT,
            profile_pic TEXT DEFAULT 'group-default.jpg',
            created_by INTEGER NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE
        );
        
        CREATE TABLE IF NOT EXISTS group_members (
            group_id INTEGER NOT NULL,
            user_id INTEGER NOT NULL,
            role TEXT DEFAULT 'member',
            joined_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (group_id, user_id),
            FOREIGN KEY (group_id) REFERENCES groups(id) ON DELETE CASCADE,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        );
        
        CREATE TABLE IF NOT EXISTS calls (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            caller_id INTEGER NOT NULL,
            receiver_id INTEGER NOT NULL,
            call_type TEXT CHECK(call_type IN ('audio', 'video')),
            call_status TEXT DEFAULT 'missed',
            start_time DATETIME DEFAULT CURRENT_TIMESTAMP,
            end_time DATETIME,
            duration INTEGER,
            FOREIGN KEY (caller_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (receiver_id) REFERENCES users(id) ON DELETE CASCADE
        );
        
        CREATE TABLE IF NOT EXISTS status_updates (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL,
            content TEXT,
            media_path TEXT,
            media_type TEXT CHECK(media_type IN ('text', 'image', 'video')),
            viewed_count INTEGER DEFAULT 0,
            expires_at DATETIME DEFAULT (datetime('now', '+24 hours')),
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        );
        
        CREATE TABLE IF NOT EXISTS status_views (
            status_id INTEGER NOT NULL,
            viewer_id INTEGER NOT NULL,
            viewed_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (status_id, viewer_id),
            FOREIGN KEY (status_id) REFERENCES status_updates(id) ON DELETE CASCADE,
            FOREIGN KEY (viewer_id) REFERENCES users(id) ON DELETE CASCADE
        );
        
        -- Create indexes for better performance
        CREATE INDEX IF NOT EXISTS idx_messages_chat_id ON messages(chat_id);
        CREATE INDEX IF NOT EXISTS idx_messages_sender_id ON messages(sender_id);
        CREATE INDEX IF NOT EXISTS idx_messages_created_at ON messages(created_at);
        CREATE INDEX IF NOT EXISTS idx_chats_user1_id ON chats(user1_id);
        CREATE INDEX IF NOT EXISTS idx_chats_user2_id ON chats(user2_id);
        CREATE INDEX IF NOT EXISTS idx_chats_updated_at ON chats(updated_at);
        CREATE INDEX IF NOT EXISTS idx_calls_caller_id ON calls(caller_id);
        CREATE INDEX IF NOT EXISTS idx_calls_receiver_id ON calls(receiver_id);
        CREATE INDEX IF NOT EXISTS idx_status_updates_user_id ON status_updates(user_id);
        CREATE INDEX IF NOT EXISTS idx_status_updates_expires_at ON status_updates(expires_at);
        CREATE INDEX IF NOT EXISTS idx_message_status_message_id ON message_status(message_id);
        CREATE INDEX IF NOT EXISTS idx_message_status_user_id ON message_status(user_id);
    ");
    
    // ========== CREATE DIRECTORIES AND DEFAULT IMAGES ==========
    $directories = ['uploads', 'uploads/status', 'uploads/files', 'uploads/wallpapers'];
    foreach ($directories as $dir) {
        if (!file_exists($dir)) {
            mkdir($dir, 0777, true);
        }
    }
    
    // Function to create placeholder images using GD
    function create_placeholder_image($path, $width, $height, $bgColor, $text) {
        // Check if GD library is available
        if (!extension_loaded('gd')) {
            // If GD not available, create a simple text file as fallback
            file_put_contents($path, 'Placeholder: ' . $text);
            return false;
        }
        
        try {
            $image = imagecreatetruecolor($width, $height);
            
            // Convert hex to RGB
            $bgColor = ltrim($bgColor, '#');
            if (strlen($bgColor) == 6) {
                list($r, $g, $b) = sscanf($bgColor, "%02x%02x%02x");
            } else {
                // Default WhatsApp colors if hex parsing fails
                $r = 7; $g = 94; $b = 84; // WhatsApp dark green
            }
            
            $bg = imagecolorallocate($image, $r, $g, $b);
            $white = imagecolorallocate($image, 255, 255, 255);
            $black = imagecolorallocate($image, 0, 0, 0);
            
            imagefill($image, 0, 0, $bg);
            
            // Add text
            $font_size = 5; // Built-in GD font size
            $text_width = imagefontwidth($font_size) * strlen($text);
            $text_height = imagefontheight($font_size);
            $x = max(0, ($width - $text_width) / 2);
            $y = max(0, ($height - $text_height) / 2);
            
            imagestring($image, $font_size, (int)$x, (int)$y, $text, $white);
            
            // Add a simple border/pattern
            imagerectangle($image, 0, 0, $width-1, $height-1, $white);
            
            // Save image
            imagejpeg($image, $path, 85);
            imagedestroy($image);
            
            return true;
        } catch (Exception $e) {
            // Fallback: create empty file
            file_put_contents($path, '');
            return false;
        }
    }
    
    // Create default images if they don't exist
    if (!file_exists('uploads/default.jpg')) {
        create_placeholder_image('uploads/default.jpg', 200, 200, '#075E54', 'User');
    }
    
    if (!file_exists('uploads/group-default.jpg')) {
        create_placeholder_image('uploads/group-default.jpg', 200, 200, '#128C7E', 'Group');
    }
    
    if (!file_exists('uploads/wallpapers/default-wallpaper.jpg')) {
        create_placeholder_image('uploads/wallpapers/default-wallpaper.jpg', 800, 1200, '#0A2F44', 'WhatsApp');
    }
    
    // Also create a fallback text file if images fail
    if (!file_exists('uploads/default.jpg') || filesize('uploads/default.jpg') == 0) {
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

function formatTime($datetime) {
    $timestamp = strtotime($datetime);
    $now = time();
    $diff = $now - $timestamp;
    
    if ($diff < 60) return 'Just now';
    if ($diff < 3600) return floor($diff / 60) . ' min ago';
    if ($diff < 86400) return floor($diff / 3600) . ' hours ago';
    if ($diff < 604800) return date('l', $timestamp);
    return date('d/m/Y', $timestamp);
}

function getChatPartner($chat, $currentUserId) {
    return $chat['user1_id'] == $currentUserId ? $chat['user2_id'] : $chat['user1_id'];
}

// ========== API ROUTES ==========
$action = $_GET['action'] ?? 'home';

// Auth endpoints
if ($action == 'api_register') {
    header('Content-Type: application/json');
    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
        $phone = $_POST['phone'] ?? '';
        $username = $_POST['username'] ?? '';
        $full_name = $_POST['full_name'] ?? '';
        $password = password_hash($_POST['password'] ?? '', PASSWORD_DEFAULT);
        
        try {
            $stmt = $pdo->prepare("INSERT INTO users (phone_number, username, full_name, password) VALUES (?, ?, ?, ?)");
            $stmt->execute([$phone, $username, $full_name, $password]);
            
            echo json_encode(['success' => true]);
        } catch(PDOException $e) {
            echo json_encode(['success' => false, 'error' => 'Phone number or username already exists']);
        }
    }
    exit;
}

if ($action == 'api_login') {
    header('Content-Type: application/json');
    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
        $identifier = $_POST['identifier'] ?? ''; // phone or username
        $password = $_POST['password'] ?? '';
        
        $stmt = $pdo->prepare("SELECT * FROM users WHERE phone_number = ? OR username = ?");
        $stmt->execute([$identifier, $identifier]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            
            // Update status to online
            $update = $pdo->prepare("UPDATE users SET status = 'online', last_seen = CURRENT_TIMESTAMP WHERE id = ?");
            $update->execute([$user['id']]);
            
            unset($user['password']);
            echo json_encode(['success' => true, 'user' => $user]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Invalid credentials']);
        }
    }
    exit;
}

if ($action == 'api_logout') {
    if (isLoggedIn()) {
        $stmt = $pdo->prepare("UPDATE users SET status = 'offline', last_seen = CURRENT_TIMESTAMP WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        session_destroy();
    }
    echo json_encode(['success' => true]);
    exit;
}

// Chats endpoints
if ($action == 'api_get_chats') {
    header('Content-Type: application/json');
    if (!isLoggedIn()) {
        echo json_encode(['error' => 'Not logged in']);
        exit;
    }
    
    $userId = $_SESSION['user_id'];
    
    $stmt = $pdo->prepare("
        SELECT c.*, 
               u1.id as user1_id, u1.full_name as user1_name, u1.username as user1_username, u1.profile_pic as user1_pic, u1.status as user1_status,
               u2.id as user2_id, u2.full_name as user2_name, u2.username as user2_username, u2.profile_pic as user2_pic, u2.status as user2_status,
               m.message as last_message,
               m.created_at as last_message_time,
               m.sender_id as last_sender_id,
               m.is_read as last_message_read,
               (SELECT COUNT(*) FROM messages WHERE chat_id = c.id AND sender_id != ? AND is_read = 0) as unread_count
        FROM chats c
        JOIN users u1 ON c.user1_id = u1.id
        JOIN users u2 ON c.user2_id = u2.id
        LEFT JOIN messages m ON c.last_message_id = m.id
        WHERE c.user1_id = ? OR c.user2_id = ?
        ORDER BY c.updated_at DESC
    ");
    $stmt->execute([$userId, $userId, $userId]);
    $chats = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($chats as &$chat) {
        $partner = $chat['user1_id'] == $userId ? $chat['user2_id'] : $chat['user1_id'];
        $chat['partner'] = [
            'id' => $partner,
            'full_name' => $chat['user1_id'] == $userId ? $chat['user2_name'] : $chat['user1_name'],
            'username' => $chat['user1_id'] == $userId ? $chat['user2_username'] : $chat['user1_username'],
            'profile_pic' => $chat['user1_id'] == $userId ? $chat['user2_pic'] : $chat['user1_pic'],
            'status' => $chat['user1_id'] == $userId ? $chat['user2_status'] : $chat['user1_status'],
            'last_seen' => $chat['user1_id'] == $userId ? 
                getUserById($chat['user2_id'])['last_seen'] : 
                getUserById($chat['user1_id'])['last_seen']
        ];
        
        if ($chat['last_message']) {
            $chat['last_message_formatted'] = ($chat['last_sender_id'] == $userId ? 'You: ' : '') . 
                (strlen($chat['last_message']) > 30 ? substr($chat['last_message'], 0, 27) . '...' : $chat['last_message']);
            $chat['last_message_time_formatted'] = formatTime($chat['last_message_time']);
        }
    }
    
    echo json_encode($chats);
    exit;
}

if ($action == 'api_get_messages') {
    header('Content-Type: application/json');
    if (!isLoggedIn() || !isset($_GET['chat_id'])) {
        echo json_encode(['error' => 'Invalid request']);
        exit;
    }
    
    $chatId = $_GET['chat_id'];
    $userId = $_SESSION['user_id'];
    
    // Mark messages as read
    $pdo->prepare("UPDATE messages SET is_read = 1 WHERE chat_id = ? AND sender_id != ? AND is_read = 0")
        ->execute([$chatId, $userId]);
    
    $stmt = $pdo->prepare("
        SELECT m.*, u.full_name, u.username, u.profile_pic,
               rm.message as reply_message, rm.sender_id as reply_sender_id,
               ru.full_name as reply_sender_name
        FROM messages m
        JOIN users u ON m.sender_id = u.id
        LEFT JOIN messages rm ON m.reply_to_id = rm.id
        LEFT JOIN users ru ON rm.sender_id = ru.id
        WHERE m.chat_id = ?
        ORDER BY m.created_at ASC
    ");
    $stmt->execute([$chatId]);
    $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode($messages);
    exit;
}

if ($action == 'api_send_message') {
    header('Content-Type: application/json');
    if (!isLoggedIn() || $_SERVER['REQUEST_METHOD'] != 'POST') {
        echo json_encode(['error' => 'Invalid request']);
        exit;
    }
    
    $senderId = $_SESSION['user_id'];
    $chatId = $_POST['chat_id'] ?? 0;
    $message = $_POST['message'] ?? '';
    $replyToId = $_POST['reply_to_id'] ?? null;
    
    // Get chat info
    $stmt = $pdo->prepare("SELECT * FROM chats WHERE id = ?");
    $stmt->execute([$chatId]);
    $chat = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$chat) {
        echo json_encode(['error' => 'Chat not found']);
        exit;
    }
    
    // Insert message
    $stmt = $pdo->prepare("
        INSERT INTO messages (chat_id, sender_id, message, reply_to_id) 
        VALUES (?, ?, ?, ?)
    ");
    $stmt->execute([$chatId, $senderId, $message, $replyToId]);
    $messageId = $pdo->lastInsertId();
    
    // Update chat's last message
    $pdo->prepare("UPDATE chats SET last_message_id = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?")
        ->execute([$messageId, $chatId]);
    
    // Add message status
    $pdo->prepare("INSERT INTO message_status (message_id, user_id, status) VALUES (?, ?, 'sent')")
        ->execute([$messageId, $senderId]);
    
    // Get receiver
    $receiverId = $chat['user1_id'] == $senderId ? $chat['user2_id'] : $chat['user1_id'];
    
    echo json_encode([
        'success' => true, 
        'message_id' => $messageId,
        'created_at' => date('Y-m-d H:i:s')
    ]);
    exit;
}

// Contacts endpoints
if ($action == 'api_get_contacts') {
    header('Content-Type: application/json');
    if (!isLoggedIn()) {
        echo json_encode(['error' => 'Not logged in']);
        exit;
    }
    
    $userId = $_SESSION['user_id'];
    
    $stmt = $pdo->prepare("
        SELECT u.*, c.contact_name
        FROM contacts c
        JOIN users u ON c.contact_id = u.id
        WHERE c.user_id = ? AND c.is_blocked = 0
        ORDER BY u.full_name ASC
    ");
    $stmt->execute([$userId]);
    $contacts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode($contacts);
    exit;
}

if ($action == 'api_add_contact') {
    header('Content-Type: application/json');
    if (!isLoggedIn() || $_SERVER['REQUEST_METHOD'] != 'POST') {
        echo json_encode(['error' => 'Invalid request']);
        exit;
    }
    
    $userId = $_SESSION['user_id'];
    $contactId = $_POST['contact_id'] ?? 0;
    $contactName = $_POST['contact_name'] ?? '';
    
    try {
        $stmt = $pdo->prepare("INSERT INTO contacts (user_id, contact_id, contact_name) VALUES (?, ?, ?)");
        $stmt->execute([$userId, $contactId, $contactName]);
        
        // Create chat if not exists
        $stmt = $pdo->prepare("
            INSERT OR IGNORE INTO chats (user1_id, user2_id) 
            VALUES (?, ?), (?, ?)
        ");
        $stmt->execute([$userId, $contactId, $contactId, $userId]);
        
        echo json_encode(['success' => true]);
    } catch(PDOException $e) {
        echo json_encode(['success' => false, 'error' => 'Contact already exists']);
    }
    exit;
}

// Search users
if ($action == 'api_search_users') {
    header('Content-Type: application/json');
    if (!isLoggedIn() || !isset($_GET['query'])) {
        echo json_encode(['error' => 'Invalid request']);
        exit;
    }
    
    $userId = $_SESSION['user_id'];
    $query = '%' . $_GET['query'] . '%';
    
    $stmt = $pdo->prepare("
        SELECT id, full_name, username, phone_number, profile_pic, about 
        FROM users 
        WHERE id != ? AND (full_name LIKE ? OR username LIKE ? OR phone_number LIKE ?)
        LIMIT 20
    ");
    $stmt->execute([$userId, $query, $query, $query]);
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode($users);
    exit;
}

// Status endpoints
if ($action == 'api_get_status') {
    header('Content-Type: application/json');
    if (!isLoggedIn()) {
        echo json_encode(['error' => 'Not logged in']);
        exit;
    }
    
    $userId = $_SESSION['user_id'];
    
    // Get status from contacts
    $stmt = $pdo->prepare("
        SELECT s.*, u.full_name, u.username, u.profile_pic,
               (SELECT COUNT(*) FROM status_views WHERE status_id = s.id) as views_count
        FROM status_updates s
        JOIN users u ON s.user_id = u.id
        JOIN contacts c ON (c.contact_id = s.user_id AND c.user_id = ?)
        WHERE s.expires_at > CURRENT_TIMESTAMP
        ORDER BY s.created_at DESC
    ");
    $stmt->execute([$userId]);
    $statuses = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Group by user
    $groupedStatus = [];
    foreach ($statuses as $status) {
        $userId = $status['user_id'];
        if (!isset($groupedStatus[$userId])) {
            $groupedStatus[$userId] = [
                'user' => [
                    'id' => $userId,
                    'full_name' => $status['full_name'],
                    'username' => $status['username'],
                    'profile_pic' => $status['profile_pic']
                ],
                'statuses' => []
            ];
        }
        $groupedStatus[$userId]['statuses'][] = $status;
    }
    
    echo json_encode(array_values($groupedStatus));
    exit;
}

if ($action == 'api_add_status') {
    header('Content-Type: application/json');
    if (!isLoggedIn() || $_SERVER['REQUEST_METHOD'] != 'POST') {
        echo json_encode(['error' => 'Invalid request']);
        exit;
    }
    
    $userId = $_SESSION['user_id'];
    $content = $_POST['content'] ?? '';
    
    try {
        $stmt = $pdo->prepare("INSERT INTO status_updates (user_id, content, media_type) VALUES (?, ?, 'text')");
        $stmt->execute([$userId, $content]);
        echo json_encode(['success' => true]);
    } catch(PDOException $e) {
        echo json_encode(['success' => false, 'error' => 'Failed to add status']);
    }
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
            --primary-dark: #075E54;
            --primary: #128C7E;
            --primary-light: #25D366;
        }

        body {
            background: #111;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
            height: 100vh;
            overflow: hidden;
        }

        .app-wrapper {
            max-width: 1800px;
            margin: 0 auto;
            height: 100vh;
            background: #111;
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
            box-shadow: 0 10px 40px rgba(0,0,0,0.4);
            display: flex;
            flex-direction: column;
        }

        /* Auth Screen */
        .auth-screen {
            display: flex;
            height: 100%;
            background: linear-gradient(45deg, #075E54, #128C7E);
        }

        .auth-left {
            flex: 1;
            background: url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 800 800"><path fill="%23075E54" d="M400 0C179.1 0 0 179.1 0 400s179.1 400 400 400 400-179.1 400-400S620.9 0 400 0z"/><circle fill="%23128C7E" cx="400" cy="400" r="350"/><text x="400" y="400" text-anchor="middle" dy=".3em" fill="%23fff" font-size="80" font-family="Arial" font-weight="bold">WA</text></svg>') no-repeat center;
            background-size: contain;
            opacity: 0.1;
        }

        .auth-right {
            width: 400px;
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
            font-weight: 300;
        }

        .auth-tabs {
            display: flex;
            border-bottom: 2px solid #f0f0f0;
            margin-bottom: 20px;
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

        /* Main App */
        .main-app {
            display: flex;
            height: 100%;
            background: #f0f2f5;
        }

        /* Sidebar */
        .sidebar {
            width: 420px;
            background: white;
            border-right: 1px solid #e9edef;
            display: flex;
            flex-direction: column;
        }

        .sidebar-header {
            background: var(--header-bg);
            color: white;
            padding: 10px 16px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .sidebar-header-left {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .profile-thumb {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            object-fit: cover;
            cursor: pointer;
            border: 2px solid rgba(255,255,255,0.3);
        }

        .app-title {
            font-size: 1.2rem;
            font-weight: 500;
        }

        .sidebar-header-right {
            display: flex;
            gap: 20px;
        }

        .sidebar-header-right i {
            font-size: 1.2rem;
            cursor: pointer;
            opacity: 0.8;
            transition: opacity 0.3s;
        }

        .sidebar-header-right i:hover {
            opacity: 1;
        }

        .sidebar-tabs {
            display: flex;
            background: white;
            border-bottom: 1px solid #f0f2f5;
        }

        .sidebar-tab {
            flex: 1;
            text-align: center;
            padding: 12px;
            cursor: pointer;
            color: #54656f;
            font-weight: 500;
            font-size: 0.95rem;
            border-bottom: 2px solid transparent;
            transition: all 0.3s;
        }

        .sidebar-tab:hover {
            background: #f5f6f6;
        }

        .sidebar-tab.active {
            color: var(--whatsapp-teal);
            border-bottom-color: var(--whatsapp-teal);
        }

        .sidebar-tab i {
            margin-right: 8px;
        }

        .search-bar {
            padding: 8px 12px;
            background: #f0f2f5;
        }

        .search-container {
            background: white;
            border-radius: 8px;
            padding: 8px 12px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .search-container i {
            color: #54656f;
            font-size: 0.9rem;
        }

        .search-container input {
            border: none;
            outline: none;
            width: 100%;
            font-size: 0.95rem;
        }

        .chats-list {
            flex: 1;
            overflow-y: auto;
            background: white;
        }

        .chat-item {
            display: flex;
            align-items: center;
            padding: 12px;
            gap: 12px;
            cursor: pointer;
            transition: background 0.3s;
            border-bottom: 1px solid #f0f2f5;
        }

        .chat-item:hover {
            background: #f5f6f6;
        }

        .chat-item.active {
            background: #f0f2f5;
        }

        .chat-avatar {
            position: relative;
        }

        .chat-avatar img {
            width: 49px;
            height: 49px;
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
            border: 2px solid white;
        }

        .chat-info {
            flex: 1;
            min-width: 0;
        }

        .chat-header-info {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 4px;
        }

        .chat-name {
            font-weight: 500;
            color: #111b21;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .chat-time {
            font-size: 0.75rem;
            color: #667781;
        }

        .chat-last-message {
            display: flex;
            justify-content: space-between;
            align-items: center;
            color: #667781;
            font-size: 0.9rem;
        }

        .message-text {
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            max-width: 200px;
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
            font-size: 0.7rem;
            font-weight: 500;
            padding: 0 4px;
        }

        /* Chat Area */
        .chat-area {
            flex: 1;
            display: flex;
            flex-direction: column;
            background: #efeae2;
            position: relative;
        }

        .chat-area::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" width="100" height="100" viewBox="0 0 100 100"><path fill="%23e0dbd1" d="M10 10 L20 10 L20 20 L10 20 Z M30 30 L40 30 L40 40 L30 40 Z M50 50 L60 50 L60 60 L50 60 Z M70 70 L80 70 L80 80 L70 80 Z"/></svg>');
            opacity: 0.5;
            pointer-events: none;
        }

        .chat-header {
            background: #f0f2f5;
            padding: 10px 16px;
            display: flex;
            align-items: center;
            gap: 15px;
            border-left: 1px solid #d1d7db;
            z-index: 1;
        }

        .chat-header-avatar img {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            object-fit: cover;
        }

        .chat-header-info {
            flex: 1;
        }

        .chat-header-info h5 {
            margin: 0;
            color: #111b21;
            font-size: 1rem;
        }

        .chat-header-info small {
            color: #667781;
            font-size: 0.85rem;
        }

        .chat-header-actions {
            display: flex;
            gap: 25px;
            color: #54656f;
        }

        .chat-header-actions i {
            font-size: 1.2rem;
            cursor: pointer;
        }

        .messages-container {
            flex: 1;
            overflow-y: auto;
            padding: 20px;
            display: flex;
            flex-direction: column;
            gap: 8px;
            z-index: 1;
        }

        .message-wrapper {
            display: flex;
            margin-bottom: 8px;
            animation: fadeIn 0.3s ease;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .message-wrapper.sent {
            justify-content: flex-end;
        }

        .message-wrapper.received {
            justify-content: flex-start;
        }

        .message-bubble {
            max-width: 65%;
            padding: 8px 12px;
            border-radius: 8px;
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

        .message-sender {
            font-weight: 600;
            font-size: 0.85rem;
            color: var(--whatsapp-teal);
            margin-bottom: 3px;
        }

        .message-text {
            color: #111b21;
            font-size: 0.95rem;
            line-height: 1.4;
        }

        .message-footer {
            display: flex;
            align-items: center;
            justify-content: flex-end;
            gap: 5px;
            margin-top: 4px;
            font-size: 0.7rem;
            color: #667781;
        }

        .message-status i {
            font-size: 0.8rem;
        }

        .message-status .fa-check-double {
            color: #53bdeb;
        }

        .reply-preview {
            background: rgba(0,0,0,0.05);
            padding: 6px 10px;
            margin-bottom: 6px;
            border-radius: 6px;
            font-size: 0.85rem;
            border-left: 3px solid var(--whatsapp-teal);
        }

        .reply-sender {
            font-weight: 600;
            color: var(--whatsapp-teal);
            margin-bottom: 2px;
        }

        .message-input-area {
            background: #f0f2f5;
            padding: 12px 16px;
            display: flex;
            align-items: center;
            gap: 12px;
            z-index: 1;
        }

        .input-actions {
            display: flex;
            gap: 15px;
            color: #54656f;
        }

        .input-actions i {
            font-size: 1.3rem;
            cursor: pointer;
        }

        .message-input-wrapper {
            flex: 1;
            background: white;
            border-radius: 8px;
            padding: 9px 12px;
        }

        .message-input-wrapper input {
            width: 100%;
            border: none;
            outline: none;
            font-size: 0.95rem;
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

        /* Contacts Tab */
        .contacts-list {
            padding: 8px 0;
        }

        .contact-item {
            display: flex;
            align-items: center;
            padding: 12px;
            gap: 12px;
            cursor: pointer;
            transition: background 0.3s;
        }

        .contact-item:hover {
            background: #f5f6f6;
        }

        .contact-item img {
            width: 49px;
            height: 49px;
            border-radius: 50%;
            object-fit: cover;
        }

        .contact-info {
            flex: 1;
        }

        .contact-info h6 {
            margin: 0;
            color: #111b21;
        }

        .contact-info small {
            color: #667781;
            display: block;
            margin-top: 3px;
        }

        /* Status Tab */
        .my-status {
            display: flex;
            align-items: center;
            padding: 12px;
            gap: 12px;
            border-bottom: 1px solid #f0f2f5;
        }

        .my-status-avatar {
            position: relative;
        }

        .my-status-avatar img {
            width: 49px;
            height: 49px;
            border-radius: 50%;
            object-fit: cover;
        }

        .add-status {
            position: absolute;
            bottom: 0;
            right: 0;
            background: var(--whatsapp-teal);
            color: white;
            width: 18px;
            height: 18px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.7rem;
            border: 2px solid white;
        }

        .my-status-info h6 {
            margin: 0;
            color: #111b21;
        }

        .my-status-info small {
            color: #667781;
        }

        .status-section {
            padding: 12px;
            color: #667781;
            font-size: 0.85rem;
            font-weight: 500;
        }

        .status-item {
            display: flex;
            align-items: center;
            padding: 12px;
            gap: 12px;
            cursor: pointer;
            transition: background 0.3s;
        }

        .status-item:hover {
            background: #f5f6f6;
        }

        .status-avatar {
            position: relative;
        }

        .status-avatar img {
            width: 49px;
            height: 49px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid var(--whatsapp-green);
        }

        .status-avatar.viewed img {
            border-color: #8696a0;
        }

        .status-info {
            flex: 1;
        }

        .status-info h6 {
            margin: 0;
            color: #111b21;
        }

        .status-info small {
            color: #667781;
            display: block;
            margin-top: 3px;
        }

        /* Modals */
        .modal-content {
            border-radius: 10px;
            overflow: hidden;
        }

        .modal-header {
            background: var(--header-bg);
            color: white;
            border-bottom: none;
        }

        .modal-header .btn-close {
            filter: brightness(0) invert(1);
        }

        .user-search-result {
            display: flex;
            align-items: center;
            padding: 10px;
            border-bottom: 1px solid #f0f2f5;
            cursor: pointer;
            transition: background 0.3s;
        }

        .user-search-result:hover {
            background: #f5f6f5;
        }

        .user-search-result img {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            margin-right: 12px;
        }

        .status-input {
            width: 100%;
            padding: 15px;
            border: 2px solid #f0f2f5;
            border-radius: 10px;
            resize: none;
            font-size: 1rem;
        }

        /* Responsive */
        @media (max-width: 992px) {
            .sidebar {
                width: 350px;
            }
        }

        @media (max-width: 768px) {
            .app-wrapper {
                padding: 0;
            }
            
            .whatsapp-container {
                border-radius: 0;
                max-height: none;
            }
            
            .auth-right {
                width: 100%;
                padding: 20px;
            }
            
            .auth-left {
                display: none;
            }
            
            .sidebar {
                width: 100%;
                position: absolute;
                left: 0;
                top: 0;
                bottom: 0;
                z-index: 10;
                transition: transform 0.3s ease;
            }
            
            .sidebar.hidden {
                transform: translateX(-100%);
            }
            
            .chat-area {
                width: 100%;
                position: absolute;
                left: 0;
                top: 0;
                bottom: 0;
                z-index: 5;
            }
            
            .chat-header .back-button {
                display: block;
            }
        }

        .back-button {
            display: none;
            background: none;
            border: none;
            color: var(--whatsapp-teal);
            font-size: 1.2rem;
            margin-right: 10px;
            cursor: pointer;
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
        }

        .typing-indicator {
            display: flex;
            gap: 4px;
            padding: 5px 10px;
            background: #e6e6e6;
            border-radius: 20px;
            width: fit-content;
        }

        .typing-indicator span {
            width: 8px;
            height: 8px;
            background: #888;
            border-radius: 50%;
            animation: typing 1s infinite ease-in-out;
        }

        .typing-indicator span:nth-child(2) {
            animation-delay: 0.2s;
        }

        .typing-indicator span:nth-child(3) {
            animation-delay: 0.4s;
        }

        @keyframes typing {
            0%, 60%, 100% { transform: translateY(0); opacity: 0.4; }
            30% { transform: translateY(-5px); opacity: 1; }
        }
    </style>
</head>
<body>
    <div class="app-wrapper">
        <div class="whatsapp-container">
            <!-- Auth Screen -->
            <div id="authScreen" class="auth-screen">
                <div class="auth-left"></div>
                <div class="auth-right">
                    <div class="auth-logo">
                        <i class="fab fa-whatsapp"></i>
                        <h2>WhatsApp Clone</h2>
                    </div>
                    
                    <div class="auth-tabs">
                        <div class="auth-tab active" onclick="switchAuthTab('login')">Login</div>
                        <div class="auth-tab" onclick="switchAuthTab('register')">Register</div>
                    </div>
                    
                    <!-- Login Form -->
                    <div id="loginForm" class="auth-form">
                        <div class="mb-3">
                            <label class="form-label">Phone or Username</label>
                            <input type="text" class="form-control" id="loginIdentifier" placeholder="Enter phone or username">
                        </div>
                        <div class="mb-4">
                            <label class="form-label">Password</label>
                            <input type="password" class="form-control" id="loginPassword" placeholder="Enter password">
                        </div>
                        <button class="btn btn-success w-100" onclick="handleLogin()">
                            <i class="fas fa-sign-in-alt"></i> Login
                        </button>
                    </div>
                    
                    <!-- Register Form -->
                    <div id="registerForm" class="auth-form" style="display: none;">
                        <div class="mb-3">
                            <label class="form-label">Full Name</label>
                            <input type="text" class="form-control" id="regFullName" placeholder="Enter your full name">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Username</label>
                            <input type="text" class="form-control" id="regUsername" placeholder="Choose username">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Phone Number</label>
                            <input type="tel" class="form-control" id="regPhone" placeholder="Enter phone number">
                        </div>
                        <div class="mb-4">
                            <label class="form-label">Password</label>
                            <input type="password" class="form-control" id="regPassword" placeholder="Create password">
                        </div>
                        <button class="btn btn-success w-100" onclick="handleRegister()">
                            <i class="fas fa-user-plus"></i> Register
                        </button>
                    </div>
                </div>
            </div>
            
            <!-- Main App -->
            <div id="mainApp" class="main-app" style="display: none;">
                <!-- Sidebar -->
                <div class="sidebar" id="sidebar">
                    <div class="sidebar-header">
                        <div class="sidebar-header-left">
                            <img id="profilePic" src="uploads/default.jpg" alt="Profile" class="profile-thumb" onclick="showProfile()">
                            <span class="app-title">WhatsApp</span>
                        </div>
                        <div class="sidebar-header-right">
                            <i class="fas fa-circle-plus" onclick="showNewChat()" title="New chat"></i>
                            <i class="fas fa-ellipsis-vertical" onclick="showMenu()" title="Menu"></i>
                        </div>
                    </div>
                    
                    <div class="sidebar-tabs">
                        <div class="sidebar-tab active" onclick="switchTab('chats')">
                            <i class="fas fa-comment"></i> <span>Chats</span>
                        </div>
                        <div class="sidebar-tab" onclick="switchTab('status')">
                            <i class="fas fa-circle"></i> <span>Status</span>
                        </div>
                        <div class="sidebar-tab" onclick="switchTab('contacts')">
                            <i class="fas fa-address-book"></i> <span>Contacts</span>
                        </div>
                    </div>
                    
                    <div class="search-bar">
                        <div class="search-container">
                            <i class="fas fa-search"></i>
                            <input type="text" id="searchInput" placeholder="Search..." onkeyup="handleSearch(event)">
                        </div>
                    </div>
                    
                    <!-- Chats List -->
                    <div id="chatsList" class="chats-list">
                        <div class="empty-state">
                            <i class="fas fa-comments"></i>
                            <h6>No chats yet</h6>
                            <p>Start a conversation</p>
                        </div>
                    </div>
                    
                    <!-- Contacts List -->
                    <div id="contactsList" class="chats-list" style="display: none;">
                        <div class="empty-state">
                            <i class="fas fa-address-book"></i>
                            <h6>No contacts</h6>
                            <p>Add contacts to chat</p>
                        </div>
                    </div>
                    
                    <!-- Status List -->
                    <div id="statusList" class="chats-list" style="display: none;">
                        <div class="my-status">
                            <div class="my-status-avatar">
                                <img id="myStatusPic" src="uploads/default.jpg" alt="My Status">
                                <div class="add-status" onclick="showAddStatus()">
                                    <i class="fas fa-plus"></i>
                                </div>
                            </div>
                            <div class="my-status-info">
                                <h6>My status</h6>
                                <small>Tap to add status update</small>
                            </div>
                        </div>
                        <div class="status-section">Recent updates</div>
                        <div class="empty-state">
                            <i class="fas fa-circle"></i>
                            <h6>No status updates</h6>
                            <p>Contacts' status will appear here</p>
                        </div>
                    </div>
                </div>
                
                <!-- Chat Area -->
                <div class="chat-area" id="chatArea">
                    <div class="empty-state">
                        <i class="fab fa-whatsapp" style="color: var(--whatsapp-green);"></i>
                        <h4>WhatsApp Clone</h4>
                        <p>Select a chat to start messaging</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- New Chat Modal -->
    <div class="modal fade" id="newChatModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">New Chat</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <input type="text" class="form-control" id="userSearch" placeholder="Search by name or phone" onkeyup="searchUsers(event)">
                    </div>
                    <div id="searchResults" style="max-height: 300px; overflow-y: auto;">
                        <div class="text-center text-muted py-3">
                            Start typing to search users
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Add Status Modal -->
    <div class="modal fade" id="addStatusModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add Status Update</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <textarea class="status-input" id="statusContent" placeholder="What's on your mind?" rows="4"></textarea>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-success" onclick="addStatus()">
                        <i class="fas fa-circle"></i> Post Status
                    </button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Profile Modal -->
    <div class="modal fade" id="profileModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Profile</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body text-center">
                    <img id="profileModalPic" src="uploads/default.jpg" alt="Profile" style="width: 150px; height: 150px; border-radius: 50%; object-fit: cover; margin-bottom: 20px;">
                    <h4 id="profileModalName">Loading...</h4>
                    <p id="profileModalUsername">@username</p>
                    <p id="profileModalAbout" class="text-muted">Hey there! I am using WhatsApp Clone</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-success" onclick="logout()">
                        <i class="fas fa-sign-out-alt"></i> Logout
                    </button>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // ========== GLOBAL VARIABLES ==========
        let currentUser = null;
        let currentChat = null;
        let currentChatPartner = null;
        let chats = [];
        let messages = [];
        let pollingInterval = null;
        let newChatModal = null;
        
        // ========== AUTH FUNCTIONS ==========
        function switchAuthTab(tab) {
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
            
            if (!identifier || !password) {
                showToast('Please fill in all fields', 'error');
                return;
            }
            
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
                    updateUserProfile();
                    loadChats();
                    startPolling();
                } else {
                    showToast(data.error, 'error');
                }
            } catch (error) {
                showToast('Login failed', 'error');
            }
        }
        
        async function handleRegister() {
            const fullName = document.getElementById('regFullName').value;
            const username = document.getElementById('regUsername').value;
            const phone = document.getElementById('regPhone').value;
            const password = document.getElementById('regPassword').value;
            
            if (!fullName || !username || !phone || !password) {
                showToast('Please fill in all fields', 'error');
                return;
            }
            
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
                    showToast('Registration successful! Please login.', 'success');
                    switchAuthTab('login');
                } else {
                    showToast(data.error, 'error');
                }
            } catch (error) {
                showToast('Registration failed', 'error');
            }
        }
        
        async function logout() {
            try {
                await fetch('?action=api_logout');
                if (pollingInterval) clearInterval(pollingInterval);
                document.getElementById('authScreen').style.display = 'flex';
                document.getElementById('mainApp').style.display = 'none';
                showToast('Logged out successfully', 'success');
            } catch (error) {
                showToast('Logout failed', 'error');
            }
        }
        
        // ========== MAIN APP FUNCTIONS ==========
        function switchTab(tab) {
            document.querySelectorAll('.sidebar-tab').forEach(t => t.classList.remove('active'));
            document.querySelectorAll('.chats-list').forEach(l => l.style.display = 'none');
            
            if (tab === 'chats') {
                document.querySelector('.sidebar-tab:first-child').classList.add('active');
                document.getElementById('chatsList').style.display = 'block';
                loadChats();
            } else if (tab === 'status') {
                document.querySelector('.sidebar-tab:nth-child(2)').classList.add('active');
                document.getElementById('statusList').style.display = 'block';
                loadStatus();
            } else {
                document.querySelector('.sidebar-tab:last-child').classList.add('active');
                document.getElementById('contactsList').style.display = 'block';
                loadContacts();
            }
        }
        
        function updateUserProfile() {
            document.getElementById('profilePic').src = 'uploads/' + (currentUser.profile_pic || 'default.jpg');
            document.getElementById('myStatusPic').src = 'uploads/' + (currentUser.profile_pic || 'default.jpg');
            document.getElementById('profileModalPic').src = 'uploads/' + (currentUser.profile_pic || 'default.jpg');
            document.getElementById('profileModalName').innerText = currentUser.full_name;
            document.getElementById('profileModalUsername').innerText = '@' + currentUser.username;
            document.getElementById('profileModalAbout').innerText = currentUser.about || 'Hey there! I am using WhatsApp Clone';
        }
        
        function showProfile() {
            const modal = new bootstrap.Modal(document.getElementById('profileModal'));
            modal.show();
        }
        
        function showNewChat() {
            newChatModal = new bootstrap.Modal(document.getElementById('newChatModal'));
            newChatModal.show();
        }
        
        function showAddStatus() {
            const modal = new bootstrap.Modal(document.getElementById('addStatusModal'));
            modal.show();
        }
        
        function showMenu() {
            // Simple menu with options
            if (confirm('Logout?')) {
                logout();
            }
        }
        
        // ========== CHAT FUNCTIONS ==========
        async function loadChats() {
            try {
                const response = await fetch('?action=api_get_chats');
                chats = await response.json();
                
                const container = document.getElementById('chatsList');
                container.innerHTML = '';
                
                if (chats.length === 0) {
                    container.innerHTML = `
                        <div class="empty-state">
                            <i class="fas fa-comments"></i>
                            <h6>No chats yet</h6>
                            <p>Start a conversation by clicking the + icon</p>
                        </div>
                    `;
                    return;
                }
                
                chats.forEach(chat => {
                    const partner = chat.partner;
                    const chatItem = document.createElement('div');
                    chatItem.className = `chat-item ${currentChat && currentChat.id === chat.id ? 'active' : ''}`;
                    chatItem.onclick = () => selectChat(chat);
                    
                    const statusIndicator = partner.status === 'online' ? 
                        '<span class="online-indicator"></span>' : '';
                    
                    const unreadBadge = chat.unread_count > 0 ? 
                        `<span class="unread-badge">${chat.unread_count}</span>` : '';
                    
                    chatItem.innerHTML = `
                        <div class="chat-avatar">
                            <img src="uploads/${partner.profile_pic || 'default.jpg'}" alt="${partner.full_name}">
                            ${statusIndicator}
                        </div>
                        <div class="chat-info">
                            <div class="chat-header-info">
                                <span class="chat-name">${escapeHtml(partner.full_name)}</span>
                                <span class="chat-time">${chat.last_message_time_formatted || ''}</span>
                            </div>
                            <div class="chat-last-message">
                                <span class="message-text">${escapeHtml(chat.last_message_formatted || 'No messages yet')}</span>
                                ${unreadBadge}
                            </div>
                        </div>
                    `;
                    container.appendChild(chatItem);
                });
            } catch (error) {
                console.error('Load chats error:', error);
            }
        }
        
        async function selectChat(chat) {
            currentChat = chat;
            currentChatPartner = chat.partner;
            
            document.getElementById('chatArea').innerHTML = `
                <div class="chat-header">
                    <button class="back-button" onclick="showChatList()">
                        <i class="fas fa-arrow-left"></i>
                    </button>
                    <div class="chat-header-avatar">
                        <img src="uploads/${chat.partner.profile_pic || 'default.jpg'}" alt="${chat.partner.full_name}">
                    </div>
                    <div class="chat-header-info">
                        <h5>${escapeHtml(chat.partner.full_name)}</h5>
                        <small>${chat.partner.status === 'online' ? 'online' : 'last seen ' + formatLastSeen(chat.partner.last_seen)}</small>
                    </div>
                    <div class="chat-header-actions">
                        <i class="fas fa-phone" onclick="startCall('audio')"></i>
                        <i class="fas fa-video" onclick="startCall('video')"></i>
                        <i class="fas fa-ellipsis-vertical"></i>
                    </div>
                </div>
                <div class="messages-container" id="messagesContainer"></div>
                <div class="message-input-area">
                    <div class="input-actions">
                        <i class="fas fa-plus-circle"></i>
                        <i class="far fa-smile"></i>
                        <i class="fas fa-paperclip"></i>
                    </div>
                    <div class="message-input-wrapper">
                        <input type="text" id="messageInput" placeholder="Type a message" onkeypress="handleMessageKeyPress(event)">
                    </div>
                    <div class="send-button" onclick="sendMessage()">
                        <i class="fas fa-paper-plane"></i>
                    </div>
                </div>
            `;
            
            loadMessages(chat.id);
            
            // Mobile view
            if (window.innerWidth <= 768) {
                document.getElementById('sidebar').classList.add('hidden');
            }
        }
        
        function showChatList() {
            document.getElementById('sidebar').classList.remove('hidden');
        }
        
        async function loadMessages(chatId) {
            try {
                const response = await fetch(`?action=api_get_messages&chat_id=${chatId}`);
                messages = await response.json();
                
                const container = document.getElementById('messagesContainer');
                container.innerHTML = '';
                
                messages.forEach(msg => {
                    const isSent = msg.sender_id == currentUser.id;
                    const time = new Date(msg.created_at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
                    
                    let statusIcon = '';
                    if (isSent) {
                        if (msg.is_read) statusIcon = '<i class="fas fa-check-double" style="color: #53bdeb;"></i>';
                        else if (msg.is_delivered) statusIcon = '<i class="fas fa-check-double"></i>';
                        else statusIcon = '<i class="fas fa-check"></i>';
                    }
                    
                    let replyHtml = '';
                    if (msg.reply_message) {
                        replyHtml = `
                            <div class="reply-preview">
                                <div class="reply-sender">${escapeHtml(msg.reply_sender_name || 'Unknown')}</div>
                                <div>${escapeHtml(msg.reply_message.substring(0, 50))}${msg.reply_message.length > 50 ? '...' : ''}</div>
                            </div>
                        `;
                    }
                    
                    const messageDiv = document.createElement('div');
                    messageDiv.className = `message-wrapper ${isSent ? 'sent' : 'received'}`;
                    messageDiv.innerHTML = `
                        <div class="message-bubble">
                            ${!isSent ? `<div class="message-sender">${escapeHtml(msg.full_name)}</div>` : ''}
                            ${replyHtml}
                            <div class="message-text">${escapeHtml(msg.message)}</div>
                            <div class="message-footer">
                                <span>${time}</span>
                                <span class="message-status">${statusIcon}</span>
                            </div>
                        </div>
                    `;
                    container.appendChild(messageDiv);
                });
                
                // Scroll to bottom
                container.scrollTop = container.scrollHeight;
            } catch (error) {
                console.error('Load messages error:', error);
            }
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
                    loadMessages(currentChat.id);
                    loadChats(); // Update chat list with new message
                }
            } catch (error) {
                showToast('Failed to send message', 'error');
            }
        }
        
        function handleMessageKeyPress(e) {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                sendMessage();
            }
        }
        
        // ========== CONTACTS FUNCTIONS ==========
        async function loadContacts() {
            try {
                const response = await fetch('?action=api_get_contacts');
                const contacts = await response.json();
                
                const container = document.getElementById('contactsList');
                container.innerHTML = '';
                
                if (contacts.length === 0) {
                    container.innerHTML = `
                        <div class="empty-state">
                            <i class="fas fa-address-book"></i>
                            <h6>No contacts</h6>
                            <p>Add contacts by searching for users</p>
                        </div>
                    `;
                    return;
                }
                
                contacts.forEach(contact => {
                    const contactItem = document.createElement('div');
                    contactItem.className = 'contact-item';
                    contactItem.onclick = () => startChatWithUser(contact.id);
                    contactItem.innerHTML = `
                        <img src="uploads/${contact.profile_pic || 'default.jpg'}" alt="${contact.full_name}">
                        <div class="contact-info">
                            <h6>${escapeHtml(contact.full_name)}</h6>
                            <small>${escapeHtml(contact.contact_name || contact.username)}</small>
                        </div>
                    `;
                    container.appendChild(contactItem);
                });
            } catch (error) {
                console.error('Load contacts error:', error);
            }
        }
        
        async function searchUsers(e) {
            const query = e.target.value.trim();
            const resultsDiv = document.getElementById('searchResults');
            
            if (query.length < 2) {
                resultsDiv.innerHTML = '<div class="text-center text-muted py-3">Type at least 2 characters</div>';
                return;
            }
            
            try {
                const response = await fetch(`?action=api_search_users&query=${encodeURIComponent(query)}`);
                const users = await response.json();
                
                if (users.length === 0) {
                    resultsDiv.innerHTML = '<div class="text-center text-muted py-3">No users found</div>';
                    return;
                }
                
                resultsDiv.innerHTML = '';
                users.forEach(user => {
                    const userDiv = document.createElement('div');
                    userDiv.className = 'user-search-result';
                    userDiv.onclick = () => addContact(user.id, user.full_name);
                    userDiv.innerHTML = `
                        <img src="uploads/${user.profile_pic || 'default.jpg'}" alt="${user.full_name}">
                        <div>
                            <h6 class="mb-0">${escapeHtml(user.full_name)}</h6>
                            <small class="text-muted">@${escapeHtml(user.username)}</small>
                        </div>
                    `;
                    resultsDiv.appendChild(userDiv);
                });
            } catch (error) {
                console.error('Search error:', error);
            }
        }
        
        async function addContact(userId, userName) {
            if (confirm(`Add ${userName} to contacts?`)) {
                const formData = new FormData();
                formData.append('contact_id', userId);
                formData.append('contact_name', userName);
                
                try {
                    const response = await fetch('?action=api_add_contact', {
                        method: 'POST',
                        body: formData
                    });
                    const data = await response.json();
                    
                    if (data.success) {
                        showToast('Contact added successfully', 'success');
                        newChatModal.hide();
                        loadContacts();
                        switchTab('contacts');
                    } else {
                        showToast(data.error, 'error');
                    }
                } catch (error) {
                    showToast('Failed to add contact', 'error');
                }
            }
        }
        
        async function startChatWithUser(userId) {
            // Check if chat exists or create new one
            // For simplicity, we'll just refresh chats
            await loadChats();
            switchTab('chats');
        }
        
        // ========== STATUS FUNCTIONS ==========
        async function loadStatus() {
            try {
                const response = await fetch('?action=api_get_status');
                const statusGroups = await response.json();
                
                const container = document.getElementById('statusList');
                const recentSection = container.querySelector('.status-section');
                const emptyState = container.querySelector('.empty-state');
                
                // Clear existing status items except first two (my-status and section header)
                const myStatus = container.querySelector('.my-status');
                container.innerHTML = '';
                container.appendChild(myStatus);
                container.appendChild(recentSection);
                
                if (statusGroups.length === 0) {
                    container.appendChild(emptyState);
                    return;
                }
                
                statusGroups.forEach(group => {
                    group.statuses.forEach(status => {
                        const statusItem = document.createElement('div');
                        statusItem.className = `status-item ${status.viewed_count > 0 ? 'viewed' : ''}`;
                        statusItem.onclick = () => viewStatus(status);
                        statusItem.innerHTML = `
                            <div class="status-avatar ${status.viewed_count > 0 ? 'viewed' : ''}">
                                <img src="uploads/${group.user.profile_pic || 'default.jpg'}" alt="${group.user.full_name}">
                            </div>
                            <div class="status-info">
                                <h6>${escapeHtml(group.user.full_name)}</h6>
                                <small>${formatTime(status.created_at)}</small>
                            </div>
                        `;
                        container.appendChild(statusItem);
                    });
                });
            } catch (error) {
                console.error('Load status error:', error);
            }
        }
        
        async function addStatus() {
            const content = document.getElementById('statusContent').value.trim();
            
            if (!content) {
                showToast('Please enter status text', 'error');
                return;
            }
            
            const formData = new FormData();
            formData.append('content', content);
            
            try {
                const response = await fetch('?action=api_add_status', {
                    method: 'POST',
                    body: formData
                });
                const data = await response.json();
                
                if (data.success) {
                    showToast('Status added successfully', 'success');
                    bootstrap.Modal.getInstance(document.getElementById('addStatusModal')).hide();
                    document.getElementById('statusContent').value = '';
                    switchTab('status');
                } else {
                    showToast(data.error, 'error');
                }
            } catch (error) {
                showToast('Failed to add status', 'error');
            }
        }
        
        function viewStatus(status) {
            showToast('Viewing status: ' + status.content, 'info');
        }
        
        // ========== CALL FUNCTIONS ==========
        function startCall(type) {
            if (!currentChatPartner) {
                showToast('Select a chat first', 'error');
                return;
            }
            
            showToast(`Starting ${type} call with ${currentChatPartner.full_name}...`, 'info');
            // In a real app, implement WebRTC here
        }
        
        // ========== UTILITY FUNCTIONS ==========
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
        
        function formatLastSeen(timestamp) {
            const date = new Date(timestamp);
            const now = new Date();
            const diff = (now - date) / 1000;
            
            if (diff < 60) return 'just now';
            if (diff < 3600) return Math.floor(diff / 60) + ' minutes ago';
            if (diff < 86400) return Math.floor(diff / 3600) + ' hours ago';
            return 'on ' + date.toLocaleDateString('en-US', { day: 'numeric', month: 'short' });
        }
        
        function showToast(message, type = 'info') {
            const toast = document.createElement('div');
            toast.style.cssText = `
                position: fixed;
                top: 20px;
                right: 20px;
                background: ${type === 'error' ? '#f44336' : type === 'success' ? '#4CAF50' : '#2196F3'};
                color: white;
                padding: 12px 24px;
                border-radius: 5px;
                box-shadow: 0 2px 5px rgba(0,0,0,0.2);
                z-index: 9999;
                animation: slideIn 0.3s ease;
            `;
            toast.textContent = message;
            
            document.body.appendChild(toast);
            
            setTimeout(() => {
                toast.style.animation = 'slideOut 0.3s ease';
                setTimeout(() => toast.remove(), 300);
            }, 3000);
        }
        
        function startPolling() {
            // Poll for updates every 3 seconds
            pollingInterval = setInterval(() => {
                if (document.querySelector('.sidebar-tab.active').innerText.includes('Chats')) {
                    loadChats();
                }
                if (currentChat) {
                    loadMessages(currentChat.id);
                }
            }, 3000);
        }
        
        function handleSearch(e) {
            // Implement search functionality
            const query = e.target.value.toLowerCase();
            if (!query) {
                loadChats();
                return;
            }
            
            const filteredChats = chats.filter(chat => 
                chat.partner.full_name.toLowerCase().includes(query) ||
                chat.partner.username.toLowerCase().includes(query)
            );
            
            // Update UI with filtered chats
            // Implementation depends on your UI structure
        }
        
        // Handle window resize
        window.addEventListener('resize', function() {
            if (window.innerWidth <= 768) {
                if (currentChat) {
                    document.getElementById('sidebar').classList.add('hidden');
                }
            } else {
                document.getElementById('sidebar').classList.remove('hidden');
            }
        });
        
        // Initialize
        <?php if (isLoggedIn()): ?>
            window.onload = function() {
                currentUser = <?php echo json_encode(getUserById($_SESSION['user_id'])); ?>;
                if (currentUser) {
                    document.getElementById('authScreen').style.display = 'none';
                    document.getElementById('mainApp').style.display = 'flex';
                    updateUserProfile();
                    loadChats();
                    startPolling();
                }
            };
        <?php endif; ?>
    </script>
</body>
</html>
