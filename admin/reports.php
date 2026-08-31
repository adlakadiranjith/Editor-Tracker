<?php
declare(strict_types=1);
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../includes/reporting.php';

$user = require_role(ROLE_ADMIN);

$view = in_array($_GET['view'] ?? '', ['weekly', 'monthly', 'custom'], true) ? $_GET['view'] : 'weekly';
$weekStart = trimmed('week_start', $_GET);
$month = trimmed('month', $_GET);
$from = trimmed('from', $_GET);
$to = trimmed('to', $_GET);

[$start, $end, $label] = resolve_period($view, $weekStart, $month, $from, $to);
$report = build_report($start, $end);

$projectsById = index_by_id(Storage::projects()->all());
$usersById = index_by_id(Storage::users()->all());

$prevWeek = $start->modify('-7 days')->format('Y-m-d');
$nextWeek = $start->modify('+7 days')->format('Y-m-d');
$prevMonth = $start->modify('-1 month')->format('Y-m');
$nextMonth = $start->modify('+1 month')->format('Y-m');

$exportQs = http_build_query(['view' => $view, 'week_start' => $weekStart, 'month' => $month, 'from' => $from, 'to' => $to]);

page_start('Reports', 'reports');
?>
<div class="tabs">
    <a class="<?= $view === 'weekly' ? 'active' : '' ?>" href="?view=weekly">Weekly</a>
    <a class="<?= $view === 'monthly' ? 'active' : '' ?>" href="?view=monthly">Monthly</a>
    <a class="<?= $view === 'custom' ? 'active' : '' ?>" href="?view=custom">Custom Range</a>
</div>

<div class="card">
    <div class="period-nav">
        <?php if ($view === 'weekly'): ?>
            <a class="btn btn-ghost" href="?view=weekly&week_start=<?= $prevWeek ?>">← Prev Week</a>
            <strong><?= e($label) ?></strong>
            <a class="btn btn-ghost" href="?view=weekly&week_start=<?= $nextWeek ?>">Next Week →</a>
        <?php elseif ($view === 'monthly'): ?>
            <a class="btn btn-ghost" href="?view=monthly&month=<?= $prevMonth ?>">← Prev Month</a>
            <strong><?= e($label) ?></strong>
            <a class="btn btn-ghost" href="?view=monthly&month=<?= $nextMonth ?>">Next Month →</a>
        <?php else: ?>
            <form method="get" class="filter-bar">
                <input type="hidden" name="view" value="custom">
                <label>From <input type="date" name="from" value="<?= e($from) ?>"></label>
                <label>To <input type="date" name="to" value="<?= e($to) ?>"></label>
                <button type="submit" class="btn">Apply</button>
            </form>
        <?php endif; ?>
    </div>
</div>

<div class="stat-grid">
    <div class="stat-card"><span class="stat-value"><?= count($report['assigned']) ?></span><span class="stat-label">Total Assigned</span></div>
    <div class="stat-card"><span class="stat-value"><?= count($report['completed']) ?></span><span class="stat-label">Total Completed</span></div>
    <div class="stat-card"><span class="stat-value"><?= $report['pending'] ?></span><span class="stat-label">Pending</span></div>
    <div class="stat-card stat-warn"><span class="stat-value"><?= $report['overdue'] ?></span><span class="stat-label">Overdue</span></div>
    <div class="stat-card"><span class="stat-value"><?= $report['totalVersions'] ?></span><span class="stat-label">Total Versions</span></div>
    <div class="stat-card"><span class="stat-value"><?= $report['avgVersions'] ?></span><span class="stat-label">Avg Versions/Reel</span></div>
</div>

<div class="grid-2">
    <div class="card">
        <div class="card-header"><h2>Project Breakdown</h2></div>
        <?php if (!$report['projectCounts']): ?>
            <p class="muted">No completed videos in this period.</p>
        <?php else: ?>
            <table class="table">
                <?php foreach ($report['projectCounts'] as $pid => $count): $p = $projectsById[$pid] ?? null; ?>
                    <tr><td><?= e($p['name'] ?? 'Unknown') ?></td><td class="text-right"><?= $count ?> completed</td></tr>
                <?php endforeach; ?>
                <tr class="total-row"><td>TOTAL</td><td class="text-right"><?= count($report['completed']) ?> completed</td></tr>
            </table>
        <?php endif; ?>
    </div>

    <div class="card">
        <div class="card-header"><h2>Completed This Period</h2></div>
        <?php if (!$report['completed']): ?>
            <p class="muted">Nothing completed yet in this period.</p>
        <?php else: ?>
            <ol class="completed-list">
                <?php foreach ($report['completed'] as $v):
                    $p = $projectsById[$v['project_id']] ?? null;
                    $latest = latest_version($v['id']); ?>
                    <li>
                        <a href="/admin/video.php?id=<?= e($v['id']) ?>"><?= e($v['title']) ?></a>
                        <span class="muted"><?= e($p['name'] ?? '—') ?></span>
                        — Final: V<?= (int) ($latest['version_number'] ?? 0) ?>
                    </li>
                <?php endforeach; ?>
            </ol>
        <?php endif; ?>
    </div>
</div>

<div class="card">
    <div class="card-header"><h2>Export</h2></div>
    <div class="quick-actions">
        <a class="btn" href="/actions/export_report.php?<?= $exportQs ?>">Download CSV Report</a>
        <a class="btn" href="/actions/export_backup.php">Download Full Data Backup (JSON)</a>
    </div>
</div>
<?php page_end(); ?>
