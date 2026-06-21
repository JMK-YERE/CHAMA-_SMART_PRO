<?php
// api_revoke.php - Revoke API Token
require_once 'config.php';

if(!isLoggedIn()) {
    header('Location: index.php');
    exit();
}

$token_id = intval($_GET['id'] ?? 0);

if($token_id > 0) {
    // Check if token belongs to user
    $stmt = $pdo->prepare("SELECT * FROM api_tokens WHERE id = ? AND member_id = ?");
    $stmt->execute([$token_id, $_SESSION['user_id']]);
    $token = $stmt->fetch();
    
    if($token) {
        $stmt = $pdo->prepare("UPDATE api_tokens SET status = 'revoked' WHERE id = ?");
        $stmt->execute([$token_id]);
        logActivity($_SESSION['user_id'], 'Revoked API token', "Token ID: $token_id, Device: " . ($token['device_name'] ?? 'Unknown'));
        
        $success = urlencode('Token imefutwa kikamilifu');
        header('Location: api_token.php?success=' . $success);
        exit();
    }
}

header('Location: api_token.php?error=Token haipatikani');
exit();
?>
