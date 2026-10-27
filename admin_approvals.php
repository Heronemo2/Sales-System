<<<<<<< HEAD
<?php
=======
<<<<<<< HEAD
```php
<?php

>>>>>>> origin/main
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

requireSalesAdmin();

$pdo = getDB();

$error = '';
$notice = '';

<<<<<<< HEAD
try {
    // Inventory System is authoritative for Sales-directed stock requests.
    syncInventoryStockouts($pdo);
} catch (Throwable $e) {
    $error = 'Could not sync Inventory System stock status: ' . $e->getMessage();
}
$notice = '';

/*
|--------------------------------------------------------------------------
| POST ACTIONS
|--------------------------------------------------------------------------
*/
=======

// ============================================================
// HANDLE APPROVAL / REJECTION
// ============================================================
>>>>>>> origin/main

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $type = $_POST['type'] ?? '';
<<<<<<< HEAD
    $id = (int)($_POST['id'] ?? 0);
    $decision = $_POST['decision'] ?? '';

    try {

        /*
        |--------------------------------------------------------------------------
        | QUOTATION APPROVAL
        |--------------------------------------------------------------------------
        |
        | A quotation can only be approved by Sales Admin when:
        |
        | 1. Inventory currently has enough stock, OR
        | 2. Inventory has approved the stock request created for this quotation.
        |
        | Team Sales never changes Inventory stock here.
        |--------------------------------------------------------------------------
        */
        if ($type === 'quotation' && in_array($decision, ['Approved', 'Rejected'], true)) {

            $st = $pdo->prepare("
                SELECT
                    q.*,
                    c.customer_name,
                    c.email AS customer_email
                FROM sales_quotations q
                JOIN customers c
                    ON c.cus_id = q.customer_id
                WHERE q.sq_id = ?
                  AND q.admin_status = 'Pending'
                LIMIT 1
            ");
            $st->execute([$id]);
            $quotation = $st->fetch();

            if (!$quotation) {
                throw new RuntimeException('Quotation is no longer pending.');
            }

            if ($decision === 'Approved') {

                $localStock = getTeamSalesInventory(
                    $pdo,
                    (string)$quotation['product_code']
                );

                $available = (float)($localStock['stock_quantity'] ?? 0);
                $required = (float)$quotation['quantity'];

                $enoughCurrentStock =
                    $localStock &&
                    ($available >= $required) &&
                    (($localStock['status'] ?? 'Active') === 'Active');

                if (!$enoughCurrentStock) {
                    throw new RuntimeException(
                        'This quotation cannot be approved yet because Team Sales Inventory has insufficient stock. Request an Inventory stock-out first.'
                    );
                }

                $s = $pdo->prepare("
                    UPDATE sales_quotations
                    SET
                        admin_status = 'Approved',
                        inventory_status = 'Approved',
                        inventory_quantity = ?,
                        inventory_checked_at = NOW()
                    WHERE sq_id = ?
                      AND admin_status = 'Pending'
                ");

                $s->execute([
                    (int)$available,
                    $id
                ]);

                $notice = $s->rowCount()
                    ? 'Sales quotation approved and ready to be sent to the customer.'
                    : 'Quotation is no longer pending.';

                if ($s->rowCount()) {
                    logActivity(
                        $pdo,
                        'Approve',
                        'Quotation',
                        'Sales Admin approved quotation ' . $quotation['quotation_no'],
                        $id
                    );
                }

            } else {

                $s = $pdo->prepare("
                    UPDATE sales_quotations
                    SET
                        admin_status = 'Rejected',
                        status = 'Rejected'
                    WHERE sq_id = ?
                      AND admin_status = 'Pending'
                ");

                $s->execute([$id]);

                $notice = $s->rowCount()
                    ? 'Sales quotation rejected.'
                    : 'Quotation is no longer pending.';
            }

        /*
        |--------------------------------------------------------------------------
        | REQUEST STOCKOUT FROM SALES APPROVAL
        |--------------------------------------------------------------------------
        | The request form is opened from the Sales Approval quotation card.
        | This keeps the stock request workflow in one place.
        */
        } elseif ($type === 'request_stockout') {

            $quotationId = (int)($_POST['quotation_id'] ?? 0);
            $productCode = trim($_POST['product_code'] ?? '');
            $quantity = (float)($_POST['quantity'] ?? 0);
            $reference = trim($_POST['reference'] ?? '');

            $st = $pdo->prepare("
                SELECT q.*
                FROM sales_quotations q
                WHERE q.sq_id = ?
                  AND q.admin_status = 'Pending'
                LIMIT 1
            ");
            $st->execute([$quotationId]);
            $quotation = $st->fetch(PDO::FETCH_ASSOC);

            if (!$quotation) {
                throw new RuntimeException('Quotation was not found or is no longer pending.');
            }

            if ($productCode === '' || $quantity <= 0) {
                throw new RuntimeException('Select a product and enter a valid quantity.');
            }

            if ((string)$quotation['product_code'] !== $productCode) {
                throw new RuntimeException('The selected product does not match the quotation.');
            }

            $localStock = getTeamSalesInventory($pdo, $productCode);
            $available = (float)($localStock['stock_quantity'] ?? 0);
            $required = (float)$quotation['quantity'];
            $missing = max(0, $required - $available);

            if ($missing <= 0) {
                throw new RuntimeException('Team Sales Inventory already has enough stock. You can approve the quotation.');
            }

            if ($quantity < $missing) {
                throw new RuntimeException(
                    'The requested quantity must be at least ' . rtrim(rtrim(number_format($missing, 2, '.', ''), '0'), '.') . '.'
                );
            }

            if ($reference === '') {
                $reference = (string)$quotation['quotation_no'];
            }

            $result = createTeamSalesStockoutRequest(
                $pdo,
                $productCode,
                $quantity,
                $reference
            );

            $pdo->prepare("
                UPDATE sales_quotations
                SET
                    inventory_status = 'Pending',
                    inventory_quantity = ?,
                    inventory_checked_at = NOW()
                WHERE sq_id = ?
                  AND admin_status = 'Pending'
            ")->execute([
                (int)$available,
                $quotationId
            ]);

            $notice = $result['message'] .
                (!empty($result['request_number'])
                    ? ' Request No.: ' . $result['request_number']
                    : '');

            logActivity(
                $pdo,
                'Create',
                'Inventory Stock Request',
                'Requested Inventory stock for quotation ' . $quotation['quotation_no'],
                $quotationId
            );

        /*
        |--------------------------------------------------------------------------
        | REQUEST STOCK (LEGACY)
        |--------------------------------------------------------------------------
        */
        } elseif ($type === 'specific_stock_request') {

            $productCode = trim($_POST['product_code'] ?? '');
            $quantity = (float)($_POST['quantity'] ?? 0);
            $remarks = trim($_POST['remarks'] ?? '');

            if ($productCode === '' || $quantity <= 0) {
                throw new RuntimeException('Select a product and enter a valid quantity.');
            }

            $result = createTeamSalesStockoutRequest(
                $pdo,
                $productCode,
                $quantity,
                $remarks !== '' ? $remarks : 'Specific stock request from Sales Approval'
            );

            $notice = ($result['message'] ?? 'Stock request sent to Inventory System.')
                . (!empty($result['request_number'])
                    ? ' Request No.: ' . $result['request_number']
                    : '');

            logActivity(
                $pdo,
                'Create',
                'Inventory Stock Request',
                'Created specific stock request ' . ($result['request_number'] ?? ''),
                (int)($result['request_id'] ?? 0)
            );

        } elseif ($type === 'quotation_stock_request') {

            $st = $pdo->prepare("
                SELECT
                    q.*,
                    c.customer_name,
                    c.email AS customer_email
                FROM sales_quotations q
                JOIN customers c
                    ON c.cus_id = q.customer_id
                WHERE q.sq_id = ?
                  AND q.admin_status = 'Pending'
                LIMIT 1
            ");
            $st->execute([$id]);
            $quotation = $st->fetch();

            if (!$quotation) {
                throw new RuntimeException('Quotation was not found or is no longer pending.');
            }

            $localStock = getTeamSalesInventory(
                $pdo,
                (string)$quotation['product_code']
            );

            $available = (float)($localStock['stock_quantity'] ?? 0);
            $required = (float)$quotation['quantity'];
            $missing = max(0, $required - $available);

            if ($missing <= 0) {
                throw new RuntimeException(
                    'Team Sales Inventory already has enough stock. You can approve the quotation.'
                );
            }

            $result = createTeamSalesStockoutRequest(
                $pdo,
                (string)$quotation['product_code'],
                $missing,
                (string)$quotation['quotation_no']
            );

            $pdo->prepare("
                UPDATE sales_quotations
                SET
                    inventory_status = 'Pending',
                    inventory_quantity = ?,
                    inventory_checked_at = NOW()
                WHERE sq_id = ?
            ")->execute([
                (int)$available,
                $id
            ]);

            $notice = $result['message'] .
                (!empty($result['request_number'])
                    ? ' Request No.: ' . $result['request_number']
                    : '');

            logActivity(
                $pdo,
                'Create',
                'Inventory Stock Request',
                'Requested Inventory stock for quotation ' . $quotation['quotation_no'],
                $id
            );

        /*
        |--------------------------------------------------------------------------
        | SALES ORDER APPROVAL
        |--------------------------------------------------------------------------
        */
        } elseif ($type === 'order' && in_array($decision, ['Approved', 'Rejected'], true)) {

            if ($decision === 'Approved') {

                $orderCheck = $pdo->prepare("
                    SELECT product_code, quantity, order_no
                    FROM sales_orders
                    WHERE so_id = ?
                    LIMIT 1
                ");
                $orderCheck->execute([$id]);
                $orderRow = $orderCheck->fetch(PDO::FETCH_ASSOC);

                $localStock = getTeamSalesInventory(
                    $pdo,
                    (string)($orderRow['product_code'] ?? '')
                );
                $available = (float)($localStock['stock_quantity'] ?? 0);
                $required = (float)($orderRow['quantity'] ?? 0);

                if (!$localStock || $available < $required) {
                    $missing = max(0, $required - $available);
                    $requestResult = createTeamSalesStockoutRequest(
                        $pdo,
                        (string)($orderRow['product_code'] ?? ''),
                        $missing,
                        (string)($orderRow['order_no'] ?? 'Sales Order')
                    );

                    throw new RuntimeException(
                        ($requestResult['message'] ?? 'Inventory stock-out request sent.') .
                        ' The Sales Order remains pending until the stock-out is approved in Team Sales.'
                    );
                }

                $delivery = convertOrderToDelivery($pdo, $id);
                $notice = "Sales Order approved and converted to Delivery {$delivery}.";

            } else {

                $s = $pdo->prepare("
                    UPDATE sales_orders
                    SET
                        admin_status = 'Rejected',
                        status = 'Cancelled'
                    WHERE so_id = ?
                      AND admin_status = 'Pending'
                ");

                $s->execute([$id]);

                $notice = $s->rowCount()
                    ? 'Sales order rejected.'
                    : 'Sales order is no longer pending.';
            }

        /*
        |--------------------------------------------------------------------------
        | SALES RETURN APPROVAL
        |--------------------------------------------------------------------------
        */
        } elseif ($type === 'return' && in_array($decision, ['Approved', 'Rejected'], true)) {

            $s = $pdo->prepare("
                UPDATE sales_returns
                SET
                    admin_status = ?,
                    status = ?
                WHERE ret_id = ?
                  AND admin_status = 'Pending'
            ");

            $s->execute([
                $decision,
                $decision === 'Approved' ? 'Approved' : 'Rejected',
                $id
            ]);

            $notice = $s->rowCount()
                ? 'Sales return approval updated.'
                : 'Sales return is no longer pending.';
        }

    } catch (Throwable $e) {

        $error = $e->getMessage();
    }

    try {
        syncInventoryStockouts($pdo);
    } catch (Throwable $e) {
        if ($error === '') {
            $error = 'Could not refresh Inventory System status: ' . $e->getMessage();
=======
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
>>>>>>> origin/main
        }
    }
}


<<<<<<< HEAD
/*
|--------------------------------------------------------------------------
| PENDING QUOTATIONS
|--------------------------------------------------------------------------
*/

$quotations = $pdo->query("
    SELECT
        q.*,
        c.customer_name,
        c.email AS customer_email,
        r.request_no
    FROM sales_quotations q
    JOIN customers c
        ON c.cus_id = q.customer_id
    LEFT JOIN customer_order_requests r
        ON r.req_id = q.source_request_id
    WHERE q.admin_status = 'Pending'
    ORDER BY q.sq_id DESC
")->fetchAll();


/*
|--------------------------------------------------------------------------
| CHECK LIVE INVENTORY + INVENTORY REQUEST STATUS
|--------------------------------------------------------------------------
*/

foreach ($quotations as &$q) {

    $localStock = getTeamSalesInventory(
        $pdo,
        (string)($q['product_code'] ?? '')
    );

    $q['live_inventory_ok'] = (bool)$localStock;
    $q['live_inventory_quantity'] = (float)($localStock['stock_quantity'] ?? 0);
    $q['live_inventory_status'] = $localStock['status'] ?? 'Unavailable';

    $required = (float)($q['quantity'] ?? 0);

    $q['enough_stock'] =
        $localStock &&
        ($q['live_inventory_quantity'] >= $required) &&
        $q['live_inventory_status'] === 'Active';

    $requestStmt = $pdo->prepare("
        SELECT inventory_request_number AS request_number, request_status, stocked_out_at
        FROM inventory_stockout_approvals
        WHERE source_reference = ?
        ORDER BY id DESC
        LIMIT 1
    ");
    $requestStmt->execute([(string)($q['quotation_no'] ?? '')]);
    $q['inventory_request'] = $requestStmt->fetch(PDO::FETCH_ASSOC) ?: null;

    $q['inventory_request_approved'] =
        !empty($q['inventory_request']) &&
        ($q['inventory_request']['request_status'] ?? '') === 'Approved';

}
unset($q);


$orders = $pdo->query("
    SELECT
        o.*,
        c.customer_name
    FROM sales_orders o
    JOIN customers c
        ON c.cus_id = o.customer_id
    WHERE o.admin_status = 'Pending'
    ORDER BY o.so_id DESC
")->fetchAll();


$returns = $pdo->query("
    SELECT
        r.*,
        o.order_no,
        c.customer_name
    FROM sales_returns r
    JOIN sales_orders o
        ON o.so_id = r.order_id
    JOIN customers c
        ON c.cus_id = o.customer_id
    WHERE r.admin_status = 'Pending'
    ORDER BY r.ret_id DESC
")->fetchAll();


$stockRequestProducts = [];
try {
    require_once __DIR__ . '/config/inventory_db.php';
    $inventoryDb = getInventoryDB();
    $stockRequestProducts = $inventoryDb->query("
        SELECT item_code, item_name, size, unit
        FROM items
        WHERE status = 'Active'
          AND item_code IS NOT NULL
          AND item_code <> ''
        ORDER BY item_name ASC, size ASC, item_code ASC
    ")->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    if ($error === '') {
        $error = 'Could not load Inventory products: ' . $e->getMessage();
    }
}
=======
// ============================================================
// PAGE SETUP
// ============================================================
>>>>>>> origin/main

$pageTitle = 'Sales Approvals';

include __DIR__ . '/includes/header.php';


<<<<<<< HEAD
function approvalForm(string $type, int $id, bool $disabled = false): void
{
    $disabledAttr = $disabled ? ' disabled' : '';

    echo '<form method="POST" class="inline-actions approval-form">'
        . '<input type="hidden" name="type" value="' . htmlspecialchars($type, ENT_QUOTES) . '">'
        . '<input type="hidden" name="id" value="' . $id . '">'
        . '<button type="submit" class="primary-btn" name="decision" value="Approved"' . $disabledAttr . '>Approve</button>'
        . '<button type="submit" class="secondary-btn" name="decision" value="Rejected">Reject</button>'
        . '</form>';
}
?>

<?php if ($notice): ?>
    <div class="alert success"><?= htmlspecialchars($notice) ?></div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="alert error"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>


<div class="approval-tabs">
    <a class="approval-tab <?= (($_GET['tab'] ?? 'approvals') === 'approvals') ? 'active' : '' ?>" href="admin_approvals.php?tab=approvals">Sales Approvals</a>
    <a class="approval-tab <?= (($_GET['tab'] ?? '') === 'stock-request') ? 'active' : '' ?>" href="admin_approvals.php?tab=stock-request">Request Specific Stock</a>
</div>

<div class="panel stock-request-tab-panel" style="margin-top:18px;display:<?= (($_GET['tab'] ?? '') === 'stock-request') ? 'block' : 'none' ?>;">
    <div class="panel-head">
        <div>
            <p class="eyebrow dark">INVENTORY REQUEST</p>
            <h2 style="margin:0;">Request a Specific Product</h2>
            <p class="muted" style="margin:5px 0 0;">Select the exact product and size, enter the quantity and remarks, then send the request to Inventory for approval.</p>
        </div>
        <button type="button" class="primary-btn" onclick="openSpecificStockRequestModal()">+ New Stock Request</button>
    </div>
    <div class="note-card">
        <b>Inventory System is the approving department.</b>
        <span>After submission, the request code is generated automatically. Approval, decline, and stock-out results will sync back to Team Sales.</span>
    </div>
</div>

<?php $stockRequestTab = (($_GET['tab'] ?? 'approvals') === 'stock-request'); ?>
<div class="sales-approval-content" style="display:<?= $stockRequestTab ? 'none' : 'block' ?>;">

<!--
|--------------------------------------------------------------------------
| SALES QUOTATION APPROVAL
|--------------------------------------------------------------------------
-->

<div class="panel approval-panel">
=======
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
>>>>>>> origin/main

    <div class="panel-head">

        <div>

<<<<<<< HEAD
            <p class="eyebrow dark">
                SALES QUOTATION APPROVAL
            </p>

            <h2>
                Quotation Approval Status
            </h2>

            <p class="muted">
                The Sales Admin checks Team Sales Inventory before approving the quotation for the customer.
            </p>

=======
            <p class="eyebrow" style="color: black;">
                QUOTATIONS
            </p>

            <h2>
                Pending Sales Quotation Approval
            </h2>

>>>>>>> origin/main
        </div>

    </div>


<<<<<<< HEAD
    <div class="approval-card-list">

        <?php foreach ($quotations as $q): ?>

            <?php
                $request = $q['inventory_request'] ?? null;
                $requestStatus = $request['request_status'] ?? '';
                $canApprove = $q['enough_stock'];
            ?>

            <article class="approval-container-card">

                <div class="approval-card-top">

                    <div>

                        <span class="eyebrow dark">
                            <?= htmlspecialchars($q['quotation_no']) ?>
                        </span>

                        <h3>
                            <?= htmlspecialchars($q['customer_name']) ?>
                        </h3>

                        <p class="muted">
                            Request:
                            <?= htmlspecialchars($q['request_no'] ?? '—') ?>
                            ·
                            <?= niceDate($q['quotation_date']) ?>
                        </p>

                    </div>

                    <span class="badge warning">
                        Pending Admin Approval
                    </span>

                </div>


                <div class="approval-detail-grid">

                    <div>
                        <span>Product</span>
                        <b><?= htmlspecialchars($q['product_code'] ?? '—') ?></b>
                    </div>

                    <div>
                        <span>Required</span>
                        <b><?= (int)$q['quantity'] ?></b>
                    </div>

                    <div>
                        <span>Current Stock</span>
                        <b>
                            <?= $q['live_inventory_ok']
                                ? number_format((float)$q['live_inventory_quantity'], 2)
                                : 'Unavailable' ?>
                        </b>
                    </div>

                    <div>
                        <span>Inventory</span>
                        <b>
                            <?php if ($q['enough_stock']): ?>
                                <span class="badge success">Stock Available</span>
                            <?php elseif ($requestStatus === 'Requested'): ?>
                                <span class="badge warning">Waiting for Inventory</span>
                            <?php elseif ($requestStatus === 'Stocked Out'): ?>
                                <span class="badge warning">Stocked Out — Team Sales Approval Needed</span>
                            <?php elseif ($requestStatus === 'Approved'): ?>
                                <span class="badge success">Stock-out Added to Team Sales</span>
                            <?php elseif ($requestStatus === 'Rejected'): ?>
                                <span class="badge danger">Stock-out Rejected</span>
                            <?php else: ?>
                                <span class="badge danger">Insufficient Stock</span>
                            <?php endif; ?>
                        </b>
                    </div>

                    <div>
                        <span>Total</span>
                        <b><?= peso((float)$q['total_amount']) ?></b>
                    </div>

                </div>


                <?php if ($request): ?>

                    <div class="note-card" style="margin-top:14px;">

                        <b>
                            Inventory Request:
                            <?= htmlspecialchars($request['request_number'] ?? 'Not Requested') ?>
                        </b>

                        <span>
                            Status:
                            <strong>
                                <?= htmlspecialchars($requestStatus ?? 'Pending') ?>
                            </strong>

                            <?php if (!empty($request['warehouse_name'])): ?>
                                · Warehouse:
                                <?= htmlspecialchars($request['warehouse_name']) ?>
                            <?php endif; ?>
                        </span>

                    </div>

                <?php endif; ?>


                <div class="approval-card-actions">

                    <a
                        class="secondary-btn"
                        href="quotation_export.php?id=<?= (int)$q['sq_id'] ?>&format=pdf"
                        target="_blank"
                    >
                        View Quotation
                    </a>


                    <?php if (!$q['enough_stock'] && $requestStatus !== 'Approved'): ?>

                        <?php if ($requestStatus === 'Requested'): ?>

                            <button
                                class="secondary-btn"
                                type="button"
                                disabled
                            >
                                Waiting for Inventory
                            </button>

                        <?php elseif ($requestStatus === 'Stocked Out'): ?>

                            <a class="secondary-btn" href="inventory_stockout_approval.php">
                                Approve Stockout First
                            </a>

                        <?php else: ?>

                            <button
                                class="primary-btn stock-request-trigger"
                                type="button"
                                data-quotation-id="<?= (int)$q['sq_id'] ?>"
                                data-product-code="<?= htmlspecialchars((string)$q['product_code'], ENT_QUOTES) ?>"
                                data-product-name="<?= htmlspecialchars((string)($q['product_code'] . ' — ' . ($q['product_name'] ?? '')), ENT_QUOTES) ?>"
                                data-quantity="<?= htmlspecialchars((string)max(0, (float)$q['quantity'] - (float)$q['live_inventory_quantity']), ENT_QUOTES) ?>"
                                data-reference="<?= htmlspecialchars((string)$q['quotation_no'], ENT_QUOTES) ?>"
                                onclick="openStockRequestModal(this)"
                            >
                                Request Stock
                            </button>

                        <?php endif; ?>

                    <?php endif; ?>


                    <?php
                        approvalForm(
                            'quotation',
                            (int)$q['sq_id'],
                            !$canApprove
                        );
                    ?>

                </div>

            </article>

        <?php endforeach; ?>


        <?php if (!$quotations): ?>

            <div class="note-card">

                <b>
                    No quotations are waiting for approval.
                </b>

                <span>
                    New quotations created by Sales Admin will appear here.
                </span>

            </div>

        <?php endif; ?>
=======
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
>>>>>>> origin/main

    </div>

</div>


<<<<<<< HEAD
<!--
|--------------------------------------------------------------------------
| SALES ORDERS
|--------------------------------------------------------------------------
-->

<div class="panel approval-panel" style="margin-top:18px">
=======
<br>


<!-- ============================================================
     PENDING SALES RETURNS
============================================================ -->

<div class="panel">
>>>>>>> origin/main

    <div class="panel-head">

        <div>

<<<<<<< HEAD
            <p class="eyebrow dark">
                SALES ORDERS
            </p>

            <h2>
                Pending Sales Order Approval
            </h2>

        </div>

    </div>

    <div class="approval-card-list">

        <?php foreach ($orders as $o): ?>

            <article class="approval-container-card">

                <div class="approval-card-top">

                    <div>

                        <span class="eyebrow dark">
                            <?= htmlspecialchars($o['order_no']) ?>
                        </span>

                        <h3>
                            <?= htmlspecialchars($o['customer_name']) ?>
                        </h3>

                    </div>

                    <span class="badge warning">
                        Pending
                    </span>

                </div>

                <div class="approval-detail-grid">

                    <div>
                        <span>Product</span>
                        <b><?= htmlspecialchars($o['product_code']) ?></b>
                    </div>

                    <div>
                        <span>Quantity</span>
                        <b><?= (int)$o['quantity'] ?></b>
                    </div>

                    <div>
                        <span>Total</span>
                        <b><?= peso((float)$o['total_amount']) ?></b>
                    </div>

                </div>

                <div class="approval-card-actions">

                    <?php approvalForm('order', (int)$o['so_id']); ?>

                </div>

            </article>

        <?php endforeach; ?>


        <?php if (!$orders): ?>

            <div class="note-card">
                <b>No Sales Orders are waiting for approval.</b>
            </div>

        <?php endif; ?>

    </div>

</div>


<!--
|--------------------------------------------------------------------------
| SALES RETURNS
|--------------------------------------------------------------------------
-->

<div class="panel approval-panel" style="margin-top:18px">

    <div class="panel-head">

        <div>

            <p class="eyebrow dark">
=======
            <p class="eyebrow" style="color: black;">
>>>>>>> origin/main
                SALES RETURNS
            </p>

            <h2>
                Pending Sales Return Approval
            </h2>

        </div>

    </div>

<<<<<<< HEAD
    <div class="approval-card-list">

        <?php foreach ($returns as $r): ?>

            <article class="approval-container-card">

                <div class="approval-card-top">

                    <div>

                        <span class="eyebrow dark">
                            <?= htmlspecialchars($r['return_no']) ?>
                        </span>

                        <h3>
                            <?= htmlspecialchars($r['customer_name']) ?>
                        </h3>

                        <p class="muted">
                            Order <?= htmlspecialchars($r['order_no']) ?>
                        </p>

                    </div>

                    <span class="badge warning">
                        Pending
                    </span>

                </div>

                <div class="approval-detail-grid">

                    <div>
                        <span>Reason</span>
                        <b><?= htmlspecialchars($r['reason'] ?? '—') ?></b>
                    </div>

                    <div>
                        <span>Amount</span>
                        <b><?= peso((float)$r['total_amount']) ?></b>
                    </div>

                </div>

                <div class="approval-card-actions">

                    <?php approvalForm('return', (int)$r['ret_id']); ?>

                </div>

            </article>

        <?php endforeach; ?>


        <?php if (!$returns): ?>

            <div class="note-card">
                <b>No Sales Returns are waiting for approval.</b>
            </div>

        <?php endif; ?>
=======

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
>>>>>>> origin/main

    </div>

</div>


<<<<<<< HEAD
</div>

<!-- SPECIFIC STOCK REQUEST MODAL -->
<div id="specificStockRequestModal" class="stock-request-modal" aria-hidden="true">
    <div class="stock-request-backdrop" onclick="closeSpecificStockRequestModal()"></div>
    <div class="stock-request-dialog" role="dialog" aria-modal="true" aria-labelledby="specificStockRequestTitle">
        <div class="stock-request-dialog-head">
            <div>
                <p class="eyebrow dark" style="margin-bottom:6px;">INVENTORY REQUEST</p>
                <h2 id="specificStockRequestTitle" style="margin:0;">Request Specific Product</h2>
                <p class="muted" style="margin:6px 0 0;">Inventory System will approve or decline this request.</p>
            </div>
            <button type="button" class="stock-request-close" onclick="closeSpecificStockRequestModal()" aria-label="Close">&times;</button>
        </div>

        <form method="POST" class="stock-request-form" id="specificStockRequestForm">
            <input type="hidden" name="type" value="specific_stock_request">
            <input type="hidden" name="request_number" id="specificRequestNumber">

            <label>
                <span>Request Code</span>
                <input type="text" id="specificRequestNumberPreview" readonly>
                <small>Generated automatically for this request.</small>
            </label>

            <div class="stock-request-fields">
                <label>
                    <span>Product</span>
                    <select name="product_code" id="specificProductCode" required>
                        <option value="">Select product</option>
                        <?php
                        $seenProducts = [];
                        foreach ($stockRequestProducts as $p):
                            $key = (string)$p['item_code'];
                            if (isset($seenProducts[$key])) continue;
                            $seenProducts[$key] = true;
                        ?>
                            <option value="<?= htmlspecialchars($p['item_code'], ENT_QUOTES) ?>">
                                <?= htmlspecialchars($p['item_code'] . ' — ' . $p['item_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>

                <label>
                    <span>Size</span>
                    <select name="size" id="specificProductSize" required disabled>
                        <option value="">Select product first</option>
                    </select>
                </label>
            </div>

            <label>
                <span>Quantity</span>
                <input type="number" name="quantity" min="0.01" step="0.01" required placeholder="0">
            </label>

            <label>
                <span>Remarks</span>
                <textarea name="remarks" rows="3" maxlength="500" placeholder="Reason or additional instructions"></textarea>
            </label>

            <div class="stock-request-actions">
                <button type="button" class="secondary-btn" onclick="closeSpecificStockRequestModal()">Cancel</button>
                <button type="submit" class="primary-btn">Send to Inventory</button>
            </div>
        </form>
    </div>
</div>

<!-- STOCK REQUEST MODAL -->
<div id="stockRequestModal" class="stock-request-modal" aria-hidden="true">
    <div class="stock-request-backdrop" onclick="closeStockRequestModal()"></div>
    <div class="stock-request-dialog" role="dialog" aria-modal="true" aria-labelledby="stockRequestTitle">
        <div class="stock-request-dialog-head">
            <div>
                <p class="eyebrow dark" style="margin-bottom:6px;">REQUEST STOCKOUT</p>
                <h2 id="stockRequestTitle" style="margin:0;">Request Stock from Inventory</h2>
                <p class="muted" style="margin:6px 0 0;">Send the missing quantity to the Inventory System for approval and stock-out.</p>
            </div>
            <button type="button" class="stock-request-close" onclick="closeStockRequestModal()" aria-label="Close">&times;</button>
        </div>

        <form method="POST" class="stock-request-form">
            <input type="hidden" name="type" value="request_stockout">
            <input type="hidden" name="quotation_id" id="stockRequestQuotationId">

            <div class="stock-request-product">
                <span>PRODUCT</span>
                <strong id="stockRequestProductName">—</strong>
            </div>

            <div class="stock-request-fields">
                <label>
                    <span>Product Code</span>
                    <input type="text" name="product_code" id="stockRequestProductCode" readonly required>
                </label>

                <label>
                    <span>Quantity to Request</span>
                    <input type="number" name="quantity" id="stockRequestQuantity" min="0.01" step="0.01" required>
                    <small id="stockRequestQuantityHint">Minimum required quantity: —</small>
                </label>
            </div>

            <label>
                <span>Reference / Reason</span>
                <input type="text" name="reference" id="stockRequestReference" maxlength="100" required>
            </label>

            <div class="stock-request-info">
                <span>After submission</span>
                <p>Request will show as <b>Waiting for Inventory</b>. Once Inventory performs the stock-out, it will appear in <b>Inventory Stockout Approval</b> for Team Sales approval.</p>
            </div>

            <div class="stock-request-actions">
                <button type="button" class="secondary-btn" onclick="closeStockRequestModal()">Cancel</button>
                <button type="submit" class="primary-btn">Send Stock Request</button>
            </div>
        </form>
    </div>
</div>

<style>
.stock-request-modal{display:none;position:fixed;inset:0;z-index:9999;align-items:center;justify-content:center;padding:24px;}
.stock-request-modal.is-open{display:flex;}
.stock-request-backdrop{position:absolute;inset:0;background:rgba(15,23,42,.48);backdrop-filter:blur(3px);}
.stock-request-dialog{position:relative;width:min(620px,100%);background:#fff;border:1px solid #eadfdf;border-radius:20px;box-shadow:0 24px 70px rgba(0,0,0,.20);overflow:hidden;}
.stock-request-dialog-head{display:flex;justify-content:space-between;gap:20px;padding:24px 26px 18px;border-bottom:1px solid #eee5e5;}
.stock-request-close{border:0;background:#f5f1f1;color:#333;width:36px;height:36px;border-radius:50%;font-size:25px;line-height:1;cursor:pointer;flex:0 0 auto;}
.stock-request-close:hover{background:#ece4e4;}
.stock-request-form{padding:22px 26px 26px;display:grid;gap:16px;}
.stock-request-form label{display:grid;gap:7px;font-weight:600;color:#242424;}
.stock-request-form label>span{font-size:13px;letter-spacing:.02em;}
.stock-request-form input{width:100%;box-sizing:border-box;border:1px solid #ddd4d4;border-radius:11px;padding:12px 13px;font:inherit;background:#fff;outline:none;}
.stock-request-form input:focus{border-color:#b70000;box-shadow:0 0 0 3px rgba(183,0,0,.08);}
.stock-request-form input[readonly]{background:#f7f4f4;color:#555;}
.stock-request-fields{display:grid;grid-template-columns:1fr 1fr;gap:14px;}
.stock-request-form small{font-size:12px;color:#777;}
.stock-request-product{padding:15px 16px;border:1px solid #eee0e0;border-radius:13px;background:#faf7f7;display:grid;gap:4px;}
.stock-request-product span,.stock-request-info>span{font-size:11px;font-weight:800;letter-spacing:.08em;color:#8a7777;}
.stock-request-product strong{font-size:17px;}
.stock-request-info{padding:13px 15px;border-radius:12px;background:#f7f8fa;border:1px solid #e7e9ed;}
.stock-request-info p{margin:5px 0 0;color:#666;font-size:13px;line-height:1.5;}
.stock-request-actions{display:flex;justify-content:flex-end;gap:10px;padding-top:4px;}
@media(max-width:650px){.stock-request-fields{grid-template-columns:1fr;}.stock-request-dialog-head,.stock-request-form{padding-left:18px;padding-right:18px;}}
</style>

<script>
function openStockRequestModal(button){
    const modal=document.getElementById('stockRequestModal');
    if(!modal)return;
    document.getElementById('stockRequestQuotationId').value=button.dataset.quotationId||'';
    document.getElementById('stockRequestProductCode').value=button.dataset.productCode||'';
    document.getElementById('stockRequestProductName').textContent=button.dataset.productName||button.dataset.productCode||'—';
    document.getElementById('stockRequestQuantity').value=button.dataset.quantity||'';
    document.getElementById('stockRequestQuantity').min=button.dataset.quantity||'0.01';
    document.getElementById('stockRequestQuantityHint').textContent='Minimum required quantity: '+(button.dataset.quantity||'0');
    document.getElementById('stockRequestReference').value=button.dataset.reference||'';
    modal.classList.add('is-open');
    modal.setAttribute('aria-hidden','false');
    document.getElementById('stockRequestQuantity').focus();
}
function closeStockRequestModal(){
    const modal=document.getElementById('stockRequestModal');
    if(!modal)return;
    modal.classList.remove('is-open');
    modal.setAttribute('aria-hidden','true');
}
document.addEventListener('keydown',function(e){if(e.key==='Escape')closeStockRequestModal();});
</script>


<style>
.approval-tabs{display:flex;gap:8px;margin-top:18px;border-bottom:1px solid #e7dfdf;padding-bottom:0;}
.approval-tab{display:inline-flex;align-items:center;padding:12px 18px;border-radius:12px 12px 0 0;text-decoration:none;color:#6d5f5f;font-weight:700;background:#f6f2f2;}
.approval-tab.active{background:#111;color:#fff;}
.specific-stock-request-modal textarea{width:100%;box-sizing:border-box;border:1px solid #ddd4d4;border-radius:11px;padding:12px 13px;font:inherit;resize:vertical;outline:none;}
.approval-confirm-modal{display:none;position:fixed;inset:0;z-index:10050;align-items:center;justify-content:center;padding:20px;}
.approval-confirm-modal.is-open{display:flex;}
.approval-confirm-backdrop{position:absolute;inset:0;background:rgba(15,23,42,.52);backdrop-filter:blur(3px);}
.approval-confirm-dialog{position:relative;width:min(430px,100%);background:#fff;border-radius:18px;padding:25px;box-shadow:0 24px 70px rgba(0,0,0,.22);}
.approval-confirm-dialog h3{margin:0 0 8px;}
.approval-confirm-dialog p{color:#666;line-height:1.5;margin:0 0 20px;}
.approval-confirm-actions{display:flex;justify-content:flex-end;gap:10px;}
</style>

<div id="approvalConfirmModal" class="approval-confirm-modal" aria-hidden="true">
    <div class="approval-confirm-backdrop" onclick="closeApprovalConfirm()"></div>
    <div class="approval-confirm-dialog" role="dialog" aria-modal="true">
        <h3>Confirm Approval</h3>
        <p id="approvalConfirmText">Are you sure you want to approve this record?</p>
        <div class="approval-confirm-actions">
            <button type="button" class="secondary-btn" onclick="closeApprovalConfirm()">Cancel</button>
            <button type="button" class="primary-btn" id="approvalConfirmButton">Yes, Approve</button>
        </div>
    </div>
</div>

<script>
(function(){
  let pendingApprovalForm=null;

  window.openApprovalConfirm=function(form){
    pendingApprovalForm=form;
    const modal=document.getElementById('approvalConfirmModal');
    modal.classList.add('is-open');
    modal.setAttribute('aria-hidden','false');
  };

  window.closeApprovalConfirm=function(){
    pendingApprovalForm=null;
    const modal=document.getElementById('approvalConfirmModal');
    modal.classList.remove('is-open');
    modal.setAttribute('aria-hidden','true');
  };

  document.addEventListener('DOMContentLoaded',function(){
    document.querySelectorAll('.approval-form').forEach(function(form){
      form.addEventListener('submit',function(e){
        if(e.submitter && e.submitter.value==='Approved'){
          if(form.dataset.confirmed==='1'){
            form.dataset.confirmed='0';
            return;
          }
          e.preventDefault();
          openApprovalConfirm(form);
        }
      });
    });

    document.getElementById('approvalConfirmButton').addEventListener('click',function(){
      if(!pendingApprovalForm)return;
      pendingApprovalForm.dataset.confirmed='1';
      pendingApprovalForm.requestSubmit(pendingApprovalForm.querySelector('button[value="Approved"]'));
      closeApprovalConfirm();
    });

    const productSelect=document.getElementById('specificProductCode');
    const sizeSelect=document.getElementById('specificProductSize');
    const productSizes=<?= json_encode(array_values($stockRequestProducts), JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_AMP|JSON_HEX_QUOT) ?>;

    function refreshSizes(){
      const code=productSelect.value;
      sizeSelect.innerHTML='<option value="">Select size</option>';
      const matches=productSizes.filter(function(p){return String(p.item_code)===String(code);});
      matches.forEach(function(p){
        const opt=document.createElement('option');
        opt.value=p.size || '—';
        opt.textContent=(p.size || '—') + (p.unit ? ' · '+p.unit : '');
        sizeSelect.appendChild(opt);
      });
      sizeSelect.disabled=matches.length===0;
    }
    function refreshRequestCode(){
      const code='AUTO-GENERATED';
      document.getElementById('specificRequestNumber').value='';
      document.getElementById('specificRequestNumberPreview').value=code;
    }
    if(productSelect) productSelect.addEventListener('change',function(){
      refreshSizes();
      refreshRequestCode();
    });

    window.openSpecificStockRequestModal=function(){
      const modal=document.getElementById('specificStockRequestModal');
      document.getElementById('specificStockRequestForm').reset();
      refreshSizes();
      refreshRequestCode();
      modal.classList.add('is-open');
      modal.setAttribute('aria-hidden','false');
    };
    window.closeSpecificStockRequestModal=function(){
      const modal=document.getElementById('specificStockRequestModal');
      modal.classList.remove('is-open');
      modal.setAttribute('aria-hidden','true');
    };
  });
})();
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
=======
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
>>>>>>> origin/main
