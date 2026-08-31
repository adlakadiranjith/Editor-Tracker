<?php
declare(strict_types=1);
require_once __DIR__ . '/../config.php';

$admin = require_role(ROLE_ADMIN);
require_post();

$id = trimmed('id', $_POST);
$targetStatus = trimmed('target_status', $_POST);

if (!in_array($targetStatus, ['active', 'inactive'], true)) {
    flash('error', 'Invalid request.');
    redirect('/admin/team.php');
}

if ($id === $admin['id']) {
    flash('error', 'You cannot deactivate your own account.');
    redirect('/admin/team.php');
}

$target = Storage::users()->find($id);
if (!$target) {
    flash('error', 'User not found.');
    redirect('/admin/team.php');
}

Storage::users()->update($id, ['status' => $targetStatus]);
flash('success', $target['name'] . ' is now ' . $targetStatus . '.');
redirect('/admin/team.php');
