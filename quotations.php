<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
<<<<<<< HEAD

requireSalesWorkspace();

=======
<<<<<<< HEAD
requireSalesWorkspace();
=======

requireSalesWorkspace();

>>>>>>> origin/main
>>>>>>> origin/main
$pdo = getDB();

$error = '';
$notice = '';

<<<<<<< HEAD
=======
<<<<<<< HEAD
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'create';

    if ($action === 'recheck') {
        $quotationId = (int) ($_POST['quotation_id'] ?? 0);
        $stmt = $pdo->prepare('SELECT * FROM sales_quotations WHERE id = ?');
        $stmt->execute([$quotationId]);
        $q = $stmt->fetch();

        if (!$q || !$q['product_code'] || !$q['quantity']) {
            $error = 'This quotation does not have enough product information to check Inventory.';
        } else {
            $stock = checkInventoryProduct($q['product_code']);
            if (!$stock['ok']) {
                $error = $stock['error'] ?? 'Inventory check failed.';
            } elseif ((int) ($stock['quantity'] ?? 0) >= (int) $q['quantity']) {
                $pdo->prepare("UPDATE sales_quotations SET status='Approved', inventory_product_id=?, inventory_quantity=?, inventory_checked_at=NOW() WHERE id=?")
                    ->execute([$stock['product_id'] ?? null, (int) $stock['quantity'], $quotationId]);
                logActivity($pdo, 'Recheck Stock', 'Quotation', 'Inventory approved quotation stock recheck', $quotationId);
                $notice = 'Inventory has approved the quotation. It can now become a Purchase Order.';
            } else {
                $pdo->prepare("UPDATE sales_quotations SET status='Rejected', inventory_product_id=?, inventory_quantity=?, inventory_checked_at=NOW() WHERE id=?")
                    ->execute([$stock['product_id'] ?? null, (int) ($stock['quantity'] ?? 0), $quotationId]);
                logActivity($pdo, 'Recheck Stock', 'Quotation', 'Inventory rejected quotation stock recheck', $quotationId);
                $notice = 'Inventory still does not have enough stock. The quotation remains rejected/awaiting stock.';
            }
        }
    } else {
        $customerId = (int) ($_POST['customer_id'] ?? 0);
        $date = $_POST['quotation_date'] ?? '';
        $validUntil = $_POST['valid_until'] ?? null;
        $productCode = trim($_POST['product_code'] ?? '');
        $quantity = (int) ($_POST['quantity'] ?? 0);
        $total = (float) ($_POST['total_amount'] ?? 0);

        if (!$customerId || !$date || !$productCode || $quantity <= 0 || $total <= 0) {
            $error = 'Customer, date, product code, quantity, and a total amount greater than 0 are required.';
        } else {
$stmt = $pdo->prepare("
        SELECT product_id, product_code, product_name, quantity, unit_price, unit, status
        FROM inventory_products
        WHERE product_code = ?
        LIMIT 1
    ");

    $stmt->execute([$productCode]);
    $stock = $stmt->fetch();

    if (!$stock) {
        $error = 'Product code was not found in Inventory Reference.';
    } else {
        $available = (int) $stock['quantity'];

        if (strtolower($stock['status']) === 'out of stock' || $available <= 0) {
            $status = 'Rejected';
        } elseif ($available < $quantity) {
            $status = 'Rejected';
        } else {
            $status = 'Approved';
        }

        $quotationNo = nextDocNo(
            $pdo,
            'sales_quotations',
            'QT-',
            2000
        );

        $stmt = $pdo->prepare('
            INSERT INTO sales_quotations
            (
                quotation_no,
                customer_id,
                quotation_date,
                valid_until,
                product_code,
                inventory_product_id,
                quantity,
                inventory_quantity,
                inventory_checked_at,
                total_amount,
                status
            )
            VALUES (?,?,?,?,?,?,?,?,NOW(),?,?)
        ');

        $stmt->execute([
            $quotationNo,
            $customerId,
            $date,
            $validUntil ?: null,
            $productCode,
            $stock['product_id'],
            $quantity,
            $available,
            $total,
            $status
        ]);

        logActivity(
            $pdo,
            'Create',
            'Quotation',
            'Created quotation ' . $quotationNo,
            (int)$pdo->lastInsertId()
        );

        header(
            'Location: quotations.php?section=core&added=1&status='
            . strtolower($status)
        );
        exit;
=======
>>>>>>> origin/main
/*
|--------------------------------------------------------------------------
| POST ACTIONS
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $action = $_POST['action'] ?? '';

    /*
    |--------------------------------------------------------------------------
    | CREATE QUOTATION FROM CUSTOMER ORDER REQUEST
    |--------------------------------------------------------------------------
    */
    if ($action === 'create_from_request') {

        $requestId  = (int)($_POST['request_id'] ?? 0);
        $date       = $_POST['quotation_date'] ?? date('Y-m-d');
        $validUntil = $_POST['valid_until'] ?? null;
        $unitPrice  = filter_var($_POST['unit_price'] ?? null, FILTER_VALIDATE_FLOAT);

        /*
        |--------------------------------------------------------------------------
        | Get customer order request
        |--------------------------------------------------------------------------
        */
        $st = $pdo->prepare("
            SELECT 
                r.*,
                c.customer_name
            FROM customer_order_requests r
            JOIN customers c 
                ON c.cus_id = r.customer_id
            WHERE r.req_id = ?
              AND r.status = 'Pending Quotation'
            LIMIT 1
        ");

        $st->execute([$requestId]);
        $request = $st->fetch();

        if (!$request) {

            $error = 'Customer order request not found or it already has a quotation.';

        } elseif ($unitPrice === false || $unitPrice <= 0) {

            $error = 'Enter a valid unit price greater than zero to create the quotation.';

        } else {

            /*
            |--------------------------------------------------------------------------
            | Check inventory using the request's product_code
            |--------------------------------------------------------------------------
            */
            $check = evaluateQuotationStock(
                $request['product_code'],
                (int)$request['quantity']
            );

            if (!$check['ok']) {

                $error = $check['error'];

            } else {

                $stock = $check['stock'];

<<<<<<< HEAD
                // Inventory supplies the product/master data; Team Sales stock determines whether the quotation can be approved.
=======
                // Inventory supplies availability; Sales supplies the quoted price.
>>>>>>> origin/main
                $total = round($unitPrice * (int)$request['quantity'], 2);

                if ($total <= 0 || !is_finite($total)) {

                    $error = 'Could not compute the quotation total. Check the unit price and quantity.';

                } else {

                    try {

                        $pdo->beginTransaction();

                        /*
                        |--------------------------------------------------------------------------
                        | Generate quotation number
                        |--------------------------------------------------------------------------
                        */
                        $quotationNo = nextDocNo(
                            $pdo,
                            'sales_quotations',
                            'QT-',
                            2000
                        );

                        /*
                        |--------------------------------------------------------------------------
                        | Insert quotation
                        |--------------------------------------------------------------------------
                        |
<<<<<<< HEAD
                        | fg_id = inventory_system.finished_goods.fg_id
=======
                        | fg_id = inventory_management.finished_goods.id
>>>>>>> origin/main
                        |
                        */
                        $ins = $pdo->prepare("
                            INSERT INTO sales_quotations
                            (
                                quotation_no,
                                customer_id,
                                source_request_id,
                                quotation_date,
                                valid_until,
<<<<<<< HEAD
                                product_code,
=======
                                finished_good_code,
>>>>>>> origin/main
                                fg_id,
                                quantity,
                                inventory_quantity,
                                inventory_checked_at,
                                inventory_status,
                                total_amount,
                                status,
                                admin_status,
                                customer_status
                            )
                            VALUES
                            (
                                ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?,
                                ?, 'Pending', 'Pending', 'Pending'
                            )
                        ");

                        $ins->execute([
                            $quotationNo,
                            $request['customer_id'],
                            $requestId,
                            $date,
                            $validUntil ?: null,

                            // Sales quotation product code
                            $request['product_code'],

                            // Inventory finished_goods.id
<<<<<<< HEAD
                            (int)($stock['fg_id'] ?? 0),
=======
                            (int)($stock['id'] ?? 0),
>>>>>>> origin/main

                            (int)$request['quantity'],
                            (int)$check['available'],

                            $check['status'],
                            $total
                        ]);

                        /*
                        |--------------------------------------------------------------------------
                        | Get newly created quotation ID
                        |--------------------------------------------------------------------------
                        */
                        $qid = (int)$pdo->lastInsertId();

                        /*
                        |--------------------------------------------------------------------------
                        | Update customer order request
                        |--------------------------------------------------------------------------
                        */
                        $updateRequest = $pdo->prepare("
                            UPDATE customer_order_requests
                            SET 
                                status = 'Quotation Sent',
                                quotation_id = ?
                            WHERE req_id = ?
                        ");

                        $updateRequest->execute([
                            $qid,
                            $requestId
                        ]);

                        $pdo->commit();

                        /*
                        |--------------------------------------------------------------------------
                        | Activity log
                        |--------------------------------------------------------------------------
                        */
                        logActivity(
                            $pdo,
                            'Create',
                            'Quotation',
                            'Created ' . $quotationNo .
                            ' for customer order ' .
                            $request['request_no'],
                            $qid
                        );

                        header(
                            'Location: quotations.php?section=core&added=1'
                        );

                        exit;

                    } catch (Throwable $e) {

                        if ($pdo->inTransaction()) {
                            $pdo->rollBack();
                        }

                        $error = 'The quotation could not be saved: ' .
                            $e->getMessage();
                    }
                }
            }
        }
    }


    /*
    |--------------------------------------------------------------------------
    | RECHECK INVENTORY
    |--------------------------------------------------------------------------
    */
    if ($action === 'recheck') {

        $quotationId = (int)($_POST['quotation_id'] ?? 0);

        $stmt = $pdo->prepare("
            SELECT *
            FROM sales_quotations
            WHERE sq_id = ?
            LIMIT 1
        ");

        $stmt->execute([$quotationId]);

        $q = $stmt->fetch();

        if (
            !$q ||
<<<<<<< HEAD
            empty($q['product_code']) ||
=======
            empty($q['finished_good_code']) ||
>>>>>>> origin/main
            empty($q['quantity'])
        ) {

            $error = 'Quotation information is incomplete.';

        } else {

            $check = evaluateQuotationStock(
<<<<<<< HEAD
                $q['product_code'],
=======
                $q['finished_good_code'],
>>>>>>> origin/main
                (int)$q['quantity']
            );

            if (!$check['ok']) {

                $error = $check['error'];

            } else {

                $update = $pdo->prepare("
                    UPDATE sales_quotations
                    SET
                        inventory_status = ?,
                        inventory_quantity = ?,
                        fg_id = ?,
                        inventory_checked_at = NOW()
                    WHERE sq_id = ?
                ");

                $update->execute([
                    $check['status'],
                    $check['available'],
<<<<<<< HEAD
                    $check['stock']['fg_id'] ?? null,
=======
                    $check['stock']['id'] ?? null,
>>>>>>> origin/main
                    $quotationId
                ]);

                $notice =
                    'Inventory status updated to ' .
                    $check['status'] .
                    '.';
<<<<<<< HEAD
=======
>>>>>>> origin/main
>>>>>>> origin/main
            }
        }
    }
}

<<<<<<< HEAD
=======
<<<<<<< HEAD
$pageTitle = 'Sales Quotations';
include __DIR__ . '/includes/header.php';

$search = trim($_GET['search'] ?? '');
$sql = 'SELECT q.*, c.customer_name AS customer_name FROM sales_quotations q JOIN customers c ON c.id = q.customer_id';
$params = [];
if ($search !== '') {
    $sql .= ' WHERE q.quotation_no LIKE ? OR c.customer_name LIKE ? OR q.product_code LIKE ? OR q.status LIKE ?';
    $like = '%' . $search . '%';
    $params = [$like, $like, $like, $like];
}
$sql .= ' ORDER BY q.id DESC';
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();  
$customers = $pdo->query('SELECT id, customer_name FROM customers WHERE status = "Active" ORDER BY customer_name')->fetchAll();
$inventoryProducts = $pdo->query("
    SELECT product_id, product_code, product_name, quantity, unit_price, unit, status
    FROM inventory_products
    ORDER BY product_name
")->fetchAll();
?>
<div class="page-toolbar">
    <p class="muted" style="color: white;">Sales prepares the quotation; Inventory checks the requested product and quantity before approval.</p>
    <button class="primary-btn" data-open-modal="addQuotationModal">+ New Quotation</button>
</div>

<?php if (!empty($_GET['added'])): ?><div class="alert success">Quotation created and sent to Inventory. Status: <?= htmlspecialchars(ucfirst($_GET['status'] ?? 'pending')) ?>.</div><?php endif; ?>
<?php if ($notice): ?><div class="alert success"><?= htmlspecialchars($notice) ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert error"><?= htmlspecialchars($error) ?></div><?php endif; ?>

<div class="module-search"><form method="GET"><input type="hidden" name="section" value="core"><input type="search" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Search quotations, customers, product, or status..."><button class="secondary-btn" type="submit">Search</button><?php if ($search !== ''): ?><a class="text-link" href="quotations.php?section=core">Clear</a><?php endif; ?></form></div>

<div class="panel"><div class="table-wrap"><table><thead><tr>
    <th>Quotation</th><th>Customer</th><th>Product</th><th>Qty</th><th>Inventory</th><th>Total</th><th>Status</th><th>Action</th>
</tr></thead><tbody>
<?php if (!$rows): ?><tr class="empty-row"><td colspan="8">No quotations found.</td></tr><?php endif; ?>
<?php foreach ($rows as $r): ?>
<tr>
    <td><b><?= htmlspecialchars($r['quotation_no']) ?></b><br><small><?= niceDate($r['quotation_date']) ?></small></td>
    <td><?= htmlspecialchars($r['customer_name']) ?></td>
    <td><?= htmlspecialchars($r['product_code'] ?? '—') ?></td>
    <td><?= $r['quantity'] !== null ? (int)$r['quantity'] : '—' ?></td>
    <td><?= $r['inventory_quantity'] !== null ? (int)$r['inventory_quantity'] . ' available' : 'Not checked' ?></td>
    <td><?= peso((float)$r['total_amount']) ?></td>
    <td><span class="badge <?= badgeClass($r['status']) ?>"><?= htmlspecialchars($r['status']) ?></span><?php if (!empty($r['inventory_checked_at'])): ?><small class="table-subtext">Checked <?= niceDate($r['inventory_checked_at']) ?></small><?php endif; ?></td>
    <td>
        <?php if ($r['status'] === 'Approved'): ?>
            <a class="table-action" href="purchase_orders.php?quotation_id=<?= (int)$r['id'] ?>">Create PO</a>
        <?php endif; ?>
        <?php if (!empty($r['product_code'])): ?>
            <form method="POST" style="display:inline">
                <input type="hidden" name="action" value="recheck">
                <input type="hidden" name="quotation_id" value="<?= (int)$r['id'] ?>">
                <button class="table-action" type="submit">Recheck Stock</button>
            </form>
        <?php endif; ?>
    </td>
</tr>
<?php endforeach; ?>
</tbody></table></div></div>

<div class="modal-backdrop" id="addQuotationModal" aria-hidden="true">
    <div class="profile-modal entry-modal" role="dialog" aria-modal="true" aria-labelledby="addQuotationTitle">
        <button class="modal-close" type="button" aria-label="Close" data-close-modal>×</button>
        <p class="eyebrow">CORE SALES</p>
        <h2 id="addQuotationTitle">New Quotation</h2>
        <p class="modal-subtitle">Inventory will be checked automatically when you save.</p>
        <form method="POST" class="entry-form">
            <label>Customer
                <select name="customer_id" required><option value="">Select customer</option>
                    <?php foreach ($customers as $c): ?><option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['customer_name']) ?></option><?php endforeach; ?>
                </select>
            </label>
            <label>Quotation Date <input type="date" name="quotation_date" value="<?= date('Y-m-d') ?>" required></label>
            <label>Valid Until <input type="date" name="valid_until"></label>
            <label>Product
    <select name="product_code" id="quotationProduct" required>
        <option value="">Select product</option>

        <?php foreach ($inventoryProducts as $product): ?>

            <option
                value="<?= htmlspecialchars($product['product_code']) ?>"
                data-price="<?= htmlspecialchars($product['unit_price']) ?>"
                data-stock="<?= (int)$product['quantity'] ?>"
                data-unit="<?= htmlspecialchars($product['unit']) ?>"
                <?= strtolower($product['status']) === 'out of stock' ? 'disabled' : '' ?>
            >
                <?= htmlspecialchars($product['product_code']) ?>
                -
                <?= htmlspecialchars($product['product_name']) ?>
                (<?= (int)$product['quantity'] ?> available)
            </option>

        <?php endforeach; ?>

    </select>
</label>
<div id="inventoryInfo" style="margin-top: 8px; font-size: 13px; color: #666;">
    Select a product to view its available stock and price.
</div>
<label>Quantity
    <input type="number" name="quantity" id="quotationQuantity" min="1" required>
</label>

<label>Total Amount (₱)
    <input type="number" step="0.01" min="0.01" name="total_amount" id="quotationTotal" required>
</label>
            <div class="modal-actions">
                <button type="button" class="secondary-btn" data-close-modal>Cancel</button>
                <button type="submit" class="primary-btn">Check Inventory & Save</button>
            </div>
        </form>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
<script>
document.addEventListener('DOMContentLoaded', function () {

    const productSelect = document.getElementById('quotationProduct');
    const quantityInput = document.getElementById('quotationQuantity');
    const totalInput = document.getElementById('quotationTotal');
    const inventoryInfo = document.getElementById('inventoryInfo');

    function updateInventoryInfo() {

        const selected = productSelect.options[productSelect.selectedIndex];

        if (!selected || !selected.value) {
            inventoryInfo.textContent =
                'Select a product to view its available stock and price.';
            return;
        }

        const price = parseFloat(selected.dataset.price || 0);
        const stock = parseInt(selected.dataset.stock || 0);
        const unit = selected.dataset.unit || 'unit';

        inventoryInfo.textContent =
            'Available Stock: ' + stock +
            ' ' + unit +
            ' | Unit Price: ₱' + price.toFixed(2);

        calculateTotal();
    }

    function calculateTotal() {

        const selected = productSelect.options[productSelect.selectedIndex];

        if (!selected || !selected.value) {
            return;
        }

        const price = parseFloat(selected.dataset.price || 0);
        const quantity = parseInt(quantityInput.value || 0);

        if (quantity > 0) {
            totalInput.value = (price * quantity).toFixed(2);
        }
    }

    productSelect.addEventListener('change', updateInventoryInfo);
    quantityInput.addEventListener('input', calculateTotal);

});
</script>
=======
>>>>>>> origin/main

/*
|--------------------------------------------------------------------------
| PAGE
|--------------------------------------------------------------------------
*/

$pageTitle = 'Sales Quotations';

include __DIR__ . '/includes/header.php';


/*
|--------------------------------------------------------------------------
| SEARCH
|--------------------------------------------------------------------------
*/

$search = trim($_GET['search'] ?? '');


/*
|--------------------------------------------------------------------------
| GET EXISTING QUOTATIONS
|--------------------------------------------------------------------------
*/

$sql = "
    SELECT
        q.*,
        c.customer_name,
        r.request_no,

        (
            SELECT po.po_no
            FROM purchase_orders po
            WHERE po.quotation_id = q.sq_id
            LIMIT 1
        ) AS po_no

    FROM sales_quotations q

    LEFT JOIN customers c
        ON c.cus_id = q.customer_id

    LEFT JOIN customer_order_requests r
        ON r.req_id = q.source_request_id
";

$params = [];

if ($search !== '') {

    $sql .= "
        WHERE
            q.quotation_no LIKE ?
            OR c.customer_name LIKE ?
<<<<<<< HEAD
            OR q.product_code LIKE ?
=======
            OR q.finished_good_code LIKE ?
>>>>>>> origin/main
            OR q.customer_status LIKE ?
    ";

    $like = '%' . $search . '%';

    $params = [
        $like,
        $like,
        $like,
        $like
    ];
}

$sql .= "
    ORDER BY q.sq_id DESC
";

$st = $pdo->prepare($sql);
$st->execute($params);

$rows = $st->fetchAll();


/*
|--------------------------------------------------------------------------
| CUSTOMER ORDER REQUESTS WAITING FOR QUOTATION
|--------------------------------------------------------------------------
|
| IMPORTANT:
| customer_order_requests uses product_code
|
| Inventory database:
<<<<<<< HEAD
| inventory_system.finished_goods
|
| Inventory column:
| item_code
=======
| inventory_management.finished_goods
|
| Inventory column:
| finished_good_code
>>>>>>> origin/main
|
|--------------------------------------------------------------------------
*/

$requests = $pdo->query("
    SELECT
        r.*,
        c.customer_name,

        (
            SELECT item_name
            FROM inventory_system.finished_goods ip
            WHERE
                ip.item_code COLLATE utf8mb4_unicode_ci
                =
                r.product_code COLLATE utf8mb4_unicode_ci
            LIMIT 1
        ) AS product_name

    FROM customer_order_requests r

    JOIN customers c
        ON c.cus_id = r.customer_id

    WHERE r.status = 'Pending Quotation'

    ORDER BY r.req_id DESC
")->fetchAll();

?>

<div class="page-toolbar">

    <p class="muted" style="color:white;">
        Customer orders appear here first. Sales reviews who ordered,
        creates the quotation, and sends the PDF to the customer for approval.
    </p>

</div>


<?php if (!empty($_GET['added'])): ?>

    <div class="alert success">
        Quotation created and sent to Sales Admin for approval.
    </div>

<?php endif; ?>


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


<!--
|--------------------------------------------------------------------------
| CUSTOMER ORDER REQUESTS
|--------------------------------------------------------------------------
-->

<div class="panel">

    <div class="panel-head">

        <div>

            <p class="eyebrow dark">
                CUSTOMER ORDER REQUESTS
            </p>

            <h2>
                Orders Waiting for Quotation
            </h2>

        </div>

    </div>


    <div class="table-wrap">

        <table>

            <thead>

                <tr>

                    <th>Request</th>
                    <th>Customer</th>
                    <th>Product</th>
                    <th>Qty</th>
                    <th>Date</th>
                    <th>Action</th>

                </tr>

            </thead>


            <tbody>

            <?php foreach ($requests as $r): ?>

                <tr>

                    <td>
                        <b>
                            <?= htmlspecialchars($r['request_no']) ?>
                        </b>
                    </td>


                    <td>
                        <?= htmlspecialchars($r['customer_name']) ?>
                    </td>


                    <td>

                        <?= htmlspecialchars(
                            $r['product_name']
                            ?: $r['product_code']
                        ) ?>

                        <br>

                        <small>
                            <?= htmlspecialchars($r['product_code']) ?>
                        </small>

                    </td>


                    <td>
                        <?= (int)$r['quantity'] ?>
                    </td>


                    <td>
                        <?= niceDate($r['request_date']) ?>
                    </td>


                    <td>

                        <button
                            class="table-action"
                            data-open-modal="createQuotation<?= (int)$r['req_id'] ?>"
                        >
                            Create Quotation
                        </button>

                    </td>

                </tr>


                <!--
                |--------------------------------------------------------------------------
                | CREATE QUOTATION MODAL
                |--------------------------------------------------------------------------
                -->

                <div
                    class="modal-backdrop"
                    id="createQuotation<?= (int)$r['req_id'] ?>"
                    aria-hidden="true"
                >

                    <div
<<<<<<< HEAD
                        class="profile-modal entry-modal quotation-modal"
=======
                        class="profile-modal entry-modal"
>>>>>>> origin/main
                        role="dialog"
                    >

                        <button
                            class="modal-close"
                            type="button"
                            data-close-modal
                        >
                            ×
                        </button>


                        <p class="eyebrow">
                            SALES QUOTATION
                        </p>


                        <h2>
                            Create for
                            <?= htmlspecialchars($r['customer_name']) ?>
                        </h2>


                        <p class="modal-subtitle">

                            <?= htmlspecialchars($r['product_code']) ?>

                            ×

                            <?= (int)$r['quantity'] ?>

                            · Customer Request

                            <?= htmlspecialchars($r['request_no']) ?>

                        </p>


                        <form
                            method="POST"
                            class="entry-form"
                        >

                            <input
                                type="hidden"
                                name="action"
                                value="create_from_request"
                            >


                            <input
                                type="hidden"
                                name="request_id"
                                value="<?= (int)$r['req_id'] ?>"
                            >


<<<<<<< HEAD
                           <div class="quotation-fields">

    <!-- Quotation Date -->
    <div class="quotation-field">
        <label for="quotation_date">
            Quotation Date
        </label>

        <input
            type="date"
            id="quotation_date"
            name="quotation_date"
            value="<?= date('Y-m-d') ?>"
            readonly
            required
        >
    </div>


    <!-- Unit Price -->
    <div class="quotation-field">
        <label for="unit_price">
            Unit Price (₱)
        </label>

        <div class="price-input">
            <span>₱</span>

            <input
                type="number"
                id="unit_price"
                name="unit_price"
                min="0.01"
                step="0.01"
                placeholder="Enter selling price"
                required
            >
        </div>
    </div>


    <!-- Valid Until -->
    <div class="quotation-field">
        <label for="valid_until">
            Valid Until
        </label>

        <input
            type="date"
            id="valid_until"
            name="valid_until"
            required
        >
    </div>
=======
                            <label>

                                Quotation Date

                                <input
                                    type="date"
                                    name="quotation_date"
                                    value="<?= date('Y-m-d') ?>"
                                    required
                                >

                            </label>


                            <label>

                                Unit Price (₱)

                                <input
                                    type="number"
                                    name="unit_price"
                                    min="0.01"
                                    step="0.01"
                                    placeholder="Enter agreed selling price"
                                    required
                                >

                            </label>


                            <label>

                                Valid Until

                                <input
                                    type="date"
                                    name="valid_until"
                                >

                            </label>


                            <div class="note-card">

                                <b>
                                    Sales Admin Approval
                                </b>

                                <span>
                                    After saving, the quotation must be
                                    approved by Sales Admin before the
                                    customer can approve it.
                                </span>

                            </div>

>>>>>>> origin/main

                            <div class="modal-actions">

                                <button
                                    type="button"
                                    class="secondary-btn"
                                    data-close-modal
                                >
                                    Cancel
                                </button>


                                <button
                                    type="submit"
                                    class="primary-btn"
                                >
                                    Create & Send Quotation
                                </button>

                            </div>

                        </form>

                    </div>

                </div>

            <?php endforeach; ?>


            <?php if (!$requests): ?>

                <tr>

                    <td colspan="6">
                        No customer orders are waiting for quotation.
                    </td>

                </tr>

            <?php endif; ?>

            </tbody>

        </table>

    </div>

</div>


<!--
|--------------------------------------------------------------------------
| EXISTING QUOTATIONS
|--------------------------------------------------------------------------
-->

<div
    class="panel"
    style="margin-top:18px"
>

    <div class="panel-head">

        <div>

            <p class="eyebrow dark">
                QUOTATIONS
            </p>

            <h2>
                Customer Approval Status
            </h2>

        </div>

    </div>


    <!-- SEARCH -->

    <div class="module-search">

        <form method="GET">

            <input
                type="hidden"
                name="section"
                value="core"
            >


            <input
                type="search"
                name="search"
                value="<?= htmlspecialchars($search) ?>"
                placeholder="Search quotation, customer, product, or status..."
            >


            <button
                class="secondary-btn"
                type="submit"
            >
                Search
            </button>

        </form>

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
                    <th>Inventory</th>
                    <th>Customer Approval</th>
                    <th>Document</th>

                </tr>

            </thead>


            <tbody>

            <?php foreach ($rows as $r): ?>

                <tr>

                    <td>

                        <b>
                            <?= htmlspecialchars($r['quotation_no']) ?>
                        </b>

                        <br>

                        <small>
                            <?= niceDate($r['quotation_date']) ?>
                        </small>

                    </td>


                    <td>
                        <?= htmlspecialchars($r['customer_name']) ?>
                    </td>


                    <td>
                        <?= htmlspecialchars(
<<<<<<< HEAD
                            $r['product_code'] ?? '—'
=======
                            $r['finished_good_code'] ?? '—'
>>>>>>> origin/main
                        ) ?>
                    </td>


                    <td>
                        <?= (int)$r['quantity'] ?>
                    </td>


                    <td>
                        <?= peso((float)$r['total_amount']) ?>
                    </td>


                    <td>

                        <span
                            class="badge <?= badgeClass($r['inventory_status']) ?>"
                        >
                            <?= htmlspecialchars($r['inventory_status']) ?>
                        </span>

                    </td>


                    <td>

                        <span
                            class="badge <?= badgeClass($r['customer_status']) ?>"
                        >
                            <?= htmlspecialchars($r['customer_status']) ?>
                        </span>


                        <?php if ($r['po_no']): ?>

                            <br>

                            <small>
                                PO:
                                <?= htmlspecialchars($r['po_no']) ?>
                            </small>

                        <?php endif; ?>

                    </td>


                    <td>

                        <a
                            class="table-action"
                            href="quotation_export.php?id=<?= (int)$r['sq_id'] ?>&format=pdf"
                            target="_blank"
                        >
                            PDF
                        </a>


                        <?php if ($r['inventory_status'] !== 'Approved'): ?>

                            <form
                                method="POST"
                                style="display:inline"
                            >

                                <input
                                    type="hidden"
                                    name="action"
                                    value="recheck"
                                >


                                <input
                                    type="hidden"
                                    name="quotation_id"
                                    value="<?= (int)$r['sq_id'] ?>"
                                >


                                <button
                                    class="table-action"
                                    type="submit"
                                >
                                    Recheck Stock
                                </button>

                            </form>

                        <?php endif; ?>

                    </td>

                </tr>

            <?php endforeach; ?>


            <?php if (!$rows): ?>

                <tr>

                    <td colspan="8">
                        No quotations found.
                    </td>

                </tr>

            <?php endif; ?>

            </tbody>

        </table>

    </div>

</div>


<<<<<<< HEAD
<script>
document.addEventListener('DOMContentLoaded', function () {
    function openQuotationModal(id) {
        var modal = document.getElementById(id);
        if (!modal) return;
        modal.classList.add('open');
        modal.setAttribute('aria-hidden', 'false');
        document.body.classList.add('modal-open');
    }

    function closeQuotationModal(modal) {
        if (!modal) return;
        modal.classList.remove('open');
        modal.setAttribute('aria-hidden', 'true');
        if (!document.querySelector('.modal-backdrop.open')) {
            document.body.classList.remove('modal-open');
        }
    }

    document.querySelectorAll('[data-open-modal]').forEach(function (button) {
        button.addEventListener('click', function (e) {
            e.preventDefault();
            openQuotationModal(button.getAttribute('data-open-modal'));
        });
    });

    document.querySelectorAll('.modal-backdrop [data-close-modal]').forEach(function (button) {
        button.addEventListener('click', function () {
            closeQuotationModal(button.closest('.modal-backdrop'));
        });
    });

    document.querySelectorAll('.modal-backdrop').forEach(function (backdrop) {
        backdrop.addEventListener('click', function (e) {
            if (e.target === backdrop) closeQuotationModal(backdrop);
        });
    });

    document.addEventListener('keydown', function (e) {
        if (e.key !== 'Escape') return;
        var modal = document.querySelector('.modal-backdrop.open');
        if (modal) closeQuotationModal(modal);
    });

    document.querySelectorAll('.quotation-modal').forEach(function (modal) {
        var price = modal.querySelector('input[name="unit_price"]');
        if (!price) return;

        var quantityText = modal.querySelector('.modal-subtitle');
        var match = quantityText ? quantityText.textContent.match(/×\s*(\d+)/) : null;
        var quantity = match ? parseFloat(match[1]) : 0;
        var total = document.createElement('div');
        total.className = 'quotation-total-live';
        price.closest('.quotation-field').appendChild(total);

        function updateTotal() {
            var value = parseFloat(price.value) || 0;
            total.textContent = 'Quotation Total: ₱' + (value * quantity).toLocaleString('en-PH', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            });
        }
        price.addEventListener('input', updateTotal);
        updateTotal();
    });
});
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
=======
<?php include __DIR__ . '/includes/footer.php'; ?>
>>>>>>> origin/main
>>>>>>> origin/main
