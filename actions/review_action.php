<?php
declare(strict_types=1);
require_once __DIR__ . '/../config.php';

$admin = require_role(ROLE_ADMIN);
require_post();

$videoId = trimmed('video_id', $_POST);
$versionId = trimmed('version_id', $_POST);
$decision = trimmed('decision', $_POST);
$note = trim((string) ($_POST['review_note'] ?? ''));
$note = mb_substr($note, 0, 2000);

if (!in_array($decision, ['approve', 'request_changes'], true)) {
    flash('error', 'Invalid review decision.');
    redirect('/admin/video.php?id=' . urlencode($videoId));
}

// Same pattern as submit_version.php: the whole check-then-write happens
// under one exclusive lock on videos.json so a double-click can't approve
// and request-changes on the same version, or review it twice.
$result = Storage::videos()->transact(function (array $rows) use ($videoId, $versionId, $decision, $note, $admin) {
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
    if (($video['status'] ?? '') !== STATUS_IN_REVIEW) {
        return ['data' => $rows, 'return' => ['error' => 'This video is not currently awaiting review.']];
    }

    $version = Storage::versions()->find($versionId);
    if (!$version || ($version['video_id'] ?? null) !== $videoId || ($version['review_status'] ?? '') !== 'pending') {
        return ['data' => $rows, 'return' => ['error' => 'That submission has already been reviewed.']];
    }

    $now = now_iso();

    if ($decision === 'approve') {
        Storage::versions()->update($versionId, [
            'review_status' => 'approved',
            'review_note' => $note !== '' ? $note : null,
            'reviewed_by' => $admin['id'],
            'reviewed_at' => $now,
        ]);
        $rows[$idx]['status'] = STATUS_FINAL;
        $rows[$idx]['finalized_at'] = $now;
        $rows[$idx]['final_version_id'] = $versionId;
        $msg = 'V' . $version['version_number'] . ' approved as FINAL.';
    } else {
        Storage::versions()->update($versionId, [
            'review_status' => 'changes_requested',
            'review_note' => $note !== '' ? $note : null,
            'reviewed_by' => $admin['id'],
            'reviewed_at' => $now,
        ]);
        $rows[$idx]['status'] = STATUS_CHANGES_REQUESTED;
        $msg = 'Changes requested for V' . $version['version_number'] . '.';
    }

    return ['data' => $rows, 'return' => ['ok' => true, 'message' => $msg]];
});

if (!empty($result['error'])) {
    flash('error', $result['error']);
} else {
    flash('success', $result['message']);
}
redirect('/admin/video.php?id=' . urlencode($videoId));
