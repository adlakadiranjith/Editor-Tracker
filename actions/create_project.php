<?php
declare(strict_types=1);
require_once __DIR__ . '/../config.php';

$user = require_role(ROLE_ADMIN);
require_post();

$name = trimmed('name', $_POST);
$description = trimmed('description', $_POST);

if ($name === '' || mb_strlen($name) > 120) {
    flash('error', 'Project name is required (max 120 characters).');
    redirect('/admin/projects.php');
}

Storage::projects()->insert([
    'id' => Storage::projects()->generateId('prj'),
    'name' => $name,
    'description' => mb_substr($description, 0, 500),
    'status' => 'active',
    'created_at' => now_iso(),
    'created_by' => $user['id'],
]);

flash('success', 'Project created.');
redirect('/admin/projects.php');
