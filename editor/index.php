<?php
declare(strict_types=1);
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/layout.php';

$user = require_role(ROLE_EDITOR);

$myVideos = array_values(array_filter(Storage::videos()->all(), fn ($v) => ($v['editor_id'] ?? '') === $user['id']));
$projectsById = index_by_id(Storage::projects()->all());

$toDo = array_values(array_filter($myVideos, fn ($v) => in_array($v['status'], [STATUS_ASSIGNED, STATUS_CHANGES_REQUESTED], true)));
usort($toDo, fn ($a, $b) => strcmp((string) $a['deadline_at'], (string) $b['deadline_at']));

$inReview = array_values(array_filter($myVideos, fn ($v) => $v['status'] === STATUS_IN_REVIEW));
usort($inReview, fn ($a, $b) => strcmp((string) ($b['assigned_at']), (string) ($a['assigned_at'])));

$completed = array_values(array_filter($myVideos, fn ($v) => $v['status'] === STATUS_FINAL));
usort($completed, fn ($a, $b) => strcmp((string) $b['finalized_at'], (string) $a['finalized_at']));
$recentCompleted = array_slice($completed, 0, 5);

page_start('My Work', 'work');
?>
<?php if (!$myVideos): ?>
    <div class="card"><p class="muted">No videos have been assigned to you yet.</p></div>
<?php endif; ?>

<?php if ($toDo): ?>
<div class="card">
    <div class="card-header"><h2>To Do</h2></div>
    <div class="work-list">
        <?php foreach ($toDo as $v):
            $p = $projectsById[$v['project_id']] ?? null;
            $overdue = is_overdue($v);
            $latest = latest_version($v['id']); ?>
            <div class="work-item <?= $overdue ? 'work-item-overdue' : '' ?>">
                <div class="work-item-main">
                    <strong><?= e($v['title']) ?></strong>
                    <span class="muted"><?= e($p['name'] ?? '—') ?></span>
                    <div>
                        <span class="<?= status_badge_class($v['status']) ?>"><?= status_label($v['status']) ?></span>
                        <?php if ($overdue): ?><span class="badge badge-overdue">OVERDUE</span><?php endif; ?>
                        <span class="muted">Deadline: <?= e(format_dt($v['deadline_at'])) ?></span>
                    </div>
                    <?php if ($latest && $latest['review_status'] === 'changes_requested' && !empty($latest['review_note'])): ?>
                        <p class="feedback-note">Feedback: <?= e($latest['review_note']) ?></p>
                    <?php endif; ?>
                </div>
                <form method="post" action="/actions/submit_version.php" class="work-item-action">
                    <?= csrf_field() ?>
                    <input type="hidden" name="video_id" value="<?= e($v['id']) ?>">
                    <button type="submit" class="btn btn-primary">Submit V<?= next_version_number($v['id']) ?></button>
                </form>
            </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<?php if ($inReview): ?>
<div class="card">
    <div class="card-header"><h2>In Review</h2></div>
    <div class="work-list">
        <?php foreach ($inReview as $v):
            $p = $projectsById[$v['project_id']] ?? null;
            $latest = latest_version($v['id']); ?>
            <div class="work-item">
                <div class="work-item-main">
                    <strong><?= e($v['title']) ?></strong>
                    <span class="muted"><?= e($p['name'] ?? '—') ?></span>
                    <div>V<?= (int) ($latest['version_number'] ?? 0) ?> submitted <?= e(format_dt($latest['submitted_at'] ?? null)) ?> — waiting for review</div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<div class="card">
    <div class="card-header">
        <h2>Recently Completed</h2>
        <a href="/editor/history.php" class="link">Full history →</a>
    </div>
    <?php if (!$recentCompleted): ?>
        <p class="muted">Nothing completed yet.</p>
    <?php else: ?>
        <ul class="activity-list">
            <?php foreach ($recentCompleted as $v):
                $p = $projectsById[$v['project_id']] ?? null;
                $latest = latest_version($v['id']); ?>
                <li><span class="activity-time"><?= e(format_date($v['finalized_at'])) ?></span><?= e($v['title']) ?> — <?= e($p['name'] ?? '—') ?> — Final: V<?= (int) ($latest['version_number'] ?? 0) ?></li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</div>
<?php page_end(); ?>
