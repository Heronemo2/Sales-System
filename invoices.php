<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
requireSalesWorkspace();
$pdo = getDB();
$error = '';
$notice = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'create') {
    $orderId = (int)($_POST['order_id'] ?? 0);
    $date = $_POST['invoice_date'] ?? date('Y-m-d');
    $st = $pdo->prepare("SELECT o.* FROM sales_orders o WHERE o.so_id=? AND o.admin_status='Approved' AND NOT EXISTS(SELECT 1 FROM sales_invoices i WHERE i.order_id=o.so_id)");
    $st->execute([$orderId]);
    $o = $st->fetch();
    if (!$o) $error = 'Only approved sales orders without an invoice can be invoiced.';
    else {
        $no = nextDocNo($pdo, 'sales_invoices', 'INV-', 3000);
        $paid = ($o['payment_status'] ?? 'Pending') === 'Paid' ? (float)$o['total_amount'] : 0.0;
        $invStatus = $paid > 0 ? 'Paid' : 'Unpaid';
        $pdo->prepare("INSERT INTO sales_invoices(invoice_no,order_id,invoice_date,total_amount,amount_paid,status) VALUES(?,?,?,?,?,?)")->execute([$no, $orderId, $date, $o['total_amount'], $paid, $invStatus]);
        $pdo->prepare("UPDATE sales_orders SET status='Invoiced' WHERE so_id=?")->execute([$orderId]);
        $notice = 'Sales Invoice ' . $no . ' created.';
    }
}
$orders = $pdo->query("SELECT o.so_id,o.order_no,o.total_amount,c.customer_name FROM sales_orders o JOIN customers c ON c.cus_id=o.customer_id WHERE o.admin_status='Approved' AND NOT EXISTS(SELECT 1 FROM sales_invoices i WHERE i.order_id=o.so_id) ORDER BY o.so_id DESC")->fetchAll();
$st = $pdo->query("SELECT i.*,o.order_no,o.product_code,o.quantity,c.customer_name,c.email,c.address FROM sales_invoices i JOIN sales_orders o ON o.so_id=i.order_id JOIN customers c ON c.cus_id=o.customer_id ORDER BY i.inv_id DESC");
$rows = $st->fetchAll();
$pageTitle = 'Sales Invoice';
include __DIR__ . '/includes/header.php';
?>
<div class="page-toolbar">
    <p class="muted" style="color:white;">Sales Invoices are displayed as formal invoice forms instead of a table.</p><button class="primary-btn" data-open-modal="invoiceModal">+ Create Sales Invoice</button>
</div>
<?php if ($notice): ?><div class="alert success"><?= htmlspecialchars($notice) ?></div><?php endif; ?><?php if ($error): ?><div class="alert error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
<div class="invoice-form-list">
    <?php foreach ($rows as $r): ?>
        <div class="invoice-paper">
            <div class="invoice-top">
                <div>
                    <p class="eyebrow dark">TEAM SALES</p>
                    <h2>SALES INVOICE</h2>
                </div>
                <div class="invoice-number"><span>Invoice No.</span><b><?= htmlspecialchars($r['invoice_no']) ?></b><small><?= niceDate($r['invoice_date']) ?></small></div>
            </div>
            <div class="invoice-customer">
                <div><span>Bill To</span><b><?= htmlspecialchars($r['customer_name']) ?></b><small><?= htmlspecialchars($r['address'] ?? '') ?></small><small><?= htmlspecialchars($r['email'] ?? '') ?></small></div>
                <div><span>Sales Order</span><b><?= htmlspecialchars($r['order_no']) ?></b></div>
            </div>
            <div class="invoice-line">
                <div><span>Product</span><b><?= htmlspecialchars($r['product_code']) ?></b></div>
                <div><span>Quantity</span><b><?= (int)$r['quantity'] ?></b></div>
                <div><span>Amount</span><b><?= peso((float)$r['total_amount']) ?></b></div>
            </div>
            <div class="invoice-total"><span>Total Amount</span><strong><?= peso((float)$r['total_amount']) ?></strong></div>
            <div class="invoice-status"><span class="badge <?= badgeClass($r['status']) ?>"><?= htmlspecialchars($r['status']) ?></span><a class="table-action" href="billing.php?section=billing">Open AR / Collection</a></div>
        </div>
    <?php endforeach; ?>
    <?php if (!$rows): ?><div class="panel">
            <div class="note-card"><b>No Sales Invoices yet.</b><span>Create an invoice after a Sales Order is approved.</span></div>
        </div><?php endif; ?>
</div>
<div class="modal-backdrop" id="invoiceModal" aria-hidden="true">
    <div class="profile-modal entry-modal"><button class="modal-close" type="button" data-close-modal>×</button>
        <p class="eyebrow">BILLING</p>
        <h2>Create Sales Invoice</h2>
        <form method="POST" class="entry-form"><input type="hidden" name="action" value="create"><label>Sales Order<select name="order_id" required>
                    <option value="">Select approved order</option><?php foreach ($orders as $o): ?><option value="<?= $o['id'] ?>"><?= htmlspecialchars($o['order_no']) ?> — <?= htmlspecialchars($o['customer_name']) ?> · <?= peso((float)$o['total_amount']) ?></option><?php endforeach; ?>
                </select></label><label>Invoice Date<input type="date" name="invoice_date" value="<?= date('Y-m-d') ?>" required></label>
            <div class="modal-actions"><button type="button" class="secondary-btn" data-close-modal>Cancel</button><button class="primary-btn">Create Invoice</button></div>
        </form>
    </div>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>