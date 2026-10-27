<?php
$pageTitle = 'Manage Profile';
include __DIR__ . '/includes/header.php';

$user = currentUser();
$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $username = trim($_POST['username'] ?? '');

    if ($name === '' || $username === '') {
        $error = 'Name and username are required.';
    } else {
        $pdo = getDB();
        try {
            $stmt = $pdo->prepare('UPDATE users SET name = ?, username = ? WHERE id = ?');
            $stmt->execute([$name, $username, $user['id'] ?? 0]);
            $_SESSION['user']['name'] = $name;
            $_SESSION['user']['username'] = $username;
            $user = currentUser();
            $success = 'Profile updated successfully.';
        } catch (PDOException $e) {
            $error = 'That username may already be in use.';
        }
    }
}
?>
<section class="settings-page">
    <div class="page-intro">
        <p class="eyebrow">ACCOUNT</p>
        <h2>Manage Profile</h2>
        <p>Keep your personal account information up to date.</p>
    </div>

    <?php if ($success): ?><div class="alert success"><?= htmlspecialchars($success) ?></div><?php endif; ?>
    <?php if ($error): ?><div class="alert error"><?= htmlspecialchars($error) ?></div><?php endif; ?>

    <div class="settings-grid">
        <section class="panel profile-card-large">
            <div class="profile-card-heading">
                <div class="profile-modal-avatar"><?= htmlspecialchars(strtoupper(substr($user['name'], 0, 1))) ?></div>
                <div>
                    <p class="eyebrow">PROFILE</p>
                    <h2><?= htmlspecialchars($user['name']) ?></h2>
                    <p><?= htmlspecialchars($user['role']) ?> · @<?= htmlspecialchars($user['username']) ?></p>
                </div>
            </div>

            <form method="POST" class="settings-form">
                <label>Full Name
                    <input type="text" name="name" value="<?= htmlspecialchars($user['name']) ?>" required>
                </label>
                <label>Username
                    <input type="text" name="username" value="<?= htmlspecialchars($user['username']) ?>" required>
                </label>
                <label>Role
                    <input type="text" value="<?= htmlspecialchars($user['role']) ?>" disabled>
                </label>
                <div class="form-actions">
                    <button type="submit" class="primary-btn">Save Profile</button>
                </div>
            </form>
        </section>

        <section class="panel">
            <p class="eyebrow">QUICK ACTIONS</p>
            <h2>Account</h2>
            <div class="quick-links">
                <a href="account_settings.php" class="quick-link"><span>⚙</span><div><b>Account Settings</b><small>Change your password and preferences.</small></div>→</a>
                <a href="help_center.php" class="quick-link"><span>?</span><div><b>Help Center</b><small>Find answers about the sales system.</small></div>→</a>
            </div>
        </section>
    </div>
</section>
<?php include __DIR__ . '/includes/footer.php'; ?>
