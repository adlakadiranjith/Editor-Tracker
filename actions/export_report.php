<?php
declare(strict_types=1);
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/reporting.php';

require_role(ROLE_ADMIN);

$view = in_array($_GET['view'] ?? '', ['weekly', 'monthly', 'custom'], true) ? $_GET['view'] : 'weekly';
$weekStart = trimmed('week_start', $_GET);
$month = trimmed('month', $_GET);
$from = trimmed('from', $_GET);
$to = trimmed('to', $_GET);

[$start, $end, $label] = resolve_period($view, $weekStart, $month, $from, $to);
$report = build_report($start, $end);

$projectsById = index_by_id(Storage::projects()->all());

$filename = 'report_' . $start->format('Y-m-d') . '_to_' . $end->format('Y-m-d') . '.csv';

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('X-Content-Type-Options: nosniff');

$out = fopen('php://output', 'w');
// Escape char is pinned explicitly (PHP 8.4+ deprecates the implicit default).
$row = fn (array $fields) => fputcsv($out, $fields, ',', '"', '\\');

$row(['Production Tracker Report']);
$row(['Period', $label]);
$row([]);
$row(['Metric', 'Value']);
$row(['Total Assigned', count($report['assigned'])]);
$row(['Total Completed', count($report['completed'])]);
$row(['Pending', $report['pending']]);
$row(['Overdue', $report['overdue']]);
$row(['Total Versions', $report['totalVersions']]);
$row(['Avg Versions / Completed Reel', $report['avgVersions']]);
$row([]);

$row(['Project Breakdown']);
$row(['Project', 'Completed']);
foreach ($report['projectCounts'] as $pid => $count) {
    $p = $projectsById[$pid] ?? null;
    $row([$p['name'] ?? 'Unknown', $count]);
}
$row([]);

$row(['Completed Videos']);
$row(['Title', 'Project', 'Final Version', 'Assigned', 'Deadline', 'Finalized', 'On Time?']);
foreach ($report['completed'] as $v) {
    $p = $projectsById[$v['project_id']] ?? null;
    $latest = latest_version($v['id']);
    $onTime = is_on_time($v);
    $row([
        $v['title'],
        $p['name'] ?? 'Unknown',
        'V' . (int) ($latest['version_number'] ?? 0),
        format_dt($v['assigned_at']),
        format_dt($v['deadline_at']),
        format_dt($v['finalized_at']),
        $onTime === null ? '' : ($onTime ? 'On Time' : 'Late'),
    ]);
}

fclose($out);
exit;
