<?php
/**
 * Shared page chrome (nav + flash messages) so every page looks consistent
 * without a templating engine. Call page_start() right after computing
 * $user = require_role(...), then page_end() at the bottom of the file.
 */

declare(strict_types=1);

function page_start(string $title, string $active = ''): void
{
    $user = current_user();
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($title) ?> · Production Tracker</title>
<link rel="stylesheet" href="/assets/style.css">
</head>
<body>
<header class="topbar">
    <div class="topbar-inner">
        <a class="brand" href="<?= $user && $user['role'] === ROLE_ADMIN ? '/admin/' : '/editor/' ?>">🎬 Production Tracker</a>
        <?php if ($user): ?>
        <nav class="nav">
            <?php if ($user['role'] === ROLE_ADMIN): ?>
                <a href="/admin/index.php" class="<?= $active === 'dashboard' ? 'active' : '' ?>">Dashboard</a>
                <a href="/admin/videos.php" class="<?= $active === 'videos' ? 'active' : '' ?>">Videos</a>
                <a href="/admin/projects.php" class="<?= $active === 'projects' ? 'active' : '' ?>">Projects</a>
                <a href="/admin/team.php" class="<?= $active === 'team' ? 'active' : '' ?>">Team</a>
                <a href="/admin/reports.php" class="<?= $active === 'reports' ? 'active' : '' ?>">Reports</a>
            <?php else: ?>
                <a href="/editor/index.php" class="<?= $active === 'work' ? 'active' : '' ?>">My Work</a>
                <a href="/editor/history.php" class="<?= $active === 'history' ? 'active' : '' ?>">History</a>
            <?php endif; ?>
        </nav>
        <div class="nav-user">
            <span class="nav-user-name"><?= e($user['name']) ?></span>
            <a href="/account.php" class="<?= $active === 'account' ? 'active' : '' ?>">Account</a>
            <form method="post" action="/logout.php" class="inline-form">
                <?= csrf_field() ?>
                <button type="submit" class="link-btn">Logout</button>
            </form>
        </div>
        <?php endif; ?>
    </div>
</header>
<main class="container">
    <?php $flash = get_flash(); if ($flash): ?>
        <div class="alert alert-<?= e($flash['type']) ?>"><?= e($flash['text']) ?></div>
    <?php endif; ?>
    <h1 class="page-title"><?= e($title) ?></h1>
    <?php
}

function page_end(): void
{
    ?>
</main>
<footer class="footer">
    <p>Production Tracker — internal tool. WhatsApp remains the video-sharing &amp; communication channel.</p>
</footer>
<script src="/assets/app.js"></script>
</body>
</html>
<?php
}
