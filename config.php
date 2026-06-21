<?php
// config.php - Mipangilio ya mfumo
session_start();

// ============================================
// DATABASE CONFIGURATION - INFINITYFREE
// ============================================

// Database - InfinityFree (Badilisha na maelezo yako)
define('DB_HOST', 'sql204.infinityfree.com');
define('DB_USER', 'if0_42236866');
define('DB_PASS', 'josephjonas123');
define('DB_NAME', 'if0_42236866_chama_smart');

// Database connection
try {
    $pdo = new PDO("mysql:host=".DB_HOST.";dbname=".DB_NAME, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

// ============================================
// APP SETTINGS
// ============================================

define('APP_NAME', 'CHAMA SMART');
define('APP_VERSION', '5.0.0');
define('APP_URL', 'https://yourdomain.infinityfreeapp.com'); // Badilisha na domain yako
define('APP_EMAIL', 'info@yourdomain.com');
define('APP_PHONE', '+255712345678');
define('TIMEZONE', 'Africa/Dar_es_Salaam');

date_default_timezone_set(TIMEZONE);

// ============================================
// M-PESA (Weka baada ya kupata credentials)
// ============================================

define('MPESA_ENVIRONMENT', 'sandbox');
define('MPESA_CONSUMER_KEY', 'your_consumer_key');
define('MPESA_CONSUMER_SECRET', 'your_consumer_secret');
define('MPESA_PASSKEY', 'your_passkey');
define('MPESA_SHORTCODE', '174379');
define('MPESA_CALLBACK_URL', APP_URL . '/mpesa_callback.php');

// ============================================
// Africa's Talking (SMS)
// ============================================

define('AT_USERNAME', 'your_username');
define('AT_API_KEY', 'your_api_key');
define('AT_SHORTCODE', 'your_shortcode');

// ============================================
// WhatsApp Business API
// ============================================

define('WHATSAPP_API_URL', 'https://graph.facebook.com/v18.0/');
define('WHATSAPP_PHONE_NUMBER_ID', 'your_phone_number_id');
define('WHATSAPP_ACCESS_TOKEN', 'your_access_token');

// ============================================
// Zoom API
// ============================================

define('ZOOM_API_KEY', 'your_zoom_api_key');
define('ZOOM_API_SECRET', 'your_zoom_api_secret');
define('ZOOM_ACCOUNT_ID', 'your_zoom_account_id');

// ============================================
// Jitsi
// ============================================

define('JITSI_URL', 'https://meet.jit.si/');

// ============================================
// CORE FUNCTIONS
// ============================================

function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function getCurrentUser() {
    global $pdo;
    if(!isLoggedIn()) return null;
    $stmt = $pdo->prepare("SELECT * FROM wanakikundi WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    return $stmt->fetch();
}

function hasRole($roles) {
    $user = getCurrentUser();
    if(!$user) return false;
    if(!is_array($roles)) $roles = [$roles];
    return in_array($user['role'], $roles);
}

function logActivity($user_id, $action, $details = null) {
    global $pdo;
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    $stmt = $pdo->prepare("INSERT INTO logs (user_id, action, details, ip_address) VALUES (?, ?, ?, ?)");
    return $stmt->execute([$user_id, $action, $details, $ip]);
}

function generatePassword($last_name) {
    $random = rand(1000, 9999);
    return strtolower($last_name) . $random;
}

function getRoleDescription($role) {
    $descriptions = [
        'mwenyekiti' => 'Kiongozi mkuu. Anaona taarifa zote (view-only) na anaidhinisha akaunti.',
        'katibu' => 'Anasimamia mawasiliano na kuthibitisha akaunti za wanakikundi.',
        'mhazina' => 'Msimamizi wa fedha. Ana uwezo kamili wa kuingiza na kudhibiti fedha.',
        'mkaguzi' => 'Anakagua rekodi. Anaweza kuona na kuchapisha ripoti.',
        'mwanakikundi' => 'Mwanakikundi wa kawaida. Anaona taarifa zake binafsi.'
    ];
    return $descriptions[$role] ?? 'Jukumu halijulikani';
}

// ============================================
// LANGUAGE FUNCTIONS
// ============================================

function getLanguage() {
    $user = getCurrentUser();
    if($user && isset($user['language'])) {
        return $user['language'];
    }
    return $_SESSION['lang'] ?? 'sw';
}

function t($key, $lang = null) {
    global $pdo;
    if(!$lang) $lang = getLanguage();
    
    static $translations = [];
    if(empty($translations)) {
        $stmt = $pdo->query("SELECT * FROM translations");
        while($row = $stmt->fetch()) {
            $translations[$row['key_string']] = $row;
        }
    }
    
    if(isset($translations[$key])) {
        return $lang === 'en' ? $translations[$key]['english'] : $translations[$key]['swahili'];
    }
    return $key;
}

function setLanguage($lang) {
    $_SESSION['lang'] = $lang;
    if(isLoggedIn()) {
        global $pdo;
        $stmt = $pdo->prepare("UPDATE wanakikundi SET language = ? WHERE id = ?");
        $stmt->execute([$lang, $_SESSION['user_id']]);
    }
}

// ============================================
// SMS & WHATSAPP FUNCTIONS
// ============================================

function sendSMS($phone, $message) {
    $phone = str_replace(['+', ' ', '-'], '', $phone);
    if(!str_starts_with($phone, '255')) {
        $phone = '255' . substr($phone, -9);
    }
    
    $url = 'https://api.africastalking.com/version1/messaging';
    $data = [
        'username' => AT_USERNAME,
        'to' => $phone,
        'message' => $message,
        'from' => AT_SHORTCODE
    ];
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'apiKey: ' . AT_API_KEY,
        'Content-Type: application/x-www-form-urlencoded'
    ]);
    $response = curl_exec($ch);
    curl_close($ch);
    
    return $response;
}

function sendWhatsAppMessage($phone, $message) {
    $phone = str_replace(['+', ' ', '-'], '', $phone);
    
    $data = [
        'messaging_product' => 'whatsapp',
        'to' => $phone,
        'type' => 'text',
        'text' => ['body' => $message]
    ];
    
    $url = WHATSAPP_API_URL . WHATSAPP_PHONE_NUMBER_ID . '/messages';
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . WHATSAPP_ACCESS_TOKEN,
        'Content-Type: application/json'
    ]);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $result = curl_exec($ch);
    curl_close($ch);
    
    return json_decode($result, true);
}

// ============================================
// M-PESA FUNCTIONS
// ============================================

function getMpesaAccessToken() {
    $url = MPESA_ENVIRONMENT === 'production' 
        ? 'https://api.safaricom.co.ke/oauth/v1/generate?grant_type=client_credentials'
        : 'https://sandbox.safaricom.co.ke/oauth/v1/generate?grant_type=client_credentials';
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_HEADER, false);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_USERPWD, MPESA_CONSUMER_KEY . ':' . MPESA_CONSUMER_SECRET);
    
    $result = curl_exec($ch);
    curl_close($ch);
    
    $data = json_decode($result);
    return $data->access_token ?? null;
}

function mpesaStkPush($phone, $amount, $account_reference, $transaction_desc) {
    $access_token = getMpesaAccessToken();
    if(!$access_token) return ['error' => 'Failed to get M-PESA token'];
    
    $phone = str_replace(['+', ' ', '-'], '', $phone);
    if(strlen($phone) === 9) $phone = '254' . $phone;
    if(strlen($phone) === 10) $phone = '254' . substr($phone, -9);
    
    $url = MPESA_ENVIRONMENT === 'production'
        ? 'https://api.safaricom.co.ke/mpesa/stkpush/v1/processrequest'
        : 'https://sandbox.safaricom.co.ke/mpesa/stkpush/v1/processrequest';
    
    $timestamp = date('YmdHis');
    $password = base64_encode(MPESA_SHORTCODE . MPESA_PASSKEY . $timestamp);
    
    $data = [
        'BusinessShortCode' => MPESA_SHORTCODE,
        'Password' => $password,
        'Timestamp' => $timestamp,
        'TransactionType' => 'CustomerPayBillOnline',
        'Amount' => $amount,
        'PartyA' => $phone,
        'PartyB' => MPESA_SHORTCODE,
        'PhoneNumber' => $phone,
        'CallBackURL' => MPESA_CALLBACK_URL,
        'AccountReference' => $account_reference,
        'TransactionDesc' => $transaction_desc
    ];
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $access_token,
        'Content-Type: application/json'
    ]);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    
    $result = curl_exec($ch);
    curl_close($ch);
    
    return json_decode($result, true);
}

// ============================================
// LOAN FUNCTIONS
// ============================================

function getLoanSettings() {
    global $pdo;
    $stmt = $pdo->query("SELECT * FROM viwango_vya_mkopo ORDER BY id DESC LIMIT 1");
    return $stmt->fetch() ?: [
        'max_loan_percentage' => 70,
        'max_loan_income_percentage' => 50,
        'default_interest_rate' => 5,
        'min_contributions_required' => 3,
        'contribution_period_months' => 6
    ];
}

function isEligibleForLoan($member_id, $requested_amount) {
    global $pdo;
    $settings = getLoanSettings();
    
    $stmt = $pdo->prepare("SELECT savings, monthly_income FROM wanakikundi WHERE id = ?");
    $stmt->execute([$member_id]);
    $member = $stmt->fetch();
    if(!$member) return ['eligible' => false, 'reason' => 'Mwanakikundi hapatikani'];
    
    $max_savings_loan = ($member['savings'] * $settings['max_loan_percentage']) / 100;
    if($requested_amount > $max_savings_loan) {
        return ['eligible' => false, 'reason' => 'Hawezi kukopa zaidi ya '.$settings['max_loan_percentage'].'% ya akiba'];
    }
    
    $max_income_loan = ($member['monthly_income'] * $settings['max_loan_income_percentage']) / 100;
    if($requested_amount > $max_income_loan) {
        return ['eligible' => false, 'reason' => 'Hawezi kukopa zaidi ya '.$settings['max_loan_income_percentage'].'% ya mapato'];
    }
    
    $stmt = $pdo->prepare("SELECT SUM(amount - repaid) as total_debt FROM mikopo WHERE member_id = ? AND status IN ('approved','active')");
    $stmt->execute([$member_id]);
    $debt = $stmt->fetch()['total_debt'] ?? 0;
    if($debt > 0) {
        return ['eligible' => false, 'reason' => 'Ana deni la '.number_format($debt).' TZS'];
    }
    
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM chango WHERE member_id = ? AND date >= DATE_SUB(CURDATE(), INTERVAL ? MONTH)");
    $stmt->execute([$member_id, $settings['contribution_period_months']]);
    $contributions = $stmt->fetch()['count'];
    if($contributions < $settings['min_contributions_required']) {
        return ['eligible' => false, 'reason' => 'Anahitaji angalau '.$settings['min_contributions_required'].' chango'];
    }
    
    return ['eligible' => true, 'reason' => 'Anastahili mkopo'];
}

function calculateLoanSchedule($amount, $term_months, $interest_rate = 5) {
    $monthly_interest = $interest_rate / 100 / 12;
    $monthly_payment = $amount * $monthly_interest * pow(1 + $monthly_interest, $term_months) / (pow(1 + $monthly_interest, $term_months) - 1);
    $schedule = [];
    $balance = $amount;
    for($i = 1; $i <= $term_months; $i++) {
        $interest_payment = $balance * $monthly_interest;
        $principal_payment = $monthly_payment - $interest_payment;
        $balance -= $principal_payment;
        $schedule[] = [
            'month' => $i,
            'payment' => round($monthly_payment, 2),
            'principal' => round($principal_payment, 2),
            'interest' => round($interest_payment, 2),
            'balance' => round(max($balance, 0), 2)
        ];
    }
    return $schedule;
}

// ============================================
// BUSINESS FUNCTIONS
// ============================================

function addContribution($member_id, $amount, $description = null, $payment_method = 'cash') {
    global $pdo;
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare("INSERT INTO chango (member_id, amount, description, payment_method) VALUES (?, ?, ?, ?)");
        $stmt->execute([$member_id, $amount, $description, $payment_method]);
        $stmt = $pdo->prepare("UPDATE wanakikundi SET savings = savings + ? WHERE id = ?");
        $stmt->execute([$amount, $member_id]);
        logActivity($_SESSION['user_id'], "Added contribution", "Member: $member_id, Amount: $amount");
        $pdo->commit();
        return true;
    } catch(Exception $e) {
        $pdo->rollBack();
        throw $e;
    }
}

function requestLoan($member_id, $amount, $term_months, $purpose, $interest_rate = null) {
    global $pdo;
    $settings = getLoanSettings();
    $rate = $interest_rate ?? $settings['default_interest_rate'];
    $due_date = date('Y-m-d', strtotime("+$term_months months"));
    $stmt = $pdo->prepare("INSERT INTO mikopo (member_id, amount, purpose, term_months, interest_rate, status, due_date) VALUES (?, ?, ?, ?, ?, 'pending', ?)");
    return $stmt->execute([$member_id, $amount, $purpose, $term_months, $rate, $due_date]);
}

function approveLoan($loan_id, $approver_id) {
    global $pdo;
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare("UPDATE mikopo SET status = 'approved', approved_by = ?, approved_at = NOW() WHERE id = ?");
        $stmt->execute([$approver_id, $loan_id]);
        logActivity($approver_id, "Approved loan", "Loan ID: $loan_id");
        $pdo->commit();
        return true;
    } catch(Exception $e) {
        $pdo->rollBack();
        throw $e;
    }
}

function rejectLoan($loan_id, $approver_id) {
    global $pdo;
    $stmt = $pdo->prepare("UPDATE mikopo SET status = 'rejected', approved_by = ? WHERE id = ?");
    $stmt->execute([$approver_id, $loan_id]);
    logActivity($approver_id, "Rejected loan", "Loan ID: $loan_id");
    return true;
}

function recordRepayment($loan_id, $amount) {
    global $pdo;
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare("SELECT * FROM mikopo WHERE id = ? FOR UPDATE");
        $stmt->execute([$loan_id]);
        $loan = $stmt->fetch();
        if(!$loan) throw new Exception("Mkopo haupatikani");
        
        $new_repaid = $loan['repaid'] + $amount;
        if($new_repaid > $loan['amount']) {
            throw new Exception("Kiasi kinazidi deni lililobaki");
        }
        $new_status = ($new_repaid >= $loan['amount']) ? 'paid' : 'active';
        
        $stmt = $pdo->prepare("UPDATE mikopo SET repaid = ?, status = ? WHERE id = ?");
        $stmt->execute([$new_repaid, $new_status, $loan_id]);
        $stmt = $pdo->prepare("INSERT INTO malipo_ya_mikopo (loan_id, amount) VALUES (?, ?)");
        $stmt->execute([$loan_id, $amount]);
        logActivity($_SESSION['user_id'], "Recorded repayment", "Loan ID: $loan_id, Amount: $amount");
        $pdo->commit();
        return true;
    } catch(Exception $e) {
        $pdo->rollBack();
        throw $e;
    }
}

function confirmMember($member_id, $confirm_by) {
    global $pdo;
    $stmt = $pdo->prepare("UPDATE wanakikundi SET status = 'active', confirmed_by = ?, confirmed_at = NOW() WHERE id = ?");
    return $stmt->execute([$confirm_by, $member_id]);
}

function changePassword($user_id, $new_password) {
    global $pdo;
    $hashed = password_hash($new_password, PASSWORD_DEFAULT);
    $stmt = $pdo->prepare("UPDATE wanakikundi SET password = ? WHERE id = ?");
    return $stmt->execute([$hashed, $user_id]);
}

function getMembers() {
    global $pdo;
    $stmt = $pdo->query("SELECT * FROM wanakikundi ORDER BY first_name");
    return $stmt->fetchAll();
}

function getMember($id) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT * FROM wanakikundi WHERE id = ?");
    $stmt->execute([$id]);
    return $stmt->fetch();
}

function getLoans($status = null) {
    global $pdo;
    $sql = "SELECT l.*, CONCAT(m.first_name, ' ', m.last_name) as member_name, m.phone as member_phone 
            FROM mikopo l 
            LEFT JOIN wanakikundi m ON l.member_id = m.id";
    if($status) {
        $sql .= " WHERE l.status = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$status]);
    } else {
        $stmt = $pdo->query($sql);
    }
    return $stmt->fetchAll();
}

function getMemberLoans($member_id) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT * FROM mikopo WHERE member_id = ? ORDER BY created_at DESC");
    $stmt->execute([$member_id]);
    return $stmt->fetchAll();
}

function getContributions($member_id = null) {
    global $pdo;
    $sql = "SELECT c.*, CONCAT(m.first_name, ' ', m.last_name) as member_name 
            FROM chango c 
            LEFT JOIN wanakikundi m ON c.member_id = m.id";
    if($member_id) {
        $sql .= " WHERE c.member_id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$member_id]);
    } else {
        $stmt = $pdo->query($sql);
    }
    return $stmt->fetchAll();
}

function getAnnouncements() {
    global $pdo;
    $stmt = $pdo->query("SELECT a.*, CONCAT(m.first_name, ' ', m.last_name) as posted_by_name 
                         FROM matangazo a 
                         LEFT JOIN wanakikundi m ON a.posted_by = m.id 
                         WHERE a.status = 'active' AND (a.expires_at IS NULL OR a.expires_at >= CURDATE())
                         ORDER BY a.posted_at DESC");
    return $stmt->fetchAll();
}

function getInstructions() {
    global $pdo;
    $stmt = $pdo->query("SELECT i.*, CONCAT(m.first_name, ' ', m.last_name) as updated_by_name 
                         FROM maelekezo i 
                         LEFT JOIN wanakikundi m ON i.updated_by = m.id 
                         ORDER BY i.updated_at DESC LIMIT 1");
    return $stmt->fetch();
}

function updateInstructions($title, $content, $user_id) {
    global $pdo;
    $stmt = $pdo->prepare("INSERT INTO maelekezo (title, content, updated_by) VALUES (?, ?, ?)");
    return $stmt->execute([$title, $content, $user_id]);
}

function addAnnouncement($title, $content, $user_id, $expires_at = null) {
    global $pdo;
    $stmt = $pdo->prepare("INSERT INTO matangazo (title, content, posted_by, expires_at) VALUES (?, ?, ?, ?)");
    return $stmt->execute([$title, $content, $user_id, $expires_at]);
}

function getTotals() {
    global $pdo;
    $result = [];
    $stmt = $pdo->query("SELECT SUM(amount) as total FROM chango");
    $result['total_funds'] = $stmt->fetch()['total'] ?? 0;
    $stmt = $pdo->query("SELECT SUM(amount) as total FROM mikopo WHERE status IN ('approved','active')");
    $result['total_loans'] = $stmt->fetch()['total'] ?? 0;
    $stmt = $pdo->query("SELECT SUM(repaid) as total FROM mikopo");
    $result['total_repaid'] = $stmt->fetch()['total'] ?? 0;
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM mikopo WHERE status = 'overdue'");
    $result['overdue_loans'] = $stmt->fetch()['count'] ?? 0;
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM wanakikundi WHERE status = 'active'");
    $result['total_members'] = $stmt->fetch()['count'] ?? 0;
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM wanakikundi WHERE status = 'pending'");
    $result['pending_members'] = $stmt->fetch()['count'] ?? 0;
    return $result;
}

function getPendingMembers() {
    global $pdo;
    $stmt = $pdo->query("SELECT * FROM wanakikundi WHERE status = 'pending' ORDER BY created_at ASC");
    return $stmt->fetchAll();
}

function getPendingLoans() {
    global $pdo;
    $stmt = $pdo->query("SELECT l.*, CONCAT(m.first_name, ' ', m.last_name) as member_name, m.phone as member_phone 
                         FROM mikopo l 
                         LEFT JOIN wanakikundi m ON l.member_id = m.id 
                         WHERE l.status = 'pending' 
                         ORDER BY l.created_at ASC");
    return $stmt->fetchAll();
}
?>
