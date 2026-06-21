<?php
// reports.php - Generate Reports (PDF, Excel, CSV)
require_once 'config.php';

if(!isLoggedIn()) {
    header('Location: index.php');
    exit();
}

if(!hasRole(['mhazina', 'mwenyekiti', 'mkaguzi'])) {
    die('Huna ruhusa ya kuona ripoti');
}

use Dompdf\Dompdf;
use Dompdf\Options;

$type = $_GET['type'] ?? 'members';
$format = $_GET['format'] ?? 'pdf';

// Get data
$data = [];
$headers = [];

if($type === 'members') {
    $members = getMembers();
    foreach($members as $member) {
        $loans = getMemberLoans($member['id']);
        $total_loan = array_sum(array_column($loans, 'amount'));
        $total_repaid = array_sum(array_column($loans, 'repaid'));
        $debt = $total_loan - $total_repaid;
        $data[] = [
            'Jina' => $member['first_name'] . ' ' . $member['last_name'],
            'Simu' => $member['phone'],
            'Jukumu' => $member['role'],
            'Hali' => $member['status'],
            'Akiba' => number_format($member['savings']),
            'Deni' => number_format($debt),
            'Tarehe' => date('d/m/Y', strtotime($member['join_date']))
        ];
    }
    $headers = ['Jina', 'Simu', 'Jukumu', 'Hali', 'Akiba', 'Deni', 'Tarehe'];
    $title = 'Ripoti ya Wanakikundi';
    
} elseif($type === 'loans') {
    $loans = getLoans();
    foreach($loans as $loan) {
        $data[] = [
            'Mkopaji' => $loan['member_name'] ?? 'Haijulikani',
            'Kiasi' => number_format($loan['amount']),
            'Kilicholipwa' => number_format($loan['repaid']),
            'Deni' => number_format($loan['amount'] - $loan['repaid']),
            'Muda' => $loan['term_months'] . ' miezi',
            'Hali' => $loan['status'],
            'Tarehe' => date('d/m/Y', strtotime($loan['created_at']))
        ];
    }
    $headers = ['Mkopaji', 'Kiasi', 'Kilicholipwa', 'Deni', 'Muda', 'Hali', 'Tarehe'];
    $title = 'Ripoti ya Mikopo';
    
} elseif($type === 'contributions') {
    $contributions = getContributions();
    foreach($contributions as $c) {
        $data[] = [
            'Mwanakikundi' => $c['member_name'] ?? 'Haijulikani',
            'Kiasi' => number_format($c['amount']),
            'Tarehe' => date('d/m/Y', strtotime($c['date'])),
            'Njia' => $c['payment_method'] ?? 'cash',
            'Maelezo' => $c['description'] ?? '-'
        ];
    }
    $headers = ['Mwanakikundi', 'Kiasi', 'Tarehe', 'Njia', 'Maelezo'];
    $title = 'Ripoti ya Chango';
    
} elseif($type === 'payments') {
    $stmt = $pdo->query("SELECT p.*, CONCAT(m.first_name, ' ', m.last_name) as member_name 
                         FROM malipo_ya_mikopo p 
                         JOIN mikopo l ON p.loan_id = l.id 
                         JOIN wanakikundi m ON l.member_id = m.id
                         ORDER BY p.payment_date DESC");
    $payments = $stmt->fetchAll();
    foreach($payments as $p) {
        $data[] = [
            'Mkopaji' => $p['member_name'],
            'Kiasi' => number_format($p['amount']),
            'Tarehe' => date('d/m/Y', strtotime($p['payment_date'])),
            'Njia' => $p['payment_method'] ?? 'cash',
            'Hali' => $p['confirmed'] ? 'Imethibitishwa' : 'Haijathibitishwa'
        ];
    }
    $headers = ['Mkopaji', 'Kiasi', 'Tarehe', 'Njia', 'Hali'];
    $title = 'Ripoti ya Malipo ya Mikopo';
}

// Generate HTML for PDF
function generateReportHTML($headers, $data, $title) {
    $html = '
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <title>' . APP_NAME . ' - ' . $title . '</title>
        <style>
            body { font-family: Arial, sans-serif; padding: 20px; }
            .header { text-align: center; margin-bottom: 30px; }
            .header h1 { color: #0d9488; font-size: 28px; margin: 0; }
            .header p { color: #64748b; margin: 5px 0; }
            .header .date { color: #94a3b8; font-size: 14px; }
            table { width: 100%; border-collapse: collapse; margin: 20px 0; font-size: 13px; }
            th { background: #0d9488; color: white; padding: 10px; text-align: left; }
            td { padding: 8px 10px; border-bottom: 1px solid #e2e8f0; }
            tr:nth-child(even) { background: #f8fafc; }
            .footer { text-align: center; color: #94a3b8; font-size: 12px; margin-top: 30px; padding-top: 20px; border-top: 1px solid #e2e8f0; }
            .summary { background: #f1f5f9; padding: 15px; border-radius: 8px; margin: 20px 0; }
            .summary span { display: inline-block; margin-right: 30px; }
            .summary strong { color: #0d9488; }
        </style>
    </head>
    <body>
        <div class="header">
            <h1>' . APP_NAME . '</h1>
            <p>' . $title . '</p>
            <p class="date">Tarehe: ' . date('d/m/Y H:i') . '</p>
        </div>
        <div class="summary">
            <span><strong>Jumla:</strong> ' . count($data) . ' rekodi</span>
            <span><strong>Imetolewa:</strong> ' . date('d/m/Y') . '</span>
            <span><strong>Mfumo:</strong> ' . APP_VERSION . '</span>
        </div>
        <table>
            <thead>
                <tr>';
    foreach($headers as $h) {
        $html .= '<th>' . $h . '</th>';
    }
    $html .= '</tr></thead><tbody>';
    
    foreach($data as $row) {
        $html .= '<tr>';
        foreach($row as $cell) {
            $html .= '<td>' . $cell . '</td>';
        }
        $html .= '</tr>';
    }
    
    $html .= '</tbody></table>
        <div class="footer">
            <p>Imetengenezwa na ' . APP_NAME . ' v' . APP_VERSION . ' | ' . date('Y') . '</p>
            <p>Hiki ni hati rasmi ya kikundi</p>
        </div>
    </body>
    </html>';
    
    return $html;
}

// Handle format output
if($format === 'pdf') {
    // Check if Dompdf is installed
    if(!class_exists('Dompdf\Dompdf')) {
        die('Dompdf haijasakinishwa. Endesha: composer require dompdf/dompdf');
    }
    
    $html = generateReportHTML($headers, $data, $title);
    
    $options = new Options();
    $options->set('defaultFont', 'DejaVu Sans');
    $options->set('isHtml5ParserEnabled', true);
    $options->set('isRemoteEnabled', true);
    $dompdf = new Dompdf($options);
    $dompdf->loadHtml($html);
    $dompdf->setPaper('A4', 'landscape');
    $dompdf->render();
    $dompdf->stream($title . '_' . date('Y-m-d') . '.pdf', ['Attachment' => false]);
    exit();
    
} elseif($format === 'excel' || $format === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $title . '_' . date('Y-m-d') . '.csv"');
    
    $output = fopen('php://output', 'w');
    fputcsv($output, $headers);
    foreach($data as $row) {
        fputcsv($output, $row);
    }
    fclose($output);
    exit();
}
?>
<!DOCTYPE html>
<html lang="<?php echo getLanguage(); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo APP_NAME; ?> - Ripoti</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>
<body>
    <div class="container">
        <?php include 'includes/header.php'; ?>
        
        <div class="dashboard-card fade-in">
            <h2><i class="fas fa-file-alt" style="color: #0d9488;"></i> Ripoti za Mfumo</h2>
            
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px; margin-bottom: 24px;">
                <!-- Members Report -->
                <div style="background: #f1f5f9; padding: 16px; border-radius: 12px; text-align: center;">
                    <i class="fas fa-users" style="font-size: 32px; color: #0d9488;"></i>
                    <h4 style="margin: 8px 0;">Wanakikundi</h4>
                    <div style="display: flex; gap: 8px; justify-content: center; flex-wrap: wrap;">
                        <a href="?type=members&format=pdf" class="btn btn-sm btn-primary">
                            <i class="fas fa-file-pdf"></i> PDF
                        </a>
                        <a href="?type=members&format=csv" class="btn btn-sm btn-outline">
                            <i class="fas fa-file-excel"></i> CSV
                        </a>
                    </div>
                </div>
                
                <!-- Loans Report -->
                <div style="background: #f1f5f9; padding: 16px; border-radius: 12px; text-align: center;">
                    <i class="fas fa-hand-holding-usd" style="font-size: 32px; color: #0d9488;"></i>
                    <h4 style="margin: 8px 0;">Mikopo</h4>
                    <div style="display: flex; gap: 8px; justify-content: center; flex-wrap: wrap;">
                        <a href="?type=loans&format=pdf" class="btn btn-sm btn-primary">
                            <i class="fas fa-file-pdf"></i> PDF
                        </a>
                        <a href="?type=loans&format=csv" class="btn btn-sm btn-outline">
                            <i class="fas fa-file-excel"></i> CSV
                        </a>
                    </div>
                </div>
                
                <!-- Contributions Report -->
                <div style="background: #f1f5f9; padding: 16px; border-radius: 12px; text-align: center;">
                    <i class="fas fa-coins" style="font-size: 32px; color: #0d9488;"></i>
                    <h4 style="margin: 8px 0;">Chango</h4>
                    <div style="display: flex; gap: 8px; justify-content: center; flex-wrap: wrap;">
                        <a href="?type=contributions&format=pdf" class="btn btn-sm btn-primary">
                            <i class="fas fa-file-pdf"></i> PDF
                        </a>
                        <a href="?type=contributions&format=csv" class="btn btn-sm btn-outline">
                            <i class="fas fa-file-excel"></i> CSV
                        </a>
                    </div>
                </div>
                
                <!-- Payments Report -->
                <div style="background: #f1f5f9; padding: 16px; border-radius: 12px; text-align: center;">
                    <i class="fas fa-hand-holding-heart" style="font-size: 32px; color: #0d9488;"></i>
                    <h4 style="margin: 8px 0;">Malipo ya Mikopo</h4>
                    <div style="display: flex; gap: 8px; justify-content: center; flex-wrap: wrap;">
                        <a href="?type=payments&format=pdf" class="btn btn-sm btn-primary">
                            <i class="fas fa-file-pdf"></i> PDF
                        </a>
                        <a href="?type=payments&format=csv" class="btn btn-sm btn-outline">
                            <i class="fas fa-file-excel"></i> CSV
                        </a>
                    </div>
                </div>
            </div>
            
            <!-- Quick Stats -->
            <div style="background: #f8fafc; padding: 20px; border-radius: 12px;">
                <h3><i class="fas fa-chart-bar"></i> Takwimu za Haraka</h3>
                <?php 
                $totals = getTotals();
                $members = getMembers();
                $loans = getLoans();
                ?>
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 12px; margin-top: 12px;">
                    <div>
                        <strong>Wanakikundi:</strong> <?php echo count($members); ?>
                    </div>
                    <div>
                        <strong>Wanakikundi Walioamilishwa:</strong> 
                        <?php echo count(array_filter($members, function($m) { return $m['status'] === 'active'; })); ?>
                    </div>
                    <div>
                        <strong>Mikopo Inayoendelea:</strong> 
                        <?php echo count(array_filter($loans, function($l) { return $l['status'] === 'active' || $l['status'] === 'approved'; })); ?>
                    </div>
                    <div>
                        <strong>Mikopo Iliolipwa:</strong> 
                        <?php echo count(array_filter($loans, function($l) { return $l['status'] === 'paid'; })); ?>
                    </div>
                    <div>
                        <strong>Jumla ya Hazina:</strong> 
                        TZS <?php echo number_format($totals['total_funds']); ?>
                    </div>
                    <div>
                        <strong>Jumla ya Mikopo:</strong> 
                        TZS <?php echo number_format($totals['total_loans']); ?>
                    </div>
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
