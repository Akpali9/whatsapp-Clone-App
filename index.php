<?php
// ============================================
// WHATSAPP CLONE - COMPLETE WITH STATUS & STORIES
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
$upload_dirs = [
    'uploads', 
    'uploads/status', 
    'uploads/files', 
    'uploads/wallpapers', 
    'uploads/themes',
    'uploads/stories'
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

// Default status backgrounds
$status_bgs = [
    'linear-gradient(135deg, #667eea 0%, #764ba2 100%)',
    'linear-gradient(135deg, #f093fb 0%, #f5576c 100%)',
    'linear-gradient(135deg, #4facfe 0%, #00f2fe 100%)',
    'linear-gradient(135deg, #43e97b 0%, #38f9d7 100%)',
    'linear-gradient(135deg, #fa709a 0%, #fee140 100%)',
    'linear-gradient(135deg, #30cfd0 0%, #330867 100%)',
    'linear-gradient(135deg, #a8edea 0%, #fed6e3 100%)',
    'linear-gradient(135deg, #ff9a9e 0%, #fecfef 100%)'
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
            theme VARCHAR(50) DEFAULT 'light',
            wallpaper VARCHAR(255) DEFAULT 'default-wallpaper.jpg',
            notification_sound BOOLEAN DEFAULT TRUE,
            vibration BOOLEAN DEFAULT TRUE,
            last_seen_privacy ENUM('everyone', 'contacts', 'nobody') DEFAULT 'everyone',
            profile_photo_privacy ENUM('everyone', 'contacts', 'nobody') DEFAULT 'everyone',
            about_privacy ENUM('everyone', 'contacts', 'nobody') DEFAULT 'everyone',
            read_receipts BOOLEAN DEFAULT TRUE,
            status_privacy ENUM('everyone', 'contacts', 'nobody') DEFAULT 'everyone',
            INDEX idx_status (status),
            INDEX idx_username (username),
            INDEX idx_phone (phone_number)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    
    // Status updates table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS status_updates (
            id INT PRIMARY KEY AUTO_INCREMENT,
            user_id INT NOT NULL,
            content TEXT,
            media_path VARCHAR(500),
            media_type ENUM('text', 'image', 'video') DEFAULT 'text',
            background_color VARCHAR(50) DEFAULT 'linear-gradient(135deg, #667eea 0%, #764ba2 100%)',
            text_color VARCHAR(20) DEFAULT '#ffffff',
            font_size INT DEFAULT 24,
            viewed_count INT DEFAULT 0,
            expires_at DATETIME DEFAULT (DATE_ADD(NOW(), INTERVAL 24 HOUR)),
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            INDEX idx_user_id (user_id),
            INDEX idx_expires_at (expires_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    
    // Status views table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS status_views (
            id INT PRIMARY KEY AUTO_INCREMENT,
            status_id INT NOT NULL,
            viewer_id INT NOT NULL,
            viewed_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (status_id) REFERENCES status_updates(id) ON DELETE CASCADE,
            FOREIGN KEY (viewer_id) REFERENCES users(id) ON DELETE CASCADE,
            UNIQUE KEY unique_view (status_id, viewer_id),
            INDEX idx_viewer (viewer_id)
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
            ['+1234567890', 'john_doe', 'John Doe', $password, 'Software Developer ✨', 'light'],
            ['+1234567891', 'jane_smith', 'Jane Smith', $password, 'Digital Artist 🎨', 'dark'],
            ['+1234567892', 'bob_wilson', 'Bob Wilson', $password, 'Music Producer 🎵', 'whatsapp'],
            ['+1234567893', 'alice_brown', 'Alice Brown', $password, 'Travel Blogger 🌍', 'blue'],
            ['+1234567894', 'mike_jones', 'Mike Jones', $password, 'Fitness Coach 💪', 'purple'],
            ['+1234567895', 'sarah_wilson', 'Sarah Wilson', $password, 'Food Blogger 🍳', 'green']
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
            "Hey! How are you? 😊",
            "I'm good, thanks! How about you?",
            "Pretty good. Want to catch up later?",
            "Sure, that sounds great! 👍",
            "What time works for you?",
            "How about 3 PM?",
            "Perfect! See you then.",
            "Can't wait! 🎉"
        ];
        
        foreach ($chats as $chat) {
            for ($i = 0; $i < 3; $i++) {
                $messageStmt->execute([$chat['id'], $chat['user1_id'], $sampleMessages[array_rand($sampleMessages)]]);
                $messageStmt->execute([$chat['id'], $chat['user2_id'], $sampleMessages[array_rand($sampleMessages)]]);
            }
        }
        
        // Add sample status updates
        $statusStmt = $pdo->prepare("
            INSERT INTO status_updates (user_id, content, media_type, background_color, text_color) 
            VALUES (?, ?, 'text', ?, '#ffffff')
        ");
        
        $statuses = [
            ["Good morning everyone! ☀️", "linear-gradient(135deg, #f093fb 0%, #f5576c 100%)"],
            ["Having a great day! ✨", "linear-gradient(135deg, #4facfe 0%, #00f2fe 100%)"],
            ["New project started 🚀", "linear-gradient(135deg, #43e97b 0%, #38f9d7 100%)"],
            ["Travel time! ✈️", "linear-gradient(135deg, #fa709a 0%, #fee140 100%)"],
            ["Working out 💪", "linear-gradient(135deg, #30cfd0 0%, #330867 100%)"],
            ["Coffee time ☕", "linear-gradient(135deg, #a8edea 0%, #fed6e3 100%)"]
        ];
        
        foreach ($userIds as $index => $user) {
            if ($index < count($statuses)) {
                $statusStmt->execute([$user['id'], $statuses[$index][0], $statuses[$index][1]]);
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

function getStatusBackgrounds() {
    return [
        'linear-gradient(135deg, #667eea 0%, #764ba2 100%)',
        'linear-gradient(135deg, #f093fb 0%, #f5576c 100%)',
        'linear-gradient(135deg, #4facfe 0%, #00f2fe 100%)',
        'linear-gradient(135deg, #43e97b 0%, #38f9d7 100%)',
        'linear-gradient(135deg, #fa709a 0%, #fee140 100%)',
        'linear-gradient(135deg, #30cfd0 0%, #330867 100%)',
        'linear-gradient(135deg, #a8edea 0%, #fed6e3 100%)',
        'linear-gradient(135deg, #ff9a9e 0%, #fecfef 100%)'
    ];
}

// ============================================
// API ROUTES
// ============================================
$action = $_GET['action'] ?? 'home';

// ... (keep all existing API routes from previous version) ...

// ============================================
// GET STATUS UPDATES API
// ============================================
if ($action == 'api_get_status') {
    header('Content-Type: application/json');
    
    if (!isLoggedIn()) {
        echo json_encode(['error' => 'Not logged in']);
        exit;
    }
    
    $userId = $_SESSION['user_id'];
    
    try {
        // Get user's privacy settings
        $userStmt = $pdo->prepare("SELECT status_privacy FROM users WHERE id = ?");
        $userStmt->execute([$userId]);
        $user = $userStmt->fetch();
        
        // Get status from contacts and own status
        $stmt = $pdo->prepare("
            SELECT s.*, 
                   u.full_name, u.username, u.profile_pic,
                   (SELECT COUNT(*) FROM status_views WHERE status_id = s.id) as views_count,
                   (SELECT viewed_at FROM status_views WHERE status_id = s.id AND viewer_id = ?) as viewed_by_me
            FROM status_updates s
            JOIN users u ON s.user_id = u.id
            LEFT JOIN contacts c ON (c.contact_id = s.user_id AND c.user_id = ?)
            WHERE s.expires_at > NOW() 
              AND (s.user_id = ? 
                   OR (c.contact_id IS NOT NULL AND c.is_blocked = 0 
                       AND (u.status_privacy = 'everyone' 
                            OR (u.status_privacy = 'contacts' AND c.contact_id IS NOT NULL))))
            ORDER BY 
                CASE WHEN s.user_id = ? THEN 0 ELSE 1 END,
                s.created_at DESC
        ");
        $stmt->execute([$userId, $userId, $userId, $userId, $userId]);
        $statuses = $stmt->fetchAll();
        
        // Group by user
        $groupedStatus = [];
        foreach ($statuses as $status) {
            $uid = $status['user_id'];
            if (!isset($groupedStatus[$uid])) {
                $groupedStatus[$uid] = [
                    'user' => [
                        'id' => $uid,
                        'full_name' => $status['full_name'],
                        'username' => $status['username'],
                        'profile_pic' => $status['profile_pic']
                    ],
                    'statuses' => [],
                    'has_unviewed' => false
                ];
            }
            
            $status['viewed'] = $status['viewed_by_me'] !== null;
            if (!$status['viewed'] && $uid != $userId) {
                $groupedStatus[$uid]['has_unviewed'] = true;
            }
            
            $groupedStatus[$uid]['statuses'][] = $status;
        }
        
        echo json_encode(array_values($groupedStatus));
    } catch(PDOException $e) {
        echo json_encode(['error' => 'Database error']);
    }
    exit;
}

// ============================================
// ADD STATUS UPDATE API
// ============================================
if ($action == 'api_add_status') {
    header('Content-Type: application/json');
    
    if (!isLoggedIn() || $_SERVER['REQUEST_METHOD'] != 'POST') {
        echo json_encode(['error' => 'Invalid request']);
        exit;
    }
    
    $userId = $_SESSION['user_id'];
    $content = trim($_POST['content'] ?? '');
    $background = $_POST['background'] ?? 'linear-gradient(135deg, #667eea 0%, #764ba2 100%)';
    $textColor = $_POST['text_color'] ?? '#ffffff';
    $fontSize = $_POST['font_size'] ?? 24;
    
    if (empty($content)) {
        echo json_encode(['error' => 'Status content cannot be empty']);
        exit;
    }
    
    try {
        $stmt = $pdo->prepare("
            INSERT INTO status_updates (user_id, content, media_type, background_color, text_color, font_size) 
            VALUES (?, ?, 'text', ?, ?, ?)
        ");
        $stmt->execute([$userId, $content, $background, $textColor, $fontSize]);
        
        echo json_encode(['success' => true, 'message' => 'Status added successfully']);
    } catch(PDOException $e) {
        echo json_encode(['error' => 'Database error']);
    }
    exit;
}

// ============================================
// VIEW STATUS API
// ============================================
if ($action == 'api_view_status') {
    header('Content-Type: application/json');
    
    if (!isLoggedIn() || $_SERVER['REQUEST_METHOD'] != 'POST') {
        echo json_encode(['error' => 'Invalid request']);
        exit;
    }
    
    $userId = $_SESSION['user_id'];
    $statusId = $_POST['status_id'] ?? 0;
    
    try {
        // Check if already viewed
        $checkStmt = $pdo->prepare("SELECT id FROM status_views WHERE status_id = ? AND viewer_id = ?");
        $checkStmt->execute([$statusId, $userId]);
        
        if (!$checkStmt->fetch()) {
            $stmt = $pdo->prepare("INSERT INTO status_views (status_id, viewer_id) VALUES (?, ?)");
            $stmt->execute([$statusId, $userId]);
            
            // Update view count
            $pdo->prepare("UPDATE status_updates SET viewed_count = viewed_count + 1 WHERE id = ?")
                ->execute([$statusId]);
        }
        
        echo json_encode(['success' => true]);
    } catch(PDOException $e) {
        echo json_encode(['error' => 'Database error']);
    }
    exit;
}

// ============================================
// DELETE STATUS API
// ============================================
if ($action == 'api_delete_status') {
    header('Content-Type: application/json');
    
    if (!isLoggedIn() || $_SERVER['REQUEST_METHOD'] != 'POST') {
        echo json_encode(['error' => 'Invalid request']);
        exit;
    }
    
    $userId = $_SESSION['user_id'];
    $statusId = $_POST['status_id'] ?? 0;
    
    try {
        $stmt = $pdo->prepare("DELETE FROM status_updates WHERE id = ? AND user_id = ?");
        $stmt->execute([$statusId, $userId]);
        
        echo json_encode(['success' => true]);
    } catch(PDOException $e) {
        echo json_encode(['error' => 'Database error']);
    }
    exit;
}

// Include all other API routes from previous version...
// (api_register, api_login, api_logout, api_get_current_user, 
//  api_update_profile, api_get_chats, api_get_messages, 
//  api_send_message, api_create_chat, api_search_users, 
//  api_add_contact, api_toggle_favorite, api_block_contact,
//  api_mute_chat, api_archive_chat, api_delete_message,
//  api_star_message, api_check_availability)
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

        @media (max-width: 768px) {
            .app-wrapper {
                margin: 0;
                height: 100vh;
                border-radius: 0;
            }
        }

        /* ========== BEAUTIFUL LOGIN PAGE ========== */
        .auth-screen {
            display: flex;
            height: 100%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            position: relative;
            overflow: hidden;
        }

        .auth-screen::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100" preserveAspectRatio="none"><path fill="rgba(255,255,255,0.05)" d="M0 0 L100 0 L100 100 L0 100 Z"/><circle cx="30" cy="30" r="10" fill="rgba(255,255,255,0.1)"/><circle cx="70" cy="70" r="15" fill="rgba(255,255,255,0.1)"/><circle cx="85" cy="20" r="8" fill="rgba(255,255,255,0.1)"/></svg>');
            background-size: cover;
            animation: float 20s ease-in-out infinite;
        }

        @keyframes float {
            0%, 100% { transform: translateY(0) rotate(0deg); }
            50% { transform: translateY(-20px) rotate(5deg); }
        }

        .auth-left {
            flex: 1;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            color: white;
            padding: 40px;
            position: relative;
            z-index: 1;
            animation: slideInLeft 1s ease;
        }

        @keyframes slideInLeft {
            from {
                opacity: 0;
                transform: translateX(-50px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }

        .auth-left i {
            font-size: 8rem;
            margin-bottom: 30px;
            filter: drop-shadow(0 10px 20px rgba(0,0,0,0.2));
            animation: pulse 2s ease-in-out infinite;
        }

        @keyframes pulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.05); }
        }

        .auth-left h1 {
            font-size: 3rem;
            font-weight: 700;
            margin-bottom: 20px;
            text-shadow: 0 10px 20px rgba(0,0,0,0.2);
        }

        .auth-left p {
            font-size: 1.2rem;
            opacity: 0.9;
            max-width: 400px;
            text-align: center;
            line-height: 1.6;
        }

        .auth-features {
            display: flex;
            gap: 30px;
            margin-top: 50px;
        }

        .feature-item {
            text-align: center;
            animation: fadeInUp 1s ease forwards;
            opacity: 0;
        }

        .feature-item:nth-child(1) { animation-delay: 0.2s; }
        .feature-item:nth-child(2) { animation-delay: 0.4s; }
        .feature-item:nth-child(3) { animation-delay: 0.6s; }

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .feature-item i {
            font-size: 2rem;
            margin-bottom: 10px;
            background: rgba(255,255,255,0.2);
            width: 60px;
            height: 60px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 15px;
        }

        .feature-item span {
            display: block;
            font-weight: 600;
            margin-bottom: 5px;
        }

        .feature-item small {
            opacity: 0.8;
            font-size: 0.9rem;
        }

        .auth-right {
            width: 450px;
            background: rgba(255,255,255,0.95);
            backdrop-filter: blur(10px);
            padding: 50px 40px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            position: relative;
            z-index: 1;
            animation: slideInRight 1s ease;
            box-shadow: -10px 0 30px rgba(0,0,0,0.1);
        }

        @keyframes slideInRight {
            from {
                opacity: 0;
                transform: translateX(50px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }

        @media (max-width: 992px) {
            .auth-left {
                display: none;
            }
            .auth-right {
                width: 100%;
                margin: 0 auto;
            }
        }

        .auth-logo-mobile {
            display: none;
            text-align: center;
            margin-bottom: 30px;
        }

        .auth-logo-mobile i {
            font-size: 3rem;
            color: var(--whatsapp-green);
            margin-bottom: 10px;
        }

        @media (max-width: 992px) {
            .auth-logo-mobile {
                display: block;
            }
        }

        .auth-tabs {
            display: flex;
            gap: 20px;
            margin-bottom: 30px;
            border-bottom: 2px solid rgba(0,0,0,0.1);
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
            position: relative;
        }

        .auth-tab::after {
            content: '';
            position: absolute;
            bottom: -12px;
            left: 0;
            width: 100%;
            height: 3px;
            background: var(--whatsapp-teal);
            transform: scaleX(0);
            transition: transform 0.3s;
        }

        .auth-tab.active {
            color: var(--whatsapp-teal);
        }

        .auth-tab.active::after {
            transform: scaleX(1);
        }

        .form-group {
            margin-bottom: 20px;
            position: relative;
        }

        .form-group i {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #999;
            transition: color 0.3s;
        }

        .form-control {
            border: 2px solid #e0e0e0;
            border-radius: 12px;
            padding: 15px 15px 15px 45px;
            font-size: 14px;
            transition: all 0.3s;
            width: 100%;
        }

        .form-control:focus {
            border-color: var(--whatsapp-teal);
            box-shadow: 0 0 0 4px rgba(18, 140, 126, 0.1);
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
            padding-left: 45px;
        }

        .success-message {
            color: #28a745;
            font-size: 12px;
            margin-top: 5px;
            display: none;
            padding-left: 45px;
        }

        .btn-success {
            background: linear-gradient(135deg, var(--whatsapp-teal), var(--whatsapp-dark-green));
            border: none;
            padding: 15px;
            font-size: 16px;
            font-weight: 600;
            border-radius: 12px;
            transition: all 0.3s;
            color: white;
            width: 100%;
            cursor: pointer;
            position: relative;
            overflow: hidden;
        }

        .btn-success::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
            transition: left 0.5s;
        }

        .btn-success:hover::before {
            left: 100%;
        }

        .btn-success:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(18, 140, 126, 0.3);
        }

        .btn-success:disabled {
            opacity: 0.7;
            cursor: not-allowed;
        }

        .loading-spinner {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 3px solid rgba(255,255,255,0.3);
            border-top: 3px solid white;
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin-right: 10px;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        .alert {
            border-radius: 12px;
            padding: 15px;
            margin-bottom: 20px;
            display: none;
            animation: shake 0.5s ease;
        }

        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            10%, 30%, 50%, 70%, 90% { transform: translateX(-5px); }
            20%, 40%, 60%, 80% { transform: translateX(5px); }
        }

        /* ========== STATUS STYLES ========== */
        .status-ring {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            padding: 3px;
            background: linear-gradient(45deg, var(--whatsapp-green), var(--whatsapp-teal));
            margin-right: 15px;
            animation: rotate 10s linear infinite;
        }

        .status-ring.viewed {
            background: #8696a0;
        }

        @keyframes rotate {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }

        .status-avatar {
            width: 54px;
            height: 54px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid white;
        }

        .status-item {
            display: flex;
            align-items: center;
            padding: 12px 15px;
            cursor: pointer;
            transition: background 0.3s;
            border-bottom: 1px solid var(--border-color);
        }

        .status-item:hover {
            background: rgba(0,0,0,0.05);
        }

        .status-info {
            flex: 1;
        }

        .status-info h6 {
            margin: 0;
            color: var(--text-primary);
            font-weight: 600;
        }

        .status-info small {
            color: var(--text-secondary);
            font-size: 12px;
        }

        .status-time {
            font-size: 11px;
            color: var(--text-secondary);
        }

        .my-status {
            display: flex;
            align-items: center;
            padding: 15px;
            background: var(--bg-secondary);
            border-bottom: 1px solid var(--border-color);
        }

        .my-status-avatar {
            position: relative;
            margin-right: 15px;
        }

        .my-status-avatar img {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            object-fit: cover;
        }

        .add-status-btn {
            position: absolute;
            bottom: 0;
            right: 0;
            background: var(--whatsapp-teal);
            color: white;
            width: 22px;
            height: 22px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
            border: 2px solid var(--bg-secondary);
            cursor: pointer;
        }

        .status-section {
            padding: 15px;
            color: var(--text-secondary);
            font-size: 14px;
            font-weight: 600;
        }

        /* Status Viewer Modal */
        .status-viewer {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.95);
            z-index: 10000;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .status-container {
            width: 100%;
            max-width: 400px;
            height: 700px;
            background: #000;
            border-radius: 20px;
            position: relative;
            overflow: hidden;
        }

        .status-header {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            padding: 20px;
            background: linear-gradient(to bottom, rgba(0,0,0,0.5), transparent);
            color: white;
            z-index: 2;
            display: flex;
            align-items: center;
        }

        .status-header img {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            object-fit: cover;
            margin-right: 15px;
            border: 2px solid white;
        }

        .status-progress {
            position: absolute;
            top: 10px;
            left: 20px;
            right: 20px;
            display: flex;
            gap: 5px;
            z-index: 2;
        }

        .progress-bar {
            height: 3px;
            background: rgba(255,255,255,0.3);
            flex: 1;
            border-radius: 3px;
            overflow: hidden;
        }

        .progress-fill {
            height: 100%;
            background: white;
            width: 0%;
            transition: width 5s linear;
        }

        .status-content {
            height: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 40px;
            color: white;
            font-size: 24px;
            text-align: center;
            position: relative;
        }

        .status-nav {
            position: absolute;
            top: 50%;
            transform: translateY(-50%);
            width: 50px;
            height: 50px;
            background: rgba(255,255,255,0.2);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            cursor: pointer;
            z-index: 3;
            transition: all 0.3s;
        }

        .status-nav:hover {
            background: rgba(255,255,255,0.3);
        }

        .status-nav.prev {
            left: 10px;
        }

        .status-nav.next {
            right: 10px;
        }

        .status-footer {
            position: absolute;
            bottom: 20px;
            left: 0;
            right: 0;
            text-align: center;
            color: rgba(255,255,255,0.8);
            font-size: 14px;
            z-index: 2;
        }

        .status-views {
            position: absolute;
            bottom: 20px;
            right: 20px;
            color: rgba(255,255,255,0.8);
            font-size: 12px;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .status-delete {
            position: absolute;
            bottom: 20px;
            left: 20px;
            color: #ff4444;
            cursor: pointer;
            font-size: 14px;
            z-index: 3;
        }

        /* Status Creation Modal */
        .status-bg-preview {
            width: 100%;
            height: 300px;
            border-radius: 15px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            transition: all 0.3s;
        }

        .status-text-input {
            background: transparent;
            border: none;
            border-bottom: 2px solid rgba(255,255,255,0.5);
            color: white;
            font-size: 24px;
            text-align: center;
            width: 100%;
            padding: 10px;
            outline: none;
        }

        .status-text-input:focus {
            border-bottom-color: white;
        }

        .bg-option {
            width: 40px;
            height: 40px;
            border-radius: 10px;
            margin: 5px;
            cursor: pointer;
            border: 2px solid transparent;
            transition: all 0.3s;
        }

        .bg-option:hover {
            transform: scale(1.1);
        }

        .bg-option.active {
            border-color: var(--whatsapp-teal);
        }

        /* ... (keep all other styles from previous version) ... */
    </style>
</head>
<body data-theme="light">
    <div class="app-wrapper">
        <!-- Auth Screen - BEAUTIFUL VERSION -->
        <div id="authScreen" class="auth-screen">
            <div class="auth-left">
                <i class="fab fa-whatsapp"></i>
                <h1>Welcome Back!</h1>
                <p>Connect with friends and family in real-time with our secure messaging platform</p>
                
                <div class="auth-features">
                    <div class="feature-item">
                        <i class="fas fa-lock"></i>
                        <span>End-to-End Encrypted</span>
                        <small>Your messages are secure</small>
                    </div>
                    <div class="feature-item">
                        <i class="fas fa-bolt"></i>
                        <span>Lightning Fast</span>
                        <small>Real-time messaging</small>
                    </div>
                    <div class="feature-item">
                        <i class="fas fa-image"></i>
                        <span>Share Moments</span>
                        <small>Photos, videos & status</small>
                    </div>
                </div>
            </div>
            
            <div class="auth-right">
                <div class="auth-logo-mobile">
                    <i class="fab fa-whatsapp"></i>
                    <h3>WhatsApp Clone</h3>
                </div>
                
                <div class="auth-tabs">
                    <div class="auth-tab active" onclick="switchTab('login')">Login</div>
                    <div class="auth-tab" onclick="switchTab('register')">Register</div>
                </div>
                
                <!-- Login Form -->
                <div id="loginForm">
                    <div id="loginAlert" class="alert alert-danger"></div>
                    
                    <div class="form-group">
                        <i class="fas fa-user"></i>
                        <input type="text" class="form-control" id="loginIdentifier" placeholder="Username or Phone">
                        <div class="error-message" id="loginIdentifierError"></div>
                    </div>
                    
                    <div class="form-group">
                        <i class="fas fa-lock"></i>
                        <input type="password" class="form-control" id="loginPassword" placeholder="Password">
                        <div class="error-message" id="loginPasswordError"></div>
                    </div>
                    
                    <button class="btn-success" onclick="handleLogin()" id="loginBtn">
                        <span>Login</span>
                    </button>
                    
                    <div class="mt-4 text-center">
                        <small class="text-muted">Test credentials: john_doe / test123</small>
                    </div>
                </div>
                
                <!-- Register Form -->
                <div id="registerForm" style="display: none;">
                    <div id="registerAlert" class="alert alert-danger"></div>
                    <div id="registerSuccess" class="alert alert-success"></div>
                    
                    <div class="form-group">
                        <i class="fas fa-user-circle"></i>
                        <input type="text" class="form-control" id="regFullName" placeholder="Full Name">
                        <div class="error-message" id="regFullNameError"></div>
                    </div>
                    
                    <div class="form-group">
                        <i class="fas fa-at"></i>
                        <input type="text" class="form-control" id="regUsername" placeholder="Username" onkeyup="checkAvailability('username')">
                        <div class="error-message" id="regUsernameError"></div>
                        <div class="success-message" id="regUsernameSuccess">Username is available</div>
                    </div>
                    
                    <div class="form-group">
                        <i class="fas fa-phone"></i>
                        <input type="tel" class="form-control" id="regPhone" placeholder="Phone Number" onkeyup="checkAvailability('phone')">
                        <div class="error-message" id="regPhoneError"></div>
                        <div class="success-message" id="regPhoneSuccess">Phone number is available</div>
                    </div>
                    
                    <div class="form-group">
                        <i class="fas fa-key"></i>
                        <input type="password" class="form-control" id="regPassword" placeholder="Password (min. 6 characters)">
                        <div class="error-message" id="regPasswordError"></div>
                    </div>
                    
                    <button class="btn-success" onclick="handleRegister()" id="registerBtn">
                        <span>Create Account</span>
                    </button>
                </div>
            </div>
        </div>
        
        <!-- Main App -->
        <div id="mainApp" class="main-app" style="display: none;">
            <!-- Sidebar with Status Tab -->
            <div class="sidebar" id="sidebar">
                <div class="sidebar-header">
                    <img id="profilePic" src="uploads/default.jpg" alt="Profile" class="profile-thumb" onclick="showProfileSettings()">
                    <span class="app-title">WhatsApp</span>
                    <div class="header-icons">
                        <i class="fas fa-circle" onclick="switchMainTab('status')" title="Status" style="color: var(--whatsapp-green);"></i>
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
                <div id="chatsList" class="chats-list">
                    <div class="empty-state">
                        <i class="fas fa-comments"></i>
                        <h6>No chats yet</h6>
                        <p>Click the + button to start a new conversation</p>
                    </div>
                </div>
                
                <!-- Status List -->
                <div id="statusList" class="chats-list" style="display: none;">
                    <div class="my-status">
                        <div class="my-status-avatar">
                            <img id="myStatusPic" src="uploads/default.jpg" alt="My Status">
                            <div class="add-status-btn" onclick="showAddStatusModal()">
                                <i class="fas fa-plus"></i>
                            </div>
                        </div>
                        <div class="status-info">
                            <h6>My status</h6>
                            <small>Tap to add status update</small>
                        </div>
                    </div>
                    
                    <div class="status-section">Recent updates</div>
                    <div id="statusUpdatesList"></div>
                </div>
                
                <!-- Calls List -->
                <div id="callsList" class="chats-list" style="display: none;">
                    <div class="empty-state">
                        <i class="fas fa-phone"></i>
                        <h6>No calls yet</h6>
                        <p>Your call history will appear here</p>
                    </div>
                </div>
            </div>
            
            <!-- Chat Area -->
            <div class="chat-area" id="chatArea">
                <div class="empty-state">
                    <i class="fab fa-whatsapp"></i>
                    <h4>Welcome to WhatsApp Clone</h4>
                    <p id="welcomeUser"></p>
                    <p>Select a chat or view status updates</p>
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
                    <div id="userSearchResults" style="max-height: 400px; overflow-y: auto;"></div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Add Status Modal -->
    <div class="modal fade" id="addStatusModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add Status Update</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div id="statusPreview" class="status-bg-preview" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                        <input type="text" class="status-text-input" id="statusContent" placeholder="What's on your mind?" maxlength="200">
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Background</label>
                        <div class="d-flex flex-wrap">
                            <div class="bg-option active" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);" onclick="selectStatusBg(this, 'linear-gradient(135deg, #667eea 0%, #764ba2 100%)')"></div>
                            <div class="bg-option" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);" onclick="selectStatusBg(this, 'linear-gradient(135deg, #f093fb 0%, #f5576c 100%)')"></div>
                            <div class="bg-option" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);" onclick="selectStatusBg(this, 'linear-gradient(135deg, #4facfe 0%, #00f2fe 100%)')"></div>
                            <div class="bg-option" style="background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);" onclick="selectStatusBg(this, 'linear-gradient(135deg, #43e97b 0%, #38f9d7 100%)')"></div>
                            <div class="bg-option" style="background: linear-gradient(135deg, #fa709a 0%, #fee140 100%);" onclick="selectStatusBg(this, 'linear-gradient(135deg, #fa709a 0%, #fee140 100%)')"></div>
                            <div class="bg-option" style="background: linear-gradient(135deg, #30cfd0 0%, #330867 100%);" onclick="selectStatusBg(this, 'linear-gradient(135deg, #30cfd0 0%, #330867 100%)')"></div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Text Color</label>
                        <div class="d-flex gap-2">
                            <div class="bg-option" style="background: #ffffff; border: 1px solid #ddd;" onclick="document.getElementById('statusPreview').style.color = '#ffffff'; document.getElementById('statusContent').style.color = '#ffffff'"></div>
                            <div class="bg-option" style="background: #000000;" onclick="document.getElementById('statusPreview').style.color = '#000000'; document.getElementById('statusContent').style.color = '#000000'"></div>
                            <div class="bg-option" style="background: #ffeb3b;" onclick="document.getElementById('statusPreview').style.color = '#ffeb3b'; document.getElementById('statusContent').style.color = '#ffeb3b'"></div>
                            <div class="bg-option" style="background: #ff5722;" onclick="document.getElementById('statusPreview').style.color = '#ff5722'; document.getElementById('statusContent').style.color = '#ff5722'"></div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-success" onclick="addStatus()">Post Status</button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Status Viewer Modal -->
    <div id="statusViewer" class="status-viewer" style="display: none;">
        <div class="status-container" id="statusContainer">
            <div class="status-progress" id="statusProgress"></div>
            <div class="status-header">
                <img id="viewerUserPic" src="" alt="">
                <div>
                    <h6 id="viewerUserName" style="margin: 0;"></h6>
                    <small id="viewerTime"></small>
                </div>
            </div>
            <div class="status-nav prev" onclick="navigateStatus('prev')">
                <i class="fas fa-chevron-left"></i>
            </div>
            <div class="status-nav next" onclick="navigateStatus('next')">
                <i class="fas fa-chevron-right"></i>
            </div>
            <div class="status-content" id="statusContent">
                <div id="statusTextView"></div>
            </div>
            <div class="status-footer">
                <span id="statusCounter">1/3</span>
            </div>
            <div class="status-views" id="statusViews">
                <i class="fas fa-eye"></i> <span id="viewCount">0</span>
            </div>
            <div class="status-delete" id="statusDelete" onclick="deleteCurrentStatus()" style="display: none;">
                <i class="fas fa-trash"></i> Delete
            </div>
        </div>
    </div>
    
    <!-- ... (keep all other modals from previous version) ... -->
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // ========== GLOBAL VARIABLES ==========
        let currentUser = null;
        let currentChat = null;
        let currentStatusGroup = null;
        let currentStatusIndex = 0;
        let statusTimer = null;
        let chats = [];
        let statusGroups = [];
        let messages = [];
        let pollingInterval = null;
        let newChatModal = null;
        let profileModal = null;
        let addStatusModal = null;
        let availabilityTimeout = null;
        let contextMenu = null;
        let statusViewer = null;
        
        // ========== INITIALIZATION ==========
        window.onload = function() {
            checkSession();
            newChatModal = new bootstrap.Modal(document.getElementById('newChatModal'));
            addStatusModal = new bootstrap.Modal(document.getElementById('addStatusModal'));
            statusViewer = document.getElementById('statusViewer');
            
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
        
        // ========== TAB SWITCHING ==========
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
        
        function switchMainTab(tab) {
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
                document.getElementById('callsList').style.display = 'block';
            }
        }
        
        // ========== STATUS FUNCTIONS ==========
        async function loadStatus() {
            try {
                const response = await fetch('?action=api_get_status');
                const data = await response.json();
                
                if (data.error) {
                    console.error(data.error);
                    return;
                }
                
                statusGroups = data;
                renderStatus();
            } catch (error) {
                console.error('Load status error:', error);
            }
        }
        
        function renderStatus() {
            const container = document.getElementById('statusUpdatesList');
            
            if (!statusGroups || statusGroups.length === 0) {
                container.innerHTML = '<div class="empty-state p-3">No status updates from contacts</div>';
                return;
            }
            
            container.innerHTML = '';
            statusGroups.forEach(group => {
                if (group.user.id === currentUser.id) return; // Skip own status (already shown in "My status")
                
                const statusItem = document.createElement('div');
                statusItem.className = 'status-item';
                statusItem.onclick = () => openStatusViewer(group);
                
                const latestStatus = group.statuses[0];
                const timeAgo = formatTime(latestStatus.created_at);
                
                statusItem.innerHTML = `
                    <div class="status-ring ${!group.has_unviewed ? 'viewed' : ''}">
                        <img src="uploads/${group.user.profile_pic || 'default.jpg'}" class="status-avatar">
                    </div>
                    <div class="status-info">
                        <h6>${escapeHtml(group.user.full_name)}</h6>
                        <small>${timeAgo}</small>
                    </div>
                    <div class="status-time">${group.statuses.length} ${group.statuses.length > 1 ? 'updates' : 'update'}</div>
                `;
                
                container.appendChild(statusItem);
            });
        }
        
        function showAddStatusModal() {
            document.getElementById('statusContent').value = '';
            document.getElementById('statusPreview').style.background = 'linear-gradient(135deg, #667eea 0%, #764ba2 100%)';
            document.getElementById('statusPreview').style.color = '#ffffff';
            document.getElementById('statusContent').style.color = '#ffffff';
            document.querySelectorAll('.bg-option').forEach(opt => opt.classList.remove('active'));
            document.querySelector('.bg-option').classList.add('active');
            addStatusModal.show();
        }
        
        function selectStatusBg(element, bg) {
            document.querySelectorAll('.bg-option').forEach(opt => opt.classList.remove('active'));
            element.classList.add('active');
            document.getElementById('statusPreview').style.background = bg;
        }
        
        async function addStatus() {
            const content = document.getElementById('statusContent').value.trim();
            
            if (!content) {
                showToast('Please enter status text', 'error');
                return;
            }
            
            const bg = document.getElementById('statusPreview').style.background;
            const textColor = document.getElementById('statusPreview').style.color;
            
            const formData = new FormData();
            formData.append('content', content);
            formData.append('background', bg);
            formData.append('text_color', textColor);
            formData.append('font_size', 24);
            
            try {
                const response = await fetch('?action=api_add_status', {
                    method: 'POST',
                    body: formData
                });
                const data = await response.json();
                
                if (data.success) {
                    showToast('Status added successfully', 'success');
                    addStatusModal.hide();
                    loadStatus();
                    switchMainTab('status');
                } else {
                    showToast(data.error || 'Failed to add status', 'error');
                }
            } catch (error) {
                console.error('Add status error:', error);
                showToast('Failed to add status', 'error');
            }
        }
        
        function openStatusViewer(group) {
            currentStatusGroup = group;
            currentStatusIndex = 0;
            
            statusViewer.style.display = 'flex';
            renderStatusViewer();
            startStatusTimer();
        }
        
        function renderStatusViewer() {
            const status = currentStatusGroup.statuses[currentStatusIndex];
            const isOwn = currentStatusGroup.user.id === currentUser.id;
            
            document.getElementById('viewerUserPic').src = 'uploads/' + (currentStatusGroup.user.profile_pic || 'default.jpg');
            document.getElementById('viewerUserName').innerText = currentStatusGroup.user.full_name;
            document.getElementById('viewerTime').innerText = formatTime(status.created_at);
            document.getElementById('viewCount').innerText = status.views_count || 0;
            document.getElementById('statusCounter').innerHTML = `${currentStatusIndex + 1}/${currentStatusGroup.statuses.length}`;
            
            // Show/hide delete button for own status
            document.getElementById('statusDelete').style.display = isOwn ? 'block' : 'none';
            
            // Set status content
            const statusContent = document.getElementById('statusTextView');
            statusContent.innerHTML = status.content;
            statusContent.style.background = status.background_color || 'linear-gradient(135deg, #667eea 0%, #764ba2 100%)';
            statusContent.style.color = status.text_color || '#ffffff';
            statusContent.style.fontSize = (status.font_size || 24) + 'px';
            statusContent.style.padding = '40px';
            statusContent.style.borderRadius = '10px';
            statusContent.style.width = '100%';
            statusContent.style.height = '100%';
            statusContent.style.display = 'flex';
            statusContent.style.alignItems = 'center';
            statusContent.style.justifyContent = 'center';
            
            // Create progress bars
            const progressContainer = document.getElementById('statusProgress');
            progressContainer.innerHTML = '';
            
            currentStatusGroup.statuses.forEach((s, index) => {
                const bar = document.createElement('div');
                bar.className = 'progress-bar';
                const fill = document.createElement('div');
                fill.className = 'progress-fill';
                if (index === currentStatusIndex) {
                    fill.style.width = '0%';
                    setTimeout(() => { fill.style.width = '100%'; }, 100);
                } else if (index < currentStatusIndex) {
                    fill.style.width = '100%';
                } else {
                    fill.style.width = '0%';
                }
                bar.appendChild(fill);
                progressContainer.appendChild(bar);
            });
            
            // Mark as viewed
            if (!isOwn && !status.viewed) {
                markStatusViewed(status.id);
            }
        }
        
        async function markStatusViewed(statusId) {
            const formData = new FormData();
            formData.append('status_id', statusId);
            
            try {
                await fetch('?action=api_view_status', {
                    method: 'POST',
                    body: formData
                });
            } catch (error) {
                console.error('Mark status viewed error:', error);
            }
        }
        
        function startStatusTimer() {
            if (statusTimer) clearTimeout(statusTimer);
            
            statusTimer = setTimeout(() => {
                navigateStatus('next');
            }, 5000);
        }
        
        function navigateStatus(direction) {
            if (statusTimer) clearTimeout(statusTimer);
            
            if (direction === 'next') {
                if (currentStatusIndex < currentStatusGroup.statuses.length - 1) {
                    currentStatusIndex++;
                    renderStatusViewer();
                    startStatusTimer();
                } else {
                    closeStatusViewer();
                }
            } else if (direction === 'prev') {
                if (currentStatusIndex > 0) {
                    currentStatusIndex--;
                    renderStatusViewer();
                    startStatusTimer();
                }
            }
        }
        
        function closeStatusViewer() {
            statusViewer.style.display = 'none';
            if (statusTimer) clearTimeout(statusTimer);
            loadStatus(); // Refresh status list
        }
        
        async function deleteCurrentStatus() {
            const status = currentStatusGroup.statuses[currentStatusIndex];
            
            if (!confirm('Are you sure you want to delete this status?')) {
                return;
            }
            
            const formData = new FormData();
            formData.append('status_id', status.id);
            
            try {
                const response = await fetch('?action=api_delete_status', {
                    method: 'POST',
                    body: formData
                });
                const data = await response.json();
                
                if (data.success) {
                    showToast('Status deleted', 'success');
                    
                    // Remove from current group
                    currentStatusGroup.statuses.splice(currentStatusIndex, 1);
                    
                    if (currentStatusGroup.statuses.length === 0) {
                        closeStatusViewer();
                    } else {
                        if (currentStatusIndex >= currentStatusGroup.statuses.length) {
                            currentStatusIndex = currentStatusGroup.statuses.length - 1;
                        }
                        renderStatusViewer();
                        startStatusTimer();
                    }
                    
                    loadStatus(); // Refresh list
                }
            } catch (error) {
                console.error('Delete status error:', error);
            }
        }
        
        // Handle keyboard navigation
        document.addEventListener('keydown', function(e) {
            if (statusViewer.style.display === 'flex') {
                if (e.key === 'ArrowLeft') {
                    navigateStatus('prev');
                } else if (e.key === 'ArrowRight') {
                    navigateStatus('next');
                } else if (e.key === 'Escape') {
                    closeStatusViewer();
                }
            }
        });
        
        // ========== AUTH FUNCTIONS ==========
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
                    document.getElementById('myStatusPic').src = 'uploads/' + (currentUser.profile_pic || 'default.jpg');
                    document.getElementById('welcomeUser').innerHTML = `Welcome, ${currentUser.full_name}!`;
                    
                    // Apply user theme
                    if (currentUser.theme) {
                        applyTheme(currentUser.theme);
                    }
                    
                    loadChats();
                    loadStatus();
                    startPolling();
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
                        document.getElementById('registerAlert').innerHTML = data.error || 'Registration failed';
                        document.getElementById('registerAlert').style.display = 'block';
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
                    document.getElementById('myStatusPic').src = 'uploads/' + (currentUser.profile_pic || 'default.jpg');
                    document.getElementById('welcomeUser').innerHTML = `Welcome back, ${currentUser.full_name}!`;
                    
                    if (currentUser.theme) {
                        applyTheme(currentUser.theme);
                    }
                    
                    loadChats();
                    loadStatus();
                    startPolling();
                }
            } catch (error) {
                console.error('Session check error:', error);
            }
        }
        
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
        function handleSearch(query) {
            const activeTab = document.querySelector('.sidebar-tab.active').innerText;
            
            if (activeTab.includes('Chats')) {
                searchChats(query);
            } else if (activeTab.includes('Status')) {
                // Search status
            }
        }
        
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
                            <div class="user-detail">@${escapeHtml(user.username)}</div>
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
                        switchMainTab('chats');
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
                    const activeTab = document.querySelector('.sidebar-tab.active')?.innerText;
                    
                    if (activeTab?.includes('Chats')) {
                        loadChats();
                        if (currentChat) {
                            loadMessages(currentChat.id);
                        }
                    } else if (activeTab?.includes('Status')) {
                        loadStatus();
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
    </script>
</body>
</html>
