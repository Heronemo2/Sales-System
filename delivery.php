<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
requireSalesWorkspace();
$pdo = getDB();

$error = '';
<<<<<<< HEAD
=======
<<<<<<< HEAD
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $orderId = (int) ($_POST['order_id'] ?? 0);
    $date = $_POST['delivery_date'] ?? '';
    $status = in_array($_POST['status'] ?? '', ['Preparing', 'In Transit', 'Delivered'], true) ? $_POST['status'] : 'Preparing';

    if (!$orderId || !$date) {
        $error = 'Order and delivery date are required.';
    } else {
        $deliveryNo = nextDocNo($pdo, 'deliveries', 'DEL-', 700);
        $stmt = $pdo->prepare('INSERT INTO deliveries (delivery_no, order_id, delivery_date, status) VALUES (?,?,?,?)');
        $stmt->execute([$deliveryNo, $orderId, $date, $status]);
        header('Location: delivery.php?section=core&added=1');
        exit;
=======
>>>>>>> origin/main
$notice = '';

// Deliveries are created automatically when the Sales Admin approves a Sales Order
// (see convertOrderToDelivery() in includes/functions.php). Staff only track shipping status here.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $deliveryId = (int) ($_POST['delivery_id'] ?? 0);
    $status = $_POST['status'] ?? '';
    if (!$deliveryId || !in_array($status, ['Preparing', 'In Transit', 'Delivered'], true)) {
        $error = 'Invalid delivery update.';
    } else {
<<<<<<< HEAD
        $deliveryStmt = $pdo->prepare('SELECT order_id FROM deliveries WHERE del_id = ?');
        $deliveryStmt->execute([$deliveryId]);
        $orderId = (int)$deliveryStmt->fetchColumn();

        $pdo->prepare('UPDATE deliveries SET status = ? WHERE del_id = ?')->execute([$status, $deliveryId]);

        // Sync Sales Order. Completed requires both Paid and Delivered.
        if ($orderId > 0) {
            syncSalesOrderCompletion($pdo, $orderId);
        }

        logActivity($pdo, 'Update', 'Delivery', 'Delivery status set to ' . $status, $deliveryId);
        $notice = 'Delivery status updated.';
=======
        $pdo->prepare('UPDATE deliveries SET status = ? WHERE del_id = ?')->execute([$status, $deliveryId]);
        logActivity($pdo, 'Update', 'Delivery', 'Delivery status set to ' . $status, $deliveryId);
        $notice = 'Delivery status updated.';
>>>>>>> origin/main
>>>>>>> origin/main
    }
}

$pageTitle = 'Delivery';
include __DIR__ . '/includes/header.php';

<<<<<<< HEAD
=======
<<<<<<< HEAD
$search=trim($_GET['search']??''); $sql='SELECT d.*, o.order_no, c.customer_name AS customer_name FROM deliveries d JOIN sales_orders o ON o.id=d.order_id JOIN customers c ON c.id=o.customer_id'; $params=[]; if($search!==''){ $sql.=' WHERE d.delivery_no LIKE ? OR o.order_no LIKE ? OR c.customer_name LIKE ? OR d.status LIKE ?'; $like='%'.$search.'%'; $params=[$like,$like,$like,$like]; } $sql.=' ORDER BY d.id DESC'; $st=$pdo->prepare($sql); $st->execute($params); $rows=$st->fetchAll();
$orders = $pdo->query('SELECT o.id, o.order_no, c.customer_name AS customer_name FROM sales_orders o JOIN customers c ON c.id = o.customer_id ORDER BY o.id DESC')->fetchAll();
?>
<div class="page-toolbar"><p class="muted" style="color: white;">Track fulfillment and shipment status for confirmed sales orders.</p><button class="primary-btn" data-open-modal="addDeliveryModal">+ New Delivery</button></div>
<div class="module-search"><form method="GET"><input type="hidden" name="section" value="core"><input type="search" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Search deliveries, orders, customers, or status..."><button class="secondary-btn" type="submit">Search</button><?php if($search!==''): ?><a class="text-link" href="delivery.php?section=core">Clear</a><?php endif; ?></form></div>

<?php if (!empty($_GET['added'])): ?><div class="alert success">Delivery recorded successfully.</div><?php endif; ?>
<?php if ($error): ?><div class="alert error"><?= htmlspecialchars($error) ?></div><?php endif; ?>

<div class="panel"><div class="table-wrap"><table><thead><tr><th>Delivery No</th><th>Order No</th><th>Customer Name</th><th>Date</th><th>Status</th></tr></thead><tbody>
<?php if (!$rows): ?><tr class="empty-row"><td colspan="5">No deliveries yet. Add the first one above.</td></tr><?php endif; ?>
=======
>>>>>>> origin/main
$search = trim($_GET['search'] ?? '');
$sql = 'SELECT d.*, o.order_no, o.product_code, o.quantity, c.customer_name AS customer_name
        FROM deliveries d JOIN sales_orders o ON o.so_id=d.order_id JOIN customers c ON c.cus_id=o.customer_id';
$params = [];
if ($search !== '') {
    $sql .= ' WHERE d.delivery_no LIKE ? OR o.order_no LIKE ? OR c.customer_name LIKE ? OR d.status LIKE ?';
    $like = '%' . $search . '%';
    $params = [$like, $like, $like, $like];
}
$sql .= ' ORDER BY d.del_id DESC';
$st = $pdo->prepare($sql);
$st->execute($params);
$rows = $st->fetchAll();
?>
<div class="page-toolbar"><p class="muted" style="color: white;">Deliveries are created automatically when the Sales Admin approves a Sales Order. Inventory is deducted at that moment.</p></div>
<div class="module-search"><form method="GET"><input type="hidden" name="section" value="core"><input type="search" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Search deliveries, orders, customers, or status..."><button class="secondary-btn" type="submit">Search</button><?php if ($search !== ''): ?><a class="text-link" href="delivery.php?section=core">Clear</a><?php endif; ?></form></div>

<?php if ($notice): ?><div class="alert success"><?= htmlspecialchars($notice) ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert error"><?= htmlspecialchars($error) ?></div><?php endif; ?>

<div class="panel"><div class="table-wrap"><table><thead><tr><th>Delivery No</th><th>Order No</th><th>Customer Name</th><th>Product</th><th>Date</th><th>Inventory</th><th>Status</th><th>Update</th></tr></thead><tbody>
<?php if (!$rows): ?><tr class="empty-row"><td colspan="8">No deliveries yet. They appear after the Sales Admin approves a Sales Order.</td></tr><?php endif; ?>
<<<<<<< HEAD
=======
>>>>>>> origin/main
>>>>>>> origin/main
<?php foreach ($rows as $r): ?>
    <tr>
        <td><b><?= htmlspecialchars($r['delivery_no']) ?></b></td>
        <td><?= htmlspecialchars($r['order_no']) ?></td>
        <td><?= htmlspecialchars($r['customer_name']) ?></td>
<<<<<<< HEAD
=======
<<<<<<< HEAD
        <td><?= niceDate($r['delivery_date']) ?></td>
        <td><span class="badge <?= badgeClass($r['status']) ?>"><?= htmlspecialchars($r['status']) ?></span></td>
=======
>>>>>>> origin/main
        <td><?= $r['product_code'] ? htmlspecialchars($r['product_code']) . ' × ' . (int)$r['quantity'] : '—' ?></td>
        <td><?= niceDate($r['delivery_date']) ?></td>
        <td><?= !empty($r['inventory_deducted']) ? '<span class="badge success">Deducted</span>' : '<span class="badge neutral">Not deducted</span>' ?></td>
        <td><span class="badge <?= badgeClass($r['status']) ?>"><?= htmlspecialchars($r['status']) ?></span></td>
        <td>
            <?php if ($r['status'] !== 'Delivered'): ?>
            <form method="POST" class="inline-actions">
                <input type="hidden" name="delivery_id" value="<?= (int)$r['del_id'] ?>">
                <?php if ($r['status'] === 'Preparing'): ?><button class="table-action" name="status" value="In Transit">Mark In Transit</button><?php endif; ?>
                <button class="table-action" name="status" value="Delivered">Mark Delivered</button>
            </form>
            <?php else: ?>—<?php endif; ?>
        </td>
<<<<<<< HEAD
=======
>>>>>>> origin/main
>>>>>>> origin/main
    </tr>
<?php endforeach; ?>
</tbody></table></div></div>

<<<<<<< HEAD
=======
<<<<<<< HEAD
<div class="modal-backdrop" id="addDeliveryModal" aria-hidden="true">
    <div class="profile-modal entry-modal" role="dialog" aria-modal="true" aria-labelledby="addDeliveryTitle">
        <button class="modal-close" type="button" aria-label="Close" data-close-modal>×</button>
        <p class="eyebrow">CORE SALES</p>
        <h2 id="addDeliveryTitle">New Delivery</h2>
        <form method="POST" class="entry-form">
            <label>Sales Order
                <select name="order_id" required>
                    <option value="">Select order</option>
                    <?php foreach ($orders as $o): ?><option value="<?= $o['id'] ?>"><?= htmlspecialchars($o['order_no']) ?> — <?= htmlspecialchars($o['customer_name']) ?></option><?php endforeach; ?>
                </select>
            </label>
            <label>Delivery Date
                <input type="date" name="delivery_date" value="<?= date('Y-m-d') ?>" required>
            </label>
            <label>Status
                <select name="status">
                    <option value="Preparing">Preparing</option>
                    <option value="In Transit">In Transit</option>
                    <option value="Delivered">Delivered</option>
                </select>
            </label>
            <div class="modal-actions">
                <button type="button" class="secondary-btn" data-close-modal>Cancel</button>
                <button type="submit" class="primary-btn">Save Delivery</button>
            </div>
        </form>
    </div>
</div>

=======
>>>>>>> origin/main
>>>>>>> origin/main
<?php include __DIR__ . '/includes/footer.php'; ?>
