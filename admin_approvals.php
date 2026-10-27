<<<<<<< HEAD
```php
<?php

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

requireSalesAdmin();

$pdo = getDB();

$error = '';
$notice = '';


// ============================================================
// HANDLE APPROVAL / REJECTION
// ============================================================

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $type = $_POST['type'] ?? '';
    $id = (int) ($_POST['id'] ?? 0);
    $decision = $_POST['decision'] ?? '';

    if (!in_array($decision, ['Approved', 'Rejected'], true)) {

        $error = 'Invalid approval decision.';

    } elseif ($type === 'quotation') {

        $st = $pdo->prepare("
            UPDATE sales_quotations
            SET admin_status = ?
            WHERE id = ?
              AND status = 'Approved'
              AND admin_status = 'Pending'
        ");

        $st->execute([$decision, $id]);

        if ($st->rowCount()) {

            logActivity(
                $pdo,
                $decision,
                'Quotation',
                $decision . ' quotation approval',
                $id
            );

            $notice = 'Quotation approval updated.';

        } else {

            $error = 'Quotation is no longer awaiting approval.';
        }

    } elseif ($type === 'return') {

        $st = $pdo->prepare("
            UPDATE sales_returns
            SET admin_status = ?
            WHERE id = ?
              AND admin_status = 'Pending'
        ");

        $st->execute([$decision, $id]);

        if ($st->rowCount()) {

            if ($decision === 'Approved') {

                $pdo
                    ->prepare("
                        UPDATE sales_returns
                        SET status = 'Approved'
                        WHERE id = ?
                    ")
                    ->execute([$id]);
            }

            logActivity(
                $pdo,
                $decision,
                'Sales Return',
                $decision . ' sales return approval',
                $id
            );

            $notice = 'Sales return approval updated.';

        } else {

            $error = 'Sales return is no longer awaiting approval.';
        }
    }
}


// ============================================================
// PAGE SETUP
// ============================================================

$pageTitle = 'Sales Approvals';

include __DIR__ . '/includes/header.php';


// ============================================================
// GET PENDING QUOTATIONS
// ============================================================

$quotations = $pdo->query("
    SELECT q.*, c.customer_name
    FROM sales_quotations q
    JOIN customers c ON c.id = q.customer_id
    WHERE q.status = 'Pending'
      AND q.admin_status = 'Pending'
    ORDER BY q.id ASC
")->fetchAll();


// ============================================================
// GET PENDING SALES RETURNS
// ============================================================

$returns = $pdo
    ->query("
        SELECT
            r.*,
            o.order_no,
            c.customer_name
        FROM sales_returns r
        JOIN sales_orders o
            ON o.id = r.order_id
        JOIN customers c
            ON c.id = o.customer_id
        WHERE r.admin_status = 'Pending'
        ORDER BY r.id ASC
    ")
    ->fetchAll();

?>

<!-- ============================================================
     PAGE TOOLBAR
============================================================ -->

<div class="page-toolbar">

    <p class="muted" style="color: white;">
        Review transactions after the Sales Staff and Inventory workflow.
        Approval here controls whether the transaction can proceed.
    </p>

</div>


<!-- ============================================================
     ALERT MESSAGES
============================================================ -->

<?php if ($notice): ?>

    <div class="alert success">
        <?= htmlspecialchars($notice) ?>
    </div>

<?php endif; ?>


<?php if ($error): ?>

    <div class="alert error">
        <?= htmlspecialchars($error) ?>
    </div>

<?php endif; ?>


<!-- ============================================================
     PENDING QUOTATIONS
============================================================ -->

<div class="panel">

    <div class="panel-head">

        <div>

            <p class="eyebrow" style="color: black;">
                QUOTATIONS
            </p>

            <h2>
                Pending Sales Quotation Approval
            </h2>

        </div>

    </div>


    <div class="table-wrap">

        <table>

            <thead>

                <tr>
                    <th>Quotation</th>
                    <th>Customer</th>
                    <th>Product</th>
                    <th>Qty</th>
                    <th>Total</th>
                    <th>Action</th>
                </tr>

            </thead>


            <tbody>

                <?php foreach ($quotations as $q): ?>

                    <tr>

                        <td>
                            <b>
                                <?= htmlspecialchars($q['quotation_no']) ?>
                            </b>
                        </td>

                        <td>
                            <?= htmlspecialchars($q['customer_name']) ?>
                        </td>

                        <td>
                            <?= htmlspecialchars($q['product_code']) ?>
                        </td>

                        <td>
                            <?= (int) $q['quantity'] ?>
                        </td>

                        <td>
                            <?= peso((float) $q['total_amount']) ?>
                        </td>

                        <td>

                            <form method="POST" class="inline-actions">

                                <input
                                    type="hidden"
                                    name="type"
                                    value="quotation"
                                >

                                <input
                                    type="hidden"
                                    name="id"
                                    value="<?= (int) $q['id'] ?>"
                                >

                                <button
                                    class="table-action approve"
                                    name="decision"
                                    value="Approved"
                                >
                                    Approve
                                </button>

                                <button
                                    class="table-action reject"
                                    name="decision"
                                    value="Rejected"
                                >
                                    Reject
                                </button>

                            </form>

                        </td>

                    </tr>

                <?php endforeach; ?>


                <?php if (!$quotations): ?>

                    <tr>

                        <td colspan="6">
                            No quotations are waiting for admin approval.
                        </td>

                    </tr>

                <?php endif; ?>

            </tbody>

        </table>

    </div>

</div>


<br>


<!-- ============================================================
     PENDING SALES RETURNS
============================================================ -->

<div class="panel">

    <div class="panel-head">

        <div>

            <p class="eyebrow" style="color: black;">
                SALES RETURNS
            </p>

            <h2>
                Pending Sales Return Approval
            </h2>

        </div>

    </div>


    <div class="table-wrap">

        <table>

            <thead>

                <tr>
                    <th>Return</th>
                    <th>Order</th>
                    <th>Customer</th>
                    <th>Reason</th>
                    <th>Amount</th>
                    <th>Action</th>
                </tr>

            </thead>


            <tbody>

                <?php foreach ($returns as $r): ?>

                    <tr>

                        <td>
                            <b>
                                <?= htmlspecialchars($r['return_no']) ?>
                            </b>
                        </td>

                        <td>
                            <?= htmlspecialchars($r['order_no']) ?>
                        </td>

                        <td>
                            <?= htmlspecialchars($r['customer_name']) ?>
                        </td>

                        <td>
                            <?= htmlspecialchars($r['reason']) ?>
                        </td>

                        <td>
                            <?= peso((float) $r['total_amount']) ?>
                        </td>

                        <td>

                            <form method="POST" class="inline-actions">

                                <input
                                    type="hidden"
                                    name="type"
                                    value="return"
                                >

                                <input
                                    type="hidden"
                                    name="id"
                                    value="<?= (int) $r['id'] ?>"
                                >

                                <button
                                    class="table-action approve"
                                    name="decision"
                                    value="Approved"
                                >
                                    Approve
                                </button>

                                <button
                                    class="table-action reject"
                                    name="decision"
                                    value="Rejected"
                                >
                                    Reject
                                </button>

                            </form>

                        </td>

                    </tr>

                <?php endforeach; ?>


                <?php if (!$returns): ?>

                    <tr>

                        <td colspan="6">
                            No sales returns are waiting for admin approval.
                        </td>

                    </tr>

                <?php endif; ?>

            </tbody>

        </table>

    </div>

</div>


<?php

include __DIR__ . '/includes/footer.php';

?>
```

I didn't change the SQL logic, conditions, database operations, form fields, or output—this is primarily an indentation/readability cleanup.
=======
<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
requireSalesAdmin();
$pdo = getDB();
$error = '';
$notice = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $type = $_POST['type'] ?? '';
    $id = (int)($_POST['id'] ?? 0);
    $decision = $_POST['decision'] ?? '';
    try {
        if ($type === 'quotation' && in_array($decision, ['Approved', 'Rejected'], true)) {
            $s = $pdo->prepare("UPDATE sales_quotations SET admin_status=? WHERE sq_id=? AND admin_status='Pending'");
            $s->execute([$decision, $id]);
            $notice = $s->rowCount() ? 'Sales quotation approval updated.' : 'Quotation is no longer pending.';
        } elseif ($type === 'order' && in_array($decision, ['Approved', 'Rejected'], true)) {
            if ($decision === 'Approved') {
                $delivery = convertOrderToDelivery($pdo, $id);
                $notice = "Sales Order approved and converted to Delivery {$delivery}.";
            } else {
                $s = $pdo->prepare("UPDATE sales_orders SET admin_status='Rejected',status='Cancelled' WHERE so_id=? AND admin_status='Pending'");
                $s->execute([$id]);
                $notice = $s->rowCount() ? 'Sales order rejected.' : 'Sales order is no longer pending.';
            }
        } elseif ($type === 'return' && in_array($decision, ['Approved', 'Rejected'], true)) {
            $s = $pdo->prepare("UPDATE sales_returns SET admin_status=?,status=? WHERE ret_id=? AND admin_status='Pending'");
            $s->execute([$decision, $decision === 'Approved' ? 'Approved' : 'Rejected', $id]);
            $notice = $s->rowCount() ? 'Sales return approval updated.' : 'Sales return is no longer pending.';
        }
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}
$quotations = $pdo->query("SELECT q.*,c.customer_name,r.request_no FROM sales_quotations q JOIN customers c ON c.cus_id=q.customer_id LEFT JOIN customer_order_requests r ON r.req_id=q.source_request_id WHERE q.admin_status='Pending' ORDER BY q.sq_id DESC")->fetchAll();
$orders = $pdo->query("SELECT o.*,c.customer_name FROM sales_orders o JOIN customers c ON c.cus_id=o.customer_id WHERE o.admin_status='Pending' ORDER BY o.so_id DESC")->fetchAll();
$returns = $pdo->query("SELECT r.*,o.order_no,c.customer_name FROM sales_returns r JOIN sales_orders o ON o.so_id=r.order_id JOIN customers c ON c.cus_id=o.customer_id WHERE r.admin_status='Pending' ORDER BY r.ret_id DESC")->fetchAll();
$pageTitle = 'Sales Approvals';
include __DIR__ . '/includes/header.php';
function approvalForm(string $type, int $id): void
{
    echo '<form method="POST" class="inline-actions"><input type="hidden" name="type" value="' . htmlspecialchars($type, ENT_QUOTES) . '"><input type="hidden" name="id" value="' . $id . '"><button class="primary-btn" name="decision" value="Approved">Approve</button><button class="secondary-btn" name="decision" value="Rejected">Reject</button></form>';
}
?>
<?php if ($notice): ?><div class="alert success"><?= htmlspecialchars($notice) ?></div><?php endif; ?><?php if ($error): ?><div class="alert error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
<div class="panel approval-panel">
    <div class="panel-head">
        <div>
            <p class="eyebrow dark">SALES QUOTATION APPROVAL</p>
            <h2>Quotation Approval Status</h2>
            <p class="muted">Review quotations in container form before they are sent to the customer for approval.</p>
        </div>
    </div>
    <div class="approval-card-list">
        <?php foreach ($quotations as $q): ?><article class="approval-container-card">
                <div class="approval-card-top">
                    <div><span class="eyebrow dark"><?= htmlspecialchars($q['quotation_no']) ?></span>
                        <h3><?= htmlspecialchars($q['customer_name']) ?></h3>
                        <p class="muted">Request: <?= htmlspecialchars($q['request_no'] ?? '—') ?> · <?= niceDate($q['quotation_date']) ?></p>
                    </div><span class="badge warning">Pending Approval</span>
                </div>
                <div class="approval-detail-grid">
                    <div><span>Product</span><b><?= htmlspecialchars($q['finished_good_code'] ?? '—') ?></b></div>
                    <div><span>Quantity</span><b><?= (int)$q['quantity'] ?></b></div>
                    <div><span>Inventory</span><b><?= htmlspecialchars($q['inventory_status']) ?></b></div>
                    <div><span>Total</span><b><?= peso((float)$q['total_amount']) ?></b></div>
                </div>
                <div class="approval-card-actions"><a class="secondary-btn" href="quotation_export.php?id=<?= (int)$q['sq_id'] ?>&format=pdf" target="_blank">View Quotation</a><?php approvalForm('quotation', (int)$q['sq_id']); ?></div>
            </article><?php endforeach; ?>
        <?php if (!$quotations): ?><div class="note-card"><b>No quotations are waiting for approval.</b><span>New quotations created by Sales will appear here.</span></div><?php endif; ?></div>
</div>
<div class="panel approval-panel" style="margin-top:18px">
    <div class="panel-head">
        <div>
            <p class="eyebrow dark">SALES ORDERS</p>
            <h2>Pending Sales Order Approval</h2>
        </div>
    </div>
    <div class="approval-card-list"><?php foreach ($orders as $o): ?><article class="approval-container-card">
                <div class="approval-card-top">
                    <div><span class="eyebrow dark"><?= htmlspecialchars($o['order_no']) ?></span>
                        <h3><?= htmlspecialchars($o['customer_name']) ?></h3>
                    </div><span class="badge warning">Pending</span>
                </div>
                <div class="approval-detail-grid">
                    <div><span>Product</span><b><?= htmlspecialchars($o['product_code']) ?></b></div>
                    <div><span>Quantity</span><b><?= (int)$o['quantity'] ?></b></div>
                    <div><span>Total</span><b><?= peso((float)$o['total_amount']) ?></b></div>
                </div>
                <div class="approval-card-actions"><?php approvalForm('order', (int)$o['so_id']); ?></div>
            </article><?php endforeach; ?><?php if (!$orders): ?><div class="note-card"><b>No Sales Orders are waiting for approval.</b></div><?php endif; ?></div>
</div>
<div class="panel approval-panel" style="margin-top:18px">
    <div class="panel-head">
        <div>
            <p class="eyebrow dark">SALES RETURNS</p>
            <h2>Pending Sales Return Approval</h2>
        </div>
    </div>
    <div class="approval-card-list"><?php foreach ($returns as $r): ?><article class="approval-container-card">
                <div class="approval-card-top">
                    <div><span class="eyebrow dark"><?= htmlspecialchars($r['return_no']) ?></span>
                        <h3><?= htmlspecialchars($r['customer_name']) ?></h3>
                        <p class="muted">Order <?= htmlspecialchars($r['order_no']) ?></p>
                    </div><span class="badge warning">Pending</span>
                </div>
                <div class="approval-detail-grid">
                    <div><span>Reason</span><b><?= htmlspecialchars($r['reason'] ?? '—') ?></b></div>
                    <div><span>Amount</span><b><?= peso((float)$r['total_amount']) ?></b></div>
                </div>
                <div class="approval-card-actions"><?php approvalForm('return', (int)$r['ret_id']); ?></div>
            </article><?php endforeach; ?><?php if (!$returns): ?><div class="note-card"><b>No Sales Returns are waiting for approval.</b></div><?php endif; ?></div>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
>>>>>>> origin/main
