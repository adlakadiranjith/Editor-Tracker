<?php
declare(strict_types=1);
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/layout.php';

$user = require_role(ROLE_ADMIN);

$users = Storage::users()->all();
usort($users, fn ($a, $b) => strcasecmp($a['name'] ?? '', $b['name'] ?? ''));
$videos = Storage::videos()->all();

$assignedCounts = [];
$completedCounts = [];
foreach ($videos as $v) {
    $eid = $v['editor_id'] ?? null;
    if (!$eid) continue;
    $assignedCounts[$eid] = ($assignedCounts[$eid] ?? 0) + 1;
    if (($v['status'] ?? '') === STATUS_FINAL) {
        $completedCounts[$eid] = ($completedCounts[$eid] ?? 0) + 1;
    }
}

// A newly-generated temporary password to show once, right after creating a user.
$revealPassword = $_SESSION['reveal_password'] ?? null;
unset($_SESSION['reveal_password']);

page_start('Team', 'team');
?>
<?php if ($revealPassword): ?>
<div class="alert alert-success">
    Account created for <strong><?= e($revealPassword['name']) ?></strong> (<?= e($revealPassword['email']) ?>).
    Temporary password: <code><?= e($revealPassword['password']) ?></code> — share this with them securely; it will not be shown again.
</div>
<?php endif; ?>

<div class="card">
    <details<?= (($_GET['new'] ?? '') === '1') ? ' open' : '' ?>>
        <summary class="btn btn-primary">+ Add Team Member</summary>
        <form method="post" action="/actions/create_user.php" class="form-grid">
            <?= csrf_field() ?>
            <label>Name
                <input type="text" name="name" required maxlength="120">
            </label>
            <label>Email
                <input type="email" name="email" required>
            </label>
            <label>Role
                <select name="role" required>
                    <option value="editor">Editor</option>
                    <option value="admin">Admin</option>
                </select>
            </label>
            <label>Temporary Password (leave blank to auto-generate)
                <input type="text" name="password" minlength="8" placeholder="At least 8 characters">
            </label>
            <div class="form-actions">
                <button type="submit" class="btn btn-primary">Create Account</button>
            </div>
        </form>
    </details>
</div>

<div class="card">
    <div class="table-scroll">
    <table class="table">
        <thead><tr><th>Name</th><th>Email</th><th>Role</th><th>Status</th><th>Assigned</th><th>Completed</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($users as $u): ?>
            <tr>
                <td><?= e($u['name']) ?><?= $u['id'] === $user['id'] ? ' <span class="muted">(you)</span>' : '' ?></td>
                <td><?= e($u['email']) ?></td>
                <td><?= e(ucfirst($u['role'])) ?></td>
                <td><span class="badge <?= ($u['status'] ?? '') === 'active' ? 'badge-final' : 'badge-overdue' ?>"><?= e(ucfirst($u['status'] ?? 'active')) ?></span></td>
                <td><?= $assignedCounts[$u['id']] ?? 0 ?></td>
                <td><?= $completedCounts[$u['id']] ?? 0 ?></td>
                <td class="actions-cell">
                    <?php if ($u['id'] !== $user['id']): ?>
                        <form method="post" action="/actions/update_user.php" class="inline-form">
                            <?= csrf_field() ?>
                            <input type="hidden" name="id" value="<?= e($u['id']) ?>">
                            <input type="hidden" name="target_status" value="<?= ($u['status'] ?? '') === 'active' ? 'inactive' : 'active' ?>">
                            <button type="submit" class="link-btn"><?= ($u['status'] ?? '') === 'active' ? 'Deactivate' : 'Activate' ?></button>
                        </form>
                    <?php endif; ?>
                    <form method="post" action="/actions/reset_password.php" class="inline-form" onsubmit="return confirm('Generate a new temporary password for this user?');">
                        <?= csrf_field() ?>
                        <input type="hidden" name="id" value="<?= e($u['id']) ?>">
                        <button type="submit" class="link-btn">Reset Password</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
</div>
<?php page_end(); ?>
