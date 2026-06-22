<?php
// login.php - Bila Encryption (Password inalinganishwa moja kwa moja)
require_once 'config.php';

if($_SERVER['REQUEST_METHOD'] === 'POST') {
    $phone = trim($_POST['phone'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if(empty($phone) || empty($password)) {
        header('Location: index.php?error=Tafadhali jaza namba na nenosiri');
        exit();
    }
    
    $stmt = $pdo->prepare("SELECT * FROM wanakikundi WHERE phone = ? AND status != 'suspended'");
    $stmt->execute([$phone]);
    $user = $stmt->fetch();
    
    // ============================================
    // BADILISHA HAPA - ONDOA PASSWORD_VERIFY
    // Sasa inalinganisha password moja kwa moja
    // ============================================
    if($user && $password == $user['password']) {
        $stmt = $pdo->prepare("UPDATE wanakikundi SET last_login = NOW() WHERE id = ?");
        $stmt->execute([$user['id']]);
        
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_name'] = $user['first_name'] . ' ' . $user['last_name'];
        $_SESSION['user_role'] = $user['role'];
        
        logActivity($user['id'], 'Login', 'User logged in successfully');
        header('Location: dashboard.php');
        exit();
    } else {
        $stmt = $pdo->prepare("SELECT id FROM wanakikundi WHERE phone = ?");
        $stmt->execute([$phone]);
        if($stmt->fetch()) {
            header('Location: index.php?error=Nenosiri si sahihi');
        } else {
            header('Location: index.php?error=Namba hii haijasajiliwa. Wasiliana na Katibu.');
        }
        exit();
    }
} else {
    header('Location: index.php');
    exit();
}
?>
