<?php
declare(strict_types=1);
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/layout.php';

$user = require_login();
$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $current = (string) ($_POST['current_password'] ?? '');
    $new = (string) ($_POST['new_password'] ?? '');
    $confirm = (string) ($_POST['new_password_confirm'] ?? '');

    $fullUser = Storage::users()->find($user['id']);
    if (!$fullUser || !password_verify($current, (string) $fullUser['password_hash'])) {
        $errors[] = 'Current password is incorrect.';
    }
    if (strlen($new) < 8) {
        $errors[] = 'New password must be at least 8 characters.';
    }
    if ($new !== $confirm) {
        $errors[] = 'New passwords do not match.';
    }

    if (!$errors) {
        Storage::users()->update($user['id'], [
            'password_hash' => password_hash($new, PASSWORD_DEFAULT),
        ]);
        $success = true;
    }
}

page_start('Account', 'account');
?>
<div class="card card-narrow">
    <h2>Your Details</h2>
    <p><strong>Name:</strong> <?= e($user['name']) ?></p>
    <p><strong>Email:</strong> <?= e($user['email']) ?></p>
    <p><strong>Role:</strong> <?= e(ucfirst($user['role'])) ?></p>
</div>

<div class="card card-narrow">
    <h2>Change Password</h2>
    <?php if ($success): ?>
        <div class="alert alert-success">Password updated.</div>
    <?php endif; ?>
    <?php if ($errors): ?>
        <div class="alert alert-error">
            <ul><?php foreach ($errors as $err) echo '<li>' . e($err) . '</li>'; ?></ul>
        </div>
    <?php endif; ?>
    <form method="post" novalidate>
        <?= csrf_field() ?>
        <label>Current Password
            <input type="password" name="current_password" required>
        </label>
        <label>New Password
            <input type="password" name="new_password" minlength="8" required>
        </label>
        <label>Confirm New Password
            <input type="password" name="new_password_confirm" minlength="8" required>
        </label>
        <button type="submit" class="btn btn-primary">Update Password</button>
    </form>
</div>
<?php page_end(); ?>
