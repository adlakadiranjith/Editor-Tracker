<?php
declare(strict_types=1);
require_once __DIR__ . '/../config.php';

require_role(ROLE_ADMIN);
require_post();

$id = trimmed('id', $_POST);
$name = trimmed('name', $_POST);
$description = trimmed('description', $_POST);
$status = trimmed('status', $_POST);

$project = $id !== '' ? Storage::projects()->find($id) : null;
if (!$project) {
    flash('error', 'Project not found.');
    redirect('/admin/projects.php');
}

if ($name === '' || mb_strlen($name) > 120) {
    flash('error', 'Project name is required (max 120 characters).');
    redirect('/admin/projects.php?edit=' . urlencode($id));
}
if (!in_array($status, ['active', 'archived'], true)) {
    $status = $project['status'] ?? 'active';
}

Storage::projects()->update($id, [
    'name' => $name,
    'description' => mb_substr($description, 0, 500),
    'status' => $status,
]);

flash('success', 'Project updated.');
redirect('/admin/projects.php');
