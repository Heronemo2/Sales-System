<?php
/**
 * Connection settings for the Inventory team's product API.
 *
 * Team Sales does NOT read Inventory's database directly — every other
 * team exposes their own PHP endpoints, and we call those over HTTP.
 * We only ever pass/store a soft reference (product_code) on our side.
 *
 * ── HOW TO CONNECT TO THE REAL INVENTORY TEAM ──
 * Ask the Inventory team for the URL of their "check product" endpoint
 * and paste it below. If you're all on the same XAMPP/localhost machine
 * during development, it usually looks like:
 *
 *   http://localhost/inventory_team_folder/api/check_product.php
 *
 * If they're on a different machine on the same network:
 *
 *   http://192.168.1.23/inventory/api/check_product.php
 */

// TODO: replace with the Inventory team's real endpoint once they share it.
$INVENTORY_API_BASE = 'http://127.0.0.1/inventory/api/check_product.php';

// Seconds to wait for Inventory's server before giving up.
$INVENTORY_API_TIMEOUT = 5;

/**
 * If true and the Inventory endpoint above cannot be reached, we return
 * fake demo stock data instead of an error. This lets Sales keep working
 * and demoing during development even if Inventory's API isn't finished
 * yet or isn't running on your machine right now.
 *
 * Set this to false once Inventory's real API is confirmed working, so
 * you don't accidentally demo fake numbers.
 */
$INVENTORY_API_MOCK_FALLBACK = false;

// Local inventory table used for standalone XAMPP/demo operation.
// Set to false when the real Inventory team's API is connected.
$INVENTORY_USE_LOCAL_TABLE = true;
?>
