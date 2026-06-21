<?php
// includes/header.php
$user = getCurrentUser();
$current_lang = getLanguage();
?>
<div class="header">
    <div class="header-left">
        <a href="dashboard.php" class="brand">
            <i class="fas fa-hand-holding-usd"></i>
            <h1><?php echo APP_NAME; ?></h1>
        </a>
        <span class="user-role role-<?php echo $user['role']; ?>">
            <i class="fas fa-user-tag"></i> <?php echo strtoupper($user['role']); ?>
        </span>
    </div>
    <div class="header-right">
        <div class="user-info">
            <div class="avatar">
                <?php echo strtoupper(substr($user['first_name'], 0, 1)); ?>
            </div>
            <span><strong><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></strong></span>
        </div>
        
        <!-- Language Switcher -->
        <div style="display: flex; gap: 4px;">
            <a href="language.php?lang=sw" style="padding: 4px 10px; border-radius: 6px; text-decoration: none; <?php echo $current_lang === 'sw' ? 'background: #0d9488; color: white;' : 'background: #e2e8f0; color: #1e293b;'; ?>">
                SW
            </a>
            <a href="language.php?lang=en" style="padding: 4px 10px; border-radius: 6px; text-decoration: none; <?php echo $current_lang === 'en' ? 'background: #0d9488; color: white;' : 'background: #e2e8f0; color: #1e293b;'; ?>">
                EN
            </a>
        </div>
        
        <a href="chat.php" class="btn btn-sm btn-outline" style="position: relative;">
            <i class="fas fa-comment-dots"></i>
            <?php
            // Get unread count
            $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM chat_messages m 
                                  JOIN chat_participants p ON m.room_id = p.room_id 
                                  WHERE p.member_id = ? AND m.read_at IS NULL AND m.sender_id != ?");
            $stmt->execute([$user['id'], $user['id']]);
            $unread = $stmt->fetch()['count'] ?? 0;
            if($unread > 0): ?>
                <span style="position: absolute; top: -6px; right: -6px; background: #ef4444; color: white; border-radius: 50%; padding: 2px 6px; font-size: 10px;"><?php echo $unread; ?></span>
            <?php endif; ?>
        </a>
        
        <a href="profile.php" class="btn btn-sm btn-outline">
            <i class="fas fa-user-cog"></i>
        </a>
        <a href="logout.php" class="btn-logout">
            <i class="fas fa-sign-out-alt"></i> <?php echo t('logout'); ?>
        </a>
    </div>
</div>
