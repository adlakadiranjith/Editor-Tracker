<?php
declare(strict_types=1);
require_once __DIR__ . '/../config.php';

require_role(ROLE_ADMIN);

// A full snapshot of every data file, for the admin to keep off-site.
// Since there is no database, this file *is* the backup. It includes
// hashed (not plaintext) passwords so accounts can be restored — treat
// the downloaded file as sensitive and store it securely.
$backup = [
    'exported_at' => now_iso(),
    'users' => Storage::users()->all(),
    'projects' => Storage::projects()->all(),
    'videos' => Storage::videos()->all(),
    'versions' => Storage::versions()->all(),
];

$json = json_encode($backup, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
$filename = 'tracker_backup_' . (new DateTimeImmutable('now'))->format('Y-m-d_His') . '.json';

header('Content-Type: application/json; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('X-Content-Type-Options: nosniff');
echo $json;
exit;
