<?php
/**
 * Shared helpers used across the module pages so every page formats
 * money, dates, and generated document numbers the same way.
 */

function peso(float $amount): string {
    return '₱' . number_format($amount, 2);
}

function niceDate(?string $date): string {
    if (!$date) return '—';
    $ts = strtotime($date);
    return $ts ? date('M j, Y', $ts) : $date;
}

/**
 * Builds a document number like QT-2005, SO-1009, INV-3011.
 * Uses MAX(<primary key>)+1 (not information_schema, which can return a stale
 * AUTO_INCREMENT) and skips any number that already exists, so a duplicate
 * document number can never make an INSERT fail.
 */
function nextDocNo(PDO $pdo, string $table, string $prefix, int $base = 0, int $pad = 0): string {
    $columns = [
        'sales_quotations' => 'quotation_no',
        'purchase_orders'  => 'po_no',
        'sales_orders'     => 'order_no',
        'deliveries'       => 'delivery_no',
        'sales_invoices'   => 'invoice_no',
        'sales_returns'    => 'return_no',
    ];
    $column = $columns[$table] ?? null;
    $idColumns = [
        'sales_quotations' => 'sq_id',
        'purchase_orders'  => 'po_id',
        'sales_orders'     => 'so_id',
        'deliveries'       => 'del_id',
        'sales_invoices'   => 'inv_id',
        'sales_returns'    => 'ret_id',
        'customers'        => 'cus_id',
        'collections'      => 'col_id',
    ];
    $idColumn = $idColumns[$table] ?? 'id';
    $next = (int) $pdo->query("SELECT COALESCE(MAX(`{$idColumn}`),0)+1 FROM `{$table}`")->fetchColumn();

    do {
        $number = $base + $next;
        $docNo = $pad > 0 ? $prefix . str_pad((string) $number, $pad, '0', STR_PAD_LEFT) : $prefix . $number;
        $taken = false;
        if ($column) {
            $chk = $pdo->prepare("SELECT 1 FROM `{$table}` WHERE `{$column}` = ? LIMIT 1");
            $chk->execute([$docNo]);
            $taken = (bool) $chk->fetchColumn();
        }
        $next++;
    } while ($taken);

    return $docNo;
}

function badgeClass(string $status): string {
    $status = strtolower($status);
    return match (true) {
        in_array($status, ['active', 'approved', 'completed', 'delivered', 'paid', 'current'], true) => 'success',
        in_array($status, ['pending', 'processing', 'partially paid', 'preparing', 'in transit'], true) => 'warning',
        in_array($status, ['rejected', 'cancelled', 'overdue', 'unpaid'], true) => 'danger',
        in_array($status, ['invoiced'], true) => 'info',
        default => 'neutral',
    };
}

/**
 * Read one finished good directly from the Inventory database.
 * The finished_goods row identifies the item through item_id; stock and
 * price are read from the related items row when those columns exist.
 */
function checkInventoryProduct(string $productCode): array {
    require_once __DIR__ . '/../config/inventory_db.php';

    $productCode = trim($productCode);

    if ($productCode === '') {
        return [
            'ok' => false,
            'error' => 'Finished good code is required.'
        ];
    }

    try {
        $inventoryDb = getInventoryDB();

        /*
         * Team Inventory v1.39 structure:
         * finished_goods -> item_id -> items
         * inventory -> item_id + warehouse_id + quantity
         *
         * Stock is NOT stored in items. It is stored in inventory,
         * so stock must be SUM(inventory.quantity).
         */
        $stmt = $inventoryDb->prepare("
            SELECT
                fg.fg_id AS id,
                fg.item_id,
                fg.finished_good_code,
                fg.product_name,
                fg.brand,
                fg.size,
                fg.unit,
                fg.description,
                fg.reorder_level,
                fg.status,
                fg.created_at,
                COALESCE(SUM(inv.quantity), 0) AS quantity
            FROM finished_goods fg
            LEFT JOIN inventory inv
                ON inv.item_id = fg.item_id
            WHERE fg.finished_good_code = ?
            GROUP BY
                fg.fg_id,
                fg.item_id,
                fg.finished_good_code,
                fg.product_name,
                fg.brand,
                fg.size,
                fg.unit,
                fg.description,
                fg.reorder_level,
                fg.status,
                fg.created_at
            LIMIT 1
        ");

        $stmt->execute([$productCode]);
        $product = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$product) {
            return [
                'ok' => false,
                'error' => 'Product not found in Inventory.',
                'finished_good_code' => $productCode
            ];
        }

        return [
            'ok' => true,
            'id' => (int)$product['id'],
            'item_id' => (int)$product['item_id'],
            'finished_good_code' => $product['finished_good_code'],
            'product_name' => $product['product_name'],
            'brand' => $product['brand'] ?? '',
            'size' => $product['size'] ?? '',
            'unit' => $product['unit'] ?? 'pcs',
            'description' => $product['description'] ?? '',
            'reorder_level' => (float)($product['reorder_level'] ?? 0),
            'status' => $product['status'] ?? 'Active',
            'created_at' => $product['created_at'] ?? null,
            'quantity' => (float)($product['quantity'] ?? 0),

            // Sales staff enters the agreed unit price when creating a quotation.
            'unit_price' => null,
        ];

    } catch (Throwable $e) {
        return [
            'ok' => false,
            'error' => 'Could not connect to the Inventory database: ' . $e->getMessage()
        ];
    }
}

/**
 * Decide a quotation's Inventory status from the inventory table.
 * Returns ['ok'=>bool, 'error'=>?, 'status'=>'Approved'|'Rejected', 'stock'=>array]
 */
function evaluateQuotationStock(string $productCode, int $quantity): array {
    $stock = checkInventoryProduct($productCode);
    if (!$stock['ok']) {
        return ['ok' => false, 'error' => $stock['error'] ?? 'Inventory check failed.'];
    }
    $available = (int) ($stock['quantity'] ?? 0);
    $isActive  = !isset($stock['status']) || $stock['status'] === 'Active';
    $status    = ($isActive && $available >= $quantity && $available > 0) ? 'Approved' : 'Rejected';
    return ['ok' => true, 'status' => $status, 'stock' => $stock, 'available' => $available];
}

/**
 * Customer-approved quotation -> Purchase Order.
 * Customer approval is the only approval required for a quotation.
 */
function createPurchaseOrderFromQuotation(PDO $pdo, int $quotationId): ?string {
    $q = $pdo->prepare("SELECT * FROM sales_quotations WHERE sq_id = ? AND customer_status = 'Approved'");
    $q->execute([$quotationId]);
    $quotation = $q->fetch();
    if (!$quotation) return null;

    $exists = $pdo->prepare('SELECT po_no FROM purchase_orders WHERE quotation_id = ? LIMIT 1');
    $exists->execute([$quotationId]);
    $existing = $exists->fetchColumn();
    if ($existing) return $existing;

    $pdo->beginTransaction();
    try {
        $poNo = nextDocNo($pdo, 'purchase_orders', 'PO-', 4000);
        $pdo->prepare("INSERT INTO purchase_orders
            (po_no, quotation_id, customer_id, product_code, inventory_product_id, quantity, total_amount, order_date, status)
            VALUES (?,?,?,?,?,?,?,CURDATE(),'Open')")
            ->execute([
                $poNo, $quotationId, $quotation['customer_id'], $quotation['finished_good_code'],
                $quotation['fg_id'], $quotation['quantity'], $quotation['total_amount'],
            ]);

        if (!empty($quotation['source_request_id'])) {
            $pdo->prepare("UPDATE customer_order_requests SET status='Converted', quotation_id=? WHERE req_id=?")
                ->execute([$quotationId, $quotation['source_request_id']]);
        }

        $pdo->commit();
        logActivity($pdo, 'Create', 'Purchase Order', 'Auto-created ' . $poNo . ' from customer-approved quotation', (int) $pdo->lastInsertId());
        return $poNo;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
}

/**
 * Sales Admin approved a Sales Order -> convert it to a Delivery and deduct
 * the quantity from Inventory. Everything happens in ONE transaction: if
 * stock is short, nothing is changed and an Exception is thrown.
 * Returns the new delivery number.
 */
function convertOrderToDelivery(PDO $pdo, int $orderId): string {
    $pdo->beginTransaction();

    try {
        $st = $pdo->prepare("
            SELECT *
            FROM sales_orders
            WHERE so_id = ?
              AND admin_status = 'Pending'
            FOR UPDATE
        ");
        $st->execute([$orderId]);
        $order = $st->fetch();

        if (!$order) {
            throw new RuntimeException('Sales order is no longer awaiting approval.');
        }

        $dup = $pdo->prepare("
            SELECT delivery_no
            FROM deliveries
            WHERE order_id = ?
            LIMIT 1
        ");
        $dup->execute([$orderId]);

        if ($dup->fetchColumn()) {
            throw new RuntimeException('A delivery already exists for this order.');
        }

        $productCode = trim((string)($order['product_code'] ?? ''));
        $quantity = (int)($order['quantity'] ?? 0);

        if ($productCode === '' || $quantity <= 0) {
            throw new RuntimeException('The Sales Order has no valid product code or quantity.');
        }

        /*
         * Connect directly to Team Inventory.
         * Stock lives in inventory.quantity, not items.quantity.
         */
        require_once __DIR__ . '/../config/inventory_db.php';
        $inventoryDb = getInventoryDB();
        $inventoryDb->beginTransaction();

        try {
            $fg = $inventoryDb->prepare("
                SELECT
                    fg.fg_id,
                    fg.item_id,
                    fg.finished_good_code,
                    fg.status,
                    COALESCE(SUM(inv.quantity), 0) AS available_quantity
                FROM finished_goods fg
                LEFT JOIN inventory inv
                    ON inv.item_id = fg.item_id
                WHERE fg.finished_good_code = ?
                GROUP BY
                    fg.fg_id,
                    fg.item_id,
                    fg.finished_good_code,
                    fg.status
                LIMIT 1
                FOR UPDATE
            ");
            $fg->execute([$productCode]);
            $finishedGood = $fg->fetch(PDO::FETCH_ASSOC);

            if (!$finishedGood || $finishedGood['status'] !== 'Active') {
                throw new RuntimeException(
                    'Finished good was not found or is inactive in Inventory.'
                );
            }

            if ((float)$finishedGood['available_quantity'] < $quantity) {
                throw new RuntimeException(
                    'Not enough Inventory stock for ' . $productCode .
                    '. Available: ' . $finishedGood['available_quantity'] .
                    ', Required: ' . $quantity . '.'
                );
            }

            /*
             * Deduct from warehouse rows, starting with the first warehouse
             * that has stock. This keeps the Inventory database as the source
             * of truth for stock.
             */
            $stockRows = $inventoryDb->prepare("
                SELECT id, quantity
                FROM inventory
                WHERE item_id = ?
                  AND quantity > 0
                ORDER BY warehouse_id ASC, id ASC
                FOR UPDATE
            ");
            $stockRows->execute([(int)$finishedGood['item_id']]);
            $stockRows = $stockRows->fetchAll(PDO::FETCH_ASSOC);

            $remaining = $quantity;

            foreach ($stockRows as $stockRow) {
                if ($remaining <= 0) {
                    break;
                }

                $take = min(
                    $remaining,
                    (float)$stockRow['quantity']
                );

                $deduct = $inventoryDb->prepare("
                    UPDATE inventory
                    SET quantity = quantity - ?
                    WHERE id = ?
                      AND quantity >= ?
                ");

                $deduct->execute([
                    $take,
                    (int)$stockRow['id'],
                    $take
                ]);

                if ($deduct->rowCount() !== 1) {
                    throw new RuntimeException(
                        'Inventory changed while processing the delivery. Please try again.'
                    );
                }

                $remaining -= $take;
            }

            if ($remaining > 0) {
                throw new RuntimeException(
                    'Inventory deduction could not be completed.'
                );
            }

            $inventoryDb->commit();

        } catch (Throwable $inventoryException) {
            if ($inventoryDb->inTransaction()) {
                $inventoryDb->rollBack();
            }

            throw $inventoryException;
        }

        $deliveryNo = nextDocNo(
            $pdo,
            'deliveries',
            'DEL-',
            700
        );

        $pdo->prepare("
            INSERT INTO deliveries
            (
                delivery_no,
                order_id,
                delivery_date,
                status,
                inventory_deducted
            )
            VALUES
            (?, ?, CURDATE(), 'Preparing', 1)
        ")->execute([
            $deliveryNo,
            $orderId
        ]);

        $pdo->prepare("
            UPDATE sales_orders
            SET
                admin_status = 'Approved',
                status = 'Processing'
            WHERE so_id = ?
        ")->execute([$orderId]);

        $pdo->commit();

        logActivity(
            $pdo,
            'Approve',
            'Sales Order',
            'Approved ' . $order['order_no'] .
            ' -> ' . $deliveryNo .
            ' (Inventory deducted)',
            $orderId
        );

        return $deliveryNo;

    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        throw $e;
    }
}

?>
