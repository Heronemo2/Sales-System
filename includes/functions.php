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
 * Builds a document number like QT-2005, SO-1009, INV-3011 using the
 * next auto_increment id of the table so numbers never collide.
 */
function nextDocNo(PDO $pdo, string $table, string $prefix, int $base = 0, int $pad = 0): string {
    $stmt = $pdo->query("SELECT AUTO_INCREMENT FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = " . $pdo->quote($table));
    $next = (int) ($stmt->fetchColumn() ?: 1);
    $number = $base + $next;
    return $pad > 0 ? $prefix . str_pad((string) $number, $pad, '0', STR_PAD_LEFT) : $prefix . $number;
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
 * Ask the Inventory team's API for product/stock data.
 * Sales stores only the returned product_code/product_id as a soft reference.
 */
function checkInventoryProduct(string $productCode): array {
    require_once __DIR__ . '/../config/inventory_api.php';

    global $INVENTORY_API_BASE, $INVENTORY_API_TIMEOUT, $INVENTORY_API_MOCK_FALLBACK, $INVENTORY_USE_LOCAL_TABLE;

    $productCode = trim($productCode);
    if ($productCode === '') {
        return ['ok' => false, 'error' => 'Product code is required.'];
    }

    // Standalone/demo mode: use the local inventory table in team_sales.
    // This makes the Sales system functional on XAMPP even when the
    // separate Inventory team's API is not running yet.
    if ($INVENTORY_USE_LOCAL_TABLE) {
        try {
            require_once __DIR__ . '/../config/database.php';
            $pdo = getDB();
            $stmt = $pdo->prepare("SELECT product_id, product_code, product_name, quantity, unit_price, unit, status FROM inventory_products WHERE product_code = ? LIMIT 1");
            $stmt->execute([$productCode]);
            $product = $stmt->fetch();

            if ($product) {
                $qty = (int)$product['quantity'];
                return [
                    'ok' => true,
                    'source' => 'local_inventory',
                    'product_id' => (string)$product['product_id'],
                    'product_code' => $product['product_code'],
                    'product_name' => $product['product_name'],
                    'available' => $product['status'] === 'Active' && $qty > 0,
                    'quantity' => $qty,
                    'unit_price' => (float)$product['unit_price'],
                    'unit' => $product['unit'],
                    'status' => $product['status']
                ];
            }

            return ['ok' => false, 'error' => 'Product code not found in Inventory.', 'product_code' => $productCode];
        } catch (Throwable $e) {
            // If the local table has not been installed yet, continue to
            // the external API path below instead of breaking the page.
        }
    }

    $url = $INVENTORY_API_BASE . '?product_code=' . urlencode($productCode);
    $ch = curl_init($url);
    if ($ch === false) {
        return ['ok' => false, 'error' => 'Could not initialize Inventory connection.'];
    }

    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => $INVENTORY_API_TIMEOUT,
        CURLOPT_CONNECTTIMEOUT => $INVENTORY_API_TIMEOUT,
        CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4,
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($response !== false && $httpCode >= 200 && $httpCode < 300) {
        $data = json_decode($response, true);
        if (is_array($data)) {
            return ['ok' => true, 'source' => 'inventory'] + $data;
        }
    }

    if ($httpCode === 404) {
        return [
            'ok' => false,
            'error' => 'Product not found in Inventory.',
            'detail' => $curlError,
            'http_code' => $httpCode,
            'url' => $url
        ];
    }

    if ($INVENTORY_API_MOCK_FALLBACK) {
        $seed = crc32($productCode);
        $qty = $seed % 25;
        return [
            'ok' => true,
            'source' => 'mock',
            'product_code' => $productCode,
            'product_id' => 'MOCK-' . strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $productCode), 0, 18)),
            'available' => $qty > 0,
            'quantity' => $qty,
            'unit_price' => 100 + ($seed % 900),
            'note' => 'Inventory API not reachable; mock data is being used.'
        ];
    }

    return ['ok' => false, 'error' => 'Could not reach Inventory service.', 'detail' => $curlError];
}

?>
