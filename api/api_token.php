<?php
// api_token.php - Generate API Token for Mobile App
require_once 'config.php';

if(!isLoggedIn()) {
    header('Location: index.php');
    exit();
}

$user = getCurrentUser();
$error = null;
$success = null;
$generated_token = null;

if($_SERVER['REQUEST_METHOD'] === 'POST') {
    $device_name = trim($_POST['device_name'] ?? 'My Device');
    $platform = $_POST['platform'] ?? 'mobile';
    $expires_days = intval($_POST['expires_days'] ?? 30);
    
    if(empty($device_name)) {
        $error = 'Tafadhali weka jina la kifaa';
    } else {
        // Revoke old tokens for this platform
        $stmt = $pdo->prepare("UPDATE api_tokens SET status = 'revoked' WHERE member_id = ? AND platform = ?");
        $stmt->execute([$user['id'], $platform]);
        
        // Generate new token
        $token = bin2hex(random_bytes(32));
        $expires = date('Y-m-d H:i:s', strtotime("+$expires_days days"));
        
        $stmt = $pdo->prepare("INSERT INTO api_tokens (member_id, token, platform, device_name, ip_address, expires_at) 
                              VALUES (?, ?, ?, ?, ?, ?)");
        if($stmt->execute([$user['id'], $token, $platform, $device_name, $_SERVER['REMOTE_ADDR'] ?? '', $expires])) {
            $generated_token = $token;
            $success = 'API token imeundwa kikamilifu!';
            logActivity($user['id'], 'API Token Generated', "Platform: $platform, Device: $device_name");
        } else {
            $error = 'Hitilafu katika kuunda token';
        }
    }
}

// Get existing tokens
$stmt = $pdo->prepare("SELECT * FROM api_tokens WHERE member_id = ? ORDER BY created_at DESC");
$stmt->execute([$user['id']]);
$tokens = $stmt->fetchAll();

// Get active token count
$active_count = 0;
foreach($tokens as $t) {
    if($t['status'] === 'active') $active_count++;
}
?>
<!DOCTYPE html>
<html lang="<?php echo getLanguage(); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo APP_NAME; ?> - API Token</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>
<body>
    <div class="container">
        <?php include 'includes/header.php'; ?>
        
        <div class="dashboard-card fade-in">
            <h2><i class="fas fa-key" style="color: #6366f1;"></i> API Token (Mobile App)</h2>
            
            <?php if($success): ?>
                <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?php echo $success; ?></div>
            <?php endif; ?>
            <?php if($error): ?>
                <div class="alert alert-error"><i class="fas fa-exclamation-circle"></i> <?php echo $error; ?></div>
            <?php endif; ?>
            
            <!-- Active Tokens Stats -->
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(120px, 1fr)); gap: 12px; margin-bottom: 20px;">
                <div style="background: #d1fae5; padding: 12px; border-radius: 10px; text-align: center;">
                    <div style="font-size: 24px; font-weight: 700; color: #065f46;"><?php echo $active_count; ?></div>
                    <div style="font-size: 13px; color: #065f46;">Tokens Zinazotumika</div>
                </div>
                <div style="background: #f1f5f9; padding: 12px; border-radius: 10px; text-align: center;">
                    <div style="font-size: 24px; font-weight: 700; color: #0d9488;"><?php echo count($tokens); ?></div>
                    <div style="font-size: 13px; color: #64748b;">Jumla ya Tokens</div>
                </div>
            </div>
            
            <?php if($generated_token): ?>
                <div class="alert alert-info" style="background: #dbeafe; border-left-color: #3b82f6;">
                    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px;">
                        <div>
                            <strong><i class="fas fa-check-circle" style="color: #3b82f6;"></i> Token yako mpya:</strong>
                            <div style="background: #1e293b; color: #0d9488; padding: 12px; border-radius: 8px; margin-top: 8px; font-family: monospace; word-break: break-all; font-size: 14px;">
                                <?php echo $generated_token; ?>
                            </div>
                        </div>
                        <button onclick="copyToken('<?php echo $generated_token; ?>')" class="btn btn-sm btn-primary" style="width: auto; background: #3b82f6;">
                            <i class="fas fa-copy"></i> Nakili
                        </button>
                    </div>
                    <p style="margin-top: 8px; color: #1e40af; font-size: 13px;">
                        <i class="fas fa-info-circle"></i> 
                        Hifadhi token hii mahali salama. Itatumika kwa muda wa siku 30.
                    </p>
                </div>
            <?php endif; ?>
            
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                <!-- Generate Token -->
                <div style="background: #f1f5f9; padding: 20px; border-radius: 16px;">
                    <h3><i class="fas fa-plus-circle"></i> Unda Token Mpya</h3>
                    <form method="POST">
                        <div class="form-group">
                            <label>Jina la Kifaa</label>
                            <input type="text" name="device_name" placeholder="Mfano: iPhone 14, Samsung Galaxy" required>
                            <small style="color: #64748b;">Tambua kifaa chako kwa urahisi</small>
                        </div>
                        <div class="form-group">
                            <label>Platform</label>
                            <select name="platform">
                                <option value="mobile">Mobile (Android/iOS)</option>
                                <option value="desktop">Desktop (Windows/Mac)</option>
                                <option value="web">Web Browser</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Muda wa Token (Siku)</label>
                            <select name="expires_days">
                                <option value="7">Siku 7</option>
                                <option value="15">Siku 15</option>
                                <option value="30" selected>Siku 30</option>
                                <option value="60">Siku 60</option>
                                <option value="90">Siku 90</option>
                            </select>
                        </div>
                        <button type="submit" class="btn btn-primary" style="background: #6366f1;">
                            <i class="fas fa-key"></i> Unda Token
                        </button>
                    </form>
                </div>
                
                <!-- API Documentation -->
                <div style="background: #f1f5f9; padding: 20px; border-radius: 16px;">
                    <h3><i class="fas fa-book"></i> Mwongozo wa API</h3>
                    <div style="font-size: 14px; color: #475569; line-height: 1.8;">
                        <p><strong>Base URL:</strong> <code style="background: #1e293b; color: #0d9488; padding: 2px 8px; border-radius: 4px;"><?php echo APP_URL; ?>/api.php</code></p>
                        <p><strong>Authentication:</strong> Bearer Token</p>
                        <p><strong>Headers:</strong></p>
                        <ul style="padding-left: 20px;">
                            <li><code>Authorization: Bearer YOUR_TOKEN</code></li>
                            <li><code>Content-Type: application/json</code></li>
                        </ul>
                        <p style="margin-top: 10px;"><strong>Endpoints Muhimu:</strong></p>
                        <ul style="padding-left: 20px;">
                            <li><code>POST /login</code> - Kuingia</li>
                            <li><code>GET /profile</code> - Wasifu</li>
                            <li><code>GET /loans</code> - Mikopo Yangu</li>
                            <li><code>POST /loans/request</code> - Omba Mkopo</li>
                            <li><code>POST /mpesa/pay</code> - M-PESA</li>
                            <li><code>GET /chat/rooms</code> - Chat Rooms</li>
                            <li><code>POST /chat/send</code> - Tuma Ujumbe</li>
                        </ul>
                        <p style="margin-top: 10px;">
                            <a href="api_docs.php" class="btn btn-sm btn-outline" style="width: auto;">
                                <i class="fas fa-external-link-alt"></i> API Documentation Kamili
                            </a>
                        </p>
                    </div>
                </div>
            </div>
            
            <!-- Active Tokens -->
            <h3 style="margin-top: 24px;"><i class="fas fa-list"></i> Tokens Zilizopo</h3>
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>Kifaa</th>
                            <th>Platform</th>
                            <th>IP</th>
                            <th>Hali</th>
                            <th>Imetumika Mwisho</th>
                            <th>Inaisha</th>
                            <th>Kitendo</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($tokens as $token): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($token['device_name'] ?? '-'); ?></td>
                            <td><span class="badge badge-<?php echo $token['platform']; ?>"><?php echo $token['platform']; ?></span></td>
                            <td><?php echo htmlspecialchars($token['ip_address'] ?? '-'); ?></td>
                            <td>
                                <span class="badge badge-<?php echo $token['status']; ?>">
                                    <?php 
                                    $status_labels = [
                                        'active' => 'Inatumika',
                                        'expired' => 'Imeisha',
                                        'revoked' => 'Imefutwa'
                                    ];
                                    echo $status_labels[$token['status']] ?? $token['status']; 
                                    ?>
                                </span>
                            </td>
                            <td><?php echo $token['last_used_at'] ? date('d/m/Y H:i', strtotime($token['last_used_at'])) : '-'; ?></td>
                            <td><?php echo $token['expires_at'] ? date('d/m/Y', strtotime($token['expires_at'])) : 'Never'; ?></td>
                            <td>
                                <?php if($token['status'] === 'active'): ?>
                                <a href="api_revoke.php?id=<?php echo $token['id']; ?>" 
                                   class="btn btn-sm btn-danger" 
                                   onclick="return confirm('Je, una uhakika wa kufuta token hii? Kifaa kitatakiwa kuingia tena.')">
                                    <i class="fas fa-trash"></i>
                                </a>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if(empty($tokens)): ?>
                        <tr><td colspan="7" style="text-align: center; color: #64748b;">Hakuna tokens zilizopo</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <div style="margin-top: 20px; text-align: center;">
                <a href="dashboard.php" class="btn btn-outline" style="width: auto;">
                    <i class="fas fa-arrow-left"></i> Rudi Dashibodi
                </a>
            </div>
        </div>
    </div>
    
    <script>
        function copyToken(token) {
            navigator.clipboard.writeText(token).then(function() {
                alert('Token imenakiliwa!');
            }, function() {
                // Fallback
                var input = document.createElement('input');
                input.value = token;
                document.body.appendChild(input);
                input.select();
                document.execCommand('copy');
                document.body.removeChild(input);
                alert('Token imenakiliwa!');
            });
        }
    </script>
</body>
</html>
