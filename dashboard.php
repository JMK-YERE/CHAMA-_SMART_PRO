<?php
// dashboard.php - Dashibodi Kuu
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
    <style>
        /* Quick Actions Bar */
        .quick-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            margin-bottom: 20px;
            background: white;
            padding: 16px 20px;
            border-radius: 16px;
            box-shadow: var(--shadow);
            align-items: center;
        }
        .quick-actions .label {
            font-weight: 600;
            color: var(--gray);
            font-size: 14px;
            margin-right: 8px;
        }
        .quick-actions .btn-api {
            background: #8b5cf6;
            color: white;
            padding: 8px 18px;
            border-radius: 40px;
            text-decoration: none;
            font-weight: 600;
            font-size: 14px;
            transition: var(--transition);
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        .quick-actions .btn-api:hover {
            background: #7c3aed;
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(139,92,246,0.3);
        }
        .quick-actions .btn-token {
            background: #0d9488;
            color: white;
            padding: 8px 18px;
            border-radius: 40px;
            text-decoration: none;
            font-weight: 600;
            font-size: 14px;
            transition: var(--transition);
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        .quick-actions .btn-token:hover {
            background: #0f766e;
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(13,148,136,0.3);
        }
        .quick-actions .btn-docs {
            background: #6366f1;
            color: white;
            padding: 8px 18px;
            border-radius: 40px;
            text-decoration: none;
            font-weight: 600;
            font-size: 14px;
            transition: var(--transition);
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        .quick-actions .btn-docs:hover {
            background: #4f46e5;
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(99,102,241,0.3);
        }
        .quick-actions .btn-chat {
            background: #f59e0b;
            color: white;
            padding: 8px 18px;
            border-radius: 40px;
            text-decoration: none;
            font-weight: 600;
            font-size: 14px;
            transition: var(--transition);
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        .quick-actions .btn-chat:hover {
            background: #d97706;
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(245,158,11,0.3);
        }
        .quick-actions .btn-mpesa {
            background: #4CAF50;
            color: white;
            padding: 8px 18px;
            border-radius: 40px;
            text-decoration: none;
            font-weight: 600;
            font-size: 14px;
            transition: var(--transition);
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        .quick-actions .btn-mpesa:hover {
            background: #43a047;
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(76,175,80,0.3);
        }
        @media (max-width: 768px) {
            .quick-actions {
                flex-direction: column;
                align-items: stretch;
            }
            .quick-actions .label {
                text-align: center;
            }
            .quick-actions a {
                text-align: center;
                justify-content: center;
            }
        }
    </style>
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
        
        <!-- ============================================= -->
        <!-- QUICK ACTIONS BAR - API GUIDE IMESHAINGIZWA -->
        <!-- ============================================= -->
        <div class="quick-actions">
            <span class="label"><i class="fas fa-rocket"></i> Vitendo vya Haraka:</span>
            
            <!-- API Guide - Kitufe Kipya -->
            <a href="api_guide.php" class="btn-api">
                <i class="fas fa-code"></i> Mwongozo wa API
            </a>
            
            <a href="api_token.php" class="btn-token">
                <i class="fas fa-key"></i> API Token
            </a>
            
            <a href="api_docs.php" class="btn-docs">
                <i class="fas fa-book"></i> API Documentation
            </a>
            
            <a href="chat.php" class="btn-chat">
                <i class="fas fa-comment-dots"></i> Live Chat
            </a>
            
            <a href="mpesa.php" class="btn-mpesa">
                <i class="fas fa-mobile-alt"></i> M-PESA
            </a>
        </div>
        
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
                    <i class="fas fa-file-excel"></i> CSV
                </a>
                <button onclick="window.print()" class="btn btn-sm btn-outline">
                    <i class="fas fa-print"></i> Chapisha
                </button>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Mwanakikundi Self View -->
        <?php if(hasRole('mwanakikundi')): ?>
        <div class="dashboard-card">
            <h2><i class="fas fa-user"></i> Taarifa Zangu</h2>
            <div class="stats-grid" style="grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));">
                <div class="stat-card">
                    <div class="icon"><i class="fas fa-money-bill-wave"></i></div>
                    <div class="number"><?php echo number_format($user['savings']); ?></div>
                    <div class="label">Akiba Yangu</div>
                </div>
                <div class="stat-card">
                    <div class="icon"><i class="fas fa-hand-holding-usd"></i></div>
                    <div class="number"><?php 
                        $my_total_loan = array_sum(array_column($my_loans, 'amount'));
                        $my_total_repaid = array_sum(array_column($my_loans, 'repaid'));
                        echo number_format($my_total_loan - $my_total_repaid);
                    ?></div>
                    <div class="label">Deni Langu</div>
                </div>
                <div class="stat-card">
                    <div class="icon"><i class="fas fa-calendar-alt"></i></div>
                    <div class="number"><?php echo count($my_loans); ?></div>
                    <div class="label">Mikopo Yangu</div>
                </div>
            </div>
            
            <h3>Mikopo Yangu</h3>
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>Kiasi</th>
                            <th>Kilicholipwa</th>
                            <th>Deni</th>
                            <th>Hali</th>
                            <th>Kitendo</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($my_loans as $loan): 
                            $remaining = $loan['amount'] - $loan['repaid'];
                        ?>
                        <tr>
                            <td><?php echo number_format($loan['amount']); ?></td>
                            <td><?php echo number_format($loan['repaid']); ?></td>
                            <td><?php echo number_format($remaining); ?></td>
                            <td><span class="badge badge-<?php echo $loan['status']; ?>"><?php echo $loan['status']; ?></span></td>
                            <td>
                                <?php if(in_array($loan['status'], ['approved', 'active']) && $remaining > 0): ?>
                                <form method="POST" style="display: flex; gap: 6px; flex-wrap: wrap;">
                                    <input type="hidden" name="action" value="record_repayment">
                                    <input type="hidden" name="loan_id" value="<?php echo $loan['id']; ?>">
                                    <input type="number" name="amount" placeholder="Kiasi" style="width: 120px; padding: 5px 10px; border: 2px solid #e2e8f0; border-radius: 10px;" required>
                                    <button type="submit" class="btn btn-sm btn-success">Lipa</button>
                                </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if(empty($my_loans)): ?>
                        <tr><td colspan="5" style="text-align: center;">Hujawahi kukopa</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Request Loan -->
            <div style="margin-top: 20px; background: #f1f5f9; padding: 18px; border-radius: 16px;">
                <h4><i class="fas fa-plus-circle"></i> Omba Mkopo</h4>
                
                <div style="background: #e0f2fe; padding: 12px; border-radius: 10px; margin-bottom: 14px;">
                    <form method="GET" style="display: flex; gap: 10px; align-items: end; flex-wrap: wrap;">
                        <input type="hidden" name="check_loan" value="1">
                        <div class="form-group" style="margin-bottom: 0; flex: 1;">
                            <label style="font-size: 13px;">Angalia kama unastahili</label>
                            <input type="number" name="amount" placeholder="Weka kiasi" required style="width: 200px;">
                        </div>
                        <button type="submit" class="btn btn-sm" style="background: #0d9488; color: white; padding: 8px 20px;">Angalia</button>
                    </form>
                    <?php if($eligibility_result): ?>
                    <div style="margin-top: 10px; padding: 10px; background: <?php echo $eligibility_result['eligible'] ? '#d1fae5' : '#fee2e2'; ?>; border-radius: 8px;">
                        <strong><?php echo $eligibility_result['eligible'] ? '✅ Unastahili mkopo!' : '❌ Hustahili mkopo'; ?></strong>
                        <p><?php echo $eligibility_result['reason']; ?></p>
                    </div>
                    <?php endif; ?>
                </div>
                
                <form method="POST">
                    <input type="hidden" name="action" value="request_loan">
                    <input type="hidden" name="member_id" value="<?php echo $user['id']; ?>">
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
                        <input type="number" name="amount" placeholder="Kiasi cha mkopo (TZS)" required>
                        <input type="number" name="term_months" placeholder="Muda (miezi)" required>
                    </div>
                    <div style="margin-top: 10px;">
                        <textarea name="purpose" placeholder="Madhumuni ya mkopo" rows="2" style="width: 100%; padding: 10px; border: 2px solid #e2e8f0; border-radius: 12px;"></textarea>
                    </div>
                    <button type="submit" class="btn btn-primary" style="margin-top: 10px;">
                        <i class="fas fa-paper-plane"></i> Wasilisha Ombi
                    </button>
                </form>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Treasurer Full Control -->
        <?php if(hasRole('mhazina')): ?>
        <div class="dashboard-card">
            <h2><i class="fas fa-calculator"></i> Udhibiti Kamili (Mhazina)</h2>
            
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 10px; margin-bottom: 20px;">
                <button onclick="toggleForm('contributionForm')" class="btn btn-outline" style="width: auto;">
                    <i class="fas fa-plus-circle"></i> Ongeza Chango
                </button>
                <button onclick="toggleForm('loanRequestForm')" class="btn btn-outline" style="width: auto;">
                    <i class="fas fa-hand-holding-usd"></i> Omba Mkopo
                </button>
                <button onclick="toggleForm('repaymentForm')" class="btn btn-outline" style="width: auto;">
                    <i class="fas fa-hand-holding-heart"></i> Rekodi Malipo
                </button>
                <a href="mpesa.php" class="btn btn-outline" style="width: auto;">
                    <i class="fas fa-mobile-alt"></i> M-PESA
                </a>
                <a href="reports.php" class="btn btn-outline" style="width: auto;">
                    <i class="fas fa-file-pdf"></i> Ripoti
                </a>
                <a href="api_token.php" class="btn btn-outline" style="width: auto;">
                    <i class="fas fa-key"></i> API Token
                </a>
                <a href="api_guide.php" class="btn btn-outline" style="width: auto; border-color: #8b5cf6; color: #8b5cf6;">
                    <i class="fas fa-code"></i> API Guide
                </a>
            </div>
            
            <!-- Contribution Form -->
            <div id="contributionForm" class="hidden" style="background: #f1f5f9; padding: 18px; border-radius: 14px; margin-bottom: 16px;">
                <h4><i class="fas fa-plus-circle"></i> Ongeza Chango</h4>
                <form method="POST">
                    <input type="hidden" name="action" value="add_contribution">
                    <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 12px;">
                        <select name="member_id" required>
                            <option value="">--Chagua Mwanakikundi--</option>
                            <?php foreach($members as $member): ?>
                            <option value="<?php echo $member['id']; ?>">
                                <?php echo htmlspecialchars($member['first_name'] . ' ' . $member['last_name']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                        <input type="number" name="amount" placeholder="Kiasi (TZS)" required>
                    </div>
                    <input type="text" name="description" placeholder="Maelezo" style="width: 100%; margin-top: 10px; padding: 10px; border: 2px solid #e2e8f0; border-radius: 12px;">
                    <button type="submit" class="btn btn-primary" style="margin-top: 10px;">Hifadhi Chango</button>
                </form>
            </div>
            
            <!-- Loan Request Form -->
            <div id="loanRequestForm" class="hidden" style="background: #f1f5f9; padding: 18px; border-radius: 14px; margin-bottom: 16px;">
                <h4><i class="fas fa-hand-holding-usd"></i> Omba Mkopo</h4>
                <form method="POST">
                    <input type="hidden" name="action" value="request_loan">
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
                        <select name="member_id" required>
                            <option value="">--Chagua Mwanakikundi--</option>
                            <?php foreach($members as $member): ?>
                            <option value="<?php echo $member['id']; ?>">
                                <?php echo htmlspecialchars($member['first_name'] . ' ' . $member['last_name']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                        <input type="number" name="amount" placeholder="Kiasi" required>
                    </div>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-top: 10px;">
                        <input type="number" name="term_months" placeholder="Muda (miezi)" required>
                        <input type="number" step="0.1" name="interest_rate" placeholder="Riba (%)" value="<?php echo $settings['default_interest_rate']; ?>">
                    </div>
                    <textarea name="purpose" placeholder="Madhumuni" rows="2" style="width: 100%; margin-top: 10px; padding: 10px; border: 2px solid #e2e8f0; border-radius: 12px;"></textarea>
                    <button type="submit" class="btn btn-primary" style="margin-top: 10px;"><i class="fas fa-paper-plane"></i> Wasilisha</button>
                </form>
            </div>
            
            <!-- Repayment Form -->
            <div id="repaymentForm" class="hidden" style="background: #f1f5f9; padding: 18px; border-radius: 14px; margin-bottom: 16px;">
                <h4><i class="fas fa-hand-holding-heart"></i> Rekodi Malipo</h4>
                <form method="POST">
                    <input type="hidden" name="action" value="record_repayment">
                    <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 12px;">
                        <select name="loan_id" required>
                            <option value="">--Chagua Mkopo--</option>
                            <?php 
                            $active_loans = getLoans('active');
                            foreach($active_loans as $loan): 
                                $remaining = $loan['amount'] - $loan['repaid'];
                            ?>
                            <option value="<?php echo $loan['id']; ?>">
                                <?php echo htmlspecialchars($loan['member_name']); ?> - TZS <?php echo number_format($loan['amount']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                        <input type="number" name="amount" placeholder="Kiasi" required>
                    </div>
                    <button type="submit" class="btn btn-primary" style="margin-top: 10px;"><i class="fas fa-save"></i> Hifadhi</button>
                </form>
            </div>
            
            <!-- Full Data -->
            <h3>Orodha Kamili ya Wanakikundi</h3>
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>Mwanakikundi</th>
                            <th>Jukumu</th>
                            <th>Akiba</th>
                            <th>Chango</th>
                            <th>Mikopo</th>
                            <th>Deni</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $contributions_all = getContributions();
                        foreach($members as $member): 
                            $member_contrib = array_sum(array_column(array_filter($contributions_all, function($c) use($member) {
                                return $c['member_id'] == $member['id'];
                            }), 'amount'));
                            $member_loans = getMemberLoans($member['id']);
                            $total_loan = array_sum(array_column($member_loans, 'amount'));
                            $total_repaid = array_sum(array_column($member_loans, 'repaid'));
                            $debt = $total_loan - $total_repaid;
                        ?>
                        <tr>
                            <td><?php echo htmlspecialchars($member['first_name'] . ' ' . $member['last_name']); ?></td>
                            <td><span class="badge badge-<?php echo $member['role']; ?>"><?php echo $member['role']; ?></span></td>
                            <td><?php echo number_format($member['savings']); ?></td>
                            <td><?php echo number_format($member_contrib); ?></td>
                            <td><?php echo number_format($total_loan); ?></td>
                            <td><?php echo number_format($debt); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Pending Loans Approval -->
        <?php if(hasRole(['mwenyekiti', 'mhazina', 'mkaguzi']) && !empty($pending_loans)): ?>
        <div class="dashboard-card" style="border-left: 4px solid #f59e0b;">
            <h2><i class="fas fa-check-double" style="color: #f59e0b;"></i> Idhini ya Mikopo</h2>
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>Mkopaji</th>
                            <th>Kiasi</th>
                            <th>Muda</th>
                            <th>Madhumuni</th>
                            <th>Kitendo</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($pending_loans as $loan): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($loan['member_name']); ?></td>
                            <td><?php echo number_format($loan['amount']); ?></td>
                            <td><?php echo $loan['term_months']; ?> miezi</td>
                            <td><?php echo htmlspecialchars(substr($loan['purpose'] ?? '', 0, 30)); ?>...</td>
                            <td>
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="action" value="approve_loan">
                                    <input type="hidden" name="loan_id" value="<?php echo $loan['id']; ?>">
                                    <button type="submit" class="btn btn-sm btn-success"><i class="fas fa-check"></i></button>
                                </form>
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="action" value="reject_loan">
                                    <input type="hidden" name="loan_id" value="<?php echo $loan['id']; ?>">
                                    <button type="submit" class="btn btn-sm btn-danger"><i class="fas fa-times"></i></button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Roles & Responsibilities -->
        <div class="dashboard-card">
            <h2><i class="fas fa-users-cog"></i> Majukumu ya Viongozi</h2>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 16px;">
                <?php 
                $role_info = [
                    'mwenyekiti' => ['icon' => 'fa-crown', 'color' => '#1e3a8a'],
                    'katibu' => ['icon' => 'fa-pen', 'color' => '#854d0e'],
                    'mhazina' => ['icon' => 'fa-calculator', 'color' => '#0d9488'],
                    'mkaguzi' => ['icon' => 'fa-search', 'color' => '#7e22ce'],
                    'mwanakikundi' => ['icon' => 'fa-user', 'color' => '#475569']
                ];
                foreach($role_info as $role => $info): 
                ?>
                <div style="background: #f8fafc; padding: 14px; border-radius: 14px; border-left: 4px solid <?php echo $info['color']; ?>;">
                    <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 6px;">
                        <i class="fas <?php echo $info['icon']; ?>" style="color: <?php echo $info['color']; ?>;"></i>
                        <strong style="text-transform: uppercase; color: <?php echo $info['color']; ?>; font-size: 14px;">
                            <?php echo str_replace('_', ' ', $role); ?>
                        </strong>
                    </div>
                    <p style="color: #475569; font-size: 13px; margin: 0;"><?php echo getRoleDescription($role); ?></p>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        
        <!-- ============================================= -->
        <!-- API QUICK LINKS - Footer ya Dashboard -->
        <!-- ============================================= -->
        <div class="dashboard-card" style="border-left: 4px solid #8b5cf6; background: #f5f3ff;">
            <h2><i class="fas fa-code" style="color: #8b5cf6;"></i> Viungo vya API</h2>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 12px;">
                <a href="api_guide.php" class="btn btn-primary" style="width: auto; text-align: center; background: #8b5cf6; display: inline-flex; align-items: center; justify-content: center; gap: 8px;">
                    <i class="fas fa-book"></i> Mwongozo wa API
                </a>
                <a href="api_token.php" class="btn btn-primary" style="width: auto; text-align: center; background: #0d9488; display: inline-flex; align-items: center; justify-content: center; gap: 8px;">
                    <i class="fas fa-key"></i> Unda Token
                </a>
                <a href="api_docs.php" class="btn btn-primary" style="width: auto; text-align: center; background: #6366f1; display: inline-flex; align-items: center; justify-content: center; gap: 8px;">
                    <i class="fas fa-file-alt"></i> Documentation
                </a>
            </div>
            <p style="margin-top: 12px; color: #64748b; font-size: 14px; text-align: center;">
                <i class="fas fa-info-circle" style="color: #8b5cf6;"></i>
                Tumia API kuunganisha mifumo yako na <?php echo APP_NAME; ?>
            </p>
        </div>
        
    </div>
    
    <script>
        function toggleForm(formId) {
            const form = document.getElementById(formId);
            if(form) form.classList.toggle('hidden');
        }
    </script>
</body>
</html>
