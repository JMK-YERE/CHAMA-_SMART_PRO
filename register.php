<?php
// register.php
require_once 'config.php';

if(isLoggedIn()) {
    header('Location: dashboard.php');
    exit();
}

$error = null;
$success = null;

if($_SERVER['REQUEST_METHOD'] === 'POST') {
    $first_name = trim($_POST['first_name'] ?? '');
    $last_name = trim($_POST['last_name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $nida = trim($_POST['nida'] ?? '');
    $monthly_income = floatval($_POST['monthly_income'] ?? 0);
    
    if(empty($first_name) || empty($last_name) || empty($phone)) {
        $error = 'Tafadhali jaza majina na namba ya simu';
    } else {
        $stmt = $pdo->prepare("SELECT id FROM wanakikundi WHERE phone = ?");
        $stmt->execute([$phone]);
        if($stmt->fetch()) {
            $error = 'Namba hii ya simu tayari imesajiliwa';
        } else {
            $generated_password = generatePassword($last_name);
            $hashed_password = password_hash($generated_password, PASSWORD_DEFAULT);
            
            $stmt = $pdo->prepare("INSERT INTO wanakikundi (first_name, last_name, phone, email, nida, monthly_income, password, status) 
                                   VALUES (?, ?, ?, ?, ?, ?, ?, 'pending')");
            if($stmt->execute([$first_name, $last_name, $phone, $email, $nida, $monthly_income, $hashed_password])) {
                $member_id = $pdo->lastInsertId();
                logActivity($member_id, 'Registered', 'New member registered');
                
                $message = "Karibu ".$first_name." ".$last_name." kwenye ".APP_NAME."!\n";
                $message .= "Nenosiri lako ni: ".$generated_password."\n";
                $message .= "Tafadhali ingia na ubadilishe nenosiri lako.";
                sendSMS($phone, $message);
                
                $stmt = $pdo->prepare("INSERT INTO notifications (user_id, type, title, message) VALUES (?, 'sms', 'Karibu ".APP_NAME."', ?)");
                $stmt->execute([$member_id, $message]);
                
                $success = 'Usajili wako umefanikiwa! Nenosiri lako limetumwa kwa SMS. 
                           Subiri kuthibitishwa na Katibu au Mwenyekiti.';
            } else {
                $error = 'Kuna hitilafu, jaribu tena';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="sw">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo APP_NAME; ?> - Jisajili</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>
<body>
    <div class="container">
        <div class="login-wrapper">
            <div class="login-card fade-in">
                <div class="login-header">
                    <div class="logo"><i class="fas fa-user-plus"></i></div>
                    <h1>Jisajili</h1>
                    <p>Jaza taarifa zako hapa</p>
                </div>
                
                <?php if($success): ?>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success); ?>
                    </div>
                    <div style="text-align: center; margin-top: 16px;">
                        <a href="index.php" class="btn btn-primary"><i class="fas fa-sign-in-alt"></i> Nenda kwenye Kuingia</a>
                    </div>
                <?php else: ?>
                    
                    <?php if($error): ?>
                        <div class="alert alert-error">
                            <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
                        </div>
                    <?php endif; ?>
                    
                    <form method="POST">
                        <div class="form-group">
                            <label>Jina la Kwanza <span class="required">*</span></label>
                            <input type="text" name="first_name" placeholder="Mfano: Juma" required>
                        </div>
                        <div class="form-group">
                            <label>Jina la Mwisho <span class="required">*</span></label>
                            <input type="text" name="last_name" placeholder="Mfano: Mwenyekiti" required>
                        </div>
                        <div class="form-group">
                            <label>Namba ya Simu <span class="required">*</span></label>
                            <input type="tel" name="phone" placeholder="Mfano: 0712345678" required>
                        </div>
                        <div class="form-group">
                            <label>Barua Pepe</label>
                            <input type="email" name="email" placeholder="Mfano: juma@email.com">
                        </div>
                        <div class="form-group">
                            <label>Namba ya NIDA</label>
                            <input type="text" name="nida" placeholder="Mfano: 19900101-12345-6789">
                        </div>
                        <div class="form-group">
                            <label>Mapato ya Mwezi (TZS)</label>
                            <input type="number" name="monthly_income" placeholder="Mfano: 500000">
                        </div>
                        
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-paper-plane"></i> Wasilisha
                        </button>
                    </form>
                    
                    <div style="text-align: center; margin-top: 16px;">
                        <p style="color: var(--gray); font-size: 14px;">
                            Tayari una akaunti? <a href="index.php" style="color: var(--primary); font-weight: 600;">Ingia</a>
                        </p>
                        <p style="color: var(--gray); font-size: 12px; margin-top: 6px;">
                            <i class="fas fa-info-circle"></i> Nenosiri lako litakutumwa kwa SMS
                        </p>
                    </div>
                    
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>
