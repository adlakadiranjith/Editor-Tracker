<?php
declare(strict_types=1);
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/layout.php';

$user = require_role(ROLE_ADMIN);

$videos = Storage::videos()->all();
$projects = Storage::projects()->all();
$versions = Storage::versions()->all();
$users = index_by_id(Storage::users()->all());
$projectsById = index_by_id($projects);

$totalAssigned = count($videos);
$completed = array_values(array_filter($videos, fn ($v) => $v['status'] === STATUS_FINAL));
$totalCompleted = count($completed);
$totalPending = $totalAssigned - $totalCompleted;
$totalOverdue = count(array_filter($videos, 'is_overdue'));
$totalVersions = count($versions);
$avgVersions = $totalCompleted > 0 ? round($totalVersions / $totalCompleted, 2) : 0.0;

// Completed-per-project (all time)
$projectCounts = [];
foreach ($completed as $v) {
    $pid = $v['project_id'] ?? null;
    if ($pid) {
        $projectCounts[$pid] = ($projectCounts[$pid] ?? 0) + 1;
    }
}
arsort($projectCounts);

// Recent activity feed — merge submission, finalization and changes-requested events.
$activity = [];
foreach ($versions as $v) {
    $video = find_by_id($videos, $v['video_id']);
    if (!$video) continue;
    $activity[] = [
        'at' => $v['submitted_at'],
        'text' => 'V' . $v['version_number'] . ' submitted — ' . $video['title'],
    ];
    if (($v['review_status'] ?? '') === 'changes_requested' && !empty($v['reviewed_at'])) {
        $activity[] = [
            'at' => $v['reviewed_at'],
            'text' => 'Changes requested — ' . $video['title'] . ' (V' . $v['version_number'] . ')',
        ];
    }
    if (($v['review_status'] ?? '') === 'approved' && !empty($v['reviewed_at'])) {
        $activity[] = [
            'at' => $v['reviewed_at'],
            'text' => 'V' . $v['version_number'] . ' finalized — ' . $video['title'],
        ];
    }
}
usort($activity, fn ($a, $b) => strcmp((string) $b['at'], (string) $a['at']));
$activity = array_slice($activity, 0, 12);

page_start('Dashboard', 'dashboard');
?>
<div class="stat-grid">
    <div class="stat-card"><span class="stat-value"><?= $totalAssigned ?></span><span class="stat-label">Assigned</span></div>
    <div class="stat-card"><span class="stat-value"><?= $totalCompleted ?></span><span class="stat-label">Completed</span></div>
    <div class="stat-card"><span class="stat-value"><?= $totalPending ?></span><span class="stat-label">Pending</span></div>
    <div class="stat-card stat-warn"><span class="stat-value"><?= $totalOverdue ?></span><span class="stat-label">Overdue</span></div>
    <div class="stat-card"><span class="stat-value"><?= $totalVersions ?></span><span class="stat-label">Versions</span></div>
    <div class="stat-card"><span class="stat-value"><?= $avgVersions ?></span><span class="stat-label">Avg Versions/Reel</span></div>
</div>

<div class="grid-2">
    <div class="card">
        <div class="card-header">
            <h2>Projects</h2>
            <a href="/admin/projects.php" class="link">Manage projects →</a>
        </div>
        <?php if (!$projectCounts): ?>
            <p class="muted">No completed videos yet.</p>
        <?php else: ?>
            <table class="table">
                <?php foreach ($projectCounts as $pid => $count):
                    $p = $projectsById[$pid] ?? null; ?>
                    <tr>
                        <td><?= e($p['name'] ?? 'Unknown project') ?></td>
                        <td class="text-right"><a href="/admin/videos.php?project_id=<?= e($pid) ?>&status=final"><?= $count ?> completed</a></td>
                    </tr>
                <?php endforeach; ?>
            </table>
        <?php endif; ?>
    </div>

    <div class="card">
        <div class="card-header">
            <h2>Recent Activity</h2>
        </div>
        <?php if (!$activity): ?>
            <p class="muted">Nothing has happened yet.</p>
        <?php else: ?>
            <ul class="activity-list">
                <?php foreach ($activity as $a): ?>
                    <li><span class="activity-time"><?= e(format_dt($a['at'])) ?></span><?= e($a['text']) ?></li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h2>Quick Actions</h2>
    </div>
    <div class="quick-actions">
        <a class="btn btn-primary" href="/admin/videos.php?new=1">+ New Assignment</a>
        <a class="btn" href="/admin/projects.php">Manage Projects</a>
        <a class="btn" href="/admin/team.php">Manage Team</a>
        <a class="btn" href="/admin/reports.php">View Reports</a>
    </div>
</div>
<?php page_end(); ?>
