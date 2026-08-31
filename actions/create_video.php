<?php
declare(strict_types=1);
require_once __DIR__ . '/../config.php';

$admin = require_role(ROLE_ADMIN);
require_post();

$title = trimmed('title', $_POST);
$projectId = trimmed('project_id', $_POST);
$editorId = trimmed('editor_id', $_POST);
$deadlineLocal = trimmed('deadline_at', $_POST);
$videoLink = trimmed('video_link', $_POST);

$errors = [];
if ($title === '' || mb_strlen($title) > 200) $errors[] = 'Video title is required.';

$project = Storage::projects()->find($projectId);
if (!$project || ($project['status'] ?? '') !== 'active') $errors[] = 'Select a valid active project.';

$editor = Storage::users()->find($editorId);
if (!$editor || $editor['role'] !== ROLE_EDITOR || ($editor['status'] ?? '') !== 'active') $errors[] = 'Select a valid active editor.';

$deadlineIso = local_input_to_iso($deadlineLocal);
if (!$deadlineIso) $errors[] = 'A valid deadline is required.';

if ($videoLink !== '' && !is_valid_url($videoLink)) $errors[] = 'The video link must be a valid http(s) URL.';

if ($errors) {
    flash('error', implode(' ', $errors));
    redirect('/admin/videos.php?new=1');
}

Storage::videos()->insert([
    'id' => Storage::videos()->generateId('vid'),
    'title' => $title,
    'project_id' => $projectId,
    'editor_id' => $editorId,
    'video_link' => $videoLink !== '' ? $videoLink : null,
    'assigned_at' => now_iso(),
    'deadline_at' => $deadlineIso,
    'status' => STATUS_ASSIGNED,
    'finalized_at' => null,
    'final_version_id' => null,
    'created_by' => $admin['id'],
]);

flash('success', 'Video assigned to ' . $editor['name'] . '.');
redirect('/admin/videos.php');
