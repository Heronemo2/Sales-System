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
    $user = $_SESSION['user'] ?? [
        'id' => 0,
        'name' => 'Martin',
        'username' => 'Martin',
        'role' => 'Sales Staff'
    ];
    if (empty($user['name']) && !empty($user['full_name'])) $user['name'] = $user['full_name'];
    if (empty($user['full_name']) && !empty($user['name'])) $user['full_name'] = $user['name'];
    return $user;
}

function requireSalesAdmin(): void {
    requireLogin();
    if (currentUser()['role'] !== 'Sales Admin') {
        header('Location: dashboard.php');
        exit;
    }
}

function requireSystemAdmin(): void {
    requireLogin();
    if (!in_array(currentUser()['role'], ['System Admin', 'Administrator'], true)) {
        header('Location: dashboard.php');
        exit;
    }
}

function requireSalesWorkspace(): void {
    requireLogin();
    if (in_array(currentUser()['role'], ['Sales Admin', 'System Admin', 'Administrator'], true)) {
        header('Location: ' . (in_array(currentUser()['role'], ['System Admin', 'Administrator'], true) ? 'system_admin.php' : 'admin_customers.php'));
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
