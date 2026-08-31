<?php
declare(strict_types=1);
require_once __DIR__ . '/config.php';

$user = current_user();
if (!$user) {
    redirect('/login.php');
}
redirect($user['role'] === ROLE_ADMIN ? '/admin/index.php' : '/editor/index.php');
