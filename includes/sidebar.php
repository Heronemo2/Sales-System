<?php
$current = basename($_SERVER['PHP_SELF']);
<<<<<<< HEAD
$user = currentUser();
$userRole = $user['role'] ?? '';
$corePages = ['quotations.php','purchase_orders.php','orders.php','delivery.php','returns.php'];
$billingPages = ['invoices.php','customer_account.php'];
$salesReport = ['reports.php'];
$inventoryPages = ['inventory.php','inventory_stockout_approval.php'];
=======
<<<<<<< HEAD
$userRole = currentUser()['role'] ?? 'Sales Staff';

$corePages = ['quotations.php','purchase_orders.php','orders.php','delivery.php','returns.php'];
$billingPages = ['invoices.php','receivables.php','collection.php','balance.php'];
=======
$user = currentUser();
$userRole = $user['role'] ?? 'Sales Staff';

$corePages = ['quotations.php','purchase_orders.php','orders.php','delivery.php','returns.php'];
$billingPages = ['invoices.php','customer_account.php'];
>>>>>>> origin/main
$salesReport = ['reports.php'];

$dashboardSection = $_GET['section'] ?? '';
>>>>>>> origin/main
$activeSection = '';
if (in_array($current, $billingPages, true)) $activeSection = 'billing';
elseif (in_array($current, $corePages, true)) $activeSection = 'core';
elseif (in_array($current, $salesReport, true)) $activeSection = 'sales';
<<<<<<< HEAD
elseif (in_array($current, $inventoryPages, true)) $activeSection = 'inventory';
$isSalesAdmin = $userRole === 'Sales Admin';
$isSalesStaff = $userRole === 'Sales Staff';
=======
<<<<<<< HEAD
elseif ($current === 'dashboard.php' && in_array($dashboardSection, ['customer','core','billing','sales'], true)) $activeSection = $dashboardSection;
$isSystemAdmin = in_array($userRole, ['System Admin','Administrator'], true);
$isSalesAdmin = $userRole === 'Sales Admin';
?>
<aside class="sidebar" id="sidebar">
    <button class="sidebar-close" id="sidebarClose" type="button" aria-label="Close sidebar">×</button>

    <div class="brand">
        <div class="brand-logo"><img src="assets/img/logo.png" alt=""></div>
        <div class="brand-text">
            <strong>TEAM SALES</strong>
            <span>Sales Management</span>
        </div>
    </div>

    <nav class="nav-menu">
<?php if (!$isSalesAdmin): ?>
        <div class="nav-label">MAIN</div>
        <?php if ($isSystemAdmin): ?>
        <a class="section-nav <?= $current === 'system_admin.php' ? 'active' : '' ?>" href="system_admin.php">
            <span class="nav-icon">D</span><span>System Dashboard</span><span class="section-arrow">›</span>
        </a>
        <?php else: ?>
        <a class="section-nav <?= $current === 'dashboard.php' && $dashboardSection === '' ? 'active' : '' ?>" href="dashboard.php">
            <span class="nav-icon">D</span><span>Dashboard</span><span class="section-arrow">›</span>
        </a>
        <?php endif; ?>
<?php endif; ?>

<?php if ($isSalesAdmin): ?>
        <div class="nav-label">SALES ADMINISTRATION</div>
        <a class="section-nav <?= $current === 'admin_customers.php' ? 'active' : '' ?>" href="admin_customers.php"><span class="nav-icon">C</span><span>Customer Management</span><span class="section-arrow">›</span></a>
        <a class="section-nav <?= $current === 'admin_approvals.php' ? 'active' : '' ?>" href="admin_approvals.php"><span class="nav-icon">✓</span><span>Approvals</span><span class="section-arrow">›</span></a>
<?php elseif ($isSystemAdmin): ?>
        <div class="nav-label">SYSTEM ADMINISTRATION</div>
        <a class="section-nav <?= $current === 'system_activity.php' ? 'active' : '' ?>" href="system_activity.php"><span class="nav-icon">A</span><span>User Activity</span><span class="section-arrow">›</span></a>
<?php else: ?>
        <div class="nav-label">MODULES</div>

        <a class="section-nav <?= $activeSection === 'core' ? 'active' : '' ?>" href="quotations.php?section=core">
            <span class="nav-icon">C</span><span>Core Sales & Order Management</span><span class="section-arrow">›</span>
        </a>
        <a class="section-nav <?= $current === 'inventory.php' ? 'active' : '' ?>" href="inventory.php">
            <span class="nav-icon">I</span><span>Inventory Reference</span><span class="section-arrow">›</span>
        </a>
        <a class="section-nav <?= $activeSection === 'billing' ? 'active' : '' ?>" href="invoices.php?section=billing">
            <span class="nav-icon">B</span><span>Billing & Financial Accounting</span><span class="section-arrow">›</span>
        </a>

        <div class="nav-label">REPORTS</div>
        <a class="section-nav <?= $activeSection === 'sales' ? 'active' : '' ?>" href="reports.php?section=sales">
            <span class="nav-icon">S</span><span>Sales Report</span><span class="section-arrow">›</span>
        </a>
<?php endif; ?>
    </nav>

    <footer>
        <p style="text-align: center; font-family:'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; font-weight: bold ">DLSP 2026-2027</p>
    </footer>
</aside>
=======

$isSalesAdmin = $userRole === 'Sales Admin';
>>>>>>> origin/main
$isCustomer = $userRole === 'Customer';
?>
<?php if (!$isCustomer): ?>
<aside class="sidebar" id="sidebar">
    <button class="sidebar-close" id="sidebarClose" type="button" aria-label="Close sidebar">×</button>
    <div class="brand">
        <div class="brand-logo"><img src="assets/img/logo.png" alt=""></div>
        <div class="brand-text"><strong>TEAM SALES</strong><span>Sales Management</span></div>
    </div>
    <nav class="nav-menu">
<<<<<<< HEAD
        <div class="nav-label">MAIN</div>
        <a class="section-nav <?= $current === 'dashboard.php' ? 'active' : '' ?>" href="dashboard.php"><span class="nav-icon">D</span><span>Dashboard</span><span class="section-arrow">›</span></a>

        <?php if ($isSalesStaff): ?>
            <div class="nav-label">SALES</div>
            <a class="section-nav <?= $activeSection === 'core' ? 'active' : '' ?>" href="quotations.php?section=core"><span class="nav-icon">C</span><span>Core Sales & Order Management</span><span class="section-arrow">›</span></a>
            <div class="nav-label">INVENTORY</div>
            <a class="section-nav <?= $current === 'inventory.php' ? 'active' : '' ?>" href="inventory.php"><span class="nav-icon">I</span><span>Inventory</span><span class="section-arrow">›</span></a>
            <a class="section-nav <?= $current === 'inventory_stockout_approval.php' ? 'active' : '' ?>" href="inventory_stockout_approval.php"><span class="nav-icon">✓</span><span>Inventory Stockout Approval</span><span class="section-arrow">›</span></a>
            <div class="nav-label">BILLING</div>
            <a class="section-nav <?= $activeSection === 'billing' ? 'active' : '' ?>" href="customer_account.php?section=billing"><span class="nav-icon">B</span><span>Billing & Financial Accounting</span><span class="section-arrow">›</span></a>
            <div class="nav-label">REPORTS</div>
            <a class="section-nav <?= $activeSection === 'sales' ? 'active' : '' ?>" href="reports.php?section=sales"><span class="nav-icon">S</span><span>Sales Report</span><span class="section-arrow">›</span></a>
        <?php elseif ($isSalesAdmin): ?>
            <div class="nav-label">ADMINISTRATION</div>
            <a class="section-nav <?= $current === 'admin_customers.php' ? 'active' : '' ?>" href="admin_customers.php"><span class="nav-icon">C</span><span>Customer Management</span><span class="section-arrow">›</span></a>
            <a class="section-nav <?= $current === 'admin_approvals.php' ? 'active' : '' ?>" href="admin_approvals.php"><span class="nav-icon">✓</span><span>Sales Approvals</span><span class="section-arrow">›</span></a>
            <a class="section-nav <?= $current === 'inventory_stockout_approval.php' ? 'active' : '' ?>" href="inventory_stockout_approval.php"><span class="nav-icon">I</span><span>Inventory Stockout Status</span><span class="section-arrow">›</span></a>
        <?php endif; ?>
    </nav>
    <footer><p style="text-align:center;font-family:'Segoe UI',Tahoma,Geneva,Verdana,sans-serif;font-weight:bold">DLSP 2026-2027</p></footer>
</aside>
<?php endif; ?>
=======
        <?php if ($isSalesAdmin): ?>
            <div class="nav-label">SALES ADMINISTRATION</div>
            <a class="section-nav <?= $current === 'admin_customers.php' ? 'active' : '' ?>" href="admin_customers.php"><span class="nav-icon">C</span><span>Customer Management</span><span class="section-arrow">›</span></a>
            <a class="section-nav <?= $current === 'admin_approvals.php' ? 'active' : '' ?>" href="admin_approvals.php"><span class="nav-icon">✓</span><span>Approvals</span><span class="section-arrow">›</span></a>
        <?php else: ?>
            <div class="nav-label">MAIN</div>
            <a class="section-nav <?= $current === 'dashboard.php' && $dashboardSection === '' ? 'active' : '' ?>" href="dashboard.php"><span class="nav-icon">D</span><span>Dashboard</span><span class="section-arrow">›</span></a>
            <div class="nav-label">MODULES</div>
            <a class="section-nav <?= $activeSection === 'core' ? 'active' : '' ?>" href="quotations.php?section=core"><span class="nav-icon">C</span><span>Core Sales & Order Management</span><span class="section-arrow">›</span></a>
            <a class="section-nav <?= $current === 'inventory.php' ? 'active' : '' ?>" href="inventory.php"><span class="nav-icon">I</span><span>Inventory Reference</span><span class="section-arrow">›</span></a>
            <a class="section-nav <?= $activeSection === 'billing' ? 'active' : '' ?>" href="customer_account.php?section=billing"><span class="nav-icon">B</span><span>Billing & Financial Accounting</span><span class="section-arrow">›</span></a>
            <div class="nav-label">REPORTS</div>
            <a class="section-nav <?= $activeSection === 'sales' ? 'active' : '' ?>" href="reports.php?section=sales"><span class="nav-icon">S</span><span>Sales Report</span><span class="section-arrow">›</span></a>
        <?php endif; ?>
    </nav>
    <footer><p style="text-align: center; font-family:'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; font-weight: bold ">DLSP 2026-2027</p></footer>
</aside>
<?php endif; ?>
>>>>>>> origin/main
>>>>>>> origin/main
