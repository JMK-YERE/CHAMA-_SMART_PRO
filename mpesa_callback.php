<?php
// mpesa_callback.php - M-PESA Callback Handler
require_once 'config.php';

// Get callback data
$data = file_get_contents('php://input');
$callback = json_decode($data, true);

if($callback && isset($callback['Body']['stkCallback'])) {
    $result = $callback['Body']['stkCallback'];
    $merchant_request_id = $result['MerchantRequestID'] ?? '';
    $checkout_request_id = $result['CheckoutRequestID'] ?? '';
    $result_code = $result['ResultCode'] ?? 1;
    $result_desc = $result['ResultDesc'] ?? 'Unknown error';
    
    // Update transaction status
    $status = $result_code == 0 ? 'completed' : 'failed';
    
    $stmt = $pdo->prepare("UPDATE mpesa_transactions 
                          SET status = ?, response_code = ?, response_description = ? 
                          WHERE request_id = ?");
    $stmt->execute([$status, $result_code, $result_desc, $checkout_request_id]);
    
    // If successful, record the payment
    if($result_code == 0) {
        // Get transaction details
        $stmt = $pdo->prepare("SELECT * FROM mpesa_transactions WHERE request_id = ?");
        $stmt->execute([$checkout_request_id]);
        $transaction = $stmt->fetch();
        
        if($transaction) {
            // Process based on transaction type
            if($transaction['transaction_type'] === 'contribution') {
                addContribution($transaction['member_id'], $transaction['amount'], 'M-PESA Payment');
            } elseif($transaction['transaction_type'] === 'loan_repayment') {
                // Find active loan for this member
                $stmt = $pdo->prepare("SELECT id FROM mikopo WHERE member_id = ? AND status IN ('approved','active') LIMIT 1");
                $stmt->execute([$transaction['member_id']]);
                $loan = $stmt->fetch();
                if($loan) {
                    recordRepayment($loan['id'], $transaction['amount']);
                }
            }
            
            // Send confirmation
            $member = getMember($transaction['member_id']);
            if($member) {
                $msg = "Malipo yako ya TZS " . number_format($transaction['amount']) . " imethibitishwa!\n";
                $msg .= "Tarehe: " . date('d/m/Y H:i') . "\n";
                $msg .= "Asante kwa kutumia " . APP_NAME . "!";
                sendSMS($member['phone'], $msg);
                sendWhatsAppMessage($member['phone'], $msg);
            }
        }
    }
}

// Return response to M-PESA
echo json_encode(['ResultCode' => 0, 'ResultDesc' => 'Success']);
?>
