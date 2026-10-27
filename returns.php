<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
requireSalesWorkspace();
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
        logActivity($pdo, 'Create', 'Sales Return', 'Created ' . $returnNo, (int)$pdo->lastInsertId());
        header('Location: returns.php?section=core&added=1');
        exit;
    }
}

$pageTitle = 'Sales Returns';
include __DIR__ . '/includes/header.php';

<<<<<<< HEAD
$search=trim($_GET['search']??''); $sql='SELECT r.*, o.order_no, c.customer_name AS customer_name FROM sales_returns r JOIN sales_orders o ON o.so_id=r.order_id JOIN customers c ON c.cus_id=o.customer_id'; $params=[]; if($search!==''){ $sql.=' WHERE r.return_no LIKE ? OR o.order_no LIKE ? OR c.customer_name LIKE ? OR r.reason LIKE ? OR r.status LIKE ?'; $like='%'.$search.'%'; $params=[$like,$like,$like,$like,$like]; } $sql.=' ORDER BY r.ret_id DESC'; $st=$pdo->prepare($sql); $st->execute($params); $rows=$st->fetchAll();
$orders = $pdo->query('SELECT o.so_id, o.order_no, c.customer_name AS customer_name FROM sales_orders o JOIN customers c ON c.cus_id = o.customer_id ORDER BY o.so_id DESC')->fetchAll();
=======
<<<<<<< HEAD
$search=trim($_GET['search']??''); $sql='SELECT r.*, o.order_no, c.customer_name AS customer_name FROM sales_returns r JOIN sales_orders o ON o.id=r.order_id JOIN customers c ON c.id=o.customer_id'; $params=[]; if($search!==''){ $sql.=' WHERE r.return_no LIKE ? OR o.order_no LIKE ? OR c.customer_name LIKE ? OR r.reason LIKE ? OR r.status LIKE ?'; $like='%'.$search.'%'; $params=[$like,$like,$like,$like,$like]; } $sql.=' ORDER BY r.id DESC'; $st=$pdo->prepare($sql); $st->execute($params); $rows=$st->fetchAll();
$orders = $pdo->query('SELECT o.id, o.order_no, c.customer_name AS customer_name FROM sales_orders o JOIN customers c ON c.id = o.customer_id ORDER BY o.id DESC')->fetchAll();
=======
$search=trim($_GET['search']??''); $sql='SELECT r.*, o.order_no, c.customer_name AS customer_name FROM sales_returns r JOIN sales_orders o ON o.so_id=r.order_id JOIN customers c ON c.cus_id=o.customer_id'; $params=[]; if($search!==''){ $sql.=' WHERE r.return_no LIKE ? OR o.order_no LIKE ? OR c.customer_name LIKE ? OR r.reason LIKE ? OR r.status LIKE ?'; $like='%'.$search.'%'; $params=[$like,$like,$like,$like,$like]; } $sql.=' ORDER BY r.ret_id DESC'; $st=$pdo->prepare($sql); $st->execute($params); $rows=$st->fetchAll();
$orders = $pdo->query('SELECT o.so_id, o.order_no, c.customer_name AS customer_name FROM sales_orders o JOIN customers c ON c.cus_id = o.customer_id ORDER BY o.so_id DESC')->fetchAll();
>>>>>>> origin/main
>>>>>>> origin/main
?>
<div class="page-toolbar"><p class="muted" style="color: white;">Record and review products returned after a sale.</p><button class="primary-btn" data-open-modal="addReturnModal">+ New Sales Return</button></div>
<div class="module-search"><form method="GET"><input type="hidden" name="section" value="core"><input type="search" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Search returns, orders, customers, reason, or status..."><button class="secondary-btn" type="submit">Search</button><?php if($search!==''): ?><a class="text-link" href="returns.php?section=core">Clear</a><?php endif; ?></form></div>

<?php if (!empty($_GET['added'])): ?><div class="alert success">Sales return recorded successfully.</div><?php endif; ?>
<?php if ($error): ?><div class="alert error"><?= htmlspecialchars($error) ?></div><?php endif; ?>

<div class="panel"><div class="table-wrap"><table><thead><tr><th>Return</th><th>Order</th><th>Customer Name</th><th>Reason</th><th>Date</th><th>Status</th><th>Admin Approval</th></tr></thead><tbody>
<?php if (!$rows): ?><tr class="empty-row"><td colspan="7">No sales returns yet.</td></tr><?php endif; ?>
<?php foreach ($rows as $r): ?>
    <tr>
        <td><b><?= htmlspecialchars($r['return_no']) ?></b></td>
        <td><?= htmlspecialchars($r['order_no']) ?></td>
        <td><?= htmlspecialchars($r['customer_name']) ?></td>
        <td><?= htmlspecialchars($r['reason']) ?></td>
        <td><?= niceDate($r['return_date']) ?></td>
        <td><span class="badge <?= badgeClass($r['status']) ?>"><?= htmlspecialchars($r['status']) ?></span></td><td><span class="badge <?= badgeClass($r['admin_status'] ?? 'Pending') ?>"><?= htmlspecialchars($r['admin_status'] ?? 'Pending') ?></span></td>
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
<<<<<<< HEAD
                    <?php foreach ($orders as $o): ?><option value="<?= $o['so_id'] ?>"><?= htmlspecialchars($o['order_no']) ?> — <?= htmlspecialchars($o['customer_name']) ?></option><?php endforeach; ?>
=======
<<<<<<< HEAD
                    <?php foreach ($orders as $o): ?><option value="<?= $o['id'] ?>"><?= htmlspecialchars($o['order_no']) ?> — <?= htmlspecialchars($o['customer_name']) ?></option><?php endforeach; ?>
=======
                    <?php foreach ($orders as $o): ?><option value="<?= $o['so_id'] ?>"><?= htmlspecialchars($o['order_no']) ?> — <?= htmlspecialchars($o['customer_name']) ?></option><?php endforeach; ?>
>>>>>>> origin/main
>>>>>>> origin/main
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
