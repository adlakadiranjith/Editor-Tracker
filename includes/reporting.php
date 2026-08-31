<?php
/**
 * Shared period-calculation logic used by admin/reports.php and
 * actions/export_report.php, so the on-screen numbers and the CSV export
 * can never drift apart.
 */

declare(strict_types=1);

function monday_of(DateTimeImmutable $d): DateTimeImmutable
{
    $dow = (int) $d->format('N'); // 1 (Mon) .. 7 (Sun)
    return $d->modify('-' . ($dow - 1) . ' days')->setTime(0, 0, 0);
}

/** @return array{0: DateTimeImmutable, 1: DateTimeImmutable, 2: string} [start, end, label] */
function resolve_period(string $view, string $weekStart, string $month, string $from, string $to): array
{
    $now = new DateTimeImmutable('now');

    if ($view === 'monthly') {
        $ym = $month !== '' ? $month : $now->format('Y-m');
        try {
            $start = (new DateTimeImmutable($ym . '-01'))->setTime(0, 0, 0);
        } catch (Exception $e) {
            $start = $now->modify('first day of this month')->setTime(0, 0, 0);
        }
        $end = $start->modify('last day of this month')->setTime(23, 59, 59);
        return [$start, $end, $start->format('F Y')];
    }

    if ($view === 'custom') {
        try {
            $start = $from !== '' ? (new DateTimeImmutable($from))->setTime(0, 0, 0) : monday_of($now);
        } catch (Exception $e) {
            $start = monday_of($now);
        }
        try {
            $end = $to !== '' ? (new DateTimeImmutable($to))->setTime(23, 59, 59) : $now->setTime(23, 59, 59);
        } catch (Exception $e) {
            $end = $now->setTime(23, 59, 59);
        }
        if ($end < $start) {
            [$start, $end] = [$end, $start];
        }
        return [$start, $end, $start->format('d M Y') . ' – ' . $end->format('d M Y')];
    }

    // weekly (default)
    try {
        $anchor = $weekStart !== '' ? new DateTimeImmutable($weekStart) : $now;
    } catch (Exception $e) {
        $anchor = $now;
    }
    $start = monday_of($anchor);
    $end = $start->modify('+6 days')->setTime(23, 59, 59);
    return [$start, $end, $start->format('d M') . ' – ' . $end->format('d M Y')];
}

/**
 * @return array{
 *   start: DateTimeImmutable, end: DateTimeImmutable, label: string,
 *   assigned: array, completed: array, pending: int, overdue: int,
 *   total_versions: int, avg_versions: float, project_counts: array<string,int>
 * }
 */
function build_report(DateTimeImmutable $start, DateTimeImmutable $end): array
{
    $videos = Storage::videos()->all();
    $versions = Storage::versions()->all();

    $inRange = fn (?string $iso) => ($dt = parse_dt($iso)) && $dt >= $start && $dt <= $end;

    $assigned = array_values(array_filter($videos, fn ($v) => $inRange($v['assigned_at'] ?? null)));
    $completed = array_values(array_filter($videos, fn ($v) => ($v['status'] ?? '') === STATUS_FINAL && $inRange($v['finalized_at'] ?? null)));
    $pending = count(array_filter($assigned, fn ($v) => ($v['status'] ?? '') !== STATUS_FINAL));
    $overdue = count(array_filter($assigned, 'is_overdue'));
    $totalVersions = count(array_filter($versions, fn ($ver) => $inRange($ver['submitted_at'] ?? null)));

    $versionsOfCompleted = 0;
    $projectCounts = [];
    foreach ($completed as $v) {
        $versionsOfCompleted += count(versions_for_video($v['id']));
        $pid = $v['project_id'] ?? null;
        if ($pid) {
            $projectCounts[$pid] = ($projectCounts[$pid] ?? 0) + 1;
        }
    }
    $avgVersions = count($completed) > 0 ? round($versionsOfCompleted / count($completed), 2) : 0.0;
    arsort($projectCounts);

    return compact('start', 'end', 'assigned', 'completed', 'pending', 'overdue', 'totalVersions', 'avgVersions', 'projectCounts');
}
