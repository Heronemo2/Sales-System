<?php
<<<<<<< HEAD
=======
<<<<<<< HEAD
>>>>>>> origin/main
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
requireLogin();
<<<<<<< HEAD
if (!in_array(currentUser()['role'] ?? '', ['Sales Admin','Sales Staff'], true)) { header('Location: login.php'); exit; }
$pdo = getDB();
$openQuotations = (int)$pdo->query("SELECT COUNT(*) FROM sales_quotations WHERE admin_status='Pending'")->fetchColumn();
$requests = (int)$pdo->query("SELECT COUNT(*) FROM customer_order_requests WHERE status='Pending Quotation'")->fetchColumn();
$ordersInProgress = (int)$pdo->query("SELECT COUNT(*) FROM sales_orders WHERE status IN('Pending','Processing','Invoiced')")->fetchColumn();
$stockoutApprovals = (int)$pdo->query("SELECT COUNT(*) FROM inventory_stockout_approvals WHERE request_status='Stocked Out'")->fetchColumn();
$teamSalesStock = (float)$pdo->query("SELECT COALESCE(SUM(stock_quantity),0) FROM inventory WHERE status='Active'")->fetchColumn();
$receivablesDue = (float)$pdo->query("SELECT COALESCE(SUM(total_amount-amount_paid),0) FROM sales_invoices WHERE status!='Paid'")->fetchColumn();
$orders = $pdo->query("SELECT o.*,c.customer_name,(SELECT d.status FROM deliveries d WHERE d.order_id=o.so_id ORDER BY d.del_id DESC LIMIT 1) delivery_status,(SELECT i.invoice_no FROM sales_invoices i WHERE i.order_id=o.so_id LIMIT 1) invoice_no FROM sales_orders o JOIN customers c ON c.cus_id=o.customer_id ORDER BY o.so_id DESC LIMIT 8")->fetchAll();
function orderProgress(array $o): array
{
    $status = $o['status'] ?? 'Pending';
    if ($status === 'Cancelled') return [0, 'Cancelled'];
    if ($status === 'Completed') return [100, 'Completed'];
    if (!empty($o['invoice_no']) || $status === 'Invoiced') return [80, 'Invoiced'];
    if (($o['delivery_status'] ?? '') === 'Delivered') return [70, 'Delivered'];
    if (($o['delivery_status'] ?? '') === 'In Transit') return [60, 'In Transit'];
    if (($o['delivery_status'] ?? '') === 'Preparing' || $status === 'Processing') return [45, 'Processing'];
    return [25, 'Pending'];
}
$pageTitle = 'Sales Dashboard';
include __DIR__ . '/includes/header.php';
?>
<section class="welcome-banner">
    <div>
        <p class="eyebrow light">SALES MANAGEMENT</p>
        <h2>Track every order from quotation to collection.</h2>
        <p>Customer requests, quotations, purchase orders, delivery, invoicing, and payment in one flow.</p>
    </div>
    <div class="banner-mark">S</div>
</section>
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon red">Q</div>
        <div><span>Quotation Approval</span><strong><?= $openQuotations ?></strong><small>Waiting for Sales Admin</small></div>
    </div>
    <div class="stat-card">
        <div class="stat-icon pink">R</div>
        <div><span>New Requests</span><strong><?= $requests ?></strong><small>Waiting for Sales quotation</small></div>
    </div>
    <div class="stat-card">
        <div class="stat-icon burgundy">O</div>
        <div><span>Orders In Progress</span><strong><?= $ordersInProgress ?></strong><small>Active Sales Orders</small></div>
    </div>
    <div class="stat-card">
        <div class="stat-icon dark">₱</div>
        <div><span>Outstanding AR</span><strong><?= peso($receivablesDue) ?></strong><small>Unpaid / partially paid</small></div>
    </div>
    <div class="stat-card">
        <div class="stat-icon red">I</div>
        <div><span>Stockout Status</span><strong><?= $stockoutApprovals ?></strong><small>Inventory System stock-outs awaiting sync</small></div>
    </div>
</div>
<div class="panel" style="margin-top:18px">
    <div class="panel-head">
        <div>
            <p class="eyebrow dark">ORDER PROGRESS</p>
            <h2>Every Order at a Glance</h2>
            <p class="muted">Each order has its own progress bar.</p>
        </div>
    </div>
    <div class="order-progress-list">
        <?php foreach ($orders as $o): [$pct, $label] = orderProgress($o); ?>
            <div class="order-progress-card">
                <div class="order-progress-head">
                    <div><b><?= htmlspecialchars($o['order_no']) ?></b><span><?= htmlspecialchars($o['customer_name']) ?> · <?= htmlspecialchars($o['product_code'] ?? '—') ?> × <?= (int)$o['quantity'] ?></span></div><strong><?= $pct ?>%</strong>
                </div>
                <div class="progress order-progress"><span style="width:<?= $pct ?>%"></span></div>
                <div class="order-progress-foot"><span><?= htmlspecialchars($label) ?></span><small><?= peso((float)$o['total_amount']) ?></small></div>
            </div>
            <?php endforeach; ?><?php if (!$orders): ?><div class="note-card"><b>No Sales Orders yet.</b><span>Customer-approved quotations will become Purchase Orders, then Sales Orders.</span></div><?php endif; ?>
    </div>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
=======
if (in_array(currentUser()['role'], ['System Admin', 'Administrator'], true)) { header('Location: system_admin.php'); exit; }
if (currentUser()['role'] === 'Sales Admin') { header('Location: admin_customers.php'); exit; }
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
        (SELECT customer_name FROM customers WHERE id = sales_quotations.customer_id) AS customer
     FROM sales_quotations ORDER BY id DESC LIMIT 4)
    UNION ALL
    (SELECT 'Order' AS kind, order_no AS ref, order_date AS d, created_at,
        (SELECT customer_name FROM customers WHERE id = sales_orders.customer_id) AS customer
     FROM sales_orders ORDER BY id DESC LIMIT 4)
    UNION ALL
    (SELECT 'Invoice' AS kind, invoice_no AS ref, invoice_date AS d, created_at,
        (SELECT customer_name FROM customers c JOIN sales_orders o ON o.id = sales_invoices.order_id WHERE c.id = o.customer_id) AS customer
     FROM sales_invoices ORDER BY id DESC LIMIT 4)
    UNION ALL
    (SELECT 'Payment' AS kind, collection_no AS ref, collection_date AS d, created_at,
        (SELECT c.customer_name FROM sales_invoices i JOIN sales_orders o ON o.id = i.order_id JOIN customers c ON c.id = o.customer_id WHERE i.id = collections.invoice_id LIMIT 1) AS customer
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
    <div class="banner-mark"><img src="assets/img/logo.png" alt=""></div>
</section>

<section class="stats-grid">
    <div class="stat-card"><div class="stat-icon dark">♙</div><div><span>Active Customers</span><strong><?= $activeCustomers ?></strong><small><?= $newCustomers7d ?> added this week</small></div></div>
    <div class="stat-card"><div class="stat-icon red">▤</div><div><span>Open Quotations</span><strong><?= $openQuotations ?></strong><small>Awaiting approval</small></div></div>
    <div class="stat-card"><div class="stat-icon pink">▣</div><div><span>Orders In Progress</span><strong><?= $ordersInProgress ?></strong><small><?= $readyOrders ?> ready for fulfillment</small></div></div>
    <div class="stat-card"><div class="stat-icon burgundy">₱</div><div><span>Receivables Due</span><strong><?= peso($receivablesDue) ?></strong><small><?= $overdueAccounts ?> account<?= $overdueAccounts === 1 ? '' : 's' ?> overdue</small></div></div>
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
            <div class="flow-item"><span>01</span><div><b>Quotation</b><small>Customer request is prepared</small></div></div><div class="flow-line"></div>
            <div class="flow-item"><span>02</span><div><b>Inventory Check</b><small>Product and stock are verified</small></div></div><div class="flow-line"></div>
            <div class="flow-item"><span>03</span><div><b>Purchase Order</b><small>Created only when Inventory approves</small></div></div><div class="flow-line"></div>
            <div class="flow-item"><span>04</span><div><b>Sales Order & Delivery</b><small>PO is converted and fulfilled</small></div></div><div class="flow-line"></div>
            <div class="flow-item"><span>05</span><div><b>Invoice & Collection</b><small>Bill and record customer payment</small></div></div>
        </div>
    </div>
</section>
<?php include __DIR__ . '/includes/footer.php'; ?>
=======
require_once __DIR__.'/config/database.php';require_once __DIR__.'/includes/auth.php';require_once __DIR__.'/includes/functions.php';requireSalesWorkspace();$pdo=getDB();
$openQuotations=(int)$pdo->query("SELECT COUNT(*) FROM sales_quotations WHERE admin_status='Pending'")->fetchColumn();
$requests=(int)$pdo->query("SELECT COUNT(*) FROM customer_order_requests WHERE status='Pending Quotation'")->fetchColumn();
$ordersInProgress=(int)$pdo->query("SELECT COUNT(*) FROM sales_orders WHERE status IN('Pending','Processing','Invoiced')")->fetchColumn();
$receivablesDue=(float)$pdo->query("SELECT COALESCE(SUM(total_amount-amount_paid),0) FROM sales_invoices WHERE status!='Paid'")->fetchColumn();
$orders=$pdo->query("SELECT o.*,c.customer_name,(SELECT d.status FROM deliveries d WHERE d.order_id=o.so_id ORDER BY d.del_id DESC LIMIT 1) delivery_status,(SELECT i.invoice_no FROM sales_invoices i WHERE i.order_id=o.so_id LIMIT 1) invoice_no FROM sales_orders o JOIN customers c ON c.cus_id=o.customer_id ORDER BY o.so_id DESC LIMIT 8")->fetchAll();
function orderProgress(array $o):array{
 $status=$o['status']??'Pending';
 if($status==='Cancelled')return [0,'Cancelled'];
 if($status==='Completed')return [100,'Completed'];
 if(!empty($o['invoice_no'])||$status==='Invoiced')return [80,'Invoiced'];
 if(($o['delivery_status']??'')==='Delivered')return [70,'Delivered'];
 if(($o['delivery_status']??'')==='In Transit')return [60,'In Transit'];
 if(($o['delivery_status']??'')==='Preparing'||$status==='Processing')return [45,'Processing'];
 return [25,'Pending'];
}
$pageTitle='Sales Dashboard';include __DIR__.'/includes/header.php';
?>
<section class="welcome-banner"><div><p class="eyebrow light">SALES MANAGEMENT</p><h2>Track every order from quotation to collection.</h2><p>Customer requests, quotations, purchase orders, delivery, invoicing, and payment in one flow.</p></div><div class="banner-mark">S</div></section>
<div class="stats-grid"><div class="stat-card"><div class="stat-icon red">Q</div><div><span>Quotation Approval</span><strong><?=$openQuotations?></strong><small>Waiting for Sales Admin</small></div></div><div class="stat-card"><div class="stat-icon pink">R</div><div><span>New Requests</span><strong><?=$requests?></strong><small>Waiting for Sales quotation</small></div></div><div class="stat-card"><div class="stat-icon burgundy">O</div><div><span>Orders In Progress</span><strong><?=$ordersInProgress?></strong><small>Active Sales Orders</small></div></div><div class="stat-card"><div class="stat-icon dark">₱</div><div><span>Outstanding AR</span><strong><?=peso($receivablesDue)?></strong><small>Unpaid / partially paid</small></div></div></div>
<div class="panel" style="margin-top:18px"><div class="panel-head"><div><p class="eyebrow dark">ORDER PROGRESS</p><h2>Every Order at a Glance</h2><p class="muted">Each order has its own progress bar.</p></div></div>
<div class="order-progress-list">
<?php foreach($orders as $o):[$pct,$label]=orderProgress($o);?>
<div class="order-progress-card"><div class="order-progress-head"><div><b><?=htmlspecialchars($o['order_no'])?></b><span><?=htmlspecialchars($o['customer_name'])?> · <?=htmlspecialchars($o['product_code']??'—')?> × <?= (int)$o['quantity']?></span></div><strong><?=$pct?>%</strong></div><div class="progress order-progress"><span style="width:<?=$pct?>%"></span></div><div class="order-progress-foot"><span><?=htmlspecialchars($label)?></span><small><?=peso((float)$o['total_amount'])?></small></div></div>
<?php endforeach;?><?php if(!$orders):?><div class="note-card"><b>No Sales Orders yet.</b><span>Customer-approved quotations will become Purchase Orders, then Sales Orders.</span></div><?php endif;?>
</div></div>
<?php include __DIR__.'/includes/footer.php'; ?>
>>>>>>> origin/main
>>>>>>> origin/main
