<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
requireLogin();
$pdo = getDB();

$pageTitle = 'Sales Reports';
include __DIR__ . '/includes/header.php';

$totalSales = (float) $pdo->query('SELECT COALESCE(SUM(total_amount),0) FROM sales_invoices')->fetchColumn();
$lastMonthSales = (float) $pdo->query("SELECT COALESCE(SUM(total_amount),0) FROM sales_invoices WHERE invoice_date >= DATE_SUB(CURDATE(), INTERVAL 60 DAY) AND invoice_date < DATE_SUB(CURDATE(), INTERVAL 30 DAY)")->fetchColumn();
$thisMonthSales = (float) $pdo->query("SELECT COALESCE(SUM(total_amount),0) FROM sales_invoices WHERE invoice_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)")->fetchColumn();
$salesChange = $lastMonthSales > 0 ? round((($thisMonthSales - $lastMonthSales) / $lastMonthSales) * 100) : ($thisMonthSales > 0 ? 100 : 0);

$quotationCount = (int) $pdo->query('SELECT COUNT(*) FROM sales_quotations')->fetchColumn();
$convertedCount = (int) $pdo->query('SELECT COUNT(*) FROM sales_orders WHERE quotation_id IS NOT NULL')->fetchColumn();
$conversionRate = $quotationCount > 0 ? round(($convertedCount / $quotationCount) * 100) : 0;

$avgCollectionDays = $pdo->query("
    SELECT AVG(DATEDIFF(col.collection_date, i.invoice_date))
    FROM collections col JOIN sales_invoices i ON i.id = col.invoice_id
")->fetchColumn();
$avgCollectionDays = $avgCollectionDays !== null ? round((float) $avgCollectionDays) : 0;

$monthlyStmt = $pdo->query("
    SELECT DATE_FORMAT(invoice_date, '%Y-%m') AS ym, DATE_FORMAT(invoice_date, '%b') AS label, SUM(total_amount) AS total
    FROM sales_invoices
    WHERE invoice_date >= DATE_SUB(CURDATE(), INTERVAL 8 MONTH)
    GROUP BY ym, label
    ORDER BY ym ASC
");
$monthly = $monthlyStmt->fetchAll();
$maxMonthly = max(array_map(fn($m) => (float) $m['total'], $monthly) ?: [0]) ?: 1;

$customerPerformance = $pdo->query("
    SELECT c.name,
           COUNT(DISTINCT o.id) AS orders_count,
           COALESCE(SUM(i.total_amount), 0) AS revenue,
           COALESCE(SUM(i.total_amount - i.amount_paid), 0) AS outstanding
    FROM customers c
    JOIN sales_orders o ON o.customer_id = c.id
    LEFT JOIN sales_invoices i ON i.order_id = o.id
    GROUP BY c.id, c.name
    ORDER BY revenue DESC
    LIMIT 5
")->fetchAll();
?>
<div class="page-toolbar"><p class="muted">A snapshot of performance across quotations, orders, delivery, invoices, and collections.</p><button class="primary-btn" onclick="window.print()">Export Summary</button></div>
<div class="report-grid">
<div class="report-card"><p class="eyebrow">TOTAL SALES</p><h2><?= peso($totalSales) ?></h2><p><?= $salesChange >= 0 ? '+' : '' ?><?= $salesChange ?>% compared with the previous month.</p></div>
<div class="report-card"><p class="eyebrow">QUOTATION → ORDER</p><h2><?= $conversionRate ?>%</h2><p><?= $convertedCount ?> of <?= $quotationCount ?> quotations converted to orders.</p></div>
<div class="report-card"><p class="eyebrow">AVERAGE COLLECTION</p><h2><?= $avgCollectionDays ?> days</h2><p>Average time from invoice date to collection.</p></div>
</div>
<div class="report-card chart-card"><p class="eyebrow">MONTHLY SALES</p>
<div class="bars">
<?php if (!$monthly): ?><span style="height:4%"></span><?php endif; ?>
<?php foreach ($monthly as $m): ?><span style="height:<?= max(4, round(((float) $m['total'] / $maxMonthly) * 100)) ?>%"></span><?php endforeach; ?>
</div>
<div class="bar-labels">
<?php if (!$monthly): ?><span>No data</span><?php endif; ?>
<?php foreach ($monthly as $m): ?><span><?= htmlspecialchars($m['label']) ?></span><?php endforeach; ?>
</div>
</div>
<div class="panel"><div class="panel-head"><div><p class="eyebrow">CUSTOMER PERFORMANCE</p><h2>Top customers by revenue</h2></div></div><div class="table-wrap"><table><thead><tr><th>Customer</th><th>Orders</th><th>Total Revenue</th><th>Outstanding</th></tr></thead><tbody>
<?php if (!$customerPerformance): ?><tr class="empty-row"><td colspan="4">No sales activity yet.</td></tr><?php endif; ?>
<?php foreach ($customerPerformance as $c): ?>
<tr><td><b><?= htmlspecialchars($c['name']) ?></b></td><td><?= (int) $c['orders_count'] ?></td><td><?= peso((float) $c['revenue']) ?></td><td><?= peso((float) $c['outstanding']) ?></td></tr>
<?php endforeach; ?>
</tbody></table></div></div>
<?php include __DIR__ . '/includes/footer.php'; ?>
