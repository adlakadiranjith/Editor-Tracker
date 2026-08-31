<?php
declare(strict_types=1);
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/layout.php';

$user = require_role(ROLE_ADMIN);

$id = trimmed('id', $_GET);
$video = $id !== '' ? Storage::videos()->find($id) : null;
if (!$video) {
    http_response_code(404);
    page_start('Not Found', 'videos');
    echo '<p>That video assignment does not exist.</p><p><a href="/admin/videos.php">← Back to videos</a></p>';
    page_end();
    exit;
}

$allProjects = Storage::projects()->all();
$projectsById = index_by_id($allProjects);
$activeProjects = array_values(array_filter($allProjects, fn ($p) => ($p['status'] ?? 'active') === 'active'));
$usersById = index_by_id(Storage::users()->all());
$editors = array_values(array_filter(Storage::users()->all(), fn ($u) => $u['role'] === ROLE_EDITOR));
$activeEditors = array_values(array_filter($editors, fn ($u) => ($u['status'] ?? 'active') === 'active'));

$versions = versions_for_video($video['id']);
$pendingVersion = null;
foreach ($versions as $v) {
    if (($v['review_status'] ?? '') === 'pending') {
        $pendingVersion = $v;
    }
}
$project = $projectsById[$video['project_id']] ?? null;
$editor = $usersById[$video['editor_id']] ?? null;
$overdue = is_overdue($video);
$onTime = is_on_time($video);

page_start($video['title'], 'videos');
?>
<p><a href="/admin/videos.php" class="link">← Back to all videos</a></p>

<div class="grid-2">
    <div class="card">
        <div class="card-header">
            <h2><?= e($video['title']) ?></h2>
            <span class="<?= status_badge_class($video['status']) ?>"><?= status_label($video['status']) ?></span>
            <?php if ($overdue): ?><span class="badge badge-overdue">OVERDUE</span><?php endif; ?>
        </div>
        <table class="kv-table">
            <tr><th>Project</th><td><?= e($project['name'] ?? '—') ?></td></tr>
            <tr><th>Editor</th><td><?= e($editor['name'] ?? '—') ?></td></tr>
            <tr><th>Assigned</th><td><?= e(format_dt($video['assigned_at'])) ?></td></tr>
            <tr><th>Deadline</th><td><?= e(format_dt($video['deadline_at'])) ?></td></tr>
            <?php if ($video['status'] === STATUS_FINAL): ?>
            <tr><th>Finalized</th><td><?= e(format_dt($video['finalized_at'])) ?></td></tr>
            <tr><th>Result</th><td><?= $onTime ? '<span class="badge badge-final">Completed On Time</span>' : '<span class="badge badge-overdue">Completed Late</span>' ?></td></tr>
            <?php endif; ?>
            <?php if (!empty($video['video_link'])): ?>
            <tr><th>Link</th><td><a href="<?= e($video['video_link']) ?>" target="_blank" rel="noopener noreferrer"><?= e($video['video_link']) ?></a></td></tr>
            <?php endif; ?>
            <tr><th>Total Versions</th><td><?= count($versions) ?></td></tr>
        </table>

        <details>
            <summary class="btn btn-ghost">Edit Assignment</summary>
            <form method="post" action="/actions/update_video.php" class="form-grid">
                <?= csrf_field() ?>
                <input type="hidden" name="id" value="<?= e($video['id']) ?>">
                <label>Video Title
                    <input type="text" name="title" value="<?= e($video['title']) ?>" required maxlength="200">
                </label>
                <label>Project
                    <select name="project_id" required>
                        <?php foreach ($allProjects as $p): if (($p['status'] ?? '') !== 'active' && $p['id'] !== $video['project_id']) continue; ?>
                            <option value="<?= e($p['id']) ?>" <?= $video['project_id'] === $p['id'] ? 'selected' : '' ?>><?= e($p['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label>Assigned Editor
                    <select name="editor_id" required>
                        <?php foreach ($editors as $ed): if (($ed['status'] ?? '') !== 'active' && $ed['id'] !== $video['editor_id']) continue; ?>
                            <option value="<?= e($ed['id']) ?>" <?= $video['editor_id'] === $ed['id'] ? 'selected' : '' ?>><?= e($ed['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label>Deadline
                    <input type="datetime-local" name="deadline_at" value="<?= e(iso_to_local_input($video['deadline_at'])) ?>" required>
                </label>
                <label>Video/Drive Link
                    <input type="url" name="video_link" value="<?= e($video['video_link'] ?? '') ?>" placeholder="https://...">
                </label>
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">Save Changes</button>
                </div>
            </form>
        </details>
    </div>

    <div class="card">
        <div class="card-header"><h2>Review</h2></div>
        <?php if ($video['status'] === STATUS_IN_REVIEW && $pendingVersion): ?>
            <p>V<?= (int) $pendingVersion['version_number'] ?> submitted <?= e(format_dt($pendingVersion['submitted_at'])) ?> by <?= e($usersById[$pendingVersion['submitted_by']]['name'] ?? '—') ?>.</p>
            <p class="muted">Review the video in WhatsApp, then record the outcome here.</p>
            <form method="post" action="/actions/review_action.php" class="stacked-form">
                <?= csrf_field() ?>
                <input type="hidden" name="video_id" value="<?= e($video['id']) ?>">
                <input type="hidden" name="version_id" value="<?= e($pendingVersion['id']) ?>">
                <label>Feedback / Notes (optional)
                    <textarea name="review_note" rows="3" maxlength="2000"></textarea>
                </label>
                <div class="form-actions">
                    <button type="submit" name="decision" value="approve" class="btn btn-success">Approve / Final</button>
                    <button type="submit" name="decision" value="request_changes" class="btn btn-warn">Request Changes</button>
                </div>
            </form>
        <?php elseif ($video['status'] === STATUS_FINAL): ?>
            <p class="muted">This video is complete. No further review needed.</p>
        <?php else: ?>
            <p class="muted">Waiting on the editor to submit a version.</p>
        <?php endif; ?>
    </div>
</div>

<div class="card">
    <div class="card-header"><h2>Version History</h2></div>
    <?php if (!$versions): ?>
        <p class="muted">No versions submitted yet.</p>
    <?php else: ?>
    <div class="table-scroll">
    <table class="table">
        <thead><tr><th>Version</th><th>Submitted</th><th>By</th><th>Outcome</th><th>Note</th></tr></thead>
        <tbody>
        <?php foreach (array_reverse($versions) as $v): ?>
            <tr>
                <td><strong>V<?= (int) $v['version_number'] ?></strong><?= $video['final_version_id'] === $v['id'] ? ' <span class="badge badge-final">FINAL</span>' : '' ?></td>
                <td><?= e(format_dt($v['submitted_at'])) ?></td>
                <td><?= e($usersById[$v['submitted_by']]['name'] ?? '—') ?></td>
                <td>
                    <?php if ($v['review_status'] === 'pending'): ?><span class="badge badge-review">Awaiting Review</span>
                    <?php elseif ($v['review_status'] === 'approved'): ?><span class="badge badge-final">Approved</span>
                    <?php else: ?><span class="badge badge-changes">Changes Requested</span><?php endif; ?>
                </td>
                <td><?= e($v['review_note'] ?? '') ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
    <?php endif; ?>
</div>
<?php page_end(); ?>
