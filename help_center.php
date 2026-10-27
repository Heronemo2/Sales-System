<?php
$pageTitle = 'Help Center';
include __DIR__ . '/includes/header.php';
?>
<section class="settings-page">
    <div class="page-intro">
        <p class="eyebrow">SUPPORT</p>
        <h2>Help Center</h2>
        <p>Quick answers for using the Team Sales Management System.</p>
    </div>

    <div class="help-grid">
        <section class="panel help-card">
            <div class="help-icon">?</div>
            <div>
                <p class="eyebrow">GETTING STARTED</p>
                <h2>How do I use the dashboard?</h2>
                <p>Use the sidebar to open Customer Management, Core Sales, Billing, and Sales Reports. The profile menu in the top-right gives you access to your profile, settings, and this Help Center.</p>
            </div>
        </section>

        <section class="panel">
            <p class="eyebrow">SALES WORKFLOW</p>
            <h2>Common tasks</h2>
            <div class="faq-list">
                <details open><summary>How do I create a quotation?</summary><p>Open Sales Quotation from Core Sales, review the customer information, then create or manage the quotation from that page.</p></details>
                <details><summary>What happens after a quotation is approved?</summary><p>An approved quotation can move into the Sales Order process. The order then continues through delivery, invoicing, and collection.</p></details>
                <details><summary>Where can I check unpaid customer balances?</summary><p>Open Billing & Financial Accounting, then choose Accounts Receivable or Customer Balance.</p></details>
                <details><summary>How can I change my password?</summary><p>Open the profile menu and select Account Settings. Enter your current password and your new password.</p></details>
            </div>
        </section>

        <section class="panel help-contact">
            <p class="eyebrow">NEED MORE HELP?</p>
            <h2>Contact your system administrator</h2>
            <p>If you encounter a database, login, or permission problem, provide the administrator with the page name and the error message you received.</p>
        </section>
    </div>
</section>
<?php include __DIR__ . '/includes/footer.php'; ?>
