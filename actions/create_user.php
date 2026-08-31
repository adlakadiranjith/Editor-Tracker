<?php
declare(strict_types=1);
require_once __DIR__ . '/../config.php';

$admin = require_role(ROLE_ADMIN);
require_post();

$name = trimmed('name', $_POST);
$email = trimmed('email', $_POST);
$role = trimmed('role', $_POST);
$password = (string) ($_POST['password'] ?? '');

$errors = [];
if ($name === '' || mb_strlen($name) > 120) $errors[] = 'Name is required.';
if ($email === '' || !is_valid_email($email)) $errors[] = 'A valid email is required.';
if (!in_array($role, [ROLE_ADMIN, ROLE_EDITOR], true)) $errors[] = 'Invalid role.';
if (email_taken($email)) $errors[] = 'That email is already in use.';

$generated = false;
if ($password === '') {
    $password = bin2hex(random_bytes(5)); // 10-char random temporary password
    $generated = true;
} elseif (strlen($password) < 8) {
    $errors[] = 'Password must be at least 8 characters, or left blank to auto-generate one.';
}

if ($errors) {
    flash('error', implode(' ', $errors));
    redirect('/admin/team.php?new=1');
}

$newUser = create_user($name, $email, $password, $role, $admin['id']);

$_SESSION['reveal_password'] = [
    'name' => $newUser['name'],
    'email' => $newUser['email'],
    'password' => $password,
];

flash('success', 'Account created for ' . $name . '.');
redirect('/admin/team.php');
