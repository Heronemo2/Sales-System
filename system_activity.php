<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
requireSystemAdmin();
$pdo = getDB();
$pageTitle = 'User Activity';
include __DIR__ . '/includes/header.php';

$search = trim($_GET['search'] ?? '');
$params = [];
$sql = "SELECT a.*, u.full_name, u.username, u.role
        FROM activity_logs a
        LEFT JOIN users u ON u.id=a.user_id";
if ($search !== '') {
    $sql .= " WHERE u.full_name LIKE ? OR u.username LIKE ? OR u.role LIKE ? OR a.action LIKE ? OR a.module LIKE ? OR a.description LIKE ?";
    $like = '%'.$search.'%';
    $params = [$like,$like,$like,$like,$like,$like];
}
$sql .= " ORDER BY a.id DESC LIMIT 200";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$activity = $stmt->fetchAll();
?>
<div class="page-toolbar"><p class="muted" style="color:white;">System-wide monitoring of recorded user sign-ins and actions.</p></div>
<div class="module-search">
    <form method="GET">
        <input type="search" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Search user, role, action, module, or description...">
        <button class="secondary-btn">Search</button>
        <?php if($search!==''): ?><a class="text-link" href="system_activity.php">Clear</a><?php endif; ?>
    </form>
</div>
<div class="panel">
    <div class="panel-head"><div><p class="eyebrow" style="color:black;">SYSTEM ACTIVITY</p><h2>User Activity Log</h2></div></div>
    <div class="table-wrap">
        <table>
            <thead><tr><th>User</th><th>Role</th><th>Action</th><th>Module</th><th>Description</th><th>Date</th></tr></thead>
            <tbody>
            <?php foreach($activity as $a): ?>
                <tr>
                    <td><b><?= htmlspecialchars($a['full_name'] ?? 'System') ?></b><br><small>@<?= htmlspecialchars($a['username'] ?? '—') ?></small></td>
                    <td><?= htmlspecialchars($a['role'] ?? '—') ?></td>
                    <td><?= htmlspecialchars($a['action']) ?></td>
                    <td><?= htmlspecialchars($a['module']) ?></td>
                    <td><?= htmlspecialchars($a['description'] ?? '—') ?></td>
                    <td><?= date('M j, Y g:i A', strtotime($a['created_at'])) ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if(!$activity): ?><tr><td colspan="6">No activity found.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
