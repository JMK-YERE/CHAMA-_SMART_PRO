<?php
// language.php - Switch Language
require_once 'config.php';

if(!isLoggedIn()) {
    header('Location: index.php');
    exit();
}

$lang = $_GET['lang'] ?? 'sw';
setLanguage($lang);

// Redirect back
$referer = $_SERVER['HTTP_REFERER'] ?? 'dashboard.php';
header('Location: ' . $referer);
exit();
?>
