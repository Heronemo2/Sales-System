<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

requireLogin();
if (!in_array(currentUser()['role'] ?? '', ['Sales Admin', 'Sales Staff'], true)) { header('Location: login.php'); exit; }

$pdo = getDB();
$pageTitle = 'Inventory Stockout Status';
$error = '';
$notice = '';

try {
    syncInventoryStockouts($pdo);
} catch (Throwable $e) {
    $error = 'Could not sync Inventory System stock-outs: ' . $e->getMessage();
}

/* Inventory System is the only authority for approving/disapproving stock requests. Team Sales only monitors the synchronized status here. */
/* Sync again after a request/approval so the page immediately reflects changes. */
try {
    syncInventoryStockouts($pdo);
} catch (Throwable $e) {
    if ($error === '') {
        $error = 'Could not refresh Inventory System stock-outs: ' . $e->getMessage();
    }
}

$stockouts = $pdo->query("
    SELECT *
    FROM inventory_stockout_approvals isa
    WHERE isa.destination = 'Sales'
      AND (
            isa.inventory_request_number LIKE 'REQ-SALES-%'
            OR isa.source_reference LIKE 'REQ-SALES-%'
          )
      /* Once Inventory has actually created a stock-out for the same
         Sales product/quantity, hide the old local Requested row.
         The Stocked Out row becomes the only row Sales needs to approve. */
      AND NOT (
            isa.request_status = 'Requested'
            AND EXISTS (
                SELECT 1
                FROM inventory_stockout_approvals so
                WHERE so.destination = 'Sales'
                  AND so.request_status IN ('Stocked Out', 'Approved')
                  AND so.stock_out_number IS NOT NULL
                  AND so.stock_out_number <> ''
                  AND so.product_code = isa.product_code
                  AND ABS(CAST(so.quantity AS DECIMAL(18,4)) - CAST(isa.quantity AS DECIMAL(18,4))) < 0.0001
                  AND DATE(COALESCE(so.stocked_out_at, so.requested_at)) >= DATE(isa.requested_at)
            )
      )
    ORDER BY
        CASE request_status
            WHEN 'Requested' THEN 1
            WHEN 'Rejected' THEN 2
            WHEN 'Approved' THEN 3
            WHEN 'Rejected' THEN 4
            ELSE 5
        END,
        id DESC
")->fetchAll();

$pendingApprovalCount = (int)$pdo->query("
    SELECT COUNT(*)
    FROM inventory_stockout_approvals
    WHERE destination = 'Sales'
      AND (inventory_request_number LIKE 'REQ-SALES-%' OR source_reference LIKE 'REQ-SALES-%')
      AND request_status = 'Stocked Out'
")->fetchColumn();

$requestedCount = (int)$pdo->query("
    SELECT COUNT(*)
    FROM inventory_stockout_approvals isa
    WHERE isa.destination = 'Sales'
      AND (isa.inventory_request_number LIKE 'REQ-SALES-%' OR isa.source_reference LIKE 'REQ-SALES-%')
      AND isa.request_status = 'Requested'
      AND NOT EXISTS (
          SELECT 1
          FROM inventory_stockout_approvals so
          WHERE so.destination = 'Sales'
            AND so.request_status IN ('Stocked Out', 'Approved')
            AND so.stock_out_number IS NOT NULL
            AND so.stock_out_number <> ''
            AND so.product_code = isa.product_code
            AND ABS(CAST(so.quantity AS DECIMAL(18,4)) - CAST(isa.quantity AS DECIMAL(18,4))) < 0.0001
            AND DATE(COALESCE(so.stocked_out_at, so.requested_at)) >= DATE(isa.requested_at)
      )
")->fetchColumn();

include __DIR__ . '/includes/header.php';
?>
<?php if ($error): ?>
    <div class="alert error"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>
<?php if ($notice): ?>
    <div class="alert success"><?= htmlspecialchars($notice) ?></div>
<?php endif; ?>

<section class="stats-grid" style="margin-top:18px;">
    <div class="stat-card">
        <div class="stat-icon red">!</div>
        <div><span>Stockout Status</span><strong><?= $pendingApprovalCount ?></strong><small>Stocked out by Inventory System</small></div>
    </div>
    <div class="stat-card">
        <div class="stat-icon dark">↗</div>
        <div><span>Requests in Inventory</span><strong><?= $requestedCount ?></strong><small>Waiting for Inventory approval</small></div>
    </div>
</section>

<section class="content-grid" style="grid-template-columns:1fr;margin-top:18px;">
    <div class="panel">
        <div class="panel-head">
            <div>
                <p class="eyebrow">INVENTORY SYSTEM → TEAM SALES</p>
                <h2 style="margin:0;">Inventory Stockout Status</h2>
                <p style="color:var(--muted);margin:5px 0 0;">
                    Inventory System is the only authority that approves and performs the stock-out. Team Sales only receives and monitors the synchronized result.
                </p>
            </div>
        </div>

        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>STOCKOUT NO.</th>
                        <th>REQUEST NO.</th>
                        <th>PRODUCT</th>
                        <th>QTY</th>
                        <th>DATE</th>
                        <th>STATUS</th>
                        <th>ACTION</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (!$stockouts): ?>
                    <tr><td colspan="7" style="text-align:center;padding:30px;">No Inventory System stock-outs have been received.</td></tr>
                <?php else: ?>
                    <?php foreach ($stockouts as $row): ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($row['stock_out_number'] ?? '—') ?></strong></td>
                        <td><?= htmlspecialchars($row['inventory_request_number'] ?? '—') ?></td>
                        <td>
                            <strong><?= htmlspecialchars($row['product_code']) ?></strong><br>
                            <small><?= htmlspecialchars($row['product_name']) ?></small>
                        </td>
                        <td><?= htmlspecialchars(rtrim(rtrim(number_format((float)$row['quantity'], 2, '.', ''), '0'), '.')) ?> <?= htmlspecialchars($row['unit']) ?></td>
                        <td><?= htmlspecialchars($row['stocked_out_at'] ?? $row['requested_at']) ?></td>
                        <td><span class="badge <?= badgeClass($row['request_status']) ?>"><?= htmlspecialchars($row['request_status']) ?></span></td>
                        <td>
                            <?php if ($row['request_status'] === 'Stocked Out'): ?>
                                <span class="badge warning">Inventory approved • Added to Team Sales Inventory</span>
                            <?php elseif ($row['request_status'] === 'Approved'): ?>
                                <span class="badge success">Added to Inventory</span>
                            <?php elseif ($row['request_status'] === 'Requested'): ?>
                                <span class="muted">Waiting for Inventory</span>
                            <?php else: ?>
                                <span class="muted">—</span>
                            <?php endif; ?>
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