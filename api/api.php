<?php
// api.php - Mobile App API Endpoint
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    exit(0);
}

require_once 'config.php';
require_once 'functions.php';

// Get request method and endpoint
$method = $_SERVER['REQUEST_METHOD'];
$endpoint = $_GET['endpoint'] ?? '';

// Validate API token
$token = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
$token = str_replace('Bearer ', '', $token);

$user = null;
if(!empty($token)) {
    $stmt = $pdo->prepare("SELECT * FROM api_tokens WHERE token = ? AND status = 'active' AND (expires_at IS NULL OR expires_at > NOW())");
    $stmt->execute([$token]);
    $api_token = $stmt->fetch();
    
    if($api_token) {
        // Update last used
        $stmt = $pdo->prepare("UPDATE api_tokens SET last_used_at = NOW() WHERE id = ?");
        $stmt->execute([$api_token['id']]);
        
        $user = getMember($api_token['member_id']);
    }
}

// API Response helper functions
function apiResponse($data, $status = 200) {
    http_response_code($status);
    echo json_encode([
        'status' => $status,
        'success' => true,
        'data' => $data,
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    exit();
}

function apiError($message, $code = 400) {
    http_response_code($code);
    echo json_encode([
        'status' => $code,
        'success' => false,
        'error' => $message,
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    exit();
}

// Log API request
function logApiRequest($endpoint, $method, $user_id = null, $request = null, $response = null, $status_code = 200) {
    global $pdo;
    try {
        $stmt = $pdo->prepare("INSERT INTO api_logs (member_id, endpoint, method, request_data, response_data, status_code, ip_address) 
                              VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $user_id,
            $endpoint,
            $method,
            $request ? json_encode($request) : null,
            $response ? json_encode($response) : null,
            $status_code,
            $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0'
        ]);
    } catch(Exception $e) {
        // Silent fail for logging
    }
}

// Authentication required for all endpoints except login and register
if(!$user && $endpoint !== 'login' && $endpoint !== 'register' && $endpoint !== 'ping') {
    apiError('Unauthorized. Please login first.', 401);
}

// Get request data
$request_data = [];
if($method === 'POST' || $method === 'PUT') {
    $input = file_get_contents('php://input');
    $request_data = json_decode($input, true) ?? $_POST;
}

// Handle endpoints
switch($endpoint) {
    
    // ======================
    // PING - Check API status
    // ======================
    case 'ping':
        apiResponse([
            'message' => 'API is running',
            'version' => APP_VERSION,
            'timestamp' => date('Y-m-d H:i:s')
        ]);
        break;
    
    // ======================
    // AUTHENTICATION
    // ======================
    case 'login':
        if($method !== 'POST') apiError('Method not allowed. Use POST', 405);
        
        $phone = $request_data['phone'] ?? $_POST['phone'] ?? '';
        $password = $request_data['password'] ?? $_POST['password'] ?? '';
        $device = $request_data['device_name'] ?? $_POST['device_name'] ?? 'Unknown Device';
        $platform = $request_data['platform'] ?? $_POST['platform'] ?? 'mobile';
        
        if(empty($phone) || empty($password)) {
            apiError('Phone and password are required');
        }
        
        $stmt = $pdo->prepare("SELECT * FROM wanakikundi WHERE phone = ? AND status = 'active'");
        $stmt->execute([$phone]);
        $user = $stmt->fetch();
        
        if($user && password_verify($password, $user['password'])) {
            // Generate API token
            $token = bin2hex(random_bytes(32));
            $expires = date('Y-m-d H:i:s', strtotime('+30 days'));
            
            $stmt = $pdo->prepare("INSERT INTO api_tokens (member_id, token, platform, device_name, ip_address, expires_at) 
                                  VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$user['id'], $token, $platform, $device, $_SERVER['REMOTE_ADDR'] ?? '', $expires]);
            
            $token_id = $pdo->lastInsertId();
            
            logActivity($user['id'], 'API Login', "Platform: $platform, Device: $device");
            
            // Get user's active loans summary
            $loans = getMemberLoans($user['id']);
            $total_loan = array_sum(array_column($loans, 'amount'));
            $total_repaid = array_sum(array_column($loans, 'repaid'));
            
            apiResponse([
                'token' => $token,
                'token_id' => $token_id,
                'expires_at' => $expires,
                'user' => [
                    'id' => $user['id'],
                    'name' => $user['first_name'] . ' ' . $user['last_name'],
                    'phone' => $user['phone'],
                    'email' => $user['email'],
                    'role' => $user['role'],
                    'savings' => $user['savings'],
                    'status' => $user['status'],
                    'total_loan' => $total_loan,
                    'total_debt' => $total_loan - $total_repaid
                ]
            ]);
        } else {
            logApiRequest($endpoint, $method, null, ['phone' => $phone], ['error' => 'Invalid credentials'], 401);
            apiError('Invalid phone number or password', 401);
        }
        break;
        
    case 'register':
        if($method !== 'POST') apiError('Method not allowed. Use POST', 405);
        
        $first_name = trim($request_data['first_name'] ?? $_POST['first_name'] ?? '');
        $last_name = trim($request_data['last_name'] ?? $_POST['last_name'] ?? '');
        $phone = trim($request_data['phone'] ?? $_POST['phone'] ?? '');
        $email = trim($request_data['email'] ?? $_POST['email'] ?? '');
        $nida = trim($request_data['nida'] ?? $_POST['nida'] ?? '');
        $monthly_income = floatval($request_data['monthly_income'] ?? $_POST['monthly_income'] ?? 0);
        
        if(empty($first_name) || empty($last_name) || empty($phone)) {
            apiError('First name, last name and phone are required');
        }
        
        // Check if phone exists
        $stmt = $pdo->prepare("SELECT id FROM wanakikundi WHERE phone = ?");
        $stmt->execute([$phone]);
        if($stmt->fetch()) {
            apiError('Phone number already registered');
        }
        
        // Check if NIDA exists
        if(!empty($nida)) {
            $stmt = $pdo->prepare("SELECT id FROM wanakikundi WHERE nida = ?");
            $stmt->execute([$nida]);
            if($stmt->fetch()) {
                apiError('NIDA number already registered');
            }
        }
        
        $password = generatePassword($last_name);
        $hashed = password_hash($password, PASSWORD_DEFAULT);
        
        $stmt = $pdo->prepare("INSERT INTO wanakikundi (first_name, last_name, phone, email, nida, monthly_income, password, status) 
                              VALUES (?, ?, ?, ?, ?, ?, ?, 'pending')");
        if($stmt->execute([$first_name, $last_name, $phone, $email, $nida, $monthly_income, $hashed])) {
            $member_id = $pdo->lastInsertId();
            
            // Send SMS with password
            $message = "Karibu " . $first_name . " " . $last_name . " kwenye " . APP_NAME . "!\n";
            $message .= "Nenosiri lako ni: " . $password . "\n";
            $message .= "Wasiliana na Katibu au Mwenyekiti kwa kuthibitishwa.";
            sendSMS($phone, $message);
            sendWhatsAppMessage($phone, $message);
            
            logActivity($member_id, 'API Registration', "Phone: $phone");
            
            apiResponse([
                'message' => 'Registration successful',
                'member_id' => $member_id,
                'password' => $password,
                'status' => 'pending',
                'next_steps' => 'Please wait for account confirmation by the chairperson or secretary'
            ]);
        } else {
            apiError('Registration failed. Please try again.');
        }
        break;
        
    // ======================
    // USER PROFILE
    // ======================
    case 'profile':
        if($method !== 'GET') apiError('Method not allowed. Use GET', 405);
        
        $loans = getMemberLoans($user['id']);
        $total_loan = array_sum(array_column($loans, 'amount'));
        $total_repaid = array_sum(array_column($loans, 'repaid'));
        
        apiResponse([
            'id' => $user['id'],
            'name' => $user['first_name'] . ' ' . $user['last_name'],
            'first_name' => $user['first_name'],
            'last_name' => $user['last_name'],
            'phone' => $user['phone'],
            'email' => $user['email'],
            'nida' => $user['nida'],
            'role' => $user['role'],
            'savings' => $user['savings'],
            'monthly_income' => $user['monthly_income'],
            'status' => $user['status'],
            'join_date' => $user['join_date'],
            'last_login' => $user['last_login'],
            'total_loans' => count($loans),
            'total_loan_amount' => $total_loan,
            'total_debt' => $total_loan - $total_repaid
        ]);
        break;
        
    case 'profile/update':
        if($method !== 'POST' && $method !== 'PUT') apiError('Method not allowed. Use POST or PUT', 405);
        
        $email = $request_data['email'] ?? $_POST['email'] ?? $user['email'];
        $monthly_income = floatval($request_data['monthly_income'] ?? $_POST['monthly_income'] ?? $user['monthly_income']);
        $mpesa_phone = $request_data['mpesa_phone'] ?? $_POST['mpesa_phone'] ?? $user['mpesa_phone'];
        
        $stmt = $pdo->prepare("UPDATE wanakikundi SET email = ?, monthly_income = ?, mpesa_phone = ? WHERE id = ?");
        if($stmt->execute([$email, $monthly_income, $mpesa_phone, $user['id']])) {
            logActivity($user['id'], 'API Profile Update', "Email: $email, Income: $monthly_income");
            apiResponse(['message' => 'Profile updated successfully']);
        } else {
            apiError('Update failed');
        }
        break;
        
    case 'profile/password':
        if($method !== 'POST' && $method !== 'PUT') apiError('Method not allowed. Use POST or PUT', 405);
        
        $current = $request_data['current'] ?? $_POST['current'] ?? '';
        $new = $request_data['new'] ?? $_POST['new'] ?? '';
        
        if(empty($current) || empty($new)) {
            apiError('Current and new password are required');
        }
        
        if(!password_verify($current, $user['password'])) {
            apiError('Current password is incorrect');
        }
        
        if(strlen($new) < 6) {
            apiError('New password must be at least 6 characters');
        }
        
        $hashed = password_hash($new, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("UPDATE wanakikundi SET password = ? WHERE id = ?");
        if($stmt->execute([$hashed, $user['id']])) {
            logActivity($user['id'], 'API Password Change', 'Password updated');
            apiResponse(['message' => 'Password changed successfully']);
        } else {
            apiError('Password change failed');
        }
        break;
        
    // ======================
    // ACCOUNT MANAGEMENT
    // ======================
    case 'account/confirm':
        if($method !== 'POST') apiError('Method not allowed. Use POST', 405);
        
        if(!in_array($user['role'], ['mwenyekiti', 'katibu'])) {
            apiError('Unauthorized. Only Chairperson or Secretary can confirm accounts', 403);
        }
        
        $member_id = intval($request_data['member_id'] ?? $_POST['member_id'] ?? 0);
        if($member_id <= 0) apiError('Invalid member ID');
        
        if(confirmMember($member_id, $user['id'])) {
            $member = getMember($member_id);
            if($member) {
                $message = "Akaunti yako kwenye " . APP_NAME . " imethibitishwa!\n";
                $message .= "Sasa unaweza kuomba mikopo na kushiriki katika shughuli zote.";
                sendSMS($member['phone'], $message);
                sendWhatsAppMessage($member['phone'], $message);
            }
            logActivity($user['id'], 'API Confirm Member', "Member ID: $member_id");
            apiResponse(['message' => 'Account confirmed successfully']);
        } else {
            apiError('Confirmation failed');
        }
        break;
        
    case 'account/pending':
        if($method !== 'GET') apiError('Method not allowed. Use GET', 405);
        
        if(!in_array($user['role'], ['mwenyekiti', 'katibu'])) {
            apiError('Unauthorized. Only Chairperson or Secretary can view pending accounts', 403);
        }
        
        $pending = getPendingMembers();
        apiResponse($pending);
        break;
        
    // ======================
    // CONTRIBUTIONS
    // ======================
    case 'contributions':
        if($method !== 'GET') apiError('Method not allowed. Use GET', 405);
        
        $limit = intval($_GET['limit'] ?? 50);
        $stmt = $pdo->prepare("SELECT * FROM chango WHERE member_id = ? ORDER BY date DESC LIMIT ?");
        $stmt->execute([$user['id'], $limit]);
        $contributions = $stmt->fetchAll();
        
        apiResponse([
            'total' => count($contributions),
            'total_amount' => array_sum(array_column($contributions, 'amount')),
            'contributions' => $contributions
        ]);
        break;
        
    case 'contributions/add':
        if($method !== 'POST') apiError('Method not allowed. Use POST', 405);
        
        if(!in_array($user['role'], ['mhazina', 'mwenyekiti'])) {
            apiError('Unauthorized. Only Treasurer or Chairperson can add contributions', 403);
        }
        
        $member_id = intval($request_data['member_id'] ?? $_POST['member_id'] ?? 0);
        $amount = floatval($request_data['amount'] ?? $_POST['amount'] ?? 0);
        $description = $request_data['description'] ?? $_POST['description'] ?? '';
        $payment_method = $request_data['payment_method'] ?? $_POST['payment_method'] ?? 'cash';
        
        if($member_id <= 0 || $amount <= 0) {
            apiError('Invalid amount or member');
        }
        
        try {
            addContribution($member_id, $amount, $description, $payment_method);
            logActivity($user['id'], 'API Add Contribution', "Member: $member_id, Amount: $amount");
            apiResponse(['message' => 'Contribution added successfully']);
        } catch(Exception $e) {
            apiError($e->getMessage());
        }
        break;
        
    // ======================
    // LOANS
    // ======================
    case 'loans':
        if($method !== 'GET') apiError('Method not allowed. Use GET', 405);
        
        $stmt = $pdo->prepare("SELECT * FROM mikopo WHERE member_id = ? ORDER BY created_at DESC");
        $stmt->execute([$user['id']]);
        $loans = $stmt->fetchAll();
        
        $total_amount = array_sum(array_column($loans, 'amount'));
        $total_repaid = array_sum(array_column($loans, 'repaid'));
        $active_loans = array_filter($loans, function($l) {
            return in_array($l['status'], ['approved', 'active']);
        });
        
        apiResponse([
            'total_loans' => count($loans),
            'total_amount' => $total_amount,
            'total_debt' => $total_amount - $total_repaid,
            'active_loans' => count($active_loans),
            'loans' => $loans
        ]);
        break;
        
    case 'loans/request':
        if($method !== 'POST') apiError('Method not allowed. Use POST', 405);
        
        $amount = floatval($request_data['amount'] ?? $_POST['amount'] ?? 0);
        $term = intval($request_data['term_months'] ?? $_POST['term_months'] ?? 0);
        $purpose = $request_data['purpose'] ?? $_POST['purpose'] ?? '';
        $interest_rate = floatval($request_data['interest_rate'] ?? $_POST['interest_rate'] ?? null);
        
        if($amount <= 0 || $term <= 0) {
            apiError('Invalid loan amount or term');
        }
        
        // Check eligibility
        $eligibility = isEligibleForLoan($user['id'], $amount);
        if(!$eligibility['eligible']) {
            apiError($eligibility['reason']);
        }
        
        if(requestLoan($user['id'], $amount, $term, $purpose, $interest_rate)) {
            // Notify loan officers
            $loan_officers = getMembers();
            foreach($loan_officers as $officer) {
                if(in_array($officer['role'], ['mwenyekiti', 'mhazina', 'mkaguzi'])) {
                    $msg = "Ombi la mkopo kutoka " . $user['first_name'] . " " . $user['last_name'] . "\n";
                    $msg .= "Kiasi: TZS " . number_format($amount) . "\n";
                    $msg .= "Muda: " . $term . " miezi\n";
                    $msg .= "Madhumuni: " . $purpose;
                    sendWhatsAppMessage($officer['phone'], $msg);
                    sendSMS($officer['phone'], $msg);
                }
            }
            logActivity($user['id'], 'API Loan Request', "Amount: $amount, Term: $term");
            apiResponse([
                'message' => 'Loan request submitted successfully',
                'status' => 'pending',
                'estimated_response' => 'Within 24 hours'
            ]);
        } else {
            apiError('Failed to submit loan request');
        }
        break;
        
    case 'loans/approve':
        if($method !== 'POST') apiError('Method not allowed. Use POST', 405);
        
        if(!in_array($user['role'], ['mwenyekiti', 'mhazina', 'mkaguzi'])) {
            apiError('Unauthorized. Only Chairperson, Treasurer or Auditor can approve loans', 403);
        }
        
        $loan_id = intval($request_data['loan_id'] ?? $_POST['loan_id'] ?? 0);
        if($loan_id <= 0) apiError('Invalid loan ID');
        
        if(approveLoan($loan_id, $user['id'])) {
            // Notify member
            $stmt = $pdo->prepare("SELECT member_id FROM mikopo WHERE id = ?");
            $stmt->execute([$loan_id]);
            $loan = $stmt->fetch();
            if($loan) {
                $member = getMember($loan['member_id']);
                if($member) {
                    $msg = "Mkopo wako kwenye " . APP_NAME . " umeidhinishwa!\n";
                    $msg .= "Angalia dashboard yako kwa maelezo zaidi.";
                    sendSMS($member['phone'], $msg);
                    sendWhatsAppMessage($member['phone'], $msg);
                }
            }
            logActivity($user['id'], 'API Approve Loan', "Loan ID: $loan_id");
            apiResponse(['message' => 'Loan approved successfully']);
        } else {
            apiError('Approval failed');
        }
        break;
        
    case 'loans/reject':
        if($method !== 'POST') apiError('Method not allowed. Use POST', 405);
        
        if(!in_array($user['role'], ['mwenyekiti', 'mhazina', 'mkaguzi'])) {
            apiError('Unauthorized. Only Chairperson, Treasurer or Auditor can reject loans', 403);
        }
        
        $loan_id = intval($request_data['loan_id'] ?? $_POST['loan_id'] ?? 0);
        $reason = $request_data['reason'] ?? $_POST['reason'] ?? 'Mkopo haukukubaliwa';
        
        if($loan_id <= 0) apiError('Invalid loan ID');
        
        if(rejectLoan($loan_id, $user['id'])) {
            // Notify member
            $stmt = $pdo->prepare("SELECT member_id FROM mikopo WHERE id = ?");
            $stmt->execute([$loan_id]);
            $loan = $stmt->fetch();
            if($loan) {
                $member = getMember($loan['member_id']);
                if($member) {
                    $msg = "Ombi lako la mkopo kwenye " . APP_NAME . " limekataliwa.\n";
                    $msg .= "Sababu: " . $reason . "\n";
                    $msg .= "Wasiliana na Mhazina kwa maelezo zaidi.";
                    sendSMS($member['phone'], $msg);
                    sendWhatsAppMessage($member['phone'], $msg);
                }
            }
            logActivity($user['id'], 'API Reject Loan', "Loan ID: $loan_id");
            apiResponse(['message' => 'Loan rejected successfully']);
        } else {
            apiError('Rejection failed');
        }
        break;
        
    // ======================
    // REPAYMENTS
    // ======================
    case 'repayments':
        if($method !== 'GET') apiError('Method not allowed. Use GET', 405);
        
        $limit = intval($_GET['limit'] ?? 20);
        $stmt = $pdo->prepare("SELECT p.*, l.amount as loan_amount, l.status as loan_status 
                              FROM malipo_ya_mikopo p 
                              JOIN mikopo l ON p.loan_id = l.id 
                              WHERE l.member_id = ? 
                              ORDER BY p.payment_date DESC LIMIT ?");
        $stmt->execute([$user['id'], $limit]);
        $repayments = $stmt->fetchAll();
        
        apiResponse([
            'total' => count($repayments),
            'total_amount' => array_sum(array_column($repayments, 'amount')),
            'repayments' => $repayments
        ]);
        break;
        
    case 'repayments/add':
        if($method !== 'POST') apiError('Method not allowed. Use POST', 405);
        
        $loan_id = intval($request_data['loan_id'] ?? $_POST['loan_id'] ?? 0);
        $amount = floatval($request_data['amount'] ?? $_POST['amount'] ?? 0);
        
        if($loan_id <= 0 || $amount <= 0) {
            apiError('Invalid loan or amount');
        }
        
        try {
            recordRepayment($loan_id, $amount);
            logActivity($user['id'], 'API Record Repayment', "Loan: $loan_id, Amount: $amount");
            apiResponse(['message' => 'Repayment recorded successfully']);
        } catch(Exception $e) {
            apiError($e->getMessage());
        }
        break;
        
    // ======================
    // M-PESA
    // ======================
    case 'mpesa/pay':
        if($method !== 'POST') apiError('Method not allowed. Use POST', 405);
        
        $type = $request_data['type'] ?? $_POST['type'] ?? 'contribution';
        $amount = floatval($request_data['amount'] ?? $_POST['amount'] ?? 0);
        $phone = $request_data['phone'] ?? $_POST['phone'] ?? $user['mpesa_phone'] ?? $user['phone'];
        
        if($amount <= 0) {
            apiError('Invalid amount');
        }
        
        $reference = 'CHAMA' . date('Ymd') . rand(1000, 9999);
        $description = $type === 'contribution' ? 'Chango ya kikundi' : 'Malipo ya mkopo';
        
        // Save transaction
        $stmt = $pdo->prepare("INSERT INTO mpesa_transactions 
                              (member_id, transaction_type, amount, phone, request_id, status) 
                              VALUES (?, ?, ?, ?, ?, 'pending')");
        $stmt->execute([$user['id'], $type, $amount, $phone, $reference]);
        $transaction_id = $pdo->lastInsertId();
        
        $mpesa_result = mpesaStkPush($phone, $amount, $reference, $description);
        
        if(isset($mpesa_result['ResponseCode']) && $mpesa_result['ResponseCode'] == '0') {
            $stmt = $pdo->prepare("UPDATE mpesa_transactions SET request_id = ? WHERE id = ?");
            $stmt->execute([$mpesa_result['CheckoutRequestID'], $transaction_id]);
            
            logActivity($user['id'], 'API M-PESA Payment', "Amount: $amount, Phone: $phone");
            
            apiResponse([
                'message' => 'M-PESA payment initiated',
                'checkout_request_id' => $mpesa_result['CheckoutRequestID'],
                'transaction_id' => $transaction_id,
                'status' => 'pending',
                'instructions' => 'Please enter your M-PESA PIN on your phone to complete payment'
            ]);
        } else {
            apiError($mpesa_result['errorMessage'] ?? 'M-PESA payment failed');
        }
        break;
        
    case 'mpesa/status':
        if($method !== 'GET') apiError('Method not allowed. Use GET', 405);
        
        $transaction_id = intval($_GET['id'] ?? 0);
        if($transaction_id <= 0) apiError('Invalid transaction ID');
        
        $stmt = $pdo->prepare("SELECT * FROM mpesa_transactions WHERE id = ? AND member_id = ?");
        $stmt->execute([$transaction_id, $user['id']]);
        $transaction = $stmt->fetch();
        
        if($transaction) {
            apiResponse($transaction);
        } else {
            apiError('Transaction not found');
        }
        break;
        
    // ======================
    // CHAT
    // ======================
    case 'chat/rooms':
        if($method !== 'GET') apiError('Method not allowed. Use GET', 405);
        
        $stmt = $pdo->prepare("SELECT r.*, 
                              (SELECT COUNT(*) FROM chat_messages m WHERE m.room_id = r.id AND m.read_at IS NULL AND m.sender_id != ?) as unread_count
                              FROM chat_rooms r 
                              JOIN chat_participants p ON r.id = p.room_id 
                              WHERE p.member_id = ? AND r.status = 'active'
                              ORDER BY r.created_at DESC");
        $stmt->execute([$user['id'], $user['id']]);
        $rooms = $stmt->fetchAll();
        
        // Get other participant names for private chats
        foreach($rooms as &$room) {
            if($room['room_type'] === 'private') {
                $stmt = $pdo->prepare("SELECT CONCAT(u.first_name, ' ', u.last_name) as name 
                                       FROM chat_participants p 
                                       JOIN wanakikundi u ON p.member_id = u.id 
                                       WHERE p.room_id = ? AND p.member_id != ?");
                $stmt->execute([$room['id'], $user['id']]);
                $other = $stmt->fetch();
                $room['other_name'] = $other ? $other['name'] : 'Mwanakikundi';
            }
            // Get last message
            $stmt = $pdo->prepare("SELECT message, created_at FROM chat_messages WHERE room_id = ? ORDER BY created_at DESC LIMIT 1");
            $stmt->execute([$room['id']]);
            $last = $stmt->fetch();
            $room['last_message'] = $last ? $last['message'] : '';
            $room['last_message_time'] = $last ? $last['created_at'] : '';
        }
        
        apiResponse($rooms);
        break;
        
    case 'chat/messages':
        if($method !== 'GET') apiError('Method not allowed. Use GET', 405);
        
        $room_id = intval($_GET['room_id'] ?? 0);
        if($room_id <= 0) apiError('Invalid room ID');
        
        // Check if user is participant
        $stmt = $pdo->prepare("SELECT * FROM chat_participants WHERE room_id = ? AND member_id = ?");
        $stmt->execute([$room_id, $user['id']]);
        if(!$stmt->fetch()) {
            apiError('Unauthorized', 403);
        }
        
        $limit = intval($_GET['limit'] ?? 50);
        $offset = intval($_GET['offset'] ?? 0);
        
        $stmt = $pdo->prepare("SELECT m.*, CONCAT(u.first_name, ' ', u.last_name) as sender_name 
                              FROM chat_messages m 
                              JOIN wanakikundi u ON m.sender_id = u.id 
                              WHERE m.room_id = ? 
                              ORDER BY m.created_at DESC LIMIT ? OFFSET ?");
        $stmt->execute([$room_id, $limit, $offset]);
        $messages = $stmt->fetchAll();
        
        // Mark as read
        $stmt = $pdo->prepare("UPDATE chat_messages SET read_at = NOW() WHERE room_id = ? AND sender_id != ? AND read_at IS NULL");
        $stmt->execute([$room_id, $user['id']]);
        
        apiResponse(array_reverse($messages));
        break;
        
    case 'chat/send':
        if($method !== 'POST') apiError('Method not allowed. Use POST', 405);
        
        $room_id = intval($request_data['room_id'] ?? $_POST['room_id'] ?? 0);
        $message = trim($request_data['message'] ?? $_POST['message'] ?? '');
        
        if($room_id <= 0 || empty($message)) {
            apiError('Invalid room or message');
        }
        
        // Check if user is participant
        $stmt = $pdo->prepare("SELECT * FROM chat_participants WHERE room_id = ? AND member_id = ?");
        $stmt->execute([$room_id, $user['id']]);
        if(!$stmt->fetch()) {
            apiError('Unauthorized', 403);
        }
        
        $stmt = $pdo->prepare("INSERT INTO chat_messages (room_id, sender_id, message) VALUES (?, ?, ?)");
        if($stmt->execute([$room_id, $user['id'], $message])) {
            $message_id = $pdo->lastInsertId();
            
            // Get room members for notification
            $stmt = $pdo->prepare("SELECT p.member_id, u.phone, u.first_name, u.last_name 
                                  FROM chat_participants p 
                                  JOIN wanakikundi u ON p.member_id = u.id 
                                  WHERE p.room_id = ? AND p.member_id != ?");
            $stmt->execute([$room_id, $user['id']]);
            $members = $stmt->fetchAll();
            
            foreach($members as $member) {
                $msg = "Ujumbe mpya kutoka " . $user['first_name'] . " " . $user['last_name'] . ":\n" . $message;
                sendWhatsAppMessage($member['phone'], $msg);
                sendSMS($member['phone'], $msg);
            }
            
            logActivity($user['id'], 'API Chat Message', "Room: $room_id");
            apiResponse([
                'message' => 'Sent successfully',
                'message_id' => $message_id,
                'timestamp' => date('Y-m-d H:i:s')
            ]);
        } else {
            apiError('Failed to send message');
        }
        break;
        
    case 'chat/create':
        if($method !== 'POST') apiError('Method not allowed. Use POST', 405);
        
        $member_id = intval($request_data['member_id'] ?? $_POST['member_id'] ?? 0);
        $initial_message = trim($request_data['message'] ?? $_POST['message'] ?? '');
        
        if($member_id <= 0 || $member_id == $user['id']) {
            apiError('Invalid member');
        }
        
        // Check if chat exists
        $stmt = $pdo->prepare("SELECT r.id FROM chat_rooms r 
                              JOIN chat_participants p1 ON r.id = p1.room_id 
                              JOIN chat_participants p2 ON r.id = p2.room_id 
                              WHERE r.room_type = 'private' 
                              AND p1.member_id = ? AND p2.member_id = ?");
        $stmt->execute([$user['id'], $member_id]);
        $room = $stmt->fetch();
        
        if(!$room) {
            // Create new private room
            $stmt = $pdo->prepare("INSERT INTO chat_rooms (room_name, room_type, created_by) VALUES (?, 'private', ?)");
            $room_name = 'chat_' . $user['id'] . '_' . $member_id . '_' . time();
            $stmt->execute([$room_name, $user['id']]);
            $room_id = $pdo->lastInsertId();
            
            // Add participants
            $stmt = $pdo->prepare("INSERT INTO chat_participants (room_id, member_id) VALUES (?, ?), (?, ?)");
            $stmt->execute([$room_id, $user['id'], $room_id, $member_id]);
        } else {
            $room_id = $room['id'];
        }
        
        // Send initial message if provided
        if(!empty($initial_message)) {
            $stmt = $pdo->prepare("INSERT INTO chat_messages (room_id, sender_id, message) VALUES (?, ?, ?)");
            $stmt->execute([$room_id, $user['id'], $initial_message]);
        }
        
        apiResponse([
            'message' => 'Chat created successfully',
            'room_id' => $room_id
        ]);
        break;
        
    // ======================
    // NOTIFICATIONS
    // ======================
    case 'notifications':
        if($method !== 'GET') apiError('Method not allowed. Use GET', 
