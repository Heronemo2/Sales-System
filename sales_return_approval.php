<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
requireSalesAdmin();

$pdo = getDB();
$error = '';
$notice = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['type'] ?? '') === 'return') {
    $id = (int)($_POST['id'] ?? 0);
    $decision = $_POST['decision'] ?? '';
    if (!in_array($decision, ['Approved', 'Rejected'], true)) {
        $error = 'Invalid approval decision.';
    } else {
        try {
            $pdo->beginTransaction();
            $stmt = $pdo->prepare("SELECT r.ret_id,r.return_no,r.order_id,r.total_amount,i.inv_id,i.total_amount invoice_total,i.amount_paid FROM sales_returns r LEFT JOIN sales_invoices i ON i.order_id=r.order_id WHERE r.ret_id=? AND r.admin_status='Pending' FOR UPDATE");
            $stmt->execute([$id]);
            $return = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$return) {
                throw new RuntimeException('Sales return is no longer pending.');
            }

            if ($decision === 'Approved') {
                $returnAmount = round((float)$return['total_amount'], 2);
                if ($returnAmount <= 0) {
                    throw new RuntimeException('Enter a return amount greater than zero before approving it.');
                }
                if (empty($return['inv_id'])) {
                    throw new RuntimeException('No sales invoice is linked to this order, so the return cannot be applied.');
                }
                $invoiceTotal = round((float)$return['invoice_total'], 2);
                if ($returnAmount > $invoiceTotal) {
                    throw new RuntimeException('The return amount exceeds the remaining invoice total.');
                }

                $newInvoiceTotal = round($invoiceTotal - $returnAmount, 2);
                $amountPaid = (float)$return['amount_paid'];
                $invoiceStatus = $newInvoiceTotal <= 0
                    ? 'Returned'
                    : ($amountPaid >= $newInvoiceTotal
                        ? 'Paid'
                        : ($amountPaid > 0 ? 'Partially Paid' : 'Unpaid'));
                $pdo->prepare('UPDATE sales_invoices SET total_amount=?,status=? WHERE inv_id=?')
                    ->execute([$newInvoiceTotal,$invoiceStatus,(int)$return['inv_id']]);
            }

            $pdo->prepare("UPDATE sales_returns SET admin_status=?,status=? WHERE ret_id=? AND admin_status='Pending'")
                ->execute([$decision,$decision,$id]);
            if ($decision === 'Approved') {
                $pdo->prepare("UPDATE sales_orders SET status='Returned' WHERE so_id=?")
                    ->execute([(int)$return['order_id']]);
            }
            $pdo->commit();
            $notice = $decision === 'Approved'
                ? 'Sales return approved and deducted from the linked invoice.'
                : 'Sales return rejected.';
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $error = $e instanceof RuntimeException ? $e->getMessage() : 'Could not update the sales return approval.';
        }
    }
}

$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 10;
$total = (int)$pdo->query("SELECT COUNT(*) FROM sales_returns WHERE admin_status = 'Pending'")->fetchColumn();
$totalPages = max(1, (int)ceil($total / $perPage));
$page = min($page, $totalPages);
$offset = ($page - 1) * $perPage;

$rows = $pdo->prepare("SELECT r.*, o.order_no, c.customer_name FROM sales_returns r JOIN sales_orders o ON o.so_id = r.order_id JOIN customers c ON c.cus_id = o.customer_id WHERE r.admin_status = 'Pending' ORDER BY r.ret_id DESC LIMIT ? OFFSET ?");
$rows->bindValue(1, $perPage, PDO::PARAM_INT);
$rows->bindValue(2, $offset, PDO::PARAM_INT);
$rows->execute();
$returns = $rows->fetchAll(PDO::FETCH_ASSOC);

$pageTitle = 'Sales Return Approval';
include __DIR__ . '/includes/header.php';
?>

<?php if ($notice): ?><div class="alert success"><?= htmlspecialchars($notice) ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert error"><?= htmlspecialchars($error) ?></div><?php endif; ?>

<div class="panel" style="margin-top:18px;">
    <div class="panel-head">
        <div>
            <p class="eyebrow dark">SALES RETURN APPROVAL</p>
            <h2 style="margin:0;">Return Approval</h2>
        </div>
    </div>
    <div class="table-wrap">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Return No.</th>
                    <th>Order No.</th>
                    <th>Customer</th>
                    <th>Transaction Date</th>
                    <th>Reason</th>
                    <th>Total</th>
                    <th>Status</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!$returns): ?>
                    <tr><td colspan="8" class="empty-row">No pending sales return approvals.</td></tr>
                <?php endif; ?>
                <?php foreach ($returns as $row): ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($row['return_no']) ?></strong></td>
                        <td><?= htmlspecialchars($row['order_no']) ?></td>
                        <td><?= htmlspecialchars($row['customer_name']) ?></td>
                        <td><?= niceDate($row['return_date']) ?></td>
                        <td><?= htmlspecialchars($row['reason']) ?></td>
                        <td><?= peso((float)($row['total_amount'] ?? 0)) ?></td>
                        <td><span class="badge warning"><?= htmlspecialchars($row['admin_status']) ?></span></td>
                        <td>
                            <form method="POST" class="inline-actions approval-form" style="display:inline;">
                                <input type="hidden" name="type" value="return">
                                <input type="hidden" name="id" value="<?= (int)$row['ret_id'] ?>">
                                <button type="submit" class="primary-btn" name="decision" value="Approved">Approve</button>
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
            <?php if ($page > 1): ?><a class="secondary-btn" href="sales_return_approval.php?page=<?= (int)($page - 1) ?>">Previous</a><?php endif; ?>
            <span>Page <?= (int)$page ?> of <?= (int)$totalPages ?></span>
            <?php if ($page < $totalPages): ?><a class="secondary-btn" href="sales_return_approval.php?page=<?= (int)($page + 1) ?>">Next</a><?php endif; ?>
        </div>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
