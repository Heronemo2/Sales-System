<?php
<<<<<<< HEAD
=======
<<<<<<< HEAD
>>>>>>> origin/main
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

<<<<<<< HEAD
requireSalesWorkspace();

$pdo = getDB();
$pageTitle = 'Inventory';
$rows = [];
$error = '';

try {
    $rows = $pdo->query("
        SELECT inventory_id, product_code, product_name, brand, size, unit,
               stock_quantity, reorder_level, status, last_updated
        FROM inventory
        WHERE status = 'Active'
        ORDER BY product_name ASC
    ")->fetchAll();
} catch (Throwable $e) {
    $error = $e->getMessage();
}

include __DIR__ . '/includes/header.php';
?>
<?php if ($error): ?>
    <div class="alert error"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<section class="content-grid" style="grid-template-columns:1fr; margin-top:25px;">
    <div class="panel">
        <div class="panel-head">
            <div>
                <p class="eyebrow">TEAM SALES INVENTORY</p>
                <h2 style="margin:0;">Inventory</h2>
                <p style="color:var(--muted);margin:5px 0 0;">
                    This is the stock currently held by Team Sales. Stock is added only after an Inventory System stock-out is approved here.
                </p>
            </div>
            <a class="primary-btn" href="inventory_stockout_approval.php">Stockout Approval</a>
=======
requireLogin();

$pdo = getDB();

$stmt = $pdo->prepare("
    SELECT *
    FROM inventory_products
    ORDER BY product_code
");
$stmt->execute();

$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$pageTitle = 'Inventory Reference';
=======

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/inventory_db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

requireSalesWorkspace();

$pageTitle = 'Inventory Reference';
$rows = [];
$inventoryError = '';

try {
    $inventoryDb = getInventoryDB();

    // Support both the older Inventory schema and the newer finished-goods schema.
    $columns = $inventoryDb->query('SHOW COLUMNS FROM finished_goods')->fetchAll(PDO::FETCH_COLUMN);

    $has = static function (string $column) use ($columns): bool {
        return in_array($column, $columns, true);
    };

    $productCode = $has('finished_good_code') ? 'finished_good_code' : null;

    if ($productCode === null) {
        throw new RuntimeException('Inventory table does not contain a product code column.');
    }

    $select = [
        'fg_id AS id',
        "{$productCode} AS finished_good_code",
        'product_name',
    ];

    foreach ([
        'brand',
        'size',
        'unit',
        'description',
        'reorder_level',
        'status',
        'created_at',
    ] as $column) {
        $select[] = $has($column)
            ? "`{$column}`"
            : "NULL AS `{$column}`";
    }

    $select[] = 'COALESCE(SUM(inv.quantity), 0) AS stock_quantity';

    $sql = '
        SELECT ' . implode(', ', $select) . '
        FROM inventory_system.finished_goods fg
        LEFT JOIN inventory_system.inventory inv
            ON inv.item_id = fg.item_id
    ';

    if ($has('status')) {
        $sql .= " WHERE status = 'Active'";
    }

    $sql .= ' GROUP BY fg.fg_id, fg.item_id, fg.finished_good_code, fg.product_name, fg.brand, fg.size, fg.unit, fg.description, fg.reorder_level, fg.status, fg.created_at ORDER BY fg.product_name ASC';

    $stmt = $inventoryDb->query($sql);
    $rows = $stmt->fetchAll();

} catch (Throwable $e) {
    $inventoryError = 'Unable to connect to the Inventory database or retrieve products.';
}

>>>>>>> origin/main
?>

<?php include __DIR__ . '/includes/header.php'; ?>

<<<<<<< HEAD
<section class="content-grid" style="grid-template-columns: 1fr; margin-top: 25px;">

    <div class="panel">

=======
<?php if ($inventoryError): ?>
    <div class="alert error">
        <?= htmlspecialchars($inventoryError) ?>
    </div>
<?php endif; ?>

<section
    class="content-grid"
    style="grid-template-columns: 1fr; margin-top: 25px;"
>
    <div class="panel">
>>>>>>> origin/main
        <div class="panel-head">
            <div>
                <p class="eyebrow">INVENTORY</p>
                <h2 style="margin: 0;">Inventory Reference</h2>
                <p style="color: var(--muted); margin: 5px 0 0;">
<<<<<<< HEAD
                    Product and stock information used by the Sales quotation inventory check.
=======
                    Product and stock information retrieved directly from the Inventory database.
>>>>>>> origin/main
                </p>
            </div>
>>>>>>> origin/main
        </div>

        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
<<<<<<< HEAD
=======
<<<<<<< HEAD
                        <th>PRODUCT CODE</th>
                        <th>PRODUCT</th>
                        <th>AVAILABLE STOCK</th>
                        <th>UNIT PRICE</th>
                        <th>UNIT</th>
=======
                        <th>IMAGE</th>
>>>>>>> origin/main
                        <th>PRODUCT CODE</th>
                        <th>PRODUCT</th>
                        <th>BRAND</th>
                        <th>SIZE</th>
                        <th>UNIT</th>
<<<<<<< HEAD
                        <th>TEAM SALES STOCK</th>
                        <th>REORDER LEVEL</th>
=======
                        <th>STOCK</th>
                        <th>REORDER LEVEL</th>
>>>>>>> origin/main
>>>>>>> origin/main
                        <th>STATUS</th>
                        <th>LAST UPDATED</th>
                    </tr>
                </thead>
<<<<<<< HEAD
                <tbody>
                <?php if (!$rows): ?>
                    <tr><td colspan="9" style="text-align:center;padding:30px;">No Team Sales inventory yet. Approved Inventory stock-outs will appear here.</td></tr>
                <?php else: ?>
                    <?php foreach ($rows as $r): ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($r['product_code']) ?></strong></td>
                        <td><?= htmlspecialchars($r['product_name']) ?></td>
                        <td><?= htmlspecialchars($r['brand'] ?? '') ?></td>
                        <td><?= htmlspecialchars($r['size'] ?? '') ?></td>
                        <td><?= htmlspecialchars($r['unit']) ?></td>
                        <td><strong><?= htmlspecialchars(rtrim(rtrim(number_format((float)$r['stock_quantity'],2,'.',''), '0'), '.')) ?></strong></td>
                        <td><?= htmlspecialchars(rtrim(rtrim(number_format((float)$r['reorder_level'],2,'.',''), '0'), '.')) ?></td>
                        <td><span class="badge <?= badgeClass($r['status']) ?>"><?= htmlspecialchars($r['status']) ?></span></td>
                        <td><?= htmlspecialchars($r['last_updated']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
=======

                <tbody>
                    <?php if (empty($rows)): ?>
<<<<<<< HEAD

                        <tr>
                            <td colspan="7" style="text-align: center; padding: 30px;">
                                No inventory products found.
                            </td>
                        </tr>

                    <?php else: ?>

                        <?php foreach ($rows as $r): ?>

                            <tr>

                                <td>
                                    <strong>
                                        <?= htmlspecialchars($r['product_code']) ?>
                                    </strong>
                                </td>

                                <td>
                                    <?= htmlspecialchars($r['product_name']) ?>
=======
                        <tr>
                            <td colspan="10" style="text-align: center; padding: 30px;">
                                No inventory products found.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($rows as $r): ?>
                            <?php
                            $imagePath = '';
                            $imageUrl = $imagePath !== ''
                                ? $imagePath
                                : 'assets/img/logo.png';
                            $status = $r['status'] ?? 'Inactive';
                            ?>

                            <tr>
                                <td>
                                    <img
                                        class="inventory-thumb"
                                        src="<?= htmlspecialchars($imageUrl) ?>"
                                        alt="<?= htmlspecialchars($r['product_name'] ?? 'Product') ?>"
                                    >
>>>>>>> origin/main
                                </td>

                                <td>
                                    <strong>
<<<<<<< HEAD
                                        <?= (int)$r['quantity'] ?>
                                    </strong>
                                </td>

                                <td>
                                    <?= peso((float)$r['unit_price']) ?>
                                </td>

                                <td>
                                    <?= htmlspecialchars($r['unit']) ?>
                                </td>

                                <td>
                                    <span class="badge <?= badgeClass($r['status']) ?>">
                                        <?= htmlspecialchars($r['status']) ?>
=======
                                        <?= htmlspecialchars($r['finished_good_code'] ?? '') ?>
                                    </strong>
                                </td>

                                <td><?= htmlspecialchars($r['product_name'] ?? '') ?></td>
                                <td><?= htmlspecialchars($r['brand'] ?? '') ?></td>
                                <td><?= htmlspecialchars($r['size'] ?? '') ?></td>
                                <td><?= htmlspecialchars($r['unit'] ?? '') ?></td>
                                <td><strong><?= htmlspecialchars($r['stock_quantity'] ?? '0') ?></strong></td>
                                <td><?= htmlspecialchars($r['reorder_level'] ?? '0') ?></td>

                                <td>
                                    <span class="badge <?= badgeClass($status) ?>">
                                        <?= htmlspecialchars($status) ?>
>>>>>>> origin/main
                                    </span>
                                </td>

                                <td>
<<<<<<< HEAD
                                    <?= niceDate($r['updated_at']) ?>
                                </td>

                            </tr>

                        <?php endforeach; ?>

                    <?php endif; ?>
                </tbody>

            </table>
        </div>

    </div>

</section>

<?php include __DIR__ . '/includes/footer.php'; ?>
=======
                                    <?= htmlspecialchars($r['created_at'] ?? '') ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
>>>>>>> origin/main
                </tbody>
            </table>
        </div>
    </div>
</section>

<<<<<<< HEAD
<script>
document.addEventListener('DOMContentLoaded', function () {
  document.querySelectorAll('.table-wrap table').forEach(function (table, index) {
    if (table.dataset.filterReady === '1') return;
    table.dataset.filterReady = '1';
    const wrap = table.closest('.table-wrap');
    if (!wrap) return;
    const box = document.createElement('div');
    box.className = 'module-search';
    box.style.marginBottom = '12px';
    box.innerHTML = '<input type="search" class="table-filter" placeholder="Filter this table..." aria-label="Filter this table">';
    wrap.parentNode.insertBefore(box, wrap);
    const input = box.querySelector('input');
    input.addEventListener('input', function () {
      const term = this.value.trim().toLowerCase();
      table.querySelectorAll('tbody tr').forEach(function (row) {
        if (row.classList.contains('empty-row')) return;
        row.style.display = !term || row.textContent.toLowerCase().includes(term) ? '' : 'none';
      });
    });
  });
});
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
=======
<?php include __DIR__ . '/includes/footer.php'; ?>
>>>>>>> origin/main
>>>>>>> origin/main
