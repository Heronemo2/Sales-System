<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
requireLogin();
$pdo = getDB();

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $orderId = (int) ($_POST['order_id'] ?? 0);
    $reason = trim($_POST['reason'] ?? '');
    $date = $_POST['return_date'] ?? '';
    $total = (float) ($_POST['total_amount'] ?? 0);

    if (!$orderId || !$date || $reason === '') {
        $error = 'Order, reason, and return date are required.';
    } else {
        $returnNo = nextDocNo($pdo, 'sales_returns', 'RET-', 0, 3);
        $stmt = $pdo->prepare("INSERT INTO sales_returns (return_no, order_id, return_date, reason, total_amount, status) VALUES (?,?,?,?,?,'Pending')");
        $stmt->execute([$returnNo, $orderId, $date, $reason, $total]);
        header('Location: returns.php?section=core&added=1');
        exit;
    }
}

$pageTitle = 'Sales Returns';
include __DIR__ . '/includes/header.php';

$rows = $pdo->query('SELECT r.*, o.order_no, c.name AS customer_name FROM sales_returns r JOIN sales_orders o ON o.id = r.order_id JOIN customers c ON c.id = o.customer_id ORDER BY r.id DESC')->fetchAll();
$orders = $pdo->query('SELECT o.id, o.order_no, c.name AS customer_name FROM sales_orders o JOIN customers c ON c.id = o.customer_id ORDER BY o.id DESC')->fetchAll();
?>
<div class="page-toolbar"><p class="muted">Record and review products returned after a sale.</p><button class="primary-btn" data-open-modal="addReturnModal">+ New Sales Return</button></div>

<?php if (!empty($_GET['added'])): ?><div class="alert success">Sales return recorded successfully.</div><?php endif; ?>
<?php if ($error): ?><div class="alert error"><?= htmlspecialchars($error) ?></div><?php endif; ?>

<div class="panel"><div class="table-wrap"><table><thead><tr><th>Return</th><th>Order</th><th>Customer</th><th>Reason</th><th>Date</th><th>Status</th></tr></thead><tbody>
<?php if (!$rows): ?><tr class="empty-row"><td colspan="6">No sales returns yet.</td></tr><?php endif; ?>
<?php foreach ($rows as $r): ?>
    <tr>
        <td><b><?= htmlspecialchars($r['return_no']) ?></b></td>
        <td><?= htmlspecialchars($r['order_no']) ?></td>
        <td><?= htmlspecialchars($r['customer_name']) ?></td>
        <td><?= htmlspecialchars($r['reason']) ?></td>
        <td><?= niceDate($r['return_date']) ?></td>
        <td><span class="badge <?= badgeClass($r['status']) ?>"><?= htmlspecialchars($r['status']) ?></span></td>
    </tr>
<?php endforeach; ?>
</tbody></table></div></div>

<div class="modal-backdrop" id="addReturnModal" aria-hidden="true">
    <div class="profile-modal entry-modal" role="dialog" aria-modal="true" aria-labelledby="addReturnTitle">
        <button class="modal-close" type="button" aria-label="Close" data-close-modal>×</button>
        <p class="eyebrow">CORE SALES</p>
        <h2 id="addReturnTitle">New Sales Return</h2>
        <form method="POST" class="entry-form">
            <label>Sales Order
                <select name="order_id" required>
                    <option value="">Select order</option>
                    <?php foreach ($orders as $o): ?><option value="<?= $o['id'] ?>"><?= htmlspecialchars($o['order_no']) ?> — <?= htmlspecialchars($o['customer_name']) ?></option><?php endforeach; ?>
                </select>
            </label>
            <label>Reason
                <input type="text" name="reason" required>
            </label>
            <label>Return Date
                <input type="date" name="return_date" value="<?= date('Y-m-d') ?>" required>
            </label>
            <label>Return Amount (₱, optional)
                <input type="number" step="0.01" min="0" name="total_amount" value="0">
            </label>
            <div class="modal-actions">
                <button type="button" class="secondary-btn" data-close-modal>Cancel</button>
                <button type="submit" class="primary-btn">Save Return</button>
            </div>
        </form>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
