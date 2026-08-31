<?php
/**
 * Application bootstrap.
 * Every entry-point PHP file must require_once this file first.
 */

declare(strict_types=1);

// ---------------------------------------------------------------------
// Timezone — change to your local timezone (e.g. 'Asia/Kolkata').
// All dates/times shown in the app use this timezone.
// ---------------------------------------------------------------------
date_default_timezone_set('UTC');

// ---------------------------------------------------------------------
// Paths
// ---------------------------------------------------------------------
define('APP_ROOT', __DIR__);
define('DATA_DIR', APP_ROOT . '/data');

// ---------------------------------------------------------------------
// Error handling — never leak stack traces/paths to the browser.
// Flip APP_DEBUG to true only in a local dev environment.
// ---------------------------------------------------------------------
define('APP_DEBUG', false);

if (APP_DEBUG) {
    ini_set('display_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
    error_reporting(E_ALL);
}
ini_set('log_errors', '1');

set_exception_handler(function (Throwable $e): void {
    error_log('Uncaught: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    http_response_code(500);
    echo 'Something went wrong. Please try again.';
    exit;
});

// ---------------------------------------------------------------------
// Sessions — hardened cookie params before the session starts.
// ---------------------------------------------------------------------
if (session_status() === PHP_SESSION_NONE) {
    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');

    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'domain' => '',
        'secure' => $isHttps,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_name('etrk_sid');
    session_start();
}

// ---------------------------------------------------------------------
// Basic security headers (defense in depth for an internal app).
// ---------------------------------------------------------------------
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: same-origin');

// ---------------------------------------------------------------------
// Core includes
// ---------------------------------------------------------------------
require_once APP_ROOT . '/includes/Storage.php';
require_once APP_ROOT . '/includes/functions.php';
require_once APP_ROOT . '/includes/auth.php';
