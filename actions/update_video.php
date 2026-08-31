<?php
declare(strict_types=1);
require_once __DIR__ . '/../config.php';

require_role(ROLE_ADMIN);
require_post();

$id = trimmed('id', $_POST);
$video = $id !== '' ? Storage::videos()->find($id) : null;
if (!$video) {
    flash('error', 'Video not found.');
    redirect('/admin/videos.php');
}

$title = trimmed('title', $_POST);
$projectId = trimmed('project_id', $_POST);
$editorId = trimmed('editor_id', $_POST);
$deadlineLocal = trimmed('deadline_at', $_POST);
$videoLink = trimmed('video_link', $_POST);

$errors = [];
if ($title === '' || mb_strlen($title) > 200) $errors[] = 'Video title is required.';

$project = Storage::projects()->find($projectId);
if (!$project) $errors[] = 'Select a valid project.';

$editor = Storage::users()->find($editorId);
if (!$editor || $editor['role'] !== ROLE_EDITOR) $errors[] = 'Select a valid editor.';

$deadlineIso = local_input_to_iso($deadlineLocal);
if (!$deadlineIso) $errors[] = 'A valid deadline is required.';

if ($videoLink !== '' && !is_valid_url($videoLink)) $errors[] = 'The video link must be a valid http(s) URL.';

if ($errors) {
    flash('error', implode(' ', $errors));
    redirect('/admin/video.php?id=' . urlencode($id));
}

Storage::videos()->update($id, [
    'title' => $title,
    'project_id' => $projectId,
    'editor_id' => $editorId,
    'deadline_at' => $deadlineIso,
    'video_link' => $videoLink !== '' ? $videoLink : null,
]);

flash('success', 'Assignment updated.');
redirect('/admin/video.php?id=' . urlencode($id));
