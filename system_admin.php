<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
requireSystemAdmin();
$pdo = getDB();
$pageTitle = 'System Admin Dashboard';
include __DIR__ . '/includes/header.php';

$userCount = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE status='Active'")->fetchColumn();
$staffCount = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE status='Active' AND role='Sales Staff'")->fetchColumn();
$adminCount = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE status='Active' AND role='Sales Admin'")->fetchColumn();
$activityCount = (int)$pdo->query("SELECT COUNT(*) FROM activity_logs")->fetchColumn();
$recent = $pdo->query("SELECT a.*,u.full_name,u.username,u.role FROM activity_logs a LEFT JOIN users u ON u.id=a.user_id ORDER BY a.id DESC LIMIT 8")->fetchAll();
?>
<section class="welcome-banner admin-banner">
    <div>
        <p class="eyebrow light">SYSTEM ADMIN / CONTROL CENTER</p>
        <h2>Monitor system users and activity.</h2>
        <p>System Admin is responsible for user access monitoring and the system-wide activity log.</p>
    </div>
    <div class="banner-mark"><span class="admin-mark">S</span></div>
</section>
<section class="stats-grid">
    <div class="stat-card"><div class="stat-icon dark">U</div><div><span>Active Users</span><strong><?= $userCount ?></strong><small>All active accounts</small></div></div>
    <div class="stat-card"><div class="stat-icon red">S</div><div><span>Sales Staff</span><strong><?= $staffCount ?></strong><small>Active staff accounts</small></div></div>
    <div class="stat-card"><div class="stat-icon pink">A</div><div><span>Sales Admins</span><strong><?= $adminCount ?></strong><small>Active sales admins</small></div></div>
    <div class="stat-card"><div class="stat-icon burgundy">L</div><div><span>Logged Activities</span><strong><?= $activityCount ?></strong><small>System activity records</small></div></div>
</section>
<div class="admin-card-grid">
    <a class="admin-action-card" href="system_activity.php"><b>User Activity</b><span>Monitor sign-ins and recorded actions from all users.</span><strong>Open →</strong></a>
</div>
<section class="content-grid">
    <div class="panel">
        <div class="panel-head"><div><p class="eyebrow">LATEST ACTIVITY</p><h2>Recent user activity</h2></div><a href="system_activity.php" class="text-link">View all →</a></div>
        <div class="activity-feed">
        <?php foreach($recent as $a): ?>
            <div class="activity-row"><span class="activity-dot"></span><div><b><?= htmlspecialchars($a['action']) ?> · <?= htmlspecialchars($a['module']) ?></b><small><?= htmlspecialchars($a['full_name'] ?? 'System') ?> (@<?= htmlspecialchars($a['username'] ?? 'system') ?>) · <?= htmlspecialchars($a['description'] ?? '') ?> · <?= date('M j, Y g:i A',strtotime($a['created_at'])) ?></small></div></div>
        <?php endforeach; ?>
        <?php if(!$recent): ?><div class="activity-row"><div><b>No activity yet.</b></div></div><?php endif; ?>
        </div>
    </div>
</section>
<?php include __DIR__ . '/includes/footer.php'; ?>
