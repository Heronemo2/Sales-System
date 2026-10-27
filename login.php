<?php
session_start();
require_once __DIR__ . '/config/database.php';

if (!empty($_SESSION['user'])) {
    header('Location: dashboard.php');
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    $pdo = getDB();

    if ($pdo) {
        $stmt = $pdo->prepare("SELECT id, name, username, password, role FROM users WHERE username = ? LIMIT 1");
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            unset($user['password']);
            $_SESSION['user'] = $user;
            header('Location: dashboard.php');
            exit;
        }
    }

    $error = 'Invalid username or password.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login | Team Sales</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=DM+Serif+Display&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="login-page">
    <div class="login-card">
        <div class="login-brand">
            <div class="brand-logo large">S</div>
            <div>
                <strong>TEAM SALES</strong>
                <span>Sales Management System</span>
            </div>
        </div>

        <div class="login-heading">
            <p class="eyebrow">WELCOME BACK</p>
            <h1>Sign in to your account</h1>
            <p>Manage customers, quotations, orders, invoices, and sales returns in one place.</p>
        </div>

        <?php if ($error): ?>
            <div class="alert error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="POST" class="login-form">
            <label>Username
                <input type="text" name="username" placeholder="Enter username" required>
            </label>
            <label>Password
                <input type="password" name="password" placeholder="Enter password" required>
            </label>
            <button type="submit" class="primary-btn full">Sign In</button>
        </form>
    </div>
</body>
</html>
