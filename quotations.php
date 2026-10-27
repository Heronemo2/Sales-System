<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
requireSalesWorkspace();
$pdo = getDB();

$error = '';
$notice = '';

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
            }
        }
    }
}

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
