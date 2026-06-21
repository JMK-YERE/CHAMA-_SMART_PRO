<?php
// chat_refresh.php - Auto-refresh chat
require_once 'config.php';

if(!isLoggedIn()) {
    exit();
}

$room_id = intval($_GET['room'] ?? 0);
$user = getCurrentUser();

if($room_id > 0) {
    // Check if user is participant
    $stmt = $pdo->prepare("SELECT * FROM chat_participants WHERE room_id = ? AND member_id = ?");
    $stmt->execute([$room_id, $user['id']]);
    if($stmt->fetch()) {
        // Get messages
        $stmt = $pdo->prepare("SELECT m.*, CONCAT(u.first_name, ' ', u.last_name) as sender_name 
                              FROM chat_messages m 
                              JOIN wanakikundi u ON m.sender_id = u.id 
                              WHERE m.room_id = ? 
                              ORDER BY m.created_at ASC");
        $stmt->execute([$room_id]);
        $messages = $stmt->fetchAll();
        
        foreach($messages as $msg):
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
        endforeach;
    }
}
?>
