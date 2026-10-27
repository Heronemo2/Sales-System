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

/**
 * Synchronize a Sales Order with its invoice and delivery status.
 * Completed means the order is BOTH fully paid AND delivered.
 */
function syncSalesOrderCompletion(PDO $pdo, int $orderId): void
{
    if ($orderId <= 0) {
        return;
    }

    $st = $pdo->prepare("
        SELECT
            EXISTS(
                SELECT 1
                FROM sales_invoices
                WHERE order_id = ?
                  AND status = 'Paid'
            ) AS is_paid,
            EXISTS(
                SELECT 1
                FROM deliveries
                WHERE order_id = ?
                  AND status = 'Delivered'
            ) AS is_delivered
    ");
    $st->execute([$orderId, $orderId]);
    $state = $st->fetch(PDO::FETCH_ASSOC) ?: [];

    $isPaid = (int)($state['is_paid'] ?? 0) === 1;
    $isDelivered = (int)($state['is_delivered'] ?? 0) === 1;

    if ($isPaid) {
        $pdo->prepare("
            UPDATE sales_orders
            SET payment_status = 'Paid',
                paid_at = COALESCE(paid_at, NOW())
            WHERE so_id = ?
              AND status <> 'Cancelled'
        ")->execute([$orderId]);
    }

    if ($isPaid && $isDelivered) {
        $pdo->prepare("
            UPDATE sales_orders
            SET status = 'Completed',
                payment_status = 'Paid',
                paid_at = COALESCE(paid_at, NOW())
            WHERE so_id = ?
              AND status <> 'Cancelled'
        ")->execute([$orderId]);
    }
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
 * Read one finished good directly from Inventory's finished_goods table.
 * Integration mapping: Sales product_code -> Inventory item_code.
 * Inventory stock comes from finished_goods.quantity.
 */
function checkInventoryProduct(string $productCode): array
{
    require_once __DIR__ . '/../config/inventory_db.php';

    $productCode = trim($productCode);

    if ($productCode === '') {
        return [
            'ok' => false,
            'error' => 'Product code is required.'
        ];
    }

    try {
        $inventoryDb = getInventoryDB();

        /*
         * Inventory System is the master product source.
         * A valid Team Sales product must exist in BOTH:
         *   items.item_code
         *   finished_goods.item_code
         *
         * finished_goods.quantity is kept by the Inventory System
         * triggers as the total of inventory.quantity across warehouses.
         */
        $stmt = $inventoryDb->prepare("
            SELECT
                i.id AS item_id,
                i.item_code,
                i.item_name,
                i.brand,
                i.size,
                i.unit,
                i.status AS item_status,
                fg.fg_id,
                fg.category_id,
                fg.description,
                fg.reorder_level,
                fg.status AS fg_status,
                fg.created_at,
                COALESCE(SUM(inv.quantity), 0) AS quantity
            FROM items i
            INNER JOIN finished_goods fg
                ON fg.item_code = i.item_code
            LEFT JOIN inventory inv
                ON inv.item_id = i.id
            WHERE i.item_code = ?
              AND i.item_type = 'Finished Good'
            GROUP BY
                i.id,
                i.item_code,
                i.item_name,
                i.brand,
                i.size,
                i.unit,
                i.status,
                fg.fg_id,
                fg.category_id,
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
                'error' => 'Product is not properly linked in the Inventory System. Check items and finished_goods for ' . $productCode . '.',
                'item_code' => $productCode
            ];
        }

        $status = ($product['item_status'] === 'Active' && $product['fg_status'] === 'Active')
            ? 'Active'
            : 'Inactive';

        return [
            'ok' => true,
            'item_id' => (int)$product['item_id'],
            'fg_id' => (int)$product['fg_id'],
            'item_code' => $product['item_code'],
            'item_name' => $product['item_name'],
            'brand' => $product['brand'] ?? '',
            'category_id' => $product['category_id'] ?? '',
            'size' => $product['size'] ?? '',
            'unit' => $product['unit'] ?? 'pcs',
            'quantity' => (float)($product['quantity'] ?? 0),
            'description' => $product['description'] ?? '',
            'reorder_level' => (float)($product['reorder_level'] ?? 0),
            'status' => $status,
            'created_at' => $product['created_at'] ?? null,
        ];

    } catch (Throwable $e) {
        return [
            'ok' => false,
            'error' => 'Could not connect to the Inventory database: ' . $e->getMessage()
        ];
    }
}

function getInventoryStockRequest(string $requestNumber): ?array
{
    $requestNumber = trim($requestNumber);

    if ($requestNumber === '') {
        return null;
    }

    try {
        require_once __DIR__ . '/../config/inventory_db.php';

        $inventoryDb = getInventoryDB();

        $stmt = $inventoryDb->prepare("
            SELECT
                r.id,
                r.request_number,
                r.status,
                r.destination,
                r.expected_receive_date,
                w.warehouse_name
            FROM requests r
            LEFT JOIN request_items ri
                ON ri.request_id = r.id
            LEFT JOIN warehouses w
                ON w.id = ri.warehouse_id
            WHERE r.request_number = ?
            LIMIT 1
        ");

        $stmt->execute([$requestNumber]);

        $request = $stmt->fetch(PDO::FETCH_ASSOC);

        return $request ?: null;

    } catch (Throwable $e) {
        return null;
    }
}

/**
 * Check the Team Sales stock that is actually available to Sales.
 * Inventory System remains the product/master source. Inventory stock-outs
 * approved by Inventory are imported into Team Sales inventory by
 * syncInventoryStockouts().
 */
function evaluateQuotationStock(string $productCode, int $quantity): array
{
    $inventoryProduct = checkInventoryProduct($productCode);

    if (!$inventoryProduct['ok']) {
        return [
            'ok' => false,
            'error' => $inventoryProduct['error'] ?? 'Inventory check failed.'
        ];
    }

    $salesDb = getDB();
    $teamSalesStock = getTeamSalesInventory($salesDb, $productCode);
    $available = (float)($teamSalesStock['stock_quantity'] ?? 0);

    $isActive =
        ($inventoryProduct['status'] ?? 'Active') === 'Active' &&
        (!$teamSalesStock || ($teamSalesStock['status'] ?? 'Active') === 'Active');

    $status = ($isActive && $available >= $quantity && $available > 0)
        ? 'Approved'
        : 'Pending';

    return [
        'ok' => true,
        'status' => $status,
        'stock' => $inventoryProduct,
        'available' => $available,
        'inventory_system_quantity' => (float)$inventoryProduct['quantity'],
        'team_sales_quantity' => $available,
        'team_sales_inventory' => $teamSalesStock
    ];
}
/**
 * Create a stock request in the existing Inventory request sheet.
 *
 * IMPORTANT:
 * This function DOES NOT stock out inventory.
 * Inventory System will handle the actual stock-out when its
 * own admin approves the request.
 */
function createInventoryStockRequest(
    string $productCode,
    float $requiredQuantity,
    string $recipientName = 'Team Sales',
    string $recipientGmail = '',
    string $remarks = ''
): array {
    require_once __DIR__ . '/../config/inventory_db.php';

    try {
        $inventoryDb = getInventoryDB();

        /*
         * 1. Get the finished good.
         */
        $stmt = $inventoryDb->prepare("
            SELECT
                fg_id,
                item_code,
                item_name,
                size,
                unit,
                quantity,
                status
            FROM finished_goods
            WHERE item_code = ?
            LIMIT 1
        ");

        $stmt->execute([trim($productCode)]);
        $product = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$product) {
            return [
                'ok' => false,
                'message' => 'Product was not found in Inventory.'
            ];
        }

        if (($product['status'] ?? '') !== 'Active') {
            return [
                'ok' => false,
                'message' => 'This product is inactive in Inventory.'
            ];
        }

        /*
         * 2. Calculate missing stock.
         */
        $available = (float)($product['quantity'] ?? 0);
        $missing = $requiredQuantity - $available;

        if ($missing <= 0) {
            return [
                'ok' => true,
                'already_available' => true,
                'message' => 'Enough stock is already available.',
                'available' => $available,
                'requested' => 0
            ];
        }

        /*
         * 3. Find the corresponding Inventory item.
         *
         * request_items requires items.id.
         */
        $stmt = $inventoryDb->prepare("
            SELECT
                id,
                item_name,
                size,
                unit
            FROM items
            WHERE item_code = ?
            LIMIT 1
        ");

        $stmt->execute([$product['item_code']]);
        $item = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$item) {
            return [
                'ok' => false,
                'message' => 'The finished good is not linked to an Inventory item.'
            ];
        }

        /*
         * 4. Find a warehouse.
         *
         * The existing Inventory request sheet requires warehouse_id.
         */
        $stmt = $inventoryDb->query("
            SELECT id, warehouse_name
            FROM warehouses
            ORDER BY id ASC
            LIMIT 1
        ");

        $warehouse = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$warehouse) {
            return [
                'ok' => false,
                'message' => 'No Inventory warehouse was found.'
            ];
        }

        /*
         * 5. Find an Inventory user to own the request.
         *
         * We are NOT changing the Inventory users table.
         * We only use an existing user ID.
         */
        $stmt = $inventoryDb->query("
            SELECT id
            FROM users
            WHERE role = 'ADMIN'
            ORDER BY id ASC
            LIMIT 1
        ");

        $inventoryUser = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$inventoryUser) {
            return [
                'ok' => false,
                'message' => 'No Inventory administrator was found.'
            ];
        }

        /*
         * 6. Create the request.
         */
        $inventoryDb->beginTransaction();

        try {

            $requestNumber =
                'REQ-SALES-' .
                date('YmdHis') .
                '-' .
                random_int(10, 99);

            $expectedReceiveDate = date(
                'Y-m-d',
                strtotime('+3 days')
            );

            /*
             * Request header.
             */
            $stmt = $inventoryDb->prepare("
                INSERT INTO requests (
                    request_number,
                    requested_by,
                    request_date,
                    status,
                    remarks,
                    recipient_name,
                    recipient_gmail,
                    destination,
                    expected_receive_date
                )
                VALUES (
                    ?,
                    ?,
                    CURDATE(),
                    'Pending',
                    ?,
                    ?,
                    ?,
                    'Sales',
                    ?
                )
            ");

            $requestRemarks = $remarks !== ''
                ? $remarks
                : 'Stock request from Team Sales.';

            $stmt->execute([
                $requestNumber,
                (int)$inventoryUser['id'],
                $requestRemarks,
                $recipientName,
                $recipientGmail,
                $expectedReceiveDate
            ]);

            $requestId = (int)$inventoryDb->lastInsertId();

            /*
             * Request item.
             */
            $stmt = $inventoryDb->prepare("
                INSERT INTO request_items (
                    request_id,
                    item_id,
                    item_name,
                    size,
                    warehouse_id,
                    quantity,
                    unit
                )
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");

            $stmt->execute([
                $requestId,
                (int)$item['id'],
                $item['item_name'],
                $item['size'] ?? '',
                (int)$warehouse['id'],
                $missing,
                $item['unit'] ?? $product['unit'] ?? 'pcs'
            ]);

            $inventoryDb->commit();

            return [
                'ok' => true,
                'request_id' => $requestId,
                'request_number' => $requestNumber,
                'available' => $available,
                'required' => $requiredQuantity,
                'requested' => $missing,
                'status' => 'Pending',
                'message' => 'Stock request was sent to Inventory.'
            ];

        } catch (Throwable $e) {

            if ($inventoryDb->inTransaction()) {
                $inventoryDb->rollBack();
            }

            throw $e;
        }

    } catch (Throwable $e) {

        return [
            'ok' => false,
            'message' => 'Could not create Inventory stock request: ' .
                         $e->getMessage()
        ];
    }
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
                $poNo, $quotationId, $quotation['customer_id'], $quotation['product_code'],
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

/**
 * Create an exact stock-out request in the Inventory System.
 * Team Sales never changes Inventory stock. Inventory's own Admin
 * approves the request and the Inventory System creates the stock_out.
 */
function createTeamSalesStockoutRequest(PDO $salesDb, string $productCode, float $quantity, string $reference = ''): array
{
    require_once __DIR__ . '/../config/inventory_db.php';

    $productCode = trim($productCode);
    if ($productCode === '' || $quantity <= 0) {
        throw new RuntimeException('A valid product code and quantity are required.');
    }

    $inventoryDb = getInventoryDB();

    // Team Sales uses one fixed request header.
    // Do not stop because an older REQ-SALES-* request is still pending;
    // new Sales items must be appended to REQ-SALES-001 instead.

    $itemStmt = $inventoryDb->prepare("
        SELECT id, item_code, item_name, size, unit, status
        FROM items
        WHERE item_code = ?
        LIMIT 1
    ");
    $itemStmt->execute([$productCode]);
    $item = $itemStmt->fetch(PDO::FETCH_ASSOC);

    if (!$item) {
        throw new RuntimeException("Product {$productCode} was not found in the Inventory System.");
    }
    if (($item['status'] ?? '') !== 'Active') {
        throw new RuntimeException("Product {$productCode} is inactive in the Inventory System.");
    }

    $warehouseStmt = $inventoryDb->query("
        SELECT id, warehouse_name
        FROM warehouses
        WHERE status = 'Active'
        ORDER BY id ASC
        LIMIT 1
    ");
    $warehouse = $warehouseStmt->fetch(PDO::FETCH_ASSOC);
    if (!$warehouse) {
        throw new RuntimeException('No active Inventory warehouse was found.');
    }

    // Team Sales is an external requester. NULL is valid for requested_by
    // and makes this request appear under Inventory System's Incoming Requests.
    $incomingRequesterId = null;

    $inventoryDb->beginTransaction();
    try {
        // Generate the next Team Sales request number.
        // Only numeric REQ-SALES-### values are part of this sequence.
        $nextStmt = $inventoryDb->query("
            SELECT COALESCE(MAX(CAST(SUBSTRING(request_number, 11) AS UNSIGNED)), 0) AS max_no
            FROM requests
            WHERE destination = 'Sales'
              AND request_number REGEXP '^REQ-SALES-[0-9]+$'
        ");
        $nextNumber = ((int)$nextStmt->fetchColumn()) + 1;
        $requestNumber = 'REQ-SALES-' . str_pad((string)$nextNumber, 3, '0', STR_PAD_LEFT);

        $stmt = $inventoryDb->prepare("
            INSERT INTO requests (
                request_number, requested_by, request_date, status, remarks,
                recipient_name, recipient_gmail, destination, expected_receive_date
            )
            VALUES (?, ?, CURDATE(), 'Pending', ?, 'Team Sales', 'team.sales@local', 'Sales', ?)
        ");
        $stmt->execute([
            $requestNumber,
            $incomingRequesterId,
            'Stock-out requested by Team Sales because Team Sales inventory is insufficient.' . ($reference !== '' ? ' Reference: ' . $reference : ''),
            date('Y-m-d', strtotime('+3 days'))
        ]);
        $requestId = (int)$inventoryDb->lastInsertId();

        $stmt = $inventoryDb->prepare("
            INSERT INTO request_items (
                request_id, item_id, item_name, size, warehouse_id, quantity, unit
            )
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $requestId,
            (int)$item['id'],
            $item['item_name'],
            $item['size'] ?? '',
            (int)$warehouse['id'],
            $quantity,
            $item['unit'] ?? 'pcs'
        ]);

        $inventoryDb->commit();

        // Keep a Team Sales-side record of the request.
        $salesDb->prepare("
            INSERT INTO inventory_stockout_approvals
            (inventory_request_id, inventory_request_number, product_code, product_name, quantity, unit, destination, request_status, requested_at, source_reference, remarks)
            VALUES (?, ?, ?, ?, ?, ?, 'Sales', 'Requested', NOW(), ?, ?)
        ")->execute([
            $requestId,
            $requestNumber,
            $item['item_code'],
            $item['item_name'],
            $quantity,
            $item['unit'] ?? 'pcs',
            $reference !== '' ? $reference : null,
            $reference !== '' ? 'Requested for ' . $reference : 'Requested from Inventory System.'
        ]);

        return [
            'ok' => true,
            'request_id' => $requestId,
            'request_number' => $requestNumber,
            'status' => 'Pending',
            'message' => 'Stock-out request sent to Inventory System.'
        ];
    } catch (Throwable $e) {
        if ($inventoryDb->inTransaction()) {
            $inventoryDb->rollBack();
        }
        throw $e;
    }
}

/**
 * Import Inventory System stock-outs for destination=Sales into the
 * Team Sales approval queue. No Team Sales stock is changed here.
 */
function syncInventoryStockouts(PDO $salesDb): int
{
    require_once __DIR__ . '/../config/inventory_db.php';
    $inventoryDb = getInventoryDB();

    // Reflect Inventory System decisions back to Team Sales.
    $requestRows = $inventoryDb->query("
        SELECT id, request_number, status, remarks
        FROM requests
        WHERE destination = 'Sales'
          AND request_number LIKE 'REQ-SALES-%'
        ORDER BY id DESC
    " )->fetchAll(PDO::FETCH_ASSOC);

    foreach ($requestRows as $requestRow) {
        if ($requestRow['status'] === 'Disapproved') {
            $salesDb->prepare("
                UPDATE inventory_stockout_approvals
                SET request_status='Rejected', remarks=CONCAT(COALESCE(remarks,''), ' Inventory System: ', ?), reviewed_at=NOW()
                WHERE inventory_request_id=? AND request_status='Requested'
            " )->execute([(string)($requestRow['remarks'] ?? 'Request disapproved.'), (int)$requestRow['id']]);
        }
    }

    $rows = $inventoryDb->query("
        SELECT
            so.id,
            so.stock_out_number,
            so.item_id,
            so.quantity,
            so.destination,
            so.reference_number,
            so.date,
            i.item_code,
            i.item_name,
            i.unit
        FROM stock_out so
        JOIN items i ON i.id = so.item_id
        LEFT JOIN requests r ON r.request_number = so.reference_number
        WHERE so.destination = 'Sales'
          AND (
                so.reference_number LIKE 'REQ-SALES-%'
                OR (r.id IS NOT NULL AND r.destination = 'Sales')
              )
        ORDER BY so.id DESC
    ")->fetchAll(PDO::FETCH_ASSOC);

    $inserted = 0;
    $stmt = $salesDb->prepare("
        INSERT INTO inventory_stockout_approvals
        (
            inventory_stock_out_id, stock_out_number, inventory_request_number,
            product_code, product_name, quantity, unit, destination,
            request_status, stocked_out_at, source_reference, remarks
        )
        VALUES (?, ?, ?, ?, ?, ?, ?, 'Sales', 'Stocked Out', ?, ?, ?)
        ON DUPLICATE KEY UPDATE
            stock_out_number = VALUES(stock_out_number),
            inventory_request_number = COALESCE(VALUES(inventory_request_number), inventory_request_number),
            product_code = VALUES(product_code),
            product_name = VALUES(product_name),
            quantity = VALUES(quantity),
            unit = VALUES(unit),
            stocked_out_at = VALUES(stocked_out_at)
    ");

    foreach ($rows as $row) {
        // If the Team Sales request exists, preserve its local request row.
        $existing = $salesDb->prepare("
            SELECT id, request_status
            FROM inventory_stockout_approvals
            WHERE inventory_stock_out_id = ?
               OR (
                    inventory_stock_out_id IS NULL
                    AND inventory_request_number = ?
                    AND product_code = ?
               )
            ORDER BY id DESC
            LIMIT 1
        ");
        $existing->execute([
            (int)$row['id'],
            $row['reference_number'] ?: null,
            $row['item_code']
        ]);
        $local = $existing->fetch(PDO::FETCH_ASSOC);

        if ($local) {
            $wasApproved = ($local['request_status'] ?? '') === 'Approved';
            if (!$wasApproved) {
                $salesDb->prepare("
                    UPDATE inventory_stockout_approvals
                    SET inventory_stock_out_id = ?,
                        stock_out_number = ?,
                        source_reference = COALESCE(source_reference, ?),
                        inventory_request_number = COALESCE(inventory_request_number, ?),
                        product_code = ?, product_name = ?, quantity = ?, unit = ?,
                        request_status = 'Approved', stocked_out_at = ?,
                        reviewed_at = NOW(), remarks = CONCAT(COALESCE(remarks,''), ' Inventory System approved and stock-out completed; quantity added to Team Sales Inventory.')
                    WHERE id = ?
                ")->execute([
                    (int)$row['id'],
                    $row['stock_out_number'],
                    $row['reference_number'] ?: null,
                    $row['reference_number'] ?: null,
                    $row['item_code'],
                    $row['item_name'],
                    $row['quantity'],
                    $row['unit'] ?? 'pcs',
                    $row['date'] . ' 00:00:00',
                    (int)$local['id']
                ]);

                $salesDb->prepare("
                    INSERT INTO inventory (product_code, product_name, unit, stock_quantity, status)
                    VALUES (?, ?, ?, ?, 'Active')
                    ON DUPLICATE KEY UPDATE
                        product_name = VALUES(product_name),
                        unit = VALUES(unit),
                        stock_quantity = stock_quantity + VALUES(stock_quantity),
                        status = 'Active',
                        last_updated = NOW()
                ")->execute([
                    $row['item_code'], $row['item_name'], $row['unit'] ?? 'pcs', (float)$row['quantity']
                ]);
            }
            continue;
        }

        $stmt->execute([
            (int)$row['id'],
            $row['stock_out_number'],
            $row['reference_number'] ?: null,
            $row['item_code'],
            $row['item_name'],
            $row['quantity'],
            $row['unit'] ?? 'pcs',
            $row['date'] . ' 00:00:00',
            $row['reference_number'] ?: null,
            'Imported from Inventory System stock-out.'
        ]);
        if ($stmt->rowCount() > 0) {
            $inserted++;
            $salesDb->prepare("
                INSERT INTO inventory (product_code, product_name, unit, stock_quantity, status)
                VALUES (?, ?, ?, ?, 'Active')
                ON DUPLICATE KEY UPDATE
                    product_name = VALUES(product_name),
                    unit = VALUES(unit),
                    stock_quantity = stock_quantity + VALUES(stock_quantity),
                    status = 'Active',
                    last_updated = NOW()
            " )->execute([
                $row['item_code'], $row['item_name'], $row['unit'] ?? 'pcs', (float)$row['quantity']
            ]);
            $salesDb->prepare("UPDATE inventory_stockout_approvals SET request_status='Approved', reviewed_at=NOW(), remarks=CONCAT(COALESCE(remarks,''), ' Inventory System approved and stock-out completed; quantity added to Team Sales Inventory.') WHERE inventory_stock_out_id=?")->execute([(int)$row['id']]);
        }
    }

    return $inserted;
}

/**
 * Convert an approved Inventory stock-out into Team Sales inventory.
 */
function approveTeamSalesStockout(PDO $salesDb, int $approvalId, int $userId): string
{
    $salesDb->beginTransaction();
    try {
        $stmt = $salesDb->prepare("
            SELECT *
            FROM inventory_stockout_approvals
            WHERE id = ?
              AND request_status = 'Stocked Out'
            FOR UPDATE
        ");
        $stmt->execute([$approvalId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            throw new RuntimeException('This Inventory stock-out is no longer waiting for approval.');
        }

        $upsert = $salesDb->prepare("
            INSERT INTO inventory
                (product_code, product_name, unit, stock_quantity, status)
            VALUES (?, ?, ?, ?, 'Active')
            ON DUPLICATE KEY UPDATE
                product_name = VALUES(product_name),
                unit = VALUES(unit),
                stock_quantity = stock_quantity + VALUES(stock_quantity),
                status = 'Active',
                last_updated = NOW()
        ");
        $upsert->execute([
            $row['product_code'],
            $row['product_name'],
            $row['unit'] ?? 'pcs',
            (float)$row['quantity']
        ]);

        $salesDb->prepare("
            UPDATE inventory_stockout_approvals
            SET request_status = 'Approved',
                reviewed_by = ?,
                reviewed_at = NOW(),
                remarks = CONCAT(COALESCE(remarks,''), ' Team Sales inventory received.')
            WHERE id = ?
        ")->execute([$userId, $approvalId]);

        $salesDb->commit();

        logActivity(
            $salesDb,
            'Approve',
            'Inventory Stockout',
            'Approved Inventory stock-out ' . ($row['stock_out_number'] ?? ''),
            $approvalId
        );

        return 'Inventory stock-out approved. ' . $row['quantity'] . ' ' . ($row['unit'] ?? 'pcs') . ' added to Team Sales Inventory.';
    } catch (Throwable $e) {
        if ($salesDb->inTransaction()) {
            $salesDb->rollBack();
        }
        throw $e;
    }
}

/**
 * Return Team Sales inventory for one product.
 */
function getTeamSalesInventory(PDO $salesDb, string $productCode): ?array
{
    $stmt = $salesDb->prepare("
        SELECT *
        FROM inventory
        WHERE product_code = ?
          AND status = 'Active'
        LIMIT 1
    ");
    $stmt->execute([trim($productCode)]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

/**
 * Deduct stock only from Team Sales inventory.
 * The Inventory System is never modified by this function.
 */
function deductTeamSalesInventory(PDO $salesDb, string $productCode, float $quantity): void
{
    $stmt = $salesDb->prepare("
        UPDATE inventory
        SET stock_quantity = stock_quantity - ?, last_updated = NOW()
        WHERE product_code = ?
          AND status = 'Active'
          AND stock_quantity >= ?
    ");
    $stmt->execute([$quantity, $productCode, $quantity]);

    if ($stmt->rowCount() !== 1) {
        $stock = getTeamSalesInventory($salesDb, $productCode);
        $available = (float)($stock['stock_quantity'] ?? 0);
        throw new RuntimeException(
            'Not enough Team Sales Inventory for ' . $productCode .
            '. Available: ' . number_format($available, 2) .
            ', Required: ' . number_format($quantity, 2) .
            '. Request an Inventory stock-out first.'
        );
    }
}

function convertOrderToDelivery(PDO $pdo, int $orderId): string
{
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
        $order = $st->fetch(PDO::FETCH_ASSOC);

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
        $quantity = (float)($order['quantity'] ?? 0);

        if ($productCode === '' || $quantity <= 0) {
            throw new RuntimeException('The Sales Order has no valid product code or quantity.');
        }

        // Team Sales owns this stock. Inventory System is replenishment only.
        deductTeamSalesInventory($pdo, $productCode, $quantity);

        $deliveryNo = nextDocNo($pdo, 'deliveries', 'DEL-', 700);

        $pdo->prepare("
            INSERT INTO deliveries
                (delivery_no, order_id, delivery_date, status, inventory_deducted)
            VALUES (?, ?, CURDATE(), 'Preparing', 1)
        ")->execute([$deliveryNo, $orderId]);

        $pdo->prepare("
            UPDATE sales_orders
            SET admin_status = 'Approved',
                status = 'Processing'
            WHERE so_id = ?
        ")->execute([$orderId]);

        $pdo->commit();

        logActivity(
            $pdo,
            'Approve',
            'Sales Order',
            'Approved ' . ($order['order_no'] ?? 'Sales Order') .
            ' -> ' . $deliveryNo . ' (Team Sales Inventory deducted)',
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
