<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
requireLogin();
$pdo = getDB();

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $customerId = (int) ($_POST['customer_id'] ?? 0);
    $date = $_POST['quotation_date'] ?? '';
    $validUntil = $_POST['valid_until'] ?: null;
    $total = (float) ($_POST['total_amount'] ?? 0);
    $status = in_array($_POST['status'] ?? '', ['Pending', 'Approved', 'Rejected', 'Expired'], true) ? $_POST['status'] : 'Pending';

    if (!$customerId || !$date || $total <= 0) {
        $error = 'Customer, date, and a total amount greater than 0 are required.';
    } else {
        $quotationNo = nextDocNo($pdo, 'sales_quotations', 'QT-', 2000);
        $stmt = $pdo->prepare('INSERT INTO sales_quotations (quotation_no, customer_id, quotation_date, valid_until, total_amount, status) VALUES (?,?,?,?,?,?)');
        $stmt->execute([$quotationNo, $customerId, $date, $validUntil, $total, $status]);
        header('Location: quotations.php?section=core&added=1');
        exit;
    }
}

$pageTitle = 'Sales Quotations';
include __DIR__ . '/includes/header.php';

$rows = $pdo->query('SELECT q.*, c.name AS customer_name FROM sales_quotations q JOIN customers c ON c.id = q.customer_id ORDER BY q.id DESC')->fetchAll();
$customers = $pdo->query('SELECT id, name FROM customers ORDER BY name')->fetchAll();
?>
<div class="page-toolbar"><p class="muted">Prepare estimated prices before a customer confirms a purchase.</p><button class="primary-btn" data-open-modal="addQuotationModal">+ New Quotation</button></div>

<?php if (!empty($_GET['added'])): ?><div class="alert success">Quotation created successfully.</div><?php endif; ?>
<?php if ($error): ?><div class="alert error"><?= htmlspecialchars($error) ?></div><?php endif; ?>

<div class="panel"><div class="table-wrap"><table><thead><tr><th>Quotation</th><th>Customer</th><th>Date</th><th>Total</th><th>Status</th></tr></thead><tbody>
<?php if (!$rows): ?><tr class="empty-row"><td colspan="5">No quotations yet. Create the first one above.</td></tr><?php endif; ?>
<?php foreach ($rows as $r): ?>
    <tr>
        <td><b><?= htmlspecialchars($r['quotation_no']) ?></b></td>
        <td><?= htmlspecialchars($r['customer_name']) ?></td>
        <td><?= niceDate($r['quotation_date']) ?></td>
        <td><?= peso((float) $r['total_amount']) ?></td>
        <td><span class="badge <?= badgeClass($r['status']) ?>"><?= htmlspecialchars($r['status']) ?></span></td>
    </tr>
<?php endforeach; ?>
</tbody></table></div></div>

<div class="modal-backdrop" id="addQuotationModal" aria-hidden="true">
    <div class="profile-modal entry-modal" role="dialog" aria-modal="true" aria-labelledby="addQuotationTitle">
        <button class="modal-close" type="button" aria-label="Close" data-close-modal>×</button>
        <p class="eyebrow">CORE SALES</p>
        <h2 id="addQuotationTitle">New Quotation</h2>
        <form method="POST" class="entry-form">
            <label>Customer
                <select name="customer_id" required>
                    <option value="">Select customer</option>
                    <?php foreach ($customers as $c): ?><option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['name']) ?></option><?php endforeach; ?>
                </select>
            </label>
            <label>Quotation Date
                <input type="date" name="quotation_date" value="<?= date('Y-m-d') ?>" required>
            </label>
            <label>Valid Until
                <input type="date" name="valid_until">
            </label>
            <label>Total Amount (₱)
                <input type="number" step="0.01" min="0.01" name="total_amount" required>
            </label>
            <label>Status
                <select name="status">
                    <option value="Pending">Pending</option>
                    <option value="Approved">Approved</option>
                    <option value="Rejected">Rejected</option>
                    <option value="Expired">Expired</option>
                </select>
            </label>
            <div class="modal-actions">
                <button type="button" class="secondary-btn" data-close-modal>Cancel</button>
                <button type="submit" class="primary-btn">Save Quotation</button>
            </div>
        </form>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
