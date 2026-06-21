<?php
// download.php - Download Reports (CSV)
require_once 'config.php';

if(!isLoggedIn()) {
    header('Location: index.php');
    exit();
}

if(!hasRole(['mhazina', 'mwenyekiti', 'mkaguzi'])) {
    die('Huna ruhusa ya kudownload ripoti');
}

$type = $_GET['type'] ?? 'members';

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $type . '_' . date('Y-m-d') . '.csv"');

$output = fopen('php://output', 'w');

// Add BOM for UTF-8
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

if($type === 'members') {
    fputcsv($output, ['Jina', 'Simu', 'Jukumu', 'Hali', 'Akiba', 'Deni']);
    $members = getMembers();
    foreach($members as $member) {
        $loans = getMemberLoans($member['id']);
        $total_loan = array_sum(array_column($loans, 'amount'));
        $total_repaid = array_sum(array_column($loans, 'repaid'));
        $debt = $total_loan - $total_repaid;
        fputcsv($output, [
            $member['first_name'] . ' ' . $member['last_name'],
            $member['phone'],
            $member['role'],
            $member['status'],
            $member['savings'],
            $debt
        ]);
    }
} elseif($type === 'loans') {
    fputcsv($output, ['Mkopaji', 'Kiasi', 'Kilicholipwa', 'Deni', 'Muda', 'Hali', 'Tarehe']);
    $loans = getLoans();
    foreach($loans as $loan) {
        fputcsv($output, [
            $loan['member_name'] ?? 'Haijulikani',
            $loan['amount'],
            $loan['repaid'],
            $loan['amount'] - $loan['repaid'],
            $loan['term_months'] . ' miezi',
            $loan['status'],
            date('d/m/Y', strtotime($loan['created_at']))
        ]);
    }
} elseif($type === 'contributions') {
    fputcsv($output, ['Mwanakikundi', 'Kiasi', 'Tarehe', 'Njia']);
    $contributions = getContributions();
    foreach($contributions as $c) {
        fputcsv($output, [
            $c['member_name'] ?? 'Haijulikani',
            $c['amount'],
            date('d/m/Y', strtotime($c['date'])),
            $c['payment_method'] ?? 'cash'
        ]);
    }
} elseif($type === 'payments') {
    fputcsv($output, ['Mkopaji', 'Kiasi', 'Tarehe']);
    $stmt = $pdo->query("SELECT p.*, CONCAT(m.first_name, ' ', m.last_name) as member_name 
                         FROM malipo_ya_mikopo p 
                         JOIN mikopo l ON p.loan_id = l.id 
                         JOIN wanakikundi m ON l.member_id = m.id");
    $payments = $stmt->fetchAll();
    foreach($payments as $p) {
        fputcsv($output, [
            $p['member_name'],
            $p['amount'],
            date('d/m/Y', strtotime($p['payment_date']))
        ]);
    }
}

fclose($output);
exit();
?>
