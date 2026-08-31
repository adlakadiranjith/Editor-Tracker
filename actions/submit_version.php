<?php
declare(strict_types=1);
require_once __DIR__ . '/../config.php';

$editor = require_role(ROLE_EDITOR);
require_post();

$videoId = trimmed('video_id', $_POST);

// The whole check-then-write happens inside one exclusive lock on videos.json,
// so two rapid clicks (or two tabs) can never create two versions for the
// same submission — the second request sees the already-updated status.
$result = Storage::videos()->transact(function (array $rows) use ($videoId, $editor) {
    $idx = null;
    foreach ($rows as $i => $r) {
        if (($r['id'] ?? null) === $videoId) {
            $idx = $i;
            break;
        }
    }
    if ($idx === null) {
        return ['data' => $rows, 'return' => ['error' => 'That video could not be found.']];
    }

    $video = $rows[$idx];
    if (($video['editor_id'] ?? null) !== $editor['id']) {
        return ['data' => $rows, 'return' => ['error' => 'This video is not assigned to you.']];
    }
    if (!in_array($video['status'] ?? '', [STATUS_ASSIGNED, STATUS_CHANGES_REQUESTED], true)) {
        return ['data' => $rows, 'return' => ['error' => 'This video is not awaiting a submission right now.']];
    }

    $versionNumber = next_version_number($videoId);
    $newVersion = [
        'id' => Storage::versions()->generateId('ver'),
        'video_id' => $videoId,
        'version_number' => $versionNumber,
        'submitted_by' => $editor['id'],
        'submitted_at' => now_iso(),
        'review_status' => 'pending',
        'review_note' => null,
        'reviewed_by' => null,
        'reviewed_at' => null,
    ];
    Storage::versions()->insert($newVersion);

    $rows[$idx]['status'] = STATUS_IN_REVIEW;

    return ['data' => $rows, 'return' => ['ok' => true, 'version_number' => $versionNumber]];
});

if (!empty($result['error'])) {
    flash('error', $result['error']);
} else {
    flash('success', 'Version V' . $result['version_number'] . ' submitted successfully.');
}
redirect('/editor/index.php');
