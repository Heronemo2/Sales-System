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