<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
requireLogin();
$pdo = getDB();

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $customerId = (int) ($_POST['customer_id'] ?? 0);
    $quotationId = $_POST['quotation_id'] !== '' ? (int) $_POST['quotation_id'] : null;
    $date = $_POST['order_date'] ?? '';
    $total = (float) ($_POST['total_amount'] ?? 0);
    $status = in_array($_POST['status'] ?? '', ['Pending', 'Processing', 'Invoiced', 'Completed', 'Cancelled'], true) ? $_POST['status'] : 'Pending';

    if (!$customerId || !$date || $total <= 0) {
        $error = 'Customer, date, and a total amount greater than 0 are required.';
    } else {
        $orderNo = nextDocNo($pdo, 'sales_orders', 'SO-', 1000);
        $stmt = $pdo->prepare('INSERT INTO sales_orders (order_no, quotation_id, customer_id, order_date, total_amount, status) VALUES (?,?,?,?,?,?)');
        $stmt->execute([$orderNo, $quotationId, $customerId, $date, $total, $status]);
        if ($quotationId) {
            $pdo->prepare("UPDATE sales_quotations SET status = 'Approved' WHERE id = ?")->execute([$quotationId]);
        }
        header('Location: orders.php?section=core&added=1');
        exit;
    }
}

$pageTitle = 'Sales Orders';
include __DIR__ . '/includes/header.php';

$rows = $pdo->query('SELECT o.*, c.name AS customer_name FROM sales_orders o JOIN customers c ON c.id = o.customer_id ORDER BY o.id DESC')->fetchAll();
$customers = $pdo->query('SELECT id, name FROM customers ORDER BY name')->fetchAll();
$quotations = $pdo->query("SELECT sq.id, sq.quotation_no, sq.customer_id FROM sales_quotations sq WHERE sq.status = 'Approved' ORDER BY sq.id DESC")->fetchAll();
?>
<div class="page-toolbar"><p class="muted">Orders are created after a customer confirms the quotation.</p><button class="primary-btn" data-open-modal="addOrderModal">+ New Sales Order</button></div>

<?php if (!empty($_GET['added'])): ?><div class="alert success">Sales order created successfully.</div><?php endif; ?>
<?php if ($error): ?><div class="alert error"><?= htmlspecialchars($error) ?></div><?php endif; ?>

<div class="panel"><div class="table-wrap"><table><thead><tr><th>Order</th><th>Customer</th><th>Date</th><th>Total</th><th>Status</th></tr></thead><tbody>
<?php if (!$rows): ?><tr class="empty-row"><td colspan="5">No sales orders yet. Create the first one above.</td></tr><?php endif; ?>
<?php foreach ($rows as $r): ?>
    <tr>
        <td><b><?= htmlspecialchars($r['order_no']) ?></b></td>
        <td><?= htmlspecialchars($r['customer_name']) ?></td>
        <td><?= niceDate($r['order_date']) ?></td>
        <td><?= peso((float) $r['total_amount']) ?></td>
        <td><span class="badge <?= badgeClass($r['status']) ?>"><?= htmlspecialchars($r['status']) ?></span></td>
    </tr>
<?php endforeach; ?>
</tbody></table></div></div>

<div class="modal-backdrop" id="addOrderModal" aria-hidden="true">
    <div class="profile-modal entry-modal" role="dialog" aria-modal="true" aria-labelledby="addOrderTitle">
        <button class="modal-close" type="button" aria-label="Close" data-close-modal>×</button>
        <p class="eyebrow">CORE SALES</p>
        <h2 id="addOrderTitle">New Sales Order</h2>
        <form method="POST" class="entry-form">
            <label>Customer
                <select name="customer_id" required>
                    <option value="">Select customer</option>
                    <?php foreach ($customers as $c): ?><option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['name']) ?></option><?php endforeach; ?>
                </select>
            </label>
            <label>Linked Quotation (optional)
                <select name="quotation_id">
                    <option value="">None</option>
                    <?php foreach ($quotations as $q): ?><option value="<?= $q['id'] ?>"><?= htmlspecialchars($q['quotation_no']) ?></option><?php endforeach; ?>
                </select>
            </label>
            <label>Order Date
                <input type="date" name="order_date" value="<?= date('Y-m-d') ?>" required>
            </label>
            <label>Total Amount (₱)
                <input type="number" step="0.01" min="0.01" name="total_amount" required>
            </label>
            <label>Status
                <select name="status">
                    <option value="Pending">Pending</option>
                    <option value="Processing">Processing</option>
                    <option value="Completed">Completed</option>
                    <option value="Cancelled">Cancelled</option>
                </select>
            </label>
            <div class="modal-actions">
                <button type="button" class="secondary-btn" data-close-modal>Cancel</button>
                <button type="submit" class="primary-btn">Save Order</button>
            </div>
        </form>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
