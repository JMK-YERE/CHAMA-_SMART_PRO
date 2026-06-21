<?php
// api_revoke.php - Revoke API Token
require_once 'config.php';

if(!isLoggedIn()) {
    header('Location: index.php');
    exit();
}

$token_id = intval($_GET['id'] ?? 0);

if($token_id > 0) {
    $stmt = $pdo->prepare("UPDATE api_tokens SET status = 'revoked' WHERE id = ? AND member_id = ?");
    $stmt->execute([$token_id, $_SESSION['user_id']]);
    logActivity($_SESSION['user_id'], 'Revoked API token', "Token ID: $token_id");
}

header('Location: api_token.php?success=Token imefutwa');
exit();
?>
