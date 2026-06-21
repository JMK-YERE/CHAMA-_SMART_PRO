<?php
// meetings.php - Online Meetings Management
require_once 'config.php';

if(!isLoggedIn()) {
    header('Location: index.php');
    exit();
}

$user = getCurrentUser();
$error = null;
$success = null;

// Create meeting
if($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_meeting') {
    if(!hasRole(['mwenyekiti', 'katibu'])) {
        $error = 'Huna ruhusa ya kuunda mikutano';
    } else {
        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $meeting_date = $_POST['meeting_date'] ?? '';
        $duration = intval($_POST['duration'] ?? 60);
        $platform = $_POST['platform'] ?? 'zoom';
        $meeting_type = $_POST['meeting_type'] ?? 'video';
        
        if(empty($title) || empty($meeting_date)) {
            $error = 'Tafadhali jaza kichwa na tarehe';
        } else {
            // Generate meeting link
            $meeting_link = '';
            $room_name = strtolower(str_replace(' ', '-', $title)) . '-' . date('Ymd') . '-' . rand(100, 999);
            
            if($platform === 'zoom') {
                // Check if Zoom credentials are set
                if(ZOOM_API_KEY && ZOOM_API_SECRET) {
                    // Would call Zoom API here
                    $meeting_link = 'https://zoom.us/j/' . rand(100000000, 999999999);
                } else {
                    $meeting_link = 'https://zoom.us/meeting/' . $room_name;
                }
            } elseif($platform === 'jitsi') {
                $meeting_link = JITSI_URL . $room_name;
            } elseif($platform === 'google_meet') {
                $meeting_link = 'https://meet.google.com/' . $room_name;
            } else {
                $meeting_link = '#'; // Custom link
            }
            
            // Save to database
            $stmt = $pdo->prepare("INSERT INTO meetings 
                                  (title, description, meeting_date, duration, platform, meeting_type, meeting_link, created_by) 
                                  VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            if($stmt->execute([$title, $description, $meeting_date, $duration, $platform, $meeting_type, $meeting_link, $user['id']])) {
                $meeting_id = $pdo->lastInsertId();
                
                // Send invitations to all active members
                $members = getMembers();
                $invited_count = 0;
                foreach($members as $member) {
                    if($member['status'] === 'active' && $member['id'] != $user['id']) {
                        $stmt = $pdo->prepare("INSERT INTO meeting_attendees (meeting_id, member_id, status) VALUES (?, ?, 'invited')");
                        $stmt->execute([$meeting_id, $member['id']]);
                        
                        // Send notification
                        $msg = "Mwaliko wa Mkutano Mtandaoni!\n\n";
                        $msg .= "Kichwa: " . $title . "\n";
                        $msg .= "Tarehe: " . date('d/m/Y H:i', strtotime($meeting_date)) . "\n";
                        $msg .= "Kiungo: " . $meeting_link . "\n\n";
                        $msg .= "Karibu sana!";
                        
                        sendWhatsAppMessage($member['phone'], $msg);
                        sendSMS($member['phone'], $msg);
                        
                        $invited_count++;
                    }
                }
                
                $success = 'Mkutano umeundwa na mialiko ' . $invited_count . ' imetumwa!';
                logActivity($user['id'], 'Created meeting', "Meeting: $title, Invited: $invited_count");
            } else {
                $error = 'Hitilafu katika kuunda mkutano';
            }
        }
    }
}

// Confirm attendance
if(isset($_GET['confirm']) && isset($_GET['id'])) {
    $meeting_id = intval($_GET['id']);
    $stmt = $pdo->prepare("UPDATE meeting_attendees SET status = 'confirmed' 
                          WHERE meeting_id = ? AND member_id = ?");
    $stmt->execute([$meeting_id, $user['id']]);
    header('Location: meetings.php?success=Umeconfirm kuhudhuria');
    exit();
}

// Get meetings
$meetings = [];
$stmt = $pdo->query("SELECT m.*, CONCAT(u.first_name, ' ', u.last_name) as creator_name 
                     FROM meetings m 
                     LEFT JOIN wanakikundi u ON m.created_by = u.id 
                     WHERE m.status != 'cancelled'
                     ORDER BY m.meeting_date DESC");
$meetings = $stmt->fetchAll();

// Get user's attendance
$attendance = [];
if(!hasRole(['mwenyekiti', 'katibu'])) {
    $stmt = $pdo->prepare("SELECT meeting_id, status FROM meeting_attendees WHERE member_id = ?");
    $stmt->execute([$user['id']]);
    while($row = $stmt->fetch()) {
        $attendance[$row['meeting_id']] = $row['status'];
    }
}

// Get meeting stats
$stats = [];
$stmt = $pdo->query("SELECT 
                     COUNT(*) as total,
                     SUM(CASE WHEN status = 'scheduled' THEN 1 ELSE 0 END) as scheduled,
                     SUM(CASE WHEN status = 'ongoing' THEN 1 ELSE 0 END) as ongoing,
                     SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed
                     FROM meetings");
$stats = $stmt->fetch();
?>
<!DOCTYPE html>
<html lang="<?php echo getLanguage(); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo APP_NAME; ?> - Mikutano</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>
<body>
    <div class="container">
        <?php include 'includes/header.php'; ?>
        
        <div class="dashboard-card fade-in">
            <h2><i class="fas fa-video" style="color: #6366f1;"></i> Mikutano ya Mtandaoni</h2>
            
            <?php if($success): ?>
                <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?php echo $success; ?></div>
            <?php endif; ?>
            <?php if($error): ?>
                <div class="alert alert-error"><i class="fas fa-exclamation-circle"></i> <?php echo $error; ?></div>
            <?php endif; ?>
            
            <!-- Stats -->
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(120px, 1fr)); gap: 12px; margin-bottom: 20px;">
                <div style="background: #f1f5f9; padding: 12px; border-radius: 10px; text-align: center;">
                    <div style="font-size: 24px; font-weight: 700; color: #0d9488;"><?php echo $stats['total'] ?? 0; ?></div>
                    <div style="font-size: 13px; color: #64748b;">Jumla</div>
                </div>
                <div style="background: #dbeafe; padding: 12px; border-radius: 10px; text-align: center;">
                    <div style="font-size: 24px; font-weight: 700; color: #1e40af;"><?php echo $stats['scheduled'] ?? 0; ?></div>
                    <div style="font-size: 13px; color: #1e40af;">Zinazokuja</div>
                </div>
                <div style="background: #d1fae5; padding: 12px; border-radius: 10px; text-align: center;">
                    <div style="font-size: 24px; font-weight: 700; color: #065f46;"><?php echo $stats['ongoing'] ?? 0; ?></div>
                    <div style="font-size: 13px; color: #065f46;">Zinazoendelea</div>
                </div>
                <div style="background: #fef3c7; padding: 12px; border-radius: 10px; text-align: center;">
                    <div style="font-size: 24px; font-weight: 700; color: #92400e;"><?php echo $stats['completed'] ?? 0; ?></div>
                    <div style="font-size: 13px; color: #92400e;">Zilizokamilika</div>
                </div>
            </div>
            
            <!-- Create Meeting (Leaders only) -->
            <?php if(hasRole(['mwenyekiti', 'katibu'])): ?>
            <div style="background: #f1f5f9; padding: 20px; border-radius: 16px; margin-bottom: 24px;">
                <h3><i class="fas fa-plus-circle"></i> Unda Mkutano Mpya</h3>
                <form method="POST">
                    <input type="hidden" name="action" value="create_meeting">
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
                        <div class="form-group" style="margin-bottom: 0;">
                            <input type="text" name="title" placeholder="Kichwa cha mkutano" required>
                        </div>
                        <div class="form-group" style="margin-bottom: 0;">
                            <input type="datetime-local" name="meeting_date" required>
                        </div>
                    </div>
                    <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 12px; margin-top: 12px;">
                        <div class="form-group" style="margin-bottom: 0;">
                            <input type="number" name="duration" placeholder="Muda (dakika)" value="60">
                        </div>
                        <div class="form-group" style="margin-bottom: 0;">
                            <select name="platform">
                                <option value="zoom">Zoom</option>
                                <option value="jitsi">Jitsi Meet</option>
                                <option value="google_meet">Google Meet</option>
                                <option value="custom">Custom</option>
                            </select>
                        </div>
                        <div class="form-group" style="margin-bottom: 0;">
                            <select name="meeting_type">
                                <option value="video">Video</option>
                                <option value="audio">Audio</option>
                                <option value="hybrid">Hybrid</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-group" style="margin-top: 12px; margin-bottom: 0;">
                        <textarea name="description" placeholder="Maelezo ya mkutano" rows="2"></textarea>
                    </div>
                    <button type="submit" class="btn btn-primary" style="margin-top: 12px;">
                        <i class="fas fa-video"></i> Unda Mkutano
                    </button>
                </form>
            </div>
            <?php endif; ?>
            
            <!-- Upcoming Meetings -->
            <h3><i class="fas fa-calendar-alt"></i> Mikutano Inayokuja</h3>
            <div style="display: grid; gap: 16px; margin-top: 16px;">
                <?php 
                $upcoming = array_filter($meetings, function($m) {
                    return strtotime($m['meeting_date']) > time() && $m['status'] !== 'cancelled';
                });
                foreach($upcoming as $meeting): 
                ?>
                <div style="background: #f8fafc; padding: 16px; border-radius: 12px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px; border-left: 4px solid #0d9488;">
                    <div>
                        <strong style="font-size: 16px;"><?php echo htmlspecialchars($meeting['title']); ?></strong>
                        <div style="color: #64748b; font-size: 14px;">
                            <i class="fas fa-calendar"></i> <?php echo date('d/m/Y H:i', strtotime($meeting['meeting_date'])); ?>
                            <span style="margin-left: 16px;">
                                <i class="fas fa-clock"></i> <?php echo $meeting['duration']; ?> min
                            </span>
                            <span style="margin-left: 16px;">
                                <i class="fas fa-user"></i> <?php echo htmlspecialchars($meeting['creator_name'] ?? 'Mfumo'); ?>
                            </span>
                            <span style="margin-left: 16px;">
                                <i class="fas fa-<?php echo $meeting['platform'] === 'zoom' ? 'video' : ($meeting['platform'] === 'jitsi' ? 'users' : 'link'); ?>"></i>
                                <?php echo ucfirst($meeting['platform']); ?>
                            </span>
                            <?php if(isset($attendance[$meeting['id']])): ?>
                                <span style="margin-left: 16px;">
                                    <span class="badge badge-<?php echo $attendance[$meeting['id']]; ?>">
                                        <?php echo $attendance[$meeting['id']]; ?>
                                    </span>
                                </span>
                            <?php endif; ?>
                        </div>
                        <?php if($meeting['description']): ?>
                            <p style="color: #475569; font-size: 14px; margin-top: 4px;"><?php echo htmlspecialchars($meeting['description']); ?></p>
                        <?php endif; ?>
                    </div>
                    <div style="display: flex; gap: 8px; flex-wrap: wrap;">
                        <?php if(!isset($attendance[$meeting['id']]) || $attendance[$meeting['id']] === 'invited'): ?>
                            <a href="?confirm=1&id=<?php echo $meeting['id']; ?>" class="btn btn-sm btn-success">
                                <i class="fas fa-check"></i> Thibitisha
                            </a>
                        <?php endif; ?>
                        <a href="<?php echo htmlspecialchars($meeting['meeting_link']); ?>" target="_blank" class="btn btn-sm btn-primary" style="background: #6366f1;">
                            <i class="fas fa-sign-in-alt"></i> Jiunge
                        </a>
                    </div>
                </div>
                <?php endforeach; ?>
                <?php if(empty($upcoming)): ?>
                <p style="color: #64748b; text-align: center; padding: 20px;">Hakuna mikutano inayokuja</p>
                <?php endif; ?>
            </div>
            
            <!-- Past Meetings -->
            <h3 style="margin-top: 24px;"><i class="fas fa-history"></i> Mikutano Iliyopita</h3>
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>Kichwa</th>
                            <th>Tarehe</th>
                            <th>Muda</th>
                            <th>Hali</th>
                            <th>Kitendo</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $past = array_filter($meetings, function($m) {
                            return strtotime($m['meeting_date']) < time() || $m['status'] === 'completed';
                        });
                        foreach($past as $meeting): 
                        ?>
                        <tr>
                            <td><?php echo htmlspecialchars($meeting['title']); ?></td>
                            <td><?php echo date('d/m/Y H:i', strtotime($meeting['meeting_date'])); ?></td>
                            <td><?php echo $meeting['duration']; ?> min</td>
                            <td><span class="badge badge-<?php echo $meeting['status']; ?>"><?php echo $meeting['status']; ?></span></td>
                            <td>
                                <?php if($meeting['meeting_link'] && $meeting['meeting_link'] != '#'): ?>
                                <a href="<?php echo htmlspecialchars($meeting['meeting_link']); ?>" target="_blank" class="btn btn-sm btn-outline">
                                    <i class="fas fa-eye"></i> Tazama
                                </a>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if(empty($past)): ?>
                        <tr><td colspan="5" style="text-align: center;">Hakuna mikutano iliyopita</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Quick Links -->
            <div style="margin-top: 20px; display: flex; gap: 10px; flex-wrap: wrap; justify-content: center;">
                <a href="dashboard.php" class="btn btn-outline" style="width: auto;">
                    <i class="fas fa-arrow-left"></i> Rudi Dashibodi
                </a>
                <a href="chat.php" class="btn btn-outline" style="width: auto;">
                    <i class="fas fa-comment-dots"></i> Live Chat
                </a>
                <a href="reports.php" class="btn btn-outline" style="width: auto;">
                    <i class="fas fa-file-pdf"></i> Ripoti
                </a>
            </div>
        </div>
    </div>
    
    <script>
        // Auto-refresh meeting status every 30 seconds
        setInterval(function() {
            location.reload();
        }, 30000);
    </script>
</body>
</html>
