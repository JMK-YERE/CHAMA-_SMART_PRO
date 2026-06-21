<?php
// dashboard.php
require_once 'config.php';
require_once 'functions.php';

if(!isLoggedIn()) {
    header('Location: index.php');
    exit();
}

$user = getCurrentUser();
$totals = getTotals();
$members = getMembers();
$loans = getLoans();
$announcements = getAnnouncements();
$instructions = getInstructions();
$settings = getLoanSettings();

// Handle POST actions
if($_SERVER['REQUEST_METHOD'] === 'POST') {
    if(isset($_POST['action'])) {
        switch($_POST['action']) {
            case 'add_contribution':
                if(hasRole('mhazina')) {
                    try {
                        addContribution($_POST['member_id'], $_POST['amount'], $_POST['description'] ?? null);
                        header('Location: dashboard.php?success=Chango imeongezwa');
                    } catch(Exception $e) {
                        header('Location: dashboard.php?error='.urlencode($e->getMessage()));
                    }
                    exit();
                }
                break;
                
            case 'request_loan':
                if(hasRole(['mhazina', 'mwanakikundi'])) {
                    $eligibility = isEligibleForLoan($_POST['member_id'], $_POST['amount']);
                    if(!$eligibility['eligible']) {
                        header('Location: dashboard.php?error='.urlencode($eligibility['reason']));
                        exit();
                    }
                    try {
                        requestLoan($_POST['member_id'], $_POST['amount'], $_POST['term_months'], $_POST['purpose'] ?? null);
                        header('Location: dashboard.php?success=Ombi la mkopo limetumwa');
                    } catch(Exception $e) {
                        header('Location: dashboard.php?error='.urlencode($e->getMessage()));
                    }
                    exit();
                }
                break;
                
            case 'record_repayment':
                if(hasRole(['mhazina', 'mwanakikundi'])) {
                    try {
                        recordRepayment($_POST['loan_id'], $_POST['amount']);
                        header('Location: dashboard.php?success=Malipo yamehifadhiwa');
                    } catch(Exception $e) {
                        header('Location: dashboard.php?error='.urlencode($e->getMessage()));
                    }
                    exit();
                }
                break;
                
            case 'approve_loan':
                if(hasRole(['mwenyekiti', 'mhazina', 'mkaguzi'])) {
                    approveLoan($_POST['loan_id'], $_SESSION['user_id']);
                    header('Location: dashboard.php?success=Mkopo umeidhinishwa');
                    exit();
                }
                break;
                
            case 'reject_loan':
                if(hasRole(['mwenyekiti', 'mhazina', 'mkaguzi'])) {
                    rejectLoan($_POST['loan_id'], $_SESSION['user_id']);
                    header('Location: dashboard.php?success=Mkopo umekataliwa');
                    exit();
                }
                break;
                
            case 'confirm_member':
                if(hasRole(['mwenyekiti', 'katibu'])) {
                    confirmMember($_POST['member_id'], $_SESSION['user_id']);
                    // Send notification
                    $member = getMember($_POST['member_id']);
                    if($member) {
                        $msg = "Akaunti yako kwenye ".APP_NAME." imethibitishwa!\nSasa unaweza kuomba mikopo.";
                        sendSMS($member['phone'], $msg);
                        sendWhatsAppMessage($member['phone'], $msg);
                    }
                    header('Location: dashboard.php?success=Mwanakikundi amethibitishwa');
                    exit();
                }
                break;
        }
    }
}

$pending_loans = [];
if(hasRole(['mwenyekiti', 'mhazina', 'mkaguzi'])) {
    $pending_loans = getPendingLoans();
}

$pending_members = [];
if(hasRole(['mwenyekiti', 'katibu'])) {
    $pending_members = getPendingMembers();
}

$my_loans = [];
if($user['role'] === 'mwanakikundi') {
    $my_loans = getMemberLoans($user['id']);
}

// Calculator
$calculator_result = null;
if(isset($_GET['calc_amount']) && isset($_GET['calc_term'])) {
    $calculator_result = calculateLoanSchedule(
        floatval($_GET['calc_amount']), 
        intval($_GET['calc_term']), 
        floatval($_GET['calc_rate'] ?? $settings['default_interest_rate'])
    );
}

// Eligibility check
$eligibility_result = null;
if($user['role'] === 'mwanakikundi' && isset($_GET['check_loan']) && isset($_GET['amount'])) {
    $eligibility_result = isEligibleForLoan($user['id'], floatval($_GET['amount']));
}
?>
<!DOCTYPE html>
<html lang="<?php echo getLanguage(); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo APP_NAME; ?> - Dashibodi</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>
<body>
    <div class="container">
        <?php include 'includes/header.php'; ?>
        
        <?php if(isset($_GET['success'])): ?>
            <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($_GET['success']); ?></div>
        <?php endif; ?>
        <?php if(isset($_GET['error'])): ?>
            <div class="alert alert-error"><i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($_GET['error']); ?></div>
        <?php endif; ?>
        
        <!-- Stats -->
        <div class="stats-grid">
            <div class="stat-card color-teal">
                <div class="icon"><i class="fas fa-coins"></i></div>
                <div class="number"><?php echo number_format($totals['total_funds']); ?></div>
                <div class="label"><?php echo t('total_funds'); ?></div>
            </div>
            <div class="stat-card color-blue">
                <div class="icon"><i class="fas fa-hand-holding-usd"></i></div>
                <div class="number"><?php echo number_format($totals['total_loans']); ?></div>
                <div class="label"><?php echo t('total_loans'); ?></div>
            </div>
            <div class="stat-card color-green">
                <div class="icon"><i class="fas fa-check-circle"></i></div>
                <div class="number"><?php echo number_format($totals['total_repaid']); ?></div>
                <div class="label">Mikopo Iliolipwa</div>
            </div>
            <div class="stat-card color-orange">
                <div class="icon"><i class="fas fa-clock"></i></div>
                <div class="number"><?php echo $totals['overdue_loans']; ?></div>
                <div class="label"><?php echo t('overdue_loans'); ?></div>
            </div>
            <div class="stat-card color-purple">
                <div class="icon"><i class="fas fa-users"></i></div>
                <div class="number"><?php echo $totals['total_members']; ?></div>
                <div class="label"><?php echo t('total_members'); ?></div>
            </div>
        </div>
        
        <!-- Announcements -->
        <?php if(!empty($announcements)): ?>
        <div class="dashboard-card" style="border-left: 4px solid #f59e0b; background: #fffbeb;">
            <h2><i class="fas fa-bullhorn" style="color: #f59e0b;"></i> Matangazo</h2>
            <?php foreach($announcements as $announcement): ?>
            <div class="announcement-item">
                <div style="display: flex; justify-content: space-between; flex-wrap: wrap; gap: 8px;">
                    <strong><?php echo htmlspecialchars($announcement['title']); ?></strong>
                    <small style="color: #64748b;">
                        <i class="fas fa-user"></i> <?php echo htmlspecialchars($announcement['posted_by_name'] ?? 'Mfumo'); ?> 
                        | <i class="fas fa-clock"></i> <?php echo date('d/m/Y H:i', strtotime($announcement['posted_at'])); ?>
                    </small>
                </div>
                <p style="margin-top: 6px; color: #334155;"><?php echo nl2br(htmlspecialchars($announcement['content'])); ?></p>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
        
        <!-- Calculator -->
        <div class="dashboard-card">
            <h2><i class="fas fa-calculator"></i> Kikokotoo cha Mkopo</h2>
            <form method="GET" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 12px; align-items: end;">
                <div class="form-group" style="margin-bottom: 0;">
                    <label>Kiasi (TZS)</label>
                    <input type="number" name="calc_amount" placeholder="Kiasi" required>
                </div>
                <div class="form-group" style="margin-bottom: 0;">
                    <label>Muda (Miezi)</label>
                    <input type="number" name="calc_term" placeholder="Miezi" required>
                </div>
                <div class="form-group" style="margin-bottom: 0;">
                    <label>Riba (%)</label>
                    <input type="number" step="0.1" name="calc_rate" placeholder="Riba" value="<?php echo $settings['default_interest_rate']; ?>">
                </div>
                <button type="submit" class="btn btn-primary" style="width: auto; padding: 12px 30px;">
                    <i class="fas fa-calculator"></i> Hesabu
                </button>
            </form>
            
            <?php if($calculator_result): ?>
            <div style="margin-top: 16px; background: #f1f5f9; padding: 16px; border-radius: 12px;">
                <h4>Ratiba ya Marejesho</h4>
                <div class="table-responsive">
                    <table>
                        <thead><tr><th>Mwezi</th><th>Malipo</th><th>Mkopo</th><th>Riba</th><th>Salio</th></tr></thead>
                        <tbody>
                            <?php foreach($calculator_result as $month): ?>
                            <tr>
                                <td><?php echo $month['month']; ?></td>
                                <td><?php echo number_format($month['payment']); ?></td>
                                <td><?php echo number_format($month['principal']); ?></td>
                                <td><?php echo number_format($month['interest']); ?></td>
                                <td><?php echo number_format($month['balance']); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php endif; ?>
        </div>
        
        <!-- Pending Members Confirmation (Katibu/Mwenyekiti) -->
        <?php if(hasRole(['mwenyekiti', 'katibu']) && !empty($pending_members)): ?>
        <div class="dashboard-card" style="border-left: 4px solid #3b82f6;">
            <h2><i class="fas fa-user-check" style="color: #3b82f6;"></i> Kuthibitisha Akaunti</h2>
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>Jina</th>
                            <th>Simu</th>
                            <th>NIDA</th>
                            <th>Tarehe</th>
                            <th>Kitendo</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($pending_members as $member): ?>
                        <tr class="pending-row">
                            <td><?php echo htmlspecialchars($member['first_name'] . ' ' . $member['last_name']); ?></td>
                            <td><?php echo htmlspecialchars($member['phone']); ?></td>
                            <td><?php echo htmlspecialchars($member['nida'] ?? '-'); ?></td>
                            <td><?php echo date('d/m/Y', strtotime($member['created_at'])); ?></td>
                            <td>
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="action" value="confirm_member">
                                    <input type="hidden" name="member_id" value="<?php echo $member['id']; ?>">
                                    <button type="submit" class="btn btn-sm btn-success">
                                        <i class="fas fa-check"></i> Thibitisha
                                    </button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- View All (Mwenyekiti, Katibu, Mkaguzi) -->
        <?php if(hasRole(['mwenyekiti', 'katibu', 'mkaguzi'])): ?>
        <div class="dashboard-card">
            <h2><i class="fas fa-eye"></i> Taarifa za Wanakikundi Wote</h2>
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>Jina</th>
                            <th>Simu</th>
                            <th>Jukumu</th>
                            <th>Hali</th>
                            <th>Akiba</th>
                            <th>Deni</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($members as $member): 
                            $loans = getMemberLoans($member['id']);
                            $total_loan = array_sum(array_column($loans, 'amount'));
                            $total_repaid = array_sum(array_column($loans, 'repaid'));
                            $debt = $total_loan - $total_repaid;
                        ?>
                        <tr>
                            <td><?php echo htmlspecialchars($member['first_name'] . ' ' . $member['last_name']); ?></td>
                            <td><?php echo htmlspecialchars($member['phone']); ?></td>
                            <td><span class="badge badge-<?php echo $member['role']; ?>"><?php echo $member['role']; ?></span></td>
                            <td><span class="badge badge-<?php echo $member['status']; ?>"><?php echo $member['status']; ?></span></td>
                            <td><?php echo number_format($member['savings']); ?></td>
                            <td><?php echo number_format($debt); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <div style="margin-top: 16px; display: flex; gap: 10px; flex-wrap: wrap;">
                <a href="reports.php?type=members" class="btn btn-sm btn-outline">
                    <i class="fas fa-file-pdf"></i> PDF
                </a>
                <a href="reports.php?type=members&format=csv" class="btn btn-sm btn-outline">
