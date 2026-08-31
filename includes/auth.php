<?php
/**
 * Authentication & authorization.
 *
 * - Passwords are hashed with password_hash() (bcrypt/argon, whatever the
 *   PHP build defaults to) and never stored or logged in plain text.
 * - Sessions are the auth mechanism (no tokens/cookies of our own).
 * - Login is rate-limited per account to blunt brute-force guessing.
 * - Every admin/editor page must call require_role() before rendering
 *   anything, so a logged-out or wrong-role user can never reach it.
 */

declare(strict_types=1);

const ROLE_ADMIN = 'admin';
const ROLE_EDITOR = 'editor';

const MAX_LOGIN_ATTEMPTS = 5;
const LOCKOUT_SECONDS = 300; // 5 minutes

/** Currently logged-in user (fresh from disk, password hash stripped), or null. */
function current_user(): ?array
{
    static $cached = false;
    static $cachedValue = null;

    if ($cached) {
        return $cachedValue;
    }
    $cached = true;

    $id = $_SESSION['user_id'] ?? null;
    if (!$id) {
        return $cachedValue = null;
    }

    $user = Storage::users()->find($id);
    if (!$user || ($user['status'] ?? 'active') !== 'active') {
        // Account was deactivated or deleted since login — force logout.
        logout_user();
        return $cachedValue = null;
    }

    unset($user['password_hash']);
    return $cachedValue = $user;
}

function is_logged_in(): bool
{
    return current_user() !== null;
}

function require_login(): array
{
    $user = current_user();
    if (!$user) {
        redirect('/login.php');
    }
    return $user;
}

function require_role(string $role): array
{
    $user = require_login();
    if ($user['role'] !== $role) {
        http_response_code(403);
        echo 'You do not have access to this page.';
        exit;
    }
    return $user;
}

function login_user(array $user): void
{
    session_regenerate_id(true);
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['role'] = $user['role'];
}

function logout_user(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }
    session_destroy();
}

/**
 * Attempt to log in. Returns the user row on success.
 * Returns a string error message on failure (bad credentials, locked, inactive).
 *
 * @return array|string
 */
function attempt_login(string $email, string $password)
{
    $email = strtolower(trim($email));
    $users = Storage::users();
    $user = null;
    foreach ($users->all() as $row) {
        if (strtolower((string) ($row['email'] ?? '')) === $email) {
            $user = $row;
            break;
        }
    }

    if (!$user) {
        // Don't reveal whether the email exists.
        return 'Incorrect email or password.';
    }

    $lockedUntil = parse_dt($user['locked_until'] ?? null);
    if ($lockedUntil && $lockedUntil > new DateTimeImmutable('now')) {
        return 'This account is temporarily locked due to repeated failed logins. Try again in a few minutes.';
    }

    if (($user['status'] ?? 'active') !== 'active') {
        return 'This account has been deactivated. Contact an admin.';
    }

    if (!password_verify($password, (string) ($user['password_hash'] ?? ''))) {
        $attempts = (int) ($user['failed_attempts'] ?? 0) + 1;
        $fields = ['failed_attempts' => $attempts];
        if ($attempts >= MAX_LOGIN_ATTEMPTS) {
            $fields['locked_until'] = (new DateTimeImmutable('now'))
                ->modify('+' . LOCKOUT_SECONDS . ' seconds')
                ->format(DateTimeImmutable::ATOM);
            $fields['failed_attempts'] = 0;
        }
        $users->update($user['id'], $fields);
        return 'Incorrect email or password.';
    }

    // Success — reset lockout counters.
    $users->update($user['id'], ['failed_attempts' => 0, 'locked_until' => null]);

    return $user;
}

function create_user(string $name, string $email, string $password, string $role, ?string $createdBy): array
{
    $users = Storage::users();
    $row = [
        'id' => $users->generateId('usr'),
        'name' => $name,
        'email' => strtolower($email),
        'password_hash' => password_hash($password, PASSWORD_DEFAULT),
        'role' => $role,
        'status' => 'active',
        'failed_attempts' => 0,
        'locked_until' => null,
        'created_at' => now_iso(),
        'created_by' => $createdBy,
    ];
    return $users->insert($row);
}

function email_taken(string $email, ?string $excludeUserId = null): bool
{
    $email = strtolower(trim($email));
    foreach (Storage::users()->all() as $u) {
        if (strtolower((string) ($u['email'] ?? '')) === $email && ($u['id'] ?? null) !== $excludeUserId) {
            return true;
        }
    }
    return false;
}
