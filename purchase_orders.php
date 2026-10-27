<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
requireSalesWorkspace();
$pdo = getDB();

$error = '';
$notice = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
<<<<<<< HEAD
=======
<<<<<<< HEAD
    $quotationId = (int) ($_POST['quotation_id'] ?? 0);

    if ($action === 'create') {
        $qStmt = $pdo->prepare("SELECT * FROM sales_quotations WHERE id = ? AND status = 'Approved' AND admin_status = 'Approved'");
        $qStmt->execute([$quotationId]);
        $q = $qStmt->fetch();

        if (!$q) {
            $error = 'Only an Inventory-approved quotation can become a Purchase Order.';
        } else {
            $existing = $pdo->prepare('SELECT id FROM purchase_orders WHERE quotation_id = ?');
            $existing->execute([$quotationId]);
            if ($existing->fetchColumn()) {
                $error = 'A Purchase Order already exists for this quotation.';
            } else {
                $poNo = nextDocNo($pdo, 'purchase_orders', 'PO-', 4000);
                $pdo->prepare("INSERT INTO purchase_orders
                    (po_no, quotation_id, customer_id, product_code, inventory_product_id, quantity, total_amount, order_date, status)
                    VALUES (?,?,?,?,?,?,?,CURDATE(),'Open')")
                    ->execute([
                        $poNo, $quotationId, $q['customer_id'], $q['product_code'],
                        $q['inventory_product_id'], $q['quantity'], $q['total_amount']
                    ]);
                logActivity($pdo, 'Create', 'Purchase Order', 'Created ' . $poNo, (int)$pdo->lastInsertId());
                $notice = 'Purchase Order ' . $poNo . ' created from the approved quotation.';
            }
        }
    } elseif ($action === 'convert') {
        $poId = (int) ($_POST['po_id'] ?? 0);
        $stmt = $pdo->prepare("SELECT * FROM purchase_orders WHERE id = ? AND status = 'Open'");
=======
>>>>>>> origin/main

    if ($action === 'convert') {
        $poId = (int) ($_POST['po_id'] ?? 0);
        $stmt = $pdo->prepare("SELECT * FROM purchase_orders WHERE po_id = ? AND status = 'Open'");
<<<<<<< HEAD
=======
>>>>>>> origin/main
>>>>>>> origin/main
        $stmt->execute([$poId]);
        $po = $stmt->fetch();

        if (!$po) {
            $error = 'Purchase Order not found or already converted.';
        } else {
<<<<<<< HEAD
=======
<<<<<<< HEAD
            $orderNo = nextDocNo($pdo, 'sales_orders', 'SO-', 1000);
            $pdo->beginTransaction();
            try {
                $pdo->prepare("INSERT INTO sales_orders
                    (order_no, quotation_id, customer_id, order_date, product_code, quantity, total_amount, status)
                    VALUES (?,?,?,?,?,?,?,'Pending')")
                    ->execute([
                        $orderNo, $po['quotation_id'], $po['customer_id'], date('Y-m-d'),
                        $po['product_code'], $po['quantity'], $po['total_amount']
                    ]);
                $pdo->prepare("UPDATE purchase_orders SET status='Converted', converted_order_no=?, converted_at=NOW() WHERE id=?")
                    ->execute([$orderNo, $poId]);
                $pdo->commit();
=======
>>>>>>> origin/main
            // Check live stock at PO conversion time. If insufficient, keep the PO open.
            $stockCheck = checkInventoryProduct((string)$po['product_code']);
            if (empty($stockCheck['ok'])) {
                $error = $stockCheck['error'] ?? 'Could not check Inventory. Purchase Order remains open.';
            } elseif ((float)($stockCheck['quantity'] ?? 0) < (float)$po['quantity']) {
                $error = 'Insufficient Inventory for ' . $po['product_code'] . '. Available: ' . number_format((float)$stockCheck['quantity'], 2) . ', required: ' . number_format((float)$po['quantity'], 2) . '. Purchase Order remains open until stock is available.';
            } else {
            $orderNo = nextDocNo($pdo, 'sales_orders', 'SO-', 1000);
            $pay = $pdo->prepare("SELECT payment_method,payment_status,payment_reference,paid_at,receipt_no FROM sales_quotations WHERE sq_id=?");
            $pay->execute([$po['quotation_id']]);
            $payment = $pay->fetch() ?: ['payment_method' => 'Cash on Delivery', 'payment_status' => 'Pending', 'payment_reference' => null, 'paid_at' => null, 'receipt_no' => null];
            $pdo->beginTransaction();
            try {
                $pdo->prepare("INSERT INTO sales_orders
                    (order_no, quotation_id, customer_id, order_date, product_code, quantity, total_amount, status, admin_status, payment_method, payment_status, payment_reference, paid_at, receipt_no)
                    VALUES (?,?,?,?,?,?,?,'Pending','Pending',?,?,?,?,?)")
                    ->execute([
                        $orderNo,
                        $po['quotation_id'],
                        $po['customer_id'],
                        date('Y-m-d'),
                        $po['product_code'],
                        $po['quantity'],
                        $po['total_amount'],
                        $payment['payment_method'],
                        $payment['payment_status'],
                        $payment['payment_reference'],
                        $payment['paid_at'],
                        $payment['receipt_no']
                    ]);
                $pdo->prepare("UPDATE purchase_orders SET status='Converted', converted_order_no=?, converted_at=NOW() WHERE po_id=?")
                    ->execute([$orderNo, $poId]);
                $pdo->commit();
                logActivity($pdo, 'Create', 'Sales Order', 'Created ' . $orderNo . ' from ' . $po['po_no'], null);
<<<<<<< HEAD
=======
>>>>>>> origin/main
>>>>>>> origin/main
                header('Location: orders.php?section=core&added=1');
                exit;
            } catch (Throwable $e) {
                $pdo->rollBack();
                $error = 'Could not convert the Purchase Order: ' . $e->getMessage();
            }
<<<<<<< HEAD
            }
=======
<<<<<<< HEAD
=======
            }
>>>>>>> origin/main
>>>>>>> origin/main
        }
    }
}

$pageTitle = 'Purchase Orders';
include __DIR__ . '/includes/header.php';

$search = trim($_GET['search'] ?? '');
<<<<<<< HEAD
=======
<<<<<<< HEAD
$sql = "SELECT po.*, q.quotation_no, c.customer_name AS customer_name FROM purchase_orders po JOIN sales_quotations q ON q.id = po.quotation_id JOIN customers c ON c.id = po.customer_id";
$params = [];
if ($search !== '') { $sql .= ' WHERE po.po_no LIKE ? OR q.quotation_no LIKE ? OR c.customer_name LIKE ? OR po.product_code LIKE ? OR po.status LIKE ?'; $like='%'.$search.'%'; $params=[$like,$like,$like,$like,$like]; }
$sql .= ' ORDER BY po.id DESC';
$stmtRows=$pdo->prepare($sql); $stmtRows->execute($params); $rows=$stmtRows->fetchAll();

$approved = $pdo->query("
    SELECT q.id, q.quotation_no, q.product_code, q.quantity, q.total_amount, c.customer_name AS customer_name
    FROM sales_quotations q
    JOIN customers c ON c.id = q.customer_id
    LEFT JOIN purchase_orders po ON po.quotation_id = q.id
    WHERE q.status='Approved' AND q.admin_status='Approved' AND po.id IS NULL
    ORDER BY q.id DESC
")->fetchAll();
?>
<div class="page-toolbar">
    <p class="muted" style="color:white;">Sales Purchase Orders are created only after Inventory approves the quotation.</p>
    <button class="primary-btn" data-open-modal="addPOModal">+ New Purchase Order</button>
</div>
<div class="module-search"><form method="GET"><input type="hidden" name="section" value="core"><input type="search" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Search PO, quotation, customer, product, or status..."><button class="secondary-btn" type="submit">Search</button><?php if($search!==''): ?><a class="text-link" href="purchase_orders.php?section=core">Clear</a><?php endif; ?></form></div>
<?php if ($notice): ?><div class="alert success"><?= htmlspecialchars($notice) ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert error"><?= htmlspecialchars($error) ?></div><?php endif; ?>

<div class="panel"><div class="table-wrap"><table><thead><tr>
<th>PO No.</th><th>Quotation</th><th>Customer</th><th>Product</th><th>Qty</th><th>Total</th><th>Status</th><th>Action</th>
</tr></thead><tbody>
<?php if (!$rows): ?><tr class="empty-row"><td colspan="8">No Purchase Orders yet.</td></tr><?php endif; ?>
<?php foreach ($rows as $r): ?>
<tr>
<td><b><?= htmlspecialchars($r['po_no']) ?></b></td>
<td><?= htmlspecialchars($r['quotation_no']) ?></td>
<td><?= htmlspecialchars($r['customer_name']) ?></td>
<td><?= htmlspecialchars($r['product_code']) ?></td>
<td><?= (int)$r['quantity'] ?></td>
<td><?= peso((float)$r['total_amount']) ?></td>
<td><span class="badge <?= badgeClass($r['status']) ?>"><?= htmlspecialchars($r['status']) ?></span></td>
<td>
<?php if ($r['status'] === 'Open'): ?>
<form method="POST" style="display:inline"><input type="hidden" name="action" value="convert"><input type="hidden" name="po_id" value="<?= (int)$r['id'] ?>"><button class="table-action" type="submit">Convert to Sales Order</button></form>
<?php else: ?><?= htmlspecialchars($r['converted_order_no'] ?? 'Converted') ?><?php endif; ?>
</td>
</tr>
<?php endforeach; ?>
</tbody></table></div></div>

<div class="modal-backdrop" id="addPOModal" aria-hidden="true">
<div class="profile-modal entry-modal" role="dialog" aria-modal="true">
<button class="modal-close" type="button" data-close-modal aria-label="Close">×</button>
<p class="eyebrow">CORE SALES</p><h2>New Purchase Order</h2>
<p class="modal-subtitle">Select an approved quotation. Inventory approval is required.</p>
<form method="POST" class="entry-form">
<input type="hidden" name="action" value="create">
<label>Approved Quotation
<select name="quotation_id" required><option value="">Select quotation</option>
<?php foreach($approved as $q): ?><option value="<?= (int)$q['id'] ?>"><?= htmlspecialchars($q['quotation_no']) ?> — <?= htmlspecialchars($q['customer_name']) ?> — <?= htmlspecialchars($q['product_code']) ?> × <?= (int)$q['quantity'] ?></option><?php endforeach; ?>
</select></label>
<div class="modal-actions"><button type="button" class="secondary-btn" data-close-modal>Cancel</button><button class="primary-btn" type="submit">Create Purchase Order</button></div>
</form>
</div></div>
<?php include __DIR__ . '/includes/footer.php'; ?>
=======
>>>>>>> origin/main
$sql = "SELECT po.*, q.quotation_no, c.customer_name AS customer_name FROM purchase_orders po JOIN sales_quotations q ON q.sq_id = po.quotation_id JOIN customers c ON c.cus_id = po.customer_id";
$params = [];
if ($search !== '') {
    $sql .= ' WHERE po.po_no LIKE ? OR q.quotation_no LIKE ? OR c.customer_name LIKE ? OR po.product_code LIKE ? OR po.status LIKE ?';
    $like = '%' . $search . '%';
    $params = [$like, $like, $like, $like, $like];
}
$sql .= ' ORDER BY po.po_id DESC';
$stmtRows = $pdo->prepare($sql);
$stmtRows->execute($params);
$rows = $stmtRows->fetchAll();

?>
<div class="page-toolbar">
    <p class="muted" style="color:white;">Purchase Orders are created automatically when the customer approves a quotation. Convert an open PO into a Sales Order when Sales is ready to process it.</p>
</div>
<div class="module-search">
    <form method="GET"><input type="hidden" name="section" value="core"><input type="search" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Search PO, quotation, customer, product, or status..."><button class="secondary-btn" type="submit">Search</button><?php if ($search !== ''): ?><a class="text-link" href="purchase_orders.php?section=core">Clear</a><?php endif; ?></form>
</div>
<?php if ($notice): ?><div class="alert success"><?= htmlspecialchars($notice) ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert error"><?= htmlspecialchars($error) ?></div><?php endif; ?>

<div class="panel">
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>PO No.</th>
                    <th>Quotation</th>
                    <th>Customer</th>
                    <th>Product</th>
                    <th>Qty</th>
                    <th>Total</th>
                    <th>Status</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!$rows): ?><tr class="empty-row">
                        <td colspan="8">No Purchase Orders yet. They appear after the customer approves a quotation.</td>
                    </tr><?php endif; ?>
                <?php foreach ($rows as $r): ?>
                    <tr>
                        <td><b><?= htmlspecialchars($r['po_no']) ?></b></td>
                        <td><?= htmlspecialchars($r['quotation_no']) ?></td>
                        <td><?= htmlspecialchars($r['customer_name']) ?></td>
                        <td><?= htmlspecialchars($r['product_code']) ?></td>
                        <td><?= (int)$r['quantity'] ?></td>
                        <td><?= peso((float)$r['total_amount']) ?></td>
                        <td><span class="badge <?= badgeClass($r['status']) ?>"><?= htmlspecialchars($r['status']) ?></span></td>
                        <td>
                            <a class="table-action" href="po_export.php?id=<?= (int)$r['po_id'] ?>&format=pdf" target="_blank">PDF</a>
                            <a class="table-action" href="po_export.php?id=<?= (int)$r['po_id'] ?>&format=doc">Word</a>
                            <?php if ($r['status'] === 'Open'): ?>
                                <form method="POST" style="display:inline"><input type="hidden" name="action" value="convert"><input type="hidden" name="po_id" value="<?= (int)$r['po_id'] ?>"><button class="table-action" type="submit">Create Sales Order</button></form>
                            <?php else: ?><small><?= htmlspecialchars($r['converted_order_no'] ?? 'Converted') ?></small><?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<<<<<<< HEAD
<?php include __DIR__ . '/includes/footer.php'; ?>
=======
<?php include __DIR__ . '/includes/footer.php'; ?>
>>>>>>> origin/main
>>>>>>> origin/main
