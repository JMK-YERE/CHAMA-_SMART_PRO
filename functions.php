<?php
// functions.php - Additional functions
require_once 'config.php';

function getMembersWithSummary() {
    global $pdo;
    $members = getMembers();
    $result = [];
    foreach($members as $member) {
        $loans = getMemberLoans($member['id']);
        $contributions = getContributions($member['id']);
        $total_loan = array_sum(array_column($loans, 'amount'));
        $total_repaid = array_sum(array_column($loans, 'repaid'));
        $total_contrib = array_sum(array_column($contributions, 'amount'));
        $result[] = [
            'member' => $member,
            'total_loan' => $total_loan,
            'total_repaid' => $total_repaid,
            'total_debt' => $total_loan - $total_repaid,
            'total_contributions' => $total_contrib,
            'loan_count' => count($loans)
        ];
    }
    return $result;
}

function getTopContributors($limit = 5) {
    global $pdo;
    $stmt = $pdo->query("SELECT m.id, CONCAT(m.first_name, ' ', m.last_name) as name, 
                         SUM(c.amount) as total 
                         FROM chango c 
                         JOIN wanakikundi m ON c.member_id = m.id 
                         GROUP BY c.member_id 
                         ORDER BY total DESC LIMIT $limit");
    return $stmt->fetchAll();
}

function getRecentTransactions($limit = 10) {
    global $pdo;
    $stmt = $pdo->query("SELECT 'chango' as type, c.amount, c.date as transaction_date, 
                         CONCAT(m.first_name, ' ', m.last_name) as member_name 
                         FROM chango c 
                         JOIN wanakikundi m ON c.member_id = m.id 
                         UNION ALL 
                         SELECT 'loan_repayment' as type, p.amount, p.payment_date as transaction_date, 
                         CONCAT(m.first_name, ' ', m.last_name) as member_name 
                         FROM malipo_ya_mikopo p 
                         JOIN mikopo l ON p.loan_id = l.id 
                         JOIN wanakikundi m ON l.member_id = m.id 
                         ORDER BY transaction_date DESC LIMIT $limit");
    return $stmt->fetchAll();
}

function getLoanStats() {
    global $pdo;
    $stats = [];
    
    // Total loans by status
    $stmt = $pdo->query("SELECT status, COUNT(*) as count, SUM(amount) as total_amount 
                         FROM mikopo GROUP BY status");
    $stats['by_status'] = $stmt->fetchAll();
    
    // Total loans by month
    $stmt = $pdo->query("SELECT DATE_FORMAT(created_at, '%Y-%m') as month, 
                         COUNT(*) as count, SUM(amount) as total 
                         FROM mikopo 
                         WHERE created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH) 
                         GROUP BY month ORDER BY month DESC");
    $stats['by_month'] = $stmt->fetchAll();
    
    return $stats;
}

function getMemberActivity($member_id) {
    global $pdo;
    $activity = [];
    
    // Recent contributions
    $stmt = $pdo->prepare("SELECT 'contribution' as type, amount, date as activity_date, 'Chango' as description 
                          FROM chango WHERE member_id = ? ORDER BY date DESC LIMIT 5");
    $stmt->execute([$member_id]);
    $activity['contributions'] = $stmt->fetchAll();
    
    // Recent loan repayments
    $stmt = $pdo->prepare("SELECT 'repayment' as type, amount, payment_date as activity_date, 'Malipo ya Mkopo' as description 
                          FROM malipo_ya_mikopo p 
                          JOIN mikopo l ON p.loan_id = l.id 
                          WHERE l.member_id = ? ORDER BY payment_date DESC LIMIT 5");
    $stmt->execute([$member_id]);
    $activity['repayments'] = $stmt->fetchAll();
    
    return $activity;
}
?>
