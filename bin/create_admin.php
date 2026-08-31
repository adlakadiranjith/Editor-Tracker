<?php
/**
 * CLI helper to create an admin account without going through the browser.
 * Useful if you'd rather not use the one-time web setup screen, or need to
 * add a second admin without asking an existing admin to do it.
 *
 * Usage:
 *   php bin/create_admin.php "Full Name" "email@example.com" "password"
 *
 * If password is omitted, a random one is generated and printed once.
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('This script may only be run from the command line.');
}

require_once __DIR__ . '/../includes/Storage.php';
define('DATA_DIR', __DIR__ . '/../data');
require_once __DIR__ . '/../includes/functions.php';

$name = $argv[1] ?? null;
$email = $argv[2] ?? null;
$password = $argv[3] ?? null;

if (!$name || !$email) {
    fwrite(STDERR, "Usage: php bin/create_admin.php \"Full Name\" \"email@example.com\" [password]\n");
    exit(1);
}

if (!is_valid_email($email)) {
    fwrite(STDERR, "Error: '$email' is not a valid email address.\n");
    exit(1);
}

$users = Storage::users();
foreach ($users->all() as $existing) {
    if (strtolower((string) ($existing['email'] ?? '')) === strtolower($email)) {
        fwrite(STDERR, "Error: an account with that email already exists.\n");
        exit(1);
    }
}

$generated = false;
if (!$password) {
    $password = bin2hex(random_bytes(6));
    $generated = true;
} elseif (strlen($password) < 8) {
    fwrite(STDERR, "Error: password must be at least 8 characters.\n");
    exit(1);
}

$row = [
    'id' => $users->generateId('usr'),
    'name' => $name,
    'email' => strtolower($email),
    'password_hash' => password_hash($password, PASSWORD_DEFAULT),
    'role' => 'admin',
    'status' => 'active',
    'failed_attempts' => 0,
    'locked_until' => null,
    'created_at' => now_iso(),
    'created_by' => null,
];
$users->insert($row);

echo "Admin account created:\n";
echo "  Name:     $name\n";
echo "  Email:    $email\n";
if ($generated) {
    echo "  Password: $password  (save this now — it will not be shown again)\n";
} else {
    echo "  Password: (as provided)\n";
}
