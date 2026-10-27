<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function requireLogin(): void {
    if (empty($_SESSION['user'])) {
        header('Location: login.php');
        exit;
    }
}

function currentUser(): array {
    return $_SESSION['user'] ?? [
        'name' => 'Martin',
        'username' => 'Martin',
        'role' => 'Sales Staff'
    ];
}
?>
