<?php

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

?>

<?php include __DIR__ . '/includes/header.php'; ?>

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
        <div class="panel-head">
            <div>
                <p class="eyebrow">INVENTORY</p>
                <h2 style="margin: 0;">Inventory Reference</h2>
                <p style="color: var(--muted); margin: 5px 0 0;">
                    Product and stock information retrieved directly from the Inventory database.
                </p>
            </div>
        </div>

        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>IMAGE</th>
                        <th>PRODUCT CODE</th>
                        <th>PRODUCT</th>
                        <th>BRAND</th>
                        <th>SIZE</th>
                        <th>UNIT</th>
                        <th>STOCK</th>
                        <th>REORDER LEVEL</th>
                        <th>STATUS</th>
                        <th>LAST UPDATED</th>
                    </tr>
                </thead>

                <tbody>
                    <?php if (empty($rows)): ?>
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
                                </td>

                                <td>
                                    <strong>
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
                                    </span>
                                </td>

                                <td>
                                    <?= htmlspecialchars($r['created_at'] ?? '') ?>
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
