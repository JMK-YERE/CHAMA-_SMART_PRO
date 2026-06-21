<?php
// chat.php - Live Chat System
require_once 'config.php';

if(!isLoggedIn()) {
    header('Location: index.php');
    exit();
}

$user = getCurrentUser();
$error = null;
$success = null;

// Handle POST actions
if($_SERVER['REQUEST_METHOD'] === 'POST') {
    if(isset($_POST['action'])) {
        switch($_POST['action']) {
            // Create new private chat
            case 'create_chat':
                $member_id = intval($_POST['member_id'] ?? 0);
                $message = trim($_POST['message'] ?? '');
                
                if($member_id > 0 && $member_id != $user['id']) {
                    // Check if chat exists
                    $stmt = $pdo->prepare("SELECT r.id FROM chat_rooms r 
                                          JOIN chat_participants p1 ON r.id = p1.room_id 
                                          JOIN chat_participants p2 ON r.id = p2.room_id 
                                          WHERE r.room_type = 'private' 
                                          AND p1.member_id = ? AND p2.member_id = ?");
                    $stmt->execute([$user['id'], $member_id]);
                    $room = $stmt->fetch();
                    
                    if(!$room) {
                        // Create new private room
                        $stmt = $pdo->prepare("INSERT INTO chat_rooms (room_name, room_type, created_by) VALUES (?, 'private', ?)");
                        $room_name = 'chat_' . $user['id'] . '_' . $member_id . '_' . time();
                        $stmt->execute([$room_name, $user['id']]);
                        $room_id = $pdo->lastInsertId();
                        
                        // Add participants
                        $stmt = $pdo->prepare("INSERT INTO chat_participants (room_id, member_id) VALUES (?, ?), (?, ?)");
                        $stmt->execute([$room_id, $user['id'], $room_id, $member_id]);
                        
                        // Log activity
                        logActivity($user['id'], 'Created chat room', "Room ID: $room_id, With: $member_id");
                    } else {
                        $room_id = $room['id'];
                    }
                    
                    // Send initial message if provided
                    if(!empty($message)) {
                        $stmt = $pdo->prepare("INSERT INTO chat_messages (room_id, sender_id, message) VALUES (?, ?, ?)");
                        $stmt->execute([$room_id, $user['id'], $message]);
                        
                        // Send notification to recipient
                        $recipient = getMember($member_id);
                        if($recipient) {
                            $msg = "Ujumbe mpya kutoka " . $user['first_name'] . " " . $user['last_name'] . ":\n" . $message;
                            sendWhatsAppMessage($recipient['phone'], $msg);
                            sendSMS($recipient['phone'], $msg);
                        }
                    }
                    
                    $success = 'Mazungumzo yameanzishwa!';
                    header('Location: chat.php?room=' . $room_id . '&success=' . urlencode($success));
                    exit();
                }
                break;
                
            // Send message
            case 'send_message':
                $room_id = intval($_POST['room_id'] ?? 0);
                $message = trim($_POST['message'] ?? '');
                
                if($room_id > 0 && !empty($message)) {
                    // Check if user is participant
                    $stmt = $pdo->prepare("SELECT * FROM chat_participants WHERE room_id = ? AND member_id = ?");
                    $stmt->execute([$room_id, $user['id']]);
                    if($stmt->fetch()) {
                        $stmt = $pdo->prepare("INSERT INTO chat_messages (room_id, sender_id, message) VALUES (?, ?, ?)");
                        if($stmt->execute([$room_id, $user['id'], $message])) {
                            // Get room members for notification
                            $stmt = $pdo->prepare("SELECT p.member_id, u.phone, u.first_name, u.last_name 
                                                  FROM chat_participants p 
                                                  JOIN wanakikundi u ON p.member_id = u.id 
                                                  WHERE p.room_id = ? AND p.member_id != ?");
                            $stmt->execute([$room_id, $user['id']]);
                            $members = $stmt->fetchAll();
                            
                            foreach($members as $member) {
                                $msg = "Ujumbe mpya kutoka " . $user['first_name'] . " " . $user['last_name'] . ":\n" . $message;
                                sendWhatsAppMessage($member['phone'], $msg);
                                sendSMS($member['phone'], $msg);
                            }
                            
                            logActivity($user['id'], 'Sent chat message', "Room ID: $room_id");
                        }
                    }
                }
                header('Location: chat.php?room=' . $room_id);
                exit();
                break;
        }
    }
}

// Get current room
$current_room_id = isset($_GET['room']) ? intval($_GET['room']) : 0;
$current_room = null;
$messages = [];
$other_participant = null;

if($current_room_id > 0) {
    // Check if user is participant
    $stmt = $pdo->prepare("SELECT * FROM chat_participants WHERE room_id = ? AND member_id = ?");
    $stmt->execute([$current_room_id, $user['id']]);
    if($stmt->fetch()) {
        // Get room details
        $stmt = $pdo->prepare("SELECT * FROM chat_rooms WHERE id = ?");
        $stmt->execute([$current_room_id]);
        $current_room = $stmt->fetch();
        
        // Get other participant for private chats
        if($current_room && $current_room['room_type'] === 'private') {
            $stmt = $pdo->prepare("SELECT u.* FROM wanakikundi u 
                                  JOIN chat_participants p ON u.id = p.member_id 
                                  WHERE p.room_id = ? AND p.member_id != ?");
            $stmt->execute([$current_room_id, $user['id']]);
            $other_participant = $stmt->fetch();
        }
        
        // Get messages
        $stmt = $pdo->prepare("SELECT m.*, CONCAT(u.first_name, ' ', u.last_name) as sender_name 
                              FROM chat_messages m 
                              JOIN wanakikundi u ON m.sender_id = u.id 
                              WHERE m.room_id = ? 
                              ORDER BY m.created_at ASC");
        $stmt->execute([$current_room_id]);
        $messages = $stmt->fetchAll();
        
        // Mark messages as read
        $stmt = $pdo->prepare("UPDATE chat_messages SET read_at = NOW() WHERE room_id = ? AND sender_id != ? AND read_at IS NULL");
        $stmt->execute([$current_room_id, $user['id']]);
        
        // Update last read
        $stmt = $pdo->prepare("UPDATE chat_participants SET last_read_at = NOW() WHERE room_id = ? AND member_id = ?");
        $stmt->execute([$current_room_id, $user['id']]);
    } else {
        $current_room_id = 0;
    }
}

// Get all chat rooms for user
$rooms = [];
$stmt = $pdo->prepare("SELECT r.*, 
                       (SELECT COUNT(*) FROM chat_messages m WHERE m.room_id = r.id AND m.read_at IS NULL AND m.sender_id != ?) as unread_count,
                       (SELECT message FROM chat_messages WHERE room_id = r.id ORDER BY created_at DESC LIMIT 1) as last_message,
                       (SELECT created_at FROM chat_messages WHERE room_id = r.id ORDER BY created_at DESC LIMIT 1) as last_message_time
                       FROM chat_rooms r 
                       JOIN chat_participants p ON r.id = p.room_id 
                       WHERE p.member_id = ? AND r.status = 'active'
                       ORDER BY r.created_at DESC");
$stmt->execute([$user['id'], $user['id']]);
$rooms = $stmt->fetchAll();

// Get other participant names for private chats
foreach($rooms as &$room) {
    if($room['room_type'] === 'private') {
        $stmt = $pdo->prepare("SELECT CONCAT(u.first_name, ' ', u.last_name) as name, u.id as member_id 
                              FROM chat_participants p 
                              JOIN wanakikundi u ON p.member_id = u.id 
                              WHERE p.room_id = ? AND p.member_id != ?");
        $stmt->execute([$room['id'], $user['id']]);
        $other = $stmt->fetch();
        $room['other_name'] = $other ? $other['name'] : 'Mwanakikundi';
        $room['other_id'] = $other ? $other['member_id'] : 0;
    } else {
        $room['other_name'] = $room['room_name'];
        $room['other_id'] = 0;
    }
}

// Get all members for creating new chat (leaders only)
$all_members = [];
if(hasRole(['mwenyekiti', 'katibu', 'mhazina'])) {
    $all_members = getMembers();
}

// Calculate total unread
$total_unread = 0;
foreach($rooms as $room) {
    $total_unread += $room['unread_count'];
}

// Handle AJAX request for refreshing
if(isset($_GET['ajax']) && $_GET['ajax'] == 1) {
    $room_id = intval($_GET['room'] ?? 0);
    if($room_id > 0) {
        $stmt = $pdo->prepare("SELECT m.*, CONCAT(u.first_name, ' ', u.last_name) as sender_name 
                              FROM chat_messages m 
                              JOIN wanakikundi u ON m.sender_id = u.id 
                              WHERE m.room_id = ? AND m.created_at > ? 
                              ORDER BY m.created_at ASC");
        $stmt->execute([$room_id, $_GET['last_time'] ?? '1970-01-01 00:00:00']);
        $new_messages = $stmt->fetchAll();
        
        if(!empty($new_messages)) {
            foreach($new_messages as $msg) {
                ?>
                <div class="chat-message <?php echo $msg['sender_id'] == $user['id'] ? 'sent' : 'received'; ?>">
                    <div class="bubble">
                        <?php if($msg['sender_id'] != $user['id']): ?>
                            <strong style="font-size: 12px; color: #0d9488;"><?php echo htmlspecialchars($msg['sender_name']); ?></strong><br>
                        <?php endif; ?>
                        <?php echo nl2br(htmlspecialchars($msg['message'])); ?>
                    </div>
                    <div class="time">
                        <?php echo date('H:i', strtotime($msg['created_at'])); ?>
                    </div>
                </div>
                <?php
            }
        }
    }
    exit();
}
?>
<!DOCTYPE html>
<html lang="<?php echo getLanguage(); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo APP_NAME; ?> - Live Chat</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        .chat-container {
            display: grid;
            grid-template-columns: 320px 1fr;
            gap: 0;
            height: 600px;
            background: white;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: var(--shadow);
        }
        
        .chat-sidebar {
            background: #f8fafc;
            border-right: 1px solid #e2e8f0;
            padding: 16px;
            overflow-y: auto;
            height: 100%;
        }
        
        .chat-sidebar .sidebar-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 16px;
            padding-bottom: 12px;
            border-bottom: 1px solid #e2e8f0;
        }
        
        .chat-sidebar .sidebar-header h4 {
            margin: 0;
            color: #1e293b;
        }
        
        .chat-sidebar .sidebar-header .unread-badge {
            background: #ef4444;
            color: white;
            padding: 2px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }
        
        .chat-sidebar .room-item {
            padding: 12px 14px;
            border-radius: 10px;
            cursor: pointer;
            transition: var(--transition);
            margin-bottom: 4px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            text-decoration: none;
            color: #1e293b;
        }
        
        .chat-sidebar .room-item:hover {
            background: #e2e8f0;
        }
        
        .chat-sidebar .room-item.active {
            background: #0d9488;
            color: white;
        }
        
        .chat-sidebar .room-item .room-info {
            display: flex;
            flex-direction: column;
            flex: 1;
            min-width: 0;
        }
        
        .chat-sidebar .room-item .room-name {
            font-weight: 600;
            font-size: 14px;
        }
        
        .chat-sidebar .room-item .room-last-msg {
            font-size: 12px;
            color: #64748b;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            max-width: 150px;
        }
        
        .chat-sidebar .room-item.active .room-last-msg {
            color: rgba(255,255,255,0.7);
        }
        
        .chat-sidebar .room-item .unread {
            background: #ef4444;
            color: white;
            padding: 2px 10px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 700;
            min-width: 20px;
            text-align: center;
        }
        
        .chat-sidebar .room-item .room-time {
            font-size: 10px;
            color: #94a3b8;
            margin-top: 2px;
        }
        
        .chat-sidebar .room-item.active .room-time {
            color: rgba(255,255,255,0.6);
        }
        
        .chat-main {
            display: flex;
            flex-direction: column;
            height: 100%;
            background: #fafafa;
        }
        
        .chat-header {
            padding: 16px 24px;
            border-bottom: 1px solid #e2e8f0;
            background: white;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .chat-header .chat-user {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .chat-header .chat-user .avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: #0d9488;
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 18px;
        }
        
        .chat-header .chat-user .name {
            font-weight: 600;
            color: #1e293b;
        }
        
        .chat-header .chat-user .status {
            font-size: 12px;
            color: #64748b;
        }
        
        .chat-messages {
            flex: 1;
            padding: 16px 24px;
            overflow-y: auto;
            background: #fafafa;
        }
        
        .chat-message {
            margin-bottom: 12px;
            display: flex;
            flex-direction: column;
        }
        
        .chat-message.sent {
            align-items: flex-end;
        }
        
        .chat-message.received {
            align-items: flex-start;
        }
        
        .chat-message .bubble {
            max-width: 75%;
            padding: 10px 18px;
            border-radius: 16px;
            word-wrap: break-word;
            font-size: 14px;
            line-height: 1.5;
        }
        
        .chat-message.sent .bubble {
            background: #0d9488;
            color: white;
            border-bottom-right-radius: 4px;
        }
        
        .chat-message.received .bubble {
            background: white;
            color: #1e293b;
            border-bottom-left-radius: 4px;
            box-shadow: 0 1px 4px rgba(0,0,0,0.05);
        }
        
        .chat-message .time {
            font-size: 11px;
            color: #94a3b8;
            margin-top: 4px;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        
        .chat-message.sent .time {
            justify-content: flex-end;
        }
        
        .chat-message .time .read-status {
            color: #0d9488;
        }
        
        .chat-input {
            padding: 16px 24px;
            border-top: 1px solid #e2e8f0;
            background: white;
            display: flex;
            gap: 12px;
            align-items: center;
        }
        
        .chat-input input {
            flex: 1;
            padding: 12px 18px;
            border: 2px solid #e2e8f0;
            border-radius: 40px;
            font-size: 15px;
            transition: var(--transition);
        }
        
        .chat-input input:focus {
            outline: none;
            border-color: #0d9488;
            box-shadow: 0 0 0 3px rgba(13,148,136,0.15);
        }
        
        .chat-input .btn-send {
            background: #0d9488;
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 40px;
            font-weight: 700;
            cursor: pointer;
            transition: var(--transition);
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .chat-input .btn-send:hover {
            background: #0f766e;
            transform: translateY(-2px);
        }
        
        .empty-chat {
            display: flex;
            align-items: center;
            justify-content: center;
            height: 100%;
            flex-direction: column;
            color: #94a3b8;
            padding: 40px;
            text-align: center;
        }
        
        .empty-chat i {
            font-size: 64px;
            color: #0d9488;
            opacity: 0.3;
            margin-bottom: 16px;
        }
        
        /* New Chat Button */
        .btn-new-chat {
            width: 100%;
            padding: 10px;
            background: #0d9488;
            color: white;
            border: none;
            border-radius: 10px;
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            margin-bottom: 12px;
        }
        
        .btn-new-chat:hover {
            background: #0f766e;
        }
        
        /* New Chat Form */
        .new-chat-form {
            background: white;
            padding: 12px;
            border-radius: 10px;
            margin-bottom: 12px;
            border: 1px solid #e2e8f0;
        }
        
        .new-chat-form select {
            width: 100%;
            padding: 8px 12px;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            font-size: 14px;
            margin-bottom: 8px;
        }
        
        .new-chat-form input {
            width: 100%;
            padding: 8px 12px;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            font-size: 14px;
            margin-bottom: 8px;
        }
        
        .new-chat-form button {
            width: 100%;
            padding: 8px;
            background: #0d9488;
            color: white;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
        }
        
        /* Online Status */
        .online-dot {
            display: inline-block;
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: #22c55e;
            margin-right: 6px;
        }
        
        .offline-dot {
            display: inline-block;
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: #94a3b8;
            margin-right: 6px;
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .chat-container {
                grid-template-columns: 1fr;
                height: auto;
                min-height: 500px;
            }
            
            .chat-sidebar {
                max-height: 200px;
                border-right: none;
                border-bottom: 1px solid #e2e8f0;
            }
            
            .chat-messages {
                min-height: 300px;
                max-height: 400px;
            }
            
            .chat-header .chat-user .name {
                font-size: 14px;
            }
        }
        
        @media (max-width: 480px) {
            .chat-message .bubble {
                max-width: 90%;
                font-size: 13px;
                padding: 8px 14px;
            }
            
            .chat-input {
                padding: 12px 16px;
                flex-wrap: wrap;
            }
            
            .chat-input input {
                font-size: 14px;
                padding: 10px 14px;
            }
            
            .chat-input .btn-send {
                padding: 10px 16px;
                font-size: 13px;
                width: 100%;
                justify-content: center;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <?php include 'includes/header.php'; ?>
        
        <div style="margin-bottom: 16px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px;">
            <h2 style="margin: 0; font-size: 22px; color: #1e293b;">
                <i class="fas fa-comment-dots" style="color: #0d9488;"></i> Live Chat
                <?php if($total_unread > 0): ?>
                    <span style="background: #ef4444; color: white; padding: 2px 14px; border-radius: 20px; font-size: 14px; margin-left: 8px;">
                        <?php echo $total_unread; ?> mpya
                    </span>
                <?php endif; ?>
            </h2>
            
            <div style="display: flex; gap: 8px;">
                <a href="api_guide.php" class="btn btn-sm btn-outline" style="border-color: #8b5cf6; color: #8b5cf6;">
                    <i class="fas fa-code"></i> API Guide
                </a>
                <a href="dashboard.php" class="btn btn-sm btn-outline">
                    <i class="fas fa-arrow-left"></i> Rudi
                </a>
            </div>
        </div>
        
        <?php if(isset($_GET['success'])): ?>
            <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($_GET['success']); ?></div>
        <?php endif; ?>
        <?php if(isset($_GET['error'])): ?>
            <div class="alert alert-error"><i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($_GET['error']); ?></div>
        <?php endif; ?>
        
        <div class="chat-container">
            <!-- Sidebar -->
            <div class="chat-sidebar">
                <div class="sidebar-header">
                    <h4><i class="fas fa-users"></i> Mazungumzo</h4>
                    <span class="unread-badge"><?php echo $total_unread; ?></span>
                </div>
                
                <!-- New Chat Button (Leaders only) -->
                <?php if(hasRole(['mwenyekiti', 'katibu', 'mhazina'])): ?>
                    <button class="btn-new-chat" onclick="toggleNewChat()">
                        <i class="fas fa-plus-circle"></i> Chat Mpya
                    </button>
                    
                    <div id="newChatForm" class="new-chat-form hidden">
                        <form method="POST">
                            <input type="hidden" name="action" value="create_chat">
                            <select name="member_id" required>
                                <option value="">--Chagua Mwanakikundi--</option>
                                <?php foreach($all_members as $member): ?>
                                    <?php if($member['id'] != $user['id']): ?>
                                    <option value="<?php echo $member['id']; ?>">
                                        <?php echo htmlspecialchars($member['first_name'] . ' ' . $member['last_name']); ?>
                                    </option>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </select>
                            <input type="text" name="message" placeholder="Ujumbe wa kwanza (hiari)">
                            <button type="submit"><i class="fas fa-paper-plane"></i> Anza Chat</button>
                        </form>
                    </div>
                <?php endif; ?>
                
                <!-- Chat Rooms List -->
                <?php foreach($rooms as $room): ?>
                    <a href="?room=<?php echo $room['id']; ?>" class="room-item <?php echo $current_room_id == $room['id'] ? 'active' : ''; ?>">
                        <div class="room-info">
                            <div class="room-name">
                                <i class="fas <?php echo $room['room_type'] === 'private' ? 'fa-user' : 'fa-users'; ?>" 
                                   style="<?php echo $current_room_id == $room['id'] ? 'color: white;' : 'color: #0d9488;'; ?>"></i>
                                <?php echo htmlspecialchars($room['other_name'] ?? 'Mwanakikundi'); ?>
                            </div>
                            <div class="room-last-msg">
                                <?php echo htmlspecialchars($room['last_message'] ?? 'Hakuna ujumbe'); ?>
                            </div>
                            <?php if($room['last_message_time']): ?>
                                <div class="room-time">
                                    <?php echo date('H:i', strtotime($room['last_message_time'])); ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        <?php if($room['unread_count'] > 0): ?>
                            <span class="unread"><?php echo $room['unread_count']; ?></span>
                        <?php endif; ?>
                    </a>
                <?php endforeach; ?>
                
                <?php if(empty($rooms)): ?>
                    <div style="text-align: center; color: #94a3b8; padding: 30px 10px;">
                        <i class="fas fa-inbox" style="font-size: 32px; opacity: 0.3;"></i>
                        <p style="margin-top: 8px;">Hakuna mazungumzo</p>
                        <p style="font-size: 13px;">Anza chat mpya na mwanakikundi mwingine</p>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Main Chat -->
            <div class="chat-main">
                <?php if($current_room): ?>
                    <!-- Chat Header -->
                    <div class="chat-header">
                        <div class="chat-user">
                            <div class="avatar">
                                <?php 
                                $display_name = '?';
                                if($current_room['room_type'] === 'private' && $other_participant) {
                                    $display_name = strtoupper(substr($other_participant['first_name'], 0, 1));
                                    $full_name = $other_participant['first_name'] . ' ' . $other_participant['last_name'];
                                } else {
                                    $display_name = strtoupper(substr($current_room['room_name'], 0, 1));
                                    $full_name = $current_room['room_name'];
                                }
                                echo $display_name;
                                ?>
                            </div>
                            <div>
                                <div class="name"><?php echo htmlspecialchars($full_name ?? 'Mwanakikundi'); ?></div>
                                <div class="status">
                                    <?php if($current_room['room_type'] === 'private' && $other_participant): ?>
                                        <span class="online-dot"></span> Online
                                    <?php else: ?>
                                        <i class="fas fa-users" style="color: #0d9488;"></i> Kikundi
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <div style="font-size: 13px; color: #64748b;">
                            <i class="fas fa-comment"></i> <?php echo count($messages); ?> ujumbe
                        </div>
                    </div>
                    
                    <!-- Chat Messages -->
                    <div class="chat-messages" id="chatMessages">
                        <?php foreach($messages as $msg): ?>
                            <div class="chat-message <?php echo $msg['sender_id'] == $user['id'] ? 'sent' : 'received'; ?>" 
                                 data-msg-id="<?php echo $msg['id']; ?>">
                                <div class="bubble">
                                    <?php if($msg['sender_id'] != $user['id']): ?>
                                        <strong style="font-size: 12px; color: #0d9488;"><?php echo htmlspecialchars($msg['sender_name']); ?></strong><br>
                                    <?php endif; ?>
                                    <?php echo nl2br(htmlspecialchars($msg['message'])); ?>
                                </div>
                                <div class="time">
                                    <?php echo date('H:i', strtotime($msg['created_at'])); ?>
                                    <?php if($msg['sender_id'] == $user['id']): ?>
                                        <?php if($msg['read_at']): ?>
                                            <span class="read-status"><i class="fas fa-check-double"></i> Soma</span>
                                        <?php else: ?>
                                            <span><i class="fas fa-check"></i> Tumwa</span>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <!-- Chat Input -->
                    <div class="chat-input">
                        <form method="POST" style="display: flex; gap: 12px; width: 100%; align-items: center;">
                            <input type="hidden" name="action" value="send_message">
                            <input type="hidden" name="room_id" value="<?php echo $current_room['id']; ?>">
                            <input type="text" name="message" placeholder="Andika ujumbe..." required autocomplete="off">
                            <button type="submit" class="btn-send">
                                <i class="fas fa-paper-plane"></i> Tuma
                            </button>
                        </form>
                    </div>
                    
                <?php else: ?>
                    <!-- Empty Chat -->
                    <div class="empty-chat">
                        <i class="fas fa-comment-dots"></i>
                        <h3 style="color: #1e293b;">Hakuna mazungumzo yaliyochaguliwa</h3>
                        <p style="color: #94a3b8; max-width: 300px;">
                            Chagua mazungumzo kutoka upande wa kushoto au 
                            unda chat mpya na mwanakikundi mwingine.
                        </p>
                        <?php if(hasRole(['mwenyekiti', 'katibu', 'mhazina'])): ?>
                            <button onclick="toggleNewChat()" class="btn btn-primary" style="width: auto; margin-top: 12px;">
                                <i class="fas fa-plus-circle"></i> Unda Chat Mpya
                            </button>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <script>
        // =============================================
        // CHAT AUTO-REFRESH
        // =============================================
        <?php if($current_room_id > 0): ?>
        let lastMessageTime = '<?php echo !empty($messages) ? end($messages)['created_at'] : date('Y-m-d H:i:s', strtotime('-1 minute')); ?>';
        
        function refreshChat() {
            fetch('chat.php?ajax=1&room=<?php echo $current_room_id; ?>&last_time=' + encodeURIComponent(lastMessageTime))
                .then(response => response.text())
                .then(html => {
                    if(html.trim()) {
                        document.getElementById('chatMessages').insertAdjacentHTML('beforeend', html);
                        // Update last message time
                        const lastMsg = document.querySelector('.chat-message:last-child');
                        if(lastMsg) {
                            const timeElement = lastMsg.querySelector('.time');
                            if(timeElement) {
                                const timeText = timeElement.textContent.trim();
                                // Parse time from existing messages
                            }
                        }
                        // Update last time to current
                        lastMessageTime = new Date().toISOString().replace('T', ' ').substring(0, 19);
                        // Scroll to bottom
                        var messages = document.getElementById('chatMessages');
                        messages.scrollTop = messages.scrollHeight;
                    }
                })
                .catch(error => console.log('Chat refresh error:', error));
        }
        
        // Auto-refresh every 3 seconds
        setInterval(refreshChat, 3000);
        
        // Scroll to bottom on load
        window.onload = function() {
            var messages = document.getElementById('chatMessages');
            if(messages) {
                messages.scrollTop = messages.scrollHeight;
            }
        };
        <?php endif; ?>
        
        // =============================================
        // TOGGLE NEW CHAT FORM
        // =============================================
        function toggleNewChat() {
            const form = document.getElementById('newChatForm');
            if(form) {
                form.classList.toggle('hidden');
            }
        }
        
        // =============================================
        // ENTER KEY TO SEND MESSAGE
        // =============================================
        document.querySelectorAll('.chat-input input').forEach(input => {
            input.addEventListener('keydown', function(e) {
                if(e.key === 'Enter' && !e.shiftKey) {
                    e.preventDefault();
                    this.form.submit();
                }
            });
        });
        
        // =============================================
        // AUTO-SCROLL TO BOTTOM
        // =============================================
        function scrollToBottom() {
            const messages = document.getElementById('chatMessages');
            if(messages) {
                messages.scrollTop = messages.scrollHeight;
            }
        }
        
        // =============================================
        // MARK MESSAGES AS READ WHEN VISIBLE
        // =============================================
        <?php if($current_room_id > 0): ?>
        document.addEventListener('visibilitychange', function() {
            if(!document.hidden) {
                // Refresh chat when tab becomes visible again
                refreshChat();
            }
        });
        <?php endif; ?>
    </script>
</body>
</html>
