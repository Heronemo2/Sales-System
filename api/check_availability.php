<?php
/**
 * GET /api/check_availability.php?product_code=XXX
 * Sales-side proxy for the Inventory team's stock/product endpoint.
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
requireLogin();

header('Content-Type: application/json');

$data = checkInventoryProduct(trim($_GET['product_code'] ?? ''));
if (!$data['ok']) {
    http_response_code(str_contains($data['error'] ?? '', 'not found') ? 404 : 502);
}
echo json_encode($data);