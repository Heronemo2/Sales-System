<?php
$current = basename($_SERVER['PHP_SELF']);

$customerManagement = ['customers.php'];
$corePages = ['quotations.php','orders.php','delivery.php','returns.php'];
$billingPages = ['invoices.php','receivables.php','collection.php','balance.php'];
$salesReport = ['reports.php'];

$dashboardSection = $_GET['section'] ?? '';
$activeSection = in_array($current, $billingPages, true) ? 'billing'
    : (in_array($current, $corePages, true) ? 'core'
    : (in_array($current, $customerManagement, true) ? 'customer'
    : (in_array($current, $salesReport, true) ? 'sales'
    : ($current === 'dashboard.php' && in_array($dashboardSection, ['customer', 'core', 'billing', 'sales'], true)
        ? $dashboardSection
        : ''))));
?>
<aside class="sidebar" id="sidebar">
    <button class="sidebar-close" id="sidebarClose" type="button" aria-label="Close sidebar">×</button>

    <div class="brand">
        <div class="brand-logo">S</div>
        <div class="brand-text">
            <strong>TEAM SALES</strong>
            <span>Sales Management</span>
        </div>
    </div>

    <nav class="nav-menu">
        <div class="nav-label">MAIN</div>

        <a class="section-nav <?= $current === 'dashboard.php' && $dashboardSection === '' ? 'active' : '' ?>"
           href="dashboard.php">
            <span class="nav-icon">D</span>
            <span>Dashboard</span>
            <span class="section-arrow">›</span>
        </a>

        <div class="nav-label">MODULES</div>

        <a class="section-nav <?= $activeSection === 'customer' ? 'active' : '' ?>"
           href="customers.php?section=customer">
            <span class="nav-icon">👥</span>
            <span>Customer Management</span>
            <span class="section-arrow">›</span>
        </a>

        <a class="section-nav <?= $activeSection === 'core' ? 'active' : '' ?>"
           href="quotations.php?section=core">
            <span class="nav-icon">▦</span>
            <span>Core Sales & Order Management</span>
            <span class="section-arrow">›</span>
        </a>

        <a class="section-nav <?= $activeSection === 'billing' ? 'active' : '' ?>"
           href="invoices.php?section=billing">
            <span class="nav-icon">₱</span>
            <span>Billing & Financial Accounting</span>
            <span class="section-arrow">›</span>
        </a>

        <div class="nav-label">Reports</div>

        <a class="section-nav <?= $activeSection === 'sales' ? 'active' : '' ?>"
           href="reports.php?section=sales">
            <span class="nav-icon">📈</span>
            <span>Sales Report</span>
            <span class="section-arrow">›</span>
        </a>
    </nav>

    <footer>
        <p style="text-align: center; font-family:'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; font-weight: bold ">DLSP 2026-2027</p>
    </footer>
</aside>
