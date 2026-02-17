<?php
// ============================================
// WHATSAPP CLONE - COMPLETE WITH PROFILE SETTINGS
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
$upload_dirs = ['uploads', 'uploads/status', 'uploads/files', 'uploads/wallpapers', 'uploads/themes'];
foreach ($upload_dirs as $dir) {
    if (!file_exists($dir)) {
        mkdir($dir, 0777, true);
    }
}

// Default profile picture
if (!file_exists('uploads/default.jpg')) {
    file_put_contents('uploads/default.jpg', '');
}

// Default themes
$default_themes = [
    'light' => '#ffffff',
    'dark' => '#111b21',
    'whatsapp' => '#075E54',
    'blue' => '#2196f3',
    'purple' => '#9c27b0',
    'green' => '#4caf50'
];

// ============================================
// DATABASE CONNECTION
// ============================================
try {
    $pdo = new PDO("mysql:host=$db_host", $db_user, $db_pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `$db_name` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    
    $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8mb4", $db_user, $db_pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    
    // Users table with additional profile fields
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
            theme VARCHAR(50) DEFAULT 'light',
            wallpaper VARCHAR(255) DEFAULT 'default-wallpaper.jpg',
            notification_sound BOOLEAN DEFAULT TRUE,
            vibration BOOLEAN DEFAULT TRUE,
            last_seen_privacy ENUM('everyone', 'contacts', 'nobody') DEFAULT 'everyone',
            profile_photo_privacy ENUM('everyone', 'contacts', 'nobody') DEFAULT 'everyone',
            about_privacy ENUM('everyone', 'contacts', 'nobody') DEFAULT 'everyone',
            read_receipts BOOLEAN DEFAULT TRUE,
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
            is_archived BOOLEAN DEFAULT FALSE,
            is_muted BOOLEAN DEFAULT FALSE,
            custom_wallpaper VARCHAR(255) NULL,
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
            message TEXT,
            message_type ENUM('text', 'image', 'video', 'audio', 'document') DEFAULT 'text',
            file_path VARCHAR(500),
            file_name VARCHAR(255),
            file_size INT,
            is_read BOOLEAN DEFAULT FALSE,
            is_delivered BOOLEAN DEFAULT FALSE,
            is_starred BOOLEAN DEFAULT FALSE,
            reply_to_id INT NULL,
            deleted_for_everyone BOOLEAN DEFAULT FALSE,
            deleted_for_me BOOLEAN DEFAULT FALSE,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (chat_id) REFERENCES chats(id) ON DELETE CASCADE,
            FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (reply_to_id) REFERENCES messages(id) ON DELETE SET NULL,
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
            is_blocked BOOLEAN DEFAULT FALSE,
            is_favorite BOOLEAN DEFAULT FALSE,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (user_id, contact_id),
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (contact_id) REFERENCES users(id) ON DELETE CASCADE,
            INDEX idx_contact (contact_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    
    // Create test users if none exist
    $stmt = $pdo->query("SELECT COUNT(*) FROM users");
    if ($stmt->fetchColumn() == 0) {
        $password = password_hash('test123', PASSWORD_DEFAULT);
        
        $users = [
            ['+1234567890', 'john_doe', 'John Doe', $password, 'Software Developer', 'light'],
            ['+1234567891', 'jane_smith', 'Jane Smith', $password, 'Digital Artist', 'dark'],
            ['+1234567892', 'bob_wilson', 'Bob Wilson', $password, 'Music Producer', 'whatsapp'],
            ['+1234567893', 'alice_brown', 'Alice Brown', $password, 'Travel Blogger', 'blue']
        ];
        
        $stmt = $pdo->prepare("INSERT INTO users (phone_number, username, full_name, password, about, theme) VALUES (?, ?, ?, ?, ?, ?)");
        foreach ($users as $user) {
            $stmt->execute($user);
        }
        
        // Get user IDs
        $userIds = $pdo->query("SELECT id FROM users")->fetchAll();
        
        // Create contacts and chats
        $contactStmt = $pdo->prepare("INSERT INTO contacts (user_id, contact_id, contact_name) VALUES (?, ?, ?)");
        $chatStmt = $pdo->prepare("INSERT INTO chats (user1_id, user2_id) VALUES (?, ?)");
        
        for ($i = 0; $i < count($userIds); $i++) {
            for ($j = $i + 1; $j < count($userIds); $j++) {
                $contactStmt->execute([$userIds[$i]['id'], $userIds[$j]['id'], $userIds[$j]['full_name']]);
                $contactStmt->execute([$userIds[$j]['id'], $userIds[$i]['id'], $userIds[$i]['full_name']]);
                $chatStmt->execute([$userIds[$i]['id'], $userIds[$j]['id']]);
            }
        }
        
        // Add sample messages
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
            for ($i = 0; $i < 3; $i++) {
                $messageStmt->execute([$chat['id'], $chat['user1_id'], $sampleMessages[array_rand($sampleMessages)]]);
                $messageStmt->execute([$chat['id'], $chat['user2_id'], $sampleMessages[array_rand($sampleMessages)]]);
            }
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
    if ($diff < 604800) return date('l', $timestamp);
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
        $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
        $stmt->execute([$username]);
        if ($stmt->fetch()) {
            echo json_encode(['success' => false, 'errors' => ['username' => 'Username already taken']]);
            exit;
        }
        
        $stmt = $pdo->prepare("SELECT id FROM users WHERE phone_number = ?");
        $stmt->execute([$phone]);
        if ($stmt->fetch()) {
            echo json_encode(['success' => false, 'errors' => ['phone' => 'Phone number already registered']]);
            exit;
        }
        
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
// UPDATE PROFILE API
// ============================================
if ($action == 'api_update_profile') {
    header('Content-Type: application/json');
    
    if (!isLoggedIn() || $_SERVER['REQUEST_METHOD'] != 'POST') {
        echo json_encode(['success' => false, 'error' => 'Invalid request']);
        exit;
    }
    
    $userId = $_SESSION['user_id'];
    $full_name = trim($_POST['full_name'] ?? '');
    $about = trim($_POST['about'] ?? '');
    $theme = $_POST['theme'] ?? 'light';
    $notification_sound = isset($_POST['notification_sound']) ? 1 : 0;
    $vibration = isset($_POST['vibration']) ? 1 : 0;
    $last_seen_privacy = $_POST['last_seen_privacy'] ?? 'everyone';
    $profile_photo_privacy = $_POST['profile_photo_privacy'] ?? 'everyone';
    $about_privacy = $_POST['about_privacy'] ?? 'everyone';
    $read_receipts = isset($_POST['read_receipts']) ? 1 : 0;
    
    // Handle profile picture upload
    $profile_pic = null;
    if (isset($_FILES['profile_pic']) && $_FILES['profile_pic']['error'] == 0) {
        $allowed = ['jpg', 'jpeg', 'png', 'gif'];
        $filename = $_FILES['profile_pic']['name'];
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        
        if (in_array($ext, $allowed)) {
            $new_filename = uniqid() . '.' . $ext;
            $upload_path = 'uploads/' . $new_filename;
            
            if (move_uploaded_file($_FILES['profile_pic']['tmp_name'], $upload_path)) {
                $profile_pic = $new_filename;
            }
        }
    }
    
    try {
        if ($profile_pic) {
            $stmt = $pdo->prepare("
                UPDATE users SET 
                    full_name = ?, 
                    about = ?, 
                    profile_pic = ?,
                    theme = ?,
                    notification_sound = ?,
                    vibration = ?,
                    last_seen_privacy = ?,
                    profile_photo_privacy = ?,
                    about_privacy = ?,
                    read_receipts = ?
                WHERE id = ?
            ");
            $stmt->execute([$full_name, $about, $profile_pic, $theme, $notification_sound, 
                           $vibration, $last_seen_privacy, $profile_photo_privacy, $about_privacy, 
                           $read_receipts, $userId]);
        } else {
            $stmt = $pdo->prepare("
                UPDATE users SET 
                    full_name = ?, 
                    about = ?,
                    theme = ?,
                    notification_sound = ?,
                    vibration = ?,
                    last_seen_privacy = ?,
                    profile_photo_privacy = ?,
                    about_privacy = ?,
                    read_receipts = ?
                WHERE id = ?
            ");
            $stmt->execute([$full_name, $about, $theme, $notification_sound, $vibration, 
                           $last_seen_privacy, $profile_photo_privacy, $about_privacy, 
                           $read_receipts, $userId]);
        }
        
        echo json_encode(['success' => true, 'message' => 'Profile updated successfully']);
    } catch(PDOException $e) {
        echo json_encode(['success' => false, 'error' => 'Database error']);
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
                   u1.profile_pic as user1_pic, u1.status as user1_status, u1.theme as user1_theme,
                   u2.id as user2_id, u2.full_name as user2_name, u2.username as user2_username, 
                   u2.profile_pic as user2_pic, u2.status as user2_status, u2.theme as user2_theme,
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
                 'pic' => $chat['user2_pic'], 'status' => $chat['user2_status'], 'theme' => $chat['user2_theme']] :
                ['id' => $chat['user1_id'], 'name' => $chat['user1_name'], 'username' => $chat['user1_username'], 
                 'pic' => $chat['user1_pic'], 'status' => $chat['user1_status'], 'theme' => $chat['user1_theme']];
            
            $result[] = [
                'id' => $chat['id'],
                'partner' => $partner,
                'last_message' => $chat['last_message'],
                'last_message_time' => $chat['last_message_time'] ? formatTime($chat['last_message_time']) : '',
                'last_sender_id' => $chat['last_sender_id'],
                'unread_count' => $chat['unread_count'],
                'is_archived' => $chat['is_archived'],
                'is_muted' => $chat['is_muted']
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
        
        $stmt = $pdo->prepare("
            SELECT m.*, u.full_name, u.username, u.profile_pic
            FROM messages m
            JOIN users u ON m.sender_id = u.id
            WHERE m.chat_id = ? AND m.deleted_for_everyone = 0
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
        $stmt = $pdo->prepare("INSERT INTO messages (chat_id, sender_id, message) VALUES (?, ?, ?)");
        $stmt->execute([$chatId, $senderId, $message]);
        $messageId = $pdo->lastInsertId();
        
        $pdo->prepare("UPDATE chats SET last_message_id = ?, updated_at = NOW() WHERE id = ?")
            ->execute([$messageId, $chatId]);
        
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
// CREATE CHAT
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
        $stmt = $pdo->prepare("
            SELECT id FROM chats 
            WHERE (user1_id = ? AND user2_id = ?) OR (user1_id = ? AND user2_id = ?)
        ");
        $stmt->execute([$userId, $otherUserId, $otherUserId, $userId]);
        $existingChat = $stmt->fetch();
        
        if ($existingChat) {
            echo json_encode(['success' => true, 'chat_id' => $existingChat['id']]);
        } else {
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
            SELECT id, full_name, username, phone_number, profile_pic, about, status, theme,
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
// TOGGLE FAVORITE
// ============================================
if ($action == 'api_toggle_favorite') {
    header('Content-Type: application/json');
    
    if (!isLoggedIn() || $_SERVER['REQUEST_METHOD'] != 'POST') {
        echo json_encode(['error' => 'Invalid request']);
        exit;
    }
    
    $userId = $_SESSION['user_id'];
    $contactId = $_POST['contact_id'] ?? 0;
    $isFavorite = $_POST['is_favorite'] ?? 0;
    
    try {
        $stmt = $pdo->prepare("UPDATE contacts SET is_favorite = ? WHERE user_id = ? AND contact_id = ?");
        $stmt->execute([$isFavorite, $userId, $contactId]);
        
        echo json_encode(['success' => true]);
    } catch(PDOException $e) {
        echo json_encode(['error' => 'Database error']);
    }
    exit;
}

// ============================================
// BLOCK CONTACT
// ============================================
if ($action == 'api_block_contact') {
    header('Content-Type: application/json');
    
    if (!isLoggedIn() || $_SERVER['REQUEST_METHOD'] != 'POST') {
        echo json_encode(['error' => 'Invalid request']);
        exit;
    }
    
    $userId = $_SESSION['user_id'];
    $contactId = $_POST['contact_id'] ?? 0;
    $isBlocked = $_POST['is_blocked'] ?? 0;
    
    try {
        $stmt = $pdo->prepare("UPDATE contacts SET is_blocked = ? WHERE user_id = ? AND contact_id = ?");
        $stmt->execute([$isBlocked, $userId, $contactId]);
        
        echo json_encode(['success' => true]);
    } catch(PDOException $e) {
        echo json_encode(['error' => 'Database error']);
    }
    exit;
}

// ============================================
// MUTE CHAT
// ============================================
if ($action == 'api_mute_chat') {
    header('Content-Type: application/json');
    
    if (!isLoggedIn() || $_SERVER['REQUEST_METHOD'] != 'POST') {
        echo json_encode(['error' => 'Invalid request']);
        exit;
    }
    
    $chatId = $_POST['chat_id'] ?? 0;
    $isMuted = $_POST['is_muted'] ?? 0;
    
    try {
        $stmt = $pdo->prepare("UPDATE chats SET is_muted = ? WHERE id = ?");
        $stmt->execute([$isMuted, $chatId]);
        
        echo json_encode(['success' => true]);
    } catch(PDOException $e) {
        echo json_encode(['error' => 'Database error']);
    }
    exit;
}

// ============================================
// ARCHIVE CHAT
// ============================================
if ($action == 'api_archive_chat') {
    header('Content-Type: application/json');
    
    if (!isLoggedIn() || $_SERVER['REQUEST_METHOD'] != 'POST') {
        echo json_encode(['error' => 'Invalid request']);
        exit;
    }
    
    $chatId = $_POST['chat_id'] ?? 0;
    $isArchived = $_POST['is_archived'] ?? 0;
    
    try {
        $stmt = $pdo->prepare("UPDATE chats SET is_archived = ? WHERE id = ?");
        $stmt->execute([$isArchived, $chatId]);
        
        echo json_encode(['success' => true]);
    } catch(PDOException $e) {
        echo json_encode(['error' => 'Database error']);
    }
    exit;
}

// ============================================
// DELETE MESSAGE
// ============================================
if ($action == 'api_delete_message') {
    header('Content-Type: application/json');
    
    if (!isLoggedIn() || $_SERVER['REQUEST_METHOD'] != 'POST') {
        echo json_encode(['error' => 'Invalid request']);
        exit;
    }
    
    $messageId = $_POST['message_id'] ?? 0;
    $deleteFor = $_POST['delete_for'] ?? 'me'; // 'me' or 'everyone'
    $userId = $_SESSION['user_id'];
    
    try {
        if ($deleteFor == 'everyone') {
            // Check if user is the sender
            $stmt = $pdo->prepare("SELECT sender_id FROM messages WHERE id = ?");
            $stmt->execute([$messageId]);
            $message = $stmt->fetch();
            
            if ($message && $message['sender_id'] == $userId) {
                $stmt = $pdo->prepare("UPDATE messages SET deleted_for_everyone = 1 WHERE id = ?");
                $stmt->execute([$messageId]);
            } else {
                echo json_encode(['error' => 'Not authorized']);
                exit;
            }
        } else {
            $stmt = $pdo->prepare("UPDATE messages SET deleted_for_me = 1 WHERE id = ?");
            $stmt->execute([$messageId]);
        }
        
        echo json_encode(['success' => true]);
    } catch(PDOException $e) {
        echo json_encode(['error' => 'Database error']);
    }
    exit;
}

// ============================================
// STAR MESSAGE
// ============================================
if ($action == 'api_star_message') {
    header('Content-Type: application/json');
    
    if (!isLoggedIn() || $_SERVER['REQUEST_METHOD'] != 'POST') {
        echo json_encode(['error' => 'Invalid request']);
        exit;
    }
    
    $messageId = $_POST['message_id'] ?? 0;
    $isStarred = $_POST['is_starred'] ?? 0;
    
    try {
        $stmt = $pdo->prepare("UPDATE messages SET is_starred = ? WHERE id = ?");
        $stmt->execute([$isStarred, $messageId]);
        
        echo json_encode(['success' => true]);
    } catch(PDOException $e) {
        echo json_encode(['error' => 'Database error']);
    }
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=yes">
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

        [data-theme="whatsapp"] {
            --bg-primary: #e5ddd5;
            --bg-secondary: #ffffff;
            --header-bg: #075E54;
            --sent-message: #dcf8c6;
            --received-message: #ffffff;
        }

        [data-theme="blue"] {
            --bg-primary: #e3f2fd;
            --bg-secondary: #ffffff;
            --header-bg: #1976d2;
            --sent-message: #bbdefb;
            --received-message: #ffffff;
        }

        [data-theme="purple"] {
            --bg-primary: #f3e5f5;
            --bg-secondary: #ffffff;
            --header-bg: #7b1fa2;
            --sent-message: #e1bee7;
            --received-message: #ffffff;
        }

        [data-theme="green"] {
            --bg-primary: #e8f5e9;
            --bg-secondary: #ffffff;
            --header-bg: #2e7d32;
            --sent-message: #c8e6c9;
            --received-message: #ffffff;
        }

        body {
            background: linear-gradient(135deg, #0f2027, #203a43, #2c5364);
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            height: 100vh;
            overflow: hidden;
            margin: 0;
            padding: 0;
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

        @media (max-width: 768px) {
            .app-wrapper {
                margin: 0;
                height: 100vh;
                border-radius: 0;
            }
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

        @media (max-width: 768px) {
            .auth-left {
                display: none;
            }
            .auth-right {
                width: 100%;
                padding: 20px;
            }
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

        /* Main App */
        .main-app {
            display: flex;
            height: 100%;
            background: var(--bg-primary);
            transition: background-color 0.3s;
        }

        /* Sidebar */
        .sidebar {
            width: 350px;
            background: var(--bg-secondary);
            border-right: 1px solid var(--border-color);
            display: flex;
            flex-direction: column;
            transition: all 0.3s;
        }

        @media (max-width: 768px) {
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
                position: absolute;
                left: 0;
                top: 0;
                bottom: 0;
                z-index: 5;
            }
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
            border: 2px solid rgba(255,255,255,0.3);
        }

        .app-title {
            font-size: 1.2rem;
            font-weight: 500;
        }

        .header-icons {
            display: flex;
            gap: 15px;
        }

        .header-icons i {
            cursor: pointer;
            font-size: 1.2rem;
            opacity: 0.9;
            transition: opacity 0.3s;
        }

        .header-icons i:hover {
            opacity: 1;
        }

        .search-box {
            padding: 10px;
            background: var(--bg-primary);
        }

        .search-box input {
            width: 100%;
            padding: 10px 15px;
            border: none;
            border-radius: 20px;
            outline: none;
            font-size: 14px;
            background: var(--bg-secondary);
            color: var(--text-primary);
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
            border-bottom: 1px solid var(--border-color);
        }

        .chat-item:hover {
            background: rgba(0,0,0,0.05);
        }

        .chat-item.active {
            background: rgba(0,0,0,0.08);
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
        }

        .chat-info {
            flex: 1;
            min-width: 0;
        }

        .chat-header-info {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 5px;
        }

        .chat-name {
            font-weight: 600;
            color: var(--text-primary);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .chat-time {
            font-size: 11px;
            color: var(--text-secondary);
        }

        .chat-last-message {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .message-preview {
            font-size: 13px;
            color: var(--text-secondary);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            max-width: 180px;
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

        /* Chat Area */
        .chat-area {
            flex: 1;
            display: flex;
            flex-direction: column;
            background: var(--bg-primary);
            position: relative;
        }

        .chat-header {
            background: var(--bg-secondary);
            padding: 10px 15px;
            display: flex;
            align-items: center;
            border-bottom: 1px solid var(--border-color);
        }

        .back-button {
            display: none;
            background: none;
            border: none;
            color: var(--text-primary);
            font-size: 1.2rem;
            margin-right: 15px;
            cursor: pointer;
        }

        @media (max-width: 768px) {
            .back-button {
                display: block;
            }
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

        .chat-header-actions {
            display: flex;
            gap: 20px;
        }

        .chat-header-actions i {
            color: var(--text-primary);
            font-size: 1.2rem;
            cursor: pointer;
            opacity: 0.8;
            transition: opacity 0.3s;
        }

        .chat-header-actions i:hover {
            opacity: 1;
        }

        .messages-container {
            flex: 1;
            overflow-y: auto;
            padding: 20px;
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .message-wrapper {
            display: flex;
            margin-bottom: 5px;
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
            background: var(--received-message);
            color: var(--text-primary);
        }

        .sent .message-bubble {
            background: var(--sent-message);
            border-top-right-radius: 0;
        }

        .received .message-bubble {
            border-top-left-radius: 0;
        }

        .message-time {
            font-size: 11px;
            color: var(--text-secondary);
            margin-top: 5px;
            text-align: right;
            display: flex;
            align-items: center;
            justify-content: flex-end;
            gap: 5px;
        }

        .message-status i {
            font-size: 12px;
        }

        .message-status .fa-check-double {
            color: #4fc3f7;
        }

        .message-input-area {
            background: var(--bg-secondary);
            padding: 10px 15px;
            display: flex;
            align-items: center;
            gap: 15px;
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
            font-size: 15px;
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
            transform: scale(1.05);
        }

        /* Empty State */
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

        .empty-state i {
            font-size: 5rem;
            margin-bottom: 20px;
            color: var(--whatsapp-teal);
        }

        /* New Chat Button */
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
        }

        @media (max-width: 768px) {
            .new-chat-btn {
                bottom: 20px;
                right: 20px;
                width: 50px;
                height: 50px;
                font-size: 20px;
            }
        }

        /* Modals */
        .modal-content {
            border-radius: 15px;
            background: var(--bg-secondary);
            color: var(--text-primary);
        }

        .modal-header {
            background: var(--header-bg);
            color: white;
            border-bottom: none;
            border-radius: 15px 15px 0 0;
        }

        .modal-header .btn-close {
            filter: brightness(0) invert(1);
        }

        .modal-body {
            background: var(--bg-secondary);
        }

        .modal-footer {
            border-top: 1px solid var(--border-color);
            background: var(--bg-secondary);
        }

        .form-control, .form-select {
            background: var(--bg-primary);
            border: 1px solid var(--border-color);
            color: var(--text-primary);
        }

        .form-control:focus, .form-select:focus {
            background: var(--bg-primary);
            color: var(--text-primary);
            border-color: var(--whatsapp-teal);
            box-shadow: 0 0 0 3px rgba(18, 140, 126, 0.1);
        }

        .form-label {
            color: var(--text-primary);
        }

        /* Profile Edit */
        .profile-pic-edit {
            text-align: center;
            margin-bottom: 20px;
            position: relative;
            display: inline-block;
        }

        .profile-pic-edit img {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid var(--whatsapp-teal);
        }

        .profile-pic-edit .edit-overlay {
            position: absolute;
            bottom: 0;
            right: 0;
            background: var(--whatsapp-teal);
            color: white;
            width: 35px;
            height: 35px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            border: 2px solid white;
        }

        .theme-option {
            display: inline-block;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            margin: 5px;
            cursor: pointer;
            border: 3px solid transparent;
            transition: all 0.3s;
        }

        .theme-option:hover {
            transform: scale(1.1);
        }

        .theme-option.active {
            border-color: var(--whatsapp-teal);
        }

        .theme-option.light { background: #ffffff; border: 1px solid #ddd; }
        .theme-option.dark { background: #111b21; }
        .theme-option.whatsapp { background: #075E54; }
        .theme-option.blue { background: #2196f3; }
        .theme-option.purple { background: #9c27b0; }
        .theme-option.green { background: #4caf50; }

        .settings-section {
            margin-bottom: 20px;
            padding-bottom: 20px;
            border-bottom: 1px solid var(--border-color);
        }

        .settings-section h6 {
            color: var(--whatsapp-teal);
            margin-bottom: 15px;
        }

        .privacy-option {
            margin-bottom: 15px;
        }

        .privacy-option label {
            display: block;
            margin-bottom: 5px;
            color: var(--text-primary);
        }

        /* Search Results */
        .user-search-item {
            display: flex;
            align-items: center;
            padding: 10px;
            border-bottom: 1px solid var(--border-color);
            cursor: pointer;
            transition: background 0.3s;
        }

        .user-search-item:hover {
            background: rgba(0,0,0,0.05);
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
            color: var(--text-primary);
        }

        .user-search-item .user-detail {
            font-size: 12px;
            color: var(--text-secondary);
        }

        .contact-badge {
            background: var(--whatsapp-teal);
            color: white;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 11px;
            margin-left: 10px;
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
            to { transform: translateX(0%); opacity: 1; }
        }

        /* Context Menu */
        .context-menu {
            position: absolute;
            background: var(--bg-secondary);
            border: 1px solid var(--border-color);
            border-radius: 8px;
            padding: 5px 0;
            min-width: 150px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
            z-index: 1000;
        }

        .context-menu-item {
            padding: 8px 15px;
            cursor: pointer;
            transition: background 0.3s;
            color: var(--text-primary);
        }

        .context-menu-item:hover {
            background: rgba(0,0,0,0.05);
        }

        .context-menu-item i {
            width: 20px;
            margin-right: 10px;
            color: var(--whatsapp-teal);
        }
    </style>
</head>
<body data-theme="light">
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
                    <div id="loginAlert" class="alert alert-danger" style="display: none;"></div>
                    
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
                    <div id="registerAlert" class="alert alert-danger" style="display: none;"></div>
                    <div id="registerSuccess" class="alert alert-success" style="display: none;"></div>
                    
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
            <div class="sidebar" id="sidebar">
                <div class="sidebar-header">
                    <img id="profilePic" src="uploads/default.jpg" alt="Profile" class="profile-thumb" onclick="showProfileSettings()">
                    <span class="app-title">WhatsApp</span>
                    <div class="header-icons">
                        <i class="fas fa-cog" onclick="showSettings()" title="Settings"></i>
                        <i class="fas fa-sign-out-alt" onclick="logout()" title="Logout"></i>
                    </div>
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
    
    <!-- Profile Settings Modal -->
    <div class="modal fade" id="profileModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Profile Settings</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="profileForm" enctype="multipart/form-data">
                        <div class="text-center mb-4">
                            <div class="profile-pic-edit">
                                <img id="profilePreview" src="uploads/default.jpg" alt="Profile">
                                <div class="edit-overlay" onclick="document.getElementById('profilePicInput').click()">
                                    <i class="fas fa-camera"></i>
                                </div>
                            </div>
                            <input type="file" id="profilePicInput" accept="image/*" style="display: none;" onchange="previewProfilePic(this)">
                        </div>
                        
                        <div class="settings-section">
                            <h6>Personal Information</h6>
                            <div class="mb-3">
                                <label class="form-label">Full Name</label>
                                <input type="text" class="form-control" id="editFullName" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">About</label>
                                <textarea class="form-control" id="editAbout" rows="2" placeholder="Tell something about yourself"></textarea>
                            </div>
                        </div>
                        
                        <div class="settings-section">
                            <h6>Theme Settings</h6>
                            <div class="mb-3">
                                <label class="form-label">Choose Theme</label>
                                <div>
                                    <div class="theme-option light" onclick="selectTheme('light')" title="Light"></div>
                                    <div class="theme-option dark" onclick="selectTheme('dark')" title="Dark"></div>
                                    <div class="theme-option whatsapp" onclick="selectTheme('whatsapp')" title="WhatsApp"></div>
                                    <div class="theme-option blue" onclick="selectTheme('blue')" title="Blue"></div>
                                    <div class="theme-option purple" onclick="selectTheme('purple')" title="Purple"></div>
                                    <div class="theme-option green" onclick="selectTheme('green')" title="Green"></div>
                                </div>
                                <input type="hidden" id="editTheme" value="light">
                            </div>
                        </div>
                        
                        <div class="settings-section">
                            <h6>Notifications</h6>
                            <div class="form-check form-switch mb-2">
                                <input class="form-check-input" type="checkbox" id="editNotificationSound" checked>
                                <label class="form-check-label">Notification Sound</label>
                            </div>
                            <div class="form-check form-switch mb-2">
                                <input class="form-check-input" type="checkbox" id="editVibration" checked>
                                <label class="form-check-label">Vibration</label>
                            </div>
                        </div>
                        
                        <div class="settings-section">
                            <h6>Privacy Settings</h6>
                            <div class="privacy-option">
                                <label>Last Seen</label>
                                <select class="form-select" id="editLastSeenPrivacy">
                                    <option value="everyone">Everyone</option>
                                    <option value="contacts">My Contacts</option>
                                    <option value="nobody">Nobody</option>
                                </select>
                            </div>
                            <div class="privacy-option">
                                <label>Profile Photo</label>
                                <select class="form-select" id="editProfilePhotoPrivacy">
                                    <option value="everyone">Everyone</option>
                                    <option value="contacts">My Contacts</option>
                                    <option value="nobody">Nobody</option>
                                </select>
                            </div>
                            <div class="privacy-option">
                                <label>About</label>
                                <select class="form-select" id="editAboutPrivacy">
                                    <option value="everyone">Everyone</option>
                                    <option value="contacts">My Contacts</option>
                                    <option value="nobody">Nobody</option>
                                </select>
                            </div>
                            <div class="form-check form-switch mb-2">
                                <input class="form-check-input" type="checkbox" id="editReadReceipts" checked>
                                <label class="form-check-label">Read Receipts</label>
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-success" onclick="saveProfile()">Save Changes</button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Chat Settings Modal -->
    <div class="modal fade" id="chatSettingsModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Chat Settings</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="list-group">
                        <button class="list-group-item list-group-item-action" onclick="toggleMuteChat()">
                            <i class="fas fa-bell-slash me-2"></i> Mute Notifications
                        </button>
                        <button class="list-group-item list-group-item-action" onclick="toggleArchiveChat()">
                            <i class="fas fa-archive me-2"></i> Archive Chat
                        </button>
                        <button class="list-group-item list-group-item-action" onclick="clearChat()">
                            <i class="fas fa-trash me-2"></i> Clear Chat
                        </button>
                        <button class="list-group-item list-group-item-action text-danger" onclick="blockContact()">
                            <i class="fas fa-ban me-2"></i> Block Contact
                        </button>
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
        let profileModal = null;
        let chatSettingsModal = null;
        let availabilityTimeout = null;
        let contextMenu = null;
        
        // ========== INITIALIZATION ==========
        window.onload = function() {
            checkSession();
            newChatModal = new bootstrap.Modal(document.getElementById('newChatModal'));
            profileModal = new bootstrap.Modal(document.getElementById('profileModal'));
            chatSettingsModal = new bootstrap.Modal(document.getElementById('chatSettingsModal'));
            
            // Handle window resize for mobile
            window.addEventListener('resize', function() {
                if (window.innerWidth <= 768) {
                    if (currentChat) {
                        document.getElementById('sidebar').classList.add('hidden');
                    } else {
                        document.getElementById('sidebar').classList.remove('hidden');
                    }
                } else {
                    document.getElementById('sidebar').classList.remove('hidden');
                }
            });
        };
        
        // ========== THEME FUNCTIONS ==========
        function applyTheme(theme) {
            document.body.setAttribute('data-theme', theme);
            localStorage.setItem('theme', theme);
        }
        
        function selectTheme(theme) {
            document.getElementById('editTheme').value = theme;
            document.querySelectorAll('.theme-option').forEach(opt => opt.classList.remove('active'));
            document.querySelector(`.theme-option.${theme}`).classList.add('active');
            applyTheme(theme);
        }
        
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
                    
                    // Apply user theme
                    if (currentUser.theme) {
                        applyTheme(currentUser.theme);
                    }
                    
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
                    
                    if (currentUser.theme) {
                        applyTheme(currentUser.theme);
                    }
                    
                    loadChats();
                    startPolling();
                }
            } catch (error) {
                console.error('Session check error:', error);
            }
        }
        
        // ========== PROFILE SETTINGS ==========
        function showProfileSettings() {
            // Load current user data
            document.getElementById('editFullName').value = currentUser.full_name || '';
            document.getElementById('editAbout').value = currentUser.about || '';
            document.getElementById('profilePreview').src = 'uploads/' + (currentUser.profile_pic || 'default.jpg');
            document.getElementById('editTheme').value = currentUser.theme || 'light';
            document.getElementById('editNotificationSound').checked = currentUser.notification_sound !== 0;
            document.getElementById('editVibration').checked = currentUser.vibration !== 0;
            document.getElementById('editLastSeenPrivacy').value = currentUser.last_seen_privacy || 'everyone';
            document.getElementById('editProfilePhotoPrivacy').value = currentUser.profile_photo_privacy || 'everyone';
            document.getElementById('editAboutPrivacy').value = currentUser.about_privacy || 'everyone';
            document.getElementById('editReadReceipts').checked = currentUser.read_receipts !== 0;
            
            // Highlight selected theme
            document.querySelectorAll('.theme-option').forEach(opt => opt.classList.remove('active'));
            document.querySelector(`.theme-option.${currentUser.theme || 'light'}`).classList.add('active');
            
            profileModal.show();
        }
        
        function previewProfilePic(input) {
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    document.getElementById('profilePreview').src = e.target.result;
                };
                reader.readAsDataURL(input.files[0]);
            }
        }
        
        async function saveProfile() {
            const formData = new FormData();
            formData.append('full_name', document.getElementById('editFullName').value);
            formData.append('about', document.getElementById('editAbout').value);
            formData.append('theme', document.getElementById('editTheme').value);
            formData.append('notification_sound', document.getElementById('editNotificationSound').checked ? '1' : '0');
            formData.append('vibration', document.getElementById('editVibration').checked ? '1' : '0');
            formData.append('last_seen_privacy', document.getElementById('editLastSeenPrivacy').value);
            formData.append('profile_photo_privacy', document.getElementById('editProfilePhotoPrivacy').value);
            formData.append('about_privacy', document.getElementById('editAboutPrivacy').value);
            formData.append('read_receipts', document.getElementById('editReadReceipts').checked ? '1' : '0');
            
            const fileInput = document.getElementById('profilePicInput');
            if (fileInput.files[0]) {
                formData.append('profile_pic', fileInput.files[0]);
            }
            
            try {
                const response = await fetch('?action=api_update_profile', {
                    method: 'POST',
                    body: formData
                });
                const data = await response.json();
                
                if (data.success) {
                    showToast(data.message, 'success');
                    
                    // Update current user data
                    currentUser.full_name = document.getElementById('editFullName').value;
                    currentUser.about = document.getElementById('editAbout').value;
                    currentUser.theme = document.getElementById('editTheme').value;
                    
                    // Update profile picture if changed
                    if (fileInput.files[0]) {
                        currentUser.profile_pic = URL.createObjectURL(fileInput.files[0]);
                        document.getElementById('profilePic').src = currentUser.profile_pic;
                    }
                    
                    // Apply theme
                    applyTheme(currentUser.theme);
                    
                    profileModal.hide();
                } else {
                    showToast(data.error || 'Failed to update profile', 'error');
                }
            } catch (error) {
                console.error('Save profile error:', error);
                showToast('Failed to save profile', 'error');
            }
        }
        
        function showSettings() {
            showProfileSettings();
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
                chatItem.oncontextmenu = (e) => showChatContextMenu(e, chat);
                
                const lastMessage = chat.last_message ? 
                    (chat.last_sender_id === currentUser.id ? 'You: ' : '') + chat.last_message : 'No messages yet';
                
                const statusClass = chat.partner.status === 'online' ? 'online-indicator' : '';
                
                chatItem.innerHTML = `
                    <div class="chat-avatar-container">
                        <img src="uploads/${chat.partner.pic || 'default.jpg'}" class="chat-avatar">
                        ${chat.partner.status === 'online' ? '<span class="online-indicator"></span>' : ''}
                    </div>
                    <div class="chat-info">
                        <div class="chat-header-info">
                            <div class="chat-name">${escapeHtml(chat.partner.name)}</div>
                            <div class="chat-time">${chat.last_message_time || ''}</div>
                        </div>
                        <div class="chat-last-message">
                            <div class="message-preview">${escapeHtml(lastMessage)}</div>
                            ${chat.unread_count > 0 ? 
                                `<div class="unread-badge">${chat.unread_count}</div>` : ''}
                        </div>
                    </div>
                `;
                
                container.appendChild(chatItem);
            });
        }
        
        function showChatContextMenu(e, chat) {
            e.preventDefault();
            
            if (contextMenu) {
                contextMenu.remove();
            }
            
            contextMenu = document.createElement('div');
            contextMenu.className = 'context-menu';
            contextMenu.style.top = e.pageY + 'px';
            contextMenu.style.left = e.pageX + 'px';
            
            contextMenu.innerHTML = `
                <div class="context-menu-item" onclick="muteChat(${chat.id}, ${!chat.is_muted})">
                    <i class="fas fa-${chat.is_muted ? 'bell' : 'bell-slash'}"></i>
                    ${chat.is_muted ? 'Unmute' : 'Mute'} Notifications
                </div>
                <div class="context-menu-item" onclick="archiveChat(${chat.id}, ${!chat.is_archived})">
                    <i class="fas fa-archive"></i>
                    ${chat.is_archived ? 'Unarchive' : 'Archive'} Chat
                </div>
                <div class="context-menu-item" onclick="markAsRead(${chat.id})">
                    <i class="fas fa-check-double"></i>
                    Mark as Read
                </div>
                <div class="context-menu-item" onclick="clearChatMessages(${chat.id})">
                    <i class="fas fa-trash"></i>
                    Clear Chat
                </div>
            `;
            
            document.body.appendChild(contextMenu);
            
            document.addEventListener('click', function removeMenu() {
                if (contextMenu) {
                    contextMenu.remove();
                    contextMenu = null;
                }
                document.removeEventListener('click', removeMenu);
            });
        }
        
        async function muteChat(chatId, mute) {
            const formData = new FormData();
            formData.append('chat_id', chatId);
            formData.append('is_muted', mute ? 1 : 0);
            
            try {
                const response = await fetch('?action=api_mute_chat', {
                    method: 'POST',
                    body: formData
                });
                const data = await response.json();
                
                if (data.success) {
                    showToast(mute ? 'Chat muted' : 'Chat unmuted', 'success');
                    loadChats();
                }
            } catch (error) {
                console.error('Mute chat error:', error);
            }
        }
        
        async function archiveChat(chatId, archive) {
            const formData = new FormData();
            formData.append('chat_id', chatId);
            formData.append('is_archived', archive ? 1 : 0);
            
            try {
                const response = await fetch('?action=api_archive_chat', {
                    method: 'POST',
                    body: formData
                });
                const data = await response.json();
                
                if (data.success) {
                    showToast(archive ? 'Chat archived' : 'Chat unarchived', 'success');
                    loadChats();
                }
            } catch (error) {
                console.error('Archive chat error:', error);
            }
        }
        
        function markAsRead(chatId) {
            // Implement mark as read
            showToast('Marked as read', 'info');
        }
        
        function clearChatMessages(chatId) {
            if (confirm('Are you sure you want to clear this chat?')) {
                showToast('Chat cleared', 'success');
            }
        }
        
        async function selectChat(chat) {
            currentChat = chat;
            renderChats(); // Update active state
            
            document.getElementById('chatArea').innerHTML = `
                <div class="chat-header">
                    <button class="back-button" onclick="showChatList()">
                        <i class="fas fa-arrow-left"></i>
                    </button>
                    <img src="uploads/${chat.partner.pic || 'default.jpg'}" class="chat-header-avatar">
                    <div class="chat-header-info">
                        <h5>${escapeHtml(chat.partner.name)}</h5>
                        <small>${chat.partner.status === 'online' ? 'online' : 'offline'}</small>
                    </div>
                    <div class="chat-header-actions">
                        <i class="fas fa-phone" onclick="startCall('audio')" title="Audio Call"></i>
                        <i class="fas fa-video" onclick="startCall('video')" title="Video Call"></i>
                        <i class="fas fa-ellipsis-vertical" onclick="showChatSettings()" title="Chat Settings"></i>
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
            
            // Hide sidebar on mobile
            if (window.innerWidth <= 768) {
                document.getElementById('sidebar').classList.add('hidden');
            }
        }
        
        function showChatList() {
            document.getElementById('sidebar').classList.remove('hidden');
        }
        
        function showChatSettings() {
            chatSettingsModal.show();
        }
        
        function toggleMuteChat() {
            if (currentChat) {
                muteChat(currentChat.id, !currentChat.is_muted);
                chatSettingsModal.hide();
            }
        }
        
        function toggleArchiveChat() {
            if (currentChat) {
                archiveChat(currentChat.id, !currentChat.is_archived);
                chatSettingsModal.hide();
            }
        }
        
        function clearChat() {
            if (currentChat && confirm('Are you sure you want to clear this chat?')) {
                clearChatMessages(currentChat.id);
                chatSettingsModal.hide();
            }
        }
        
        function blockContact() {
            if (currentChat && confirm('Are you sure you want to block this contact?')) {
                // Implement block contact
                showToast('Contact blocked', 'success');
                chatSettingsModal.hide();
            }
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
                
                let statusIcon = '';
                if (isSent) {
                    if (msg.is_read) {
                        statusIcon = '<i class="fas fa-check-double" style="color: #4fc3f7;"></i>';
                    } else if (msg.is_delivered) {
                        statusIcon = '<i class="fas fa-check-double"></i>';
                    } else {
                        statusIcon = '<i class="fas fa-check"></i>';
                    }
                }
                
                const messageDiv = document.createElement('div');
                messageDiv.className = `message-wrapper ${isSent ? 'sent' : 'received'}`;
                messageDiv.oncontextmenu = (e) => showMessageContextMenu(e, msg);
                
                messageDiv.innerHTML = `
                    <div class="message-bubble">
                        <div>${escapeHtml(msg.message)}</div>
                        <div class="message-time">
                            <span>${time}</span>
                            ${statusIcon ? `<span class="message-status">${statusIcon}</span>` : ''}
                        </div>
                    </div>
                `;
                
                container.appendChild(messageDiv);
            });
            
            container.scrollTop = container.scrollHeight;
        }
        
        function showMessageContextMenu(e, message) {
            e.preventDefault();
            
            if (contextMenu) {
                contextMenu.remove();
            }
            
            contextMenu = document.createElement('div');
            contextMenu.className = 'context-menu';
            contextMenu.style.top = e.pageY + 'px';
            contextMenu.style.left = e.pageX + 'px';
            
            contextMenu.innerHTML = `
                <div class="context-menu-item" onclick="replyToMessage(${message.id})">
                    <i class="fas fa-reply"></i> Reply
                </div>
                <div class="context-menu-item" onclick="forwardMessage(${message.id})">
                    <i class="fas fa-forward"></i> Forward
                </div>
                <div class="context-menu-item" onclick="starMessage(${message.id}, ${!message.is_starred})">
                    <i class="fas fa-star${message.is_starred ? '' : '-o'}"></i>
                    ${message.is_starred ? 'Unstar' : 'Star'}
                </div>
                ${message.sender_id == currentUser.id ? `
                    <div class="context-menu-item" onclick="deleteMessage(${message.id}, 'everyone')">
                        <i class="fas fa-trash"></i> Delete for Everyone
                    </div>
                ` : ''}
                <div class="context-menu-item" onclick="deleteMessage(${message.id}, 'me')">
                    <i class="fas fa-trash"></i> Delete for Me
                </div>
            `;
            
            document.body.appendChild(contextMenu);
            
            document.addEventListener('click', function removeMenu() {
                if (contextMenu) {
                    contextMenu.remove();
                    contextMenu = null;
                }
                document.removeEventListener('click', removeMenu);
            });
        }
        
        function replyToMessage(messageId) {
            const input = document.getElementById('messageInput');
            if (input) {
                input.placeholder = 'Reply to message...';
                input.focus();
                // Store reply_to_id for actual implementation
            }
        }
        
        function forwardMessage(messageId) {
            showToast('Forward feature coming soon', 'info');
        }
        
        async function starMessage(messageId, starred) {
            const formData = new FormData();
            formData.append('message_id', messageId);
            formData.append('is_starred', starred ? 1 : 0);
            
            try {
                const response = await fetch('?action=api_star_message', {
                    method: 'POST',
                    body: formData
                });
                const data = await response.json();
                
                if (data.success) {
                    showToast(starred ? 'Message starred' : 'Message unstarred', 'success');
                }
            } catch (error) {
                console.error('Star message error:', error);
            }
        }
        
        async function deleteMessage(messageId, deleteFor) {
            if (!confirm(`Are you sure you want to delete this message ${deleteFor === 'everyone' ? 'for everyone' : 'for you'}?`)) {
                return;
            }
            
            const formData = new FormData();
            formData.append('message_id', messageId);
            formData.append('delete_for', deleteFor);
            
            try {
                const response = await fetch('?action=api_delete_message', {
                    method: 'POST',
                    body: formData
                });
                const data = await response.json();
                
                if (data.success) {
                    showToast('Message deleted', 'success');
                    if (currentChat) {
                        loadMessages(currentChat.id);
                    }
                }
            } catch (error) {
                console.error('Delete message error:', error);
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
        
        function startCall(type) {
            if (currentChat) {
                showToast(`Starting ${type} call with ${currentChat.partner.name}...`, 'info');
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
                    <div class="chat-avatar-container">
                        <img src="uploads/${chat.partner.pic || 'default.jpg'}" class="chat-avatar">
                        ${chat.partner.status === 'online' ? '<span class="online-indicator"></span>' : ''}
                    </div>
                    <div class="chat-info">
                        <div class="chat-name">${escapeHtml(chat.partner.name)}</div>
                        <div class="message-preview">${chat.last_message || 'No messages'}</div>
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
                                <span class="user-name">${escapeHtml(user.full_name)}</span>
                                ${user.is_contact ? '<span class="contact-badge">Contact</span>' : ''}
                            </div>
                            <div class="user-detail">@${escapeHtml(user.username)} · ${escapeHtml(user.phone_number)}</div>
                            ${user.about ? `<small class="text-muted">${escapeHtml(user.about.substring(0, 30))}...</small>` : ''}
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
