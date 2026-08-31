<?php
declare(strict_types=1);
require_once __DIR__ . '/config.php';

// Already logged in? Go straight to the right dashboard.
if ($user = current_user()) {
    redirect($user['role'] === ROLE_ADMIN ? '/admin/index.php' : '/editor/index.php');
}

$users = Storage::users();
$noUsersYet = count($users->all()) === 0;
$errors = [];
$old = ['name' => '', 'email' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    if ($noUsersYet && ($_POST['action'] ?? '') === 'setup') {
        // ---------------------------------------------------------------
        // First-run bootstrap: create the very first admin account.
        // Only reachable while users.json is genuinely empty.
        // ---------------------------------------------------------------
        $name = trimmed('name', $_POST);
        $email = trimmed('email', $_POST);
        $password = (string) ($_POST['password'] ?? '');
        $confirm = (string) ($_POST['password_confirm'] ?? '');
        $old = ['name' => $name, 'email' => $email];

        if ($name === '') $errors[] = 'Name is required.';
        if ($email === '' || !is_valid_email($email)) $errors[] = 'A valid email is required.';
        if (strlen($password) < 8) $errors[] = 'Password must be at least 8 characters.';
        if ($password !== $confirm) $errors[] = 'Passwords do not match.';

        if (!$errors) {
            // Re-check under the hood right before insert to close the race
            // where two people load this page before either submits.
            if (count($users->all()) > 0) {
                $errors[] = 'Setup already completed. Please log in.';
                $noUsersYet = false;
            } else {
                $admin = create_user($name, $email, $password, ROLE_ADMIN, null);
                login_user($admin);
                flash('success', 'Welcome! Your admin account has been created.');
                redirect('/admin/index.php');
            }
        }
    } elseif (!$noUsersYet && ($_POST['action'] ?? '') === 'login') {
        $email = trimmed('email', $_POST);
        $password = (string) ($_POST['password'] ?? '');
        $old = ['name' => '', 'email' => $email];

        $result = attempt_login($email, $password);
        if (is_string($result)) {
            $errors[] = $result;
        } else {
            login_user($result);
            redirect($result['role'] === ROLE_ADMIN ? '/admin/index.php' : '/editor/index.php');
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= $noUsersYet ? 'Initial Setup' : 'Login' ?> · Production Tracker</title>
<link rel="stylesheet" href="/assets/style.css">
</head>
<body class="auth-body">
<div class="auth-card">
    <h1 class="brand">🎬 Production Tracker</h1>

    <?php if ($errors): ?>
        <div class="alert alert-error">
            <ul><?php foreach ($errors as $err) echo '<li>' . e($err) . '</li>'; ?></ul>
        </div>
    <?php endif; ?>

    <?php if ($noUsersYet): ?>
        <p class="muted">No accounts exist yet. Create the first admin account to get started.</p>
        <form method="post" novalidate>
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="setup">
            <label>Name
                <input type="text" name="name" value="<?= e($old['name']) ?>" required autofocus>
            </label>
            <label>Email
                <input type="email" name="email" value="<?= e($old['email']) ?>" required>
            </label>
            <label>Password
                <input type="password" name="password" minlength="8" required>
            </label>
            <label>Confirm Password
                <input type="password" name="password_confirm" minlength="8" required>
            </label>
            <button type="submit" class="btn btn-primary btn-block">Create Admin Account</button>
        </form>
    <?php else: ?>
        <form method="post" novalidate>
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="login">
            <label>Email
                <input type="email" name="email" value="<?= e($old['email']) ?>" required autofocus>
            </label>
            <label>Password
                <input type="password" name="password" required>
            </label>
            <button type="submit" class="btn btn-primary btn-block">Log In</button>
        </form>
    <?php endif; ?>
</div>
</body>
</html>
