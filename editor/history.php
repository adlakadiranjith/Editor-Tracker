<?php
declare(strict_types=1);
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/layout.php';

$user = require_role(ROLE_EDITOR);

$myVideos = array_values(array_filter(Storage::videos()->all(), fn ($v) => ($v['editor_id'] ?? '') === $user['id']));
usort($myVideos, fn ($a, $b) => strcmp((string) $b['assigned_at'], (string) $a['assigned_at']));
$projectsById = index_by_id(Storage::projects()->all());

page_start('History', 'history');
?>
<div class="card">
    <p class="muted"><?= count($myVideos) ?> assignment<?= count($myVideos) === 1 ? '' : 's' ?> total</p>
    <?php if (!$myVideos): ?>
        <p class="muted">No assignments yet.</p>
    <?php endif; ?>
    <?php foreach ($myVideos as $v):
        $p = $projectsById[$v['project_id']] ?? null;
        $versions = versions_for_video($v['id']);
        $overdue = is_overdue($v);
    ?>
    <details class="history-item">
        <summary>
            <strong><?= e($v['title']) ?></strong>
            <span class="muted"><?= e($p['name'] ?? '—') ?></span>
            <span class="<?= status_badge_class($v['status']) ?>"><?= status_label($v['status']) ?></span>
            <?php if ($overdue): ?><span class="badge badge-overdue">OVERDUE</span><?php endif; ?>
        </summary>
        <table class="kv-table">
            <tr><th>Assigned</th><td><?= e(format_dt($v['assigned_at'])) ?></td></tr>
            <tr><th>Deadline</th><td><?= e(format_dt($v['deadline_at'])) ?></td></tr>
            <?php if ($v['status'] === STATUS_FINAL): ?>
            <tr><th>Finalized</th><td><?= e(format_dt($v['finalized_at'])) ?></td></tr>
            <?php endif; ?>
        </table>
        <table class="table">
            <thead><tr><th>Version</th><th>Submitted</th><th>Outcome</th><th>Feedback</th></tr></thead>
            <tbody>
            <?php foreach ($versions as $ver): ?>
                <tr>
                    <td>V<?= (int) $ver['version_number'] ?></td>
                    <td><?= e(format_dt($ver['submitted_at'])) ?></td>
                    <td>
                        <?php if ($ver['review_status'] === 'pending'): ?><span class="badge badge-review">Awaiting Review</span>
                        <?php elseif ($ver['review_status'] === 'approved'): ?><span class="badge badge-final">Approved</span>
                        <?php else: ?><span class="badge badge-changes">Changes Requested</span><?php endif; ?>
                    </td>
                    <td><?= e($ver['review_note'] ?? '') ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </details>
    <?php endforeach; ?>
</div>
<?php page_end(); ?>
