<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
requireLogin();
$pdo = getDB();

$error = '';
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
    }
}

$pageTitle = 'Delivery';
include __DIR__ . '/includes/header.php';

$rows = $pdo->query('SELECT d.*, o.order_no, c.name AS customer_name FROM deliveries d JOIN sales_orders o ON o.id = d.order_id JOIN customers c ON c.id = o.customer_id ORDER BY d.id DESC')->fetchAll();
$orders = $pdo->query('SELECT o.id, o.order_no, c.name AS customer_name FROM sales_orders o JOIN customers c ON c.id = o.customer_id ORDER BY o.id DESC')->fetchAll();
?>
<div class="page-toolbar"><p class="muted">Track fulfillment and shipment status for confirmed sales orders.</p><button class="primary-btn" data-open-modal="addDeliveryModal">+ New Delivery</button></div>

<?php if (!empty($_GET['added'])): ?><div class="alert success">Delivery recorded successfully.</div><?php endif; ?>
<?php if ($error): ?><div class="alert error"><?= htmlspecialchars($error) ?></div><?php endif; ?>

<div class="panel"><div class="table-wrap"><table><thead><tr><th>Delivery No</th><th>Order No</th><th>Customer</th><th>Date</th><th>Status</th></tr></thead><tbody>
<?php if (!$rows): ?><tr class="empty-row"><td colspan="5">No deliveries yet. Add the first one above.</td></tr><?php endif; ?>
<?php foreach ($rows as $r): ?>
    <tr>
        <td><b><?= htmlspecialchars($r['delivery_no']) ?></b></td>
        <td><?= htmlspecialchars($r['order_no']) ?></td>
        <td><?= htmlspecialchars($r['customer_name']) ?></td>
        <td><?= niceDate($r['delivery_date']) ?></td>
        <td><span class="badge <?= badgeClass($r['status']) ?>"><?= htmlspecialchars($r['status']) ?></span></td>
    </tr>
<?php endforeach; ?>
</tbody></table></div></div>

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

<?php include __DIR__ . '/includes/footer.php'; ?>
