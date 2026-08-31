<?php
declare(strict_types=1);
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/layout.php';

$user = require_role(ROLE_ADMIN);

$allProjects = Storage::projects()->all();
$activeProjects = array_values(array_filter($allProjects, fn ($p) => ($p['status'] ?? 'active') === 'active'));
$projectsById = index_by_id($allProjects);
$editors = array_values(array_filter(Storage::users()->all(), fn ($u) => $u['role'] === ROLE_EDITOR));
$activeEditors = array_values(array_filter($editors, fn ($u) => ($u['status'] ?? 'active') === 'active'));
$usersById = index_by_id(Storage::users()->all());

// ---------------------------------------------------------------------
// Filters
// ---------------------------------------------------------------------
$fProject = trimmed('project_id', $_GET);
$fEditor = trimmed('editor_id', $_GET);
$fStatus = trimmed('status', $_GET);
$fOverdueOnly = trimmed('overdue', $_GET) === '1';
$fFrom = trimmed('from', $_GET);
$fTo = trimmed('to', $_GET);
$fSearch = trimmed('q', $_GET);

$videos = Storage::videos()->all();

$filtered = array_values(array_filter($videos, function ($v) use ($fProject, $fEditor, $fStatus, $fOverdueOnly, $fFrom, $fTo, $fSearch) {
    if ($fProject !== '' && ($v['project_id'] ?? '') !== $fProject) return false;
    if ($fEditor !== '' && ($v['editor_id'] ?? '') !== $fEditor) return false;
    if ($fStatus !== '' && ($v['status'] ?? '') !== $fStatus) return false;
    if ($fOverdueOnly && !is_overdue($v)) return false;
    if ($fSearch !== '' && stripos((string) ($v['title'] ?? ''), $fSearch) === false) return false;

    if ($fFrom !== '') {
        $from = parse_dt($fFrom);
        $assigned = parse_dt($v['assigned_at'] ?? null);
        if ($from && $assigned && $assigned < $from) return false;
    }
    if ($fTo !== '') {
        $to = parse_dt($fTo . ' 23:59:59');
        $assigned = parse_dt($v['assigned_at'] ?? null);
        if ($to && $assigned && $assigned > $to) return false;
    }
    return true;
}));

usort($filtered, fn ($a, $b) => strcmp((string) ($b['assigned_at'] ?? ''), (string) ($a['assigned_at'] ?? '')));

// Pagination
$perPage = 25;
$page = max(1, (int) ($_GET['page'] ?? 1));
$total = count($filtered);
$totalPages = max(1, (int) ceil($total / $perPage));
$page = min($page, $totalPages);
$pageItems = array_slice($filtered, ($page - 1) * $perPage, $perPage);

$qs = $_GET;
unset($qs['page']);
$baseQuery = http_build_query($qs);

page_start('Videos', 'videos');
?>
<div class="card">
    <details<?= (($_GET['new'] ?? '') === '1') ? ' open' : '' ?>>
        <summary class="btn btn-primary">+ New Assignment</summary>
        <form method="post" action="/actions/create_video.php" class="form-grid">
            <?= csrf_field() ?>
            <label>Video Title
                <input type="text" name="title" required maxlength="200">
            </label>
            <label>Project
                <select name="project_id" required>
                    <option value="">— Select —</option>
                    <?php foreach ($activeProjects as $p): ?>
                        <option value="<?= e($p['id']) ?>"><?= e($p['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>Assigned Editor
                <select name="editor_id" required>
                    <option value="">— Select —</option>
                    <?php foreach ($activeEditors as $ed): ?>
                        <option value="<?= e($ed['id']) ?>"><?= e($ed['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>Deadline
                <input type="datetime-local" name="deadline_at" required>
            </label>
            <label>Video/Drive Link (optional)
                <input type="url" name="video_link" placeholder="https://...">
            </label>
            <div class="form-actions">
                <button type="submit" class="btn btn-primary">Assign</button>
            </div>
            <?php if (!$activeProjects || !$activeEditors): ?>
                <p class="muted">
                    <?php if (!$activeProjects): ?>You need at least one active project. <a href="/admin/projects.php">Create one →</a><br><?php endif; ?>
                    <?php if (!$activeEditors): ?>You need at least one active editor. <a href="/admin/team.php">Add one →</a><?php endif; ?>
                </p>
            <?php endif; ?>
        </form>
    </details>
</div>

<div class="card">
    <form method="get" class="filter-bar">
        <input type="text" name="q" placeholder="Search title…" value="<?= e($fSearch) ?>">
        <select name="project_id">
            <option value="">All Projects</option>
            <?php foreach ($allProjects as $p): ?>
                <option value="<?= e($p['id']) ?>" <?= $fProject === $p['id'] ? 'selected' : '' ?>><?= e($p['name']) ?></option>
            <?php endforeach; ?>
        </select>
        <select name="editor_id">
            <option value="">All Editors</option>
            <?php foreach ($editors as $ed): ?>
                <option value="<?= e($ed['id']) ?>" <?= $fEditor === $ed['id'] ? 'selected' : '' ?>><?= e($ed['name']) ?></option>
            <?php endforeach; ?>
        </select>
        <select name="status">
            <option value="">All Statuses</option>
            <?php foreach ([STATUS_ASSIGNED, STATUS_IN_REVIEW, STATUS_CHANGES_REQUESTED, STATUS_FINAL] as $s): ?>
                <option value="<?= $s ?>" <?= $fStatus === $s ? 'selected' : '' ?>><?= status_label($s) ?></option>
            <?php endforeach; ?>
        </select>
        <label class="checkbox-label"><input type="checkbox" name="overdue" value="1" <?= $fOverdueOnly ? 'checked' : '' ?>> Overdue only</label>
        <label>From <input type="date" name="from" value="<?= e($fFrom) ?>"></label>
        <label>To <input type="date" name="to" value="<?= e($fTo) ?>"></label>
        <button type="submit" class="btn">Filter</button>
        <a href="/admin/videos.php" class="btn btn-ghost">Reset</a>
    </form>
</div>

<div class="card">
    <p class="muted"><?= $total ?> assignment<?= $total === 1 ? '' : 's' ?></p>
    <div class="table-scroll">
    <table class="table">
        <thead>
        <tr>
            <th>Video</th><th>Project</th><th>Editor</th><th>Assigned</th><th>Deadline</th>
            <th>Version</th><th>Status</th><th>Final Version</th><th></th>
        </tr>
        </thead>
        <tbody>
        <?php if (!$pageItems): ?>
            <tr><td colspan="9" class="muted">No assignments match these filters.</td></tr>
        <?php endif; ?>
        <?php foreach ($pageItems as $v):
            $p = $projectsById[$v['project_id']] ?? null;
            $ed = $usersById[$v['editor_id']] ?? null;
            $latest = latest_version($v['id']);
            $overdue = is_overdue($v);
        ?>
            <tr>
                <td><a href="/admin/video.php?id=<?= e($v['id']) ?>"><?= e($v['title']) ?></a></td>
                <td><?= e($p['name'] ?? '—') ?></td>
                <td><?= e($ed['name'] ?? '—') ?></td>
                <td><?= e(format_date($v['assigned_at'] ?? null)) ?></td>
                <td><?= e(format_dt($v['deadline_at'] ?? null)) ?></td>
                <td><?= $latest ? 'V' . (int) $latest['version_number'] : '—' ?></td>
                <td>
                    <span class="<?= status_badge_class($v['status']) ?>"><?= status_label($v['status']) ?></span>
                    <?php if ($overdue): ?><span class="badge badge-overdue">OVERDUE</span><?php endif; ?>
                </td>
                <td><?= $v['final_version_id'] ? 'V' . (int) ($latest['version_number'] ?? '?') : '—' ?></td>
                <td><a href="/admin/video.php?id=<?= e($v['id']) ?>" class="link">View →</a></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>

    <?php if ($totalPages > 1): ?>
    <div class="pagination">
        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
            <a class="<?= $i === $page ? 'active' : '' ?>" href="?<?= $baseQuery ? $baseQuery . '&' : '' ?>page=<?= $i ?>"><?= $i ?></a>
        <?php endfor; ?>
    </div>
    <?php endif; ?>
</div>
<?php page_end(); ?>
