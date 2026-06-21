<?php
// settings.php - System Settings
require_once 'config.php';

if(!isLoggedIn()) {
    header('Location: index.php');
    exit();
}

if(!hasRole(['mwenyekiti', 'mhazina'])) {
    header('Location: dashboard.php?error=Huna ruhusa ya kuona ukurasa huu');
    exit();
}

$user = getCurrentUser();
$settings = getLoanSettings();
$error = null;
$success = null;

// Update settings
if($_SERVER['REQUEST_METHOD'] === 'POST') {
    $max_loan_percentage = floatval($_POST['max_loan_percentage'] ?? 70);
    $max_loan_income_percentage = floatval($_POST['max_loan_income_percentage'] ?? 50);
    $default_interest_rate = floatval($_POST['default_interest_rate'] ?? 5);
    $min_contributions_required = intval($_POST['min_contributions_required'] ?? 3);
    $contribution_period_months = intval($_POST['contribution_period_months'] ?? 6);
    
    $stmt = $pdo->prepare("INSERT INTO viwango_vya_mkopo 
                          (max_loan_percentage, max_loan_income_percentage, default_interest_rate, 
                           min_contributions_required, contribution_period_months, updated_by) 
                          VALUES (?, ?, ?, ?, ?, ?)");
    if($stmt->execute([$max_loan_percentage, $max_loan_income_percentage, $default_interest_rate, 
                       $min_contributions_required, $contribution_period_months, $user['id']])) {
        $success = 'Viwango vimesasishwa!';
        logActivity($user['id'], 'Updated loan settings', "New settings saved");
        $settings = getLoanSettings(); // Refresh
    } else {
        $error = 'Hitilafu katika kusasisha viwango';
    }
}
?>
<!DOCTYPE html>
<html lang="<?php echo getLanguage(); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo APP_NAME; ?> - Mipangilio</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>
<body>
    <div class="container">
        <?php include 'includes/header.php'; ?>
        
        <div class="dashboard-card fade-in" style="max-width: 700px; margin: 0 auto;">
            <h2><i class="fas fa-sliders-h" style="color: #6366f1;"></i> Mipangilio ya Mfumo</h2>
            
            <?php if($success): ?>
                <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?php echo $success; ?></div>
            <?php endif; ?>
            <?php if($error): ?>
                <div class="alert alert-error"><i class="fas fa-exclamation-circle"></i> <?php echo $error; ?></div>
            <?php endif; ?>
            
            <div style="background: #f1f5f9; padding: 16px; border-radius: 12px; margin-bottom: 20px;">
                <p style="color: #475569; font-size: 14px;">
                    <i class="fas fa-info-circle" style="color: #0d9488;"></i>
                    Mipangilio hii inaathiri jinsi wanakikundi wanavyopata mikopo.
                </p>
            </div>
            
            <form method="POST">
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                    <div class="form-group" style="margin-bottom: 0;">
                        <label>% ya Akiba Inayoruhusiwa Kukopa</label>
                        <input type="number" step="0.1" name="max_loan_percentage" 
                               value="<?php echo $settings['max_loan_percentage']; ?>" required>
                        <small style="color: #64748b;">Mfano: 70%</small>
                    </div>
                    
                    <div class="form-group" style="margin-bottom: 0;">
                        <label>% ya Mapato Inayoruhusiwa Kukopa</label>
                        <input type="number" step="0.1" name="max_loan_income_percentage" 
                               value="<?php echo $settings['max_loan_income_percentage']; ?>" required>
                        <small style="color: #64748b;">Mfano: 50%</small>
                    </div>
                    
                    <div class="form-group" style="margin-bottom: 0;">
                        <label>Riba ya Mkopo (%)</label>
                        <input type="number" step="0.1" name="default_interest_rate" 
                               value="<?php echo $settings['default_interest_rate']; ?>" required>
                        <small style="color: #64748b;">Mfano: 5.00%</small>
                    </div>
                    
                    <div class="form-group" style="margin-bottom: 0;">
                        <label>Idadi ya Chango Inayohitajika</label>
                        <input type="number" name="min_contributions_required" 
                               value="<?php echo $settings['min_contributions_required']; ?>" required>
                        <small style="color: #64748b;">Mfano: 3</small>
                    </div>
                    
                    <div class="form-group" style="margin-bottom: 0; grid-column: span 2;">
                        <label>Muda wa Kuangalia Chango (Miezi)</label>
                        <input type="number" name="contribution_period_months" 
                               value="<?php echo $settings['contribution_period_months']; ?>" required>
                        <small style="color: #64748b;">Mfano: Miezi 6</small>
                    </div>
                </div>
                
                <button type="submit" class="btn btn-primary" style="margin-top: 20px;">
                    <i class="fas fa-save"></i> Hifadhi Mipangilio
                </button>
            </form>
            
            <div style="margin-top: 24px; padding-top: 20px; border-top: 1px solid #e2e8f0;">
                <h3><i class="fas fa-database"></i> Takwimu za Mfumo</h3>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-top: 12px;">
                    <div><strong>Jumla ya Wanakikundi:</strong> <?php echo count(getMembers()); ?></div>
                    <div><strong>Jumla ya Hazina:</strong> TZS <?php echo number_format(getTotals()['total_funds']); ?></div>
                    <div><strong>Mikopo Inayoendelea:</strong> <?php echo count(getLoans('active')); ?></div>
                    <div><strong>Mikopo Iliolipwa:</strong> <?php echo count(getLoans('paid')); ?></div>
                </div>
            </div>
            
            <div style="margin-top: 20px; text-align: center;">
                <a href="dashboard.php" class="btn btn-outline" style="width: auto;">
                    <i class="fas fa-arrow-left"></i> Rudi Dashibodi
                </a>
            </div>
        </div>
    </div>
</body>
</html>
