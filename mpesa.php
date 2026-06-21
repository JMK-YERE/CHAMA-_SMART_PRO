<?php
// mpesa.php - M-PESA Payment Integration
require_once 'config.php';

if(!isLoggedIn()) {
    header('Location: index.php');
    exit();
}

$user = getCurrentUser();
$error = null;
$success = null;
$result = null;

// Handle M-PESA STK Push
if($_SERVER['REQUEST_METHOD'] === 'POST') {
    $type = $_POST['type'] ?? '';
    $amount = floatval($_POST['amount'] ?? 0);
    $phone = $_POST['phone'] ?? '';
    
    if(empty($phone)) {
        $phone = $user['mpesa_phone'] ?? $user['phone'];
    }
    
    if($amount <= 0) {
        $error = 'Tafadhali weka kiasi sahihi';
    } elseif(empty($phone)) {
        $error = 'Tafadhali weka namba ya M-PESA';
    } else {
        // Generate reference
        $reference = 'CHAMA' . date('Ymd') . rand(1000, 9999);
        $description = $type === 'contribution' ? 'Chango ya kikundi' : 'Malipo ya mkopo';
        
        // Save transaction
        $stmt = $pdo->prepare("INSERT INTO mpesa_transactions 
                              (member_id, transaction_type, amount, phone, request_id, status) 
                              VALUES (?, ?, ?, ?, ?, 'pending')");
        $stmt->execute([$user['id'], $type, $amount, $phone, $reference]);
        $transaction_id = $pdo->lastInsertId();
        
        // Send STK Push
        $mpesa_result = mpesaStkPush($phone, $amount, $reference, $description);
        
        if(isset($mpesa_result['ResponseCode']) && $mpesa_result['ResponseCode'] == '0') {
            // Update transaction with request ID
            $stmt = $pdo->prepare("UPDATE mpesa_transactions 
                                  SET request_id = ? 
                                  WHERE id = ?");
            $stmt->execute([$mpesa_result['CheckoutRequestID'], $transaction_id]);
            
            $success = 'Ombi la malipo limetumwa. Tafadhali ingiza PIN yako kwenye simu.';
            
            // Log
            logActivity($user['id'], 'M-PESA STK Push', "Amount: $amount, Phone: $phone");
        } else {
            $error = $mpesa_result['errorMessage'] ?? 'Hitilafu katika M-PESA, jaribu tena';
        }
    }
}

// Check transaction status
if(isset($_GET['check']) && isset($_GET['id'])) {
    $stmt = $pdo->prepare("SELECT * FROM mpesa_transactions WHERE id = ? AND member_id = ?");
    $stmt->execute([$_GET['id'], $user['id']]);
    $result = $stmt->fetch();
}

// Get user's transactions
$transactions = [];
$stmt = $pdo->prepare("SELECT * FROM mpesa_transactions WHERE member_id = ? ORDER BY transaction_date DESC LIMIT 20");
$stmt->execute([$user['id']]);
$transactions = $stmt->fetchAll();

// Get M-PESA settings
$mpesa_settings = [
    'environment' => MPESA_ENVIRONMENT,
    'shortcode' => MPESA_SHORTCODE,
    'callback_url' => MPESA_CALLBACK_URL
];
?>
<!DOCTYPE html>
<html lang="<?php echo getLanguage(); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo APP_NAME; ?> - M-PESA</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>
<body>
    <div class="container">
        <?php include 'includes/header.php'; ?>
        
        <div class="dashboard-card fade-in">
            <h2><i class="fas fa-mobile-alt" style="color: #4CAF50;"></i> M-PESA Payments</h2>
            
            <?php if($success): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i> <?php echo $success; ?>
                </div>
            <?php endif; ?>
            <?php if($error): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
                </div>
            <?php endif; ?>
            
            <?php if($result): ?>
                <div class="alert alert-info">
                    <strong>Status:</strong> <?php echo $result['status']; ?>
                    <?php if($result['status'] === 'completed'): ?>
                        <br><strong>Receipt:</strong> <?php echo $result['mpesa_receipt'] ?? 'N/A'; ?>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
            
            <!-- M-PESA Status -->
            <div style="background: #f1f5f9; padding: 12px; border-radius: 10px; margin-bottom: 20px;">
                <div style="display: flex; gap: 20px; flex-wrap: wrap;">
                    <div><strong>Mazingira:</strong> <span class="badge badge-<?php echo MPESA_ENVIRONMENT; ?>"><?php echo MPESA_ENVIRONMENT; ?></span></div>
                    <div><strong>Shortcode:</strong> <?php echo MPESA_SHORTCODE; ?></div>
                    <div><strong>Callback:</strong> <?php echo MPESA_CALLBACK_URL; ?></div>
                </div>
            </div>
            
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                <!-- Make Payment -->
                <div style="background: #f1f5f9; padding: 20px; border-radius: 16px;">
                    <h3><i class="fas fa-hand-holding-usd"></i> Lipa kwa M-PESA</h3>
                    <form method="POST">
                        <div class="form-group">
                            <label>Jina la Malipo</label>
                            <select name="type" required>
                                <option value="contribution">Chango ya Kikundi</option>
                                <option value="loan_repayment">Malipo ya Mkopo</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Kiasi (TZS)</label>
                            <input type="number" name="amount" placeholder="Weka kiasi" required min="1">
                        </div>
                        <div class="form-group">
                            <label>Namba ya M-PESA</label>
                            <input type="tel" name="phone" placeholder="Mfano: 0712345678" 
                                   value="<?php echo htmlspecialchars($user['mpesa_phone'] ?? $user['phone']); ?>">
                            <small style="color: #64748b;">Acha tupu utumie namba yako</small>
                        </div>
                        <button type="submit" class="btn btn-success" style="background: #4CAF50;">
                            <i class="fas fa-paper-plane"></i> Tuma Ombi
                        </button>
                    </form>
                </div>
                
                <!-- Instructions -->
                <div style="background: #f1f5f9; padding: 20px; border-radius: 16px;">
                    <h3><i class="fas fa-info-circle"></i> Maelekezo</h3>
                    <ol style="padding-left: 20px; line-height: 2.2;">
                        <li>Weka kiasi unachotaka kulipa</li>
                        <li>Weka namba yako ya M-PESA</li>
                        <li>Bonyeza "Tuma Ombi"</li>
                        <li>Utapokea STK Push kwenye simu yako</li>
                        <li>Ingiza PIN yako kuthibitisha</li>
                        <li>Malipo yako yatathibitishwa ndani ya sekunde</li>
                    </ol>
                    
                    <div style="margin-top: 16px; padding: 12px; background: #dbeafe; border-radius: 8px;">
                        <p style="color: #1e40af; font-size: 13px;">
                            <i class="fas fa-shield-alt"></i>
                            <strong>Usalama:</strong> Malipo yako ni salama. Hutumii PIN yako kwenye mfumo huu.
                        </p>
                    </div>
                </div>
            </div>
            
            <!-- Transaction History -->
            <h3 style="margin-top: 24px;"><i class="fas fa-history"></i> Historia ya Malipo</h3>
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Aina</th>
                            <th>Kiasi</th>
                            <th>Hali</th>
                            <th>Tarehe</th>
                            <th>Kitendo</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($transactions as $txn): ?>
                        <tr>
                            <td><?php echo $txn['id']; ?></td>
                            <td><?php echo str_replace('_', ' ', $txn['transaction_type']); ?></td>
                            <td>TZS <?php echo number_format($txn['amount']); ?></td>
                            <td>
                                <span class="badge badge-<?php echo $txn['status']; ?>">
                                    <?php echo $txn['status']; ?>
                                </span>
                            </td>
                            <td><?php echo date('d/m/Y H:i', strtotime($txn['transaction_date'])); ?></td>
                            <td>
                                <a href="?check=1&id=<?php echo $txn['id']; ?>" class="btn btn-sm btn-outline">
                                    <i class="fas fa-sync-alt"></i>
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if(empty($transactions)): ?>
                        <tr><td colspan="6" style="text-align: center;">Hakuna malipo bado</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Quick Stats -->
            <div style="margin-top: 20px; display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 12px;">
                <?php
                $stmt = $pdo->prepare("SELECT COUNT(*) as total, SUM(amount) as total_amount FROM mpesa_transactions WHERE member_id = ? AND status = 'completed'");
                $stmt->execute([$user['id']]);
                $stats = $stmt->fetch();
                ?>
                <div style="background: #f1f5f9; padding: 12px; border-radius: 10px; text-align: center;">
                    <div style="font-size: 20px; font-weight: 700; color: #0d9488;"><?php echo $stats['total'] ?? 0; ?></div>
                    <div style="font-size: 13px; color: #64748b;">Malipo Yaliyofanikiwa</div>
                </div>
                <div style="background: #f1f5f9; padding: 12px; border-radius: 10px; text-align: center;">
                    <div style="font-size: 20px; font-weight: 700; color: #0d9488;">TZS <?php echo number_format($stats['total_amount'] ?? 0); ?></div>
                    <div style="font-size: 13px; color: #64748b;">Jumla ya Malipo</div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
