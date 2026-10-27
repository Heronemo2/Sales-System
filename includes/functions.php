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
?>
