<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/inventory_db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
requireCustomer();
$pdo = getDB();
$user = currentUser();
$customerId = (int)($user['customer_id'] ?? 0);

$st = $pdo->prepare("SELECT * FROM customers WHERE cus_id=? AND status='Active' LIMIT 1");
$st->execute([$customerId]);
$customer = $st->fetch();
if (!$customer) {
    http_response_code(403);
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Account Deactivated | Team Sales</title>

        <style>
            * {
                box-sizing: border-box;
                margin: 0;
                padding: 0;
            }

            body {
                font-family: Arial, Helvetica, sans-serif;
                background: #f5f7fa;
                min-height: 100vh;
                display: flex;
                align-items: center;
                justify-content: center;
                padding: 20px;
                color: #1f2937;
            }

            .deactivated-card {
                width: 100%;
                max-width: 520px;
                background: #ffffff;
                border-radius: 16px;
                padding: 45px 40px;
                text-align: center;
                box-shadow: 0 10px 35px rgba(0, 0, 0, 0.08);
                border: 1px solid #e5e7eb;
            }

            .icon {
                width: 70px;
                height: 70px;
                margin: 0 auto 25px;
                border-radius: 50%;
                background: #fef2f2;
                color: #dc2626;
                display: flex;
                align-items: center;
                justify-content: center;
                font-size: 32px;
                font-weight: bold;
            }

            .brand {
                font-size: 14px;
                font-weight: 700;
                color: #2563eb;
                letter-spacing: 1px;
                margin-bottom: 12px;
                text-transform: uppercase;
            }

            h1 {
                font-size: 26px;
                margin-bottom: 14px;
                color: #111827;
            }

            .message {
                font-size: 15px;
                line-height: 1.7;
                color: #6b7280;
                margin-bottom: 25px;
            }

            .notice {
                background: #f9fafb;
                border: 1px solid #e5e7eb;
                border-radius: 10px;
                padding: 15px;
                font-size: 13px;
                line-height: 1.6;
                color: #6b7280;
                margin-bottom: 25px;
            }

            .exit-btn {
                display: inline-block;
                width: 100%;
                padding: 13px 20px;
                background: #1f2937;
                color: #ffffff;
                text-decoration: none;
                border-radius: 9px;
                font-size: 14px;
                font-weight: 600;
                transition: 0.2s ease;
            }

            .exit-btn:hover {
                background: #111827;
            }

            .footer {
                margin-top: 20px;
                font-size: 12px;
                color: #9ca3af;
            }
        </style>
    </head>

    <body>

        <div class="deactivated-card">

            <div class="icon">!</div>

            <div class="brand">
                Team Sales
            </div>

            <h1>Account Deactivated</h1>

            <p class="message">
                Your customer account has been deactivated and you
                no longer have access to the customer portal.
            </p>

            <div class="notice">
                If you believe this was done in error or you need
                assistance, please contact the Sales Administrator
                for further assistance.
            </div>

            <a href="logout.php" class="exit-btn">
                Exit
            </a>

            <div class="footer">
                Please contact your administrator for more information.
            </div>

        </div>

    </body>
    </html>
    <?php
    exit;
}

$error = '';
$notice = '';
$section = $_GET['section'] ?? 'order';
if (!in_array($section, ['order','quotations','checkout','availability'], true)) $section = 'order';

function productImageUrl(?string $path): string {
    $path = trim((string)$path);
    return $path !== '' ? $path : 'assets/img/logo.png';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'place_request') {
        $productCode = trim($_POST['product_code'] ?? '');
        $qty = max(1, (int)($_POST['quantity'] ?? 0));
        $notes = trim($_POST['notes'] ?? '');
        require_once __DIR__ . '/config/inventory_db.php';
        $inventoryDb = getInventoryDB();
        $product = checkInventoryProduct($productCode);
        if (!$product['ok'] || (int)($product['quantity'] ?? 0) < $qty) {
            $error = 'The requested product or quantity is not currently available.';
        } else {
            try {
                $requestNo = nextDocNo($pdo, 'customer_order_requests', 'REQ-', 1000);
                $pdo->prepare("INSERT INTO customer_order_requests(request_no,customer_id,request_date,product_code,quantity,notes,status) VALUES(?,?,CURDATE(),?,?,?,'Pending Quotation')")
                    ->execute([$requestNo,$customerId,$productCode,$qty,$notes ?: null]);
                logActivity($pdo,'Create','Customer Order Request','Customer submitted '.$requestNo,null);
                $notice = 'Order request '.$requestNo.' was sent to Sales. Sales will prepare your quotation.';
                $section = 'quotations';
            } catch (Throwable $e) { $error = 'Could not submit the order request: '.$e->getMessage(); }
        }
    }

    if ($action === 'quotation_decision') {
        $qid = (int)($_POST['quotation_id'] ?? 0);
        $decision = $_POST['decision'] ?? '';
        if (!in_array($decision,['Approved','Rejected'],true)) {
            $error = 'Invalid quotation decision.';
        } else {
            $q = $pdo->prepare("SELECT * FROM sales_quotations WHERE sq_id=? AND customer_id=? LIMIT 1");
            $q->execute([$qid,$customerId]);
            $quotation = $q->fetch();
            if (!$quotation || $quotation['customer_status'] !== 'Pending') $error = 'This quotation is no longer awaiting your decision.';
            elseif ($quotation['admin_status'] !== 'Approved') $error = 'Sales Admin must approve this quotation before you can approve it.';
            
            else {
                try {
                    $pdo->beginTransaction();
                    $pdo->prepare("UPDATE sales_quotations SET customer_status=?, status=? WHERE sq_id=?")
                        ->execute([$decision,$decision,$qid]);
                    if (!empty($quotation['source_request_id'])) {
                        $pdo->prepare("UPDATE customer_order_requests SET status=? WHERE req_id=?")
                            ->execute([$decision==='Approved'?'Quotation Approved':'Quotation Rejected',(int)$quotation['source_request_id']]);
                    }
                    $pdo->commit();
                    $poNo = null;
                    if ($decision === 'Approved') $poNo = createPurchaseOrderFromQuotation($pdo,$qid);
                    logActivity($pdo,$decision,'Quotation','Customer '.$decision.' quotation', $qid);
                    $notice = $decision==='Approved'
                        ? 'Quotation approved. '.($poNo?'Purchase Order '.$poNo.' was created in Sales.':'Purchase Order is already in Sales.')
                        : 'Quotation rejected.';
                    $section = 'quotations';
                } catch (Throwable $e) { if($pdo->inTransaction()) $pdo->rollBack(); $error = $e->getMessage(); }
            }
        }
    }

    if ($action === 'pay_quotation') {
        $qid=(int)($_POST['quotation_id']??0); $method=$_POST['payment_method']??''; $ref=trim($_POST['payment_reference']??'');
        if(!in_array($method,['GCash','Bank Transfer','Cash on Delivery'],true)) $error='Choose a valid payment method.';
        elseif($method!=='Cash on Delivery' && $ref==='') $error='Payment reference is required for this demo payment.';
        else {
            $q=$pdo->prepare("SELECT * FROM sales_quotations WHERE sq_id=? AND customer_id=? AND customer_status='Approved' LIMIT 1"); $q->execute([$qid,$customerId]); $quotation=$q->fetch();
            if(!$quotation) $error='Approved quotation not found.';
            else {
                $paid=$method==='Cash on Delivery'?'Pending':'Paid'; $paidAt=$paid==='Paid'?date('Y-m-d H:i:s'):null;
                $receipt='RCT-Q'.str_pad((string)$qid,5,'0',STR_PAD_LEFT);
                $pdo->prepare("UPDATE sales_quotations SET payment_method=?,payment_status=?,payment_reference=?,paid_at=?,receipt_no=? WHERE sq_id=?")
                    ->execute([$method,$paid,$ref?:null,$paidAt,$receipt,$qid]);
                $notice='Payment details saved. Receipt '.$receipt.' is ready.'; $section='checkout';
            }
        }
    }
}

$inventoryDb = getInventoryDB();
// finished_goods.quantity is maintained as a stock summary by Inventory triggers.
// Fall back to the live inventory aggregate for databases not yet migrated.
$inventoryColumns = $inventoryDb->query('SHOW COLUMNS FROM finished_goods')->fetchAll(PDO::FETCH_COLUMN);
$quantityExpr = in_array('quantity', $inventoryColumns, true)
    ? 'COALESCE(fg.quantity, stock.total_quantity, 0)'
    : 'COALESCE(stock.total_quantity, 0)';
$products = $inventoryDb->query("SELECT fg.fg_id AS finished_goods_id, fg.finished_good_code AS product_code, fg.product_name, fg.brand, fg.size, fg.unit, fg.status, fg.description, {$quantityExpr} AS quantity, 0 AS unit_price, NULL AS image_path FROM finished_goods fg LEFT JOIN (SELECT item_id, SUM(quantity) AS total_quantity FROM inventory GROUP BY item_id) stock ON stock.item_id=fg.item_id WHERE fg.status='Active' ORDER BY fg.product_name")->fetchAll();

$rq=$pdo->prepare("SELECT r.*,q.sq_id quotation_id,q.quotation_no,q.quotation_date,q.valid_until,q.total_amount,q.inventory_status,q.admin_status,q.customer_status,q.payment_method,q.payment_status,q.payment_reference,q.receipt_no
    FROM customer_order_requests r LEFT JOIN sales_quotations q ON q.sq_id=r.quotation_id WHERE r.customer_id=? ORDER BY r.req_id DESC LIMIT 20");
$rq->execute([$customerId]);
$requests=$rq->fetchAll();

foreach ($requests as &$request) {
    $stock = checkInventoryProduct((string)($request['product_code'] ?? ''));
    $request['product_name'] = $stock['product_name'] ?? '';
    $request['image_path'] = $stock['image_path'] ?? '';
}
unset($request);

$approvedQuotation=null;
$qid=(int)($_GET['quotation_id']??0);
if($qid){$q=$pdo->prepare("SELECT q.*,c.customer_name,c.email,c.phone,c.address FROM sales_quotations q JOIN customers c ON c.cus_id=q.customer_id WHERE q.sq_id=? AND q.customer_id=?");$q->execute([$qid,$customerId]);$approvedQuotation=$q->fetch();}
$pageTitle='Customer Ordering'; include __DIR__.'/includes/header.php';
?>
<section class="customer-hero"><div><p class="eyebrow light">ONLINE CATALOG</p><h2>Welcome, <?=htmlspecialchars($customer['customer_name'])?>.</h2><p>Browse products like an online store. View the product, details, price, stock, and image before sending your order request.</p></div><div class="customer-hero-mark">S</div></section>
<?php if($error):?><div class="alert error"><?=htmlspecialchars($error)?></div><?php endif;?>
<?php if($notice):?><div class="alert success"><?=htmlspecialchars($notice)?></div><?php endif;?>

<?php if($section==='order'): ?>
<div class="panel customer-shop-panel">
 <div class="panel-head"><div><p class="eyebrow dark">PRODUCT CATALOG</p><h2>Shop Products</h2><p class="muted">Choose a product to see its details before ordering.</p></div><a class="secondary-btn" href="customer_portal.php?section=quotations">My Quotations</a></div>
 <div class="customer-product-grid">
 <?php foreach($products as $p): $img=productImageUrl($p['image_path']??''); ?>
  <article class="customer-product-card">
   <div class="customer-product-image"><img src="<?=htmlspecialchars($img)?>" alt="<?=htmlspecialchars($p['product_name'])?>"></div>
   <div class="customer-product-body"><span class="product-code"><?=htmlspecialchars($p['product_code'])?></span><h3><?=htmlspecialchars($p['product_name'])?></h3><strong class="product-price">Price provided in quotation</strong><p class="product-stock <?=((int)$p['quantity']>0?'in-stock':'out-stock')?>"><?=((int)$p['quantity']>0)?((int)$p['quantity'].' '.htmlspecialchars($p['unit']).' available'):'Out of stock'?></p><button type="button" class="primary-btn full" data-open-modal="product<?=htmlspecialchars($p['product_code'])?>">View Details</button></div>
  </article>
  <div class="modal-backdrop" id="product<?=htmlspecialchars($p['product_code'])?>" aria-hidden="true"><div class="product-detail-modal" role="dialog" aria-modal="true"><button class="modal-close" type="button" data-close-modal>×</button><div class="product-detail-grid"><div class="product-detail-image"><img src="<?=htmlspecialchars($img)?>" alt="<?=htmlspecialchars($p['product_name'])?>"></div><div class="product-detail-info"><span class="product-code"><?=htmlspecialchars($p['product_code'])?></span><h2><?=htmlspecialchars($p['product_name'])?></h2><div class="product-detail-price">Final price will appear in your quotation</div><div class="product-detail-meta"><div><span>Availability</span><b><?=((int)$p['quantity']>0)?'In Stock':'Out of Stock'?></b></div><div><span>Stock</span><b><?= (int)$p['quantity']?> <?=htmlspecialchars($p['unit'])?></b></div><div><span>Status</span><b><?=htmlspecialchars($p['status'])?></b></div></div><?php if((int)$p['quantity']>0): ?><form method="POST" class="entry-form"><input type="hidden" name="action" value="place_request"><input type="hidden" name="product_code" value="<?=htmlspecialchars($p['product_code'])?>"><label>Quantity<input type="number" name="quantity" min="1" max="<?= (int)$p['quantity']?>" value="1" required></label><label>Order Notes<textarea name="notes" placeholder="Optional instructions for Sales..."></textarea></label><button class="primary-btn full">Order This Product →</button></form><?php else: ?><div class="note-card"><b>Currently unavailable.</b><span>This product cannot be ordered until stock is available.</span></div><?php endif; ?></div></div></div></div>
 <?php endforeach; ?>
 <?php if(!$products): ?><div class="note-card"><b>No products available.</b><span>Sales has not received any active inventory products yet.</span></div><?php endif; ?>
 </div>
</div>
<?php endif; ?>

<?php if($section==='quotations'): ?>
<div class="panel"><div class="panel-head"><div><p class="eyebrow dark">MY ORDERS & QUOTATIONS</p><h2>Quotation Center</h2><p class="muted">Sales prepares the quotation, Sales Admin approves it, then you can approve or reject it.</p></div><a class="primary-btn" href="customer_portal.php?section=order">Continue Shopping</a></div>
<div class="customer-order-list">
<?php foreach($requests as $r): ?>
<div class="customer-order-card"><div class="customer-order-main"><img class="customer-order-thumb" src="<?=htmlspecialchars(productImageUrl($r['image_path']??''))?>" alt=""><div><span class="eyebrow dark"><?=htmlspecialchars($r['request_no'])?></span><h3><?=htmlspecialchars($r['product_name']?:$r['product_code'])?> × <?= (int)$r['quantity']?></h3><p class="muted">Requested <?=niceDate($r['request_date'])?></p></div></div><div class="customer-order-status"><span class="badge <?=badgeClass($r['status'])?>"><?=htmlspecialchars($r['status'])?></span><?php if($r['quotation_id']): ?><a class="table-action" href="customer_portal.php?section=quotations&quotation_id=<?= (int)$r['quotation_id'] ?>">Open</a><?php endif; ?></div></div>
<?php if($r['quotation_id'] && $qid===(int)$r['quotation_id']): ?>
<div class="quotation-customer-container"><div class="quotation-pdf-wrap"><iframe src="quotation_export.php?id=<?= (int)$r['quotation_id'] ?>" title="Sales Quotation PDF"></iframe></div><div class="quotation-decision"><p class="eyebrow dark">QUOTATION STATUS</p><h3><?=htmlspecialchars($r['quotation_no'])?></h3><strong><?=peso((float)$r['total_amount'])?></strong><p class="muted">Sales Admin: <?=htmlspecialchars($r['admin_status'])?> · Inventory: <?=htmlspecialchars($r['inventory_status'])?> · Valid until: <?= $r['valid_until']?niceDate($r['valid_until']):'—'?></p><?php if($r['admin_status']!=='Approved'): ?><div class="note-card"><b>Waiting for Sales Admin approval.</b><span>You can review the quotation, but customer approval will open after Sales Admin approves it.</span></div><?php elseif($r['customer_status']==='Pending'): ?><form method="POST" class="inline-actions"><input type="hidden" name="action" value="quotation_decision"><input type="hidden" name="quotation_id" value="<?= (int)$r['quotation_id'] ?>"><button class="primary-btn" name="decision" value="Approved" <?= $r['inventory_status']!=='Approved'?'disabled':'' ?>>Approve Quotation</button><button class="secondary-btn" name="decision" value="Rejected">Reject</button></form><?php elseif($r['customer_status']==='Approved'): ?><div class="note-card"><b>Quotation approved.</b><span>You may continue to checkout.</span></div><a class="primary-btn" href="customer_portal.php?section=checkout&quotation_id=<?= (int)$r['quotation_id'] ?>">Continue to Checkout</a><?php else: ?><div class="note-card"><b>Quotation rejected.</b><span>Sales can prepare another quotation if needed.</span></div><?php endif; ?></div></div>
<?php endif; ?>
<?php endforeach; ?>
<?php if(!$requests): ?><div class="note-card"><b>No order requests yet.</b><span>Start by shopping from the product catalog.</span></div><?php endif; ?></div></div>
<?php endif; ?>

<?php if($section==='checkout'): if(!$approvedQuotation && $qid){$q=$pdo->prepare("SELECT * FROM sales_quotations WHERE sq_id=? AND customer_id=?");$q->execute([$qid,$customerId]);$approvedQuotation=$q->fetch();} ?>
<div class="panel customer-checkout-panel combined-checkout"><div class="panel-head"><div><p class="eyebrow dark">CHECKOUT</p><h2>Check Details · Mode of Payment · Payment · Receipt</h2><p class="muted">All checkout information is kept in one container.</p></div></div>
<?php if(!$approvedQuotation || $approvedQuotation['customer_status']!=='Approved'): ?><div class="note-card"><b>No approved quotation selected.</b><span>Approve a quotation first.</span></div><a class="primary-btn" href="customer_portal.php?section=quotations">Back to Quotations</a><?php else: ?><div class="combined-checkout-grid"><section><span class="checkout-label">01 · CHECK DETAILS</span><div class="checkout-summary"><div><span>Quotation</span><strong><?=htmlspecialchars($approvedQuotation['quotation_no'])?></strong></div><div><span>Product</span><strong><?=htmlspecialchars($approvedQuotation['finished_good_code'])?></strong></div><div><span>Quantity</span><strong><?= (int)$approvedQuotation['quantity']?></strong></div><div class="checkout-total"><span>Total</span><strong><?=peso((float)$approvedQuotation['total_amount'])?></strong></div></div></section><section><span class="checkout-label">02 · MODE OF PAYMENT</span><form method="POST" class="entry-form"><input type="hidden" name="action" value="pay_quotation"><input type="hidden" name="quotation_id" value="<?= (int)$approvedQuotation['sq_id'] ?>"><label>Payment Method<select name="payment_method" required><option value="">Choose</option><?php foreach(['GCash','Bank Transfer','Cash on Delivery'] as $m): ?><option value="<?=$m?>" <?=$approvedQuotation['payment_method']===$m?'selected':''?>><?=$m?></option><?php endforeach; ?></select></label><label>03 · PAYMENT REFERENCE<input type="text" name="payment_reference" value="<?=htmlspecialchars($approvedQuotation['payment_reference']??'')?>" placeholder="Required for GCash / Bank Transfer"></label><button class="primary-btn">Save Payment & Generate Receipt</button></form></section><section class="receipt-mini"><span class="checkout-label">04 · RECEIPT</span><?php if($approvedQuotation['receipt_no']): ?><div class="receipt-paper"><div class="receipt-success">✓ PAYMENT RECORDED</div><div class="receipt-meta"><div><span>Receipt</span><b><?=htmlspecialchars($approvedQuotation['receipt_no'])?></b></div><div><span>Quotation</span><b><?=htmlspecialchars($approvedQuotation['quotation_no'])?></b></div><div><span>Status</span><b><?=htmlspecialchars($approvedQuotation['payment_status'])?></b></div></div><div class="receipt-lines"><div><span>Total</span><strong><?=peso((float)$approvedQuotation['total_amount'])?></strong></div></div></div><?php else: ?><div class="note-card"><b>Receipt will appear here.</b><span>Submit the payment details to generate it.</span></div><?php endif; ?></section></div><?php endif; ?></div>
<?php endif; ?>

<?php if($section==='availability'): ?><div class="panel"><div class="panel-head"><div><p class="eyebrow dark">INVENTORY REFERENCE</p><h2>Product Availability</h2><p class="muted">The same products shown in the catalog, including images and stock.</p></div><a class="primary-btn" href="customer_portal.php?section=order">Shop Now</a></div><div class="customer-product-grid compact-products"><?php foreach($products as $p): $img=productImageUrl($p['image_path']??''); ?><article class="customer-product-card"><div class="customer-product-image"><img src="<?=htmlspecialchars($img)?>" alt=""></div><div class="customer-product-body"><span class="product-code"><?=htmlspecialchars($p['product_code'])?></span><h3><?=htmlspecialchars($p['product_name'])?></h3><strong class="product-price">Price provided in quotation</strong><p class="product-stock <?=((int)$p['quantity']>0?'in-stock':'out-stock')?>"><?=((int)$p['quantity']>0)?((int)$p['quantity'].' '.htmlspecialchars($p['unit']).' available'):'Out of stock'?></p></div></article><?php endforeach; ?></div></div><?php endif; ?>
<?php include __DIR__.'/includes/footer.php'; ?>
