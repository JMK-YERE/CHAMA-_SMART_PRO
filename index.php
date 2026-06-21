<?php
// index.php
require_once 'config.php';

if(isLoggedIn()) {
    header('Location: dashboard.php');
    exit();
}

$success = $_GET['success'] ?? null;
$error = $_GET['error'] ?? null;
?>
<!DOCTYPE html>
<html lang="sw">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo APP_NAME; ?> - Karibu</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="icon" href="assets/images/favicon.png">
</head>
<body>
    <div class="container">
        <div class="login-wrapper">
            <div class="login-card fade-in">
                <div class="login-header">
                    <div class="logo"><i class="fas fa-hand-holding-usd"></i></div>
                    <h1><?php echo APP_NAME; ?></h1>
                    <p>Mfumo wa Kuwekeza na Kukopa</p>
                </div>
                
                <?php if($success): ?>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success); ?>
                    </div>
                <?php endif; ?>
                
                <?php if($error): ?>
                    <div class="alert alert-error">
                        <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>
                
                <form action="login.php" method="POST">
                    <div class="form-group">
                        <label><i class="fas fa-phone"></i> Namba ya Simu</label>
                        <input type="text" name="phone" placeholder="Weka namba yako" required>
                    </div>
                    <div class="form-group">
                        <label><i class="fas fa-lock"></i> Neno siri</label>
                        <input type="password" name="password" placeholder="Weka nenosiri" required>
                    </div>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-sign-in-alt"></i> Ingia
                    </button>
                </form>
                
                <div style="text-align: center; margin-top: 20px;">
                    <p style="color: var(--gray); font-size: 13px;">
                        Huna akaunti? <a href="register.php" style="color: var(--primary); font-weight: 600;">Jisajili</a>
                    </p>
                    <p style="color: var(--gray); font-size: 12px; margin-top: 8px;">
                        Wasiliana na Katibu au Mhazina kwa usajili wa haraka
                    </p>
                </div>
                
                <div style="text-align: center; margin-top: 24px; border-top: 1px solid #e2e8f0; padding-top: 16px;">
                    <p style="color: var(--gray); font-size: 11px;">
                        &copy; <?php echo date('Y'); ?> <?php echo APP_NAME; ?> - All Rights Reserved
                    </p>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
