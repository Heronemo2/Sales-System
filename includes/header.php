<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/auth.php';
requireLogin();
$user = currentUser();
$pageTitle = $pageTitle ?? 'Dashboard';
$currentPage = basename($_SERVER['PHP_SELF']);
$coreModules = [
    'quotations.php' => ['label' => 'Sales Quotation', 'icon' => '▤'],
    'orders.php' => ['label' => 'Sales Order', 'icon' => '▣'],
    'delivery.php' => ['label' => 'Delivery', 'icon' => '▱'],
    'returns.php' => ['label' => 'Sales Return', 'icon' => '↩'],
];
$billingModules = [
    'invoices.php' => ['label' => 'Sales Invoice', 'icon' => '▥'],
    'receivables.php' => ['label' => 'Accounts Receivable', 'icon' => '◷'],
    'collection.php' => ['label' => 'Collection', 'icon' => '▤'],
    'balance.php' => ['label' => 'Customer Balance', 'icon' => '₱'],
];
$activeModuleSection = array_key_exists($currentPage, $billingModules)
    ? 'billing'
    : (array_key_exists($currentPage, $coreModules) ? 'core' : '');
$initial = strtoupper(substr($user['name'] ?? 'S', 0, 1));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?> | Team Sales</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=DM+Serif+Display&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<div class="app-shell">
    <?php include __DIR__ . '/sidebar.php'; ?>
    <main class="main-content">
        <header class="topbar">
            <button class="mobile-menu" id="mobileMenu" aria-label="Open navigation">☰</button>
            <div>
                <p class="eyebrow">SALES DEPARTMENT</p>
                <h1><?= htmlspecialchars($pageTitle) ?></h1>
            </div>

            <div class="topbar-actions">
                <div class="profile-menu-wrap">
                    <button class="top-profile" id="profileMenuButton" type="button" aria-expanded="false">
                        <span class="avatar"><?= htmlspecialchars($initial) ?></span>
                        <span class="top-user-info">
                            <strong><?= htmlspecialchars($user['name']) ?></strong>
                            <span><?= htmlspecialchars($user['role']) ?></span>
                        </span>
                        <span class="profile-chevron">⌄</span>
                    </button>
                    <div class="profile-dropdown" id="profileDropdown">
                        <div class="profile-dropdown-head">
                            <span class="avatar large"><?= htmlspecialchars($initial) ?></span>
                            <div>
                                <strong><?= htmlspecialchars($user['name']) ?></strong>
                                <span>@<?= htmlspecialchars($user['username']) ?></span>
                            </div>
                        </div>
                        <div class="profile-dropdown-links">
                            <button type="button" class="profile-dropdown-link" id="openProfileModal">
                                <span>◉</span> Manage Profile
                            </button>
                            <a class="profile-dropdown-link" href="account_settings.php">
                                <span>⚙</span> Account Settings
                            </a>
                            <a class="profile-dropdown-link" href="help_center.php">
                                <span>?</span> Help Center
                            </a>
                        </div>
                        <div class="profile-dropdown-footer">
                            <a href="logout.php">↪ Logout</a>
                        </div>
                    </div>
                </div>
            </div>
        </header>

<?php if ($currentPage !== 'dashboard.php' && $currentPage !== 'customers.php' && $currentPage !== 'reports.php' && !in_array($currentPage, ['manage_profile.php','account_settings.php','help_center.php'], true)): ?>
<nav class="module-switcher" id="moduleSwitcher">
    <?php $modulesToShow = $activeModuleSection === 'billing' ? $billingModules : $coreModules; ?>
    <div class="module-links">
        <?php foreach ($modulesToShow as $file => $module): ?>
            <a class="module-link <?= $currentPage === $file ? 'active' : '' ?>" href="<?= htmlspecialchars($file) ?>">
                <span><?= $module['icon'] ?></span>
                <?= htmlspecialchars($module['label']) ?>
            </a>
        <?php endforeach; ?>
    </div>
</nav>
<?php endif; ?>

<!-- Manage Profile modal -->
<div class="modal-backdrop" id="profileModal" aria-hidden="true">
    <div class="profile-modal" role="dialog" aria-modal="true" aria-labelledby="profileModalTitle">
        <button class="modal-close" id="closeProfileModal" type="button" aria-label="Close">×</button>
        <div class="profile-modal-avatar"><?= htmlspecialchars($initial) ?></div>
        <p class="eyebrow">YOUR ACCOUNT</p>
        <h2 id="profileModalTitle">Manage Profile</h2>
        <p class="modal-subtitle">Update the basic information displayed in your Team Sales profile.</p>
        <form method="POST" action="manage_profile.php" class="profile-form">
            <label>Full Name
                <input type="text" name="name" value="<?= htmlspecialchars($user['name']) ?>" required>
            </label>
            <label>Username
                <input type="text" name="username" value="<?= htmlspecialchars($user['username']) ?>" required>
            </label>
            <div class="modal-actions">
                <button type="button" class="secondary-btn" id="cancelProfileModal">Cancel</button>
                <button type="submit" class="primary-btn">Save Changes</button>
            </div>
        </form>
        <a class="modal-page-link" href="manage_profile.php">Open full profile page →</a>
    </div>
</div>
