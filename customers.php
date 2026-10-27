<?php
<<<<<<< HEAD
=======
<<<<<<< HEAD
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
requireLogin();
$pdo = getDB();

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $contact = trim($_POST['contact_person'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $status = in_array($_POST['status'] ?? '', ['Active', 'Inactive'], true) ? $_POST['status'] : 'Active';

    if ($name === '') {
        $error = 'Customer name is required.';
    } else {
        $code = nextDocNo($pdo, 'customers', 'CUS-', 0, 3);
        $stmt = $pdo->prepare('INSERT INTO customers (customer_code, name, contact_person, phone, email, address, status) VALUES (?,?,?,?,?,?,?)');
        $stmt->execute([$code, $name, $contact, $phone, $email, $address, $status]);
        header('Location: customers.php?section=customer&added=1');
        exit;
    }
}

$pageTitle = 'Customer Management';
include __DIR__ . '/includes/header.php';

$customers = $pdo->query('SELECT * FROM customers ORDER BY id DESC')->fetchAll();
?>

<div class="page-toolbar"><div><p class="muted" style="color: white;">Customer master records</p></div><button class="primary-btn" data-open-modal="addCustomerModal">+ Add Customer</button></div>

<?php if (!empty($_GET['added'])): ?><div class="alert success">Customer added successfully.</div><?php endif; ?>
<?php if ($error): ?><div class="alert error"><?= htmlspecialchars($error) ?></div><?php endif; ?>

<div class="panel">
    <div class="search-row"><input class="search-input" data-table-search="customerTable" placeholder="Search customers..."><span class="result-count"><?= count($customers) ?> records</span></div>
    <div class="table-wrap"><table id="customerTable"><thead><tr><th>Code</th><th>Customer</th><th>Contact Person</th><th>Phone</th><th>Email</th><th>Status</th></tr></thead><tbody>
    <?php if (!$customers): ?><tr class="empty-row"><td colspan="6">No customers yet. Add your first customer above.</td></tr><?php endif; ?>
    <?php foreach ($customers as $c): ?>
        <tr>
            <td><b><?= htmlspecialchars($c['customer_code']) ?></b></td>
            <td><?= htmlspecialchars($c['name']) ?></td>
            <td><?= htmlspecialchars($c['contact_person'] ?? '') ?></td>
            <td><?= htmlspecialchars($c['phone'] ?? '') ?></td>
            <td><?= htmlspecialchars($c['email'] ?? '') ?></td>
            <td><span class="badge <?= badgeClass($c['status']) ?>"><?= htmlspecialchars($c['status']) ?></span></td>
        </tr>
    <?php endforeach; ?>
    </tbody></table></div>
</div>

<div class="modal-backdrop" id="addCustomerModal" aria-hidden="true">
    <div class="profile-modal entry-modal" role="dialog" aria-modal="true" aria-labelledby="addCustomerTitle">
        <button class="modal-close" type="button" aria-label="Close" data-close-modal>×</button>
        <p class="eyebrow">CUSTOMER MANAGEMENT</p>
        <h2 id="addCustomerTitle">Add Customer</h2>
        <form method="POST" class="entry-form">
            <label>Customer Name
                <input type="text" name="name" required>
            </label>
            <label>Contact Person
                <input type="text" name="contact_person">
            </label>
            <label>Phone
                <input type="text" name="phone">
            </label>
            <label>Email
                <input type="email" name="email">
            </label>
            <label>Address
                <input type="text" name="address">
            </label>
            <label>Status
                <select name="status">
                    <option value="Active">Active</option>
                    <option value="Inactive">Inactive</option>
                </select>
            </label>
            <div class="modal-actions">
                <button type="button" class="secondary-btn" data-close-modal>Cancel</button>
                <button type="submit" class="primary-btn">Save Customer</button>
            </div>
        </form>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
=======
>>>>>>> origin/main
require_once __DIR__ . '/includes/auth.php';

requireLogin();

if (currentUser()['role'] === 'Sales Admin') {
    header('Location: admin_customers.php');
    exit;
}

header('Location: dashboard.php');
exit;
?>
<<<<<<< HEAD
=======
>>>>>>> origin/main
>>>>>>> origin/main
