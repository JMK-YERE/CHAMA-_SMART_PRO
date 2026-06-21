<?php
// logout.php
require_once 'config.php';

if(isLoggedIn()) {
    logActivity($_SESSION['user_id'], 'Logout', 'User logged out');
}

session_destroy();
header('Location: index.php');
exit();
?>
