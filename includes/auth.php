<?php
if (session_status() === PHP_SESSION_NONE) {
    session_name('TEAM_SALES_SESSID');
    session_start();
}

function requireLogin(): void {
    if (empty($_SESSION['user'])) {
        header('Location: login.php');
        exit;
    }
}

function currentUser(): array {
    $user = $_SESSION['user'] ?? [
        'id' => 0,
        'name' => 'Martin',
        'username' => 'Martin',
        'role' => 'Sales Staff'
    ];
    if (empty($user['id']) && !empty($user['user_id'])) $user['id'] = (int)$user['user_id'];
    if (empty($user['user_id']) && !empty($user['id'])) $user['user_id'] = (int)$user['id'];
    if (empty($user['name']) && !empty($user['full_name'])) $user['name'] = $user['full_name'];
    if (empty($user['full_name']) && !empty($user['name'])) $user['full_name'] = $user['name'];
    return $user;
}

function requireCustomer(): void {
    requireLogin();
    if (currentUser()['role'] !== 'Customer') {
        header('Location: dashboard.php');
        exit;
    }
}

function requireSalesAdmin(): void {
    requireLogin();
    if (currentUser()['role'] !== 'Sales Admin') {
        header('Location: login.php');
        exit;
    }
}

function requireSalesWorkspace(): void {
    requireLogin();
    if (currentUser()['role'] !== 'Sales Staff') {
        header('Location: login.php');
        exit;
    }
}

function logActivity(PDO $pdo, string $action, string $module, ?string $description = null, ?int $recordId = null): void {
    try {
        $user = currentUser();
        $stmt = $pdo->prepare('INSERT INTO activity_logs (user_id, action, module, record_id, description, created_at) VALUES (?,?,?,?,?,NOW())');
        $stmt->execute([(int)($user['id'] ?? 0), $action, $module, $recordId, $description]);
    } catch (Throwable $e) {
        // Activity logging must never stop a sales transaction.
    }
}
?>
