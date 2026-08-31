<?php
declare(strict_types=1);
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/layout.php';

$user = require_role(ROLE_ADMIN);

$projects = Storage::projects()->all();
usort($projects, fn ($a, $b) => strcasecmp($a['name'] ?? '', $b['name'] ?? ''));
$videos = Storage::videos()->all();

$videoCounts = [];
foreach ($videos as $v) {
    $pid = $v['project_id'] ?? null;
    if ($pid) {
        $videoCounts[$pid] = ($videoCounts[$pid] ?? 0) + 1;
    }
}

$editId = trimmed('edit', $_GET);
$editing = $editId !== '' ? find_by_id($projects, $editId) : null;

page_start('Projects', 'projects');
?>
<div class="card">
    <details<?= ($editing || ($_GET['new'] ?? '') === '1') ? ' open' : '' ?>>
        <summary class="btn btn-primary"><?= $editing ? 'Edit Project' : '+ New Project' ?></summary>
        <form method="post" action="/actions/<?= $editing ? 'update_project.php' : 'create_project.php' ?>" class="form-grid">
            <?= csrf_field() ?>
            <?php if ($editing): ?><input type="hidden" name="id" value="<?= e($editing['id']) ?>"><?php endif; ?>
            <label>Project Name
                <input type="text" name="name" value="<?= e($editing['name'] ?? '') ?>" required maxlength="120">
            </label>
            <label>Description (optional)
                <input type="text" name="description" value="<?= e($editing['description'] ?? '') ?>" maxlength="500">
            </label>
            <?php if ($editing): ?>
            <label>Status
                <select name="status">
                    <option value="active" <?= ($editing['status'] ?? '') === 'active' ? 'selected' : '' ?>>Active</option>
                    <option value="archived" <?= ($editing['status'] ?? '') === 'archived' ? 'selected' : '' ?>>Archived</option>
                </select>
            </label>
            <?php endif; ?>
            <div class="form-actions">
                <button type="submit" class="btn btn-primary"><?= $editing ? 'Save Changes' : 'Create Project' ?></button>
                <?php if ($editing): ?><a href="/admin/projects.php" class="btn btn-ghost">Cancel</a><?php endif; ?>
            </div>
        </form>
    </details>
</div>

<div class="card">
    <div class="table-scroll">
    <table class="table">
        <thead><tr><th>Project</th><th>Description</th><th>Status</th><th>Videos</th><th></th></tr></thead>
        <tbody>
        <?php if (!$projects): ?>
            <tr><td colspan="5" class="muted">No projects yet. Create your first one above.</td></tr>
        <?php endif; ?>
        <?php foreach ($projects as $p): ?>
            <tr>
                <td><?= e($p['name']) ?></td>
                <td><?= e($p['description'] ?? '') ?></td>
                <td><span class="badge <?= ($p['status'] ?? '') === 'active' ? 'badge-final' : '' ?>"><?= e(ucfirst($p['status'] ?? 'active')) ?></span></td>
                <td><a href="/admin/videos.php?project_id=<?= e($p['id']) ?>"><?= $videoCounts[$p['id']] ?? 0 ?></a></td>
                <td>
                    <a href="/admin/projects.php?edit=<?= e($p['id']) ?>" class="link">Edit</a>
                    <?php if (($p['status'] ?? '') === 'active'): ?>
                        <form method="post" action="/actions/archive_project.php" class="inline-form" onsubmit="return confirm('Archive this project? It will be hidden from new assignments but existing videos are kept.');">
                            <?= csrf_field() ?>
                            <input type="hidden" name="id" value="<?= e($p['id']) ?>">
                            <input type="hidden" name="target_status" value="archived">
                            <button type="submit" class="link-btn">Archive</button>
                        </form>
                    <?php else: ?>
                        <form method="post" action="/actions/archive_project.php" class="inline-form">
                            <?= csrf_field() ?>
                            <input type="hidden" name="id" value="<?= e($p['id']) ?>">
                            <input type="hidden" name="target_status" value="active">
                            <button type="submit" class="link-btn">Unarchive</button>
                        </form>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
</div>
<?php page_end(); ?>
