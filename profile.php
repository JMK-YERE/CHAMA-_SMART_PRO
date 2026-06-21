<?php
// profile.php
require_once 'config.php';

if(!isLoggedIn()) {
    header('Location: index.php');
    exit();
}

$user = getCurrentUser();
$error = null;
$success = null;

if($_SERVER['REQUEST_METHOD'] === 'POST') {
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    if(empty($current_password) || empty($new_password) || empty($confirm_password)) {
        $error = 'Tafadhali jaza sehemu zote';
    } elseif(!password_verify($current_password, $user['password'])) {
        $error = 'Nenosiri lako la sasa si sahihi';
    } elseif(strlen($new_password) < 6) {
        $error = 'Nenosiri jipya lazima liwe na angalau herufi 6';
    } elseif($new_password !== $confirm_password) {
        $error = 'Nenosiri jipya na uthibitisho havilingani';
    } else {
        if(changePassword($user['id'], $new_password)) {
            $success = 'Nenosiri lako limebadilishwa!';
            logActivity($user['id'], 'Changed password', 'Password updated successfully');
        } else {
            $error = 'Kuna hitilafu, jaribu tena';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="<?php echo getLanguage(); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo APP_NAME; ?> - Wasifu</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>
<body>
    <div class="container">
        <?php include 'includes/header.php'; ?>
        
        <div class="dashboard-card fade-in" style="max-width: 600px; margin: 0 auto;">
            <h2><i class="fas fa-user-cog"></i> Wasifu na Nenosiri</h2>
            
            <?php if($success): ?>
                <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?php echo $success; ?></div>
            <?php endif; ?>
            <?php if($error): ?>
                <div class="alert alert-error"><i class="fas fa-exclamation-circle"></i> <?php echo $error; ?></div>
            <?php endif; ?>
            
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 24px;">
                <div><strong>Jina:</strong> <?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></div>
                <div><strong>Simu:</strong> <?php echo htmlspecialchars($user['phone']); ?></div>
                <div><strong>Jukumu:</strong> <span class="badge badge-<?php echo $user['role']; ?>"><?php echo $user['role']; ?></span></div>
                <div><strong>Akiba:</strong> TZS <?php echo number_format($user['savings']); ?></div>
                <div><strong>NIDA:</strong> <?php echo htmlspecialchars($user['nida'] ?? '-'); ?></div>
                <div><strong>Hali:</strong> <span class="badge badge-<?php echo $user['status']; ?>"><?php echo $user['status']; ?></span></div>
            </div>
            
            <hr style="margin: 20px 0; border: none; border-top: 2px solid #e2e8f0;">
            
            <h3><i class="fas fa-key"></i> Badilisha Nenosiri</h3>
            <form method="POST" style="margin-top: 16px;">
                <div class="form-group">
                    <label>Nenosiri la Sasa</label>
                    <input type="password" name="current_password" placeholder="Weka nenosiri lako la sasa" required>
                </div>
                <div class="form-group">
                    <label>Nenosiri Jipya</label>
                    <input type="password" name="new_password" placeholder="Weka nenosiri jipya (angalau herufi 6)" required>
                </div>
                <div class="form-group">
                    <label>Thibitisha Nenosiri Jipya</label>
                    <input type="password" name="confirm_password" placeholder="Andika tena nenosiri jipya" required>
                </div>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> Badilisha Nenosiri
                </button>
            </form>
            
            <div style="margin-top: 20px; text-align: center;">
                <a href="dashboard.php" class="btn btn-outline" style="width: auto;">
                    <i class="fas fa-arrow-left"></i> Rudi Dashibodi
                </a>
            </div>
        </div>
    </div>
</body>
</html>
