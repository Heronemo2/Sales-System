<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

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
?>

<?php include __DIR__ . '/includes/header.php'; ?>

<section class="content-grid" style="grid-template-columns: 1fr; margin-top: 25px;">

    <div class="panel">

        <div class="panel-head">
            <div>
                <p class="eyebrow">INVENTORY</p>
                <h2 style="margin: 0;">Inventory Reference</h2>
                <p style="color: var(--muted); margin: 5px 0 0;">
                    Product and stock information used by the Sales quotation inventory check.
                </p>
            </div>
        </div>

        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>PRODUCT CODE</th>
                        <th>PRODUCT</th>
                        <th>AVAILABLE STOCK</th>
                        <th>UNIT PRICE</th>
                        <th>UNIT</th>
                        <th>STATUS</th>
                        <th>LAST UPDATED</th>
                    </tr>
                </thead>

                <tbody>
                    <?php if (empty($rows)): ?>

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
                                </td>

                                <td>
                                    <strong>
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
                                    </span>
                                </td>

                                <td>
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