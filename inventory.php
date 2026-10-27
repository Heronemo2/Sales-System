<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

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
        </div>

        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>PRODUCT CODE</th>
                        <th>PRODUCT</th>
                        <th>BRAND</th>
                        <th>SIZE</th>
                        <th>UNIT</th>
                        <th>TEAM SALES STOCK</th>
                        <th>REORDER LEVEL</th>
                        <th>STATUS</th>
                        <th>LAST UPDATED</th>
                    </tr>
                </thead>
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
                </tbody>
            </table>
        </div>
    </div>
</section>

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