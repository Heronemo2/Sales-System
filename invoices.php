<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
requireSalesWorkspace();
$pdo = getDB();

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $orderId = (int) ($_POST['order_id'] ?? 0);
    $date = $_POST['invoice_date'] ?? '';
    $total = (float) ($_POST['total_amount'] ?? 0);

    if (!$orderId || !$date || $total <= 0) {
        $error = 'Order, date, and a total amount greater than 0 are required.';
    } else {
        $invoiceNo = nextDocNo($pdo, 'sales_invoices', 'INV-', 3000);
        $stmt = $pdo->prepare("INSERT INTO sales_invoices (invoice_no, order_id, invoice_date, total_amount, amount_paid, status) VALUES (?,?,?,?,0,'Unpaid')");
        $stmt->execute([$invoiceNo, $orderId, $date, $total]);
        logActivity($pdo, 'Create', 'Sales Invoice', 'Created ' . $invoiceNo, (int)$pdo->lastInsertId());
        $pdo->prepare("UPDATE sales_orders SET status = 'Invoiced' WHERE id = ?")->execute([$orderId]);
        header('Location: invoices.php?section=billing&added=1');
        exit;
    }
}

$pageTitle = 'Sales Invoices';
include __DIR__ . '/includes/header.php';

$search=trim($_GET['search']??''); $sql='SELECT i.*, o.order_no, c.customer_name AS customer_name FROM sales_invoices i JOIN sales_orders o ON o.id=i.order_id JOIN customers c ON c.id=o.customer_id'; $params=[]; if($search!==''){ $sql.=' WHERE i.invoice_no LIKE ? OR o.order_no LIKE ? OR c.customer_name LIKE ? OR i.status LIKE ?'; $like='%'.$search.'%'; $params=[$like,$like,$like,$like]; } $sql.=' ORDER BY i.id DESC'; $st=$pdo->prepare($sql); $st->execute($params); $rows=$st->fetchAll();
$orders = $pdo->query('SELECT o.id, o.order_no, o.total_amount, c.customer_name AS customer_name FROM sales_orders o JOIN customers c ON c.id = o.customer_id ORDER BY o.id DESC')->fetchAll();
?>
<div class="page-toolbar"><p class="muted" style="color: white;">Billing records and payment status.</p><button class="primary-btn" data-open-modal="addInvoiceModal">+ Create Invoice</button></div>
<div class="module-search"><form method="GET"><input type="hidden" name="section" value="billing"><input type="search" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Search invoices, orders, customers, or status..."><button class="secondary-btn" type="submit">Search</button><?php if($search!==''): ?><a class="text-link" href="invoices.php?section=billing">Clear</a><?php endif; ?></form></div>

<?php if (!empty($_GET['added'])): ?><div class="alert success">Invoice created successfully.</div><?php endif; ?>
<?php if ($error): ?><div class="alert error"><?= htmlspecialchars($error) ?></div><?php endif; ?>

<div class="panel"><div class="table-wrap"><table><thead><tr><th>Invoice</th><th>Order</th><th>Customer Name</th><th>Amount</th><th>Payment Status</th></tr></thead><tbody>
<?php if (!$rows): ?><tr class="empty-row"><td colspan="5">No invoices yet. Create the first one above.</td></tr><?php endif; ?>
<?php foreach ($rows as $r): ?>
    <tr>
        <td><b><?= htmlspecialchars($r['invoice_no']) ?></b></td>
        <td><?= htmlspecialchars($r['order_no']) ?></td>
        <td><?= htmlspecialchars($r['customer_name']) ?></td>
        <td><?= peso((float) $r['total_amount']) ?></td>
        <td><span class="badge <?= badgeClass($r['status']) ?>"><?= htmlspecialchars($r['status']) ?></span></td>
    </tr>
<?php endforeach; ?>
</tbody></table></div></div>

<div class="modal-backdrop" id="addInvoiceModal" aria-hidden="true">
    <div class="profile-modal entry-modal" role="dialog" aria-modal="true" aria-labelledby="addInvoiceTitle">
        <button class="modal-close" type="button" aria-label="Close" data-close-modal>×</button>
        <p class="eyebrow">BILLING</p>
        <h2 id="addInvoiceTitle">Create Invoice</h2>
        <form method="POST" class="entry-form">
            <label>Sales Order
                <select name="order_id" id="invoiceOrderSelect" required>
                    <option value="">Select order</option>
                    <?php foreach ($orders as $o): ?><option value="<?= $o['id'] ?>" data-total="<?= $o['total_amount'] ?>"><?= htmlspecialchars($o['order_no']) ?> — <?= htmlspecialchars($o['customer_name']) ?></option><?php endforeach; ?>
                </select>
            </label>
            <label>Invoice Date
                <input type="date" name="invoice_date" value="<?= date('Y-m-d') ?>" required>
            </label>
            <label>Total Amount (₱)
                <input type="number" step="0.01" min="0.01" name="total_amount" id="invoiceTotalInput" required>
            </label>
            <div class="modal-actions">
                <button type="button" class="secondary-btn" data-close-modal>Cancel</button>
                <button type="submit" class="primary-btn">Save Invoice</button>
            </div>
        </form>
    </div>
</div>
<script>
document.getElementById('invoiceOrderSelect')?.addEventListener('change', function () {
    const opt = this.options[this.selectedIndex];
    const total = opt?.dataset.total;
    if (total) document.getElementById('invoiceTotalInput').value = total;
});
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
