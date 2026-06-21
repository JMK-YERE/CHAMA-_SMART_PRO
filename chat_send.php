<?php
// chat_send.php - Send message via AJAX
require_once 'config.php';

if(!isLoggedIn()) {
    die(json_encode(['error' => 'Not logged in']));
}

$user = getCurrentUser();
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
            
            echo json_encode(['success' => true]);
            exit();
        }
    }
}

echo json_encode(['error' => 'Failed to send message']);
?>
