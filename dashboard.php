<?php
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
