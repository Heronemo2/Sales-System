<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
requireLogin();
if (!in_array(currentUser()['role'] ?? '', ['Sales Admin','Sales Staff'], true)) { header('Location: login.php'); exit; }
$pdo = getDB();
$openQuotations = (int)$pdo->query("SELECT COUNT(*) FROM sales_quotations WHERE admin_status='Pending'")->fetchColumn();
$requests = (int)$pdo->query("SELECT COUNT(*) FROM customer_order_requests WHERE status='Pending Quotation'")->fetchColumn();
$ordersInProgress = (int)$pdo->query("SELECT COUNT(*) FROM sales_orders WHERE status IN('Pending','Processing','Invoiced')")->fetchColumn();
$stockoutApprovals = (int)$pdo->query("SELECT COUNT(*) FROM inventory_stockout_approvals WHERE request_status='Stocked Out'")->fetchColumn();
$teamSalesStock = (float)$pdo->query("SELECT COALESCE(SUM(stock_quantity),0) FROM inventory WHERE status='Active'")->fetchColumn();
$receivablesDue = (float)$pdo->query("SELECT COALESCE(SUM(total_amount-amount_paid),0) FROM sales_invoices WHERE status!='Paid'")->fetchColumn();
$ordersPerPage = 8;
$totalOrders = (int)$pdo->query('SELECT COUNT(*) FROM sales_orders')->fetchColumn();
$totalOrderPages = max(1, (int)ceil($totalOrders / $ordersPerPage));
$progressPage = max(1, min((int)($_GET['progress_page'] ?? 1), $totalOrderPages));
$ordersStmt = $pdo->prepare("SELECT o.*,c.customer_name,(SELECT d.status FROM deliveries d WHERE d.order_id=o.so_id ORDER BY d.del_id DESC LIMIT 1) delivery_status,(SELECT i.invoice_no FROM sales_invoices i WHERE i.order_id=o.so_id LIMIT 1) invoice_no FROM sales_orders o JOIN customers c ON c.cus_id=o.customer_id ORDER BY o.so_id DESC LIMIT :limit OFFSET :offset");
$ordersStmt->bindValue(':limit', $ordersPerPage, PDO::PARAM_INT);
$ordersStmt->bindValue(':offset', ($progressPage - 1) * $ordersPerPage, PDO::PARAM_INT);
$ordersStmt->execute();
$orders = $ordersStmt->fetchAll();
function orderProgressPageUrl(int $page): string
{
    return '?' . http_build_query(['progress_page' => $page]);
}
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
            <p class="muted">Each order has its own progress bar. Showing <?= $totalOrders === 0 ? 0 : (($progressPage - 1) * $ordersPerPage + 1) ?>–<?= min($progressPage * $ordersPerPage, $totalOrders) ?> of <?= $totalOrders ?> orders.</p>
        </div>
    </div>
    <div class="order-progress-list">
        <?php foreach ($orders as $o): [$pct, $label] = orderProgress($o); ?>
            <div class="order-progress-card">
                <div class="order-progress-head">
                    <div><b><?= htmlspecialchars($o['customer_name']) ?></b><span><?= htmlspecialchars($o['order_no']) ?> · <?= htmlspecialchars($o['product_code'] ?? '—') ?> × <?= (int)$o['quantity'] ?></span></div><strong><?= $pct ?>%</strong>
                </div>
                <div class="progress order-progress"><span style="width:<?= $pct ?>%"></span></div>
                <div class="order-progress-foot"><span><?= htmlspecialchars($label) ?></span><small><?= peso((float)$o['total_amount']) ?></small></div>
            </div>
            <?php endforeach; ?><?php if (!$orders): ?><div class="note-card"><b>No Sales Orders yet.</b><span>Customer-approved quotations will become Purchase Orders, then Sales Orders.</span></div><?php endif; ?>
    </div>
    <?php if ($totalOrderPages > 1): ?>
        <nav class="table-pagination order-progress-pagination" aria-label="Order progress pages">
            <?php if ($progressPage > 1): ?>
                <a href="<?= htmlspecialchars(orderProgressPageUrl($progressPage - 1), ENT_QUOTES, 'UTF-8') ?>" aria-label="Previous page">Previous</a>
            <?php else: ?>
                <span class="pagination-disabled" aria-disabled="true">Previous</span>
            <?php endif; ?>

            <?php
            $firstPage = max(1, $progressPage - 2);
            $lastPage = min($totalOrderPages, $progressPage + 2);
            if ($firstPage > 1):
            ?>
                <a href="<?= htmlspecialchars(orderProgressPageUrl(1), ENT_QUOTES, 'UTF-8') ?>">1</a>
                <?php if ($firstPage > 2): ?><span class="page-ellipsis" aria-hidden="true">...</span><?php endif; ?>
            <?php endif; ?>

            <?php for ($page = $firstPage; $page <= $lastPage; $page++): ?>
                <?php if ($page === $progressPage): ?>
                    <span class="active" aria-current="page"><?= $page ?></span>
                <?php else: ?>
                    <a href="<?= htmlspecialchars(orderProgressPageUrl($page), ENT_QUOTES, 'UTF-8') ?>"><?= $page ?></a>
                <?php endif; ?>
            <?php endfor; ?>

            <?php if ($lastPage < $totalOrderPages): ?>
                <?php if ($lastPage < $totalOrderPages - 1): ?><span class="page-ellipsis" aria-hidden="true">...</span><?php endif; ?>
                <a href="<?= htmlspecialchars(orderProgressPageUrl($totalOrderPages), ENT_QUOTES, 'UTF-8') ?>"><?= $totalOrderPages ?></a>
            <?php endif; ?>

            <?php if ($progressPage < $totalOrderPages): ?>
                <a href="<?= htmlspecialchars(orderProgressPageUrl($progressPage + 1), ENT_QUOTES, 'UTF-8') ?>" aria-label="Next page">Next</a>
            <?php else: ?>
                <span class="pagination-disabled" aria-disabled="true">Next</span>
            <?php endif; ?>
        </nav>
    <?php endif; ?>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>