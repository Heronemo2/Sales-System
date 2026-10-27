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
    $termsAccepted = isset($_POST['terms_accepted']) && $_POST['terms_accepted'] === '1';

    $pdo = getDB();

    if (!$termsAccepted) {
        $error = 'You must accept the Terms of Service before signing in.';
    } elseif ($pdo) {
        $stmt = $pdo->prepare("SELECT id, full_name AS name, username, password, role, avatar_path FROM users WHERE username = ? AND status = 'Active' LIMIT 1");
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            // Terms acceptance is required for this login. No additional
            // database table is used so the supplied sales schema remains unchanged.
            unset($user['password']);
            $_SESSION['user'] = $user;
            $_SESSION['terms_accepted'] = true;
            $_SESSION['terms_version'] = '1.0';
            $pdo->prepare('UPDATE users SET last_login = NOW() WHERE id = ?')->execute([(int)$user['id']]);
            try { $pdo->prepare('INSERT INTO activity_logs (user_id, action, module, record_id, description, created_at) VALUES (?,?,?,?,?,NOW())')->execute([(int)$user['id'], 'Sign In', 'Authentication', null, 'User signed in']); } catch (Throwable $e) {}

            if (in_array($user['role'], ['System Admin', 'Administrator'], true)) {
                header('Location: system_admin.php');
            } elseif ($user['role'] === 'Sales Admin') {
                header('Location: admin_customers.php');
            } else {
                header('Location: dashboard.php');
            }
            exit;
        }

        $error = 'Invalid username or password.';
    } else {
        $error = 'Unable to connect to the database.';
    }
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
    <link rel="icon" href="assets/img/logo.png">
</head>
<body class="login-page login-split-page">
    <main class="login-split">
    <section class="login-left">
    <div class="login-panel">
        <div class="login-brand">
            <div class="brand-logo large"><img src="assets/img/logo.png" alt=""></div>
            <div>
                <strong>Team Sales</strong>
                <span>Sales Management System</span>
            </div>
        </div>

        <div class="login-heading">
            <p class="eyebrow">WELCOME BACK</p>
            <h1>Sign in to your account</h1>
            <p>Manage Sales quotations, orders, invoices, deliveries, collections, and returns in one place.</p>
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
            <br><br>
            <div class="login-terms">
                <div style="font-weight:700; margin-bottom:6px;">
                    Terms of Service
                </div>

                <div id="termsPreview">
                    <p style="font-size:12px; line-height:1.5; margin:0 0 6px;">
                        This system is the property of [Company Name]
                    </p>
                    <button class="termsread" type="button" id="termsToggle">
                        Read full Terms
                    </button>
                </div>

                <div id="termsFull" style="display:none;">
                    <p style="font-size:12px; line-height:1.5; margin:0 0 6px;">
                        This system is the property of [Company Name]
                        and is restricted to authorized users for business purposes only.
                        By logging in, you agree to comply with the company's Information
                        Security and Acceptable Use Policies. Unauthorized access or misuse
                        is strictly prohibited and may result in disciplinary action up to
                        termination, as well as civil or criminal liability. Activities on
                        this system are monitored and logged.
                    </p>
                    <button class="termsread" type="button" id="termsHide"
                            style="border:0; background:none; padding:0; cursor:pointer; text-decoration:underline; font-size:12px;">
                        Show less
                    </button>
                </div>

                <label class="terms-agree">
                    <input type="checkbox" name="terms_accepted" value="1" required style="width:16px; height:16px; margin:0; flex-shrink:0;">
                    <span>I have read and agree to the Terms of Service.</span>
                </label>
            </div>


        </form>
    </div>
    </section>

    <aside class="login-right" aria-label="About the Sales Department">
        <div class="login-hero">
            <p class="hero-eyebrow">SALES DEPARTMENT</p>
            <h2>Every sale, from quotation to collection.</h2>
            <p class="hero-text">The Sales Department handles the full customer journey for our business: quoting, taking orders, delivering, billing, and collecting payment, all tracked in one system.</p>
            <ul class="hero-points">
                <li><span>01</span><div><b>Quotations &amp; Orders</b><small>Quotations, purchase orders and sales orders</small></div></li>
                <li><span>02</span><div><b>Delivery &amp; Returns</b><small>Track shipments and handle customer returns</small></div></li>
                <li><span>03</span><div><b>Billing &amp; Collections</b><small>Invoices, receivables and customer balances</small></div></li>
            </ul>
        </div>
    </aside>
    </main>

    <script>
    document.addEventListener('DOMContentLoaded', function () {
        const preview = document.getElementById('termsPreview');
        const full = document.getElementById('termsFull');
        const toggle = document.getElementById('termsToggle');
        const hide = document.getElementById('termsHide');

        if (toggle && hide && preview && full) {
            toggle.addEventListener('click', function () {
                preview.style.display = 'none';
                full.style.display = 'block';
            });

            hide.addEventListener('click', function () {
                full.style.display = 'none';
                preview.style.display = 'block';
            });
        }
    });
    </script>
</body>
</html>