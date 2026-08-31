<?php
declare(strict_types=1);
require_once __DIR__ . '/../config.php';

require_role(ROLE_ADMIN);
require_post();

$id = trimmed('id', $_POST);
$target = $id !== '' ? Storage::users()->find($id) : null;
if (!$target) {
    flash('error', 'User not found.');
    redirect('/admin/team.php');
}

$password = bin2hex(random_bytes(5));
Storage::users()->update($id, [
    'password_hash' => password_hash($password, PASSWORD_DEFAULT),
    'failed_attempts' => 0,
    'locked_until' => null,
]);

$_SESSION['reveal_password'] = [
    'name' => $target['name'],
    'email' => $target['email'],
    'password' => $password,
];

flash('success', 'Password reset for ' . $target['name'] . '.');
redirect('/admin/team.php');
