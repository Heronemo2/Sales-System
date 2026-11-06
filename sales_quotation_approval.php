<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
requireSalesAdmin();

$pdo = getDB();
$error = '';
$notice = '';

try {
    syncInventoryStockouts($pdo);
} catch (Throwable $e) {
    $error = 'Could not sync Inventory System stock status: ' . $e->getMessage();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['type'] ?? '') === 'quotation') {
    $id = (int)($_POST['id'] ?? 0);
    $decision = $_POST['decision'] ?? '';
    if (!in_array($decision, ['Approved', 'Rejected', 'RequestStock'], true)) {
        $error = 'Invalid approval decision.';
    } else {
        try {
            $st = $pdo->prepare("SELECT q.*, c.customer_name FROM sales_quotations q JOIN customers c ON c.cus_id = q.customer_id WHERE q.sq_id = ? AND q.admin_status = 'Pending' LIMIT 1");
            $st->execute([$id]);
            $quotation = $st->fetch(PDO::FETCH_ASSOC);
            if (!$quotation) {
                throw new RuntimeException('Quotation is no longer pending.');
            }

            if ($decision === 'Approved') {
                $localStock = getTeamSalesInventory($pdo, (string)$quotation['product_code']);
                $available = (float)($localStock['stock_quantity'] ?? 0);
                $required = (float)$quotation['quantity'];
                if (!$localStock || $required <= 0 || $available < $required || (($localStock['status'] ?? '') !== 'Active')) {
                    throw new RuntimeException('This quotation cannot be approved yet because Team Sales Inventory has insufficient stock.');
                }
                $upd = $pdo->prepare("UPDATE sales_quotations SET admin_status = 'Approved', inventory_status = 'Approved', inventory_quantity = ?, inventory_checked_at = NOW() WHERE sq_id = ? AND admin_status = 'Pending'");
                $upd->execute([(int)$available, $id]);
                $notice = $upd->rowCount() ? 'Quotation approved.' : 'Quotation is no longer pending.';
            } elseif ($decision === 'RequestStock') {
                $localStock = getTeamSalesInventory($pdo, (string)$quotation['product_code']);
                $available = (float)($localStock['stock_quantity'] ?? 0);
                $required = (float)$quotation['quantity'];
                $missing = max(0, $required - $available);
                if ($required <= 0 || $missing <= 0) {
                    throw new RuntimeException('A stock request is only needed when the quotation quantity exceeds available active stock.');
                }

                $existingRequestStmt = $pdo->prepare("
                    SELECT request_status
                    FROM inventory_stockout_approvals
                    WHERE source_reference = ?
                    ORDER BY id DESC
                    LIMIT 1
                ");
                $existingRequestStmt->execute([(string)$quotation['quotation_no']]);
                $existingRequestStatus = $existingRequestStmt->fetchColumn();
                if ($existingRequestStatus === 'Requested') {
                    throw new RuntimeException('A stock request for this quotation is already waiting for the Inventory System.');
                }
                if ($existingRequestStatus === 'Stocked Out') {
                    throw new RuntimeException('Inventory has stocked out the requested items. Approve the stock-out in Team Sales before requesting more.');
                }

                $request = createTeamSalesStockoutRequest(
                    $pdo,
                    (string)$quotation['product_code'],
                    $missing,
                    (string)$quotation['quotation_no']
                );
                $pdo->prepare("
                    UPDATE sales_quotations
                    SET inventory_status = 'Pending',
                        inventory_quantity = ?,
                        inventory_checked_at = NOW()
                    WHERE sq_id = ? AND admin_status = 'Pending'
                ")->execute([(int)$available, $id]);

                $notice = ($request['message'] ?? 'Stock request sent to Inventory System.')
                    . (!empty($request['request_number']) ? ' Request No.: ' . $request['request_number'] : '')
                    . ' The quotation remains pending until stock is received.';
            } else {
                $upd = $pdo->prepare("UPDATE sales_quotations SET admin_status = 'Rejected', status = 'Rejected' WHERE sq_id = ? AND admin_status = 'Pending'");
                $upd->execute([$id]);
                $notice = $upd->rowCount() ? 'Quotation rejected.' : 'Quotation is no longer pending.';
            }
        } catch (Throwable $e) {
            $error = $e->getMessage();
        }
    }
}

$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 10;
$total = (int)$pdo->query("SELECT COUNT(*) FROM sales_quotations WHERE admin_status = 'Pending'")->fetchColumn();
$totalPages = max(1, (int)ceil($total / $perPage));
$page = min($page, $totalPages);
$offset = ($page - 1) * $perPage;

$rows = $pdo->prepare("SELECT q.*, c.customer_name FROM sales_quotations q JOIN customers c ON c.cus_id = q.customer_id WHERE q.admin_status = 'Pending' ORDER BY q.sq_id DESC LIMIT ? OFFSET ?");
$rows->bindValue(1, $perPage, PDO::PARAM_INT);
$rows->bindValue(2, $offset, PDO::PARAM_INT);
$rows->execute();
$quotations = $rows->fetchAll(PDO::FETCH_ASSOC);
$requestStmt = $pdo->prepare("
    SELECT inventory_request_number, request_status
    FROM inventory_stockout_approvals
    WHERE source_reference = ?
    ORDER BY id DESC
    LIMIT 1
");
foreach ($quotations as &$quotation) {
    $stock = getTeamSalesInventory($pdo, (string)$quotation['product_code']);
    $quotation['available_stock'] = (float)($stock['stock_quantity'] ?? 0);
    $quotation['has_enough_stock'] = $stock
        && ($stock['status'] ?? '') === 'Active'
        && (float)$quotation['quantity'] > 0
        && $quotation['available_stock'] >= (float)$quotation['quantity'];
    $requestStmt->execute([(string)$quotation['quotation_no']]);
    $quotation['inventory_request'] = $requestStmt->fetch(PDO::FETCH_ASSOC) ?: null;
}
unset($quotation);

$pageTitle = 'Sales Quotation Approval';
include __DIR__ . '/includes/header.php';
?>

<?php if ($notice): ?><div class="alert success"><?= htmlspecialchars($notice) ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert error"><?= htmlspecialchars($error) ?></div><?php endif; ?>

<div class="panel" style="margin-top:18px;">
    <div class="panel-head">
        <div>
            <p class="eyebrow dark">SALES QUOTATION APPROVAL</p>
            <h2 style="margin:0;">Quotation Approval</h2>
        </div>
    </div>
    <div class="table-wrap">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Quotation No.</th>
                    <th>Customer</th>
                    <th>Transaction Date</th>
                    <th>Product</th>
                    <th>Quantity</th>
                    <th>Inventory</th>
                    <th>Total</th>
                    <th>Status</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!$quotations): ?>
                    <tr><td colspan="9" class="empty-row">No pending quotation approvals.</td></tr>
                <?php endif; ?>
                <?php foreach ($quotations as $row): ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($row['quotation_no']) ?></strong></td>
                        <td><?= htmlspecialchars($row['customer_name']) ?></td>
                        <td><?= niceDate($row['quotation_date']) ?></td>
                        <td><?= htmlspecialchars($row['product_code']) ?></td>
                        <td><?= (int)$row['quantity'] ?></td>
                        <td>
                            <?= number_format($row['available_stock'], 2) ?> available
                            <?php if (!$row['has_enough_stock']): ?>
                                <span class="badge warning">Insufficient</span>
                            <?php endif; ?>
                            <?php if (!empty($row['inventory_request'])): ?>
                                <br><small>
                                    <?= htmlspecialchars($row['inventory_request']['inventory_request_number'] ?? '') ?>
                                    · <?= htmlspecialchars($row['inventory_request']['request_status'] ?? '') ?>
                                </small>
                            <?php endif; ?>
                        </td>
                        <td><?= peso((float)$row['total_amount']) ?></td>
                        <td><span class="badge warning"><?= htmlspecialchars($row['admin_status']) ?></span></td>
                        <td>
                            <form method="POST" class="inline-actions approval-form" style="display:inline;">
                                <input type="hidden" name="type" value="quotation">
                                <input type="hidden" name="id" value="<?= (int)$row['sq_id'] ?>">
                                <?php if ($row['has_enough_stock']): ?>
                                    <button type="submit" class="primary-btn" name="decision" value="Approved">Approve</button>
                                <?php else: ?>
                                    <button type="submit" class="primary-btn" name="decision" value="Approved" disabled>Approve</button>
                                    <?php $requestStatus = $row['inventory_request']['request_status'] ?? ''; ?>
                                    <?php if ($requestStatus === 'Requested'): ?>
                                        <button type="button" class="secondary-btn" disabled>Waiting for Inventory</button>
                                    <?php elseif ($requestStatus === 'Stocked Out'): ?>
                                        <a class="secondary-btn" href="inventory_stockout_approval.php">Approve Stockout First</a>
                                    <?php else: ?>
                                        <button type="submit" class="secondary-btn" name="decision" value="RequestStock">Request Stock</button>
                                    <?php endif; ?>
                                <?php endif; ?>
                                <button type="submit" class="secondary-btn" name="decision" value="Rejected">Reject</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <?php if ($totalPages > 1): ?>
        <div class="pagination" style="margin-top:18px; display:flex; justify-content:center; gap:8px; align-items:center;">
            <?php if ($page > 1): ?><a class="secondary-btn" href="sales_quotation_approval.php?page=<?= (int)($page - 1) ?>">Previous</a><?php endif; ?>
            <span>Page <?= (int)$page ?> of <?= (int)$totalPages ?></span>
            <?php if ($page < $totalPages): ?><a class="secondary-btn" href="sales_quotation_approval.php?page=<?= (int)($page + 1) ?>">Next</a><?php endif; ?>
        </div>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
