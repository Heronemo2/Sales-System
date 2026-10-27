<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
requireLogin();
$pdo = getDB();

$pageTitle = 'Dashboard';
include __DIR__ . '/includes/header.php';

$dashboardSection = $_GET['section'] ?? '';
if (!in_array($dashboardSection, ['', 'core', 'billing'], true)) {
    $dashboardSection = '';
}
$showCore = $dashboardSection === '' || $dashboardSection === 'core';
$showBilling = $dashboardSection === '' || $dashboardSection === 'billing';

$activeCustomers = (int) $pdo->query("SELECT COUNT(*) FROM customers WHERE status = 'Active'")->fetchColumn();
$newCustomers7d = (int) $pdo->query("SELECT COUNT(*) FROM customers WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)")->fetchColumn();
$openQuotations = (int) $pdo->query("SELECT COUNT(*) FROM sales_quotations WHERE status = 'Pending'")->fetchColumn();
$ordersInProgress = (int) $pdo->query("SELECT COUNT(*) FROM sales_orders WHERE status IN ('Pending','Processing')")->fetchColumn();
$readyOrders = (int) $pdo->query("SELECT COUNT(*) FROM sales_orders WHERE status = 'Processing'")->fetchColumn();
$receivablesDue = (float) $pdo->query("SELECT COALESCE(SUM(total_amount - amount_paid),0) FROM sales_invoices WHERE status != 'Paid'")->fetchColumn();
$overdueAccounts = (int) $pdo->query("SELECT COUNT(*) FROM sales_invoices WHERE status != 'Paid' AND DATE_ADD(invoice_date, INTERVAL 30 DAY) < CURDATE()")->fetchColumn();

$activity = $pdo->query("
    (SELECT 'Quotation' AS kind, quotation_no AS ref, quotation_date AS d, created_at,
        (SELECT name FROM customers WHERE id = sales_quotations.customer_id) AS customer
     FROM sales_quotations ORDER BY id DESC LIMIT 4)
    UNION ALL
    (SELECT 'Order' AS kind, order_no AS ref, order_date AS d, created_at,
        (SELECT name FROM customers WHERE id = sales_orders.customer_id) AS customer
     FROM sales_orders ORDER BY id DESC LIMIT 4)
    UNION ALL
    (SELECT 'Invoice' AS kind, invoice_no AS ref, invoice_date AS d, created_at,
        (SELECT name FROM customers c JOIN sales_orders o ON o.id = sales_invoices.order_id WHERE c.id = o.customer_id) AS customer
     FROM sales_invoices ORDER BY id DESC LIMIT 4)
    UNION ALL
    (SELECT 'Payment' AS kind, collection_no AS ref, collection_date AS d, created_at,
        (SELECT c.name FROM customers c JOIN sales_orders o ON o.id = i.order_id JOIN sales_invoices i ON i.id = collections.invoice_id WHERE c.id = o.customer_id LIMIT 1) AS customer
     FROM collections ORDER BY id DESC LIMIT 4)
    ORDER BY created_at DESC
    LIMIT 4
")->fetchAll();
?>
<section class="welcome-banner">
    <div>
        <p class="eyebrow light">TEAM SALES / SALES OVERVIEW</p>
        <h2>Here’s how your sales are moving today.</h2>
        <p>Track customers, quotations, orders, delivery, invoicing, and collection in one place.</p>
    </div>
    <div class="banner-mark">S</div>
</section>

<section class="stats-grid">
    <div class="stat-card"><div class="stat-icon dark">♙</div><div><span>Active Customers</span><strong><?= $activeCustomers ?></strong><small><?= $newCustomers7d ?> added this week</small></div></div>
    <div class="stat-card"><div class="stat-icon red">▤</div><div><span>Open Quotations</span><strong><?= $openQuotations ?></strong><small>Awaiting approval</small></div></div>
    <div class="stat-card"><div class="stat-icon pink">▣</div><div><span>Orders In Progress</span><strong><?= $ordersInProgress ?></strong><small><?= $readyOrders ?> ready for fulfillment</small></div></div>
    <div class="stat-card"><div class="stat-icon burgundy">₱</div><div><span>Receivables Due</span><strong><?= peso($receivablesDue) ?></strong><small><?= $overdueAccounts ?> account<?= $overdueAccounts === 1 ? '' : 's' ?> overdue</small></div></div>
</section>

<section class="dashboard-module-section" id="reports-section">
    <div class="dashboard-module-title">
        <p class="eyebrow">REPORTING</p>
        <h2>Sales Reports</h2>
    </div>
    <section class="module-grid">
        <a href="reports.php" class="module-card"><span class="module-symbol">▥</span><div><h3>Sales Reports</h3><p>Review sales performance and trends.</p></div><span>→</span></a>
    </section>
</section>

<section class="content-grid">
    <div class="panel">
        <div class="panel-head"><div><p class="eyebrow">RECENT ACTIVITY</p><h2>Latest activity</h2></div><a href="reports.php" class="text-link">See reports →</a></div>
        <div class="activity-feed">
            <?php if (!$activity): ?>
                <div class="activity-row"><span class="activity-dot"></span><div><b>No activity yet</b><small>Create a quotation, order, invoice, or payment to see it here.</small></div></div>
            <?php endif; ?>
            <?php foreach ($activity as $a): ?>
                <div class="activity-row"><span class="activity-dot"></span><div><b><?= htmlspecialchars($a['kind']) ?> <?= htmlspecialchars($a['ref']) ?></b><small><?= htmlspecialchars($a['customer'] ?? '—') ?> • <?= niceDate($a['d']) ?></small></div></div>
            <?php endforeach; ?>
        </div>
    </div>
    <div class="panel">
        <div class="panel-head"><div><p class="eyebrow">PROCESS</p><h2>Sales workflow</h2></div></div>
        <div class="workflow">
            <div class="flow-item"><span>01</span><div><b>Quotation</b><small>Price proposal is prepared</small></div></div><div class="flow-line"></div>
            <div class="flow-item"><span>02</span><div><b>Order</b><small>Approved quotation becomes an order</small></div></div><div class="flow-line"></div>
            <div class="flow-item"><span>03</span><div><b>Delivery</b><small>Order is fulfilled and delivered</small></div></div><div class="flow-line"></div>
            <div class="flow-item"><span>04</span><div><b>Invoice & Collection</b><small>Bill and record customer payment</small></div></div>
        </div>
    </div>
</section>
<?php include __DIR__ . '/includes/footer.php'; ?>
