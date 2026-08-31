<?php
declare(strict_types=1);
require_once __DIR__ . '/../config.php';

require_role(ROLE_ADMIN);
require_post();

$id = trimmed('id', $_POST);
$targetStatus = trimmed('target_status', $_POST);

if (!in_array($targetStatus, ['active', 'archived'], true)) {
    flash('error', 'Invalid request.');
    redirect('/admin/projects.php');
}

$project = $id !== '' ? Storage::projects()->find($id) : null;
if (!$project) {
    flash('error', 'Project not found.');
    redirect('/admin/projects.php');
}

// Archiving only hides a project from new assignments — existing videos and
// their full history are left untouched, per spec.
Storage::projects()->update($id, ['status' => $targetStatus]);

flash('success', $targetStatus === 'archived' ? 'Project archived.' : 'Project reactivated.');
redirect('/admin/projects.php');
