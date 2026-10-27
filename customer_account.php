<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';


requireSalesWorkspace();

$pdo = getDB();
$pageTitle = 'Billing & Finance';
$error = '';
$notice = '';

/* =========================================================
   RECORD COLLECTION FROM THE SAME BILLING RECEIPT
   ========================================================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'record_collection') {
    $invoiceId = (int)($_POST['invoice_id'] ?? 0);
    $collectionDate = $_POST['collection_date'] ?? '';
    $dueDate = $_POST['due_date'] ?? '';
    $amount = (float)($_POST['amount'] ?? 0);
    $allowedMethods = ['Cash', 'GCash', 'Bank Transfer'];
    $method = in_array($_POST['method'] ?? '', $allowedMethods, true) ? $_POST['method'] : 'Cash';

    if (!$invoiceId || !$collectionDate || !$dueDate || $amount <= 0) {
        $error = 'Invoice, dates, and an amount greater than 0 are required.';
    } else {
        $stmt = $pdo->prepare("
            SELECT i.inv_id, i.invoice_no, i.total_amount, i.amount_paid
            FROM sales_invoices i
            WHERE i.inv_id = ?
            LIMIT 1
        ");
        $stmt->execute([$invoiceId]);
        $invoice = $stmt->fetch();

        if (!$invoice) {
            $error = 'Selected invoice was not found.';
        } else {
            $remaining = max(0, (float)$invoice['total_amount'] - (float)$invoice['amount_paid']);

            if ($amount > $remaining) {
                $error = 'Collection amount cannot be greater than the remaining Accounts Receivable.';
            } else {
                try {
                    $pdo->beginTransaction();

                    $collectionNo = nextDocNo($pdo, 'collections', 'COL-', 500);

                    $pdo->prepare("
                        INSERT INTO collections
                        (collection_no, invoice_id, collection_date, due_date, amount, method)
                        VALUES (?, ?, ?, ?, ?, ?)
                    ")->execute([
                        $collectionNo,
                        $invoiceId,
                        $collectionDate,
                        $dueDate,
                        $amount,
                        $method
                    ]);

                    $newPaid = (float)$invoice['amount_paid'] + $amount;
                    $newStatus = $newPaid >= (float)$invoice['total_amount']
                        ? 'Paid'
                        : 'Partially Paid';

                    $pdo->prepare("
                        UPDATE sales_invoices
                        SET amount_paid = ?, status = ?
                        WHERE inv_id = ?
                    ")->execute([$newPaid, $newStatus, $invoiceId]);

                    $pdo->commit();

                    header('Location: customer_account.php?section=billing&added=1');
                    exit;
                } catch (Throwable $e) {
                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }
                    $error = 'Could not save collection: ' . $e->getMessage();
                }
            }
        }
    }
}

if (!empty($_GET['added'])) {
    $notice = 'Collection recorded successfully. The Accounts Receivable and Customer Balance on the receipt were updated.';
}

/* =========================================================
   SEARCH
   ========================================================= */
$search = trim($_GET['search'] ?? '');

$where = '';
$params = [];

if ($search !== '') {
    $where = "
        WHERE i.invoice_no LIKE ?
           OR c.customer_name LIKE ?
           OR c.customer_code LIKE ?
    ";
    $like = '%' . $search . '%';
    $params = [$like, $like, $like];
}

/* =========================================================
   BILLING RECEIPTS
   One receipt contains:
   - Accounts Receivable
   - Collection history / collection form
   - Customer Balance
   ========================================================= */
$sql = "
    SELECT
        i.inv_id AS invoice_id,
        i.invoice_no,
        i.invoice_date,
        i.total_amount,
        i.amount_paid,
        i.status AS invoice_status,
        o.order_no,
        c.cus_id AS customer_id,
        c.customer_code,
        c.customer_name,
        c.payment_terms,
        DATE_ADD(i.invoice_date, INTERVAL 30 DAY) AS due_date,

        COALESCE(cb.total_billed, 0) AS customer_total_billed,
        COALESCE(cb.total_paid, 0) AS customer_total_paid,
        COALESCE(cb.outstanding, 0) AS customer_outstanding

    FROM sales_invoices i
    JOIN sales_orders o ON o.so_id = i.order_id
    JOIN customers c ON c.cus_id = o.customer_id

    LEFT JOIN (
        SELECT
            o2.customer_id,
            SUM(i2.total_amount) AS total_billed,
            SUM(i2.amount_paid) AS total_paid,
            SUM(i2.total_amount - i2.amount_paid) AS outstanding
        FROM sales_orders o2
        JOIN sales_invoices i2 ON i2.order_id = o2.so_id
        GROUP BY o2.customer_id
    ) cb ON cb.customer_id = c.cus_id

    $where
    ORDER BY i.invoice_date DESC, i.inv_id DESC
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$receipts = $stmt->fetchAll();

/* Collection history for all visible invoices. */
$collectionsByInvoice = [];
if ($receipts) {
    $ids = array_map(static fn($r) => (int)$r['invoice_id'], $receipts);
    $placeholders = implode(',', array_fill(0, count($ids), '?'));

    $collectionStmt = $pdo->prepare("
        SELECT collection_no, invoice_id, collection_date, due_date, amount, method
        FROM collections
        WHERE invoice_id IN ($placeholders)
        ORDER BY collection_date DESC, col_id DESC
    ");
    $collectionStmt->execute($ids);

    foreach ($collectionStmt->fetchAll() as $row) {
        $collectionsByInvoice[(int)$row['invoice_id']][] = $row;
    }
}
include __DIR__ . '/includes/header.php'; ?>


<div class="billing-workspace">
    <div class="billing-hero">
        <div>
            <p class="eyebrow light">BILLING / FINANCE</p>
            <h2>Billing &amp; Finance</h2>
            <p>Accounts Receivable, Collection, and Customer Balance are kept together on one billing receipt.</p>
        </div>
        <a class="secondary-btn" href="invoices.php?section=billing">Open Sales Invoices</a>
    </div>

    <?php if ($notice): ?>
        <div class="alert success"><?= htmlspecialchars($notice) ?></div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="alert error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <div class="module-search">
        <form method="GET">
            <input type="hidden" name="section" value="billing">
            <input type="search" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Search invoice, customer, or customer code...">
            <button class="secondary-btn" type="submit">Search</button>
            <?php if ($search !== ''): ?>
                <a class="text-link" href="customer_account.php?section=billing">Clear</a>
            <?php endif; ?>
        </form>
    </div>

    <?php if (!$receipts): ?>
        <div class="panel billing-empty">
            <strong>No billing receipts found.</strong>
            <span>Create a Sales Invoice first, then it will appear here as one combined Billing &amp; Finance receipt.</span>
        </div>
    <?php endif; ?>

    <div class="billing-receipt-list">
        <?php foreach ($receipts as $receipt):
            $invoiceId = (int)$receipt['invoice_id'];
            $total = (float)$receipt['total_amount'];
            $paid = (float)$receipt['amount_paid'];
            $amountDue = max(0, $total - $paid);
            $isPaid = $amountDue <= 0.00001;
            $overdue = !$isPaid && strtotime($receipt['due_date']) < strtotime('today');
            $receiptCollections = $collectionsByInvoice[$invoiceId] ?? [];
        ?>
            <article class="billing-receipt">
                <div class="receipt-top">
                    <div>
                        <p class="eyebrow dark">BILLING &amp; FINANCE RECEIPT</p>
                        <h2><?= htmlspecialchars($receipt['invoice_no']) ?></h2>
                        <p class="muted">
                            <?= htmlspecialchars($receipt['customer_name']) ?>
                            · <?= htmlspecialchars($receipt['customer_code']) ?>
                            · Order <?= htmlspecialchars($receipt['order_no']) ?>
                        </p>
                    </div>
                    <div class="receipt-status">
                        <span class="badge <?= $isPaid ? 'success' : ($overdue ? 'danger' : 'warning') ?>">
                            <?= $isPaid ? 'Paid' : ($overdue ? 'Overdue' : 'Outstanding') ?>
                        </span>
                        <small><?= niceDate($receipt['invoice_date']) ?></small>
                    </div>
                </div>

                <div class="receipt-divider"></div>

                <div class="receipt-sections">
                    <!-- ACCOUNTS RECEIVABLE -->
                    <section class="receipt-section">
                        <div class="receipt-section-title">01 · ACCOUNTS RECEIVABLE</div>
                        <div class="receipt-detail-grid">
                            <div><span>Invoice</span><strong><?= htmlspecialchars($receipt['invoice_no']) ?></strong></div>
                            <div><span>Invoice Date</span><strong><?= niceDate($receipt['invoice_date']) ?></strong></div>
                            <div><span>Due Date</span><strong><?= niceDate($receipt['due_date']) ?></strong></div>
                            <div><span>Invoice Total</span><strong><?= peso($total) ?></strong></div>
                        </div>
                        <div class="receipt-amount-row">
                            <span>Amount Paid</span>
                            <strong><?= peso($paid) ?></strong>
                        </div>
                        <div class="receipt-amount-row outstanding">
                            <span>Accounts Receivable / Amount Due</span>
                            <strong><?= peso($amountDue) ?></strong>
                        </div>
                    </section>

                    <!-- COLLECTION -->
                    <section class="receipt-section">
                        <div class="receipt-section-title">02 · COLLECTION</div>

                        <?php if ($receiptCollections): ?>
                            <div class="receipt-collection-list">
                                <?php foreach ($receiptCollections as $collection): ?>
                                    <div class="receipt-collection-row">
                                        <div>
                                            <span>Collection No.</span>
                                            <strong><?= htmlspecialchars($collection['collection_no']) ?></strong>
                                        </div>
                                        <div>
                                            <span>Date</span>
                                            <strong><?= niceDate($collection['collection_date']) ?></strong>
                                        </div>
                                        <div>
                                            <span>Method</span>
                                            <strong><?= htmlspecialchars($collection['method']) ?></strong>
                                        </div>
                                        <div>
                                            <span>Amount</span>
                                            <strong><?= peso((float)$collection['amount']) ?></strong>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <p class="empty-text">No collection has been recorded for this invoice.</p>
                        <?php endif; ?>

                        <?php if (!$isPaid): ?>
                            <form method="POST" class="receipt-payment-form">
                                <input type="hidden" name="action" value="record_collection">
                                <input type="hidden" name="invoice_id" value="<?= $invoiceId ?>">

                                <div class="receipt-form-title">Record Collection</div>

                                <div class="receipt-form-grid">
                                    <label>Amount (₱)
                                        <input type="number" name="amount" step="0.01" min="0.01" max="<?= htmlspecialchars(number_format($amountDue, 2, '.', '')) ?>" required>
                                    </label>

                                    <label>Collection Date
                                        <input type="date" name="collection_date" value="<?= date('Y-m-d') ?>" required>
                                    </label>

                                    <label>Due Date
                                        <input type="date" name="due_date" value="<?= htmlspecialchars($receipt['due_date']) ?>" required>
                                    </label>

                                    <label>Method
                                        <select name="method">
                                            <option value="Cash">Cash</option>
                                            <option value="GCash">GCash</option>
                                            <option value="Bank Transfer">Bank Transfer</option>
                                        </select>
                                    </label>
                                </div>

                                <button type="submit" class="primary-btn">Save Collection</button>
                            </form>
                        <?php endif; ?>
                    </section>

                    <!-- CUSTOMER BALANCE -->
                    <section class="receipt-section">
                        <div class="receipt-section-title">03 · CUSTOMER BALANCE</div>
                        <div class="customer-balance-box">
                            <div>
                                <span>Customer</span>
                                <strong><?= htmlspecialchars($receipt['customer_name']) ?></strong>
                            </div>
                            <div>
                                <span>Total Billed</span>
                                <strong><?= peso((float)$receipt['customer_total_billed']) ?></strong>
                            </div>
                            <div>
                                <span>Total Paid</span>
                                <strong><?= peso((float)$receipt['customer_total_paid']) ?></strong>
                            </div>
                            <div class="balance-highlight">
                                <span>Outstanding Balance</span>
                                <strong><?= peso((float)$receipt['customer_outstanding']) ?></strong>
                            </div>
                        </div>
                    </section>
                </div>

                <div class="receipt-footer">
                    <span>Payment Terms: <?= htmlspecialchars($receipt['payment_terms'] ?: '30 Days') ?></span>
                    <span>Receipt status is computed from the invoice and collection records.</span>
                </div>
            </article>
        <?php endforeach; ?>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
