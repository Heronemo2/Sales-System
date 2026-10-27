<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
requireLogin();
$pdo = getDB();

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $invoiceId = (int) ($_POST['invoice_id'] ?? 0);
    $date = $_POST['collection_date'] ?? '';
    $amount = (float) ($_POST['amount'] ?? 0);
    $method = in_array($_POST['method'] ?? '', ['Cash', 'GCash', 'Bank Transfer'], true) ? $_POST['method'] : 'Cash';

    if (!$invoiceId || !$date || $amount <= 0) {
        $error = 'Invoice, date, and an amount greater than 0 are required.';
    } else {
        $invStmt = $pdo->prepare('SELECT total_amount, amount_paid FROM sales_invoices WHERE id = ?');
        $invStmt->execute([$invoiceId]);
        $invoice = $invStmt->fetch();

        if (!$invoice) {
            $error = 'Selected invoice was not found.';
        } else {
            $pdo->beginTransaction();

            $collectionNo = nextDocNo($pdo, 'collections', 'COL-', 500);
            $pdo->prepare('INSERT INTO collections (collection_no, invoice_id, collection_date, amount, method) VALUES (?,?,?,?,?)')
                ->execute([$collectionNo, $invoiceId, $date, $amount, $method]);

            $newPaid = min((float) $invoice['amount_paid'] + $amount, (float) $invoice['total_amount']);
            $newStatus = $newPaid >= (float) $invoice['total_amount'] ? 'Paid' : 'Partially Paid';
            $pdo->prepare('UPDATE sales_invoices SET amount_paid = ?, status = ? WHERE id = ?')
                ->execute([$newPaid, $newStatus, $invoiceId]);

            $pdo->commit();
            header('Location: collection.php?section=billing&added=1');
            exit;
        }
    }
}

$pageTitle = 'Collection';
include __DIR__ . '/includes/header.php';

$rows = $pdo->query("
    SELECT col.*, i.invoice_no, c.name AS customer_name
    FROM collections col
    JOIN sales_invoices i ON i.id = col.invoice_id
    JOIN sales_orders o ON o.id = i.order_id
    JOIN customers c ON c.id = o.customer_id
    ORDER BY col.id DESC
")->fetchAll();

$openInvoices = $pdo->query("
    SELECT i.id, i.invoice_no, (i.total_amount - i.amount_paid) AS balance, c.name AS customer_name
    FROM sales_invoices i
    JOIN sales_orders o ON o.id = i.order_id
    JOIN customers c ON c.id = o.customer_id
    WHERE i.status != 'Paid'
    ORDER BY i.id DESC
")->fetchAll();
?>
<div class="page-toolbar"><p class="muted">Record customer payments received against outstanding invoices.</p><button class="primary-btn" data-open-modal="addCollectionModal">+ Record Payment</button></div>

<?php if (!empty($_GET['added'])): ?><div class="alert success">Payment recorded successfully.</div><?php endif; ?>
<?php if ($error): ?><div class="alert error"><?= htmlspecialchars($error) ?></div><?php endif; ?>

<div class="panel"><div class="table-wrap"><table><thead><tr><th>Collection No</th><th>Customer</th><th>Invoice No</th><th>Amount</th><th>Date</th><th>Method</th></tr></thead><tbody>
<?php if (!$rows): ?><tr class="empty-row"><td colspan="6">No payments recorded yet.</td></tr><?php endif; ?>
<?php foreach ($rows as $r): ?>
    <tr>
        <td><b><?= htmlspecialchars($r['collection_no']) ?></b></td>
        <td><?= htmlspecialchars($r['customer_name']) ?></td>
        <td><?= htmlspecialchars($r['invoice_no']) ?></td>
        <td><?= peso((float) $r['amount']) ?></td>
        <td><?= niceDate($r['collection_date']) ?></td>
        <td><span class="badge info"><?= htmlspecialchars($r['method']) ?></span></td>
    </tr>
<?php endforeach; ?>
</tbody></table></div></div>

<div class="modal-backdrop" id="addCollectionModal" aria-hidden="true">
    <div class="profile-modal entry-modal" role="dialog" aria-modal="true" aria-labelledby="addCollectionTitle">
        <button class="modal-close" type="button" aria-label="Close" data-close-modal>×</button>
        <p class="eyebrow">BILLING</p>
        <h2 id="addCollectionTitle">Record Payment</h2>
        <?php if (!$openInvoices): ?>
            <p class="modal-subtitle">There are no unpaid invoices to collect against right now.</p>
        <?php else: ?>
        <form method="POST" class="entry-form">
            <label>Invoice
                <select name="invoice_id" required>
                    <option value="">Select invoice</option>
                    <?php foreach ($openInvoices as $i): ?><option value="<?= $i['id'] ?>"><?= htmlspecialchars($i['invoice_no']) ?> — <?= htmlspecialchars($i['customer_name']) ?> (<?= peso((float) $i['balance']) ?> due)</option><?php endforeach; ?>
                </select>
            </label>
            <label>Amount (₱)
                <input type="number" step="0.01" min="0.01" name="amount" required>
            </label>
            <label>Collection Date
                <input type="date" name="collection_date" value="<?= date('Y-m-d') ?>" required>
            </label>
            <label>Method
                <select name="method">
                    <option value="Cash">Cash</option>
                    <option value="GCash">GCash</option>
                    <option value="Bank Transfer">Bank Transfer</option>
                </select>
            </label>
            <div class="modal-actions">
                <button type="button" class="secondary-btn" data-close-modal>Cancel</button>
                <button type="submit" class="primary-btn">Save Payment</button>
            </div>
        </form>
        <?php endif; ?>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
